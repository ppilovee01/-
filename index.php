<?php
session_start();
include 'db.php'; 

// --- Welcome Promo Pop-up Session Logic ---
$show_welcome_popup = false;
$welcome_coupon = null;
if (!isset($_SESSION['welcome_popup_shown'])) {
    $shop_sett_q = mysqli_query($conn, "SELECT welcome_promo_enabled, welcome_promo_coupon FROM shop_settings WHERE id=1");
    $shop_sett = mysqli_fetch_assoc($shop_sett_q);
    
    $promo_enabled = isset($shop_sett['welcome_promo_enabled']) ? intval($shop_sett['welcome_promo_enabled']) : 1;
    $promo_coupon = $shop_sett['welcome_promo_coupon'] ?? '';
    
    if ($promo_enabled == 1) {
        $today = date('Y-m-d');
        if (!empty($promo_coupon)) {
            $promo_coupon_escaped = mysqli_real_escape_string($conn, $promo_coupon);
            $cp_q = mysqli_query($conn, "SELECT * FROM coupons WHERE code='$promo_coupon_escaped' AND status='active' AND expiry_date >= '$today' LIMIT 1");
        } else {
            // Auto mode: fetch the best coupon
            $cp_q = mysqli_query($conn, "SELECT * FROM coupons WHERE status='active' AND expiry_date >= '$today' ORDER BY (code = 'WELCOME100') DESC, discount_type DESC, discount_value DESC LIMIT 1");
        }
        
        if ($cp_q && mysqli_num_rows($cp_q) > 0) {
            $welcome_coupon = mysqli_fetch_assoc($cp_q);
            $show_welcome_popup = true;
            $_SESSION['welcome_popup_shown'] = true;
        }
    }
} 

// --- Helper for Filters URL Generation ---
function buildFilterUrl($paramsToUpdate) {
    $currentParams = $_GET;
    foreach ($paramsToUpdate as $key => $val) {
        if ($val === null) {
            unset($currentParams[$key]);
        } else {
            $currentParams[$key] = $val;
        }
    }
    // Remove empty parameters
    foreach ($currentParams as $key => $val) {
        if ($val === '') {
            unset($currentParams[$key]);
        }
    }
    return 'index.php?' . http_build_query($currentParams) . '#shop';
}

// --- Feedback Logic (Anti-F5 Fixed) ---
if (isset($_POST['send_feedback'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['swal'] = ['title'=>'แจ้งเตือน', 'text'=>'กรุณา Login ก่อนส่งความคิดเห็น', 'icon'=>'warning'];
    } else {
        $msg = mysqli_real_escape_string($conn, $_POST['message']);
        $uid = $_SESSION['user_id'];
        if(mysqli_query($conn, "INSERT INTO feedback (user_id, message) VALUES ('$uid', '$msg')")) {
            // Insert admin notification for feedback
            $u_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT fullname FROM users WHERE id='$uid'"));
            $fullname = mysqli_real_escape_string($conn, $u_data['fullname'] ?? 'ผู้ใช้งาน');
            $title = "ข้อเสนอแนะใหม่จากคุณ $fullname";
            $message = "คุณได้รับข้อเสนอแนะใหม่: " . mysqli_real_escape_string($conn, mb_strimwidth($msg, 0, 80, '...'));
            $url = "admin_feedback.php";
            mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, url, is_read, is_admin) VALUES (NULL, '$title', '$message', '$url', 0, 1)");

            $_SESSION['swal'] = ['title'=>'ขอบคุณครับ!', 'text'=>'เราได้รับข้อเสนอแนะของคุณแล้ว', 'icon'=>'success'];
        } else {
            $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>mysqli_error($conn), 'icon'=>'error'];
        }
    }
    header("Location: index.php"); exit();
}

// --- ดึงข้อมูล Wishlist ---
$wishlist_items = [];
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $w_query = mysqli_query($conn, "SELECT product_id FROM wishlist WHERE user_id = '$uid'");
    while($w = mysqli_fetch_assoc($w_query)) {
        $wishlist_items[] = $w['product_id'];
    }
}

// --- Query Data ---
$banners = [];
$res = mysqli_query($conn, "SELECT * FROM banners ORDER BY id DESC");
while ($b = mysqli_fetch_assoc($res)) $banners[] = $b;

$categories = [];
$res = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");
while ($c = mysqli_fetch_assoc($res)) $categories[] = $c;

$cart_count = isset($_SESSION['cart']) ? array_sum(is_array($_SESSION['cart']) ? array_column($_SESSION['cart'], 'qty') : $_SESSION['cart']) : 0;

$page_title = "Por Mae Bet Taled | แหล่งรวมสินค้าเบ็ดเตล็ด";
$extra_css = "
<style>
    /* Hero Section styling */
    .hero-section {
        background: linear-gradient(135deg, #F0F8FF 0%, #FFFFFF 100%);
        padding: 90px 0;
        border-bottom-left-radius: 60px;
        border-bottom-right-radius: 60px;
        position: relative;
        overflow: hidden;
    }
    .hero-section::before {
        content: '';
        position: absolute;
        top: -50px;
        left: -50px;
        width: 250px;
        height: 250px;
        border-radius: 50%;
        background: rgba(174, 226, 255, 0.25);
        filter: blur(50px);
        z-index: 1;
    }
    .hero-section::after {
        content: '';
        position: absolute;
        bottom: -80px;
        right: -80px;
        width: 350px;
        height: 350px;
        border-radius: 50%;
        background: rgba(127, 181, 255, 0.18);
        filter: blur(60px);
        z-index: 1;
    }
    .hero-section .container {
        position: relative;
        z-index: 2;
    }
    
    .carousel-item img { height: 400px; object-fit: cover; object-position: center; }
    
    @media (max-width: 991px) {
        .hero-section { padding: 50px 0; border-bottom-left-radius: 30px; border-bottom-right-radius: 30px; }
        .carousel-item img { height: 250px; }
        h1.display-4 { font-size: 2.2rem; }
    }

    /* Category scroll menu styling */
    .scroll-menu { 
        display: flex; 
        overflow-x: auto; 
        gap: 12px; 
        padding-bottom: 12px; 
        scrollbar-width: thin; 
        scrollbar-color: var(--blue-hover) #f0f0f0; 
    }
    .cat-btn { 
        border: 1px solid #E2E8F0; 
        color: var(--text-secondary); 
        background: white; 
        padding: 10px 24px; 
        border-radius: 50px; 
        text-decoration: none; 
        transition: var(--transition-smooth); 
        white-space: nowrap; 
        font-weight: 500;
    }
    .cat-btn:hover, .cat-btn.active { 
        background: var(--blue-hover); 
        color: white; 
        border-color: var(--blue-hover); 
        box-shadow: 0 4px 12px rgba(127, 181, 255, 0.3);
    }
    @keyframes pulse-lightning {
        0% { transform: scale(1); }
        50% { transform: scale(1.15); }
        100% { transform: scale(1); }
    }
    .flash-card-hover {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .flash-card-hover:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(220, 53, 69, 0.15) !important;
    }
</style>
";
include 'header.php';
?>

<?php if (count($banners) > 0): ?>
    <div class="container mt-4 animate__animated animate__fadeIn">
        <div id="mainCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-indicators">
                <?php foreach($banners as $index => $b): ?>
                    <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="<?= $index ?>" class="<?= $index==0?'active':'' ?>"></button>
                <?php endforeach; ?>
            </div>
            <div class="carousel-inner">
                <?php foreach($banners as $index => $b): ?>
                    <div class="carousel-item <?= $index==0?'active':'' ?>">
                        <img src="<?= $b['image'] ?>" class="d-block w-100 rounded-4" alt="Banner">
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#mainCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon bg-dark rounded-circle bg-opacity-25 p-3"></span></button>
            <button class="carousel-control-next" type="button" data-bs-target="#mainCarousel" data-bs-slide="next"><span class="carousel-control-next-icon bg-dark rounded-circle bg-opacity-25 p-3"></span></button>
        </div>
    </div>
<?php else: ?>
    <header class="hero-section animate__animated animate__fadeIn">
        <div class="container">
            <div class="row align-items-center flex-column-reverse flex-lg-row">
                <div class="col-lg-6 animate__animated animate__fadeInLeft text-center text-lg-start mt-4 mt-lg-0">
                    <h1 class="display-4 fw-bold mb-3">แหล่งรวม <br><span style="color: var(--blue-dark);">สินค้าเบ็ดเตล็ด</span></h1>
                    <p class="lead text-muted mb-4">สินค้าคุณภาพดี ของใช้ทั่วไป ของใช้ในบ้าน ครบจบในที่เดียว</p>
                    <a href="#shop" class="btn btn-gradient btn-lg px-5 shadow">เริ่มช้อปเลย</a>
                </div>
                <div class="col-lg-6 animate__animated animate__fadeInRight text-center">
                    <img src="https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?q=80&w=800" class="img-fluid rounded-5 shadow-lg" style="max-height: 400px; object-fit: cover;" alt="Por Mae Bet Taled">
                </div>
            </div>
        </div>
    </header>
<?php endif; ?>

<?php
// --- Query Active Flash Sale Campaigns ---
$active_flash_sales = [];
$now_str = date('Y-m-d H:i:s');
$fs_query = mysqli_query($conn, "SELECT fs.*, p.name, p.image, p.price as original_price 
    FROM flash_sales fs 
    JOIN products p ON fs.product_id = p.id 
    WHERE '$now_str' BETWEEN fs.start_time AND fs.end_time 
    AND fs.flash_sold < fs.flash_stock 
    ORDER BY fs.end_time ASC");
while ($fs_row = mysqli_fetch_assoc($fs_query)) {
    $active_flash_sales[] = $fs_row;
}

if (!empty($active_flash_sales)):
    $nearest_end_time = $active_flash_sales[0]['end_time'];
?>
    <div class="container mt-5 animate__animated animate__fadeIn">
        <div class="card border-0 shadow-sm p-4 rounded-4" style="background: linear-gradient(135deg, rgba(174, 226, 255, 0.15) 0%, rgba(255, 255, 255, 0.75) 100%); border: 1px solid rgba(174, 226, 255, 0.35); backdrop-filter: blur(10px);">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="flash-icon-wrapper bg-danger text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; animation: pulse-lightning 1.5s infinite;">
                        <i class="bi bi-lightning-charge-fill fs-5"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0 text-dark" style="letter-spacing: -0.5px;">⚡ FLASH SALE</h3>
                        <span class="text-muted small">สินค้าแคมเปญพิเศษ ลดจำกัดเวลาและจำนวน!</span>
                    </div>
                </div>
                <!-- Countdown Clock -->
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold small text-muted text-uppercase me-2">เหลือเวลาอีก</span>
                    <div class="d-flex gap-1 align-items-center" id="flash-countdown-container">
                        <span class="badge bg-dark px-3 py-2 fs-6 rounded-3" id="fs-hours">00</span>
                        <span class="fw-bold text-dark">:</span>
                        <span class="badge bg-dark px-3 py-2 fs-6 rounded-3" id="fs-mins">00</span>
                        <span class="fw-bold text-dark">:</span>
                        <span class="badge bg-dark px-3 py-2 fs-6 rounded-3" id="fs-secs">00</span>
                    </div>
                </div>
            </div>

            <!-- Flash Sale Products Grid -->
            <div class="row g-3 g-md-4">
                <?php foreach ($active_flash_sales as $fs): 
                    $pct = $fs['flash_stock'] > 0 ? ($fs['flash_sold'] / $fs['flash_stock']) * 100 : 0;
                    if ($pct > 100) $pct = 100;
                    $remaining = max(0, $fs['flash_stock'] - $fs['flash_sold']);
                    $discount_pct = round((($fs['original_price'] - $fs['flash_price']) / $fs['original_price']) * 100);
                ?>
                    <div class="col-6 col-md-3">
                        <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden position-relative flash-card-hover" style="background: white;">
                            <!-- Floating Flash Discount Tag -->
                            <div class="position-absolute top-0 start-0 m-2 bg-danger text-white px-2 py-1 rounded-3 fw-bold small z-3" style="font-size: 0.75rem;">
                                -<?= $discount_pct ?>%
                            </div>
                            
                            <div class="product-img-wrapper" style="height: 180px; overflow: hidden; position: relative;">
                                <a href="product_detail.php?id=<?= $fs['product_id'] ?>" class="text-decoration-none">
                                    <img src="<?= htmlspecialchars($fs['image']) ?>" class="w-100 h-100" style="object-fit: cover; transition: transform 0.5s ease;" alt="<?= htmlspecialchars($fs['name']) ?>" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'">
                                </a>
                            </div>
                            
                            <div class="card-body p-3 d-flex flex-column">
                                <h6 class="card-title fw-bold text-dark mb-1 text-truncate" title="<?= htmlspecialchars($fs['name']) ?>"><?= htmlspecialchars($fs['name']) ?></h6>
                                
                                <div class="d-flex align-items-baseline gap-2 mb-2">
                                    <span class="fs-5 fw-bold text-danger">฿<?= number_format($fs['flash_price']) ?></span>
                                    <span class="text-muted text-decoration-line-through small" style="font-size: 0.8rem;">฿<?= number_format($fs['original_price']) ?></span>
                                </div>
                                
                                <div class="mt-auto">
                                    <!-- Stock Progress Bar -->
                                    <div class="d-flex justify-content-between small text-muted mb-1" style="font-size: 0.75rem;">
                                        <span>ขายไปแล้ว <?= $fs['flash_sold'] ?> ชิ้น</span>
                                        <span>เหลือ <?= $remaining ?> ชิ้น</span>
                                    </div>
                                    <div class="progress mb-3" style="height: 6px; background-color: #f1f5f9; border-radius: 50px;">
                                        <div class="progress-bar bg-danger rounded-5" role="progressbar" style="width: <?= $pct ?>%"></div>
                                    </div>
                                    
                                    <a href="product_detail.php?id=<?= $fs['product_id'] ?>" class="btn btn-danger w-100 rounded-pill btn-sm fw-semibold py-2">ช้อปโปรนี้ <i class="bi bi-chevron-right"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Countdown Timer Script -->
    <script>
        (function() {
            var endTime = new Date("<?= date('Y-m-d\TH:i:s', strtotime($nearest_end_time)) ?>").getTime();
            
            function updateTimer() {
                var now = new Date().getTime();
                var distance = endTime - now;
                
                if (distance < 0) {
                    clearInterval(x);
                    document.getElementById("flash-countdown-container").innerHTML = '<span class="badge bg-secondary px-3 py-2 fs-6 rounded-3">หมดเวลาแคมเปญ</span>';
                    setTimeout(function() { location.reload(); }, 1500);
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

<section id="shop" class="container py-5">
    <?php 
    $search = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
    $cat_id = isset($_GET['cat']) ? mysqli_real_escape_string($conn, $_GET['cat']) : '';
    $min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? floatval($_GET['min_price']) : null;
    $max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? floatval($_GET['max_price']) : null;
    $in_stock = isset($_GET['in_stock']) ? intval($_GET['in_stock']) : 0;
    $sort = isset($_GET['sort']) ? mysqli_real_escape_string($conn, $_GET['sort']) : 'newest';

    $sql = "SELECT p.*, c.name as cat_name,
            (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id) as avg_rating,
            (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as review_count 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE 1=1 ";
    if ($search) $sql .= "AND p.name LIKE '%$search%' ";
    if ($cat_id) $sql .= "AND p.category_id = '$cat_id' ";
    if ($min_price !== null) $sql .= "AND p.price >= $min_price ";
    if ($max_price !== null) $sql .= "AND p.price <= $max_price ";
    if ($in_stock) $sql .= "AND p.stock > 0 ";

    $order_by = "ORDER BY p.id DESC";
    if ($sort === 'price_asc') {
        $order_by = "ORDER BY p.price ASC";
    } elseif ($sort === 'price_desc') {
        $order_by = "ORDER BY p.price DESC";
    } elseif ($sort === 'rating') {
        $order_by = "ORDER BY COALESCE(avg_rating, 0) DESC, p.id DESC";
    }

    $result = mysqli_query($conn, $sql . $order_by);
    $total_found = mysqli_num_rows($result);
    ?>

    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="fw-bold mb-0 text-dark">สินค้าทั้งหมด <span style="color:var(--blue-hover)">🛒</span></h2>
        </div>
        <div class="scroll-menu" id="categoryMenu">
            <a href="<?= buildFilterUrl(['cat' => null]) ?>" class="cat-btn <?= !isset($_GET['cat']) ? 'active' : '' ?>">ทั้งหมด</a>
            <?php foreach($categories as $c): ?>
                <a href="<?= buildFilterUrl(['cat' => $c['id']]) ?>" class="cat-btn <?= (isset($_GET['cat']) && $_GET['cat'] == $c['id']) ? 'active' : '' ?>"><?= $c['name'] ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- คอนโทรลบาร์สำหรับตัวกรองและเรียงลำดับ -->
    <div class="row align-items-center mb-4">
        <div class="col-12 col-md-6 text-center text-md-start mb-3 mb-md-0">
            <span class="text-muted" style="font-size: 0.95rem;">
                พบสินค้าทั้งหมด <strong class="text-dark"><?= $total_found ?></strong> รายการ
            </span>
        </div>
        <div class="col-12 col-md-6 d-flex justify-content-center justify-content-md-end gap-2">
            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 d-flex align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse" style="border-color: #E2E8F0; color: var(--text-secondary); background: white; font-weight: 500;">
                <i class="bi bi-sliders"></i> ตัวกรองขั้นสูง
                <?php if (($min_price !== null) || ($max_price !== null) || $in_stock): ?>
                    <span class="badge bg-primary rounded-circle" style="width: 8px; height: 8px; padding: 0;"></span>
                <?php endif; ?>
            </button>
            
            <div class="d-flex align-items-center gap-2">
                <select name="sort" class="form-select form-select-sm rounded-pill px-3" style="width: 170px; border-color: #E2E8F0; color: var(--text-secondary); cursor: pointer; font-weight: 500;" onchange="applySorting(this.value)">
                    <option value="newest" <?= ($sort=='newest')?'selected':'' ?>>ล่าสุด</option>
                    <option value="price_asc" <?= ($sort=='price_asc')?'selected':'' ?>>ราคา: ต่ำ - สูง</option>
                    <option value="price_desc" <?= ($sort=='price_desc')?'selected':'' ?>>ราคา: สูง - ต่ำ</option>
                    <option value="rating" <?= ($sort=='rating')?'selected':'' ?>>คะแนนรีวิวสูงสุด</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ลิ้นชักตัวกรองแบบพับได้ (Filter Collapse Drawer) -->
    <div class="collapse <?= (($min_price !== null) || ($max_price !== null) || $in_stock) ? 'show' : '' ?> mb-4" id="filterCollapse">
        <div class="card card-body shadow-sm border-0 p-4" style="background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-radius: var(--radius-md); border: 1px solid rgba(255, 255, 255, 0.5);">
            <form method="GET" action="index.php#shop">
                <!-- รักษาตัวแปรที่มีอยู่ -->
                <?php if($cat_id): ?>
                    <input type="hidden" name="cat" value="<?= htmlspecialchars($cat_id) ?>">
                <?php endif; ?>
                <?php if($search): ?>
                    <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                <?php endif; ?>
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-bold text-dark small mb-2 d-block">ช่วงราคา (บาท)</label>
                        <div class="d-flex align-items-center gap-2">
                          <input type="number" name="min_price" class="form-control form-control-sm rounded-pill text-center py-2" placeholder="ต่ำสุด" value="<?= $min_price !== null ? htmlspecialchars($min_price) : '' ?>" style="border-color: #E2E8F0; font-size: 0.9rem;" min="0">
                          <span class="text-muted">—</span>
                          <input type="number" name="max_price" class="form-control form-control-sm rounded-pill text-center py-2" placeholder="สูงสุด" value="<?= $max_price !== null ? htmlspecialchars($max_price) : '' ?>" style="border-color: #E2E8F0; font-size: 0.9rem;" min="0">
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-4">
                        <div class="form-check form-switch mb-2">
                          <input class="form-check-input" type="checkbox" name="in_stock" id="inStockCheck" value="1" <?= $in_stock ? 'checked' : '' ?> style="cursor: pointer; width: 2.2em; height: 1.1em;">
                          <label class="form-check-label fw-bold text-dark small ms-2" for="inStockCheck" style="cursor: pointer;">แสดงเฉพาะสินค้าที่มีในสต๊อก</label>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-gradient btn-sm rounded-pill flex-grow-1 py-2 font-weight-bold" style="font-size: 0.85rem; height: 38px;">
                            นำไปใช้
                        </button>
                        <?php if (($min_price !== null) || ($max_price !== null) || $in_stock): ?>
                            <a href="index.php?<?= $cat_id ? 'cat='.urlencode($cat_id) : '' ?><?= $search ? '&q='.urlencode($search) : '' ?>#shop" class="btn btn-outline-secondary btn-sm rounded-pill d-flex align-items-center justify-content-center" style="border-color: #E2E8F0; width: 38px; height: 38px; padding: 0; background: white;" title="ล้างตัวกรอง">
                                <i class="bi bi-trash3"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 g-md-4">
        <?php 
        if($total_found > 0):
            while ($p = mysqli_fetch_assoc($result)):
                $is_out = ($p['stock'] <= 0);
                $rating = $p['avg_rating'] ? round($p['avg_rating'], 1) : 0;
                $rv_count = $p['review_count'];
                $is_fav = in_array($p['id'], $wishlist_items);
                $fav_class = $is_fav ? 'liked' : '';
                $fav_icon = $is_fav ? 'bi-heart-fill' : 'bi-heart';
        ?>
        <div class="col-6 col-md-4 col-lg-3 animate__animated animate__fadeInUp">
            <div class="card card-product">
                <button onclick="toggleFeature('toggle_wishlist', <?= $p['id'] ?>, this)" class="wishlist-tag <?= $fav_class ?>" title="เก็บลงรายการโปรด">
                    <i class="bi <?= $fav_icon ?>"></i>
                </button>
                <?php $active_fs = getActiveFlashSale($conn, $p['id']); ?>
                <div class="product-img-wrapper">
                    <a href="product_detail.php?id=<?= $p['id'] ?>" class="text-decoration-none d-block w-100 h-100">
                        <img src="<?= $p['image'] ?>" alt="<?= $p['name'] ?>">
                        <?php if($active_fs !== null): ?>
                            <div class="position-absolute top-0 start-0 m-2 bg-danger text-white px-2 py-1 rounded-3 fw-bold small z-3" style="font-size: 0.75rem; box-shadow: 0 2px 6px rgba(220,53,69,0.3);">⚡ FLASH</div>
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
                    <p class="text-muted small mb-2 d-none d-md-block text-truncate"><?= $p['description'] ?></p>
                    <div class="mt-auto">
                        <div class="mb-3">
                            <?php if ($active_fs !== null): ?>
                                <span class="fw-bold text-danger" style="font-size:1.3rem;">฿<?= number_format($active_fs['flash_price']) ?></span>
                                <span class="text-muted text-decoration-line-through small ms-1" style="font-size: 0.8rem;">฿<?= number_format($p['price']) ?></span>
                            <?php else: ?>
                                <span class="fw-bold" style="color:var(--blue-dark); font-size:1.3rem;">฿<?= number_format($p['price']) ?></span>
                            <?php endif; ?>
                            <?php if(!$is_out): ?>
                                <div class="text-muted small" style="font-size:0.75rem;">คงเหลือ <?= $p['stock'] ?> ชิ้น</div>
                            <?php endif; ?>
                        </div>
                        <?php if($is_out): ?>
                            <button class="btn btn-secondary w-100 btn-sm rounded-pill py-2" disabled>สินค้าหมดแล้ว</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-gradient w-100 btn-sm shadow-sm position-relative py-2" style="z-index:2;" onclick="event.preventDefault(); event.stopPropagation(); quickAddToCart(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['options'] ?? ''), ENT_QUOTES) ?>', this)">
                                <i class="bi bi-cart-plus"></i> <span class="d-none d-lg-inline"><?= !empty($p['options']) ? 'เลือกตัวเลือก' : 'เพิ่มลงตะกร้า' ?></span><span class="d-inline d-md-none"><?= !empty($p['options']) ? 'เลือก' : 'หยิบใส่' ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; else: ?>
            <div class="col-12 text-center py-5">
                <i class="bi bi-search display-1 text-muted opacity-25"></i>
                <h4 class="text-muted mt-3">ไม่พบสินค้าในหมวดหมู่นี้</h4>
                <a href="index.php" class="btn btn-link text-blue">ดูสินค้าทั้งหมด</a>
            </div>
        <?php endif; ?>
    </div>
</section>


<!-- สินค้าที่ดูล่าสุด (Recently Viewed Products) -->
<?php
$recently_viewed = [];
if (isset($_COOKIE['recently_viewed'])) {
    $recently_viewed = json_decode($_COOKIE['recently_viewed'], true);
    if (!is_array($recently_viewed)) {
        $recently_viewed = [];
    }
}

if (!empty($recently_viewed)):
    $ids_string = implode("','", array_map(function($val) use ($conn) {
        return mysqli_real_escape_string($conn, $val);
    }, $recently_viewed));

    $rv_query = mysqli_query($conn, "SELECT p.*, 
        (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id) as avg_rating,
        (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as review_count
        FROM products p WHERE p.id IN ('$ids_string')");

    $rv_products = [];
    while ($p = mysqli_fetch_assoc($rv_query)) {
        $rv_products[$p['id']] = $p;
    }

    $ordered_rv_products = [];
    foreach ($recently_viewed as $rv_id) {
        if (isset($rv_products[$rv_id])) {
            $ordered_rv_products[] = $rv_products[$rv_id];
        }
    }

    if (!empty($ordered_rv_products)):
?>
<section class="container py-5 animate__animated animate__fadeInUp">
    <div class="border-top pt-5">
        <h2 class="fw-bold mb-4 text-dark">สินค้าที่คุณดูล่าสุด <span style="color:var(--blue-hover);">👀</span></h2>
        <div class="scroll-menu py-2 px-1" style="display: flex; flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; gap: 20px;">
            <?php foreach ($ordered_rv_products as $p): 
                $is_out = ($p['stock'] <= 0);
                $rating = $p['avg_rating'] ? round($p['avg_rating'], 1) : 0;
                $rv_count = $p['review_count'];
                
                // Wishlist check
                $is_fav = in_array($p['id'], $wishlist_items);
                $fav_class = $is_fav ? 'liked' : '';
                $fav_icon = $is_fav ? 'bi-heart-fill' : 'bi-heart';
            ?>
            <div style="flex: 0 0 auto; width: 240px; height: 320px; position: relative;">
                <div class="card card-product h-100 shadow-sm border-0" style="background: #fff; border-radius: var(--radius-md); overflow: hidden; transition: var(--transition-smooth);">
                    <button onclick="toggleFeature('toggle_wishlist', <?= $p['id'] ?>, this)" class="wishlist-tag <?= $fav_class ?>" title="เก็บลงรายการโปรด" style="position: absolute; top: 12px; right: 12px; z-index: 5;">
                        <i class="bi <?= $fav_icon ?>"></i>
                    </button>
                    <div class="product-img-wrapper" style="height: 180px; display: flex; align-items: center; justify-content: center; background: #fff; border-bottom: 1px solid rgba(226, 232, 240, 0.4);">
                        <a href="product_detail.php?id=<?= $p['id'] ?>" class="text-decoration-none d-flex align-items-center justify-content-center w-100 h-100">
                            <img src="<?= $p['image'] ?>" alt="<?= $p['name'] ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                            <?php if($is_out): ?>
                                <div class="out-stock-overlay"><span class="badge bg-danger rounded-pill px-2 py-1">สินค้าหมด</span></div>
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
</section>
<?php 
    endif;
endif; 
?>

<footer class="py-5 bg-white border-top mt-5">

    <div class="container">
        <div class="row g-4 text-start justify-content-between">
            <div class="col-lg-5 col-md-6">
                <h5 class="fw-bold mb-3" style="color: var(--blue-hover);">พ่อแม่ เบ็ดเตล็ด</h5>
                <p class="text-muted small" style="line-height: 1.8;">แหล่งรวมของใช้ทั่วไป ของใช้ในบ้าน อุปกรณ์สำนักงาน เครื่องปรุงรส และของใช้เบ็ดเตล็ดคุณภาพดี คัดสรรมาเพื่อตอบโจทย์ทุกครอบครัวในราคามิตรภาพ</p>
                <div class="d-flex gap-3 mt-3">
                    <a href="contact.php" class="text-muted"><i class="bi bi-facebook fs-5"></i></a>
                    <a href="contact.php" class="text-muted"><i class="bi bi-line fs-5"></i></a>
                    <a href="contact.php" class="text-muted"><i class="bi bi-instagram fs-5"></i></a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <h6 class="fw-bold mb-3">ลิงก์ด่วน</h6>
                <ul class="list-unstyled d-flex flex-column gap-2 small">
                    <li><a href="index.php" class="text-decoration-none text-muted">หน้าแรก</a></li>
                    <li><a href="index.php#shop" class="text-decoration-none text-muted">สินค้าทั้งหมด</a></li>
                    <li><a href="about.php" class="text-decoration-none text-muted">เกี่ยวกับเรา</a></li>
                    <li><a href="contact.php" class="text-decoration-none text-muted">ติดต่อเรา</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-12">
                <h6 class="fw-bold mb-3">การบริการและช่วยเหลือ</h6>
                <p class="text-muted small mb-1"><i class="bi bi-clock me-1"></i> บริการลูกค้า: 08:30 - 18:00 น.</p>
                <p class="text-muted small mb-1"><i class="bi bi-truck me-1"></i> จัดส่งฟรีเมื่อถึงยอดขั้นต่ำ</p>
                <p class="text-muted small"><i class="bi bi-shield-check me-1"></i> สินค้าของแท้ คุณภาพดี 100%</p>
            </div>
        </div>
        <hr class="my-4 opacity-25">
        <div class="text-center text-muted small">
            © 2026 Por Mae Bet Taled. All rights reserved.
        </div>
    </div>
</footer>
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

<!-- Quick Add to Cart Option Modal -->
<div class="modal fade" id="quickOptionModal" tabindex="-1" aria-labelledby="quickOptionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(15px);">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h6 class="modal-title fw-bold text-dark" id="quickOptionModalLabel"><i class="bi bi-bag-plus me-2" style="color: var(--blue-hover);"></i>เลือกตัวเลือกสินค้า</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 pb-4 pt-3">
                <div id="quickOptionBody"></div>
                <button type="button" class="btn btn-gradient w-100 rounded-pill py-2 mt-3 fw-bold shadow-sm" id="quickOptionSubmitBtn" onclick="submitQuickOption()">
                    <i class="bi bi-cart-plus me-2"></i>เพิ่มลงตะกร้า
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .quick-opt-group { margin-bottom: 16px; }
    .quick-opt-label { font-weight: 600; font-size: 0.85rem; margin-bottom: 8px; display: block; color: var(--slate-dark); }
    .quick-opt-btn { display: inline-block; }
    .quick-opt-btn input[type="radio"] { display: none; }
    .quick-opt-btn label {
        display: inline-block;
        border: 1.5px solid #E2E8F0;
        background: white;
        color: var(--text-secondary);
        padding: 6px 16px;
        margin-right: 6px;
        margin-bottom: 6px;
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.2s ease;
        font-weight: 500;
        font-size: 0.85rem;
    }
    .quick-opt-btn label:hover {
        border-color: var(--blue-hover);
        color: var(--blue-hover);
        background: rgba(174, 226, 255, 0.08);
    }
    .quick-opt-btn input[type="radio"]:checked + label {
        background: var(--blue-hover);
        color: white;
        border-color: var(--blue-hover);
        box-shadow: 0 3px 10px rgba(127, 181, 255, 0.3);
    }
    #quickOptionModal .modal-content {
        animation: modalSlideUp 0.35s cubic-bezier(.22,1,.36,1);
    }
    @keyframes modalSlideUp {
        from { transform: translateY(30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
</style>

<script>
    function applySorting(sortVal) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('sort', sortVal);
        if(urlParams.toString()) {
            window.location.href = 'index.php?' + urlParams.toString() + '#shop';
        } else {
            window.location.href = 'index.php#shop';
        }
    }

    const scrollContainer = document.getElementById('categoryMenu');
    if(scrollContainer){
        scrollContainer.addEventListener("wheel", (evt) => { evt.preventDefault(); scrollContainer.scrollLeft += evt.deltaY; });
    }

    function toggleFeature(action, pid, btn) {
        let fd = new FormData(); fd.append('action', action); fd.append('product_id', pid);
        fetch('ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if(data.status === 'success') {
                if (action === 'toggle_wishlist') {
                    const icon = btn.querySelector('i');
                    if (data.state === 'added') {
                        btn.classList.add('liked'); icon.classList.remove('bi-heart'); icon.classList.add('bi-heart-fill');
                    } else {
                        btn.classList.remove('liked'); icon.classList.remove('bi-heart-fill'); icon.classList.add('bi-heart');
                    }
                }
                Swal.fire({ icon: 'success', title: data.message, toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
                if(data.count !== undefined && action === 'toggle_compare') {
                    let badge = document.getElementById('badge-compare');
                    if(badge) { badge.innerText = data.count; badge.classList.remove('hidden'); }
                }
            } else { Swal.fire('แจ้งเตือน', data.message, 'warning'); }
        })
        .catch(err => console.error(err));
    }

    // ==========================================
    // Quick Add to Cart (with Option Popup)
    // ==========================================
    let _quickAddProductId = null;
    let _quickAddBtn = null;

    function quickAddToCart(productId, optionsStr, btn) {
        _quickAddProductId = productId;
        _quickAddBtn = btn;

        // If product has NO options, add to cart immediately
        if (!optionsStr || optionsStr.trim() === '') {
            doQuickAdd(productId, '', btn);
            return;
        }

        // Product HAS options — show the popup
        const body = document.getElementById('quickOptionBody');
        body.innerHTML = ''; // reset

        const groups = optionsStr.split('|');
        groups.forEach((group, gi) => {
            const parts = group.split(':');
            if (parts.length !== 2) return;
            const optName = parts[0].trim();
            const values = parts[1].split(',');

            let html = '<div class="quick-opt-group">';
            html += '<span class="quick-opt-label">' + optName + '</span>';
            html += '<div class="d-flex flex-wrap">';
            values.forEach((val, vi) => {
                val = val.trim();
                const uid = 'qopt_' + gi + '_' + vi;
                html += '<span class="quick-opt-btn">';
                html += '<input type="radio" name="qopt_' + optName + '" id="' + uid + '" value="' + val + '" data-optname="' + optName + '" required>';
                html += '<label for="' + uid + '">' + val + '</label>';
                html += '</span>';
            });
            html += '</div></div>';
            body.innerHTML += html;
        });

        const modal = new bootstrap.Modal(document.getElementById('quickOptionModal'));
        modal.show();
    }

    function submitQuickOption() {
        const body = document.getElementById('quickOptionBody');
        const groups = body.querySelectorAll('.quick-opt-group');
        let selectedOpts = [];
        let allSelected = true;

        groups.forEach(g => {
            const radios = g.querySelectorAll('input[type="radio"]');
            let checked = false;
            radios.forEach(r => {
                if (r.checked) {
                    checked = true;
                    selectedOpts.push(r.dataset.optname + ': ' + r.value);
                }
            });
            if (!checked) {
                allSelected = false;
                // Highlight the group
                g.querySelector('.quick-opt-label').style.color = '#dc3545';
                setTimeout(() => {
                    g.querySelector('.quick-opt-label').style.color = '';
                }, 2000);
            }
        });

        if (!allSelected) {
            Swal.fire({ icon: 'warning', title: 'กรุณาเลือกตัวเลือกให้ครบ', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            return;
        }

        const opts = selectedOpts.join(', ');

        // Close modal
        const modalEl = document.getElementById('quickOptionModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();

        doQuickAdd(_quickAddProductId, opts, _quickAddBtn);
    }

    function doQuickAdd(productId, options, btn) {
        // Show loading state on button
        const origHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:14px;height:14px;"></span>';

        const fd = new FormData();
        fd.append('action', 'add');
        fd.append('product_id', productId);
        fd.append('qty', 1);
        fd.append('options', options);

        fetch('ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            if (data.status === 'success') {
                // Animated success feedback
                btn.innerHTML = '<i class="bi bi-check2"></i> <span class="d-none d-lg-inline">เพิ่มแล้ว!</span><span class="d-inline d-md-none">✓</span>';
                btn.classList.remove('btn-gradient');
                btn.classList.add('btn-success');

                setTimeout(() => {
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-gradient');
                    btn.innerHTML = origHTML;
                }, 1800);

                // Update cart badge
                const badge = document.getElementById('nav-cart-badge');
                if (badge) {
                    badge.innerText = data.cart_count;
                    badge.classList.remove('hidden');
                }

                // Open cart drawer
                if (typeof window.toggleCartDrawer === 'function') {
                    const drawer = document.getElementById('cartDrawer');
                    if (drawer && !drawer.classList.contains('show')) {
                        window.toggleCartDrawer();
                    } else {
                        window.loadCartDrawer();
                    }
                }
            } else {
                btn.innerHTML = origHTML;
                Swal.fire({ icon: 'error', title: data.message || 'เกิดข้อผิดพลาด', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = origHTML;
            console.error(err);
        });
    }
</script>

<?php if ($show_welcome_popup && $welcome_coupon): ?>
<!-- Modal คูปองต้อนรับ -->
<div class="modal fade" id="welcomePromoModal" tabindex="-1" aria-labelledby="welcomePromoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(15px);">
            <div class="modal-header border-0 pb-0" style="background: #f8f9fa;">
                <h5 class="modal-title fw-bold text-dark animate__animated animate__fadeInDown" id="welcomePromoModalLabel"><i class="bi bi-gift-fill text-danger me-2"></i>ข้อเสนอสุดพิเศษสำหรับคุณ!</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4 px-4">
                <div class="animate__animated animate__bounceIn mb-3">
                    <i class="bi bi-ticket-perforated text-primary animate__animated animate__pulse animate__infinite" style="font-size: 4.5rem; display: inline-block;"></i>
                </div>
                <h4 class="fw-bold text-dark mb-1">ยินดีต้อนรับสู่ พ่อแม่ เบ็ดเตล็ด</h4>
                <p class="text-muted small mb-4">รับโค้ดส่วนลดพิเศษเพื่อฉลองการช้อปปิ้งของคุณวันนี้!</p>
                
                <div class="coupon-box-premium p-3 rounded-4 mb-4 border d-flex flex-column align-items-center justify-content-center" style="background: linear-gradient(135deg, #7FB5FF, #AEE2FF); border-color: rgba(255,255,255,0.5); box-shadow: 0 8px 25px rgba(127,181,255,0.3);">
                    <span class="badge bg-white text-primary rounded-pill px-3 py-1 fw-bold mb-2 shadow-sm" style="font-size: 0.75rem;">คูปองต้อนรับสมาชิก</span>
                    <h2 class="fw-bold text-white mb-1 font-monospace" style="letter-spacing: 1px; font-size: 2rem;"><?= htmlspecialchars($welcome_coupon['code']) ?></h2>
                    <h3 class="fw-bold text-white mb-2" style="font-size: 1.5rem;">
                        ลดทันที <?= $welcome_coupon['discount_type'] == 'percent' ? intval($welcome_coupon['discount_value']) . '%' : '฿' . number_format($welcome_coupon['discount_value']) ?>
                    </h3>
                    <div class="text-white small opacity-90" style="font-size: 0.75rem;">
                        <?= $welcome_coupon['min_spend'] > 0 ? 'ยอดซื้อขั้นต่ำ ฿' . number_format($welcome_coupon['min_spend']) : 'ไม่มีขั้นต่ำ' ?>
                        • หมดอายุ: <?= date('d/m/Y', strtotime($welcome_coupon['expiry_date'])) ?>
                    </div>
                </div>
                
                <button type="button" class="btn btn-primary rounded-pill w-100 py-3 border-0 shadow-md fw-bold" onclick="claimWelcomeCoupon('<?= htmlspecialchars($welcome_coupon['code']) ?>')" style="background: linear-gradient(45deg, #7FB5FF, #AEE2FF); font-size: 1.05rem; color: #fff;">
                    <i class="bi bi-tag-fill me-2"></i>เก็บสะสมโค้ดเลย
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const welcomeModal = new bootstrap.Modal(document.getElementById('welcomePromoModal'));
        welcomeModal.show();
    });

    function claimWelcomeCoupon(code) {
        const fd = new FormData();
        fd.append('action', 'claim_welcome_coupon');
        fd.append('coupon_code', code);
        
        fetch('ajax.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            bootstrap.Modal.getInstance(document.getElementById('welcomePromoModal')).hide();
            
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'เก็บคูปองสำเร็จ!',
                    text: data.message,
                    confirmButtonText: 'ช้อปเลย',
                    confirmButtonColor: '#7FB5FF'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'เก็บคูปองไม่สำเร็จ',
                    text: data.message,
                    confirmButtonColor: '#FF6B6B'
                });
            }
        })
        .catch(err => {
            console.error(err);
            bootstrap.Modal.getInstance(document.getElementById('welcomePromoModal')).hide();
        });
    }
</script>
<?php endif; ?>

</body>
</html>
