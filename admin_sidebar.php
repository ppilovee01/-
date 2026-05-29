<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<link rel="stylesheet" href="style.css?v=2.3">
<style>
    .sidebar { background: white; border-right: 1px solid #f1f5f9; padding: 30px 20px; }
    @media (min-width: 768px) { .sidebar { min-height: 100vh; } }
    .brand-logo { font-weight: 800; color: #1e293b; font-size: 1.4rem; letter-spacing: -0.5px; }
    .brand-logo span { color: #7FB5FF; }
    .admin-badge { background: #7FB5FF; color: white; font-size: 0.65rem; padding: 4px 8px; border-radius: 6px; vertical-align: middle; margin-left: 5px; font-weight: 700; letter-spacing: 0.5px; }
    .nav-link { color: #64748b; padding: 12px 20px; border-radius: 12px; margin-bottom: 5px; transition: all 0.3s ease; display: flex; align-items: center; font-weight: 500; text-decoration: none; }
    .nav-link i { font-size: 1.25rem; margin-right: 12px; width: 25px; text-align: center; color: #94a3b8; transition: color 0.3s; }
    .nav-link:hover { background-color: #f1f5f9; color: #7FB5FF; transform: translateX(4px); }
    .nav-link:hover i { color: #7FB5FF; }
    .nav-link.active { background-color: #7FB5FF; color: white; box-shadow: 0 8px 20px rgba(127, 181, 255, 0.35); }
    .nav-link.active i { color: white; }
    .nav-link.text-danger:hover { background-color: #fee2e2; color: #dc3545; }
    .nav-link.text-danger:hover i { color: #dc3545; }

    /* ======== Mobile Premium Floating Menu Overrides ======== */
    @media (max-width: 767px) {
        /* ปรับสไตล์ปุ่ม toggle หน้าเพจแอดมินเดิมให้กลายเป็น Header Bar ติดขอบบนสุด */
        button[data-bs-target="#sidebarMenu"] {
            position: sticky !important;
            top: 0;
            z-index: 1050;
            background: rgba(255, 255, 255, 0.85) !important;
            backdrop-filter: blur(15px) !important;
            -webkit-backdrop-filter: blur(15px) !important;
            border: none !important;
            border-bottom: 1px solid rgba(0,0,0,0.05) !important;
            padding: 16px 20px !important;
            font-size: 0 !important; /* ซ่อนตัวอักษรเดิม */
            display: flex !important;
            align-items: center;
            justify-content: space-between;
            border-radius: 0 !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02) !important;
            text-align: left;
            width: 100% !important;
        }
        
        /* ใส่ชื่อร้าน/แบรนด์แอดมินจำลองฝั่งซ้ายของแถบ */
        button[data-bs-target="#sidebarMenu"]::before {
            content: 'Por Mae Bet Taled \00a0 Admin';
            font-weight: 800;
            font-size: 1.15rem;
            color: #1e293b;
            font-family: 'Kanit', sans-serif;
            letter-spacing: -0.5px;
        }

        /* ปรับไอคอนแฮมเบอร์เกอร์ฝั่งขวา */
        button[data-bs-target="#sidebarMenu"] i {
            font-size: 1.5rem !important;
            color: #7FB5FF !important;
            margin: 0 !important;
            order: 2;
        }

        /* ปรับเมนูที่ดร็อปลงมาให้ลอยทับเนื้อหา (Overlay) แทนการดันเนื้อหาลงด้านล่าง */
        #sidebarMenu {
            position: absolute;
            top: 60px;
            left: 0;
            right: 0;
            z-index: 1040;
            background: white;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border-bottom: 3px solid #7FB5FF;
            max-height: calc(100vh - 60px);
            overflow-y: auto;
        }

        .sidebar {
            padding: 20px !important;
            border: none !important;
            min-height: auto !important;
        }

        /* ซ่อนโลโก้ในลิสต์เมนูบนมือถือเนื่องจากไปแสดงที่แถบด้านบนแล้ว */
        .sidebar .mb-5 {
            display: none !important;
        }
        
        /* ปรับปุ่มเมนูให้กดง่ายขึ้นในจอสัมผัส */
        .nav-link {
            padding: 14px 20px;
            font-size: 1.05rem;
        }
    }
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
        <?php
        $pending_orders_count = 0;
        if (isset($conn)) {
            $q_pending = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
            if ($q_pending) {
                $pending_orders_count = mysqli_fetch_assoc($q_pending)['count'] ?? 0;
            }
        }
        ?>
        <a class="nav-link <?= $current_page == 'admin_orders.php' ? 'active' : '' ?>" href="admin_orders.php">
            <i class="bi bi-clipboard-check"></i> รายการสั่งซื้อ
            <?php if($pending_orders_count > 0): ?>
                <span class="badge bg-danger ms-auto rounded-pill" style="font-size: 0.7rem; padding: 4px 8px; font-weight: 700;"><?= $pending_orders_count ?></span>
            <?php endif; ?>
        </a>
        <a class="nav-link <?= $current_page == 'admin_payments.php' ? 'active' : '' ?>" href="admin_payments.php"><i class="bi bi-credit-card"></i> ช่องทางชำระเงิน</a>
        <a class="nav-link <?= $current_page == 'admin_coupons.php' ? 'active' : '' ?>" href="admin_coupons.php"><i class="bi bi-ticket-perforated"></i> จัดการคูปอง</a>
        <a class="nav-link <?= $current_page == 'admin_flash_sale.php' ? 'active' : '' ?>" href="admin_flash_sale.php"><i class="bi bi-lightning-charge-fill"></i> จัดการ Flash Sale</a>
        <a class="nav-link <?= $current_page == 'admin_banners.php' ? 'active' : '' ?>" href="admin_banners.php"><i class="bi bi-images"></i> จัดการแบนเนอร์</a>
        <a class="nav-link <?= $current_page == 'admin_users.php' ? 'active' : '' ?>" href="admin_users.php"><i class="bi bi-people-fill"></i> จัดการสมาชิก</a>
        <a class="nav-link <?= $current_page == 'admin_settings.php' ? 'active' : '' ?>" href="admin_settings.php"><i class="bi bi-gear-fill"></i> ตั้งค่าร้านค้า</a>
        <a class="nav-link <?= $current_page == 'admin_reviews.php' ? 'active' : '' ?>" href="admin_reviews.php"><i class="bi bi-chat-square-quote me-2"></i> รีวิวสินค้า</a>
        <a class="nav-link <?= $current_page == 'admin_about.php' ? 'active' : '' ?>" href="admin_about.php"><i class="bi bi-info-circle me-2"></i> เกี่ยวกับเรา</a>
        <a class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>" href="index.php"><i class="bi bi-hand-index me-2"></i> หน้าแรก</a>
        <hr class="text-muted opacity-25">
        <a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-left"></i> ออกจากระบบ</a>
    </nav>
</div>

<script>
    // ระบบป้องกันการส่งฟอร์มซ้ำ (Double-Submit Prevention) สำหรับแอดมิน
    document.addEventListener('DOMContentLoaded', function() {
        document.addEventListener('submit', function(e) {
            var form = e.target;
            if (e.defaultPrevented) return;
            if (form.classList.contains('is-submitting')) {
                e.preventDefault();
                return false;
            }
            
            var activeBtn = document.activeElement;
            var btn = form.querySelector('button[type="submit"], input[type="submit"]');
            var submitBtn = (activeBtn && activeBtn.form === form && activeBtn.type === 'submit') ? activeBtn : btn;
            
            form.classList.add('is-submitting');
            
            if (submitBtn && submitBtn.name) {
                var hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = submitBtn.name;
                hiddenInput.value = submitBtn.value;
                form.appendChild(hiddenInput);
            }
            
            if (submitBtn) {
                setTimeout(function() {
                    submitBtn.disabled = true;
                    if (submitBtn.tagName === 'INPUT') {
                        submitBtn.value = 'กำลังประมวลผล...';
                    } else {
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังส่งข้อมูล...';
                    }
                }, 1);
            }
        });
    });
</script>
