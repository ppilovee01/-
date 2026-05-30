<?php
session_start();
include 'db.php';

// 1. เช็คสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    header("Location: login.php"); 
    exit(); 
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
        $_SESSION['swal'] = ['title' => 'สำเร็จ', 'text' => 'ล้างประวัติการทำงานทั้งหมดเรียบร้อยแล้ว', 'icon' => 'success'];
    } else {
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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการทำงานแอดมิน | Por Mae Bet Taled Admin</title>
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
        .log-time { font-size: 0.82rem; color: #94a3b8; }
        .log-admin { font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 6px; }
        .admin-avatar { width: 28px; height: 28px; background: #E3F2FD; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #7FB5FF; font-size: 0.85rem; font-weight: bold; }
        
        .filter-card { border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); background: white; }
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
                <form method="GET" action="admin_logs.php" class="row g-3">
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
            <div class="card table-card p-3">
                <div class="table-responsive">
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
                            if ($total_rows > 0):
                                while ($row = mysqli_fetch_assoc($result)):
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
                                    <div class="log-details font-monospace"><?= htmlspecialchars($row['details']) ?></div>
                                </td>
                                <td class="pe-4 text-center">
                                    <code class="text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($row['ip_address'] ?: '-') ?></code>
                                </td>
                            </tr>
                            <?php 
                                endwhile; 
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
            <form method="POST" action="admin_logs.php">
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
</body>
</html>
