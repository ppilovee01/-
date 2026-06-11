<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<link rel="stylesheet" href="style.css?v=2.7">
<style>
    .sidebar { background: white; border-right: 1px solid #f1f5f9; padding: 30px 20px; }
    @media (min-width: 768px) { 
        #sidebarMenu { 
            position: sticky !important; 
            top: 0 !important; 
            height: 100vh !important; 
            overflow-y: auto !important; 
            z-index: 1000 !important;
        } 
        .sidebar {
            min-height: 100vh !important;
        }
    }
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

    /* ======== Mobile Premium Off-Canvas Sidenav Drawer ======== */
    @media (max-width: 767px) {
        /* แถบ Header บาร์สีขาวคลีนด้านบนสุด */
        button[data-bs-target="#sidebarMenu"] {
            position: fixed !important;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            background: rgba(255, 255, 255, 0.85) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            border: none !important;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05) !important;
            padding: 12px 20px !important;
            font-size: 0 !important; /* ซ่อนตัวอักษรเดิม */
            display: flex !important;
            align-items: center;
            justify-content: space-between;
            border-radius: 0 !important;
            box-shadow: 0 4px 30px rgba(0,0,0,0.02) !important;
            width: 100% !important;
            height: 60px;
        }

        button[data-bs-target="#sidebarMenu"]::before {
            content: 'Por Mae Bet Taled \00a0 Admin';
            font-weight: 800;
            font-size: 1.1rem;
            color: #1e293b;
            font-family: 'Kanit', sans-serif;
            letter-spacing: -0.5px;
        }

        button[data-bs-target="#sidebarMenu"] i {
            font-size: 1.4rem !important;
            color: #475569 !important;
            margin: 0 !important;
            order: 2;
            width: 38px;
            height: 38px;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        /* แปลง #sidebarMenu ให้เป็นลิ้นชักสไลด์จากซ้าย (Off-Canvas Side Drawer) */
        #sidebarMenu {
            display: block !important; /* ปิดการซ่อนซ้อนของ Bootstrap collapse */
            position: fixed !important;
            top: 0 !important;
            left: -280px !important;
            width: 280px !important;
            height: 100vh !important;
            z-index: 2000 !important;
            background: #0f172a !important; /* พื้นหลังสีเข้มพรีเมียม (Dark Sidenav) */
            box-shadow: 15px 0 40px rgba(0,0,0,0.15) !important;
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
            overflow-y: auto !important;
        }
        
        #sidebarMenu.show {
            transform: translateX(280px) !important;
        }

        .sidebar {
            padding: 25px 20px !important;
            border: none !important;
            background: transparent !important;
            display: flex !important;
            flex-direction: column !important;
            min-height: 100% !important;
        }

        /* แสดงโลโก้ในลิ้นชักเมนูให้หรูหรา */
        .sidebar .mb-5 {
            display: block !important;
            margin-bottom: 30px !important;
            padding-bottom: 20px !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
        }
        
        .brand-logo {
            color: #ffffff !important;
            font-size: 1.25rem !important;
        }
        
        .admin-badge {
            background: rgba(127, 181, 255, 0.2) !important;
            color: #7FB5FF !important;
            border: 1px solid rgba(127, 181, 255, 0.3) !important;
        }

        /* ดีไซน์ลิงก์เมนูสไตล์มินิมอลลิสต์แบบ Stripe/Tailwind */
        .nav-link {
            padding: 12px 16px !important;
            font-size: 0.92rem !important;
            border-radius: 12px !important;
            margin-bottom: 6px !important;
            color: #94a3b8 !important;
            background: transparent !important;
            border: none !important;
            transition: all 0.2s ease !important;
            display: flex !important;
            align-items: center;
            justify-content: flex-start !important;
        }
        
        .nav-link i {
            margin-right: 12px !important;
            width: 24px !important;
            height: 24px !important;
            background: transparent !important;
            color: #64748b !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 1.15rem !important;
            transition: all 0.2s !important;
        }

        /* เอฟเฟกต์โฮเวอร์เมนู */
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.05) !important;
            color: #ffffff !important;
            transform: translateX(4px) !important;
        }
        .nav-link:hover i {
            color: #7FB5FF !important;
        }

        /* เมนู Active โดดเด่นพรีเมียมเรืองแสง */
        .nav-link.active {
            background: #7FB5FF !important;
            color: #ffffff !important;
            box-shadow: 0 8px 20px rgba(127, 181, 255, 0.25) !important;
            transform: scale(1.02) !important;
        }
        .nav-link.active i {
            color: #ffffff !important;
        }

        /* ปุ่มออกจากระบบสีแดงซอฟต์ */
        .nav-link.text-danger {
            color: #f43f5e !important;
        }
        .nav-link.text-danger i {
            color: #f43f5e !important;
        }
        .nav-link.text-danger:hover {
            background: rgba(244, 63, 94, 0.1) !important;
            color: #ff859b !important;
        }

        /* แผ่นพื้นหลังเบลอสีดำโปร่งแสง (Backdrop Overlay) */
        .admin-sidebar-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 1999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .admin-sidebar-backdrop.show {
            opacity: 1;
            visibility: visible;
        }

        /* ดันเนื้อหาหลักลงมาใต้ Header */
        body {
            padding-top: 60px !important;
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
            <span id="sidebar-pending-orders-badge" class="badge bg-danger ms-auto rounded-pill <?= $pending_orders_count > 0 ? '' : 'd-none' ?>" style="font-size: 0.7rem; padding: 4px 8px; font-weight: 700;"><?= $pending_orders_count ?></span>
        </a>
        <a class="nav-link <?= $current_page == 'admin_payments.php' ? 'active' : '' ?>" href="admin_payments.php"><i class="bi bi-credit-card"></i> ช่องทางชำระเงิน</a>
        <a class="nav-link <?= $current_page == 'admin_coupons.php' ? 'active' : '' ?>" href="admin_coupons.php"><i class="bi bi-ticket-perforated"></i> จัดการคูปอง</a>
        <a class="nav-link <?= $current_page == 'admin_flash_sale.php' ? 'active' : '' ?>" href="admin_flash_sale.php"><i class="bi bi-lightning-charge-fill"></i> จัดการ Flash Sale</a>
        <a class="nav-link <?= $current_page == 'admin_banners.php' ? 'active' : '' ?>" href="admin_banners.php"><i class="bi bi-images"></i> จัดการแบนเนอร์</a>
        <a class="nav-link <?= $current_page == 'admin_users.php' ? 'active' : '' ?>" href="admin_users.php"><i class="bi bi-people-fill"></i> จัดการสมาชิก</a>
        <a class="nav-link <?= $current_page == 'admin_send_mail.php' ? 'active' : '' ?>" href="admin_send_mail.php"><i class="bi bi-send me-2"></i> ส่งอีเมลหาลูกค้า</a>
        <a class="nav-link <?= $current_page == 'admin_settings.php' ? 'active' : '' ?>" href="admin_settings.php"><i class="bi bi-gear-fill"></i> ตั้งค่าร้านค้า</a>
        <a class="nav-link <?= $current_page == 'admin_logs.php' ? 'active' : '' ?>" href="admin_logs.php"><i class="bi bi-clock-history"></i> ประวัติการทำงาน</a>
        <a class="nav-link <?= $current_page == 'admin_reviews.php' ? 'active' : '' ?>" href="admin_reviews.php"><i class="bi bi-chat-square-quote me-2"></i> รีวิวสินค้า</a>
        <?php
        $unread_contacts_count = 0;
        if (isset($conn)) {
            $q_unread_contacts = mysqli_query($conn, "SELECT COUNT(*) as count FROM contact_messages WHERE status = 'unread'");
            if ($q_unread_contacts) {
                $unread_contacts_count = mysqli_fetch_assoc($q_unread_contacts)['count'] ?? 0;
            }
        }
        ?>
        <a class="nav-link <?= $current_page == 'admin_contact.php' ? 'active' : '' ?>" href="admin_contact.php">
            <i class="bi bi-envelope me-2"></i> ข้อความติดต่อ
            <span id="unread-badge" class="badge bg-danger ms-auto rounded-pill <?= $unread_contacts_count > 0 ? '' : 'd-none' ?>" style="font-size: 0.7rem; padding: 4px 8px; font-weight: 700;">ใหม่ <?= $unread_contacts_count ?></span>
        </a>
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

        // --- ระบบควบคุม Backdrop สำหรับลิ้นชักเมนูสไลด์บนมือถือ ---
        const sidebar = document.getElementById('sidebarMenu');
        if (sidebar) {
            // สร้าง Backdrop หากยังไม่มีในระบบ
            let backdrop = document.querySelector('.admin-sidebar-backdrop');
            if (!backdrop) {
                backdrop = document.createElement('div');
                backdrop.className = 'admin-sidebar-backdrop';
                document.body.appendChild(backdrop);
            }

            // ฟังก์ชันซิงค์สถานะ Backdrop
            function checkSidebarState() {
                if (sidebar.classList.contains('show')) {
                    backdrop.classList.add('show');
                } else {
                    backdrop.classList.remove('show');
                }
            }

            // ดักจับการเปลี่ยนแปลงคลาส (MutationObserver) เพื่อตรวจการเปิด/ปิดลิ้นชักเมนู
            const observer = new MutationObserver(checkSidebarState);
            observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });

            // คลิกพื้นที่ว่างสีดำ (Backdrop) เพื่อปิดเมนูสไลด์
            backdrop.addEventListener('click', function(e) {
                if (window.innerWidth < 768) {
                    e.preventDefault();
                    sidebar.classList.remove('show');
                }
            });

            const toggleBtn = document.querySelector('button[data-bs-target="#sidebarMenu"]');
            if (toggleBtn) {
                // ดักจับการกดปุ่มเมนูแฮมเบอร์เกอร์บนมือถือเพื่อ toggle คลาสตรงๆ โดยไม่ผ่าน Bootstrap Collapse
                toggleBtn.addEventListener('click', function(e) {
                    if (window.innerWidth < 768) {
                        e.preventDefault();
                        e.stopPropagation();
                        sidebar.classList.toggle('show');
                    }
                });
            }

            // --- Swipe-to-Reveal Sidebar Menu for Mobile Devices ---
            let startX = 0;
            let startY = 0;
            let currentX = 0;
            let currentY = 0;
            let isSwiping = false;
            let swipeType = ''; // 'open' หรือ 'close'
            const swipeWidth = 280; // ความกว้างของ Sidebar

            document.addEventListener('touchstart', function(e) {
                if (window.innerWidth >= 768) return;

                const touch = e.touches[0];
                startX = touch.clientX;
                startY = touch.clientY;
                currentX = startX;
                currentY = startY;

                const isOpen = sidebar.classList.contains('show');

                if (!isOpen) {
                    // ปัดจากขอบซ้ายเพื่อเปิด
                    if (startX < 35) {
                        isSwiping = true;
                        swipeType = 'open';
                        sidebar.style.transition = 'none';
                        backdrop.style.transition = 'none';
                        backdrop.style.visibility = 'visible';
                    }
                } else {
                    // ปัดซ้ายบนตัวเมนูหรือ Backdrop เพื่อปิด
                    if (e.target.closest('#sidebarMenu') || e.target.classList.contains('admin-sidebar-backdrop')) {
                        isSwiping = true;
                        swipeType = 'close';
                        sidebar.style.transition = 'none';
                        backdrop.style.transition = 'none';
                    }
                }
            }, { passive: true });

            document.addEventListener('touchmove', function(e) {
                if (!isSwiping) return;

                const touch = e.touches[0];
                currentX = touch.clientX;
                currentY = touch.clientY;

                const dx = currentX - startX;
                const dy = currentY - startY;

                // ถ้าขยับแนวตั้งเยอะกว่าแนวนอนให้ยกเลิกสไลด์เมนู
                if (Math.abs(dy) > Math.abs(dx) * 1.2) {
                    isSwiping = false;
                    sidebar.style.transition = '';
                    backdrop.style.transition = '';
                    sidebar.style.transform = '';
                    backdrop.style.opacity = '';
                    backdrop.style.visibility = '';
                    return;
                }

                if (swipeType === 'open') {
                    if (dx < 0) return;
                    // ป้องกันการเลื่อนหน้าเพจขณะสไลด์
                    if (e.cancelable) e.preventDefault();
                    const translateX = Math.min(swipeWidth, dx);
                    sidebar.style.transform = `translateX(${translateX}px)`;
                    
                    const progress = translateX / swipeWidth;
                    backdrop.style.opacity = progress;
                } else if (swipeType === 'close') {
                    if (dx > 0) return;
                    // ป้องกันการเลื่อนหน้าเพจขณะสไลด์
                    if (e.cancelable) e.preventDefault();
                    const translateX = Math.min(swipeWidth, Math.max(0, swipeWidth + dx));
                    sidebar.style.transform = `translateX(${translateX}px)`;

                    const progress = translateX / swipeWidth;
                    backdrop.style.opacity = progress;
                }
            }, { passive: false });

            document.addEventListener('touchend', function(e) {
                if (!isSwiping) return;
                isSwiping = false;

                // คืนค่า transition ปกติเพื่อให้แอนิเมชันลื่นไหล
                sidebar.style.transition = '';
                backdrop.style.transition = '';

                const dx = currentX - startX;
                const isOpen = sidebar.classList.contains('show');

                if (swipeType === 'open') {
                    if (dx > 80) {
                        sidebar.classList.add('show');
                    }
                    // ล้างสไตล์อินไลน์ทันทีเพื่อให้สไตล์จากคลาส CSS ทำงานต่อเนื่องโดยไม่มีอาการสะดุด
                    sidebar.style.transform = '';
                    backdrop.style.opacity = '';
                } else if (swipeType === 'close') {
                    if (dx < -80) {
                        sidebar.classList.remove('show');
                    }
                    sidebar.style.transform = '';
                    backdrop.style.opacity = '';
                }
            });
        }
    });
</script>

<?php
// Get notification sound setting
$shop_sound = 'chime';
if (isset($conn)) {
    $q_sound = mysqli_query($conn, "SELECT notification_sound FROM shop_settings WHERE id = 1");
    if ($q_sound && mysqli_num_rows($q_sound) > 0) {
        $shop_sound = mysqli_fetch_assoc($q_sound)['notification_sound'] ?? 'chime';
    }
}
?>

<!-- Admin Premium Real-time Notification Bell Widget -->
<div class="admin-bell-container">
    <div class="admin-bell-btn" id="adminBellBtn" onclick="toggleAdminNotifications(event)" title="การแจ้งเตือน">
        <i class="bi bi-bell-fill" style="font-size: 1.25rem;"></i>
        <span class="admin-bell-badge d-none" id="adminBellBadge">0</span>
    </div>
    
    <div class="admin-bell-dropdown" id="adminBellDropdown">
        <div class="admin-bell-header">
            <span class="d-flex align-items-center"><i class="bi bi-bell me-2"></i>การแจ้งเตือนแอดมิน</span>
            <button onclick="testAdminSound(event)" class="btn btn-link p-0 text-decoration-none text-muted small ms-auto me-2" style="font-size: 0.72rem; border: none; background: transparent; cursor: pointer; display: inline-flex; align-items: center;" title="ทดสอบเสียงระบบ"><i class="bi bi-volume-up-fill me-1"></i>ทดสอบเสียง</button>
            <div class="d-flex gap-2">
                <button onclick="markAllAdminRead(event)" class="btn btn-link p-0 text-decoration-none text-primary small" style="font-size: 0.72rem; border: none; background: transparent; cursor: pointer;">อ่านทั้งหมด</button>
                <span class="text-muted" style="font-size: 0.72rem;">|</span>
                <button onclick="clearAllAdminNotifications(event)" class="btn btn-link p-0 text-decoration-none text-danger small" style="font-size: 0.72rem; border: none; background: transparent; cursor: pointer;">ล้างทั้งหมด</button>
            </div>
        </div>
        <div class="admin-bell-body" id="adminBellList">
            <div class="text-center py-4 text-muted">
                <i class="bi bi-bell-slash fs-3 mb-2 d-block opacity-25"></i>
                ไม่มีการแจ้งเตือนใหม่
            </div>
        </div>
        <div class="admin-bell-footer">
            <a href="admin_logs.php" class="text-decoration-none text-secondary small" style="font-size: 0.8rem;">
                ดูประวัติการทำงานทั้งหมด <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</div>

<!-- Admin Floating Toast Notification Container -->
<div class="admin-toast-container" id="adminToastContainer"></div>

<style>
    /* CSS for Bell Widget */
    .admin-bell-container {
        position: fixed;
        top: 15px;
        right: 25px;
        z-index: 1060;
        font-family: 'Kanit', sans-serif;
    }
    
    @media (max-width: 767px) {
        .admin-bell-container {
            top: 11px;
            right: 75px;
        }
    }
    
    .admin-bell-btn {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: white;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        position: relative;
        transition: all 0.2s ease;
        color: #475569;
    }
    
    .admin-bell-btn:hover {
        background: #f8fafc;
        color: #7FB5FF;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(127, 181, 255, 0.2);
    }
    
    .admin-bell-badge {
        position: absolute;
        top: -3px;
        right: -3px;
        background: #ef4444;
        color: white;
        font-size: 0.65rem;
        font-weight: 700;
        min-width: 18px;
        height: 18px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid white;
        padding: 0 4px;
        box-shadow: 0 2px 5px rgba(239, 68, 68, 0.4);
    }
    
    .admin-bell-dropdown {
        position: absolute;
        top: 48px;
        right: 0;
        width: 320px;
        background: white;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        display: none;
        flex-direction: column;
        overflow: hidden;
        z-index: 1070;
        animation: adminFadeIn 0.2s ease;
    }
    
    @keyframes adminFadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .admin-bell-dropdown.show {
        display: flex;
    }
    
    @media (max-width: 576px) {
        .admin-bell-dropdown {
            right: -70px;
            width: 290px;
        }
    }
    
    .admin-bell-header {
        padding: 14px 16px;
        border-bottom: 1px solid #f1f5f9;
        font-weight: 600;
        color: #1e293b;
        font-size: 0.88rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8fafc;
    }
    
    .admin-bell-body {
        max-height: 280px;
        overflow-y: auto;
    }
    
    .admin-bell-footer {
        padding: 12px;
        border-top: 1px solid #f1f5f9;
        text-align: center;
        background: #f8fafc;
    }
    
    .admin-notif-item {
        padding: 12px 16px;
        border-bottom: 1px solid #f8fafc;
        cursor: pointer;
        transition: background 0.2s;
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }
    
    .admin-notif-item:hover {
        background: #f1f5f9;
    }
    
    .admin-notif-item.unread {
        background: #f0f7ff;
        border-left: 3px solid #7FB5FF;
    }
    
    .admin-notif-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1rem;
    }
    
    .admin-notif-icon.order { background: #e0f2fe; color: #0284c7; }
    .admin-notif-icon.review { background: #fef9c3; color: #ca8a04; }
    .admin-notif-icon.contact { background: #ccfbf1; color: #0d9488; }
    .admin-notif-icon.default { background: #f1f5f9; color: #475569; }
    
    .admin-notif-content {
        flex-grow: 1;
        min-width: 0;
    }
    
    .admin-notif-title {
        font-weight: 600;
        color: #1e293b;
        font-size: 0.82rem;
        margin-bottom: 2px;
        display: block;
    }
    
    .admin-notif-msg {
        color: #64748b;
        font-size: 0.78rem;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .admin-notif-time {
        font-size: 0.7rem;
        color: #94a3b8;
        display: block;
        margin-top: 4px;
    }
    
    /* Shake keyframes */
    @keyframes admin-bell-shake {
        0% { transform: rotate(0); }
        15% { transform: rotate(15deg); }
        30% { transform: rotate(-15deg); }
        45% { transform: rotate(10deg); }
        60% { transform: rotate(-10deg); }
        75% { transform: rotate(5deg); }
        85% { transform: rotate(-5deg); }
        100% { transform: rotate(0); }
    }
    
    .admin-bell-shake {
        animation: admin-bell-shake 0.8s ease-in-out;
    }

    /* CSS for Toast Alerts */
    .admin-toast-container {
        position: fixed;
        top: 70px;
        right: 20px;
        z-index: 2100;
        display: flex;
        flex-direction: column;
        gap: 10px;
        pointer-events: none;
        font-family: 'Kanit', sans-serif;
    }
    
    .admin-toast {
        pointer-events: auto;
        width: 300px;
        background: white;
        border-radius: 12px;
        border-left: 5px solid #7FB5FF;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        padding: 12px 16px;
        display: flex;
        gap: 12px;
        align-items: flex-start;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        transform: translateX(350px);
        opacity: 0;
    }
    
    .admin-toast.show {
        transform: translateX(0);
        opacity: 1;
    }
    
    .admin-toast.order { border-left-color: #0284c7; }
    .admin-toast.review { border-left-color: #ca8a04; }
    .admin-toast.contact { border-left-color: #0d9488; }
    .admin-toast.default { border-left-color: #64748b; }
    
    .admin-toast-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1rem;
    }
    
    .admin-toast-icon.order { background: #e0f2fe; color: #0284c7; }
    .admin-toast-icon.review { background: #fef9c3; color: #ca8a04; }
    .admin-toast-icon.contact { background: #ccfbf1; color: #0d9488; }
    .admin-toast-icon.default { background: #f1f5f9; color: #475569; }
    
    .admin-toast-body {
        flex-grow: 1;
        min-width: 0;
    }
    
    .admin-toast-title {
        font-weight: 600;
        color: #1e293b;
        font-size: 0.82rem;
        margin-bottom: 2px;
        display: block;
    }
    
    .admin-toast-msg {
        color: #64748b;
        font-size: 0.78rem;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
</style>

<script>
(function() {
    const soundType = <?= json_encode($shop_sound) ?>;
    let maxSeenId = parseInt(localStorage.getItem('admin_max_seen_id')) || 0;
    let isPolling = false;
    let audioActivated = false;

    // Open/Close Dropdown
    window.toggleAdminNotifications = function(e) {
        if (e) e.stopPropagation();
        const dropdown = document.getElementById('adminBellDropdown');
        dropdown.classList.toggle('show');
        if (dropdown.classList.contains('show')) {
            loadAdminNotifications();
            activateAudioContext();
        }
    };

    // Close on click outside
    document.addEventListener('click', function(e) {
        const container = document.querySelector('.admin-bell-container');
        if (container && !container.contains(e.target)) {
            const dropdown = document.getElementById('adminBellDropdown');
            if (dropdown) dropdown.classList.remove('show');
        }
    });

    // Activate AudioContext upon first user gesture
    function activateAudioContext() {
        if (audioActivated) return;
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) {
                const ctx = new AudioContext();
                if (ctx.state === 'suspended') {
                    ctx.resume();
                }
                audioActivated = true;
            }
        } catch (e) {
            console.warn('Failed to activate AudioContext:', e);
        }
    }
    document.addEventListener('click', activateAudioContext, { once: true });
    document.addEventListener('keydown', activateAudioContext, { once: true });

    // Web Audio API Sound Generator
    function playSoundAlert() {
        if (soundType === 'mute') return;
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            const now = ctx.currentTime;
            
            if (soundType === 'custom') {
                let audioUrl = '';
                const hasUploadedFile = <?php
                    $uploaded_sounds = glob("uploads/custom_alarm.*");
                    echo (!empty($uploaded_sounds) && file_exists($uploaded_sounds[0])) ? 'true' : 'false';
                ?>;
                if (hasUploadedFile) {
                    audioUrl = '<?php
                        $uploaded_sounds = glob("uploads/custom_alarm.*");
                        echo !empty($uploaded_sounds) ? $uploaded_sounds[0] : '';
                    ?>?v=' + Date.now();
                } else {
                    const customSoundUrl = <?= json_encode(getenv('CUSTOM_SOUND_URL') ?: '') ?>;
                    if (customSoundUrl) {
                        audioUrl = customSoundUrl;
                    }
                }
                if (audioUrl) {
                    const audio = new Audio(audioUrl);
                    audio.play().catch(err => console.warn('Custom audio playback failed:', err));
                } else {
                    // Fallback to chime
                    const osc1 = ctx.createOscillator();
                    const gain1 = ctx.createGain();
                    osc1.type = 'sine';
                    osc1.frequency.setValueAtTime(659.25, now);
                    gain1.gain.setValueAtTime(0.1, now);
                    gain1.gain.exponentialRampToValueAtTime(0.0001, now + 0.5);
                    osc1.connect(gain1);
                    gain1.connect(ctx.destination);
                    osc1.start(now);
                    osc1.stop(now + 0.5);
                    
                    const osc2 = ctx.createOscillator();
                    const gain2 = ctx.createGain();
                    osc2.type = 'sine';
                    osc2.frequency.setValueAtTime(880.00, now + 0.1);
                    gain2.gain.setValueAtTime(0.15, now + 0.1);
                    gain2.gain.exponentialRampToValueAtTime(0.0001, now + 0.7);
                    osc2.connect(gain2);
                    gain2.connect(ctx.destination);
                    osc2.start(now + 0.1);
                    osc2.stop(now + 0.7);
                }
                return;
            }
            
            if (soundType === 'chime') {
                const osc1 = ctx.createOscillator();
                const gain1 = ctx.createGain();
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(659.25, now);
                gain1.gain.setValueAtTime(0.1, now);
                gain1.gain.exponentialRampToValueAtTime(0.0001, now + 0.5);
                osc1.connect(gain1);
                gain1.connect(ctx.destination);
                osc1.start(now);
                osc1.stop(now + 0.5);
                
                const osc2 = ctx.createOscillator();
                const gain2 = ctx.createGain();
                osc2.type = 'sine';
                osc2.frequency.setValueAtTime(880.00, now + 0.1);
                gain2.gain.setValueAtTime(0.15, now + 0.1);
                gain2.gain.exponentialRampToValueAtTime(0.0001, now + 0.7);
                osc2.connect(gain2);
                gain2.connect(ctx.destination);
                osc2.start(now + 0.1);
                osc2.stop(now + 0.7);
            } else if (soundType === 'glass') {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(1500, now);
                gain.gain.setValueAtTime(0.1, now);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.3);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now);
                osc.stop(now + 0.3);
            } else if (soundType === 'beep') {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(880, now);
                gain.gain.setValueAtTime(0.1, now);
                gain.gain.setValueAtTime(0.1, now + 0.15);
                gain.gain.linearRampToValueAtTime(0.0001, now + 0.2);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now);
                osc.stop(now + 0.2);
            } else if (soundType === 'piano') {
                const notes = [261.63, 329.63, 392.00, 523.25];
                notes.forEach((freq, index) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, now + index * 0.05);
                    gain.gain.setValueAtTime(0.08, now + index * 0.05);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now + 1.0);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(now + index * 0.05);
                    osc.stop(now + 1.0);
                });
            }
        } catch (e) {
            console.warn('AudioContext playback failed:', e);
        }
    }

    // Sound Test Button Click handler
    window.testAdminSound = function(e) {
        if (e) e.stopPropagation();
        activateAudioContext();
        playSoundAlert();
        triggerShake();
    };

    // Trigger Shake Animation
    function triggerShake() {
        const bellBtn = document.getElementById('adminBellBtn');
        if (bellBtn) {
            bellBtn.classList.remove('admin-bell-shake');
            void bellBtn.offsetWidth; // Trigger reflow
            bellBtn.classList.add('admin-bell-shake');
        }
    }

    // Get Notification Icon depending on URL/Title
    function getNotificationIconClass(url) {
        if (!url) return 'default bi-bell';
        if (url.includes('admin_orders')) return 'order bi-box-seam';
        if (url.includes('admin_reviews')) return 'review bi-star-fill';
        if (url.includes('admin_contact')) return 'contact bi-envelope';
        return 'default bi-bell';
    }

    // Display beautiful toast alert
    function showToastNotification(title, message, url) {
        const container = document.getElementById('adminToastContainer');
        if (!container) return;
        
        const toast = document.createElement('div');
        const iconClass = getNotificationIconClass(url);
        const typeClass = iconClass.split(' ')[0];
        const iconBiClass = iconClass.split(' ')[1];
        
        toast.className = `admin-toast ${typeClass}`;
        
        toast.innerHTML = `
            <div class="admin-toast-icon ${typeClass}">
                <i class="bi ${iconBiClass}"></i>
            </div>
            <div class="admin-toast-body">
                <span class="admin-toast-title">${title}</span>
                <p class="admin-toast-msg">${message}</p>
            </div>
        `;
        
        toast.onclick = function() {
            if (url && url !== '#') {
                window.location.href = url;
            } else {
                toast.remove();
            }
        };
        
        container.appendChild(toast);
        
        // Trigger show animation
        void toast.offsetWidth; // Force reflow
        toast.classList.add('show');
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 5000);
    }

    // Fetch notifications
    function loadAdminNotifications() {
        fetch('ajax.php?action=get_notifications')
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                updateBadgeCount(res.unread_count);
                
                const list = document.getElementById('adminBellList');
                if (res.notifications.length === 0) {
                    list.innerHTML = `
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-bell-slash fs-3 mb-2 d-block opacity-25"></i>
                            ไม่มีการแจ้งเตือนในขณะนี้
                        </div>
                    `;
                    return;
                }
                
                let html = '';
                res.notifications.forEach(item => {
                    const unreadClass = item.is_read ? '' : 'unread';
                    const iconClass = getNotificationIconClass(item.url);
                    const link = item.url ? item.url : '#';
                    html += `
                        <div class="admin-notif-item ${unreadClass}" onclick="handleAdminNotificationClick(event, ${item.id}, '${link}')">
                            <div class="admin-notif-icon ${iconClass.split(' ')[0]}">
                                <i class="bi ${iconClass.split(' ')[1]}"></i>
                            </div>
                            <div class="admin-notif-content">
                                <span class="admin-notif-title">${item.title}</span>
                                <p class="admin-notif-msg">${item.message}</p>
                                <span class="admin-notif-time">${item.time_ago}</span>
                            </div>
                        </div>
                    `;
                });
                list.innerHTML = html;
            }
        })
        .catch(err => console.error(err));
    }

    // Update red badge count
    function updateBadgeCount(count) {
        const badge = document.getElementById('adminBellBadge');
        if (badge) {
            badge.innerText = count;
            if (count > 0) {
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        }
    }

    // Handle Item Click
    window.handleAdminNotificationClick = function(e, id, url) {
        if (e) e.stopPropagation();
        let fd = new FormData();
        fd.append('action', 'mark_read');
        fd.append('notification_id', id);
        fd.append('csrf_token', '<?= get_csrf_token() ?>');
        
        fetch('ajax.php', { method: 'POST', body: fd })
        .then(() => {
            if (url && url !== '#') {
                window.location.href = url;
            } else {
                loadAdminNotifications();
                pollNewNotifications();
            }
        })
        .catch(() => {
            if (url && url !== '#') window.location.href = url;
        });
    };

    // Mark All Read
    window.markAllAdminRead = function(e) {
        if (e) e.stopPropagation();
        let fd = new FormData();
        fd.append('action', 'mark_read');
        fd.append('csrf_token', '<?= get_csrf_token() ?>');
        
        fetch('ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                updateBadgeCount(0);
                loadAdminNotifications();
                pollNewNotifications(); // Update sidebar badges too
            }
        });
    };

    // Clear All
    window.clearAllAdminNotifications = function(e) {
        if (e) e.stopPropagation();
        Swal.fire({
            title: 'ยืนยันล้างประวัติการแจ้งเตือน?',
            text: "คุณต้องการลบการแจ้งเตือนแอดมินทั้งหมดใช่หรือไม่?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7FB5FF',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'ล้างข้อมูล',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                let fd = new FormData();
                fd.append('action', 'clear_notifications');
                fd.append('csrf_token', '<?= get_csrf_token() ?>');
                fetch('ajax.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        updateBadgeCount(0);
                        loadAdminNotifications();
                        pollNewNotifications();
                    }
                });
            }
        });
    };

    // Background Poll (runs every 10 seconds)
    function pollNewNotifications() {
        if (isPolling) return;
        isPolling = true;
        
        fetch('ajax.php?action=get_notifications')
        .then(r => r.json())
        .then(res => {
            isPolling = false;
            if (res.status === 'success') {
                updateBadgeCount(res.unread_count);
                
                // Track if there's any new notification ID
                let hasNew = false;
                let localMax = maxSeenId;
                
                res.notifications.forEach(item => {
                    const itemId = parseInt(item.id);
                    if (itemId > maxSeenId) {
                        hasNew = true;
                        if (itemId > localMax) {
                            localMax = itemId;
                        }
                    }
                });
                
                if (hasNew) {
                    // Show Toast Alert for the latest new item
                    const latestItem = res.notifications.find(item => parseInt(item.id) === localMax);
                    if (latestItem) {
                        showToastNotification(latestItem.title, latestItem.message, latestItem.url);
                    }
                    
                    maxSeenId = localMax;
                    localStorage.setItem('admin_max_seen_id', maxSeenId);
                    playSoundAlert();
                    triggerShake();
                    
                    // Load into list if dropdown is currently open
                    const dropdown = document.getElementById('adminBellDropdown');
                    if (dropdown && dropdown.classList.contains('show')) {
                        loadAdminNotifications();
                    }
                }
                
                // --- Dynamic Sidebar Badge Syncing ---
                // 1. Update pending orders badge in the sidebar
                const orderBadge = document.getElementById('sidebar-pending-orders-badge');
                if (orderBadge) {
                    const pendingOrdersCount = res.notifications.filter(item => !item.is_read && item.url && item.url.includes('admin_orders')).length;
                    if (pendingOrdersCount > 0) {
                        orderBadge.classList.remove('d-none');
                        orderBadge.innerText = pendingOrdersCount;
                    } else {
                        orderBadge.classList.add('d-none');
                    }
                }
                
                // 2. Unread contact messages
                const contactBadge = document.getElementById('unread-badge');
                if (contactBadge) {
                    const unreadContactsCount = res.notifications.filter(item => !item.is_read && item.url && item.url.includes('admin_contact')).length;
                    if (unreadContactsCount > 0) {
                        contactBadge.classList.remove('d-none');
                        contactBadge.innerText = 'ใหม่ ' + unreadContactsCount;
                    } else {
                        contactBadge.classList.add('d-none');
                    }
                }
            }
        })
        .catch(err => {
            isPolling = false;
            console.error(err);
        });
    }

    // Initialize
    // Fetch once on load to get the initial maxSeenId without triggering sound
    fetch('ajax.php?action=get_notifications')
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            updateBadgeCount(res.unread_count);
            let localMax = maxSeenId;
            res.notifications.forEach(item => {
                const itemId = parseInt(item.id);
                if (itemId > localMax) {
                    localMax = itemId;
                }
            });
            maxSeenId = localMax;
            localStorage.setItem('admin_max_seen_id', maxSeenId);
        }
        
        // Start periodic polling every 10 seconds
        setInterval(pollNewNotifications, 10000);
    });
})();

function changePageLimit(el) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('limit', el.value);
    urlParams.set('page', '1');
    const newUrl = window.location.pathname + '?' + urlParams.toString();
    
    // Update hidden input fields for limit if present in filter forms
    const limitInput = document.getElementById('limit_input');
    if (limitInput) limitInput.value = el.value;
    
    if (typeof fetchLogs === 'function') {
        history.pushState(null, '', newUrl);
        fetchLogs(newUrl);
    } else if (typeof fetchOrdersFiltered === 'function') {
        // fetchOrdersFiltered handles limit reading inside it, so we just call it
        fetchOrdersFiltered(false, '1');
    } else {
        window.location.href = newUrl;
    }
}
</script>

