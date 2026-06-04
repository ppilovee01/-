<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
}

// --- การบันทึกข้อมูลตั้งค่าร้านค้า ---
if (isset($_POST['save_settings']) || isset($_POST['test_smtp'])) {
    $shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id = 1"));
    
    $name = mysqli_real_escape_string($conn, $_POST['shop_name']);
    $addr = mysqli_real_escape_string($conn, $_POST['address']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['shop_email']);
    $remark = mysqli_real_escape_string($conn, $_POST['print_remark']);
    $smtp_host = mysqli_real_escape_string($conn, $_POST['smtp_host']);
    $smtp_port = intval($_POST['smtp_port']);
    $smtp_secure = mysqli_real_escape_string($conn, $_POST['smtp_secure']);
    $smtp_user = mysqli_real_escape_string($conn, $_POST['smtp_user']);
    
    $smtp_pass_raw = str_replace(' ', '', $_POST['smtp_pass'] ?? '');
    if ($smtp_pass_raw === getMaskedValue('SMTP_PASS', $shop['smtp_pass'] ?? '')) {
        $smtp_pass_raw = getSecretValue('SMTP_PASS', $shop['smtp_pass'] ?? '');
    }
    $smtp_pass = mysqli_real_escape_string($conn, $smtp_pass_raw);
    
    $welcome_promo_enabled = isset($_POST['welcome_promo_enabled']) ? intval($_POST['welcome_promo_enabled']) : 1;
    $welcome_promo_coupon = mysqli_real_escape_string($conn, $_POST['welcome_promo_coupon'] ?? '');
    $shipping_fee_fixed = floatval($_POST['shipping_fee_fixed'] ?? 40.00);
    $shipping_free_threshold = floatval($_POST['shipping_free_threshold'] ?? 350.00);
    $points_earn_rate = intval($_POST['points_earn_rate'] ?? 100);
    $points_spend_rate = intval($_POST['points_spend_rate'] ?? 1);
    
    $line_notify_token_raw = trim($_POST['line_notify_token'] ?? '');
    if ($line_notify_token_raw === getMaskedValue('LINE_NOTIFY_TOKEN', $shop['line_notify_token'] ?? '')) {
        $line_notify_token_raw = getSecretValue('LINE_NOTIFY_TOKEN', $shop['line_notify_token'] ?? '');
    }
    $line_notify_token = mysqli_real_escape_string($conn, $line_notify_token_raw);
    
    $slip_ai_provider = mysqli_real_escape_string($conn, $_POST['slip_ai_provider'] ?? 'none');
    
    $openai_api_key_raw = trim($_POST['openai_api_key'] ?? '');
    if ($openai_api_key_raw === getMaskedValue('OPENAI_API_KEY', $shop['openai_api_key'] ?? '')) {
        $openai_api_key_raw = getSecretValue('OPENAI_API_KEY', $shop['openai_api_key'] ?? '');
    }
    $openai_api_key = mysqli_real_escape_string($conn, $openai_api_key_raw);
    
    $gemini_api_key_raw = trim($_POST['gemini_api_key'] ?? '');
    if ($gemini_api_key_raw === getMaskedValue('GEMINI_API_KEY', $shop['gemini_api_key'] ?? '')) {
        $gemini_api_key_raw = getSecretValue('GEMINI_API_KEY', $shop['gemini_api_key'] ?? '');
    }
    $gemini_api_key = mysqli_real_escape_string($conn, $gemini_api_key_raw);
    
    $claude_api_key_raw = trim($_POST['claude_api_key'] ?? '');
    if ($claude_api_key_raw === getMaskedValue('CLAUDE_API_KEY', $shop['claude_api_key'] ?? '')) {
        $claude_api_key_raw = getSecretValue('CLAUDE_API_KEY', $shop['claude_api_key'] ?? '');
    }
    $claude_api_key = mysqli_real_escape_string($conn, $claude_api_key_raw);
    
    // 1. อัปเดตข้อมูลข้อความ
    $sql = "UPDATE shop_settings SET 
            shop_name='$name', 
            address='$addr', 
            phone='$phone', 
            shop_email='$email', 
            print_remark='$remark', 
            smtp_host='$smtp_host', 
            smtp_port='$smtp_port', 
            smtp_user='$smtp_user', 
            smtp_pass='$smtp_pass', 
            smtp_secure='$smtp_secure', 
            welcome_promo_enabled='$welcome_promo_enabled', 
            welcome_promo_coupon='$welcome_promo_coupon', 
            shipping_fee_fixed='$shipping_fee_fixed', 
            shipping_free_threshold='$shipping_free_threshold',
            points_earn_rate='$points_earn_rate',
            points_spend_rate='$points_spend_rate',
            line_notify_token='$line_notify_token',
            slip_ai_provider='$slip_ai_provider',
            openai_api_key='$openai_api_key',
            gemini_api_key='$gemini_api_key',
            claude_api_key='$claude_api_key'
            WHERE id=1";
    mysqli_query($conn, $sql);
    
    // บันทึกข้อมูลความลับลงไฟล์ .env คู่ขนาน (ถ้าเขียนไฟล์ได้)
    $env_path = __DIR__ . '/.env';
    updateEnv('SLIP_AI_PROVIDER', $slip_ai_provider, $env_path);
    updateEnv('OPENAI_API_KEY', $openai_api_key_raw, $env_path);
    updateEnv('GEMINI_API_KEY', $gemini_api_key_raw, $env_path);
    updateEnv('CLAUDE_API_KEY', $claude_api_key_raw, $env_path);
    updateEnv('LINE_NOTIFY_TOKEN', $line_notify_token_raw, $env_path);
    updateEnv('SMTP_HOST', trim($_POST['smtp_host'] ?? ''), $env_path);
    updateEnv('SMTP_PORT', trim($_POST['smtp_port'] ?? '587'), $env_path);
    updateEnv('SMTP_USER', trim($_POST['smtp_user'] ?? ''), $env_path);
    updateEnv('SMTP_PASS', $smtp_pass_raw, $env_path);
    updateEnv('SMTP_SECURE', trim($_POST['smtp_secure'] ?? 'tls'), $env_path);
    
    log_admin_action($conn, 'แก้ไขตั้งค่าร้านค้า', "แก้ไขข้อมูลร้านค้า, ระบบ SMTP, อัตราแต้มสะสม ($points_earn_rate บาท/แต้ม, $points_spend_rate บาท/แต้ม), LINE Notify และตั้งค่าสแกนสลิปด้วย AI ($slip_ai_provider)");

    // 2. อัปเดต Icon (ถ้ามีการอัปโหลดใหม่)
    if (isset($_FILES['shop_icon']) && $_FILES['shop_icon']['error'] == 0) {
        $ext = pathinfo($_FILES['shop_icon']['name'], PATHINFO_EXTENSION);
        $allowed = ['ico', 'png', 'jpg', 'jpeg', 'webp'];
        if (in_array(strtolower($ext), $allowed)) {
            $new_icon = "favicon_" . time() . "." . strtolower($ext);
            
            if (!is_dir("uploads")) mkdir("uploads");
            
            if (move_uploaded_file($_FILES['shop_icon']['tmp_name'], "uploads/" . $new_icon)) {
                mysqli_query($conn, "UPDATE shop_settings SET shop_icon='$new_icon' WHERE id=1");
            }
        }
    }

    if (isset($_POST['test_smtp'])) {
        include 'mail_sender.php';
        $res = send_test_email($conn);
        if ($res === true) {
            log_admin_action($conn, 'ทดสอบ SMTP', "กดทดสอบการเชื่อมต่อระบบ SMTP (ผลลัพธ์: สำเร็จ)");
            $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'ทดสอบการเชื่อมต่อ SMTP สำเร็จแล้ว! มีอีเมลทดสอบส่งไปยังกล่องจดหมายของคุณเรียบร้อย', 'icon'=>'success'];
        } else {
            log_admin_action($conn, 'ทดสอบ SMTP', "กดทดสอบการเชื่อมต่อระบบ SMTP (ผลลัพธ์: ล้มเหลว - $res)");
            $_SESSION['swal'] = ['title'=>'เกิดข้อผิดพลาด', 'text'=>'เชื่อมต่อล้มเหลว: ' . $res, 'icon'=>'error'];
        }
    } else {
        $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'บันทึกข้อมูลร้านค้าเรียบร้อยแล้ว', 'icon'=>'success'];
    }
    header("Location: admin_settings.php"); exit();
}

$shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id=1"));
$icon_show = !empty($shop['shop_icon']) ? "uploads/".$shop['shop_icon'] : "assets/default_icon.png";

// ดึงคูปองที่มีสถานะเปิดใช้งานและยังไม่หมดอายุ
$today = date('Y-m-d');
$coupons_query = mysqli_query($conn, "SELECT code, discount_type, discount_value FROM coupons WHERE status='active' AND expiry_date >= '$today'");
$active_coupons = [];
if ($coupons_query) {
    while ($c = mysqli_fetch_assoc($coupons_query)) {
        $active_coupons[] = $c;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าร้านค้า | Por Mae Bet Taled Admin</title>
    <link rel="icon" type="image/x-icon" href="<?= $icon_show ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style> 
        body { font-family: 'Kanit'; background: #f8f9fa; } 
        .btn-pastel-blue { background-color: #AEE2FF; color: #444; border: none; transition: 0.3s; }
        .btn-pastel-blue:hover { background-color: #7FB5FF; color: white; }
    </style>
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
            <h3 class="fw-bold mb-4">⚙️ ตั้งค่าร้านค้า (Shop Settings)</h3>

            <div class="card border-0 shadow-sm rounded-4 p-4">
                <form method="POST" enctype="multipart/form-data">
                    <?= get_csrf_input() ?>
                    
                    <div class="d-flex flex-column flex-sm-row align-items-center mb-4 p-3 border rounded bg-light">
                        <div class="me-sm-4 text-center mb-3 mb-sm-0">
                            <label class="form-label fw-bold d-block mb-2">ไอคอนเว็บไซต์ (Icon)</label>
                            <img id="iconPreview" src="<?= $icon_show ?>" class="rounded shadow-sm bg-white" style="width: 64px; height: 64px; object-fit: contain; border:1px solid #ddd;">
                        </div>
                        <div class="flex-grow-1 w-100">
                            <label for="iconInput" class="form-label small text-muted">เปลี่ยนรูปไอคอน (รองรับไฟล์ .png, .ico, .jpg)</label>
                            <input type="file" name="shop_icon" id="iconInput" class="form-control" accept="image/*" onchange="previewIcon(event)">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">ชื่อร้านค้า</label>
                            <input type="text" name="shop_name" class="form-control" value="<?= htmlspecialchars($shop['shop_name']) ?>" placeholder="ชื่อร้านค้าของคุณ" required>
                        </div>
                        <div class="col-12 col-md-3 mb-3">
                            <label class="form-label fw-bold">เบอร์โทรศัพท์ติดต่อ</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($shop['phone']) ?>" placeholder="เช่น 081-234-5678" required>
                        </div>
                        <div class="col-12 col-md-3 mb-3">
                            <label class="form-label fw-bold">อีเมล หรือ ไลน์ไอดี</label>
                            <input type="text" name="shop_email" class="form-control" value="<?= htmlspecialchars($shop['shop_email']) ?>" placeholder="เช่น contact@example.com">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">ที่อยู่ร้านค้า (สำหรับจัดส่ง)</label>
                        <textarea name="address" class="form-control" rows="3" placeholder="ระบุที่อยู่ที่ต้องการให้ปรากฏในใบเสร็จหรือใบปะหน้า" required><?= htmlspecialchars($shop['address']) ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-danger">หมายเหตุเพิ่มเติม (จะปรากฏท้ายใบปะหน้าพัสดุ)</label>
                        <textarea name="print_remark" class="form-control" rows="2" placeholder="เช่น กรุณาถ่ายวิดีโอขณะเปิดกล่องพัสดุ"><?= htmlspecialchars($shop['print_remark']) ?></textarea>
                    </div>

                    <hr class="my-4">
                    <h5 class="fw-bold text-primary mb-3"><i class="bi bi-gift-fill me-1"></i> ตั้งค่าป๊อปอัปข้อเสนอพิเศษต้อนรับ (Welcome Promo Pop-up Settings)</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted">สถานะป๊อปอัปข้อเสนอพิเศษต้อนรับหน้าแรก</label>
                            <select name="welcome_promo_enabled" class="form-select">
                                <option value="1" <?= ($shop['welcome_promo_enabled'] ?? 1) == 1 ? 'selected' : '' ?>>เปิดใช้งาน (Enabled)</option>
                                <option value="0" <?= ($shop['welcome_promo_enabled'] ?? 1) == 0 ? 'selected' : '' ?>>ปิดใช้งาน (Disabled)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted">คูปองที่ต้องการแนะนำป๊อปอัป</label>
                            <select name="welcome_promo_coupon" class="form-select">
                                <option value="" <?= empty($shop['welcome_promo_coupon']) ? 'selected' : '' ?>>เลือกคูปองที่คุ้มที่สุดโดยอัตโนมัติ</option>
                                <?php foreach ($active_coupons as $c): ?>
                                    <option value="<?= htmlspecialchars($c['code']) ?>" <?= ($shop['welcome_promo_coupon'] ?? '') == $c['code'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['code']) ?> - ลด <?= $c['discount_type'] == 'percent' ? intval($c['discount_value']) . '%' : '฿' . number_format($c['discount_value']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="fw-bold text-primary mb-3"><i class="bi bi-truck me-1"></i> ตั้งค่าเป้าหมายจัดส่งฟรี (Free Shipping Target Settings)</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted">ค่าจัดส่งปกติ (บาท)</label>
                            <input type="number" step="0.01" name="shipping_fee_fixed" class="form-control" value="<?= htmlspecialchars(number_format($shop['shipping_fee_fixed'] ?? 40.00, 2, '.', '')) ?>" placeholder="เช่น 40.00" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted">ยอดซื้อขั้นต่ำเพื่อจัดส่งฟรี (บาท)</label>
                            <input type="number" step="0.01" name="shipping_free_threshold" class="form-control" value="<?= htmlspecialchars(number_format($shop['shipping_free_threshold'] ?? 350.00, 2, '.', '')) ?>" placeholder="เช่น 350.00" required>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="fw-bold text-primary mb-3"><i class="bi bi-coin me-1"></i> ตั้งค่าแต้มสะสมสมาชิก (Membership Reward Points Settings)</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted">อัตราส่วนการได้รับแต้ม (บาทต่อ 1 แต้ม)</label>
                            <input type="number" name="points_earn_rate" class="form-control" value="<?= intval($shop['points_earn_rate'] ?? 100) ?>" placeholder="เช่น 100" required min="1">
                            <div class="form-text">ซื้อสินค้าครบทุกๆ กี่บาท ถึงจะได้รับคะแนนสะสม 1 แต้ม</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted">อัตราส่วนการใช้แต้ม (1 แต้มต่อส่วนลดกี่บาท)</label>
                            <input type="number" name="points_spend_rate" class="form-control" value="<?= intval($shop['points_spend_rate'] ?? 1) ?>" placeholder="เช่น 1" required min="1">
                            <div class="form-text">เมื่อลูกค้าแลกแต้ม 1 แต้ม จะได้รับส่วนลดแทนเงินสดกี่บาท</div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="fw-bold text-primary mb-3"><i class="bi bi-envelope-at-fill me-1"></i> ตั้งค่าอีเมลส่งแจ้งเตือน (SMTP Settings)</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-bold text-muted">
                                SMTP Server Host
                                <?php if (getenv('SMTP_HOST') !== false): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars(getSecretValue('SMTP_HOST', $shop['smtp_host'] ?? '')) ?>" placeholder="เช่น smtp.gmail.com">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label small fw-bold text-muted">
                                SMTP Port
                                <?php if (getenv('SMTP_PORT') !== false): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars(getSecretValue('SMTP_PORT', $shop['smtp_port'] ?? '587')) ?>" placeholder="เช่น 587">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label small fw-bold text-muted">
                                ประเภทความปลอดภัย
                                <?php if (getenv('SMTP_SECURE') !== false): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <?php $active_secure = getSecretValue('SMTP_SECURE', $shop['smtp_secure'] ?? 'tls'); ?>
                            <select name="smtp_secure" class="form-select">
                                <option value="tls" <?= $active_secure == 'tls' ? 'selected' : '' ?>>TLS (แนะนำ)</option>
                                <option value="ssl" <?= $active_secure == 'ssl' ? 'selected' : '' ?>>SSL</option>
                                <option value="none" <?= $active_secure == 'none' ? 'selected' : '' ?>>ไม่มี (None)</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-bold text-muted">
                                SMTP Username (Email)
                                <?php if (getenv('SMTP_USER') !== false): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <input type="email" name="smtp_user" class="form-control" value="<?= htmlspecialchars(getSecretValue('SMTP_USER', $shop['smtp_user'] ?? '')) ?>" placeholder="เช่น shop@gmail.com">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-bold text-muted">
                                SMTP Password (หรือ App Password)
                                <?php if (getenv('SMTP_PASS') !== false): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group">
                                <input type="password" name="smtp_pass" id="smtpPassInput" class="form-control" value="<?= htmlspecialchars(getMaskedValue('SMTP_PASS', $shop['smtp_pass'] ?? '')) ?>" placeholder="รหัสผ่านอีเมลจัดส่ง">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('smtpPassInput', this)" style="border-color: #dee2e6;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="fw-bold text-primary mb-3"><i class="bi bi-bell-fill me-1"></i> ตั้งค่าการแจ้งเตือน Line Notify (Line Notify Settings)</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-12 mb-3">
                            <label class="form-label small fw-bold text-muted">
                                LINE Notify Token
                                <?php if (getenv('LINE_NOTIFY_TOKEN') !== false): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" name="line_notify_token" class="form-control" value="<?= htmlspecialchars(getMaskedValue('LINE_NOTIFY_TOKEN', $shop['line_notify_token'] ?? '')) ?>" placeholder="ใส่ Line Notify Token ของร้านค้า เพื่อรับแจ้งเตือนเมื่อมีออเดอร์ใหม่">
                            <div class="form-text">สามารถขอ Token ได้ที่ <a href="https://notify-bot.line.me/" target="_blank" class="text-decoration-none" style="color: #0ea5e9;">LINE Notify Portal</a> และเชิญบอทเข้ากลุ่มแชทที่ต้องการรับแจ้งเตือน</div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="fw-bold text-primary mb-3"><i class="bi bi-robot me-1"></i> ตั้งค่าระบบตรวจสอบสลิปด้วย AI (AI Slip Verification Settings)</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-4 mb-3">
                            <label class="form-label small fw-bold text-muted">
                                เลือกผู้ให้บริการหลัก (AI Provider)
                                <?php if (getenv('SLIP_AI_PROVIDER') !== false): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <?php $active_provider = getSecretValue('SLIP_AI_PROVIDER', $shop['slip_ai_provider'] ?? 'none'); ?>
                            <select name="slip_ai_provider" class="form-select" onchange="toggleAIKeys(this.value)">
                                <option value="none" <?= $active_provider === 'none' ? 'selected' : '' ?>>ปิดใช้งาน (Disabled)</option>
                                <option value="openai" <?= $active_provider === 'openai' ? 'selected' : '' ?>>OpenAI (GPT-4o-mini)</option>
                                <option value="gemini" <?= $active_provider === 'gemini' ? 'selected' : '' ?>>Google Gemini (Gemini 2.5 Flash)</option>
                                <option value="claude" <?= $active_provider === 'claude' ? 'selected' : '' ?>>Anthropic Claude (Claude 3.5 Haiku)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-4" id="ai-keys-container">
                        <!-- OpenAI Key Input -->
                        <div class="col-12 col-md-12 mb-3 ai-key-input-box" id="openai-key-box" style="<?= getSecretValue('SLIP_AI_PROVIDER', $shop['slip_ai_provider'] ?? 'none') === 'openai' ? '' : 'display:none;' ?>">
                            <label class="form-label small fw-bold text-muted">
                                OpenAI API Key
                                <?php if (getenv('OPENAI_API_KEY') !== false): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group">
                                <input type="password" name="openai_api_key" id="openaiKeyInput" class="form-control" value="<?= htmlspecialchars(getMaskedValue('OPENAI_API_KEY', $shop['openai_api_key'] ?? '')) ?>" placeholder="sk-...">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('openaiKeyInput', this)" style="border-color: #dee2e6;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">ไปที่ <a href="https://platform.openai.com/" target="_blank" class="text-decoration-none" style="color: #0ea5e9;">OpenAI Platform</a> เพื่อสร้าง API Key สำหรับสแกนสลิปด้วยโมเดล GPT-4o-mini</div>
                        </div>
                        
                        <!-- Gemini Key Input -->
                        <div class="col-12 col-md-12 mb-3 ai-key-input-box" id="gemini-key-box" style="<?= getSecretValue('SLIP_AI_PROVIDER', $shop['slip_ai_provider'] ?? 'none') === 'gemini' ? '' : 'display:none;' ?>">
                            <label class="form-label small fw-bold text-muted">
                                Google Gemini API Key
                                <?php if (getenv('GEMINI_API_KEY') !== false): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group">
                                <input type="password" name="gemini_api_key" id="geminiKeyInput" class="form-control" value="<?= htmlspecialchars(getMaskedValue('GEMINI_API_KEY', $shop['gemini_api_key'] ?? '')) ?>" placeholder="AIzaSy...">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('geminiKeyInput', this)" style="border-color: #dee2e6;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">ไปที่ <a href="https://aistudio.google.com/" target="_blank" class="text-decoration-none" style="color: #0ea5e9;">Google AI Studio</a> เพื่อสร้าง API Key สำหรับโมเดล Gemini 2.5 Flash</div>
                        </div>
                        
                        <!-- Claude Key Input -->
                        <div class="col-12 col-md-12 mb-3 ai-key-input-box" id="claude-key-box" style="<?= getSecretValue('SLIP_AI_PROVIDER', $shop['slip_ai_provider'] ?? 'none') === 'claude' ? '' : 'display:none;' ?>">
                            <label class="form-label small fw-bold text-muted">
                                Anthropic Claude API Key
                                <?php if (getenv('CLAUDE_API_KEY') !== false): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group">
                                <input type="password" name="claude_api_key" id="claudeKeyInput" class="form-control" value="<?= htmlspecialchars(getMaskedValue('CLAUDE_API_KEY', $shop['claude_api_key'] ?? '')) ?>" placeholder="sk-ant-...">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('claudeKeyInput', this)" style="border-color: #dee2e6;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">ไปที่ <a href="https://console.anthropic.com/" target="_blank" class="text-decoration-none" style="color: #0ea5e9;">Anthropic Console</a> เพื่อสร้าง API Key สำหรับสแกนด้วยโมเดล Claude 3.5 Haiku</div>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-8">
                            <button type="submit" name="save_settings" class="btn btn-pastel-blue rounded-pill px-4 w-100 py-2 fw-bold shadow-sm">
                                <i class="bi bi-save me-1"></i> บันทึกข้อมูลทั้งหมด
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" name="test_smtp" class="btn btn-outline-primary rounded-pill px-4 w-100 py-2 fw-bold shadow-sm bg-white" style="border-color: #AEE2FF; color: #444;">
                                <i class="bi bi-send-check me-1"></i> ทดสอบการส่งอีเมล
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if(isset($_SESSION['swal'])): ?>
<script>
    Swal.fire({
        icon: '<?= $_SESSION['swal']['icon'] ?>',
        title: '<?= $_SESSION['swal']['title'] ?>',
        text: '<?= $_SESSION['swal']['text'] ?>',
        confirmButtonColor: '#AEE2FF'
    });
</script>
<?php unset($_SESSION['swal']); endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function previewIcon(event) {
        const output = document.getElementById('iconPreview');
        output.src = URL.createObjectURL(event.target.files[0]);
    }

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

    function toggleAIKeys(val) {
        document.querySelectorAll('.ai-key-input-box').forEach(box => {
            box.style.display = 'none';
        });
        if (val === 'openai') {
            document.getElementById('openai-key-box').style.display = 'block';
        } else if (val === 'gemini') {
            document.getElementById('gemini-key-box').style.display = 'block';
        } else if (val === 'claude') {
            document.getElementById('claude-key-box').style.display = 'block';
        }
    }
</script>
</body>
</html>
