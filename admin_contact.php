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
    mysqli_query($conn, "DELETE FROM contact_messages WHERE id = '$did'");
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'ลบข้อความเรียบร้อยแล้ว', 'unread' => get_unread_count($conn)]);
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
    mysqli_query($conn, "UPDATE contact_messages SET status = '$new_status' WHERE id = '$rid'");
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success', 
            'message' => $new_status == 'read' ? 'ทำเครื่องหมายว่าอ่านแล้วเรียบร้อย' : 'ทำเครื่องหมายว่ายังไม่ได้อ่านเรียบร้อย',
            'unread' => get_unread_count($conn),
            'new_status' => $new_status
        ]);
        exit();
    }
    header("Location: admin_contact.php"); exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages | Por Mae Bet Taled Admin</title>
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --blue-primary: #AEE2FF;
            --blue-soft: #F0F8FF;
            --bg-admin: #f4f7f6;
        }

        body { 
            background-color: var(--bg-admin); 
            font-family: 'Kanit', sans-serif; 
        }

        .admin-wrapper { display: flex; min-height: 100vh; flex-direction: row; }
        
        /* Sidebar Styling */
        .admin-sidebar-container { width: 280px; flex-shrink: 0; background: white; }
        
        .admin-main-content { flex-grow: 1; padding: 30px 40px; width: 100%; }

        .content-card {
            background: white; border-radius: 20px; padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.02);
        }

        /* Responsive Fixes */
        @media (max-width: 768px) {
            .admin-wrapper { flex-direction: column; }
            .admin-sidebar-container { width: 100%; }
            .admin-main-content { padding: 20px; }
            .content-card { padding: 20px; }
        }

        .custom-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        .custom-table tr { background: white; transition: 0.3s; }
        .custom-table td { padding: 20px; border-top: 1px solid #f1f1f1; border-bottom: 1px solid #f1f1f1; vertical-align: middle; }
        .custom-table td:first-child { border-left: 1px solid #f1f1f1; border-top-left-radius: 15px; border-bottom-left-radius: 15px; }
        .custom-table td:last-child { border-right: 1px solid #f1f1f1; border-top-right-radius: 15px; border-bottom-right-radius: 15px; }

        .badge-status { font-size: 0.7rem; padding: 4px 12px; border-radius: 50px; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; }
        .bg-unread { background-color: #ffe5eb; color: #ff4d6d; }
        .bg-read { background-color: #f1f3f5; color: #868e96; }

        .btn-action { width: 35px; height: 35px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; background: #f8f9fa; color: #888; border: none; transition: 0.2s; }
        .btn-action:hover { background: var(--blue-primary); color: white; transform: translateY(-2px); }
    </style>
</head>
<body>

<div class="container-fluid p-0">
    <div class="row g-0">
        <div class="col-md-2 border-end bg-white">
            <button class="btn btn-light w-100 d-md-none border-bottom p-3 fw-bold text-primary text-start" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
                <i class="bi bi-list me-2"></i> เมนูจัดการ
            </button>
            <div class="collapse d-md-block" id="sidebarMenu">
                <div style="min-height: 100vh;">
                    <?php include 'admin_sidebar.php'; ?>
                </div>
            </div>
        </div>

        <div class="col-md-10">
            <div class="admin-main-content">
                <div class="content-card animate__animated animate__fadeIn">
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="fw-bold mb-0">ข้อความติดต่อลูกค้า</h4>
                            <p class="text-muted small mb-0">รายการข้อความปัญหาจากหน้าเว็บ</p>
                        </div>
                        <?php 
                            $unread = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM contact_messages WHERE status='unread'"))['c'];
                        ?>
                        <span class="badge bg-danger rounded-pill px-3 py-2" id="unread-badge">ใหม่ <?= $unread ?></span>
                    </div>

                    <div class="table-responsive">
                        <table class="custom-table" style="min-width: 600px;">
                            <thead>
                                <tr class="text-muted small uppercase">
                                    <th class="ps-4">ผู้ติดต่อ</th>
                                    <th>ข้อความ</th>
                                    <th class="text-center">วันที่</th>
                                    <th class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $res = mysqli_query($conn, "SELECT * FROM contact_messages ORDER BY id DESC");
                                while($row = mysqli_fetch_assoc($res)):
                                    $is_new = ($row['status'] == 'unread');
                                ?>
                                <tr id="message-row-<?= $row['id'] ?>" style="<?= $is_new ? 'border-left: 4px solid var(--blue-primary);' : '' ?>">
                                    <td class="ps-4">
                                        <div class="mb-1">
                                            <span class="status-badge badge-status <?= $is_new ? 'bg-unread' : 'bg-read' ?>">
                                                <?= $is_new ? 'ยังไม่อ่าน' : 'อ่านแล้ว' ?>
                                            </span>
                                        </div>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['name']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($row['email']) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-blue small"><?= htmlspecialchars($row['subject']) ?></div>
                                        <div class="text-muted small" style="white-space: pre-line; word-break: break-word;">
                                            <?= htmlspecialchars($row['message']) ?>
                                        </div>
                                    </td>
                                    <td class="text-center text-muted small">
                                        <?= date('d/m/Y', strtotime($row['created_at'])) ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            <button onclick="toggleRead(this, <?= $row['id'] ?>, '<?= get_csrf_token() ?>')" class="btn-action" title="<?= $is_new ? 'ทำเครื่องหมายว่าอ่านแล้ว' : 'ทำเครื่องหมายว่ายังไม่อ่าน' ?>">
                                                <i class="bi <?= $is_new ? 'bi-envelope' : 'bi-envelope-open' ?>"></i>
                                            </button>
                                            <button onclick="confirmDelete(<?= $row['id'] ?>, '<?= get_csrf_token() ?>')" class="btn-action text-danger" title="ลบ">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
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

function toggleRead(btn, id, token) {
    const isUnread = btn.querySelector('i').classList.contains('bi-envelope');
    const targetStatus = isUnread ? 'read' : 'unread';
    
    btn.disabled = true;
    fetch(`admin_contact.php?read_id=${id}&status=${targetStatus}&csrf_token=${token}&ajax=1`)
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        if (data.status === 'success') {
            const row = document.getElementById('message-row-' + id);
            const statusBadge = row.querySelector('.status-badge');
            const icon = btn.querySelector('i');
            
            if (data.new_status === 'read') {
                icon.className = 'bi bi-envelope-open';
                btn.title = 'ทำเครื่องหมายว่ายังไม่อ่าน';
                statusBadge.className = 'status-badge badge-status bg-read';
                statusBadge.innerText = 'อ่านแล้ว';
                row.style.borderLeft = 'none';
            } else {
                icon.className = 'bi bi-envelope';
                btn.title = 'ทำเครื่องหมายว่าอ่านแล้ว';
                statusBadge.className = 'status-badge badge-status bg-unread';
                statusBadge.innerText = 'ยังไม่อ่าน';
                row.style.borderLeft = '4px solid var(--blue-primary)';
            }
            
            document.getElementById('unread-badge').innerText = 'ใหม่ ' + data.unread;
            
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
            fetch(`admin_contact.php?delete_id=${id}&csrf_token=${token}&ajax=1`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    Toast.fire({
                        icon: 'success',
                        title: data.message
                    });
                    const row = document.getElementById('message-row-' + id);
                    if (row) {
                        row.style.transition = 'all 0.3s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(30px)';
                        setTimeout(() => {
                            row.remove();
                            document.getElementById('unread-badge').innerText = 'ใหม่ ' + data.unread;
                        }, 300);
                    }
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

</body>
</html>


