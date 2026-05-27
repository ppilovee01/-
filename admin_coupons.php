<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');

// แŠเน‡ค Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

// --- Logic 1: เพิ่มคูปอง (Add) ---
if (isset($_POST['add'])) {
    $code = strtoupper(mysqli_real_escape_string($conn, $_POST['code']));
    $type = $_POST['discount_type'];
    $val = $_POST['discount_value'];
    $min = $_POST['min_spend'];
    $exp = $_POST['expiry_date'];
    
    $check = mysqli_query($conn, "SELECT id FROM coupons WHERE code='$code'");
    if(mysqli_num_rows($check) > 0) {
        echo "<script>alert('โค้ดนี้มีอยูเนˆแล้ว!');</script>";
    } else {
        $sql = "INSERT INTO coupons (code, discount_type, discount_value, min_spend, expiry_date) 
                VALUES ('$code', '$type', '$val', '$min', '$exp')";
        mysqli_query($conn, $sql);
        header("Location: admin_coupons.php"); exit();
    }
}

// --- Logic 2: ลบ (Delete) ---
if (isset($_GET['del'])) {
    $id = $_GET['del'];
    mysqli_query($conn, "DELETE FROM coupons WHERE id=$id");
    header("Location: admin_coupons.php"); exit();
}

// --- Logic 3: เตรียมขเน‰อมูลเนเเน‰เน„ข (Edit Fetch) ---
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $res = mysqli_query($conn, "SELECT * FROM coupons WHERE id=$id");
    $edit_data = mysqli_fetch_assoc($res);
}

// --- Logic 4: อัปവ (Update) ---
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $code = strtoupper(mysqli_real_escape_string($conn, $_POST['code']));
    $type = $_POST['discount_type'];
    $val = $_POST['discount_value'];
    $min = $_POST['min_spend'];
    $exp = $_POST['expiry_date'];

    $sql = "UPDATE coupons SET code='$code', discount_type='$type', discount_value='$val', min_spend='$min', expiry_date='$exp' WHERE id=$id";
    mysqli_query($conn, $sql);
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
            <h2 class="fw-bold mb-4">จัดการคูปองสเนˆวนลด</h2>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm rounded-4 p-4 sticky-top" style="top: 20px;">
                        <h5 class="fw-bold mb-3">
                            <?php if($edit_data): ?>
                                <i class="bi bi-pencil-square text-warning"></i> เนเเน‰เน„ขคูปอง
                            <?php else: ?>
                                <i class="bi bi-ticket-perforated text-blue" style="color:#AEE2FF"></i> สรเน‰างคูปองใหม่
                            <?php endif; ?>
                        </h5>
                        
                        <form method="POST">
                            <input type="hidden" name="id" value="<?= $edit_data['id'] ?? '' ?>">
                            
                            <div class="mb-3">
                                <label class="small text-muted">รหัสคูปอง (Code)</label>
                                <input type="text" name="code" class="form-control text-uppercase fw-bold" placeholder="แŠเนˆน SALE2024" value="<?= $edit_data['code'] ?? '' ?>" required>
                            </div>
                            
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="small text-muted">ประเภท</label>
                                    <select name="discount_type" class="form-select">
                                        <option value="fixed" <?= ($edit_data['discount_type'] ?? '') == 'fixed' ? 'selected' : '' ?>>ลดเป็นบาท (฿)</option>
                                        <option value="percent" <?= ($edit_data['discount_type'] ?? '') == 'percent' ? 'selected' : '' ?>>ลดเป็น %</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">มูลคเนˆาสเนˆวนลด</label>
                                    <input type="number" name="discount_value" class="form-control" placeholder="0" value="<?= $edit_data['discount_value'] ?? '' ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="small text-muted">ยอดซื้อขัเน‰นตเนˆำ (บาท)</label>
                                <input type="number" name="min_spend" class="form-control" placeholder="0 = ไม่มีขัเน‰นตเนˆำ" value="<?= $edit_data['min_spend'] ?? '0' ?>">
                            </div>

                            <div class="mb-4">
                                <label class="small text-muted">วันหมดอายุ</label>
                                <input type="date" name="expiry_date" class="form-control" value="<?= $edit_data['expiry_date'] ?? date('Y-m-d', strtotime('+1 month')) ?>" required>
                            </div>
                            
                            <?php if($edit_data): ?>
                                <div class="d-flex gap-2">
                                    <button type="submit" name="update" class="btn btn-warning w-100 rounded-3 text-white">อัปവ</button>
                                    <a href="admin_coupons.php" class="btn btn-secondary rounded-3">ยกเลิก</a>
                                </div>
                            <?php else: ?>
                                <button type="submit" name="add" class="btn btn-dark w-100 rounded-3">สรเน‰างคูปอง</button>
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
                                        <th>สเนˆวนลด</th>
                                        <th>แ‡ืเนˆอนเน„ข</th>
                                        <th>หมดอายุ</th>
                                        <th>สถานะ</th>
                                        <th class="text-end">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $res = mysqli_query($conn, "SELECT * FROM coupons ORDER BY id DESC"); 
                                    while($row = mysqli_fetch_assoc($res)): 
                                        $is_expired = (date('Y-m-d') > $row['expiry_date']);
                                        $is_editing = ($edit_data && $edit_data['id'] == $row['id']) ? 'table-warning' : '';
                                    ?>
                                    <tr class="<?= $is_editing ?>">
                                        <td>
                                            <div class="fw-bold text-primary"><?= $row['code'] ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-blue text-white" style="background:#AEE2FF">
                                                <?= $row['discount_type']=='fixed' ? '-฿'.number_format($row['discount_value']) : '-'.number_format($row['discount_value']).'%' ?>
                                            </span>
                                        </td>
                                        <td class="small text-muted">
                                            <?= $row['min_spend'] > 0 ? 'ขัเน‰นตเนˆำ ฿'.number_format($row['min_spend']) : 'ไม่มีขัเน‰นตเนˆำ' ?>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($row['expiry_date'])) ?></td>
                                        <td>
                                            <?php if($is_expired): ?>
                                                <span class="badge bg-secondary">หมดอายุ</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">ใช้งานได้</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="?edit=<?= $row['id'] ?>" class="btn btn-light btn-sm text-primary rounded-circle shadow-sm me-1"><i class="bi bi-pencil-fill"></i></a>
                                            <a href="?del=<?= $row['id'] ?>" class="btn btn-light btn-sm text-danger rounded-circle shadow-sm" onclick="return confirm('ลบคูปองนี้?');"><i class="bi bi-trash-fill"></i></a>
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


