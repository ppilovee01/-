<?php
session_start();
include 'db.php';
include 'mail_sender.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }

// --- Logic: อัปวสถานะ (Anti-F5 Fixed) ---
if (isset($_POST['update_status'])) {
    $oid = $_POST['order_id'];
    $status = $_POST['status'];
    
    // คืนสต็อกกรณีที่ยกเลิกออเดอร์
    if ($status == 'cancelled') {
        $chk = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM orders WHERE id='$oid'"));
        if ($chk['status'] != 'cancelled') {
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
    $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'อัปวสถานะเรียบร้อย', 'icon'=>'success'];
    header("Location: admin_orders.php"); exit();
}

// --- Logic: บันทึกเลขพัสดุ ---
if (isset($_POST['save_tracking'])) {
    $oid = $_POST['order_id'];
    $track = mysqli_real_escape_string($conn, $_POST['tracking_no']);
    mysqli_query($conn, "UPDATE orders SET tracking_no = '$track', status = 'shipping' WHERE id = '$oid'");
    
    // Insert user notification for tracking number
    $order_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id, id FROM orders WHERE id = '$oid'"));
    if ($order_data) {
        $uid = $order_data['user_id'];
        $ono = $order_data['id'];
        $title = "คำสั่งซื้อ #$ono ถูกจัดส่งแล้ว";
        $message = "คำสั่งซื้อหมายเลข #$ono ของคุณได้รับการจัดส่งแล้ว! เลขพัสดุของคุณคือ: $track";
        $url = "my_orders.php";
        mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, url, is_read, is_admin) VALUES ('$uid', '$title', '$message', '$url', 0, 0)");
    }

    send_order_email($conn, $oid);
    $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'บันทึกเลขพัสดุ', 'icon'=>'success'];
    header("Location: admin_orders.php"); exit();
}

// --- Logic: บันทึกหมายเหตุ ---
if (isset($_POST['save_note'])) {
    $oid = $_POST['order_id'];
    $note = mysqli_real_escape_string($conn, $_POST['admin_note']);
    mysqli_query($conn, "UPDATE orders SET admin_note = '$note' WHERE id = '$oid'");
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
            $res = mysqli_query($conn, "SELECT o.*, u.fullname FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.id DESC");
            while ($row = mysqli_fetch_assoc($res)): 
                $oid = $row['id']; 
                $st = $row['status'];
            ?>
            <div class="order-card p-4">
                <div class="row align-items-center">
                    <div class="col-12 col-md-3 border-end-md">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <h5 class="fw-bold m-0">#<?= str_pad($oid, 5, '0', STR_PAD_LEFT) ?></h5>
                            <a href="admin_print_label.php?id=<?= $oid ?>" target="_blank" class="btn btn-sm btn-dark rounded-circle"><i class="bi bi-printer"></i></a>
                        </div>
                        <div class="small text-muted"><?= date('d/m/Y H:i', strtotime($row['order_date'])) ?></div>
                        <div class="fw-bold text-primary mt-1"><?= $row['fullname'] ?></div>
                        
                        <?php if(!empty($row['admin_note'])): ?>
                            <div class="alert alert-warning py-1 px-2 mt-2 mb-0 small">
                                <i class="bi bi-pin-angle-fill"></i> <?= $row['admin_note'] ?>
                            </div>
                        <?php endif; ?>
                        
                        <button class="btn btn-sm btn-link text-muted p-0 mt-1 text-decoration-none" data-bs-toggle="modal" data-bs-target="#noteModal<?= $oid ?>">
                            <i class="bi bi-pencil-square"></i> Note
                        </button>
                    </div>

                    <div class="col-6 col-md-3 border-end-md text-center">
                        <div class="text-muted small">ยอดสุทธิ</div>
                        <h4 class="fw-bold text-danger m-0">฿<?= number_format($row['final_price'], 2) ?></h4>
                        <div class="small text-muted mt-1"><?= $row['payment_method'] ?></div>
                        
                        <button class="btn btn-sm btn-outline-primary rounded-pill mt-2 px-3" data-bs-toggle="modal" data-bs-target="#detailModal<?= $oid ?>">
                            <i class="bi bi-list-ul"></i> สินค้า
                        </button>
                    </div>

                    <div class="col-12 col-md-6 ps-md-4 pt-3 pt-md-0">
                        <div class="d-flex justify-content-between mb-3 align-items-center">
                            <div>สถานะ: <span class="badge rounded-pill bg-secondary"><?= $st ?></span></div>
                            <?php if($row['payment_slip']): ?>
                                <button onclick="viewSlip('uploads/<?= $row['payment_slip'] ?>')" class="btn btn-sm btn-light border rounded-pill">ดูสลิป</button>
                            <?php endif; ?>
                        </div>

                        <div class="bg-light p-3 rounded-3">
                            <form method="POST" class="row g-2">
                                <input type="hidden" name="order_id" value="<?= $oid ?>">
                                <div class="col-6">
                                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="pending" <?=$st=='pending'?'selected':''?>>รอตรวจ</option>
                                        <option value="shipping" <?=$st=='shipping'?'selected':''?>>ส่งแล้ว</option>
                                        <option value="completed" <?=$st=='completed'?'selected':''?>>สำเร็จ</option>
                                        <option value="cancelled" <?=$st=='cancelled'?'selected':''?>>ยกเลิก</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </div>
                                <div class="col-6">
                                    <?php if($st != 'cancelled'): ?>
                                        <button type="button" class="btn btn-sm btn-dark w-100" data-bs-toggle="modal" data-bs-target="#trackingModal<?= $oid ?>">
                                            <i class="bi bi-truck"></i> เลขพัสดุเรียบร้อย
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                            <?php if(!empty($row['tracking_no'])): ?>
                                <div class="mt-2 small text-center text-success fw-bold">Tracking: <?= $row['tracking_no'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="noteModal<?= $oid ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content"><div class="modal-header border-0 pb-0"><h6 class="modal-title fw-bold">หมายเหตุ</h6></div><form method="POST"><div class="modal-body"><input type="hidden" name="order_id" value="<?= $oid ?>"><textarea name="admin_note" class="form-control" rows="3"><?= $row['admin_note'] ?></textarea></div><div class="modal-footer border-0 pt-0"><button type="submit" name="save_note" class="btn btn-primary w-100 btn-sm">บันทึก</button></div></form></div></div></div>
            <div class="modal fade" id="trackingModal<?= $oid ?>" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content"><div class="modal-header border-0 pb-0"><h6 class="modal-title">หมายเลขพัสดุ</h6></div><form method="POST"><div class="modal-body"><input type="hidden" name="order_id" value="<?= $oid ?>"><input type="text" name="tracking_no" class="form-control" value="<?= $row['tracking_no'] ?>" required></div><div class="modal-footer border-0 pt-0"><button name="save_tracking" class="btn btn-success w-100 btn-sm">บันทึก</button></div></form></div></div></div>
            <div class="modal fade" id="detailModal<?= $oid ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow"><div class="modal-header bg-light"><h5 class="modal-title fw-bold">รายการสินค้า #<?= $oid ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-3"><?php $items = mysqli_query($conn, "SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id='$oid'"); while($i = mysqli_fetch_assoc($items)): ?><div class="d-flex justify-content-between mb-2 small"><span><?= $i['name'] ?> (x<?= $i['quantity'] ?>)</span><span class="fw-bold">฿<?= number_format($i['price'] * $i['quantity']) ?></span></div><?php endwhile; ?></div></div></div></div>

            <?php endwhile; ?>
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
<script>function viewSlip(url){ new bootstrap.Modal(document.getElementById('slipModal')).show(); document.getElementById('slipImage').src=url; }</script>
</body>
</html>


