<?php
session_start();
include 'db.php';

// แŠเน‡ค Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // ดึงข้อมูลรีวิวเพื่อบันทึกประวัติก่อนลบ
    $r_q = mysqli_query($conn, "SELECT r.comment, u.fullname, p.name as product_name FROM product_reviews r JOIN users u ON r.user_id = u.id JOIN products p ON r.product_id = p.id WHERE r.id = '$id'");
    $r_info = mysqli_fetch_assoc($r_q);
    $r_comment = $r_info['comment'] ?? 'ไม่ระบุความคิดเห็น';
    $u_name = $r_info['fullname'] ?? 'ไม่ระบุผู้ใช้';
    $p_name = $r_info['product_name'] ?? 'ไม่ระบุสินค้า';
    
    // ดึงข้อมูลรูปภาพเพื่อลบไฟล์จากโฟลเดอร์ uploads/
    $q_img = mysqli_query($conn, "SELECT image FROM product_reviews WHERE id = '$id'");
    if ($q_img && mysqli_num_rows($q_img) > 0) {
        $row_img = mysqli_fetch_assoc($q_img);
        if (!empty($row_img['image'])) {
            $file_path = $row_img['image'];
            // ตรวจสอบว่ามีไฟล์อยู่จริงแล้วทำการลบ
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        }
    }
    
    mysqli_query($conn, "DELETE FROM product_reviews WHERE id = '$id'");
    log_admin_action($conn, 'ลบรีวิวสินค้า', "ลบรีวิวสินค้า '$p_name' ของลูกค้า '$u_name' (รีวิว: '$r_comment')");
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
            <h3 class="fw-bold mb-4">จัดการรีวิวสินค้า</h3>

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
                            // ดึงรีวิว + ชื่อสินค้า + ชื่อคนรีวิว (เลี่ยงคอลัมน์ image ชนกัน)
                            $sql = "SELECT r.*, r.image as review_image, p.name as product_name, p.image as product_image, u.fullname 
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
                                        <img src="<?= $row['product_image'] ?>" class="rounded me-2" style="width:35px; height:35px; object-fit:cover;">
                                        <span class="text-truncate small" style="max-width: 150px;"><?= htmlspecialchars($row['product_name'] ?? '') ?></span>
                                    </div>
                                </td>
                                <td class="small fw-bold"><?= htmlspecialchars($row['fullname'] ?? '') ?></td>
                                <td>
                                    <span class="text-warning small">
                                        <?php for($i=1;$i<=5;$i++) echo $i<=$row['rating'] ? '★' : '☆'; ?>
                                    </span>
                                    <span class="small text-muted">(<?= $row['rating'] ?>)</span>
                                </td>
                                <td class="text-secondary small">
                                    <div class="mb-1"><?= htmlspecialchars($row['comment']) ?></div>
                                    <?php if (!empty($row['review_image']) && file_exists($row['review_image'])): ?>
                                        <div>
                                            <img src="<?= htmlspecialchars($row['review_image']) ?>" class="rounded border shadow-sm" style="width:45px; height:45px; object-fit:cover; cursor:pointer;" onclick="showReviewImage('<?= htmlspecialchars($row['review_image']) ?>', '<?= htmlspecialchars($row['fullname']) ?>')" title="คลิกเพื่อดูรูปภาพขนาดใหญ่">
                                        </div>
                                    <?php endif; ?>
                                </td>
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

    function showReviewImage(src, name) {
        Swal.fire({
            title: 'รูปภาพรีวิวจากคุณ ' + name,
            imageUrl: src,
            imageAlt: 'รีวิวสินค้า',
            confirmButtonText: 'ปิด',
            confirmButtonColor: 'var(--blue-hover)',
            customClass: {
                image: 'img-fluid rounded shadow-sm'
            }
        });
    }
</script>
</body>
</html>


