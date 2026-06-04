<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');

// เช็ค Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
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
        echo "<script>alert('โค้ดนี้มีอยู่แล้ว!');</script>";
    } else {
        $start_date_val = $start_date ? "'$start_date'" : "NULL";
        $sql = "INSERT INTO coupons (code, discount_type, discount_value, min_spend, max_discount, usage_limit, user_limit, start_date, expiry_date) 
                VALUES ('$code', '$type', '$val', '$min', '$max_discount', '$usage_limit', '$user_limit', $start_date_val, '$exp')";
        mysqli_query($conn, $sql);
        
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
        header("Location: admin_coupons.php"); exit();
    }
}

// --- Logic 2: ลบ (Delete) ---
if (isset($_GET['del'])) {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
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
    header("Location: admin_coupons.php"); exit();
}

// --- Logic 3: เตรียมข้อมูลแก้ไข (Edit Fetch) ---
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $res = mysqli_query($conn, "SELECT * FROM coupons WHERE id=$id");
    $edit_data = mysqli_fetch_assoc($res);
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
    <style> body { font-family: 'Kanit'; background: #f8f9fa; } </style>
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
                        <h5 class="fw-bold mb-3">
                            <?php if($edit_data): ?>
                                <i class="bi bi-pencil-square text-warning"></i> แก้ไขคูปอง
                            <?php else: ?>
                                <i class="bi bi-ticket-perforated text-blue" style="color:#AEE2FF"></i> สร้างคูปองใหม่
                            <?php endif; ?>
                        </h5>
                        
                        <form method="POST">
                            <?= get_csrf_input() ?>
                            <input type="hidden" name="id" value="<?= $edit_data['id'] ?? '' ?>">
                            
                            <div class="mb-3">
                                <label class="small text-muted">รหัสคูปอง (Code)</label>
                                <input type="text" name="code" class="form-control text-uppercase fw-bold" placeholder="ใส่รหัสคูปอง" value="<?= $edit_data['code'] ?? '' ?>" required>
                            </div>
                            
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="small text-muted">ประเภท</label>
                                    <select name="discount_type" id="discount_type" class="form-select" onchange="toggleDiscountVal()">
                                        <option value="fixed" <?= ($edit_data['discount_type'] ?? '') == 'fixed' ? 'selected' : '' ?>>ลดเป็นบาท (฿)</option>
                                        <option value="percent" <?= ($edit_data['discount_type'] ?? '') == 'percent' ? 'selected' : '' ?>>ลดเป็น %</option>
                                        <option value="free_shipping" <?= ($edit_data['discount_type'] ?? '') == 'free_shipping' ? 'selected' : '' ?>>คูปองส่งฟรี</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">มูลค่าส่วนลด</label>
                                    <input type="number" name="discount_value" id="discount_value" class="form-control" placeholder="0" value="<?= $edit_data['discount_value'] ?? '' ?>" required>
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="small text-muted">ยอดซื้อขั้นต่ำ (บาท)</label>
                                    <input type="number" name="min_spend" class="form-control" placeholder="0 = ไม่มีขั้นต่ำ" value="<?= $edit_data['min_spend'] ?? '0' ?>">
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">ลดสูงสุด (บาท)</label>
                                    <input type="number" name="max_discount" class="form-control" placeholder="0 = ไม่จำกัด" value="<?= isset($edit_data['max_discount']) ? floatval($edit_data['max_discount']) : '0' ?>">
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="small text-muted">สิทธิ์รวมระบบ (ครั้ง)</label>
                                    <input type="number" name="usage_limit" class="form-control" placeholder="0 = ไม่จำกัด" value="<?= $edit_data['usage_limit'] ?? '0' ?>">
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">สิทธิ์ต่อคน (ครั้ง)</label>
                                    <input type="number" name="user_limit" class="form-control" placeholder="0 = ไม่จำกัด" value="<?= $edit_data['user_limit'] ?? '0' ?>">
                                </div>
                            </div>

                            <div class="row g-2 mb-4">
                                <div class="col-6">
                                    <label class="small text-muted">วันเริ่มใช้งาน</label>
                                    <input type="datetime-local" name="start_date" class="form-control" value="<?= isset($edit_data['start_date']) ? date('Y-m-d\TH:i', strtotime($edit_data['start_date'])) : date('Y-m-d\T00:00') ?>">
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">วันหมดอายุ</label>
                                    <input type="datetime-local" name="expiry_date" class="form-control" value="<?= isset($edit_data['expiry_date']) ? date('Y-m-d\TH:i', strtotime($edit_data['expiry_date'])) : date('Y-m-d\T23:59', strtotime('+1 month')) ?>" required>
                                </div>
                            </div>
                            
                            <?php if($edit_data): ?>
                                <div class="d-flex gap-2">
                                    <button type="submit" name="update" class="btn btn-warning w-100 rounded-3 text-white">อัปเดต</button>
                                    <a href="admin_coupons.php" class="btn btn-secondary rounded-3">ยกเลิก</a>
                                </div>
                            <?php else: ?>
                                <button type="submit" name="add" class="btn btn-dark w-100 rounded-3">สร้างคูปอง</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <div class="table-responsive">
                            <table class="table align-middle" style="min-width: 600px;">
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
                                <tbody>
                                    <?php 
                                    $res = mysqli_query($conn, "SELECT *, 
                                        (expiry_date < NOW()) as is_expired,
                                        (start_date IS NOT NULL AND start_date > NOW()) as not_started
                                        FROM coupons ORDER BY id DESC"); 
                                    while($row = mysqli_fetch_assoc($res)):
                                        $is_expired = $row['is_expired'];
                                        $not_started = $row['not_started'];
                                        $is_editing = ($edit_data && $edit_data['id'] == $row['id']) ? 'table-warning' : '';
                                    ?>
                                    <tr class="<?= $is_editing ?>">
                                        <td>
                                            <div class="fw-bold text-primary"><?= $row['code'] ?></div>
                                        </td>
                                        <td>
                                            <?php if ($row['discount_type'] == 'free_shipping'): ?>
                                                <span class="badge bg-success text-white">ส่งฟรี 🚚</span>
                                            <?php else: ?>
                                                <span class="badge bg-blue text-white" style="background:#AEE2FF">
                                                    <?= $row['discount_type']=='fixed' ? '-฿'.number_format($row['discount_value']) : '-'.number_format($row['discount_value']).'%' ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                         <td class="small text-muted" style="line-height: 1.4;">
                                             <div><?= $row['min_spend'] > 0 ? 'ขั้นต่ำ: ฿'.number_format($row['min_spend']) : 'ไม่มีขั้นต่ำ' ?></div>
                                             <?php if ($row['discount_type'] == 'percent' && floatval($row['max_discount']) > 0): ?>
                                                 <div class="text-danger fw-semibold" style="font-size: 0.78rem;">ลดสูงสุด: ฿<?= number_format($row['max_discount']) ?></div>
                                             <?php endif; ?>
                                             <div class="x-small" style="font-size: 0.75rem; color: #94a3b8;">
                                                 สิทธิ์รวม: <?= $row['usage_limit'] > 0 ? number_format($row['usage_limit']).' ครั้ง' : 'ไม่จำกัด' ?><br>
                                                 สิทธิ์ต่อคน: <?= $row['user_limit'] > 0 ? number_format($row['user_limit']).' ครั้ง' : 'ไม่จำกัด' ?>
                                             </div>
                                         </td>
                                        <td class="small text-muted" style="line-height: 1.4;">
                                            <div>เริ่ม: <?= $row['start_date'] ? date('d/m/Y H:i', strtotime($row['start_date'])) : 'ทันที' ?></div>
                                            <div class="text-danger">หมด: <?= date('d/m/Y H:i', strtotime($row['expiry_date'])) ?></div>
                                        </td>
                                        <td>
                                            <?php if($is_expired): ?>
                                                <span class="badge bg-secondary">หมดอายุ</span>
                                            <?php elseif($not_started): ?>
                                                <span class="badge bg-warning text-dark">ยังไม่เริ่ม</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">ใช้งานได้</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="?edit=<?= $row['id'] ?>" class="btn btn-light btn-sm text-primary rounded-circle shadow-sm me-1"><i class="bi bi-pencil-fill"></i></a>
                                            <a href="?del=<?= $row['id'] ?>&csrf_token=<?= get_csrf_token() ?>" class="btn btn-light btn-sm text-danger rounded-circle shadow-sm" onclick="return confirm('ลบคูปองนี้?');"><i class="bi bi-trash-fill"></i></a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
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
<script>
function toggleDiscountVal() {
    const type = document.getElementById('discount_type').value;
    const valInput = document.getElementById('discount_value');
    const maxInput = document.getElementsByName('max_discount')[0];
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
</script>
</body>
</html>


