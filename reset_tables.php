<?php
// ไฟล์สำหรับรีเซ็ตฐานข้อมูลเพื่อให้นำเข้าข้อมูลใหม่ล่าสุดอัตโนมัติ
// คำเตือนความปลอดภัย: ลบไฟล์นี้ออกจากเซิร์ฟเวอร์ทันทีหลังจากรันเสร็จ!
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';

// ดึงรายชื่อตารางทั้งหมด
$result = mysqli_query($conn, "SHOW TABLES");
if ($result) {
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
    $dropped = [];
    while ($row = mysqli_fetch_array($result)) {
        $tableName = $row[0];
        if (mysqli_query($conn, "DROP TABLE IF EXISTS `$tableName`")) {
            $dropped[] = $tableName;
        }
    }
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
    
    echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #d4edda; background-color: #d4edda; color: #155724; border-radius: 5px;'>";
    echo "<h2>ล้างฐานข้อมูลเก่าสำเร็จ!</h2>";
    echo "<p>ลบตารางทั้งหมดเรียบร้อยแล้ว: " . implode(', ', $dropped) . "</p>";
    echo "<p>กำลังเปลี่ยนเส้นทางไปหน้าหลักเพื่อสร้างระบบฐานข้อมูลใหม่ใน 3 วินาที...</p>";
    echo "<p><strong>⚠️ คำเตือน: กรุณาลบไฟล์ <code>reset_tables.php</code> ออกจากเซิร์ฟเวอร์ทันทีเพื่อความปลอดภัย!</strong></p>";
    echo "</div>";
    
    echo "<script>setTimeout(function() { window.location.href = 'index.php'; }, 3000);</script>";
} else {
    echo "ไม่สามารถดึงข้อมูลตารางเพื่อรีเซ็ตได้ หรือไม่มีตารางให้ลบ";
}
?>
