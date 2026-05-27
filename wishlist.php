<?php
session_start();
include 'db.php';
include 'header.php'; // ใน header.php ต้องมี bootstrap.bundle.min.js นะครับ

if (!isset($_SESSION['user_id'])) { echo "<script>window.location='login.php';</script>"; exit(); }
$uid = $_SESSION['user_id'];

$sql = "SELECT p.*, w.created_at as added_date FROM wishlist w JOIN products p ON w.product_id = p.id WHERE w.user_id = '$uid' ORDER BY w.created_at DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการโปรด | Por Mae Bet Taled</title>
</head>
<body>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-3">
            <a class="btn btn-light w-100 d-lg-none mb-3 border shadow-sm fw-bold text-start" 
               data-bs-toggle="collapse" 
               href="#userSidebar" 
               role="button" 
               aria-expanded="false" 
               aria-controls="userSidebar">
                <i class="bi bi-list me-2"></i> เมนูสมาชิก (คลิกเพื่อเปิด)
            </a>
            
            <div class="collapse d-lg-block" id="userSidebar">
                <?php include 'user_sidebar.php'; ?>
            </div>
        </div>

        <div class="col-lg-9">
            <h3 class="fw-bold mb-4" style="color: #333;">💖 รายการที่ชื่นชอบ</h3>

            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="row g-3">
                    <?php while ($p = mysqli_fetch_assoc($result)): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="card card-product">
                            <button onclick="toggleFeature('toggle_wishlist', <?= $p['id'] ?>, this); this.closest('.col-6').remove();" class="wishlist-tag liked"><i class="bi bi-heart-fill"></i></button>
                            <div class="product-img-wrapper">
                                <a href="product_detail.php?id=<?= $p['id'] ?>">
                                    <img src="<?= $p['image'] ?>" class="wishlist-img">
                                </a>
                            </div>
                            <div class="card-body d-flex flex-column text-center mt-2 p-3 pt-0">
                                <h6 class="fw-bold mb-2 text-truncate">
                                    <a href="product_detail.php?id=<?= $p['id'] ?>" class="product-name stretched-link"><?= $p['name'] ?></a>
                                </h6>
                                <div class="mb-3">
                                    <span class="fw-bold" style="color:var(--blue-dark); font-size:1.2rem;">฿<?= number_format($p['price']) ?></span>
                                </div>
                                <a href="product_detail.php?id=<?= $p['id'] ?>" class="btn btn-gradient btn-sm py-2 position-relative" style="z-index:2;">
                                    <i class="bi bi-bag"></i> ดูสินค้า
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 bg-white rounded-4 shadow-sm">
                    <h5 class="text-dark fw-bold">ยังไม่มีสินค้าที่ถูกใจ</h5>
                    <a href="index.php" class="btn btn-dark rounded-pill px-4 mt-3">ไปช้อปเลย</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleFeature(action, pid) {
    let fd = new FormData(); fd.append('action', action); fd.append('product_id', pid);
    fetch('ajax.php', { method: 'POST', body: fd }).then(r=>r.json()).then(data => {
        const Toast = Swal.mixin({toast: true, position: 'top-end', showConfirmButton: false, timer: 1000});
        Toast.fire({icon: 'success', title: 'ลบเรียบร้อย'});
    });
}
</script>

</body>
</html>


