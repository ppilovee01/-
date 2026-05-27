<?php
session_start();

// ตรวจสอบวเนˆามีการส่ง id สินค้ามาเน„หม
if (isset($_GET['add_to_cart'])) {
    $p_id = $_GET['add_to_cart'];
    
    // ถ้ายังไม่มีตะกร้า ให้สรเน‰าง Array วเนˆางขึเน‰นมา
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = array();
    }
    
    // เพิ่ม id สินค้าลงในตะกร้า
    array_push($_SESSION['cart'], $p_id);
    
    // กลับไปหน้าหลัเ
    header("Location: index.php");
}

// ระบบลเน‰างตะกร้า (สำหรับทดสอบ)
if (isset($_GET['clear'])) {
    unset($_SESSION['cart']);
    header("Location: index.php");
}
?>


