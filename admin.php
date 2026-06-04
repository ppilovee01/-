<?php
session_start();
include 'db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
}

// --- ฟังก์ชันซิงค์สต๊อกและราคาจากล็อต (คำนวณ FIFO อัตโนมัติ) ---
function sync_product_stock($conn, $product_id) {
    $q_stock = mysqli_query($conn, "SELECT SUM(stock) as total_stock FROM product_lots WHERE product_id='$product_id' AND stock > 0");
    $tot = mysqli_fetch_assoc($q_stock)['total_stock'];
    if($tot == null) $tot = 0;
    
    $q_price = mysqli_query($conn, "SELECT price FROM product_lots WHERE product_id='$product_id' AND stock > 0 ORDER BY imported_at ASC LIMIT 1");
    $r_price = mysqli_fetch_assoc($q_price);
    
    if ($tot > 0 && $r_price) {
        $price = $r_price['price'];
        mysqli_query($conn, "UPDATE products SET stock='$tot', price='$price' WHERE id='$product_id'");
    } else {
        mysqli_query($conn, "UPDATE products SET stock=0 WHERE id='$product_id'");
    }
}

// --- Logic: เพิ่มล็อตสินค้าใหม่ (Quick Import) ---
if (isset($_POST['add_lot'])) {
    $pid = intval($_POST['lot_product_id']);
    $lot_num = mysqli_real_escape_string($conn, $_POST['lot_number'] ?? '');
    $lot_cost = !empty($_POST['lot_cost']) ? floatval($_POST['lot_cost']) : 0.00;
    $lot_price = floatval($_POST['lot_price'] ?? 0);
    $lot_stock = intval($_POST['lot_stock'] ?? 0);
    
    mysqli_query($conn, "INSERT INTO product_lots (product_id, lot_number, import_cost, price, stock, imported_at) VALUES ('$pid', '$lot_num', '$lot_cost', '$lot_price', '$lot_stock', NOW())");
    sync_product_stock($conn, $pid); 
    
    // get product name
    $p_q = mysqli_query($conn, "SELECT name FROM products WHERE id='$pid'");
    $p_name = mysqli_fetch_assoc($p_q)['name'] ?? 'ไม่พบชื่อสินค้า';
    
    log_admin_action($conn, 'นำเข้าล็อตสินค้า', [
        'title' => "นำเข้าล็อตสินค้าใหม่สำหรับสินค้า: $p_name (รหัส #$pid)",
        'sections' => [
            [
                'title' => 'ข้อมูลล็อตที่นำเข้า',
                'items' => [
                    "หมายเลขล็อต: $lot_num",
                    "ราคาทุน (Import Cost): ฿" . number_format($lot_cost, 2),
                    "ราคาขาย (Selling Price): ฿" . number_format($lot_price, 2),
                    "จำนวนนำเข้า (Quantity): " . number_format($lot_stock) . " ชิ้น"
                ]
            ]
        ]
    ]);
    
    $_SESSION['swal'] = ['title' => 'สำเร็จ', 'text' => 'นำเข้าล็อตสินค้าเรียบร้อย', 'icon' => 'success'];
    header("Location: admin.php"); exit();
}

// --- Logic: Save Product (Add & Edit) ---
if (isset($_POST['save_product'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $cat_id = empty($_POST['category_id']) ? '' : intval($_POST['category_id']);
    $cat_val = empty($cat_id) ? "NULL" : "'$cat_id'";
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $options = mysqli_real_escape_string($conn, $_POST['options']); 
    $image_path = mysqli_real_escape_string($conn, $_POST['old_image']); 
    
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
        $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array(strtolower($ext), $allowed)) {
            $_SESSION['swal'] = ['title' => 'ผิดพลาด', 'text' => 'รองรับเฉพาะไฟล์รูปภาพ (jpg, jpeg, png, gif, webp)', 'icon' => 'error'];
            header("Location: admin.php"); exit();
        }
        $new_name = "prod_" . uniqid() . "." . strtolower($ext);
        if (!is_dir("uploads")) mkdir("uploads");
        move_uploaded_file($_FILES['image_file']['tmp_name'], "uploads/" . $new_name);
        $image_path = "uploads/" . $new_name;
    }
    $image_path_esc = mysqli_real_escape_string($conn, $image_path);
    
    $new_cat_name = 'ไม่มีหมวดหมู่';
    if (!empty($cat_id)) {
        $nc_q = mysqli_query($conn, "SELECT name FROM categories WHERE id = '$cat_id'");
        $nc_row = mysqli_fetch_assoc($nc_q);
        if ($nc_row) $new_cat_name = $nc_row['name'];
    }

    if (!empty($_POST['id'])) {
        // --- กรณีแก้ไขสินค้า ---
        $id = intval($_POST['id']);
        
        // ดึงข้อมูลเดิมมาตรวจสอบส่วนต่าง
        $old_p_q = mysqli_query($conn, "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = '$id'");
        $old_prod = mysqli_fetch_assoc($old_p_q);
        
        $changes = [];
        if ($old_prod) {
            if ($old_prod['name'] !== $name) {
                $changes[] = ['field' => 'ชื่อสินค้า', 'old' => $old_prod['name'], 'new' => $name];
            }
            $old_cat_name = $old_prod['category_name'] ?? 'ไม่มีหมวดหมู่';
            if ($old_cat_name !== $new_cat_name) {
                $changes[] = ['field' => 'หมวดหมู่', 'old' => $old_cat_name, 'new' => $new_cat_name];
            }
            if ($old_prod['description'] !== $desc) {
                $changes[] = ['field' => 'คำอธิบายสินค้า', 'old' => mb_strimwidth($old_prod['description'], 0, 50, '...'), 'new' => mb_strimwidth($desc, 0, 50, '...')];
            }
            if ($old_prod['options'] !== $options) {
                $changes[] = ['field' => 'ตัวเลือกสินค้า (สี/ไซส์)', 'old' => $old_prod['options'], 'new' => $options];
            }
            if ($old_prod['image'] !== $image_path) {
                $changes[] = ['field' => 'รูปภาพสินค้า', 'old' => $old_prod['image'], 'new' => $image_path];
            }
        }
        
        $sql = "UPDATE products SET name='$name', category_id=$cat_val, description='$desc', image='$image_path_esc', options='$options' WHERE id='$id'";
        mysqli_query($conn, $sql);
        
        $lot_log_items = [];
        
        // 1. ประมวลผลลบล็อตที่สั่งลบ
        if (!empty($_POST['delete_lots'])) {
            foreach ($_POST['delete_lots'] as $del_lot_id) {
                $del_lot_id = intval($del_lot_id);
                $dl_q = mysqli_query($conn, "SELECT lot_number, stock FROM product_lots WHERE id='$del_lot_id' AND product_id='$id'");
                $dl_row = mysqli_fetch_assoc($dl_q);
                if ($dl_row) {
                    $lot_log_items[] = "ลบล็อต " . ($dl_row['lot_number'] ?: "#$del_lot_id") . " (เหลืออยู่: " . $dl_row['stock'] . " ชิ้น)";
                }
                mysqli_query($conn, "DELETE FROM product_lots WHERE id='$del_lot_id' AND product_id='$id'");
            }
        }
        
        // 2. อัปเดตข้อมูลล็อตเดิมที่ถูกแก้ไข
        if (!empty($_POST['lot'])) {
            foreach ($_POST['lot'] as $lot_id => $lot_data) {
                $lot_id = intval($lot_id);
                $lot_num = mysqli_real_escape_string($conn, $lot_data['lot_number'] ?? '');
                $lot_cost = floatval($lot_data['import_cost'] ?? 0);
                $lot_price = floatval($lot_data['price'] ?? 0);
                $lot_stock = intval($lot_data['stock'] ?? 0);
                
                $ol_q = mysqli_query($conn, "SELECT * FROM product_lots WHERE id='$lot_id' AND product_id='$id'");
                $ol_row = mysqli_fetch_assoc($ol_q);
                if ($ol_row) {
                    $lot_changes = [];
                    if ($ol_row['lot_number'] !== $lot_num) {
                        $lot_changes[] = "เปลี่ยนเลขล็อต: " . $ol_row['lot_number'] . " -> " . $lot_num;
                    }
                    if (floatval($ol_row['import_cost']) !== $lot_cost) {
                        $lot_changes[] = "ต้นทุน: ฿" . $ol_row['import_cost'] . " -> ฿" . $lot_cost;
                    }
                    if (floatval($ol_row['price']) !== $lot_price) {
                        $lot_changes[] = "ราคาขาย: ฿" . $ol_row['price'] . " -> ฿" . $lot_price;
                    }
                    if (intval($ol_row['stock']) !== $lot_stock) {
                        $lot_changes[] = "จำนวน: " . $ol_row['stock'] . " -> " . $lot_stock;
                    }
                    
                    if (count($lot_changes) > 0) {
                        $lot_log_items[] = "แก้ไขล็อต " . ($ol_row['lot_number'] ?: "#$lot_id") . ": " . implode(", ", $lot_changes);
                    }
                }
                
                mysqli_query($conn, "UPDATE product_lots SET lot_number='$lot_num', import_cost='$lot_cost', price='$lot_price', stock='$lot_stock' WHERE id='$lot_id' AND product_id='$id'");
            }
        }
        
        // 3. เพิ่มล็อตใหม่
        if (!empty($_POST['new_lot'])) {
            foreach ($_POST['new_lot'] as $lot_data) {
                $lot_num = mysqli_real_escape_string($conn, $lot_data['lot_number'] ?? '');
                $lot_cost = floatval($lot_data['import_cost'] ?? 0);
                $lot_price = floatval($lot_data['price'] ?? 0);
                $lot_stock = intval($lot_data['stock'] ?? 0);
                
                $lot_log_items[] = "เพิ่มล็อตใหม่ " . ($lot_num ?: "ไม่ระบุเลข") . ": ต้นทุน = ฿$lot_cost, ราคาขาย = ฿$lot_price, จำนวน = $lot_stock ชิ้น";
                
                mysqli_query($conn, "INSERT INTO product_lots (product_id, lot_number, import_cost, price, stock, imported_at) VALUES ('$id', '$lot_num', '$lot_cost', '$lot_price', '$lot_stock', NOW())");
            }
        }
        
        // ซิงค์สต๊อกและราคากลาง FIFO
        sync_product_stock($conn, $id);
        
        $sections = [];
        if (count($lot_log_items) > 0) {
            $sections[] = [
                'title' => 'ความเปลี่ยนแปลงของล็อตย่อย',
                'items' => $lot_log_items
            ];
        }
        
        log_admin_action($conn, 'แก้ไขสินค้าและล็อต', [
            'title' => "แก้ไขข้อมูลสินค้า '$name' (รหัส #$id)",
            'changes' => $changes,
            'sections' => $sections
        ]);
        
        $action_text = "อัปเดตข้อมูล";
    } else {
        // --- กรณีเพิ่มสินค้าใหม่ ---
        $sql = "INSERT INTO products (name, price, stock, category_id, image, description, options) VALUES ('$name', 0, 0, $cat_val, '$image_path_esc', '$desc', '$options')";
        mysqli_query($conn, $sql);
        $new_id = mysqli_insert_id($conn);
        
        $lot_log_items = [];
        // บันทึกล็อตทั้งหมดที่ส่งมาจากตาราง
        if (!empty($_POST['new_lot'])) {
            foreach ($_POST['new_lot'] as $lot_data) {
                $lot_num = mysqli_real_escape_string($conn, $lot_data['lot_number'] ?? '');
                $lot_cost = floatval($lot_data['import_cost'] ?? 0);
                $lot_price = floatval($lot_data['price'] ?? 0);
                $lot_stock = intval($lot_data['stock'] ?? 0);
                
                $lot_log_items[] = "เลขล็อต = $lot_num, ต้นทุน = ฿$lot_cost, ราคาขาย = ฿$lot_price, จำนวน = $lot_stock ชิ้น";
                
                mysqli_query($conn, "INSERT INTO product_lots (product_id, lot_number, import_cost, price, stock, imported_at) VALUES ('$new_id', '$lot_num', '$lot_cost', '$lot_price', '$lot_stock', NOW())");
            }
        } else {
            $lot_log_items[] = "เลขล็อต = LOT-001, ต้นทุน = ฿0.00, ราคาขาย = ฿0.00, จำนวน = 0 (คลังเริ่มต้น)";
            mysqli_query($conn, "INSERT INTO product_lots (product_id, lot_number, import_cost, price, stock, imported_at) VALUES ('$new_id', 'LOT-001', 0.00, 0.00, 0, NOW())");
        }
        
        // ซิงค์ราคาขายและสต๊อกจริง FIFO
        sync_product_stock($conn, $new_id);
        
        log_admin_action($conn, 'เพิ่มสินค้า', [
            'title' => "เพิ่มสินค้าใหม่ '$name' (รหัส #$new_id)",
            'changes' => [
                ['field' => 'ชื่อสินค้า', 'old' => '-', 'new' => $name],
                ['field' => 'หมวดหมู่', 'old' => '-', 'new' => $new_cat_name],
                ['field' => 'คำอธิบายสินค้า', 'old' => '-', 'new' => mb_strimwidth($desc, 0, 50, '...')],
                ['field' => 'ตัวเลือกสินค้า', 'old' => '-', 'new' => $options]
            ],
            'sections' => [
                [
                    'title' => 'คลังสินค้าเริ่มต้นที่บันทึก',
                    'items' => $lot_log_items
                ]
            ]
        ]);
        
        $action_text = "เพิ่มสินค้า";
    }
    
    $_SESSION['swal'] = ['title' => 'สำเร็จ', 'text' => "$action_text เรียบร้อย", 'icon' => 'success'];
    header("Location: admin.php"); exit();
}

// --- Logic: ลบสินค้า ---
if (isset($_GET['delete'])) { 
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
    $del_id = intval($_GET['delete']);
    $p_q = mysqli_query($conn, "SELECT name FROM products WHERE id='$del_id'");
    $p_name = mysqli_fetch_assoc($p_q)['name'] ?? 'ไม่พบชื่อสินค้า';
    mysqli_query($conn, "DELETE FROM product_lots WHERE product_id = '$del_id'");
    mysqli_query($conn, "DELETE FROM products WHERE id = '$del_id'"); 
    
    log_admin_action($conn, 'ลบสินค้า', [
        'title' => "ลบสินค้า '$p_name' (รหัส #$del_id)",
        'sections' => [
            [
                'title' => 'รายละเอียดการดำเนินการ',
                'items' => [
                    "ลบสินค้าชื่อ: $p_name",
                    "รหัสสินค้า: #$del_id",
                    "ประวัติคลังสินค้าล็อตย่อยทั้งหมดถูกลบออกจากระบบ"
                ]
            ]
        ]
    ]);
    
    header("Location: admin.php"); exit(); 
}

// --- Logic: ลบล็อตย่อย (จากประวัติรับเข้าแมนนวล) ---
if (isset($_GET['delete_lot']) && isset($_GET['pid'])) {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
    $del_lot = intval($_GET['delete_lot']);
    $pid = intval($_GET['pid']);
    $l_q = mysqli_query($conn, "SELECT l.lot_number, p.name FROM product_lots l JOIN products p ON l.product_id = p.id WHERE l.id = '$del_lot'");
    $l_info = mysqli_fetch_assoc($l_q);
    $lot_number = $l_info['lot_number'] ?? 'ไม่ระบุ';
    $p_name = $l_info['name'] ?? 'ไม่พบชื่อสินค้า';
    mysqli_query($conn, "DELETE FROM product_lots WHERE id = '$del_lot'");
    sync_product_stock($conn, $pid);
    
    log_admin_action($conn, 'ลบล็อตย่อย', [
        'title' => "ลบล็อตย่อย '$lot_number' ของสินค้า '$p_name'",
        'sections' => [
            [
                'title' => 'รายละเอียดล็อตที่ลบ',
                'items' => [
                    "รหัสสินค้า: #$pid",
                    "ชื่อสินค้า: $p_name",
                    "รหัสล็อตสินค้า: #$del_lot",
                    "หมายเลขล็อต: $lot_number"
                ]
            ]
        ]
    ]);
    
    $_SESSION['swal'] = ['title' => 'สำเร็จ', 'text' => "ลบล็อตสินค้าย่อยเรียบร้อย", 'icon' => 'success'];
    header("Location: admin.php"); exit();
}

$edit_data = null;
$edit_lots = [];
if (isset($_GET['edit'])) { 
    $edit_id = intval($_GET['edit']);
    $edit_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM products WHERE id='$edit_id'")); 
    if ($edit_data) {
        $lots_q = mysqli_query($conn, "SELECT * FROM product_lots WHERE product_id='$edit_id' ORDER BY imported_at ASC");
        while ($l = mysqli_fetch_assoc($lots_q)) {
            $edit_lots[] = $l;
        }
    }
}

// ดึงข้อมูลสินค้าทั้งหมดเก็บใส่ Array เตรียมไว้
$products_list = [];
$sql = "SELECT p.*, c.name as cat_name, (SELECT COUNT(*) FROM product_lots WHERE product_id = p.id AND stock > 0) as active_lots FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC"; 
$res = mysqli_query($conn, $sql); 
if($res) {
    while($row = mysqli_fetch_assoc($res)) {
        $products_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสินค้า | Por Mae Bet Taled Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style> 
        body { font-family: 'Kanit'; background: #f8f9fa; } 
        .btn-main { background-color: #85D1FF; color: white; border: none; transition: all 0.2s ease-in-out; }
        .btn-main:hover { background-color: #6BBEFF; color: white; transform: translateY(-1px); }
        .option-row { background: #f9f9f9; padding: 10px; border-radius: 8px; border: 1px solid #eee; margin-bottom: 10px; }
        
        .pulse-dot {
            width: 8px; height: 8px;
            background-color: #ef4444;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(0.9); opacity: 1; box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1); opacity: 0.8; box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
            100% { transform: scale(0.9); opacity: 1; box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        .tr-hover:hover { background-color: #f8faff !important; }
        .img-thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 12px; border: 1px solid #eee; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        
        /* Modal Custom Styling */
        .custom-modal-header {
            background: linear-gradient(135deg, #f5f9ff 0%, #eef5ff 100%);
            border-bottom: 1px solid #e2e8f0;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
        }
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 15px 30px rgba(0,0,0,0.08);
        }

        /* ======== Mobile Responsive CSS ======== */
        @media (max-width: 991px) {
            .table-responsive { border: none !important; }
            .table-responsive table { width: 100%; display: block; }
            .table-responsive thead { display: none; }
            .table-responsive tbody { display: block; width: 100%; }
            .table-responsive tr { 
                display: block; 
                background: #fff; 
                border: 1px solid #eaeaea; 
                border-radius: 16px; 
                margin-bottom: 15px; 
                padding: 15px; 
                box-shadow: 0 4px 10px rgba(0,0,0,0.02); 
            }
            .table-responsive td { 
                display: block; 
                width: 100%; 
                border: none !important; 
                padding: 6px 0 !important; 
                text-align: left !important; 
            }
            .td-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px dashed #eee;
            }
            .td-actions > button, .td-actions > a {
                flex: 1;
                justify-content: center;
                margin: 0 !important;
            }
            .btn-circle-mobile {
                flex-grow: 0 !important;
                width: 40px; height: 40px;
                display: flex; align-items: center; justify-content: center;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Menu -->
        <div class="col-md-2 p-0 border-end bg-white">
            <button class="btn btn-light w-100 d-md-none border-bottom p-3 fw-bold text-primary text-start" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
                <i class="bi bi-list me-2"></i> เมนูจัดการ
            </button>
            <div class="collapse d-md-block" id="sidebarMenu">
                <div style="min-height: 100vh;">
                    <?php include 'admin_sidebar.php'; ?>
                </div>
            </div>
        </div>

        <!-- Main Workspace -->
        <div class="col-md-10 p-3 p-md-5">
            <h3 class="fw-bold mb-4 d-none d-md-block">📦 จัดการสินค้าและสต๊อก (FIFO)</h3>
            
            <!-- Control & Search Panel -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 bg-white p-3 rounded-4 shadow-sm border">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <button type="button" onclick="openAddProductModal()" class="btn btn-main rounded-pill px-4 fw-bold shadow-sm py-2">
                        <i class="bi bi-plus-circle-fill me-1"></i> เพิ่มสินค้าใหม่
                    </button>
                </div>
                <div class="d-flex flex-column flex-sm-row gap-2 align-items-stretch align-items-sm-center flex-grow-1 flex-md-grow-0" style="max-width: 600px;">
                    <div class="input-group shadow-sm rounded-3 overflow-hidden">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="productSearchInput" class="form-control bg-white border-start-0 py-2" placeholder="ค้นหาชื่อสินค้า..." onkeyup="filterProducts()">
                    </div>
                    <select id="categoryFilterSelect" class="form-select bg-white py-2 shadow-sm rounded-3" onchange="filterProducts()" style="min-width: 160px;">
                        <option value="">ทุกหมวดหมู่</option>
                        <?php 
                        $cat_q = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");
                        while($c = mysqli_fetch_assoc($cat_q)) {
                            echo "<option value='{$c['name']}'>{$c['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <!-- Product Grid/Table -->
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border">
                <div class="table-responsive">
                    <table class="table align-middle table-hover m-0">
                        <thead class="bg-light text-secondary small rounded-3">
                            <tr>
                                <th class="ps-3 py-3" style="width: 45%;">สินค้า</th>
                                <th class="py-3" style="width: 30%;">ราคาและสต๊อก</th>
                                <th class="text-end pe-3 py-3" style="width: 25%;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="product-list-tbody">
                            <?php foreach($products_list as $row): 
                                $hl = ($edit_data && $edit_data['id'] == $row['id']) ? 'table-warning' : ''; 
                            ?>
                            <tr class="product-row tr-hover <?= $hl ?>" data-name="<?= htmlspecialchars(strtolower($row['name']), ENT_QUOTES, 'UTF-8') ?>" data-category="<?= htmlspecialchars($row['cat_name'] ?: '', ENT_QUOTES, 'UTF-8') ?>">
                                <td class="ps-lg-3">
                                    <div class="d-flex align-items-center">
                                        <img src="<?= $row['image'] ?>" class="img-thumb me-3">
                                        <div>
                                            <div class="fw-bold text-dark text-truncate" style="max-width: 350px; font-size: 1.05rem;"><?= $row['name'] ?></div>
                                            <small class="badge bg-light text-secondary border mt-1"><i class="bi bi-tag-fill me-1 opacity-50"></i><?= $row['cat_name'] ?: 'ไม่มีหมวดหมู่' ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-row flex-lg-column align-items-center align-items-lg-start justify-content-between mt-2 mt-lg-0">
                                        <div>
                                            <span class="fw-bold text-dark fs-5">฿<?= number_format($row['price']) ?></span><br class="d-none d-lg-block">
                                            
                                            <?php if($row['stock'] <= 0): ?>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle py-1 px-2.5 rounded-pill d-inline-flex align-items-center gap-1 mt-1">
                                                    <i class="bi bi-exclamation-circle-fill"></i> หมดสต๊อก
                                                </span>
                                            <?php elseif($row['stock'] <= 5): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-1 px-2.5 rounded-pill d-inline-flex align-items-center gap-1 mt-1">
                                                    <span class="pulse-dot"></span> เหลือเพียง <?= number_format($row['stock']) ?> ชิ้น
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2.5 rounded-pill d-inline-flex align-items-center gap-1 mt-1">
                                                    พร้อมส่ง: <?= number_format($row['stock']) ?> ชิ้น
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="mt-lg-2">
                                            <button type="button" class="btn btn-sm btn-outline-info rounded-pill py-1 px-3 w-100 w-lg-auto" style="font-size: 0.8rem;" data-bs-toggle="modal" data-bs-target="#viewLotsModal<?= $row['id'] ?>">
                                                <i class="bi bi-clock-history"></i> ประวัติรับเข้า (<?= $row['active_lots'] ?> ล็อต)
                                            </button>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end pe-lg-3 td-actions">
                                    <button type="button" onclick="openLotModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>')" class="btn btn-sm btn-primary rounded-pill mb-lg-2 w-100 fw-bold shadow-sm py-2" style="font-size:0.8rem; background-color: #85D1FF; border:none;">
                                        <i class="bi bi-box-arrow-in-down"></i> นำเข้าล็อต
                                    </button>
                                    <a href="?edit=<?= $row['id'] ?>" class="btn btn-sm btn-light text-primary rounded-circle shadow-sm btn-circle-mobile ms-lg-1"><i class="bi bi-pencil-fill"></i></a>
                                    <button onclick="confirmDelete(<?= $row['id'] ?>)" class="btn btn-sm btn-light text-danger rounded-circle shadow-sm btn-circle-mobile ms-2 ms-lg-1"><i class="bi bi-trash-fill"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if(empty($products_list)): ?>
                                <tr id="no-products-row"><td colspan="3" class="text-center text-muted py-5 bg-white rounded-4">ยังไม่มีสินค้าในระบบ</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- Unified Product Add / Edit Modal -->
<!-- ========================================== -->
<div class="modal fade" id="productFormModal" tabindex="-1" aria-labelledby="productFormModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header custom-modal-header py-3 px-4">
                <h5 class="modal-title fw-bold" id="productFormModalLabel">
                    <i class="bi bi-plus-circle-fill text-success"></i> เพิ่มสินค้าใหม่
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="cancelEditRedirect()"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" onsubmit="prepareOptionsBeforeSubmit()">
                <?= get_csrf_input() ?>
                <input type="hidden" name="id" id="product-id-input" value="<?= $edit_data['id'] ?? '' ?>">
                <input type="hidden" name="old_image" id="old-image-input" value="<?= $edit_data['image'] ?? '' ?>">
                
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <!-- Left Column: General Info -->
                        <div class="col-lg-6 border-end">
                            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-info-circle-fill"></i> ข้อมูลสินค้าทั่วไป</h6>
                            
                            <div class="mb-3">
                                <label class="fw-bold small text-muted mb-1">ชื่อสินค้า</label>
                                <input type="text" name="name" id="product-name-input" class="form-control bg-light border-0" value="<?= htmlspecialchars($edit_data['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="fw-bold small text-muted mb-1">หมวดหมู่</label>
                                    <select name="category_id" id="product-category-select" class="form-select bg-light border-0">
                                        <option value="">-- เลือกหมวดหมู่ --</option>
                                        <?php 
                                        $cat_q = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");
                                        while($c = mysqli_fetch_assoc($cat_q)):
                                            $sel = ($edit_data && $edit_data['category_id'] == $c['id']) ? 'selected' : '';
                                            echo "<option value='{$c['id']}' $sel>{$c['name']}</option>";
                                        endwhile; 
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="fw-bold small text-muted mb-1">รูปภาพสินค้า</label>
                                    <input type="file" name="image_file" id="product-image-file" class="form-control bg-light border-0" accept="image/*" onchange="previewProductImage(event)">
                                </div>
                            </div>
                            
                            <div class="mb-3 text-center">
                                <img id="product-image-preview" src="" class="rounded-3 shadow-sm bg-white mx-auto border" style="max-height: 120px; display: none; object-fit: contain; padding: 5px;">
                            </div>

                            <div class="mb-3">
                                <label class="fw-bold small text-muted mb-1">รายละเอียดสินค้า</label>
                                <textarea name="description" id="product-description-textarea" class="form-control bg-light border-0" rows="4"><?= htmlspecialchars($edit_data['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                            <div class="p-3 border rounded-4 bg-light">
                                <label class="fw-bold small text-primary mb-2 d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-sliders"></i> ตัวเลือกสินค้า (Options)</span>
                                    <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-2 rounded" style="font-size:0.75rem;" onclick="addOptionRow()"><i class="bi bi-plus"></i> เพิ่มตัวเลือก</button>
                                </label>
                                <div id="option-container" style="max-height: 140px; overflow-y: auto;"></div>
                                <input type="hidden" name="options" id="real_options_input" value="<?= htmlspecialchars($edit_data['options'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>

                        <!-- Right Column: FIFO Inventory Lots -->
                        <div class="col-lg-6">
                            <h6 class="fw-bold text-primary mb-1"><i class="bi bi-boxes"></i> จัดการคลังสินค้าและล็อตสินค้า (FIFO)</h6>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted"><i class="bi bi-info-circle me-1"></i> ล็อตย่อยจะคำนวณ FIFO อัตโนมัติเมื่อสั่งซื้อ</small>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded" style="font-size:0.75rem; display:none;" id="globalPriceBtn" onclick="applyGlobalPrice()">
                                    <i class="bi bi-currency-dollar"></i> ปรับราคาทุกล็อตเท่ากัน
                                </button>
                            </div>
                            
                            <div class="table-responsive border rounded-3 bg-white" style="max-height: 380px; overflow-y: auto;">
                                <table class="table table-sm align-middle table-bordered small text-center m-0">
                                    <thead class="bg-light text-secondary sticky-top">
                                        <tr>
                                            <th>เลขล็อต</th>
                                            <th style="width: 100px;">ต้นทุน (บาท)</th>
                                            <th style="width: 100px;">ราคาขาย (บาท)</th>
                                            <th style="width: 80px;">จำนวน</th>
                                            <th style="width: 140px;">อัตรากำไร</th>
                                            <th style="width: 40px;">ลบ</th>
                                        </tr>
                                    </thead>
                                    <tbody id="lots-edit-container">
                                        <?php 
                                        if ($edit_data): 
                                            foreach ($edit_lots as $lot): 
                                                $profit = $lot['price'] - $lot['import_cost'];
                                                $margin = $lot['price'] > 0 ? round(($profit / $lot['price']) * 100, 1) : 0;
                                                $margin_color = $profit >= 0 ? 'text-success' : 'text-danger';
                                        ?>
                                        <tr class="lot-row">
                                            <td>
                                                <input type="hidden" name="lot[<?= $lot['id'] ?>][id]" value="<?= $lot['id'] ?>">
                                                <input type="text" name="lot[<?= $lot['id'] ?>][lot_number]" class="form-control form-control-sm text-center border-0 fw-bold font-monospace" value="<?= htmlspecialchars($lot['lot_number'], ENT_QUOTES, 'UTF-8') ?>" required>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" name="lot[<?= $lot['id'] ?>][import_cost]" class="form-control form-control-sm text-end lot-cost border-0 bg-transparent" value="<?= htmlspecialchars($lot['import_cost'], ENT_QUOTES, 'UTF-8') ?>" onkeyup="calcRowMargin(this)" required>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" name="lot[<?= $lot['id'] ?>][price]" class="form-control form-control-sm text-end lot-price border-0 bg-transparent fw-bold text-primary" value="<?= htmlspecialchars($lot['price'], ENT_QUOTES, 'UTF-8') ?>" onkeyup="calcRowMargin(this)" required>
                                            </td>
                                            <td>
                                                <input type="number" name="lot[<?= $lot['id'] ?>][stock]" class="form-control form-control-sm text-center border-0" value="<?= htmlspecialchars($lot['stock'], ENT_QUOTES, 'UTF-8') ?>" required>
                                            </td>
                                            <td class="text-center font-monospace align-middle">
                                                <span class="margin-badge fw-bold <?= $margin_color ?>" style="font-size: 0.75rem;">฿<?= number_format($profit, 2) ?><br>(<?= $margin ?>%)</span>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm text-danger border-0" onclick="deleteLotRow(this, <?= $lot['id'] ?>)"><i class="bi bi-trash"></i></button>
                                            </td>
                                        </tr>
                                        <?php 
                                            endforeach; 
                                        endif; 
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <button type="button" class="btn btn-outline-primary btn-sm w-100 mt-2 bg-white rounded-pill fw-bold border-dashed" onclick="addNewLotRow()">
                                <i class="bi bi-plus-circle-fill me-1"></i> นำเข้าล็อตสินค้าเพิ่มเติม
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-top-0 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal" onclick="cancelEditRedirect()">ยกเลิก</button>
                    <button type="submit" name="save_product" class="btn btn-main rounded-pill px-5 fw-bold py-2 shadow-sm">บันทึกข้อมูลสินค้า</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- Lot Viewers Modal (ReadOnly Lots List) -->
<!-- ========================================== -->
<?php foreach($products_list as $row): ?>
<div class="modal fade" id="viewLotsModal<?= $row['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">🕒 ประวัติการรับเข้าสินค้า (Lots)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center mb-3 p-3 bg-light rounded-3">
                    <img src="<?= $row['image'] ?>" class="rounded-3 me-3" style="width:50px; height:50px; object-fit:cover;">
                    <h6 class="text-primary fw-bold mb-0"><?= $row['name'] ?></h6>
                </div>
                
                <div class="table-responsive border rounded-3 bg-white">
                    <table class="table table-bordered align-middle small text-center m-0">
                        <thead class="bg-light">
                            <tr>
                                <th># ล็อตที่</th>
                                <th>เลขล็อต</th>
                                <th>วันที่นำเข้า</th>
                                <th>ต้นทุนนำเข้า</th>
                                <th>ราคาขายตั้งไว้</th>
                                <th>กำไร/ชิ้น</th>
                                <th>คงเหลือ</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $lots_q = mysqli_query($conn, "SELECT * FROM product_lots WHERE product_id = '{$row['id']}' ORDER BY imported_at ASC");
                            $lot_num = 1;
                            if($lots_q && mysqli_num_rows($lots_q) > 0):
                                while($lot = mysqli_fetch_assoc($lots_q)):
                                    $is_empty = ($lot['stock'] <= 0);
                            ?>
                            <tr class="<?= $is_empty ? 'table-secondary text-muted' : '' ?>">
                                <td><?= $lot_num++ ?></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($lot['lot_number'] ?: '-') ?></td>
                                <td class="small"><?= date('d/m/Y H:i', strtotime($lot['imported_at'])) ?> น.</td>
                                <td class="text-danger">฿<?= number_format($lot['import_cost'], 2) ?></td>
                                <td class="text-success fw-bold">฿<?= number_format($lot['price'], 2) ?></td>
                                <td class="fw-bold">
                                    <?php 
                                    $profit = $lot['price'] - $lot['import_cost'];
                                    $prof_color = $profit >= 0 ? 'text-primary' : 'text-danger';
                                    echo "<span class='{$prof_color}'>" . ($profit >= 0 ? '+' : '') . '฿' . number_format($profit, 2) . "</span>";
                                    ?>
                                </td>
                                <td>
                                    <?php if($is_empty): ?>
                                        <span class="badge bg-secondary">หมดแล้ว</span>
                                    <?php else: ?>
                                        <span class="fw-bold text-success fs-6"><?= $lot['stock'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?delete_lot=<?= $lot['id'] ?>&pid=<?= $row['id'] ?>&csrf_token=<?= get_csrf_token() ?>" class="btn btn-sm btn-outline-danger py-1 px-2 rounded-3" onclick="return confirm('ต้องการลบล็อตนี้ทิ้งใช่หรือไม่? (สต๊อกจะหายไปด้วย)')"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="8" class="py-4 text-muted">ไม่พบข้อมูลล็อตสินค้า</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- ========================================== -->
<!-- Quick Lot Import Modal -->
<!-- ========================================== -->
<div class="modal fade" id="addLotModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-in-down text-primary"></i> นำเข้าสินค้าล็อตใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= get_csrf_input() ?>
                <div class="modal-body p-4">
                    <input type="hidden" name="lot_product_id" id="lot_product_id">
                    <div class="mb-4 text-center p-3 bg-light rounded-3 border">
                        <h6 class="fw-bold text-primary mb-1" id="lot_product_name">ชื่อสินค้า</h6>
                        <small class="text-muted"><i class="bi bi-info-circle me-1"></i> ระบบจะรับเข้าคลังตามแบบจำลองเวลา FIFO</small>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="small text-muted fw-bold mb-1">เลขล็อตสินค้า</label>
                            <input type="text" name="lot_number" class="form-control bg-light" placeholder="เช่น LOT-002" required>
                        </div>
                        <div class="col-6">
                            <label class="small text-muted fw-bold mb-1">ต้นทุนนำเข้า (บาท/ชิ้น)</label>
                            <input type="number" step="0.01" name="lot_cost" class="form-control bg-light" placeholder="เช่น 100" required>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="small text-muted fw-bold mb-1">ราคาขายล็อตนี้ (บาท)</label>
                            <input type="number" name="lot_price" class="form-control bg-light" placeholder="เช่น 150" required>
                        </div>
                        <div class="col-6">
                            <label class="small text-muted fw-bold mb-1">จำนวนที่นำเข้า (ชิ้น)</label>
                            <input type="number" name="lot_stock" class="form-control bg-light" placeholder="เช่น 50" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="submit" name="add_lot" class="btn btn-primary w-100 rounded-pill fw-bold py-2 shadow-sm" style="background-color: #85D1FF; border:none; font-size: 1rem;">
                        บันทึกล็อตใหม่เข้าระบบ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let newLotCount = 0;
    
    // คัดกรองและค้นหาตารางสินค้าแบบเรียลไทม์
    function filterProducts() {
        const query = document.getElementById('productSearchInput').value.toLowerCase().trim();
        const category = document.getElementById('categoryFilterSelect').value;
        const rows = document.querySelectorAll('.product-row');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const name = row.getAttribute('data-name');
            const cat = row.getAttribute('data-category');
            
            const matchesQuery = name.includes(query);
            const matchesCategory = category === '' || cat === category;
            
            if (matchesQuery && matchesCategory) {
                row.style.setProperty('display', '', 'important');
                visibleCount++;
            } else {
                row.style.setProperty('display', 'none', 'important');
            }
        });

        // ซ่อนข้อความไม่มีสินค้าถ้ายังมีสินค้าที่ค้นเจอ
        const noProductRow = document.getElementById('no-products-row');
        if (noProductRow) {
            if (visibleCount === 0) {
                noProductRow.style.setProperty('display', '', 'important');
            } else {
                noProductRow.style.setProperty('display', 'none', 'important');
            }
        }
    }

    // เปิด Modal เพิ่มสินค้า (Clear ข้อมูลเดิม)
    function openAddProductModal() {
        document.getElementById('product-id-input').value = '';
        document.getElementById('old-image-input').value = '';
        document.getElementById('product-name-input').value = '';
        document.getElementById('product-category-select').value = '';
        document.getElementById('product-description-textarea').value = '';
        document.getElementById('product-image-file').value = '';
        
        const preview = document.getElementById('product-image-preview');
        preview.src = '';
        preview.style.display = 'none';

        // เคลียร์ Option และแอดแถวใหม่
        document.getElementById('option-container').innerHTML = '';
        document.getElementById('real_options_input').value = '';
        
        // เคลียร์ ล็อต
        document.getElementById('lots-edit-container').innerHTML = '';
        document.getElementById('globalPriceBtn').style.display = 'none';
        newLotCount = 0;
        
        // เพิ่มล็อตตั้งต้น 1 ล็อตสำหรับเพิ่มสินค้าใหม่
        addNewLotRow();

        // เปลี่ยนหัวข้อ Modal
        document.getElementById('productFormModalLabel').innerHTML = '<i class="bi bi-plus-circle-fill text-success"></i> เพิ่มสินค้าใหม่';
        
        // เปิด Modal
        const productModal = new bootstrap.Modal(document.getElementById('productFormModal'));
        productModal.show();
    }

    // แอดแถวล็อตสินค้าใหม่
    function addNewLotRow() {
        const container = document.getElementById('lots-edit-container');
        const tr = document.createElement('tr');
        tr.className = 'lot-row';
        tr.innerHTML = `
            <td>
                <input type="text" name="new_lot[${newLotCount}][lot_number]" class="form-control form-control-sm text-center border-0 font-monospace" placeholder="LOT-001" required>
            </td>
            <td>
                <input type="number" step="0.01" name="new_lot[${newLotCount}][import_cost]" class="form-control form-control-sm text-end lot-cost border-0 bg-transparent" placeholder="0.00" onkeyup="calcRowMargin(this)" required>
            </td>
            <td>
                <input type="number" step="0.01" name="new_lot[${newLotCount}][price]" class="form-control form-control-sm text-end lot-price border-0 bg-transparent fw-bold text-primary" placeholder="0.00" onkeyup="calcRowMargin(this)" required>
            </td>
            <td>
                <input type="number" name="new_lot[${newLotCount}][stock]" class="form-control form-control-sm text-center border-0" placeholder="0" required>
            </td>
            <td class="text-center font-monospace align-middle">
                <span class="margin-badge fw-bold text-muted" style="font-size:0.75rem;">-</span>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm text-danger border-0" onclick="this.parentElement.parentElement.remove(); checkGlobalPriceBtn();"><i class="bi bi-trash"></i></button>
            </td>
        `;
        container.appendChild(tr);
        newLotCount++;
        checkGlobalPriceBtn();
    }

    // ลบแถวล็อตเดิม (และบันทึกลง delete_lots[])
    function deleteLotRow(btn, lotId) {
        Swal.fire({
            title: 'ยืนยันลบล็อต?',
            text: 'ยอดสต๊อกล็อตย่อยนี้จะถูกลบถาวรเมื่อกดยืนยันบันทึกข้อมูลสินค้า',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'ลบเลย',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = btn.closest('form');
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_lots[]';
                input.value = lotId;
                form.appendChild(input);
                
                btn.closest('tr').remove();
                checkGlobalPriceBtn();
            }
        });
    }

    // ตรวจสอบว่าจะแสดงปุ่มปรับราคาทุกแถวเท่ากันหรือไม่
    function checkGlobalPriceBtn() {
        const rows = document.querySelectorAll('#lots-edit-container tr');
        const btn = document.getElementById('globalPriceBtn');
        if (rows.length > 1) {
            btn.style.display = 'block';
        } else {
            btn.style.display = 'none';
        }
    }

    // คำนวณมาร์จินกำไรสำหรับแต่ละแถว
    function calcRowMargin(input) {
        const row = input.closest('tr');
        const costInput = row.querySelector('.lot-cost');
        const priceInput = row.querySelector('.lot-price');
        const badge = row.querySelector('.margin-badge');
        
        if (!costInput || !priceInput || !badge) return;
        
        const cost = parseFloat(costInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        const profit = price - cost;
        const margin = price > 0 ? ((profit / price) * 100).toFixed(1) : 0;
        
        badge.innerHTML = `฿${profit.toFixed(2)}<br>(${margin}%)`;
        if (profit >= 0) {
            badge.className = 'margin-badge fw-bold text-success';
        } else {
            badge.className = 'margin-badge fw-bold text-danger';
        }
    }

    // ปรับราคาทุกล็อตเท่ากันแบบด่วน
    function applyGlobalPrice() {
        Swal.fire({
            title: 'ปรับราคาทุกล็อตย่อยเท่ากัน',
            text: 'กรอกราคาขายที่ต้องการตั้งค่าให้ทุกๆ ล็อตสินค้า',
            input: 'number',
            inputAttributes: {
                step: '0.01',
                min: '0'
            },
            inputPlaceholder: 'เช่น 150.00',
            showCancelButton: true,
            confirmButtonColor: '#85D1FF',
            confirmButtonText: 'ตั้งค่า',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const targetPrice = parseFloat(result.value).toFixed(2);
                const priceInputs = document.querySelectorAll('.lot-price');
                priceInputs.forEach(input => {
                    input.value = targetPrice;
                    calcRowMargin(input);
                });
                
                Swal.fire({
                    title: 'สำเร็จ!',
                    text: `ปรับราคาขายทุกล็อตเป็น ฿${targetPrice} แล้ว`,
                    icon: 'success',
                    timer: 1200,
                    showConfirmButton: false
                });
            }
        });
    }

    // พรีวิวรูปภาพใน Modal
    function previewProductImage(event) {
        const preview = document.getElementById('product-image-preview');
        preview.src = URL.createObjectURL(event.target.files[0]);
        preview.style.display = 'block';
    }

    // เมนูแก้ไขด่วน (เปิด Lot Modal)
    function openLotModal(id, name) {
        document.getElementById('lot_product_id').value = id;
        document.getElementById('lot_product_name').innerText = name;
        new bootstrap.Modal(document.getElementById('addLotModal')).show();
    }

    // ยืนยันลบสินค้า
    function confirmDelete(id) { 
        Swal.fire({ 
            title: 'ยืนยันการลบสินค้า?', 
            text: 'สินค้า ประวัติล็อต และรายการในคลังสินค้าทั้งหมดจะถูกลบถาวรทันที!', 
            icon: 'warning', 
            showCancelButton: true, 
            confirmButtonColor: '#ef4444', 
            confirmButtonText: 'ลบเลย', 
            cancelButtonText: 'ยกเลิก' 
        }).then((result)=>{ 
            if(result.isConfirmed) window.location.href='?delete='+id+'&csrf_token=<?= get_csrf_token() ?>'; 
        }) 
    }

    // ย้ายหน้ากลับเมื่อยกเลิกการแก้ไข
    function cancelEditRedirect() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('edit')) {
            window.location.href = 'admin.php';
        }
    }

    // การจัดการตาราง Option (ลักษณะเดียวกับโค้ดดั้งเดิม)
    const container = document.getElementById('option-container'); 
    const realInput = document.getElementById('real_options_input');
    
    function addOptionRow(name = '', values = '') {
        const div = document.createElement('div');
        div.className = 'option-row animate__animated animate__fadeIn';
        div.innerHTML = `
            <div class="d-flex gap-2 mb-1">
                <input type="text" class="form-control form-control-sm fw-bold opt-name" placeholder="ชื่อตัวเลือก (เช่น สี)" value="${name}">
                <button type="button" class="btn btn-sm btn-light text-danger border-0" onclick="this.parentElement.parentElement.remove()"><i class="bi bi-trash"></i></button>
            </div>
            <input type="text" class="form-control form-control-sm opt-values" placeholder="รายการย่อย (คั่นด้วยจุลภาค , เช่น ดำ,ขาว)" value="${values}">
        `;
        document.getElementById('option-container').appendChild(div);
    }
    
    function prepareOptionsBeforeSubmit() {
        let rows = document.querySelectorAll('.option-row');
        let result = [];
        rows.forEach(row => {
            let name = row.querySelector('.opt-name').value.trim();
            let values = row.querySelector('.opt-values').value.trim();
            if (name && values) {
                if (values.endsWith(',')) values = values.slice(0, -1);
                result.push(name + ':' + values);
            }
        });
        document.getElementById('real_options_input').value = result.join(' | ');
    }
</script>

<?php if($edit_data): ?>
<script>
    // ตรวจจับเมื่อมีข้อมูลแก้ไข ให้เปิด Modal ขึ้นมาอัตโนมัติ
    window.addEventListener('DOMContentLoaded', () => {
        document.getElementById('productFormModalLabel').innerHTML = '<i class="bi bi-pencil-square text-warning"></i> แก้ไขข้อมูลสินค้า';
        
        // โหลดและสร้าง Option ใหม่
        if (realInput.value) {
            let groups = realInput.value.split('|');
            groups.forEach(group => {
                let parts = group.split(':');
                if (parts.length === 2) addOptionRow(parts[0].trim(), parts[1].trim());
            });
        }

        // แสดงรูปภาพพรีวิวภาพเดิม
        const oldImg = document.getElementById('old-image-input').value;
        if (oldImg) {
            const preview = document.getElementById('product-image-preview');
            preview.src = oldImg;
            preview.style.display = 'block';
        }

        // เปิด Modal ทันทีที่โหลด
        const productModal = new bootstrap.Modal(document.getElementById('productFormModal'));
        productModal.show();
        checkGlobalPriceBtn();
    });
</script>
<?php endif; ?>

<?php if(isset($_SESSION['swal'])): ?>
<script>
    Swal.fire({
        icon: '<?= $_SESSION['swal']['icon'] ?>',
        title: '<?= $_SESSION['swal']['title'] ?>',
        text: '<?= $_SESSION['swal']['text'] ?>',
        confirmButtonColor: '#85D1FF',
        timer: 1500,
        showConfirmButton: false
    });
</script>
<?php unset($_SESSION['swal']); endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

