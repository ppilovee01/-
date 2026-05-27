<?php
session_start();
include 'db.php';
include 'header.php'; // ใน header.php ตเนอเธมี bootstrap.bundle.min.js เธะเธรัเธ

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
    <title>รายการเนเธรด | Por Mae Bet Taled</title>
    <style>
        body { background-color: #f8f9fa; font-family: 'Kanit', sans-serif; }
        .wishlist-card { background: white; border: none; border-radius: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); transition: all 0.3s ease; position: relative; overflow: hidden; height: 100%; }
        .img-zoom-container { height: 200px; overflow: hidden; position: relative; background: #fff; display: flex; align-items: center; justify-content: center; }
        .wishlist-img { width: 100%; height: 100%; object-fit: cover; transition: 0.5s; }
        .wishlist-card:hover .wishlist-img { transform: scale(1.08); }
        .btn-remove-circle { position: absolute; top: 10px; right: 10px; width: 32px; height: 32px; background: rgba(255, 255, 255, 0.9); border-radius: 50%; border: none; color: #999; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; z-index: 5; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .btn-remove-circle:hover { background: #ff4d4f; color: white; }
        .card-body-custom { padding: 15px; text-align: center; }
        .product-title { font-size: 0.95rem; font-weight: 500; color: #333; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .product-price { font-size: 1.1rem; font-weight: 700; color: #AEE2FF; margin-bottom: 15px; }
        .btn-action-main { background: linear-gradient(135deg, #333 0%, #000 100%); color: white; border: none; border-radius: 50px; padding: 8px 0; width: 100%; font-size: 0.85rem; font-weight: 500; display: block; text-decoration: none; transition: 0.3s; }
    </style>
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
                <i class="bi bi-list me-2"></i> เมนูสมาชิก (เธดเเธืเนอเเธิด)
            </a>
            
            <div class="collapse d-lg-block" id="userSidebar">
                <?php include 'user_sidebar.php'; ?>
            </div>
        </div>

        <div class="col-lg-9">
            <h3 class="fw-bold mb-4" style="color: #333;">๐’– รายการที่เธัเธชอบ</h3>

            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="row g-3">
                    <?php while ($p = mysqli_fetch_assoc($result)): ?>
                    <div class="col-6 col-md-4">
                        <div class="wishlist-card">
                            <button onclick="toggleFeature('toggle_wishlist', <?= $p['id'] ?>, this); this.closest('.col-6').remove();" class="btn-remove-circle"><i class="bi bi-x-lg"></i></button>
                            <a href="product_detail.php?id=<?= $p['id'] ?>">
                                <div class="img-zoom-container"><img src="<?= $p['image'] ?>" class="wishlist-img"></div>
                            </a>
                            <div class="card-body-custom">
                                <div class="product-title"><?= $p['name'] ?></div>
                                <div class="product-price">฿<?= number_format($p['price']) ?></div>
                                <a href="product_detail.php?id=<?= $p['id'] ?>" class="btn-action-main"><i class="bi bi-bag"></i> 价ี่สินค้า</a>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 bg-white rounded-4 shadow-sm">
                    <h5 class="text-dark fw-bold">ยัเธเนมเนมีสินค้าที่ถูเธเนเธ</h5>
                    <a href="index.php" class="btn btn-dark rounded-pill px-4 mt-3">ไปเธเนอเธเลย</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleFeature(action, pid) {
    let fd = new FormData(); fd.append('action', action); fd.append('product_id', pid);
    fetch('ajax_features.php', { method: 'POST', body: fd }).then(r=>r.json()).then(data => {
        const Toast = Swal.mixin({toast: true, position: 'top-end', showConfirmButton: false, timer: 1000});
        Toast.fire({icon: 'success', title: 'ลเธเรียเธรเนอย'});
    });
}
</script>

</body>
</html>


