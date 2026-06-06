<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$cart_count = 0;
if(isset($_SESSION['cart'])) {
    foreach($_SESSION['cart'] as $item) {
        $cart_count += is_array($item) ? $item['qty'] : $item;
    }
}

$wishlist_count = 0;
if(isset($_SESSION['user_id'])) {
    $wishlist_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM wishlist WHERE user_id = '{$_SESSION['user_id']}'"))['count'] ?? 0;
}



if (!isset($page_title)) $page_title = "Por Mae Bet Taled | ร้านค้าออนไลน์เบ็ดเตล็ด";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <meta name="csrf-token" content="<?= get_csrf_token() ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { 
            --blue-main: #AEE2FF; 
            --blue-hover: #7FB5FF; 
            --bg-soft: #f8fafc; 
            --text-main: #1e293b;
        }
        body { font-family: 'Kanit', sans-serif; background-color: var(--bg-soft); color: var(--text-main); }
        
        /* Cart Badge Update Animations */
        @keyframes cartBadgeBounce {
            0%, 100% { transform: scale(1); }
            30% { transform: scale(1.4); }
            50% { transform: scale(0.9); }
            80% { transform: scale(1.15); }
        }
        @keyframes floatingCartTada {
            0% { transform: scale(1); }
            10%, 20% { transform: scale(0.9) rotate(-3deg); }
            30%, 50%, 70%, 90% { transform: scale(1.1) rotate(3deg); }
            40%, 60%, 80% { transform: scale(1.1) rotate(-3deg); }
            100% { transform: scale(1) rotate(0); }
        }
        .cart-badge-bounce {
            animation: cartBadgeBounce 0.5s ease-in-out;
        }
        .cart-float-tada {
            animation: floatingCartTada 0.6s ease-in-out;
        }
        
        /* Navbar Glassmorphism Styling */
        .navbar { 
            background: rgba(255, 255, 255, 0.8) !important; 
            backdrop-filter: blur(20px); 
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 4px 30px rgba(0,0,0,0.03); 
            padding: 10px 0; 
            border-bottom: 1px solid rgba(255, 255, 255, 0.4); 
            transition: all 0.3s ease;
        }
        .navbar-brand { font-weight: 800; color: #1e293b !important; font-size: 1.45rem; letter-spacing: -0.5px; margin-right: 20px; }
        .navbar-brand span { color: var(--blue-hover); }
        
        .nav-link { 
            font-weight: 500; 
            color: #64748b !important; 
            margin: 0 12px; 
            font-size: 0.95rem; 
            position: relative; 
            transition: color 0.3s ease; 
            padding: 6px 0;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background-color: var(--blue-hover);
            transition: width 0.3s ease, left 0.3s ease;
        }
        .nav-link:hover::after, .nav-link.active::after {
            width: 100%;
            left: 0;
        }
        .nav-link:hover, .nav-link.active { color: #1e293b !important; }
        
        /* Premium Search Input */
        .search-form { width: 100%; max-width: 380px; position: relative; }
        .search-input { 
            background-color: #f1f5f9; 
            border: 1px solid transparent; 
            font-size: 0.9rem; 
            padding-left: 20px; 
            padding-right: 45px; 
            height: 42px; 
            transition: all 0.3s ease; 
            border-radius: 50px;
        }
        .search-input:focus { 
            background-color: white; 
            border-color: var(--blue-main); 
            box-shadow: 0 8px 25px rgba(174, 226, 255, 0.25); 
        }
        .btn-search { 
            position: absolute; 
            right: 15px; 
            top: 50%; 
            transform: translateY(-50%); 
            color: #64748b; 
            border: none; 
            background: transparent; 
            padding: 0;
            font-size: 1.05rem;
            transition: color 0.2s;
        }
        .btn-search:hover { color: var(--blue-hover); }

        /* Icon Buttons */
        .icon-group { display: flex; align-items: center; gap: 12px; }
        .icon-btn { 
            width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; 
            border-radius: 50%; background: #f1f5f9; color: #475569; border: none; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; text-decoration: none; font-size: 1.15rem;
        }
        .icon-btn:hover { 
            background: var(--blue-hover); 
            color: white; 
            transform: translateY(-2px); 
            box-shadow: 0 8px 20px rgba(127, 181, 255, 0.4); 
        }
        
        /* Badge Count */
        .badge-count { 
            background-color: #ef4444; 
            color: white; 
            font-size: 0.65rem; 
            font-weight: 800; 
            position: absolute; 
            top: -2px; 
            right: -2px; 
            min-width: 18px; 
            height: 18px; 
            border-radius: 50%; 
            border: 2px solid white; 
            display: flex; 
            align-items: center; 
            justify-content: center;
        }

        /* Authentication Button */
        .btn-auth { 
            background: linear-gradient(135deg, var(--blue-main) 0%, var(--blue-hover) 100%); 
            color: #fff !important; 
            border-radius: 50px; 
            padding: 8px 24px; 
            font-weight: 600; 
            text-decoration: none; 
            box-shadow: 0 6px 18px rgba(174, 226, 255, 0.4); 
            transition: all 0.3s ease; 
            font-size: 0.9rem; 
            border: none;
        }
        .btn-auth:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 10px 25px rgba(127, 181, 255, 0.55); 
            color: #fff !important;
        }

        .hidden { display: none !important; }
        
        /* Dropdown User Menu */
        .dropdown-menu { 
            border: 1px solid rgba(0,0,0,0.03); 
            box-shadow: 0 15px 35px rgba(0,0,0,0.08); 
            border-radius: 16px; 
            padding: 8px; 
            margin-top: 12px; 
        }
        .dropdown-item { border-radius: 10px; padding: 10px 18px; font-size: 0.9rem; font-weight: 500; color: #475569; transition: all 0.2s; }
        .dropdown-item i { font-size: 1.05rem; color: #94a3b8; transition: color 0.2s; }
        .dropdown-item:hover { background-color: #f1f5f9; color: var(--blue-hover); }
        .dropdown-item:hover i { color: var(--blue-hover); }
        .dropdown-item.text-danger:hover { background-color: #fee2e2; color: #dc3545; }
        .dropdown-item.text-danger:hover i { color: #dc3545; }

        /* Toggler Icon */
        .navbar-toggler {
            border: none;
            padding: 8px;
            background: #f1f5f9;
            border-radius: 50%;
            transition: background 0.3s;
        }
        .navbar-toggler:focus {
            box-shadow: none;
            background: #e2e8f0;
        }

        /* Mobile Responsive UI/UX Overhauls */
        @media (max-width: 991px) { 
            .navbar-collapse {
                background: white;
                border-radius: 20px;
                padding: 20px;
                box-shadow: 0 15px 35px rgba(0,0,0,0.08);
                margin-top: 15px;
                border: 1px solid rgba(0,0,0,0.03);
            }
            .search-form { max-width: 100%; margin: 10px 0 15px 0; } 
            .nav-link { 
                margin: 5px 0; 
                padding: 12px 10px; 
                border-bottom: 1px solid #f1f5f9; 
            }
            .nav-link::after { display: none; }
            .icon-group { 
                position: relative;
                justify-content: center; 
                margin-top: 15px; 
                gap: 15px; 
            }
            /* จัดการดรอปดาวน์ผู้ใช้งานบนหน้าจอมือถือให้สวยงามลอยเต็มกว้าง */
            .icon-group .dropdown {
                position: static;
            }
            .icon-group .dropdown-menu {
                position: absolute !important;
                top: 50px !important;
                left: 50% !important;
                right: auto !important;
                transform: translateX(-50%) !important;
                width: 290px !important;
                border-radius: 16px;
                box-shadow: 0 12px 30px rgba(0,0,0,0.15);
                border: 1px solid #f1f5f9;
                padding: 10px;
                z-index: 1100;
            }
            .dropdown-item {
                padding: 12px 18px;
                font-size: 0.95rem;
                border-bottom: 1px solid #f8fafc;
            }
            .dropdown-item:last-child {
                border-bottom: none;
            }
            .btn-auth { width: 100%; text-align: center; margin-left: 0 !important; margin-top: 10px; }
        }

        /* Global Premium Preloader & Transition Loader Styles (Inline fallback to bypass cache) */
        .preloader-overlay {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(12px) !important;
            -webkit-backdrop-filter: blur(12px) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            z-index: 99999 !important;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .preloader-overlay.active {
            opacity: 1 !important;
            visibility: visible !important;
        }
        .preloader-content {
            text-align: center !important;
            width: 280px !important;
            background: white !important;
            padding: 30px !important;
            border-radius: 24px !important; /* var(--radius-lg) fallback */
            box-shadow: 0 20px 40px -10px rgba(127, 181, 255, 0.22) !important; /* var(--shadow-lg) fallback */
            border: 1px solid rgba(255,255,255,0.8) !important;
        }
        .preloader-spinner {
            width: 55px !important;
            height: 55px !important;
            border: 5px solid #f1f5f9 !important;
            border-top-color: var(--blue-hover) !important;
            border-bottom-color: var(--blue-main) !important;
            border-radius: 50% !important;
            margin: 0 auto 20px !important;
            animation: loader-spin 1s cubic-bezier(0.55, 0.055, 0.675, 0.19) infinite !important;
        }
        @keyframes loader-spin {
            to { transform: rotate(360deg); }
        }
        .preloader-text {
            font-weight: 600 !important;
            color: var(--text-main, #1E293B) !important;
            font-size: 0.95rem !important;
            margin-bottom: 12px !important;
        }
        .preloader-progress-bar {
            width: 100% !important;
            height: 5px !important;
            background-color: #e2e8f0 !important;
            border-radius: 10px !important;
            overflow: hidden !important;
            margin-bottom: 6px !important;
        }
        .preloader-progress-fill {
            width: 0%;
            height: 100% !important;
            background: linear-gradient(90deg, var(--blue-main), var(--blue-hover)) !important;
            border-radius: 10px !important;
            transition: width 0.3s ease-out !important;
        }
        .preloader-percentage {
            font-size: 0.8rem !important;
            font-weight: 700 !important;
            color: #64748B !important; /* var(--text-secondary) fallback */
        }

        /* ==========================================================================
           Mobile Redesigned Header & Bottom Navigation Bar (Premium Glassmorphic)
           ========================================================================== */
        
        /* Mobile Top Navigation Bar Styling */
        .mobile-top-bar {
            height: 60px;
            background: rgba(255, 255, 255, 0.85) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            z-index: 1030;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05) !important;
        }
        .mobile-brand {
            font-size: 1.1rem;
            font-weight: 800;
        }
        .mobile-top-icon-btn {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #f1f5f9;
            color: #475569;
            border: none;
            font-size: 1.05rem;
            transition: all 0.2s ease;
            cursor: pointer;
            padding: 0;
            outline: none;
        }
        .mobile-top-icon-btn:hover, .mobile-top-icon-btn:focus {
            background: var(--blue-main);
            color: #1e293b;
        }
        
        /* Mobile Expandable Search Bar Overlay */
        .mobile-search-overlay {
            position: fixed;
            top: -70px;
            left: 0;
            width: 100%;
            height: 60px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            z-index: 1025;
            transition: top 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            align-items: center;
            padding: 0 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        .mobile-search-overlay.show {
            top: 60px;
        }
        .mobile-search-form {
            width: 100%;
        }
        .mobile-search-form .input-group {
            background: #f1f5f9;
            border-radius: 50px;
            padding: 2px 5px;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .mobile-search-form input {
            background: transparent;
            font-size: 0.9rem;
            height: 38px;
        }
        .mobile-search-form input:focus {
            box-shadow: none;
            background: transparent;
        }

        /* Mobile Bottom Navigation Bar Styling */
        .mobile-bottom-nav {
            position: fixed;
            bottom: 15px;
            left: 15px;
            right: 15px;
            height: 66px;
            background: rgba(255, 255, 255, 0.85) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.4) !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08) !important;
            z-index: 1020;
            padding: 0 10px;
            transition: all 0.3s ease;
        }
        
        .mobile-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #64748b;
            text-decoration: none;
            font-size: 0.72rem;
            font-weight: 500;
            height: 100%;
            flex: 1;
            transition: all 0.2s ease;
            position: relative;
        }
        .mobile-nav-item i {
            font-size: 1.3rem;
            margin-bottom: 2px;
            transition: transform 0.2s ease, color 0.2s ease;
        }
        
        /* Active & Hover States */
        .mobile-nav-item.active, .mobile-nav-item:hover, .mobile-nav-item:focus {
            color: var(--blue-hover);
        }
        .mobile-nav-item.active i {
            transform: scale(1.18) translateY(-2px);
            color: var(--blue-hover);
        }
        
        .mobile-nav-icon-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .mobile-nav-icon-wrapper .badge-count {
            font-size: 0.6rem !important;
            top: -4px !important;
            right: -8px !important;
            min-width: 16px !important;
            height: 16px !important;
            border: 2px solid white !important;
            padding: 0 3px !important;
            border-radius: 10px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            position: absolute !important;
            background-color: #ef4444 !important;
            color: white !important;
            font-weight: 800 !important;
        }
        body.dark-theme .mobile-nav-icon-wrapper .badge-count {
            border-color: #1e293b !important;
        }
        
        /* Dropup custom adjustments */
        .mobile-bottom-nav .dropup .dropdown-menu {
            position: absolute !important;
            bottom: 75px !important;
            left: 50% !important;
            right: auto !important;
            transform: translateX(-50%) !important;
            width: 260px !important;
            border-radius: 16px;
            box-shadow: 0 -10px 30px rgba(0,0,0,0.12) !important;
            border: 1px solid rgba(0,0,0,0.05);
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 8px;
        }
        /* Hide carets in all circular dropdown buttons with hide-arrow class */
        .hide-arrow::after,
        .hide-arrow::before,
        .dropdown-toggle.hide-arrow::after,
        .dropdown-toggle.hide-arrow::before,
        .mobile-bottom-nav .dropdown-toggle::after,
        .mobile-bottom-nav .dropup .dropdown-toggle::after,
        .mobile-bottom-nav .dropup .dropdown-toggle::before {
            display: none !important;
        }

        /* Dark Theme Support for Mobile Navigation */
        body.dark-theme .mobile-top-bar,
        body.dark-theme .mobile-bottom-nav,
        body.dark-theme .mobile-search-overlay {
            background: rgba(15, 23, 42, 0.85) !important;
            border-color: rgba(255, 255, 255, 0.08) !important;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.3) !important;
        }
        body.dark-theme .mobile-brand span,
        body.dark-theme .mobile-nav-item {
            color: #94a3b8 !important;
        }
        body.dark-theme .mobile-nav-item.active,
        body.dark-theme .mobile-nav-item:hover {
            color: var(--blue-main) !important;
        }
        body.dark-theme .mobile-top-icon-btn {
            background: #1e293b;
            color: #94a3b8;
        }
        body.dark-theme .mobile-top-icon-btn:hover {
            background: var(--blue-hover);
            color: white;
        }
        body.dark-theme .mobile-search-form .input-group {
            background: #1e293b;
            border-color: rgba(255, 255, 255, 0.08);
        }
        body.dark-theme .mobile-search-form input {
            color: #f8fafc;
        }
        body.dark-theme .mobile-bottom-nav .dropup .dropdown-menu {
            background: rgba(15, 23, 42, 0.98);
            border-color: rgba(255, 255, 255, 0.08);
        }

        /* Layout modifications for mobile */
        @media (max-width: 991px) {
            body {
                padding-bottom: 95px !important; /* Make room for bottom nav */
            }
            .wishlist-btn-fixed, .floating-cart-btn {
                display: none !important; /* Hide old desktop floating buttons on mobile */
            }
        }
    </style>
    
    <?php if(isset($extra_css)) echo $extra_css; ?>
    <link rel="stylesheet" href="style.css?v=2.7">
    <script>
        // ฟังก์ชันแปลงชื่อไฟล์หน้าเว็บเป็นชื่อภาษาไทยที่เข้าใจง่าย
        window.getPageNameThai = (urlStr) => {
            try {
                const url = new URL(urlStr, window.location.href);
                const file = url.pathname.split('/').pop();
                if (!file || file === 'index.php') return 'หน้าแรก';
                if (file === 'about.php') return 'เกี่ยวกับเรา';
                if (file === 'contact.php') return 'ติดต่อสอบถาม';
                if (file === 'cart.php') return 'ตะกร้าสินค้า';
                if (file === 'product_detail.php') return 'รายละเอียดสินค้า';
                if (file === 'profile.php') return 'ข้อมูลส่วนตัว';
                if (file === 'my_orders.php') return 'รายการสั่งซื้อ';
                if (file === 'wishlist.php') return 'รายการโปรด';
                if (file === 'login.php') return 'หน้าเข้าสู่ระบบ';
                if (file.startsWith('admin_dashboard.php')) return 'แดชบอร์ดผู้ดูแล';
                if (file.startsWith('admin')) return 'หน้าควบคุมแอดมิน';
                return 'หน้าหลัก';
            } catch (e) {
                return 'หน้าเว็บ';
            }
        };

        // ฟังก์ชันคืนค่าข้อความอัปเดตข้อมูลตามความคืบหน้า (เปอร์เซ็นต์)
        window.getLoadingStatusMessage = (progress, pageThai) => {
            if (progress < 25) return `กำลังเชื่อมต่อเซิร์ฟเวอร์เพื่อเปิด ${pageThai}...`;
            if (progress < 55) return `กำลังดึงข้อมูลโครงสร้าง ${pageThai}...`;
            if (progress < 85) return `กำลังดึงข้อมูลองค์ประกอบและรูปภาพ...`;
            if (progress < 100) return `กำลังโหลดส่วนเสริมดีไซน์พาสเทล...`;
            return `ดาวน์โหลดข้อมูลเสร็จสิ้น! กำลังแสดงผล...`;
        };

        document.addEventListener('DOMContentLoaded', () => {
            // ตั้งค่าคลาส active ให้กับเมนูหลักตามหน้าเว็บปัจจุบัน
            const currentPath = window.location.pathname.split('/').pop() || 'index.php';
            const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href) {
                    const linkPath = href.split('#')[0];
                    if (linkPath === currentPath) {
                        link.classList.add('active');
                    }
                }
            });

            // ดักจับการคลิกลิงก์เพื่อทำอนิเมชันตอนย้ายหน้า
            const links = document.querySelectorAll('a');
            links.forEach(link => {
                const href = link.getAttribute('href');
                const target = link.getAttribute('target');
                
                // ข้ามลิงก์ที่ไม่ใช่การย้ายหน้าปกติ (เช่น ลิงก์สมอ #, ปุ่มเปิด modal, ลิงก์ภายนอก, ฯลฯ)
                if (!href || 
                    href.startsWith('#') || 
                    href.startsWith('javascript:') || 
                    target === '_blank' || 
                    link.classList.contains('dropdown-toggle') ||
                    link.hasAttribute('data-bs-toggle') ||
                    link.hasAttribute('download') ||
                    link.getAttribute('role') === 'button') {
                    return;
                }

                // ตรวจสอบว่าเป็นแค่การคลิกเพื่อเลื่อนจอไปยังสมอ (#) บนหน้าเดิมหรือไม่
                try {
                    const url = new URL(link.href);
                    const currentUrl = new URL(window.location.href);
                    const pathL = url.pathname.replace(/index\.php$/, '').replace(/\/$/, '');
                    const pathC = currentUrl.pathname.replace(/index\.php$/, '').replace(/\/$/, '');
                    if (pathL === pathC && url.hash) {
                        return; // ข้ามการเปลี่ยนหน้าและอนิเมชันเฟด
                    }
                } catch (e) {}

                // เช็คว่าลิงก์นำไปยังโฮสต์เดียวกันหรือไม่
                const isInternal = link.hostname === window.location.hostname || !link.hostname;
                if (!isInternal) return;

                link.addEventListener('click', (e) => {
                    // ป้องกันการย้ายหน้าทันที
                    e.preventDefault();
                    const targetUrl = link.href;

                    // 1. สั่งเฟดบอดี้ (จอจางสีขาว) ทันทีเพื่อความสมูทในการเปลี่ยนหน้าเร็ว
                    document.body.classList.add('fade-out');

                    // เคลียร์ค่าตัวหน่วงเวลาเดิมหากมีตกค้าง
                    if (window.loaderTimeout) clearTimeout(window.loaderTimeout);
                    if (window.loaderInterval) clearInterval(window.loaderInterval);

                    // 2. ตั้งเวลาหน่วง 1.5 วินาที: หากหน้าเว็บยังไม่สลับ ค่อยเปิดตัวโหลด UI Load ขึ้นมา
                    window.loaderTimeout = setTimeout(() => {
                        const loader = document.getElementById('global-loader');
                        const progressFill = document.getElementById('loader-progress');
                        const percentageText = document.getElementById('loader-percentage');
                        const statusLabel = document.getElementById('loader-text');

                        if (loader) {
                            // ดึงคลาสเฟดออกเพื่อให้เห็นการจำลองตัวโหลดกลางจออย่างชัดเจนไม่หม่นแสง
                            document.body.classList.remove('fade-out');
                            
                            loader.classList.remove('hidden');
                            loader.classList.add('active');
                            
                            const pageThai = typeof window.getPageNameThai === 'function' ? window.getPageNameThai(targetUrl) : 'หน้าหลัก';
                            if (statusLabel && typeof window.getLoadingStatusMessage === 'function') {
                                statusLabel.innerText = window.getLoadingStatusMessage(0, pageThai);
                            }

                            // เริ่มแสดงความคืบหน้าหลอดโหลดแบบจำลองกรณีหน้าเว็บโหลดช้าจริงๆ
                            let progress = 0;
                            window.loaderInterval = setInterval(() => {
                                if (progress < 90) {
                                    progress += Math.floor(Math.random() * 12) + 5;
                                    if (progress > 90) progress = 90;
                                    if (progressFill) progressFill.style.width = progress + '%';
                                    if (percentageText) percentageText.innerText = progress + '%';
                                    if (statusLabel && typeof window.getLoadingStatusMessage === 'function') {
                                        statusLabel.innerText = window.getLoadingStatusMessage(progress, pageThai);
                                    }
                                } else {
                                    clearInterval(window.loaderInterval);
                                }
                            }, 150);
                        }
                    }, 2000); // 2.0 วินาทีตามเงื่อนไขหากเว็บโหลดช้า

                    // 3. เริ่มส่งคำขอเปลี่ยนหน้าของบราวเซอร์หลังจากคลิกและเฟดจอมารวม 150ms
                    setTimeout(() => {
                        window.location.href = targetUrl;
                    }, 150);
                });
            });

            // --- Live Auto-suggest Search (Integrated Desktop & Mobile) ---
            function initSearchSuggestions(inputId, suggestId) {
                const input = document.getElementById(inputId);
                const box = document.getElementById(suggestId);
                let debounceTimeout;
                
                if (input && box) {
                    input.addEventListener('input', () => {
                        clearTimeout(debounceTimeout);
                        const query = input.value.trim();

                        if (query.length < 2) {
                            box.innerHTML = '';
                            box.classList.add('hidden');
                            return;
                        }

                        debounceTimeout = setTimeout(() => {
                            fetch(`ajax.php?action=search_suggest&q=${encodeURIComponent(query)}`)
                            .then(r => r.json())
                            .then(res => {
                                if (res.status === 'success') {
                                    renderSuggestions(res.data, box);
                                }
                            })
                            .catch(err => console.error(err));
                        }, 200);
                    });

                    input.addEventListener('focus', () => {
                        const query = input.value.trim();
                        if (query.length >= 2 && box.children.length > 0) {
                            box.classList.remove('hidden');
                        }
                    });

                    input.addEventListener('blur', () => {
                        setTimeout(() => {
                            box.classList.add('hidden');
                        }, 150);
                    });
                }
            }

            function renderSuggestions(data, box) {
                if (data.length === 0) {
                    box.innerHTML = '<div class="suggest-no-result text-muted text-center p-2">ไม่พบข้อมูลสินค้า</div>';
                    box.classList.remove('hidden');
                    return;
                }

                let html = '';
                data.forEach(item => {
                    html += `
                        <a href="product_detail.php?id=${item.id}" class="suggest-item">
                            <img src="${item.image}" class="suggest-img" alt="${item.name}">
                            <div class="suggest-details">
                                <div class="suggest-name">${item.name}</div>
                                <div class="suggest-price">฿${item.price}</div>
                            </div>
                        </a>
                    `;
                });

                box.innerHTML = html;
                box.classList.remove('hidden');
            }

            initSearchSuggestions('globalSearchInput', 'search-suggest');
            initSearchSuggestions('mobileSearchInput', 'mobile-search-suggest');

            // Mobile Search Overlay Actions
            const mobileSearchToggle = document.getElementById('mobileSearchToggle');
            const mobileNavSearchBtn = document.getElementById('mobileNavSearchBtn');
            const mobileSearchOverlay = document.getElementById('mobileSearchOverlay');
            const mobileSearchClose = document.getElementById('mobileSearchClose');
            const mobileSearchInput = document.getElementById('mobileSearchInput');

            function openMobileSearch() {
                if (mobileSearchOverlay) {
                    mobileSearchOverlay.classList.add('show');
                    if (mobileSearchInput) {
                        setTimeout(() => mobileSearchInput.focus(), 150);
                    }
                }
            }

            function closeMobileSearch() {
                if (mobileSearchOverlay) {
                    mobileSearchOverlay.classList.remove('show');
                }
            }

            if (mobileSearchToggle) mobileSearchToggle.addEventListener('click', openMobileSearch);
            if (mobileNavSearchBtn) mobileNavSearchBtn.addEventListener('click', (e) => {
                e.preventDefault();
                openMobileSearch();
            });
            if (mobileSearchClose) mobileSearchClose.addEventListener('click', closeMobileSearch);
        });

        // หากกด Back/Forward จากเบราว์เซอร์ ให้ซ่อน Preloader และแสดงหน้าเพจปกติ
        window.addEventListener('pageshow', (event) => {
            const loader = document.getElementById('global-loader');
            if (loader) {
                loader.classList.remove('active');
                loader.classList.add('hidden');
                
                const progressFill = document.getElementById('loader-progress');
                const percentageText = document.getElementById('loader-percentage');
                const statusLabel = document.getElementById('loader-text');
                if (progressFill && percentageText) {
                    progressFill.style.width = '0%';
                    percentageText.innerText = '0%';
                    if (statusLabel) statusLabel.innerText = 'กำลังโหลดข้อมูล...';
                }
            }
            document.body.classList.remove('fade-out');
            
            // Check notifications on load if element exists
            if (document.getElementById('notification-badge')) {
                checkNotificationsCount();
                // Check every 30 seconds for new notifications
                setInterval(checkNotificationsCount, 30000);
            }
        });

        window.loadNotifications = function() {
            const list = document.getElementById('notification-list');
            const mobileList = document.getElementById('mobile-notification-list');
            
            if (list) list.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div>';
            if (mobileList) mobileList.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div>';
            
            fetch('ajax.php?action=get_notifications')
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    updateNotificationBadge(res.unread_count);
                    
                    if (res.notifications.length === 0) {
                        const noNotificationsHtml = `
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-bell-slash fs-2 mb-2 d-block opacity-25"></i>
                                ไม่มีแจ้งเตือนในขณะนี้
                            </div>
                        `;
                        if (list) list.innerHTML = noNotificationsHtml;
                        if (mobileList) mobileList.innerHTML = noNotificationsHtml;
                        return;
                    }
                    
                    let html = '';
                    res.notifications.forEach(item => {
                        const readClass = item.is_read ? 'bg-white opacity-75' : 'bg-light fw-bold';
                        const titleColor = item.is_read ? 'text-secondary' : 'text-dark';
                        const link = item.url ? item.url : '#';
                        html += `
                            <div class="p-3 border-bottom notification-item ${readClass}" style="transition: background 0.2s; cursor: pointer;" onclick="handleNotificationClick(${item.id}, '${link}')">
                                <div class="d-flex justify-content-between align-items-start">
                                    <span class="${titleColor} small d-block mb-1">${item.title}</span>
                                    <span class="text-muted text-nowrap ms-2" style="font-size: 0.7rem;">${item.time_ago}</span>
                                </div>
                                <p class="text-muted mb-0 small text-truncate" style="font-size: 0.8rem; font-weight: normal;">${item.message}</p>
                            </div>
                        `;
                    });
                    if (list) list.innerHTML = html;
                    if (mobileList) mobileList.innerHTML = html;
                }
            })
            .catch(err => console.error(err));
        };

        window.updateNotificationBadge = function(count) {
            const badge = document.getElementById('notification-badge');
            const mobileBadge = document.getElementById('mobile-notification-badge');
            
            if (badge) {
                badge.innerText = count;
                if (count > 0) badge.classList.remove('hidden');
                else badge.classList.add('hidden');
            }
            if (mobileBadge) {
                mobileBadge.innerText = count;
                if (count > 0) mobileBadge.classList.remove('hidden');
                else mobileBadge.classList.add('hidden');
            }
        };

        window.handleNotificationClick = function(id, url) {
            let fd = new FormData();
            fd.append('action', 'mark_read');
            fd.append('notification_id', id);
            fd.append('csrf_token', '<?= get_csrf_token() ?>');
            
            fetch('ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (url !== '#') {
                    window.location.href = url;
                } else {
                    loadNotifications();
                }
            })
            .catch(() => {
                if (url !== '#') window.location.href = url;
            });
        };

        window.markAllNotificationsAsRead = function() {
            let fd = new FormData();
            fd.append('action', 'mark_read');
            fd.append('csrf_token', '<?= get_csrf_token() ?>');
            
            fetch('ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if(data.status === 'success') {
                    updateNotificationBadge(0);
                    loadNotifications();
                }
            });
        };

        window.clearAllNotifications = function() {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: "คุณต้องการล้างประวัติการแจ้งเตือนทั้งหมดใช่หรือไม่?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#AEE2FF',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'ใช่, ลบทั้งหมด',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    let fd = new FormData();
                    fd.append('action', 'clear_notifications');
                    fd.append('csrf_token', '<?= get_csrf_token() ?>');
                    fetch('ajax.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if(data.status === 'success') {
                            updateNotificationBadge(0);
                            loadNotifications();
                        }
                    });
                }
            });
        };

        window.checkNotificationsCount = function() {
            fetch('ajax.php?action=get_notifications')
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    updateNotificationBadge(res.unread_count);
                }
            })
            .catch(err => console.error(err));
        };

        // ระบบป้องกันการส่งฟอร์มซ้ำ (Double-Submit Prevention) สำหรับฟอร์มทั่วไป
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

        // --- Interactive Cart Drawer Javascript Functions ---
        window.toggleCartDrawer = function() {
            const drawer = document.getElementById('cartDrawer');
            const backdrop = document.getElementById('cartDrawerBackdrop');
            if (drawer && backdrop) {
                const isShowing = drawer.classList.contains('show');
                if (!isShowing) {
                    window.loadCartDrawer();
                    drawer.classList.add('show');
                    backdrop.classList.add('show');
                    document.body.style.overflow = 'hidden';
                } else {
                    drawer.classList.remove('show');
                    backdrop.classList.remove('show');
                    document.body.style.overflow = '';
                }
            }
        };

        window.loadCartDrawer = function() {
            const body = document.getElementById('cart-drawer-body');
            const subtotalEl = document.getElementById('cart-drawer-subtotal-val');
            const discountRow = document.getElementById('cart-drawer-discount-row');
            const discountEl = document.getElementById('cart-drawer-discount-val');
            const shippingEl = document.getElementById('cart-drawer-shipping-val');
            const totalEl = document.getElementById('cart-drawer-total-val');
            const legacySubtotalEl = document.getElementById('cart-drawer-subtotal');
            
            const badgeEl = document.getElementById('nav-cart-badge');
            const floatBtn = document.getElementById('floatingCartBtn');
            const floatBadge = document.getElementById('floating-cart-badge');
            
            fetch('ajax.php?action=get_cart_drawer')
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    if (body) body.innerHTML = res.html;
                    
                    // Update pricing values in drawer footer
                    if (subtotalEl) subtotalEl.innerText = '฿' + res.subtotal;
                    if (legacySubtotalEl) legacySubtotalEl.innerText = '฿' + res.final_total;
                    
                    // Handle discount row
                    const discountVal = parseFloat((res.discount || '0').replace(/,/g, ''));
                    if (discountVal > 0) {
                        if (discountEl) discountEl.innerText = res.discount;
                        if (discountRow) discountRow.style.setProperty('display', 'flex', 'important');
                    } else {
                        if (discountRow) discountRow.style.setProperty('display', 'none', 'important');
                    }
                    
                    // Handle shipping fee value
                    const shippingVal = parseFloat((res.shipping_fee || '0').replace(/,/g, ''));
                    if (shippingEl) {
                        if (shippingVal === 0) {
                            shippingEl.innerText = 'ส่งฟรี';
                            shippingEl.className = 'fw-bold text-success';
                        } else {
                            shippingEl.innerText = '฿' + res.shipping_fee;
                            shippingEl.className = 'fw-semibold text-dark';
                        }
                    }
                    
                    if (totalEl) totalEl.innerText = '฿' + res.final_total;
                    
                    // Update Drawer Free Shipping Progress Bar
                    const drawerWidget = document.getElementById('drawer-free-shipping-widget');
                    const drawerFill = document.getElementById('drawer-free-shipping-bar-fill');
                    const drawerText = document.getElementById('drawer-free-shipping-text');
                    const drawerIcon = document.getElementById('drawer-free-shipping-icon');
                    
                    if (drawerWidget && drawerFill && drawerText) {
                        const subFloat = parseFloat((res.subtotal || '0').replace(/,/g, ''));
                        const threshold = parseFloat(res.shipping_free_threshold || 350.00);
                        const shippingVal = parseFloat((res.shipping_fee || '0').replace(/,/g, ''));
                        const isFreeCoupon = shippingVal === 0;
                        
                        if (res.cart_count === 0 || subFloat <= 0) {
                            drawerWidget.style.display = 'none';
                        } else {
                            drawerWidget.style.display = 'block';
                            let pct = Math.min(100, (subFloat / threshold) * 100);
                            
                            if (isFreeCoupon || pct >= 100) {
                                drawerFill.style.width = '100%';
                                drawerFill.classList.add('success');
                                if (pct >= 100) {
                                    drawerText.innerHTML = 'ยินดีด้วย! คุณได้รับสิทธิ์ส่งฟรีแล้ว 🎉';
                                } else {
                                    drawerText.innerHTML = 'ยินดีด้วย! คุณได้รับสิทธิ์ส่งฟรีจากคูปองแล้ว 🎉';
                                }
                                if (drawerIcon) drawerIcon.innerText = '🎉';
                            } else {
                                drawerFill.style.width = pct + '%';
                                drawerFill.classList.remove('success');
                                let remaining = threshold - subFloat;
                                drawerText.innerHTML = 'ช้อปอีกเพียง <strong>฿' + remaining.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</strong> เพื่อรับส่งฟรี!';
                                if (drawerIcon) drawerIcon.innerText = '🚚';
                            }
                        }
                    }
                    
                    const prevCount = parseInt(badgeEl ? badgeEl.innerText : '0') || 0;
                    const newCount = parseInt(res.cart_count) || 0;

                    if (badgeEl) {
                        badgeEl.innerText = res.cart_count;
                        if (res.cart_count > 0) {
                            badgeEl.classList.remove('hidden');
                        } else {
                            badgeEl.classList.add('hidden');
                        }
                    }
                    if (floatBtn && floatBadge) {
                        floatBadge.innerText = res.cart_count;
                        if (res.cart_count > 0) {
                            floatBtn.classList.remove('hidden');
                        } else {
                            floatBtn.classList.add('hidden');
                        }
                    }
                    
                    // Sync Mobile Bottom Navigation Cart Badge
                    const mobileBottomCartBadge = document.getElementById('mobile-bottom-cart-badge');
                    if (mobileBottomCartBadge) {
                        mobileBottomCartBadge.innerText = res.cart_count;
                        if (res.cart_count > 0) {
                            mobileBottomCartBadge.classList.remove('hidden');
                        } else {
                            mobileBottomCartBadge.classList.add('hidden');
                        }
                    }
                    
                    // Hide cart drawer footer when cart is empty
                    const footerEl = document.getElementById('cart-drawer-footer');
                    if (footerEl) {
                        if (newCount === 0) {
                            footerEl.style.setProperty('display', 'none', 'important');
                        } else {
                            footerEl.style.setProperty('display', 'block', 'important');
                        }
                    }

                    // Trigger animations if count changed
                    if (prevCount !== newCount && newCount > 0) {
                        if (badgeEl) {
                            badgeEl.classList.remove('cart-badge-bounce');
                            void badgeEl.offsetWidth; // trigger reflow
                            badgeEl.classList.add('cart-badge-bounce');
                        }
                        if (floatBtn) {
                            floatBtn.classList.remove('cart-float-tada');
                            void floatBtn.offsetWidth; // trigger reflow
                            floatBtn.classList.add('cart-float-tada');
                        }
                    }
                }
            })
            .catch(err => {
                console.error('Error loading cart drawer:', err);
                if (body) body.innerHTML = '<div class="text-center py-5 text-danger"><i class="bi bi-exclamation-triangle fs-2 mb-2 d-block"></i>เกิดข้อผิดพลาดในการโหลดตะกร้า</div>';
            });
        };

        window.updateQtyDrawer = function(cartKey, type) {
            const data = new FormData();
            data.append('action', 'update_qty');
            data.append('product_id', cartKey);
            data.append('type', type);
            data.append('csrf_token', '<?= get_csrf_token() ?>');
            
            fetch('ajax.php', {
                method: 'POST',
                body: data
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    window.loadCartDrawer();
                    
                    // หากอยู่ในหน้า cart.php ให้ทำการอัปเดต UI ของหน้าหลักไปด้วยพร้อมกัน
                    const isCartPage = window.location.pathname.endsWith('cart.php');
                    if (isCartPage) {
                        const qtyEl = document.getElementById('qty-' + cartKey);
                        const totalEl = document.getElementById('line-total-' + cartKey);
                        const priceDescEl = document.getElementById('price-desc-' + cartKey);
                        const subtotalEl = document.getElementById('subtotal');
                        const finalEl = document.getElementById('final_total');
                        const discEl = document.getElementById('discount_val');
                        const qrEl = document.getElementById('qr-total');
                        
                        if (qtyEl) qtyEl.innerText = res.new_qty;
                        if (totalEl) totalEl.innerText = res.line_total;
                        if (priceDescEl) priceDescEl.innerText = res.price_desc;
                        if (subtotalEl) subtotalEl.innerText = res.subtotal;
                        if (finalEl) finalEl.innerText = res.final_total;
                        if (discEl) discEl.innerText = res.discount;
                        if (qrEl) qrEl.innerText = res.final_total;
                        
                        const inTotal = document.getElementById('in_total');
                        const inDisc = document.getElementById('in_disc');
                        const inFinal = document.getElementById('in_final');
                        const hiddenTotal = document.getElementById('hidden_total');
                        
                        if (inTotal) inTotal.value = res.subtotal.replace(/,/g, '');
                        if (inDisc) inDisc.value = res.discount.replace(/,/g, '');
                        if (inFinal) inFinal.value = res.final_total.replace(/,/g, '');
                        if (hiddenTotal) hiddenTotal.value = res.subtotal.replace(/,/g, '');
                        
                        if (typeof window.updateFreeShippingProgressBar === 'function') {
                            const subFloat = parseFloat(res.subtotal.replace(/,/g, ''));
                            const isFreeCoupon = parseFloat(res.shipping_fee.replace(/,/g, '')) === 0;
                            window.updateFreeShippingProgressBar(subFloat, isFreeCoupon);
                        }

                        const pm = document.querySelector('input[name="payment_method_id"]:checked');
                        if (pm && typeof updatePaymentUI === 'function') {
                            updatePaymentUI(pm);
                        }
                    }
                } else if (res.message) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'ข้อจำกัดสินค้า',
                        text: res.message,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000
                    });
                }
            })
            .catch(err => console.error('Error updating quantity:', err));
        };

        window.removeDrawerItem = function(cartKey) {
            const data = new FormData();
            data.append('action', 'remove_item');
            data.append('product_id', cartKey);
            data.append('csrf_token', '<?= get_csrf_token() ?>');
            
            fetch('ajax.php', {
                method: 'POST',
                body: data
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    window.loadCartDrawer();
                    
                    // หากอยู่ในหน้า cart.php ให้ทำการอัปเดต UI หน้าหลักไปด้วยพร้อมกัน
                    const isCartPage = window.location.pathname.endsWith('cart.php');
                    if (isCartPage) {
                        const rowEl = document.getElementById('item-row-' + cartKey);
                        if (rowEl) rowEl.remove();
                        
                        if (res.cart_count === 0) {
                            window.location.reload();
                        } else {
                            const subtotalEl = document.getElementById('subtotal');
                            const finalEl = document.getElementById('final_total');
                            const discEl = document.getElementById('discount_val');
                            const qrEl = document.getElementById('qr-total');
                            
                            if (subtotalEl) subtotalEl.innerText = res.subtotal;
                            if (finalEl) finalEl.innerText = res.final_total;
                            if (discEl) discEl.innerText = res.discount;
                            if (qrEl) qrEl.innerText = res.final_total;
                            
                            const inTotal = document.getElementById('in_total');
                            const inDisc = document.getElementById('in_disc');
                            const inFinal = document.getElementById('in_final');
                            const hiddenTotal = document.getElementById('hidden_total');
                            
                            if (inTotal) inTotal.value = res.subtotal.replace(/,/g, '');
                            if (inDisc) inDisc.value = res.discount.replace(/,/g, '');
                            if (inFinal) inFinal.value = res.final_total.replace(/,/g, '');
                            if (hiddenTotal) hiddenTotal.value = res.subtotal.replace(/,/g, '');
                            
                            if (typeof window.updateFreeShippingProgressBar === 'function') {
                                const subFloat = parseFloat(res.subtotal.replace(/,/g, ''));
                                const isFreeCoupon = parseFloat(res.shipping_fee.replace(/,/g, '')) === 0;
                                window.updateFreeShippingProgressBar(subFloat, isFreeCoupon);
                            }

                            const pm = document.querySelector('input[name="payment_method_id"]:checked');
                            if (pm && typeof updatePaymentUI === 'function') {
                                updatePaymentUI(pm);
                            }
                        }
                    }
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'ลบสำเร็จ',
                        text: res.message,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 1500
                    });
                }
            })
            .catch(err => console.error('Error removing item:', err));
        };

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('a[href="cart.php"]').forEach(link => {
                link.addEventListener('click', (e) => {
                    const currentPath = window.location.pathname.split('/').pop() || 'index.php';
                    if (currentPath !== 'cart.php') {
                        e.preventDefault();
                        window.toggleCartDrawer();
                    }
                });
            });
        });
    </script>
</head>
<body>
<script>
    (function() {
        const theme = localStorage.getItem('theme');
        if (theme === 'dark') {
            document.body.classList.add('dark-theme');
        }
    })();
</script>

<!-- Global Premium Preloader -->
<div id="global-loader" class="preloader-overlay hidden">
    <div class="preloader-content animate__animated animate__zoomIn">
        <div class="preloader-spinner"></div>
        <div class="preloader-text" id="loader-text">กำลังโหลดข้อมูล...</div>
        <div class="preloader-progress-bar">
            <div class="preloader-progress-fill" id="loader-progress"></div>
        </div>
        <div class="preloader-percentage" id="loader-percentage">0%</div>
    </div>
</div>

<nav class="navbar navbar-expand-lg sticky-top glass-nav d-none d-lg-block">
    <div class="container">
        
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img src="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>" alt="Logo" style="height: 40px; width: auto; margin-right: 10px; object-fit: contain;">
            <span>Por Mae <span>Bet Taled</span></span>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navItems">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navItems">
            
            <div class="mx-auto flex-grow-1 text-center d-flex justify-content-center px-lg-4">
                <form action="index.php" method="GET" class="search-form" id="globalSearchForm" autocomplete="off">
                    <input class="form-control rounded-pill search-input" type="search" name="q" id="globalSearchInput" placeholder="ค้นหาสินค้าที่ต้องการ..." value="<?= isset($_GET['q']) ? $_GET['q'] : '' ?>">
                    <button type="submit" class="btn-search"><i class="bi bi-search"></i></button>
                    <div id="search-suggest" class="search-suggest-box hidden"></div>
                </form>
            </div>

            <div class="d-flex flex-column flex-lg-row align-items-lg-center">
                <ul class="navbar-nav me-lg-3 text-center text-lg-start">
                    <li class="nav-item"><a class="nav-link" href="index.php">หน้าแรก</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#shop">สินค้าทั้งหมด</a></li>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="demo_loader.php">ทดสอบตัวโหลด</a></li>
                    <?php endif; ?>
                </ul>

                <div class="icon-group">
                    
                    <button class="icon-btn border-0" id="darkModeToggle" title="เปลี่ยนธีมสี" type="button" style="cursor: pointer;">
                        <i class="bi bi-moon-stars" id="darkModeIcon"></i>
                    </button>
                    
                    <a class="icon-btn" href="wishlist.php" title="รายการโปรด">
                        <i class="bi bi-heart"></i>
                    </a>

                    <a class="icon-btn" href="cart.php" title="ตะกร้าสินค้า">
                        <i class="bi bi-cart3"></i>
                        <span id="nav-cart-badge" class="badge-count <?= $cart_count > 0 ? '' : 'hidden' ?>"><?= $cart_count ?></span>
                    </a>

                    <!-- Notification Bell Dropdown -->
                    <?php if(isset($_SESSION['user_id']) || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')): ?>
                        <div class="dropdown" id="notificationDropdown">
                            <a class="icon-btn dropdown-toggle hide-arrow" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" onclick="loadNotifications()">
                                <i class="bi bi-bell"></i>
                                <span id="notification-badge" class="badge-count hidden">0</span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end animate__animated animate__fadeIn p-0 shadow-lg border-0" style="width: 320px; border-radius: var(--radius-md); overflow: hidden; z-index: 9999;">
                                <div class="p-3 border-bottom d-flex justify-content-between align-items-center notification-header">
                                    <span class="fw-bold text-dark mb-0" style="font-size: 0.95rem;">การแจ้งเตือน</span>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-link p-0 text-decoration-none text-muted small" onclick="markAllNotificationsAsRead()" style="font-size: 0.75rem;">อ่านทั้งหมด</button>
                                        <span class="text-muted" style="font-size: 0.75rem;">|</span>
                                        <button class="btn btn-link p-0 text-decoration-none text-danger small" onclick="clearAllNotifications()" style="font-size: 0.75rem;">ล้างทั้งหมด</button>
                                    </div>
                                </div>
                                <div id="notification-list" style="max-height: 300px; overflow-y: auto;">
                                    <div class="text-center py-4 text-muted">
                                        <i class="bi bi-bell-slash fs-2 mb-2 d-block opacity-25"></i>
                                        ไม่มีแจ้งเตือนในขณะนี้
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if(isset($_SESSION['user_id'])): ?>
                        <?php 
                        $user_points_nav = 0;
                        if (isset($conn)) {
                            $uid_nav = $_SESSION['user_id'];
                            $up_q = mysqli_query($conn, "SELECT points FROM users WHERE id = '$uid_nav'");
                            if ($up_q && mysqli_num_rows($up_q) > 0) {
                                $user_points_nav = intval(mysqli_fetch_assoc($up_q)['points']);
                            }
                        }
                        ?>
                        <div class="dropdown">
                            <a class="icon-btn dropdown-toggle hide-arrow" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end animate__animated animate__fadeIn">
                                <li>
                                    <h6 class="dropdown-header text-truncate fw-bold" style="color: var(--text-main);">
                                        สวัสดี, <?= htmlspecialchars($_SESSION['fullname']) ?>
                                        <div class="text-warning mt-1" style="font-size:0.8rem; font-weight:normal;">
                                            <i class="bi bi-coin me-1"></i>🪙 <?= number_format($user_points_nav) ?> แต้มสะสม
                                        </div>
                                    </h6>
                                </li>
                                <li><a class="dropdown-item" href="my_orders.php"><i class="bi bi-box-seam me-2"></i> รายการสั่งซื้อ</a></li>
                                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-gear me-2"></i> ข้อมูลส่วนตัว</a></li>
                                <li><a class="dropdown-item" href="contact.php"><i class=" bi bi-telephone-fill me-2"></i> ติดต่อสอบถาม</a></li>
                                <li><a class="dropdown-item" href="about.php"><i class="bi bi-info-circle me-2"></i> เกี่ยวกับเรา</a></li>
                                <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-primary" href="admin_dashboard.php"><i class="bi bi-speedometer2 me-2"></i> จัดการระบบ</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a class="btn-auth ms-2" href="login.php">เข้าสู่ระบบ</a>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
    </div>
</nav>

<!-- Mobile Top Navigation Bar -->
<div class="mobile-top-bar sticky-top d-flex d-lg-none align-items-center justify-content-between px-3 py-2 bg-white border-bottom shadow-sm">
    <a class="mobile-brand d-flex align-items-center text-decoration-none" href="index.php">
        <img src="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>" alt="Logo" style="height: 34px; width: auto; object-fit: contain;">
        <span class="ms-2 fw-bold text-dark" style="font-size: 1.15rem;">Por Mae <span style="color: var(--blue-hover);">Bet Taled</span></span>
    </a>
    
    <div class="d-flex align-items-center gap-2">
        <button class="mobile-top-icon-btn border-0" id="mobileSearchToggle" title="ค้นหาสินค้า" type="button">
            <i class="bi bi-search"></i>
        </button>
        
        <button class="mobile-top-icon-btn border-0" id="mobileDarkModeToggle" title="เปลี่ยนธีมสี" type="button">
            <i class="bi bi-moon-stars" id="mobileDarkModeIcon"></i>
        </button>

        <?php if(isset($_SESSION['user_id']) || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')): ?>
            <div class="dropdown">
                <button class="mobile-top-icon-btn hide-arrow position-relative border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false" onclick="loadNotifications()">
                    <i class="bi bi-bell"></i>
                    <span id="mobile-notification-badge" class="badge-count hidden" style="font-size: 0.6rem; top: -1px; right: -1px; min-width: 15px; height: 15px;">0</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end animate__animated animate__fadeIn p-0 shadow-lg border-0" style="width: 290px; border-radius: var(--radius-md); overflow: hidden; z-index: 9999;">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center notification-header">
                        <span class="fw-bold text-dark mb-0" style="font-size: 0.9rem;">การแจ้งเตือน</span>
                        <div class="d-flex gap-2">
                            <button class="btn btn-link p-0 text-decoration-none text-muted small" onclick="markAllNotificationsAsRead()" style="font-size: 0.7rem;">อ่านทั้งหมด</button>
                            <span class="text-muted" style="font-size: 0.7rem;">|</span>
                            <button class="btn btn-link p-0 text-decoration-none text-danger small" onclick="clearAllNotifications()" style="font-size: 0.7rem;">ล้างทั้งหมด</button>
                        </div>
                    </div>
                    <div id="mobile-notification-list" style="max-height: 250px; overflow-y: auto;">
                        <div class="text-center py-3 text-muted">
                            <i class="bi bi-bell-slash fs-3 mb-2 d-block opacity-25"></i>
                            ไม่มีแจ้งเตือนในขณะนี้
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Mobile Expandable Search Bar Overlay -->
<div class="mobile-search-overlay d-lg-none" id="mobileSearchOverlay">
    <form action="index.php" method="GET" class="mobile-search-form" autocomplete="off">
        <div class="input-group">
            <span class="input-group-text bg-transparent border-0"><i class="bi bi-search text-muted"></i></span>
            <input class="form-control border-0" type="search" name="q" id="mobileSearchInput" placeholder="ค้นหาสินค้าที่ต้องการ..." value="<?= isset($_GET['q']) ? $_GET['q'] : '' ?>">
            <button class="btn border-0 text-muted" type="button" id="mobileSearchClose"><i class="bi bi-x-lg"></i></button>
        </div>
        <div id="mobile-search-suggest" class="search-suggest-box hidden" style="top: 55px; width: calc(100% - 30px); left: 15px;"></div>
    </form>
</div>

<!-- Mobile Bottom Navigation Bar -->
<div class="mobile-bottom-nav d-flex d-lg-none justify-content-around align-items-center bg-white border-top shadow-lg">
    <a href="index.php" class="mobile-nav-item <?= (basename($_SERVER['PHP_SELF']) == 'index.php' || basename($_SERVER['PHP_SELF']) == 'FitGear') ? 'active' : '' ?>">
        <i class="bi bi-house-door"></i>
        <span>หน้าแรก</span>
    </a>
    
    <a href="#" class="mobile-nav-item" id="mobileNavSearchBtn">
        <i class="bi bi-search"></i>
        <span>ค้นหา</span>
    </a>
    
    <a href="#" class="mobile-nav-item" onclick="event.preventDefault(); window.toggleCartDrawer();">
        <div class="mobile-nav-icon-wrapper">
            <i class="bi bi-cart3"></i>
            <span id="mobile-bottom-cart-badge" class="badge-count <?= $cart_count > 0 ? '' : 'hidden' ?>"><?= $cart_count ?></span>
        </div>
        <span>ตะกร้า</span>
    </a>
    
    <a href="wishlist.php" class="mobile-nav-item <?= (basename($_SERVER['PHP_SELF']) == 'wishlist.php' || basename($_SERVER['PHP_SELF']) == 'wishlist') ? 'active' : '' ?>">
        <div class="mobile-nav-icon-wrapper">
            <i class="bi bi-heart"></i>
            <span id="mobile-bottom-wishlist-badge" class="badge-count <?= $wishlist_count > 0 ? '' : 'hidden' ?>"><?= $wishlist_count ?></span>
        </div>
        <span>รายการโปรด</span>
    </a>
    
    <?php if(isset($_SESSION['user_id'])): ?>
        <div class="dropup mobile-nav-item">
            <a href="#" class="mobile-nav-item hide-arrow text-decoration-none" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="padding:0; border:none; background:none;">
                <i class="bi bi-person"></i>
                <span>บัญชี</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end animate__animated animate__fadeInUp shadow-lg border-0 mb-2" style="border-radius: 16px; z-index: 9999;">
                <li>
                    <h6 class="dropdown-header text-truncate fw-bold" style="color: var(--text-main); font-size: 0.85rem; padding: 12px 18px 6px 18px;">
                        สวัสดี, <?= htmlspecialchars($_SESSION['fullname']) ?>
                        <div class="text-warning mt-1" style="font-size:0.75rem; font-weight:normal;">
                            🪙 <?= number_format($user_points_nav) ?> แต้ม
                        </div>
                    </h6>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item" href="my_orders.php" style="padding: 10px 18px; font-size: 0.85rem;"><i class="bi bi-box-seam me-2" style="font-size:0.95rem;"></i> รายการสั่งซื้อ</a></li>
                <li><a class="dropdown-item" href="profile.php" style="padding: 10px 18px; font-size: 0.85rem;"><i class="bi bi-person-gear me-2" style="font-size:0.95rem;"></i> ข้อมูลส่วนตัว</a></li>
                <li><a class="dropdown-item" href="contact.php" style="padding: 10px 18px; font-size: 0.85rem;"><i class="bi bi-telephone-fill me-2" style="font-size:0.95rem;"></i> ติดต่อสอบถาม</a></li>
                <li><a class="dropdown-item" href="about.php" style="padding: 10px 18px; font-size: 0.85rem;"><i class="bi bi-info-circle me-2" style="font-size:0.95rem;"></i> เกี่ยวกับเรา</a></li>
                <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item text-primary" href="admin_dashboard.php" style="padding: 10px 18px; font-size: 0.85rem;"><i class="bi bi-speedometer2 me-2" style="font-size:0.95rem;"></i> จัดการระบบ</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item text-danger" href="logout.php" style="padding: 10px 18px; font-size: 0.85rem;"><i class="bi bi-box-arrow-right me-2" style="font-size:0.95rem;"></i> ออกจากระบบ</a></li>
            </ul>
        </div>
    <?php else: ?>
        <a href="login.php" class="mobile-nav-item">
            <i class="bi bi-box-arrow-in-right"></i>
            <span>เข้าสู่ระบบ</span>
        </a>
    <?php endif; ?>
</div>

<!-- Cart Drawer Structure -->
<div id="cartDrawerBackdrop" class="cart-drawer-backdrop" onclick="toggleCartDrawer()"></div>
<div id="cartDrawer" class="cart-drawer">
    <div class="cart-drawer-header">
        <h5 class="fw-bold mb-0 text-dark">🛍️ ตะกร้าสินค้าของคุณ</h5>
        <button type="button" class="btn-close-drawer" onclick="toggleCartDrawer()">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    
    <!-- Drawer Free Shipping Widget -->
    <div class="free-shipping-widget px-3 py-3 border-bottom" id="drawer-free-shipping-widget" style="display: none; background: #f8fafc;">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span id="drawer-free-shipping-icon" style="font-size: 1.1rem;">🚚</span>
            <span class="fw-bold text-dark" id="drawer-free-shipping-text" style="font-size: 0.78rem; line-height: 1.2;"></span>
        </div>
        <div class="free-shipping-bar-container" style="height: 8px; background: #e2e8f0; border-radius: 10px; overflow: hidden; position: relative;">
            <div class="free-shipping-bar-fill" id="drawer-free-shipping-bar-fill" style="width: 0%; height: 100%; border-radius: 10px; transition: width 0.4s cubic-bezier(0.16, 1, 0.3, 1), background 0.4s ease;"></div>
        </div>
    </div>

    <div class="cart-drawer-body" id="cart-drawer-body">
        <div class="text-center py-5 text-muted">
            <div class="spinner-border spinner-border-sm text-primary mb-2"></div>
            <div>กำลังโหลดสินค้า...</div>
        </div>
    </div>
    <div class="cart-drawer-footer" id="cart-drawer-footer" style="<?= $cart_count == 0 ? 'display: none !important;' : '' ?>">
        <div class="d-flex justify-content-between mb-1" style="font-size: 0.88rem;">
            <span class="text-muted">ยอดรวมสินค้า:</span>
            <span class="text-dark fw-semibold" id="cart-drawer-subtotal-val">฿0.00</span>
        </div>
        <div class="justify-content-between mb-1 text-success" id="cart-drawer-discount-row" style="display: none; font-size: 0.88rem;">
            <span>ส่วนลดคูปอง:</span>
            <span>-฿<span id="cart-drawer-discount-val">0.00</span></span>
        </div>
        <div class="d-flex justify-content-between mb-2" style="font-size: 0.88rem;">
            <span class="text-muted">ค่าจัดส่ง:</span>
            <span class="fw-semibold text-dark" id="cart-drawer-shipping-val">฿0.00</span>
        </div>
        <hr class="my-2 opacity-25">
        <div class="d-flex justify-content-between mb-3">
            <span class="text-dark fw-bold">ยอดสุทธิ:</span>
            <span class="fw-bold text-primary fs-5" id="cart-drawer-total-val">฿0.00</span>
            <span class="d-none" id="cart-drawer-subtotal">฿0.00</span> <!-- Legacy fallback compatibility -->
        </div>
        <div class="d-grid gap-2">
            <button type="button" onclick="window.toggleCartDrawer();" class="btn btn-outline-secondary rounded-pill">เลือกสินค้าต่อ</button>
            <a href="cart.php?action=checkout" id="cart-drawer-checkout-btn" class="btn btn-blue rounded-pill text-white fw-bold">ดำเนินการชำระเงิน</a>
        </div>
    </div>
</div>

<!-- Floating Cart Button -->
<button type="button" id="floatingCartBtn" class="floating-cart-btn <?= $cart_count > 0 ? '' : 'hidden' ?>" onclick="toggleCartDrawer()" title="ดูตะกร้าสินค้า">
    <i class="bi bi-cart3"></i>
    <span id="floating-cart-badge" class="floating-badge-count"><?= $cart_count ?></span>
</button>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('darkModeToggle');
    const toggleIcon = document.getElementById('darkModeIcon');
    const mobileToggleBtn = document.getElementById('mobileDarkModeToggle');
    const mobileToggleIcon = document.getElementById('mobileDarkModeIcon');
    
    // Sync dark mode icons on load
    if (document.body.classList.contains('dark-theme')) {
        if (toggleIcon) toggleIcon.className = 'bi bi-sun';
        if (mobileToggleIcon) mobileToggleIcon.className = 'bi bi-sun';
    }
    
    function toggleTheme() {
        document.body.classList.toggle('dark-theme');
        let theme = 'light';
        if (document.body.classList.contains('dark-theme')) {
            theme = 'dark';
            if (toggleIcon) toggleIcon.className = 'bi bi-sun';
            if (mobileToggleIcon) mobileToggleIcon.className = 'bi bi-sun';
        } else {
            if (toggleIcon) toggleIcon.className = 'bi bi-moon-stars';
            if (mobileToggleIcon) mobileToggleIcon.className = 'bi bi-moon-stars';
        }
        localStorage.setItem('theme', theme);
    }
    
    if (toggleBtn) toggleBtn.addEventListener('click', toggleTheme);
    if (mobileToggleBtn) mobileToggleBtn.addEventListener('click', toggleTheme);
});
</script>


