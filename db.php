<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล Por Mae Bet Taled
error_reporting(0);
ini_set('display_errors', 0);

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
?>
<link rel="icon" type="image/x-icon" href="<?= $current_favicon ?>">
