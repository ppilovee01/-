<?php
// migrate_slip_ai.php - รันครั้งเดียวบน server แล้วลบทิ้ง
session_start();
include 'db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { die("Access denied"); }

$results = [];

// 1. เพิ่ม column slip_ai_status ในตาราง orders
$check = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'slip_ai_status'");
if (mysqli_num_rows($check) == 0) {
    $r = mysqli_query($conn, "ALTER TABLE orders ADD COLUMN slip_ai_status ENUM('pending','verified','mismatch','invalid','skipped','error') DEFAULT NULL AFTER payment_slip");
    $results[] = $r ? "✅ เพิ่ม orders.slip_ai_status สำเร็จ" : "❌ " . mysqli_error($conn);
} else {
    // ถ้ามีอยู่แล้วให้ปรับ ENUM ให้รองรับ 'error' ด้วย เผื่อกรณีรันก่อนหน้า
    $r = mysqli_query($conn, "ALTER TABLE orders MODIFY COLUMN slip_ai_status ENUM('pending','verified','mismatch','invalid','skipped','error') DEFAULT NULL");
    $results[] = $r ? "✅ ปรับปรุง ENUM orders.slip_ai_status สำเร็จ" : "❌ " . mysqli_error($conn);
}

// 2. เพิ่ม column slip_ai_amount
$check = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'slip_ai_amount'");
if (mysqli_num_rows($check) == 0) {
    $r = mysqli_query($conn, "ALTER TABLE orders ADD COLUMN slip_ai_amount DECIMAL(10,2) DEFAULT NULL AFTER slip_ai_status");
    $results[] = $r ? "✅ เพิ่ม orders.slip_ai_amount สำเร็จ" : "❌ " . mysqli_error($conn);
} else {
    $results[] = "⏭️ orders.slip_ai_amount มีอยู่แล้ว";
}

// 3. เพิ่ม column slip_ai_note
$check = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'slip_ai_note'");
if (mysqli_num_rows($check) == 0) {
    $r = mysqli_query($conn, "ALTER TABLE orders ADD COLUMN slip_ai_note TEXT DEFAULT NULL AFTER slip_ai_amount");
    $results[] = $r ? "✅ เพิ่ม orders.slip_ai_note สำเร็จ" : "❌ " . mysqli_error($conn);
} else {
    $results[] = "⏭️ orders.slip_ai_note มีอยู่แล้ว";
}

// 4. เพิ่ม openai_api_key ใน shop_settings
$check = mysqli_query($conn, "SHOW COLUMNS FROM shop_settings LIKE 'openai_api_key'");
if (mysqli_num_rows($check) == 0) {
    $r = mysqli_query($conn, "ALTER TABLE shop_settings ADD COLUMN openai_api_key VARCHAR(255) DEFAULT NULL");
    $results[] = $r ? "✅ เพิ่ม shop_settings.openai_api_key สำเร็จ" : "❌ " . mysqli_error($conn);
} else {
    $results[] = "⏭️ shop_settings.openai_api_key มีอยู่แล้ว";
}

// 5. เพิ่ม slip_ai_provider ใน shop_settings
$check = mysqli_query($conn, "SHOW COLUMNS FROM shop_settings LIKE 'slip_ai_provider'");
if (mysqli_num_rows($check) == 0) {
    $r = mysqli_query($conn, "ALTER TABLE shop_settings ADD COLUMN slip_ai_provider VARCHAR(50) DEFAULT 'none'");
    $results[] = $r ? "✅ เพิ่ม shop_settings.slip_ai_provider สำเร็จ" : "❌ " . mysqli_error($conn);
} else {
    $results[] = "⏭️ shop_settings.slip_ai_provider มีอยู่แล้ว";
}

// 6. เพิ่ม gemini_api_key ใน shop_settings
$check = mysqli_query($conn, "SHOW COLUMNS FROM shop_settings LIKE 'gemini_api_key'");
if (mysqli_num_rows($check) == 0) {
    $r = mysqli_query($conn, "ALTER TABLE shop_settings ADD COLUMN gemini_api_key VARCHAR(255) DEFAULT NULL");
    $results[] = $r ? "✅ เพิ่ม shop_settings.gemini_api_key สำเร็จ" : "❌ " . mysqli_error($conn);
} else {
    $results[] = "⏭️ shop_settings.gemini_api_key มีอยู่แล้ว";
}

// 7. เพิ่ม claude_api_key ใน shop_settings
$check = mysqli_query($conn, "SHOW COLUMNS FROM shop_settings LIKE 'claude_api_key'");
if (mysqli_num_rows($check) == 0) {
    $r = mysqli_query($conn, "ALTER TABLE shop_settings ADD COLUMN claude_api_key VARCHAR(255) DEFAULT NULL");
    $results[] = $r ? "✅ เพิ่ม shop_settings.claude_api_key สำเร็จ" : "❌ " . mysqli_error($conn);
} else {
    $results[] = "⏭️ shop_settings.claude_api_key มีอยู่แล้ว";
}

echo "<pre style='font-family:monospace; font-size:14px; padding:20px;'>";
echo "<h3>🤖 Migration: AI Slip Verification</h3>\n\n";
foreach ($results as $r) { echo $r . "\n"; }
echo "\n<strong style='color:green'>✅ เสร็จสิ้น! ลบไฟล์นี้ออกจาก server หลังจาก migrate แล้ว</strong>";
echo "\n\n<a href='admin_settings.php'>→ ไปตั้งค่า OpenAI API Key</a>";
echo "</pre>";
?>
