<?php
session_start();
include 'db.php';
include 'mail_sender.php';

// 1. ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// 2. เช็คสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

$shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id = 1"));
$smtp_user = getenv('SMTP_USER') ?: ($shop['smtp_user'] ?? '');
$smtp_pass = getenv('SMTP_PASS') ?: ($shop['smtp_pass'] ?? '');
$smtp_host = getenv('SMTP_HOST') ?: ($shop['smtp_host'] ?? '');

$smtp_configured = (!empty($smtp_user) && !empty($smtp_pass) && !empty($smtp_host));

// ดึงข้อมูลสมาชิกทั่วไปที่มีอีเมล (เรียงตามชื่อจริง)
$q_users = mysqli_query($conn, "SELECT id, username, fullname, email FROM users WHERE email IS NOT NULL AND email != '' ORDER BY fullname ASC");
$users = [];
if ($q_users) {
    while ($r = mysqli_fetch_assoc($q_users)) {
        $users[] = $r;
    }
}

// ดึงข้อมูลคูปองส่วนลดที่ยังไม่หมดอายุ (หรือไม่มีวันหมดอายุ)
$q_coupons = mysqli_query($conn, "SELECT * FROM coupons WHERE expiry_date >= CURDATE() OR expiry_date IS NULL ORDER BY id DESC");
$coupons = [];
if ($q_coupons) {
    while ($c = mysqli_fetch_assoc($q_coupons)) {
        $coupons[] = $c;
    }
}

// ดึงข้อมูลแอดมินที่กำลังล็อกอินอยู่สำหรับส่งอีเมลทดสอบ
$admin_id = intval($_SESSION['user_id'] ?? 0);
$admin_email = '';
$admin_name = 'ผู้ดูแลระบบ';
if ($admin_id > 0) {
    $admin_res = mysqli_query($conn, "SELECT email, fullname FROM users WHERE id = $admin_id");
    if ($admin_res && $admin_user = mysqli_fetch_assoc($admin_res)) {
        $admin_email = !empty($admin_user['email']) ? $admin_user['email'] : '';
        $admin_name = !empty($admin_user['fullname']) ? $admin_user['fullname'] : 'ผู้ดูแลระบบ';
    }
}

// ระบบประมวลผลการส่งฟอร์ม (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $log_data = "=== " . date('Y-m-d H:i:s') . " ===\n" . print_r($_POST, true) . "=====================\n\n";
    @file_put_contents(__DIR__ . '/logs/debug_post.log', $log_data, FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['send_mail']) || isset($_POST['send_test']) || !empty($_POST['submit_type']))) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }

    $is_test = isset($_POST['send_test']);
    if (isset($_POST['submit_type'])) {
        if ($_POST['submit_type'] === 'send_test') {
            $is_test = true;
        } elseif ($_POST['submit_type'] === 'send_mail') {
            $is_test = false;
        }
    }

    $to_email = trim($_POST['to_email'] ?? '');
    $to_name = trim($_POST['to_name'] ?? '');
    
    if ($is_test) {
        // สลับเป็นอีเมลจำลองของแอดมินเอง
        $to_email = !empty($admin_email) ? $admin_email : (!empty($shop['shop_email']) ? $shop['shop_email'] : $smtp_user);
        $to_name = $admin_name . " (ทดสอบระบบ)";
        $recipient_type = 'manual';
    } else {
        $recipient_type = trim($_POST['recipient_type'] ?? 'member');
    }

    $subject = trim($_POST['subject'] ?? '');
    $body_content = trim($_POST['body_content'] ?? '');
    $coupon_id = intval($_POST['coupon_id'] ?? 0);
    $inactivity_days = intval($_POST['inactivity_days'] ?? 30);

    $attachments = [];
    $total_size = 0;
    $max_file_size = 5 * 1024 * 1024;    // 5MB ต่อไฟล์
    $max_total_size = 15 * 1024 * 1024;  // 15MB ขนาดรวมทั้งหมด

    $promo_image = null;
    $coupon_data = null;
    $error_msg = '';

    if (!$smtp_configured) {
        $error_msg = 'ระบบอีเมลยังไม่ถูกตั้งค่า กรุณาตั้งค่า SMTP ในหน้าตั้งค่าร้านค้าก่อนส่งอีเมล';
    } elseif ($is_test && empty($to_email)) {
        $error_msg = 'ไม่พบอีเมลผู้รับสำหรับส่งทดสอบ กรุณาตั้งค่าอีเมลในโปรไฟล์ของคุณ หรือระบุอีเมลร้านค้าในหน้าตั้งค่าก่อน';
    } elseif ($recipient_type !== 'all' && $recipient_type !== 'inactive' && (empty($to_email) || empty($to_name))) {
        $error_msg = 'กรุณากรอกข้อมูลผู้รับให้ครบถ้วน';
    } elseif (empty($subject) || empty($body_content)) {
        $error_msg = 'กรุณากรอกข้อมูลให้ครบถ้วนทุกช่อง';
    } elseif ($recipient_type !== 'all' && $recipient_type !== 'inactive' && !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'กรุณาระบุรูปแบบอีเมลผู้รับที่ถูกต้อง';
    } else {
        // 1. ตรวจสอบความถูกต้องของคูปองที่เลือก
        if ($coupon_id > 0) {
            $c_res = mysqli_query($conn, "SELECT * FROM coupons WHERE id = $coupon_id");
            if ($c_res && mysqli_num_rows($c_res) > 0) {
                $coupon_data = mysqli_fetch_assoc($c_res);
            }
        }

        // 2. ตรวจสอบความถูกต้องของรูปภาพโปรโมชั่น
        if (isset($_FILES['promo_image']) && $_FILES['promo_image']['error'] === UPLOAD_ERR_OK) {
            $img_size = $_FILES['promo_image']['size'];
            $img_name = $_FILES['promo_image']['name'];
            $img_ext = strtolower(pathinfo($img_name, PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($img_ext, $allowed_exts)) {
                $error_msg = 'รูปภาพโปรโมชั่นแบนเนอร์ต้องเป็นนามสกุล JPG, JPEG, PNG, GIF หรือ WebP เท่านั้น';
            } elseif ($img_size > 3 * 1024 * 1024) {
                $error_msg = 'รูปภาพโปรโมชั่นต้องมีขนาดไม่เกิน 3MB';
            } else {
                $promo_image = [
                    'tmp_name' => $_FILES['promo_image']['tmp_name'],
                    'name' => $img_name
                ];
            }
        } elseif (isset($_FILES['promo_image']) && $_FILES['promo_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $error_msg = 'เกิดข้อผิดพลาดในการอัปโหลดรูปภาพโปรโมชั่น';
        }

        // 3. ประมวลผลไฟล์แนบ
        if (empty($error_msg) && !empty($_FILES['attachments']['name'][0])) {
            for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_size = $_FILES['attachments']['size'][$i];
                    $file_name = $_FILES['attachments']['name'][$i];

                    if ($file_size > $max_file_size) {
                        $error_msg = "ไฟล์ '$file_name' มีขนาดใหญ่เกินไป (สูงสุด 5MB ต่อไฟล์)";
                        break;
                    }

                    $total_size += $file_size;
                    if ($total_size > $max_total_size) {
                        $error_msg = "ขนาดไฟล์แนบรวมทั้งหมดเกินกำหนด (สูงสุด 15MB)";
                        break;
                    }

                    $attachments[] = [
                        'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                        'name' => $file_name
                    ];
                } elseif ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                    $error_msg = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์แนบบางรายการ";
                    break;
                }
            }
        }

        // 4. ดำเนินการส่งอีเมลหากตรวจสอบผ่านทั้งหมด
        if (empty($error_msg)) {
            if ($recipient_type === 'all') {
                // ส่งหาลูกค้าทั่วไปทุกคนที่มีอีเมล
                $q_recipients = mysqli_query($conn, "SELECT fullname, email FROM users WHERE role = 'user' AND email IS NOT NULL AND email != ''");
                $success_count = 0;
                $fail_count = 0;
                $failed_list = [];

                if ($q_recipients && mysqli_num_rows($q_recipients) > 0) {
                    @set_time_limit(0); // ปิดข้อจำกัดเวลาทำงาน (Unlimited) สำหรับปริมาณข้อมูลขนาดใหญ่

                    while ($user = mysqli_fetch_assoc($q_recipients)) {
                        $res = send_direct_custom_email($conn, $user['email'], $user['fullname'], $subject, $body_content, $attachments, $promo_image, $coupon_data);
                        if ($res === true) {
                            $success_count++;
                        } else {
                            $fail_count++;
                            $failed_list[] = $user['fullname'] . " (" . $user['email'] . ")";
                        }
                    }

                    $_SESSION['swal'] = [
                        'title' => 'ส่งอีเมลสำเร็จ!',
                        'text' => "ส่งอีเมลโปรโมชั่นหาลูกค้าสำเร็จทั้งหมด $success_count รายการ" . ($fail_count > 0 ? ", ล้มเหลว $fail_count รายการ" : ""),
                        'icon' => 'success'
                    ];
                    
                    log_admin_action($conn, 'กระจายข่าวส่งอีเมลโปรโมชั่น', [
                        'title' => "กระจายข่าวส่งอีเมลหาลูกค้าทุกคนสำเร็จ: ส่งสำเร็จ $success_count ราย, ล้มเหลว $fail_count ราย",
                        'details' => [
                            'subject' => $subject,
                            'success_count' => $success_count,
                            'fail_count' => $fail_count,
                            'failed_list' => $failed_list,
                            'attachment_count' => count($attachments),
                            'has_promo_image' => ($promo_image !== null),
                            'coupon_code' => ($coupon_data ? $coupon_data['code'] : null)
                        ]
                    ]);
                } else {
                    $error_msg = 'ไม่พบรายชื่อลูกค้าระดับสิทธิ์ทั่วไปที่มีข้อมูลอีเมลในระบบ';
                }
            } elseif ($recipient_type === 'inactive') {
                // ส่งหาลูกค้าที่ไม่ได้ออนไลน์นานตามช่วงเวลาที่กำหนดที่ถูกเลือกส่ง
                $days = $inactivity_days <= 0 ? 30 : $inactivity_days;
                $selected_emails = isset($_POST['selected_inactive_emails']) ? $_POST['selected_inactive_emails'] : [];
                
                if (empty($selected_emails)) {
                    $error_msg = "กรุณาเลือกรายชื่อลูกค้าอย่างน้อย 1 คนสำหรับส่งอีเมล";
                } else {
                    $email_list = [];
                    foreach ($selected_emails as $email) {
                        $email_list[] = "'" . mysqli_real_escape_string($conn, $email) . "'";
                    }
                    
                    if (empty($email_list)) {
                        $error_msg = "กรุณาเลือกรายชื่อลูกค้าอย่างน้อย 1 คนสำหรับส่งอีเมล";
                    } else {
                        $email_in_clause = implode(",", $email_list);
                        $q_recipients = mysqli_query($conn, "
                            SELECT fullname, email 
                            FROM users 
                            WHERE role = 'user' 
                              AND email IS NOT NULL 
                              AND email != '' 
                              AND email IN ($email_in_clause)
                              AND (last_login < DATE_SUB(NOW(), INTERVAL $days DAY) 
                                   OR (last_login IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL $days DAY)))
                        ");
                        $success_count = 0;
                        $fail_count = 0;
                        $failed_list = [];

                        if ($q_recipients && mysqli_num_rows($q_recipients) > 0) {
                            @set_time_limit(0); // ปิดข้อจำกัดเวลาทำงาน (Unlimited) สำหรับปริมาณข้อมูลขนาดใหญ่

                            while ($user = mysqli_fetch_assoc($q_recipients)) {
                                $res = send_direct_custom_email($conn, $user['email'], $user['fullname'], $subject, $body_content, $attachments, $promo_image, $coupon_data);
                                if ($res === true) {
                                    $success_count++;
                                } else {
                                    $fail_count++;
                                    $failed_list[] = $user['fullname'] . " (" . $user['email'] . ")";
                                }
                            }

                            $_SESSION['swal'] = [
                                'title' => 'ส่งอีเมลสำเร็จ!',
                                'text' => "ส่งอีเมลหาลูกค้าเก่าที่เลือกสำเร็จทั้งหมด $success_count รายการ" . ($fail_count > 0 ? ", ล้มเหลว $fail_count รายการ" : ""),
                                'icon' => 'success'
                            ];

                            log_admin_action($conn, 'กระจายข่าวส่งอีเมลกระตุ้นลูกค้าเก่า', [
                                'title' => "ส่งอีเมลกระตุ้นลูกค้าเก่าที่เลือก ($success_count ราย) สำเร็จ: ล้มเหลว $fail_count ราย",
                                'details' => [
                                    'inactivity_days' => $days,
                                    'subject' => $subject,
                                    'success_count' => $success_count,
                                    'fail_count' => $fail_count,
                                    'failed_list' => $failed_list,
                                    'attachment_count' => count($attachments),
                                    'has_promo_image' => ($promo_image !== null),
                                    'coupon_code' => ($coupon_data ? $coupon_data['code'] : null)
                                ]
                            ]);
                        } else {
                            $error_msg = "ไม่พบรายชื่อลูกค้าระดับสิทธิ์ทั่วไปที่ตรงกับเงื่อนไขในระบบ";
                        }
                    }
                }
            } else {
                // ส่งอีเมลแบบรายเดี่ยว
                $res = send_direct_custom_email($conn, $to_email, $to_name, $subject, $body_content, $attachments, $promo_image, $coupon_data);
                if ($res === true) {
                    if ($is_test) {
                        $_SESSION['swal'] = [
                            'title' => 'ส่งอีเมลทดสอบสำเร็จ!',
                            'text' => "ส่งอีเมลทดสอบระบบหาคุณ $to_name ($to_email) เรียบร้อยแล้ว!",
                            'icon' => 'success'
                        ];
                        log_admin_action($conn, 'ส่งอีเมลทดสอบระบบ', [
                            'title' => "ส่งอีเมลทดสอบระบบสำเร็จ: $to_name ($to_email)",
                            'details' => [
                                'to_name' => $to_name,
                                'to_email' => $to_email,
                                'subject' => $subject,
                                'message_length' => mb_strlen($body_content),
                                'attachment_count' => count($attachments),
                                'has_promo_image' => ($promo_image !== null),
                                'coupon_code' => ($coupon_data ? $coupon_data['code'] : null)
                            ]
                        ]);
                    } else {
                        $_SESSION['swal'] = [
                            'title' => 'ส่งอีเมลสำเร็จ!',
                            'text' => "ส่งอีเมลหาคุณ $to_name ($to_email) พร้อมแนบข้อมูลโปรโมชั่นสำเร็จเรียบร้อยแล้ว!",
                            'icon' => 'success'
                        ];
                        log_admin_action($conn, 'ส่งอีเมลหาลูกค้า', [
                            'title' => "ส่งอีเมลหาลูกค้าสำเร็จ: $to_name ($to_email)",
                            'details' => [
                                'to_name' => $to_name,
                                'to_email' => $to_email,
                                'subject' => $subject,
                                'message_length' => mb_strlen($body_content),
                                'attachment_count' => count($attachments),
                                'has_promo_image' => ($promo_image !== null),
                                'coupon_code' => ($coupon_data ? $coupon_data['code'] : null)
                            ]
                        ]);
                    }
                } else {
                    $error_msg = ($is_test ? 'การส่งอีเมลทดสอบระบบล้มเหลว: ' : 'การส่งอีเมลล้มเหลว: ') . $res;
                }
            }
        }
    }

    // หากมีข้อผิดพลาดเก็บบันทึกและทำ PRG Redirect
    if (!empty($error_msg)) {
        $_SESSION['swal'] = [
            'title' => 'เกิดข้อผิดพลาด',
            'text' => $error_msg,
            'icon' => 'error'
        ];
    }
    
    // PRG Pattern: ทำการ Redirect กลับมาหน้าเดิมเพื่อป้องกัน F5 ส่งค่าซ้ำซ้อน
    header("Location: admin_send_mail.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ส่งอีเมลหาลูกค้า | Por Mae Bet Taled Admin</title>
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit'; background: #f8f9fa; }
        .btn-gradient { background: linear-gradient(135deg, #AEE2FF 0%, #7FB5FF 100%); color: white; border: none; }
        .btn-gradient:hover { color: white; opacity: 0.9; }
        .card-modern {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(127, 181, 255, 0.05);
        }
        .email-preview-box {
            transition: all 0.3s ease;
        }
        .hover-bg-light:hover {
            background-color: rgba(127, 181, 255, 0.1) !important;
        }
        .cursor-pointer {
            cursor: pointer;
        }
        
        /* Premium Segmented Recipient Selection Tabs */
        .recipient-tabs {
            background: #f1f5f9;
            padding: 6px;
            border-radius: 16px;
            display: flex;
            gap: 4px;
        }
        .recipient-tabs .btn-check + .btn {
            flex: 1;
            border: none;
            border-radius: 12px;
            color: #64748b;
            background: transparent;
            font-weight: 500;
            transition: all 0.25s ease;
            font-size: 0.85rem;
            padding: 10px 4px;
        }
        .recipient-tabs .btn-check:checked + .btn {
            background: #ffffff;
            color: #1e3a8a;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.02);
            font-weight: 600;
        }
        .recipient-tabs .btn-check:not(:checked) + .btn:hover {
            background: rgba(255, 255, 255, 0.5);
            color: #334155;
        }

        /* Modern styled form controls */
        .form-control, .form-select {
            border: 2px solid #f1f5f9;
            background-color: #f8fafc;
            border-radius: 12px !important;
            padding: 12px 16px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #7FB5FF;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(127, 181, 255, 0.15) !important;
        }

        /* Custom Scrollbar for list container */
        #inactive_users_list::-webkit-scrollbar {
            width: 6px;
        }
        #inactive_users_list::-webkit-scrollbar-track {
            background: transparent;
        }
        #inactive_users_list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        #inactive_users_list::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Inactive User Rows */
        .user-row-card {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            margin-bottom: 8px;
            background: #ffffff;
            border: 1px solid #f1f5f9;
            border-radius: 12px;
            transition: all 0.2s ease;
        }
        .user-row-card:hover {
            border-color: #7FB5FF;
            box-shadow: 0 4px 12px rgba(127, 181, 255, 0.08);
            transform: translateY(-1px);
        }
        .user-avatar-circle {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
            color: #0369a1;
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            flex-shrink: 0;
        }
        
        /* Styled File Upload Dropzone */
        .custom-file-upload {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            padding: 24px;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }
        .custom-file-upload:hover {
            border-color: #7FB5FF;
            background: #f0f7ff;
        }
        .custom-file-upload i {
            font-size: 2rem;
            color: #94a3b8;
            margin-bottom: 8px;
            transition: color 0.2s ease;
        }
        .custom-file-upload:hover i {
            color: #7FB5FF;
        }
        .custom-file-upload input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        /* Mock browser header for Live Preview */
        .preview-window-header {
            background: #f1f5f9;
            padding: 12px;
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
        }
        .preview-dots {
            display: flex;
            gap: 6px;
            margin-right: 16px;
        }
        .preview-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        .dot-red { background: #ff5f56; }
        .dot-yellow { background: #ffbd2e; }
        .dot-green { background: #27c93f; }
        .preview-address-bar {
            flex-grow: 1;
            background: #ffffff;
            border-radius: 8px;
            font-size: 0.75rem;
            padding: 4px 12px;
            color: #94a3b8;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .sticky-preview {
            position: -webkit-sticky;
            position: sticky;
            top: 24px;
            z-index: 10;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navigation Drawer -->
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

        <!-- Main Content Area -->
        <div class="col-md-10 p-4 p-md-5">
            <!-- Breadcrumbs / Title -->
            <div class="d-flex flex-column mb-4">
                <h2 class="fw-bold m-0"><i class="bi bi-send-fill text-primary me-2"></i>ส่งอีเมลหาลูกค้า (Email Composer)</h2>
                <p class="text-muted small mb-0">ส่งอีเมลโปรโมชั่น แบนเนอร์ภาพ คูปองส่วนลดแบบกระจายกลุ่ม หรือดึงลูกค้าเก่าที่ไม่ออนไลน์กลับมา</p>
            </div>

            <!-- Warning if SMTP is not configured -->
            <?php if (!$smtp_configured): ?>
                <div class="alert alert-warning border-0 rounded-4 p-4 shadow-sm mb-4 d-flex align-items-center">
                    <div class="fs-1 me-4 text-warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    <div>
                        <h5 class="alert-heading fw-bold mb-1">ยังไม่ได้ตั้งค่าระบบ SMTP</h5>
                        <p class="mb-0 text-secondary">กรุณาตั้งค่าความปลอดภัยและข้อมูลเชื่อมต่ออีเมลของคุณก่อน เพื่ออนุญาตให้ระบบสามารถส่งจดหมายได้</p>
                        <a href="admin_settings.php" class="btn btn-warning btn-sm rounded-pill mt-2 text-white px-3 fw-bold"><i class="bi bi-gear-fill me-1"></i>ไปที่ตั้งค่าร้านค้า</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Composer Layout -->
            <div class="row g-4">
                <!-- Composer Form (Left) -->
                <div class="col-lg-6">
                    <div class="card card-modern p-4 border-0">
                        <form method="POST" id="email-composer-form" enctype="multipart/form-data">
                            <?= get_csrf_input() ?>
                            <input type="hidden" name="submit_type" id="submit_type" value="">
                            
                            <!-- Recipient Selection Toggle -->
                            <div class="mb-4">
                                <label class="form-label fw-bold text-secondary mb-2">ประเภทผู้รับจดหมาย</label>
                                <div class="recipient-tabs w-100" role="group">
                                    <input type="radio" class="btn-check" name="recipient_type" id="type_member" value="member" checked autocomplete="off" <?= !$smtp_configured ? 'disabled' : '' ?>>
                                    <label class="btn btn-outline-primary py-2" for="type_member">
                                        <i class="bi bi-people-fill me-1"></i>เลือกสมาชิก
                                    </label>

                                    <input type="radio" class="btn-check" name="recipient_type" id="type_all" value="all" autocomplete="off" <?= !$smtp_configured ? 'disabled' : '' ?>>
                                    <label class="btn btn-outline-primary py-2" for="type_all">
                                        <i class="bi bi-megaphone-fill me-1"></i>ส่งหาทุกคน
                                    </label>

                                    <input type="radio" class="btn-check" name="recipient_type" id="type_inactive" value="inactive" autocomplete="off" <?= !$smtp_configured ? 'disabled' : '' ?>>
                                    <label class="btn btn-outline-primary py-2" for="type_inactive">
                                        <i class="bi bi-person-dash-fill me-1"></i>ดึงลูกค้าเก่า
                                    </label>

                                    <input type="radio" class="btn-check" name="recipient_type" id="type_manual" value="manual" autocomplete="off" <?= !$smtp_configured ? 'disabled' : '' ?>>
                                    <label class="btn btn-outline-primary py-2" for="type_manual">
                                        <i class="bi bi-pencil-square me-1"></i>กรอกอีเมลเอง
                                    </label>
                                </div>
                            </div>

                            <!-- Member Selector Dropdown (Visible only if recipient_type = member) -->
                            <div class="mb-3" id="member_select_div">
                                <label class="form-label fw-bold text-secondary" for="member_select">ค้นหาสมาชิกในระบบ</label>
                                <select class="form-select rounded-3 py-2" id="member_select" <?= !$smtp_configured ? 'disabled' : '' ?>>
                                    <option value="">-- กรุณาเลือกรายชื่อสมาชิก --</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= htmlspecialchars($u['email']) ?>" data-name="<?= htmlspecialchars($u['fullname']) ?>">
                                            <?= htmlspecialchars($u['fullname']) ?> (<?= htmlspecialchars($u['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Inactivity Days Selector Dropdown (Visible only if recipient_type = inactive) -->
                            <div class="mb-3 d-none" id="inactivity_period_div">
                                <label class="form-label fw-bold text-secondary" for="inactivity_days">ระยะเวลาที่ลูกค้าไม่ออนไลน์</label>
                                <select name="inactivity_days" id="inactivity_days" class="form-select rounded-3 py-2" <?= !$smtp_configured ? 'disabled' : '' ?>>
                                    <option value="7">ไม่ออนไลน์เกิน 7 วัน</option>
                                    <option value="14">ไม่ออนไลน์เกิน 14 วัน</option>
                                    <option value="30" selected>ไม่ออนไลน์เกิน 30 วัน (แนะนำ)</option>
                                    <option value="90">ไม่ออนไลน์เกิน 90 วัน</option>
                                    <option value="180">ไม่ออนไลน์เกิน 180 วัน</option>
                                </select>
                            </div>

                            <!-- Selected Inactive Customers Section (Visible only if recipient_type = inactive) -->
                            <div class="mb-3 d-none" id="inactive_users_selection_div">
                                <label class="form-label fw-bold text-secondary d-flex justify-content-between align-items-center mb-2">
                                    <span>เลือกรายชื่อลูกค้า (<span id="inactive_count">0</span> คน)</span>
                                    <span class="text-primary small fw-semibold" id="selected_count">เลือกแล้ว 0 คน</span>
                                </label>
                                <div class="input-group mb-2 shadow-sm rounded-3">
                                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" id="search_inactive_user" class="form-control border-start-0 ps-0 py-2" placeholder="ค้นหาตามชื่อ หรือ อีเมล...">
                                </div>
                                <div class="card border border-light-subtle rounded-4 p-3 bg-light">
                                    <div class="d-flex justify-content-between align-items-center px-2 py-1 mb-2 border-bottom pb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="check_all_inactive" checked>
                                            <label class="form-check-label fw-bold text-secondary" for="check_all_inactive">
                                                เลือกทั้งหมด
                                            </label>
                                        </div>
                                        <button type="button" id="btn_toggle_selection" class="btn btn-sm btn-link text-decoration-none py-0">สลับการเลือก</button>
                                    </div>
                                    <div id="inactive_users_list" style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
                                        <div class="text-center py-4 text-muted" id="inactive_users_loading">
                                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>กำลังโหลดรายชื่อ...
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Name and Email Inputs -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-secondary" for="to_name">ชื่อผู้รับ</label>
                                    <input type="text" name="to_name" id="to_name" class="form-control rounded-3 py-2" required placeholder="เช่น สมชาย ใจดี" <?= !$smtp_configured ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-secondary" for="to_email">อีเมลผู้รับ</label>
                                    <input type="email" name="to_email" id="to_email" class="form-control rounded-3 py-2" required placeholder="เช่น somchai@gmail.com" <?= !$smtp_configured ? 'disabled' : '' ?>>
                                </div>
                            </div>

                            <!-- Email Subject -->
                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary" for="subject">หัวข้ออีเมล</label>
                                <input type="text" name="subject" id="subject" class="form-control rounded-3 py-2" required placeholder="เช่น แจ้งสิทธิพิเศษสำหรับลูกค้าคนสำคัญ" <?= !$smtp_configured ? 'disabled' : '' ?>>
                            </div>

                            <!-- Promotional Banner Image Upload -->
                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary mb-2"><i class="bi bi-image me-1"></i>รูปภาพแบนเนอร์โปรโมชั่น (แทรกด้านบนสุดของจดหมาย)</label>
                                <div class="custom-file-upload mb-1" id="promo_image_dropzone">
                                    <i class="bi bi-cloud-arrow-up"></i>
                                    <div class="fw-semibold text-secondary" id="promo_image_filename">คลิกหรือลากไฟล์ภาพแบนเนอร์มาที่นี่</div>
                                    <div class="text-muted small mt-1">PNG, JPG, JPEG, WebP (สูงสุด 3MB)</div>
                                    <input type="file" name="promo_image" id="promo_image" accept="image/*" <?= !$smtp_configured ? 'disabled' : '' ?>>
                                </div>
                            </div>

                            <!-- Coupon Recommendation Dropdown -->
                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary" for="coupon_id"><i class="bi bi-ticket-perforated me-1"></i>แนะนำคูปองโปรโมชั่น (แทรกด้านล่างข้อความ)</label>
                                <select name="coupon_id" id="coupon_id" class="form-select rounded-3 py-2" <?= !$smtp_configured ? 'disabled' : '' ?>>
                                    <option value="">-- ไม่แนบคูปองส่วนลด --</option>
                                    <?php foreach ($coupons as $c): ?>
                                        <?php
                                        $desc = ($c['discount_type'] === 'percentage') ? "ลด " . floatval($c['discount_value']) . "%" : "ลด ฿" . number_format($c['discount_value']);
                                        $min = ($c['min_spend'] > 0) ? " ขั้นต่ำ ฿" . number_format($c['min_spend']) : "";
                                        $exp = ($c['expiry_date']) ? " (หมดเขต " . date('d/m/Y', strtotime($c['expiry_date'])) . ")" : "";
                                        ?>
                                        <option value="<?= htmlspecialchars($c['id']) ?>" 
                                                data-code="<?= htmlspecialchars($c['code']) ?>"
                                                data-desc="<?= htmlspecialchars($desc . $min) ?>"
                                                data-exp="<?= htmlspecialchars($exp ? "หมดเขต " . date('d/m/Y', strtotime($c['expiry_date'])) : "ไม่มีวันหมดอายุ") ?>">
                                            <?= htmlspecialchars($c['code']) ?> - <?= htmlspecialchars($desc . $min . $exp) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Email Body Content -->
                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary" for="body_content">เนื้อหาอีเมล</label>
                                <textarea name="body_content" id="body_content" class="form-control rounded-3" rows="6" required placeholder="พิมพ์รายละเอียดจดหมายที่นี่..." <?= !$smtp_configured ? 'disabled' : '' ?>></textarea>
                            </div>

                            <!-- File Attachments Upload -->
                            <div class="mb-4">
                                <label class="form-label fw-bold text-secondary mb-2"><i class="bi bi-paperclip me-1"></i>แนบไฟล์เอกสารเพิ่มเติม (เลือกส่งได้หลายไฟล์พร้อมกัน)</label>
                                <div class="custom-file-upload mb-1" id="attachments_dropzone">
                                    <i class="bi bi-files"></i>
                                    <div class="fw-semibold text-secondary" id="attachments_filename">คลิกเพื่อเลือกไฟล์แนบเอกสารเพิ่มเติม</div>
                                    <div class="text-muted small mt-1">แนบได้หลายไฟล์พร้อมกัน (รวมไม่เกิน 15MB)</div>
                                    <input type="file" name="attachments[]" id="attachments" multiple <?= !$smtp_configured ? 'disabled' : '' ?>>
                                </div>
                            </div>

                            <!-- Submit Buttons -->
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <button type="submit" name="send_test" class="btn btn-outline-secondary w-100 rounded-pill py-2.5 shadow-sm fw-bold" <?= !$smtp_configured ? 'disabled' : '' ?>>
                                        <i class="bi bi-envelope-check-fill me-2"></i>ส่งทดสอบหาตัวเอง
                                    </button>
                                </div>
                                <div class="col-sm-6">
                                    <button type="submit" name="send_mail" class="btn btn-gradient w-100 rounded-pill py-2.5 shadow-sm fw-bold" <?= !$smtp_configured ? 'disabled' : '' ?>>
                                        <i class="bi bi-send-fill me-2"></i>ส่งอีเมลหาลูกค้าทันที
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Live Preview Block (Right) -->
                <div class="col-lg-6">
                    <div class="card card-modern border-0 overflow-hidden sticky-preview">
                        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                            <h5 class="fw-bold m-0"><i class="bi bi-eye text-primary me-2"></i>ตัวอย่างอีเมลจริง (Live Preview)</h5>
                            <p class="text-muted small">ตัวอย่างจดหมายแบนเนอร์และคูปองจำลองรูปหน้าจอจริง</p>
                        </div>
                        <div class="card-body p-4 bg-light d-flex flex-column align-items-center justify-content-center">
                            <!-- Browser Mockup Window -->
                            <div class="w-100 shadow rounded-4 overflow-hidden bg-white border" style="max-width: 480px;">
                                <div class="preview-window-header">
                                    <div class="preview-dots">
                                        <span class="preview-dot dot-red"></span>
                                        <span class="preview-dot dot-yellow"></span>
                                        <span class="preview-dot dot-green"></span>
                                    </div>
                                    <div class="preview-address-bar">
                                        <i class="bi bi-shield-lock-fill me-1 text-success"></i>https://mail.pormaebettaled.pp.ua/inbox
                                    </div>
                                </div>
                                <div style="padding: 20px; background-color: #f8fafc; max-height: 600px; overflow-y: auto;">
                                    <div class="email-preview-box shadow-sm rounded-4 overflow-hidden bg-white w-100" style="font-family: 'Kanit', sans-serif;">
                                        <!-- Mockup Mail Header -->
                                        <div style="background: linear-gradient(135deg, #AEE2FF 0%, #7FB5FF 100%); padding: 25px; text-align: center; color: white;">
                                            <h4 style="margin: 0; font-weight: 800; font-size: 1.25rem; letter-spacing: -0.5px;"><?= htmlspecialchars($shop['shop_name'] ?? 'Por Mae Bet Taled') ?></h4>
                                        </div>
                                        <!-- Mockup Live Banner Image -->
                                        <div id="preview_hero_image_div" class="d-none" style="text-align: center; border-bottom: 1px solid #e2e8f0; line-height: 0;">
                                            <img id="preview_hero_image" src="" style="max-width: 100%; height: auto; display: block;" alt="Banner Preview">
                                        </div>
                                        <!-- Mockup Mail Body -->
                                        <div style="padding: 25px; color: #1e293b; font-size: 0.9rem; line-height: 1.6;">
                                            <p style="margin-bottom: 12px; font-weight: 600;">สวัสดีคุณ <span id="preview_to_name">[ชื่อผู้รับ]</span>,</p>
                                            <div id="preview_body" style="white-space: pre-wrap; color: #475569; margin: 20px 0; min-height: 120px; font-size: 0.9rem;">ข้อความรายละเอียดอีเมลที่คุณกรอกจะแสดงจำลองตัวอย่างแบบสดๆ ณ บริเวณนี้...</div>
                                            
                                            <!-- Mockup Live Coupon Box (CTA) -->
                                            <div id="preview_coupon_div" class="d-none mt-4 p-4 text-center" style="border: 2px dashed #7FB5FF; background-color: #f0f7ff; border-radius: 16px;">
                                                <div style="font-size: 0.72rem; color: #7FB5FF; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">คูปองส่วนลดพิเศษสำหรับคุณ</div>
                                                <div id="preview_coupon_code" style="font-size: 1.7rem; color: #1d4ed8; font-weight: 800; letter-spacing: 2px; line-height: 1.2; margin-bottom: 6px;">COUPONCODE</div>
                                                <div id="preview_coupon_desc" style="font-size: 0.85rem; color: #475569; font-weight: 600; margin-bottom: 4px;">ส่วนลดพิเศษ 10% เมื่อช้อปขั้นต่ำ ฿500</div>
                                                <div id="preview_coupon_exp" style="font-size: 0.75rem; color: #94a3b8;">หมดเขต: 31/12/2026</div>
                                            </div>

                                            <!-- Mockup Live File Attachments List (Bottom of Body) -->
                                            <div id="preview_attachments_section" class="d-none mt-4 pt-3 border-top" style="border-top: 1px dashed #e2e8f0;">
                                                <div style="font-weight: 700; font-size: 0.8rem; color: #64748b; margin-bottom: 8px;">
                                                    <i class="bi bi-paperclip me-1"></i>เอกสารแนบ (<span id="preview_attachment_count">0</span>)
                                                </div>
                                                <div id="preview_attachment_list" class="d-flex flex-wrap gap-2">
                                                    <!-- Dynamically filled by JS -->
                                                </div>
                                            </div>

                                            <!-- Mockup Mail Footer -->
                                            <div style="text-align: center; font-size: 0.75rem; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 20px; margin-top: 25px;">
                                                <p style="margin: 0 0 4px 0;">เบอร์โทรติดต่อ: <?= htmlspecialchars($shop['phone'] ?? '-') ?> • อีเมล: <?= htmlspecialchars($shop['shop_email'] ?? '-') ?></p>
                                                <p style="margin: 0;">ขอแสดงความนับถือ, <?= htmlspecialchars($shop['shop_name'] ?? 'Por Mae Bet Taled') ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // ดึง Element ฟอร์มและตัวอย่าง Live Preview
    const toNameInput = document.getElementById('to_name');
    const bodyContentInput = document.getElementById('body_content');
    const previewToName = document.getElementById('preview_to_name');
    const previewBody = document.getElementById('preview_body');
    const memberSelect = document.getElementById('member_select');
    const toEmailInput = document.getElementById('to_email');

    // ตัวแปรและ Element สำหรับรูปภาพแบนเนอร์
    const promoImageInput = document.getElementById('promo_image');
    const previewHeroImageDiv = document.getElementById('preview_hero_image_div');
    const previewHeroImage = document.getElementById('preview_hero_image');

    // ตัวแปรและ Element สำหรับคูปองโปรโมชั่น
    const couponIdSelect = document.getElementById('coupon_id');
    const previewCouponDiv = document.getElementById('preview_coupon_div');
    const previewCouponCode = document.getElementById('preview_coupon_code');
    const previewCouponDesc = document.getElementById('preview_coupon_desc');
    const previewCouponExp = document.getElementById('preview_coupon_exp');

    // ตัวแปรและ Element สำหรับไฟล์แนบ
    const attachmentsInput = document.getElementById('attachments');
    const previewAttachmentsSection = document.getElementById('preview_attachments_section');
    const previewAttachmentCount = document.getElementById('preview_attachment_count');
    const previewAttachmentList = document.getElementById('preview_attachment_list');

    // ตัวแปรและ Element สำหรับระยะเวลาไม่เคลื่อนไหว
    const inactivityDaysSelect = document.getElementById('inactivity_days');

    // ฟังก์ชันความปลอดภัยแปลงอักษรพิเศษ (HTML Escape)
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // ฟังก์ชันอัปเดตข้อมูลตัวอย่างอีเมลสด
    function updatePreview() {
        if (toNameInput) {
            previewToName.innerText = toNameInput.value.trim() !== '' ? toNameInput.value.trim() : '[ชื่อผู้รับ]';
        }
        if (bodyContentInput) {
            const bodyVal = bodyContentInput.value.trim();
            previewBody.innerText = bodyVal !== '' ? bodyVal : 'ข้อความรายละเอียดอีเมลที่คุณกรอกจะแสดงจำลองตัวอย่างแบบสดๆ ณ บริเวณนี้...';
        }
    }

    let inactiveUsers = [];

    // ฟังก์ชันอัปเดตข้อมูลจำนวนคนเลือก inactive
    function updateInactiveRecipientDetails() {
        if (!inactivityDaysSelect) return;
        const days = inactivityDaysSelect.value;
        let selectedCount = 0;
        const listDiv = document.getElementById('inactive_users_list');
        if (listDiv) {
            const checkboxes = listDiv.querySelectorAll('.inactive-user-checkbox');
            selectedCount = Array.from(checkboxes).filter(chk => chk.checked).length;
        }
        toNameInput.value = `ลูกค้าที่ไม่ออนไลน์เกิน ${days} วัน (${selectedCount} คน)`;
        toEmailInput.value = `inactive_${days}_days@system`;
        updatePreview();
    }

    // ฟังก์ชันโหลดลูกค้าที่ไม่เคลื่อนไหวผ่าน AJAX
    function loadInactiveUsers() {
        if (!inactivityDaysSelect) return;
        const days = inactivityDaysSelect.value;
        const listDiv = document.getElementById('inactive_users_list');
        if (!listDiv) return;

        listDiv.innerHTML = `
            <div class="text-center py-4 text-muted" id="inactive_users_loading">
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>กำลังโหลดรายชื่อ...
            </div>
        `;

        fetch(`ajax.php?action=get_inactive_users&days=${days}`)
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP error! Status: ${res.status}`);
                }
                return res.text();
            })
            .then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("Failed to parse JSON. Response text was:", text);
                    throw new Error("รูปแบบข้อมูลที่ตอบกลับไม่ถูกต้อง (ไม่ใช่ JSON)");
                }
            })
            .then(data => {
                if (data.status === 'success') {
                    inactiveUsers = (data.users || []).map(u => ({ ...u, checked: true }));
                    renderInactiveUsers();
                } else {
                    listDiv.innerHTML = `<div class="text-danger p-3 text-center">เกิดข้อผิดพลาด: ${escapeHtml(data.message)}</div>`;
                }
            })
            .catch(err => {
                listDiv.innerHTML = `<div class="text-danger p-3 text-center">เกิดข้อผิดพลาด: ${escapeHtml(err.message)}</div>`;
                console.error("Fetch error:", err);
            });
    }

    // ฟังก์ชันวาดรายการลูกค้าเก่าพร้อมระบายสีแถวเมื่อ hover
    function renderInactiveUsers() {
        const listDiv = document.getElementById('inactive_users_list');
        const countSpan = document.getElementById('inactive_count');
        const searchInput = document.getElementById('search_inactive_user');
        if (!listDiv || !countSpan) return;

        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';

        // กรองด้วยคำค้นหา
        const filteredUsers = inactiveUsers.filter(u => {
            return u.fullname.toLowerCase().includes(query) || 
                   u.email.toLowerCase().includes(query) || 
                   u.username.toLowerCase().includes(query);
        });

        countSpan.innerText = inactiveUsers.length;

        if (filteredUsers.length === 0) {
            listDiv.innerHTML = `<div class="text-muted text-center py-4">ไม่พบรายชื่อลูกค้าที่ตรงตามเงื่อนไข</div>`;
            updateSelectionCount(filteredUsers);
            updateInactiveRecipientDetails();
            return;
        }

        let html = '';
        filteredUsers.forEach(u => {
            const firstLetter = u.fullname ? u.fullname.trim().charAt(0).toUpperCase() : 'U';
            html += `
                <div class="user-row-card cursor-pointer" onclick="toggleRowCheckbox(event, 'chk_user_${u.id}')">
                    <div class="form-check m-0 d-flex align-items-center w-100">
                        <input class="form-check-input inactive-user-checkbox me-3 cursor-pointer" type="checkbox" name="selected_inactive_emails[]" value="${escapeHtml(u.email)}" id="chk_user_${u.id}" ${u.checked ? 'checked' : ''} onclick="event.stopPropagation();">
                        <div class="user-avatar-circle">${escapeHtml(firstLetter)}</div>
                        <label class="form-check-label flex-grow-1 user-select-none cursor-pointer" for="chk_user_${u.id}" onclick="event.stopPropagation();">
                            <div class="fw-semibold text-dark" style="font-size: 0.9rem;">${escapeHtml(u.fullname)}</div>
                            <div class="text-muted small" style="font-size: 0.78rem;">${escapeHtml(u.email)} • ล็อกอินล่าสุด: ${escapeHtml(u.last_login)}</div>
                        </label>
                    </div>
                </div>
            `;
        });
        listDiv.innerHTML = html;

        // ผูก event listener ให้ checkbox ทุกอันใน DOM เพื่อเปลี่ยนค่าในโครงสร้างข้อมูล inactiveUsers
        filteredUsers.forEach(u => {
            const chk = listDiv.querySelector(`#chk_user_${u.id}`);
            if (chk) {
                chk.addEventListener('change', (e) => {
                    const targetUser = inactiveUsers.find(user => user.id == u.id);
                    if (targetUser) {
                        targetUser.checked = e.target.checked;
                    }
                    updateSelectionCount(filteredUsers);
                    updateInactiveRecipientDetails();
                });
            }
        });

        // ตั้งค่าปุ่มเลือกทั้งหมด/สลับการเลือก
        setupSelectionControls(filteredUsers);

        updateSelectionCount(filteredUsers);
        updateInactiveRecipientDetails();
    }

    // ฟังก์ชันกดเลือกแถวแล้วติ๊ก checkbox
    window.toggleRowCheckbox = function(event, checkboxId) {
        const checkbox = document.getElementById(checkboxId);
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            // dispatch change event manually เพื่อรัน callback ของเช็คบ็อกซ์
            checkbox.dispatchEvent(new Event('change'));
        }
    };

    // ฟังก์ชันจัดการปุ่มเลือกทั้งหมด/สลับ
    function setupSelectionControls(filteredUsers) {
        const checkAll = document.getElementById('check_all_inactive');
        if (checkAll) {
            const newCheckAll = checkAll.cloneNode(true);
            checkAll.parentNode.replaceChild(newCheckAll, checkAll);
            
            newCheckAll.addEventListener('change', (e) => {
                const isChecked = e.target.checked;
                filteredUsers.forEach(u => {
                    const targetUser = inactiveUsers.find(user => user.id == u.id);
                    if (targetUser) {
                        targetUser.checked = isChecked;
                    }
                });
                renderInactiveUsers();
            });
        }

        const btnToggle = document.getElementById('btn_toggle_selection');
        if (btnToggle) {
            const newBtnToggle = btnToggle.cloneNode(true);
            btnToggle.parentNode.replaceChild(newBtnToggle, btnToggle);
            
            newBtnToggle.addEventListener('click', () => {
                filteredUsers.forEach(u => {
                    const targetUser = inactiveUsers.find(user => user.id == u.id);
                    if (targetUser) {
                        targetUser.checked = !targetUser.checked;
                    }
                });
                renderInactiveUsers();
            });
        }
    }

    // ฟังก์ชันคำนวณจำนวนที่ถูกเลือกและอัปเดตสถานะ checkAll (รวมถึง indeterminate state)
    function updateSelectionCount(filteredUsers) {
        const selectedSpan = document.getElementById('selected_count');
        const checkAll = document.getElementById('check_all_inactive');
        if (!selectedSpan) return;

        const totalFiltered = filteredUsers.length;
        const checkedFiltered = filteredUsers.filter(u => u.checked).length;

        selectedSpan.innerText = `เลือกแล้ว ${checkedFiltered} คน`;

        if (checkAll) {
            if (totalFiltered === 0) {
                checkAll.checked = false;
                checkAll.indeterminate = false;
            } else if (checkedFiltered === totalFiltered) {
                checkAll.checked = true;
                checkAll.indeterminate = false;
            } else if (checkedFiltered === 0) {
                checkAll.checked = false;
                checkAll.indeterminate = false;
            } else {
                checkAll.checked = false;
                checkAll.indeterminate = true;
            }
        }
    }

    // ฟังก์ชันตอบสนองการสลับประเภทผู้รับ
    function updateRecipientFields() {
        const checkedRadio = document.querySelector('input[name="recipient_type"]:checked');
        if (!checkedRadio) return;
        
        const type = checkedRadio.value;
        const memberSelectDiv = document.getElementById('member_select_div');
        const inactivityPeriodDiv = document.getElementById('inactivity_period_div');
        const inactiveUsersSelectionDiv = document.getElementById('inactive_users_selection_div');

        // จัดการสถานะช่องกรอกและ Selector ต่างๆ
        if (type === 'member') {
            if (memberSelectDiv) memberSelectDiv.classList.remove('d-none');
            if (inactivityPeriodDiv) inactivityPeriodDiv.classList.add('d-none');
            if (inactiveUsersSelectionDiv) inactiveUsersSelectionDiv.classList.add('d-none');
            toNameInput.readOnly = true;
            toEmailInput.readOnly = true;
            onMemberSelect();
        } else if (type === 'all') {
            if (memberSelectDiv) memberSelectDiv.classList.add('d-none');
            if (inactivityPeriodDiv) inactivityPeriodDiv.classList.add('d-none');
            if (inactiveUsersSelectionDiv) inactiveUsersSelectionDiv.classList.add('d-none');
            toNameInput.readOnly = true;
            toEmailInput.readOnly = true;
            toNameInput.value = 'ลูกค้าทุกคนในระบบ';
            toEmailInput.value = 'all_users@system';
            if (memberSelect) memberSelect.value = '';
            updatePreview();
        } else if (type === 'inactive') {
            if (memberSelectDiv) memberSelectDiv.classList.add('d-none');
            if (inactivityPeriodDiv) inactivityPeriodDiv.classList.remove('d-none');
            if (inactiveUsersSelectionDiv) inactiveUsersSelectionDiv.classList.remove('d-none');
            toNameInput.readOnly = true;
            toEmailInput.readOnly = true;
            if (memberSelect) memberSelect.value = '';
            loadInactiveUsers();
        } else {
            if (memberSelectDiv) memberSelectDiv.classList.add('d-none');
            if (inactivityPeriodDiv) inactivityPeriodDiv.classList.add('d-none');
            if (inactiveUsersSelectionDiv) inactiveUsersSelectionDiv.classList.add('d-none');
            toNameInput.readOnly = false;
            toEmailInput.readOnly = false;
            toNameInput.value = '';
            toEmailInput.value = '';
            if (memberSelect) memberSelect.value = '';
            updatePreview();
        }
    }

    // ฟังก์ชันตอบสนองเมื่อสมาชิกใน dropdown ถูกเลือก
    function onMemberSelect() {
        if (!memberSelect) return;
        const selectedOption = memberSelect.options[memberSelect.selectedIndex];

        if (selectedOption && selectedOption.value !== '') {
            toEmailInput.value = selectedOption.value;
            toNameInput.value = selectedOption.getAttribute('data-name') || '';
        } else {
            toEmailInput.value = '';
            toNameInput.value = '';
        }
        updatePreview();
    }

    // ฟังก์ชันอัปเดตพรีวิวกล่องคูปองสด
    function updateCouponPreview() {
        if (!couponIdSelect || !previewCouponDiv) return;
        const selectedOption = couponIdSelect.options[couponIdSelect.selectedIndex];
        
        if (selectedOption && selectedOption.value !== '') {
            previewCouponCode.innerText = selectedOption.getAttribute('data-code') || '';
            previewCouponDesc.innerText = selectedOption.getAttribute('data-desc') || '';
            previewCouponExp.innerText = selectedOption.getAttribute('data-exp') || '';
            previewCouponDiv.classList.remove('d-none');
        } else {
            previewCouponDiv.classList.add('d-none');
        }
    }

    // ฟังก์ชันอัปเดตรายชื่อไฟล์ใน Live Preview
    function updateAttachmentsPreview() {
        if (!attachmentsInput || !previewAttachmentList || !previewAttachmentsSection) return;
        
        const files = attachmentsInput.files;
        previewAttachmentList.innerHTML = '';
        
        if (files && files.length > 0) {
            previewAttachmentsSection.classList.remove('d-none');
            previewAttachmentCount.innerText = files.length;
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                
                const badge = document.createElement('span');
                badge.className = 'badge bg-light text-secondary border rounded-pill px-3 py-2 d-inline-flex align-items-center';
                badge.style.fontSize = '0.75rem';
                badge.innerHTML = `<i class="bi bi-file-earmark-text text-primary me-1"></i>${escapeHtml(file.name)} (${sizeMB} MB)`;
                previewAttachmentList.appendChild(badge);
            }
        } else {
            previewAttachmentsSection.classList.add('d-none');
        }
    }

    // ติดตั้ง Event Listeners
    document.addEventListener('DOMContentLoaded', function() {
        if (toNameInput && bodyContentInput) {
            toNameInput.addEventListener('input', updatePreview);
            bodyContentInput.addEventListener('input', updatePreview);
        }

        const radios = document.querySelectorAll('input[name="recipient_type"]');
        radios.forEach(radio => {
            radio.addEventListener('change', updateRecipientFields);
        });

        if (memberSelect) {
            memberSelect.addEventListener('change', onMemberSelect);
        }

        if (attachmentsInput) {
            attachmentsInput.addEventListener('change', function() {
                updateAttachmentsPreview();
                const files = this.files;
                const label = document.getElementById('attachments_filename');
                if (label) {
                    if (files && files.length > 0) {
                        label.innerText = files.length === 1 ? files[0].name : `เลือกแล้ว ${files.length} ไฟล์`;
                        label.classList.remove('text-secondary');
                        label.classList.add('text-primary');
                    } else {
                        label.innerText = 'คลิกเพื่อเลือกไฟล์แนบเอกสารเพิ่มเติม';
                        label.classList.remove('text-primary');
                        label.classList.add('text-secondary');
                    }
                }
            });
        }

        if (couponIdSelect) {
            couponIdSelect.addEventListener('change', updateCouponPreview);
        }

        if (inactivityDaysSelect) {
            inactivityDaysSelect.addEventListener('change', loadInactiveUsers);
        }

        const searchInput = document.getElementById('search_inactive_user');
        if (searchInput) {
            searchInput.addEventListener('input', renderInactiveUsers);
        }

        // ประมวลผลพรีวิวรูปภาพแบนเนอร์ด้วย FileReader
        if (promoImageInput) {
            promoImageInput.addEventListener('change', function() {
                const file = this.files[0];
                const label = document.getElementById('promo_image_filename');
                if (label) {
                    if (file) {
                        label.innerText = file.name;
                        label.classList.remove('text-secondary');
                        label.classList.add('text-primary');
                    } else {
                        label.innerText = 'คลิกหรือลากไฟล์ภาพแบนเนอร์มาที่นี่';
                        label.classList.remove('text-primary');
                        label.classList.add('text-secondary');
                    }
                }
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        if (previewHeroImage) {
                            previewHeroImage.src = e.target.result;
                        }
                        if (previewHeroImageDiv) {
                            previewHeroImageDiv.classList.remove('d-none');
                        }
                    }
                    reader.readAsDataURL(file);
                } else {
                    if (previewHeroImage) previewHeroImage.src = '';
                    if (previewHeroImageDiv) previewHeroImageDiv.classList.add('d-none');
                }
            });
        }

        updateRecipientFields();
        updateAttachmentsPreview();
        updateCouponPreview();

        // ดักจับฟอร์ม submit เพื่อแสดง spinner และป้องกันการคลิกเบิ้ล
        const composerForm = document.getElementById('email-composer-form');
        const submitTypeInput = document.getElementById('submit_type');
        const btnMail = document.querySelector('button[name="send_mail"]');
        const btnTest = document.querySelector('button[name="send_test"]');

        if (btnMail && submitTypeInput) {
            btnMail.addEventListener('click', function() {
                submitTypeInput.value = 'send_mail';
            });
        }
        if (btnTest && submitTypeInput) {
            btnTest.addEventListener('click', function() {
                submitTypeInput.value = 'send_test';
            });
        }

        if (composerForm) {
            composerForm.addEventListener('submit', function(e) {
                let action = submitTypeInput.value;
                if (!action) {
                    if (e.submitter) {
                        action = e.submitter.name;
                    } else {
                        action = 'send_test';
                    }
                    submitTypeInput.value = action;
                }

                // ดึงปุ่มทั้งหมดมา disabled เพื่อป้องกันการส่งซ้ำซ้อน
                if (btnMail) {
                    btnMail.disabled = true;
                    if (action === 'send_mail') {
                        btnMail.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>กำลังส่งข้อมูล...';
                    }
                }
                if (btnTest) {
                    btnTest.disabled = true;
                    if (action === 'send_test') {
                        btnTest.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>กำลังส่งข้อมูล...';
                    }
                }
            });
        }
    });
</script>

<!-- SweetAlert2 Success & Error Notifications -->
<?php if(isset($_SESSION['swal'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: '<?= htmlspecialchars($_SESSION['swal']['title'], ENT_QUOTES, 'UTF-8') ?>',
            text: '<?= htmlspecialchars($_SESSION['swal']['text'], ENT_QUOTES, 'UTF-8') ?>',
            icon: '<?= htmlspecialchars($_SESSION['swal']['icon'], ENT_QUOTES, 'UTF-8') ?>',
            confirmButtonColor: '#7FB5FF'
        });
    });
</script>
<?php unset($_SESSION['swal']); endif; ?>

</body>
</html>
