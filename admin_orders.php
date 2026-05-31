<?php
ob_start();
session_start();
include 'db.php';
include 'mail_sender.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้องหรือหมดเวลาเซสชัน (Invalid CSRF Token)']);
            exit();
        }
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
}

// ฟังก์ชันสำหรับขอเลขสถิติออเดอร์ในแต่ละสถานะล่าสุดแบบเรียลไทม์
function get_stats_counts($conn) {
    return [
        'all' => intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders"))['count'] ?? 0),
        'pending' => intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status='pending'"))['count'] ?? 0),
        'shipping' => intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status='shipping'"))['count'] ?? 0),
        'completed' => intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status='completed'"))['count'] ?? 0),
        'cancelled' => intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status='cancelled'"))['count'] ?? 0),
    ];
}

// --- Logic: อัปวสถานะ (Anti-F5 Fixed) ---
if (isset($_POST['update_status'])) {
    $oid = intval($_POST['order_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // ดึงสถานะและค่าแต้มของออเดอร์เดิมก่อนทำการอัปเดต
    $order_q = mysqli_query($conn, "SELECT status, user_id, points_earned, points_spent FROM orders WHERE id='$oid'");
    $old_order = mysqli_fetch_assoc($order_q);
    
    if ($old_order) {
        $old_status = $old_order['status'];
        $user_id = $old_order['user_id'];
        $points_earned = intval($old_order['points_earned']);
        $points_spent = intval($old_order['points_spent']);

        // 1. ถ้าปรับสถานะเป็น completed (และเดิมไม่ใช่ completed)
        if ($status == 'completed' && $old_status != 'completed') {
            if ($points_earned > 0) {
                mysqli_query($conn, "UPDATE users SET points = points + $points_earned WHERE id='$user_id'");
                mysqli_query($conn, "INSERT INTO point_history (user_id, points, description) VALUES ('$user_id', '$points_earned', 'ได้รับคะแนนสะสมจากคำสั่งซื้อสำเร็จ #$oid')");
            }
        }
        
        // 2. ถ้าเปลี่ยนสถานะจาก completed เป็นอย่างอื่น
        if ($old_status == 'completed' && $status != 'completed') {
            if ($points_earned > 0) {
                mysqli_query($conn, "UPDATE users SET points = GREATEST(0, points - $points_earned) WHERE id='$user_id'");
                mysqli_query($conn, "INSERT INTO point_history (user_id, points, description) VALUES ('$user_id', '-$points_earned', 'คะแนนถูกหักคืนเนื่องจากสถานะออเดอร์ #$oid ถูกปรับเปลี่ยนจากสำเร็จเป็นสถานะอื่น')");
            }
        }

        // 3. คืนสต็อกและคืนแต้มที่ใช้ไปกรณีที่ยกเลิกออเดอร์
        if ($status == 'cancelled' && $old_status != 'cancelled') {
            // คืนแต้มสะสมที่เคยใช้ไป
            if ($points_spent > 0) {
                mysqli_query($conn, "UPDATE users SET points = points + $points_spent WHERE id='$user_id'");
                mysqli_query($conn, "INSERT INTO point_history (user_id, points, description) VALUES ('$user_id', '$points_spent', 'ได้รับคืนคะแนนสะสมจากการยกเลิกคำสั่งซื้อ #$oid')");
            }
            
            $items = mysqli_query($conn, "SELECT product_id, quantity FROM order_items WHERE order_id='$oid'");
            while ($item = mysqli_fetch_assoc($items)) {
                $pid = $item['product_id'];
                $qty = $item['quantity'];
                
                // 1. คืนสต็อกลงในล็อตล่าสุดของสินค้าตัวนั้น
                $lot_q = mysqli_query($conn, "SELECT id FROM product_lots WHERE product_id='$pid' ORDER BY imported_at DESC LIMIT 1");
                if ($lot_q && mysqli_num_rows($lot_q) > 0) {
                    $lot = mysqli_fetch_assoc($lot_q);
                    $lot_id = $lot['id'];
                    mysqli_query($conn, "UPDATE product_lots SET stock = stock + $qty WHERE id='$lot_id'");
                } else {
                    mysqli_query($conn, "INSERT INTO product_lots (product_id, lot_number, import_cost, price, stock, imported_at) VALUES ('$pid', 'RETURNED', 0, 0, $qty, NOW())");
                }
                
                // 2. ซิงค์ตารางผลิตภัณฑ์หลัก (เหมือนฟังก์ชัน sync_product_stock ใน admin.php)
                $q_stock = mysqli_query($conn, "SELECT SUM(stock) as total_stock FROM product_lots WHERE product_id='$pid' AND stock > 0");
                $tot = mysqli_fetch_assoc($q_stock)['total_stock'] ?? 0;
                
                $q_price = mysqli_query($conn, "SELECT price FROM product_lots WHERE product_id='$pid' AND stock > 0 ORDER BY imported_at ASC LIMIT 1");
                $r_price = mysqli_fetch_assoc($q_price);
                
                if ($tot > 0 && $r_price) {
                    $price = $r_price['price'];
                    mysqli_query($conn, "UPDATE products SET stock='$tot', price='$price' WHERE id='$pid'");
                } else {
                    mysqli_query($conn, "UPDATE products SET stock=0 WHERE id='$pid'");
                }
                
                // 3. คืนสต็อกในระบบ Flash Sale หากแคมเปญยังไม่สิ้นสุด
                mysqli_query($conn, "UPDATE flash_sales SET flash_sold = GREATEST(0, flash_sold - $qty) WHERE product_id = '$pid' AND NOW() < end_time");
            }
        }
    }
    mysqli_query($conn, "UPDATE orders SET status = '$status' WHERE id = '$oid'");
    log_admin_action($conn, 'อัปเดตสถานะออเดอร์', "เปลี่ยนสถานะออเดอร์ #$oid เป็น $status");
    
    // Insert user notification
    $order_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id, id FROM orders WHERE id = '$oid'"));
    if ($order_data) {
        $uid = $order_data['user_id'];
        $ono = $order_data['id'];
        $status_th = '';
        if ($status == 'pending') $status_th = 'รอการตรวจสอบชำระเงิน';
        elseif ($status == 'shipping') $status_th = 'กำลังจัดส่งสินค้า';
        elseif ($status == 'completed') $status_th = 'สำเร็จแล้ว';
        elseif ($status == 'cancelled') $status_th = 'ถูกยกเลิกแล้ว';
        
        $title = "อัปเดตสถานะคำสั่งซื้อ #$ono";
        $message = "คำสั่งซื้อหมายเลข #$ono ของคุณเปลี่ยนสถานะเป็น: $status_th";
        $url = "my_orders.php";
        mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, url, is_read, is_admin) VALUES ('$uid', '$title', '$message', '$url', 0, 0)");
    }

    send_order_email($conn, $oid);
    if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
        ob_end_clean();
        echo json_encode([
            'status' => 'success', 
            'message' => 'อัปเดตสถานะเรียบร้อย',
            'stats' => get_stats_counts($conn)
        ]);
        exit();
    }
    $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'อัปเดตสถานะเรียบร้อย', 'icon'=>'success'];
    header("Location: admin_orders.php"); exit();
}

// --- Logic: บันทึกเลขพัสดุ ---
if (isset($_POST['save_tracking'])) {
    $oid = intval($_POST['order_id']);
    $track = mysqli_real_escape_string($conn, $_POST['tracking_no']);
    $carrier = mysqli_real_escape_string($conn, $_POST['shipping_carrier'] ?? 'other');
    
    // ตรวจสอบสถานะเดิมก่อนเปลี่ยนสถานะเป็น shipping
    $order_q = mysqli_query($conn, "SELECT status, user_id, points_earned FROM orders WHERE id='$oid'");
    $old_order = mysqli_fetch_assoc($order_q);
    if ($old_order && $old_order['status'] == 'completed') {
        $points_earned = intval($old_order['points_earned']);
        $user_id = $old_order['user_id'];
        if ($points_earned > 0) {
            mysqli_query($conn, "UPDATE users SET points = GREATEST(0, points - $points_earned) WHERE id='$user_id'");
            mysqli_query($conn, "INSERT INTO point_history (user_id, points, description) VALUES ('$user_id', '-$points_earned', 'คะแนนถูกหักคืนเนื่องจากสถานะออเดอร์ #$oid ถูกปรับเปลี่ยนจากสำเร็จเป็นกำลังจัดส่ง')");
        }
    }
    
    mysqli_query($conn, "UPDATE orders SET tracking_no = '$track', shipping_carrier = '$carrier', status = 'shipping' WHERE id = '$oid'");
    log_admin_action($conn, 'บันทึกเลขพัสดุ', "บันทึกเลขพัสดุ $track ขนส่ง $carrier สำหรับออเดอร์ #$oid");
    
    // Insert user notification for tracking number
    $order_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id, id FROM orders WHERE id = '$oid'"));
    if ($order_data) {
        $uid = $order_data['user_id'];
        $ono = $order_data['id'];
        $carrier_label = match($carrier) {
            'thailandpost' => 'ไปรษณีย์ไทย',
            'kerry', 'kex' => 'KEX Express',
            'flash' => 'Flash Express',
            'jnt' => 'J&T Express',
            default => 'บริการขนส่งหลัก'
        };
        $title = "คำสั่งซื้อ #$ono ถูกจัดส่งแล้ว";
        $message = "คำสั่งซื้อหมายเลข #$ono ของคุณได้รับการจัดส่งแล้วโดย $carrier_label! เลขพัสดุของคุณคือ: $track";
        $url = "my_orders.php";
        mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, url, is_read, is_admin) VALUES ('$uid', '$title', '$message', '$url', 0, 0)");
    }

    send_order_email($conn, $oid);
    if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
        ob_end_clean();
        echo json_encode([
            'status' => 'success', 
            'message' => 'บันทึกเลขพัสดุเรียบร้อย',
            'carrier' => $carrier,
            'stats' => get_stats_counts($conn)
        ]);
        exit();
    }
    $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'บันทึกเลขพัสดุ', 'icon'=>'success'];
    header("Location: admin_orders.php"); exit();
}

// --- Logic: บันทึกหมายเหตุ ---
if (isset($_POST['save_note'])) {
    $oid = intval($_POST['order_id']);
    $note = mysqli_real_escape_string($conn, $_POST['admin_note']);
    mysqli_query($conn, "UPDATE orders SET admin_note = '$note' WHERE id = '$oid'");
    log_admin_action($conn, 'บันทึกหมายเหตุ', "บันทึกหมายเหตุสำหรับออเดอร์ #$oid: $note");
    if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
        ob_end_clean();
        echo json_encode([
            'status' => 'success', 
            'message' => 'บันทึกหมายเหตุเรียบร้อย',
            'note' => $note
        ]);
        exit();
    }
    $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'บันทึกหมายเหตุเรียบร้อย', 'icon'=>'success'];
    header("Location: admin_orders.php"); exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการออเดอร์ | Por Mae Bet Taled Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit'; background: #f8f9fa; }
        .order-card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 20px; background: white; }
        /* ปรับเสเน‰นขอบในมือถือ */
        @media (max-width: 767px) {
            .border-end-md { border-right: none !important; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px; }
            .border-top-md { border-top: 1px solid #eee; padding-top: 15px; }
        }
        @media (min-width: 768px) {
            .border-end-md { border-right: 1px solid #eee; }
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 p-0 border-end bg-white">
            <button class="btn btn-light w-100 d-md-none border-bottom p-3 fw-bold text-primary text-start" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
                <i class="bi bi-list me-2"></i> เมนูจัดการ
            </button>
            <div class="collapse d-md-block" id="sidebarMenu">
                <div style="min-height: 100vh;">
                    <?php include 'admin_sidebar.php'; ?>
                </div>
            </div>
        </div>

        <div class="col-md-10 p-4">
            <h3 class="fw-bold mb-4">จัดการคำสั่งซื้อ</h3>

            <?php 
            // 1. คำนวณตัวเลขสถิติออเดอร์ (ไม่รวมเงื่อนไขการค้นหาเพื่อดูภาพรวมระบบตลอดเวลา)
            $count_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders"))['count'] ?? 0;
            $count_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status='pending'"))['count'] ?? 0;
            $count_shipping = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status='shipping'"))['count'] ?? 0;
            $count_completed = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status='completed'"))['count'] ?? 0;
            $count_cancelled = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status='cancelled'"))['count'] ?? 0;

            // 2. ดึงค่าตัวกรองและการค้นหาจาก GET
            $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
            $status_filter = isset($_GET['status_filter']) ? mysqli_real_escape_string($conn, trim($_GET['status_filter'])) : '';

            $where_clauses = [];
            if ($search !== '') {
                if (is_numeric($search)) {
                    $where_clauses[] = "(o.id = '$search' OR u.fullname LIKE '%$search%' OR o.address LIKE '%$search%')";
                } else {
                    $where_clauses[] = "(u.fullname LIKE '%$search%' OR o.address LIKE '%$search%')";
                }
            }
            if ($status_filter !== '') {
                $where_clauses[] = "o.status = '$status_filter'";
            }

            $where_sql = "";
            if (count($where_clauses) > 0) {
                $where_sql = "WHERE " . implode(" AND ", $where_clauses);
            }

            // 3. ดึงรายการออเดอร์ตามเงื่อนไขค้นหา
            $res = mysqli_query($conn, "SELECT o.*, u.fullname FROM orders o LEFT JOIN users u ON o.user_id = u.id $where_sql ORDER BY o.id DESC");
            ?>

            <!-- Stats Dashboard Cards -->
            <div class="row g-3 mb-4 row-cols-2 row-cols-sm-3 row-cols-md-5">
                <div class="col">
                    <div class="card border-0 shadow-sm rounded-4 bg-white p-3 text-center h-100">
                        <div class="text-muted small mb-1 fw-bold">📦 ทั้งหมด</div>
                        <h4 class="fw-bold text-dark mb-0" id="stat-all"><?= number_format($count_all) ?></h4>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-0 shadow-sm rounded-4 p-3 text-center h-100" style="background-color: #fffbeb; border: 1px solid #fef3c7; border-left: 4px solid #ffc107 !important;">
                        <div class="text-warning small mb-1 fw-bold">⏳ รอตรวจ</div>
                        <h4 class="fw-bold text-warning mb-0" id="stat-pending"><?= number_format($count_pending) ?></h4>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-0 shadow-sm rounded-4 p-3 text-center h-100" style="background-color: #f0f9ff; border: 1px solid #e0f2fe; border-left: 4px solid #0ea5e9 !important;">
                        <div class="text-info small mb-1 fw-bold">🚚 ส่งแล้ว</div>
                        <h4 class="fw-bold text-info mb-0" id="stat-shipping"><?= number_format($count_shipping) ?></h4>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-0 shadow-sm rounded-4 p-3 text-center h-100" style="background-color: #f0fdf4; border: 1px solid #dcfce7; border-left: 4px solid #16a34a !important;">
                        <div class="text-success small mb-1 fw-bold">✅ สำเร็จ</div>
                        <h4 class="fw-bold text-success mb-0" id="stat-completed"><?= number_format($count_completed) ?></h4>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-0 shadow-sm rounded-4 p-3 text-center h-100" style="background-color: #f9fafb; border: 1px solid #f3f4f6; border-left: 4px solid #9ca3af !important;">
                        <div class="text-secondary small mb-1 fw-bold">❌ ยกเลิก</div>
                        <h4 class="fw-bold text-secondary mb-0" id="stat-cancelled"><?= number_format($count_cancelled) ?></h4>
                    </div>
                </div>
            </div>

            <!-- Search & Filter Controls -->
            <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-5 col-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control bg-light border-start-0" placeholder="รหัสออเดอร์, ชื่อลูกค้า, หรือที่อยู่..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-4 col-12">
                        <div class="d-flex align-items-center gap-2">
                            <span class="small text-muted fw-bold text-nowrap">สถานะ:</span>
                            <select name="status_filter" class="form-select form-select-sm bg-light" onchange="this.form.submit()">
                                <option value="" <?= ($status_filter == '') ? 'selected' : '' ?>>ทั้งหมด</option>
                                <option value="pending" <?= ($status_filter == 'pending') ? 'selected' : '' ?>>รอตรวจสอบ (Pending)</option>
                                <option value="shipping" <?= ($status_filter == 'shipping') ? 'selected' : '' ?>>กำลังจัดส่ง (Shipping)</option>
                                <option value="completed" <?= ($status_filter == 'completed') ? 'selected' : '' ?>>สำเร็จแล้ว (Completed)</option>
                                <option value="cancelled" <?= ($status_filter == 'cancelled') ? 'selected' : '' ?>>ยกเลิกแล้ว (Cancelled)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3 col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100 rounded-pill"><i class="bi bi-funnel-fill me-1"></i>กรอง</button>
                        <?php if ($search !== '' || $status_filter !== ''): ?>
                            <a href="admin_orders.php" class="btn btn-sm btn-outline-secondary w-100 rounded-pill"><i class="bi bi-x-circle me-1"></i>ล้าง</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if (mysqli_num_rows($res) > 0): ?>
                <?php 
                while ($row = mysqli_fetch_assoc($res)): 
                    $oid = $row['id']; 
                    $st = $row['status'];
                ?>
            <div class="order-card p-4">
                <div class="row align-items-center">
                    <div class="col-12 col-md-3 border-end-md">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <h5 class="fw-bold m-0">#<?= str_pad($oid, 5, '0', STR_PAD_LEFT) ?></h5>
                            <div class="d-flex gap-1">
                                <a href="print_invoice.php?id=<?= $oid ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-circle d-flex align-items-center justify-content-center" style="width:31px; height:31px;" title="พิมพ์ใบเสร็จ"><i class="bi bi-file-earmark-text"></i></a>
                                <a href="admin_print_label.php?id=<?= $oid ?>" target="_blank" class="btn btn-sm btn-dark rounded-circle d-flex align-items-center justify-content-center" style="width:31px; height:31px;" title="พิมพ์ใบปะหน้า"><i class="bi bi-printer"></i></a>
                            </div>
                        </div>
                        <div class="small text-muted"><?= date('d/m/Y H:i', strtotime($row['order_date'])) ?></div>
                        <div class="fw-bold text-primary mt-1"><?= htmlspecialchars($row['fullname'] ?? 'ผู้ใช้งานถูกลบ/ไม่พบข้อมูล') ?></div>
                        
                        <div id="note-container-<?= $oid ?>">
                            <?php if(!empty($row['admin_note'])): ?>
                                <div class="alert alert-warning py-1 px-2 mt-2 mb-0 small">
                                    <i class="bi bi-pin-angle-fill"></i> <span id="note-text-<?= $oid ?>"><?= htmlspecialchars($row['admin_note']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <button class="btn btn-sm btn-link text-muted p-0 mt-1 text-decoration-none" data-bs-toggle="modal" data-bs-target="#noteModal<?= $oid ?>">
                            <i class="bi bi-pencil-square"></i> Note
                        </button>
                    </div>

                    <div class="col-6 col-md-3 border-end-md text-center">
                        <div class="text-muted small">ยอดสุทธิ</div>
                        <h4 class="fw-bold text-danger m-0">฿<?= number_format($row['final_price'], 2) ?></h4>
                        <div class="small text-muted mt-1"><?= $row['payment_method'] ?></div>
                        
                        <?php if (intval($row['points_spent']) > 0 || intval($row['points_earned']) > 0): ?>
                            <div class="mt-2 text-start px-2 mx-auto" style="max-width: 200px; font-size: 0.75rem; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px; padding: 4px 8px;">
                                <?php if (intval($row['points_spent']) > 0): ?>
                                    <div class="text-muted">
                                        🪙 ใช้แต้ม: <span class="fw-bold text-danger"><?= number_format($row['points_spent']) ?></span> (-฿<?= number_format($row['points_discount'], 2) ?>)
                                    </div>
                                <?php endif; ?>
                                <?php if (intval($row['points_earned']) > 0): ?>
                                    <div class="text-muted">
                                        🪙 ได้รับแต้ม: 
                                        <?php if ($st == 'completed'): ?>
                                            <span class="fw-bold text-success">+<?= number_format($row['points_earned']) ?></span>
                                        <?php elseif ($st == 'cancelled'): ?>
                                            <span class="text-secondary text-decoration-line-through">+<?= number_format($row['points_earned']) ?></span>
                                        <?php else: ?>
                                            <span class="fw-bold text-warning">+<?= number_format($row['points_earned']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <button class="btn btn-sm btn-outline-primary rounded-pill mt-2 px-3" data-bs-toggle="modal" data-bs-target="#detailModal<?= $oid ?>">
                            <i class="bi bi-list-ul"></i> สินค้า
                        </button>
                    </div>

                    <div class="col-12 col-md-6 ps-md-4 pt-3 pt-md-0">
                        <div class="d-flex justify-content-between mb-3 align-items-center">
                            <?php
                            $badge_color = match($st) {
                                'pending' => 'bg-warning text-dark',
                                'shipping' => 'bg-info text-dark',
                                'completed' => 'bg-success text-white',
                                'cancelled' => 'bg-danger text-white',
                                default => 'bg-secondary text-white'
                            };
                            $st_th = match($st) {
                                'pending' => 'รอตรวจ',
                                'shipping' => 'ส่งแล้ว',
                                'completed' => 'สำเร็จ',
                                'cancelled' => 'ยกเลิก',
                                default => $st
                            };
                            ?>
                            <div>สถานะ: <span class="badge rounded-pill <?= $badge_color ?>" id="status-badge-<?= $oid ?>"><?= $st_th ?></span></div>
                            <?php if($row['payment_slip']): ?>
                                <button onclick="viewSlip('uploads/<?= $row['payment_slip'] ?>')" class="btn btn-sm btn-light border rounded-pill">ดูสลิป</button>
                            <?php endif; ?>
                        </div>

                        <div class="bg-light p-3 rounded-3">
                            <form method="POST" class="row g-2" onsubmit="event.preventDefault()">
                                <input type="hidden" name="order_id" value="<?= $oid ?>">
                                <div class="<?= $st == 'cancelled' ? 'col-12' : 'col-6' ?>" id="status-col-<?= $oid ?>">
                                    <select name="status" class="form-select form-select-sm shadow-sm" onchange="submitStatusAjax(this, '<?= $oid ?>')">
                                        <option value="pending" <?=$st=='pending'?'selected':''?>>รอตรวจ</option>
                                        <option value="shipping" <?=$st=='shipping'?'selected':''?>>ส่งแล้ว</option>
                                        <option value="completed" <?=$st=='completed'?'selected':''?>>สำเร็จ</option>
                                        <option value="cancelled" <?=$st=='cancelled'?'selected':''?>>ยกเลิก</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </div>
                                <div class="col-6" id="tracking-btn-container-<?= $oid ?>" style="<?= $st == 'cancelled' ? 'display: none;' : '' ?>">
                                    <button type="button" class="btn btn-sm btn-dark w-100 shadow-sm" data-bs-toggle="modal" data-bs-target="#trackingModal<?= $oid ?>">
                                        <i class="bi bi-truck"></i> เลขพัสดุเรียบร้อย
                                    </button>
                                </div>
                            </form>
                            <div id="tracking-container-<?= $oid ?>">
                                <?php if(!empty($row['tracking_no'])): ?>
                                    <?php
                                    $carrier_lbl = match($row['shipping_carrier'] ?? '') {
                                        'thailandpost' => 'ไปรษณีย์ไทย',
                                        'kerry', 'kex' => 'KEX Express',
                                        'flash' => 'Flash',
                                        'jnt' => 'J&T',
                                        default => 'อื่นๆ'
                                    };
                                    ?>
                                    <div class="mt-2 small text-center text-success fw-bold">
                                        Tracking: <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold px-2 py-1" style="font-size: 0.75rem;"><?= $carrier_lbl ?></span> <span id="track-text-val-<?= $oid ?>"><?= htmlspecialchars($row['tracking_no']) ?></span>
                                        <i class="bi bi-copy ms-1 text-secondary" style="cursor:pointer;" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($row['tracking_no']) ?>'); Toast.fire({icon:'success', title:'คัดลอกเลขพัสดุแล้ว'})" title="คัดลอกเลขพัสดุ"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="noteModal<?= $oid ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content"><div class="modal-header border-0 pb-0"><h6 class="modal-title fw-bold">หมายเหตุ</h6></div><form id="note-form-<?= $oid ?>" onsubmit="submitNoteAjax(event, '<?= $oid ?>')"><?= get_csrf_input() ?><div class="modal-body"><input type="hidden" name="order_id" value="<?= $oid ?>"><textarea name="admin_note" class="form-control" rows="3"><?= htmlspecialchars($row['admin_note']) ?></textarea></div><div class="modal-footer border-0 pt-0"><button type="submit" class="btn btn-primary w-100 btn-sm shadow-sm">บันทึก</button></div></form></div></div></div>
            <div class="modal fade" id="trackingModal<?= $oid ?>" tabindex="-1">
                <div class="modal-dialog modal-sm modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header border-0 pb-0">
                            <h6 class="modal-title fw-bold text-dark">บันทึกส่งพัสดุ #<?= $oid ?></h6>
                        </div>
                        <form id="tracking-form-<?= $oid ?>" onsubmit="submitTrackingAjax(event, '<?= $oid ?>')">
                            <?= get_csrf_input() ?>
                            <div class="modal-body py-3">
                                <input type="hidden" name="order_id" value="<?= $oid ?>">
                                <div class="mb-2">
                                    <label class="form-label small text-muted mb-1 fw-bold">ผู้ให้บริการขนส่ง</label>
                                    <select name="shipping_carrier" class="form-select form-select-sm shadow-sm" required>
                                        <option value="thailandpost" <?= ($row['shipping_carrier'] ?? '') == 'thailandpost' ? 'selected' : '' ?>>ไปรษณีย์ไทย (EMS/ลงทะเบียน)</option>
                                        <option value="kex" <?= (($row['shipping_carrier'] ?? '') == 'kex' || ($row['shipping_carrier'] ?? '') == 'kerry') ? 'selected' : '' ?>>KEX Express</option>
                                        <option value="flash" <?= ($row['shipping_carrier'] ?? '') == 'flash' ? 'selected' : '' ?>>Flash Express</option>
                                        <option value="jnt" <?= ($row['shipping_carrier'] ?? '') == 'jnt' ? 'selected' : '' ?>>J&T Express</option>
                                        <option value="other" <?= (empty($row['shipping_carrier']) || $row['shipping_carrier'] == 'other') ? 'selected' : '' ?>>อื่นๆ / ค้นหาอัตโนมัติ (17Track)</option>
                                    </select>
                                </div>
                                <div class="mb-1">
                                    <label class="form-label small text-muted mb-1 fw-bold">หมายเลขพัสดุ (Tracking No.)</label>
                                    <input type="text" name="tracking_no" class="form-control form-control-sm shadow-sm" value="<?= htmlspecialchars($row['tracking_no'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="modal-footer border-0 pt-0">
                                <button type="submit" name="save_tracking" class="btn btn-success w-100 btn-sm shadow-sm fw-bold">บันทึกจัดส่ง</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="detailModal<?= $oid ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow"><div class="modal-header bg-light"><h5 class="modal-title fw-bold">รายการสินค้า #<?= $oid ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-3"><?php $items = mysqli_query($conn, "SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id='$oid'"); while($i = mysqli_fetch_assoc($items)): ?><div class="d-flex justify-content-between mb-2 small"><span><?= $i['name'] ?> (x<?= $i['quantity'] ?>)</span><span class="fw-bold">฿<?= number_format($i['price'] * $i['quantity']) ?></span></div><?php endwhile; ?></div></div></div></div>

            <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-5 bg-white rounded-4 shadow-sm my-4">
                    <i class="bi bi-inbox display-3 text-muted opacity-25"></i>
                    <h5 class="text-muted mt-3">ไม่พบรายการสั่งซื้อที่ค้นหา</h5>
                    <?php if ($search !== '' || $status_filter !== ''): ?>
                        <a href="admin_orders.php" class="btn btn-sm btn-primary rounded-pill mt-3 px-4">ดูรายการทั้งหมด</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="slipModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content bg-transparent border-0 text-center"><img id="slipImage" src="" class="img-fluid rounded shadow-lg" style="max-height:85vh;"></div></div></div>

<?php if(isset($_SESSION['swal'])): ?>
<script>
    Swal.fire({
        icon: '<?= $_SESSION['swal']['icon'] ?>',
        title: '<?= $_SESSION['swal']['title'] ?>',
        text: '<?= $_SESSION['swal']['text'] ?>',
        confirmButtonColor: '#AEE2FF',
        timer: 1000,
        showConfirmButton: false
    });
</script>
<?php unset($_SESSION['swal']); endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function viewSlip(url){ 
    new bootstrap.Modal(document.getElementById('slipModal')).show(); 
    document.getElementById('slipImage').src=url; 
}

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

function updateDashboardStats(stats) {
    if (!stats) return;
    if (document.getElementById('stat-all')) document.getElementById('stat-all').innerText = Number(stats.all).toLocaleString();
    if (document.getElementById('stat-pending')) document.getElementById('stat-pending').innerText = Number(stats.pending).toLocaleString();
    if (document.getElementById('stat-shipping')) document.getElementById('stat-shipping').innerText = Number(stats.shipping).toLocaleString();
    if (document.getElementById('stat-completed')) document.getElementById('stat-completed').innerText = Number(stats.completed).toLocaleString();
    if (document.getElementById('stat-cancelled')) document.getElementById('stat-cancelled').innerText = Number(stats.cancelled).toLocaleString();
}

function submitStatusAjax(selectEl, orderId) {
    const status = selectEl.value;
    selectEl.disabled = true;
    
    const formData = new FormData();
    formData.append('order_id', orderId);
    formData.append('status', status);
    formData.append('update_status', '1');
    formData.append('ajax', '1');
    formData.append('csrf_token', '<?= get_csrf_token() ?>');
    
    fetch('admin_orders.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        selectEl.disabled = false;
        if (data.status === 'success') {
            Toast.fire({
                icon: 'success',
                title: data.message
            });
            
            const badge = document.getElementById('status-badge-' + orderId);
            if (badge) {
                badge.innerText = status === 'pending' ? 'รอตรวจ' : (status === 'shipping' ? 'ส่งแล้ว' : (status === 'completed' ? 'สำเร็จ' : 'ยกเลิก'));
                badge.className = 'badge rounded-pill';
                
                if (status === 'pending') badge.classList.add('bg-warning', 'text-dark');
                else if (status === 'shipping') badge.classList.add('bg-info', 'text-dark');
                else if (status === 'completed') badge.classList.add('bg-success', 'text-white');
                else if (status === 'cancelled') badge.classList.add('bg-danger', 'text-white');
                else badge.classList.add('bg-secondary', 'text-white');
            }
            
            updateDashboardStats(data.stats);
            
            const statusCol = document.getElementById('status-col-' + orderId);
            const trackingBtnCont = document.getElementById('tracking-btn-container-' + orderId);
            if (trackingBtnCont) {
                if (status === 'cancelled') {
                    trackingBtnCont.style.display = 'none';
                    if (statusCol) statusCol.className = 'col-12';
                } else {
                    trackingBtnCont.style.display = 'block';
                    if (statusCol) statusCol.className = 'col-6';
                }
            }
        } else {
            Toast.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาดในการอัปเดต'
            });
        }
    })
    .catch(err => {
        selectEl.disabled = false;
        console.error(err);
        Toast.fire({
            icon: 'error',
            title: 'เชื่อมต่อเซิร์ฟเวอร์ล้มเหลว'
        });
    });
}

function submitNoteAjax(event, orderId) {
    event.preventDefault();
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    
    const formData = new FormData(form);
    formData.append('save_note', '1');
    formData.append('ajax', '1');
    
    fetch('admin_orders.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        submitBtn.disabled = false;
        
        const modalEl = document.getElementById('noteModal' + orderId);
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) modalInstance.hide();
        
        if (data.status === 'success') {
            Toast.fire({
                icon: 'success',
                title: data.message
            });
            
            const container = document.getElementById('note-container-' + orderId);
            if (container) {
                if (data.note.trim() !== '') {
                    container.innerHTML = `
                        <div class="alert alert-warning py-1 px-2 mt-2 mb-0 small">
                            <i class="bi bi-pin-angle-fill"></i> <span id="note-text-${orderId}">${escapeHtml(data.note)}</span>
                        </div>
                    `;
                } else {
                    container.innerHTML = '';
                }
            }
        } else {
            Toast.fire({
                icon: 'error',
                title: 'ไม่สามารถบันทึกหมายเหตุได้'
            });
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        console.error(err);
        Toast.fire({
            icon: 'error',
            title: 'การเชื่อมต่อล้มเหลว'
        });
    });
}

function submitTrackingAjax(event, orderId) {
    event.preventDefault();
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    
    const formData = new FormData(form);
    formData.append('save_tracking', '1');
    formData.append('ajax', '1');
    
    fetch('admin_orders.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        submitBtn.disabled = false;
        
        const modalEl = document.getElementById('trackingModal' + orderId);
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) modalInstance.hide();
        
        if (data.status === 'success') {
            Toast.fire({
                icon: 'success',
                title: data.message
            });
            
            const trackingNo = form.querySelector('input[name="tracking_no"]').value;
            const carrierSelect = form.querySelector('select[name="shipping_carrier"]');
            const carrierVal = carrierSelect ? carrierSelect.value : 'other';
            const carrierLabel = carrierVal === 'thailandpost' ? 'ไปรษณีย์ไทย' : ((carrierVal === 'kerry' || carrierVal === 'kex') ? 'KEX Express' : (carrierVal === 'flash' ? 'Flash' : (carrierVal === 'jnt' ? 'J&T' : 'อื่นๆ')));
            
            const container = document.getElementById('tracking-container-' + orderId);
            if (container && trackingNo.trim() !== '') {
                container.innerHTML = `
                    <div class="mt-2 small text-center text-success fw-bold">
                        Tracking: <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold px-2 py-1" style="font-size: 0.75rem;">${carrierLabel}</span> <span id="track-text-val-${orderId}">${escapeHtml(trackingNo)}</span>
                        <i class="bi bi-copy ms-1 text-secondary" style="cursor:pointer;" onclick="navigator.clipboard.writeText('${escapeHtml(trackingNo)}'); Toast.fire({icon:'success', title:'คัดลอกเลขพัสดุแล้ว'})" title="คัดลอกเลขพัสดุ"></i>
                    </div>
                `;
            }
            
            const selectEl = document.getElementById('status-badge-' + orderId).closest('.order-card').querySelector('select[name="status"]');
            if (selectEl) {
                selectEl.value = 'shipping';
            }
            
            const badge = document.getElementById('status-badge-' + orderId);
            if (badge) {
                badge.innerText = 'ส่งแล้ว';
                badge.className = 'badge rounded-pill bg-info text-dark';
            }
            
            updateDashboardStats(data.stats);
        } else {
            Toast.fire({
                icon: 'error',
                title: 'ไม่สามารถบันทึกเลขพัสดุได้'
            });
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        console.error(err);
        Toast.fire({
            icon: 'error',
            title: 'การเชื่อมต่อล้มเหลว'
        });
    });
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}
</script>
</body>
</html>


