<?php
/**
 * Por Mae Bet Taled - Hosting Debug & Health Check
 * ไฟล์นี้ใช้สำหรับตรวจสอบสถานะ hosting เมื่อเกิด Error 500
 * *** ลบไฟล์นี้ออกหลังจาก debug เสร็จ ***
 */

// แสดง Error ทั้งหมดเพื่อ debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Por Mae Bet Taled - Hosting Health Check</h1>";
echo "<hr>";

// 1. PHP Version
echo "<h3>1. PHP Version</h3>";
echo "<p>PHP Version: <strong>" . phpversion() . "</strong></p>";
echo "<p>Server API: <strong>" . php_sapi_name() . "</strong></p>";
echo "<p>Server Software: <strong>" . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "</strong></p>";

// 2. Required Extensions
echo "<h3>2. PHP Extensions Check</h3>";
$required = ['mysqli', 'curl', 'mbstring', 'session', 'json', 'openssl'];
echo "<ul>";
foreach ($required as $ext) {
    $loaded = extension_loaded($ext);
    $icon = $loaded ? '✅' : '❌';
    echo "<li>$icon <strong>$ext</strong>: " . ($loaded ? 'Loaded' : '<span style="color:red">NOT LOADED</span>') . "</li>";
}
echo "</ul>";

// 3. Database Connection Test
echo "<h3>3. Database Connection</h3>";
$servername = "localhost";
$username = "herbar79_1234";
$password = "Dc6JtpaKJb2HTNff2qs3";
$dbname = "herbar79_1234";

try {
    $test_conn = @mysqli_connect($servername, $username, $password, $dbname);
} catch (Exception $e) {
    $test_conn = false;
}
if ($test_conn) {
    echo "<p>✅ Database connection: <strong style='color:green'>SUCCESS</strong></p>";
    echo "<p>Server Info: " . mysqli_get_server_info($test_conn) . "</p>";
    
    // Test a basic query
    $tables = mysqli_query($test_conn, "SHOW TABLES");
    if ($tables) {
        echo "<p>✅ Tables found: <strong>" . mysqli_num_rows($tables) . "</strong></p>";
    }
    mysqli_close($test_conn);
} else {
    echo "<p>❌ Database connection: <strong style='color:red'>FAILED</strong></p>";
    echo "<p>Error: " . (isset($e) ? $e->getMessage() : mysqli_connect_error()) . "</p>";
}

// 4. File/Directory Permissions
echo "<h3>4. Directory Check</h3>";
$dirs = ['uploads', 'assets', 'PHPMailer', 'scratch'];
echo "<ul>";
foreach ($dirs as $dir) {
    $exists = is_dir($dir);
    $writable = $exists ? is_writable($dir) : false;
    $icon = $exists ? '✅' : '❌';
    echo "<li>$icon <strong>$dir/</strong>: " . ($exists ? "Exists" : "<span style='color:red'>NOT FOUND</span>");
    if ($exists) {
        echo " | Writable: " . ($writable ? "Yes" : "<span style='color:orange'>No</span>");
        echo " | Permissions: " . substr(sprintf('%o', fileperms($dir)), -4);
    }
    echo "</li>";
}
echo "</ul>";

// 5. Key Files Check
echo "<h3>5. Key Files Check</h3>";
$files = [
    'db.php', 'index.php', 'header.php', 'style.css', 'ajax.php',
    '.htaccess', 'uploads/.htaccess', 'assets/default_icon.png'
];
echo "<ul>";
foreach ($files as $file) {
    $exists = file_exists($file);
    $icon = $exists ? '✅' : '❌';
    echo "<li>$icon <strong>$file</strong>: " . ($exists ? "OK (" . filesize($file) . " bytes)" : "<span style='color:red'>MISSING</span>") . "</li>";
}
echo "</ul>";

// 6. Session Test
echo "<h3>6. Session Test</h3>";
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['health_check'] = time();
    echo "<p>✅ Session: <strong style='color:green'>Working</strong></p>";
    echo "<p>Session save path: " . session_save_path() . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Session: <strong style='color:red'>FAILED</strong> - " . $e->getMessage() . "</p>";
}

// 7. Try including db.php
echo "<h3>7. db.php Include Test</h3>";
try {
    ob_start();
    include 'db.php';
    $output = ob_get_clean();
    if (empty(trim($output))) {
        echo "<p>✅ db.php include: <strong style='color:green'>Clean (no output)</strong></p>";
    } else {
        echo "<p>⚠️ db.php produced output: <pre>" . htmlspecialchars($output) . "</pre></p>";
    }
    echo "<p>✅ \$conn variable: " . (isset($conn) && $conn ? "<strong style='color:green'>Connected</strong>" : "<strong style='color:red'>NOT SET</strong>") . "</p>";
} catch (Exception $e) {
    echo "<p>❌ db.php include: <strong style='color:red'>ERROR</strong> - " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p style='color:gray; font-size:12px;'>⚠️ ลบไฟล์นี้ออกหลังจาก debug เสร็จเรียบร้อยแล้ว เพื่อความปลอดภัย</p>";
echo "<p style='color:gray; font-size:12px;'>Generated at: " . date('Y-m-d H:i:s') . "</p>";
?>
