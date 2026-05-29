<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล Por Mae Bet Taled
error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Bangkok');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fitness_db"; 

// สร้างการเชื่อมต่อ
$conn = mysqli_connect($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if (!$conn) {
    die("ขออภัย ไม่สามารถเชื่อมต่อฐานข้อมูลได้");
}

// ตั้งค่าชุดตัวอักษรเป็น UTF-8
mysqli_set_charset($conn, "utf8");

// ดึงข้อมูลการตั้งค่าร้านค้า (เช่น Icon)
$current_favicon = "assets/default_icon.png"; 

$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'shop_settings'");
if ($check_table && mysqli_num_rows($check_table) > 0) {
    $shop_info_query = mysqli_query($conn, "SELECT * FROM shop_settings WHERE id=1");
    if ($shop_info_query && mysqli_num_rows($shop_info_query) > 0) {
        $shop_info = mysqli_fetch_assoc($shop_info_query);
        if (!empty($shop_info['shop_icon'])) {
            $current_favicon = "uploads/" . $shop_info['shop_icon'];
        }
    }
}

// --- Helper to get active flash sale for a product ---
function getActiveFlashSale($conn, $product_id) {
    $q = mysqli_query($conn, "SELECT * FROM flash_sales WHERE product_id = '$product_id' AND NOW() BETWEEN start_time AND end_time LIMIT 1");
    if ($q && mysqli_num_rows($q) > 0) {
        $fs = mysqli_fetch_assoc($q);
        if ($fs['flash_sold'] < $fs['flash_stock']) {
            return $fs;
        }
    }
    return null;
}

// --- Helper to get current active price (checks flash sale) ---
function getCurrentPrice($conn, $product_id) {
    $fs = getActiveFlashSale($conn, $product_id);
    if ($fs !== null) {
        return $fs['flash_price'];
    }
    $pq = mysqli_query($conn, "SELECT price FROM products WHERE id = '$product_id'");
    $p = mysqli_fetch_assoc($pq);
    return $p['price'] ?? 0;
}
?>
<link rel="icon" type="image/x-icon" href="<?= $current_favicon ?>">
