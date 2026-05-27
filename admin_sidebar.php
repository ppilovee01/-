<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    .sidebar { background: white; border-right: 1px solid #f0f0f0; padding: 30px 20px; }
    @media (min-width: 768px) { .sidebar { min-height: 100vh; } }
    .brand-logo { font-weight: 800; color: #333; font-size: 1.5rem; letter-spacing: -1px; }
    .brand-logo span { color: #AEE2FF; }
    .admin-badge { background: #333; color: white; font-size: 0.6rem; padding: 3px 8px; border-radius: 4px; vertical-align: middle; margin-left: 5px; letter-spacing: 1px; }
    .nav-link { color: #777; padding: 12px 20px; border-radius: 12px; margin-bottom: 5px; transition: 0.3s; display: flex; align-items: center; font-weight: 500; text-decoration: none; }
    .nav-link i { font-size: 1.2rem; margin-right: 12px; width: 25px; text-align: center; }
    .nav-link:hover { background-color: #F0F8FF; color: #AEE2FF; transform: translateX(5px); }
    .nav-link.active { background-color: #AEE2FF; color: white; box-shadow: 0 4px 10px rgba(174, 226, 255, 0.4); }
    .nav-link.text-danger:hover { background-color: #fee2e2; color: #dc3545; }
</style>

<div class="sidebar d-flex flex-column h-100">
    <div class="mb-5 px-2">
        <div class="brand-logo">Por Mae Bet Taled</div>
        <span class="brand-logo admin-badge">MERCHANT ADMIN</span>
    </div>

    <nav class="nav flex-column">
        <a class="nav-link <?= $current_page == 'admin_dashboard.php' ? 'active' : '' ?>" href="admin_dashboard.php"><i class="bi bi-grid-1x2-fill"></i> ภาพรวมร้านค้า</a>
        <a class="nav-link <?= $current_page == 'admin.php' ? 'active' : '' ?>" href="admin.php"><i class="bi bi-box-seam"></i> จัดการสินค้า</a>
        <a class="nav-link <?= $current_page == 'admin_categories.php' ? 'active' : '' ?>" href="admin_categories.php"><i class="bi bi-tags-fill"></i> หมวดหมู่สินค้า</a>
        <a class="nav-link <?= $current_page == 'admin_orders.php' ? 'active' : '' ?>" href="admin_orders.php"><i class="bi bi-clipboard-check"></i> รายการสั่งซื้อ</a>
        <a class="nav-link <?= $current_page == 'admin_payments.php' ? 'active' : '' ?>" href="admin_payments.php"><i class="bi bi-credit-card"></i> ช่องทางชำระเงิน</a>
        <a class="nav-link <?= $current_page == 'admin_coupons.php' ? 'active' : '' ?>" href="admin_coupons.php"><i class="bi bi-ticket-perforated"></i> จัดการคูปอง</a>
        <a class="nav-link <?= $current_page == 'admin_banners.php' ? 'active' : '' ?>" href="admin_banners.php"><i class="bi bi-images"></i> จัดการแบนเนอร์</a>
        <a class="nav-link <?= $current_page == 'admin_settings.php' ? 'active' : '' ?>" href="admin_settings.php"><i class="bi bi-gear-fill"></i> ตั้งค่าร้านค้า</a>
        <a class="nav-link <?= $current_page == 'admin_reviews.php' ? 'active' : '' ?>" href="admin_reviews.php"><i class="bi bi-chat-square-quote me-2"></i> รีวิวสินค้า</a>
        <a class="nav-link <?= $current_page == 'admin_about.php' ? 'active' : '' ?>" href="admin_about.php"><i class="bi bi-info-circle me-2"></i> เกี่ยวกับเรา</a>
        <a class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>" href="index.php"><i class="bi bi-hand-index me-2"></i> หน้าแรก</a>
        <hr class="text-muted opacity-25">
        <a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-left"></i> ออกจากระบบ</a>
    </nav>
</div>
