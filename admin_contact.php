<?php
session_start();
include 'db.php';

// ระบบความปลอดภัย
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') { 
    header("Location: login.php"); 
    exit(); 
}

// --- Logic จัดการข้อมูล ---
function get_unread_count($conn) {
    return intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM contact_messages WHERE status='unread'"))['c'] ?? 0);
}

// การตอบกลับข้อความติดต่อทางอีเมล (SMTP)
if (isset($_POST['send_reply'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
    
    $msg_id = intval($_POST['msg_id']);
    $reply_text = mysqli_real_escape_string($conn, $_POST['reply_text']);
    $admin_name = $_SESSION['fullname'] ?? 'Admin';
    
    // ดึงข้อมูลอีเมลผู้ติดต่อ
    $q = mysqli_query($conn, "SELECT name, email, subject FROM contact_messages WHERE id = $msg_id");
    if ($q && mysqli_num_rows($q) > 0) {
        $msg = mysqli_fetch_assoc($q);
        
        // โหลดฟังก์ชันส่งเมล
        include_once 'mail_sender.php';
        $send_res = send_custom_reply($conn, $msg['email'], $msg['name'], $msg['subject'], $_POST['reply_text']);
        
        if ($send_res === true) {
            $now = date('Y-m-d H:i:s');
            mysqli_query($conn, "UPDATE contact_messages SET 
                                 status = 'read', 
                                 reply_message = '$reply_text', 
                                 replied_at = '$now', 
                                 replied_by = '$admin_name' 
                                 WHERE id = $msg_id");
            
            log_admin_action($conn, 'ตอบกลับข้อความติดต่อ', [
                'ข้อความติดต่อ ID' => $msg_id,
                'ผู้รับ' => $msg['name'] . " (" . $msg['email'] . ")",
                'หัวข้อ' => $msg['subject'],
                'ข้อความตอบกลับ' => $_POST['reply_text']
            ]);
            
            $_SESSION['swal'] = ['title' => 'สำเร็จ', 'text' => 'ส่งอีเมลตอบกลับลูกค้าเรียบร้อยแล้ว!', 'icon' => 'success'];
        } else {
            $_SESSION['swal'] = ['title' => 'ผิดพลาด', 'text' => 'ล้มเหลวในการส่งอีเมล: ' . $send_res, 'icon' => 'error'];
        }
    } else {
        $_SESSION['swal'] = ['title' => 'ผิดพลาด', 'text' => 'ไม่พบข้อมูลข้อความติดต่อ', 'icon' => 'error'];
    }
    header("Location: admin_contact.php");
    exit();
}

if (isset($_POST['save_shop_contact'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
    
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['shop_email'] ?? '');
    $address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
    $fb = mysqli_real_escape_string($conn, $_POST['facebook_url'] ?? '#');
    $line = mysqli_real_escape_string($conn, $_POST['line_url'] ?? '#');
    $ig = mysqli_real_escape_string($conn, $_POST['instagram_url'] ?? '#');
    
    $sql = "UPDATE shop_settings SET 
            phone = '$phone', 
            shop_email = '$email', 
            address = '$address', 
            facebook_url = '$fb', 
            line_url = '$line', 
            instagram_url = '$ig' 
            WHERE id = 1";
            
    if (mysqli_query($conn, $sql)) {
        log_admin_action($conn, 'แก้ไขข้อมูลติดต่อร้านค้า', [
            'เบอร์โทรศัพท์' => $phone,
            'อีเมลร้านค้า' => $email,
            'ที่อยู่ร้านค้า' => $address,
            'Facebook' => $fb,
            'Line' => $line,
            'Instagram' => $ig
        ]);
        $_SESSION['swal'] = ['title' => 'สำเร็จ', 'text' => 'บันทึกข้อมูลติดต่อร้านค้าเรียบร้อยแล้ว!', 'icon' => 'success'];
    } else {
        error_log("[admin_contact.php] save_shop_contact error: " . mysqli_error($conn));
        $_SESSION['swal'] = ['title' => 'ผิดพลาด', 'text' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล', 'icon' => 'error'];
    }
    header("Location: admin_contact.php");
    exit();
}

// --- AJAX GET operations (delete/read) ---
if (isset($_GET['delete_id'])) {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้องหรือหมดเวลาเซสชัน (Invalid CSRF Token)']);
            exit();
        }
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
    $did = mysqli_real_escape_string($conn, $_GET['delete_id']);
    
    $msg_q = mysqli_query($conn, "SELECT name, subject FROM contact_messages WHERE id = '$did'");
    $msg_info = mysqli_fetch_assoc($msg_q);
    $msg_name = $msg_info['name'] ?? 'ไม่ทราบชื่อ';
    $msg_subj = $msg_info['subject'] ?? 'ไม่มีหัวข้อ';
    
    mysqli_query($conn, "DELETE FROM contact_messages WHERE id = '$did'");
    log_admin_action($conn, 'ลบข้อความติดต่อ', "ลบข้อความติดต่อลูกค้า ID #$did (ผู้ส่ง: $msg_name, หัวข้อ: $msg_subj) สำเร็จ");
    
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success', 
            'message' => 'ลบข้อความเรียบร้อยแล้ว',
            'stats' => [
                'unread' => get_unread_count($conn),
                'total' => intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM contact_messages"))['c'] ?? 0),
                'replied' => intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM contact_messages WHERE reply_message IS NOT NULL"))['c'] ?? 0),
                'pending' => intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM contact_messages WHERE reply_message IS NULL"))['c'] ?? 0)
            ]
        ]);
        exit();
    }
    header("Location: admin_contact.php"); exit();
}

if (isset($_GET['read_id'])) {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้องหรือหมดเวลาเซสชัน (Invalid CSRF Token)']);
            exit();
        }
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
    $rid = mysqli_real_escape_string($conn, $_GET['read_id']);
    $new_status = ($_GET['status'] == 'read') ? 'read' : 'unread';
    
    $msg_q = mysqli_query($conn, "SELECT name, subject FROM contact_messages WHERE id = '$rid'");
    $msg_info = mysqli_fetch_assoc($msg_q);
    $msg_name = $msg_info['name'] ?? 'ไม่ทราบชื่อ';
    $msg_subj = $msg_info['subject'] ?? 'ไม่มีหัวข้อ';
    
    mysqli_query($conn, "UPDATE contact_messages SET status = '$new_status' WHERE id = '$rid'");
    $status_thai = $new_status == 'read' ? 'อ่านแล้ว' : 'ยังไม่ได้อ่าน';
    log_admin_action($conn, 'แก้ไขสถานะข้อความติดต่อ', "แก้ไขสถานะข้อความติดต่อลูกค้า ID #$rid (ผู้ส่ง: $msg_name, หัวข้อ: $msg_subj) เป็น '$status_thai' สำเร็จ");
    
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success', 
            'message' => $new_status == 'read' ? 'ทำเครื่องหมายว่าอ่านแล้วเรียบร้อย' : 'ทำเครื่องหมายว่ายังไม่ได้อ่านเรียบร้อย',
            'new_status' => $new_status,
            'stats' => [
                'unread' => get_unread_count($conn),
                'total' => intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM contact_messages"))['c'] ?? 0),
                'replied' => intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM contact_messages WHERE reply_message IS NOT NULL"))['c'] ?? 0),
                'pending' => intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM contact_messages WHERE reply_message IS NULL"))['c'] ?? 0)
            ]
        ]);
        exit();
    }
    header("Location: admin_contact.php"); exit();
}

// --- Batch Actions Handler ---
if (isset($_POST['batch_action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้องหรือหมดเวลาเซสชัน (Invalid CSRF Token)']);
        exit();
    }
    
    $action = $_POST['batch_action'];
    $ids = $_POST['ids'] ?? [];
    if (empty($ids) || !is_array($ids)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'กรุณาเลือกรายการที่ต้องการดำเนินการ']);
        exit();
    }
    
    $clean_ids = array_map('intval', $ids);
    $ids_str = implode(',', $clean_ids);
    
    if ($action === 'read') {
        mysqli_query($conn, "UPDATE contact_messages SET status = 'read' WHERE id IN ($ids_str)");
        log_admin_action($conn, 'ทำเครื่องหมายว่าอ่านแล้วแบบกลุ่ม', "ทำเครื่องหมายข้อความ ID ($ids_str) ว่าอ่านแล้ว");
        $msg = 'ทำเครื่องหมายว่าอ่านแล้วเรียบร้อย';
    } elseif ($action === 'unread') {
        mysqli_query($conn, "UPDATE contact_messages SET status = 'unread' WHERE id IN ($ids_str)");
        log_admin_action($conn, 'ทำเครื่องหมายว่ายังไม่ได้อ่านแบบกลุ่ม', "ทำเครื่องหมายข้อความ ID ($ids_str) ว่ายังไม่ได้อ่าน");
        $msg = 'ทำเครื่องหมายว่ายังไม่ได้อ่านเรียบร้อย';
    } elseif ($action === 'delete') {
        mysqli_query($conn, "DELETE FROM contact_messages WHERE id IN ($ids_str)");
        log_admin_action($conn, 'ลบข้อความติดต่อแบบกลุ่ม', "ลบข้อความติดต่อลูกค้า ID ($ids_str) สำเร็จ");
        $msg = 'ลบข้อความทั้งหมดที่เลือกเรียบร้อยแล้ว';
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'การทำงานไม่ถูกต้อง']);
        exit();
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => $msg,
        'stats' => [
            'unread' => get_unread_count($conn),
            'total' => intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM contact_messages"))['c'] ?? 0),
            'replied' => intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM contact_messages WHERE reply_message IS NOT NULL"))['c'] ?? 0),
            'pending' => intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM contact_messages WHERE reply_message IS NULL"))['c'] ?? 0)
        ]
    ]);
    exit();
}

$shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id = 1"));

// --- ระบบกรองและค้นหาข้อมูล ---
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'all';

$where_clause = "1=1";
if (!empty($search)) {
    $where_clause .= " AND (name LIKE '%$search%' OR email LIKE '%$search%' OR subject LIKE '%$search%' OR message LIKE '%$search%')";
}
if ($filter_status === 'unread') {
    $where_clause .= " AND status = 'unread'";
} elseif ($filter_status === 'read') {
    $where_clause .= " AND status = 'read' AND reply_message IS NULL";
} elseif ($filter_status === 'replied') {
    $where_clause .= " AND reply_message IS NOT NULL";
} elseif ($filter_status === 'unreplied') {
    $where_clause .= " AND reply_message IS NULL";
}

// คำนวณสถิติ
$total_messages = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM contact_messages"))['c'] ?? 0);
$unread_messages = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM contact_messages WHERE status='unread'"))['c'] ?? 0);
$replied_messages = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM contact_messages WHERE reply_message IS NOT NULL"))['c'] ?? 0);
$pending_messages = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM contact_messages WHERE reply_message IS NULL"))['c'] ?? 0);

// ตรวจสอบการตั้งค่า SMTP
$smtp_user = getenv('SMTP_USER') ?: ($shop['smtp_user'] ?? '');
$smtp_host = getenv('SMTP_HOST') ?: ($shop['smtp_host'] ?? '');
$smtp_configured = (!empty($smtp_user) && !empty($smtp_host)) ? 1 : 0;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อความติดต่อ | Por Mae Bet Taled Admin</title>
    <link class="favicon" rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --blue-primary: #AEE2FF;
            --blue-hover: #7FB5FF;
            --blue-soft: #F0F8FF;
            --bg-admin: #f4f7f6;
        }

        body { 
            background-color: var(--bg-admin); 
            font-family: 'Kanit', sans-serif; 
        }

        .admin-main-content { padding: 30px 40px; width: 100%; }

        .content-card {
            background: white; border-radius: 20px; padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.02);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .btn-pastel-blue {
            background-color: var(--blue-primary);
            border: none;
            color: #444;
            transition: all 0.3s ease;
        }
        .btn-pastel-blue:hover {
            background-color: var(--blue-hover);
            color: white;
        }

        .custom-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        .custom-table tr { background: white; transition: 0.3s; }
        .custom-table td { padding: 20px; border-top: 1px solid #f1f1f1; border-bottom: 1px solid #f1f1f1; vertical-align: middle; }
        .custom-table td:first-child { border-left: 1px solid #f1f1f1; border-top-left-radius: 15px; border-bottom-left-radius: 15px; }
        .custom-table td:last-child { border-right: 1px solid #f1f1f1; border-top-right-radius: 15px; border-bottom-right-radius: 15px; }
        .custom-table tr.unread-message td:first-child { border-left: 4px solid var(--blue-hover) !important; }

        .badge-status { font-size: 0.7rem; padding: 4px 12px; border-radius: 50px; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; }
        .bg-unread { background-color: #ffe5eb; color: #ff4d6d; }
        .bg-read { background-color: #f1f3f5; color: #868e96; }

        .btn-action { width: 35px; height: 35px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; background: #f8f9fa; color: #888; border: none; transition: 0.2s; }
        .btn-action:hover { background: var(--blue-primary); color: white; transform: translateY(-2px); }
            
        .stats-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.01);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(127, 181, 255, 0.1);
        }

        /* Mobile Layout */
        @media (max-width: 767.98px) {
            .admin-main-content { padding: 20px 10px; }
            .content-card { padding: 20px 15px; }
            .card-modern-mobile {
                background: #ffffff !important;
                border: 1px solid rgba(226, 232, 240, 0.8) !important;
                border-radius: 20px !important;
                box-shadow: 0 10px 30px rgba(127, 181, 255, 0.05) !important;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                position: relative !important;
                overflow: hidden !important;
            }
            .card-modern-mobile:hover, .card-modern-mobile:active {
                transform: translateY(-3px) scale(1.01);
                box-shadow: 0 15px 35px rgba(127, 181, 255, 0.12) !important;
                border-color: rgba(127, 181, 255, 0.3) !important;
            }
            .card-modern-mobile .btn {
                border-radius: 12px !important;
                font-weight: 500;
                padding: 6px 12px;
                font-size: 0.78rem;
            }
            .card-modern-mobile .btn-light {
                background: #f8fafc !important;
                border: 1px solid #e2e8f0 !important;
                color: #475569 !important;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Column -->
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

        <!-- Main Content Column -->
        <div class="col-md-10 p-4 p-md-5">
            
            <!-- 1. ส่วนหัวข้อและสถิติการติดต่อ -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">📬 กล่องข้อความติดต่อ</h2>
                    <p class="text-muted mb-0">ระบบจัดการข้อความลูกค้าและช่องทางติดต่อร้านค้า</p>
                </div>
            </div>

            <!-- Stats summary cards -->
            <div class="row g-3 mb-4 animate__animated animate__fadeInDown">
                <div class="col-6 col-lg-3">
                    <div class="card stats-card p-3 bg-white h-100">
                        <div class="d-flex align-items-center">
                            <div class="p-3 rounded-circle bg-light text-primary me-3 d-none d-sm-block">
                                <i class="bi bi-chat-left-text fs-4"></i>
                            </div>
                            <div>
                                <h6 class="text-muted small mb-1">ข้อความทั้งหมด</h6>
                                <h3 class="fw-bold mb-0 text-dark" id="total-count"><?= $total_messages ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card stats-card p-3 bg-white h-100">
                        <div class="d-flex align-items-center">
                            <div class="p-3 rounded-circle bg-danger-subtle text-danger me-3 d-none d-sm-block">
                                <i class="bi bi-envelope-exclamation fs-4"></i>
                            </div>
                            <div>
                                <h6 class="text-muted small mb-1">ยังไม่ได้อ่าน</h6>
                                <h3 class="fw-bold mb-0 text-danger" id="unread-count"><?= $unread_messages ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card stats-card p-3 bg-white h-100">
                        <div class="d-flex align-items-center">
                            <div class="p-3 rounded-circle bg-success-subtle text-success me-3 d-none d-sm-block">
                                <i class="bi bi-reply-all fs-4"></i>
                            </div>
                            <div>
                                <h6 class="text-muted small mb-1">ตอบกลับแล้ว</h6>
                                <h3 class="fw-bold mb-0 text-success" id="replied-count"><?= $replied_messages ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card stats-card p-3 bg-white h-100">
                        <div class="d-flex align-items-center">
                            <div class="p-3 rounded-circle bg-warning-subtle text-warning me-3 d-none d-sm-block">
                                <i class="bi bi-hourglass-split fs-4"></i>
                            </div>
                            <div>
                                <h6 class="text-muted small mb-1">รอดำเนินการตอบ</h6>
                                <h3 class="fw-bold mb-0 text-warning" id="pending-count"><?= $pending_messages ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Left Column: incoming contact messages with filtering -->
                <div class="col-lg-8 col-12">
                    <div class="content-card animate__animated animate__fadeIn">
                        
                        <!-- Search & Filter Header -->
                        <form method="GET" class="row g-2 mb-4 align-items-center">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span>
                                    <input type="text" name="search" class="form-control bg-light border-0" value="<?= htmlspecialchars(stripcslashes($search)) ?>" placeholder="ค้นหาชื่อ, อีเมล, หัวข้อ...">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select name="status" class="form-select bg-light border-0" onchange="this.form.submit()">
                                    <option value="all" <?= $filter_status == 'all' ? 'selected' : '' ?>>แสดงทุกสถานะ</option>
                                    <option value="unread" <?= $filter_status == 'unread' ? 'selected' : '' ?>>ยังไม่ได้อ่าน</option>
                                    <option value="read" <?= $filter_status == 'read' ? 'selected' : '' ?>>อ่านแล้ว (ยังไม่ตอบ)</option>
                                    <option value="replied" <?= $filter_status == 'replied' ? 'selected' : '' ?>>ตอบกลับแล้ว</option>
                                    <option value="unreplied" <?= $filter_status == 'unreplied' ? 'selected' : '' ?>>ยังไม่ได้ตอบกลับ</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-pastel-blue w-100 rounded-3"><i class="bi bi-funnel"></i> กรอง</button>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="custom-table">
                                <thead class="d-none d-md-table-header-group">
                                    <tr class="text-muted small uppercase">
                                        <th class="ps-4" style="width: 45px; text-align: center;">
                                            <input type="checkbox" class="form-check-input select-all-checkbox" onclick="toggleSelectAll(this)">
                                        </th>
                                        <th>ผู้ติดต่อ</th>
                                        <th>ข้อความ / หัวข้อ</th>
                                        <th class="text-center">วันที่</th>
                                        <th class="text-center">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $res = mysqli_query($conn, "SELECT * FROM contact_messages WHERE $where_clause ORDER BY id DESC");
                                    if (mysqli_num_rows($res) > 0):
                                        while($row = mysqli_fetch_assoc($res)):
                                            $is_new = ($row['status'] == 'unread');
                                            $has_replied = !empty($row['reply_message']);
                                    ?>
                                    <!-- Desktop Row -->
                                    <tr id="message-row-<?= $row['id'] ?>" class="d-none d-md-table-row <?= $is_new ? 'unread-message' : '' ?>">
                                        <td style="text-align: center; vertical-align: middle;" class="ps-4">
                                            <input type="checkbox" class="form-check-input message-checkbox" value="<?= $row['id'] ?>" onclick="onMessageSelectChange()">
                                        </td>
                                        <td>
                                            <div class="mb-1">
                                                <span class="status-badge badge-status <?= $is_new ? 'bg-unread' : 'bg-read' ?>">
                                                    <?= $is_new ? 'ยังไม่อ่าน' : 'อ่านแล้ว' ?>
                                                </span>
                                                <?php if($has_replied): ?>
                                                    <span class="badge bg-success-subtle text-success rounded-pill" style="font-size: 0.65rem; padding: 4px 8px;"><i class="bi bi-reply-fill"></i> ตอบแล้ว</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($row['name']) ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($row['email']) ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-blue small"><?= htmlspecialchars($row['subject']) ?></div>
                                            <div class="text-muted small text-truncate" style="max-width: 200px;">
                                                <?= htmlspecialchars($row['message']) ?>
                                            </div>
                                        </td>
                                        <td class="text-center text-muted small">
                                            <?= date('d/m/Y H:i', strtotime($row['created_at'])) ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <button onclick="viewDetails(<?= htmlspecialchars(json_encode($row)) ?>)" class="btn-action" title="ดูข้อความอย่างละเอียด">
                                                    <i class="bi bi-eye-fill text-dark"></i>
                                                </button>
                                                
                                                <?php if(!$has_replied): ?>
                                                    <button onclick="openReplyModal(<?= htmlspecialchars(json_encode($row)) ?>)" class="btn-action text-primary" title="เขียนข้อความตอบกลับเมล">
                                                        <i class="bi bi-reply-fill text-dark"></i>
                                                    </button>
                                                <?php endif; ?>

                                                <button onclick="toggleRead(this, <?= $row['id'] ?>, '<?= get_csrf_token() ?>')" class="btn-action" title="<?= $is_new ? 'ทำเครื่องหมายว่าอ่านแล้ว' : 'ทำเครื่องหมายว่ายังไม่อ่าน' ?>">
                                                    <i class="bi <?= $is_new ? 'bi-envelope' : 'bi-envelope-open' ?>"></i>
                                                </button>
                                                
                                                <button onclick="confirmDelete(<?= $row['id'] ?>, '<?= get_csrf_token() ?>')" class="btn-action text-danger" title="ลบข้อความ">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Mobile Row -->
                                    <tr id="message-mob-row-<?= $row['id'] ?>" class="d-md-none">
                                        <td colspan="5" class="p-0 border-0">
                                            <div class="card-modern-mobile p-3 mb-3 text-start" style="<?= $is_new ? 'border-left: 5px solid var(--blue-hover) !important;' : '' ?>">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <input type="checkbox" class="form-check-input message-checkbox" value="<?= $row['id'] ?>" onclick="onMessageSelectChange()">
                                                        <div class="fw-bold text-dark" style="font-size: 0.95rem;"><?= htmlspecialchars($row['name']) ?></div>
                                                    </div>
                                                    <div>
                                                        <span class="status-badge badge-status <?= $is_new ? 'bg-unread' : 'bg-read' ?>">
                                                            <?= $is_new ? 'ยังไม่อ่าน' : 'อ่านแล้ว' ?>
                                                        </span>
                                                        <?php if($has_replied): ?>
                                                            <span class="badge bg-success-subtle text-success rounded-pill ms-1" style="font-size: 0.65rem; padding: 4px 8px;"><i class="bi bi-reply-fill"></i> ตอบแล้ว</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="text-muted small mb-2"><?= htmlspecialchars($row['email']) ?></div>
                                                <div class="mb-2">
                                                    <div class="fw-bold text-blue small"><?= htmlspecialchars($row['subject']) ?></div>
                                                    <div class="text-muted small" style="white-space: pre-line; word-break: break-word;">
                                                        <?= htmlspecialchars($row['message']) ?>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2">
                                                    <span class="small text-muted" style="font-size: 0.75rem;"><i class="bi bi-clock me-1"></i><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></span>
                                                    <div class="d-flex gap-1">
                                                        <button onclick="viewDetails(<?= htmlspecialchars(json_encode($row)) ?>)" class="btn-action" title="รายละเอียด">
                                                            <i class="bi bi-eye-fill text-dark"></i>
                                                        </button>
                                                        <?php if(!$has_replied): ?>
                                                            <button onclick="openReplyModal(<?= htmlspecialchars(json_encode($row)) ?>)" class="btn-action text-primary" title="ตอบกลับ">
                                                                <i class="bi bi-reply-fill text-dark"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button onclick="toggleRead(this, <?= $row['id'] ?>, '<?= get_csrf_token() ?>')" class="btn-action" title="สลับสถานะ">
                                                            <i class="bi <?= $is_new ? 'bi-envelope' : 'bi-envelope-open' ?>"></i>
                                                        </button>
                                                        <button onclick="confirmDelete(<?= $row['id'] ?>, '<?= get_csrf_token() ?>')" class="btn-action text-danger" title="ลบ">
                                                            <i class="bi bi-trash3"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox display-4 d-block mb-3 opacity-25"></i>
                                            ไม่พบข้อความติดต่อ
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Edit shop contact info -->
                <div class="col-lg-4 col-12">
                    <div class="content-card animate__animated animate__fadeIn h-100">
                        <h5 class="fw-bold mb-3"><i class="bi bi-info-circle-fill me-2 text-primary"></i>ข้อมูลติดต่อหลังบ้าน</h5>
                        <p class="text-muted small mb-4">ปรับแต่งข้อมูลติดต่อร้านค้าสำหรับแสดงในหน้า "ติดต่อเรา" ของฝั่งลูกค้า</p>
                        
                        <form method="POST" action="admin_contact.php">
                            <?= get_csrf_input() ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold small">เบอร์โทรศัพท์</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($shop['phone'] ?? '') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small">อีเมลร้านค้า</label>
                                <input type="email" name="shop_email" class="form-control" value="<?= htmlspecialchars($shop['shop_email'] ?? '') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small">ที่อยู่ร้านค้า</label>
                                <textarea name="address" class="form-control" rows="3" required><?= htmlspecialchars($shop['address'] ?? '') ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small"><i class="bi bi-facebook text-primary me-1"></i> Facebook Link</label>
                                <input type="text" name="facebook_url" class="form-control" value="<?= htmlspecialchars($shop['facebook_url'] ?? '#') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small"><i class="bi bi-line text-success me-1"></i> Line Link</label>
                                <input type="text" name="line_url" class="form-control" value="<?= htmlspecialchars($shop['line_url'] ?? '#') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small"><i class="bi bi-instagram text-danger me-1"></i> Instagram Link</label>
                                <input type="text" name="instagram_url" class="form-control" value="<?= htmlspecialchars($shop['instagram_url'] ?? '#') ?>">
                            </div>
                            <button type="submit" name="save_shop_contact" class="btn btn-dark w-100 rounded-pill py-2 mt-2">
                                <i class="bi bi-save me-2"></i> บันทึกข้อมูลติดต่อ
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FLOATING BATCH PANEL -->
<div id="batchActionPanel" class="position-fixed bottom-0 start-50 translate-middle-x mb-4 p-3 bg-dark text-white rounded-pill shadow-lg d-none animate__animated animate__slideInUp" style="z-index: 1050; min-width: 320px;">
    <div class="d-flex align-items-center justify-content-between gap-3 px-2">
        <span class="small fw-medium" id="selectedCountText">เลือกแล้ว 0 รายการ</span>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3" onclick="executeBatchAction('read')">
                <i class="bi bi-envelope-open me-1"></i> อ่านแล้ว
            </button>
            <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3" onclick="executeBatchAction('unread')">
                <i class="bi bi-envelope me-1"></i> ยังไม่อ่าน
            </button>
            <button type="button" class="btn btn-sm btn-danger rounded-pill px-3" onclick="executeBatchAction('delete')">
                <i class="bi bi-trash3 me-1"></i> ลบที่เลือก
            </button>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: รายละเอียดข้อความติดต่อลูกค้า -->
<!-- ======================================================== -->
<div class="modal fade" id="messageDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold modal-title">📝 รายละเอียดข้อความติดต่อ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="text-muted small d-block">ผู้ส่งติดต่อ:</label>
                    <strong class="text-dark" id="detail-name">-</strong> (<a href="#" id="detail-email-link" class="text-primary"><span id="detail-email">-</span></a>)
                </div>
                <div class="mb-3">
                    <label class="text-muted small d-block">หัวข้อเรื่อง:</label>
                    <strong class="text-dark" id="detail-subject">-</strong>
                </div>
                <div class="mb-3">
                    <label class="text-muted small d-block">ส่งเมื่อวันที่:</label>
                    <span class="text-muted small" id="detail-date">-</span>
                </div>
                <div class="mb-3 bg-light p-3 rounded-3">
                    <label class="text-muted small d-block mb-1">เนื้อหาข้อความ:</label>
                    <div class="text-dark small" style="white-space: pre-wrap; word-break: break-word;" id="detail-message">-</div>
                </div>
                
                <!-- ส่วนข้อความตอบกลับถ้ามีแล้ว -->
                <div id="detail-reply-section" class="mb-0 bg-success-subtle p-3 rounded-3 border border-success-subtle d-none">
                    <label class="text-success fw-bold small d-block mb-1"><i class="bi bi-reply-all-fill"></i> ข้อความที่ตอบกลับลูกค้าไปแล้ว:</label>
                    <div class="text-dark small mb-2" style="white-space: pre-wrap; word-break: break-word;" id="detail-reply-text">-</div>
                    <small class="text-muted d-block" style="font-size:0.75rem;" id="detail-reply-meta">-</small>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" id="detail-reply-btn" class="btn btn-pastel-blue rounded-pill px-4"><i class="bi bi-reply-fill"></i> เขียนเมลตอบกลับ</button>
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: ฟอร์มเขียนจดหมายตอบกลับผ่าน SMTP -->
<!-- ======================================================== -->
<div class="modal fade" id="replyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold modal-title">✉️ ส่งอีเมลตอบกลับลูกค้า</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="admin_contact.php">
                <?= get_csrf_input() ?>
                <input type="hidden" name="msg_id" id="reply-msg-id" value="">
                <div class="modal-body">
                    <!-- Warning message if SMTP not configured -->
                    <?php if (!$smtp_configured): ?>
                        <div class="alert alert-warning border-0 rounded-3 d-flex align-items-center gap-2 mb-3" style="font-size: 0.85rem;">
                            <i class="bi bi-exclamation-triangle-fill fs-5 text-warning flex-shrink-0"></i>
                            <div>
                                <b>ระบบอีเมล SMTP ยังไม่ได้ตั้งค่า:</b> คุณจะไม่สามารถส่งอีเมลตอบกลับลูกค้าได้จนกว่าจะไปตั้งค่าเซิร์ฟเวอร์ SMTP ในหน้า <a href="admin_settings.php" class="alert-link fw-bold text-decoration-underline">ตั้งค่าร้านค้า</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3 p-3 bg-light rounded-3" style="font-size: 0.85rem;">
                        <div><strong>ถึง:</strong> <span id="reply-to-name">-</span> (<span id="reply-to-email">-</span>)</div>
                        <div><strong>หัวข้อข้อความเดิม:</strong> <span id="reply-subject">-</span></div>
                        <hr class="my-2">
                        <div class="text-muted text-truncate" style="max-width: 100%;"><strong>ข้อความเดิม:</strong> <span id="reply-message">-</span></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">เทมเพลตข้อความตอบกลับด่วน</label>
                        <select class="form-select bg-light border-0" onchange="insertQuickReplyTemplate(this.value)">
                            <option value="">-- เลือกข้อความตอบกลับด่วน --</option>
                            <option value="welcome">ขอบคุณที่ติดต่อและแนะนำตัว</option>
                            <option value="delivery">ชี้แจงสถานะจัดส่งพัสดุกำลังดำเนินการ</option>
                            <option value="product_problem">แจ้งปัญหาสินค้าชำรุด/เปลี่ยนสินค้า</option>
                            <option value="general_help">สอบถามรายละเอียดเพิ่มเติม</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">เนื้อหาจดหมายตอบกลับ (ส่งไปยังอีเมลลูกค้าโดยตรง)</label>
                        <textarea name="reply_text" id="reply-text-input" class="form-control" rows="8" placeholder="เขียนข้อความตอบกลับลูกค้าที่นี่..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" name="send_reply" class="btn btn-success rounded-pill px-4" <?= !$smtp_configured ? 'disabled' : '' ?>><i class="bi bi-send-fill me-1"></i> ส่งเมลตอบกลับ</button>
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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

// อัปเดตข้อมูลสถิติบน UI แบบเรียลไทม์
function updateStatsUI(stats) {
    if (!stats) return;
    
    // อัปเดต badge บน sidebar
    const unreadBadge = document.getElementById('unread-badge');
    if (unreadBadge) {
        unreadBadge.innerText = 'ใหม่ ' + stats.unread;
        if (parseInt(stats.unread) > 0) {
            unreadBadge.classList.remove('d-none');
        } else {
            unreadBadge.classList.add('d-none');
        }
    }
    
    // อัปเดตการ์ดสถิติในหน้า contact
    const unreadCount = document.getElementById('unread-count');
    if (unreadCount) unreadCount.innerText = stats.unread;
    
    const totalCount = document.getElementById('total-count');
    if (totalCount) totalCount.innerText = stats.total;
    
    const repliedCount = document.getElementById('replied-count');
    if (repliedCount) repliedCount.innerText = stats.replied;
    
    const pendingCount = document.getElementById('pending-count');
    if (pendingCount) pendingCount.innerText = stats.pending;
}

// ฟังก์ชันเลือก/ยกเลิกข้อความทั้งหมด
function toggleSelectAll(selectAllCb) {
    const checkboxes = document.querySelectorAll('.message-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = selectAllCb.checked;
    });
    onMessageSelectChange();
}

// ฟังก์ชันเมื่อเปลี่ยนสถานะการเลือกข้อความ
function onMessageSelectChange() {
    const checkboxes = document.querySelectorAll('.message-checkbox');
    const selectedIds = [];
    checkboxes.forEach(cb => {
        if (cb.checked) {
            selectedIds.push(cb.value);
        }
    });
    
    const panel = document.getElementById('batchActionPanel');
    const countText = document.getElementById('selectedCountText');
    
    if (selectedIds.length > 0) {
        countText.innerText = `เลือกแล้ว ${selectedIds.length} รายการ`;
        panel.classList.remove('d-none');
    } else {
        panel.classList.add('d-none');
        const selectAllCb = document.querySelector('.select-all-checkbox');
        if (selectAllCb) selectAllCb.checked = false;
    }
}

// ฟังก์ชันประมวลผล Batch Action (read/unread/delete)
function executeBatchAction(action) {
    const checkboxes = document.querySelectorAll('.message-checkbox');
    const selectedIds = [];
    checkboxes.forEach(cb => {
        if (cb.checked) {
            selectedIds.push(cb.value);
        }
    });
    
    if (selectedIds.length === 0) return;
    
    const actionNames = {
        'read': 'ทำเครื่องหมายว่าอ่านแล้ว',
        'unread': 'ทำเครื่องหมายว่ายังไม่ได้อ่าน',
        'delete': 'ลบข้อมูล'
    };
    
    const confirmText = action === 'delete' 
        ? 'ข้อความที่เลือกทั้งหมดจะถูกลบออกอย่างถาวร ไม่สามารถย้อนกลับได้' 
        : `คุณต้องการ${actionNames[action]}สำหรับ ${selectedIds.length} รายการที่เลือกใช่หรือไม่?`;
        
    Swal.fire({
        title: action === 'delete' ? 'ยืนยันการลบแบบกลุ่ม?' : 'ยืนยันการดำเนินการ?',
        text: confirmText,
        icon: action === 'delete' ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: action === 'delete' ? '#ff4d6d' : '#7FB5FF',
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('batch_action', action);
            formData.append('csrf_token', '<?= get_csrf_token() ?>');
            selectedIds.forEach(id => {
                formData.append('ids[]', id);
            });
            
            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    Toast.fire({
                        icon: 'success',
                        title: data.message
                    });
                    
                    // ลบหรืออัปเดตแถวในตารางโดยตรงตาม action
                    selectedIds.forEach(id => {
                        const row = document.getElementById('message-row-' + id);
                        const mobRow = document.getElementById('message-mob-row-' + id);
                        
                        if (action === 'delete') {
                            [row, mobRow].forEach(r => {
                                if (r) {
                                    r.style.transition = 'all 0.3s ease';
                                    r.style.opacity = '0';
                                    r.style.transform = 'translateX(30px)';
                                    setTimeout(() => r.remove(), 300);
                                }
                            });
                        } else {
                            const isRead = (action === 'read');
                            if (row) {
                                row.classList.toggle('unread-message', !isRead);
                                const badge = row.querySelector('.status-badge');
                                if (badge) {
                                    badge.className = 'status-badge badge-status ' + (isRead ? 'bg-read' : 'bg-unread');
                                    badge.innerText = isRead ? 'อ่านแล้ว' : 'ยังไม่อ่าน';
                                }
                                // Update desktop icon and title
                                const readBtn = row.querySelector('.btn-action[title*="อ่าน"]');
                                if (readBtn) {
                                    const icon = readBtn.querySelector('i');
                                    if (icon) icon.className = 'bi ' + (isRead ? 'bi-envelope-open' : 'bi-envelope');
                                    readBtn.title = isRead ? 'ทำเครื่องหมายว่ายังไม่อ่าน' : 'ทำเครื่องหมายว่าอ่านแล้ว';
                                }
                                const cb = row.querySelector('.message-checkbox');
                                if (cb) cb.checked = false;
                            }
                            if (mobRow) {
                                const card = mobRow.querySelector('.card-modern-mobile');
                                if (card) {
                                    if (isRead) {
                                        card.style.setProperty('border-left', '', 'important');
                                    } else {
                                        card.style.setProperty('border-left', '5px solid var(--blue-hover)', 'important');
                                    }
                                }
                                const badge = mobRow.querySelector('.status-badge');
                                if (badge) {
                                    badge.className = 'status-badge badge-status ' + (isRead ? 'bg-read' : 'bg-unread');
                                    badge.innerText = isRead ? 'อ่านแล้ว' : 'ยังไม่อ่าน';
                                }
                                // Update mobile icon
                                const readBtn = mobRow.querySelector('.btn-action[title*="สลับ"]');
                                if (readBtn) {
                                    const icon = readBtn.querySelector('i');
                                    if (icon) icon.className = 'bi ' + (isRead ? 'bi-envelope-open' : 'bi-envelope');
                                }
                                const cb = mobRow.querySelector('.message-checkbox');
                                if (cb) cb.checked = false;
                            }
                        }
                    });
                    
                    // ปิดและรีเซ็ตพาเนลเลือกกลุ่ม
                    setTimeout(() => {
                        const panel = document.getElementById('batchActionPanel');
                        panel.classList.add('d-none');
                        const selectAllCb = document.querySelector('.select-all-checkbox');
                        if (selectAllCb) selectAllCb.checked = false;
                        
                        updateStatsUI(data.stats);
                    }, 310);
                } else {
                    Swal.fire('ข้อผิดพลาด', data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                Toast.fire({
                    icon: 'error',
                    title: 'การทำงานล้มเหลว'
                });
            });
        }
    });
}

// ฟังก์ชันเทมเพลตจดหมายตอบกลับด่วน
function insertQuickReplyTemplate(value) {
    const textarea = document.getElementById('reply-text-input');
    if (!textarea) return;
    
    const templates = {
        welcome: 'สวัสดีครับคุณ [ชื่อลูกค้า]\n\nทางทีมงานแอดมินได้รับข้อความของคุณเรียบร้อยแล้วครับ ขอบคุณสำหรับความสนใจในสินค้าและบริการของร้านเรานะครับ\n\n[ข้อความเพิ่มเติม]',
        delivery: 'สวัสดีครับคุณ [ชื่อลูกค้า]\n\nทางทีมงานตรวจสอบแล้วพบว่า ออเดอร์ของคุณกำลังอยู่ระหว่างการเตรียมจัดส่งโดยด่วนครับ คาดว่าจะได้รับรหัสติดตามพัสดุผ่านทางระบบภายใน 24 ชั่วโมงนี้ครับ ขออภัยในความล่าช้าด้วยครับ',
        product_problem: 'สวัสดีครับคุณ [ชื่อลูกค้า]\n\nทางทีมงานกราบขออภัยในความไม่สะดวกเป็นอย่างยิ่งครับ รบกวนคุณลูกค้าส่งรูปภาพหรือวิดีโอของสินค้าที่มีปัญหาเข้ามาเพิ่มเติม เพื่อที่แอดมินจะได้ทำการส่งเคลมตัวใหม่ให้ทันทีครับ',
        general_help: 'สวัสดีครับคุณ [ชื่อลูกค้า]\n\nรบกวนขอทราบข้อมูลเพิ่มเติม เช่น เลขที่คำสั่งซื้อ หรือช่องทางชำระเงิน เพื่อให้แอดมินตรวจสอบรายละเอียดให้ถูกต้องและรวดเร็วยิ่งขึ้นครับ ขอบคุณครับ'
    };
    
    if (templates[value]) {
        const customerName = document.getElementById('reply-to-name').innerText;
        let text = templates[value];
        if (customerName && customerName !== '-') {
            text = text.replace('[ชื่อลูกค้า]', customerName);
        }
        textarea.value = text;
    }
}

// ฟังก์ชันดูรายละเอียดข้อความ
function viewDetails(msg) {
    document.getElementById('detail-name').innerText = msg.name || '-';
    document.getElementById('detail-email').innerText = msg.email || '-';
    document.getElementById('detail-email-link').href = 'mailto:' + msg.email;
    document.getElementById('detail-subject').innerText = msg.subject || '-';
    document.getElementById('detail-message').innerText = msg.message || '-';
    document.getElementById('detail-date').innerText = new Date(msg.created_at).toLocaleString('th-TH') || '-';
    
    const replySection = document.getElementById('detail-reply-section');
    if (msg.reply_message) {
        replySection.classList.remove('d-none');
        document.getElementById('detail-reply-text').innerText = msg.reply_message;
        document.getElementById('detail-reply-meta').innerText = `ตอบกลับโดย: ${msg.replied_by} เมื่อ ${new Date(msg.replied_at).toLocaleString('th-TH')}`;
        document.getElementById('detail-reply-btn').classList.add('d-none');
    } else {
        replySection.classList.add('d-none');
        const replyBtn = document.getElementById('detail-reply-btn');
        replyBtn.classList.remove('d-none');
        replyBtn.onclick = function() {
            bootstrap.Modal.getInstance(document.getElementById('messageDetailModal')).hide();
            openReplyModal(msg);
        };
    }
    
    // ตั้งสถานะเป็นอ่านแล้วแบบเรียลไทม์เบื้องหลัง (ถ้ายังไม่ได้อ่าน)
    const row = document.getElementById('message-row-' + msg.id);
    if (row) {
        const isUnread = row.querySelector('.status-badge').classList.contains('bg-unread');
        if (isUnread) {
            const toggleBtn = row.querySelector('.btn-action[title*="อ่าน"]');
            if (toggleBtn) {
                toggleReadDirectly(toggleBtn, msg.id, '<?= get_csrf_token() ?>');
            }
        }
    }

    new bootstrap.Modal(document.getElementById('messageDetailModal')).show();
}

// ฟังก์ชันเปิดฟอร์มตอบกลับ
function openReplyModal(msg) {
    document.getElementById('reply-msg-id').value = msg.id;
    document.getElementById('reply-to-name').innerText = msg.name || '-';
    document.getElementById('reply-to-email').innerText = msg.email || '-';
    document.getElementById('reply-subject').innerText = msg.subject || '-';
    document.getElementById('reply-message').innerText = msg.message || '-';
    document.getElementById('reply-text-input').value = '';
    
    new bootstrap.Modal(document.getElementById('replyModal')).show();
}

// ฟังก์ชันสลับสถานะอ่านแบบทันที
function toggleReadDirectly(btn, id, token) {
    fetch(window.location.pathname + `?read_id=${id}&status=read&csrf_token=${token}&ajax=1`)
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            const row = document.getElementById('message-row-' + id);
            const mobRow = document.getElementById('message-mob-row-' + id);
            
            if (row) {
                row.classList.remove('unread-message');
                const badge = row.querySelector('.status-badge');
                if (badge) {
                    badge.className = 'status-badge badge-status bg-read';
                    badge.innerText = 'อ่านแล้ว';
                }
            }
            if (mobRow) {
                const card = mobRow.querySelector('.card-modern-mobile');
                if (card) card.style.setProperty('border-left', '', 'important');
                const badge = mobRow.querySelector('.status-badge');
                if (badge) {
                    badge.className = 'status-badge badge-status bg-read';
                    badge.innerText = 'อ่านแล้ว';
                }
            }
            
            const icon = btn.querySelector('i');
            if (icon) icon.className = 'bi bi-envelope-open';
            btn.title = 'ทำเครื่องหมายว่ายังไม่อ่าน';
            
            updateStatsUI(data.stats);
        }
    })
    .catch(err => console.error('Auto-read status check failed:', err));
}

function toggleRead(btn, id, token) {
    const isUnread = btn.querySelector('i').classList.contains('bi-envelope');
    const targetStatus = isUnread ? 'read' : 'unread';
    
    btn.disabled = true;
    fetch(window.location.pathname + `?read_id=${id}&status=${targetStatus}&csrf_token=${token}&ajax=1`)
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        if (data.status === 'success') {
            const row = document.getElementById('message-row-' + id);
            const mobRow = document.getElementById('message-mob-row-' + id);
            const icon = btn.querySelector('i');
            
            if (data.new_status === 'read') {
                if (icon) icon.className = 'bi bi-envelope-open';
                btn.title = 'ทำเครื่องหมายว่ายังไม่อ่าน';
                
                if (row) {
                    row.classList.remove('unread-message');
                    const badge = row.querySelector('.status-badge');
                    if (badge) {
                        badge.className = 'status-badge badge-status bg-read';
                        badge.innerText = 'อ่านแล้ว';
                    }
                }
                if (mobRow) {
                    const card = mobRow.querySelector('.card-modern-mobile');
                    if (card) card.style.setProperty('border-left', '', 'important');
                    const badge = mobRow.querySelector('.status-badge');
                    if (badge) {
                        badge.className = 'status-badge badge-status bg-read';
                        badge.innerText = 'อ่านแล้ว';
                    }
                }
            } else {
                if (icon) icon.className = 'bi bi-envelope';
                btn.title = 'ทำเครื่องหมายว่าอ่านแล้ว';
                
                if (row) {
                    row.classList.add('unread-message');
                    const badge = row.querySelector('.status-badge');
                    if (badge) {
                        badge.className = 'status-badge badge-status bg-unread';
                        badge.innerText = 'ยังไม่อ่าน';
                    }
                }
                if (mobRow) {
                    const card = mobRow.querySelector('.card-modern-mobile');
                    if (card) card.style.setProperty('border-left', '5px solid var(--blue-hover)', 'important');
                    const badge = mobRow.querySelector('.status-badge');
                    if (badge) {
                        badge.className = 'status-badge badge-status bg-unread';
                        badge.innerText = 'ยังไม่อ่าน';
                    }
                }
            }
            
            updateStatsUI(data.stats);
            
            Toast.fire({
                icon: 'success',
                title: data.message
            });
        } else {
            Swal.fire('ข้อผิดพลาด', data.message, 'error');
        }
    })
    .catch(err => {
        btn.disabled = false;
        console.error(err);
        Toast.fire({
            icon: 'error',
            title: 'การเชื่อมต่อล้มเหลว'
        });
    });
}

function confirmDelete(id, token) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: 'ข้อความนี้จะถูกลบออกอย่างถาวร',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ff4d6d',
        confirmButtonText: 'ลบข้อมูล',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) { 
            fetch(window.location.pathname + `?delete_id=${id}&csrf_token=${token}&ajax=1`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    Toast.fire({
                        icon: 'success',
                        title: data.message
                    });
                    const row = document.getElementById('message-row-' + id);
                    const mobRow = document.getElementById('message-mob-row-' + id);
                    
                    [row, mobRow].forEach(r => {
                        if (r) {
                            r.style.transition = 'all 0.3s ease';
                            r.style.opacity = '0';
                            r.style.transform = 'translateX(30px)';
                            setTimeout(() => { r.remove(); }, 300);
                        }
                    });
                    
                    setTimeout(() => {
                        updateStatsUI(data.stats);
                    }, 310);
                } else {
                    Swal.fire('ข้อผิดพลาด', data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                Toast.fire({
                    icon: 'error',
                    title: 'การเชื่อมต่อล้มเหลว'
                });
            });
        }
    });
}
</script>

<?php if(isset($_SESSION['swal'])): ?>
<script>
    Swal.fire({
        icon: '<?= $_SESSION['swal']['icon'] ?>',
        title: '<?= $_SESSION['swal']['title'] ?>',
        text: '<?= $_SESSION['swal']['text'] ?>',
    });
</script>
<?php unset($_SESSION['swal']); endif; ?>

</body>
</html>
