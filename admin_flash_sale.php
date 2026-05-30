<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');

// Check Admin Role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    header("Location: index.php"); 
    exit(); 
}

$error_msg = "";
$success_msg = "";

if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $error_msg = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

if (isset($_POST['update_settings'])) {
    $auto_flash_sale = isset($_POST['auto_flash_sale']) ? 1 : 0;
    $auto_flash_discount = intval($_POST['auto_flash_discount']);
    $auto_flash_duration = intval($_POST['auto_flash_duration']);
    
    $upd_s = mysqli_query($conn, "UPDATE shop_settings SET 
        auto_flash_sale = '$auto_flash_sale', 
        auto_flash_discount = '$auto_flash_discount', 
        auto_flash_duration = '$auto_flash_duration' 
        WHERE id = 1");
    if ($upd_s) {
        log_admin_action($conn, 'ตั้งค่า Flash Sale อัตโนมัติ', "อัปเดตการตั้งค่าระบบออโต้: เปิด = $auto_flash_sale, ส่วนลด = $auto_flash_discount%, รอบละ = $auto_flash_duration ชม.");
        $_SESSION['success_msg'] = "บันทึกการตั้งค่าระบบอัตโนมัติสำเร็จ!";
        // Trigger generation check
        checkAndGenerateAutoFlashSale($conn);
        header("Location: admin_flash_sale.php");
        exit();
    } else {
        $error_msg = "บันทึกข้อมูลล้มเหลว: " . mysqli_error($conn);
    }
}

// --- Logic 1: Add Campaign ---
if (isset($_POST['add'])) {
    $product_id = intval($_POST['product_id']);
    $flash_price = floatval($_POST['flash_price']);
    $flash_stock = intval($_POST['flash_stock']);
    $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
    $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);
    
    // Check if start_time is before end_time
    if (strtotime($start_time) >= strtotime($end_time)) {
        $error_msg = "เวลาเริ่มต้นต้องมาก่อนเวลาสิ้นสุด";
    } else {
        // Check for overlapping campaigns on same product
        $overlap = mysqli_query($conn, "SELECT id FROM flash_sales 
            WHERE product_id = '$product_id' 
            AND (
                ('$start_time' BETWEEN start_time AND end_time) OR 
                ('$end_time' BETWEEN start_time AND end_time) OR 
                (start_time BETWEEN '$start_time' AND '$end_time')
            )");
        if (mysqli_num_rows($overlap) > 0) {
            $error_msg = "สินค้านี้มีแคมเปญ Flash Sale ในช่วงเวลาดังกล่าวอยู่แล้ว";
        } else {
            $sql = "INSERT INTO flash_sales (product_id, flash_price, flash_stock, flash_sold, start_time, end_time) 
                    VALUES ('$product_id', '$flash_price', '$flash_stock', 0, '$start_time', '$end_time')";
            if (mysqli_query($conn, $sql)) {
                $p_q = mysqli_query($conn, "SELECT name FROM products WHERE id = '$product_id'");
                $p_name = mysqli_fetch_assoc($p_q)['name'] ?? 'ไม่พบชื่อสินค้า';
                log_admin_action($conn, 'สร้างแคมเปญ Flash Sale', "สร้างแคมเปญสำหรับสินค้า '$p_name' (#$product_id): ราคาพิเศษ = ฿$flash_price, โควตา = $flash_stock ชิ้น, เริ่ม = $start_time, สิ้นสุด = $end_time");
                $_SESSION['success_msg'] = "สร้างแคมเปญ Flash Sale สำเร็จ!";
                header("Location: admin_flash_sale.php");
                exit();
            } else {
                $error_msg = "ไม่สามารถบันทึกข้อมูลได้: " . mysqli_error($conn);
            }
        }
    }
}

if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $fs_q = mysqli_query($conn, "SELECT fs.product_id, p.name FROM flash_sales fs JOIN products p ON fs.product_id = p.id WHERE fs.id = $id");
    $fs_info = mysqli_fetch_assoc($fs_q);
    $p_name = $fs_info['name'] ?? 'ไม่พบชื่อสินค้า';
    $pid = $fs_info['product_id'] ?? 0;
    
    if (mysqli_query($conn, "DELETE FROM flash_sales WHERE id = $id")) {
        log_admin_action($conn, 'ลบแคมเปญ Flash Sale', "ลบแคมเปญ Flash Sale รหัส #$id ของสินค้า '$p_name' (รหัสสินค้า #$pid)");
        $_SESSION['success_msg'] = "ลบแคมเปญเรียบร้อยแล้ว";
    } else {
        $_SESSION['error_msg'] = "ลบแคมเปญไม่สำเร็จ";
    }
    header("Location: admin_flash_sale.php");
    exit();
}

if (isset($_GET['cancel_campaign'])) {
    $id = intval($_GET['cancel_campaign']);
    $fs_q = mysqli_query($conn, "SELECT fs.product_id, p.name FROM flash_sales fs JOIN products p ON fs.product_id = p.id WHERE fs.id = $id");
    $fs_info = mysqli_fetch_assoc($fs_q);
    $p_name = $fs_info['name'] ?? 'ไม่พบชื่อสินค้า';
    $pid = $fs_info['product_id'] ?? 0;
    
    // Set end_time to current time
    if (mysqli_query($conn, "UPDATE flash_sales SET end_time = NOW() WHERE id = $id")) {
        log_admin_action($conn, 'บังคับปิดแคมเปญ Flash Sale', "จบแคมเปญ Flash Sale ทันที รหัส #$id สำหรับสินค้า '$p_name' (รหัสสินค้า #$pid)");
        $_SESSION['success_msg'] = "สิ้นสุดแคมเปญดังกล่าวทันทีเรียบร้อยแล้ว";
    } else {
        $_SESSION['error_msg'] = "ไม่สามารถปิดแคมเปญได้";
    }
    header("Location: admin_flash_sale.php");
    exit();
}

// --- Logic 4: Fetch Edit Data ---
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $res = mysqli_query($conn, "SELECT * FROM flash_sales WHERE id = $id");
    $edit_data = mysqli_fetch_assoc($res);
}

// --- Logic 5: Update ---
if (isset($_POST['update'])) {
    $id = intval($_POST['id']);
    $product_id = intval($_POST['product_id']);
    $flash_price = floatval($_POST['flash_price']);
    $flash_stock = intval($_POST['flash_stock']);
    $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
    $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);
    
    if (strtotime($start_time) >= strtotime($end_time)) {
        $error_msg = "เวลาเริ่มต้นต้องมาก่อนเวลาสิ้นสุด";
    } else {
        // Check overlap excluding current campaign
        $overlap = mysqli_query($conn, "SELECT id FROM flash_sales 
            WHERE product_id = '$product_id' 
            AND id != '$id'
            AND (
                ('$start_time' BETWEEN start_time AND end_time) OR 
                ('$end_time' BETWEEN start_time AND end_time) OR 
                (start_time BETWEEN '$start_time' AND '$end_time')
            )");
        if (mysqli_num_rows($overlap) > 0) {
            $error_msg = "สินค้านี้มีแคมเปญ Flash Sale ในช่วงเวลาดังกล่าวอยู่แล้ว";
        } else {
            $sql = "UPDATE flash_sales SET 
                    product_id = '$product_id', 
                    flash_price = '$flash_price', 
                    flash_stock = '$flash_stock', 
                    start_time = '$start_time', 
                    end_time = '$end_time' 
                    WHERE id = $id";
            if (mysqli_query($conn, $sql)) {
                $p_q = mysqli_query($conn, "SELECT name FROM products WHERE id = '$product_id'");
                $p_name = mysqli_fetch_assoc($p_q)['name'] ?? 'ไม่พบชื่อสินค้า';
                log_admin_action($conn, 'แก้ไขแคมเปญ Flash Sale', "แก้ไขแคมเปญ Flash Sale รหัส #$id ของสินค้า '$p_name' (รหัสสินค้า #$product_id): ราคาพิเศษ = ฿$flash_price, โควตา = $flash_stock ชิ้น, เริ่ม = $start_time, สิ้นสุด = $end_time");
                $_SESSION['success_msg'] = "อัปเดตแคมเปญเรียบร้อย!";
                header("Location: admin_flash_sale.php");
                exit();
            } else {
                $error_msg = "อัปเดตแคมเปญล้มเหลว: " . mysqli_error($conn);
            }
        }
    }
}

// Fetch all products for dropdown
$products_list = [];
$p_res = mysqli_query($conn, "SELECT id, name, price FROM products ORDER BY name ASC");
while ($p = mysqli_fetch_assoc($p_res)) {
    $products_list[] = $p;
}

// Fetch shop settings for auto flash sale
$settings_res = mysqli_query($conn, "SELECT auto_flash_sale, auto_flash_discount, auto_flash_duration FROM shop_settings WHERE id = 1");
$shop_s = mysqli_fetch_assoc($settings_res);

// Fetch flash sale campaigns
$campaigns = [];
$sql_c = "SELECT fs.*, p.name as product_name, p.image as product_image, p.price as original_price 
          FROM flash_sales fs 
          JOIN products p ON fs.product_id = p.id 
          ORDER BY fs.id DESC";
$c_res = mysqli_query($conn, $sql_c);
while ($c = mysqli_fetch_assoc($c_res)) {
    $campaigns[] = $c;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการ Flash Sale | Por Mae Bet Taled Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Kanit'; background: #f8f9fa; }
        .text-blue { color: #7FB5FF !important; }
        .bg-blue { background-color: #7FB5FF !important; }
        .btn-blue { background: #7FB5FF; color: white; border: none; border-radius: 12px; font-weight: 500; transition: all 0.3s; }
        .btn-blue:hover { background: #5c9dfc; color: white; box-shadow: 0 4px 15px rgba(127, 181, 255, 0.4); }
        .card-modern { background: white; border: none; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); }
        .flash-badge { font-size: 0.75rem; font-weight: 600; padding: 5px 12px; border-radius: 50px; display: inline-block; white-space: nowrap; text-align: center; min-width: 80px; }
        .badge-active { background: #d1e7dd; color: #0f5132; }
        .badge-scheduled { background: #fff3cd; color: #664d03; }
        .badge-expired { background: #f8d7da; color: #842029; }
        .progress-bar-flash { height: 8px; border-radius: 50px; background: linear-gradient(90deg, #AEE2FF, #7FB5FF); }
        .product-thumbnail { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; }
        @media (min-width: 1200px) {
            .sticky-column-wrapper {
                position: sticky;
                top: 30px;
                z-index: 10;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Menu -->
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

        <!-- Main Content -->
        <div class="col-md-10 p-4 p-md-5">
            <h2 class="fw-bold mb-4">จัดการระบบ Flash Sale <span class="text-blue">⚡</span></h2>
            
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger border-0 rounded-3 shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error_msg ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success border-0 rounded-3 shadow-sm mb-4"><i class="bi bi-check-circle-fill me-2"></i><?= $success_msg ?></div>
            <?php endif; ?>

            <div class="row">
                <!-- Left: Form -->
                <div class="col-xl-4 mb-4">
                    <div class="sticky-column-wrapper">
                        <!-- Create/Edit Campaign Card -->
                        <div class="card-modern p-4">
                            <h5 class="fw-bold mb-4">
                                <?php if ($edit_data): ?>
                                    <i class="bi bi-pencil-square text-warning"></i> แก้ไขแคมเปญ Flash Sale
                                <?php else: ?>
                                    <i class="bi bi-lightning-charge-fill text-blue"></i> สร้างแคมเปญใหม่
                                <?php endif; ?>
                            </h5>
                            
                            <form method="POST" action="admin_flash_sale.php">
                                <?php if ($edit_data): ?>
                                    <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-muted">เลือกสินค้า</label>
                                    <select class="form-select rounded-3" name="product_id" required>
                                        <option value="">-- กรุณาเลือกสินค้า --</option>
                                        <?php foreach ($products_list as $prod): ?>
                                            <option value="<?= $prod['id'] ?>" <?= ($edit_data && $edit_data['product_id'] == $prod['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($prod['name']) ?> (ปกติ ฿<?= number_format($prod['price']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-muted">ราคา Flash Sale (บาท)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-muted">฿</span>
                                        <input type="number" step="0.01" class="form-control" name="flash_price" value="<?= $edit_data ? $edit_data['flash_price'] : '' ?>" required min="0">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-muted">โควตาจำนวน (ชิ้น)</label>
                                    <input type="number" class="form-control" name="flash_stock" value="<?= $edit_data ? $edit_data['flash_stock'] : '' ?>" required min="1">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-muted">เวลาเริ่มต้น</label>
                                    <input type="datetime-local" class="form-control" name="start_time" value="<?= $edit_data ? date('Y-m-d\TH:i', strtotime($edit_data['start_time'])) : '' ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-muted">เวลาสิ้นสุด</label>
                                    <input type="datetime-local" class="form-control" name="end_time" value="<?= $edit_data ? date('Y-m-d\TH:i', strtotime($edit_data['end_time'])) : '' ?>" required>
                                </div>

                                <?php if ($edit_data): ?>
                                    <button type="submit" name="update" class="btn btn-warning w-100 rounded-3 py-2 text-white fw-bold">อัปเดตข้อมูล</button>
                                    <a href="admin_flash_sale.php" class="btn btn-outline-secondary w-100 rounded-3 py-2 mt-2">ยกเลิกแก้ไข</a>
                                <?php else: ?>
                                    <button type="submit" name="add" class="btn btn-blue w-100 rounded-3 py-2 fw-bold">สร้างแคมเปญ</button>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- Auto Flash Settings Card -->
                        <div class="card-modern p-4 mt-4">
                            <h5 class="fw-bold mb-3"><i class="bi bi-robot text-blue"></i> ระบบรันแคมเปญอัตโนมัติ</h5>
                            <p class="text-muted small mb-3">เมื่อเปิดใช้งาน หากระบบตรวจไม่พบแคมเปญ Flash Sale ที่กำลังรันอยู่ ระบบจะสุ่มเลือกสินค้าขึ้นมาจัดแคมเปญอัตโนมัติทันที</p>
                            
                            <form method="POST" action="admin_flash_sale.php">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="auto_flash_sale" id="autoFlashCheck" value="1" <?= (isset($shop_s['auto_flash_sale']) && $shop_s['auto_flash_sale'] == 1) ? 'checked' : '' ?> style="cursor: pointer; width: 2.2em; height: 1.1em;">
                                    <label class="form-check-label fw-bold text-dark small ms-2" for="autoFlashCheck" style="cursor: pointer;">เปิดใช้งานระบบอัตโนมัติ</label>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-muted">ส่วนลดอัตโนมัติ (%)</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" class="form-control" name="auto_flash_discount" value="<?= isset($shop_s['auto_flash_discount']) ? $shop_s['auto_flash_discount'] : '20' ?>" required min="10" max="85">
                                        <span class="input-group-text bg-light text-muted">%</span>
                                    </div>
                                    <span class="text-muted" style="font-size: 0.75rem;">สัดส่วนการลดราคาจากราคาปกติ (10% - 85%)</span>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-muted">ระยะเวลาแต่ละรอบ (ชั่วโมง)</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" class="form-control" name="auto_flash_duration" value="<?= isset($shop_s['auto_flash_duration']) ? $shop_s['auto_flash_duration'] : '2' ?>" required min="1" max="24">
                                        <span class="input-group-text bg-light text-muted">ชม.</span>
                                    </div>
                                    <span class="text-muted" style="font-size: 0.75rem;">ระยะเวลานับถอยหลังต่อหนึ่งรอบแคมเปญ</span>
                                </div>
                                
                                <button type="submit" name="update_settings" class="btn btn-blue w-100 btn-sm py-2 fw-bold">บันทึกการตั้งค่าออโต้</button>
                            </form>
                            
                            <div class="mt-3 p-3 bg-light rounded-3 border-0 small text-muted">
                                <div class="fw-bold text-dark mb-2 d-flex align-items-center gap-1">
                                    <i class="bi bi-info-circle-fill text-blue"></i>
                                    <span>เกณฑ์การเลือกสินค้าอัตโนมัติ:</span>
                                </div>
                                <ul class="ps-3 mb-0" style="font-size: 0.8rem; line-height: 1.5;">
                                    <li>เลือกสินค้าที่มีสต็อกมากกว่า 5 ชิ้นเป็นลำดับแรก</li>
                                    <li>ต้องเป็นสินค้าที่ยังไม่มีแคมเปญ Flash Sale อื่นที่ยังไม่สิ้นสุดครอบคลุมอยู่</li>
                                    <li>หากไม่มีสินค้าตรงตามเงื่อนไขด้านบน จะสุ่มเลือกจากสินค้าใดก็ได้ที่มีสต็อก > 0 และไม่มีแคมเปญทับซ้อน</li>
                                    <li>โควตาสินค้าอัตโนมัติคิดเป็น 30% ของสต็อกจริง (ขั้นต่ำ 1 ชิ้น, สูงสุด 10 ชิ้น)</li>
                                    <li>ส่วนลดจะอิงตามร้อยละที่ตั้งค่าไว้ข้างต้น</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: List Table -->
                <div class="col-xl-8">
                    <div class="card-modern p-4">
                        <h5 class="fw-bold mb-4"><i class="bi bi-list-stars text-blue"></i> รายการแคมเปญทั้งหมด</h5>
                        
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr class="text-muted small">
                                        <th>สินค้า</th>
                                        <th class="text-center">ราคาพิเศษ</th>
                                        <th class="text-center">ความคืบหน้ายอดขาย</th>
                                        <th>ช่วงเวลาแคมเปญ</th>
                                        <th class="text-center">สถานะ</th>
                                        <th class="text-center">การจัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($campaigns)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">ไม่พบข้อมูลแคมเปญ Flash Sale</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($campaigns as $camp): 
                                            $now = time();
                                            $start = strtotime($camp['start_time']);
                                            $end = strtotime($camp['end_time']);
                                            
                                            $status_text = "";
                                            $status_class = "";
                                            $is_active = false;
                                            
                                            if ($now < $start) {
                                                $status_text = "Waiting";
                                                $status_class = "badge-scheduled";
                                            } elseif ($now > $end) {
                                                $status_text = "End";
                                                $status_class = "badge-expired";
                                            } elseif ($camp['flash_sold'] >= $camp['flash_stock']) {
                                                $status_text = "Sold Out";
                                                $status_class = "badge-expired";
                                            } else {
                                                $status_text = "Run";
                                                $status_class = "badge-active";
                                                $is_active = true;
                                            }
                                            
                                            $pct = $camp['flash_stock'] > 0 ? ($camp['flash_sold'] / $camp['flash_stock']) * 100 : 0;
                                            if ($pct > 100) $pct = 100;
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="<?= htmlspecialchars($camp['product_image']) ?>" class="product-thumbnail">
                                                        <div>
                                                            <div class="fw-bold text-dark small"><?= htmlspecialchars($camp['product_name']) ?></div>
                                                            <small class="text-muted">ปกติ ฿<?= number_format($camp['original_price']) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <strong class="text-danger">฿<?= number_format($camp['flash_price'], 2) ?></strong>
                                                </td>
                                                <td>
                                                    <div class="small text-muted mb-1 d-flex justify-content-between">
                                                        <span>ขายแล้ว <?= $camp['flash_sold'] ?>/<?= $camp['flash_stock'] ?> ชิ้น</span>
                                                        <span><?= round($pct) ?>%</span>
                                                    </div>
                                                    <div class="progress" style="height: 6px;">
                                                        <div class="progress-bar bg-blue" role="progressbar" style="width: <?= $pct ?>%"></div>
                                                    </div>
                                                </td>
                                                <td style="font-size: 0.85rem;">
                                                    <div><i class="bi bi-play-circle-fill text-success me-1"></i><?= date('d/m/Y H:i', $start) ?></div>
                                                    <div class="mt-1"><i class="bi bi-stop-circle-fill text-danger me-1"></i><?= date('d/m/Y H:i', $end) ?></div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="flash-badge <?= $status_class ?>"><?= $status_text ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <?php if ($is_active): ?>
                                                            <a href="admin_flash_sale.php?cancel_campaign=<?= $camp['id'] ?>" class="btn btn-outline-danger btn-sm rounded-3 px-2" title="จบแคมเปญทันที" onclick="return confirm('คุณต้องการบังคับปิดแคมเปญนี้ทันทีใช่หรือไม่?')">
                                                                <i class="bi bi-stop-fill"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="admin_flash_sale.php?edit=<?= $camp['id'] ?>" class="btn btn-light btn-sm rounded-3 text-warning border" title="แก้ไข">
                                                            <i class="bi bi-pencil-fill"></i>
                                                        </a>
                                                        <a href="admin_flash_sale.php?del=<?= $camp['id'] ?>" class="btn btn-light btn-sm rounded-3 text-danger border" title="ลบ" onclick="return confirm('คุณต้องการลบแคมเปญนี้ใช่หรือไม่?')">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
