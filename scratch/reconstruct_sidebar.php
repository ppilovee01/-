<?php
$file = 'admin_sidebar.php';

// Reset to clean git state first to prevent duplicate injections
shell_exec('git checkout -- admin_sidebar.php');

$content = file_get_contents($file);

// Normalize line endings to LF to avoid matching issues on Windows
$content = str_replace("\r\n", "\n", $content);

// 1. Immediate theme loader script injection
$search1 = 'basename($_SERVER[\'PHP_SELF\']);
?>';
$replace1 = 'basename($_SERVER[\'PHP_SELF\']);
?>
<script>
    (function() {
        const theme = localStorage.getItem(\'admin-theme\') || \'light\';
        if (theme === \'dark\') {
            document.documentElement.classList.add(\'admin-dark-theme\');
        }
    })();
</script>';

// 2. Custom CSS injection
$search2 = '        /* ดันเนื้อหาหลักลงมาใต้ Header */
        body {
            padding-top: 60px !important;
        }
    }
</style>';
$replace2 = '        /* ดันเนื้อหาหลักลงมาใต้ Header */
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
</style>';

// 3. Toggle button HTML injection
$search3 = '<div class="sidebar d-flex flex-column h-100">';
$replace3 = '<div class="admin-theme-toggle-wrapper">
    <button class="btn btn-theme-toggle" id="admin-dark-mode-toggle" onclick="toggleAdminTheme(event)" style="cursor: pointer;">
        <i class="bi bi-moon-fill" id="admin-theme-icon"></i> 
        <span class="d-none d-md-inline" id="admin-theme-text">โหมดมืด</span>
    </button>
</div>

<div class="sidebar d-flex flex-column h-100">';

// 4. Toggle script injection
$search4 = '    if (typeof fetchLogs === \'function\') {
        history.pushState(null, \'\', newUrl);
        fetchLogs(newUrl);
    } else if (typeof fetchOrdersFiltered === \'function\') {
        // fetchOrdersFiltered handles limit reading inside it, so we just call it
        fetchOrdersFiltered(false, \'1\');
    } else {
        window.location.href = newUrl;
    }
}
</script>';
$replace4 = '    if (typeof fetchLogs === \'function\') {
        history.pushState(null, \'\', newUrl);
        fetchLogs(newUrl);
    } else if (typeof fetchOrdersFiltered === \'function\') {
        // fetchOrdersFiltered handles limit reading inside it, so we just call it
        fetchOrdersFiltered(false, \'1\');
    } else {
        window.location.href = newUrl;
    }
}

// Function to update Chart.js theme colors dynamically
function updateChartsForTheme(theme) {
    const isDark = (theme === \'dark\');
    const labelColor = isDark ? \'#94a3b8\' : \'#64748b\';
    const gridColor = isDark ? \'#334155\' : \'#e2e8f0\';
    
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
            window.categoryChartInstance.data.datasets[0].borderColor = isDark ? \'#1e293b\' : \'#ffffff\';
        }
        window.categoryChartInstance.update();
    }
}

// Sync Admin Theme toggles on load
document.addEventListener(\'DOMContentLoaded\', function() {
    const theme = localStorage.getItem(\'admin-theme\') || \'light\';
    const iconEl = document.getElementById(\'admin-theme-icon\');
    const textEl = document.getElementById(\'admin-theme-text\');
    if (iconEl && textEl) {
        if (theme === \'dark\') {
            iconEl.className = \'bi bi-sun-fill\';
            textEl.textContent = \'โหมดสว่าง\';
        } else {
            iconEl.className = \'bi bi-moon-fill\';
            textEl.textContent = \'โหมดมืด\';
        }
    }
    
    // Initial chart colors update (delay slightly to let Chart.js instances initialize)
    setTimeout(function() {
        updateChartsForTheme(theme);
    }, 300);
});

function toggleAdminTheme(event) {
    if (event) event.preventDefault();
    const isDark = document.documentElement.classList.toggle(\'admin-dark-theme\');
    document.body.classList.toggle(\'admin-dark-theme\', isDark);
    
    const theme = isDark ? \'dark\' : \'light\';
    localStorage.setItem(\'admin-theme\', theme);
    
    const iconEl = document.getElementById(\'admin-theme-icon\');
    const textEl = document.getElementById(\'admin-theme-text\');
    if (iconEl && textEl) {
        if (isDark) {
            iconEl.className = \'bi bi-sun-fill\';
            textEl.textContent = \'โหมดสว่าง\';
        } else {
            iconEl.className = \'bi bi-moon-fill\';
            textEl.textContent = \'โหมดมืด\';
        }
    }
    
    // Update charts dynamically on toggle
    updateChartsForTheme(theme);
}
</script>';

// Run replacements
$content = str_replace($search1, $replace1, $content);
$content = str_replace($search2, $replace2, $content);
$content = str_replace($search3, $replace3, $content);
$content = str_replace($search4, $replace4, $content);

// Save back
file_put_contents($file, $content);
echo "Sidebar reconstructed successfully!\n";
?>
