<?php
session_start();
include 'db.php';
include 'mail_sender.php';

// ป้องกันผู้ที่ไม่ได้ล็อกอิน หรือไม่ได้เป็นแอดมิน เข้าใช้งานหน้านี้
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// หากผ่านการยืนยัน 2FA เรียบร้อยแล้ว ให้เด้งไปหน้าแอดมินบอร์ดเลย
if (!empty($_SESSION['2fa_verified'])) {
    header("Location: admin_dashboard.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$fullname = $_SESSION['fullname'] ?? 'Admin';

// 1. ดึงข้อมูลอีเมลของแอดมินคนนี้มาเพื่อส่งรหัส OTP (ใช้ Prepared Statement)
$email = "";
$stmt = mysqli_prepare($conn, "SELECT email, fullname FROM users WHERE id = ? LIMIT 1");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $db_email, $db_fullname);
    if (mysqli_stmt_fetch($stmt)) {
        $email = $db_email;
        if (!empty($db_fullname)) {
            $fullname = $db_fullname;
        }
    }
    mysqli_stmt_close($stmt);
}

if (empty($email)) {
    die("Error: ไม่พบอีเมลสำหรับบัญชีผู้ดูแลระบบนี้ในระบบ");
}

$error_msg = "";
$success_msg = "";

// 2. ฟังก์ชันสำหรับสร้างและส่งรหัส OTP
function generateAndSendOTP($conn, $user_id, $email, $fullname) {
    $otp = sprintf("%06d", mt_rand(100000, 999999));
    $expires_at = date('Y-m-d H:i:s', time() + 300); // หมดอายุใน 5 นาที

    // ลบรหัส OTP เก่าของแอดมินคนนี้ออกก่อน
    $stmt_del = mysqli_prepare($conn, "DELETE FROM admin_2fa_otps WHERE user_id = ?");
    if ($stmt_del) {
        mysqli_stmt_bind_param($stmt_del, "i", $user_id);
        mysqli_stmt_execute($stmt_del);
        mysqli_stmt_close($stmt_del);
    }

    // บันทึกรหัส OTP ใหม่ลงในฐานข้อมูล
    $stmt_ins = mysqli_prepare($conn, "INSERT INTO admin_2fa_otps (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
    if ($stmt_ins) {
        mysqli_stmt_bind_param($stmt_ins, "iss", $user_id, $otp, $expires_at);
        mysqli_stmt_execute($stmt_ins);
        mysqli_stmt_close($stmt_ins);
    }

    // ส่งอีเมล
    $send_ok = send_admin_2fa_otp($conn, $email, $fullname, $otp);
    if ($send_ok) {
        $_SESSION['last_otp_sent_time'] = time();
        return true;
    }
    return false;
}

// 3. จัดการกรณีโหลดครั้งแรก (ถ้าไม่มี OTP ที่ยังไม่หมดอายุใน DB ให้สร้างและส่งใหม่ทันที)
$need_send = true;
$stmt_chk = mysqli_prepare($conn, "SELECT id FROM admin_2fa_otps WHERE user_id = ? AND expires_at > NOW() LIMIT 1");
if ($stmt_chk) {
    mysqli_stmt_bind_param($stmt_chk, "i", $user_id);
    mysqli_stmt_execute($stmt_chk);
    mysqli_stmt_store_result($stmt_chk);
    if (mysqli_stmt_num_rows($stmt_chk) > 0) {
        $need_send = false; // มีรหัสที่ยังไม่หมดอายุอยู่แล้ว ไม่ต้องส่งซ้ำตอนเข้าหน้าครั้งแรก
    }
    mysqli_stmt_close($stmt_chk);
}

if ($need_send && !isset($_POST['verify_otp']) && !isset($_POST['resend_otp'])) {
    if (generateAndSendOTP($conn, $user_id, $email, $fullname)) {
        $success_msg = "ส่งรหัส OTP ไปยังอีเมล " . getMaskedEmail($email) . " เรียบร้อยแล้ว!";
    } else {
        $error_msg = "เกิดข้อผิดพลาดในการส่งอีเมล OTP กรุณาตรวจสอบการตั้งค่า SMTP ในระบบ";
    }
}

// ฟังก์ชันเซนเซอร์อีเมล (เช่น a•••••b@domain.com)
function getMaskedEmail($email) {
    $parts = explode('@', $email);
    if (count($parts) < 2) return $email;
    $name = $parts[0];
    $domain = $parts[1];
    $len = strlen($name);
    if ($len <= 3) {
        return substr($name, 0, 1) . '•••@' . $domain;
    }
    return substr($name, 0, 2) . str_repeat('•', $len - 3) . substr($name, -1) . '@' . $domain;
}

// 4. จัดการปุ่มกดส่งรหัสใหม่ (Resend OTP)
if (isset($_POST['resend_otp'])) {
    $cooldown = 60; // คูลดาวน์ 60 วินาที
    $elapsed = time() - ($_SESSION['last_otp_sent_time'] ?? 0);
    if ($elapsed < $cooldown) {
        $wait_sec = $cooldown - $elapsed;
        $error_msg = "กรุณารอสักครู่ คุณจะสามารถกดส่งรหัสใหม่ได้อีกครั้งในอีก {$wait_sec} วินาที";
    } else {
        if (generateAndSendOTP($conn, $user_id, $email, $fullname)) {
            $success_msg = "ได้ทำการส่งรหัส OTP ชุดใหม่ไปยังอีเมลของคุณเรียบร้อยแล้ว!";
        } else {
            $error_msg = "เกิดข้อผิดพลาดในการส่งอีเมล OTP กรุณาลองใหม่อีกครั้ง";
        }
    }
}

// 5. จัดการเมื่อกดปุ่มยืนยันรหัส OTP (Verify OTP)
if (isset($_POST['verify_otp'])) {
    $entered_otp = trim($_POST['otp_code'] ?? '');
    
    if (empty($entered_otp) || strlen($entered_otp) !== 6 || !is_numeric($entered_otp)) {
        $error_msg = "กรุณากรอกรหัส OTP เป็นตัวเลข 6 หลักให้ถูกต้อง";
    } else {
        // ตรวจสอบความถูกต้องของรหัส OTP ในฐานข้อมูล (ใช้ Prepared Statement)
        $is_valid = false;
        $stmt_verify = mysqli_prepare($conn, "SELECT id FROM admin_2fa_otps WHERE user_id = ? AND otp_code = ? AND expires_at > NOW() LIMIT 1");
        if ($stmt_verify) {
            mysqli_stmt_bind_param($stmt_verify, "is", $user_id, $entered_otp);
            mysqli_stmt_execute($stmt_verify);
            mysqli_stmt_store_result($stmt_verify);
            if (mysqli_stmt_num_rows($stmt_verify) > 0) {
                $is_valid = true;
            }
            mysqli_stmt_close($stmt_verify);
        }

        if ($is_valid) {
            // ยืนยันรหัสผ่านสำเร็จ! 
            $_SESSION['2fa_verified'] = true;
            
            // ลบรหัส OTP ที่ใช้แล้วออก
            $stmt_clean = mysqli_prepare($conn, "DELETE FROM admin_2fa_otps WHERE user_id = ?");
            if ($stmt_clean) {
                mysqli_stmt_bind_param($stmt_clean, "i", $user_id);
                mysqli_stmt_execute($stmt_clean);
                mysqli_stmt_close($stmt_clean);
            }

            // จัดการระบบจำอุปกรณ์ 7 วัน (Trust Device for 7 days)
            $trust = isset($_POST['trust_device']);
            if ($trust) {
                $raw_token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $raw_token);
                $expires_at = date('Y-m-d H:i:s', time() + (7 * 86400)); // 7 วันจากนี้

                // บันทึกโทเค็นเครื่องแอดมินลง DB
                $stmt_trust = mysqli_prepare($conn, "INSERT INTO admin_trusted_devices (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
                if ($stmt_trust) {
                    mysqli_stmt_bind_param($stmt_trust, "iss", $user_id, $token_hash, $expires_at);
                    mysqli_stmt_execute($stmt_trust);
                    mysqli_stmt_close($stmt_trust);
                }

                // ฝังคุกกี้ไว้ในบราวเซอร์ 7 วัน
                $cookie_val = $user_id . ':' . $raw_token;
                
                // กำหนดตัวเลือกความปลอดภัยสำหรับ Cookie (Secure, HttpOnly, SameSite)
                $cookie_options = [
                    'expires' => time() + (7 * 86400),
                    'path' => '/',
                    'domain' => '', 
                    'secure' => (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1 || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')),
                    'httponly' => true,
                    'samesite' => 'Lax'
                ];
                setcookie('admin_trusted_device', $cookie_val, $cookie_options);
            }

            // บันทึกประวัติประมวลผลสำเร็จลงระบบความปลอดภัยหลังบ้าน
            log_admin_action($conn, 'แอดมินผ่าน 2FA', "เข้าสู่ระบบหลังบ้านสำเร็จด้วยรหัส OTP (จำอุปกรณ์ 7 วัน: " . ($trust ? 'เปิด' : 'ปิด') . ")", $user_id, $fullname);

            // แจ้งเตือนสวีตอเลิร์ตและพาเข้าบอร์ดหลังบ้าน
            $_SESSION['swal'] = ['title' => 'เข้าสู่ระบบสำเร็จ', 'text' => 'ยินดีต้อนรับแอดมินกลับเข้าสู่ระบบจัดการหลังบ้าน', 'icon' => 'success'];
            header("Location: admin_dashboard.php");
            exit();
        } else {
            // บันทึกความล้มเหลวในการตรวจสอบสิทธิ์ลงบันทึกแอดมิน
            log_admin_action($conn, 'แอดมินยืนยัน 2FA ล้มเหลว', "ใส่รหัส OTP ผิดพลาดหรือรหัสหมดอายุ (ค่าที่กรอก: $entered_otp)", $user_id, $fullname);
            $error_msg = "รหัส OTP ไม่ถูกต้อง หรืออาจหมดอายุการใช้งานแล้ว กรุณาลองใหม่อีกครั้ง";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยืนยันสิทธิ์ความปลอดภัย 2FA | Por Mae Bet Taled</title>
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --blue-main: #85D1FF;
            --blue-hover: #6BBEFF;
            --bg-light: #f8fafc;
        }
        body {
            font-family: 'Kanit', sans-serif;
            background: linear-gradient(135deg, #eef2f6 0%, #dbeafe 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .otp-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(15px);
            border-radius: 24px;
            box-shadow: 0 15px 35px rgba(133, 209, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.7);
            padding: 2.5rem;
            width: 100%;
            max-width: 480px;
            transition: transform 0.3s;
        }
        .icon-box {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #85D1FF 0%, #6BBEFF 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            color: white;
            font-size: 2.5rem;
            box-shadow: 0 10px 20px rgba(133, 209, 255, 0.3);
        }
        .form-control-otp {
            font-size: 2rem;
            letter-spacing: 0.5rem;
            text-align: center;
            font-weight: 700;
            border-radius: 15px;
            border: 2px solid #e2e8f0;
            padding: 10px;
            background: #fff;
            transition: all 0.3s;
            color: #1e3a8a;
        }
        .form-control-otp:focus {
            border-color: var(--blue-main);
            box-shadow: 0 0 15px rgba(133, 209, 255, 0.3);
            outline: none;
        }
        .btn-verify {
            background: var(--blue-main);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 14px;
            width: 100%;
            font-weight: 600;
            font-size: 1.1rem;
            box-shadow: 0 10px 20px rgba(133, 209, 255, 0.3);
            transition: all 0.3s;
        }
        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px rgba(107, 190, 255, 0.5);
            background: var(--blue-hover);
            color: white;
        }
        .btn-resend {
            color: #64748b;
            text-decoration: none;
            background: none;
            border: none;
            font-size: 0.9rem;
            transition: color 0.2s;
        }
        .btn-resend:hover {
            color: var(--blue-hover);
        }
        .alert-custom {
            border-radius: 12px;
            font-size: 0.9rem;
            padding: 12px;
        }
    </style>
</head>
<body>

<div class="otp-card text-center animate__animated animate__fadeIn">
    <div class="icon-box">
        <i class="bi bi-shield-lock"></i>
    </div>
    
    <h1 id="main-title" class="h3 fw-bold mb-1 text-dark">ระบบยืนยันตนแอดมิน</h1>
    <p class="text-muted small mb-4">เข้าสู่ระบบหลังบ้านอย่างปลอดภัยด้วย OTP</p>
    
    <div class="text-start mb-4 bg-light p-3 rounded-4 border border-light-subtle">
        <div class="small text-muted mb-1"><i class="bi bi-envelope-open me-1"></i> ส่งรหัสไปที่อีเมลแอดมิน:</div>
        <div class="fw-semibold text-dark"><?= htmlspecialchars(getMaskedEmail($email), ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger alert-custom text-start mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success alert-custom text-start mb-3" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success_msg, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="text-start">
        <?= get_csrf_input() ?>
        <div class="mb-4">
            <label for="otp_code" class="form-label fw-semibold text-muted small">ระบุรหัส OTP 6 หลัก</label>
            <input type="text" name="otp_code" id="otp_code" class="form-control form-control-otp" placeholder="••••••" maxlength="6" autocomplete="off" required autofocus>
        </div>

        <div class="form-check mb-4 bg-light p-3 rounded-4 border border-light-subtle d-flex align-items-center">
            <input class="form-check-input ms-0 me-2" type="checkbox" name="trust_device" id="trust_device" checked>
            <label class="form-check-label text-muted small" for="trust_device" style="user-select: none;">
                จดจำและเชื่อใจอุปกรณ์นี้เป็นเวลา 7 วัน (ไม่ต้องกรอก OTP ซ้ำ)
            </label>
        </div>

        <button type="submit" name="verify_otp" id="verify_otp_btn" class="btn btn-verify mb-3">
            ยืนยันรหัสยืนยันตน <i class="bi bi-check-lg ms-1"></i>
        </button>
    </form>

    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top border-light-subtle">
        <form method="POST" class="w-100 d-flex justify-content-between">
            <?= get_csrf_input() ?>
            <button type="submit" name="resend_otp" id="resend_otp_btn" class="btn-resend">
                <i class="bi bi-arrow-clockwise me-1"></i> ส่งรหัส OTP ใหม่อีกครั้ง
            </button>
            <a href="logout.php" class="text-decoration-none text-danger small"><i class="bi bi-box-arrow-left me-1"></i> ออกจากระบบ</a>
        </form>
    </div>
</div>

<script>
    // สคริปต์ช่วยอำนวยความสะดวกการพิมพ์รหัส OTP และสแกนเฉพาะตัวเลขเท่านั้น
    const otpInput = document.getElementById('otp_code');
    otpInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, ''); // สกัดกั้นเฉพาะตัวเลขเท่านั้น
    });
</script>
</body>
</html>
