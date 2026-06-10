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
    $facebook_url = mysqli_real_escape_string($conn, $_POST['facebook_url'] ?? '#');
    $line_url = mysqli_real_escape_string($conn, $_POST['line_url'] ?? '#');
    $instagram_url = mysqli_real_escape_string($conn, $_POST['instagram_url'] ?? '#');
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

    $line_channel_access_token_raw = trim($_POST['line_channel_access_token'] ?? '');
    if ($line_channel_access_token_raw === getMaskedValue('LINE_CHANNEL_ACCESS_TOKEN', $shop['line_channel_access_token'] ?? '')) {
        $line_channel_access_token_raw = getSecretValue('LINE_CHANNEL_ACCESS_TOKEN', $shop['line_channel_access_token'] ?? '');
    }
    $line_channel_access_token = mysqli_real_escape_string($conn, $line_channel_access_token_raw);

    $line_user_id_raw = trim($_POST['line_user_id'] ?? '');
    if ($line_user_id_raw === getMaskedValue('LINE_USER_ID', $shop['line_user_id'] ?? '')) {
        $line_user_id_raw = getSecretValue('LINE_USER_ID', $shop['line_user_id'] ?? '');
    }
    $line_user_id = mysqli_real_escape_string($conn, $line_user_id_raw);

    $discord_webhook_url_raw = trim($_POST['discord_webhook_url'] ?? '');
    if ($discord_webhook_url_raw === getMaskedValue('DISCORD_WEBHOOK_URL', $shop['discord_webhook_url'] ?? '')) {
        $discord_webhook_url_raw = getSecretValue('DISCORD_WEBHOOK_URL', $shop['discord_webhook_url'] ?? '');
    }
    $discord_webhook_url = mysqli_real_escape_string($conn, $discord_webhook_url_raw);

    $telegram_bot_token_raw = trim($_POST['telegram_bot_token'] ?? '');
    if ($telegram_bot_token_raw === getMaskedValue('TELEGRAM_BOT_TOKEN', $shop['telegram_bot_token'] ?? '')) {
        $telegram_bot_token_raw = getSecretValue('TELEGRAM_BOT_TOKEN', $shop['telegram_bot_token'] ?? '');
    }
    $telegram_bot_token = mysqli_real_escape_string($conn, $telegram_bot_token_raw);

    $telegram_chat_id_raw = trim($_POST['telegram_chat_id'] ?? '');
    if ($telegram_chat_id_raw === getMaskedValue('TELEGRAM_CHAT_ID', $shop['telegram_chat_id'] ?? '')) {
        $telegram_chat_id_raw = getSecretValue('TELEGRAM_CHAT_ID', $shop['telegram_chat_id'] ?? '');
    }
    $telegram_chat_id = mysqli_real_escape_string($conn, $telegram_chat_id_raw);

    $slack_webhook_url_raw = trim($_POST['slack_webhook_url'] ?? '');
    if ($slack_webhook_url_raw === getMaskedValue('SLACK_WEBHOOK_URL', $shop['slack_webhook_url'] ?? '')) {
        $slack_webhook_url_raw = getSecretValue('SLACK_WEBHOOK_URL', $shop['slack_webhook_url'] ?? '');
    }
    $slack_webhook_url = mysqli_real_escape_string($conn, $slack_webhook_url_raw);

    $custom_webhook_url_raw = trim($_POST['custom_webhook_url'] ?? '');
    if ($custom_webhook_url_raw === getMaskedValue('CUSTOM_WEBHOOK_URL', $shop['custom_webhook_url'] ?? '')) {
        $custom_webhook_url_raw = getSecretValue('CUSTOM_WEBHOOK_URL', $shop['custom_webhook_url'] ?? '');
    }
    $custom_webhook_url = mysqli_real_escape_string($conn, $custom_webhook_url_raw);
    
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
    
    // 1. อัปเดตข้อมูลข้อความ (ไม่เก็บความลับ/API key ในฐานข้อมูล)
    $sql = "UPDATE shop_settings SET 
            shop_name='$name', 
            address='$addr', 
            phone='$phone', 
            shop_email='$email', 
            print_remark='$remark', 
            smtp_host='$smtp_host', 
            smtp_port='$smtp_port', 
            smtp_user='$smtp_user', 
            smtp_secure='$smtp_secure', 
            welcome_promo_enabled='$welcome_promo_enabled', 
            welcome_promo_coupon='$welcome_promo_coupon', 
            shipping_fee_fixed='$shipping_fee_fixed', 
            shipping_free_threshold='$shipping_free_threshold',
            points_earn_rate='$points_earn_rate',
            points_spend_rate='$points_spend_rate',
            slip_ai_provider='$slip_ai_provider',
            facebook_url='$facebook_url',
            line_url='$line_url',
            instagram_url='$instagram_url',
            notification_sound='" . mysqli_real_escape_string($conn, $_POST['notification_sound'] ?? 'chime') . "'
            WHERE id=1";
    mysqli_query($conn, $sql);
    
    // บันทึกข้อมูลความลับลงไฟล์ .env คู่ขนาน (ถ้าเขียนไฟล์ได้)
    $env_path = __DIR__ . '/.env';
    updateEnv('SLIP_AI_PROVIDER', $slip_ai_provider, $env_path);
    updateEnv('OPENAI_API_KEY', $openai_api_key_raw, $env_path);
    updateEnv('GEMINI_API_KEY', $gemini_api_key_raw, $env_path);
    updateEnv('CLAUDE_API_KEY', $claude_api_key_raw, $env_path);
    updateEnv('LINE_NOTIFY_TOKEN', $line_notify_token_raw, $env_path);
    updateEnv('LINE_CHANNEL_ACCESS_TOKEN', $line_channel_access_token_raw, $env_path);
    updateEnv('LINE_USER_ID', $line_user_id_raw, $env_path);
    updateEnv('DISCORD_WEBHOOK_URL', $discord_webhook_url_raw, $env_path);
    updateEnv('TELEGRAM_BOT_TOKEN', $telegram_bot_token_raw, $env_path);
    updateEnv('TELEGRAM_CHAT_ID', $telegram_chat_id_raw, $env_path);
    updateEnv('SLACK_WEBHOOK_URL', $slack_webhook_url_raw, $env_path);
    updateEnv('CUSTOM_WEBHOOK_URL', $custom_webhook_url_raw, $env_path);
    updateEnv('SMTP_HOST', trim($_POST['smtp_host'] ?? ''), $env_path);
    updateEnv('SMTP_PORT', trim($_POST['smtp_port'] ?? '587'), $env_path);
    updateEnv('SMTP_USER', trim($_POST['smtp_user'] ?? ''), $env_path);
    updateEnv('SMTP_PASS', $smtp_pass_raw, $env_path);
    updateEnv('SMTP_SECURE', trim($_POST['smtp_secure'] ?? 'tls'), $env_path);
    updateEnv('NOTIFICATION_SOUND', trim($_POST['notification_sound'] ?? 'chime'), $env_path);
    updateEnv('CUSTOM_SOUND_URL', trim($_POST['custom_sound_url'] ?? ''), $env_path);
    
    $changes = [];
    if (($shop['shop_name'] ?? '') !== $name) {
        $changes[] = ['field' => 'ชื่อร้านค้า', 'old' => $shop['shop_name'] ?? '', 'new' => $name];
    }
    if (($shop['address'] ?? '') !== $addr) {
        $changes[] = ['field' => 'ที่อยู่ร้านค้า', 'old' => $shop['address'] ?? '', 'new' => $addr];
    }
    if (($shop['phone'] ?? '') !== $phone) {
        $changes[] = ['field' => 'เบอร์โทรศัพท์', 'old' => $shop['phone'] ?? '', 'new' => $phone];
    }
    if (($shop['shop_email'] ?? '') !== $email) {
        $changes[] = ['field' => 'อีเมลร้านค้า', 'old' => $shop['shop_email'] ?? '', 'new' => $email];
    }
    if (($shop['facebook_url'] ?? '') !== $facebook_url) {
        $changes[] = ['field' => 'Facebook Link', 'old' => $shop['facebook_url'] ?? '', 'new' => $facebook_url];
    }
    if (($shop['line_url'] ?? '') !== $line_url) {
        $changes[] = ['field' => 'Line Link', 'old' => $shop['line_url'] ?? '', 'new' => $line_url];
    }
    if (($shop['instagram_url'] ?? '') !== $instagram_url) {
        $changes[] = ['field' => 'Instagram Link', 'old' => $shop['instagram_url'] ?? '', 'new' => $instagram_url];
    }
    if (($shop['print_remark'] ?? '') !== $remark) {
        $changes[] = ['field' => 'หมายเหตุท้ายใบเสร็จ', 'old' => $shop['print_remark'] ?? '', 'new' => $remark];
    }
    if (($shop['smtp_host'] ?? '') !== $smtp_host) {
        $changes[] = ['field' => 'SMTP Host', 'old' => $shop['smtp_host'] ?? '', 'new' => $smtp_host];
    }
    if (intval($shop['smtp_port'] ?? 0) !== $smtp_port) {
        $changes[] = ['field' => 'SMTP Port', 'old' => $shop['smtp_port'] ?? '', 'new' => $smtp_port];
    }
    if (($shop['smtp_secure'] ?? '') !== $smtp_secure) {
        $changes[] = ['field' => 'SMTP Secure', 'old' => $shop['smtp_secure'] ?? '', 'new' => $smtp_secure];
    }
    if (($shop['smtp_user'] ?? '') !== $smtp_user) {
        $changes[] = ['field' => 'SMTP Username', 'old' => $shop['smtp_user'] ?? '', 'new' => $smtp_user];
    }
    if (getSecretValue('SMTP_PASS', '') !== $smtp_pass_raw) {
        $changes[] = ['field' => 'SMTP Password', 'old' => '******', 'new' => '(เปลี่ยนรหัสผ่าน)'];
    }
    if (intval($shop['welcome_promo_enabled'] ?? 0) !== $welcome_promo_enabled) {
        $changes[] = ['field' => 'เปิดใช้งานโค้ดต้อนรับสมาชิก', 'old' => ($shop['welcome_promo_enabled'] ?? 0) ? 'เปิด' : 'ปิด', 'new' => $welcome_promo_enabled ? 'เปิด' : 'ปิด'];
    }
    if (($shop['welcome_promo_coupon'] ?? '') !== $welcome_promo_coupon) {
        $changes[] = ['field' => 'โค้ดส่วนลดต้อนรับสมาชิก', 'old' => $shop['welcome_promo_coupon'] ?? 'ไม่มี', 'new' => $welcome_promo_coupon ?: 'ไม่มี'];
    }
    if (floatval($shop['shipping_fee_fixed'] ?? 0) !== $shipping_fee_fixed) {
        $changes[] = ['field' => 'ค่าจัดส่งคงที่', 'old' => '฿' . number_format($shop['shipping_fee_fixed'] ?? 0, 2), 'new' => '฿' . number_format($shipping_fee_fixed, 2)];
    }
    if (floatval($shop['shipping_free_threshold'] ?? 0) !== $shipping_free_threshold) {
        $changes[] = ['field' => 'ยอดสั่งซื้อขั้นต่ำส่งฟรี', 'old' => '฿' . number_format($shop['shipping_free_threshold'] ?? 0, 2), 'new' => '฿' . number_format($shipping_free_threshold, 2)];
    }
    if (intval($shop['points_earn_rate'] ?? 0) !== $points_earn_rate) {
        $changes[] = ['field' => 'อัตราการรับแต้ม (บาท/แต้ม)', 'old' => ($shop['points_earn_rate'] ?? 0) . ' บาท/แต้ม', 'new' => $points_earn_rate . ' บาท/แต้ม'];
    }
    if (intval($shop['points_spend_rate'] ?? 0) !== $points_spend_rate) {
        $changes[] = ['field' => 'มูลค่าคะแนนสะสม (บาท/แต้ม)', 'old' => ($shop['points_spend_rate'] ?? 0) . ' บาท/แต้ม', 'new' => $points_spend_rate . ' บาท/แต้ม'];
    }
    if (getSecretValue('LINE_NOTIFY_TOKEN', '') !== $line_notify_token_raw) {
        $changes[] = ['field' => 'LINE Notify Token', 'old' => '******', 'new' => '(เปลี่ยน Token)'];
    }
    if (getSecretValue('LINE_CHANNEL_ACCESS_TOKEN', '') !== $line_channel_access_token_raw) {
        $changes[] = ['field' => 'LINE Channel Access Token', 'old' => '******', 'new' => '(เปลี่ยน Token)'];
    }
    if (getSecretValue('LINE_USER_ID', '') !== $line_user_id_raw) {
        $changes[] = ['field' => 'LINE User ID', 'old' => '******', 'new' => '(เปลี่ยน User ID)'];
    }
    if (getSecretValue('DISCORD_WEBHOOK_URL', '') !== $discord_webhook_url_raw) {
        $changes[] = ['field' => 'Discord Webhook URL', 'old' => '******', 'new' => '(เปลี่ยน URL)'];
    }
    if (getSecretValue('TELEGRAM_BOT_TOKEN', '') !== $telegram_bot_token_raw) {
        $changes[] = ['field' => 'Telegram Bot Token', 'old' => '******', 'new' => '(เปลี่ยน Token)'];
    }
    if (getSecretValue('TELEGRAM_CHAT_ID', '') !== $telegram_chat_id_raw) {
        $changes[] = ['field' => 'Telegram Chat ID', 'old' => '******', 'new' => '(เปลี่ยน Chat ID)'];
    }
    if (getSecretValue('SLACK_WEBHOOK_URL', '') !== $slack_webhook_url_raw) {
        $changes[] = ['field' => 'Slack Webhook URL', 'old' => '******', 'new' => '(เปลี่ยน URL)'];
    }
    if (getSecretValue('CUSTOM_WEBHOOK_URL', '') !== $custom_webhook_url_raw) {
        $changes[] = ['field' => 'Custom Webhook URL', 'old' => '******', 'new' => '(เปลี่ยน URL)'];
    }
    if (($shop['slip_ai_provider'] ?? '') !== $slip_ai_provider) {
        $changes[] = ['field' => 'ผู้ให้บริการ Slip AI', 'old' => $shop['slip_ai_provider'] ?? '', 'new' => $slip_ai_provider];
    }
    if (getSecretValue('OPENAI_API_KEY', '') !== $openai_api_key_raw) {
        $changes[] = ['field' => 'OpenAI API Key', 'old' => '******', 'new' => '(เปลี่ยน Key)'];
    }
    if (getSecretValue('GEMINI_API_KEY', '') !== $gemini_api_key_raw) {
        $changes[] = ['field' => 'Gemini API Key', 'old' => '******', 'new' => '(เปลี่ยน Key)'];
    }
    if (getSecretValue('CLAUDE_API_KEY', '') !== $claude_api_key_raw) {
        $changes[] = ['field' => 'Claude API Key', 'old' => '******', 'new' => '(เปลี่ยน Key)'];
    }
    $sound = mysqli_real_escape_string($conn, $_POST['notification_sound'] ?? 'chime');
    if (($shop['notification_sound'] ?? 'chime') !== $sound) {
        $sound_labels = [
            'chime' => 'กระดิ่งพาสเทล',
            'glass' => 'เสียงแก้วใส',
            'beep' => 'เสียงบี๊บแจ้งเตือน',
            'piano' => 'เสียงคอร์ดเปียโน',
            'mute' => 'ปิดเสียงแจ้งเตือน'
        ];
        $old_lbl = $sound_labels[$shop['notification_sound'] ?? 'chime'] ?? ($shop['notification_sound'] ?? 'chime');
        $new_lbl = $sound_labels[$sound] ?? $sound;
        $changes[] = ['field' => 'เสียงแจ้งเตือนออเดอร์ใหม่', 'old' => $old_lbl, 'new' => $new_lbl];
    }

    log_admin_action($conn, 'แก้ไขตั้งค่าร้านค้า', [
        'title' => 'แก้ไขการตั้งค่าระบบและข้อมูลร้านค้า',
        'changes' => $changes
    ]);

    // 2. อัปเดต Icon (ถ้ามีการอัปโหลดใหม่)
    if (isset($_FILES['shop_icon']) && $_FILES['shop_icon']['error'] == 0) {
        $ext = pathinfo($_FILES['shop_icon']['name'], PATHINFO_EXTENSION);
        $allowed = ['ico', 'png', 'jpg', 'jpeg', 'webp'];
        if (in_array(strtolower($ext), $allowed)) {
            $new_icon = "favicon_" . time() . "." . strtolower($ext);
            
            if (!is_dir("uploads")) mkdir("uploads");
            
            if (move_uploaded_file($_FILES['shop_icon']['tmp_name'], "uploads/" . $new_icon)) {
    }

    // 2.1 อัปเดตไฟล์เสียงระบบกำหนดเอง (ถ้ามีการอัปโหลดใหม่)
    if (isset($_FILES['custom_sound_file']) && $_FILES['custom_sound_file']['error'] == 0) {
        $ext = pathinfo($_FILES['custom_sound_file']['name'], PATHINFO_EXTENSION);
        $allowed_audio = ['mp3', 'wav', 'ogg', 'aac', 'm4a'];
        if (in_array(strtolower($ext), $allowed_audio)) {
            $new_sound_name = "custom_alarm." . strtolower($ext);
            if (!is_dir("uploads")) {
                mkdir("uploads", 0755, true);
            }
            // Delete any existing custom_alarm files with different extensions to avoid conflict
            foreach ($allowed_audio as $all_ext) {
                @unlink("uploads/custom_alarm." . $all_ext);
            }
            if (move_uploaded_file($_FILES['custom_sound_file']['tmp_name'], "uploads/" . $new_sound_name)) {
                updateEnv('CUSTOM_SOUND_PATH', "uploads/" . $new_sound_name, $env_path);
            }
        }
    }

    $ajax_success_message = 'บันทึกข้อมูลร้านค้าเรียบร้อยแล้ว';
    $ajax_status = 'success';

    if (isset($_POST['test_smtp'])) {
        include 'mail_sender.php';
        $res = send_test_email($conn);
        if ($res === true) {
            log_admin_action($conn, 'ทดสอบ SMTP', [
                'title' => "ทดสอบการเชื่อมต่อระบบ SMTP",
                'changes' => [
                    ['field' => 'ผลลัพธ์การทดสอบ', 'old' => '-', 'new' => 'สำเร็จ']
                ]
            ]);
            $ajax_success_message = 'ทดสอบการเชื่อมต่อ SMTP สำเร็จแล้ว! มีอีเมลทดสอบส่งไปยังกล่องจดหมายของคุณเรียบร้อย';
            $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'ทดสอบการเชื่อมต่อ SMTP สำเร็จแล้ว! มีอีเมลทดสอบส่งไปยังกล่องจดหมายของคุณเรียบร้อย', 'icon'=>'success'];
        } else {
            log_admin_action($conn, 'ทดสอบ SMTP', [
                'title' => "ทดสอบการเชื่อมต่อระบบ SMTP",
                'changes' => [
                    ['field' => 'ผลลัพธ์การทดสอบ', 'old' => '-', 'new' => "ล้มเหลว - $res"]
                ]
            ]);
            $ajax_success_message = 'เชื่อมต่อล้มเหลว: ' . $res;
            $ajax_status = 'error';
            $_SESSION['swal'] = ['title'=>'เกิดข้อผิดพลาด', 'text'=>'เชื่อมต่อล้มเหลว: ' . $res, 'icon'=>'error'];
        }
    } else {
        $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'บันทึกข้อมูลร้านค้าเรียบร้อยแล้ว', 'icon'=>'success'];
    }

    if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
        header('Content-Type: application/json');
        echo json_encode(['status' => $ajax_status, 'message' => $ajax_success_message]);
        exit();
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
                <form id="settings-form" method="POST" enctype="multipart/form-data" onsubmit="submitSettingsForm(event)">
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

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold"><i class="bi bi-facebook text-primary me-1"></i> Facebook Link</label>
                            <input type="text" name="facebook_url" class="form-control" value="<?= htmlspecialchars($shop['facebook_url'] ?? '#') ?>" placeholder="ลิงก์ Facebook ของร้าน">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold"><i class="bi bi-line text-success me-1"></i> Line Link</label>
                            <input type="text" name="line_url" class="form-control" value="<?= htmlspecialchars($shop['line_url'] ?? '#') ?>" placeholder="ลิงก์ Line ของร้าน">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold"><i class="bi bi-instagram text-danger me-1"></i> Instagram Link</label>
                            <input type="text" name="instagram_url" class="form-control" value="<?= htmlspecialchars($shop['instagram_url'] ?? '#') ?>" placeholder="ลิงก์ Instagram ของร้าน">
                        </div>
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
                                <?php if (hasEnvValue('SMTP_HOST')): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars(getSecretValue('SMTP_HOST', $shop['smtp_host'] ?? '')) ?>" placeholder="เช่น smtp.gmail.com">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label small fw-bold text-muted">
                                SMTP Port
                                <?php if (hasEnvValue('SMTP_PORT')): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars(getSecretValue('SMTP_PORT', $shop['smtp_port'] ?? '587')) ?>" placeholder="เช่น 587">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label small fw-bold text-muted">
                                ประเภทความปลอดภัย
                                <?php if (hasEnvValue('SMTP_SECURE')): ?>
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
                                <?php if (hasEnvValue('SMTP_USER')): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <input type="email" name="smtp_user" class="form-control" value="<?= htmlspecialchars(getSecretValue('SMTP_USER', $shop['smtp_user'] ?? '')) ?>" placeholder="เช่น shop@gmail.com">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-bold text-muted">
                                SMTP Password (หรือ App Password)
                                <?php if (hasEnvValue('SMTP_PASS')): ?>
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
                    <h5 class="fw-bold text-primary mb-3"><i class="bi bi-bell-fill me-1"></i> ระบบแจ้งเตือนคำสั่งซื้อใหม่ (Social & Webhook Notifications)</h5>
                    <div class="row g-3 mb-4">
                        <!-- LINE Notify -->
                        <div class="col-12 mb-3">
                            <label class="form-label small fw-bold text-muted">
                                LINE Notify Token
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚠️ บริการนี้ยกเลิกโดย LINE แล้ว</span>
                                <?php if (hasEnvValue('LINE_NOTIFY_TOKEN')): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group">
                                <input type="password" name="line_notify_token" id="lineNotifyInput" class="form-control" value="<?= htmlspecialchars(getMaskedValue('LINE_NOTIFY_TOKEN', $shop['line_notify_token'] ?? '')) ?>" placeholder="ใส่ Line Notify Token ของร้านค้า">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('lineNotifyInput', this)" style="border-color: #dee2e6;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text text-danger">⚠️ LINE ได้ประกาศยกเลิกการให้บริการ LINE Notify อย่างเป็นทางการแล้ว (เมื่อ 31 มี.ค. 2568) แนะนำให้ปรับไปใช้ LINE Messaging API ด้านล่าง หรือการแจ้งเตือนช่องทางอื่น ๆ ทดแทน</div>
                        </div>

                        <!-- LINE Messaging API -->
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted">
                                LINE Channel Access Token (Messaging API)
                                <?php if (hasEnvValue('LINE_CHANNEL_ACCESS_TOKEN')): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group">
                                <input type="password" name="line_channel_access_token" id="lineChannelTokenInput" class="form-control" value="<?= htmlspecialchars(getMaskedValue('LINE_CHANNEL_ACCESS_TOKEN', $shop['line_channel_access_token'] ?? '')) ?>" placeholder="ใส่ Channel Access Token ของ LINE Bot ที่นี่">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('lineChannelTokenInput', this)" style="border-color: #dee2e6;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted">
                                LINE User ID / Group ID (Messaging API)
                                <?php if (hasEnvValue('LINE_USER_ID')): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group">
                                <input type="password" name="line_user_id" id="lineUserIdInput" class="form-control" value="<?= htmlspecialchars(getMaskedValue('LINE_USER_ID', $shop['line_user_id'] ?? '')) ?>" placeholder="U123456789abcdef... หรือ Group ID">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('lineUserIdInput', this)" style="border-color: #dee2e6;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">ระบุ LINE User ID ของแอดมิน หรือ Group ID ที่บอทเข้าร่วม เพื่อส่งข้อความแจ้งเตือนออเดอร์ใหม่</div>
                        </div>

                        <!-- Discord Webhook -->
                        <div class="col-12 mb-3">
                            <label class="form-label small fw-bold text-muted">
                                Discord Webhook URL
                                <?php if (hasEnvValue('DISCORD_WEBHOOK_URL')): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group">
                                <input type="password" name="discord_webhook_url" id="discordWebhookInput" class="form-control" value="<?= htmlspecialchars(getMaskedValue('DISCORD_WEBHOOK_URL', $shop['discord_webhook_url'] ?? '')) ?>" placeholder="https://discord.com/api/webhooks/...">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('discordWebhookInput', this)" style="border-color: #dee2e6;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">รับแจ้งเตือนออเดอร์ใหม่เข้าแชนแนล Discord (สร้าง Webhook ได้ในส่วน Integration ของห้องแชทใน Discord)</div>
                        </div>

                        <!-- Telegram Integration -->
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted">
                                Telegram Bot Token
                                <?php if (hasEnvValue('TELEGRAM_BOT_TOKEN')): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group">
                                <input type="password" name="telegram_bot_token" id="telegramBotTokenInput" class="form-control" value="<?= htmlspecialchars(getMaskedValue('TELEGRAM_BOT_TOKEN', $shop['telegram_bot_token'] ?? '')) ?>" placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('telegramBotTokenInput', this)" style="border-color: #dee2e6;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted">
                                Telegram Chat ID
                                <?php if (hasEnvValue('TELEGRAM_CHAT_ID')): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group">
                                <input type="password" name="telegram_chat_id" id="telegramChatIdInput" class="form-control" value="<?= htmlspecialchars(getMaskedValue('TELEGRAM_CHAT_ID', $shop['telegram_chat_id'] ?? '')) ?>" placeholder="เช่น -100123456789 หรือ 123456789">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('telegramChatIdInput', this)" style="border-color: #dee2e6;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">บอทเทเลแกรมและ Chat ID ของห้อง/กลุ่มที่จะรับแจ้งเตือน (ติดต่อ @BotFather เพื่อสร้างบอท)</div>
                        </div>

                        <!-- Slack Webhook -->
                        <div class="col-12 mb-3">
                            <label class="form-label small fw-bold text-muted">
                                Slack Webhook URL
                                <?php if (hasEnvValue('SLACK_WEBHOOK_URL')): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group">
                                <input type="password" name="slack_webhook_url" id="slackWebhookInput" class="form-control" value="<?= htmlspecialchars(getMaskedValue('SLACK_WEBHOOK_URL', $shop['slack_webhook_url'] ?? '')) ?>" placeholder="https://hooks.slack.com/services/...">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('slackWebhookInput', this)" style="border-color: #dee2e6;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">รับแจ้งเตือนผ่านช่องทาง Slack (สร้าง Incoming Webhook ได้จากแอป Slack)</div>
                        </div>

                        <!-- Custom Webhook URL -->
                        <div class="col-12 mb-3">
                            <label class="form-label small fw-bold text-muted">
                                Custom Webhook URL (Generic Webhook API)
                                <?php if (hasEnvValue('CUSTOM_WEBHOOK_URL')): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-2" style="font-size: 0.65rem; margin-left: 5px;">⚙️ โหลดจาก .env</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group">
                                <input type="password" name="custom_webhook_url" id="customWebhookInput" class="form-control" value="<?= htmlspecialchars(getMaskedValue('CUSTOM_WEBHOOK_URL', $shop['custom_webhook_url'] ?? '')) ?>" placeholder="https://yourdomain.com/webhook-receiver">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('customWebhookInput', this)" style="border-color: #dee2e6;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">ส่งข้อมูลออเดอร์ใหม่ในรูปแบบ JSON POST ไปยัง API ปลายทางภายนอกที่คุณกำหนดโดยตรง</div>
                        </div>

                        <!-- เสียงแจ้งเตือนออเดอร์ใหม่ (Real-Time Sound Settings) -->
                        <div class="col-12 mb-3">
                            <label class="form-label small fw-bold text-muted">🔊 เสียงแจ้งเตือนเมื่อมีกิจกรรมใหม่ (Real-Time Sound Settings)</label>
                            <select name="notification_sound" id="notificationSoundSelect" class="form-select mb-2" onchange="previewNotificationSound(this.value); toggleCustomSoundField(this.value)">
                                <option value="chime" <?= ($shop['notification_sound'] ?? 'chime') == 'chime' ? 'selected' : '' ?>>กระดิ่งพาสเทล / Chime (Default)</option>
                                <option value="glass" <?= ($shop['notification_sound'] ?? 'chime') == 'glass' ? 'selected' : '' ?>>เสียงแก้วใส / Crystal Glass</option>
                                <option value="beep" <?= ($shop['notification_sound'] ?? 'chime') == 'beep' ? 'selected' : '' ?>>เสียงบี๊บแจ้งเตือน / Digital Beep</option>
                                <option value="piano" <?= ($shop['notification_sound'] ?? 'chime') == 'piano' ? 'selected' : '' ?>>เสียงคอร์ดเปียโน / Piano Chord</option>
                                <option value="custom" <?= ($shop['notification_sound'] ?? 'chime') == 'custom' ? 'selected' : '' ?>>🎵 เสียงกำหนดเอง / Custom Sound (อัพโหลด/ระบุลิงก์)</option>
                                <option value="mute" <?= ($shop['notification_sound'] ?? 'chime') == 'mute' ? 'selected' : '' ?>>🔇 ปิดเสียงแจ้งเตือน / Mute</option>
                            </select>
                            <div class="form-text">เมื่อลูกค้ารีวิว สั่งซื้อ หรือส่งข้อความเข้ามาใหม่ เสียงที่เลือกจะเล่นเตือนแอดมินทันที</div>
                        </div>

                        <!-- ตัวเลือกสำหรับกำหนดไฟล์เสียงเอง (Custom Sound Input) -->
                        <div class="col-12 mb-3 <?= ($shop['notification_sound'] ?? 'chime') == 'custom' ? '' : 'd-none' ?>" id="customSoundContainer">
                            <div class="card bg-light border-0 rounded-3 p-3">
                                <label class="form-label small fw-bold text-dark mb-2"><i class="bi bi-file-earmark-music text-primary me-1"></i>อัพโหลดไฟล์เสียง หรือ ระบุลิงก์ไฟล์เสียงภายนอก</label>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">อัพโหลดไฟล์เสียงระบบ (.mp3, .wav, .ogg, .aac, .m4a)</label>
                                        <input type="file" name="custom_sound_file" class="form-control" accept="audio/*">
                                        <?php 
                                        $uploaded_sounds = glob("uploads/custom_alarm.*");
                                        if (!empty($uploaded_sounds) && file_exists($uploaded_sounds[0])):
                                            $sound_ext = pathinfo($uploaded_sounds[0], PATHINFO_EXTENSION);
                                        ?>
                                            <div class="form-text text-success mt-1"><i class="bi bi-check-circle-fill"></i> มีไฟล์เสียงอัพโหลดอยู่ในระบบแล้ว: <code><?= htmlspecialchars($uploaded_sounds[0]) ?></code></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">หรือระบุลิงก์ URL เสียงภายนอก (Audio URL Link)</label>
                                        <input type="url" name="custom_sound_url" id="customSoundUrlInput" class="form-control" value="<?= htmlspecialchars(getSecretValue('CUSTOM_SOUND_URL', $shop['custom_sound_url'] ?? '')) ?>" placeholder="เช่น https://example.com/sound.mp3">
                                        <div class="form-text">หากใช้งานทั้งสองแบบ ระบบจะยึดไฟล์เสียงที่คุณอัพโหลดขึ้นไปเป็นหลัก</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="fw-bold text-primary mb-3"><i class="bi bi-robot me-1"></i> ตั้งค่าระบบตรวจสอบสลิปด้วย AI (AI Slip Verification Settings)</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-4 mb-3">
                            <label class="form-label small fw-bold text-muted">
                                เลือกผู้ให้บริการหลัก (AI Provider)
                                <?php if (hasEnvValue('SLIP_AI_PROVIDER')): ?>
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
                                <?php if (hasEnvValue('OPENAI_API_KEY')): ?>
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
                                <?php if (hasEnvValue('GEMINI_API_KEY')): ?>
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
                                <?php if (hasEnvValue('CLAUDE_API_KEY')): ?>
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
                            <button type="submit" name="save_settings" id="save-settings-btn" class="btn btn-pastel-blue rounded-pill px-4 w-100 py-2 fw-bold shadow-sm">
                                <i class="bi bi-save me-1"></i> บันทึกข้อมูลทั้งหมด
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button type="button" onclick="testSmtpConnection()" id="test-smtp-btn" class="btn btn-outline-primary rounded-pill px-4 w-100 py-2 fw-bold shadow-sm bg-white" style="border-color: #AEE2FF; color: #444;">
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

    function previewNotificationSound(soundType) {
        if (soundType === 'mute') return;
        
        if (soundType === 'custom') {
            try {
                let audioUrl = '';
                const hasUploadedFile = <?php
                    $uploaded_sounds = glob("uploads/custom_alarm.*");
                    echo (!empty($uploaded_sounds) && file_exists($uploaded_sounds[0])) ? 'true' : 'false';
                ?>;
                if (hasUploadedFile) {
                    audioUrl = '<?php
                        $uploaded_sounds = glob("uploads/custom_alarm.*");
                        echo !empty($uploaded_sounds) ? $uploaded_sounds[0] : '';
                    ?>?v=' + Date.now();
                } else {
                    const urlInput = document.getElementById('customSoundUrlInput');
                    if (urlInput && urlInput.value) {
                        audioUrl = urlInput.value;
                    }
                }
                if (audioUrl) {
                    const audio = new Audio(audioUrl);
                    audio.play().catch(err => console.warn('Custom audio preview failed:', err));
                } else {
                    previewNotificationSound('chime');
                }
            } catch (e) {
                console.warn('Custom audio preview failed:', e);
            }
            return;
        }

        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            const now = ctx.currentTime;
            
            if (soundType === 'chime') {
                const osc1 = ctx.createOscillator();
                const gain1 = ctx.createGain();
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(659.25, now);
                gain1.gain.setValueAtTime(0.1, now);
                gain1.gain.exponentialRampToValueAtTime(0.0001, now + 0.5);
                osc1.connect(gain1);
                gain1.connect(ctx.destination);
                osc1.start(now);
                osc1.stop(now + 0.5);
                
                const osc2 = ctx.createOscillator();
                const gain2 = ctx.createGain();
                osc2.type = 'sine';
                osc2.frequency.setValueAtTime(880.00, now + 0.1);
                gain2.gain.setValueAtTime(0.15, now + 0.1);
                gain2.gain.exponentialRampToValueAtTime(0.0001, now + 0.7);
                osc2.connect(gain2);
                gain2.connect(ctx.destination);
                osc2.start(now + 0.1);
                osc2.stop(now + 0.7);
            } else if (soundType === 'glass') {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(1500, now);
                gain.gain.setValueAtTime(0.1, now);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.3);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now);
                osc.stop(now + 0.3);
            } else if (soundType === 'beep') {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(880, now);
                gain.gain.setValueAtTime(0.1, now);
                gain.gain.setValueAtTime(0.1, now + 0.15);
                gain.gain.linearRampToValueAtTime(0.0001, now + 0.2);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now);
                osc.stop(now + 0.2);
            } else if (soundType === 'piano') {
                const notes = [261.63, 329.63, 392.00, 523.25];
                notes.forEach((freq, index) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, now + index * 0.05);
                    gain.gain.setValueAtTime(0.08, now + index * 0.05);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now + 1.0);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(now + index * 0.05);
                    osc.stop(now + 1.0);
                });
            }
        } catch (e) {
            console.warn('AudioContext failed:', e);
        }
    }

    function toggleCustomSoundField(value) {
        const container = document.getElementById('customSoundContainer');
        if (value === 'custom') {
            container.classList.remove('d-none');
        } else {
            container.classList.add('d-none');
        }
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

    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    function submitSettingsForm(e) {
        e.preventDefault();
        const form = document.getElementById('settings-form');
        const submitBtn = document.getElementById('save-settings-btn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> กำลังบันทึก...';
        
        const formData = new FormData(form);
        formData.append('save_settings', '1');
        formData.append('ajax', '1');
        
        fetch(window.location.pathname, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-save me-1"></i> บันทึกข้อมูลทั้งหมด';
            
            if (data.status === 'success') {
                Toast.fire({
                    icon: 'success',
                    title: data.message
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: data.message,
                    confirmButtonColor: '#AEE2FF'
                });
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-save me-1"></i> บันทึกข้อมูลทั้งหมด';
            console.error(err);
            Toast.fire({
                icon: 'error',
                title: 'การเชื่อมต่อล้มเหลว'
            });
        });
    }

    function testSmtpConnection() {
        const form = document.getElementById('settings-form');
        const testBtn = document.getElementById('test-smtp-btn');
        testBtn.disabled = true;
        
        Swal.fire({
            title: 'กำลังทดสอบเชื่อมต่อ SMTP',
            text: 'กรุณารอสักครู่ ระบบกำลังทดสอบการส่งอีเมล...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        const formData = new FormData(form);
        formData.append('save_settings', '1');
        formData.append('test_smtp', '1');
        formData.append('ajax', '1');
        
        fetch(window.location.pathname, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            testBtn.disabled = false;
            Swal.close();
            
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'เชื่อมต่อสำเร็จ',
                    text: data.message,
                    confirmButtonColor: '#AEE2FF'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'เชื่อมต่อล้มเหลว',
                    text: data.message,
                    confirmButtonColor: '#AEE2FF'
                });
            }
        })
        .catch(err => {
            testBtn.disabled = false;
            Swal.close();
            console.error(err);
            Swal.fire({
                icon: 'error',
                title: 'ข้อผิดพลาด',
                text: 'การเชื่อมต่อกับเซิร์ฟเวอร์ล้มเหลว',
                confirmButtonColor: '#AEE2FF'
            });
        });
    }
</script>
</body>
</html>
