<?php
session_start();
include 'db.php';

// แŠเน‡ค Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }

// --- Logic: ลบรีวิว ---
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    mysqli_query($conn, "DELETE FROM product_reviews WHERE id = '$id'");
    header("Location: admin_reviews.php"); exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการรีวิว | Por Mae Bet Taled Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style> body { font-family: 'Kanit'; background: #f8f9fa; } </style>
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
            <h3 class="fw-bold mb-4">๐Ÿ’ฌ จัดการรีวิวสินค้า</h3>

            <div class="card border-0 shadow-sm rounded-4 p-4">
                <div class="table-responsive">
                    <table class="table align-middle table-hover" style="min-width: 600px;">
                        <thead class="bg-light text-secondary small">
                            <tr>
                                <th>สินค้า</th>
                                <th>ลูกค้า</th>
                                <th>คะแนนน</th>
                                <th style="width: 40%;">ความคิดเห็น</th>
                                <th>วันที่</th>
                                <th class="text-end">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // ดึงรีวิว + ชื่อสินค้า + ชื่อคนรีวิว
                            $sql = "SELECT r.*, p.name as product_name, p.image, u.fullname 
                                    FROM product_reviews r 
                                    JOIN products p ON r.product_id = p.id 
                                    JOIN users u ON r.user_id = u.id 
                                    ORDER BY r.created_at DESC";
                            $res = mysqli_query($conn, $sql);
                            
                            if(mysqli_num_rows($res) > 0):
                                while($row = mysqli_fetch_assoc($res)): 
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?= $row['image'] ?>" class="rounded me-2" style="width:35px; height:35px; object-fit:cover;">
                                        <span class="text-truncate small" style="max-width: 150px;"><?= $row['product_name'] ?></span>
                                    </div>
                                </td>
                                <td class="small fw-bold"><?= $row['fullname'] ?></td>
                                <td>
                                    <span class="text-warning small">
                                        <?php for($i=1;$i<=5;$i++) echo $i<=$row['rating'] ? 'โ˜…' : 'โ˜†'; ?>
                                    </span>
                                    <span class="small text-muted">(<?= $row['rating'] ?>)</span>
                                </td>
                                <td class="text-secondary small"><?= $row['comment'] ?></td>
                                <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                                <td class="text-end">
                                    <button onclick="confirmDelete(<?= $row['id'] ?>)" class="btn btn-sm btn-outline-danger rounded-circle">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">ยังไม่มีรีวิว</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function confirmDelete(id) {
        Swal.fire({
            title: 'ลบรีวิวนี้?',
            text: "ข้อมูลจะหายไปถาวร",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'ลบเลย'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '?delete=' + id;
            }
        })
    }
</script>
</body>
</html>


