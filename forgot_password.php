<?php
session_start();
include 'db.php';

// 1. ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// --- Logic: สร้างรหัส OTP ส่งเข้าอีเมล ---
if (isset($_POST['request_reset'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // 1. เช็คว่ามีอีเมลนี้ในระบบไหม
    $check = mysqli_query($conn, "SELECT id, fullname FROM users WHERE email = '$email'");
    
    if (mysqli_num_rows($check) > 0) {
        $user = mysqli_fetch_assoc($check);
        
        // 2. สร้างรหัส OTP 6 หลัก และวันหมดอายุ (15 นาที)
        // ใช้ NOW() ของ MySQL เพื่อป้องกันปัญหา timezone ต่างกันระหว่าง PHP กับ MySQL server
        $otp = sprintf("%06d", rand(100000, 999999));
        
        // 3. บันทึกลงฐานข้อมูล (คำนวณวันหมดอายุใน PHP เพื่อหลีกเลี่ยงปัญหาความเหลื่อมล้ำของนาฬิกาบน Hosting)
        $expiry_str = date('Y-m-d H:i:s', time() + 900); // 15 นาที
        $sql = "UPDATE users SET reset_token='$otp', reset_expiry='$expiry_str' WHERE email='$email'";
        
        if (mysqli_query($conn, $sql)) {
            include 'mail_sender.php';
            $_SESSION['reset_email'] = $email;
            
            // ส่งอีเมลจริง
            $sent = send_password_reset_email($conn, $email, $otp);
            if ($sent) {
                $success_msg = "ระบบได้ส่งรหัส OTP ไปยังอีเมลของท่านเรียบร้อยแล้ว";
                $otp_sent = true;
            } else {
                $success_msg = "ระบบสร้างรหัส OTP เรียบร้อย (จำลองเนื่องจากไม่ได้ตั้งค่า SMTP)";
                $debug_otp = $otp;
                $otp_sent = false;
            }
        } else {
            $error_msg = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
        }
    } else {
        $error_msg = "ไม่พบอีเมลนี้ในระบบ";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน | Por Mae Bet Taled</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit'; background: #f8f9fa; display: flex; align-items: center; min-height: 100vh; }
        .card-auth { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow: hidden; }
        .btn-blue { background: #AEE2FF; color: white; border: none; border-radius: 50px; padding: 10px; font-weight: 600; transition: 0.3s; }
        .btn-blue:hover { background: #7FB5FF; transform: translateY(-2px); }
        .link-back { text-decoration: none; color: #666; font-size: 0.9rem; transition: 0.2s; }
        .link-back:hover { color: #AEE2FF; }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card card-auth p-4">
                <div class="card-body text-center">
                    <div class="mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" fill="#AEE2FF" class="bi bi-shield-lock-fill" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M8 0c-.69 0-1.843.265-2.928.56-1.11.3-2.229.655-2.887.87a1.54 1.54 0 0 0-1.044 1.262c-.596 4.477.787 7.795 2.465 9.99a11.777 11.777 0 0 0 2.517 2.453c.386.273.744.482 1.048.625.28.132.581.24.829.24s.548-.108.829-.24a7.159 7.159 0 0 0 1.048-.625 11.775 11.775 0 0 0 2.517-2.453c1.678-2.195 3.061-5.513 2.465-9.99a1.541 1.541 0 0 0-1.044-1.263 62.467 62.467 0 0 0-2.887-.87C9.843.266 8.69 0 8 0zm0 5a1.5 1.5 0 0 1 .5 2.915l.385 1.99a.5.5 0 0 1-.491.595h-.788a.5.5 0 0 1-.49-.595l.384-1.99A1.5 1.5 0 0 1 8 5z"/>
                        </svg>
                    </div>
                    <h3 class="fw-bold mb-2">ลืมรหัสผ่าน?</h3>
                    <p class="text-muted small mb-4">กรอกอีเมลของคุณเพื่อรับลิงก์สำหรับตั้งรหัสผ่านใหม่</p>
                    
                    <form method="POST">
                        <?= get_csrf_input() ?>
                        <div class="form-floating mb-3 text-start">
                            <input type="email" name="email" class="form-control rounded-4" id="emailInput" placeholder="name@example.com" required>
                            <label for="emailInput">อีเมลที่ใช้สมัครสมาชิก</label>
                        </div>
                        <button type="submit" name="request_reset" class="btn btn-blue w-100 mb-3">ส่งคำขอเปลี่ยนรหัส</button>
                    </form>
                    
                    <a href="login.php" class="link-back">← กลับไปหน้าเข้าสู่ระบบ</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if(isset($success_msg)): ?>
<script>
    <?php if(isset($otp_sent) && $otp_sent): ?>
    Swal.fire({
        icon: 'success',
        title: 'ส่ง OTP สำเร็จ',
        text: 'ระบบได้ส่งรหัส OTP ไปยังอีเมล <?= htmlspecialchars($email) ?> เรียบร้อยแล้ว (รหัสมีอายุ 15 นาที)',
        confirmButtonText: 'ไปหน้าตั้งรหัสผ่านใหม่',
        confirmButtonColor: '#7FB5FF',
        allowOutsideClick: false
    }).then(() => {
        window.location.href = 'reset_password.php';
    });
    <?php else: ?>
    Swal.fire({
        icon: 'success',
        title: 'ตรวจสอบ OTP (จำลอง)',
        html: 'ระบบได้สร้างรหัส OTP รีเซ็ตรหัสผ่านแล้ว<br><br>' +
              '<h2 class="text-primary fw-bold" style="letter-spacing: 4px;"><?= $debug_otp ?></h2>' +
              '<br><span class="text-muted small">(บน Server จริง รหัสนี้จะถูกส่งเข้าอีเมลของท่าน)</span>',
        confirmButtonText: 'ไปหน้าตั้งรหัสผ่านใหม่',
        confirmButtonColor: '#7FB5FF',
        allowOutsideClick: false,
        showCloseButton: true
    }).then(() => {
        window.location.href = 'reset_password.php';
    });
    <?php endif; ?>
</script>
<?php endif; ?>

<?php if(isset($error_msg)): ?>
<script>Swal.fire({icon: 'error', title: 'ขออภัย', text: '<?= $error_msg ?>', confirmButtonColor: '#333'});</script>
<?php endif; ?>

<script>
// ระบบป้องกันการส่งฟอร์มซ้ำ (Double-Submit Prevention)
document.addEventListener('submit', function(e) {
    var form = e.target;
    if (e.defaultPrevented) return;
    if (form.classList.contains('is-submitting')) {
        e.preventDefault();
        return false;
    }
    
    var activeBtn = document.activeElement;
    var btn = form.querySelector('button[type="submit"], input[type="submit"]');
    var submitBtn = (activeBtn && activeBtn.form === form && activeBtn.type === 'submit') ? activeBtn : btn;
    
    form.classList.add('is-submitting');
    
    if (submitBtn && submitBtn.name) {
        var hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = submitBtn.name;
        hiddenInput.value = submitBtn.value;
        form.appendChild(hiddenInput);
    }
    
    if (submitBtn) {
        setTimeout(function() {
            submitBtn.disabled = true;
            if (submitBtn.tagName === 'INPUT') {
                submitBtn.value = 'กำลังประมวลผล...';
            } else {
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังส่งข้อมูล...';
            }
        }, 1);
    }
});
</script>
</body>
</html>


