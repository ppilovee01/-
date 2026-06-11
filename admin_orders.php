<?php
ob_start();
session_start();
include 'db.php';
include 'mail_sender.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }

if (isset($_GET['check_new_orders'])) {
    $max_q = mysqli_query($conn, "SELECT MAX(id) as max_id FROM orders");
    $max_row = mysqli_fetch_assoc($max_q);
    $max_id = intval($max_row['max_id'] ?? 0);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'max_id' => $max_id]);
    exit();
}

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
    
    if (!function_exists('get_status_label')) {
        function get_status_label($st) {
            return match($st) {
                'pending' => 'รอตรวจสอบชำระเงิน',
                'shipping' => 'กำลังจัดส่งสินค้า',
                'completed' => 'คำสั่งซื้อสำเร็จ',
                'cancelled' => 'คำสั่งซื้อถูกยกเลิก',
                default => $st
            };
        }
    }
    
    $ord_info_q = mysqli_query($conn, "SELECT o.final_price, u.fullname FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = '$oid'");
    $ord_info = mysqli_fetch_assoc($ord_info_q);
    $ord_desc = "";
    if ($ord_info) {
        $ord_desc = " (ลูกค้า: " . ($ord_info['fullname'] ?? 'ไม่ระบุ') . ", ยอดสุทธิ: ฿" . number_format($ord_info['final_price'], 2) . ")";
    }
    
    log_admin_action($conn, 'อัปเดตสถานะออเดอร์', [
        'title' => "อัปเดตสถานะคำสั่งซื้อ #$oid" . $ord_desc,
        'changes' => [
            ['field' => 'สถานะคำสั่งซื้อ', 'old' => get_status_label($old_status), 'new' => get_status_label($status)]
        ]
    ]);
    
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
    
    // ตรวจสอบสถานะเดิมและเลขพัสดุเดิมก่อนเปลี่ยน
    $order_q = mysqli_query($conn, "SELECT status, user_id, points_earned, tracking_no, shipping_carrier FROM orders WHERE id='$oid'");
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
    
    $carrier_lbl_old = match($old_order['shipping_carrier'] ?? '') {
        'thailandpost' => 'ไปรษณีย์ไทย',
        'kerry', 'kex' => 'KEX Express',
        'flash' => 'Flash Express',
        'jnt' => 'J&T Express',
        default => 'อื่นๆ / ไม่ระบุ'
    };
    $carrier_lbl_new = match($carrier) {
        'thailandpost' => 'ไปรษณีย์ไทย',
        'kex', 'kerry' => 'KEX Express',
        'flash' => 'Flash Express',
        'jnt' => 'J&T Express',
        default => 'อื่นๆ'
    };
    
    $ord_info_q = mysqli_query($conn, "SELECT o.final_price, u.fullname FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = '$oid'");
    $ord_info = mysqli_fetch_assoc($ord_info_q);
    $ord_desc = "";
    if ($ord_info) {
        $ord_desc = " (ลูกค้า: " . ($ord_info['fullname'] ?? 'ไม่ระบุ') . ")";
    }
    
    if (!function_exists('get_status_label')) {
        function get_status_label($st) {
            return match($st) {
                'pending' => 'รอตรวจสอบชำระเงิน',
                'shipping' => 'กำลังจัดส่งสินค้า',
                'completed' => 'คำสั่งซื้อสำเร็จ',
                'cancelled' => 'คำสั่งซื้อถูกยกเลิก',
                default => $st
            };
        }
    }
    
    log_admin_action($conn, 'บันทึกเลขพัสดุ', [
        'title' => "บันทึกข้อมูลจัดส่งพัสดุสำหรับออเดอร์ #$oid" . $ord_desc,
        'changes' => [
            ['field' => 'ผู้ให้บริการขนส่ง', 'old' => $carrier_lbl_old, 'new' => $carrier_lbl_new],
            ['field' => 'หมายเลขพัสดุ (Tracking No.)', 'old' => $old_order['tracking_no'] ?: 'ยังไม่ได้บันทึก', 'new' => $track],
            ['field' => 'สถานะคำสั่งซื้อ', 'old' => get_status_label($old_order['status'] ?? 'pending'), 'new' => get_status_label('shipping')]
        ]
    ]);
    
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
    
    // ดึงหมายเหตุเดิมก่อนเปลี่ยน
    $old_note_q = mysqli_query($conn, "SELECT admin_note FROM orders WHERE id='$oid'");
    $old_note_row = mysqli_fetch_assoc($old_note_q);
    $old_note = $old_note_row ? ($old_note_row['admin_note'] ?? '') : '';
    
    mysqli_query($conn, "UPDATE orders SET admin_note = '$note' WHERE id = '$oid'");
    
    $ord_info_q = mysqli_query($conn, "SELECT o.final_price, u.fullname FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = '$oid'");
    $ord_info = mysqli_fetch_assoc($ord_info_q);
    $ord_desc = "";
    if ($ord_info) {
        $ord_desc = " (ลูกค้า: " . ($ord_info['fullname'] ?? 'ไม่ระบุ') . ")";
    }
    
    log_admin_action($conn, 'บันทึกหมายเหตุ', [
        'title' => "บันทึกหมายเหตุเพิ่มเติมสำหรับออเดอร์ #$oid" . $ord_desc,
        'changes' => [
            ['field' => 'หมายเหตุ (Admin Note)', 'old' => $old_note ?: 'ไม่มีหมายเหตุ', 'new' => $note ?: 'ลบหมายเหตุ']
        ]
    ]);
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
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
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
        .badge-wrap {
            white-space: normal !important;
            text-align: left;
            word-break: break-word;
            max-width: 100%;
            display: inline-block;
        }
        .modal-product-item:last-child {
            border-bottom: none !important;
            margin-bottom: 0 !important;
            padding-bottom: 0 !important;
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

        <div class="col-md-10 p-3 p-md-4">
            <h3 class="fw-bold mb-4">จัดการคำสั่งซื้อ</h3>

            <?php 
            $shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id = 1"));
            $notification_sound = $shop['notification_sound'] ?? 'chime';

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

            // 3. ดึงรายการออเดอร์ตามเงื่อนไขค้นหา (พร้อมระบบแบ่งหน้า)
            $limit = isset($_GET['limit']) ? max(10, min(100, intval($_GET['limit']))) : 20;
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            
            $count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders o LEFT JOIN users u ON o.user_id = u.id $where_sql");
            $total_rows = mysqli_fetch_assoc($count_query)['total'] ?? 0;
            $total_pages = ceil($total_rows / $limit);
            if ($total_pages > 0 && $page > $total_pages) {
                $page = $total_pages;
            }
            $offset = ($page - 1) * $limit;

            $res = mysqli_query($conn, "SELECT o.*, u.fullname FROM orders o LEFT JOIN users u ON o.user_id = u.id $where_sql ORDER BY o.id DESC LIMIT $limit OFFSET $offset");
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
                <form id="filter-form" method="GET" class="row g-2 align-items-center" onsubmit="event.preventDefault(); fetchOrdersFiltered();">
                    <div class="col-md-5 col-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control bg-light border-start-0" placeholder="รหัสออเดอร์, ชื่อลูกค้า, หรือที่อยู่..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-4 col-12">
                        <div class="d-flex align-items-center gap-2">
                            <span class="small text-muted fw-bold text-nowrap">สถานะ:</span>
                            <select name="status_filter" class="form-select form-select-sm bg-light" onchange="fetchOrdersFiltered()">
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
                        <button type="button" id="clear-filter-btn" onclick="clearFilters()" class="btn btn-sm btn-outline-secondary w-100 rounded-pill" style="<?= ($search !== '' || $status_filter !== '') ? '' : 'display: none;' ?>"><i class="bi bi-x-circle me-1"></i>ล้าง</button>
                    </div>
                </form>
            </div>

            <?php 
            $ajax_fetch = isset($_GET['ajax_fetch']);
            if ($ajax_fetch) ob_start(); 
            ?>
            <div id="orders-list-wrapper">
                <?php if (mysqli_num_rows($res) > 0): ?>
                    <?php 
                    while ($row = mysqli_fetch_assoc($res)): 
                        $oid = $row['id']; 
                        $st = $row['status'];
                    ?>
            <div class="order-card p-3 p-md-4">
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

                    <div class="col-12 col-md-3 border-end-md text-start text-md-center">
                        <div class="text-muted small">ยอดสุทธิ</div>
                        <h4 class="fw-bold text-danger m-0">฿<?= number_format($row['final_price'], 2) ?></h4>
                        <div class="small text-muted mt-1"><?= htmlspecialchars($row['payment_method'], ENT_QUOTES, 'UTF-8') ?></div>
                        
                        <?php if (intval($row['points_spent']) > 0 || intval($row['points_earned']) > 0): ?>
                            <div class="mt-2 text-start px-2 ms-0 ms-md-auto me-md-auto" style="max-width: 200px; font-size: 0.75rem; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px; padding: 4px 8px;">
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

                        <!-- รายการสินค้าแสดงเลยไม่ต้องกดเปิดโมดอล -->
                        <div class="mt-3 text-start mx-auto ms-md-auto me-md-auto" style="max-width: 250px;">
                            <div class="text-muted small mb-2 fw-semibold text-center text-md-start">📦 รายการสินค้า:</div>
                            <?php 
                            $items = mysqli_query($conn, "SELECT oi.*, p.name, p.image FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id='$oid'"); 
                            while($i = mysqli_fetch_assoc($items)): 
                            ?>
                                <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom border-light modal-product-item">
                                    <?php if(!empty($i['image'])): ?>
                                        <img src="<?= $i['image'] ?>" class="rounded" style="width: 32px; height: 32px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded bg-light d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                            <i class="bi bi-box-seam text-muted" style="font-size: 0.8rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div style="font-size: 0.8rem; line-height: 1.2; min-width: 0; flex: 1;">
                                        <div class="fw-semibold text-dark text-truncate" title="<?= htmlspecialchars($i['name']) ?>"><?= htmlspecialchars($i['name']) ?></div>
                                        <div class="text-muted" style="font-size: 0.72rem;">
                                            x<?= $i['quantity'] ?> (฿<?= number_format($i['price']) ?>)
                                            <?php if(!empty($i['selected_option'])): ?>
                                                <span class="ms-1 badge bg-light text-secondary border fw-normal" style="font-size: 0.65rem; padding: 2px 4px;"><?= htmlspecialchars($i['selected_option']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 ps-md-4 pt-3 pt-md-0">
                        <div class="d-flex justify-content-between mb-3 align-items-center flex-wrap gap-2">
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
                                <div class="d-flex flex-column align-items-end gap-1" id="slip-ai-badge-container-<?= $oid ?>">
                                    <button onclick="viewSlip('uploads/<?= $row['payment_slip'] ?>')" class="btn btn-sm btn-light border rounded-pill py-1 px-2" style="font-size: 0.8rem;">
                                        <i class="bi bi-image me-1"></i> ดูสลิป
                                    </button>
                                    
                                    <?php 
                                    $ai_st = $row['slip_ai_status'];
                                    $ai_amt = $row['slip_ai_amount'];
                                    $ai_note = $row['slip_ai_note'] ?? '';
                                    
                                    if ($ai_st === 'verified'): ?>
                                        <span class="badge badge-wrap bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size:0.7rem;" title="ยอดเงินโอนตรงกับคำสั่งซื้อ">
                                            🤖 AI: ตรงยอด (฿<?= number_format($ai_amt, 2) ?>)
                                        </span>
                                    <?php elseif ($ai_st === 'mismatch'): ?>
                                        <span class="badge badge-wrap bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 mb-1" style="font-size:0.7rem;" title="ยอดเงินโอนไม่ตรงกับคำสั่งซื้อ (สลิปโอนเงิน: ฿<?= number_format($ai_amt, 2) ?>)">
                                            🤖 AI: ยอดไม่ตรง (฿<?= number_format($ai_amt, 2) ?>)
                                        </span>
                                        <button onclick="runSlipAI('<?= $oid ?>', '<?= htmlspecialchars($row['payment_slip']) ?>', '<?= $row['final_price'] ?>', this)" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded border small text-muted animate__animated animate__fadeIn" style="font-size:0.65rem;" title="ลองส่งสลิปให้ AI ตรวจสอบใหม่อีกครั้ง">
                                            🤖 ลองสแกนใหม่
                                        </button>
                                    <?php elseif ($ai_st === 'invalid'): ?>
                                        <span class="badge badge-wrap bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 mb-1" style="font-size:0.7rem;" title="AI ประเมินว่ารูปนี้ไม่ใช่สลิป หรือชื่อผู้รับเงินไม่ตรง: <?= htmlspecialchars($ai_note) ?>">
                                            🤖 AI: ไม่ผ่าน (<?= htmlspecialchars($ai_note) ?>)
                                        </span>
                                        <button onclick="runSlipAI('<?= $oid ?>', '<?= htmlspecialchars($row['payment_slip']) ?>', '<?= $row['final_price'] ?>', this)" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded border small text-muted animate__animated animate__fadeIn" style="font-size:0.65rem;" title="ลองส่งสลิปให้ AI ตรวจสอบใหม่อีกครั้ง">
                                            🤖 ลองสแกนใหม่
                                        </button>
                                    <?php elseif ($ai_st === 'error' || $ai_st === 'skipped'): ?>
                                        <span class="badge badge-wrap bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 mb-1" style="font-size:0.7rem; cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="ระบบขัดข้อง: <?= htmlspecialchars($ai_note ?: 'กรุณาลองสแกนใหม่อีกครั้ง') ?>">
                                            ⚠️ 😱 AI ขัดข้อง
                                        </span>
                                        <button onclick="runSlipAI('<?= $oid ?>', '<?= htmlspecialchars($row['payment_slip']) ?>', '<?= $row['final_price'] ?>', this)" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded border small text-muted animate__animated animate__fadeIn" style="font-size:0.65rem;" title="ลองส่งสลิปให้ AI ตรวจสอบใหม่อีกครั้ง">
                                            🤖 ลองสแกนใหม่
                                        </button>
                                    <?php else: ?>
                                        <button onclick="runSlipAI('<?= $oid ?>', '<?= htmlspecialchars($row['payment_slip']) ?>', '<?= $row['final_price'] ?>', this)" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded border small text-muted animate__animated animate__fadeIn" style="font-size:0.65rem;" title="ส่งสลิปนี้ให้ AI ตรวจสอบความถูกต้อง">
                                            🤖 สแกนสลิป
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="bg-light p-3 rounded-3">
                            <form method="POST" class="row g-2" onsubmit="event.preventDefault()">
                                <input type="hidden" name="order_id" value="<?= $oid ?>">
                                <div class="<?= $st == 'cancelled' ? 'col-12' : 'col-12 col-sm-6' ?>" id="status-col-<?= $oid ?>">
                                    <select name="status" class="form-select form-select-sm shadow-sm" onchange="submitStatusAjax(this, '<?= $oid ?>')">
                                        <option value="pending" <?=$st=='pending'?'selected':''?>>รอตรวจ</option>
                                        <option value="shipping" <?=$st=='shipping'?'selected':''?>>ส่งแล้ว</option>
                                        <option value="completed" <?=$st=='completed'?'selected':''?>>สำเร็จ</option>
                                        <option value="cancelled" <?=$st=='cancelled'?'selected':''?>>ยกเลิก</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </div>
                                <div class="col-12 col-sm-6" id="tracking-btn-container-<?= $oid ?>" style="<?= $st == 'cancelled' ? 'display: none;' : '' ?>">
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
            <!-- รายการโมดอลสินค้าถูกยกเลิกเนื่องจากใช้การแสดงผล inline บนการ์ดโดยตรงแล้ว -->

            <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-5 bg-white rounded-4 shadow-sm my-4">
                    <i class="bi bi-inbox display-3 text-muted opacity-25"></i>
                    <h5 class="text-muted mt-3">ไม่พบรายการสั่งซื้อที่ค้นหา</h5>
                    <button type="button" onclick="clearFilters()" class="btn btn-sm btn-primary rounded-pill mt-3 px-4">ดูรายการทั้งหมด</button>
                </div>
            <?php endif; ?>
            <!-- การแบ่งหน้า (Pagination) -->
            <?= render_pagination_controls($total_rows, $limit, $page, $offset) ?>
            </div>
            <?php 
            if ($ajax_fetch) {
                $html = ob_get_clean();
                ob_end_clean(); // Discard outer buffer
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success',
                    'html' => $html,
                    'stats' => get_stats_counts($conn)
                ]);
                exit();
            }
            ?>
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
let currentMaxOrderId = 0;

document.addEventListener("DOMContentLoaded", function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize absolute max order ID from database
    initMaxOrderId();
    
    // Check for new orders every 10 seconds
    setInterval(checkForNewOrders, 10000);
});

function initMaxOrderId() {
    fetch(window.location.pathname + '?check_new_orders=1')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                currentMaxOrderId = parseInt(data.max_id, 10);
            }
        })
        .catch(err => console.error(err));
}

function checkForNewOrders() {
    fetch(window.location.pathname + '?check_new_orders=1')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const serverMaxId = parseInt(data.max_id, 10);
                if (currentMaxOrderId > 0 && serverMaxId > currentMaxOrderId) {
                    currentMaxOrderId = serverMaxId;
                    
                    // Play synthesized chime notification
                    playNotificationSound();
                    
                    // Show top-right Swal Toast
                    Toast.fire({
                        icon: 'info',
                        title: '🔔 มีคำสั่งซื้อใหม่เข้ามา!'
                    });
                    
                    // Refresh current filtered view silently
                    fetchOrdersFiltered(true);
                } else if (currentMaxOrderId === 0) {
                    currentMaxOrderId = serverMaxId;
                }
            }
        })
        .catch(err => console.error(err));
}

const activeSoundType = '<?= $notification_sound ?>';

function playNotificationSound() {
    const soundType = activeSoundType;
    if (soundType === 'mute') return;
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();
        const now = ctx.currentTime;
        
        if (soundType === 'chime') {
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(659.25, now);
            gain1.gain.setValueAtTime(0.1, now);
            gain1.gain.exponentialRampToValueAtTime(0.0001, now + 0.5);
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start(now);
            osc1.stop(now + 0.5);
            
            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(880.00, now + 0.1);
            gain2.gain.setValueAtTime(0.15, now + 0.1);
            gain2.gain.exponentialRampToValueAtTime(0.0001, now + 0.7);
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.start(now + 0.1);
            osc2.stop(now + 0.7);
        } else if (soundType === 'glass') {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(1500, now);
            gain.gain.setValueAtTime(0.1, now);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.3);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(now);
            osc.stop(now + 0.3);
        } else if (soundType === 'beep') {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(880, now);
            gain.gain.setValueAtTime(0.1, now);
            gain.gain.setValueAtTime(0.1, now + 0.15);
            gain.gain.linearRampToValueAtTime(0.0001, now + 0.2);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(now);
            osc.stop(now + 0.2);
        } else if (soundType === 'piano') {
            const notes = [261.63, 329.63, 392.00, 523.25];
            notes.forEach((freq, index) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, now + index * 0.05);
                gain.gain.setValueAtTime(0.08, now + index * 0.05);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + 1.0);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now + index * 0.05);
                osc.stop(now + 1.0);
            });
        }
    } catch (e) {
        console.warn('AudioContext playback blocked or failed:', e);
    }
}

function viewSlip(url){ 
    new bootstrap.Modal(document.getElementById('slipModal')).show(); 
    document.getElementById('slipImage').src=url; 
}

function updateSlipAIBadge(orderId, aiStatus, aiAmount, aiNote, filename, expected) {
    const container = document.getElementById('slip-ai-badge-container-' + orderId);
    if (!container) return;

    let html = `
        <button onclick="viewSlip('uploads/${filename}')" class="btn btn-sm btn-light border rounded-pill py-1 px-2" style="font-size: 0.8rem;">
            <i class="bi bi-image me-1"></i> ดูสลิป
        </button>
    `;

    if (aiStatus === 'verified') {
        html += `
            <span class="badge badge-wrap bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size:0.7rem;" title="ยอดเงินโอนตรงกับคำสั่งซื้อ">
                🤖 AI: ตรงยอด (฿${Number(aiAmount).toLocaleString('th-TH', {minimumFractionDigits: 2, maximumFractionDigits: 2})})
            </span>
        `;
    } else if (aiStatus === 'mismatch') {
        html += `
            <span class="badge badge-wrap bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 mb-1" style="font-size:0.7rem;" title="ยอดเงินโอนไม่ตรงกับคำสั่งซื้อ (สลิปโอนเงิน: ฿${Number(aiAmount).toLocaleString('th-TH', {minimumFractionDigits: 2, maximumFractionDigits: 2})})">
                🤖 AI: ยอดไม่ตรง (฿${Number(aiAmount).toLocaleString('th-TH', {minimumFractionDigits: 2, maximumFractionDigits: 2})})
            </span>
            <button onclick="runSlipAI('${orderId}', '${filename}', '${expected}', this)" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded border small text-muted animate__animated animate__fadeIn" style="font-size:0.65rem;" title="ลองส่งสลิปให้ AI ตรวจสอบใหม่อีกครั้ง">
                🤖 ลองสแกนใหม่
            </button>
        `;
    } else if (aiStatus === 'invalid') {
        html += `
            <span class="badge badge-wrap bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 mb-1" style="font-size:0.7rem;" title="AI ประเมินว่ารูปนี้ไม่ใช่สลิป หรือชื่อผู้รับเงินไม่ตรง: ${escapeHtml(aiNote)}">
                🤖 AI: ไม่ผ่าน (${escapeHtml(aiNote)})
            </span>
            <button onclick="runSlipAI('${orderId}', '${filename}', '${expected}', this)" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded border small text-muted animate__animated animate__fadeIn" style="font-size:0.65rem;" title="ลองส่งสลิปให้ AI ตรวจสอบใหม่อีกครั้ง">
                🤖 ลองสแกนใหม่
            </button>
        `;
    } else if (aiStatus === 'error' || aiStatus === 'skipped') {
        html += `
            <span class="badge badge-wrap bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 mb-1" style="font-size:0.7rem; cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="ระบบขัดข้อง: ${escapeHtml(aiNote || 'กรุณาลองสแกนใหม่อีกครั้ง')}">
                ⚠️ 😱 AI ขัดข้อง
            </span>
            <button onclick="runSlipAI('${orderId}', '${filename}', '${expected}', this)" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded border small text-muted animate__animated animate__fadeIn" style="font-size:0.65rem;" title="ลองส่งสลิปให้ AI ตรวจสอบใหม่อีกครั้ง">
                🤖 ลองสแกนใหม่
            </button>
        `;
    }

    container.innerHTML = html;

    // Re-initialize tooltips if any
    var tooltipTriggerList = [].slice.call(container.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

function runSlipAI(orderId, filename, expected, btn) {
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" style="width:0.7rem; height:0.7rem;"></span> กำลังตรวจ...';
    
    const formData = new FormData();
    formData.append('order_id', orderId);
    formData.append('expected_amount', expected);
    formData.append('slip_filename', filename);
    formData.append('csrf_token', '<?= get_csrf_token() ?>');
    
    fetch('verify_slip.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        if (data.status === 'success') {
            Swal.fire({
                icon: data.ai_status === 'verified' ? 'success' : (data.ai_status === 'invalid' ? 'error' : 'warning'),
                title: 'ผลการตรวจสลิปด้วย AI',
                text: data.message,
                confirmButtonColor: '#AEE2FF'
            }).then(() => {
                // อัปเดตสถานะสลิปโดยไม่ต้องรีโหลดหน้าเว็บ!
                updateSlipAIBadge(orderId, data.ai_status, data.ai_amount, data.note, filename, expected);
                fetchOrdersFiltered(true);
            });
        } else {
            btn.innerHTML = originalText;
            Swal.fire({
                icon: 'error',
                title: 'ไม่สามารถตรวจสอบได้',
                text: data.message,
                confirmButtonColor: '#AEE2FF'
            });
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        console.error(err);
        Swal.fire({
            icon: 'error',
            title: 'ผิดพลาด',
            text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ตรวจสอบได้',
            confirmButtonColor: '#AEE2FF'
        });
    });
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
    
    fetch(window.location.pathname, {
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
                    if (statusCol) statusCol.className = 'col-12 col-sm-6';
                }
            }
            fetchOrdersFiltered(true);
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
    
    fetch(window.location.pathname, {
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
            fetchOrdersFiltered(true);
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
    
    fetch(window.location.pathname, {
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
            fetchOrdersFiltered(true);
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

function fetchOrdersFiltered(silent = false, page = null) {
    const filterForm = document.getElementById('filter-form');
    if (!filterForm) return;
    
    const search = filterForm.querySelector('input[name="search"]').value;
    const status_filter = filterForm.querySelector('select[name="status_filter"]').value;
    
    // Read limit from dropdown, if not present read from URL, default to 20
    const limitEl = document.getElementById('page-limit-select');
    const limit = limitEl ? limitEl.value : (new URLSearchParams(window.location.search).get('limit') || '20');
    
    if (page === null) {
        // Reset to page 1 on filter changes, unless it is a silent check
        page = silent ? (new URLSearchParams(window.location.search).get('page') || '1') : '1';
    }
    
    // Update URL query parameters in browser without reload
    if (!silent) {
        const url = new URL(window.location.href);
        url.searchParams.set('search', search);
        url.searchParams.set('status_filter', status_filter);
        url.searchParams.set('limit', limit);
        url.searchParams.set('page', page);
        window.history.pushState(null, '', url.toString());
    }
    
    // Show a loading opacity on the orders list
    const wrapper = document.getElementById('orders-list-wrapper');
    if (wrapper && !silent) wrapper.style.opacity = '0.5';
    
    fetch(window.location.pathname + `?search=${encodeURIComponent(search)}&status_filter=${encodeURIComponent(status_filter)}&limit=${limit}&page=${page}&ajax_fetch=1`)
    .then(res => res.json())
    .then(data => {
        if (wrapper) {
            wrapper.style.opacity = '1';
            if (data.status === 'success') {
                wrapper.innerHTML = data.html;
                updateDashboardStats(data.stats);
                bindPaginationClicks();
                
                // Re-initialize tooltips if any
                var tooltipTriggerList = [].slice.call(wrapper.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
                
                // Show/hide clear button
                const clearBtn = document.getElementById('clear-filter-btn');
                if (clearBtn) {
                    if (search !== '' || status_filter !== '') {
                        clearBtn.style.display = 'inline-block';
                    } else {
                        clearBtn.style.display = 'none';
                    }
                }
            }
        }
    })
    .catch(err => {
        if (wrapper) wrapper.style.opacity = '1';
        console.error(err);
    });
}

function bindPaginationClicks() {
    const wrapper = document.getElementById('orders-list-wrapper');
    if (!wrapper) return;
    
    const links = wrapper.querySelectorAll('.pagination .page-link');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const href = this.getAttribute('href');
            if (href && href !== '#') {
                const url = new URL(href, window.location.origin + window.location.pathname);
                const page = url.searchParams.get('page') || '1';
                fetchOrdersFiltered(false, page);
            }
        });
    });
}

// Bind load event to initialize AJAX pagination click listeners
document.addEventListener('DOMContentLoaded', function() {
    bindPaginationClicks();
});

// Bind popstate to support browser Back/Forward navigation
window.addEventListener('popstate', function() {
    const url = new URL(window.location.href);
    const search = url.searchParams.get('search') || '';
    const status_filter = url.searchParams.get('status_filter') || '';
    const page = url.searchParams.get('page') || '1';
    
    const filterForm = document.getElementById('filter-form');
    if (filterForm) {
        filterForm.querySelector('input[name="search"]').value = search;
        filterForm.querySelector('select[name="status_filter"]').value = status_filter;
    }
    fetchOrdersFiltered(true, page);
});

function clearFilters() {
    const filterForm = document.getElementById('filter-form');
    if (filterForm) {
        filterForm.querySelector('input[name="search"]').value = '';
        filterForm.querySelector('select[name="status_filter"]').value = '';
        fetchOrdersFiltered();
    }
}
</script>
</body>
</html>


