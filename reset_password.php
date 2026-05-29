<?php
session_start();
include 'db.php';

// 1. ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// รับ Token จาก URL
$token = isset($_GET['token']) ? $_GET['token'] : '';
$valid_token = false;

// 2. ตรวจสอบว่า Token นี้มีจริงไหม และหมดอายุหรือยัง?
if (!empty($token)) {
    $now = date('Y-m-d H:i:s');
    // ค้นหา User ที่มี Token นี้ และเวลาหมดอายุ (reset_expiry) ต้องมากกว่าเวลาปัจจุบัน
    $sql = "SELECT id FROM users WHERE reset_token = '$token' AND reset_expiry > '$now'";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        $valid_token = true;
    } else {
        $error_msg = "ลิงก์นี้หมดอายุ หรือถูกใช้งานไปแล้ว กรุณาขอเปลี่ยนรหัสใหม่";
    }
} else {
    // ถ้าไม่มี Token ติดมาเลย ให้ดีดกลับไปหน้า Login
    header("Location: login.php"); exit();
}

// 3. เมื่อกดปุ่ม "บันทึกรหัสผ่าน" (Anti-F5 Fixed)
if (isset($_POST['save_password']) && $valid_token) {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    
    if ($new_pass === $confirm_pass) {
        // เข้ารหัสรหัสผ่านใหม่
        $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
        
        // อัปเดตลงฐานข้อมูล + ล้าง Token ทิ้ง
        $update = "UPDATE users SET password='$hashed_pass', reset_token=NULL, reset_expiry=NULL WHERE reset_token='$token'";
        
        if (mysqli_query($conn, $update)) {
            // ส่งข้อความแจ้งเตือนผ่าน Session ไปหน้า Login
            $_SESSION['swal'] = [
                'title' => 'สำเร็จ!',
                'text' => 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว! กรุณาเข้าสู่ระบบด้วยรหัสใหม่',
                'icon' => 'success'
            ];
            header("Location: login.php"); exit();
        } else {
            $form_error = "เกิดข้อผิดพลาด: " . mysqli_error($conn);
        }
    } else {
        $form_error = "รหัสผ่านทั้งสองช่องไม่ตรงกัน";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งรหัสผ่านใหม่ | Por Mae Bet Taled</title>
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
                    <h3 class="fw-bold text-center mb-4 text-dark">🔑 ตั้งรหัสผ่านใหม่</h3>
                    
                    <?php if($valid_token): ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label text-muted small fw-bold">รหัสผ่านใหม่</label>
                                <div class="input-group">
                                    <input type="password" name="new_password" id="resetNewPass" class="form-control rounded-start-4" placeholder="อย่างน้อย 6 ตัวอักษร" required minlength="6">
                                    <button class="btn btn-outline-secondary rounded-end-4 border-start-0" type="button" onclick="togglePasswordVisibility('resetNewPass', this)" style="background: white; border-color: #dee2e6;">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label text-muted small fw-bold">ยืนยันรหัสผ่านอีกครั้ง</label>
                                <div class="input-group">
                                    <input type="password" name="confirm_password" id="resetConfirmPass" class="form-control rounded-start-4" placeholder="กรอกให้ตรงกับช่องบน" required minlength="6">
                                    <button class="btn btn-outline-secondary rounded-end-4 border-start-0" type="button" onclick="togglePasswordVisibility('resetConfirmPass', this)" style="background: white; border-color: #dee2e6;">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" name="save_password" class="btn btn-blue w-100 mb-3">บันทึกรหัสผ่านใหม่</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-danger text-center rounded-4 border-0 shadow-sm">
                            <i class="bi bi-exclamation-circle-fill me-2"></i> <?= $error_msg ?> <br><br>
                            <a href="forgot_password.php" class="btn btn-sm btn-outline-danger rounded-pill px-4">ขอลิงก์ใหม่</a>
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

<script>
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

// ระบบป้องกันการส่งฟอร์มซ้ำ (Double-Submit Prevention)
document.addEventListener('submit', function(e) {
    var form = e.target;
    if (e.defaultPrevented) return;
    if (form.classList.contains('is-submitting')) {
        e.preventDefault();
        return false;
    }
    form.classList.add('is-submitting');
    var btn = form.querySelector('button[type="submit"], input[type="submit"]');
    if (btn) {
        btn.disabled = true;
        if (btn.tagName === 'INPUT') {
            btn.value = 'กำลังประมวลผล...';
        } else {
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังส่งข้อมูล...';
        }
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


