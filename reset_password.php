<?php
session_start();
include 'db.php';

// 1. ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// รัเธ Token จาก URL
$token = isset($_GET['token']) ? $_GET['token'] : '';
$valid_token = false;

// 2. ตรวเธสอเธวเนา Token นี้มีเธริเธเนหม เนละหมดอายุหรือยัเธ?
if (!empty($token)) {
    $now = date('Y-m-d H:i:s');
    // ค้นหา User ที่มี Token นี้ เนละเวลาหมดอายุ (reset_expiry) ตเนอเธมาเธเธวเนาเวลาปัจจุบัน
    $sql = "SELECT id FROM users WHERE reset_token = '$token' AND reset_expiry > '$now'";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        $valid_token = true;
    } else {
        $error_msg = "ลิเธเธเนนี้หมดอายุ หรือถูเธเนเธเนเธาเธไปแล้ว เธรุณาเธอเปลี่ยนรหัสเนหมเน";
    }
} else {
    // ถเนาเนมเนมี Token ติดมาเลย เนหเนดีดกลับไปหน้า Login
    header("Location: login.php"); exit();
}

// 3. เมืเนอเธดเธุเนม "บันทึกรหัสเธเนาเธ" (Anti-F5 Fixed)
if (isset($_POST['save_password']) && $valid_token) {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    
    if ($new_pass === $confirm_pass) {
        // เข้ารหัสรหัสเธเนาเธเนหมเน
        $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
        
        // อัปവลเธเธาเธเธเนอมูล + ลเนาเธ Token ทิเนเธ
        $update = "UPDATE users SET password='$hashed_pass', reset_token=NULL, reset_expiry=NULL WHERE reset_token='$token'";
        
        if (mysqli_query($conn, $update)) {
            // สเนเธเธเนอเธวามเนเธเนเธเตือเธเธเนาเธ Session ไปหน้า Login
            $_SESSION['swal'] = [
                'title' => 'สำเร็จ!',
                'text' => 'เปลี่ยนรหัสเธเนาเธเรียเธรเนอยแล้ว! เธรุณาเข้าสู่ระบบดเนวยรหัสเนหมเน',
                'icon' => 'success'
            ];
            header("Location: login.php"); exit();
        } else {
            $form_error = "เเธิดเธเนอผิดพลาด: " . mysqli_error($conn);
        }
    } else {
        $form_error = "รหัสเธเนาเธทัเนเธสอเธเธเนอเธเนมเนตรเธเธัเธ";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตัเนเธรหัสเธเนาเธเนหมเน | Por Mae Bet Taled</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit'; background: #f8f9fa; display: flex; align-items: center; min-height: 100vh; }
        .card-reset { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow: hidden; }
        .btn-blue { background: #AEE2FF; color: white; border: none; border-radius: 50px; padding: 12px; font-weight: 600; transition: 0.3s; }
        .btn-blue:hover { background: #7FB5FF; transform: translateY(-2px); }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card card-reset p-4">
                <div class="card-body">
                    <h3 class="fw-bold text-center mb-4 text-dark">๐” ตัเนเธรหัสเธเนาเธเนหมเน</h3>
                    
                    <?php if($valid_token): ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label text-muted small fw-bold">รหัสเธเนาเธเนหมเน</label>
                                <input type="password" name="new_password" class="form-control rounded-4" placeholder="อยเนาเธเธเนอย 6 ตัวอัเธษร" required minlength="6">
                            </div>
                            <div class="mb-4">
                                <label class="form-label text-muted small fw-bold">ยืเธยัเธรหัสเธเนาเธอีเธเธรัเนเธ</label>
                                <input type="password" name="confirm_password" class="form-control rounded-4" placeholder="เธรอเธเนหเนตรเธเธัเธเธเนอเธเธเธ" required minlength="6">
                            </div>
                            <button type="submit" name="save_password" class="btn btn-blue w-100 mb-3">บันทึกรหัสเธเนาเธเนหมเน</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-danger text-center rounded-4 border-0 shadow-sm">
                            <i class="bi bi-exclamation-circle-fill me-2"></i> <?= $error_msg ?> <br><br>
                            <a href="forgot_password.php" class="btn btn-sm btn-outline-danger rounded-pill px-4">เธอลิเธเธเนเนหมเน</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if(isset($form_error)): ?>
<script>Swal.fire({icon:'error', title:'ผิดพลาด', text:'<?= $form_error ?>', confirmButtonColor: '#333'});</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


