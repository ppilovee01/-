<?php
session_start();
include 'db.php'; 
include 'header.php';

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
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Por Mae Bet Taled | Por Mae Bet Taled</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --blue-dark: #AEE2FF; --blue-light: #F0F8FF; }
        body { font-family: 'Kanit', sans-serif; background-color: #f8f9fa; }
        
        .navbar { background: white !important; border-bottom: 2px solid var(--blue-light); box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
        .navbar-brand { font-weight: 800; color: var(--blue-dark) !important; font-size: 1.6rem; letter-spacing: -0.5px; }
        
        /* Banner Styles Responsive */
        .hero-section {
            background: linear-gradient(135deg, #F0F8FF 0%, #FFFFFF 100%);
            padding: 80px 0; border-bottom-left-radius: 60px; border-bottom-right-radius: 60px;
        }
        .carousel-item img { height: 400px; object-fit: cover; object-position: center; }
        
        @media (max-width: 991px) {
            .hero-section { padding: 40px 0; border-bottom-left-radius: 30px; border-bottom-right-radius: 30px; }
            .carousel-item img { height: 250px; } /* ลดความสูงแบนเนอร์มือถือ */
            h1.display-4 { font-size: 2rem; } /* ลดขนาดหัวข้อ */
        }

        /* Product Card */
        .card-product { 
            border: none; border-radius: 20px; 
            transition: all 0.3s; overflow: hidden; background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.03); height: 100%; position: relative;
        }
        .card-product:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(174, 226, 255, 0.25); }

        .product-img-wrapper {
            position: relative; height: 250px; width: 100%; background: #fff; 
            display: flex; align-items: center; justify-content: center; overflow: hidden; border-bottom: 1px solid #f8f9fa;
        }
        @media (max-width: 576px) { .product-img-wrapper { height: 180px; } } /* รูปเล็กลงในมือถือ */

        .product-img-wrapper img { width: 100%; height: 100%; object-fit: cover; transition: 0.5s; }
        .card-product:hover .product-img-wrapper img { transform: scale(1.1); }
        
        /* Product Actions */
        .product-actions { 
            position: absolute; top: 10px; right: 10px; 
            display: flex; flex-direction: column; gap: 8px; 
            opacity: 0; transition: 0.3s; transform: translateX(20px); z-index: 5; 
        }
        .card-product:hover .product-actions { opacity: 1; transform: translateX(0); }
        
        .btn-action-mini { 
            width: 38px; height: 38px; border-radius: 50%; background: white; border: none; 
            box-shadow: 0 3px 10px rgba(0,0,0,0.1); color: #555; display: flex; align-items: center; justify-content: center; 
            transition: 0.2s; cursor: pointer; text-decoration: none;
        }
        .btn-action-mini:hover { background: #AEE2FF; color: white; transform: scale(1.1); }
        .btn-action-mini.liked { background: #AEE2FF; color: white; }
        .btn-action-mini.liked i::before { content: "\f417"; }

        /* ชื่อสินค้า */
        .product-name { font-size: 1.1rem; font-weight: 600; color: #333 !important; text-decoration: none !important; transition: 0.3s; }
        .product-name:hover { color: var(--blue-dark) !important; }

        .btn-gradient {
            background: linear-gradient(135deg, #AEE2FF 0%, #7FB5FF 100%);
            color: white !important; border: none; border-radius: 50px; font-weight: 600;
            box-shadow: 0 5px 15px rgba(174, 226, 255, 0.4); transition: 0.3s;
            text-decoration: none; display: block; text-align: center;
        }
        .btn-gradient:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(174, 226, 255, 0.6); }
        
        .out-stock-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.7); display: flex; align-items: center; justify-content: center; z-index: 10; backdrop-filter: blur(3px); }
        .badge-out { background: #333; color: white; padding: 8px 20px; border-radius: 30px; font-weight: bold; }

        .floating-cart {
            position: fixed; bottom: 30px; right: 30px; background: var(--blue-dark); color: white;
            width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 5px 20px rgba(174, 226, 255, 0.5); z-index: 1000; text-decoration: none; transition: 0.3s; animation: bounceIn 0.5s;
        }
        @media (max-width: 576px) { .floating-cart { bottom: 20px; right: 20px; width: 50px; height: 50px; } }

        .floating-cart:hover { transform: scale(1.1) rotate(10deg); color: white; }
        .floating-count {
            position: absolute; top: -5px; right: -5px; background: #fff; color: var(--blue-dark);
            width: 25px; height: 25px; border-radius: 50%; font-size: 0.8rem; font-weight: bold; border: 2px solid var(--blue-dark);
            display: flex; align-items: center; justify-content: center;
        }
        .hidden { display: none !important; }
        
        .scroll-menu { display: flex; overflow-x: auto; gap: 10px; padding-bottom: 10px; scrollbar-width: thin; scrollbar-color: var(--blue-dark) #f0f0f0; }
        .cat-btn { border: 1px solid #ddd; color: #666; background: white; padding: 8px 20px; border-radius: 50px; text-decoration: none; transition: 0.3s; white-space: nowrap; }
        .cat-btn:hover, .cat-btn.active { background: var(--blue-dark); color: white; border-color: var(--blue-dark); }
        
        @keyframes bounceIn { 0% { transform: scale(0); opacity: 0; } 60% { transform: scale(1.1); } 100% { transform: scale(1); opacity: 1; } }
    </style>
</head>
<body>

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
                    <h1 class="display-4 fw-bold mb-3">เปลี่ยนบ้านให้เป็น <br><span style="color: var(--blue-dark);">ฟิตเนสสุดชิค</span></h1>
                    <p class="lead text-muted mb-4">อุปกรณ์ออกกำลังกายเกรดพรีเมียม เพื่อผลลัพธ์ที่ชัดਹ</p>
                    <a href="#shop" class="btn btn-gradient btn-lg px-5 shadow">เริ่มช้อปเลย</a>
                </div>
                <div class="col-lg-6 animate__animated animate__fadeInRight text-center">
                    <img src="https://images.unsplash.com/photo-1541534741688-6078c6bfb5c5?q=80&w=800" class="img-fluid rounded-5 shadow-lg" style="max-height: 400px; object-fit: cover;" alt="Por Mae Bet Taled">
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
                <div class="product-img-wrapper">
                    <a href="product_detail.php?id=<?= $p['id'] ?>" class="text-decoration-none d-block w-100 h-100">
                        <img src="<?= $p['image'] ?>" alt="<?= $p['name'] ?>">
                        <?php if($is_out): ?>
                            <div class="out-stock-overlay"><span class="badge-out">สินค้าหมด</span></div>
                        <?php endif; ?>
                    </a>
                    
                    <div class="product-actions">
                        <button onclick="toggleFeature('toggle_wishlist', <?= $p['id'] ?>, this)" class="btn-action-mini <?= $fav_class ?>" title="เก็บลงรายการโปรด">
                            <i class="bi <?= $fav_icon ?>"></i>
                        </button>
                        <button onclick="toggleFeature('toggle_compare', <?= $p['id'] ?>, this)" class="btn-action-mini" title="เปรียบเทียบ">
                            <i class="bi bi-arrow-left-right"></i>
                        </button>
                    </div>
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
    <div class="container text-center">
        <h4 class="fw-bold mb-4 text-dark">ส่งข้อเสนอแนะถึงเรา 💬</h4>
        <div class="row justify-content-center mb-4">
            <div class="col-md-6">
                <form method="POST" class="input-group shadow-sm rounded-pill overflow-hidden border">
                    <input type="text" name="message" class="form-control border-0 px-4" placeholder="บอกสิ觷ี่คุณต้องการ..." required>
                    <button class="btn btn-gradient px-4" name="send_feedback" type="submit">ส่งข้อความ</button>
                </form>
            </div>
        </div>
        <p class="text-muted small">© 2026 Por Mae Bet Taled. All rights reserved.</p>
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
        fetch('ajax_features.php', { method: 'POST', body: fd })
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
