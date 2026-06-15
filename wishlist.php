<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
$uid = $_SESSION['user_id'];

$sql = "SELECT p.*, w.created_at as added_date FROM wishlist w JOIN products p ON w.product_id = p.id WHERE w.user_id = '$uid' ORDER BY w.created_at DESC";
$result = mysqli_query($conn, $sql);

$page_title = "รายการโปรด | Por Mae Bet Taled";
include 'header.php';
?>

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
            <h3 class="fw-bold mb-4">💖 รายการที่ชื่นชอบ</h3>

            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="row g-3">
                    <?php while ($p = mysqli_fetch_assoc($result)): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="card card-product">
                            <button onclick="toggleFeature('toggle_wishlist', <?= $p['id'] ?>, this); this.closest('.col-6').remove();" class="wishlist-tag liked"><i class="bi bi-heart-fill"></i></button>
                            <div class="product-img-wrapper">
                                <a href="product_detail.php?name=<?= urlencode($p['name']) ?>">
                                    <img src="<?= htmlspecialchars($p['image'], ENT_QUOTES, 'UTF-8') ?>" class="wishlist-img" alt="<?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>">
                                </a>
                            </div>
                            <div class="card-body d-flex flex-column text-center mt-2 p-3 pt-0">
                                <h6 class="fw-bold mb-2 text-truncate">
                                    <a href="product_detail.php?name=<?= urlencode($p['name']) ?>" class="product-name stretched-link"><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></a>
                                </h6>
                                <div class="mb-3">
                                    <span class="fw-bold" style="color:var(--blue-dark); font-size:1.2rem;">฿<?= number_format($p['price']) ?></span>
                                </div>
                                <div class="d-grid gap-2 position-relative" style="z-index:2;">
                                    <button type="button" class="btn btn-gradient btn-sm py-2 btn-quick-add"
                                            data-pid="<?= $p['id'] ?>" 
                                            data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-image="<?= htmlspecialchars($p['image'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-price="<?= $p['price'] ?>"
                                            data-stock="<?= $p['stock'] ?>"
                                            data-options="<?= htmlspecialchars($p['options'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-cart-plus"></i> เพิ่มลงตะกร้า
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 content-card rounded-4 shadow-sm">
                    <h5 class="fw-bold">ยังไม่มีสินค้าที่ถูกใจ</h5>
                    <a href="index.php" class="btn btn-gradient rounded-pill px-4 mt-3">ไปช้อปเลย</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Add to Cart Option Modal -->
<div class="modal fade" id="quickOptionModal" tabindex="-1" aria-labelledby="quickOptionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px; width: 95%;">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h6 class="modal-title fw-bold text-dark" id="quickOptionModalLabel"><i class="bi bi-bag-plus me-2" style="color: var(--blue-hover);"></i>เลือกตัวเลือกสินค้า</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 pb-4 pt-3">
                <!-- Product Preview Section -->
                <div class="d-flex align-items-center mb-3">
                    <div class="me-3 position-relative" style="width: 80px; height: 80px; flex-shrink: 0;">
                        <img id="quickOptionProductImage" src="" alt="" class="img-fluid rounded-3 border w-100 h-100" style="object-fit: cover;">
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <h6 id="quickOptionProductName" class="fw-bold text-dark text-truncate mb-1" style="font-size: 0.95rem;"></h6>
                        <div id="quickOptionProductPrice"></div>
                    </div>
                </div>
                
                <hr class="my-3 text-muted opacity-25">
                
                <div id="quickOptionBody"></div>
                
                <!-- Quantity Selector Section -->
                <div class="d-flex align-items-center justify-content-between mt-3 mb-4">
                    <span class="fw-bold text-secondary" style="font-size: 0.85rem;">จำนวน</span>
                    <div class="d-flex align-items-center bg-light rounded-pill p-1" style="border: 1px solid #E2E8F0;">
                        <button type="button" class="btn quick-qty-btn" onclick="changeQuickQty(-1)">
                            <i class="bi bi-dash"></i>
                        </button>
                        <input type="number" id="quickOptionQty" value="1" min="1" max="99" class="form-control form-control-sm text-center border-0 bg-transparent p-0 fw-bold text-dark" style="width: 40px; box-shadow: none; font-size: 0.95rem;" readonly>
                        <button type="button" class="btn quick-qty-btn" onclick="changeQuickQty(1)">
                            <i class="bi bi-plus"></i>
                        </button>
                    </div>
                </div>
                
                <button type="button" class="btn btn-gradient w-100 rounded-pill py-2 fw-bold shadow-sm" id="quickOptionSubmitBtn" onclick="submitQuickOption()">
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
    .quick-qty-btn {
        width: 30px;
        height: 30px;
        border-radius: 50% !important;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #CBD5E1 !important;
        background: white !important;
        color: var(--slate-dark) !important;
        font-size: 0.95rem;
        font-weight: bold;
        padding: 0;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    .quick-qty-btn:hover {
        background: var(--blue-hover) !important;
        color: white !important;
        border-color: var(--blue-hover) !important;
        box-shadow: 0 3px 10px rgba(127, 181, 255, 0.25);
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleFeature(action, pid, btn) {
    let fd = new FormData(); fd.append('action', action); fd.append('product_id', pid);
    fd.append('csrf_token', '<?= get_csrf_token() ?>');
    fetch('ajax.php', { method: 'POST', body: fd }).then(r=>r.json()).then(data => {
        const Toast = Swal.mixin({toast: true, position: 'top-end', showConfirmButton: false, timer: 1000});
        Toast.fire({icon: 'success', title: 'ลบเรียบร้อย'});
    });
}

let _quickAddProductId = null;
let _quickAddBtn = null;
let _quickOptionModalInstance = null;

// Event delegation for Add to Cart click
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-quick-add');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    const pid = parseInt(btn.dataset.pid);
    const opts = btn.dataset.options || '';
    quickAddToCart(pid, opts, btn);
});

function quickAddToCart(productId, optionsStr, btn) {
    _quickAddProductId = productId;
    _quickAddBtn = btn;

    const name = btn.dataset.name || '';
    const img = btn.dataset.image || '';
    const price = parseFloat(btn.dataset.price) || 0;
    const stock = parseInt(btn.dataset.stock) || 0;

    // If no options, add directly
    if (!optionsStr || optionsStr.trim() === '') {
        doQuickAdd(productId, '', btn, 1);
        return;
    }

    const body = document.getElementById('quickOptionBody');
    body.innerHTML = ''; // reset

    const qtyInput = document.getElementById('quickOptionQty');
    if (qtyInput) {
        qtyInput.value = 1;
        qtyInput.dataset.maxStock = stock;
    }

    const modalImg = document.getElementById('quickOptionProductImage');
    if (modalImg) {
        modalImg.src = img;
        modalImg.alt = name;
    }

    const modalName = document.getElementById('quickOptionProductName');
    if (modalName) modalName.textContent = name;

    const modalPrice = document.getElementById('quickOptionProductPrice');
    if (modalPrice) {
        modalPrice.innerHTML = `<span class="fw-bold" style="color:var(--blue-dark); font-size:1.15rem;">฿${formatNumber(price)}</span>`;
    }

    const groups = optionsStr.split('|');
    groups.forEach((group, gi) => {
        const parts = group.split(':');
        if (parts.length < 2) return;
        const optName = parts[0].trim();
        const values = parts.slice(1).join(':').split(',');

        let html = '<div class="quick-opt-group">';
        html += '<span class="quick-opt-label">' + escapeHtml(optName) + '</span>';
        html += '<div class="d-flex flex-wrap">';
        values.forEach((val, vi) => {
            val = val.trim();
            if (!val) return;
            const uid = 'qopt_' + gi + '_' + vi;
            html += '<span class="quick-opt-btn">';
            html += '<input type="radio" name="qopt_' + gi + '" id="' + uid + '" value="' + escapeHtml(val) + '" data-optname="' + escapeHtml(optName) + '" required>';
            html += '<label for="' + uid + '">' + escapeHtml(val) + '</label>';
            html += '</span>';
        });
        html += '</div></div>';
        body.innerHTML += html;
    });

    const modalEl = document.getElementById('quickOptionModal');
    if (!_quickOptionModalInstance) {
        _quickOptionModalInstance = new bootstrap.Modal(modalEl);
    }
    _quickOptionModalInstance.show();
}

function formatNumber(num) {
    return new Intl.NumberFormat('th-TH').format(num);
}

function changeQuickQty(amount) {
    const qtyInput = document.getElementById('quickOptionQty');
    if (!qtyInput) return;
    let val = parseInt(qtyInput.value) || 1;
    const maxStock = parseInt(qtyInput.dataset.maxStock) || 99;
    
    val += amount;
    if (val < 1) val = 1;
    if (val > maxStock) {
        val = maxStock;
        Swal.fire({ icon: 'warning', title: `มีสินค้าในสต็อกเพียง ${maxStock} ชิ้น`, toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
    }
    qtyInput.value = val;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
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
            g.querySelector('.quick-opt-label').style.color = '#dc3545';
            setTimeout(() => { g.querySelector('.quick-opt-label').style.color = ''; }, 2000);
        }
    });

    if (!allSelected) {
        Swal.fire({ icon: 'warning', title: 'กรุณาเลือกตัวเลือกให้ครบ', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
        return;
    }

    const opts = selectedOpts.join(', ');
    const qtyInput = document.getElementById('quickOptionQty');
    const qty = qtyInput ? parseInt(qtyInput.value) || 1 : 1;

    const modalEl = document.getElementById('quickOptionModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    doQuickAdd(_quickAddProductId, opts, _quickAddBtn, qty);
}

function doQuickAdd(productId, options, btn, qty = 1) {
    const origHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:14px;height:14px;"></span>';

    const fd = new FormData();
    fd.append('action', 'wishlist_to_cart');
    fd.append('product_id', productId);
    fd.append('qty', qty);
    fd.append('options', options);
    fd.append('csrf_token', '<?= get_csrf_token() ?>');

    fetch('ajax.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        if (data.status === 'success') {
            btn.innerHTML = '<i class="bi bi-check2"></i> เพิ่มแล้ว!';
            btn.classList.remove('btn-gradient');
            btn.classList.add('btn-success');

            const badge = document.getElementById('nav-cart-badge');
            if (badge) {
                badge.innerText = data.cart_count;
                badge.classList.remove('hidden');
            }

            Swal.fire({ icon: 'success', title: data.message, toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });

            const cardCol = btn.closest('.col-6');
            if (cardCol) {
                cardCol.style.transition = 'all 0.3s';
                cardCol.style.opacity = '0';
                cardCol.style.transform = 'scale(0.8)';
                setTimeout(() => {
                    cardCol.remove();
                    const container = document.querySelector('.row.g-3');
                    if (container && container.children.length === 0) {
                        location.reload();
                    }
                }, 300);
            }

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
</body>
</html>


