<?php
session_start();
include 'db.php';

// 1. เช็คสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    header("Location: login.php"); 
    exit(); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้องหรือหมดเวลาเซสชัน (Invalid CSRF Token)']);
            exit();
        }
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
}

// 2. ล้างประวัติการทำงาน (ต้องกรอกรหัสผ่านยืนยัน)
if (isset($_POST['clear_logs'])) {
    $password = $_POST['admin_password'];
    $admin_id = $_SESSION['user_id'];
    
    $admin_q = mysqli_query($conn, "SELECT password FROM users WHERE id = '$admin_id'");
    $admin_user = mysqli_fetch_assoc($admin_q);
    
    if ($admin_user && password_verify($password, $admin_user['password'])) {
        mysqli_query($conn, "TRUNCATE TABLE admin_logs");
        log_admin_action($conn, 'ล้างประวัติการทำงาน', "ทำการล้างข้อมูลประวัติการทำงานแอดมินทั้งหมดในระบบ");
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'ล้างประวัติการทำงานทั้งหมดเรียบร้อยแล้ว']);
            exit();
        }
        $_SESSION['swal'] = ['title' => 'สำเร็จ', 'text' => 'ล้างประวัติการทำงานทั้งหมดเรียบร้อยแล้ว', 'icon' => 'success'];
    } else {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'รหัสผ่านไม่ถูกต้อง ไม่สามารถล้างประวัติได้!']);
            exit();
        }
        $_SESSION['swal'] = ['title' => 'ผิดพลาด', 'text' => 'รหัสผ่านไม่ถูกต้อง ไม่สามารถล้างประวัติได้!', 'icon' => 'error'];
    }
    header("Location: admin_logs.php");
    exit();
}

// 3. จัดการตัวกรองและการค้นหา
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$action_filter = isset($_GET['action_filter']) ? mysqli_real_escape_string($conn, $_GET['action_filter']) : '';
$role_filter = isset($_GET['role_filter']) ? mysqli_real_escape_string($conn, $_GET['role_filter']) : '';
$date_start = isset($_GET['date_start']) ? mysqli_real_escape_string($conn, $_GET['date_start']) : '';
$date_end = isset($_GET['date_end']) ? mysqli_real_escape_string($conn, $_GET['date_end']) : '';

// สร้างคำสั่ง SQL ค้นหา
$where_clauses = ["1=1"];

if ($search !== '') {
    $where_clauses[] = "(l.admin_name LIKE '%$search%' OR l.action_type LIKE '%$search%' OR l.details LIKE '%$search%' OR l.ip_address LIKE '%$search%')";
}
if ($action_filter !== '') {
    $where_clauses[] = "l.action_type = '$action_filter'";
}
if ($date_start !== '') {
    $where_clauses[] = "DATE(l.created_at) >= '$date_start'";
}
if ($date_end !== '') {
    $where_clauses[] = "DATE(l.created_at) <= '$date_end'";
}
if ($role_filter === 'admin') {
    $where_clauses[] = "u.role = 'admin'";
} elseif ($role_filter === 'user') {
    $where_clauses[] = "u.role = 'user'";
} elseif ($role_filter === 'system') {
    $where_clauses[] = "(l.admin_id IS NULL OR u.role IS NULL)";
}

$where_sql = implode(" AND ", $where_clauses);

// 4. ทำระบบแบ่งหน้า (Pagination)
$limit = 20; // แสดงหน้าละ 20 รายการ
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// นับจำนวนรายการทั้งหมดตามเงื่อนไข
$count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM admin_logs l LEFT JOIN users u ON l.admin_id = u.id WHERE $where_sql");
$total_rows = mysqli_fetch_assoc($count_query)['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);

// ดึงประวัติกิจกรรมแอดมินตามหน้าและเงื่อนไขกรอง
$sql = "SELECT l.*, u.role FROM admin_logs l LEFT JOIN users u ON l.admin_id = u.id WHERE $where_sql ORDER BY l.id DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $sql);
$rows = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
}

// ดึงประเภทกิจกรรมที่เป็นไปได้ทั้งหมดมาทำตัวเลือกกรอง (Dropdown)
$type_query = mysqli_query($conn, "SELECT DISTINCT action_type FROM admin_logs ORDER BY action_type ASC");
$action_types = [];
if ($type_query) {
    while ($t = mysqli_fetch_assoc($type_query)) {
        if (!empty($t['action_type'])) {
            $action_types[] = $t['action_type'];
        }
    }
}

// ฟังก์ชันเรนเดอร์รายละเอียดกิจกรรมแบบโครงสร้างข้อมูล (JSON) หรือข้อความทั่วไป
function render_log_details($details_json_or_text, $row_id) {
    $data = json_decode($details_json_or_text, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
        $html = '';
        if (isset($data['title'])) {
            $html .= '<div class="fw-semibold text-dark mb-0">' . htmlspecialchars($data['title']) . '</div>';
        }
        
        $has_changes = !empty($data['changes']);
        $has_sections = !empty($data['sections']);
        
        $summary_parts = [];
        if ($has_changes) {
            $changed_fields = [];
            foreach ($data['changes'] as $change) {
                if (!empty($change['field'])) {
                    $changed_fields[] = $change['field'];
                }
            }
            if (count($changed_fields) > 0) {
                $summary_parts[] = 'แก้ไข: ' . implode(', ', $changed_fields);
            }
        }
        if ($has_sections) {
            $section_titles = [];
            foreach ($data['sections'] as $sec) {
                if (!empty($sec['title'])) {
                    $st = $sec['title'];
                    // ยุบชื่อหัวข้อให้สั้นลงเพื่อความกะทัดรัด
                    if ($st === 'ความเปลี่ยนแปลงของล็อตย่อย') {
                        $st = 'ล็อตสินค้า';
                    } elseif ($st === 'คลังสินค้าเริ่มต้นที่บันทึก') {
                        $st = 'คลังเริ่มต้น';
                    } elseif ($st === 'ข้อมูลคูปองที่ถูกลบ' || $st === 'ข้อมูลสมาชิกที่ถูกลบ' || $st === 'รายละเอียดการดำเนินการ' || $st === 'รายละเอียดล็อตที่ลบ') {
                        $st = 'ลบข้อมูล';
                    }
                    $section_titles[] = $st;
                }
            }
            if (count($section_titles) > 0) {
                $summary_parts[] = implode(', ', $section_titles);
            }
        }
        
        if (count($summary_parts) > 0) {
            $html .= '<div class="text-secondary small my-1" style="font-size: 0.76rem; font-weight: normal;"><i class="bi bi-info-circle me-1"></i>' . htmlspecialchars(implode(' | ', $summary_parts)) . '</div>';
        }
        
        if ($has_changes || $has_sections) {
            $html .= '<div class="mt-1">';
            $html .= '<button class="btn btn-xs btn-outline-secondary py-0 px-2 rounded font-monospace text-decoration-none shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#collapseLog' . $row_id . '" aria-expanded="false" style="font-size: 0.72rem; border-color: #cbd5e1; color: #475569;">';
            $html .= '<i class="bi bi-chevron-down me-1"></i> ดูรายละเอียดการเปลี่ยนแปลง';
            $html .= '</button>';
            $html .= '<div class="collapse mt-2" id="collapseLog' . $row_id . '">';
            $html .= '<div class="card card-body p-2 border-0 bg-light rounded-3" style="max-width: 100%; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">';
            
            // แสดงตารางข้อมูลที่มีการเปลี่ยนแปลง
            if ($has_changes) {
                $html .= '<div class="table-responsive mb-2">';
                $html .= '<table class="table table-sm table-bordered mb-0" style="font-size: 0.8rem; background: white; border-color: #e2e8f0;">';
                $html .= '<thead class="table-light"><tr style="font-size: 0.75rem;"><th style="width: 30%;">หัวข้อ/ฟิลด์</th><th style="width: 35%;">ค่าเดิม</th><th style="width: 35%;">ค่าใหม่</th></tr></thead>';
                $html .= '<tbody>';
                foreach ($data['changes'] as $change) {
                    $field = htmlspecialchars($change['field'] ?? '');
                    $old = htmlspecialchars($change['old'] ?? '');
                    $new = htmlspecialchars($change['new'] ?? '');
                    $html .= '<tr>';
                    $html .= '<td class="fw-semibold text-secondary" style="font-size: 0.76rem;">' . $field . '</td>';
                    $html .= '<td class="text-danger bg-danger-subtle px-2 py-1" style="text-decoration: line-through; font-size: 0.76rem;">' . $old . '</td>';
                    $html .= '<td class="text-success bg-success-subtle px-2 py-1 fw-bold" style="font-size: 0.76rem;">' . $new . '</td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody></table></div>';
            }
            
            // แสดงรายการข้อมูลย่อย (เช่น ล็อตสินค้า, ข้อมูลลบ)
            if ($has_sections) {
                foreach ($data['sections'] as $sec) {
                    $sec_title = htmlspecialchars($sec['title'] ?? '');
                    $html .= '<div class="mt-1 small">';
                    $html .= '<div class="fw-semibold text-primary mb-1" style="font-size: 0.78rem;"><i class="bi bi-chevron-right small"></i> ' . $sec_title . '</div>';
                    if (!empty($sec['items'])) {
                        $html .= '<ul class="list-unstyled ps-2 mb-0" style="font-size: 0.75rem;">';
                        foreach ($sec['items'] as $item) {
                            $html .= '<li class="text-muted mb-1"><i class="bi bi-dot me-1"></i> ' . htmlspecialchars($item) . '</li>';
                        }
                        $html .= '</ul>';
                    }
                    $html .= '</div>';
                }
            }
            
            $html .= '</div></div></div>';
        }
        
        return $html;
    }
    
    // แบบเก่า: แสดงข้อความทั่วไป (พร้อมการจัดหน้าเว้นบรรทัด)
    return '<div class="lh-base" style="white-space: pre-wrap; font-size: 0.85rem;">' . htmlspecialchars($details_json_or_text) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการทำงานแอดมิน | Por Mae Bet Taled Admin</title>
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit'; background: #f8f9fa; }
        .table-card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.03); background: white; overflow: hidden; }
        .btn-gradient { background: linear-gradient(135deg, #AEE2FF 0%, #7FB5FF 100%); color: white; border: none; }
        .btn-gradient:hover { color: white; opacity: 0.9; }
        
        /* สไตล์ Badge ตามประเภทการทำงานเพื่อความพรีเมียม */
        .badge-log { font-size: 0.8rem; padding: 5px 10px; border-radius: 30px; font-weight: 500; }
        .bg-create { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; } /* เพิ่ม, สร้าง */
        .bg-update { background: #cff4fc; color: #055160; border: 1px solid #b6effb; } /* แก้ไข, ปรับปรุง */
        .bg-delete { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; } /* ลบ, เคลียร์ */
        .bg-other { background: #e2e3e5; color: #41464b; border: 1px solid #d3d6d8; } /* อื่นๆ */
        
        /* สไตล์หน้าแสดงผล */
        .log-details { font-size: 0.88rem; color: #475569; word-break: break-word; }
        .log-details table { margin-bottom: 0; }
        .btn-xs { padding: 2px 6px; font-size: 0.72rem; line-height: 1.2; border-radius: 4px; }
        .log-time { font-size: 0.82rem; color: #94a3b8; }
        .log-admin { font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 6px; }
        .admin-avatar { width: 28px; height: 28px; background: #E3F2FD; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #7FB5FF; font-size: 0.85rem; font-weight: bold; }
        
        .filter-card { border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); background: white; }
        
        /* สไตล์การ์ดมือถือพรีเมียม */
        @media (max-width: 767.98px) {
            .card-modern-mobile {
                background: #ffffff !important;
                border: 1px solid rgba(226, 232, 240, 0.8) !important;
                border-radius: 20px !important;
                box-shadow: 0 10px 30px rgba(127, 181, 255, 0.05) !important;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                position: relative !important;
                overflow: hidden !important;
                border-left: 5px solid #7FB5FF !important; /* Pastel Blue left accent */
            }
            .card-modern-mobile:hover, .card-modern-mobile:active {
                transform: translateY(-3px) scale(1.01);
                box-shadow: 0 15px 35px rgba(127, 181, 255, 0.12) !important;
                border-color: rgba(127, 181, 255, 0.3) !important;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Menu -->
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

        <!-- Main Content -->
        <div class="col-md-10 p-4 p-md-5">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
                <div>
                    <h2 class="fw-bold m-0">ประวัติการทำงานแอดมิน (Admin Logs)</h2>
                    <p class="text-muted small mb-0">ตรวจสอบและควบคุมกิจกรรมของระบบความเคลื่อนไหวทั้งหมดหลังบ้าน</p>
                </div>
                <button type="button" class="btn btn-outline-danger rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#clearLogsModal">
                    <i class="bi bi-trash3-fill me-1"></i> ล้างประวัติทั้งหมด
                </button>
            </div>

            <!-- ค้นหาและตัวกรอง -->
            <div class="card filter-card p-4 mb-4">
                <form id="filter-form" method="GET" action="admin_logs.php" class="row g-3" onsubmit="submitFilterForm(event)">
                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-bold">ค้นหาคำค้น</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control bg-light border-start-0" placeholder="ผู้ใช้, รายละเอียด, IP..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label text-muted small fw-bold">บทบาท</label>
                        <select name="role_filter" class="form-select bg-light">
                            <option value="">-- ทั้งหมด --</option>
                            <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>เฉพาะแอดมิน</option>
                            <option value="user" <?= $role_filter === 'user' ? 'selected' : '' ?>>เฉพาะลูกค้า</option>
                            <option value="system" <?= $role_filter === 'system' ? 'selected' : '' ?>>เฉพาะระบบ</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label text-muted small fw-bold">ประเภทกิจกรรม</label>
                        <select name="action_filter" class="form-select bg-light">
                            <option value="">-- ทั้งหมด --</option>
                            <?php foreach ($action_types as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>" <?= $action_filter === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label text-muted small fw-bold">จากวันที่</label>
                        <input type="date" name="date_start" class="form-control bg-light" value="<?= htmlspecialchars($date_start) ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label text-muted small fw-bold">ถึงวันที่</label>
                        <input type="date" name="date_end" class="form-control bg-light" value="<?= htmlspecialchars($date_end) ?>">
                    </div>

                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100 rounded-3 shadow-sm py-2" style="background:#7FB5FF; border:none;"><i class="bi bi-funnel-fill"></i></button>
                    </div>
                </form>
            </div>

            <!-- แสดงรายการบันทึก -->
            <div class="card table-card p-3" id="logs-table-wrapper">
                <?php 
                $ajax_fetch = isset($_GET['ajax_fetch']);
                if ($ajax_fetch) ob_start(); 
                ?>
                <div class="table-responsive d-none d-md-block">
                    <table class="table align-middle table-hover">
                        <thead class="bg-light">
                            <tr class="text-secondary small">
                                <th class="ps-4" style="width: 15%;">วัน-เวลา</th>
                                <th style="width: 15%;">ผู้ดำเนินการ</th>
                                <th style="width: 15%;">ประเภทกิจกรรม</th>
                                <th style="width: 43%;">รายละเอียดกิจกรรม</th>
                                <th class="pe-4 text-center" style="width: 12%;">IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (count($rows) > 0):
                                foreach ($rows as $row):
                                    // กำหนด Class ของประเภทกิจกรรม
                                    $act = $row['action_type'];
                                    $badge_class = 'bg-other';
                                    
                                    if (strpos($act, 'เพิ่ม') !== false || strpos($act, 'สร้าง') !== false) {
                                        $badge_class = 'bg-create';
                                    } elseif (strpos($act, 'แก้ไข') !== false || strpos($act, 'อัปเดต') !== false || strpos($act, 'ตั้งค่า') !== false) {
                                        $badge_class = 'bg-update';
                                    } elseif (strpos($act, 'ลบ') !== false || strpos($act, 'ล้าง') !== false || strpos($act, 'ยกเลิก') !== false || strpos($act, 'บังคับปิด') !== false) {
                                        $badge_class = 'bg-delete';
                                    }
                                    
                                    $avatar_letter = !empty($row['admin_name']) ? mb_strtoupper(mb_substr($row['admin_name'], 0, 1, 'UTF-8'), 'UTF-8') : 'S';
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="log-time fw-semibold">
                                        <i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y', strtotime($row['created_at'])) ?>
                                    </div>
                                    <div class="log-time text-muted mt-1">
                                        <i class="bi bi-clock me-1"></i><?= date('H:i:s', strtotime($row['created_at'])) ?> น.
                                    </div>
                                </td>
                                <td>
                                    <div class="log-admin">
                                        <div class="admin-avatar" title="<?= htmlspecialchars($row['admin_name']) ?>"><?= $avatar_letter ?></div>
                                        <div>
                                            <div style="font-size: 0.9rem; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                                <span><?= htmlspecialchars($row['admin_name']) ?></span>
                                                <?php if (($row['role'] ?? '') === 'admin'): ?>
                                                    <span class="badge bg-primary text-white" style="font-size: 0.65rem; padding: 2px 6px; border-radius: 4px;">แอดมิน</span>
                                                <?php elseif (($row['role'] ?? '') === 'user'): ?>
                                                    <span class="badge bg-info text-white" style="font-size: 0.65rem; padding: 2px 6px; border-radius: 4px;">ลูกค้า</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary text-white" style="font-size: 0.65rem; padding: 2px 6px; border-radius: 4px;">ระบบ</span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted" style="font-size: 0.72rem;">ID: #<?= $row['admin_id'] ?? 'System' ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-log <?= $badge_class ?>"><?= htmlspecialchars($row['action_type']) ?></span>
                                </td>
                                <td>
                                    <div class="log-details font-monospace"><?= render_log_details($row['details'], $row['id']) ?></div>
                                </td>
                                <td class="pe-4 text-center">
                                    <code class="text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($row['ip_address'] ?: '-') ?></code>
                                </td>
                            </tr>
                            <?php 
                                endforeach; 
                            else: 
                            ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-clipboard-x display-4 opacity-25"></i>
                                        <h5 class="mt-3">ไม่พบรายการประวัติการทำงานแอดมิน</h5>
                                        <p class="small mb-0 text-secondary">ลองเปลี่ยนตัวเลือกหรือคำค้นหาของคุณ</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- รายการการ์ดสำหรับหน้าจอมือถือ (Mobile View) -->
                <div class="d-md-none">
                    <?php if (count($rows) > 0): ?>
                        <?php foreach ($rows as $row): 
                            $act = $row['action_type'];
                            $badge_class = 'bg-other';
                            if (strpos($act, 'เพิ่ม') !== false || strpos($act, 'สร้าง') !== false) {
                                $badge_class = 'bg-create';
                            } elseif (strpos($act, 'แก้ไข') !== false || strpos($act, 'อัปเดต') !== false || strpos($act, 'ตั้งค่า') !== false) {
                                $badge_class = 'bg-update';
                            } elseif (strpos($act, 'ลบ') !== false || strpos($act, 'ล้าง') !== false || strpos($act, 'ยกเลิก') !== false || strpos($act, 'บังคับปิด') !== false) {
                                $badge_class = 'bg-delete';
                            }
                            $avatar_letter = !empty($row['admin_name']) ? mb_strtoupper(mb_substr($row['admin_name'], 0, 1, 'UTF-8'), 'UTF-8') : 'S';
                        ?>
                            <div class="card-modern-mobile p-3 mb-3 shadow-sm">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="admin-avatar" style="width:26px; height:26px; font-size:0.75rem;"><?= $avatar_letter ?></div>
                                        <div>
                                            <span class="fw-bold text-dark small" style="font-size: 0.85rem;"><?= htmlspecialchars($row['admin_name'] ?: 'ระบบ') ?></span>
                                            <?php if (($row['role'] ?? '') === 'admin'): ?>
                                                <span class="badge bg-primary text-white" style="font-size: 0.6rem; padding: 1px 4px; border-radius: 3px;">แอดมิน</span>
                                            <?php elseif (($row['role'] ?? '') === 'user'): ?>
                                                <span class="badge bg-info text-white" style="font-size: 0.6rem; padding: 1px 4px; border-radius: 3px;">ลูกค้า</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary text-white" style="font-size: 0.6rem; padding: 1px 4px; border-radius: 3px;">ระบบ</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <code class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($row['ip_address'] ?: '-') ?></code>
                                </div>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge-log <?= $badge_class ?>" style="font-size: 0.72rem; padding: 3px 8px;"><?= htmlspecialchars($row['action_type']) ?></span>
                                    <span class="text-muted font-monospace" style="font-size: 0.72rem;">
                                        <i class="bi bi-clock me-1"></i><?= date('d/m/Y H:i:s', strtotime($row['created_at'])) ?>
                                    </span>
                                </div>
                                <div class="log-details font-monospace p-2 bg-light rounded-3" style="font-size: 0.78rem;">
                                    <?= render_log_details($row['details'], 'mob_' . $row['id']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted bg-white rounded-4 shadow-sm border" style="border-color: #f1f5f9;">
                            <i class="bi bi-clipboard-x display-4 opacity-25"></i>
                            <h5 class="mt-3">ไม่พบรายการประวัติการทำงานแอดมิน</h5>
                            <p class="small mb-0 text-secondary">ลองเปลี่ยนตัวเลือกหรือคำค้นหาของคุณ</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- การแบ่งหน้า (Pagination) -->
                <?php if ($total_pages > 1): ?>
                    <nav class="d-flex justify-content-between align-items-center mt-4 px-3">
                        <div class="small text-muted">
                            แสดงข้อมูลแถว <?= $offset + 1 ?> - <?= min($offset + $limit, $total_rows) ?> จากทั้งหมด <?= number_format($total_rows) ?> รายการ
                        </div>
                        <ul class="pagination pagination-sm m-0">
                                            <!-- ปุ่มก่อนหน้า -->
                                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&action_filter=<?= urlencode($action_filter) ?>&role_filter=<?= urlencode($role_filter) ?>&date_start=<?= urlencode($date_start) ?>&date_end=<?= urlencode($date_end) ?>"><i class="bi bi-chevron-left"></i></a>
                                            </li>
                                            
                                            <!-- หมายเลขหน้า -->
                                            <?php 
                                            $start_p = max(1, $page - 2);
                                            $end_p = min($total_pages, $page + 2);
                                            for ($i = $start_p; $i <= $end_p; $i++): 
                                            ?>
                                                <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&action_filter=<?= urlencode($action_filter) ?>&role_filter=<?= urlencode($role_filter) ?>&date_start=<?= urlencode($date_start) ?>&date_end=<?= urlencode($date_end) ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <!-- ปุ่มถัดไป -->
                                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&action_filter=<?= urlencode($action_filter) ?>&role_filter=<?= urlencode($role_filter) ?>&date_start=<?= urlencode($date_start) ?>&date_end=<?= urlencode($date_end) ?>"><i class="bi bi-chevron-right"></i></a>
                                            </li>
                                        </ul>
                    </nav>
                <?php endif; ?>
                <?php
                if ($ajax_fetch) {
                    $html = ob_get_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'html' => $html]);
                    exit();
                }
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal ยืนยันรหัสผ่านเพื่อล้างประวัติ -->
<div class="modal fade" id="clearLogsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-shield-lock-fill"></i> ยืนยันตัวตน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="clear-logs-form" method="POST" action="admin_logs.php" onsubmit="submitClearLogsForm(event)">
                <?= get_csrf_input() ?>
                <div class="modal-body py-3">
                    <p class="small text-muted mb-3">กรุณากรอกรหัสผ่านบัญชีแอดมินของคุณเพื่อยืนยันการล้างประวัติการทำงานทั้งหมดถาวร</p>
                    <div class="mb-1">
                        <label class="form-label small fw-bold text-muted">รหัสผ่านแอดมิน</label>
                        <input type="password" name="admin_password" class="form-control form-control-sm rounded-3" placeholder="ป้อนรหัสผ่านของคุณ" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" name="clear_logs" class="btn btn-danger btn-sm w-100 rounded-pill fw-bold">ยืนยันล้างข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert แจ้งเตือนความสำเร็จ/ล้มเหลว -->
<?php if(isset($_SESSION['swal'])): ?>
<script>
    Swal.fire({
        icon: '<?= $_SESSION['swal']['icon'] ?>',
        title: '<?= $_SESSION['swal']['title'] ?>',
        text: '<?= $_SESSION['swal']['text'] ?>',
        confirmButtonColor: '#7FB5FF',
        timer: 2000,
        showConfirmButton: false
    });
</script>
<?php unset($_SESSION['swal']); endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    bindPaginationClicks();
});

function submitFilterForm(event) {
    if (event) event.preventDefault();
    const form = document.getElementById('filter-form');
    const formData = new FormData(form);
    const params = new URLSearchParams();
    for (const [key, value] of formData.entries()) {
        if (value.trim() !== '') {
            params.append(key, value);
        }
    }
    const queryString = params.toString();
    const url = window.location.pathname + (queryString ? '?' + queryString : '');
    
    // Push history
    history.pushState(null, '', url);
    
    // Fetch logs
    fetchLogs(url);
}

function fetchLogs(url) {
    const wrapper = document.getElementById('logs-table-wrapper');
    if (wrapper) {
        wrapper.style.opacity = '0.5';
    }
    
    const ajaxUrl = new URL(url, window.location.origin + window.location.pathname);
    ajaxUrl.searchParams.set('ajax_fetch', '1');
    
    fetch(ajaxUrl.toString())
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                wrapper.innerHTML = data.html;
                bindPaginationClicks();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: data.message || 'ไม่สามารถโหลดข้อมูลได้',
                    confirmButtonColor: '#7FB5FF'
                });
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้',
                confirmButtonColor: '#7FB5FF'
            });
        })
        .finally(() => {
            if (wrapper) {
                wrapper.style.opacity = '1';
            }
        });
}

function bindPaginationClicks() {
    const wrapper = document.getElementById('logs-table-wrapper');
    if (!wrapper) return;
    
    const links = wrapper.querySelectorAll('.pagination .page-link');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const href = this.getAttribute('href');
            if (href && href !== '#') {
                const url = href.startsWith('?') ? window.location.pathname + href : href;
                history.pushState(null, '', url);
                fetchLogs(url);
            }
        });
    });
}

window.addEventListener('popstate', function() {
    fetchLogs(window.location.href);
    syncFilterFormFromURL();
});

function syncFilterFormFromURL() {
    const form = document.getElementById('filter-form');
    if (!form) return;
    
    const params = new URLSearchParams(window.location.search);
    
    const searchInput = form.querySelector('input[name="search"]');
    if (searchInput) searchInput.value = params.get('search') || '';
    
    const roleSelect = form.querySelector('select[name="role_filter"]');
    if (roleSelect) roleSelect.value = params.get('role_filter') || '';
    
    const actionSelect = form.querySelector('select[name="action_filter"]');
    if (actionSelect) actionSelect.value = params.get('action_filter') || '';
    
    const dateStart = form.querySelector('input[name="date_start"]');
    if (dateStart) dateStart.value = params.get('date_start') || '';
    
    const dateEnd = form.querySelector('input[name="date_end"]');
    if (dateEnd) dateEnd.value = params.get('date_end') || '';
}

function submitClearLogsForm(event) {
    event.preventDefault();
    const form = event.target;
    
    Swal.fire({
        title: 'ยืนยันการล้างประวัติ?',
        text: "การดำเนินการนี้ไม่สามารถย้อนกลับได้ ประวัติกิจกรรมแอดมินทั้งหมดจะถูกลบถาวร!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'ใช่, ล้างข้อมูลทั้งหมด',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData(form);
            formData.append('ajax', '1');
            formData.append('clear_logs', '1');
            
            Swal.fire({
                title: 'กำลังดำเนินการ...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.status === 'success') {
                    const modalEl = document.getElementById('clearLogsModal');
                    const modalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                    modalInstance.hide();
                    form.reset();
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'ล้างข้อมูลสำเร็จ',
                        text: data.message,
                        confirmButtonColor: '#7FB5FF',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    fetchLogs(window.location.pathname);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'ผิดพลาด',
                        text: data.message || 'รหัสผ่านไม่ถูกต้อง หรือเกิดข้อผิดพลาดในการตรวจสอบ',
                        confirmButtonColor: '#7FB5FF'
                    });
                }
            })
            .catch(err => {
                console.error(err);
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้',
                    confirmButtonColor: '#7FB5FF'
                });
            });
        }
    });
}
</script>
</body>
</html>
