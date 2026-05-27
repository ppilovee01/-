<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');

// แŠเน‡ค Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

// Logic: ลบ Feedback
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    mysqli_query($conn, "DELETE FROM feedback WHERE id=$id");
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
    <style>
        body { font-family: 'Kanit'; background: #f8f9fa; }
        .card-feed { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.03); transition: 0.3s; background: white; margin-bottom: 20px; }
        .card-feed:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        .user-avatar { width: 50px; height: 50px; background: #AEE2FF; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; flex-shrink: 0; }
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

            <div class="row g-4">
                <?php 
                $sql = "SELECT f.*, u.fullname, u.email FROM feedback f 
                        JOIN users u ON f.user_id = u.id 
                        ORDER BY f.id DESC";
                $result = mysqli_query($conn, $sql);

                if (mysqli_num_rows($result) > 0):
                    while($row = mysqli_fetch_assoc($result)):
                        $initial = mb_substr($row['fullname'], 0, 1);
                ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card card-feed p-4 h-100">
                        <div class="d-flex align-items-center mb-3">
                            <div class="user-avatar me-3 shadow-sm"><?= $initial ?></div>
                            <div style="overflow: hidden;">
                                <h6 class="fw-bold m-0 text-truncate"><?= $row['fullname'] ?></h6>
                                <small class="text-muted text-truncate d-block"><?= $row['email'] ?></small>
                            </div>
                            <a href="?delete=<?= $row['id'] ?>" class="btn btn-light text-danger btn-sm rounded-circle ms-auto" onclick="return confirm('ลบข้อความนี้?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                        <div class="bg-light p-3 rounded-3 text-secondary mb-2" style="font-style: italic; min-height: 80px;">
                            "<?= $row['message'] ?>"
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
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="bi bi-chat-square-dots display-1 d-block mb-3 opacity-25"></i>
                        ยังไม่มีความคิดเห็นจากลูกค้า
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


