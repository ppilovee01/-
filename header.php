<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$cart_count = 0;
if(isset($_SESSION['cart'])) {
    foreach($_SESSION['cart'] as $item) {
        $cart_count += is_array($item) ? $item['qty'] : $item;
    }
}



if (!isset($page_title)) $page_title = "Por Mae Bet Taled | ร้านค้าออนไลน์เบ็ดเตล็ด";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
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
                top: 55px !important;
                left: 0 !important;
                right: 0 !important;
                width: 100% !important;
                transform: none !important;
                border-radius: 16px;
                box-shadow: 0 12px 30px rgba(0,0,0,0.1);
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
    </style>
    
    <?php if(isset($extra_css)) echo $extra_css; ?>
    <link rel="stylesheet" href="style.css?v=2.4">
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

            // --- Live Auto-suggest Search ---
            const searchInput = document.getElementById('globalSearchInput');
            const suggestBox = document.getElementById('search-suggest');
            let debounceTimeout;

            if (searchInput && suggestBox) {
                searchInput.addEventListener('input', () => {
                    clearTimeout(debounceTimeout);
                    const query = searchInput.value.trim();

                    if (query.length < 2) {
                        suggestBox.innerHTML = '';
                        suggestBox.classList.add('hidden');
                        return;
                    }

                    // รอหน่วงเวลา 200ms เพื่อไม่ให้ถล่มเซิร์ฟเวอร์ด้วยคิวรี่จำนวนมาก
                    debounceTimeout = setTimeout(() => {
                        fetch(`ajax.php?action=search_suggest&q=${encodeURIComponent(query)}`)
                        .then(r => r.json())
                        .then(res => {
                            if (res.status === 'success') {
                                renderSuggestions(res.data);
                            }
                        })
                        .catch(err => console.error(err));
                    }, 200);
                });

                // แสดงกล่องข้อแนะนำอีกครั้งเมื่อรับโฟกัส
                searchInput.addEventListener('focus', () => {
                    const query = searchInput.value.trim();
                    if (query.length >= 2 && suggestBox.children.length > 0) {
                        suggestBox.classList.remove('hidden');
                    }
                });

                // ซ่อนเมื่ออยู่นอกโฟกัส (หน่วงเวลา 150ms เพื่อให้คลิกที่รายการสินค้าทำงานสำเร็จก่อน)
                searchInput.addEventListener('blur', () => {
                    setTimeout(() => {
                        suggestBox.classList.add('hidden');
                    }, 150);
                });
            }

            function renderSuggestions(data) {
                if (data.length === 0) {
                    suggestBox.innerHTML = '<div class="suggest-no-result text-muted">ไม่พบข้อมูลสินค้า</div>';
                    suggestBox.classList.remove('hidden');
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

                suggestBox.innerHTML = html;
                suggestBox.classList.remove('hidden');
            }
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
            if(!list) return;
            
            list.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div>';
            
            fetch('ajax.php?action=get_notifications')
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    updateNotificationBadge(res.unread_count);
                    
                    if (res.notifications.length === 0) {
                        list.innerHTML = `
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-bell-slash fs-2 mb-2 d-block opacity-25"></i>
                                ไม่มีแจ้งเตือนในขณะนี้
                            </div>
                        `;
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
                    list.innerHTML = html;
                }
            })
            .catch(err => console.error(err));
        };

        window.updateNotificationBadge = function(count) {
            const badge = document.getElementById('notification-badge');
            if(!badge) return;
            if (count > 0) {
                badge.innerText = count;
                badge.classList.remove('hidden');
            } else {
                badge.innerText = 0;
                badge.classList.add('hidden');
            }
        };

        window.handleNotificationClick = function(id, url) {
            let fd = new FormData();
            fd.append('action', 'mark_read');
            fd.append('notification_id', id);
            
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
    </script>
</head>
<body>

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

<nav class="navbar navbar-expand-lg sticky-top glass-nav">
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
                    
                    <a class="icon-btn" href="wishlist.php" title="รายการโปรด">
                        <i class="bi bi-heart"></i>
                    </a>

                    <a class="icon-btn" href="cart.php" title="ตะกร้าสินค้า">
                        <i class="bi bi-bag"></i>
                        <span id="nav-cart-badge" class="badge-count <?= $cart_count > 0 ? '' : 'hidden' ?>"><?= $cart_count ?></span>
                    </a>

                    <!-- Notification Bell Dropdown -->
                    <?php if(isset($_SESSION['user_id']) || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')): ?>
                        <div class="dropdown" id="notificationDropdown">
                            <a class="icon-btn dropdown-toggle hide-arrow" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" onclick="loadNotifications()">
                                <i class="bi bi-bell"></i>
                                <span id="notification-badge" class="badge-count hidden">0</span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end animate__animated animate__fadeIn p-0 shadow-lg border-0" style="width: 320px; border-radius: var(--radius-md); overflow: hidden; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(15px); z-index: 9999;">
                                <div class="p-3 border-bottom d-flex justify-content-between align-items-center" style="background: var(--bg-soft);">
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
                        <div class="dropdown">
                            <a class="icon-btn dropdown-toggle hide-arrow" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end animate__animated animate__fadeIn">
                                <li><h6 class="dropdown-header text-truncate fw-bold">สวัสดี, <?= htmlspecialchars($_SESSION['fullname']) ?></h6></li>
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

