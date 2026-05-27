<?php
session_start();
include 'db.php';

// รับค่าจาก AJAX
$search = isset($_POST['q']) ? mysqli_real_escape_string($conn, $_POST['q']) : '';
$cat_id = isset($_POST['cat']) ? mysqli_real_escape_string($conn, $_POST['cat']) : '';

// สร้าง Query
$where = [];
if ($search) $where[] = "name LIKE '%$search%'";
if ($cat_id) $where[] = "category_id = '$cat_id'";

$sql_where = "";
if (count($where) > 0) $sql_where = "WHERE " . implode(' AND ', $where);

$query = "SELECT * FROM products $sql_where ORDER BY id DESC";
$result = mysqli_query($conn, $query);

// ถ้ามีสินค้า แสดงผลออกมาเป็น HTML
if(mysqli_num_rows($result) > 0):
    while ($p = mysqli_fetch_assoc($result)):
        $is_out = ($p['stock'] <= 0);
?>
<div class="col-6 col-md-3 animate__animated animate__fadeInUp">
    <div class="card card-product h-100">
        <div class="product-img-wrapper" style="position: relative; overflow: hidden;">
            <a href="product_detail.php?id=<?= $p['id'] ?>" class="text-decoration-none d-block w-100 h-100">
                <img src="<?= $p['image'] ?>" alt="<?= $p['name'] ?>" class="w-100 h-100" style="object-fit:cover;">
                <?php if($is_out): ?>
                    <div class="out-stock-overlay"><span class="badge-out">สินค้าหมด</span></div>
                <?php endif; ?>
            </a>
        </div>
        
        <div class="card-body d-flex flex-column text-center pt-3">
            <h6 class="fw-bold mb-1 text-truncate">
                <a href="product_detail.php?id=<?= $p['id'] ?>" class="product-name text-dark text-decoration-none">
                    <?= $p['name'] ?>
                </a>
            </h6>
            <div class="mt-auto">
                <div class="mb-2">
                    <span class="fw-bold" style="color:#85D1FF; font-size:1.2rem;">฿<?= number_format($p['price']) ?></span>
                    <?php if(!$is_out): ?>
                        <div class="text-muted small">คงเหลือ <?= $p['stock'] ?> ชิ้น</div>
                    <?php endif; ?>
                </div>
                <?php if($is_out): ?>
                    <button class="btn btn-secondary w-100 btn-sm rounded-pill py-1" disabled>สินค้าหมดแล้ว</button>
                <?php else: ?>
                    <?php if(!empty($p['options'])): ?>
                        <a href="product_detail.php?id=<?= $p['id'] ?>" class="btn btn-primary w-100 btn-sm shadow-sm position-relative py-1 rounded-pill" style="background-color: #85D1FF; border: none;">
                            <i class="bi bi-list-ul"></i> <span class="d-none d-md-inline">เลือกตัวเลือก</span><span class="d-inline d-md-none">เลือก</span>
                        </a>
                    <?php else: ?>
                        <button onclick="addToCart(<?= $p['id'] ?>)" class="btn btn-primary w-100 btn-sm shadow-sm position-relative py-1 rounded-pill" style="background-color: #85D1FF; border: none;">
                            <i class="bi bi-cart-plus"></i> <span class="d-none d-md-inline">ใส่ตะกร้า</span><span class="d-inline d-md-none">ซื้อ</span>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endwhile; else: ?>
    <div class="col-12 text-center py-5">
        <div class="py-5">
            <i class="bi bi-search display-1 text-muted opacity-25"></i>
            <h4 class="text-muted mt-3">ไม่พบสินค้าในหมวดหมู่นี้</h4>
        </div>
    </div>
<?php endif; ?>