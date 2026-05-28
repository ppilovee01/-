<?php
session_start();
include 'db.php'; 

// --- Feedback Logic (Anti-F5 Fixed) ---
if (isset($_POST['send_feedback'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['swal'] = ['title'=>'แจ้งเตือน', 'text'=>'กรุณา Login ก่อนส่งความคิดเห็น', 'icon'=>'warning'];
    } else {
        $msg = mysqli_real_escape_string($conn, $_POST['message']);
        $uid = $_SESSION['user_id'];
        if(mysqli_query($conn, "INSERT INTO feedback (user_id, message) VALUES ('$uid', '$msg')")) {
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

<section id="shop" class="container py-5">
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="fw-bold mb-0 text-dark">สินค้าแนะนำ <span style="color:var(--blue-dark)">🔥</span></h2>
        </div>
        <div class="scroll-menu" id="categoryMenu">
            <a href="index.php#shop" class="cat-btn <?= !isset($_GET['cat']) ? 'active' : '' ?>">ทั้งหมด</a>
            <?php foreach($categories as $c): ?>
                <a href="?cat=<?= $c['id'] ?>#shop" class="cat-btn <?= (isset($_GET['cat']) && $_GET['cat'] == $c['id']) ? 'active' : '' ?>"><?= $c['name'] ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row g-3 g-md-4">
        <?php 
        $search = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
        $cat_id = isset($_GET['cat']) ? mysqli_real_escape_string($conn, $_GET['cat']) : '';
        $sql = "SELECT p.*, 
                (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id) as avg_rating,
                (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as review_count 
                FROM products p WHERE 1=1 ";
        if ($search) $sql .= "AND name LIKE '%$search%' ";
        if ($cat_id) $sql .= "AND category_id = '$cat_id' ";
        $result = mysqli_query($conn, $sql . "ORDER BY id DESC");
        
        if(mysqli_num_rows($result) > 0):
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
                <div class="product-img-wrapper">
                    <a href="product_detail.php?id=<?= $p['id'] ?>" class="text-decoration-none d-block w-100 h-100">
                        <img src="<?= $p['image'] ?>" alt="<?= $p['name'] ?>">
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
                            <span class="fw-bold" style="color:var(--blue-dark); font-size:1.3rem;">฿<?= number_format($p['price']) ?></span>
                            <?php if(!$is_out): ?>
                                <div class="text-muted small" style="font-size:0.75rem;">คงเหลือ <?= $p['stock'] ?> ชิ้น</div>
                            <?php endif; ?>
                        </div>
                        <?php if($is_out): ?>
                            <button class="btn btn-secondary w-100 btn-sm rounded-pill py-2" disabled>สินค้าหมดแล้ว</button>
                        <?php else: ?>
                            <a href="product_detail.php?id=<?= $p['id'] ?>" class="btn btn-gradient w-100 btn-sm shadow-sm position-relative py-2" style="z-index:2;">
                                <i class="bi bi-cart-plus"></i> <span class="d-none d-lg-inline">เลือกตัวเลือก</span><span class="d-inline d-md-none">เลือก</span>
                            </a>
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

<a href="cart.php" id="floating-cart" class="floating-cart <?= $cart_count > 0 ? '' : 'hidden' ?>">
    <i class="bi bi-cart-fill fs-4"></i>
    <span id="floating-count" class="floating-count"><?= $cart_count ?></span>
</a>

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
                const icon = btn.querySelector('i');
                if (action === 'toggle_wishlist') {
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
</script>

</body>
</html>
