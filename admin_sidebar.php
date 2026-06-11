<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<script>
    (function() {
        const theme = localStorage.getItem('admin-theme') || 'light';
        if (theme === 'dark') {
            document.documentElement.classList.add('admin-dark-theme');
        }
    })();
</script>
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
    
    /* ==========================================================================
       PREMIUM DARK THEME STYLING FOR MERCHANT ADMIN (EYE-COMFORT SLATE DESIGN)
       ========================================================================= */
    /* Light/Dark Toggle and Bell circular unified modern layout */
    .admin-theme-toggle-wrapper {
        position: fixed !important;
        top: 15px !important;
        right: 75px !important;
        z-index: 2000 !important;
        height: 38px !important;
        display: flex !important;
        align-items: center !important;
    }
    .btn-theme-toggle {
        background: rgba(255, 255, 255, 0.85);
        border: 1px solid rgba(0, 0, 0, 0.06);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        border-radius: 50px;
        padding: 8px 16px;
        font-weight: 600;
        color: #1e293b;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        align-items: center;
        gap: 8px;
        height: 38px !important;
        box-sizing: border-box !important;
    }
    .btn-theme-toggle:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(127, 181, 255, 0.2);
        background: #ffffff;
        color: #7FB5FF;
    }
    .admin-dark-theme .btn-theme-toggle {
        background: rgba(30, 41, 59, 0.7) !important;
        border: 1px solid #334155 !important;
        color: #cbd5e1 !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2) !important;
    }
    .admin-dark-theme .btn-theme-toggle:hover {
        background: rgba(30, 41, 59, 0.9) !important;
        color: #7FB5FF !important;
        border-color: rgba(127, 181, 255, 0.3) !important;
        box-shadow: 0 8px 25px rgba(127, 181, 255, 0.25) !important;
    }

    .admin-bell-container {
        position: fixed !important;
        top: 15px !important;
        right: 25px !important;
        z-index: 2000 !important;
        height: 38px !important;
        display: flex !important;
        align-items: center !important;
    }
    .admin-bell-btn {
        height: 38px !important;
        width: 38px !important;
        box-sizing: border-box !important;
    }

    /* Soft Slate Dark Theme Variables & Styling (Anti-Eye Strain) */
    html.admin-dark-theme,
    body.admin-dark-theme,
    .admin-dark-theme body,
    .admin-dark-theme .container-fluid,
    .admin-dark-theme .row,
    .admin-dark-theme main,
    .admin-dark-theme .col-md-10,
    .admin-dark-theme .col-lg-10,
    .admin-dark-theme [class*="col-md-10"],
    .admin-dark-theme [class*="col-lg-10"],
    .admin-dark-theme [class*="col-sm-10"],
    .admin-dark-theme [class*="col-10"],
    .admin-dark-theme .bg-light,
    .admin-dark-theme .bg-body,
    .admin-dark-theme .bg-white {
        background: #0f172a !important;
        background-color: #0f172a !important;
        color: #cbd5e1 !important; /* Soft gray instead of pure white */
    }

    /* Sidebar Column Dark Theme Overrides */
    .admin-dark-theme .sidebar,
    .admin-dark-theme #sidebarMenu,
    .admin-dark-theme .col-md-2,
    .admin-dark-theme .col-lg-2,
    .admin-dark-theme .col-md-2.bg-white {
        background: #1e293b !important;
        background-color: #1e293b !important;
        border-right: 1px solid #334155 !important;
        border-color: #334155 !important;
    }
    .admin-dark-theme .brand-logo {
        color: #f1f5f9 !important; /* Soft off-white */
    }

    /* Sidenav Links in Dark Mode */
    .admin-dark-theme .nav-link {
        color: #94a3b8 !important;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }
    .admin-dark-theme .nav-link:not(.active):hover {
        background-color: rgba(255, 255, 255, 0.04) !important;
        color: #7FB5FF !important;
        transform: translateX(4px) !important;
    }
    .admin-dark-theme .nav-link.active {
        background: linear-gradient(135deg, #7FB5FF 0%, #5c9dfc 100%) !important;
        color: #ffffff !important;
        box-shadow: 0 8px 25px rgba(92, 157, 252, 0.4) !important;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1) !important;
    }

    /* Cards, Modals, Forms and Content Containers */
    .admin-dark-theme .card,
    .admin-dark-theme .table-card,
    .admin-dark-theme .card-modern,
    .admin-dark-theme .card-modern-mobile,
    .admin-dark-theme .card-feed,
    .admin-dark-theme .option-row,
    .admin-dark-theme .custom-modal-header,
    .admin-dark-theme .modal-content,
    .admin-dark-theme .modal-header,
    .admin-dark-theme .modal-body,
    .admin-dark-theme .modal-footer,
    .admin-dark-theme .filter-card-collapse,
    .admin-dark-theme .offcanvas,
    .admin-dark-theme .dropdown-menu,
    .admin-dark-theme .order-card,
    .admin-dark-theme .content-card,
    .admin-dark-theme .stat-card,
    .admin-dark-theme .stats-card,
    .admin-dark-theme .filter-card {
        background: linear-gradient(145deg, #1e293b 0%, #151e2e 100%) !important;
        color: #cbd5e1 !important;
        border: 1px solid rgba(255, 255, 255, 0.06) !important;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.18) !important;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    /* Premium Card Hover Glow and Lift effects */
    .admin-dark-theme .card:hover,
    .admin-dark-theme .order-card:hover,
    .admin-dark-theme .content-card:hover,
    .admin-dark-theme .stat-card:hover,
    .admin-dark-theme .stats-card:hover,
    .admin-dark-theme .card-modern:hover {
        transform: translateY(-4px) !important;
        box-shadow: 0 20px 35px rgba(0, 0, 0, 0.3), 0 0 15px rgba(127, 181, 255, 0.08) !important;
        border-color: rgba(127, 181, 255, 0.25) !important;
    }

    /* Headings & Text Colors */
    .admin-dark-theme h1,
    .admin-dark-theme h2,
    .admin-dark-theme h3,
    .admin-dark-theme h4,
    .admin-dark-theme h5,
    .admin-dark-theme h6,
    .admin-dark-theme .text-dark,
    .admin-dark-theme .modal-title,
    .admin-dark-theme .fw-bold,
    .admin-dark-theme strong,
    .admin-dark-theme .cat-name-td,
    .admin-dark-theme td.fw-bold {
        color: #f1f5f9 !important; /* Soft off-white */
    }
    .admin-dark-theme .text-muted,
    .admin-dark-theme .text-secondary,
    .admin-dark-theme label,
    .admin-dark-theme .form-label {
        color: #94a3b8 !important;
    }

    /* Input Controls & Dropdowns */
    .admin-dark-theme .form-control,
    .admin-dark-theme .form-select,
    .admin-dark-theme .form-control-plaintext,
    .admin-dark-theme .input-group-text,
    .admin-dark-theme textarea {
        background: #0f172a !important;
        background-color: #0f172a !important;
        color: #f1f5f9 !important;
        border: 1px solid #334155 !important;
        border-color: #334155 !important;
    }
    .admin-dark-theme .form-control::placeholder {
        color: #475569 !important;
    }
    .admin-dark-theme .form-control:focus,
    .admin-dark-theme .form-select:focus {
        background: #0f172a !important;
        background-color: #0f172a !important;
        color: #f1f5f9 !important;
        border-color: #7FB5FF !important;
        box-shadow: 0 0 0 4px rgba(127, 181, 255, 0.15) !important;
    }
    .admin-dark-theme select option {
        background-color: #1e293b !important;
        color: #f1f5f9 !important;
    }

    /* Action Buttons styling */
    .admin-dark-theme .btn-action {
        background: rgba(255, 255, 255, 0.05) !important;
        background-color: rgba(255, 255, 255, 0.05) !important;
        color: #94a3b8 !important;
    }
    .admin-dark-theme .btn-action:hover {
        background: #7FB5FF !important;
        background-color: #7FB5FF !important;
        color: white !important;
    }
    .admin-dark-theme .btn-close {
        filter: invert(1) grayscale(1) brightness(1.5);
    }

    /* Badges & Pagination */
    .admin-dark-theme .badge.bg-light,
    .admin-dark-theme .badge-light {
        background: rgba(255, 255, 255, 0.05) !important;
        background-color: rgba(255, 255, 255, 0.05) !important;
        color: #7FB5FF !important;
        border: 1px solid rgba(127, 181, 255, 0.15) !important;
    }
    
    /* Success State */
    .admin-dark-theme .badge.bg-success,
    .admin-dark-theme .badge-active,
    .admin-dark-theme .badge.bg-success-subtle {
        background-color: rgba(16, 185, 129, 0.15) !important;
        color: #34d399 !important;
        border: 1px solid rgba(16, 185, 129, 0.2) !important;
    }
    /* Warning/Pending State */
    .admin-dark-theme .badge.bg-warning,
    .admin-dark-theme .badge-scheduled,
    .admin-dark-theme .badge.bg-warning-subtle {
        background-color: rgba(245, 158, 11, 0.15) !important;
        color: #fbbf24 !important;
        border: 1px solid rgba(245, 158, 11, 0.2) !important;
    }
    /* Info/Shipping State */
    .admin-dark-theme .badge.bg-info,
    .admin-dark-theme .badge.bg-info-subtle {
        background-color: rgba(14, 165, 233, 0.15) !important;
        color: #38bdf8 !important;
        border: 1px solid rgba(14, 165, 233, 0.2) !important;
    }
    /* Danger/Cancelled State */
    .admin-dark-theme .badge.bg-danger,
    .admin-dark-theme .badge-expired,
    .admin-dark-theme .badge.bg-danger-subtle {
        background-color: rgba(239, 68, 68, 0.15) !important;
        color: #f87171 !important;
        border: 1px solid rgba(239, 68, 68, 0.2) !important;
    }
    /* Secondary/Neutral State */
    .admin-dark-theme .badge.bg-secondary,
    .admin-dark-theme .badge.bg-secondary-subtle {
        background-color: rgba(148, 163, 184, 0.15) !important;
        color: #cbd5e1 !important;
        border: 1px solid rgba(148, 163, 184, 0.2) !important;
    }

    .admin-dark-theme .page-link {
        background: #1e293b !important;
        background-color: #1e293b !important;
        border-color: #334155 !important;
        color: #cbd5e1 !important;
    }
    .admin-dark-theme .page-link:hover {
        background: rgba(255, 255, 255, 0.05) !important;
        background-color: rgba(255, 255, 255, 0.05) !important;
        color: #7FB5FF !important;
    }
    .admin-dark-theme .page-item.active .page-link {
        background: #7FB5FF !important;
        background-color: #7FB5FF !important;
        border-color: #7FB5FF !important;
        color: white !important;
    }

    /* Tables, Rows & Hover Overrides */
    .admin-dark-theme table,
    .admin-dark-theme .table {
        background: transparent !important;
        background-color: transparent !important;
        color: #cbd5e1 !important;
        border-color: #334155 !important;
        --bs-table-bg: transparent !important;
        --bs-table-border-color: #334155 !important;
        --bs-table-hover-bg: rgba(255, 255, 255, 0.02) !important;
    }
    .admin-dark-theme .table-responsive tr,
    .admin-dark-theme .custom-table tr,
    .admin-dark-theme table tr {
        background: transparent !important;
        background-color: transparent !important;
        border-color: #334155 !important;
    }
    .admin-dark-theme thead,
    .admin-dark-theme .table thead,
    .admin-dark-theme table thead {
        background: #1e293b !important;
        background-color: #1e293b !important;
    }
    .admin-dark-theme .table th,
    .admin-dark-theme table th {
        color: #94a3b8 !important;
        border-bottom: 2px solid #334155 !important;
        background: #1e293b !important;
        background-color: #1e293b !important;
    }
    .admin-dark-theme .table td,
    .admin-dark-theme table td {
        color: #cbd5e1 !important;
        border-bottom: 1px solid #334155 !important;
    }
    .admin-dark-theme .table tbody tr:hover,
    .admin-dark-theme table tbody tr:hover,
    .admin-dark-theme .tr-hover:hover,
    .admin-dark-theme .custom-table tr:hover {
        background: rgba(255, 255, 255, 0.02) !important;
        background-color: rgba(255, 255, 255, 0.02) !important;
    }

    /* Borders & Lines */
    .admin-dark-theme .border,
    .admin-dark-theme .border-bottom,
    .admin-dark-theme .border-top,
    .admin-dark-theme .border-end,
    .admin-dark-theme .border-start,
    .admin-dark-theme .border-end-md,
    .admin-dark-theme .border-top-md {
        border-color: #334155 !important;
    }
    .admin-dark-theme hr {
        background-color: #334155 !important;
        border-color: #334155 !important;
        opacity: 0.3;
    }

    /* Links Legibility in Dark Mode */
    .admin-dark-theme a:not(.btn):not(.nav-link):not(.page-link) {
        color: #7FB5FF !important;
    }
    .admin-dark-theme a:not(.btn):not(.nav-link):not(.page-link):hover {
        color: #AEE2FF !important;
    }

    /* Target specific warning/info boxes with inline backgrounds */
    .admin-dark-theme div[style*="background: #fffbeb"],
    .admin-dark-theme div[style*="background-color: #fffbeb"] {
        background: #0f172a !important;
        background-color: #0f172a !important;
        border-color: #334155 !important;
        color: #cbd5e1 !important;
    }

    /* Alerts overrides */
    .admin-dark-theme .alert {
        background-color: #1e293b !important;
        border-color: #334155 !important;
        color: #f1f5f9 !important;
    }
    .admin-dark-theme .alert-success { border-left: 4px solid #10b981 !important; }
    .admin-dark-theme .alert-danger { border-left: 4px solid #ef4444 !important; }
    .admin-dark-theme .alert-warning { border-left: 4px solid #f59e0b !important; }

    /* SweetAlert2 (Swal Dialogs) */
    .admin-dark-theme .swal2-popup {
        background-color: #1e293b !important;
        color: #cbd5e1 !important;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3) !important;
        border: 1px solid #334155 !important;
    }
    .admin-dark-theme .swal2-title,
    .admin-dark-theme .swal2-html-container {
        color: #f1f5f9 !important;
    }
    .admin-dark-theme .swal2-confirm {
        background-color: #7FB5FF !important;
    }
    .admin-dark-theme .swal2-cancel {
        background-color: rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
    }

    /* MacBook Mail Preview, Tabs & Dropzones */
    .admin-dark-theme .tab-segmented {
        background: #0f172a !important;
        border-color: #334155 !important;
    }
    .admin-dark-theme .tab-segmented .nav-link:not(.active) {
        color: #94a3b8 !important;
    }
    .admin-dark-theme .tab-segmented .nav-link.active {
        background-color: #1e293b !important;
        color: #7FB5FF !important;
    }
    .admin-dark-theme .upload-dropzone {
        border-color: #475569 !important;
        background: #0f172a !important;
    }
    .admin-dark-theme .upload-dropzone:hover {
        border-color: #7FB5FF !important;
        background: rgba(127, 181, 255, 0.05) !important;
    }
    .admin-dark-theme .macbook-mockup {
        background: #1e293b !important;
        border-color: #334155 !important;
    }
    .admin-dark-theme .preview-header-bar {
        background: #1e293b !important;
        border-bottom: 1px solid #334155 !important;
    }
    .admin-dark-theme .preview-body {
        background: #1e293b !important;
    }
    .admin-dark-theme .user-card-pill {
        background: #0f172a !important;
        border-color: #334155 !important;
    }
    .admin-dark-theme .user-card-pill.active {
        border-color: #7FB5FF !important;
        background: rgba(127, 181, 255, 0.05) !important;
    }

    /* Dark Mode Premium Toggle Button Switcher */
    .admin-dark-theme .btn-theme-toggle {
        background: rgba(30, 41, 59, 0.7) !important;
        border: 1px solid #334155 !important;
        color: #cbd5e1 !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2) !important;
    }
    .admin-dark-theme .btn-theme-toggle:hover {
        background: rgba(30, 41, 59, 0.9) !important;
        color: #7FB5FF !important;
        border-color: rgba(127, 181, 255, 0.3) !important;
        box-shadow: 0 8px 25px rgba(127, 181, 255, 0.25) !important;
    }

    /* Bell Widget & Toast Alerts Dark Theme */
    .admin-dark-theme .admin-bell-btn {
        background: rgba(30, 41, 59, 0.7) !important;
        border: 1px solid #334155 !important;
        color: #cbd5e1 !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2) !important;
    }
    .admin-dark-theme .admin-bell-btn:hover {
        background: rgba(30, 41, 59, 0.9) !important;
        color: #7FB5FF !important;
        border-color: rgba(127, 181, 255, 0.3) !important;
        box-shadow: 0 8px 25px rgba(127, 181, 255, 0.25) !important;
    }
    .admin-dark-theme .admin-bell-dropdown {
        background: #1e293b !important;
        border-color: #334155 !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.45) !important;
    }
    .admin-dark-theme .admin-bell-header {
        background: #1e293b !important;
        border-bottom: 1px solid #334155 !important;
        color: #f1f5f9 !important;
    }
    .admin-dark-theme .admin-bell-footer {
        background: #1e293b !important;
        border-top: 1px solid #334155 !important;
        color: #f1f5f9 !important;
    }
    .admin-dark-theme .admin-notif-item {
        border-bottom-color: rgba(255, 255, 255, 0.03) !important;
        color: #cbd5e1 !important;
    }
    .admin-dark-theme .admin-notif-item:hover {
        background: rgba(255, 255, 255, 0.02) !important;
    }
    .admin-dark-theme .admin-notif-item.unread {
        background: rgba(127, 181, 255, 0.1) !important;
        border-left-color: #7FB5FF !important;
    }
    .admin-dark-theme .admin-notif-title {
        color: #f1f5f9 !important;
    }
    .admin-dark-theme .admin-notif-msg {
        color: #94a3b8 !important;
    }
    .admin-dark-theme .admin-toast {
        background: #1e293b !important;
        color: #cbd5e1 !important;
        box-shadow: 0 10px 25px rgba(0,0,0,0.3) !important;
        border: 1px solid #334155 !important;
    }
    .admin-dark-theme .admin-toast-title {
        color: #f1f5f9 !important;
    }
    .admin-dark-theme .admin-toast-msg {
        color: #94a3b8 !important;
    }

    /* Mobile dark styling */
    @media (max-width: 767px) {
        .admin-theme-toggle-wrapper {
            top: 11px !important;
            right: 15px !important;
            z-index: 2010 !important;
            height: 38px !important;
        }
        .btn-theme-toggle {
            width: 38px !important;
            height: 38px !important;
            padding: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 50% !important;
        }
        .btn-theme-toggle span {
            display: none !important;
        }
        .admin-bell-container {
            top: 11px !important;
            right: 60px !important;
            z-index: 2010 !important;
            height: 38px !important;
        }
        
        /* Mobile Hamburger Header Realignment */
        button[data-bs-target="#sidebarMenu"] {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            gap: 12px !important;
            pointer-events: none !important; /* Prevent accidental triggers on header empty space */
            padding-left: 20px !important;
        }
        button[data-bs-target="#sidebarMenu"]::before {
            order: 2 !important;
            pointer-events: none !important;
        }
        button[data-bs-target="#sidebarMenu"] i {
            order: 1 !important;
            pointer-events: auto !important; /* Only the icon triggers the drawer */
            cursor: pointer !important;
            margin-right: 0 !important;
        }

        .admin-dark-theme button[data-bs-target="#sidebarMenu"] {
            background: rgba(30, 41, 59, 0.9) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
        }
        .admin-dark-theme button[data-bs-target="#sidebarMenu"]::before {
            color: #f1f5f9 !important;
        }
        .admin-dark-theme button[data-bs-target="#sidebarMenu"] i {
            background: #334155 !important;
            color: #f1f5f9 !important;
        }
    }

    /* Custom premium scrollbar in dark mode */
    .admin-dark-theme ::-webkit-scrollbar {
        width: 8px !important;
        height: 8px !important;
    }
    .admin-dark-theme ::-webkit-scrollbar-track {
        background: #0f172a !important;
    }
    .admin-dark-theme ::-webkit-scrollbar-thumb {
        background: #1e293b !important;
        border: 2px solid #0f172a !important;
        border-radius: 10px !important;
    }
    .admin-dark-theme ::-webkit-scrollbar-thumb:hover {
        background: #334155 !important;
    }

    /* --- Order Status Summary Cards Dark Overrides --- */
    .admin-dark-theme div[style*="background-color: #fffbeb"] {
        background: rgba(245, 158, 11, 0.08) !important;
        background-color: rgba(245, 158, 11, 0.08) !important;
        border: 1px solid rgba(245, 158, 11, 0.18) !important;
        border-left: 4px solid #ffc107 !important;
        color: #cbd5e1 !important;
    }
    .admin-dark-theme div[style*="background-color: #f0f9ff"] {
        background: rgba(14, 165, 233, 0.08) !important;
        background-color: rgba(14, 165, 233, 0.08) !important;
        border: 1px solid rgba(14, 165, 233, 0.18) !important;
        border-left: 4px solid #0ea5e9 !important;
        color: #cbd5e1 !important;
    }
    .admin-dark-theme div[style*="background-color: #f0fdf4"] {
        background: rgba(16, 185, 129, 0.08) !important;
        background-color: rgba(16, 185, 129, 0.08) !important;
        border: 1px solid rgba(16, 185, 129, 0.18) !important;
        border-left: 4px solid #16a34a !important;
        color: #cbd5e1 !important;
    }
    .admin-dark-theme div[style*="background-color: #f9fafb"] {
        background: rgba(148, 163, 184, 0.08) !important;
        background-color: rgba(148, 163, 184, 0.08) !important;
        border: 1px solid rgba(148, 163, 184, 0.18) !important;
        border-left: 4px solid #9ca3af !important;
        color: #cbd5e1 !important;
    }

    /* --- Form Outline Buttons & Password Visibility Toggles --- */
    .admin-dark-theme button[onclick*="togglePasswordVisibility"] {
        background: #0f172a !important;
        background-color: #0f172a !important;
        border: 1px solid #334155 !important;
        color: #cbd5e1 !important;
    }
    .admin-dark-theme button[onclick*="togglePasswordVisibility"]:hover {
        background: #1e293b !important;
        color: #f1f5f9 !important;
    }
    .admin-dark-theme .btn-outline-secondary {
        background: rgba(255, 255, 255, 0.04) !important;
        color: #cbd5e1 !important;
        border: 1px solid #334155 !important;
    }
    .admin-dark-theme .btn-outline-secondary:hover {
        background: rgba(255, 255, 255, 0.08) !important;
        color: #f1f5f9 !important;
        border-color: #475569 !important;
    }
    .admin-dark-theme .btn-outline-primary {
        background: rgba(127, 181, 255, 0.04) !important;
        color: #7FB5FF !important;
        border: 1px solid rgba(127, 181, 255, 0.3) !important;
    }
    .admin-dark-theme .btn-outline-primary:hover {
        background: #7FB5FF !important;
        color: white !important;
        box-shadow: 0 4px 12px rgba(127, 181, 255, 0.25) !important;
    }
    .admin-dark-theme #test-smtp-btn {
        background-color: #1e293b !important;
        color: #7FB5FF !important;
        border-color: rgba(127, 181, 255, 0.3) !important;
    }
    .admin-dark-theme #test-smtp-btn:hover {
        background-color: #7FB5FF !important;
        color: white !important;
    }
    .admin-dark-theme #iconPreview {
        background-color: #0f172a !important;
        border-color: #334155 !important;
    }

    /* --- Tab Controls and Logs Page Visual Comfort --- */
    .admin-dark-theme #log-tabs,
    .admin-dark-theme .nav-tabs {
        border-bottom-color: #334155 !important;
    }
    .admin-dark-theme #log-tabs .nav-link,
    .admin-dark-theme .nav-tabs .nav-link {
        color: #94a3b8 !important;
        background: transparent !important;
        border: none !important;
    }
    .admin-dark-theme #log-tabs .nav-link.active,
    .admin-dark-theme .nav-tabs .nav-link.active {
        color: #7FB5FF !important;
        border-bottom: 3px solid #7FB5FF !important;
        background: transparent !important;
    }
    .admin-dark-theme .collapse table,
    .admin-dark-theme table[style*="background: white"],
    .admin-dark-theme table[style*="background-color: white"] {
        background: #0f172a !important;
        background-color: #0f172a !important;
        border-color: #334155 !important;
        color: #cbd5e1 !important;
    }
    .admin-dark-theme .table-light,
    .admin-dark-theme thead.table-light,
    .admin-dark-theme thead[class*="table-light"] {
        background: #1e293b !important;
        background-color: #1e293b !important;
        color: #f1f5f9 !important;
    }
    .admin-dark-theme .bg-light,
    .admin-dark-theme .card.bg-light {
        background: #0f172a !important;
        background-color: #0f172a !important;
        border-color: #334155 !important;
    }

    /* --- Row hover details and button color protection --- */
    .admin-dark-theme tr:hover td,
    .admin-dark-theme tr:hover th,
    .admin-dark-theme tbody tr:hover td,
    .admin-dark-theme tbody tr:hover th {
        background-color: rgba(255, 255, 255, 0.02) !important;
        color: #f1f5f9 !important;
    }
    .admin-dark-theme .btn-light {
        background: rgba(255, 255, 255, 0.05) !important;
        border: 1px solid rgba(255, 255, 255, 0.08) !important;
        color: #cbd5e1 !important;
    }
    .admin-dark-theme .btn-light:hover {
        background: rgba(255, 255, 255, 0.12) !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
        color: #ffffff !important;
    }
    .admin-dark-theme .btn-light.text-primary { color: #7FB5FF !important; }
    .admin-dark-theme .btn-light.text-primary:hover { color: #AEE2FF !important; }
    .admin-dark-theme .btn-light.text-danger { color: #f87171 !important; }
    .admin-dark-theme .btn-light.text-danger:hover { color: #ffa3a3 !important; }
    .admin-dark-theme .btn-light.text-success { color: #34d399 !important; }
    .admin-dark-theme .btn-light.text-success:hover { color: #6ee7b7 !important; }

    /* --- Recipient selection tabs & row cards (Send Mail Page) --- */
    .admin-dark-theme .recipient-tabs {
        background: #0f172a !important;
        border: 1px solid #334155 !important;
    }
    .admin-dark-theme .recipient-tabs .btn-check + .btn {
        color: #94a3b8 !important;
    }
    .admin-dark-theme .recipient-tabs .btn-check:checked + .btn {
        background: #1e293b !important;
        color: #7FB5FF !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
        font-weight: 600;
    }
    .admin-dark-theme .recipient-tabs .btn-check:not(:checked) + .btn:hover {
        background: rgba(255, 255, 255, 0.03) !important;
        color: #cbd5e1 !important;
    }
    .admin-dark-theme .user-row-card {
        background: #1e293b !important;
        border: 1px solid #334155 !important;
        color: #cbd5e1 !important;
    }
    .admin-dark-theme .user-row-card:hover {
        border-color: #7FB5FF !important;
        box-shadow: 0 4px 12px rgba(127, 181, 255, 0.15) !important;
    }
    
    /* modal compatibility */
    .admin-dark-theme .modal-dialog .modal-content {
        background: #1e293b !important;
        color: #cbd5e1 !important;
        border: 1px solid #334155 !important;
    }

    /* --- Table Highlight States for Editing/Warning (Comfort Colors) --- */
    .admin-dark-theme .table-warning,
    .admin-dark-theme tr.table-warning,
    .admin-dark-theme tr.table-warning td,
    .admin-dark-theme tr.table-warning th {
        background: rgba(245, 158, 11, 0.12) !important;
        background-color: rgba(245, 158, 11, 0.12) !important;
        color: #fbbf24 !important;
        border-color: rgba(245, 158, 11, 0.25) !important;
    }
    .admin-dark-theme tr.table-warning td a,
    .admin-dark-theme tr.table-warning td span,
    .admin-dark-theme tr.table-warning td div {
        color: #fbbf24 !important;
    }

    .admin-dark-theme .table-success,
    .admin-dark-theme tr.table-success,
    .admin-dark-theme tr.table-success td,
    .admin-dark-theme tr.table-success th {
        background: rgba(16, 185, 129, 0.12) !important;
        background-color: rgba(16, 185, 129, 0.12) !important;
        color: #34d399 !important;
        border-color: rgba(16, 185, 129, 0.25) !important;
    }
    .admin-dark-theme tr.table-success td a,
    .admin-dark-theme tr.table-success td span,
    .admin-dark-theme tr.table-success td div {
        color: #34d399 !important;
    }

    .admin-dark-theme .table-danger,
    .admin-dark-theme tr.table-danger,
    .admin-dark-theme tr.table-danger td,
    .admin-dark-theme tr.table-danger th {
        background: rgba(239, 68, 68, 0.12) !important;
        background-color: rgba(239, 68, 68, 0.12) !important;
        color: #f87171 !important;
        border-color: rgba(239, 68, 68, 0.25) !important;
    }
    .admin-dark-theme tr.table-danger td a,
    .admin-dark-theme tr.table-danger td span,
    .admin-dark-theme tr.table-danger td div {
        color: #f87171 !important;
    }

    .admin-dark-theme .table-info,
    .admin-dark-theme tr.table-info,
    .admin-dark-theme tr.table-info td,
    .admin-dark-theme tr.table-info th {
        background: rgba(14, 165, 233, 0.12) !important;
        background-color: rgba(14, 165, 233, 0.12) !important;
        color: #38bdf8 !important;
        border-color: rgba(14, 165, 233, 0.25) !important;
    }
    .admin-dark-theme tr.table-info td a,
    .admin-dark-theme tr.table-info td span,
    .admin-dark-theme tr.table-info td div {
        color: #38bdf8 !important;
    }

    /* --- Inline Light Blue Badges Dark Override --- */
    .admin-dark-theme span[style*="background:#AEE2FF"],
    .admin-dark-theme span[style*="background: #AEE2FF"],
    .admin-dark-theme span[style*="background-color:#AEE2FF"],
    .admin-dark-theme span[style*="background-color: #AEE2FF"] {
        background: rgba(127, 181, 255, 0.15) !important;
        background-color: rgba(127, 181, 255, 0.15) !important;
        color: #7FB5FF !important;
        border: 1px solid rgba(127, 181, 255, 0.25) !important;
    }

    /* ==========================================================================
       PHASE 2: TARGET REMAINING LIGHT/EYE-STRAINING ELEMENTS
       ========================================================================= */
    /* --- Admin Lottery Buttons (Admin.php) --- */
    .admin-dark-theme button[style*="background-color: #85D1FF"],
    .admin-dark-theme button[style*="background-color:#85D1FF"] {
        background: #7FB5FF !important;
        background-color: #7FB5FF !important;
        color: #0f172a !important;
        box-shadow: 0 4px 12px rgba(127, 181, 255, 0.25) !important;
    }
    .admin-dark-theme button[style*="background-color: #85D1FF"]:hover,
    .admin-dark-theme button[style*="background-color:#85D1FF"]:hover {
        background: #aee2ff !important;
        background-color: #aee2ff !important;
        color: #0f172a !important;
    }

    /* --- Separate Warning backgrounds to protect border layout --- */
    .admin-dark-theme div[style*="background-color: #fffbeb"] {
        background: rgba(245, 158, 11, 0.08) !important;
        background-color: rgba(245, 158, 11, 0.08) !important;
        border: 1px solid rgba(245, 158, 11, 0.18) !important;
        border-left: 4px solid #ffc107 !important;
        color: #cbd5e1 !important;
    }
    .admin-dark-theme div[style*="background: #fffbeb"] {
        background: rgba(245, 158, 11, 0.08) !important;
        background-color: rgba(245, 158, 11, 0.08) !important;
        border: 1px solid rgba(245, 158, 11, 0.18) !important;
        color: #cbd5e1 !important;
    }

    /* --- Mockup Email Preview Box (Send Mail Page) --- */
    .admin-dark-theme div[style*="background-color: #f8fafc"] {
        background-color: #0f172a !important;
        background: #0f172a !important;
    }
    .admin-dark-theme div[style*="background-color: #f0f7ff"] {
        background-color: rgba(127, 181, 255, 0.08) !important;
        background: rgba(127, 181, 255, 0.08) !important;
        border-color: rgba(127, 181, 255, 0.3) !important;
    }
    .admin-dark-theme #preview_coupon_code {
        color: #7FB5FF !important;
    }
    .admin-dark-theme .email-preview-box div[style*="color: #1e293b"] {
        color: #cbd5e1 !important;
    }
    .admin-dark-theme .email-preview-box #preview_body {
        color: #94a3b8 !important;
    }
    .admin-dark-theme .email-preview-box div[style*="color: #475569"],
    .admin-dark-theme .email-preview-box span[style*="color: #475569"] {
        color: #cbd5e1 !important;
    }
    .admin-dark-theme #preview_hero_image_div {
        border-bottom-color: #334155 !important;
    }

    /* --- User Feedbacks Avatar (Feedback Page) --- */
    .admin-dark-theme .user-avatar {
        background: rgba(127, 181, 255, 0.15) !important;
        color: #7FB5FF !important;
        border: 1px solid rgba(127, 181, 255, 0.25) !important;
    }

    /* --- Admin Logs Page Polish (Operator visibility & avatars) --- */
    .admin-dark-theme .log-admin {
        color: #cbd5e1 !important;
    }
    .admin-dark-theme .admin-avatar {
        background: rgba(127, 181, 255, 0.15) !important;
        color: #7FB5FF !important;
        border: 1px solid rgba(127, 181, 255, 0.25) !important;
    }
</style>

<div class="admin-theme-toggle-wrapper">
    <button class="btn btn-theme-toggle" id="admin-dark-mode-toggle" onclick="toggleAdminTheme(event)" style="cursor: pointer;">
        <i class="bi bi-moon-fill" id="admin-theme-icon"></i> 
        <span class="d-none d-md-inline" id="admin-theme-text">โหมดมืด</span>
    </button>
</div>

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

// Function to update Chart.js theme colors dynamically
function updateChartsForTheme(theme) {
    const isDark = (theme === 'dark');
    const labelColor = isDark ? '#94a3b8' : '#64748b';
    const gridColor = isDark ? '#334155' : '#e2e8f0';
    
    if (window.salesChartInstance && window.salesChartInstance.options) {
        if (window.salesChartInstance.options.scales) {
            if (window.salesChartInstance.options.scales.y) {
                window.salesChartInstance.options.scales.y.ticks.color = labelColor;
                window.salesChartInstance.options.scales.y.grid.color = gridColor;
            }
            if (window.salesChartInstance.options.scales.x) {
                window.salesChartInstance.options.scales.x.ticks.color = labelColor;
                if (window.salesChartInstance.options.scales.x.grid) {
                    window.salesChartInstance.options.scales.x.grid.color = gridColor;
                }
            }
        }
        window.salesChartInstance.update();
    }
    
    if (window.categoryChartInstance && window.categoryChartInstance.options) {
        if (window.categoryChartInstance.options.plugins && window.categoryChartInstance.options.plugins.legend) {
            window.categoryChartInstance.options.plugins.legend.labels = window.categoryChartInstance.options.plugins.legend.labels || {};
            window.categoryChartInstance.options.plugins.legend.labels.color = labelColor;
        }
        if (window.categoryChartInstance.data && window.categoryChartInstance.data.datasets && window.categoryChartInstance.data.datasets[0]) {
            window.categoryChartInstance.data.datasets[0].borderColor = isDark ? '#1e293b' : '#ffffff';
        }
        window.categoryChartInstance.update();
    }
}

// Sync Admin Theme toggles on load
document.addEventListener('DOMContentLoaded', function() {
    const theme = localStorage.getItem('admin-theme') || 'light';
    const iconEl = document.getElementById('admin-theme-icon');
    const textEl = document.getElementById('admin-theme-text');
    if (iconEl && textEl) {
        if (theme === 'dark') {
            iconEl.className = 'bi bi-sun-fill';
            textEl.textContent = 'โหมดสว่าง';
        } else {
            iconEl.className = 'bi bi-moon-fill';
            textEl.textContent = 'โหมดมืด';
        }
    }
    
    // Initial chart colors update (delay slightly to let Chart.js instances initialize)
    setTimeout(function() {
        updateChartsForTheme(theme);
    }, 300);
});

function toggleAdminTheme(event) {
    if (event) event.preventDefault();
    const isDark = document.documentElement.classList.toggle('admin-dark-theme');
    document.body.classList.toggle('admin-dark-theme', isDark);
    
    const theme = isDark ? 'dark' : 'light';
    localStorage.setItem('admin-theme', theme);
    
    const iconEl = document.getElementById('admin-theme-icon');
    const textEl = document.getElementById('admin-theme-text');
    if (iconEl && textEl) {
        if (isDark) {
            iconEl.className = 'bi bi-sun-fill';
            textEl.textContent = 'โหมดสว่าง';
        } else {
            iconEl.className = 'bi bi-moon-fill';
            textEl.textContent = 'โหมดมืด';
        }
    }
    
    // Update charts dynamically on toggle
    updateChartsForTheme(theme);
}
</script>

