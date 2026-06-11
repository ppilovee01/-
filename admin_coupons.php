<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');

// เช็ค Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้องหรือหมดเวลาเซสชัน (Invalid CSRF Token)']);
            exit();
        }
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
}

// --- Logic 1: เพิ่มคูปอง (Add) ---
if (isset($_POST['add'])) {
    $code = strtoupper(mysqli_real_escape_string($conn, $_POST['code']));
    $type = mysqli_real_escape_string($conn, $_POST['discount_type']);
    $val = floatval($_POST['discount_value']);
    $min = floatval($_POST['min_spend'] ?? 0);
    $max_discount = floatval($_POST['max_discount'] ?? 0);
    $usage_limit = intval($_POST['usage_limit'] ?? 0);
    $user_limit = intval($_POST['user_limit'] ?? 0);
    $start_date = !empty($_POST['start_date']) ? date('Y-m-d H:i:s', strtotime($_POST['start_date'])) : null;
    $exp = !empty($_POST['expiry_date']) ? date('Y-m-d H:i:s', strtotime($_POST['expiry_date'])) : '';
    
    $check = mysqli_query($conn, "SELECT id FROM coupons WHERE code='$code'");
    if(mysqli_num_rows($check) > 0) {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'โค้ดนี้มีอยู่แล้ว!']);
            exit();
        }
        echo "<script>alert('โค้ดนี้มีอยู่แล้ว!');</script>";
    } else {
        $start_date_val = $start_date ? "'$start_date'" : "NULL";
        $sql = "INSERT INTO coupons (code, discount_type, discount_value, min_spend, max_discount, usage_limit, user_limit, start_date, expiry_date) 
                VALUES ('$code', '$type', '$val', '$min', '$max_discount', '$usage_limit', '$user_limit', $start_date_val, '$exp')";
        mysqli_query($conn, $sql);
        $new_id = mysqli_insert_id($conn);
        
        log_admin_action($conn, 'สร้างคูปอง', [
            'title' => "สร้างคูปองส่วนลดโค้ด '$code'",
            'changes' => [
                ['field' => 'รหัสคูปอง (Code)', 'old' => '-', 'new' => $code],
                ['field' => 'ประเภทส่วนลด', 'old' => '-', 'new' => $type === 'percent' ? 'ลดเป็นเปอร์เซ็นต์ (%)' : ($type === 'free_shipping' ? 'ส่งฟรี' : 'ลดเป็นบาท (฿)')],
                ['field' => 'มูลค่าส่วนลด', 'old' => '-', 'new' => $type === 'free_shipping' ? 'ส่งฟรี 🚚' : ($type === 'percent' ? "$val %" : "฿$val")],
                ['field' => 'ยอดซื้อขั้นต่ำ', 'old' => '-', 'new' => $min > 0 ? "฿" . number_format($min, 2) : 'ไม่มีขั้นต่ำ'],
                ['field' => 'ส่วนลดสูงสุด', 'old' => '-', 'new' => $max_discount > 0 ? "฿" . number_format($max_discount, 2) : 'ไม่จำกัด'],
                ['field' => 'จำนวนสิทธิ์รวม', 'old' => '-', 'new' => $usage_limit > 0 ? number_format($usage_limit) . ' ครั้ง' : 'ไม่จำกัด'],
                ['field' => 'สิทธิ์การใช้ต่อคน', 'old' => '-', 'new' => $user_limit > 0 ? number_format($user_limit) . ' ครั้ง' : 'ไม่จำกัด'],
                ['field' => 'วันเริ่มใช้งาน', 'old' => '-', 'new' => $start_date ?: 'ใช้งานได้ทันที'],
                ['field' => 'วันหมดอายุ', 'old' => '-', 'new' => $exp]
            ]
        ]);
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'สร้างคูปองส่วนลดเรียบร้อยแล้ว',
                'coupon' => [
                    'id' => $new_id,
                    'code' => $code,
                    'discount_type' => $type,
                    'discount_value' => $val,
                    'min_spend' => $min,
                    'max_discount' => $max_discount,
                    'usage_limit' => $usage_limit,
                    'user_limit' => $user_limit,
                    'start_date' => $start_date,
                    'expiry_date' => $exp
                ],
                'csrf_token' => get_csrf_token()
            ]);
            exit();
        }
        header("Location: admin_coupons.php"); exit();
    }
}

// --- Logic 2: ลบ (Delete) ---
if (isset($_GET['del'])) {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้องหรือหมดเวลาเซสชัน (Invalid CSRF Token)']);
            exit();
        }
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
    $id = intval($_GET['del']);
    $c_q = mysqli_query($conn, "SELECT code FROM coupons WHERE id=$id");
    $c_info = mysqli_fetch_assoc($c_q);
    $c_code = $c_info ? $c_info['code'] : "ไม่ทราบ ID";
    mysqli_query($conn, "DELETE FROM coupons WHERE id=$id");
    
    log_admin_action($conn, 'ลบคูปอง', [
        'title' => "ลบคูปองออกจากระบบ (โค้ด: $c_code)",
        'sections' => [
            [
                'title' => 'ข้อมูลคูปองที่ถูกลบ',
                'items' => [
                    "รหัสคูปอง: #$id",
                    "โค้ดส่วนลด: $c_code"
                ]
            ]
        ]
    ]);
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'ลบคูปองเรียบร้อยแล้ว']);
        exit();
    }
    header("Location: admin_coupons.php"); exit();
}

// --- Logic 4: อัปเดต (Update) ---
if (isset($_POST['update'])) {
    $id = intval($_POST['id']);
    $code = strtoupper(mysqli_real_escape_string($conn, $_POST['code']));
    $type = mysqli_real_escape_string($conn, $_POST['discount_type']);
    $val = floatval($_POST['discount_value']);
    $min = floatval($_POST['min_spend'] ?? 0);
    $max_discount = floatval($_POST['max_discount'] ?? 0);
    $usage_limit = intval($_POST['usage_limit'] ?? 0);
    $user_limit = intval($_POST['user_limit'] ?? 0);
    $start_date = !empty($_POST['start_date']) ? date('Y-m-d H:i:s', strtotime($_POST['start_date'])) : null;
    $exp = !empty($_POST['expiry_date']) ? date('Y-m-d H:i:s', strtotime($_POST['expiry_date'])) : '';

    // ดึงข้อมูลเดิมมาตรวจสอบส่วนต่าง
    $old_c_q = mysqli_query($conn, "SELECT * FROM coupons WHERE id=$id");
    $old_c = mysqli_fetch_assoc($old_c_q);
    
    $start_date_val = $start_date ? "'$start_date'" : "NULL";
    $sql = "UPDATE coupons SET code='$code', discount_type='$type', discount_value='$val', min_spend='$min', max_discount='$max_discount', usage_limit='$usage_limit', user_limit='$user_limit', start_date=$start_date_val, expiry_date='$exp' WHERE id=$id";
    mysqli_query($conn, $sql);
    
    $changes = [];
    if ($old_c) {
        if ($old_c['code'] !== $code) {
            $changes[] = ['field' => 'รหัสโค้ด (Code)', 'old' => $old_c['code'], 'new' => $code];
        }
        if ($old_c['discount_type'] !== $type) {
            $old_type_lbl = $old_c['discount_type'] === 'percent' ? 'ลดเปอร์เซ็นต์ (%)' : ($old_c['discount_type'] === 'free_shipping' ? 'ส่งฟรี' : 'ลดบาท (฿)');
            $new_type_lbl = $type === 'percent' ? 'ลดเปอร์เซ็นต์ (%)' : ($type === 'free_shipping' ? 'ส่งฟรี' : 'ลดบาท (฿)');
            $changes[] = ['field' => 'ประเภทส่วนลด', 'old' => $old_type_lbl, 'new' => $new_type_lbl];
        }
        if (floatval($old_c['discount_value']) !== $val) {
            $changes[] = ['field' => 'มูลค่าส่วนลด', 'old' => $old_c['discount_value'], 'new' => $val];
        }
        if (floatval($old_c['min_spend']) !== $min) {
            $changes[] = ['field' => 'ยอดซื้อขั้นต่ำ', 'old' => '฿' . number_format($old_c['min_spend'], 2), 'new' => '฿' . number_format($min, 2)];
        }
        if (floatval($old_c['max_discount'] ?? 0) !== $max_discount) {
            $changes[] = ['field' => 'ส่วนลดสูงสุด', 'old' => '฿' . number_format($old_c['max_discount'] ?? 0, 2), 'new' => '฿' . number_format($max_discount, 2)];
        }
        if (intval($old_c['usage_limit'] ?? 0) !== $usage_limit) {
            $changes[] = ['field' => 'จำกัดสิทธิ์รวม', 'old' => number_format($old_c['usage_limit'] ?? 0), 'new' => number_format($usage_limit)];
        }
        if (intval($old_c['user_limit'] ?? 0) !== $user_limit) {
            $changes[] = ['field' => 'จำกัดสิทธิ์ต่อคน', 'old' => number_format($old_c['user_limit'] ?? 0), 'new' => number_format($user_limit)];
        }
        
        $old_start = $old_c['start_date'] ? date('Y-m-d H:i:s', strtotime($old_c['start_date'])) : '';
        $new_start = $start_date ? date('Y-m-d H:i:s', strtotime($start_date)) : '';
        if ($old_start !== $new_start) {
            $changes[] = ['field' => 'วันเริ่มใช้งาน', 'old' => $old_start ?: 'ใช้งานทันที', 'new' => $new_start ?: 'ใช้งานทันที'];
        }
        
        $old_exp = $old_c['expiry_date'] ? date('Y-m-d H:i:s', strtotime($old_c['expiry_date'])) : '';
        $new_exp = $exp ? date('Y-m-d H:i:s', strtotime($exp)) : '';
        if ($old_exp !== $new_exp) {
            $changes[] = ['field' => 'วันหมดอายุ', 'old' => $old_exp, 'new' => $new_exp];
        }
    }
    
    log_admin_action($conn, 'แก้ไขคูปอง', [
        'title' => "แก้ไขข้อมูลคูปอง ID #$id (โค้ด: $code)",
        'changes' => $changes
    ]);
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'แก้ไขคูปองส่วนลดเรียบร้อยแล้ว',
            'coupon' => [
                'id' => $id,
                'code' => $code,
                'discount_type' => $type,
                'discount_value' => $val,
                'min_spend' => $min,
                'max_discount' => $max_discount,
                'usage_limit' => $usage_limit,
                'user_limit' => $user_limit,
                'start_date' => $start_date,
                'expiry_date' => $exp
            ]
        ]);
        exit();
    }
    header("Location: admin_coupons.php"); exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการคูปอง | Por Mae Bet Taled Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit'; background: #f8f9fa; }
        .coupon-row { transition: all 0.3s ease; }
        .coupon-row.fade-out { opacity: 0; transform: translateX(30px); }
            
        /* สไตล์การ์ดมือถือพรีเมียม */
        @media (max-width: 767.98px) {
            .card-modern-mobile {
                background: #ffffff !important;
                border: 1px solid rgba(226, 232, 240, 0.8) !important;
                border-radius: 20px !important;
                box-shadow: 0 10px 30px rgba(127, 181, 255, 0.05) !important;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                position: relative !important;
                overflow: hidden !important;
                border-left: 5px solid #7FB5FF !important; /* Pastel Blue left accent */
            }
            .card-modern-mobile:hover, .card-modern-mobile:active {
                transform: translateY(-3px) scale(1.01);
                box-shadow: 0 15px 35px rgba(127, 181, 255, 0.12) !important;
                border-color: rgba(127, 181, 255, 0.3) !important;
            }
            .card-modern-mobile .btn {
                border-radius: 12px !important;
                font-weight: 500;
                padding: 6px 12px;
                font-size: 0.78rem;
            }
            .card-modern-mobile .btn-light {
                background: #f8fafc !important;
                border: 1px solid #e2e8f0 !important;
                color: #475569 !important;
            }
            .card-modern-mobile .btn-light:hover {
                background: #f1f5f9 !important;
            }
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

        <div class="col-md-10 p-4 p-md-5">
            <h2 class="fw-bold mb-4">จัดการคูปองส่วนลด</h2>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm rounded-4 p-4 sticky-top" style="top: 20px;">
                        <h5 class="fw-bold mb-3" id="form-title">
                            <i class="bi bi-ticket-perforated text-blue" style="color:#AEE2FF"></i> สร้างคูปองใหม่
                        </h5>
                        
                        <form id="coupon-form" method="POST" onsubmit="submitCouponForm(event)">
                            <?= get_csrf_input() ?>
                            <input type="hidden" name="id" id="coupon_id" value="">
                            
                            <div class="mb-3">
                                <label class="small text-muted">รหัสคูปอง (Code)</label>
                                <input type="text" name="code" id="coupon_code" class="form-control text-uppercase fw-bold" placeholder="ใส่รหัสคูปอง" required>
                            </div>
                            
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="small text-muted">ประเภท</label>
                                    <select name="discount_type" id="discount_type" class="form-select" onchange="toggleDiscountVal()">
                                        <option value="fixed">ลดเป็นบาท (฿)</option>
                                        <option value="percent">ลดเป็น %</option>
                                        <option value="free_shipping">คูปองส่งฟรี</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">มูลค่าส่วนลด</label>
                                    <input type="number" name="discount_value" id="discount_value" class="form-control" placeholder="0" required>
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="small text-muted">ยอดซื้อขั้นต่ำ (บาท)</label>
                                    <input type="number" name="min_spend" id="min_spend" class="form-control" placeholder="0 = ไม่มีขั้นต่ำ" value="0">
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">ลดสูงสุด (บาท)</label>
                                    <input type="number" name="max_discount" id="max_discount" class="form-control" placeholder="0 = ไม่จำกัด" value="0">
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="small text-muted">สิทธิ์รวมระบบ (ครั้ง)</label>
                                    <input type="number" name="usage_limit" id="usage_limit" class="form-control" placeholder="0 = ไม่จำกัด" value="0">
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">สิทธิ์ต่อคน (ครั้ง)</label>
                                    <input type="number" name="user_limit" id="user_limit" class="form-control" placeholder="0 = ไม่จำกัด" value="0">
                                </div>
                            </div>

                            <div class="row g-2 mb-4">
                                <div class="col-6">
                                    <label class="small text-muted">วันเริ่มใช้งาน</label>
                                    <input type="datetime-local" name="start_date" id="start_date" class="form-control" value="<?= date('Y-m-d\T00:00') ?>">
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">วันหมดอายุ</label>
                                    <input type="datetime-local" name="expiry_date" id="expiry_date" class="form-control" value="<?= date('Y-m-d\T23:59', strtotime('+1 month')) ?>" required>
                                </div>
                            </div>
                            
                            <div id="form-actions-container">
                                <button type="submit" name="add" id="submit-btn" class="btn btn-dark w-100 rounded-3">สร้างคูปอง</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead class="text-muted small">
                                    <tr>
                                        <th>โค้ด</th>
                                        <th>ส่วนลด</th>
                                        <th>เงื่อนไข</th>
                                        <th>ระยะเวลาใช้งาน</th>
                                        <th>สถานะ</th>
                                        <th class="text-end">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody id="coupons-tbody">
                                    <?php 
                                    $limit = isset($_GET['limit']) ? max(10, min(100, intval($_GET['limit']))) : 20;
                                    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                                    
                                    $count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM coupons");
                                    $total_rows = mysqli_fetch_assoc($count_query)['total'] ?? 0;
                                    $total_pages = ceil($total_rows / $limit);
                                    if ($total_pages > 0 && $page > $total_pages) {
                                        $page = $total_pages;
                                    }
                                    $offset = ($page - 1) * $limit;

                                    $res = mysqli_query($conn, "SELECT *, 
                                        (expiry_date < NOW()) as is_expired,
                                        (start_date IS NOT NULL AND start_date > NOW()) as not_started
                                        FROM coupons ORDER BY id DESC LIMIT $limit OFFSET $offset"); 
                                    while($row = mysqli_fetch_assoc($res)):
                                        $is_expired = $row['is_expired'];
                                        $not_started = $row['not_started'];
                                    ?>
                                    <!-- Desktop Row -->
                                    <tr id="coupon-row-<?= $row['id'] ?>" class="coupon-row d-none d-md-table-row">
                                        <td>
                                            <div class="fw-bold text-primary coupon-code-cell"><?= htmlspecialchars($row['code']) ?></div>
                                        </td>
                                        <td class="coupon-discount-cell">
                                            <?php if ($row['discount_type'] == 'free_shipping'): ?>
                                                <span class="badge bg-success text-white">ส่งฟรี 🚚</span>
                                            <?php else: ?>
                                                <span class="badge bg-blue text-white" style="background:#AEE2FF">
                                                    <?= $row['discount_type']=='fixed' ? '-฿'.number_format($row['discount_value']) : '-'.number_format($row['discount_value']).'%' ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted coupon-condition-cell" style="line-height: 1.4;">
                                             <div><?= $row['min_spend'] > 0 ? 'ขั้นต่ำ: ฿'.number_format($row['min_spend']) : 'ไม่มีขั้นต่ำ' ?></div>
                                             <?php if ($row['discount_type'] == 'percent' && floatval($row['max_discount']) > 0): ?>
                                                 <div class="text-danger fw-semibold" style="font-size: 0.78rem;">ลดสูงสุด: ฿<?= number_format($row['max_discount']) ?></div>
                                             <?php endif; ?>
                                             <div class="x-small" style="font-size: 0.75rem; color: #94a3b8;">
                                                 สิทธิ์รวม: <?= $row['usage_limit'] > 0 ? number_format($row['usage_limit']).' ครั้ง' : 'ไม่จำกัด' ?><br>
                                                 สิทธิ์ต่อคน: <?= $row['user_limit'] > 0 ? number_format($row['user_limit']).' ครั้ง' : 'ไม่จำกัด' ?>
                                             </div>
                                         </td>
                                        <td class="small text-muted coupon-date-cell" style="line-height: 1.4;">
                                            <div>เริ่ม: <?= $row['start_date'] ? date('d/m/Y H:i', strtotime($row['start_date'])) : 'ทันที' ?></div>
                                            <div class="text-danger">หมด: <?= date('d/m/Y H:i', strtotime($row['expiry_date'])) ?></div>
                                        </td>
                                        <td class="coupon-status-cell">
                                            <?php if($is_expired): ?>
                                                <span class="badge bg-secondary">หมดอายุ</span>
                                            <?php elseif($not_started): ?>
                                                <span class="badge bg-warning text-dark">ยังไม่เริ่ม</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">ใช้งานได้</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button onclick='loadEditCoupon(<?= json_encode($row) ?>)' class="btn btn-light btn-sm text-primary rounded-circle shadow-sm me-1 edit-coupon-btn"><i class="bi bi-pencil-fill"></i></button>
                                            <button onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars($row['code'], ENT_QUOTES) ?>', '<?= get_csrf_token() ?>')" class="btn btn-light btn-sm text-danger rounded-circle shadow-sm"><i class="bi bi-trash-fill"></i></button>
                                        </td>
                                    </tr>

                                    <!-- Mobile Row -->
                                    <tr id="coupon-mob-row-<?= $row['id'] ?>" class="coupon-row d-md-none">
                                        <td colspan="6" class="p-0 border-0">
                                            <div class="card-modern-mobile p-3 mb-3 text-start">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <div class="fw-bold text-primary coupon-code-cell"><?= htmlspecialchars($row['code']) ?></div>
                                                    <div class="coupon-status-cell">
                                                        <?php if($is_expired): ?>
                                                            <span class="badge bg-secondary">หมดอายุ</span>
                                                        <?php elseif($not_started): ?>
                                                            <span class="badge bg-warning text-dark">ยังไม่เริ่ม</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">ใช้งานได้</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="mb-2">
                                                    <span class="coupon-discount-cell">
                                                        <?php if ($row['discount_type'] == 'free_shipping'): ?>
                                                            <span class="badge bg-success text-white">ส่งฟรี 🚚</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-blue text-white" style="background:#AEE2FF">
                                                                <?= $row['discount_type']=='fixed' ? '-฿'.number_format($row['discount_value']) : '-['.number_format($row['discount_value']).'%' ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <div class="mb-2 small text-muted coupon-condition-cell" style="line-height: 1.4;">
                                                     <div><?= $row['min_spend'] > 0 ? 'ขั้นต่ำ: ฿'.number_format($row['min_spend']) : 'ไม่มีขั้นต่ำ' ?></div>
                                                     <?php if ($row['discount_type'] == 'percent' && floatval($row['max_discount']) > 0): ?>
                                                         <div class="text-danger fw-semibold" style="font-size: 0.78rem;">ลดสูงสุด: ฿<?= number_format($row['max_discount']) ?></div>
                                                     <?php endif; ?>
                                                     <div class="x-small" style="font-size: 0.75rem; color: #94a3b8;">
                                                         สิทธิ์รวม: <?= $row['usage_limit'] > 0 ? number_format($row['usage_limit']).' ครั้ง' : 'ไม่จำกัด' ?><br>
                                                         สิทธิ์ต่อคน: <?= $row['user_limit'] > 0 ? number_format($row['user_limit']).' ครั้ง' : 'ไม่จำกัด' ?>
                                                     </div>
                                                </div>
                                                <div class="mb-3 small text-muted coupon-date-cell" style="line-height: 1.4;">
                                                    <div>เริ่ม: <?= $row['start_date'] ? date('d/m/Y H:i', strtotime($row['start_date'])) : 'ทันที' ?></div>
                                                    <div class="text-danger">หมด: <?= date('d/m/Y H:i', strtotime($row['expiry_date'])) ?></div>
                                                </div>
                                                <div class="d-flex justify-content-end gap-2 border-top pt-2">
                                                    <button onclick='loadEditCoupon(<?= json_encode($row) ?>)' class="btn btn-light btn-sm text-primary rounded-3 border px-3 edit-coupon-btn"><i class="bi bi-pencil-fill"></i> แก้ไข</button>
                                                    <button onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars($row['code'], ENT_QUOTES) ?>', '<?= get_csrf_token() ?>')" class="btn btn-light btn-sm text-danger rounded-3 border px-3"><i class="bi bi-trash-fill"></i> ลบ</button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- การแบ่งหน้า (Pagination) -->
                        <?= render_pagination_controls($total_rows, $limit, $page, $offset) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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

    let currentCsrfToken = '<?= get_csrf_token() ?>';

    function toggleDiscountVal() {
        const type = document.getElementById('discount_type').value;
        const valInput = document.getElementById('discount_value');
        const maxInput = document.getElementById('max_discount');
        if (type === 'free_shipping') {
            valInput.value = '0';
            valInput.readOnly = true;
            if (maxInput) {
                maxInput.value = '0';
                maxInput.readOnly = true;
            }
        } else {
            valInput.readOnly = false;
            if (maxInput) {
                maxInput.readOnly = (type === 'fixed');
                if (type === 'fixed') maxInput.value = '0';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', toggleDiscountVal);

    function loadEditCoupon(data) {
        // Remove warning class from any existing rows
        document.querySelectorAll('.coupon-row').forEach(row => row.classList.remove('table-warning'));
        
        // Add highlight class to selected row
        const row = document.getElementById('coupon-row-' + data.id);
        if (row) row.classList.add('table-warning');

        // Populate fields
        document.getElementById('form-title').innerHTML = '<i class="bi bi-pencil-square text-warning"></i> แก้ไขคูปอง';
        document.getElementById('coupon_id').value = data.id;
        document.getElementById('coupon_code').value = data.code;
        document.getElementById('discount_type').value = data.discount_type;
        document.getElementById('discount_value').value = data.discount_value;
        document.getElementById('min_spend').value = data.min_spend;
        document.getElementById('max_discount').value = data.max_discount || 0;
        document.getElementById('usage_limit').value = data.usage_limit || 0;
        document.getElementById('user_limit').value = data.user_limit || 0;
        
        if (data.start_date) {
            document.getElementById('start_date').value = data.start_date.replace(' ', 'T').substring(0, 16);
        }
        if (data.expiry_date) {
            document.getElementById('expiry_date').value = data.expiry_date.replace(' ', 'T').substring(0, 16);
        }

        toggleDiscountVal();

        // Render Cancel & Update buttons
        document.getElementById('form-actions-container').innerHTML = `
            <div class="d-flex gap-2">
                <button type="submit" name="update" id="submit-btn" class="btn btn-warning w-100 rounded-3 text-white">อัปเดต</button>
                <button type="button" class="btn btn-secondary rounded-3" onclick="resetCouponForm()">ยกเลิก</button>
            </div>
        `;
    }

    function resetCouponForm() {
        document.querySelectorAll('.coupon-row').forEach(row => row.classList.remove('table-warning'));
        
        document.getElementById('form-title').innerHTML = '<i class="bi bi-ticket-perforated text-blue" style="color:#AEE2FF"></i> สร้างคูปองใหม่';
        document.getElementById('coupon_id').value = '';
        document.getElementById('coupon-form').reset();
        
        // Default start & expiry
        const now = new Date();
        const startStr = now.toISOString().substring(0, 16);
        const expiry = new Date();
        expiry.setMonth(expiry.getMonth() + 1);
        const expiryStr = expiry.toISOString().substring(0, 16);
        
        document.getElementById('start_date').value = startStr;
        document.getElementById('expiry_date').value = expiryStr;
        
        toggleDiscountVal();

        document.getElementById('form-actions-container').innerHTML = `
            <button type="submit" name="add" id="submit-btn" class="btn btn-dark w-100 rounded-3">สร้างคูปอง</button>
        `;
    }

    function submitCouponForm(e) {
        e.preventDefault();
        const form = document.getElementById('coupon-form');
        const submitBtn = document.getElementById('submit-btn');
        submitBtn.disabled = true;

        const isEdit = document.getElementById('coupon_id').value !== '';
        const formData = new FormData(form);
        formData.append(isEdit ? 'update' : 'add', '1');
        formData.append('ajax', '1');
        formData.append('csrf_token', currentCsrfToken);

        fetch(window.location.pathname, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            if (data.status === 'success') {
                Toast.fire({
                    icon: 'success',
                    title: data.message
                });

                if (!isEdit) {
                    currentCsrfToken = data.csrf_token;
                    document.querySelectorAll('input[name="csrf_token"]').forEach(el => el.value = currentCsrfToken);
                }

                // Prepare cell markup updates
                const discountHtml = data.coupon.discount_type === 'free_shipping' 
                    ? '<span class="badge bg-success text-white">ส่งฟรี 🚚</span>'
                    : `<span class="badge bg-blue text-white" style="background:#AEE2FF">${data.coupon.discount_type === 'fixed' ? '-฿' + Number(data.coupon.discount_value).toLocaleString() : '-' + Number(data.coupon.discount_value) + '%'}</span>`;
                
                const conditionHtml = `
                    <div>${data.coupon.min_spend > 0 ? 'ขั้นต่ำ: ฿' + Number(data.coupon.min_spend).toLocaleString() : 'ไม่มีขั้นต่ำ'}</div>
                    ${data.coupon.discount_type === 'percent' && Number(data.coupon.max_discount) > 0 ? '<div class="text-danger fw-semibold" style="font-size: 0.78rem;">ลดสูงสุด: ฿' + Number(data.coupon.max_discount).toLocaleString() + '</div>' : ''}
                    <div class="x-small" style="font-size: 0.75rem; color: #94a3b8;">
                        สิทธิ์รวม: ${data.coupon.usage_limit > 0 ? Number(data.coupon.usage_limit).toLocaleString() + ' ครั้ง' : 'ไม่จำกัด'}<br>
                        สิทธิ์ต่อคน: ${data.coupon.user_limit > 0 ? Number(data.coupon.user_limit).toLocaleString() + ' ครั้ง' : 'ไม่จำกัด'}
                    </div>
                `;

                const startText = data.coupon.start_date ? formatDate(data.coupon.start_date) : 'ทันที';
                const expiryText = formatDate(data.coupon.expiry_date);
                const dateHtml = `
                    <div>เริ่ม: ${startText}</div>
                    <div class="text-danger">หมด: ${expiryText}</div>
                `;

                // Calculate status
                const now = new Date();
                const exp = new Date(data.coupon.expiry_date);
                const start = data.coupon.start_date ? new Date(data.coupon.start_date) : null;
                let statusHtml = '<span class="badge bg-success">ใช้งานได้</span>';
                if (exp < now) {
                    statusHtml = '<span class="badge bg-secondary">หมดอายุ</span>';
                } else if (start && start > now) {
                    statusHtml = '<span class="badge bg-warning text-dark">ยังไม่เริ่ม</span>';
                }

                if (isEdit) {
                    // Update existing row
                    const row = document.getElementById('coupon-row-' + data.coupon.id);
                    if (row) {
                        row.querySelector('.coupon-code-cell').innerText = data.coupon.code;
                        row.querySelector('.coupon-discount-cell').innerHTML = discountHtml;
                        row.querySelector('.coupon-condition-cell').innerHTML = conditionHtml;
                        row.querySelector('.coupon-date-cell').innerHTML = dateHtml;
                        row.querySelector('.coupon-status-cell').innerHTML = statusHtml;
                        
                        const editBtn = row.querySelector('.edit-coupon-btn');
                        if (editBtn) {
                            editBtn.setAttribute('onclick', `loadEditCoupon(${JSON.stringify(data.coupon)})`);
                        }
                    }
                    resetCouponForm();
                } else {
                    // Add new row at start of list
                    const tbody = document.getElementById('coupons-tbody');
                    const tr = document.createElement('tr');
                    tr.id = 'coupon-row-' + data.coupon.id;
                    tr.className = 'coupon-row';
                    tr.innerHTML = `
                        <td>
                            <div class="fw-bold text-primary coupon-code-cell">${escapeHtml(data.coupon.code)}</div>
                        </td>
                        <td class="coupon-discount-cell">${discountHtml}</td>
                        <td class="small text-muted coupon-condition-cell" style="line-height: 1.4;">${conditionHtml}</td>
                        <td class="small text-muted coupon-date-cell" style="line-height: 1.4;">${dateHtml}</td>
                        <td class="coupon-status-cell">${statusHtml}</td>
                        <td class="text-end">
                            <button onclick='loadEditCoupon(${JSON.stringify(data.coupon)})' class="btn btn-light btn-sm text-primary rounded-circle shadow-sm me-1 edit-coupon-btn"><i class="bi bi-pencil-fill"></i></button>
                            <button onclick="confirmDelete(${data.coupon.id}, '${escapeHtmlString(data.coupon.code)}', '${currentCsrfToken}')" class="btn btn-light btn-sm text-danger rounded-circle shadow-sm"><i class="bi bi-trash-fill"></i></button>
                        </td>
                    `;
                    tbody.insertBefore(tr, tbody.firstChild);
                    resetCouponForm();
                }
            } else {
                Toast.fire({
                    icon: 'error',
                    title: data.message || 'เกิดข้อผิดพลาดในการบันทึก'
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

    function confirmDelete(id, code, token) {
        Swal.fire({
            title: 'ลบคูปองหรือไม่?',
            text: `ยืนยันการลบคูปองโค้ด "${code}" ออกจากระบบถาวร?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'ลบเลย',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(window.location.pathname + `?del=${id}&csrf_token=${token}&ajax=1`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Toast.fire({
                            icon: 'success',
                            title: data.message
                        });
                        const row = document.getElementById('coupon-row-' + id);
                        if (row) {
                            row.classList.add('fade-out');
                            setTimeout(() => row.remove(), 300);
                        }
                    } else {
                        Toast.fire({
                            icon: 'error',
                            title: data.message || 'ลบไม่สำเร็จ'
                        });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Toast.fire({
                        icon: 'error',
                        title: 'การเชื่อมต่อล้มเหลว'
                    });
                });
            }
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

    function escapeHtmlString(text) {
        return text.replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr.replace(' ', 'T'));
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        return `${day}/${month}/${year} ${hours}:${minutes}`;
    }
</script>
</body>
</html>
