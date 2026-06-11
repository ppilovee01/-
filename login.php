<?php
include 'db.php';

// --- Logic: สมัครสมาชิก (Anti-F5 Fixed) ---
if (isset($_POST['register'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
    // Security: ตรวจสอบความยาวรหัสผ่านฝั่ง Server
    if (strlen($_POST['password']) < 6) {
        $_SESSION['swal'] = ['title'=>'แจ้งเตือน', 'text'=>'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร', 'icon'=>'error'];
        $_SESSION['active_tab'] = 'register';
        header('Location: login.php');
        exit();
    }
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $name = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$user' OR email='$email'");
    
    if(mysqli_num_rows($check) > 0) {
        $_SESSION['swal'] = ['title'=>'แจ้งเตือน', 'text'=>'ชื่อผู้ใช้ หรือ อีเมลนี้ มีคนใช้แล้ว!', 'icon'=>'error'];
        $_SESSION['active_tab'] = 'register';
    } else {
        $sql = "INSERT INTO users (username, password, fullname, email, role, created_at) VALUES ('$user', '$pass', '$name', '$email', 'user', NOW())";
        if(mysqli_query($conn, $sql)){
            $new_user_id = mysqli_insert_id($conn);
            log_admin_action($conn, 'สมัครสมาชิก', "ลูกค้าสมัครสมาชิกใหม่ ชื่อผู้ใช้: $user, อีเมล: $email", $new_user_id, $name);
            $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ', 'icon'=>'success'];
            $_SESSION['active_tab'] = 'login';
        } else {
            error_log('Registration SQL Error: ' . mysqli_error($conn));
            $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่', 'icon'=>'error'];
            $_SESSION['active_tab'] = 'register';
        }
    }
    header("Location: login.php"); exit();
}

// --- Logic: เข้าสู่ระบบ ---
if (isset($_POST['login'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
    // Rate limiting: ป้องกัน Brute-force Attack
    $rate_key = 'login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? '');
    if (!isset($_SESSION[$rate_key])) $_SESSION[$rate_key] = ['count' => 0, 'first_attempt' => time()];
    if (time() - $_SESSION[$rate_key]['first_attempt'] > 900) $_SESSION[$rate_key] = ['count' => 0, 'first_attempt' => time()];
    if ($_SESSION[$rate_key]['count'] >= 5) {
        $_SESSION['swal'] = ['title'=>'ถูกระงับชั่วคราว', 'text'=>'คุณพยายามเข้าสู่ระบบผิดหลายครั้ง กรุณารอ 15 นาที', 'icon'=>'warning'];
        $_SESSION['active_tab'] = 'login';
        header('Location: login.php'); exit();
    }
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = $_POST['password'];
    $res = mysqli_query($conn, "SELECT * FROM users WHERE username='$user'");
    $u = mysqli_fetch_assoc($res);
    if ($u && password_verify($pass, $u['password'])) {
        session_regenerate_id(true);
        unset($_SESSION[$rate_key]); // Reset rate limit on successful login
        $_SESSION['user_id'] = $u['id'];
        $_SESSION['fullname'] = $u['fullname'];
        $_SESSION['role'] = $u['role'];
        mysqli_query($conn, "UPDATE users SET last_login = NOW() WHERE id = " . intval($u['id']));
        log_admin_action($conn, 'เข้าสู่ระบบ', "ลูกค้าเข้าสู่ระบบสำเร็จ ชื่อผู้ใช้: {$u['username']}", $u['id'], $u['fullname']);
        header("Location: index.php");
        exit();
    } else {
        $_SESSION[$rate_key]['count']++; // Increment failed login attempts
        $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>'ชื่อผู้ใช้หรือรหัสผ่านผิด!', 'icon'=>'error'];
        $_SESSION['active_tab'] = 'login';
        header("Location: login.php"); exit();
    }
}

$active_tab = isset($_SESSION['active_tab']) ? $_SESSION['active_tab'] : "login";
unset($_SESSION['active_tab']);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | Por Mae Bet Taled</title>
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --blue-main: #85D1FF; --blue-hover: #6BBEFF; --bg-light: #f8f9fa; }
        body { font-family: 'Kanit', sans-serif; height: 100vh; overflow: hidden; background: #fff; }
        .bg-image-col { background: url('https://images.unsplash.com/photo-1574680096141-1cddd32e38e1?q=80&w=1200') no-repeat center center; background-size: cover; position: relative; min-height: 100vh; }
        .bg-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to bottom, rgba(133,209,255,0.9), rgba(0,0,0,0.5)); display: flex; flex-direction: column; justify-content: center; padding: 4rem; color: white; }
        .form-col { display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100vh; padding: 2rem; overflow-y: auto; background-color: #ffffff; }
        .form-container { width: 100%; max-width: 420px; }
        .nav-pills { background: var(--bg-light); padding: 6px; border-radius: 50px; display: grid; grid-template-columns: 1fr 1fr; gap: 5px; width: 100%; margin-bottom: 30px; border: 1px solid #e9ecef; }
        .nav-item { width: 100%; text-align: center; }
        .nav-pills .nav-link { border-radius: 50px; color: #868e96; font-weight: 600; width: 100%; text-align: center; transition: all 0.3s ease; padding: 10px 0; border: none; }
        .nav-pills .nav-link.active { background: white; color: var(--blue-main); box-shadow: 0 4px 12px rgba(0,0,0,0.05); font-weight: 700; }
        .form-control { border-radius: 12px; padding: 12px 15px; border: 2px solid #f8f9fa; background: #f8f9fa; transition: 0.3s; }
        .form-control:focus { border-color: var(--blue-main); background: #fff; box-shadow: none; }
        .input-group-text { border: 2px solid #f8f9fa; border-right: none; background: #f8f9fa; }
        .btn-auth { background: var(--blue-main); color: white; border: none; border-radius: 50px; padding: 14px; width: 100%; font-weight: 600; box-shadow: 0 10px 20px rgba(133, 209, 255, 0.4); transition: all 0.3s; }
        .btn-auth:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(107, 190, 255, 0.6); color: white; background: var(--blue-hover); }
        @media (max-width: 768px) { .bg-image-col { display: none; } body { background: #fff; } }
    </style>
</head>
<body>
<div class="container-fluid p-0">
    <div class="row g-0">
        <div class="col-lg-7 d-none d-lg-block bg-image-col animate__animated animate__fadeIn">
            <div class="bg-overlay">
                <h1 class="display-3 fw-bold mb-3">Por Mae Bet Taled<span style="color: #fff;">.</span></h1>
                <p class="fs-4 fw-light opacity-75">สินค้าเบ็ดเตล็ดคุณภาพดี<br>เพื่อทุกความต้องการของคุณ</p>
            </div>
        </div>
        <div class="col-lg-5 form-col animate__animated animate__fadeInRight">
            <div class="form-container">
                <div class="text-center mb-4 d-lg-none">
                    <h2 class="fw-bold text-dark">Por Mae<span style="color:var(--blue-main)">.</span></h2>
                </div>
                <h3 class="fw-bold mb-2 text-center">ยินดีต้อนรับ</h3>
                <p class="text-muted text-center mb-4">เข้าสู่ระบบเพื่อจัดการออเดอร์ของคุณ</p>

                <ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation"><button class="nav-link <?= $active_tab=='login'?'active':'' ?>" data-bs-toggle="pill" data-bs-target="#login-form" type="button">เข้าสู่ระบบ</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link <?= $active_tab=='register'?'active':'' ?>" data-bs-toggle="pill" data-bs-target="#reg-form" type="button">สมัครสมาชิก</button></li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade <?= $active_tab=='login'?'show active':'' ?>" id="login-form">
                        <form method="POST">
                            <?= get_csrf_input() ?>
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text rounded-start-4 text-muted"><i class="bi bi-person"></i></span>
                                    <input type="text" name="username" class="form-control rounded-end-4 border-start-0 ps-2" placeholder="ชื่อผู้ใช้" required>
                                </div>
                            </div>
                            <div class="mb-2">
                                <div class="input-group">
                                    <span class="input-group-text rounded-start-4 text-muted"><i class="bi bi-key"></i></span>
                                    <input type="password" name="password" id="loginPassword" class="form-control border-start-0 ps-2" placeholder="รหัสผ่าน" required>
                                    <button class="btn btn-outline-light border border-start-0 text-muted rounded-end-4" type="button" onclick="togglePasswordVisibility('loginPassword', this)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="text-end mb-3"><a href="forgot_password.php" class="text-decoration-none small text-muted">ลืมรหัสผ่าน?</a></div>
                            <button type="submit" name="login" class="btn btn-auth mb-3">เข้าสู่ระบบ <i class="bi bi-arrow-right-short"></i></button>
                        </form>
                    </div>

                    <div class="tab-pane fade <?= $active_tab=='register'?'show active':'' ?>" id="reg-form">
                        <form method="POST">
                            <?= get_csrf_input() ?>
                            <div class="mb-3"><input type="text" name="fullname" class="form-control rounded-4 ps-3" placeholder="ชื่อ-นามสกุล" required></div>
                            <div class="mb-3"><input type="email" name="email" class="form-control rounded-4 ps-3" placeholder="อีเมล" required></div>
                            <div class="row">
                                <div class="col-6 mb-3"><input type="text" name="username" class="form-control rounded-4 ps-3" placeholder="ชื่อผู้ใช้ (Eng)" required></div>
                                <div class="col-6 mb-3">
                                    <div class="input-group">
                                        <input type="password" name="password" id="regPassword" class="form-control rounded-start-4 ps-3" placeholder="รหัส 6 ตัวขึ้นไป" required>
                                        <button class="btn btn-outline-light border border-start-0 text-muted rounded-end-4" type="button" onclick="togglePasswordVisibility('regPassword', this)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" name="register" class="btn btn-auth mb-3">สมัครสมาชิก</button>
                        </form>
                    </div>
                </div>
                <div class="text-center mt-4"><a href="index.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> กลับหน้าหลัก</a></div>
            </div>
        </div>
    </div>
</div>

<?php if(isset($_SESSION['swal'])): ?>
<script>Swal.fire({icon: '<?= htmlspecialchars($_SESSION['swal']['icon'], ENT_QUOTES, 'UTF-8') ?>', title: '<?= htmlspecialchars($_SESSION['swal']['title'], ENT_QUOTES, 'UTF-8') ?>', text: '<?= htmlspecialchars($_SESSION['swal']['text'], ENT_QUOTES, 'UTF-8') ?>', confirmButtonColor: '#85D1FF'});</script>
<?php unset($_SESSION['swal']); endif; ?>

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