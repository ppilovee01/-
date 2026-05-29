<?php
ob_start();
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');

// ปิด Error เพื่อให้ส่ง JSON ได้สมบูรณ์
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาด'];

// ==========================================
// 1. ตรวจสอบสิทธิ์สำหรับแอกชันที่ต้องการสิทธิ์สมาชิก
// ==========================================
$member_actions = ['update_profile', 'change_password', 'add_address', 'delete_address', 'toggle_wishlist'];
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
            'final_total' => number_format($cart_data['final'], 2),
            'cart_count' => count_cart_items()
        ];
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
    } elseif (strlen($new_pass) < 4) {
        $response = ['status' => 'error', 'message' => 'รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร'];
    } else {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET password = '$hash' WHERE id = '$user_id'");
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
        // สร้าง HTML การ์ดที่อยู่เพื่อส่งกลับไปแปะ
        $html = '
        <div class="col-md-6 animate__animated animate__fadeIn" id="addr-'.$new_id.'">
            <div class="address-item h-100">
                <div class="fw-bold text-dark mb-1 fs-5">'.$name.'</div>
                <div class="text-muted small mb-2"><i class="bi bi-telephone"></i> '.$phone.'</div>
                <div class="small text-secondary" style="line-height: 1.5;">
                    '.$addr.'<br>
                    '.$sub.' '.$dist.'<br>
                    '.$prov.' '.$zip.'
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
    if(mysqli_query($conn, "DELETE FROM user_addresses WHERE id='$addr_id' AND user_id='$user_id'")) {
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
        $response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . mysqli_error($conn)];
    }
}

// 4.3 ระบบสืบค้นด่วน (Live Search Suggestion)
elseif ($action == 'search_suggest') {
    $q = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
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
    if (isset($_SESSION['coupon']) && $subtotal > 0) {
        $c = $_SESSION['coupon'];
        $discount = ($c['type'] == 'fixed') ? $c['value'] : ($subtotal * $c['value'] / 100);
        if($discount > $subtotal) $discount = $subtotal;
    }

    return ['subtotal' => $subtotal, 'discount' => $discount, 'final' => $subtotal - $discount];
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
