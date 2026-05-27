<?php
session_start();
include 'db.php';

// 1. ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// --- Logic: สร้างลิงก์รีเซ็ต (แบบจำลอง) ---
if (isset($_POST['request_reset'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // 1. เช็คว่ามีอีเมลนี้ในระบบไหม
    $check = mysqli_query($conn, "SELECT id, fullname FROM users WHERE email = '$email'");
    
    if (mysqli_num_rows($check) > 0) {
        $user = mysqli_fetch_assoc($check);
        
        // 2. สร้างรหัสลับ (Token) และวันหมดอายุ (1 ชม.)
        $token = bin2hex(random_bytes(32)); 
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // 3. บันทึกลงฐานข้อมูล
        $sql = "UPDATE users SET reset_token='$token', reset_expiry='$expiry' WHERE email='$email'";
        
        if (mysqli_query($conn, $sql)) {
            // -----------------------------------------------------------
            // ⚠️ จุดที่แก้ไข: เปลี่ยนจาก Por Mae Bet Taled เป็น FitGear ให้ตรงกับเครื่องคุณ
            // -----------------------------------------------------------
            $reset_link = "http://localhost/FitGear/reset_password.php?token=" . $token;
            
            // เก็บลิงก์ไว้โชว์ใน Popup (Simulation Mode)
            $success_msg = "ระบบสร้างลิงก์เรียบร้อย (จำลองการส่งเมล)";
            $debug_link = $reset_link; 
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
    Swal.fire({
        icon: 'success',
        title: 'ตรวจสอบอีเมล (จำลอง)',
        html: 'ระบบได้สร้างลิงก์รีเซ็ตรหัสผ่านแล้ว<br><br>' +
              '<a href="<?= $debug_link ?>" class="btn btn-primary btn-sm px-4 rounded-pill">👉 คลิกที่นี่เพื่อตั้งรหัสใหม่</a>' +
              '<br><br><span class="text-muted small">(บน Server จริง ลิงก์นี้จะถูกส่งเข้าอีเมล)</span>',
        showConfirmButton: false,
        allowOutsideClick: false,
        showCloseButton: true
    });
</script>
<?php endif; ?>

<?php if(isset($error_msg)): ?>
<script>Swal.fire({icon: 'error', title: 'ขออภัย', text: '<?= $error_msg ?>', confirmButtonColor: '#333'});</script>
<?php endif; ?>

</body>
</html>


