<?php
ob_start();
session_start();
include 'db.php';

if (!isset($_GET['id'])) { header("Location: index.php"); exit(); }
$id = mysqli_real_escape_string($conn, $_GET['id']);

$sql = "SELECT * FROM products WHERE id = '$id'";
$result = mysqli_query($conn, $sql);
$product = mysqli_fetch_assoc($result);

if (!$product) { header("Location: index.php"); exit(); }

// --- Logic Recently Viewed Products ---
$recently_viewed = [];
if (isset($_COOKIE['recently_viewed'])) {
    $recently_viewed = json_decode($_COOKIE['recently_viewed'], true);
    if (!is_array($recently_viewed)) {
        $recently_viewed = [];
    }
}
if (($key = array_search($id, $recently_viewed)) !== false) {
    unset($recently_viewed[$key]);
}
array_unshift($recently_viewed, $id);
$recently_viewed = array_slice($recently_viewed, 0, 10);
setcookie('recently_viewed', json_encode($recently_viewed), time() + (86400 * 30), "/");


// --- Logic Wishlist Check ---
$is_fav = false;
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $check_fav = mysqli_query($conn, "SELECT id FROM wishlist WHERE user_id='$uid' AND product_id='$id'");
    if (mysqli_num_rows($check_fav) > 0) { $is_fav = true; }
}
$fav_class = $is_fav ? 'liked' : '';
$fav_icon = $is_fav ? 'bi-heart-fill' : 'bi-heart';

// --- Logic Review (Anti-F5 Fixed) ---
if (isset($_POST['submit_review']) && isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $rating = intval($_POST['rating']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    
    $review_image = null;
    if (isset($_FILES['review_image']) && $_FILES['review_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['review_image']['tmp_name'];
        $fileName = $_FILES['review_image']['name'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'webp');
        if (in_array($fileExtension, $allowedfileExtensions)) {
            $newFileName = 'review_' . uniqid() . '.' . $fileExtension;
            $uploadFileDir = 'uploads/';
            
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            
            $dest_path = $uploadFileDir . $newFileName;
            if(move_uploaded_file($fileTmpPath, $dest_path)) {
                $review_image = $dest_path;
            }
        }
    }
    
    $review_image_val = $review_image ? "'" . mysqli_real_escape_string($conn, $review_image) . "'" : "NULL";
    $sql_review = "INSERT INTO product_reviews (product_id, user_id, rating, comment, image) VALUES ('$id', '$uid', '$rating', '$comment', $review_image_val)";
    
    if(mysqli_query($conn, $sql_review)) {
         $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'ขอบคุณสำหรับการรีวิว!', 'icon'=>'success'];
    } else {
         $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>mysqli_error($conn), 'icon'=>'error'];
    }
    header("Location: product_detail.php?id=$id"); exit();
}

$can_review = false;
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $check_order = mysqli_query($conn, "SELECT o.id FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE o.user_id = '$uid' AND oi.product_id = '$id' AND o.status IN ('shipping', 'completed', 'approved')");
    $check_reviewed = mysqli_query($conn, "SELECT id FROM product_reviews WHERE user_id = '$uid' AND product_id = '$id'");
    if (mysqli_num_rows($check_order) > 0 && mysqli_num_rows($check_reviewed) == 0) { $can_review = true; }
}

$reviews = mysqli_query($conn, "SELECT r.*, u.fullname FROM product_reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = '$id' ORDER BY r.created_at DESC");
$avg_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(rating) as avg, COUNT(*) as count FROM product_reviews WHERE product_id = '$id'"));
$avg_rating = $avg_data['avg'] ? round($avg_data['avg'], 1) : 0;
$review_count = $avg_data['count'];

$page_title = $product['name'] . " | Por Mae Bet Taled";
$extra_css = "
<style>
    .badge-cart { background-color: var(--blue-main) !important; color: #fff; border: 2px solid white; }
    .product-img-container { border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); background: white; padding: 30px; text-align: center; border: 1px solid rgba(226, 232, 240, 0.8); }
    .product-img { max-width: 100%; height: auto; transition: var(--transition-smooth); border-radius: var(--radius-md); }
    .product-img:hover { transform: scale(1.02); }
    h1.product-title { color: var(--slate-dark); font-weight: 700; letter-spacing: -0.5px; }
    .price-tag { color: var(--blue-hover); font-weight: 800; font-size: 2.2rem; }
    .option-group { margin-bottom: 24px; }
    .option-label { font-weight: 600; font-size: 0.95rem; margin-bottom: 12px; display: block; color: var(--slate-dark); }
    
    .btn-option { border: 1px solid #E2E8F0; background: white; color: var(--text-secondary); padding: 8px 22px; margin-right: 8px; margin-bottom: 8px; border-radius: var(--radius-sm); cursor: pointer; transition: var(--transition-smooth); font-weight: 500; }
    .btn-option:hover { border-color: var(--blue-hover); color: var(--blue-hover); }
    .btn-check:checked + .btn-option { background-color: var(--blue-hover); color: white; border-color: var(--blue-hover); font-weight: 600; box-shadow: 0 4px 12px rgba(127, 181, 255, 0.3); }
    
    .btn-add-cart { background: linear-gradient(135deg, var(--blue-main) 0%, var(--blue-hover) 100%); color: white; border: none; border-radius: 50px; padding: 14px 30px; font-weight: 600; font-size: 1.1rem; box-shadow: 0 8px 20px rgba(174, 226, 255, 0.4); transition: var(--transition-smooth); flex-grow: 1; }
    .btn-add-cart:hover { transform: translateY(-2px); box-shadow: 0 12px 25px rgba(127, 181, 255, 0.55); color: white; }
    .btn-add-cart:disabled { background: #cbd5e1; cursor: not-allowed; transform: none; box-shadow: none; }
    
    .qty-input-group { width: 130px; border-radius: 50px; overflow: hidden; border: 1px solid #E2E8F0; background: white; padding: 2px; }
    .btn-qty { border: none; background: white; color: var(--text-secondary); width: 36px; font-weight: bold; transition: var(--transition-smooth); }
    .btn-qty:hover { background: var(--blue-light); color: var(--blue-hover); }
    .form-control-qty { border: none; text-align: center; font-weight: 700; color: var(--text-primary); width: 50px; background: white; }
    
    .btn-icon-action { width: 54px; height: 54px; border-radius: 50%; border: 1px solid #E2E8F0; background: white; color: var(--text-secondary); display: flex; align-items: center; justify-content: center; transition: var(--transition-smooth); font-size: 1.25rem; cursor: pointer; box-shadow: var(--shadow-sm); }
    .btn-icon-action:hover { border-color: #ff5e84; color: #ff5e84; background: #FFF5F7; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(255, 94, 132, 0.15); }
    .btn-icon-action.liked { background: #fff; color: #ff5e84; border-color: #ff5e84; }
    
    .detail-box { background: white; border-radius: var(--radius-md); padding: 40px; box-shadow: var(--shadow-sm); margin-top: 50px; border: 1px solid rgba(226, 232, 240, 0.8); }
    .nav-tabs { border-bottom: 2px solid #E2E8F0; }
    .nav-tabs .nav-link { color: var(--text-secondary); border: none; font-weight: 500; padding-bottom: 15px; margin-right: 20px; font-size: 1.1rem; }
    .review-item { border-bottom: 1px solid #E2E8F0; padding-bottom: 20px; margin-bottom: 20px; }
    .star-rating i { color: #FFC107; font-size: 0.9rem; }
    .review-img-thumb { width: 100px; height: 100px; object-fit: cover; border-radius: var(--radius-sm); cursor: pointer; border: 1px solid rgba(226, 232, 240, 0.8); transition: var(--transition-smooth); margin-top: 10px; }
    .review-img-thumb:hover { transform: scale(1.05); box-shadow: var(--shadow-md); }
</style>
";
include 'header.php';
?>

<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">หน้าแรก</a></li>
            <li class="breadcrumb-item"><a href="index.php#shop" class="text-decoration-none text-muted">สินค้า</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= $product['name'] ?></li>
        </ol>
    </nav>

    <div class="row g-5">
        <div class="col-lg-6 animate__animated animate__fadeInLeft">
            <div class="product-img-container">
                <img src="<?= $product['image'] ?>" alt="<?= $product['name'] ?>" class="product-img">
            </div>
        </div>

        <div class="col-lg-6 animate__animated animate__fadeInRight">
            <?php 
            $active_fs = getActiveFlashSale($conn, $id); 
            if ($active_fs !== null): 
                $pct = $active_fs['flash_stock'] > 0 ? ($active_fs['flash_sold'] / $active_fs['flash_stock']) * 100 : 0;
                if ($pct > 100) $pct = 100;
                $remaining = max(0, $active_fs['flash_stock'] - $active_fs['flash_sold']);
            ?>
                <div class="alert border-0 rounded-4 mb-3 p-3 text-white d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 animate__animated animate__pulse" style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); box-shadow: 0 4px 15px rgba(220, 53, 69, 0.2);">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-lightning-charge-fill animate__animated animate__flash animate__infinite animate__slower" style="font-size: 1.25rem;"></i>
                        <div>
                            <span class="fw-bold d-block">⚡ FLASH SALE โปรโมชันพิเศษ</span>
                            <small style="opacity: 0.9;">ขายแล้ว <?= $active_fs['flash_sold'] ?> ชิ้น | เหลือโควตา <?= $remaining ?> ชิ้น</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <small class="text-uppercase fw-bold" style="font-size: 0.75rem;">หมดเวลาใน</small>
                        <div class="d-flex gap-1 align-items-center">
                            <span class="badge bg-dark text-white px-2 py-1" id="fs-hours">00</span>:
                            <span class="badge bg-dark text-white px-2 py-1" id="fs-mins">00</span>:
                            <span class="badge bg-dark text-white px-2 py-1" id="fs-secs">00</span>
                        </div>
                    </div>
                </div>
                
                <script>
                    (function() {
                        var endTime = new Date("<?= date('Y-m-d\TH:i:s', strtotime($active_fs['end_time'])) ?>").getTime();
                        function updateTimer() {
                            var now = new Date().getTime();
                            var distance = endTime - now;
                            if (distance < 0) {
                                clearInterval(x);
                                location.reload();
                                return;
                            }
                            var hours = Math.floor(distance / (1000 * 60 * 60));
                            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                            var seconds = Math.floor((distance % (1000 * 60)) / 1000);
                            
                            hours = (hours < 10) ? "0" + hours : hours;
                            minutes = (minutes < 10) ? "0" + minutes : minutes;
                            seconds = (seconds < 10) ? "0" + seconds : seconds;
                            
                            document.getElementById("fs-hours").innerText = hours;
                            document.getElementById("fs-mins").innerText = minutes;
                            document.getElementById("fs-secs").innerText = seconds;
                        }
                        updateTimer();
                        var x = setInterval(updateTimer, 1000);
                    })();
                </script>
            <?php endif; ?>

            <h1 class="product-title display-6 mb-2"><?= $product['name'] ?></h1>
            
            <div class="d-flex align-items-center mb-4">
                <div class="star-rating me-2">
                    <?php for($i=1; $i<=5; $i++) echo $i <= $avg_rating ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star text-muted opacity-25"></i>'; ?>
                </div>
                <span class="text-muted small">(<?= $review_count ?> รีวิว)</span>
            </div>

            <div class="d-flex align-items-center gap-3 mb-4">
                <?php if ($active_fs !== null): ?>
                    <span class="price-tag text-danger">฿<?= number_format($active_fs['flash_price']) ?></span>
                    <span class="text-muted text-decoration-line-through fs-5 mt-2">฿<?= number_format($product['price']) ?></span>
                <?php else: ?>
                    <span class="price-tag">฿<?= number_format($product['price']) ?></span>
                <?php endif; ?>
                <?php if($product['stock'] > 0): ?>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-3">มีสินค้า <?= $product['stock'] ?> ชิ้น</span>
                <?php else: ?>
                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger rounded-pill px-3">สินค้าหมด</span>
                <?php endif; ?>
            </div>

            <form id="addCartForm" onsubmit="return false;">
                <input type="hidden" name="product_id" value="<?= $id ?>">
                <input type="hidden" name="qty" id="qty_val" value="1">
                <input type="hidden" name="action" value="add">
                
                <?php 
                if (!empty($product['options'])): 
                    $all_opts = explode('|', $product['options']);
                    foreach ($all_opts as $opt_group):
                        $parts = explode(':', $opt_group);
                        if(count($parts) == 2):
                            $opt_name = trim($parts[0]);
                            $opt_values = explode(',', trim($parts[1]));
                ?>
                <div class="option-group">
                    <label class="option-label"><?= $opt_name ?></label>
                    <div class="d-flex flex-wrap">
                        <?php foreach($opt_values as $k => $val): $val = trim($val); ?>
                            <input type="radio" class="btn-check option-input" name="options[<?= $opt_name ?>]" id="opt_<?= $opt_name ?>_<?= $k ?>" value="<?= $val ?>" required>
                            <label class="btn-option" for="opt_<?= $opt_name ?>_<?= $k ?>"><?= $val ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; endforeach; endif; ?>

                <div class="d-flex align-items-center gap-3 mt-4">
                    <div class="qty-input-group d-flex">
                        <button type="button" class="btn-qty" onclick="changeQty(-1)">-</button>
                        <input type="text" name="quantity" id="qty_display" value="1" class="form-control-qty" readonly>
                        <button type="button" class="btn-qty" onclick="changeQty(1)">+</button>
                    </div>
                    
                    <?php if($product['stock'] > 0): ?>
                        <button type="button" class="btn-add-cart" onclick="submitCart()">
                            <i class="bi bi-cart-plus me-2"></i> เพิ่มลงตะกร้า
                        </button>
                    <?php else: ?>
                        <button class="btn-add-cart" disabled>สินค้าหมดชั่วคราว</button>
                    <?php endif; ?>

                    <button type="button" class="btn-icon-action <?= $fav_class ?>" onclick="toggleFeature('toggle_wishlist', <?= $id ?>, this)" title="เก็บลงรายการโปรด">
                        <i class="bi <?= $fav_icon ?>"></i>
                    </button>
                </div>
            </form>

            <div class="mt-4 pt-4 border-top d-flex gap-4 text-secondary small">
                <div><i class="bi bi-truck text-primary me-1"></i> จัดส่งฟรีทั่วไทย</div>
                <div><i class="bi bi-shield-check text-success me-1"></i> ของแท้ 100%</div>
                <div><i class="bi bi-arrow-return-left text-danger me-1"></i> คืนสินค้าได้ใน 7 วัน</div>
            </div>
        </div>
    </div>

    <div class="detail-box animate__animated animate__fadeInUp">
        <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#desc">รายละเอียดสินค้า</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#review">รีวิวจากผู้ซื้อ (<?= $review_count ?>)</button></li>
        </ul>
        
        <div class="tab-content">
            <div class="tab-pane fade show active" id="desc">
                <div class="text-secondary lh-lg" style="font-size: 1.05rem;">
                    <?= nl2br($product['description']) ?>
                </div>
            </div>
            
            <div class="tab-pane fade" id="review">
                <?php if ($can_review): ?>
                <div class="bg-light p-4 rounded-3 mb-4 border shadow-sm">
                    <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-pencil-square me-2 text-primary"></i>เขียนรีวิวสินค้า</h6>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="small text-muted mb-1 d-block fw-bold">ความพึงพอใจ</label>
                                <select name="rating" class="form-select border-0 shadow-sm rounded-3">
                                    <option value="5">⭐⭐⭐⭐⭐ (ดีเยี่ยม)</option>
                                    <option value="4">⭐⭐⭐⭐ (ดี)</option>
                                    <option value="3">⭐⭐⭐ (ปานกลาง)</option>
                                    <option value="2">⭐⭐ (พอใช้)</option>
                                    <option value="1">⭐ (แย่)</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="small text-muted mb-1 d-block fw-bold">ความคิดเห็น</label>
                                <textarea name="comment" class="form-control border-0 shadow-sm rounded-3" rows="1" placeholder="บอกเล่าประสบการณ์ใช้งาน..." required></textarea>
                            </div>
                            <div class="col-12 col-md-8 offset-md-4 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                                <div class="flex-grow-1">
                                    <label class="small text-muted mb-1 d-block fw-bold"><i class="bi bi-camera me-1"></i>แนบรูปภาพสินค้า (รูปถ่ายจริง)</label>
                                    <input type="file" name="review_image" class="form-control form-control-sm border-0 shadow-sm rounded-3" accept="image/*">
                                </div>
                                <div class="text-end pt-sm-4">
                                    <button type="submit" name="submit_review" class="btn btn-blue rounded-pill px-4 text-white fw-bold">ส่งรีวิว</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- ตัวกรองรีวิว -->
                <div class="card border-0 shadow-sm rounded-3 p-3 mb-4 bg-white animate__animated animate__fadeIn">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="small text-muted fw-bold me-2" style="font-size: 0.8rem;"><i class="bi bi-filter text-primary"></i> คะแนนรีวิว:</span>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 active filter-star-btn" data-rating="all" onclick="setStarFilter(this)" style="font-size: 0.75rem;">ทั้งหมด</button>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 filter-star-btn" data-rating="5" onclick="setStarFilter(this)" style="font-size: 0.75rem;">5 ดาว</button>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 filter-star-btn" data-rating="4" onclick="setStarFilter(this)" style="font-size: 0.75rem;">4 ดาว</button>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 filter-star-btn" data-rating="3" onclick="setStarFilter(this)" style="font-size: 0.75rem;">3 ดาว</button>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 filter-star-btn" data-rating="2" onclick="setStarFilter(this)" style="font-size: 0.75rem;">2 ดาว</button>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 filter-star-btn" data-rating="1" onclick="setStarFilter(this)" style="font-size: 0.75rem;">1 ดาว</button>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="filter-has-image-chk" onchange="toggleImageFilter()">
                            <label class="form-check-label small text-muted fw-bold" for="filter-has-image-chk" style="font-size: 0.8rem; cursor: pointer;"><i class="bi bi-image text-secondary me-1"></i>เฉพาะที่มีรูปภาพ</label>
                        </div>
                    </div>
                </div>

                <div id="reviews-list-container" style="transition: opacity 0.2s ease;">
                    <?php if(mysqli_num_rows($reviews) > 0): while($r = mysqli_fetch_assoc($reviews)): ?>
                    <div class="review-item animate__animated animate__fadeIn">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div>
                                <strong class="text-dark me-2"><?= $r['fullname'] ?></strong>
                                <span class="text-warning small">
                                    <?php for($i=1;$i<=5;$i++) echo $i<=$r['rating'] ? '★' : '☆'; ?>
                                </span>
                            </div>
                            <small class="text-muted" style="font-size:0.8rem;"><?= date('d/m/Y', strtotime($r['created_at'])) ?></small>
                        </div>
                        <p class="mb-2 text-secondary"><?= $r['comment'] ?></p>
                        <?php if(!empty($r['image']) && file_exists($r['image'])): ?>
                            <div class="mt-2">
                                <img src="<?= htmlspecialchars($r['image']) ?>" class="review-img-thumb img-thumbnail" onclick="showReviewImage('<?= htmlspecialchars($r['image']) ?>', '<?= htmlspecialchars($r['fullname']) ?>')" alt="รูปรีวิว">
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; else: ?>
                        <div class="text-center py-5 text-muted opacity-50">
                            <i class="bi bi-chat-square-quote display-3 d-block mb-3"></i>
                            ยังไม่มีรีวิวสำหรับสินค้านี้
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- สินค้าแนะนำสำหรับคุณ (Recommended Products) -->
<?php
$cat_id = $product['category_id'];
$recommended_products = [];
$recommended_ids = [];

// 1. ดึงสินค้าหมวดหมู่เดียวกันก่อน (ยกเว้นสินค้าตัวปัจจุบัน)
$rel_query = mysqli_query($conn, "SELECT p.*, 
    (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id) as avg_rating,
    (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as review_count
    FROM products p 
    WHERE p.category_id = '$cat_id' AND p.id != '$id' 
    LIMIT 4");

while ($p = mysqli_fetch_assoc($rel_query)) {
    $recommended_products[] = $p;
    $recommended_ids[] = $p['id'];
}

// 2. ถ้าหากสินค้าใกล้เคียงยังมีไม่ครบ 4 ชิ้น ให้ดึงสินค้าขายดีจากร้านมาเติมให้เต็ม
$count_fetched = count($recommended_products);
if ($count_fetched < 4) {
    $needed = 4 - $count_fetched;
    $not_in_clause = "";
    if (!empty($recommended_ids)) {
        $not_in_clause = "AND p.id NOT IN ('" . implode("','", $recommended_ids) . "')";
    }
    
    // ดึงสินค้าขายดีที่สุด (ยอดขายรวมจาก order_items) หรือเรียงตามคะแนนดาว/รหัสสินค้าถ้าไม่มียอดขาย
    $best_query = mysqli_query($conn, "SELECT p.*, 
        (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id) as avg_rating,
        (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as review_count,
        (SELECT IFNULL(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.product_id = p.id) as sales_volume
        FROM products p 
        WHERE p.id != '$id' $not_in_clause 
        ORDER BY sales_volume DESC, p.id DESC 
        LIMIT $needed");
        
    while ($p = mysqli_fetch_assoc($best_query)) {
        $recommended_products[] = $p;
    }
}

if (!empty($recommended_products)):
?>
<div class="container pb-4 animate__animated animate__fadeInUp">
    <div class="border-top pt-5">
        <h3 class="fw-bold mb-4 text-dark" style="font-size: 1.5rem;">สินค้าแนะนำสำหรับคุณ <span style="color:var(--blue-hover);">✨</span></h3>
        <div class="row g-3 g-md-4">
            <?php foreach ($recommended_products as $p): 
                $is_out = ($p['stock'] <= 0);
                $rating = $p['avg_rating'] ? round($p['avg_rating'], 1) : 0;
                $rv_count = $p['review_count'];
                
                // Wishlist check
                $is_fav = false;
                if (isset($_SESSION['user_id'])) {
                    $uid = $_SESSION['user_id'];
                    $check_fav = mysqli_query($conn, "SELECT id FROM wishlist WHERE user_id='$uid' AND product_id='{$p['id']}'");
                    if (mysqli_num_rows($check_fav) > 0) { $is_fav = true; }
                }
                $fav_class = $is_fav ? 'liked' : '';
                $fav_icon = $is_fav ? 'bi-heart-fill' : 'bi-heart';
            ?>
            <div class="col-6 col-md-3">
                <div class="card card-product">
                    <button onclick="toggleFeature('toggle_wishlist', <?= $p['id'] ?>, this)" class="wishlist-tag <?= $fav_class ?>" title="เก็บลงรายการโปรด">
                        <i class="bi <?= $fav_icon ?>"></i>
                    </button>
                    <?php $rec_fs = getActiveFlashSale($conn, $p['id']); ?>
                    <div class="product-img-wrapper">
                        <a href="product_detail.php?id=<?= $p['id'] ?>" class="text-decoration-none d-block w-100 h-100">
                            <img src="<?= $p['image'] ?>" alt="<?= $p['name'] ?>">
                            <?php if($rec_fs !== null): ?>
                                <div class="position-absolute top-0 start-0 m-2 bg-danger text-white px-2 py-1 rounded-3 fw-bold small z-3" style="font-size: 0.75rem;">⚡ FLASH</div>
                            <?php endif; ?>
                            <?php if($is_out): ?>
                                <div class="out-stock-overlay"><span class="badge-out">สินค้าหมด</span></div>
                            <?php endif; ?>
                        </a>
                    </div>
                    
                    <div class="card-body d-flex flex-column text-center pt-0">
                        <h6 class="fw-bold mb-1 text-truncate mt-3">
                            <a href="product_detail.php?id=<?= $p['id'] ?>" class="product-name stretched-link">
                                <?= $p['name'] ?>
                            </a>
                        </h6>
                        <div class="small text-warning mb-2">
                            <?php for($i=1; $i<=5; $i++) echo $i<=$rating ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star text-muted opacity-25"></i>'; ?>
                            <span class="text-muted ms-1" style="font-size: 0.8rem;">(<?= $rv_count ?>)</span>
                        </div>
                        <div class="mt-auto">
                            <div class="mb-3">
                                <?php if ($rec_fs !== null): ?>
                                    <span class="fw-bold text-danger" style="font-size:1.2rem;">฿<?= number_format($rec_fs['flash_price']) ?></span>
                                    <span class="text-muted text-decoration-line-through small ms-1" style="font-size: 0.75rem;">฿<?= number_format($p['price']) ?></span>
                                <?php else: ?>
                                    <span class="fw-bold" style="color:var(--blue-dark); font-size:1.2rem;">฿<?= number_format($p['price']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if($is_out): ?>
                                <button class="btn btn-secondary w-100 btn-sm rounded-pill py-2" disabled>สินค้าหมด</button>
                            <?php else: ?>
                                <a href="product_detail.php?id=<?= $p['id'] ?>" class="btn btn-gradient w-100 btn-sm shadow-sm position-relative py-2" style="z-index:2;">
                                    <i class="bi bi-cart-plus"></i> เลือก
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- สินค้าที่ดูล่าสุด (Recently Viewed Products) -->
<?php
$display_recently_viewed = array_filter($recently_viewed, function($val) use ($id) {
    return $val != $id;
});

if (!empty($display_recently_viewed)):
    $ids_string = implode("','", array_map(function($val) use ($conn) {
        return mysqli_real_escape_string($conn, $val);
    }, $display_recently_viewed));

    $rv_query = mysqli_query($conn, "SELECT p.*, 
        (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id) as avg_rating,
        (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as review_count
        FROM products p WHERE p.id IN ('$ids_string')");

    $rv_products = [];
    while ($p = mysqli_fetch_assoc($rv_query)) {
        $rv_products[$p['id']] = $p;
    }

    $ordered_rv_products = [];
    foreach ($display_recently_viewed as $rv_id) {
        if (isset($rv_products[$rv_id])) {
            $ordered_rv_products[] = $rv_products[$rv_id];
        }
    }

    if (!empty($ordered_rv_products)):
?>
<div class="container pb-5 animate__animated animate__fadeInUp">
    <div class="border-top pt-5">
        <h3 class="fw-bold mb-4 text-dark" style="font-size: 1.5rem;">สินค้าที่คุณดูล่าสุด <span style="color:var(--blue-hover);">👀</span></h3>
        <div class="scroll-menu py-2 px-1" style="display: flex; flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; gap: 20px;">
            <?php foreach ($ordered_rv_products as $p): 
                $is_out = ($p['stock'] <= 0);
                $rating = $p['avg_rating'] ? round($p['avg_rating'], 1) : 0;
                $rv_count = $p['review_count'];
                
                // Wishlist check
                $is_fav = false;
                if (isset($_SESSION['user_id'])) {
                    $uid = $_SESSION['user_id'];
                    $check_fav = mysqli_query($conn, "SELECT id FROM wishlist WHERE user_id='$uid' AND product_id='{$p['id']}'");
                    if (mysqli_num_rows($check_fav) > 0) { $is_fav = true; }
                }
                $fav_class = $is_fav ? 'liked' : '';
                $fav_icon = $is_fav ? 'bi-heart-fill' : 'bi-heart';
            ?>
            <div style="flex: 0 0 auto; width: 220px; height: 300px; position: relative;">
                <div class="card card-product h-100 shadow-sm border-0" style="background: #fff; border-radius: var(--radius-md); overflow: hidden; transition: var(--transition-smooth);">
                    <button onclick="toggleFeature('toggle_wishlist', <?= $p['id'] ?>, this)" class="wishlist-tag <?= $fav_class ?>" title="เก็บลงรายการโปรด" style="position: absolute; top: 12px; right: 12px; z-index: 5;">
                        <i class="bi <?= $fav_icon ?>"></i>
                    </button>
                    <div class="product-img-wrapper" style="height: 160px; display: flex; align-items: center; justify-content: center; background: #fff; border-bottom: 1px solid rgba(226, 232, 240, 0.4);">
                        <a href="product_detail.php?id=<?= $p['id'] ?>" class="text-decoration-none d-flex align-items-center justify-content-center w-100 h-100">
                            <img src="<?= $p['image'] ?>" alt="<?= $p['name'] ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                            <?php $rv_fs = getActiveFlashSale($conn, $p['id']); ?>
                            <?php if($rv_fs !== null): ?>
                                <div class="position-absolute top-0 start-0 m-2 bg-danger text-white px-2 py-1 rounded-3 fw-bold small z-3" style="font-size: 0.65rem;">⚡ FLASH</div>
                            <?php endif; ?>
                            <?php if($is_out): ?>
                                <div class="out-stock-overlay" style="border-radius: var(--radius-md) var(--radius-md) 0 0;"><span class="badge-out" style="font-size: 0.75rem; padding: 4px 10px;">สินค้าหมด</span></div>
                            <?php endif; ?>
                        </a>
                    </div>
                    <div class="card-body d-flex flex-column text-center p-3">
                        <h6 class="fw-bold mb-1 text-truncate mt-1">
                            <a href="product_detail.php?id=<?= $p['id'] ?>" class="product-name text-decoration-none text-dark" style="font-size: 0.9rem; font-weight: 600;">
                                <?= $p['name'] ?>
                            </a>
                        </h6>
                        <div class="small text-warning mb-2">
                            <?php for($i=1; $i<=5; $i++) echo $i<=$rating ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star text-muted opacity-25"></i>'; ?>
                            <span class="text-muted ms-1" style="font-size: 0.75rem;">(<?= $rv_count ?>)</span>
                        </div>
                        <div class="mt-auto">
                            <span class="fw-bold" style="color:var(--blue-hover); font-size:1.1rem;">฿<?= number_format($p['price']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php 
    endif;
endif; 
?>

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
<script>
    function changeQty(n) {
        let q = parseInt(document.getElementById('qty_display').value) + n;
        if(q >= 1 && q <= <?= $product['stock'] ?>) {
            document.getElementById('qty_display').value = q;
            document.getElementById('qty_val').value = q;
        }
    }

    let isCartSubmitting = false;
    function submitCart() {
        if (isCartSubmitting) return;

        const form = document.getElementById('addCartForm');
        const options = form.querySelectorAll('input[type="radio"]');
        let groups = {}; 
        options.forEach(opt => { 
            let name = opt.name;
            if(!(name in groups)) groups[name] = false;
        }); 
        options.forEach(opt => { if(opt.checked) groups[opt.name] = true; }); 

        for (let key in groups) {
            if (!groups[key]) {
                let cleanName = key.match(/\[(.*?)\]/)[1];
                Swal.fire({icon: 'warning', title: 'กรุณาเลือก ' + cleanName, confirmButtonColor: '#222'});
                return;
            }
        }

        let selectedOpts = [];
        options.forEach(opt => {
            if(opt.checked) {
                let optName = opt.name.match(/\[(.*?)\]/)[1];
                selectedOpts.push(optName + ": " + opt.value);
            }
        });
        
        let formData = new FormData(form);
        if(!formData.has('action')) formData.append('action', 'add');
        formData.append('options', selectedOpts.join(', '));

        isCartSubmitting = true;
        const submitBtn = form.querySelector('.btn-add-cart');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังเพิ่ม...';
        }

        fetch('ajax.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            isCartSubmitting = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-cart-plus me-2"></i> เพิ่มลงตะกร้า';
            }
            if(data.status === 'success') {
                const badge = document.getElementById('nav-cart-badge'); 
                if(badge) {
                    badge.innerText = data.cart_count;
                    badge.classList.remove('hidden'); 
                }
                // เปิดตะกร้าสไลด์ข้างทันทีเพื่อตอบสนองการกระทำของลูกค้า
                if (typeof window.toggleCartDrawer === 'function') {
                    const drawer = document.getElementById('cartDrawer');
                    if (drawer && !drawer.classList.contains('show')) {
                        window.toggleCartDrawer();
                    } else {
                        window.loadCartDrawer();
                    }
                } else {
                    const Toast = Swal.mixin({toast: true, position: 'top-end', showConfirmButton: false, timer: 1500, timerProgressBar: true});
                    Toast.fire({icon: 'success', title: 'เพิ่มลงตะกร้าแล้ว'});
                }
            } else {
                Swal.fire({icon: 'error', title: 'เกิดข้อผิดพลาด', text: data.message});
            }
        })
        .catch(error => {
            isCartSubmitting = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-cart-plus me-2"></i> เพิ่มลงตะกร้า';
            }
            console.error('Error:', error);
            Swal.fire({icon: 'error', title: 'Error', text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้'});
        });
    }

    // ✅ ฟังก์ชัน Wishlist ที่อัปเดต UI ทันที
    function toggleFeature(action, pid, btn) {
        let fd = new FormData(); 
        fd.append('action', action); 
        fd.append('product_id', pid);
        
        fetch('ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if(data.status === 'success') {
                if (action === 'toggle_wishlist') {
                    const icon = btn.querySelector('i');
                    if (data.state === 'added') {
                        btn.classList.add('liked');
                        icon.classList.remove('bi-heart');
                        icon.classList.add('bi-heart-fill');
                    } else {
                        btn.classList.remove('liked');
                        icon.classList.remove('bi-heart-fill');
                        icon.classList.add('bi-heart');
                    }
                }
                
                Swal.fire({
                    icon: 'success', 
                    title: data.message, 
                    toast: true, 
                    position: 'top-end', 
                    showConfirmButton: false, 
                    timer: 1500
                });
            } else {
                Swal.fire('แจ้งเตือน', data.message, 'warning');
            }
        })
        .catch(err => console.error(err));
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

    let selectedRating = 'all';
    let hasImageOnly = 0;
    
    function setStarFilter(btn) {
        document.querySelectorAll('.filter-star-btn').forEach(b => {
            b.classList.remove('active');
        });
        btn.classList.add('active');
        selectedRating = btn.dataset.rating;
        fetchFilteredReviews();
    }
    
    function toggleImageFilter() {
        hasImageOnly = document.getElementById('filter-has-image-chk').checked ? 1 : 0;
        fetchFilteredReviews();
    }
    
    function fetchFilteredReviews() {
        const container = document.getElementById('reviews-list-container');
        if (container) {
            container.style.opacity = '0.4';
        }
        
        const pid = '<?= $product["id"] ?>';
        
        const fd = new FormData();
        fd.append('action', 'get_filtered_reviews');
        fd.append('product_id', pid);
        fd.append('rating', selectedRating);
        fd.append('has_image', hasImageOnly);
        
        fetch('ajax.php', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if (container) {
                container.style.opacity = '1';
                if (data.status === 'success') {
                    container.innerHTML = data.html;
                } else {
                    container.innerHTML = `
                        <div class="text-center py-5 text-danger opacity-75">
                            <i class="bi bi-exclamation-triangle display-4 d-block mb-2"></i>
                            <div>เกิดข้อผิดพลาดในการโหลดรีวิว</div>
                        </div>`;
                }
            }
        })
        .catch(err => {
            console.error(err);
            if (container) {
                container.style.opacity = '1';
                container.innerHTML = `
                    <div class="text-center py-5 text-danger opacity-75">
                        <i class="bi bi-exclamation-triangle display-4 d-block mb-2"></i>
                        <div>เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์</div>
                    </div>`;
            }
        });
    }
</script>

</body>
</html>


