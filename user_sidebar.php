<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="list-group list-group-flush">
        <div class="list-group-item bg-dark text-white p-3 text-center">
            <div class="d-flex justify-content-center mb-2">
                <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="bi bi-person-fill"></i>
                </div>
            </div>
            <h6 class="fw-bold mb-0 text-truncate"><?= htmlspecialchars($_SESSION['fullname'] ?? 'User') ?></h6>
        </div>

        <a href="profile.php" class="list-group-item list-group-item-action p-3 <?= $current_page == 'profile.php' ? 'active bg-blue text-white' : '' ?>">
            <i class="bi bi-person-gear me-2"></i> ข้อมูลส่วนตัว
        </a>
        <a href="my_orders.php" class="list-group-item list-group-item-action p-3 <?= $current_page == 'my_orders.php' ? 'active bg-blue text-white' : '' ?>">
            <i class="bi bi-box-seam me-2"></i> ประวัติคำสั่งซื้อ
        </a>
        <a href="wishlist.php" class="list-group-item list-group-item-action p-3 <?= $current_page == 'wishlist.php' ? 'active bg-blue text-white' : '' ?>">
            <i class="bi bi-heart me-2"></i> รายการที่ชอบ
        </a>

        <a href="cart.php" class="list-group-item list-group-item-action p-3 <?= $current_page == 'cart.php' ? 'active bg-blue text-white' : '' ?>">
            <i class="bi bi-cart me-2"></i> ตะกร้าสินค้า
        </a>
        <a href="index.php#shop" class="list-group-item list-group-item-action p-3 <?= $current_page == 'index.php' ? 'active bg-blue text-white' : '' ?>">
            <i class="bi bi-basket-fill me-2"></i> เลือกชมสินค้า
        </a>
        <a href="logout.php" class="list-group-item list-group-item-action p-3 text-danger">
            <i class="bi bi-power me-2"></i> ออกจากระบบ
        </a>
    </div>
</div>

<style>
    .bg-blue { background-color: #AEE2FF !important; border-color: #AEE2FF !important; }
    .list-group-item-action:hover { background-color: #F0F8FF; color: #AEE2FF; }
    .list-group-item.active { z-index: 2; color: #fff; background-color: #AEE2FF; border-color: #AEE2FF; }

    /* Dark Mode Overrides for Sidebar */
    body.dark-theme .bg-blue {
        background-color: var(--blue-main) !important;
        border-color: var(--blue-main) !important;
        color: #060913 !important;
    }
    body.dark-theme .list-group-item.active {
        background-color: var(--blue-main) !important;
        border-color: var(--blue-main) !important;
        color: #060913 !important;
    }
    body.dark-theme .list-group-item-action:hover {
        background-color: var(--blue-light) !important;
        color: var(--blue-main) !important;
    }
</style>