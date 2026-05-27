<?php
// เริ่มต้นด้วยการจัดการ Buffer และ Error
ob_start(); 
session_start();
include 'db.php';
error_reporting(0); 
ini_set('display_errors', 0);
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$response = ['status' => 'error', 'message' => 'ไม่ทำรายการ'];

// --- 1. Wishlist (รายการโปรด) ---
if ($action == 'toggle_wishlist') {
    if (!isset($_SESSION['user_id'])) {
        $response = ['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อน'];
    } else {
        $uid = $_SESSION['user_id'];
        $pid = mysqli_real_escape_string($conn, $_POST['product_id']);

        $check = mysqli_query($conn, "SELECT id FROM wishlist WHERE user_id='$uid' AND product_id='$pid'");
        if (mysqli_num_rows($check) > 0) {
            mysqli_query($conn, "DELETE FROM wishlist WHERE user_id='$uid' AND product_id='$pid'");
            $state = 'removed'; $msg = 'ลบจากรายการโปรดแล้ว';
        } else {
            mysqli_query($conn, "INSERT INTO wishlist (user_id, product_id) VALUES ('$uid', '$pid')");
            $state = 'added'; $msg = 'เพิ่มในรายการโปรดแล้ว';
        }
        $response = ['status' => 'success', 'state' => $state, 'message' => $msg];
    }
}

// --- 2. Compare (เปรียบเทียบ) ---
if ($action == 'toggle_compare') {
    $pid = $_POST['product_id'];
    if (!isset($_SESSION['compare'])) $_SESSION['compare'] = [];

    if (in_array($pid, $_SESSION['compare'])) {
        $_SESSION['compare'] = array_diff($_SESSION['compare'], [$pid]);
        $state = 'removed'; $msg = 'ลบสินค้าจากการเปรียบเทียบ';
    } else {
        if (count($_SESSION['compare']) >= 4) {
            $response = ['status' => 'error', 'message' => 'เปรียบเทียบได้สูงสุด 4 ชิ้น'];
            echo json_encode($response); exit(); 
        }
        $_SESSION['compare'][] = $pid;
        $state = 'added'; $msg = 'เพิ่มสินค้าเพื่อเปรียบเทียบ';
    }
    $response = ['status' => 'success', 'state' => $state, 'message' => $msg, 'count' => count($_SESSION['compare'])];
}

// --- 3. Contact Us (ติดต่อเรา) ---
if ($action == 'send_contact') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);

    $sql = "INSERT INTO contact_messages (name, email, subject, message) VALUES ('$name', '$email', '$subject', '$message')";
    if (mysqli_query($conn, $sql)) {
        $response = ['status' => 'success', 'message' => 'ส่งข้อความเรียบร้อย เราจะติดต่อกลับโดยเร็วที่สุด'];
    } else {
        $response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . mysqli_error($conn)];
    }
}

ob_end_clean();
echo json_encode($response);
?>