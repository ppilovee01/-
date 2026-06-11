<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');  
// เช็ค Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$action_status = "";

// คำนวณจำนวนสินค้าในตะกร้า
$cart_count = isset($_SESSION['cart']) ? array_sum(is_array($_SESSION['cart']) ? array_column($_SESSION['cart'], 'qty') : $_SESSION['cart']) : 0;

// --- Logic: ลูกค้ารีวิวสินค้าผ่าน Modal ---
if (isset($_POST['submit_modal_review']) && isset($_SESSION['user_id'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
    $uid = $_SESSION['user_id'];
    $pid = mysqli_real_escape_string($conn, $_POST['product_id']);
    $rating = intval($_POST['rating']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    
    // ป้องกันการรีวิวซ้ำ
    $check_reviewed = mysqli_query($conn, "SELECT id FROM product_reviews WHERE user_id = '$uid' AND product_id = '$pid'");
    if (mysqli_num_rows($check_reviewed) == 0) {
        $review_image = null;
        if (isset($_FILES['review_image']) && $_FILES['review_image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['review_image']['tmp_name'];
            $fileName = $_FILES['review_image']['name'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            
            $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'webp');
            if (in_array($fileExtension, $allowedfileExtensions)) {
                // Validate actual file content (MIME type) — ป้องกัน webshell upload
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['review_image']['tmp_name']);
                finfo_close($finfo);
                $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($mime, $allowed_mimes)) {
                    $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>'ประเภทไฟล์ไม่ถูกต้อง อนุญาตเฉพาะรูปภาพเท่านั้น', 'icon'=>'error'];
                    header('Location: my_orders.php'); exit();
                }
                // Validate file size — จำกัดขนาดไฟล์สูงสุด 5MB
                if ($_FILES['review_image']['size'] > 5 * 1024 * 1024) {
                    $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>'ไฟล์รูปภาพมีขนาดใหญ่เกินไป (สูงสุด 5MB)', 'icon'=>'error'];
                    header('Location: my_orders.php'); exit();
                }
                $newFileName = 'review_' . uniqid() . '.' . $fileExtension;
                $uploadFileDir = 'uploads/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }
                $dest_path = $uploadFileDir . $newFileName;
                if(move_uploaded_file($fileTmpPath, $dest_path)) {
                    $review_image = $dest_path;
                }
            }
        }
        $review_image_val = $review_image ? "'" . mysqli_real_escape_string($conn, $review_image) . "'" : "NULL";
        $sql_review = "INSERT INTO product_reviews (product_id, user_id, rating, comment, image) VALUES ('$pid', '$uid', '$rating', '$comment', $review_image_val)";
        if(mysqli_query($conn, $sql_review)) {
             $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'ขอบคุณสำหรับการรีวิว!', 'icon'=>'success'];
             log_admin_action($conn, 'เขียนรีวิว', "ลูกค้าเขียนรีวิวให้คะแนนสินค้า ID #$pid คะแนน: $rating ดาว (เขียนรีวิวจากหน้าประวัติสั่งซื้อ)", $uid, $_SESSION['fullname']);
             
             // Admin notification
             $cust_name = mysqli_real_escape_string($conn, $_SESSION['fullname'] ?? 'ลูกค้า');
             $product_q = mysqli_query($conn, "SELECT name FROM products WHERE id = '$pid'");
             $product_name = mysqli_fetch_assoc($product_q)['name'] ?? 'สินค้า';
             $product_name_escaped = mysqli_real_escape_string($conn, $product_name);
             $notif_title = mysqli_real_escape_string($conn, "มีรีวิวสินค้าใหม่");
             $notif_msg = mysqli_real_escape_string($conn, "คุณ $cust_name ได้เขียนรีวิวให้สินค้า '$product_name_escaped' คะแนน: $rating ดาว");
             $notif_url = "admin_reviews.php";
             mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, url, is_read, is_admin) VALUES (NULL, '$notif_title', '$notif_msg', '$notif_url', 0, 1)");
        } else {
             $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>'เกิดข้อผิดพลาดในการบันทึกรีวิว', 'icon'=>'error'];
        }
    }
    header("Location: my_orders.php");
    exit();
}

// --- Logic: ลูกค้ายกเลิกออเดอร์ ---
if (isset($_GET['cancel_my_order'])) {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
    $oid = mysqli_real_escape_string($conn, $_GET['cancel_my_order']);
    
    // เช็คความเป็นเจ้าของ + สถานะ pending
    $check = mysqli_query($conn, "SELECT id FROM orders WHERE id='$oid' AND user_id='$user_id' AND status='pending'");
    
    if (mysqli_num_rows($check) > 0) {
        // คืนแต้มสะสม
        $order_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT points_spent FROM orders WHERE id='$oid' AND user_id='$user_id'"));
        $points_spent = isset($order_data['points_spent']) ? intval($order_data['points_spent']) : 0;
        if ($points_spent > 0) {
            mysqli_query($conn, "UPDATE users SET points = points + $points_spent WHERE id='$user_id'");
        }

        // คืนสต็อก
        $items = mysqli_query($conn, "SELECT product_id, quantity FROM order_items WHERE order_id='$oid'");
        while ($item = mysqli_fetch_assoc($items)) {
            mysqli_query($conn, "UPDATE products SET stock = stock + {$item['quantity']} WHERE id='{$item['product_id']}'");
        }
        
        // เปลี่ยนสถานะ
        if(mysqli_query($conn, "UPDATE orders SET status = 'cancelled' WHERE id='$oid'")){
            $action_status = "success";
            log_admin_action($conn, 'ยกเลิกออเดอร์', "ลูกค้ายกเลิกคำสั่งซื้อ #$oid ด้วยตนเอง", $user_id, $_SESSION['fullname']);
            if ($points_spent > 0) {
                mysqli_query($conn, "INSERT INTO point_history (user_id, points, description) VALUES ('$user_id', '$points_spent', 'ได้รับคืนคะแนนสะสมจากการยกเลิกออเดอร์ #$oid')");
            }
        }
    } else {
        $action_status = "error";
    }
}

$page_title = "ติดตามคำสั่งซื้อ | Por Mae Bet Taled";
$extra_css = "
<style>
    /* การ์ดออเดอร์ */
    .order-card {
        border: none; border-radius: 16px; background: white;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 25px;
        overflow: hidden; transition: all 0.3s ease;
    }
    .order-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.06); }
    .order-card.cancelled { opacity: 0.7; filter: grayscale(100%); }
    
    .card-header-custom {
        padding: 15px 20px; background: #fff; border-bottom: 1px dashed #eee;
        display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;
    }
    
    /* Badges */
    .status-badge { padding: 6px 12px; border-radius: 30px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
    .status-pending { background: #fff8e1; color: #f59e0b; }
    .status-approved { background: #e0f2fe; color: #0ea5e9; }
    .status-shipping { background: #dcfce7; color: #16a34a; }
    .status-completed { background: #d1e7dd; color: #0f5132; }
    .status-cancelled { background: #f3f4f6; color: #6b7280; }

    /* Timeline */
    .step-progress { display: flex; justify-content: space-between; position: relative; margin: 30px 0; padding: 0 10px; }
    .step-progress::before { content: ''; position: absolute; top: 14px; left: 30px; right: 30px; height: 4px; background: #e5e7eb; z-index: 1; border-radius: 2px; }
    .step-progress-line { position: absolute; top: 14px; left: 30px; height: 4px; background: #AEE2FF; z-index: 1; transition: width 0.5s ease; border-radius: 2px; }
    .step-item { position: relative; z-index: 2; text-align: center; width: 25%; }
    .step-circle { width: 32px; height: 32px; background: #fff; border: 3px solid #e5e7eb; border-radius: 50%; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; color: #cbd5e1; font-size: 14px; transition: 0.3s; position: relative; z-index: 3; }
    .step-item.active .step-circle { background: #AEE2FF; border-color: #AEE2FF; color: white; box-shadow: 0 0 0 4px rgba(174,226,255,0.2); }
    .step-text { font-size: 0.8rem; color: #9ca3af; font-weight: 500; }
    .step-item.active .step-text { color: #555; font-weight: 700; }
    
    /* Product List */
    .product-item { display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid #f9f9f9; }
    .product-item:last-child { border-bottom: none; }
    .product-img { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; border: 1px solid #eee; margin-right: 15px; }
    
    /* Buttons */
    .btn-action { font-size: 0.8rem; padding: 5px 15px; border-radius: 20px; text-decoration: none; display: inline-block; cursor: pointer; transition: 0.2s; }
    .btn-view-slip { background: #f3f4f6; color: #4b5563; }
    .btn-view-slip:hover { background: #e5e7eb; color: #1f2937; }
    .btn-cancel { border: 1px solid #ef4444; color: #ef4444; background: white; }
    .btn-cancel:hover { background: #ef4444; color: white; }
    .btn-review { border: 1px solid var(--slate-dark); color: var(--slate-dark); background: white; margin-left: 10px; }
    .btn-review:hover { background: var(--slate-dark); color: white; }
    .btn-track { background: #e0f2fe; color: #0ea5e9; border: 1px solid #bae6fd; }
    .btn-track:hover { background: #0ea5e9; color: white; }
    .btn-reorder { border: 1px solid var(--blue-hover); color: #060913; background: var(--blue-main); margin-left: 10px; font-weight: 600; }
    .btn-reorder:hover { background: var(--blue-hover); border-color: var(--blue-hover); color: white; }

    .tracking-box { background: #fdf2f8; border: 1px dashed var(--blue-hover); border-radius: 10px; padding: 10px; text-align: center; margin-top: 15px; }
    .tracking-number { font-size: 1rem; font-weight: 700; color: var(--blue-hover); letter-spacing: 1px; }
    .hidden { display: none !important; }

    /* Dark Theme Overrides for my_orders.php */
    body.dark-theme .order-card {
        background: rgba(13, 20, 38, 0.65) !important;
        border: 1px solid rgba(56, 189, 248, 0.15) !important;
        box-shadow: none !important;
    }
    body.dark-theme .card-header-custom {
        background: transparent !important;
        border-bottom: 1px dashed rgba(255, 255, 255, 0.08) !important;
    }
    body.dark-theme .step-progress::before {
        background: rgba(255, 255, 255, 0.08) !important;
    }
    body.dark-theme .step-progress-line {
        background: var(--blue-main) !important;
    }
    body.dark-theme .step-circle {
        background: #0b1329 !important;
        border-color: rgba(255, 255, 255, 0.08) !important;
        color: #475569 !important;
    }
    body.dark-theme .step-item.active .step-circle {
        background: var(--blue-main) !important;
        border-color: var(--blue-main) !important;
        color: #060913 !important;
        box-shadow: 0 0 15px rgba(56, 189, 248, 0.4) !important;
    }
    body.dark-theme .step-text {
        color: #475569 !important;
    }
    body.dark-theme .step-item.active .step-text {
        color: #f8fafc !important;
    }
    body.dark-theme .product-item {
        border-bottom-color: rgba(255, 255, 255, 0.05) !important;
    }
    body.dark-theme .product-img {
        border-color: rgba(255, 255, 255, 0.08) !important;
    }
    body.dark-theme .btn-view-slip {
        background: rgba(255, 255, 255, 0.05) !important;
        color: #94a3b8 !important;
    }
    body.dark-theme .btn-view-slip:hover {
        background: rgba(255, 255, 255, 0.1) !important;
        color: #f8fafc !important;
    }
    body.dark-theme .btn-cancel {
        background: transparent !important;
        border-color: #ef4444 !important;
        color: #f87171 !important;
    }
    body.dark-theme .btn-cancel:hover {
        background: #ef4444 !important;
        color: #ffffff !important;
    }
    body.dark-theme .btn-review {
        border-color: var(--blue-main) !important;
        color: var(--blue-main) !important;
        background: transparent !important;
    }
    body.dark-theme .btn-review:hover {
        background: var(--blue-main) !important;
        color: #060913 !important;
    }
    body.dark-theme .btn-track {
        background: rgba(56, 189, 248, 0.15) !important;
        color: var(--blue-main) !important;
        border-color: rgba(56, 189, 248, 0.3) !important;
    }
    body.dark-theme .btn-track:hover {
        background: var(--blue-main) !important;
        color: #060913 !important;
    }
    body.dark-theme .tracking-box {
        background: rgba(56, 189, 248, 0.03) !important;
        border-color: rgba(56, 189, 248, 0.15) !important;
    }
    body.dark-theme .tracking-number {
        color: var(--blue-main) !important;
    }
    body.dark-theme .btn-reorder {
        border-color: var(--blue-main) !important;
        color: #060913 !important;
        background: var(--blue-main) !important;
    }
    body.dark-theme .btn-reorder:hover {
        background: var(--blue-hover) !important;
        border-color: var(--blue-hover) !important;
        color: white !important;
    }
</style>
";
include 'header.php';
date_default_timezone_set('Asia/Bangkok');
?>


<div class="container py-5">
    <div class="row">
                <div class="col-lg-3">
            <a class="btn btn-light w-100 d-lg-none mb-3 border shadow-sm fw-bold text-start" 
               data-bs-toggle="collapse" 
               href="#userSidebar" 
               role="button" 
               aria-expanded="false" 
               aria-controls="userSidebar">
                <i class="bi bi-list me-2"></i> เมนูสมาชิก (คลิกเพื่อเปิด)
            </a>
            
            <div class="collapse d-lg-block" id="userSidebar">
                <?php include 'user_sidebar.php'; ?>
            </div>
        </div>

        <div class="col-lg-9">
            <h3 class="fw-bold mb-4">📦 ประวัติคำสั่งซื้อ</h3>

            <?php 
            // Calculate total rows first
            $count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE user_id = '$user_id'");
            $count_row = mysqli_fetch_assoc($count_query);
            $total_rows = intval($count_row['total']);

            // Pagination calculations
            $limit = isset($_GET['limit']) ? max(10, min(100, intval($_GET['limit']))) : 10;
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $total_pages = ceil($total_rows / $limit);
            if ($page > $total_pages && $total_pages > 0) {
                $page = $total_pages;
            }
            $offset = ($page - 1) * $limit;

            $sql = "SELECT * FROM orders WHERE user_id = '$user_id' ORDER BY id DESC LIMIT $limit OFFSET $offset";
            $res = mysqli_query($conn, $sql);

            if (mysqli_num_rows($res) > 0):
                while($row = mysqli_fetch_assoc($res)): 
                    $oid = $row['id'];
                    $status = $row['status'];
                    
                    // Logic Timeline (4 Steps)
                    $s1 = ($status != 'cancelled') ? 'active' : ''; 
                    $s2 = ($status == 'approved' || $status == 'shipping' || $status == 'completed') ? 'active' : '';
                    $s3 = ($status == 'shipping' || $status == 'completed') ? 'active' : '';
                    $s4 = ($status == 'completed') ? 'active' : '';
            ?>
            
            <div class="order-card animate__animated animate__fadeInUp <?= $status == 'cancelled' ? 'cancelled' : '' ?>">
                <div class="card-header-custom">
                    <div>
                        <div class="fw-bold">Order #<?= str_pad($row['id'], 5, '0', STR_PAD_LEFT) ?></div>
                        <div class="text-muted small" style="font-size: 0.75rem;"><i class="bi bi-clock"></i> <?= date('d/m/Y H:i', strtotime($row['order_date'])) ?></div>
                    </div>
                    <div>
                        <?php 
                        if($status == 'pending') echo '<span class="status-badge status-pending"><i class="bi bi-hourglass-split"></i> รอตรวจสอบ</span>';
                        elseif($status == 'approved') echo '<span class="status-badge status-approved"><i class="bi bi-check2-circle"></i> เตรียมส่ง</span>';
                        elseif($status == 'shipping') echo '<span class="status-badge status-shipping"><i class="bi bi-truck"></i> ส่งแล้ว</span>';
                        elseif($status == 'completed') echo '<span class="status-badge status-completed"><i class="bi bi-check-circle-fill"></i> สำเร็จ</span>';
                        else echo '<span class="status-badge status-cancelled"><i class="bi bi-x-circle"></i> ยกเลิก</span>';
                        ?>
                    </div>
                </div>

                <div class="p-3">
                    <?php if($status != 'cancelled'): ?>
                        <?php 
                        $progress_width = match($status) {
                            'pending' => '0%',
                            'approved' => '33%',
                            'shipping' => '66%',
                            'completed' => '100%',
                            default => '0%'
                        };
                        ?>
                        <div class="step-progress">
                            <div class="step-progress-line" style="width: <?= $progress_width ?>;"></div>
                            <div class="step-item <?= $s1 ?>"><div class="step-circle"><i class="bi bi-cart-check"></i></div><div class="step-text">สั่งซื้อ</div></div>
                            <div class="step-item <?= $s2 ?>"><div class="step-circle"><i class="bi bi-box-seam"></i></div><div class="step-text">เตรียมของ</div></div>
                            <div class="step-item <?= $s3 ?>"><div class="step-circle"><i class="bi bi-truck"></i></div><div class="step-text">ขนส่ง</div></div>
                            <div class="step-item <?= $s4 ?>"><div class="step-circle"><i class="bi bi-check-circle"></i></div><div class="step-text">สำเร็จ</div></div>
                        </div>
 
                        <?php if(($status == 'shipping' || $status == 'completed') && !empty($row['tracking_no'])): ?>
                            <?php
                            $carrier_code = $row['shipping_carrier'] ?? 'other';
                            $carrier_label = match($carrier_code) {
                                'thailandpost' => 'ไปรษณีย์ไทย',
                                'kerry', 'kex' => 'KEX Express',
                                'flash' => 'Flash Express',
                                'jnt' => 'J&T Express',
                                default => 'บริการขนส่งหลัก'
                            };
                            $track_url = match($carrier_code) {
                                'thailandpost' => "https://track.thailandpost.co.th/?trackNumber=" . htmlspecialchars($row['tracking_no']),
                                'kerry', 'kex' => "https://th.kex-express.com/th/track/?track=" . htmlspecialchars($row['tracking_no']),
                                'flash' => "https://www.flashexpress.co.th/tracking/?se=" . htmlspecialchars($row['tracking_no']),
                                'jnt' => "https://www.jtexpress.co.th/index/query/gzquery.html?bills=" . htmlspecialchars($row['tracking_no']),
                                default => "https://t.17track.net/th#nums=" . htmlspecialchars($row['tracking_no'])
                            };
                            ?>
                            <div class="tracking-box">
                                <div class="small text-muted mb-1">เลขพัสดุ (Tracking) - <b><?= $carrier_label ?></b></div>
                                <div class="tracking-number">
                                    <?= htmlspecialchars($row['tracking_no']) ?> 
                                    <i class="bi bi-copy ms-2 text-secondary" style="cursor:pointer;" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($row['tracking_no']) ?>'); Swal.fire({toast:true, position:'top-end', icon:'success', title:'คัดลอกเลขพัสดุแล้ว', showConfirmButton:false, timer:1000})"></i>
                                </div>
                                <div class="mt-2">
                                    <a href="<?= $track_url ?>" target="_blank" class="btn btn-sm btn-outline-custom rounded-pill px-3" style="font-size: 0.75rem;">
                                         <i class="bi bi-search"></i> ติดตามสถานะพัสดุด่วน (<?= $carrier_label ?>)
                                     </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <hr class="text-muted opacity-25 my-3">

                    <div class="mb-3">
                        <?php 
                        $items_sql = "SELECT oi.*, p.name, p.image FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = '$oid'";
                        $items_res = mysqli_query($conn, $items_sql);
                        while($item = mysqli_fetch_assoc($items_res)):
                        ?>
                        <div class="product-item">
                            <a href="product_detail.php?id=<?= $item['product_id'] ?>" class="text-decoration-none d-flex align-items-center" style="flex:1; min-width:0; color:inherit;">
                                <img src="<?= htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8') ?>" class="product-img" alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <div style="flex:1; min-width:0;">
                                    <div class="fw-bold text-dark text-truncate small"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if(!empty($item['selected_option'])): ?>
                                        <small class="text-muted bg-light border px-2 py-0 rounded-pill d-inline-block mt-1 mb-1">
                                            <?= htmlspecialchars($item['selected_option'], ENT_QUOTES, 'UTF-8') ?>
                                        </small>
                                        <br>
                                    <?php endif; ?>
                                    <span class="small text-muted" style="font-size:0.75rem;">x<?= $item['quantity'] ?></span>
                                </div>
                            </a>
                            
                            <div class="text-end">
                                <div class="fw-bold small mb-1">฿<?= number_format($item['price'] * $item['quantity']) ?></div>
                                
                                <?php if($status == 'completed' || $status == 'shipping'): ?>
                                    <?php
                                    $check_reviewed = mysqli_query($conn, "SELECT id FROM product_reviews WHERE user_id = '$user_id' AND product_id = '{$item['product_id']}'");
                                    $has_reviewed = mysqli_num_rows($check_reviewed) > 0;
                                    if ($has_reviewed):
                                    ?>
                                        <span class="text-success small"><i class="bi bi-check-circle-fill"></i> รีวิวแล้ว</span>
                                    <?php else: ?>
                                        <button type="button" onclick="openReviewModal('<?= $item['product_id'] ?>', '<?= htmlspecialchars($item['name']) ?>', '<?= htmlspecialchars($item['image']) ?>')" class="btn-action btn-review border-0 text-decoration-none">
                                            <i class="bi bi-star"></i> รีวิว
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>

                    <?php if (intval($row['points_spent']) > 0 || intval($row['points_earned']) > 0): ?>
                        <div class="mb-3 p-2 bg-light rounded-3 small">
                            <?php if (intval($row['points_spent']) > 0): ?>
                                <div class="text-muted d-flex justify-content-between mb-1">
                                    <span>🪙 ใช้แต้มแลกส่วนลด:</span>
                                    <span class="fw-bold text-danger">-<?= number_format($row['points_spent']) ?> แต้ม (ลด ฿<?= number_format($row['points_discount'], 2) ?>)</span>
                                </div>
                            <?php endif; ?>
                            <?php if (intval($row['points_earned']) > 0): ?>
                                <div class="text-muted d-flex justify-content-between">
                                    <span>🪙 แต้มที่จะได้รับ:</span>
                                    <?php if ($status == 'completed'): ?>
                                        <span class="fw-bold text-success">+<?= number_format($row['points_earned']) ?> แต้ม (ได้รับแล้ว)</span>
                                    <?php elseif ($status == 'cancelled'): ?>
                                        <span class="text-secondary text-decoration-line-through">+<?= number_format($row['points_earned']) ?> แต้ม (ยกเลิกแล้ว)</span>
                                    <?php else: ?>
                                        <span class="fw-bold text-warning">+<?= number_format($row['points_earned']) ?> แต้ม (ได้รับเมื่อส่งสำเร็จ)</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="bg-light rounded-3 p-3 mb-3 d-flex justify-content-between align-items-center">
                        <span class="text-muted small">ยอดสุทธิ</span>
                        <span class="fw-bold fs-5 text-blue" style="color:var(--blue-dark)">฿<?= number_format($row['final_price'] > 0 ? $row['final_price'] : $row['total_price'], 2) ?></span>
                    </div>

                    <div class="text-end">
                        <span onclick="reorder(<?= $row['id'] ?>)" class="btn-action btn-reorder me-2"><i class="bi bi-arrow-repeat"></i> สั่งซื้ออีกครั้ง</span>
                        
                        <a href="print_invoice.php?id=<?= $row['id'] ?>" target="_blank" class="btn-action btn-view-slip me-2 text-decoration-none">
                            <i class="bi bi-printer"></i> ใบเสร็จ
                        </a>

                        <?php if(in_array($status, ['approved', 'shipping', 'completed'])): ?>
                            <span onclick='showTrackingModal(<?= json_encode([
                                "id" => str_pad($row["id"], 5, "0", STR_PAD_LEFT),
                                "order_date" => $row["order_date"],
                                "status" => $status,
                                "tracking_no" => $row["tracking_no"] ?? "",
                                "shipping_carrier" => $row["shipping_carrier"] ?? "other"
                            ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="btn-action btn-track me-2"><i class="bi bi-geo-alt-fill"></i> ติดตามพัสดุ</span>
                        <?php endif; ?>

                        <?php if(!empty($row['payment_slip'])): ?>
                            <span onclick="viewSlip('uploads/<?= htmlspecialchars($row['payment_slip'], ENT_QUOTES, 'UTF-8') ?>')" class="btn-action btn-view-slip me-2"><i class="bi bi-image"></i> สลิป</span>
                        <?php endif; ?>
                        
                        <?php if($status == 'pending'): ?>
                            <span onclick="confirmCancel(<?= $row['id'] ?>)" class="btn-action btn-cancel">ยกเลิก</span>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
            <?php endwhile; else: ?>
                <div class="text-center py-5 bg-white rounded-4 shadow-sm">
                    <i class="bi bi-basket display-1 text-muted opacity-25"></i>
                    <h5 class="text-muted mt-3">ไม่มีประวัติการสั่งซื้อ</h5>
                    <a href="index.php" class="btn btn-sm btn-outline-danger rounded-pill mt-3 px-4">ไปช้อปปิ้งเลย</a>
                </div>
            <?php endif; ?>

            <!-- การแบ่งหน้า (Pagination) -->
            <?= render_pagination_controls($total_rows, $limit, $page, $offset) ?>
        </div>
    </div>
</div>

<div class="modal fade" id="slipModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content bg-transparent border-0"><div class="modal-body p-0 text-center"><button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button><img id="slipImage" src="" class="img-fluid rounded-3 shadow-lg" style="max-height: 85vh;"></div></div></div></div>

<!-- Tracking Modal -->
<div class="modal fade" id="trackingModal" tabindex="-1" aria-labelledby="trackingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-geo-alt-fill text-blue me-2"></i>ติดตามพัสดุ ออเดอร์ #<span id="track-order-id"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div id="track-no-box" class="bg-light rounded-3 p-3 mb-4 d-none text-center">
                    <span class="small text-muted d-block fw-bold text-start">เลขพัสดุ (Tracking Number)</span>
                    <span class="fw-bold fs-5 text-blue d-block my-1" id="track-no-val"></span>
                    <a id="track-link-btn" href="" target="_blank" class="btn btn-sm btn-blue rounded-pill px-3 mt-2" style="font-size: 0.8rem; font-weight: 500;">
                        <i class="bi bi-box-seam"></i> ติดตามพัสดุผ่านระบบ 17Track
                    </a>
                </div>
                
                <div class="tracking-timeline">
                    <!-- Step 1 -->
                    <div class="tracking-step" id="t-step-1">
                        <h6 class="fw-bold mb-1 text-dark">รับออเดอร์แล้ว (Order Accepted)</h6>
                        <p class="text-muted small mb-0 font-monospace" id="t-time-1">-</p>
                        <p class="text-muted small">ออเดอร์เข้าสู่ระบบร้านค้าและได้รับการบันทึกข้อมูลเรียบร้อย</p>
                    </div>
                    <!-- Step 2 -->
                    <div class="tracking-step" id="t-step-2">
                        <h6 class="fw-bold mb-1 text-dark">คลังสินค้าทำการจัดส่ง (Packed & Dispatched)</h6>
                        <p class="text-muted small mb-0 font-monospace" id="t-time-2">-</p>
                        <p class="text-muted small">สินค้าของคุณได้รับการแพ็คและส่งมอบให้เจ้าหน้าที่ขนส่งแล้ว</p>
                    </div>
                    <!-- Step 3 -->
                    <div class="tracking-step" id="t-step-3">
                        <h6 class="fw-bold mb-1 text-dark">อยู่ระหว่างขนส่ง (In Transit)</h6>
                        <p class="text-muted small mb-0 font-monospace" id="t-time-3">-</p>
                        <p class="text-muted small">พัสดุกำลังนำส่งโดยพนักงานขนส่งไปยังปลายทาง</p>
                    </div>
                    <!-- Step 4 -->
                    <div class="tracking-step" id="t-step-4">
                        <h6 class="fw-bold mb-1 text-dark">จัดส่งสำเร็จ (Delivered)</h6>
                        <p class="text-muted small mb-0 font-monospace" id="t-time-4">-</p>
                        <p class="text-muted small">พัสดุจัดส่งถึงผู้รับเรียบร้อยแล้ว ขอบคุณที่ไว้วางใจเรา</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-blue rounded-pill px-4 text-white fw-bold" data-bs-dismiss="modal">ตกลง</button>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-star-fill text-warning me-2"></i>รีวิวสินค้า</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="modalReviewForm">
                <?= get_csrf_input() ?>
                <div class="modal-body py-4">
                    <input type="hidden" name="product_id" id="review-product-id">
                    <input type="hidden" name="rating" id="modal-rating-val" value="5">
                    
                    <div class="d-flex align-items-center gap-3 bg-light p-3 rounded-3 mb-4">
                        <img id="review-product-img" src="" class="rounded" style="width: 60px; height: 60px; object-fit: cover; border: 1px solid #eee;">
                        <div class="fw-bold text-dark text-truncate small" id="review-product-name">ชื่อสินค้า</div>
                    </div>
                    
                    <div class="mb-3 text-center">
                        <label class="small text-muted mb-2 d-block fw-bold">ระดับความพึงพอใจ</label>
                        <div class="d-flex justify-content-center gap-2">
                            <i class="bi bi-star-fill text-warning fs-2 star-select" data-value="1" style="cursor: pointer;"></i>
                            <i class="bi bi-star-fill text-warning fs-2 star-select" data-value="2" style="cursor: pointer;"></i>
                            <i class="bi bi-star-fill text-warning fs-2 star-select" data-value="3" style="cursor: pointer;"></i>
                            <i class="bi bi-star-fill text-warning fs-2 star-select" data-value="4" style="cursor: pointer;"></i>
                            <i class="bi bi-star-fill text-warning fs-2 star-select" data-value="5" style="cursor: pointer;"></i>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="small text-muted mb-1 d-block fw-bold">ความคิดเห็นของคุณ</label>
                        <textarea name="comment" class="form-control border shadow-sm rounded-3" rows="3" placeholder="บอกเล่าประสบการณ์ของคุณหลังการใช้งาน..." required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="small text-muted mb-1 d-block fw-bold"><i class="bi bi-camera me-1"></i>แนบรูปภาพสินค้า (รูปถ่ายจริง - ทางเลือก)</label>
                        <input type="file" name="review_image" class="form-control border shadow-sm rounded-3" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="submit_modal_review" class="btn btn-blue rounded-pill px-4 text-white fw-bold">ส่งรีวิว</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function viewSlip(url){ new bootstrap.Modal(document.getElementById('slipModal')).show(); document.getElementById('slipImage').src = url; }
    function confirmCancel(id){ Swal.fire({title:'ยืนยันยกเลิก?',icon:'warning',showCancelButton:true,confirmButtonColor:'#ef4444',confirmButtonText:'ยกเลิกออเดอร์'}).then((r)=>{if(r.isConfirmed) window.location.href='?cancel_my_order='+id+'&csrf_token=<?= get_csrf_token() ?>';}) }
    
    let isReordering = false;
    function reorder(orderId) {
        if (isReordering) return;
        isReordering = true;
        
        Swal.fire({
            title: 'กำลังเตรียมสินค้า...',
            text: 'กรุณารอสักครู่ ระบบกำลังนำสินค้าเข้าตะกร้า',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        const fd = new FormData();
        fd.append('action', 'reorder');
        fd.append('order_id', orderId);
        fd.append('csrf_token', '<?= get_csrf_token() ?>');
        
        fetch('ajax.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            isReordering = false;
            Swal.close();
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ!',
                    text: data.message,
                    confirmButtonColor: '#AEE2FF',
                    timer: 2000
                }).then(() => {
                    // โหลดตะกร้าสไลด์ด้านข้างใหม่และสั่งเปิด
                    if (typeof window.loadCartDrawer === 'function' && typeof window.toggleCartDrawer === 'function') {
                        window.loadCartDrawer();
                        const drawer = document.getElementById('cartDrawer');
                        if (drawer && !drawer.classList.contains('show')) {
                            window.toggleCartDrawer();
                        }
                    }
                });
            } else {
                Swal.fire('เกิดข้อผิดพลาด', data.message, 'error');
            }
        })
        .catch(err => {
            isReordering = false;
            Swal.close();
            console.error(err);
            Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
        });
    }
    
    function formatThaiDate(dateObj) {
        const months = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
        const day = dateObj.getDate();
        const month = months[dateObj.getMonth()];
        const year = dateObj.getFullYear() + 543;
        const hours = String(dateObj.getHours()).padStart(2, '0');
        const minutes = String(dateObj.getMinutes()).padStart(2, '0');
        return `${day} ${month} ${year} เวลา ${hours}:${minutes} น.`;
    }

    function showTrackingModal(orderInfo) {
        document.getElementById('track-order-id').innerText = orderInfo.id;
        const trackNoBox = document.getElementById('track-no-box');
        const trackNoVal = document.getElementById('track-no-val');
        const trackLinkBtn = document.getElementById('track-link-btn');
        
        if (orderInfo.tracking_no && orderInfo.tracking_no.trim() !== '') {
            trackNoVal.innerHTML = orderInfo.tracking_no + ` <i class="bi bi-copy ms-2 text-secondary" style="cursor:pointer;" onclick="navigator.clipboard.writeText('${orderInfo.tracking_no}'); Swal.fire({toast:true, position:'top-end', icon:'success', title:'คัดลอกเลขพัสดุแล้ว', showConfirmButton:false, timer:1000})"></i>`;
            trackNoBox.classList.remove('d-none');
            if (trackLinkBtn) {
                const carrier = orderInfo.shipping_carrier || 'other';
                let url = `https://t.17track.net/th#nums=${orderInfo.tracking_no}`;
                let label = 'ติดตามพัสดุผ่านระบบ 17Track';
                if (carrier === 'thailandpost') {
                    url = `https://track.thailandpost.co.th/?trackNumber=${orderInfo.tracking_no}`;
                    label = 'ติดตามพัสดุผ่าน ไปรษณีย์ไทย';
                } else if (carrier === 'kerry' || carrier === 'kex') {
                    url = `https://th.kex-express.com/th/track/?track=${orderInfo.tracking_no}`;
                    label = 'ติดตามพัสดุผ่าน KEX Express';
                } else if (carrier === 'flash') {
                    url = `https://www.flashexpress.co.th/tracking/?se=${orderInfo.tracking_no}`;
                    label = 'ติดตามพัสดุผ่าน Flash Express';
                } else if (carrier === 'jnt') {
                    url = `https://www.jtexpress.co.th/index/query/gzquery.html?bills=${orderInfo.tracking_no}`;
                    label = 'ติดตามพัสดุผ่าน J&T Express';
                }
                trackLinkBtn.href = url;
                trackLinkBtn.innerHTML = `<i class="bi bi-box-seam"></i> ${label}`;
            }
        } else {
            trackNoBox.classList.add('d-none');
        }
        
        // Calculate dates
        const orderDate = new Date(orderInfo.order_date.replace(/-/g, '/'));
        const now = new Date();
        
        const step1Time = new Date(orderDate.getTime());
        const step2Time = new Date(orderDate.getTime() + 4 * 60 * 60 * 1000);
        const step3Time = new Date(orderDate.getTime() + 12 * 60 * 60 * 1000);
        const step4Time = new Date(orderDate.getTime() + 24 * 60 * 60 * 1000);
        
        // Display times
        document.getElementById('t-time-1').innerText = formatThaiDate(step1Time);
        document.getElementById('t-time-2').innerText = formatThaiDate(step2Time);
        document.getElementById('t-time-3').innerText = formatThaiDate(step3Time);
        document.getElementById('t-time-4').innerText = formatThaiDate(step4Time);
        
        // Reset classes
        const resetStep = (id) => {
            const el = document.getElementById(id);
            if (el) el.classList.remove('active', 'completed');
        };
        resetStep('t-step-1');
        resetStep('t-step-2');
        resetStep('t-step-3');
        resetStep('t-step-4');
        
        // Step 1: Order Accepted - always completed
        document.getElementById('t-step-1').classList.add('completed');
        
        // Step 2: Packed & Dispatched
        if (orderInfo.status === 'approved' || orderInfo.status === 'shipping' || orderInfo.status === 'completed' || now >= step2Time) {
            document.getElementById('t-step-2').classList.add('completed');
        } else {
            document.getElementById('t-step-2').classList.add('active');
        }
        
        // Step 3: In Transit
        if (orderInfo.status === 'shipping' || orderInfo.status === 'completed' || now >= step3Time) {
            document.getElementById('t-step-3').classList.add('completed');
        } else if (document.getElementById('t-step-2').classList.contains('completed')) {
            document.getElementById('t-step-3').classList.add('active');
        }
        
        // Step 4: Delivered
        if (orderInfo.status === 'completed') {
            document.getElementById('t-step-4').classList.add('completed');
        } else if (orderInfo.status === 'shipping' && now >= step3Time) {
            document.getElementById('t-step-4').classList.add('active');
        }
        
        new bootstrap.Modal(document.getElementById('trackingModal')).show();
    }
    
    function openReviewModal(pid, name, img) {
        document.getElementById('review-product-id').value = pid;
        document.getElementById('review-product-name').innerText = name;
        document.getElementById('review-product-img').src = img;
        
        // Reset rating values and stars
        document.getElementById('modal-rating-val').value = 5;
        document.querySelectorAll('.star-select').forEach(star => {
            star.classList.remove('bi-star');
            star.classList.add('bi-star-fill');
        });
        
        // Show modal
        new bootstrap.Modal(document.getElementById('reviewModal')).show();
    }
    
    // Bind star selection click listeners
    document.querySelectorAll('.star-select').forEach(star => {
        star.addEventListener('click', function() {
            const val = parseInt(this.getAttribute('data-value'));
            document.getElementById('modal-rating-val').value = val;
            
            document.querySelectorAll('.star-select').forEach(s => {
                const sVal = parseInt(s.getAttribute('data-value'));
                if (sVal <= val) {
                    s.classList.remove('bi-star');
                    s.classList.add('bi-star-fill');
                } else {
                    s.classList.remove('bi-star-fill');
                    s.classList.add('bi-star');
                }
            });
        });
    });

    <?php if($action_status == 'success'): ?>
        Swal.fire({icon:'success',title:'สำเร็จ',text:'ยกเลิกรายการแล้ว',confirmButtonColor:'#AEE2FF'}).then(()=>{location.href='my_orders.php'});
    <?php endif; ?>

    <?php if(isset($_SESSION['swal'])): ?>
        Swal.fire({
            icon: <?= json_encode($_SESSION['swal']['icon']) ?>,
            title: <?= json_encode($_SESSION['swal']['title']) ?>,
            text: <?= json_encode($_SESSION['swal']['text']) ?>,
            confirmButtonColor: '#AEE2FF'
        });
    <?php unset($_SESSION['swal']); endif; ?>

    function changePageLimit(el) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('limit', el.value);
        urlParams.set('page', '1');
        window.location.href = window.location.pathname + '?' + urlParams.toString();
    }
</script>
</body>
</html>
