<?php
ob_start();
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');

// ปิด Error เพื่อให้ส่ง JSON ได้สมบูรณ์
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $response = ['status' => 'error', 'message' => 'คำขอไม่ถูกต้องหรือหมดเวลาเซสชัน (Invalid CSRF Token)'];
        ob_end_clean();
        echo json_encode($response);
        exit();
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาด'];

// ==========================================
// 1. ตรวจสอบสิทธิ์สำหรับแอกชันที่ต้องการสิทธิ์สมาชิก
// ==========================================
$member_actions = ['update_profile', 'change_password', 'add_address', 'delete_address', 'toggle_wishlist', 'reorder', 'wishlist_to_cart'];
if (in_array($action, $member_actions) && !isset($_SESSION['user_id'])) {
    $response = ['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อน'];
    ob_end_clean();
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;

// ==========================================
// 2. ฟีเจอร์ที่เกี่ยวข้องกับตะกร้าสินค้า (Cart Actions)
// ==========================================

// 2.1 เพิ่มสินค้าลงในตะกร้า (Add to Cart)
if ($action == 'add' && isset($_POST['product_id'])) {
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

// 2.2 อัปเดตจำนวนสินค้า (Update Quantity)
elseif ($action == 'update_qty') {
    $id = $_POST['product_id'];
    $type = $_POST['type'];

    if (isset($_SESSION['cart'][$id])) {
        $current_qty = is_array($_SESSION['cart'][$id]) ? $_SESSION['cart'][$id]['qty'] : $_SESSION['cart'][$id];
        $real_id = is_array($_SESSION['cart'][$id]) ? $_SESSION['cart'][$id]['id'] : $id;
        
        $q = mysqli_query($conn, "SELECT stock, price FROM products WHERE id='$real_id'");
        $row = mysqli_fetch_assoc($q);
        if ($row) {
            $row['price'] = getCurrentPrice($conn, $real_id);
        }

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
        $line_total = getProductTotalPrice($conn, $real_id, $new_qty);
        
        $cart_data = calculate_cart_totals($conn);
        $response = [
            'status' => 'success',
            'new_qty' => $new_qty,
            'line_total' => number_format($line_total),
            'price_desc' => getProductPriceText($conn, $real_id, $new_qty),
            'subtotal' => number_format($cart_data['subtotal'], 2),
            'discount' => number_format($cart_data['discount'], 2),
            'shipping_fee' => number_format($cart_data['shipping_fee'], 2),
            'shipping_fee_fixed' => $cart_data['shipping_fee_fixed'],
            'shipping_free_threshold' => $cart_data['shipping_free_threshold'],
            'final_total' => number_format($cart_data['final'], 2),
            'cart_count' => count_cart_items()
        ];
    }
}

// 2.3 ลบสินค้าออกจากตะกร้า (Remove Item)
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
            'shipping_fee' => number_format($cart_data['shipping_fee'], 2),
            'shipping_fee_fixed' => $cart_data['shipping_fee_fixed'],
            'shipping_free_threshold' => $cart_data['shipping_free_threshold'],
            'final_total' => number_format($cart_data['final'], 2),
            'cart_count' => count_cart_items()
        ];
    }
}

// 2.3.1 สั่งซื้อสินค้าซ้ำด่วน (Quick Re-Order)
elseif ($action == 'reorder') {
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    
    // ตรวจสอบสิทธิ์ว่าออเดอร์นั้นเป็นของผู้ใช้นี้จริงหรือไม่ (ป้องกัน IDOR)
    $order_q = mysqli_query($conn, "SELECT id FROM orders WHERE id = '$order_id' AND user_id = '$user_id'");
    if (mysqli_num_rows($order_q) == 0) {
        $response = ['status' => 'error', 'message' => 'ไม่พบข้อมูลคำสั่งซื้อที่ถูกต้อง'];
        ob_end_clean(); echo json_encode($response); exit();
    }
    
    // ดึงรายการสินค้าเดิม
    $items_q = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id = '$order_id'");
    $added_count = 0;
    $skipped_names = [];
    
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    
    while ($item = mysqli_fetch_assoc($items_q)) {
        $pid = $item['product_id'];
        $qty = intval($item['quantity']);
        $opts = $item['selected_option'] ?? '';
        
        // ดึงชื่อและเช็คสต๊อกล่าสุดจากสินค้า
        $p_q = mysqli_query($conn, "SELECT name, stock FROM products WHERE id = '$pid'");
        $prod = mysqli_fetch_assoc($p_q);
        
        if ($prod) {
            if ($prod['stock'] >= $qty) {
                $cartKey = $pid . ($opts ? '_' . md5($opts) : '');
                
                if (isset($_SESSION['cart'][$cartKey])) {
                    if (is_array($_SESSION['cart'][$cartKey])) {
                        $_SESSION['cart'][$cartKey]['qty'] += $qty;
                    } else {
                        $_SESSION['cart'][$cartKey] += $qty;
                    }
                } else {
                    $_SESSION['cart'][$cartKey] = ['id' => $pid, 'qty' => $qty, 'options' => $opts];
                }
                $added_count++;
            } else {
                $skipped_names[] = $prod['name'];
            }
        }
    }
    
    if ($added_count > 0) {
        $msg = 'เพิ่มสินค้าเข้าตะกร้าเรียบร้อยแล้ว ' . $added_count . ' รายการ';
        if (!empty($skipped_names)) {
            $msg .= ' (บางชิ้นหมดสต๊อก: ' . implode(', ', $skipped_names) . ')';
        }
        $response = [
            'status' => 'success',
            'message' => $msg,
            'cart_count' => count_cart_items()
        ];
    } else {
        $response = [
            'status' => 'error',
            'message' => 'ไม่สามารถสั่งซื้อซ้ำได้ เนื่องจากสินค้าในออเดอร์นี้หมดสต๊อกทั้งหมดแล้ว'
        ];
    }
}

// 2.3.2 ย้ายสินค้าจากรายการโปรดเข้าตะกร้า (Wishlist to Cart)
elseif ($action == 'wishlist_to_cart') {
    $pid = isset($_POST['product_id']) ? mysqli_real_escape_string($conn, $_POST['product_id']) : '';
    $opts = isset($_POST['options']) ? $_POST['options'] : '';
    $qty = isset($_POST['qty']) ? intval($_POST['qty']) : 1;
    
    $q = mysqli_query($conn, "SELECT name, stock FROM products WHERE id='$pid'");
    $prod = mysqli_fetch_assoc($q);
    
    if ($prod && $prod['stock'] >= $qty) {
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        
        $cartKey = $pid . ($opts ? '_' . md5($opts) : '');
        
        if (isset($_SESSION['cart'][$cartKey])) {
            if (is_array($_SESSION['cart'][$cartKey])) {
                $_SESSION['cart'][$cartKey]['qty'] += $qty;
            } else {
                $_SESSION['cart'][$cartKey] += $qty;
            }
        } else {
            $_SESSION['cart'][$cartKey] = ['id' => $pid, 'qty' => $qty, 'options' => $opts];
        }
        
        // ลบออกจากรายการโปรด
        mysqli_query($conn, "DELETE FROM wishlist WHERE user_id='$user_id' AND product_id='$pid'");
        
        $response = [
            'status' => 'success',
            'message' => 'ย้ายสินค้า ' . htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8') . ' ลงตะกร้าแล้ว',
            'cart_count' => count_cart_items()
        ];
    } else {
        $response = ['status' => 'error', 'message' => 'ขออภัย สินค้ามีจำนวนไม่พอในสต๊อก'];
    }
}

// 2.4 ดึงข้อมูลตะกร้าสินค้าสำหรับสไลด์ข้าง (Get Cart Drawer Data)
elseif ($action == 'get_cart_drawer') {
    $html = '';
    $cart_data = calculate_cart_totals($conn);
    
    if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $k => $item) {
            $pid = is_array($item) ? $item['id'] : $k;
            $qty = is_array($item) ? $item['qty'] : $item;
            $opts = is_array($item) ? ($item['options'] ?? '') : '';
            
            // ดึงข้อมูลสินค้า
            $q = mysqli_query($conn, "SELECT name, image, price FROM products WHERE id='$pid'");
            $prod = mysqli_fetch_assoc($q);
            if ($prod) {
                // เช็คราคาโปรโมชัน ณ ปัจจุบัน (เช่น Flash Sale)
                $price = getCurrentPrice($conn, $pid);
                $original_price = $prod['price'];
                $item_total = getProductTotalPrice($conn, $pid, $qty);
                $price_desc = getProductPriceText($conn, $pid, $qty);
                
                $html .= '
                <div class="cart-drawer-item" id="drawer-item-' . $k . '">
                    <img src="' . htmlspecialchars($prod['image']) . '" alt="' . htmlspecialchars($prod['name']) . '">
                    <div class="cart-drawer-item-info">
                        <div class="cart-drawer-item-title" title="' . htmlspecialchars($prod['name']) . '">' . htmlspecialchars($prod['name']) . '</div>
                        <div class="text-muted small mb-1" style="font-size: 0.75rem;">' . $price_desc . '</div>';
                
                if (!empty($opts)) {
                    $html .= '<div class="cart-drawer-item-option">' . htmlspecialchars($opts) . '</div>';
                }
                
                $html .= '
                        <div class="d-flex align-items-center justify-content-between mt-1">
                            <div class="cart-drawer-item-qty">
                                <button type="button" class="btn-qty-drawer" onclick="updateQtyDrawer(\'' . $k . '\', \'dec\')">-</button>
                                <span class="qty-drawer-val">' . $qty . '</span>
                                <button type="button" class="btn-qty-drawer" onclick="updateQtyDrawer(\'' . $k . '\', \'inc\')">+</button>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-primary" style="font-size: 0.95rem;">฿' . number_format($item_total, 2) . '</div>';
                
                if ($price < $original_price) {
                    $html .= '<div class="text-decoration-line-through text-muted" style="font-size: 0.75rem;">฿' . number_format($original_price, 2) . '</div>';
                }
                
                $html .= '
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-remove-drawer" onclick="removeDrawerItem(\'' . $k . '\')" title="ลบรายการนี้">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>';
            }
        }
    } else {
        $html = '
        <div class="text-center py-5 text-muted">
            <i class="bi bi-bag-x display-4 mb-3 d-block opacity-25"></i>
            <div>ไม่มีสินค้าในตะกร้าของคุณ</div>
            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill mt-3 px-4" onclick="toggleCartDrawer()">ไปเลือกสินค้า</button>
        </div>';
    }
    
    $response = [
        'status' => 'success',
        'html' => $html,
        'subtotal' => number_format($cart_data['subtotal'], 2),
        'discount' => number_format($cart_data['discount'], 2),
        'shipping_fee' => number_format($cart_data['shipping_fee'], 2),
        'shipping_fee_fixed' => $cart_data['shipping_fee_fixed'],
        'shipping_free_threshold' => $cart_data['shipping_free_threshold'],
        'final_total' => number_format($cart_data['final'], 2),
        'cart_count' => count_cart_items()
    ];
}

// ==========================================
// 3. ฟีเจอร์ที่เกี่ยวข้องกับบัญชีผู้ใช้ (Profile Actions)
// ==========================================

// 3.1 อัปเดตข้อมูลส่วนตัว (Update Profile Info)
elseif ($action == 'update_profile') {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // เช็คอีเมลซ้ำ
    $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' AND id != '$user_id'");
    if (mysqli_num_rows($check) > 0) {
        $response = ['status' => 'error', 'message' => 'อีเมลนี้มีผู้ใช้งานแล้ว'];
    } else {
        $sql = "UPDATE users SET fullname = '$fullname', email = '$email' WHERE id = '$user_id'";
        if (mysqli_query($conn, $sql)) {
            log_admin_action($conn, 'แก้ไขข้อมูลส่วนตัว', "ลูกค้าแก้ไขข้อมูลส่วนตัว: อีเมลใหม่ = $email, ชื่อ = $fullname", $user_id, $fullname);
            $_SESSION['fullname'] = $fullname;
            $response = ['status' => 'success', 'message' => 'บันทึกข้อมูลเรียบร้อย', 'fullname' => $fullname];
        } else {
            $response = ['status' => 'error', 'message' => 'บันทึกข้อมูลไม่สำเร็จ'];
        }
    }
}

// 3.2 เปลี่ยนรหัสผ่าน (Change Password)
elseif ($action == 'change_password') {
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    $res = mysqli_query($conn, "SELECT password FROM users WHERE id = '$user_id'");
    $row = mysqli_fetch_assoc($res);

    if (!password_verify($old_pass, $row['password'])) {
        $response = ['status' => 'error', 'message' => 'รหัสผ่านเดิมไม่ถูกต้อง'];
    } elseif ($new_pass !== $confirm_pass) {
        $response = ['status' => 'error', 'message' => 'รหัสผ่านใหม่ไม่ตรงกัน'];
    } elseif (strlen($new_pass) < 6) {
        $response = ['status' => 'error', 'message' => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร'];
    } else {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET password = '$hash' WHERE id = '$user_id'");
        log_admin_action($conn, 'เปลี่ยนรหัสผ่าน', "ลูกค้าทำการเปลี่ยนรหัสผ่านใหม่สำเร็จ", $user_id, $_SESSION['fullname']);
        $response = ['status' => 'success', 'message' => 'เปลี่ยนรหัสผ่านสำเร็จ!'];
    }
}

// 3.3 เพิ่มที่อยู่จัดส่ง (Add Address)
elseif ($action == 'add_address') {
    $name = mysqli_real_escape_string($conn, $_POST['recipient_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $addr = mysqli_real_escape_string($conn, $_POST['address_line1']);
    $sub = mysqli_real_escape_string($conn, $_POST['subdistrict']);
    $dist = mysqli_real_escape_string($conn, $_POST['district']);
    $prov = mysqli_real_escape_string($conn, $_POST['province']);
    $zip = mysqli_real_escape_string($conn, $_POST['zipcode']);

    $sql = "INSERT INTO user_addresses (user_id, recipient_name, phone, address_line1, subdistrict, district, province, zipcode) 
            VALUES ('$user_id', '$name', '$phone', '$addr', '$sub', '$dist', '$prov', '$zip')";
    
    if (mysqli_query($conn, $sql)) {
        $new_id = mysqli_insert_id($conn);
        log_admin_action($conn, 'เพิ่มที่อยู่', "ลูกค้าเพิ่มที่อยู่จัดส่งใหม่: $name ($phone) - ที่อยู่: $addr $sub $dist $prov $zip", $user_id, $_SESSION['fullname']);
        // สร้าง HTML การ์ดที่อยู่เพื่อส่งกลับไปแปะ
        // Security Fix: ป้องกัน XSS โดย escape ข้อมูลผู้ใช้ก่อนแสดงผลใน HTML
        $html = '
        <div class="col-md-6 animate__animated animate__fadeIn" id="addr-'.$new_id.'">
            <div class="address-item h-100">
                <div class="fw-bold text-dark mb-1 fs-5">'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'</div>
                <div class="text-muted small mb-2"><i class="bi bi-telephone"></i> '.htmlspecialchars($phone, ENT_QUOTES, 'UTF-8').'</div>
                <div class="small text-secondary" style="line-height: 1.5;">
                    '.htmlspecialchars($addr, ENT_QUOTES, 'UTF-8').'<br>
                    '.htmlspecialchars($sub, ENT_QUOTES, 'UTF-8').' '.htmlspecialchars($dist, ENT_QUOTES, 'UTF-8').'<br>
                    '.htmlspecialchars($prov, ENT_QUOTES, 'UTF-8').' '.htmlspecialchars($zip, ENT_QUOTES, 'UTF-8').'
                </div>
                <div class="btn-del-addr" onclick="deleteAddress('.$new_id.')">
                    <i class="bi bi-trash"></i>
                </div>
            </div>
        </div>';
        
        $response = ['status' => 'success', 'message' => 'เพิ่มที่อยู่เรียบร้อย', 'html' => $html, 'new_address_id' => $new_id];
    } else {
        $response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการเพิ่มที่อยู่'];
    }
}

// 3.4 ลบที่อยู่จัดส่ง (Delete Address)
elseif ($action == 'delete_address') {
    $addr_id = mysqli_real_escape_string($conn, $_POST['address_id']);
    $addr_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT recipient_name, phone FROM user_addresses WHERE id='$addr_id' AND user_id='$user_id'"));
    $recipient_name = $addr_info['recipient_name'] ?? '';
    $phone = $addr_info['phone'] ?? '';
    
    if(mysqli_query($conn, "DELETE FROM user_addresses WHERE id='$addr_id' AND user_id='$user_id'")) {
        log_admin_action($conn, 'ลบที่อยู่', "ลูกค้าลบที่อยู่จัดส่ง ID #$addr_id ของคุณ $recipient_name ($phone)", $user_id, $_SESSION['fullname']);
        $response = ['status' => 'success', 'message' => 'ลบที่อยู่แล้ว'];
    } else {
        $response = ['status' => 'error', 'message' => 'ลบไม่สำเร็จ'];
    }
}

// ==========================================
// 4. ฟีเจอร์ทั่วไป (General Features Actions)
// ==========================================

// 4.1 รายการสินค้าโปรด (Toggle Wishlist)
elseif ($action == 'toggle_wishlist') {
    $pid = mysqli_real_escape_string($conn, $_POST['product_id']);

    $check = mysqli_query($conn, "SELECT id FROM wishlist WHERE user_id='$user_id' AND product_id='$pid'");
    if (mysqli_num_rows($check) > 0) {
        mysqli_query($conn, "DELETE FROM wishlist WHERE user_id='$user_id' AND product_id='$pid'");
        $state = 'removed'; $msg = 'ลบจากรายการโปรดแล้ว';
    } else {
        mysqli_query($conn, "INSERT INTO wishlist (user_id, product_id) VALUES ('$user_id', '$pid')");
        $state = 'added'; $msg = 'เพิ่มในรายการโปรดแล้ว';
    }
    $response = ['status' => 'success', 'state' => $state, 'message' => $msg];
}

// 4.2 ติดต่อเรา (Contact Messages Submissions)
elseif ($action == 'send_contact') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);

    $sql = "INSERT INTO contact_messages (name, email, subject, message) VALUES ('$name', '$email', '$subject', '$message')";
    if (mysqli_query($conn, $sql)) {
        $response = ['status' => 'success', 'message' => 'ส่งข้อความเรียบร้อย เราจะติดต่อกลับโดยเร็วที่สุด'];
    } else {
        // Security Fix: ไม่แสดง mysqli_error ให้ผู้ใช้เห็น ป้องกันการรั่วไหลของข้อมูล DB
        error_log('Contact form DB error: ' . mysqli_error($conn));
        $response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการส่งข้อความ กรุณาลองใหม่อีกครั้ง'];
    }
}

// 4.3 ระบบสืบค้นด่วน (Live Search Suggestion)
elseif ($action == 'search_suggest') {
    $q = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
    // Security Fix: ป้องกัน LIKE wildcard injection โดย escape ตัวอักษรพิเศษ % และ _
    $q = addcslashes($q, '%_');
    $products = [];
    if (strlen($q) >= 2) {
        $res = mysqli_query($conn, "SELECT id, name, price, image FROM products WHERE name LIKE '%$q%' LIMIT 5");
        while ($row = mysqli_fetch_assoc($res)) {
            $products[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'price' => number_format($row['price']),
                'image' => $row['image']
            ];
        }
    }
    $response = ['status' => 'success', 'data' => $products];
}

// 4.4 ดึงข้อมูลแจ้งเตือน (Get Notifications)
elseif ($action == 'get_notifications') {
    $notifications = [];
    $unread_count = 0;
    
    $uid = $_SESSION['user_id'] ?? null;
    $is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 1 : 0;
    
    if ($is_admin) {
        $res = mysqli_query($conn, "SELECT * FROM notifications WHERE is_admin = 1 ORDER BY created_at DESC LIMIT 15");
        $unread_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE is_admin = 1 AND is_read = 0");
        $unread_count = mysqli_fetch_assoc($unread_res)['count'] ?? 0;
    } elseif ($uid) {
        $res = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id = '$uid' AND is_admin = 0 ORDER BY created_at DESC LIMIT 15");
        $unread_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = '$uid' AND is_admin = 0 AND is_read = 0");
        $unread_count = mysqli_fetch_assoc($unread_res)['count'] ?? 0;
    } else {
        $res = false;
    }

    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $time_ago = time_elapsed_string($row['created_at']);
            $notifications[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'message' => $row['message'],
                'url' => $row['url'],
                'is_read' => intval($row['is_read']),
                'time_ago' => $time_ago
            ];
        }
    }
    
    $response = [
        'status' => 'success',
        'notifications' => $notifications,
        'unread_count' => intval($unread_count)
    ];
}

// 4.5 อ่านแจ้งเตือน (Mark Notifications as Read)
elseif ($action == 'mark_read') {
    $uid = $_SESSION['user_id'] ?? null;
    $is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 1 : 0;
    $nid = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;
    
    if ($nid > 0) {
        if ($is_admin) {
            mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE id = '$nid' AND is_admin = 1");
        } elseif ($uid) {
            mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE id = '$nid' AND user_id = '$uid' AND is_admin = 0");
        }
    } else {
        if ($is_admin) {
            mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE is_admin = 1");
        } elseif ($uid) {
            mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = '$uid' AND is_admin = 0");
        }
    }
    $response = ['status' => 'success'];
}

// 4.6 ลบแจ้งเตือนทั้งหมด (Clear All Notifications)
elseif ($action == 'clear_notifications') {
    $uid = $_SESSION['user_id'] ?? null;
    $is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 1 : 0;
    
    if ($is_admin) {
        mysqli_query($conn, "DELETE FROM notifications WHERE is_admin = 1");
    } elseif ($uid) {
        mysqli_query($conn, "DELETE FROM notifications WHERE user_id = '$uid' AND is_admin = 0");
    }
    $response = ['status' => 'success'];
}

// 4.7 ดึงข้อมูลคูปองที่พร้อมใช้ (Get Available Coupons)
elseif ($action == 'get_available_coupons') {
    $cart_data = calculate_cart_totals($conn);
    $subtotal = $cart_data['subtotal'];
    
    // ใช้ MySQL NOW() เพื่อป้องกันปัญหา timezone ระหว่าง PHP กับ MySQL server
    $query = mysqli_query($conn, "SELECT * FROM coupons WHERE status='active' AND (start_date IS NULL OR start_date <= NOW()) AND expiry_date >= NOW() ORDER BY expiry_date ASC");
    $html = '<div class="row g-3">';
    $count = 0;
    
    if ($query && mysqli_num_rows($query) > 0) {
        while ($c = mysqli_fetch_assoc($query)) {
            $count++;
            $code = htmlspecialchars($c['code']);
            $badge_title = $c['discount_type'] == 'free_shipping' ? 'จัดส่ง' : 'ลดทันที';
            if ($c['discount_type'] == 'free_shipping') {
                $discount_text = 'ส่งฟรี';
            } else {
                $discount_text = $c['discount_type'] == 'percent' ? intval($c['discount_value']) . '%' : '฿' . number_format($c['discount_value']);
            }
            $min_spend = floatval($c['min_spend']);
            $expiry = date('d/m/Y H:i', strtotime($c['expiry_date']));
            
            // Check usage limit
            $is_claimed_out = false;
            if ($c['usage_limit'] > 0) {
                $total_used = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE coupon_code = '{$c['code']}' AND status != 'cancelled'"))['count'];
                if ($total_used >= $c['usage_limit']) {
                    $is_claimed_out = true;
                }
            }
            
            // Check user limit
            $is_user_out = false;
            if ($user_id && $c['user_limit'] > 0) {
                $user_used = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE coupon_code = '{$c['code']}' AND user_id = '$user_id' AND status != 'cancelled'"))['count'];
                if ($user_used >= $c['user_limit']) {
                    $is_user_out = true;
                }
            }
            
            // Check min spend
            $is_low_spend = $subtotal < $min_spend;
            
            $is_eligible = !$is_claimed_out && !$is_user_out && !$is_low_spend;
            
            $status_msg = '';
            if ($is_claimed_out) {
                $status_msg = 'สิทธิ์การใช้งานเต็มแล้ว';
            } elseif ($is_user_out) {
                $status_msg = 'คุณใช้สิทธิ์คูปองนี้ครบแล้ว';
            } elseif ($is_low_spend) {
                $diff = $min_spend - $subtotal;
                $status_msg = 'ช้อปเพิ่มอีก ฿' . number_format($diff, 2) . ' เพื่อใช้โค้ดนี้';
            }
            
            $card_class = $is_eligible ? 'coupon-card-eligible' : 'coupon-card-disabled';
            $btn_html = '';
            if ($is_eligible) {
                if (isset($_SESSION['coupon']) && $_SESSION['coupon']['code'] === $c['code']) {
                    $btn_html = '<button class="btn btn-sm btn-success rounded-pill px-3" disabled><i class="bi bi-check2-circle"></i> ใช้งานอยู่</button>';
                } else {
                    $btn_html = '<button class="btn btn-sm btn-primary rounded-pill px-3 btn-apply-coupon" data-code="' . $code . '">ใช้โค้ด</button>';
                }
            } else {
                $btn_html = '<button class="btn btn-sm btn-secondary rounded-pill px-3" disabled>ไม่สามารถใช้ได้</button>';
            }
            
            $html .= '
            <div class="col-12">
                <div class="coupon-item-card ' . $card_class . ' p-3 d-flex align-items-center justify-content-between rounded-3 border bg-white shadow-sm" style="transition: all 0.2s ease;">
                    <div class="d-flex align-items-center">
                        <div class="coupon-badge text-center me-3 p-2 rounded-3 text-white fw-bold d-flex flex-column justify-content-center align-items-center" style="min-width: 75px; height: 75px; background: linear-gradient(135deg, #7FB5FF, #AEE2FF);">
                            <span class="small" style="font-size: 0.65rem; font-weight: normal; opacity: 0.95;">' . $badge_title . '</span>
                            <span style="font-size: 1.1rem;">' . $discount_text . '</span>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-light text-primary border border-primary font-monospace px-2 py-1" style="font-size: 0.85rem; letter-spacing: 0.5px;">' . $code . '</span>
                            </div>
                            <div class="small mt-1 text-muted" style="font-size: 0.8rem;">';
            $max_discount = floatval($c['max_discount']);
            if ($min_spend > 0) {
                $html .= 'ยอดขั้นต่ำ ฿' . number_format($min_spend);
            } else {
                $html .= 'ไม่มีขั้นต่ำ';
            }
            if ($max_discount > 0 && $c['discount_type'] == 'percent') {
                $html .= ' • ลดสูงสุด ฿' . number_format($max_discount);
            }
            $html .= ' • หมดอายุ: ' . $expiry;
            $html .= '</div>';
            
            if (!$is_eligible && !empty($status_msg)) {
                $html .= '<div class="small text-danger mt-1 fw-bold" style="font-size: 0.75rem;"><i class="bi bi-info-circle me-1"></i>' . $status_msg . '</div>';
            }
            
            $html .= '
                        </div>
                    </div>
                    <div>
                        ' . $btn_html . '
                    </div>
                </div>
            </div>';
        }
    } else {
        $html .= '
        <div class="col-12 text-center py-4 text-muted">
            <i class="bi bi-ticket-perforated display-5 mb-2 opacity-25"></i>
            <div>ไม่มีคูปองส่วนลดที่พร้อมใช้งานในขณะนี้</div>
        </div>';
    }
    
    $html .= '</div>';
    $response = ['status' => 'success', 'html' => $html, 'count' => $count];
}

// 4.8 ดึงข้อมูลสถิติแดชบอร์ดแอดมินตามช่วงเวลา (Get Admin Dashboard Stats)
elseif ($action == 'get_dashboard_stats') {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        $response = ['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึง'];
        ob_end_clean(); echo json_encode($response); exit();
    }
    
    $preset = mysqli_real_escape_string($conn, $_GET['preset'] ?? '');
    $start_date = mysqli_real_escape_string($conn, $_GET['start_date'] ?? '');
    $end_date = mysqli_real_escape_string($conn, $_GET['end_date'] ?? '');

    $today = date('Y-m-d');
    if ($preset == '7days') {
        $start_date = date('Y-m-d', strtotime('-6 days'));
        $end_date = $today;
    } elseif ($preset == '30days') {
        $start_date = date('Y-m-d', strtotime('-29 days'));
        $end_date = $today;
    } elseif ($preset == 'this_month') {
        $start_date = date('Y-m-01');
        $end_date = $today;
    } elseif ($preset == 'this_year') {
        $start_date = date('Y-01-01');
        $end_date = $today;
    } elseif ($preset == 'custom') {
        if (empty($start_date)) $start_date = date('Y-m-d', strtotime('-6 days'));
        if (empty($end_date)) $end_date = $today;
    } else {
        $start_date = date('Y-m-d', strtotime('-6 days'));
        $end_date = $today;
    }
    
    // คำนวณยอดขายรวม
    $q_sales = mysqli_query($conn, "SELECT SUM(final_price) as total FROM orders WHERE DATE(order_date) BETWEEN '$start_date' AND '$end_date' AND status != 'cancelled'");
    $r_sales = mysqli_fetch_assoc($q_sales);
    $sales_val = floatval($r_sales['total'] ?? 0);

    // คำนวณกำไรสุทธิ (FIFO)
    $q_cost = mysqli_query($conn, "SELECT SUM(oi.quantity * oi.import_cost) as total_cost 
                                   FROM order_items oi 
                                   JOIN orders o ON oi.order_id = o.id 
                                   WHERE DATE(o.order_date) BETWEEN '$start_date' AND '$end_date' AND o.status != 'cancelled'");
    $r_cost = mysqli_fetch_assoc($q_cost);
    $cost_val = floatval($r_cost['total_cost'] ?? 0);
    $profit_val = $sales_val - $cost_val;
    
    // ค่าคงเดิม (System snap) เพื่ออัปเดตตัวเลขแผงควบคุม
    $q_pending = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
    $pending_val = intval(mysqli_fetch_assoc($q_pending)['count'] ?? 0);
    
    $q_users = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'user'");
    $users_val = intval(mysqli_fetch_assoc($q_users)['count'] ?? 0);
    
    $q_low = mysqli_query($conn, "SELECT COUNT(*) as count FROM products WHERE stock < 5");
    $low_val = intval(mysqli_fetch_assoc($q_low)['count'] ?? 0);
    
    // ดึงสถิติกราฟ
    $chart_dates = [];
    $chart_sales = [];
    $thai_months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    
    $diff_days = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);
    if ($diff_days > 31) {
        // Group by month
        $start_month = intval(date('m', strtotime($start_date)));
        $start_year = intval(date('Y', strtotime($start_date)));
        $end_month = intval(date('m', strtotime($end_date)));
        $end_year = intval(date('Y', strtotime($end_date)));
        
        $current_year = $start_year;
        $current_month = $start_month;
        
        while (($current_year < $end_year) || ($current_year == $end_year && $current_month <= $end_month)) {
            $q = mysqli_query($conn, "SELECT SUM(final_price) as total FROM orders WHERE YEAR(order_date) = '$current_year' AND MONTH(order_date) = '$current_month' AND status != 'cancelled'");
            $r = mysqli_fetch_assoc($q);
            $chart_dates[] = $thai_months[$current_month - 1] . ' ' . substr($current_year, 2);
            $chart_sales[] = floatval($r['total'] ?? 0);
            
            $current_month++;
            if ($current_month > 12) {
                $current_month = 1;
                $current_year++;
            }
        }
    } else {
        // Group by day
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $end->modify('+1 day');
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end);
        foreach ($period as $date) {
            $d = $date->format('Y-m-d');
            $q = mysqli_query($conn, "SELECT SUM(final_price) as total FROM orders WHERE DATE(order_date) = '$d' AND status != 'cancelled'");
            $r = mysqli_fetch_assoc($q);
            $chart_dates[] = $date->format('d/m');
            $chart_sales[] = floatval($r['total'] ?? 0);
        }
    }
    
    // กราฟสัดส่วนยอดขายตามหมวดหมู่
    $cat_names = [];
    $cat_revenues = [];
    $cat_sql = "SELECT c.name as cat_name, SUM(oi.quantity * oi.price) as revenue 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.id 
                JOIN categories c ON p.category_id = c.id 
                JOIN orders o ON oi.order_id = o.id 
                WHERE DATE(o.order_date) BETWEEN '$start_date' AND '$end_date' AND o.status != 'cancelled' 
                GROUP BY p.category_id 
                ORDER BY revenue DESC";
    $cat_res = mysqli_query($conn, $cat_sql);
    while ($cat_row = mysqli_fetch_assoc($cat_res)) {
        $cat_names[] = $cat_row['cat_name'];
        $cat_revenues[] = floatval($cat_row['revenue']);
    }
    
    // ตารางสินค้าขายดี Top 5
    $top5_sql = "SELECT p.name, p.image, p.stock, SUM(oi.quantity) as total_qty, SUM(oi.quantity * oi.price) as total_income
                 FROM order_items oi
                 JOIN products p ON oi.product_id = p.id
                 JOIN orders o ON oi.order_id = o.id
                 WHERE DATE(o.order_date) BETWEEN '$start_date' AND '$end_date' AND o.status != 'cancelled'
                 GROUP BY oi.product_id
                 ORDER BY total_qty DESC LIMIT 5";
    $top5_res = mysqli_query($conn, $top5_sql);
    
    $top5_html = '';
    if ($top5_res && mysqli_num_rows($top5_res) > 0) {
        $rank = 1;
        while ($item = mysqli_fetch_assoc($top5_res)) {
            $rank_class = "rank-" . $rank;
            $is_out = ($item['stock'] == 0);
            $top5_html .= '
            <tr>
                <td style="width: 40px;"><div class="rank-badge ' . ($rank <= 3 ? $rank_class : '') . '">' . $rank . '</div></td>
                <td>
                    <div class="d-flex align-items-center">
                        <img src="' . htmlspecialchars($item['image']) . '" style="width: 40px; height: 40px; border-radius: 10px; object-fit: cover; margin-right: 12px;">
                        <div>
                            <div class="fw-bold text-truncate" style="max-width: 150px;">' . htmlspecialchars($item['name']) . '</div>
                            <div class="small text-muted" style="font-size: 0.75rem;">
                                จำหน่ายแล้ว ' . $item['total_qty'] . ' ชิ้น
                                ' . ($is_out ? '<span class="badge bg-danger ms-1" style="font-size:0.6rem;">สินค้าหมด</span>' : '') . '
                            </div>
                        </div>
                    </div>
                </td>
                <td class="text-end fw-bold text-success small">+฿' . number_format($item['total_income']) . '</td>
            </tr>';
            $rank++;
        }
    } else {
        $top5_html = '<tr><td colspan="3" class="text-center text-muted py-5">ยังไม่มีข้อมูลการขายในช่วงเวลานี้</td></tr>';
    }
    
    $response = [
        'status' => 'success',
        'sales_total' => '฿' . number_format($sales_val),
        'profit_total' => '฿' . number_format($profit_val),
        'pending_count' => $pending_val . ' รายการ',
        'users_count' => $users_val . ' ท่าน',
        'low_stock_count' => $low_val . ' รายการ',
        'chart_dates' => $chart_dates,
        'chart_sales' => $chart_sales,
        'cat_names' => $cat_names,
        'cat_revenues' => $cat_revenues,
        'top5_html' => $top5_html
    ];
}

// 4.9 ดึงข้อมูลรีวิวสินค้าแบบกรองดาวและสื่อรูปภาพ (Get Filtered Product Reviews)
elseif ($action == 'get_filtered_reviews') {
    $pid = mysqli_real_escape_string($conn, $_POST['product_id'] ?? $_GET['product_id'] ?? '');
    $rating = mysqli_real_escape_string($conn, $_POST['rating'] ?? $_GET['rating'] ?? 'all');
    $has_image = intval($_POST['has_image'] ?? $_GET['has_image'] ?? 0);
    
    $where_clause = "WHERE r.product_id = '$pid'";
    if ($rating !== 'all') {
        $where_clause .= " AND r.rating = '$rating'";
    }
    if ($has_image == 1) {
        $where_clause .= " AND r.image IS NOT NULL AND r.image != ''";
    }
    
    $uid_clause = $user_id ? "'$user_id'" : "NULL";
    $sql = "SELECT r.*, u.fullname,
              (SELECT COUNT(*) FROM review_votes WHERE review_id = r.id) as helpful_count,
              (SELECT COUNT(*) FROM review_votes WHERE review_id = r.id AND user_id = $uid_clause) as user_voted
            FROM product_reviews r 
            JOIN users u ON r.user_id = u.id 
            $where_clause 
            ORDER BY helpful_count DESC, r.created_at DESC";
    $query = mysqli_query($conn, $sql);
    
    $html = '';
    $count = 0;
    if ($query && mysqli_num_rows($query) > 0) {
        while ($r = mysqli_fetch_assoc($query)) {
            $count++;
            $stars = '';
            for ($i = 1; $i <= 5; $i++) {
                $stars .= $i <= $r['rating'] ? '★' : '☆';
            }
            $img_html = '';
            if (!empty($r['image']) && file_exists($r['image'])) {
                $img_html = '
                <div class="mt-2">
                    <img src="' . htmlspecialchars($r['image']) . '" class="review-img-thumb img-thumbnail" onclick="showReviewImage(\'' . htmlspecialchars($r['image']) . '\', \'' . htmlspecialchars($r['fullname']) . '\')" alt="รูปรีวิว">
                </div>';
            }
            
            $voted = intval($r['user_voted'] ?? 0);
            $helpful_cnt = intval($r['helpful_count'] ?? 0);
            $btn_class = $voted ? 'btn-success text-white' : 'btn-outline-success';
            $vote_text = $voted ? 'โหวตแล้วว่ามีประโยชน์' : 'มีประโยชน์';
            
            $html .= '
            <div class="review-item animate__animated animate__fadeIn">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div>
                        <strong class="text-dark me-2">' . htmlspecialchars($r['fullname']) . '</strong>
                        <span class="text-warning small">' . $stars . '</span>
                    </div>
                    <small class="text-muted" style="font-size:0.8rem;">' . date('d/m/Y', strtotime($r['created_at'])) . '</small>
                </div>
                <p class="mb-2 text-secondary">' . htmlspecialchars($r['comment']) . '</p>
                ' . $img_html . '
                <div class="mt-2 text-start">
                    <button class="btn btn-sm ' . $btn_class . ' rounded-pill px-3" data-rid="' . $r['id'] . '" onclick="voteHelpful(this)" style="font-size: 0.75rem; font-weight: 500; cursor: pointer; border-color: var(--blue-hover); color: ' . ($voted ? 'white' : 'var(--blue-hover)') . '; background-color: ' . ($voted ? 'var(--blue-hover)' : 'transparent') . ';">
                        <i class="bi bi-hand-thumbs-up-fill me-1"></i> ' . $vote_text . ' (' . $helpful_cnt . ')
                    </button>
                </div>
            </div>';
        }
    } else {
        $html = '
        <div class="text-center py-5 text-muted opacity-50">
            <i class="bi bi-chat-square-quote display-3 d-block mb-3"></i>
            ยังไม่มีรีวิวสำหรับเงื่อนไขที่เลือก
        </div>';
    }
    
    $response = ['status' => 'success', 'html' => $html, 'count' => $count];
}

// 4.9.2 โหวตคะแนนรีวิวมีประโยชน์ (Helpful Review Vote)
elseif ($action == 'vote_review') {
    if (!$user_id) {
        $response = ['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนโหวตรีวิว'];
        ob_end_clean(); echo json_encode($response); exit();
    }
    
    $rid = isset($_POST['review_id']) ? intval($_POST['review_id']) : 0;
    
    // Check if review exists
    $chk_r = mysqli_query($conn, "SELECT id FROM product_reviews WHERE id = '$rid'");
    if (mysqli_num_rows($chk_r) == 0) {
        $response = ['status' => 'error', 'message' => 'ไม่พบข้อมูลรีวิว'];
        ob_end_clean(); echo json_encode($response); exit();
    }
    
    // Check if user has already voted
    $chk_vote = mysqli_query($conn, "SELECT id FROM review_votes WHERE review_id = '$rid' AND user_id = '$user_id'");
    $state = '';
    if (mysqli_num_rows($chk_vote) > 0) {
        // Remove vote
        mysqli_query($conn, "DELETE FROM review_votes WHERE review_id = '$rid' AND user_id = '$user_id'");
        $state = 'unvoted';
        $msg = 'ยกเลิกการโหวต';
    } else {
        // Add vote
        mysqli_query($conn, "INSERT INTO review_votes (review_id, user_id) VALUES ('$rid', '$user_id')");
        $state = 'voted';
        $msg = 'โหวตรีวิวมีประโยชน์สำเร็จ';
    }
    
    // Get new count
    $cnt_q = mysqli_query($conn, "SELECT COUNT(*) as count FROM review_votes WHERE review_id = '$rid'");
    $new_count = mysqli_fetch_assoc($cnt_q)['count'] ?? 0;
    
    $response = [
        'status' => 'success',
        'state' => $state,
        'message' => $msg,
        'helpful_count' => intval($new_count)
    ];
}

// 4.10 เก็บสะสมคูปองต้อนรับ (Claim Welcome Coupon)
elseif ($action == 'claim_welcome_coupon') {
    $code = mysqli_real_escape_string($conn, $_POST['coupon_code'] ?? '');
    
    // ใช้ MySQL NOW() เพื่อป้องกันปัญหา timezone ระหว่าง PHP กับ MySQL server
    $check = mysqli_query($conn, "SELECT * FROM coupons WHERE code='$code' AND status='active' AND (start_date IS NULL OR start_date <= NOW()) AND expiry_date >= NOW()");
    if (mysqli_num_rows($check) > 0) {
        $c = mysqli_fetch_assoc($check);
        
        $coupon_error = false;
        
        // ตรวจสอบสิทธิ์รวม
        if ($c['usage_limit'] > 0) {
            $total_used = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE coupon_code = '{$c['code']}' AND status != 'cancelled'"))['count'];
            if ($total_used >= $c['usage_limit']) {
                $coupon_error = true;
                $response = ['status' => 'error', 'message' => 'ขออภัย คูปองนี้สิทธิ์การใช้งานเต็มโควตาแล้ว'];
            }
        }
        
        // ตรวจสอบสิทธิ์ต่อคน
        if (!$coupon_error && $user_id && $c['user_limit'] > 0) {
            $user_used = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE coupon_code = '{$c['code']}' AND user_id = '$user_id' AND status != 'cancelled'"))['count'];
            if ($user_used >= $c['user_limit']) {
                $coupon_error = true;
                $response = ['status' => 'error', 'message' => 'ขออภัย คุณใช้สิทธิ์คูปองนี้ครบโควตาแล้ว'];
            }
        }
        
        if (!$coupon_error) {
            $_SESSION['coupon'] = [
                'code' => $c['code'],
                'type' => $c['discount_type'],
                'value' => $c['discount_value'],
                'max_discount' => floatval($c['max_discount'] ?? 0)
            ];
            $response = ['status' => 'success', 'message' => 'เก็บคูปอง ' . $code . ' สำเร็จ! คูปองจะถูกปรับใช้ที่หน้าชำระเงินโดยอัตโนมัติ'];
        }
    } else {
        $response = ['status' => 'error', 'message' => 'คูปองใช้งานไม่ได้หรือหมดอายุแล้ว'];
    }
}

// ==========================================
// 5. ปิดการส่งออกข้อมูลและส่ง JSON ตอบกลับ
// ==========================================
ob_end_clean();
echo json_encode($response);
exit();

// ==========================================
// 6. ฟังก์ชันช่วยคำนวณด้านใน (Helper Functions)
// ==========================================
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
            
            $subtotal += getProductTotalPrice($conn, $pid, $qty);
        }
    }

    $discount = 0;
    $is_free_shipping_coupon = false;
    if (isset($_SESSION['coupon']) && $subtotal > 0) {
        $c = $_SESSION['coupon'];
        if ($c['type'] == 'fixed') {
            $discount = floatval($c['value']);
        } elseif ($c['type'] == 'percent') {
            $discount = $subtotal * floatval($c['value']) / 100;
            $max_disc = floatval($c['max_discount'] ?? 0);
            if ($max_disc > 0 && $discount > $max_disc) {
                $discount = $max_disc;
            }
        } elseif ($c['type'] == 'free_shipping') {
            $is_free_shipping_coupon = true;
        }
        if($discount > $subtotal) $discount = $subtotal;
    }

    // ดึงค่าตั้งค่าระบบจัดส่งจากฐานข้อมูล
    $settings_q = mysqli_query($conn, "SELECT shipping_fee_fixed, shipping_free_threshold FROM shop_settings WHERE id = 1");
    $shop_settings = mysqli_fetch_assoc($settings_q);
    $fee = floatval($shop_settings['shipping_fee_fixed'] ?? 40.00);
    $threshold = floatval($shop_settings['shipping_free_threshold'] ?? 350.00);

    $shipping_fee = ($subtotal >= $threshold || $subtotal == 0 || $is_free_shipping_coupon) ? 0.00 : $fee;
    $final = max(0, $subtotal - $discount + $shipping_fee);

    return [
        'subtotal' => $subtotal, 
        'discount' => $discount, 
        'shipping_fee' => $shipping_fee,
        'shipping_fee_fixed' => $fee,
        'shipping_free_threshold' => $threshold,
        'final' => $final
    ];
}

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'ปี',
        'm' => 'เดือน',
        'w' => 'สัปดาห์',
        'd' => 'วัน',
        'h' => 'ชั่วโมง',
        'i' => 'นาที',
        's' => 'วินาที',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v;
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . 'ที่แล้ว' : 'เมื่อครู่นี้';
}
?>
