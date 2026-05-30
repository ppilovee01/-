<?php
session_start();
include 'db.php';

// ตั้งค่า Timezone ให้ตรงเับเน„ทยเสมอ
date_default_timezone_set('Asia/Bangkok');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

// --- Logic 1: เพิ่มข้อมูล (Add) ---
if (isset($_POST['add'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $num = mysqli_real_escape_string($conn, $_POST['account_number']);
    $acc_name = mysqli_real_escape_string($conn, $_POST['account_name']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $sql = "INSERT INTO payment_methods (name, type, account_number, account_name, status) VALUES ('$name', '$type', '$num', '$acc_name', '$status')";
    if(mysqli_query($conn, $sql)) {
        log_admin_action($conn, 'เพิ่มช่องทางชำระเงิน', "เพิ่มช่องทางชำระเงิน: $name, ประเภท = $type, เลขที่ = $num, ชื่อบัญชี = $acc_name, สถานะ = $status");
        header("Location: admin_payments.php"); exit();
    }
}

// --- Logic 2: ลบข้อมูล (Delete) ---
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $p_q = mysqli_query($conn, "SELECT name, account_number FROM payment_methods WHERE id=$id");
    $p_info = mysqli_fetch_assoc($p_q);
    $p_name = $p_info['name'] ?? 'ไม่ระบุ';
    $p_num = $p_info['account_number'] ?? 'ไม่ระบุ';
    mysqli_query($conn, "DELETE FROM payment_methods WHERE id=$id");
    log_admin_action($conn, 'ลบช่องทางชำระเงิน', "ลบช่องทางชำระเงิน ID #$id: $p_name (เลขบัญชี/เบอร์ $p_num)");
    header("Location: admin_payments.php"); exit();
}

// --- Logic 3: เตรียมข้อมูลแก้ไข (Fetch for Edit) ---
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $res = mysqli_query($conn, "SELECT * FROM payment_methods WHERE id=$id");
    $edit_data = mysqli_fetch_assoc($res);
}

// --- Logic 4: อัปเดตข้อมูล (Update) ---
if (isset($_POST['update'])) {
    $id = intval($_POST['id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $num = mysqli_real_escape_string($conn, $_POST['account_number']);
    $acc_name = mysqli_real_escape_string($conn, $_POST['account_name']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $sql = "UPDATE payment_methods SET name='$name', type='$type', account_number='$num', account_name='$acc_name', status='$status' WHERE id=$id";
    if(mysqli_query($conn, $sql)) {
        log_admin_action($conn, 'แก้ไขช่องทางชำระเงิน', "แก้ไขช่องทางชำระเงิน ID #$id: $name, ประเภท = $type, เลขที่ = $num, ชื่อบัญชี = $acc_name, สถานะ = $status");
        header("Location: admin_payments.php"); exit();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ช่องทางการชำระเงิน | Por Mae Bet Taled Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <?php 
        $icon_q = mysqli_query($conn, "SELECT shop_icon FROM shop_settings WHERE id=1");
        $icon_r = mysqli_fetch_assoc($icon_q);
        $favicon = !empty($icon_r['shop_icon']) ? "uploads/".$icon_r['shop_icon'] : "assets/default_icon.png";
    ?>
    <link rel="icon" type="image/x-icon" href="<?= $favicon ?>">
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
            <h2 class="fw-bold mb-4">ช่องทางชำระเงิน</h2>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm rounded-4 p-4 sticky-top" style="top: 20px;">
                        <h5 class="fw-bold mb-3">
                            <?php if($edit_data): ?>
                                <i class="bi bi-pencil-square text-warning"></i> เนเเน‰เน„ขขเน‰อมูล
                            <?php else: ?>
                                <i class="bi bi-plus-circle text-primary"></i> เพิ่มช่องทางใหม่
                            <?php endif; ?>
                        </h5>
                        
                        <form method="POST">
                            <input type="hidden" name="id" value="<?= $edit_data['id'] ?? '' ?>">
                            
                            <div class="mb-2">
                                <label class="small text-muted fw-bold">เลือกธนาคาร / ช่องทางชำระเงิน</label>
                                <select name="bank_select" id="bankSelect" class="form-select mb-2" onchange="toggleCustomBankName(this)">
                                    <option value="ธนาคารกสิกรไทย" <?= ($edit_data['name'] ?? '') == 'ธนาคารกสิกรไทย' ? 'selected' : '' ?>>ธนาคารกสิกรไทย (KBANK)</option>
                                    <option value="ธนาคารไทยพาณิชย์" <?= ($edit_data['name'] ?? '') == 'ธนาคารไทยพาณิชย์' ? 'selected' : '' ?>>ธนาคารไทยพาณิชย์ (SCB)</option>
                                    <option value="ธนาคารกรุงไทย" <?= ($edit_data['name'] ?? '') == 'ธนาคารกรุงไทย' ? 'selected' : '' ?>>ธนาคารกรุงไทย (KTB)</option>
                                    <option value="ธนาคารกรุงเทพ" <?= ($edit_data['name'] ?? '') == 'ธนาคารกรุงเทพ' ? 'selected' : '' ?>>ธนาคารกรุงเทพ (BBL)</option>
                                    <option value="ธนาคารกรุงศรีอยุธยา" <?= ($edit_data['name'] ?? '') == 'ธนาคารกรุงศรีอยุธยา' ? 'selected' : '' ?>>ธนาคารกรุงศรีอยุธยา (BAY)</option>
                                    <option value="ธนาคารทหารไทยธนชาต" <?= ($edit_data['name'] ?? '') == 'ธนาคารทหารไทยธนชาต' ? 'selected' : '' ?>>ธนาคารทหารไทยธนชาต (TTB)</option>
                                    <option value="ธนาคารออมสิน" <?= ($edit_data['name'] ?? '') == 'ธนาคารออมสิน' ? 'selected' : '' ?>>ธนาคารออมสิน (GSB)</option>
                                    <option value="พร้อมเพย์ (PromptPay)" <?= ($edit_data['name'] ?? '') == 'พร้อมเพย์ (PromptPay)' ? 'selected' : '' ?>>พร้อมเพย์ (PromptPay)</option>
                                    <option value="เก็บเงินปลายทาง (COD)" <?= ($edit_data['name'] ?? '') == 'เก็บเงินปลายทาง (COD)' ? 'selected' : '' ?>>เก็บเงินปลายทาง (COD)</option>
                                    <option value="custom" <?= (!empty($edit_data['name']) && !in_array($edit_data['name'], ['ธนาคารกสิกรไทย','ธนาคารไทยพาณิชย์','ธนาคารกรุงไทย','ธนาคารกรุงเทพ','ธนาคารกรุงศรีอยุธยา','ธนาคารทหารไทยธนชาต','ธนาคารออมสิน','พร้อมเพย์ (PromptPay)','เก็บเงินปลายทาง (COD)'])) ? 'selected' : '' ?>>อื่นๆ (ระบุเอง)</option>
                                </select>
                                <input type="text" name="name" id="customBankName" class="form-control" placeholder="ระบุชื่อธนาคาร/ช่องทางชำระเงิน" value="<?= $edit_data['name'] ?? '' ?>" required>
                            </div>
                            
                            <div class="mb-2">
                                <label class="small text-muted fw-bold">ประเภท</label>
                                <select name="type" class="form-select">
                                    <option value="bank" <?= ($edit_data['type'] ?? '') == 'bank' ? 'selected' : '' ?>>บัญชีธนาคาร</option>
                                    <option value="promptpay" <?= ($edit_data['type'] ?? '') == 'promptpay' ? 'selected' : '' ?>>promptpay (QR)</option>
                                    <option value="cod" <?= ($edit_data['type'] ?? '') == 'cod' ? 'selected' : '' ?>>เก็บเงินปลายทาง</option>
                                </select>
                            </div>
                            
                            <div class="mb-2">
                                <label class="small text-muted fw-bold">ชื่อบัญชี / ชื่อผู้รับโอน</label>
                                <input type="text" name="account_name" class="form-control" value="<?= $edit_data['account_name'] ?? '' ?>" placeholder="เช่น นายสมชาย รักดี" required>
                            </div>
                            
                            <div class="mb-2">
                                <label class="small text-muted fw-bold">เลขบัญชี / เบอร์โทรพร้อมเพย์</label>
                                <input type="text" name="account_number" class="form-control" value="<?= $edit_data['account_number'] ?? '' ?>" placeholder="ไม่จำเป็นสำหรับ COD">
                            </div>

                            <div class="mb-3">
                                <label class="small text-muted fw-bold">สถานะการใช้งาน</label>
                                <select name="status" class="form-select">
                                    <option value="active" <?= ($edit_data['status'] ?? '') == 'active' ? 'selected' : '' ?>>เปิดใช้งาน (Active)</option>
                                    <option value="inactive" <?= ($edit_data['status'] ?? '') == 'inactive' ? 'selected' : '' ?>>ปิดใช้งาน (Inactive)</option>
                                </select>
                            </div>
                            
                            <?php if($edit_data): ?>
                                <div class="d-flex gap-2">
                                    <button type="submit" name="update" class="btn btn-warning w-100 rounded-3 text-white">อัปเดตข้อมูล</button>
                                    <a href="admin_payments.php" class="btn btn-secondary rounded-3">ยกเลิก</a>
                                </div>
                            <?php else: ?>
                                <button type="submit" name="add" class="btn btn-dark w-100 rounded-3">บันทึกช่องทาง</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <div class="table-responsive">
                            <table class="table align-middle" style="min-width: 500px;">
                                <thead class="text-muted small">
                                    <tr>
                                        <th>ชื่อช่องทาง</th>
                                        <th>ประเภท</th>
                                        <th>ชื่อบัญชี</th>
                                        <th>เลขบัญชี/เบอร์พร้อมเพย์</th>
                                        <th>สถานะ</th>
                                        <th class="text-end">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $res = mysqli_query($conn, "SELECT * FROM payment_methods"); 
                                    while($row = mysqli_fetch_assoc($res)): 
                                        $is_editing = ($edit_data && $edit_data['id'] == $row['id']) ? 'table-warning' : '';
                                        
                                        // ตั้งค่า Badge ตามประเภท
                                        $type_badge = 'bg-light text-dark border';
                                        if($row['type'] == 'promptpay') $type_badge = 'bg-info-subtle text-info border border-info-subtle';
                                        elseif($row['type'] == 'cod') $type_badge = 'bg-warning-subtle text-warning border border-warning-subtle';
                                        
                                        // ตั้งค่า Badge ตามสถานะ
                                        $status_badge = 'bg-success';
                                        $status_text = 'เปิดใช้งาน';
                                        if(($row['status'] ?? 'active') == 'inactive') {
                                            $status_badge = 'bg-secondary';
                                            $status_text = 'ปิดใช้งาน';
                                        }
                                    ?>
                                    <tr class="<?= $is_editing ?>">
                                        <td class="fw-bold"><?= $row['name'] ?></td>
                                        <td><span class="badge <?= $type_badge ?>"><?= $row['type'] ?></span></td>
                                        <td><?= !empty($row['account_name']) ? $row['account_name'] : '-' ?></td>
                                        <td><?= !empty($row['account_number']) ? $row['account_number'] : '-' ?></td>
                                        <td><span class="badge <?= $status_badge ?>"><?= $status_text ?></span></td>
                                        <td class="text-end">
                                            <a href="?edit=<?= $row['id'] ?>" class="btn btn-light btn-sm text-primary rounded-circle shadow-sm me-1" title="แก้ไข">
                                                <i class="bi bi-pencil-fill"></i>
                                            </a>
                                            <a href="?del=<?= $row['id'] ?>" class="btn btn-light btn-sm text-danger rounded-circle shadow-sm" title="ลบ" onclick="return confirm('ยืนยันการลบ?');">
                                                <i class="bi bi-trash-fill"></i>
                                            </a>
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

<script>
function toggleCustomBankName(select) {
    const customInput = document.getElementById('customBankName');
    const typeSelect = document.querySelector('select[name="type"]');
    
    if (select.value === 'custom') {
        customInput.style.display = 'block';
        customInput.required = true;
    } else {
        customInput.style.display = 'none';
        customInput.value = select.value;
        customInput.required = false;
        
        // ออโต้เลือกประเภทช่องทางชำระเงินตามชื่อเพื่อช่วยให้แอดมินทำงานง่ายขึ้น
        if (typeSelect) {
            if (select.value === 'พร้อมเพย์ (PromptPay)') {
                typeSelect.value = 'promptpay';
            } else if (select.value === 'เก็บเงินปลายทาง (COD)') {
                typeSelect.value = 'cod';
            } else {
                typeSelect.value = 'bank';
            }
        }
    }
}

// เรียกใช้ทันทีที่โหลดหน้าเสร็จเพื่อกำหนดสถานะฟอร์มเบื้องต้น
document.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('bankSelect');
    if (select) {
        toggleCustomBankName(select);
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


