<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');

// เช็ค Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

// Logic: ลบ Feedback
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
    
    // ดึงข้อมูลความคิดเห็นก่อนลบเพื่อนำมาบันทึก Log
    $fb_q = mysqli_query($conn, "SELECT f.message, u.fullname FROM feedback f JOIN users u ON f.user_id = u.id WHERE f.id=$id");
    $fb_info = mysqli_fetch_assoc($fb_q);
    $fb_name = $fb_info['fullname'] ?? 'ไม่ระบุชื่อ';
    $fb_msg = $fb_info['message'] ?? 'ไม่มีข้อความ';
    
    mysqli_query($conn, "DELETE FROM feedback WHERE id=$id");
    log_admin_action($conn, 'ลบความคิดเห็นลูกค้า', "ลบความคิดเห็นลูกค้า ID #$id (ผู้ส่ง: $fb_name, ข้อความ: $fb_msg) สำเร็จ");
    
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'ลบข้อความความคิดเห็นเรียบร้อยแล้ว']);
        exit();
    }
    header("Location: admin_feedback.php"); exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ความคิดเห็นลูกค้า | Por Mae Bet Taled Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit'; background: #f8f9fa; }
        .card-feed { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.03); transition: 0.3s; background: white; margin-bottom: 20px; }
        .card-feed:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        .user-avatar { width: 50px; height: 50px; background: #AEE2FF; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; flex-shrink: 0; }
        .feedback-card { transition: all 0.3s ease; }
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
            <h2 class="fw-bold mb-4">ความคิดเห็นจากลูกค้า</h2>

            <div class="row g-4" id="feedbacks-container">
                <?php 
                $limit = isset($_GET['limit']) ? max(10, min(100, intval($_GET['limit']))) : 20;
                $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                
                $count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM feedback f JOIN users u ON f.user_id = u.id");
                $total_rows = mysqli_fetch_assoc($count_query)['total'] ?? 0;
                $total_pages = ceil($total_rows / $limit);
                if ($total_pages > 0 && $page > $total_pages) {
                    $page = $total_pages;
                }
                $offset = ($page - 1) * $limit;

                $sql = "SELECT f.*, u.fullname, u.email FROM feedback f 
                        JOIN users u ON f.user_id = u.id 
                        ORDER BY f.id DESC LIMIT $limit OFFSET $offset";
                $result = mysqli_query($conn, $sql);

                if (mysqli_num_rows($result) > 0):
                    while($row = mysqli_fetch_assoc($result)):
                        $initial = mb_substr($row['fullname'], 0, 1);
                ?>
                <div class="col-12 col-md-6 col-lg-4 feedback-card" id="feedback-card-<?= $row['id'] ?>">
                    <div class="card card-feed p-4 h-100">
                        <div class="d-flex align-items-center mb-3">
                            <div class="user-avatar me-3 shadow-sm"><?= htmlspecialchars($initial) ?></div>
                            <div style="overflow: hidden;">
                                <h6 class="fw-bold m-0 text-truncate"><?= htmlspecialchars($row['fullname']) ?></h6>
                                <small class="text-muted text-truncate d-block"><?= htmlspecialchars($row['email']) ?></small>
                            </div>
                            <button onclick="confirmDelete(<?= intval($row['id']) ?>, '<?= get_csrf_token() ?>')" class="btn btn-light text-danger btn-sm rounded-circle ms-auto" title="ลบข้อความ">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <div class="bg-light p-3 rounded-3 text-secondary mb-2" style="font-style: italic; min-height: 80px;">
                            "<?= htmlspecialchars($row['message']) ?>"
                        </div>
                        <div class="text-end">
                            <small class="text-muted" style="font-size: 0.8rem;">
                                <i class="bi bi-clock"></i> 
                                <?= isset($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : '-'; ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endwhile; else: ?>
                    <div class="col-12 text-center py-5 text-muted" id="no-feedbacks-placeholder">
                        <i class="bi bi-chat-square-dots display-1 d-block mb-3 opacity-25"></i>
                        ยังไม่มีความคิดเห็นจากลูกค้า
                    </div>
                <?php endif; ?>
            </div>
            <!-- การแบ่งหน้า (Pagination) -->
            <?= render_pagination_controls($total_rows, $limit, $page, $offset) ?>
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

    function confirmDelete(id, token) {
        Swal.fire({
            title: 'ลบความคิดเห็นนี้?',
            text: "ข้อมูลจะหายไปถาวร",
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
                        const card = document.getElementById('feedback-card-' + id);
                        if (card) {
                            card.style.transition = 'all 0.3s ease';
                            card.style.opacity = '0';
                            card.style.transform = 'scale(0.8)';
                            setTimeout(() => {
                                card.remove();
                                const container = document.getElementById('feedbacks-container');
                                if (container && container.querySelectorAll('.feedback-card').length === 0) {
                                    container.innerHTML = `
                                        <div class="col-12 text-center py-5 text-muted" id="no-feedbacks-placeholder">
                                            <i class="bi bi-chat-square-dots display-1 d-block mb-3 opacity-25"></i>
                                            ยังไม่มีความคิดเห็นจากลูกค้า
                                        </div>
                                    `;
                                }
                            }, 300);
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
        });
    }
</script>
</body>
</html>
