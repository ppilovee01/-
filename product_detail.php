<?php
session_start();
include 'db.php';

if (!isset($_GET['id'])) { header("Location: index.php"); exit(); }
$id = mysqli_real_escape_string($conn, $_GET['id']);

$sql = "SELECT * FROM products WHERE id = '$id'";
$result = mysqli_query($conn, $sql);
$product = mysqli_fetch_assoc($result);

if (!$product) { header("Location: index.php"); exit(); }

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
    $rating = $_POST['rating'];
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    $sql_review = "INSERT INTO product_reviews (product_id, user_id, rating, comment) VALUES ('$id', '$uid', '$rating', '$comment')";
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
    :root { --blue-dark: #AEE2FF; --blue-light: #F0F8FF; --dark-heading: #222; }
    .badge-cart { background-color: var(--blue-dark) !important; color: white; border: 2px solid white; }
    .product-img-container { border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.05); background: white; padding: 20px; text-align: center; border: 1px solid #f0f0f0; }
    .product-img { max-width: 100%; height: auto; transition: transform 0.3s; border-radius: 10px; }
    .product-img:hover { transform: scale(1.03); }
    h1.product-title { color: var(--dark-heading); font-weight: 700; letter-spacing: -0.5px; }
    .price-tag { color: var(--blue-dark); font-weight: 700; font-size: 2rem; }
    .option-group { margin-bottom: 20px; }
    .option-label { font-weight: 600; font-size: 0.95rem; margin-bottom: 10px; display: block; color: var(--dark-heading); }
    .btn-option { border: 1px solid #ddd; background: white; color: #666; padding: 8px 20px; margin-right: 8px; margin-bottom: 8px; border-radius: 8px; cursor: pointer; transition: all 0.2s; font-weight: 400; }
    .btn-option:hover { border-color: var(--dark-heading); color: var(--dark-heading); }
    .btn-check:checked + .btn-option { background-color: var(--dark-heading); color: white; border-color: var(--dark-heading); font-weight: 500; box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
    .btn-add-cart { background-color: var(--dark-heading); color: white; border: none; border-radius: 50px; padding: 14px 30px; font-weight: 600; font-size: 1.1rem; box-shadow: 0 5px 15px rgba(0,0,0,0.1); transition: all 0.3s ease; flex-grow: 1; }
    .btn-add-cart:hover { background-color: var(--blue-dark); color: white; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255, 94, 132, 0.3); }
    .btn-add-cart:disabled { background: #ccc; cursor: not-allowed; transform: none; box-shadow: none; }
    .qty-input-group { width: 130px; border-radius: 50px; overflow: hidden; border: 1px solid #ddd; background: white; }
    .btn-qty { border: none; background: white; color: #333; width: 40px; font-weight: bold; transition: 0.2s; }
    .btn-qty:hover { background: #f8f9fa; }
    .form-control-qty { border: none; text-align: center; font-weight: 600; color: #333; width: 50px; background: white; }
    
    /* สเนตลเนเธุเนมวเธเธลม */
    .btn-icon-action { width: 54px; height: 54px; border-radius: 50%; border: 1px solid #eee; background: white; color: #555; display: flex; align-items: center; justify-content: center; transition: 0.2s; font-size: 1.2rem; cursor: pointer; }
    .btn-icon-action:hover { border-color: var(--blue-dark); color: var(--blue-dark); background: #F0F8FF; transform: translateY(-2px); }
    
    /* สเนตลเนเมืเนอเธดเนลเธเนแล้ว */
    .btn-icon-action.liked { background: var(--blue-dark); color: white; border-color: var(--blue-dark); }
    
    .detail-box { background: white; border-radius: 16px; padding: 40px; box-shadow: 0 5px 25px rgba(0,0,0,0.03); margin-top: 50px; border: 1px solid #f0f0f0; }
    .nav-tabs { border-bottom: 1px solid #eee; }
    .nav-tabs .nav-link { color: #888; border: none; font-weight: 500; padding-bottom: 15px; margin-right: 20px; font-size: 1.1rem; }
    .nav-tabs .nav-link.active { color: var(--blue-dark); border-bottom: 3px solid var(--blue-dark); background: transparent; font-weight: 700; }
    .review-item { border-bottom: 1px solid #f0f0f0; padding-bottom: 20px; margin-bottom: 20px; }
    .star-rating i { color: #FFC107; font-size: 0.9rem; }
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
            <h1 class="product-title display-6 mb-2"><?= $product['name'] ?></h1>
            
            <div class="d-flex align-items-center mb-4">
                <div class="star-rating me-2">
                    <?php for($i=1; $i<=5; $i++) echo $i <= $avg_rating ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star text-muted opacity-25"></i>'; ?>
                </div>
                <span class="text-muted small">(<?= $review_count ?> รีวิว)</span>
            </div>

            <div class="d-flex align-items-center gap-3 mb-4">
                <span class="price-tag">฿<?= number_format($product['price']) ?></span>
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
                            <i class="bi bi-cart-plus me-2"></i> เเธิเนมลเธตะกร้า
                        </button>
                    <?php else: ?>
                        <button class="btn-add-cart" disabled>สินค้าหมดชั่วคราว</button>
                    <?php endif; ?>

                    <button type="button" class="btn-icon-action <?= $fav_class ?>" onclick="toggleFeature('toggle_wishlist', <?= $id ?>, this)" title="เก็บลเธรายการโปรด">
                        <i class="bi <?= $fav_icon ?>"></i>
                    </button>
                    <button type="button" class="btn-icon-action" onclick="toggleFeature('toggle_compare', <?= $id ?>, this)" title="เปรียบเทียบสินค้า">
                        <i class="bi bi-arrow-left-right"></i>
                    </button>
                </div>
            </form>

            <div class="mt-4 pt-4 border-top d-flex gap-4 text-secondary small">
                <div><i class="bi bi-truck text-primary me-1"></i> เธัดสเนเธเธรีทัเนวเนทย</div>
                <div><i class="bi bi-shield-check text-success me-1"></i> ของเนทเน 100%</div>
                <div><i class="bi bi-arrow-return-left text-danger me-1"></i> คืนสินค้าได้ใน 7 วัน</div>
            </div>
        </div>
    </div>

    <div class="detail-box animate__animated animate__fadeInUp">
        <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#desc">รายละเอียดสินค้า</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#review">รีวิวจากเธูเนซื้อ (<?= $review_count ?>)</button></li>
        </ul>
        
        <div class="tab-content">
            <div class="tab-pane fade show active" id="desc">
                <div class="text-secondary lh-lg" style="font-size: 1.05rem;">
                    <?= nl2br($product['description']) ?>
                </div>
            </div>
            
            <div class="tab-pane fade" id="review">
                <?php if ($can_review): ?>
                <div class="bg-light p-4 rounded-3 mb-4 border">
                    <h6 class="fw-bold mb-3">เขียนรีวิวสินค้า</h6>
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="small text-muted">ความเธึเธเธอเนเธ</label>
                                <select name="rating" class="form-select border-0 shadow-sm">
                                    <option value="5">โญโญโญโญโญ (ดีเยีเนยม)</option>
                                    <option value="4">โญโญโญโญ (ดี)</option>
                                    <option value="3">โญโญโญ (เธาเธเธลาเธ)</option>
                                    <option value="2">โญโญ (เธอเนเธเน)</option>
                                    <option value="1">โญ (เนยเน)</option>
                                </select>
                            </div>
                            <div class="col-md-9">
                                <label class="small text-muted">ความคิดเห็น</label>
                                <textarea name="comment" class="form-control border-0 shadow-sm" rows="1" placeholder="เธอเธเลเนาเธระสเธการณเนเนเธเนเธาเธ..." required></textarea>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" name="submit_review" class="btn btn-dark rounded-pill px-4">สเนเธรีวิว</button>
                            </div>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <?php if(mysqli_num_rows($reviews) > 0): while($r = mysqli_fetch_assoc($reviews)): ?>
                <div class="review-item">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div>
                            <strong class="text-dark me-2"><?= $r['fullname'] ?></strong>
                            <span class="text-warning small">
                                <?php for($i=1;$i<=5;$i++) echo $i<=$r['rating'] ? 'โ…' : 'โ'; ?>
                            </span>
                        </div>
                        <small class="text-muted" style="font-size:0.8rem;"><?= date('d/m/Y', strtotime($r['created_at'])) ?></small>
                    </div>
                    <p class="mb-0 text-secondary"><?= $r['comment'] ?></p>
                </div>
                <?php endwhile; else: ?>
                    <div class="text-center py-5 text-muted opacity-50">
                        <i class="bi bi-chat-square-quote display-3 d-block mb-3"></i>
                        ยัเธเนมเนมีรีวิวสำหรับสินค้านี้
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

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

    function submitCart() {
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
                Swal.fire({icon: 'warning', title: 'เธรุณาเลือก ' + cleanName, confirmButtonColor: '#222'});
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

        fetch('ajax_cart.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                const badge = document.getElementById('nav-cart-badge'); 
                if(badge) {
                    badge.innerText = data.cart_count;
                    badge.classList.remove('hidden'); 
                }
                const Toast = Swal.mixin({toast: true, position: 'top-end', showConfirmButton: false, timer: 1500, timerProgressBar: true});
                Toast.fire({icon: 'success', title: 'เเธิเนมลเธตะกร้าแล้ว'});
            } else {
                Swal.fire({icon: 'error', title: 'เเธิดเธเนอผิดพลาด', text: data.message});
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({icon: 'error', title: 'Error', text: 'เนมเนสามารถเชื่อมตเนอเธัเธเเธิรเนเธเวอรเนได้'});
        });
    }

    // โ… เธัเธเธเนเธัเธ Wishlist/Compare ที่อัปവ UI ทัเธที
    function toggleFeature(action, pid, btn) {
        let fd = new FormData(); 
        fd.append('action', action); 
        fd.append('product_id', pid);
        
        fetch('ajax_features.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if(data.status === 'success') {
                const icon = btn.querySelector('i');
                
                // เธรณี Wishlist: เปลี่ยนสีหัวเนเธทัเธที
                if (action === 'toggle_wishlist') {
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

                if(data.count !== undefined && action === 'toggle_compare') {
                    let badge = document.getElementById('badge-compare');
                    if(badge) { badge.innerText = data.count; badge.classList.remove('hidden'); }
                }
            } else {
                Swal.fire('เนเธเนเธเตือเธ', data.message, 'warning');
            }
        })
        .catch(err => console.error(err));
    }
</script>

</body>
</html>


