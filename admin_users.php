<?php
session_start();
include 'db.php';

// 1. ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// 1.5. ฟังก์ชันคำนวณเวลาแบบเข้าใจง่าย (Relative Time Thai)
function get_relative_time_thai($timestamp) {
    if (!$timestamp) return '-';
    $diff = time() - $timestamp;
    
    if ($diff < 0) return 'เมื่อครู่นี้';
    if ($diff < 60) return 'เมื่อครู่นี้';
    
    $minutes = round($diff / 60);
    if ($minutes < 60) {
        return $minutes . ' นาทีที่แล้ว';
    }
    
    $hours = round($diff / 3600);
    if ($hours < 24) {
        return $hours . ' ชั่วโมงที่แล้ว';
    }
    
    $days = round($diff / 86400);
    return $days . ' วันที่แล้ว';
}

// 2. เช็คสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

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

// --- Logic 1: เพิ่มสมาชิกใหม่ (Anti-F5 Fixed) ---
if (isset($_POST['add_user'])) {
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT); 
    $name = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    // Security: ตรวจสอบค่า role ที่อนุญาตเท่านั้น ป้องกันการเปลี่ยนสิทธิ์โดยไม่ได้รับอนุญาต
    $allowed_roles = ['user', 'admin'];
    $role = in_array($_POST['role'], $allowed_roles) ? $_POST['role'] : 'user';

    // เช็คซ้ำ
    $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$user' OR email='$email'");
    if(mysqli_num_rows($check) > 0) {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'ชื่อผู้ใช้ หรือ อีเมลนี้ มีคนใช้แล้ว!']);
            exit();
        }
        $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>'ชื่อผู้ใช้ หรือ อีเมลนี้ มีคนใช้แล้ว!', 'icon'=>'error'];
    } else {
        $sql = "INSERT INTO users (username, password, fullname, email, role, created_at) VALUES ('$user', '$pass', '$name', '$email', '$role', NOW())";
        if(mysqli_query($conn, $sql)) {
            $new_id = mysqli_insert_id($conn);
            log_admin_action($conn, 'เพิ่มสมาชิก', [
                'title' => "เพิ่มสมาชิกใหม่: $name (User: $user)",
                'changes' => [
                    ['field' => 'ชื่อผู้ใช้งาน (Username)', 'old' => '-', 'new' => $user],
                    ['field' => 'ชื่อ-นามสกุล', 'old' => '-', 'new' => $name],
                    ['field' => 'อีเมล (Email)', 'old' => '-', 'new' => $email],
                    ['field' => 'ระดับสิทธิ์ (Role)', 'old' => '-', 'new' => $role === 'admin' ? 'ผู้ดูแลระบบ (Admin)' : 'ลูกค้าทั่วไป (User)']
                ]
            ]);
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success',
                    'message' => 'เพิ่มสมาชิกใหม่เรียบร้อย',
                    'user' => [
                        'id' => $new_id,
                        'username' => $user,
                        'fullname' => $name,
                        'email' => $email,
                        'role' => $role,
                        'points' => 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ],
                    'csrf_token' => get_csrf_token()
                ]);
                exit();
            }
            $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'เพิ่มสมาชิกใหม่เรียบร้อย', 'icon'=>'success'];
        } else {
            // Security: บันทึก error ลง log แทนการแสดงต่อผู้ใช้ ป้องกันการเปิดเผยโครงสร้าง DB
            error_log('[admin_users.php] add_user error: ' . mysqli_error($conn));
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล']);
                exit();
            }
            $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>'เกิดข้อผิดพลาดในการบันทึกข้อมูล', 'icon'=>'error'];
        }
    }
    header("Location: admin_users.php"); exit();
}

// --- Logic 2: แก้ไขสมาชิก ---
if (isset($_POST['edit_user'])) {
    $id = intval($_POST['edit_id']);
    $name = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    // Security: ตรวจสอบค่า role ที่อนุญาตเท่านั้น
    $allowed_roles = ['user', 'admin'];
    $role = in_array($_POST['role'], $allowed_roles) ? $_POST['role'] : 'user';
    $points = intval($_POST['points']);
    
    // ดึงข้อมูลเดิมมาเปรียบเทียบส่วนต่าง
    $old_user_q = mysqli_query($conn, "SELECT * FROM users WHERE id='$id'");
    $old_user = mysqli_fetch_assoc($old_user_q);
    $old_points = $old_user ? intval($old_user['points']) : 0;
    
    if (!empty($_POST['password'])) {
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql = "UPDATE users SET fullname='$name', email='$email', role='$role', password='$pass', points='$points' WHERE id='$id'";
    } else {
        $sql = "UPDATE users SET fullname='$name', email='$email', role='$role', points='$points' WHERE id='$id'";
    }

    if(mysqli_query($conn, $sql)) {
        $changes = [];
        if ($old_user) {
            if ($old_user['fullname'] !== $name) {
                $changes[] = ['field' => 'ชื่อ-นามสกุล', 'old' => $old_user['fullname'], 'new' => $name];
            }
            if ($old_user['email'] !== $email) {
                $changes[] = ['field' => 'อีเมล', 'old' => $old_user['email'], 'new' => $email];
            }
            if ($old_user['role'] !== $role) {
                $changes[] = ['field' => 'ระดับสิทธิ์ (Role)', 'old' => $old_user['role'] === 'admin' ? 'Admin' : 'User', 'new' => $role === 'admin' ? 'Admin' : 'User'];
            }
            if (intval($old_user['points']) !== $points) {
                $changes[] = ['field' => 'คะแนนสะสม (Points)', 'old' => number_format($old_user['points']) . ' แต้ม', 'new' => number_format($points) . ' แต้ม'];
            }
            if (!empty($_POST['password'])) {
                $changes[] = ['field' => 'รหัสผ่าน', 'old' => '******', 'new' => '(เปลี่ยนรหัสผ่านใหม่)'];
            }
        }
        
        log_admin_action($conn, 'แก้ไขสมาชิก', [
            'title' => "แก้ไขข้อมูลสมาชิก ID #$id (Username: " . ($old_user['username'] ?? '') . ")",
            'changes' => $changes
        ]);
        
        // บันทึกธุรกรรมประวัติแต้มสะสมหากแต้มมีการเปลี่ยนแปลง
        if ($points != $old_points) {
            $diff = $points - $old_points;
            $desc = "ผู้ดูแลระบบแก้ไขแต้มสะสมโดยตรง (จาก $old_points เป็น $points แต้ม)";
            mysqli_query($conn, "INSERT INTO point_history (user_id, points, description) VALUES ('$id', '$diff', '$desc')");
        }
        
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'อัปเดตข้อมูลเรียบร้อย',
                'user' => [
                    'id' => $id,
                    'username' => $old_user['username'],
                    'fullname' => $name,
                    'email' => $email,
                    'role' => $role,
                    'points' => $points,
                    'created_at' => $old_user['created_at'],
                    'last_login' => $old_user['last_login']
                ]
            ]);
            exit();
        }
        $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'อัปเดตข้อมูลเรียบร้อย', 'icon'=>'success'];
    } else {
        // Security: บันทึก error ลง log แทนการแสดงต่อผู้ใช้
        error_log('[admin_users.php] edit_user error: ' . mysqli_error($conn));
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล']);
            exit();
        }
        $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>'เกิดข้อผิดพลาดในการอัปเดตข้อมูล', 'icon'=>'error'];
    }
    header("Location: admin_users.php"); exit();
}

// --- Logic 3: ลบสมาชิก ---
if (isset($_GET['delete'])) {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้องหรือหมดเวลาเซสชัน (Invalid CSRF Token)']);
            exit();
        }
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
    $id = intval($_GET['delete']);
    if ($id == $_SESSION['user_id']) {
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่สามารถลบตัวเองได้!']);
            exit();
        }
        $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>'คุณไม่สามารถลบตัวเองได้!', 'icon'=>'warning'];
    } else {
        $user_q = mysqli_query($conn, "SELECT fullname, username FROM users WHERE id = $id");
        $user_info = mysqli_fetch_assoc($user_q);
        $fullname = $user_info ? $user_info['fullname'] : "ไม่ทราบชื่อ";
        $username = $user_info ? $user_info['username'] : "ไม่ทราบ ID";
        
        $sql = "DELETE FROM users WHERE id = $id";
        if(mysqli_query($conn, $sql)) {
            log_admin_action($conn, 'ลบสมาชิก', [
                'title' => "ลบสมาชิกออกจากระบบ (ID #$id)",
                'sections' => [
                    [
                        'title' => 'ข้อมูลสมาชิกที่ถูกลบ',
                        'items' => [
                            "รหัสสมาชิก: #$id",
                            "ชื่อผู้ใช้งาน (Username): $username",
                            "ชื่อ-นามสกุล: $fullname"
                        ]
                    ]
                ]
            ]);
            if (isset($_GET['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'message' => 'ลบสมาชิกเรียบร้อยแล้ว']);
                exit();
            }
            $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'ลบสมาชิกเรียบร้อยแล้ว', 'icon'=>'success'];
        } else {
            if (isset($_GET['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'ลบไม่ได้ (อาจมีออเดอร์ค้างอยู่)']);
                exit();
            }
            $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>'ลบไม่ได้ (อาจมีออเดอร์ค้างอยู่)', 'icon'=>'error'];
        }
    }
    header("Location: admin_users.php"); exit();
}

// 4. ทำระบบแบ่งหน้าและการค้นหา (Pagination & Search)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = isset($_GET['limit']) ? max(10, min(100, intval($_GET['limit']))) : 20; // แสดงตามที่ปรับปรุง หรือ ดีฟอลต์ 20

$where_clauses = [];
if ($search !== '') {
    $s = mysqli_real_escape_string($conn, $search);
    $where_clauses[] = "(username LIKE '%$s%' OR fullname LIKE '%$s%' OR email LIKE '%$s%')";
}
$where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

$count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM users" . $where_sql);
$total_rows = mysqli_fetch_assoc($count_query)['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
if ($total_pages > 0 && $page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;

$sql = "SELECT * FROM users" . $where_sql . " ORDER BY id DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $sql);
$users_list = [];
if ($result && mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
        $users_list[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสมาชิก | Por Mae Bet Taled Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit'; background: #f8f9fa; }
        .table-card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.03); background: white; overflow: hidden; }
        .user-icon { width: 40px; height: 40px; background: #e9ecef; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #555; }
        .btn-gradient { background: linear-gradient(135deg, #AEE2FF 0%, #7FB5FF 100%); color: white; border: none; }
        .btn-gradient:hover { color: white; opacity: 0.9; }
        .user-row { transition: all 0.3s ease; }
        .user-row.fade-out { opacity: 0; transform: translateX(30px); }
        
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
            .card-modern-mobile .btn {
                border-radius: 12px !important;
                font-weight: 500;
                padding: 8px 16px;
                font-size: 0.82rem;
            }
        }
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
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
                <div>
                    <h2 class="fw-bold m-0">จัดการสมาชิก (Users)</h2>
                    <p class="text-muted small mb-0">เพิ่ม ลบ แก้ไข ข้อมูลสมาชิกและแอดมิน</p>
                    <button class="btn btn-gradient rounded-pill px-4 shadow-sm w-100 w-md-auto mt-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-person-plus-fill me-2"></i> เพิ่มสมาชิก
                    </button>
                </div>
                <!-- Search Bar -->
                <div class="w-100 w-md-auto">
                    <form method="GET" action="admin_users.php" class="d-flex gap-2">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control border-start-0" placeholder="ค้นหา ชื่อ, username, อีเมล..." value="<?= htmlspecialchars($search) ?>" style="min-width: 250px;">
                        </div>
                        <button type="submit" class="btn btn-gradient px-3 rounded-3 shadow-sm"><i class="bi bi-search"></i></button>
                        <?php if ($search !== ''): ?>
                            <a href="admin_users.php" class="btn btn-outline-secondary px-3 rounded-3 shadow-sm"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card table-card p-3">
                <div class="table-responsive d-none d-md-block">
                    <table class="table align-middle table-hover" style="min-width: 800px;">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>ชื่อผู้ใช้งาน</th>
                                <th>อีเมล</th>
                                <th>สถานะ</th>
                                <th>แต้มสะสม</th>
                                <th>วันที่สมัคร</th>
                                <th>เข้าใช้งานล่าสุด</th>
                                <th class="text-end pe-4">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="users-tbody">
                            <?php 
                            if (count($users_list) > 0):
                                foreach($users_list as $row):
                                $role_badge = ($row['role'] == 'admin') ? '<span class="badge bg-dark">Admin</span>' : '<span class="badge bg-light text-dark border">User</span>';
                                
                                $last_online_txt = '-';
                                $relative_txt = 'ไม่เคยเข้าใช้งาน';
                                if (!empty($row['last_login'])) {
                                    $last_online_txt = date('d/m/Y H:i', strtotime($row['last_login']));
                                    $relative_txt = get_relative_time_thai(strtotime($row['last_login']));
                                }
                                
                                $ref_time = !empty($row['last_login']) ? strtotime($row['last_login']) : (!empty($row['created_at']) ? strtotime($row['created_at']) : null);
                                $days_inactive = 0;
                                if ($ref_time) {
                                    $days_inactive = floor((time() - $ref_time) / 86400);
                                }
                                $is_inactive_30_days = ($row['role'] === 'user' && $days_inactive >= 30);
                            ?>
                            <tr id="user-row-<?= $row['id'] ?>" class="user-row">
                                <td class="ps-4 text-muted">#<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-icon me-3"><i class="bi bi-person-fill"></i></div>
                                        <div>
                                            <div class="fw-bold user-fullname"><?= htmlspecialchars($row['fullname']) ?></div>
                                            <div class="small text-muted">User: <?= htmlspecialchars($row['username']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="user-email"><?= htmlspecialchars($row['email']) ?></td>
                                <td class="user-role-badge"><?= $role_badge ?></td>
                                <td>🪙 <span class="fw-bold text-warning user-points"><?= number_format($row['points'] ?? 0) ?></span> แต้ม</td>
                                <td class="text-muted small">
                                    <?= isset($row['created_at']) ? date('d/m/Y', strtotime($row['created_at'])) : '-' ?>
                                </td>
                                <td class="text-muted small user-last-login-td">
                                    <?= htmlspecialchars($last_online_txt) ?>
                                    <br><small class="text-muted">(<?= htmlspecialchars($relative_txt) ?>)</small>
                                    <?php if ($is_inactive_30_days): ?>
                                        <br><span class="badge bg-danger-subtle text-danger border border-danger-subtle mt-1" style="font-size: 0.75rem; padding: 4px 8px;"><i class="bi bi-clock-history me-1"></i>ไม่ออนไลน์ <?= $days_inactive ?> วัน</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <button onclick='editUser(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="btn btn-light text-primary btn-sm rounded-circle shadow-sm me-1 edit-user-btn" data-bs-toggle="modal" data-bs-target="#editUserModal">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    
                                    <?php if($row['id'] != $_SESSION['user_id']): ?>
                                    <button onclick="confirmBan(<?= $row['id'] ?>, '<?= htmlspecialchars($row['fullname'], ENT_QUOTES) ?>', '<?= get_csrf_token() ?>')" class="btn btn-light text-danger btn-sm rounded-circle shadow-sm">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php 
                                endforeach;
                            else:
                            ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="bi bi-people display-4 opacity-25"></i>
                                        <h5 class="mt-3">ไม่พบข้อมูลสมาชิก</h5>
                                        <p class="small mb-0 text-secondary">ลองค้นหาใหม่อีกครั้ง</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards View -->
                <div class="d-md-none" id="users-mobile-list">
                    <?php if (count($users_list) > 0): ?>
                        <?php foreach ($users_list as $row): 
                            $role_badge = ($row['role'] == 'admin') ? '<span class="badge bg-dark">Admin</span>' : '<span class="badge bg-light text-dark border">User</span>';
                            
                            $last_online_txt = '-';
                            $relative_txt = 'ไม่เคยเข้าใช้งาน';
                            if (!empty($row['last_login'])) {
                                $last_online_txt = date('d/m/Y H:i', strtotime($row['last_login']));
                                $relative_txt = get_relative_time_thai(strtotime($row['last_login']));
                            }
                            
                            $ref_time = !empty($row['last_login']) ? strtotime($row['last_login']) : (!empty($row['created_at']) ? strtotime($row['created_at']) : null);
                            $days_inactive = 0;
                            if ($ref_time) {
                                $days_inactive = floor((time() - $ref_time) / 86400);
                            }
                            $is_inactive_30_days = ($row['role'] === 'user' && $days_inactive >= 30);
                        ?>
                            <div class="card-modern-mobile p-3 mb-3 shadow-sm user-row" id="user-card-<?= $row['id'] ?>">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="text-muted small fw-bold">#<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT) ?></span>
                                    <span class="user-role-badge"><?= $role_badge ?></span>
                                </div>
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div class="user-icon" style="width:34px; height:34px; font-size: 0.9rem;"><i class="bi bi-person-fill"></i></div>
                                    <div>
                                        <div class="fw-bold text-dark user-fullname" style="font-size: 0.95rem;"><?= htmlspecialchars($row['fullname']) ?></div>
                                        <div class="small text-muted" style="font-size: 0.8rem;">Username: <?= htmlspecialchars($row['username']) ?></div>
                                    </div>
                                </div>
                                <div class="mb-3" style="font-size: 0.85rem; line-height: 1.5;">
                                    <div class="text-muted"><i class="bi bi-envelope me-1"></i> <span class="user-email"><?= htmlspecialchars($row['email']) ?></span></div>
                                    <div class="text-muted mt-1">🪙 แต้มสะสม: <span class="fw-bold text-warning user-points"><?= number_format($row['points'] ?? 0) ?></span> แต้ม</div>
                                    <div class="text-muted mt-1"><i class="bi bi-calendar3 me-1"></i> สมัครเมื่อ: <?= isset($row['created_at']) ? date('d/m/Y', strtotime($row['created_at'])) : '-' ?></div>
                                    <div class="text-muted mt-1 user-last-login-wrapper">
                                        <i class="bi bi-clock me-1"></i> ออนไลน์ล่าสุด: <span class="user-last-login"><?= htmlspecialchars($last_online_txt) ?></span> <small class="text-muted">(<?= htmlspecialchars($relative_txt) ?>)</small>
                                        <?php if ($is_inactive_30_days): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1 user-inactive-badge" style="font-size: 0.7rem; padding: 2px 6px;"><i class="bi bi-exclamation-circle me-1"></i>ไม่ออนไลน์ <?= $days_inactive ?> วัน</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button onclick='editUser(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="btn btn-light text-primary btn-sm rounded-pill py-2 px-3 flex-grow-1 shadow-sm edit-user-btn" data-bs-toggle="modal" data-bs-target="#editUserModal">
                                        <i class="bi bi-pencil-fill me-1"></i> แก้ไข
                                    </button>
                                    <?php if($row['id'] != $_SESSION['user_id']): ?>
                                        <button onclick="confirmBan(<?= $row['id'] ?>, '<?= htmlspecialchars($row['fullname'], ENT_QUOTES) ?>', '<?= get_csrf_token() ?>')" class="btn btn-light text-danger btn-sm rounded-pill py-2 px-3 shadow-sm">
                                            <i class="bi bi-trash-fill me-1"></i> ลบ
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted bg-white rounded-4 shadow-sm border border-light w-100">
                            <i class="bi bi-people display-4 opacity-25"></i>
                            <h5 class="mt-3">ไม่พบข้อมูลสมาชิก</h5>
                            <p class="small mb-0 text-secondary">ลองค้นหาใหม่อีกครั้ง</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- การแบ่งหน้า (Pagination) -->
                <?= render_pagination_controls($total_rows, $limit, $page, $offset) ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">เพิ่มสมาชิกใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="closeAddUserModalBtn"></button>
            </div>
            <form id="add-user-form" method="POST" onsubmit="submitAddUser(event)">
                <?= get_csrf_input() ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="small text-muted">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Password</label>
                        <div class="input-group">
                            <input type="password" name="password" id="adminAddPass" class="form-control" required>
                            <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePasswordVisibility('adminAddPass', this)" style="background: white; border-color: #ced4da;">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">ชื่อ-นามสกุล</label>
                        <input type="text" name="fullname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Role (สิทธิ์การใช้งาน)</label>
                        <select name="role" class="form-select">
                            <option value="user">User (ลูกค้าทั่วไป)</option>
                            <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" name="add_user" class="btn btn-gradient w-100 rounded-pill">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">แก้ไขข้อมูลสมาชิก</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="closeEditUserModalBtn"></button>
            </div>
            <form id="edit-user-form" method="POST" onsubmit="submitEditUser(event)">
                <?= get_csrf_input() ?>
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="small text-muted">Username (แก้ไขไม่ได้)</label>
                        <input type="text" id="edit_username" class="form-control bg-light" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">ชื่อ-นามสกุล</label>
                        <input type="text" name="fullname" id="edit_fullname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">เปลี่ยนรหัสผ่าน (ปล่อยว่างถ้าไม่เปลี่ยน)</label>
                        <div class="input-group">
                            <input type="password" name="password" id="adminEditPass" class="form-control" placeholder="*******">
                            <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePasswordVisibility('adminEditPass', this)" style="background: white; border-color: #ced4da;">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Role</label>
                        <select name="role" id="edit_role" class="form-select">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">แต้มสะสมสมาชิก</label>
                        <input type="number" name="points" id="edit_points" class="form-control" required min="0">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" name="edit_user" class="btn btn-warning w-100 rounded-pill text-white">อัปเดตข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

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

    let currentCsrfToken = '<?= get_csrf_token() ?>';

    function editUser(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_username').value = data.username;
        document.getElementById('edit_fullname').value = data.fullname;
        document.getElementById('edit_email').value = data.email;
        document.getElementById('edit_role').value = data.role;
        document.getElementById('edit_points').value = data.points || 0;
    }

    function submitAddUser(e) {
        e.preventDefault();
        const form = document.getElementById('add-user-form');
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        
        const formData = new FormData(form);
        formData.append('add_user', '1');
        formData.append('ajax', '1');
        formData.append('csrf_token', currentCsrfToken);
        
        fetch(window.location.pathname, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            
            const closeBtn = document.getElementById('closeAddUserModalBtn');
            if (closeBtn) closeBtn.click();
            
            if (data.status === 'success') {
                form.reset();
                currentCsrfToken = data.csrf_token;
                document.querySelectorAll('input[name="csrf_token"]').forEach(el => el.value = currentCsrfToken);
                
                Toast.fire({
                    icon: 'success',
                    title: data.message
                });
                
                const tbody = document.getElementById('users-tbody');
                const tr = document.createElement('tr');
                tr.id = 'user-row-' + data.user.id;
                tr.className = 'user-row';
                
                const paddedId = String(data.user.id).padStart(4, '0');
                const roleBadge = data.user.role === 'admin' ? '<span class="badge bg-dark">Admin</span>' : '<span class="badge bg-light text-dark border">User</span>';
                
                const lastLoginText = data.user.last_login ? formatDateTime(data.user.last_login) : '-';
                const relativeText = data.user.last_login ? getRelativeTimeThai(data.user.last_login) : 'ไม่เคยเข้าใช้งาน';
                const refDate = data.user.last_login || data.user.created_at;
                const daysInactive = getDaysInactive(refDate);
                const isInactive = data.user.role === 'user' && daysInactive >= 30;
                const inactiveBadge = isInactive ? `<br><span class="badge bg-danger-subtle text-danger border border-danger-subtle mt-1" style="font-size: 0.75rem; padding: 4px 8px;"><i class="bi bi-clock-history me-1"></i>ไม่ออนไลน์ ${daysInactive} วัน</span>` : '';

                tr.innerHTML = `
                    <td class="ps-4 text-muted">#${paddedId}</td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="user-icon me-3"><i class="bi bi-person-fill"></i></div>
                            <div>
                                <div class="fw-bold user-fullname">${escapeHtml(data.user.fullname)}</div>
                                <div class="small text-muted">User: ${escapeHtml(data.user.username)}</div>
                            </div>
                        </div>
                    </td>
                    <td class="user-email">${escapeHtml(data.user.email)}</td>
                    <td class="user-role-badge">${roleBadge}</td>
                    <td>🪙 <span class="fw-bold text-warning user-points">0</span> แต้ม</td>
                    <td class="text-muted small">${formatDate(data.user.created_at)}</td>
                    <td class="text-muted small user-last-login-td">
                        ${lastLoginText}
                        <br><small class="text-muted">(${relativeText})</small>
                        ${inactiveBadge}
                    </td>
                    <td class="text-end pe-4">
                        <button onclick='editUser(${JSON.stringify(data.user)})' class="btn btn-light text-primary btn-sm rounded-circle shadow-sm me-1 edit-user-btn" data-bs-toggle="modal" data-bs-target="#editUserModal">
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                        <button onclick="confirmBan(${data.user.id}, '${escapeHtmlString(data.user.fullname)}', '${currentCsrfToken}')" class="btn btn-light text-danger btn-sm rounded-circle shadow-sm">
                            <i class="bi bi-trash-fill"></i>
                        </button>
                    </td>
                `;
                tbody.insertBefore(tr, tbody.firstChild);
                
                // Prepend to mobile list
                const mobList = document.getElementById('users-mobile-list');
                if (mobList) {
                    const emptyPlaceholder = mobList.querySelector('.text-center');
                    if (emptyPlaceholder && emptyPlaceholder.innerText.includes('ไม่พบข้อมูล')) {
                        emptyPlaceholder.remove();
                    }
                    
                    const card = document.createElement('div');
                    card.id = 'user-card-' + data.user.id;
                    card.className = 'card-modern-mobile p-3 mb-3 shadow-sm user-row';
                    
                    const paddedId = String(data.user.id).padStart(4, '0');
                    const roleBadge = data.user.role === 'admin' ? '<span class="badge bg-dark">Admin</span>' : '<span class="badge bg-light text-dark border">User</span>';
                    
                    const mobLastLoginText = data.user.last_login ? formatDateTime(data.user.last_login) : '-';
                    const mobRelativeText = data.user.last_login ? getRelativeTimeThai(data.user.last_login) : 'ไม่เคยเข้าใช้งาน';
                    const mobRefDate = data.user.last_login || data.user.created_at;
                    const mobDaysInactive = getDaysInactive(mobRefDate);
                    const mobIsInactive = data.user.role === 'user' && mobDaysInactive >= 30;
                    const mobInactiveBadge = mobIsInactive ? `<span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1 user-inactive-badge" style="font-size: 0.7rem; padding: 2px 6px;"><i class="bi bi-exclamation-circle me-1"></i>ไม่ออนไลน์ ${mobDaysInactive} วัน</span>` : '';

                    card.innerHTML = `
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-bold">#${paddedId}</span>
                            <span class="user-role-badge">${roleBadge}</span>
                        </div>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="user-icon" style="width:34px; height:34px; font-size: 0.9rem;"><i class="bi bi-person-fill"></i></div>
                            <div>
                                <div class="fw-bold text-dark user-fullname" style="font-size: 0.95rem;">${escapeHtml(data.user.fullname)}</div>
                                <div class="small text-muted" style="font-size: 0.8rem;">Username: ${escapeHtml(data.user.username)}</div>
                            </div>
                        </div>
                        <div class="mb-3" style="font-size: 0.85rem; line-height: 1.5;">
                            <div class="text-muted"><i class="bi bi-envelope me-1"></i> <span class="user-email">${escapeHtml(data.user.email)}</span></div>
                            <div class="text-muted mt-1">🪙 แต้มสะสม: <span class="fw-bold text-warning user-points">0</span> แต้ม</div>
                            <div class="text-muted mt-1"><i class="bi bi-calendar3 me-1"></i> สมัครเมื่อ: ${formatDate(data.user.created_at)}</div>
                            <div class="text-muted mt-1 user-last-login-wrapper">
                                <i class="bi bi-clock me-1"></i> ออนไลน์ล่าสุด: <span class="user-last-login">${mobLastLoginText}</span> <small class="text-muted">(${mobRelativeText})</small>
                                ${mobInactiveBadge}
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button onclick='editUser(${JSON.stringify(data.user)})' class="btn btn-light text-primary btn-sm rounded-pill py-2 px-3 flex-grow-1 shadow-sm edit-user-btn" data-bs-toggle="modal" data-bs-target="#editUserModal">
                                <i class="bi bi-pencil-fill me-1"></i> แก้ไข
                            </button>
                            <button onclick="confirmBan(${data.user.id}, '${escapeHtmlString(data.user.fullname)}', '${currentCsrfToken}')" class="btn btn-light text-danger btn-sm rounded-pill py-2 px-3 shadow-sm">
                                <i class="bi bi-trash-fill me-1"></i> ลบ
                            </button>
                        </div>
                    `;
                    mobList.insertBefore(card, mobList.firstChild);
                }
            } else {
                Toast.fire({
                    icon: 'error',
                    title: data.message || 'เกิดข้อผิดพลาดในการบันทึก'
                });
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            console.error(err);
            Toast.fire({
                icon: 'error',
                title: 'การเชื่อมต่อล้มเหลว'
            });
        });
    }

    function submitEditUser(e) {
        e.preventDefault();
        const form = document.getElementById('edit-user-form');
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        
        const id = document.getElementById('edit_id').value;
        
        const formData = new FormData(form);
        formData.append('edit_user', '1');
        formData.append('ajax', '1');
        formData.append('csrf_token', currentCsrfToken);
        
        fetch(window.location.pathname, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            
            const closeBtn = document.getElementById('closeEditUserModalBtn');
            if (closeBtn) closeBtn.click();
            
            if (data.status === 'success') {
                Toast.fire({
                    icon: 'success',
                    title: data.message
                });
                
                const row = document.getElementById('user-row-' + id);
                if (row) {
                    row.querySelector('.user-fullname').innerText = data.user.fullname;
                    row.querySelector('.user-email').innerText = data.user.email;
                    row.querySelector('.user-points').innerText = Number(data.user.points).toLocaleString();
                    row.querySelector('.user-role-badge').innerHTML = data.user.role === 'admin' ? '<span class="badge bg-dark">Admin</span>' : '<span class="badge bg-light text-dark border">User</span>';
                    
                    const lastLoginTd = row.querySelector('.user-last-login-td');
                    if (lastLoginTd) {
                        const lastLoginText = data.user.last_login ? formatDateTime(data.user.last_login) : '-';
                        const relativeText = data.user.last_login ? getRelativeTimeThai(data.user.last_login) : 'ไม่เคยเข้าใช้งาน';
                        const refDate = data.user.last_login || data.user.created_at;
                        const daysInactive = getDaysInactive(refDate);
                        const isInactive = data.user.role === 'user' && daysInactive >= 30;
                        const inactiveBadge = isInactive ? `<br><span class="badge bg-danger-subtle text-danger border border-danger-subtle mt-1" style="font-size: 0.75rem; padding: 4px 8px;"><i class="bi bi-clock-history me-1"></i>ไม่ออนไลน์ ${daysInactive} วัน</span>` : '';
                        lastLoginTd.innerHTML = `${lastLoginText}<br><small class="text-muted">(${relativeText})</small>${inactiveBadge}`;
                    }

                    const editBtn = row.querySelector('.edit-user-btn');
                    if (editBtn) {
                        editBtn.setAttribute('onclick', `editUser(${JSON.stringify(data.user)})`);
                    }
                }
                
                // Update card values
                const card = document.getElementById('user-card-' + id);
                if (card) {
                    card.querySelector('.user-fullname').innerText = data.user.fullname;
                    card.querySelector('.user-email').innerText = data.user.email;
                    card.querySelector('.user-points').innerText = Number(data.user.points).toLocaleString();
                    card.querySelector('.user-role-badge').innerHTML = data.user.role === 'admin' ? '<span class="badge bg-dark">Admin</span>' : '<span class="badge bg-light text-dark border">User</span>';
                    
                    const wrapper = card.querySelector('.user-last-login-wrapper');
                    if (wrapper) {
                        const lastLoginText = data.user.last_login ? formatDateTime(data.user.last_login) : '-';
                        const relativeText = data.user.last_login ? getRelativeTimeThai(data.user.last_login) : 'ไม่เคยเข้าใช้งาน';
                        const refDate = data.user.last_login || data.user.created_at;
                        const daysInactive = getDaysInactive(refDate);
                        const isInactive = data.user.role === 'user' && daysInactive >= 30;
                        const inactiveBadge = isInactive ? `<span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1 user-inactive-badge" style="font-size: 0.7rem; padding: 2px 6px;"><i class="bi bi-exclamation-circle me-1"></i>ไม่ออนไลน์ ${daysInactive} วัน</span>` : '';
                        wrapper.innerHTML = `<i class="bi bi-clock me-1"></i> ออนไลน์ล่าสุด: <span class="user-last-login">${lastLoginText}</span> <small class="text-muted">(${relativeText})</small> ${inactiveBadge}`;
                    }

                    const editBtn = card.querySelector('.edit-user-btn');
                    if (editBtn) {
                        editBtn.setAttribute('onclick', `editUser(${JSON.stringify(data.user)})`);
                    }
                }
            } else {
                Toast.fire({
                    icon: 'error',
                    title: data.message || 'อัปเดตข้อมูลล้มเหลว'
                });
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            console.error(err);
            Toast.fire({
                icon: 'error',
                title: 'การเชื่อมต่อล้มเหลว'
            });
        });
    }

    function confirmBan(id, name, token) {
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: "ต้องการลบสมาชิก '" + name + "' หรือไม่?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'ลบเลย',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(window.location.pathname + `?delete=${id}&csrf_token=${token}&ajax=1`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Toast.fire({
                            icon: 'success',
                            title: data.message
                        });
                        const row = document.getElementById('user-row-' + id);
                        if (row) {
                            row.classList.add('fade-out');
                            setTimeout(() => row.remove(), 300);
                        }
                        const card = document.getElementById('user-card-' + id);
                        if (card) {
                            card.classList.add('fade-out');
                            setTimeout(() => card.remove(), 300);
                        }
                    } else {
                        Toast.fire({
                            icon: 'error',
                            title: data.message || 'ลบไม่สำเร็จ'
                        });
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
        })
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

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function escapeHtmlString(text) {
        return text.replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        return `${day}/${month}/${year}`;
    }

    function formatDateTime(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        return `${day}/${month}/${year} ${hours}:${minutes}`;
    }

    function isInactive30Days(dateStr) {
        if (!dateStr) return false;
        const date = new Date(dateStr);
        const now = new Date();
        const diffTime = Math.abs(now - date);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        return diffDays > 30;
    }

    function getRelativeTimeThai(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        const now = new Date();
        const diffSeconds = Math.floor((now - date) / 1000);
        
        if (diffSeconds < 0) return 'เมื่อครู่นี้';
        if (diffSeconds < 60) return 'เมื่อครู่นี้';
        
        const minutes = Math.floor(diffSeconds / 60);
        if (minutes < 60) return `${minutes} นาทีที่แล้ว`;
        
        const hours = Math.floor(diffSeconds / 3600);
        if (hours < 24) return `${hours} ชั่วโมงที่แล้ว`;
        
        const days = Math.floor(diffSeconds / 86400);
        return `${days} วันที่แล้ว`;
    }

    function getDaysInactive(dateStr) {
        if (!dateStr) return 0;
        const date = new Date(dateStr);
        const now = new Date();
        const diffTime = now - date;
        if (diffTime < 0) return 0;
        return Math.floor(diffTime / (1000 * 60 * 60 * 24));
    }


</script>

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
</body>
</html>
