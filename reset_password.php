<?php
session_start();
include 'db.php';

// 1. ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// ดึงอีเมลจาก Session (ถ้ามี) เพื่อใช้อ้างอิง
$session_email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : '';

// เมื่อกดปุ่ม "บันทึกรหัสผ่านใหม่" (ป้องกันการทำธุรกรรมซ้ำซ้อน)
if (isset($_POST['save_password'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $otp = mysqli_real_escape_string($conn, trim($_POST['otp']));
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    
    if (empty($email) || empty($otp) || empty($new_pass) || empty($confirm_pass)) {
        $form_error = "กรุณากรอกข้อมูลให้ครบทุกช่อง";
    } elseif ($new_pass !== $confirm_pass) {
        $form_error = "รหัสผ่านทั้งสองช่องไม่ตรงกัน";
    } elseif (strlen($new_pass) < 6) {
        $form_error = "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
    } else {
        // Security: ป้องกัน OTP Brute-force (ล็อค 30 นาที หลังพยายามผิด 5 ครั้ง)
        $otp_fail_key = 'otp_failures_' . md5($email);
        if (!isset($_SESSION[$otp_fail_key])) $_SESSION[$otp_fail_key] = ['count' => 0, 'first_attempt' => time()];
        if (time() - $_SESSION[$otp_fail_key]['first_attempt'] > 1800) $_SESSION[$otp_fail_key] = ['count' => 0, 'first_attempt' => time()];
        if ($_SESSION[$otp_fail_key]['count'] >= 5) {
            $form_error = "คุณกรอก OTP ผิดหลายครั้งเกินไป กรุณารอ 30 นาทีแล้วลองใหม่";
        }

        if (!isset($form_error)) {
        // ตรวจสอบความถูกต้องของ OTP (เปรียบเทียบวันหมดอายุใน PHP เพื่อตัดปัญหาความเหลื่อมล้ำของนาฬิกาและ Timezone ระหว่างเซิร์ฟเวอร์เว็บกับฐานข้อมูล)
        $sql = "SELECT id, reset_expiry FROM users WHERE email = '$email' AND reset_token = '$otp'";
        $result = mysqli_query($conn, $sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $user_data = mysqli_fetch_assoc($result);
            $reset_expiry = $user_data['reset_expiry'];
            $now_str = date('Y-m-d H:i:s');
            
            if ($reset_expiry && $reset_expiry > $now_str) {
                // เข้ารหัสรหัสผ่านใหม่
                $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
                
                // อัปเดตลงฐานข้อมูล + ล้าง OTP ทิ้ง
                $update = "UPDATE users SET password='$hashed_pass', reset_token=NULL, reset_expiry=NULL WHERE email='$email'";
                
                if (mysqli_query($conn, $update)) {
                    // เคลียร์ session อีเมล
                    unset($_SESSION['reset_email']);
                    
                    // ส่งข้อความแจ้งเตือนผ่าน Session ไปหน้า Login
                    $_SESSION['swal'] = [
                        'title' => 'สำเร็จ!',
                        'text' => 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว! กรุณาเข้าสู่ระบบด้วยรหัสใหม่',
                        'icon' => 'success'
                    ];
                    header("Location: login.php"); exit();
                } else {
                    error_log('Reset Password SQL Error: ' . mysqli_error($conn));
                    $form_error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่";
                }
            } else {
                $form_error = "รหัส OTP หมดอายุแล้ว กรุณาขอรหัสใหม่";
            }
        } else {
            $_SESSION[$otp_fail_key]['count']++; // นับจำนวนครั้งที่กรอก OTP ผิด
            $form_error = "รหัส OTP ไม่ถูกต้อง กรุณากรอกรหัสใหม่อีกครั้ง";
        }
        } // end if (!isset($form_error))
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <link rel="stylesheet" href="style.css?v=2.7">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit'; background: #f8f9fa; display: flex; align-items: center; min-height: 100vh; }
        .card-reset { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow: hidden; }
        .btn-blue { background: #AEE2FF; color: white; border: none; border-radius: 50px; padding: 12px; font-weight: 600; transition: 0.3s; }
        .btn-blue:hover { background: #7FB5FF; transform: translateY(-2px); }
        .link-back { text-decoration: none; color: #666; font-size: 0.9rem; transition: 0.2s; }
        .link-back:hover { color: #7FB5FF; }

        /* Dark Theme Specific Overrides */
        body.dark-theme.auth-page {
            background: #060913 !important;
        }
        body.dark-theme .card-reset {
            background: rgba(13, 20, 38, 0.65) !important;
            backdrop-filter: blur(14px) saturate(180%) !important;
            -webkit-backdrop-filter: blur(14px) saturate(180%) !important;
            box-shadow: 0 10px 40px rgba(56, 189, 248, 0.15) !important;
            border: 1px solid rgba(56, 189, 248, 0.15) !important;
        }
        body.dark-theme .link-back {
            color: #8493a8 !important;
        }
        body.dark-theme .link-back:hover {
            color: var(--blue-main) !important;
        }
        body.dark-theme .btn-blue {
            background: linear-gradient(135deg, var(--blue-main) 0%, var(--blue-hover) 100%) !important;
            color: #060913 !important;
            font-weight: 700 !important;
            box-shadow: 0 6px 20px rgba(56, 189, 248, 0.35) !important;
        }
        body.dark-theme .btn-blue:hover {
            background: linear-gradient(135deg, #7dd3fc 0%, #38bdf8 100%) !important;
            color: #060913 !important;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(56, 189, 248, 0.5) !important;
        }
        body.dark-theme .btn-outline-secondary {
            background-color: rgba(6, 9, 19, 0.8) !important;
            border-color: rgba(56, 189, 248, 0.15) !important;
            color: #8493a8 !important;
        }
        body.dark-theme .btn-outline-secondary:hover {
            background-color: rgba(13, 20, 38, 0.8) !important;
            color: #f8fafc !important;
        }
    </style>
</head>
<body class="auth-page">
<script>
    (function() {
        const theme = localStorage.getItem('theme');
        if (theme === 'dark') {
            document.body.classList.add('dark-theme');
        }
    })();
</script>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card card-reset p-4">
                <div class="card-body">
                    <h3 class="fw-bold text-center mb-2 text-dark">🔑 ตั้งรหัสผ่านใหม่</h3>
                    <p class="text-muted text-center small mb-4">กรอกอีเมล รหัส OTP 6 หลัก และตั้งรหัสผ่านใหม่ของคุณ</p>
                    
                    <form method="POST">
                        <?= get_csrf_input() ?>
                        <div class="mb-3 text-start">
                            <label class="form-label text-muted small fw-bold">อีเมลที่ลงทะเบียน</label>
                            <input type="email" name="email" class="form-control rounded-4" placeholder="name@example.com" value="<?= htmlspecialchars($session_email) ?>" required>
                        </div>
                        
                        <div class="mb-3 text-start">
                            <label class="form-label text-muted small fw-bold">รหัสยืนยัน OTP (6 หลัก)</label>
                            <input type="text" name="otp" class="form-control rounded-4" placeholder="กรอกรหัส OTP 6 หลัก" required maxlength="6" pattern="\d{6}" style="letter-spacing: 4px; font-weight: bold; text-align: center; font-size: 1.2rem;">
                        </div>
                        
                        <div class="mb-3 text-start">
                            <label class="form-label text-muted small fw-bold">รหัสผ่านใหม่</label>
                            <div class="input-group">
                                <input type="password" name="new_password" id="resetNewPass" class="form-control rounded-start-4" placeholder="อย่างน้อย 6 ตัวอักษร" required minlength="6">
                                <button class="btn btn-outline-secondary rounded-end-4 border-start-0" type="button" onclick="togglePasswordVisibility('resetNewPass', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-4 text-start">
                            <label class="form-label text-muted small fw-bold">ยืนยันรหัสผ่านอีกครั้ง</label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" id="resetConfirmPass" class="form-control rounded-start-4" placeholder="กรอกให้ตรงกับช่องบน" required minlength="6">
                                <button class="btn btn-outline-secondary rounded-end-4 border-start-0" type="button" onclick="togglePasswordVisibility('resetConfirmPass', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" name="save_password" class="btn btn-blue w-100 mb-3">บันทึกรหัสผ่านใหม่</button>
                    </form>
                    
                    <div class="text-center mt-2 d-flex justify-content-between">
                        <a href="forgot_password.php" class="link-back">← ขอรหัส OTP ใหม่</a>
                        <a href="login.php" class="link-back">เข้าสู่ระบบ →</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if(isset($form_error)): ?>
<script>Swal.fire({icon:'error', title:'ผิดพลาด', text:'<?= htmlspecialchars($form_error, ENT_QUOTES, 'UTF-8') ?>', confirmButtonColor: '#7FB5FF'});</script>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
