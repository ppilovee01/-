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

// --- Helper to get product total price with split flash/regular quota pricing ---
function getProductTotalPrice($conn, $product_id, $qty) {
    $fs = getActiveFlashSale($conn, $product_id);
    $pq = mysqli_query($conn, "SELECT price FROM products WHERE id = '$product_id'");
    $p = mysqli_fetch_assoc($pq);
    $regular_price = floatval($p['price'] ?? 0);

    if ($fs !== null) {
        $fs_remaining = intval($fs['flash_stock']) - intval($fs['flash_sold']);
        $flash_price = floatval($fs['flash_price']);
        if ($fs_remaining <= 0) {
            return $regular_price * $qty;
        } elseif ($qty <= $fs_remaining) {
            return $flash_price * $qty;
        } else {
            return ($flash_price * $fs_remaining) + ($regular_price * ($qty - $fs_remaining));
        }
    }
    return $regular_price * $qty;
}

// --- Helper to get formatted split price description text ---
function getProductPriceText($conn, $product_id, $qty) {
    $fs = getActiveFlashSale($conn, $product_id);
    $pq = mysqli_query($conn, "SELECT price FROM products WHERE id = '$product_id'");
    $p = mysqli_fetch_assoc($pq);
    $regular_price = floatval($p['price'] ?? 0);

    if ($fs !== null) {
        $fs_remaining = intval($fs['flash_stock']) - intval($fs['flash_sold']);
        $flash_price = floatval($fs['flash_price']);
        if ($fs_remaining <= 0) {
            return '฿' . number_format($regular_price, 2) . ' / ชิ้น';
        } elseif ($qty <= $fs_remaining) {
            return '฿' . number_format($flash_price, 2) . ' (Flash Sale)';
        } else {
            return '฿' . number_format($flash_price, 2) . ' x ' . $fs_remaining . ' ชิ้น (Flash) + ฿' . number_format($regular_price, 2) . ' x ' . ($qty - $fs_remaining) . ' ชิ้น (ปกติ)';
        }
    }
    return '฿' . number_format($regular_price, 2) . ' / ชิ้น';
}

// --- Helper to check and automatically generate a flash sale campaign if enabled ---
function checkAndGenerateAutoFlashSale($conn) {
    $now_str = date('Y-m-d H:i:s');
    // Check if there is an active flash sale campaign
    $q = mysqli_query($conn, "SELECT id FROM flash_sales WHERE '$now_str' BETWEEN start_time AND end_time AND flash_sold < flash_stock LIMIT 1");
    if ($q && mysqli_num_rows($q) > 0) {
        return; // Campaign is already active
    }

    // Check if auto flash sale setting is enabled
    $s_q = mysqli_query($conn, "SELECT auto_flash_sale, auto_flash_discount, auto_flash_duration FROM shop_settings WHERE id = 1");
    if ($s_q && mysqli_num_rows($s_q) > 0) {
        $s = mysqli_fetch_assoc($s_q);
        if ($s['auto_flash_sale'] == 1) {
            // Find a product with stock > 5 and no upcoming campaigns
            $p_q = mysqli_query($conn, "SELECT id, price, stock FROM products WHERE stock > 5 AND id NOT IN (SELECT product_id FROM flash_sales WHERE end_time > '$now_str') ORDER BY RAND() LIMIT 1");
            if (!$p_q || mysqli_num_rows($p_q) == 0) {
                // Fallback: any product with stock > 0 and no upcoming campaigns
                $p_q = mysqli_query($conn, "SELECT id, price, stock FROM products WHERE stock > 0 AND id NOT IN (SELECT product_id FROM flash_sales WHERE end_time > '$now_str') ORDER BY RAND() LIMIT 1");
            }

            if ($p_q && mysqli_num_rows($p_q) > 0) {
                $product = mysqli_fetch_assoc($p_q);
                $pid = $product['id'];
                
                // Calculate discount price
                $discount_pct = intval($s['auto_flash_discount']);
                $discount_pct = max(10, min(85, $discount_pct));
                $flash_price = round($product['price'] * (1 - $discount_pct / 100));
                
                // Calculate stock quota: 30% of current stock, min 1, max 10
                $flash_stock = min(10, max(1, round($product['stock'] * 0.3)));
                
                // Set start and end times
                $duration_hours = intval($s['auto_flash_duration']);
                if ($duration_hours <= 0) $duration_hours = 2;
                
                $start = date('Y-m-d H:i:s');
                $end = date('Y-m-d H:i:s', time() + 3600 * $duration_hours);
                
                mysqli_query($conn, "INSERT INTO flash_sales (product_id, flash_price, flash_stock, flash_sold, start_time, end_time) 
                    VALUES ('$pid', '$flash_price', '$flash_stock', 0, '$start', '$end')");
            }
        }
    }
}

// Automatically check and trigger auto-campaign on load
checkAndGenerateAutoFlashSale($conn);

// --- Helper to send Line Notify alerts ---
function sendLineNotify($conn, $message) {
    $q = mysqli_query($conn, "SELECT line_notify_token FROM shop_settings WHERE id = 1");
    if ($q && mysqli_num_rows($q) > 0) {
        $row = mysqli_fetch_assoc($q);
        $token = $row['line_notify_token'];
        if (!empty($token)) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://notify-api.line.me/api/notify");
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "message=" . urlencode($message));
            $headers = array(
                'Content-type: application/x-www-form-urlencoded',
                'Authorization: Bearer ' . $token,
            );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $res = curl_exec($ch);
            curl_close($ch);
            return $res;
        }
    }
    return false;
}
?>
<link rel="icon" type="image/x-icon" href="<?= $current_favicon ?>">

