<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล Por Mae Bet Taled
error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Bangkok');

// --- มาตรการป้องกันการแฮกเกอร์และความปลอดภัย HTTP Headers & Sessions ---
ini_set('session.cookie_httponly', 1); // ป้องกันไม่ให้ JavaScript อ่านคุกกี้เซสชันได้ (ป้องกัน Session Hijacking จาก XSS)
ini_set('session.use_only_cookies', 1); // บังคับให้ใช้คุกกี้ในการเก็บเซสชันเท่านั้น
if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1 || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    ini_set('session.cookie_secure', 1); // บังคับส่งคุกกี้ผ่าน HTTPS เท่านั้น
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!headers_sent()) {
    header("X-Frame-Options: DENY"); // ป้องกัน Clickjacking (การแอบฝังเว็บใน iframe)
    header("X-Content-Type-Options: nosniff"); // ป้องกัน MIME-type Sniffing
    header("Referrer-Policy: strict-origin-when-cross-origin"); // ป้องกันข้อมูลหน้าอ้างอิงรั่วไหล
}

// --- สร้างและประมวลผล CSRF Token เพื่อความปลอดภัยของระบบ ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function get_csrf_token() {
    return $_SESSION['csrf_token'] ?? '';
}

function get_csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fitness_db"; 

// สร้างการเชื่อมต่อ (รองรับ PHP 8.1+ ที่ throw exception)
try {
    $conn = mysqli_connect($servername, $username, $password, $dbname);
} catch (mysqli_sql_exception $e) {
    $conn = false;
}

// ตรวจสอบการเชื่อมต่อ
if (!$conn) {
    die("ขออภัย ไม่สามารถเชื่อมต่อฐานข้อมูลได้");
}

// ตั้งค่าชุดตัวอักษรเป็น UTF-8
mysqli_set_charset($conn, "utf8");

// ========================================================
// ตั้งค่า Timezone ของ MySQL Session ให้ตรงกับ Asia/Bangkok
// แก้ปัญหาเวลาไม่ตรงระหว่าง PHP (UTC+7) กับ MySQL Server
// ที่อาจ default เป็น UTC หรือ timezone อื่นบน hosting
// ========================================================
mysqli_query($conn, "SET time_zone = '+07:00'");

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
    // Check if there is an active flash sale campaign (ใช้ MySQL NOW() เพื่อป้องกันปัญหา timezone)
    $q = mysqli_query($conn, "SELECT id FROM flash_sales WHERE NOW() BETWEEN start_time AND end_time AND flash_sold < flash_stock LIMIT 1");
    if ($q && mysqli_num_rows($q) > 0) {
        return; // Campaign is already active
    }

    // Check if auto flash sale setting is enabled
    $s_q = mysqli_query($conn, "SELECT auto_flash_sale, auto_flash_discount, auto_flash_duration FROM shop_settings WHERE id = 1");
    if ($s_q && mysqli_num_rows($s_q) > 0) {
        $s = mysqli_fetch_assoc($s_q);
        if ($s['auto_flash_sale'] == 1) {
            // Find a product with stock > 5 and no upcoming campaigns
            $p_q = mysqli_query($conn, "SELECT id, price, stock FROM products WHERE stock > 5 AND id NOT IN (SELECT product_id FROM flash_sales WHERE end_time > NOW()) ORDER BY RAND() LIMIT 1");
            if (!$p_q || mysqli_num_rows($p_q) == 0) {
                // Fallback: any product with stock > 0 and no upcoming campaigns
                $p_q = mysqli_query($conn, "SELECT id, price, stock FROM products WHERE stock > 0 AND id NOT IN (SELECT product_id FROM flash_sales WHERE end_time > NOW()) ORDER BY RAND() LIMIT 1");
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
                
                // Set start and end times (ใช้ MySQL NOW() และ DATE_ADD เพื่อให้ timezone ตรงกัน)
                $duration_hours = intval($s['auto_flash_duration']);
                if ($duration_hours <= 0) $duration_hours = 2;
                
                mysqli_query($conn, "INSERT INTO flash_sales (product_id, flash_price, flash_stock, flash_sold, start_time, end_time) 
                    VALUES ('$pid', '$flash_price', '$flash_stock', 0, NOW(), DATE_ADD(NOW(), INTERVAL $duration_hours HOUR))");
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

// --- Helper to log admin actions ---
function log_admin_action($conn, $action_type, $details, $user_id = null, $fullname = null) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $admin_id = $user_id !== null ? $user_id : ($_SESSION['user_id'] ?? null);
    $admin_name = $fullname !== null ? $fullname : ($_SESSION['fullname'] ?? 'System');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

    $admin_id_val = $admin_id !== null ? intval($admin_id) : "NULL";
    $admin_name_esc = mysqli_real_escape_string($conn, $admin_name);
    $action_type_esc = mysqli_real_escape_string($conn, $action_type);
    $details_esc = mysqli_real_escape_string($conn, $details);
    $ip_address_esc = mysqli_real_escape_string($conn, $ip_address);

    $sql = "INSERT INTO admin_logs (admin_id, admin_name, action_type, details, ip_address) 
            VALUES ($admin_id_val, '$admin_name_esc', '$action_type_esc', '$details_esc', '$ip_address_esc')";
    mysqli_query($conn, $sql);
}
?>
