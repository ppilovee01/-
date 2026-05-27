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
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { 
            --blue-main: #AEE2FF; 
            --blue-hover: #7FB5FF; 
            --bg-soft: #f9f9f9; 
            --text-main: #2c2c2c;
        }
        body { font-family: 'Kanit', sans-serif; background-color: var(--bg-soft); color: var(--text-main); }
        
        /* Navbar Styling */
        .navbar { background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.03); padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
        .navbar-brand { font-weight: 800; color: #333 !important; font-size: 1.6rem; letter-spacing: -0.5px; margin-right: 20px; }
        .navbar-brand span { color: var(--blue-main); }
        
        .nav-link { font-weight: 500; color: #555 !important; margin: 0 10px; font-size: 1rem; position: relative; transition: color 0.2s; }
        .nav-link:hover, .nav-link.active { color: var(--blue-main) !important; }
        
        /* Search Form */
        .search-form { width: 100%; max-width: 400px; position: relative; }
        .search-input { background-color: #f8f9fa; border: 1px solid #eee; font-size: 0.9rem; padding-left: 15px; height: 40px; transition: 0.3s; }
        .search-input:focus { background-color: white; border-color: var(--blue-main); box-shadow: 0 0 0 3px rgba(174, 226, 255, 0.1); }
        .btn-search { position: absolute; right: 5px; top: 50%; transform: translateY(-50%); color: #999; border: none; background: transparent; padding: 5px 10px; }
        .btn-search:hover { color: var(--blue-main); }

        /* Icon Buttons */
        .icon-group { display: flex; align-items: center; gap: 10px; }
        .icon-btn { 
            width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; 
            border-radius: 50%; background: #fff; color: #444; border: 1px solid #eee; 
            transition: 0.2s; position: relative; text-decoration: none; font-size: 1.1rem;
        }
        .icon-btn:hover { background: var(--blue-main); color: white; border-color: var(--blue-main); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(174, 226, 255, 0.3); }
        
        /* Badge Count */
        .badge-count { 
            background-color: var(--blue-main); color: #444; 
            font-size: 0.6rem; font-weight: bold; 
            position: absolute; top: -5px; right: -5px; 
            min-width: 18px; height: 18px; 
            border-radius: 50%; border: 2px solid white; 
            display: flex; align-items: center; justify-content: center;
        }

        /* Buttons */
        .btn-auth { background: var(--blue-main); color: #444 !important; border-radius: 50px; padding: 8px 24px; font-weight: 500; text-decoration: none; box-shadow: 0 4px 12px rgba(174, 226, 255, 0.4); transition: 0.3s; font-size: 0.9rem; }
        .btn-auth:hover { background: var(--blue-hover); color: white !important; transform: translateY(-2px); box-shadow: 0 6px 18px rgba(174, 226, 255, 0.6); }

        .hidden { display: none !important; }
        
        /* Dropdown User */
        .dropdown-menu { border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-radius: 15px; padding: 10px; margin-top: 10px; }
        .dropdown-item { border-radius: 8px; padding: 8px 15px; font-size: 0.9rem; transition: 0.2s; }
        .dropdown-item:hover { background-color: #F0F8FF; color: var(--blue-main); }
        .dropdown-item.text-danger:hover { background-color: #fee2e2; color: #dc3545; }

        /* Mobile Responsive */
        @media (max-width: 991px) { 
            .search-form { max-width: 100%; margin: 15px 0; } 
            .nav-link { margin: 5px 0; padding: 10px 0; border-bottom: 1px solid #f8f8f8; }
            .icon-group { justify-content: center; margin-top: 15px; }
            .navbar-toggler { border: none; padding: 5px; }
            .navbar-toggler:focus { box-shadow: none; }
        }
    </style>
    
    <?php if(isset($extra_css)) echo $extra_css; ?>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top">
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
                <form action="index.php" method="GET" class="search-form">
                    <input class="form-control rounded-pill search-input" type="search" name="q" placeholder="ค้นหาสินค้าที่ต้องการ..." value="<?= isset($_GET['q']) ? $_GET['q'] : '' ?>">
                    <button type="submit" class="btn-search"><i class="bi bi-search"></i></button>
                </form>
            </div>

            <div class="d-flex flex-column flex-lg-row align-items-lg-center">
                <ul class="navbar-nav me-lg-3 text-center text-lg-start">
                    <li class="nav-item"><a class="nav-link" href="index.php">หน้าแรก</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#shop">สินค้าทั้งหมด</a></li>
                </ul>

                <div class="icon-group">
                    
                    <a class="icon-btn" href="wishlist.php" title="รายการโปรด">
                        <i class="bi bi-heart"></i>
                    </a>

                    <a class="icon-btn" href="cart.php" title="ตะกร้าสินค้า">
                        <i class="bi bi-bag"></i>
                        <span id="nav-cart-badge" class="badge-count <?= $cart_count > 0 ? '' : 'hidden' ?>"><?= $cart_count ?></span>
                    </a>

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
