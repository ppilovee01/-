<?php
// เริ่มเก็บ Buffer (ป้องกัน Error หน้าขาว)
ob_start();

session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');

// ปิด Error เพื่อให้ JSON สมบูรณ์
error_reporting(0);
ini_set('display_errors', 0);

$action = $_POST['action'] ?? '';
$response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาด'];

// --- 1. เพิ่มสินค้า (Add) ---
if (($action == '' || $action == 'add') && isset($_POST['product_id']) && !isset($_POST['type'])) {
    $id = mysqli_real_escape_string($conn, $_POST['product_id']);
    $qty = isset($_POST['qty']) ? intval($_POST['qty']) : 1;
    $opts = isset($_POST['options']) ? $_POST['options'] : '';

    $q = mysqli_query($conn, "SELECT stock FROM products WHERE id='$id'");
    $row = mysqli_fetch_assoc($q);

    if ($row && $row['stock'] >= $qty) {
        $cartKey = $id . ($opts ? '_' . md5($opts) : '');

        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

        if (isset($_SESSION['cart'][$cartKey])) {
            if (is_array($_SESSION['cart'][$cartKey])) {
                $_SESSION['cart'][$cartKey]['qty'] += $qty;
            } else {
                $_SESSION['cart'][$cartKey] += $qty;
            }
        } else {
            $_SESSION['cart'][$cartKey] = ['id' => $id, 'qty' => $qty, 'options' => $opts];
        }
        $response = ['status' => 'success', 'message' => 'เพิ่มลงตะกร้าแล้ว', 'cart_count' => count_cart_items()];
    } else {
        $response = ['status' => 'error', 'message' => 'สินค้ามีไม่เพียงพอ'];
    }
}

// --- 2. อัปเดตจำนวน (Update Qty) ---
elseif ($action == 'update_qty') {
    $id = $_POST['product_id'];
    $type = $_POST['type'];

    if (isset($_SESSION['cart'][$id])) {
        $current_qty = is_array($_SESSION['cart'][$id]) ? $_SESSION['cart'][$id]['qty'] : $_SESSION['cart'][$id];
        $real_id = is_array($_SESSION['cart'][$id]) ? $_SESSION['cart'][$id]['id'] : $id;
        
        $q = mysqli_query($conn, "SELECT stock, price FROM products WHERE id='$real_id'");
        $row = mysqli_fetch_assoc($q);

        if ($type == 'inc' && $row['stock'] > $current_qty) {
            if(is_array($_SESSION['cart'][$id])) $_SESSION['cart'][$id]['qty']++;
            else $_SESSION['cart'][$id]++;
        } elseif ($type == 'dec' && $current_qty > 1) {
            if(is_array($_SESSION['cart'][$id])) $_SESSION['cart'][$id]['qty']--;
            else $_SESSION['cart'][$id]--;
        } else {
            $response = ['status' => 'error', 'message' => 'สินค้ามีจำกัด'];
            ob_end_clean(); echo json_encode($response); exit();
        }

        $new_qty = is_array($_SESSION['cart'][$id]) ? $_SESSION['cart'][$id]['qty'] : $_SESSION['cart'][$id];
        $line_total = $row['price'] * $new_qty;
        
        $cart_data = calculate_cart_totals($conn);
        $response = [
            'status' => 'success',
            'new_qty' => $new_qty,
            'line_total' => number_format($line_total),
            'subtotal' => number_format($cart_data['subtotal'], 2),
            'discount' => number_format($cart_data['discount'], 2),
            'final_total' => number_format($cart_data['final'], 2),
            'cart_count' => count_cart_items()
        ];
    }
}

// --- 3. ลบสินค้า (Remove) ---
elseif ($action == 'remove_item') {
    $id = $_POST['product_id'];
    if (isset($_SESSION['cart'][$id])) {
        unset($_SESSION['cart'][$id]);
        
        $cart_data = calculate_cart_totals($conn);
        $response = [
            'status' => 'success',
            'message' => 'ลบสินค้าเรียบร้อย',
            'subtotal' => number_format($cart_data['subtotal'], 2),
            'discount' => number_format($cart_data['discount'], 2),
            'final_total' => number_format($cart_data['final'], 2),
            'cart_count' => count_cart_items()
        ];
    }
}

ob_end_clean();
echo json_encode($response);

// --- ฟังก์ชันช่วยคำนวณ ---
function count_cart_items() {
    $c = 0;
    if(isset($_SESSION['cart'])) {
        foreach($_SESSION['cart'] as $item) {
            $c += is_array($item) ? $item['qty'] : $item;
        }
    }
    return $c;
}

function calculate_cart_totals($conn) {
    $subtotal = 0;
    if(isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $k => $item) {
            $pid = is_array($item) ? $item['id'] : $k;
            $qty = is_array($item) ? $item['qty'] : $item;
            
            $res = mysqli_query($conn, "SELECT price FROM products WHERE id='$pid'");
            $r = mysqli_fetch_assoc($res);
            if($r) $subtotal += $r['price'] * $qty;
        }
    }

    $discount = 0;
    if (isset($_SESSION['coupon']) && $subtotal > 0) {
        $c = $_SESSION['coupon'];
        $discount = ($c['type'] == 'fixed') ? $c['value'] : ($subtotal * $c['value'] / 100);
        if($discount > $subtotal) $discount = $subtotal;
    }

    return ['subtotal' => $subtotal, 'discount' => $discount, 'final' => $subtotal - $discount];
}
?>