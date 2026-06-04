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
        <a class="nav-link <?= $current_page == 'admin_logs.php' ? 'active' : '' ?>" href="admin_logs.php"><i class="bi bi-clock-history"></i> ประวัติการทำงาน</a>
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
