<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล Por Mae Bet Taled
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
date_default_timezone_set('Asia/Bangkok');

// --- ฟังก์ชันโหลดไฟล์ .env สำหรับเก็บความลับของระบบ ---
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            $val = trim($val, '"\'');
            putenv("{$key}={$val}");
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }
}

// --- ฟังก์ชันอัปเดตค่าในไฟล์ .env สำหรับการตั้งค่าจากหน้าเว็บหลังบ้าน ---
function updateEnv($key, $value, $path) {
    if (!file_exists($path)) {
        if (file_exists(dirname($path) . '/.env.example')) {
            @copy(dirname($path) . '/.env.example', $path);
        } else {
            @file_put_contents($path, "");
        }
    }
    
    $content = @file_get_contents($path);
    if ($content === false) {
        $content = '';
    }
    $pattern = "/^" . preg_quote($key, '/') . "=(.*)$/m";
    $escapedValue = trim($value);
    
    if (preg_match('/\s/', $escapedValue) || empty($escapedValue)) {
        $escapedValue = '"' . str_replace('"', '\\"', $escapedValue) . '"';
    }
    
    if (preg_match($pattern, $content)) {
        $content = preg_replace($pattern, "{$key}={$escapedValue}", $content);
    } else {
        if (!empty($content) && substr($content, -1) !== "\n") {
            $content .= "\n";
        }
        $content .= "{$key}={$escapedValue}\n";
    }
    
    return @file_put_contents($path, $content) !== false;
}
loadEnv(__DIR__ . '/.env');

// --- ฟังก์ชันดึงค่าความลับ (อ่านจาก env ก่อน ถ้าไม่มีค่อยดึงจาก DB) ---
function getSecretValue($envKey, $dbValue) {
    $val = getenv($envKey);
    if ($val === false || trim($val) === '') {
        $val = $_ENV[$envKey] ?? ($_SERVER[$envKey] ?? false);
    }
    return ($val !== false && trim($val) !== '') ? $val : $dbValue;
}

// --- ฟังก์ชันตรวจสอบว่าทำงานอยู่บน Localhost หรือไม่ ---
function isLocalhost() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return (
        strpos($host, 'localhost') !== false || 
        strpos($host, '127.0.0.1') !== false || 
        $host === '[::1]'
    );
}

// --- ฟังก์ชันเช็คว่ามีค่าในไฟล์ env จริงหรือไม่ (ไม่ใช่ค่าว่างหรือเว้นวรรค) ---
function hasEnvValue($envKey) {
    $val = getenv($envKey);
    if ($val === false || trim($val) === '') {
        $val = $_ENV[$envKey] ?? ($_SERVER[$envKey] ?? false);
    }
    return $val !== false && trim($val) !== '';
}


// --- ฟังก์ชันดึงค่าในไฟล์ env อย่างปลอดภัย (รองรับ XAMPP/Apache Fallbacks) ---
function getEnvValue($envKey) {
    $val = getenv($envKey);
    if ($val === false || trim($val) === '') {
        $val = $_ENV[$envKey] ?? ($_SERVER[$envKey] ?? '');
    }
    return ($val !== false) ? trim($val) : '';
}



// --- ฟังก์ชันเซนเซอร์ข้อมูลความลับสำหรับแสดงผลบน UI (เช่น AIzaSy••••••••4fG) ---
function getMaskedValue($envKey, $dbValue) {
    $val = trim(getSecretValue($envKey, $dbValue) ?? '');
    if (empty($val)) {
        return '';
    }
    $len = strlen($val);
    if ($len <= 10) {
        return substr($val, 0, 2) . '••••' . substr($val, -2);
    }
    return substr($val, 0, 6) . '••••••••' . substr($val, -4);
}

// --- มาตรการป้องกันการแฮกเกอร์และความปลอดภัย HTTP Headers & Sessions ---
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); // ป้องกันไม่ให้ JavaScript อ่านคุกกี้เซสชันได้ (ป้องกัน Session Hijacking จาก XSS)
    ini_set('session.use_only_cookies', 1); // บังคับให้ใช้คุกกี้ในการเก็บเซสชันเท่านั้น
    ini_set('session.cookie_samesite', 'Lax'); // ป้องกัน CSRF จาก Cross-site request (SameSite Cookie Policy)
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1 || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
        ini_set('session.cookie_secure', 1); // บังคับส่งคุกกี้ผ่าน HTTPS เท่านั้น
    }
    session_start();
}

if (!headers_sent()) {
    header("X-Frame-Options: DENY"); // ป้องกัน Clickjacking (การแอบฝังเว็บใน iframe)
    header("X-Content-Type-Options: nosniff"); // ป้องกัน MIME-type Sniffing
    header("Referrer-Policy: strict-origin-when-cross-origin"); // ป้องกันข้อมูลหน้าอ้างอิงรั่วไหล
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()"); // จำกัดการเข้าถึงอุปกรณ์
    // Content-Security-Policy: อนุญาตเฉพาะแหล่ง CDN ที่ใช้งานจริง
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com https://challenges.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: blob: https:; connect-src 'self' https:; frame-src 'self' https://challenges.cloudflare.com; frame-ancestors 'none';");
    // HSTS: บังคับใช้ HTTPS (เปิดเมื่อมีใบรับรอง SSL)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }
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

$servername = getenv('DB_HOST') ?: "localhost";
$username = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "";
$dbname = getenv('DB_NAME') ?: "fitness_db"; 

// 1. เชื่อมต่อเซิร์ฟเวอร์ MySQL ก่อน (แบบยังไม่ระบุชื่อ DB เพื่อความปลอดภัยในการสร้าง DB ใหม่แบบ auto)
try {
    $conn = mysqli_connect($servername, $username, $password);
} catch (mysqli_sql_exception $e) {
    $conn = false;
}

// หากการเชื่อมต่อล้มเหลว
if (!$conn) {
    die("ขออภัย ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ฐานข้อมูลได้");
}

// 2. สร้างฐานข้อมูลอัตโนมัติหากยังไม่มี
mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8 COLLATE utf8_general_ci");

// 3. เลือกใช้งานฐานข้อมูล
if (!mysqli_select_db($conn, $dbname)) {
    die("ขออภัย ไม่สามารถเชื่อมต่อฐานข้อมูลได้");
}

// ตั้งค่าชุดตัวอักษรเป็น UTF-8
mysqli_set_charset($conn, "utf8");

// 4. ตรวจสอบว่ามีตารางสินค้าหรือไม่ หากเป็นห้องว่าง ให้ทำการสร้างและอิมพอร์ต SQL อัตโนมัติ (Auto-Initialization)
$check_table_empty = mysqli_query($conn, "SHOW TABLES LIKE 'products'");
if (!$check_table_empty || mysqli_num_rows($check_table_empty) == 0) {
    $sql_file_path = __DIR__ . '/fitness_db.sql';
    $sql_queries = '';
    if (file_exists($sql_file_path)) {
        $sql_queries = file_get_contents($sql_file_path);
    } else {
        // SQL Dump บีบอัดแบบ Base64 + Gzip ในตัว เพื่อให้สามารถติดตั้งได้ทันทีโดยไม่ต้องอัปโหลดไฟล์ SQL
        $compressed_sql = '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';
        $sql_queries = gzdecode(base64_decode($compressed_sql));
    }
    
    if (!empty($sql_queries)) {
        // รันคำสั่ง SQL ทั้งหมดแบบ Multi-Query เพื่อสร้างโครงสร้างและข้อมูลเริ่มต้นโดยอัตโนมัติ
        if (mysqli_multi_query($conn, $sql_queries)) {
            // เคลียร์คิวรี่ทั้งหมดเพื่อเลี่ยงข้อผิดพลาด Commands out of sync
            do {
                if ($result = mysqli_store_result($conn)) {
                    mysqli_free_result($result);
                }
            } while (mysqli_next_result($conn));
        }
    }
}

// ========================================================
// ตั้งค่า Timezone ของ MySQL Session ให้ตรงกับ Asia/Bangkok
// แก้ปัญหาเวลาไม่ตรงระหว่าง PHP (UTC+7) กับ MySQL Server
// ที่อาจ default เป็น UTC หรือ timezone อื่นบน hosting
// ========================================================
mysqli_query($conn, "SET time_zone = '+07:00'");

// --- ระบบยืนยันตัวตนแอดมิน 2 ขั้นตอน (2FA OTP & Trusted Devices) ---
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `admin_2fa_otps` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `otp_code` VARCHAR(6) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `admin_trusted_devices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    if (empty($_SESSION['2fa_verified'])) {
        $trusted = false;
        $cookie_name = 'admin_trusted_device';
        if (isset($_COOKIE[$cookie_name])) {
            $cookie_val = $_COOKIE[$cookie_name];
            $parts = explode(':', $cookie_val, 2);
            if (count($parts) === 2) {
                $user_id = intval($parts[0]);
                $raw_token = $parts[1];
                $token_hash = hash('sha256', $raw_token);
                
                $stmt = mysqli_prepare($conn, "SELECT id FROM admin_trusted_devices WHERE user_id = ? AND token_hash = ? AND expires_at > NOW() LIMIT 1");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "is", $user_id, $token_hash);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_store_result($stmt);
                    if (mysqli_stmt_num_rows($stmt) > 0) {
                        $_SESSION['2fa_verified'] = true;
                        $trusted = true;
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
        
        if (!$trusted) {
            $current_script = basename($_SERVER['SCRIPT_NAME']);
            if ($current_script !== 'admin_otp.php' && $current_script !== 'logout.php') {
                header("Location: admin_otp.php");
                exit();
            }
        }
    }
}

// ดึงข้อมูลการตั้งค่าร้านค้า (เช่น Icon)
$current_favicon = "assets/default_icon.png"; 

$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'shop_settings'");
if ($check_table && mysqli_num_rows($check_table) > 0) {
    // Auto-migrate social and messaging columns if missing
    foreach (['facebook_url', 'line_url', 'instagram_url', 'line_channel_access_token', 'line_user_id'] as $col) {
        $check_col = mysqli_query($conn, "SHOW COLUMNS FROM shop_settings LIKE '$col'");
        if ($check_col && mysqli_num_rows($check_col) == 0) {
            if ($col === 'line_channel_access_token') {
                mysqli_query($conn, "ALTER TABLE shop_settings ADD COLUMN line_channel_access_token VARCHAR(255) DEFAULT NULL");
            } elseif ($col === 'line_user_id') {
                mysqli_query($conn, "ALTER TABLE shop_settings ADD COLUMN line_user_id VARCHAR(100) DEFAULT NULL");
            } else {
                mysqli_query($conn, "ALTER TABLE shop_settings ADD COLUMN $col VARCHAR(255) DEFAULT '#'");
            }
        }
    }
    
    $shop_info_query = mysqli_query($conn, "SELECT * FROM shop_settings WHERE id=1");
    if ($shop_info_query && mysqli_num_rows($shop_info_query) > 0) {
        $shop_info = mysqli_fetch_assoc($shop_info_query);
        if (!empty($shop_info['shop_icon'])) {
            $current_favicon = "uploads/" . $shop_info['shop_icon'];
        }
    }
}

$check_table_contact = mysqli_query($conn, "SHOW TABLES LIKE 'contact_messages'");
if ($check_table_contact && mysqli_num_rows($check_table_contact) > 0) {
    foreach (['reply_message', 'replied_at', 'replied_by'] as $col) {
        $check_col = mysqli_query($conn, "SHOW COLUMNS FROM contact_messages LIKE '$col'");
        if ($check_col && mysqli_num_rows($check_col) == 0) {
            if ($col === 'reply_message') {
                mysqli_query($conn, "ALTER TABLE contact_messages ADD COLUMN reply_message TEXT DEFAULT NULL");
            } elseif ($col === 'replied_at') {
                mysqli_query($conn, "ALTER TABLE contact_messages ADD COLUMN replied_at TIMESTAMP NULL DEFAULT NULL");
            } elseif ($col === 'replied_by') {
                mysqli_query($conn, "ALTER TABLE contact_messages ADD COLUMN replied_by VARCHAR(100) DEFAULT NULL");
            }
        }
    }
}

// Auto-migrate users table (add last_login column if missing)
$check_table_users = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
if ($check_table_users && mysqli_num_rows($check_table_users) > 0) {
    $check_col = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'last_login'");
    if ($check_col && mysqli_num_rows($check_col) == 0) {
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN last_login DATETIME DEFAULT NULL AFTER created_at");
        mysqli_query($conn, "UPDATE users SET last_login = created_at WHERE last_login IS NULL");
    }
}

// Auto-migrate banners table (add missing columns)
$check_table_banners = mysqli_query($conn, "SHOW TABLES LIKE 'banners'");
if ($check_table_banners && mysqli_num_rows($check_table_banners) > 0) {
    $required_cols = [
        'title' => "ALTER TABLE banners ADD COLUMN title VARCHAR(255) DEFAULT NULL",
        'link_url' => "ALTER TABLE banners ADD COLUMN link_url VARCHAR(255) DEFAULT NULL",
        'status' => "ALTER TABLE banners ADD COLUMN status ENUM('active','inactive') DEFAULT 'active'",
        'sort_order' => "ALTER TABLE banners ADD COLUMN sort_order INT DEFAULT 0",
        'start_date' => "ALTER TABLE banners ADD COLUMN start_date DATETIME DEFAULT NULL",
        'end_date' => "ALTER TABLE banners ADD COLUMN end_date DATETIME DEFAULT NULL"
    ];
    foreach ($required_cols as $col => $alter_sql) {
        $check_col = mysqli_query($conn, "SHOW COLUMNS FROM banners LIKE '$col'");
        if ($check_col && mysqli_num_rows($check_col) == 0) {
            mysqli_query($conn, $alter_sql);
        }
    }
}



// --- Helper to get active flash sale for a product ---
function getActiveFlashSale($conn, $product_id) {
    $product_id = intval($product_id); // ป้องกัน SQL Injection
    $q = mysqli_query($conn, "SELECT * FROM flash_sales WHERE product_id = $product_id AND NOW() BETWEEN start_time AND end_time LIMIT 1");
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
    $product_id = intval($product_id); // ป้องกัน SQL Injection
    $fs = getActiveFlashSale($conn, $product_id);
    if ($fs !== null) {
        return $fs['flash_price'];
    }
    $pq = mysqli_query($conn, "SELECT price FROM products WHERE id = $product_id");
    $p = mysqli_fetch_assoc($pq);
    return $p['price'] ?? 0;
}

// --- Helper to get product total price with split flash/regular quota pricing ---
function getProductTotalPrice($conn, $product_id, $qty) {
    $product_id = intval($product_id); // ป้องกัน SQL Injection
    $fs = getActiveFlashSale($conn, $product_id);
    $pq = mysqli_query($conn, "SELECT price FROM products WHERE id = $product_id");
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
    $product_id = intval($product_id); // ป้องกัน SQL Injection
    $fs = getActiveFlashSale($conn, $product_id);
    $pq = mysqli_query($conn, "SELECT price FROM products WHERE id = $product_id");
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

// --- Helper to calculate dynamic discount based on 30-day popularity/sales velocity ---
function calculateDynamicDiscount($conn, $product_id, $min_discount, $max_discount) {
    $all_sales_q = mysqli_query($conn, "
        SELECT p.id, COALESCE(SUM(oi.quantity), 0) AS total_sold
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.status != 'cancelled' AND o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY p.id
    ");
    
    $sales_map = [];
    $max_sold = 0;
    $min_sold = null;
    
    if ($all_sales_q) {
        while ($row = mysqli_fetch_assoc($all_sales_q)) {
            $sold = intval($row['total_sold']);
            $sales_map[$row['id']] = $sold;
            if ($sold > $max_sold) {
                $max_sold = $sold;
            }
            if ($min_sold === null || $sold < $min_sold) {
                $min_sold = $sold;
            }
        }
    }
    
    if ($min_sold === null) $min_sold = 0;
    $target_sold = $sales_map[$product_id] ?? 0;
    
    if ($max_sold > $min_sold) {
        $discount = $max_discount - (($max_discount - $min_discount) * ($target_sold - $min_sold) / ($max_sold - $min_sold));
    } else {
        $discount = $max_discount;
    }
    
    return max($min_discount, min($max_discount, round($discount)));
}

// --- Helper to check and automatically generate a flash sale campaign if enabled ---
function checkAndGenerateAutoFlashSale($conn) {
    // Check if auto flash sale setting is enabled
    $s_q = mysqli_query($conn, "SELECT auto_flash_sale, auto_flash_discount, auto_flash_duration, auto_flash_type, auto_flash_min_discount, auto_flash_max_discount, auto_flash_selection_rule, auto_flash_count, auto_flash_stock FROM shop_settings WHERE id = 1");
    if (!$s_q || mysqli_num_rows($s_q) == 0) {
        return;
    }
    $s = mysqli_fetch_assoc($s_q);
    if ($s['auto_flash_sale'] != 1) {
        return;
    }

    $duration_hours = intval($s['auto_flash_duration']);
    if ($duration_hours <= 0) $duration_hours = 2;
    $round_limit = intval($s['auto_flash_count'] ?? 3);
    if ($round_limit <= 0) $round_limit = 3;

    // 1. Determine the current active round window.
    $active_q = mysqli_query($conn, "SELECT start_time, end_time FROM flash_sales WHERE end_time > NOW() AND start_time <= NOW() ORDER BY start_time ASC LIMIT 1");
    
    if ($active_q && mysqli_num_rows($active_q) > 0) {
        $active_row = mysqli_fetch_assoc($active_q);
        $curr_start = $active_row['start_time'];
        $curr_end = $active_row['end_time'];
    } else {
        $curr_start = date('Y-m-d H:i:s');
        $curr_end = date('Y-m-d H:i:s', strtotime($curr_start) + ($duration_hours * 3600));
    }

    // 2. Count campaigns in current round
    $curr_count_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM flash_sales WHERE start_time = '$curr_start' AND end_time = '$curr_end'");
    $curr_count = intval(mysqli_fetch_assoc($curr_count_q)['cnt'] ?? 0);

    if ($curr_count < $round_limit) {
        $target_start = $curr_start;
        $target_end = $curr_end;
    } else {
        $next_start = $curr_end;
        $next_end = date('Y-m-d H:i:s', strtotime($next_start) + ($duration_hours * 3600));

        // Count campaigns in next round
        $next_count_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM flash_sales WHERE start_time = '$next_start' AND end_time = '$next_end'");
        $next_count = intval(mysqli_fetch_assoc($next_count_q)['cnt'] ?? 0);

        if ($next_count < $round_limit) {
            $target_start = $next_start;
            $target_end = $next_end;
        } else {
            return; // Both current and next rounds are fully populated
        }
    }

    // 3. Select a product for the target round ($target_start to $target_end)
    $overlap_subquery = "SELECT product_id FROM flash_sales WHERE end_time > '$target_start' AND start_time < '$target_end'";

    $rule = $s['auto_flash_selection_rule'] ?? 'random';
    $product = null;
    $stock_filter = "stock > 5";

    for ($attempt = 1; $attempt <= 2; $attempt++) {
        if ($attempt == 2) {
            $stock_filter = "stock > 0";
        }

        $sql = "";
        if ($rule === 'slow_moving') {
            $sql = "SELECT p.id, p.price, p.stock, COALESCE(SUM(oi.quantity), 0) AS total_sold
                    FROM products p
                    LEFT JOIN order_items oi ON p.id = oi.product_id
                    LEFT JOIN orders o ON oi.order_id = o.id AND o.status != 'cancelled' AND o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    WHERE p.{$stock_filter} AND p.id NOT IN ($overlap_subquery)
                    GROUP BY p.id
                    ORDER BY total_sold ASC, p.id ASC
                    LIMIT 1";
        } elseif ($rule === 'popular') {
            $sql = "SELECT p.id, p.price, p.stock, COALESCE(SUM(oi.quantity), 0) AS total_sold
                    FROM products p
                    LEFT JOIN order_items oi ON p.id = oi.product_id
                    LEFT JOIN orders o ON oi.order_id = o.id AND o.status != 'cancelled' AND o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    WHERE p.{$stock_filter} AND p.id NOT IN ($overlap_subquery)
                    GROUP BY p.id
                    ORDER BY total_sold DESC, p.id ASC
                    LIMIT 1";
        } elseif ($rule === 'high_stock') {
            $sql = "SELECT id, price, stock FROM products
                    WHERE {$stock_filter} AND id NOT IN ($overlap_subquery)
                    ORDER BY stock DESC, id ASC
                    LIMIT 1";
        } else { // 'random'
            $sql = "SELECT id, price, stock FROM products
                    WHERE {$stock_filter} AND id NOT IN ($overlap_subquery)
                    ORDER BY RAND()
                    LIMIT 1";
        }

        $p_res = mysqli_query($conn, $sql);
        if ($p_res && mysqli_num_rows($p_res) > 0) {
            $product = mysqli_fetch_assoc($p_res);
            break;
        }
    }

    if ($product) {
        $pid = intval($product['id']);

        if ($s['auto_flash_type'] === 'dynamic') {
            $min_d = intval($s['auto_flash_min_discount']);
            $max_d = intval($s['auto_flash_max_discount']);
            $discount_pct = calculateDynamicDiscount($conn, $pid, $min_d, $max_d);
        } else {
            $discount_pct = intval($s['auto_flash_discount']);
        }

        $discount_pct = max(5, min(90, $discount_pct));
        $flash_price = round($product['price'] * (1 - $discount_pct / 100));
        $max_auto_stock = intval($s['auto_flash_stock'] ?? 10);
        if ($max_auto_stock <= 0) $max_auto_stock = 10;
        // Limit stock to max configured or actual product stock (min 1)
        $flash_stock = min($max_auto_stock, max(1, $product['stock']));

        $ins = mysqli_query($conn, "INSERT INTO flash_sales (product_id, flash_price, flash_stock, flash_sold, start_time, end_time) 
            VALUES ('$pid', '$flash_price', '$flash_stock', 0, '$target_start', '$target_end')");

        if ($ins) {
            $p_name_res = mysqli_query($conn, "SELECT name FROM products WHERE id = $pid");
            $p_name = mysqli_fetch_assoc($p_name_res)['name'] ?? 'Unknown';

            log_admin_action($conn, 'ระบบสุ่ม Flash Sale อัตโนมัติ', [
                'title' => "สร้างแคมเปญ Flash Sale อัตโนมัติสำเร็จสำหรับสินค้า '$p_name'",
                'details' => "สินค้า: $p_name (ID #$pid), ราคา: ฿$flash_price (ส่วนลด $discount_pct% แบบ {$s['auto_flash_type']}), โควตา: $flash_stock ชิ้น, เริ่มต้น: $target_start, สิ้นสุด: $target_end"
            ], 0, 'ระบบ');

            checkAndGenerateAutoFlashSale($conn);
        }
    }
}

checkAndGenerateAutoFlashSale($conn);

// --- Helper to send Line Notify alerts ---
function sendLineNotify($conn, $message) {
    // 1. ลองใช้ LINE Messaging API (แนะนำ)
    $channel_token = getenv('LINE_CHANNEL_ACCESS_TOKEN') ?: '';
    $user_id = getenv('LINE_USER_ID') ?: '';
    
    if (empty($channel_token) || empty($user_id)) {
        $q = mysqli_query($conn, "SELECT line_channel_access_token, line_user_id FROM shop_settings WHERE id = 1");
        if ($q && mysqli_num_rows($q) > 0) {
            $row = mysqli_fetch_assoc($q);
            if (empty($channel_token)) $channel_token = $row['line_channel_access_token'] ?? '';
            if (empty($user_id)) $user_id = $row['line_user_id'] ?? '';
        }
    }
    
    if (!empty($channel_token) && !empty($user_id)) {
        $url = "https://api.line.me/v2/bot/message/push";
        $data = [
            'to' => $user_id,
            'messages' => [
                [
                    'type' => 'text',
                    'text' => $message
                ]
            ]
        ];
        $payload = json_encode($data);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // ตรวจสอบ SSL Certificate (ป้องกัน MITM Attack)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $channel_token
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }

    // 2. Fallback ไปที่ LINE Notify (Deprecated)
    $token = getenv('LINE_NOTIFY_TOKEN') ?: '';
    if (empty($token)) {
        $q = mysqli_query($conn, "SELECT line_notify_token FROM shop_settings WHERE id = 1");
        if ($q && mysqli_num_rows($q) > 0) {
            $row = mysqli_fetch_assoc($q);
            $token = $row['line_notify_token'] ?? '';
        }
    }
    
    if (!empty($token)) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://notify-api.line.me/api/notify");
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // ตรวจสอบ SSL Certificate (ป้องกัน MITM Attack)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
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
    
    if (is_array($details) || is_object($details)) {
        $details_str = json_encode($details, JSON_UNESCAPED_UNICODE);
    } else {
        $details_str = (string)$details;
    }
    $details_esc = mysqli_real_escape_string($conn, $details_str);
    $ip_address_esc = mysqli_real_escape_string($conn, $ip_address);

    $sql = "INSERT INTO admin_logs (admin_id, admin_name, action_type, details, ip_address) 
            VALUES ($admin_id_val, '$admin_name_esc', '$action_type_esc', '$details_esc', '$ip_address_esc')";
    mysqli_query($conn, $sql);
}

// --- Helper to render pagination controls matching mockup style ---
function render_pagination_controls($total_rows, $limit, $page, $offset, $js_func = 'changePageLimit') {
    $total_pages = ceil($total_rows / $limit);
    if ($total_rows <= 0) return '';
    
    // build dropdown options
    $limit_options = [10, 20, 50, 100];
    $options_html = '';
    foreach ($limit_options as $opt) {
        $selected = ($limit == $opt) ? 'selected' : '';
        $options_html .= "<option value=\"$opt\" $selected>$opt</option>";
    }
    
    // calculate start and end rows
    $start_row = $total_rows > 0 ? $offset + 1 : 0;
    $end_row = min($offset + $limit, $total_rows);
    
    // Build query params for links
    $params = $_GET;
    unset($params['page']); // page will be set dynamically per link
    unset($params['ajax_fetch']); // make sure ajax_fetch doesn't persist to final url
    unset($params['ajax']);
    
    $prev_disabled = $page <= 1 ? 'disabled' : '';
    $prev_page = max(1, $page - 1);
    $params['page'] = $prev_page;
    $prev_url = '?' . http_build_query($params);
    
    $next_disabled = $page >= $total_pages ? 'disabled' : '';
    $next_page = min($total_pages, $page + 1);
    $params['page'] = $next_page;
    $next_url = '?' . http_build_query($params);
    
    $pages_html = '';
    $start_p = max(1, $page - 2);
    $end_p = min($total_pages, $page + 2);
    for ($i = $start_p; $i <= $end_p; $i++) {
        $active = ($page == $i) ? 'active' : '';
        $params['page'] = $i;
        $url = '?' . http_build_query($params);
        $pages_html .= "
            <li class=\"page-item $active\">
                <a class=\"page-link\" href=\"$url\">$i</a>
            </li>";
    }
    
    // Custom style overrides to ensure the pagination is extremely elegant and matching the pastel design
    $pagination_css = "
    <style>
        .pagination .page-link {
            border: 1px solid #dee2e6;
            color: #7FB5FF;
            transition: all 0.2s ease;
        }
        .pagination .page-item.active .page-link {
            background-color: #7FB5FF !important;
            border-color: #7FB5FF !important;
            color: white !important;
        }
        .pagination .page-item.disabled .page-link {
            color: #6c757d;
            background-color: #e9ecef;
            border-color: #dee2e6;
        }
    </style>";
    
    return $pagination_css . "
    <nav class=\"d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 px-3 gap-3 w-100\">
        <div class=\"d-flex align-items-center gap-2 text-muted small flex-wrap\">
            <span>แสดงผล</span>
            <select id=\"page-limit-select\" class=\"form-select form-select-sm rounded-3\" style=\"width: 70px;\" onchange=\"{$js_func}(this)\">
                $options_html
            </select>
            <span>รายการต่อหน้า (แถว " . number_format($start_row) . " - " . number_format($end_row) . " จากทั้งหมด " . number_format($total_rows) . " รายการ)</span>
        </div>
        " . ($total_pages > 1 ? "
        <ul class=\"pagination pagination-sm m-0\">
            <li class=\"page-item $prev_disabled\">
                <a class=\"page-link\" href=\"$prev_url\"><i class=\"bi bi-chevron-left\"></i></a>
            </li>
            $pages_html
            <li class=\"page-item $next_disabled\">
                <a class=\"page-link\" href=\"$next_url\"><i class=\"bi bi-chevron-right\"></i></a>
            </li>
        </ul>" : "") . "
    </nav>";
}

// --- ฟังก์ชันยืนยันความปลอดภัยด้วย Cloudflare Turnstile ---
function verifyTurnstile() {
    if (!hasEnvValue('TURNSTILE_SECRET_KEY')) {
        return true; // ถ้าไม่ได้ตั้งค่าคีย์ลับ ให้ข้ามการตรวจสอบ
    }
    
    $token = $_POST['cf-turnstile-response'] ?? '';
    if (empty($token)) {
        return false;
    }
    
    $secret = getEnvValue('TURNSTILE_SECRET_KEY');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://challenges.cloudflare.com/turnstile/v0/siteverify");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $ip
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    
    // หากอยู่บน Localhost และการเชื่อมต่ออินเทอร์เน็ตล้มเหลว (เช่น ทำงานแบบออฟไลน์) ให้ข้ามเพื่อไม่ให้ล็อคผู้พัฒนา
    if ($response === false && isLocalhost()) {
        curl_close($ch);
        return true;
    }
    curl_close($ch);
    
    $res_data = json_decode($response, true);
    return ($res_data && isset($res_data['success']) && $res_data['success'] === true);
}
?>

