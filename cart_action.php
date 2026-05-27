<?php
session_start();

// ตรวเธสอเธวเนามีการสเนเธ id สินค้ามาเนหม
if (isset($_GET['add_to_cart'])) {
    $p_id = $_GET['add_to_cart'];
    
    // ถเนายัเธเนมเนมีตะกร้า เนหเนสรเนาเธ Array วเนาเธเธึเนเธมา
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = array();
    }
    
    // เเธิเนม id สินค้าลเธในตะกร้า
    array_push($_SESSION['cart'], $p_id);
    
    // กลับไปหน้าหลัเธ
    header("Location: index.php");
}

// ระบบลเนาเธตะกร้า (สำหรับทดสอเธ)
if (isset($_GET['clear'])) {
    unset($_SESSION['cart']);
    header("Location: index.php");
}
?>


