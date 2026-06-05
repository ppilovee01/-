<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล Por Mae Bet Taled
error_reporting(0);
ini_set('display_errors', 0);
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
    return ($val !== false && trim($val) !== '') ? $val : $dbValue;
}

// --- ฟังก์ชันเช็คว่ามีค่าในไฟล์ env จริงหรือไม่ (ไม่ใช่ค่าว่างหรือเว้นวรรค) ---
function hasEnvValue($envKey) {
    $val = getenv($envKey);
    return $val !== false && trim($val) !== '';
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

$servername = getenv('DB_HOST') ?: "localhost";
$username = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "";
$dbname = getenv('DB_NAME') ?: "fitness_db"; 

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
            ]);

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
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
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
?>
