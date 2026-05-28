<?php
session_start();
include 'db.php';

// ตั้งค่า Timezone ให้ตรงเับเน„ทยเสมอ
date_default_timezone_set('Asia/Bangkok');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

// --- Logic 1: เพิ่มขเน‰อมูล (Add) ---
if (isset($_POST['add'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $type = $_POST['type'];
    $num = mysqli_real_escape_string($conn, $_POST['account_number']);
    
    $sql = "INSERT INTO payment_methods (name, type, account_number) VALUES ('$name', '$type', '$num')";
    if(mysqli_query($conn, $sql)) {
        header("Location: admin_payments.php"); exit();
    }
}

// --- Logic 2: ลบขเน‰อมูล (Delete) ---
if (isset($_GET['del'])) {
    $id = $_GET['del'];
    mysqli_query($conn, "DELETE FROM payment_methods WHERE id=$id");
    header("Location: admin_payments.php"); exit();
}

// --- Logic 3: เตรียมขเน‰อมูลเนเเน‰เน„ข (Fetch for Edit) ---
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $res = mysqli_query($conn, "SELECT * FROM payment_methods WHERE id=$id");
    $edit_data = mysqli_fetch_assoc($res);
}

// --- Logic 4: อัปവขเน‰อมูล (Update) ---
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $type = $_POST['type'];
    $num = mysqli_real_escape_string($conn, $_POST['account_number']);

    $sql = "UPDATE payment_methods SET name='$name', type='$type', account_number='$num' WHERE id=$id";
    if(mysqli_query($conn, $sql)) {
        header("Location: admin_payments.php"); exit();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ช่องทางเŠำระแ‡ิน | Por Mae Bet Taled Admin</title>
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
                                <label class="small text-muted">ชื่อธนาคาร/ช่องทาง</label>
                                <input type="text" name="name" class="form-control" value="<?= $edit_data['name'] ?? '' ?>" required>
                            </div>
                            
                            <div class="mb-2">
                                <label class="small text-muted">ประเภท</label>
                                <select name="type" class="form-select">
                                    <option value="bank" <?= ($edit_data['type'] ?? '') == 'bank' ? 'selected' : '' ?>>บัญชีธนาคาร</option>
                                    <option value="promptpay" <?= ($edit_data['type'] ?? '') == 'promptpay' ? 'selected' : '' ?>>เžรเน‰อมแžยเนŒ (QR)</option>
                                    <option value="cod" <?= ($edit_data['type'] ?? '') == 'cod' ? 'selected' : '' ?>>เก็บแ‡ินปลายทาง</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="small text-muted">เลขบัญชี/เบอร์พร้อมเพย์</label>
                                <input type="text" name="account_number" class="form-control" value="<?= $edit_data['account_number'] ?? '' ?>">
                            </div>
                            
                            <?php if($edit_data): ?>
                                <div class="d-flex gap-2">
                                    <button type="submit" name="update" class="btn btn-warning w-100 rounded-3 text-white">อัปเดตข้อมูล</button>
                                    <a href="admin_payments.php" class="btn btn-secondary rounded-3">ยกเลิก</a>
                                </div>
                            <?php else: ?>
                                <button type="submit" name="add" class="btn btn-dark w-100 rounded-3">บันทึก</button>
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
                                        <th>เลขบัญชี/เบอร์พร้อมเพย์</th>
                                        <th class="text-end">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $res = mysqli_query($conn, "SELECT * FROM payment_methods"); 
                                    while($row = mysqli_fetch_assoc($res)): 
                                        $is_editing = ($edit_data && $edit_data['id'] == $row['id']) ? 'table-warning' : '';
                                    ?>
                                    <tr class="<?= $is_editing ?>">
                                        <td class="fw-bold"><?= $row['name'] ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= $row['type'] ?></span></td>
                                        <td><?= $row['account_number'] ?></td>
                                        <td class="text-end">
                                            <a href="?edit=<?= $row['id'] ?>" class="btn btn-light btn-sm text-primary rounded-circle shadow-sm me-1" title="เนเเน‰เน„ข">
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


