<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok'); 

$discord_webhook_url = "https://discord.com/api/webhooks/1473327005234761760/yOg6j2pYCa0DDSnqUxs5yCL3mlODeIrnYNNo1nJJldGFjnvDHQalkSHzd6RM0691w-b4"; 

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id'];
$shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id=1"));

// --- 2. ใช้คูปอง ---
if (isset($_POST['apply_coupon'])) {
    $code = mysqli_real_escape_string($conn, $_POST['coupon_code']); 
    $current_total = str_replace(',', '', $_POST['current_total']); 
    $today = date('Y-m-d'); 
    $check = mysqli_query($conn, "SELECT * FROM coupons WHERE code='$code' AND status='active' AND expiry_date >= '$today'");
    if (mysqli_num_rows($check) > 0) {
        $c = mysqli_fetch_assoc($check);
        
        $coupon_error = false;
        
        // ตรวจสอบลิมิตการใช้งานทั้งหมด (Global limit)
        if ($c['usage_limit'] > 0) {
            $total_used = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE coupon_code = '{$c['code']}' AND status != 'cancelled'"))['count'];
            if ($total_used >= $c['usage_limit']) {
                $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>"ขออภัย คูปองนี้สิทธิ์การใช้งานเต็มโควตาแล้ว", 'icon'=>'error'];
                $coupon_error = true;
            }
        }
        
        // ตรวจสอบลิมิตการใช้งานต่อคน (User limit)
        if (!$coupon_error && $c['user_limit'] > 0) {
            $user_used = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE coupon_code = '{$c['code']}' AND user_id = '$user_id' AND status != 'cancelled'"))['count'];
            if ($user_used >= $c['user_limit']) {
                $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>"ขออภัย คุณใช้สิทธิ์คูปองนี้ครบโควตาของคุณแล้ว", 'icon'=>'error'];
                $coupon_error = true;
            }
        }
        
        if (!$coupon_error) {
            if ($current_total >= $c['min_spend']) { 
                $_SESSION['coupon'] = [
                    'code' => $c['code'], 
                    'type' => $c['discount_type'], 
                    'value' => $c['discount_value'],
                    'max_discount' => floatval($c['max_discount'] ?? 0)
                ]; 
                $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'ใช้คูปองสำเร็จ!', 'icon'=>'success'];
            } else { 
                $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>"ยอดซื้อขั้นต่ำไม่ถึง " . number_format($c['min_spend']) . " บาท", 'icon'=>'error'];
            }
        }
    } else { 
        $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>"คูปองใช้ไม่ได้หรือหมดอายุ", 'icon'=>'error'];
    }
    header("Location: cart.php"); exit();
}

if (isset($_GET['remove_coupon'])) { unset($_SESSION['coupon']); header("Location: cart.php"); exit(); }

// --- 3. ยืนยันคำสั่งซื้อ (เพิ่มระบบตัดสต๊อก FIFO) ---
if (isset($_POST['confirm_order'])) {
    $stock_error = false;
    foreach ($_SESSION['cart'] as $key => $item) {
        $pid = is_array($item) ? $item['id'] : $key;
        $qty = is_array($item) ? $item['qty'] : $item;
        $s_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name, stock FROM products WHERE id='$pid'"));
        if ($s_row['stock'] < $qty) { 
            $error_msg = "สินค้า {$s_row['name']} มีไม่พอ (เหลือ {$s_row['stock']} ชิ้น)"; 
            $stock_error = true; 
            break; 
        }
        // คลายนโยบายความจำกัดโควตา (ลูกค้าสามารถซื้อเกินโควตาได้ โดยจะคำนวณแยกส่วนเป็นราคาทั่วไปแทน)
    }

    if (!$stock_error) {
        if (!isset($_POST['selected_address'])) { $error_msg = "กรุณาเลือกที่อยู่จัดส่ง"; }
        elseif (!isset($_POST['payment_method_id'])) { $error_msg = "กรุณาเลือกวิธีการชำระเงิน"; }
        else {
            $pm_id = $_POST['payment_method_id'];
            $pm = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM payment_methods WHERE id='$pm_id'"));
            $addr_id = $_POST['selected_address'];
            $a = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM user_addresses WHERE id='$addr_id'"));
            $full_addr = $a['recipient_name']." (".$a['phone'].")\n".$a['address_line1']." ".$a['subdistrict']." ".$a['district']." ".$a['province']." ".$a['zipcode'];
            
            $total = str_replace(',', '', $_POST['total_price_hidden']);
            $disc = str_replace(',', '', $_POST['discount_hidden']);
            $final = str_replace(',', '', $_POST['final_price_hidden']);
            $coupon = isset($_SESSION['coupon']) ? $_SESSION['coupon']['code'] : '';
            
            if (!empty($coupon)) {
                $chk_c = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM coupons WHERE code='$coupon' AND status='active'"));
                if ($chk_c) {
                    if ($chk_c['usage_limit'] > 0) {
                        $tot_used = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE coupon_code = '$coupon' AND status != 'cancelled'"))['count'];
                        if ($tot_used >= $chk_c['usage_limit']) {
                            $error_msg = "ขออภัย คูปองนี้สิทธิ์การใช้งานเต็มโควตาแล้ว";
                        }
                    }
                    if (!isset($error_msg) && $chk_c['user_limit'] > 0) {
                        $usr_used = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE coupon_code = '$coupon' AND user_id = '$user_id' AND status != 'cancelled'"))['count'];
                        if ($usr_used >= $chk_c['user_limit']) {
                            $error_msg = "ขออภัย คุณใช้สิทธิ์คูปองนี้ครบโควตาแล้ว";
                        }
                    }
                } else {
                    $error_msg = "ขออภัย คูปองนี้ใช้งานไม่ได้แล้ว";
                }
            }
            
            $slip = "";
            if ($pm['type'] != 'cod' && isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] == 0) {
                $ext = pathinfo($_FILES['payment_slip']['name'], PATHINFO_EXTENSION);
                $slip = "slip_" . uniqid() . "." . $ext;
                if(!is_dir("uploads")) mkdir("uploads");
                move_uploaded_file($_FILES['payment_slip']['tmp_name'], "uploads/" . $slip);
            } elseif ($pm['type'] != 'cod' && empty($_FILES['payment_slip']['name'])) {
                 $error_msg = "กรุณาแนบสลิปโอนเงิน";
            }

            if (!isset($error_msg)) {
                // คำนวณแต้มสะสมที่ใช้ลดราคา
                $points_spent = 0;
                $points_discount = 0.00;
                if (isset($_POST['use_points']) && $_POST['use_points'] == '1') {
                    $up_chk = mysqli_fetch_assoc(mysqli_query($conn, "SELECT points FROM users WHERE id = '$user_id'"));
                    $db_points = $up_chk ? intval($up_chk['points']) : 0;
                    
                    $shipping_fee_fixed = floatval($shop['shipping_fee_fixed'] ?? 40.00);
                    $shipping_free_threshold = floatval($shop['shipping_free_threshold'] ?? 350.00);
                    $is_free_shipping_coupon = false;
                    if (isset($_SESSION['coupon']) && $_SESSION['coupon']['type'] == 'free_shipping') {
                        $is_free_shipping_coupon = true;
                    }
                    $shipping_fee = ($total >= $shipping_free_threshold || $total == 0 || $is_free_shipping_coupon) ? 0 : $shipping_fee_fixed;
                    $base_final = max(0, $total - $disc + $shipping_fee);
                    
                    $points_spend_rate = intval($shop['points_spend_rate'] ?? 1);
                    $points_needed = ceil($base_final / $points_spend_rate);
                    $points_spent = min($db_points, $points_needed);
                    $points_discount = floatval($points_spent * $points_spend_rate);
                    $final = max(0, $base_final - $points_discount);
                }

                // คำนวณแต้มที่จะได้รับเมื่อจัดส่งสำเร็จ
                $points_earn_rate = intval($shop['points_earn_rate'] ?? 100);
                $points_earned = floor($final / $points_earn_rate);

                // ทำการหักแต้มสะสมออกจากโปรไฟล์ผู้ใช้
                if ($points_spent > 0) {
                    mysqli_query($conn, "UPDATE users SET points = points - $points_spent WHERE id = '$user_id'");
                }

                $sql = "INSERT INTO orders (user_id, total_price, discount_amount, final_price, coupon_code, status, address, payment_slip, payment_method, order_date, points_earned, points_spent, points_discount) 
                        VALUES ('$user_id', '$total', '$disc', '$final', '$coupon', 'pending', '$full_addr', '$slip', '{$pm['name']}', NOW(), '$points_earned', '$points_spent', '$points_discount')";
                
                if(mysqli_query($conn, $sql)){
                    $order_id = mysqli_insert_id($conn);
                    $discord_items = "";

                    foreach($_SESSION['cart'] as $key => $item) {
                        if (is_array($item)) {
                            $pid = $item['id'];
                            $qty = $item['qty'];
                            $opts = isset($item['options']) ? $item['options'] : '';
                        } else {
                            $pid = $key;
                            $qty = $item;
                            $opts = '';
                        }

                        // === ระบบตัดสต๊อกแบบ FIFO และคำนวณราคาทุน ===
                        $qty_needed = $qty;
                        $lot_query = mysqli_query($conn, "SELECT id, stock, import_cost FROM product_lots WHERE product_id='$pid' AND stock > 0 ORDER BY imported_at ASC");
                        $total_import_cost = 0;
                        
                        while ($lot = mysqli_fetch_assoc($lot_query)) {
                            if ($qty_needed <= 0) break;
                            $lot_id = $lot['id'];
                            $lot_stock = $lot['stock'];
                            $lot_cost = floatval($lot['import_cost']);
                            
                            if ($lot_stock >= $qty_needed) {
                                // ล็อตนี้ของพอ ตัดสต๊อกและจบการทำงาน
                                mysqli_query($conn, "UPDATE product_lots SET stock = stock - $qty_needed WHERE id='$lot_id'");
                                $total_import_cost += $qty_needed * $lot_cost;
                                $qty_needed = 0;
                            } else {
                                // ล็อตนี้ของไม่พอ ตัดจนเหลือ 0 แล้วไปเอาล็อตถัดไปต่อ
                                mysqli_query($conn, "UPDATE product_lots SET stock = 0 WHERE id='$lot_id'");
                                $total_import_cost += $lot_stock * $lot_cost;
                                $qty_needed -= $lot_stock;
                            }
                        }
                        
                        // หากมีส่วนต่างที่หลงเหลือ (เช่น สต๊อกไม่ตรงกัน) ให้ดึงทุนจากล็อตล่าสุด
                        if ($qty_needed > 0) {
                            $last_lot_q = mysqli_query($conn, "SELECT import_cost FROM product_lots WHERE product_id='$pid' ORDER BY id DESC LIMIT 1");
                            $last_cost = 0;
                            if ($last_lot_q && mysqli_num_rows($last_lot_q) > 0) {
                                $last_cost = floatval(mysqli_fetch_assoc($last_lot_q)['import_cost']);
                            }
                            $total_import_cost += $qty_needed * $last_cost;
                        }
                        
                        $unit_import_cost = $qty > 0 ? round($total_import_cost / $qty, 2) : 0;

                        $pr = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name, price FROM products WHERE id='$pid'"));
                        $opts_esc = mysqli_real_escape_string($conn, $opts);
                        
                        // คำนวณราคาขายแบบแยกส่วน (ส่วนที่อยู่ในโควตา = ราคา Flash, ส่วนเกินโควตา = ราคาปกติ)
                        $line_total_price = getProductTotalPrice($conn, $pid, $qty);
                        $average_unit_price = $qty > 0 ? ($line_total_price / $qty) : 0;
                        
                        // บันทึกรายการลงบิล พร้อมทุนต้นทุน FIFO
                        mysqli_query($conn, "INSERT INTO order_items (order_id, product_id, quantity, price, import_cost, selected_option) VALUES ('$order_id', '$pid', '$qty', '$average_unit_price', '$unit_import_cost', '$opts_esc')");
                        
                        // เพิ่มยอดขายในระบบ Flash Sale (ไม่เกินจำนวนโควตาที่เหลือในแคมเปญ)
                        $active_fs = getActiveFlashSale($conn, $pid);
                        if ($active_fs !== null) {
                            $fs_remaining = $active_fs['flash_stock'] - $active_fs['flash_sold'];
                            $fs_sold_increment = min($qty, max(0, $fs_remaining));
                            if ($fs_sold_increment > 0) {
                                mysqli_query($conn, "UPDATE flash_sales SET flash_sold = flash_sold + $fs_sold_increment WHERE id = '{$active_fs['id']}'");
                            }
                        }
                        
                        // ซิงค์ตารางสินค้าหลัก (อัปเดตราคาใหม่ล่าสุด และสต๊อกรวม)
                        $q_tot = mysqli_query($conn, "SELECT SUM(stock) as total_stock FROM product_lots WHERE product_id='$pid' AND stock > 0");
                        $tot = mysqli_fetch_assoc($q_tot)['total_stock'] ?? 0;

                        $q_price = mysqli_query($conn, "SELECT price FROM product_lots WHERE product_id='$pid' AND stock > 0 ORDER BY imported_at ASC LIMIT 1");
                        $r_price = mysqli_fetch_assoc($q_price);

                        $final_stock = ($tot > 0) ? intval($tot) : 0;
                        if ($tot > 0 && $r_price) {
                            $new_price = $r_price['price'];
                            mysqli_query($conn, "UPDATE products SET stock='$final_stock', price='$new_price' WHERE id='$pid'");
                        } else {
                            mysqli_query($conn, "UPDATE products SET stock=0 WHERE id='$pid'");
                        }

                        // ระบบแจ้งเตือนสินค้าใกล้หมดเข้ากระดิ่งแอดมินอัตโนมัติ (ต่ำกว่า 5 ชิ้น)
                        if ($final_stock < 5) {
                            $p_name_esc = mysqli_real_escape_string($conn, $pr['name']);
                            $title_alert = "สินค้าใกล้หมดคลัง: " . $p_name_esc;
                            
                            // เช็กป้องกันการแจ้งเตือนซ้ำซ้อน หากรายการแจ้งเตือนก่อนหน้านี้ของสินค้าชิ้นนี้ยังไม่ได้อ่าน
                            $chk_notif = mysqli_query($conn, "SELECT id FROM notifications WHERE title = '$title_alert' AND is_read = 0 AND is_admin = 1");
                            if (mysqli_num_rows($chk_notif) == 0) {
                                $msg_alert = "สินค้า " . $p_name_esc . " เหลือในคลังเพียง " . $final_stock . " ชิ้น กรุณาตรวจสอบและเติมสต๊อก";
                                $url_alert = "admin.php";
                                mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, url, is_read, is_admin) VALUES (NULL, '$title_alert', '$msg_alert', '$url_alert', 0, 1)");
                            }
                        }
                        // =============================
                        
                        $discord_items .= "- {$pr['name']} (x$qty) $opts\n";
                    }

                    if($discord_webhook_url) {
                        $msg_content = "💰 **มีคำสั่งซื้อใหม่! #$order_id**\n👤 ลูกค้า: {$a['recipient_name']}\n📦 สินค้า:\n$discord_items\n💵 ยอดสุทธิ: " . number_format($final, 2) . " บาท\n💳 ชำระโดย: {$pm['name']}";
                        $json_data = json_encode(["content" => $msg_content], JSON_UNESCAPED_UNICODE);
                        $ch = curl_init($discord_webhook_url);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
                        curl_setopt($ch, CURLOPT_POST, 1);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); // หมดเวลาเชื่อมต่อใน 2 วิ
                        curl_setopt($ch, CURLOPT_TIMEOUT, 2); // หมดเวลารอข้อมูลใน 2 วิ
                        curl_exec($ch); curl_close($ch);
                    }

                    // ส่งแจ้งเตือนผ่าน Line Notify ไปยังแอดมิน
                    $line_msg = "\n💰 มีคำสั่งซื้อใหม่! (#" . str_pad($order_id, 5, '0', STR_PAD_LEFT) . ")\n"
                              . "👤 ลูกค้า: " . $a['recipient_name'] . " (" . $a['phone'] . ")\n"
                              . "📦 สินค้าที่สั่งซื้อ:\n" . $discord_items
                              . "💵 ยอดสุทธิ: ฿" . number_format($final, 2) . "\n"
                              . "💳 ชำระผ่าน: " . $pm['name'];
                    sendLineNotify($conn, $line_msg);

                    // Insert admin notification for new order
                    $cust_name = mysqli_real_escape_string($conn, $a['recipient_name']);
                    $title = "มีคำสั่งซื้อใหม่เข้ามา #$order_id";
                    $message = "มีคำสั่งซื้อใหม่เข้ามา #$order_id จากคุณ $cust_name ยอดชำระ ฿" . number_format($final);
                    $url = "admin_orders.php";
                    mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, url, is_read, is_admin) VALUES (NULL, '$title', '$message', '$url', 0, 1)");

                    unset($_SESSION['cart']); unset($_SESSION['coupon']);
                    $_SESSION['swal'] = [
                        'title' => 'สั่งซื้อสำเร็จ!',
                        'text' => "เลขที่คำสั่งซื้อ #$order_id",
                        'icon' => 'success',
                        'timer' => 2000
                    ];
                    header("Location: my_orders.php"); exit();
                } else {
                    $error_msg = "เกิดข้อผิดพลาด: " . mysqli_error($conn);
                }
            }
        }
    }
    
    if(isset($error_msg)){
        $_SESSION['swal'] = ['title'=>'ขออภัย', 'text'=>$error_msg, 'icon'=>'error'];
        header("Location: cart.php"); exit();
    }
}

$page_title = "ตระกร้าสินค้า | Por Mae Bet Taled";
$extra_css = "
<style>
    .card-modern { border: 1px solid rgba(226, 232, 240, 0.8); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); background: white; margin-bottom: 25px; overflow: hidden; }
    .card-header-modern { background: white; border-bottom: 1px solid #E2E8F0; padding: 20px 25px; font-weight: 700; font-size: 1.1rem; color: var(--slate-dark); }
    .item-img { width: 70px; height: 70px; object-fit: cover; border-radius: 12px; }
    .qty-box { display: flex; align-items: center; justify-content: center; border: 1px solid #E2E8F0; border-radius: 50px; background: white; width: 100px; padding: 3px; }
    .qty-btn { width: 28px; height: 28px; border-radius: 50%; border: none; background: white; color: var(--text-secondary); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: var(--transition-smooth); }
    .qty-btn:hover { background: var(--blue-main); color: white; }
    .qty-num { flex-grow: 1; text-align: center; font-weight: bold; font-size: 0.95rem; color: var(--text-primary); user-select: none; }
    
    .summary-card { position: sticky; top: 100px; border: 1px solid rgba(226, 232, 240, 0.8); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); background: white; }
    .btn-checkout { background: linear-gradient(135deg, var(--blue-main) 0%, var(--blue-hover) 100%); color: white; border: none; border-radius: 50px; padding: 15px; width: 100%; font-weight: 600; font-size: 1.1rem; box-shadow: 0 8px 20px rgba(174, 226, 255, 0.4); transition: var(--transition-smooth); }
    .btn-checkout:hover { transform: translateY(-2px); box-shadow: 0 12px 25px rgba(127, 181, 255, 0.55); color: white; }
    .info-box { background: var(--blue-light); border: 1px dashed var(--blue-hover); border-radius: 12px; padding: 15px; margin-top: 15px; display: none; text-align: center; }
</style>
";
include 'header.php';
$shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id=1"));
$shipping_fee_fixed = floatval($shop['shipping_fee_fixed'] ?? 40.00);
$shipping_free_threshold = floatval($shop['shipping_free_threshold'] ?? 350.00);
$user_points = 0;
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $up_q = mysqli_query($conn, "SELECT points FROM users WHERE id = '$uid'");
    if ($up_q && mysqli_num_rows($up_q) > 0) {
        $user_points = intval(mysqli_fetch_assoc($up_q)['points']);
    }
}
$points_earn_rate = intval($shop['points_earn_rate'] ?? 100);
$points_spend_rate = intval($shop['points_spend_rate'] ?? 1);
?>
<script>
    window.shippingThreshold = <?= $shipping_free_threshold ?>;
    window.shippingFeeFixed = <?= $shipping_fee_fixed ?>;
    window.userPointsAvailable = <?= $user_points ?>;
    window.pointsEarnRate = <?= $points_earn_rate ?>;
    window.pointsSpendRate = <?= $points_spend_rate ?>;
</script>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-12">
            <h3 class="fw-bold mb-4">🛒 ตะกร้าสินค้า</h3>
            
            <form method="POST" enctype="multipart/form-data" id="checkoutForm">
                <div class="row g-4">
                    <div class="col-xl-8">
                        <div class="card-modern animate__animated animate__fadeInUp">
                            <div class="card-header-modern"><i class="bi bi-bag-check text-blue me-2" style="color:#AEE2FF"></i> รายการสินค้า</div>
                            <div class="p-3" id="cart-items-container">
                                <?php 
                                $subtotal = 0;
                                if (!empty($_SESSION['cart'])):
                                    foreach ($_SESSION['cart'] as $key => $item) {
                                        if (is_array($item)) {
                                            $pid = $item['id'];
                                            $qty = $item['qty'];
                                            $opts = $item['options'] ?? '';
                                        } else {
                                            $pid = $key;
                                            $qty = $item;
                                            $opts = '';
                                        }

                                        $res = mysqli_query($conn, "SELECT * FROM products WHERE id = '$pid'");
                                        $row = mysqli_fetch_assoc($res);
                                        
                                        if($row) {
                                            $line_total = getProductTotalPrice($conn, $pid, $qty);
                                            $subtotal += $line_total;
                                            $price_desc = getProductPriceText($conn, $pid, $qty);
                                ?>
                                <div class="item-row d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom" id="item-row-<?= $key ?>">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= $row['image'] ?>" class="item-img">
                                        <div>
                                            <h6 class="mb-0 fw-bold text-dark"><?= $row['name'] ?></h6>
                                            <?php if($opts): ?>
                                                <small class="text-muted bg-light px-2 py-0 rounded border d-inline-block mt-1"><?= $opts ?></small>
                                            <?php endif; ?>
                                            <div class="text-muted small mt-1" id="price-desc-<?= $key ?>"><?= $price_desc ?></div>
                                            <button type="button" onclick="removeItem('<?= $key ?>')" class="text-danger small text-decoration-none mt-1 d-inline-block border-0 bg-transparent p-0"><i class="bi bi-trash"></i> ลบ</button>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="d-flex justify-content-end mb-1">
                                            <div class="qty-box">
                                                <button type="button" class="qty-btn" onclick="updateQty('<?= $key ?>', 'dec')"><i class="bi bi-dash"></i></button>
                                                <span class="qty-num" id="qty-<?= $key ?>"><?= $qty ?></span>
                                                <button type="button" class="qty-btn" onclick="updateQty('<?= $key ?>', 'inc')"><i class="bi bi-plus"></i></button>
                                            </div>
                                        </div>
                                        <div class="fw-bold text-dark">฿<span id="line-total-<?= $key ?>"><?= number_format($line_total) ?></span></div>
                                    </div>
                                </div>
                                <?php 
                                        }
                                    } 
                                endif; 
                                ?>
                                
                                <?php if($subtotal == 0): ?>
                                    <div class="text-center py-5 text-muted"><i class="bi bi-cart-x fs-1 d-block mb-3"></i> ตะกร้าว่างเปล่า</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card-modern animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
                            <div class="card-header-modern d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-geo-alt text-blue me-2" style="color:#AEE2FF"></i> ที่อยู่จัดส่ง</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addAddressModal">+ เพิ่ม</button>
                            </div>
                            <div class="p-4">
                                <div class="row g-3" id="address-list-container">
                                    <?php $aq = mysqli_query($conn, "SELECT * FROM user_addresses WHERE user_id='$user_id' ORDER BY id DESC"); 
                                    if(mysqli_num_rows($aq)>0): $fst=true; while($ad=mysqli_fetch_assoc($aq)): ?>
                                    <div class="col-md-6 address-item-col">
                                        <label class="w-100 h-100">
                                            <input type="radio" name="selected_address" value="<?= $ad['id'] ?>" class="d-none" <?=$fst?'checked':''?>>
                                            <div class="option-card">
                                                <i class="bi bi-check-circle-fill check-icon"></i>
                                                <div class="fw-bold text-dark mb-1"><?=$ad['recipient_name']?> <span class="text-muted fw-normal small">(<?=$ad['phone']?>)</span></div>
                                                <div class="text-muted small text-truncate"><?=$ad['address_line1']?>...</div>
                                            </div>
                                        </label>
                                    </div>
                                    <?php $fst=false; endwhile; else: ?>
                                        <div id="no-address-msg" class="text-center py-3 text-muted">ยังไม่มีที่อยู่ กรุณาเพิ่มที่อยู่ใหม่</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="card-modern animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                            <div class="card-header-modern"><i class="bi bi-credit-card text-blue me-2" style="color:#AEE2FF"></i> วิธีการชำระเงิน</div>
                            <div class="p-4">
                                <?php 
                                $shipping_fee_fixed = floatval($shop['shipping_fee_fixed'] ?? 40.00);
                                $shipping_free_threshold = floatval($shop['shipping_free_threshold'] ?? 350.00);
                                $disc = 0;
                                $is_free_shipping_coupon = false;
                                if (isset($_SESSION['coupon'])) {
                                    $cp = $_SESSION['coupon'];
                                    if ($cp['type'] == 'fixed') {
                                        $disc = floatval($cp['value']);
                                    } elseif ($cp['type'] == 'percent') {
                                        $disc = $subtotal * floatval($cp['value']) / 100;
                                        $max_disc = floatval($cp['max_discount'] ?? 0);
                                        if ($max_disc > 0 && $disc > $max_disc) {
                                            $disc = $max_disc;
                                        }
                                    } elseif ($cp['type'] == 'free_shipping') {
                                        $is_free_shipping_coupon = true;
                                    }
                                }
                                $shipping_fee = ($subtotal >= $shipping_free_threshold || $subtotal == 0 || $is_free_shipping_coupon) ? 0 : $shipping_fee_fixed;
                                $final = max(0, $subtotal - $disc + $shipping_fee);
                                ?>
                                <div class="row g-3">
                                    <?php $pq=mysqli_query($conn,"SELECT * FROM payment_methods WHERE status='active'"); while($pm=mysqli_fetch_assoc($pq)): ?>
                                    <div class="col-6 col-md-4">
                                        <label class="w-100">
                                            <input type="radio" name="payment_method_id" value="<?=$pm['id']?>" class="d-none" data-type="<?=$pm['type']?>" data-acc="<?=$pm['account_number']?>" data-holder="<?=htmlspecialchars($pm['account_name'] ?? '')?>" data-name="<?=htmlspecialchars($pm['name'])?>" onchange="updatePaymentUI(this)">
                                            <div class="option-card text-center py-3">
                                                <i class="bi bi-check-circle-fill check-icon"></i>
                                                <?php 
                                                $icon_class = 'bi-wallet2';
                                                if ($pm['type'] == 'promptpay') $icon_class = 'bi-qr-code-scan';
                                                elseif ($pm['type'] == 'bank') $icon_class = 'bi-bank';
                                                elseif ($pm['type'] == 'cod') $icon_class = 'bi-cash-coin';
                                                ?>
                                                <i class="bi <?= $icon_class ?> fs-2 d-block mb-1" style="color:#AEE2FF"></i>
                                                <div class="fw-bold small"><?=$pm['name']?></div>
                                            </div>
                                        </label>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                                <div id="qrSection" class="info-box" style="background: white; border: 1px solid #E2E8F0; border-radius: 16px; padding: 20px; display: none;">
                                    <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-qr-code-scan text-primary me-2"></i>สแกน QR Code พร้อมเพย์</h6>
                                    <div class="d-inline-block bg-white p-3 border rounded-4 mb-2 shadow-sm" style="border-color: #E2E8F0 !important;">
                                        <img id="qrImg" src="" style="width:230px; height:230px; object-fit:contain;" class="d-block mx-auto">
                                    </div>
                                    <div class="text-dark fw-bold mb-1" id="qr-acc-holder" style="font-size:0.95rem;"></div>
                                    <div class="text-muted small mb-2" id="qr-acc-num"></div>
                                    <div class="text-danger fw-bold" style="font-size:1.15rem;">ยอดโอน: ฿<span id="qr-total"><?= number_format($final, 2) ?></span></div>
                                </div>
                                <div id="bankSection" class="info-box" style="background: white; border: 1px solid #E2E8F0; border-radius: 16px; padding: 20px; display: none;">
                                    <h6 class="fw-bold text-muted mb-2"><i class="bi bi-bank text-primary me-2"></i>รายละเอียดการโอนเงินธนาคาร</h6>
                                    <div class="fs-5 fw-bold text-dark mb-1" id="bankName"></div>
                                    <div id="bankAcc" class="fs-3 fw-bold text-primary my-2" style="letter-spacing: 0.5px;"></div>
                                    <div class="small text-muted" style="font-size:0.95rem;">ชื่อบัญชี: <span id="bankHolder" class="fw-bold text-dark"></span></div>
                                </div>
                                <div id="slipUploadSection" class="mt-3" style="display:none;">
                                    <label class="form-label fw-bold text-dark small">แนบสลิปโอนเงิน</label>
                                    <input type="file" name="payment_slip" class="form-control form-control-sm" accept="image/*">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="card-modern summary-card p-4">
                            <h5 class="fw-bold mb-3">สรุปยอด</h5>
                            
                            <!-- Free Shipping Progress Bar Widget -->
                            <div class="free-shipping-widget mb-4 p-3 rounded-4 border bg-light" id="free-shipping-widget-wrapper" style="display: none;">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="free-shipping-icon" id="free-shipping-icon" style="font-size: 1.15rem;">🚚</span>
                                    <span class="fw-bold text-dark" id="free-shipping-text" style="font-size: 0.82rem; line-height: 1.2;"></span>
                                </div>
                                <div class="free-shipping-bar-container" style="height: 10px; background: #e2e8f0; border-radius: 10px; overflow: hidden; position: relative;">
                                    <div class="free-shipping-bar-fill" id="free-shipping-bar-fill" style="width: 0%; height: 100%; border-radius: 10px; transition: width 0.4s cubic-bezier(0.16, 1, 0.3, 1), background 0.4s ease;"></div>
                                </div>
                            </div>

                            <div class="input-group input-group-sm mb-1">
                                <input type="text" name="coupon_code" class="form-control" placeholder="โค้ดส่วนลด" value="<?= isset($_SESSION['coupon'])?$_SESSION['coupon']['code']:'' ?>">
                                <input type="hidden" name="current_total" id="hidden_total" value="<?=$subtotal?>">
                                <button class="btn btn-dark" type="submit" name="apply_coupon" formnovalidate>ใช้</button>
                            </div>
                            <div class="text-end mb-3">
                                <button type="button" class="btn btn-link btn-sm text-primary p-0 text-decoration-none" onclick="openCouponModal()" style="font-size: 0.8rem; font-weight: 500;">
                                    <i class="bi bi-ticket-perforated me-1"></i> ดูคูปองส่วนลดที่มีทั้งหมด
                                </button>
                            </div>

                            <!-- ส่วนของแต้มสะสม -->
                            <?php if ($user_points > 0): ?>
                                <div class="p-3 mb-3 rounded-4 border bg-light" style="border-style: dashed !important; border-color: var(--blue-hover) !important;">
                                    <div class="form-check form-switch d-flex align-items-center justify-content-between p-0 mb-2">
                                        <div style="padding-left: 0;">
                                            <label class="form-check-label fw-bold text-dark" for="use_points_toggle" style="cursor: pointer; font-size: 0.85rem;">
                                                🪙 ใช้แต้มสะสมของฉัน
                                            </label>
                                            <div class="text-muted" style="font-size: 0.72rem;">คุณมี <?= number_format($user_points) ?> แต้ม (ลดได้ ฿<?= number_format($user_points * $points_spend_rate) ?>)</div>
                                        </div>
                                        <input class="form-check-input" type="checkbox" name="use_points" id="use_points_toggle" value="1" onchange="togglePointsRedemption(this)" style="width: 2.2em; height: 1.2em; cursor: pointer; margin-left: 10px;">
                                    </div>
                                    <div class="text-muted p-2 rounded-3 bg-white" style="font-size: 0.68rem; border: 1px solid rgba(226,232,240,0.6);">
                                        <i class="bi bi-info-circle text-primary me-1"></i> <strong>กติกาแต้ม:</strong> 1 แต้ม = ฿<?= number_format($points_spend_rate) ?> ส่วนลด<br>
                                        (จะได้รับ 1 แต้ม สำหรับทุกยอดซื้อครบ ฿<?= number_format($points_earn_rate) ?> เมื่อจัดส่งออเดอร์สำเร็จ)
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if(isset($_SESSION['coupon'])): ?>
                                <?php if($_SESSION['coupon']['type'] !== 'free_shipping'): ?>
                                    <div class="d-flex justify-content-between small mb-2 text-success" id="cart-discount-row"><span>ส่วนลดคูปอง</span><span>-฿<span id="discount_val"><?=number_format($disc,2)?></span></span></div>
                                <?php endif; ?>
                                <div class="text-end mb-2"><a href="?remove_coupon=1" class="text-danger small text-decoration-none"><i class="bi bi-x-circle me-1"></i>ยกเลิกคูปอง</a></div>
                            <?php endif; ?>

                            <div class="d-flex justify-content-between small mb-2 text-muted"><span>ยอดรวมสินค้า</span><span>฿<span id="subtotal"><?=number_format($subtotal,2)?></span></span></div>
                            <div class="d-flex justify-content-between small mb-2 text-muted">
                                <span>ค่าจัดส่ง</span>
                                <span id="shipping_fee_val" class="fw-bold <?= $shipping_fee == 0 ? 'text-success' : '' ?>">
                                    <?= $shipping_fee == 0 ? 'ส่งฟรี' : '฿' . number_format($shipping_fee, 2) ?>
                                </span>
                            </div>
                            <div class="d-none justify-content-between small mb-2 text-success" id="points_applied_row">
                                 <span>ส่วนลดจากแต้ม (ใช้ <span id="points_spent_val">0</span> แต้ม)</span>
                                 <span>-฿<span id="points_discount_val">0.00</span></span>
                             </div>
                            <hr class="my-2 opacity-25">
                            <div class="d-flex justify-content-between fw-bold fs-5 mb-3"><span>สุทธิ</span><span style="color:#AEE2FF">฿<span id="final_total"><?=number_format($final,2)?></span></span></div>

                            <input type="hidden" name="total_price_hidden" id="in_total" value="<?=$subtotal?>">
                            <input type="hidden" name="discount_hidden" id="in_disc" value="<?=$disc?>">
                            <input type="hidden" name="final_price_hidden" id="in_final" value="<?=$final?>">
                            
                            <?php if($subtotal > 0): ?>
                                <button type="button" onclick="validateForm()" class="btn btn-checkout">สั่งซื้อเลย</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary w-100 py-3 rounded-pill disabled">ตะกร้าว่างเปล่า</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addAddressModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0"><h5 class="modal-title fw-bold">เพิ่มที่อยู่ใหม่</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <form id="form-add-address-cart" onsubmit="saveAddressCart(); return false;">
                <input type="hidden" name="action" value="add_address">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-6"><input type="text" name="recipient_name" class="form-control" placeholder="ชื่อ-นามสกุล" required></div>
                        <div class="col-6"><input type="text" name="phone" class="form-control" placeholder="เบอร์โทรศัพท์" required></div>
                        <div class="col-12"><textarea name="address_line1" class="form-control" placeholder="บ้านเลขที่, หมู่บ้าน, ซอย, ถนน" rows="2" required></textarea></div>
                        <div class="col-6"><input type="text" name="subdistrict" class="form-control" placeholder="ตำบล/แขวง" required></div>
                        <div class="col-6"><input type="text" name="district" class="form-control" placeholder="อำเภอ/เขต" required></div>
                        <div class="col-6"><input type="text" name="province" class="form-control" placeholder="จังหวัด" required></div>
                        <div class="col-6"><input type="text" name="zipcode" class="form-control" placeholder="รหัสไปรษณีย์" required></div>
                    </div>
                </div>
                <div class="modal-footer border-0"><button type="submit" class="btn btn-dark w-100 rounded-pill">บันทึกที่อยู่</button></div>
            </form>
        </div>
    </div>
</div>

<?php if(isset($_SESSION['swal'])): ?>
<script>
    Swal.fire({
        icon: '<?= $_SESSION['swal']['icon'] ?>',
        title: '<?= $_SESSION['swal']['title'] ?>',
        text: '<?= $_SESSION['swal']['text'] ?>',
        confirmButtonColor: '#AEE2FF',
        timer: <?= isset($_SESSION['swal']['timer']) ? $_SESSION['swal']['timer'] : 'null' ?>,
        showConfirmButton: <?= isset($_SESSION['swal']['timer']) ? 'false' : 'true' ?>
    });
</script>
<?php unset($_SESSION['swal']); endif; ?>

<script>
window.pointsSpendRate = <?= $points_spend_rate ?>;
window.userPointsAvailable = <?= $user_points ?>;

function updateFreeShippingProgressBar(subtotal, isFreeCoupon) {
    const widget = document.getElementById('free-shipping-widget-wrapper');
    const fill = document.getElementById('free-shipping-bar-fill');
    const text = document.getElementById('free-shipping-text');
    const icon = document.getElementById('free-shipping-icon');
    const shippingFeeValEl = document.getElementById('shipping_fee_val');

    if (!widget || !fill || !text) return;

    if (subtotal <= 0) {
        widget.style.display = 'none';
        if (shippingFeeValEl) {
            shippingFeeValEl.innerText = 'ส่งฟรี';
            shippingFeeValEl.className = 'fw-bold text-success';
        }
        return;
    }

    widget.style.display = 'block';
    let pct = Math.min(100, (subtotal / window.shippingThreshold) * 100);

    if (isFreeCoupon || pct >= 100) {
        fill.style.width = '100%';
        fill.classList.add('success');
        if (pct >= 100) {
            text.innerHTML = 'ยินดีด้วย! คุณได้รับสิทธิ์ส่งฟรีแล้ว 🎉';
        } else {
            text.innerHTML = 'ยินดีด้วย! คุณได้รับสิทธิ์ส่งฟรีจากคูปองแล้ว 🎉';
        }
        if (icon) icon.innerText = '🎉';
        if (shippingFeeValEl) {
            shippingFeeValEl.innerText = 'ส่งฟรี';
            shippingFeeValEl.className = 'fw-bold text-success';
        }
    } else {
        fill.style.width = pct + '%';
        fill.classList.remove('success');
        let remaining = window.shippingThreshold - subtotal;
        text.innerHTML = 'ช้อปอีกเพียง <strong style="color:var(--blue-hover)">฿' + remaining.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</strong> เพื่อรับส่งฟรี!';
        if (icon) icon.innerText = '🚚';
        if (shippingFeeValEl) {
            shippingFeeValEl.innerText = '฿' + window.shippingFeeFixed.toFixed(2);
            shippingFeeValEl.className = 'fw-bold text-dark';
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const initialSubtotal = <?= floatval($subtotal) ?>;
    const isFreeCoupon = <?= $is_free_shipping_coupon ? 'true' : 'false' ?>;
    updateFreeShippingProgressBar(initialSubtotal, isFreeCoupon);
});

let isSavingAddressCart = false;
function saveAddressCart() {
    if (isSavingAddressCart) return;
    isSavingAddressCart = true;
    
    const form = document.getElementById('form-add-address-cart');
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> กำลังบันทึก...';
    }
    
    const formData = new FormData(form);

    fetch('ajax.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        isSavingAddressCart = false;
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'บันทึกที่อยู่';
        }
        if(data.status === 'success') {
            bootstrap.Modal.getInstance(document.getElementById('addAddressModal')).hide();
            
            const noAddrMsg = document.getElementById('no-address-msg');
            if(noAddrMsg) noAddrMsg.remove();

            const name = formData.get('recipient_name');
            const phone = formData.get('phone');
            const addrFull = formData.get('address_line1') + '...';
            const newId = data.new_address_id; 

            const newHtml = `
            <div class="col-md-6 address-item-col animate__animated animate__fadeIn">
                <label class="w-100 h-100">
                    <input type="radio" name="selected_address" value="${newId}" class="d-none" checked>
                    <div class="option-card">
                        <i class="bi bi-check-circle-fill check-icon"></i>
                        <div class="fw-bold text-dark mb-1">${name} <span class="text-muted fw-normal small">(${phone})</span></div>
                        <div class="text-muted small text-truncate">${addrFull}</div>
                    </div>
                </label>
            </div>`;

            const container = document.getElementById('address-list-container');
            container.insertAdjacentHTML('afterbegin', newHtml);

            Swal.fire({ icon: 'success', title: 'เพิ่มที่อยู่แล้ว', showConfirmButton: false, timer: 1000 });
            form.reset();
        } else {
            Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: data.message });
        }
    })
    .catch(err => console.error(err));
}

function updateQty(id, type) {
    let formData = new FormData();
    formData.append('action', 'update_qty');
    formData.append('product_id', id);
    formData.append('type', type);

    fetch('ajax.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            document.getElementById('qty-'+id).innerText = data.new_qty;
            document.getElementById('line-total-'+id).innerText = data.line_total;
            if(document.getElementById('price-desc-'+id)) {
                document.getElementById('price-desc-'+id).innerText = data.price_desc;
            }
            document.getElementById('subtotal').innerText = data.subtotal;
            document.getElementById('final_total').innerText = data.final_total;
            if(document.getElementById('discount_val')) document.getElementById('discount_val').innerText = data.discount;
            if(document.getElementById('qr-total')) document.getElementById('qr-total').innerText = data.final_total;

            document.getElementById('in_total').value = data.subtotal.replace(/,/g, '');
            document.getElementById('in_disc').value = data.discount.replace(/,/g, '');
            document.getElementById('in_final').value = data.final_total.replace(/,/g, '');
            document.getElementById('hidden_total').value = data.subtotal.replace(/,/g, '');

            const badge = document.getElementById('nav-cart-badge');
            badge.innerText = data.cart_count;
            badge.classList.remove('d-none', 'hidden');
            
            const pm = document.querySelector('input[name="payment_method_id"]:checked');
            if(pm) updatePaymentUI(pm);

            let subFloat = parseFloat(data.subtotal.replace(/,/g, ''));
            const isFreeCoupon = parseFloat(data.shipping_fee.replace(/,/g, '')) === 0;
            updateFreeShippingProgressBar(subFloat, isFreeCoupon);

            // Recalculate points redemption if checked
            if (typeof recalculateCheckoutSummary === 'function') {
                recalculateCheckoutSummary();
            }

            // Sync with interactive cart drawer if available
            if (typeof window.loadCartDrawer === 'function') {
                window.loadCartDrawer();
            }
        } else {
            Swal.fire('แจ้งเตือน', data.message, 'warning');
        }
    });
}

function removeItem(id) {
    Swal.fire({
        title: 'ลบสินค้านี้?', icon: 'warning', showCancelButton: true, confirmButtonText: 'ลบ', cancelButtonText: 'ยกเลิก', confirmButtonColor: '#d33'
    }).then((r)=>{
        if(r.isConfirmed) {
            let fd = new FormData(); fd.append('action','remove_item'); fd.append('product_id',id);
            fetch('ajax.php', { method:'POST', body:fd }).then(r=>r.json()).then(data=>{
                if(data.status==='success') {
                    const Toast = Swal.mixin({toast: true, position: 'top-end', showConfirmButton: false, timer: 1500, timerProgressBar: true});
                    Toast.fire({icon: 'success', title: 'ลบสินค้าเรียบร้อย'});

                    let row = document.getElementById('item-row-' + id);
                    if(row) {
                        row.style.transition = 'all 0.3s';
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            if(data.cart_count == 0) {
                                document.getElementById('cart-items-container').innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-cart-x fs-1 d-block mb-3"></i> ตะกร้าว่างเปล่า</div>';
                                document.querySelector('.btn-checkout').classList.add('disabled');
                                document.querySelector('.btn-checkout').disabled = true;
                                document.getElementById('nav-cart-badge').classList.add('hidden');
                            }

                            document.getElementById('subtotal').innerText = data.subtotal;
                            document.getElementById('final_total').innerText = data.final_total;
                            if(document.getElementById('discount_val')) document.getElementById('discount_val').innerText = data.discount;

                            document.getElementById('in_total').value = data.subtotal.replace(/,/g, '');
                            document.getElementById('in_disc').value = data.discount.replace(/,/g, '');
                            document.getElementById('in_final').value = data.final_total.replace(/,/g, '');
                            document.getElementById('hidden_total').value = data.subtotal.replace(/,/g, '');
                            
                            const badge = document.getElementById('nav-cart-badge');
                            badge.innerText = data.cart_count;

                            let subFloat = parseFloat(data.subtotal.replace(/,/g, ''));
                            const isFreeCoupon = parseFloat(data.shipping_fee.replace(/,/g, '')) === 0;
                            updateFreeShippingProgressBar(subFloat, isFreeCoupon);

                            // Recalculate points redemption if checked
                            if (typeof recalculateCheckoutSummary === 'function') {
                                recalculateCheckoutSummary();
                            }

                            // Sync with interactive cart drawer if available
                            if (typeof window.loadCartDrawer === 'function') {
                                window.loadCartDrawer();
                            }
                        }, 300);
                    }
                }
            });
        }
    });
}

function generatePromptPayPayload(target, amount) {
    const sanitizedId = target.replace(/[^0-9]/g, '');
    let subtag = '';
    if (sanitizedId.length === 10) {
        const formattedPhone = '0066' + sanitizedId.substring(1);
        subtag = '0113' + formattedPhone;
    } else if (sanitizedId.length === 13) {
        subtag = '0213' + sanitizedId;
    } else {
        subtag = '03' + String(sanitizedId.length).padStart(2, '0') + sanitizedId;
    }
    
    const merchantInfo = '0016A000000677010111' + subtag;
    const merchantLen = String(merchantInfo.length).padStart(2, '0');
    const tag29 = '29' + merchantLen + merchantInfo;
    
    const floatAmount = parseFloat(amount);
    const hasAmount = !isNaN(floatAmount) && floatAmount > 0;
    const poiMethod = hasAmount ? '12' : '11';
    
    let payload = '000201' + '0102' + poiMethod + tag29 + '5802TH5303764';
    
    if (hasAmount) {
        const amountStr = floatAmount.toFixed(2);
        const amountLen = String(amountStr.length).padStart(2, '0');
        payload += '54' + amountLen + amountStr;
    }
    
    payload += '6304';
    
    let crc = 0xFFFF;
    for (let i = 0; i < payload.length; i++) {
        crc ^= (payload.charCodeAt(i) << 8);
        for (let j = 0; j < 8; j++) {
            if (crc & 0x8000) {
                crc = ((crc << 1) ^ 0x1021) & 0xFFFF;
            } else {
                crc = (crc << 1) & 0xFFFF;
            }
        }
    }
    const crcHex = (crc & 0xFFFF).toString(16).toUpperCase().padStart(4, '0');
    
    return payload + crcHex;
}

function updatePaymentUI(radio) {
    const type = radio.dataset.type;
    const acc = radio.dataset.acc || '';
    const holder = radio.dataset.holder || '';
    const name = radio.dataset.name || '';
    const total = document.getElementById('final_total').innerText.replace(/,/g, '');
    
    document.getElementById('qrSection').style.display = 'none';
    document.getElementById('bankSection').style.display = 'none';
    document.getElementById('slipUploadSection').style.display = 'none';

    if (type === 'promptpay') {
        const floatTotal = parseFloat(total);
        const sanitizedTotal = isNaN(floatTotal) ? '0.00' : floatTotal.toFixed(2);
        
        // Generate standard EMVCo payload
        const ppPayload = generatePromptPayPayload(acc, sanitizedTotal);
        
        // Render QR code using high-reliability standard QR server
        document.getElementById('qrImg').src = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(ppPayload)}`;
        document.getElementById('qr-acc-holder').innerText = 'ชื่อบัญชี: ' + (holder || '-');
        document.getElementById('qr-acc-num').innerText = 'เบอร์โทรศัพท์/เลขพร้อมเพย์: ' + acc;
        
        document.getElementById('qrSection').style.display = 'block';
        document.getElementById('slipUploadSection').style.display = 'block';
    } else if (type === 'bank') {
        document.getElementById('bankName').innerText = name;
        document.getElementById('bankAcc').innerText = acc;
        document.getElementById('bankHolder').innerText = holder || '-';
        
        document.getElementById('bankSection').style.display = 'block';
        document.getElementById('slipUploadSection').style.display = 'block';
    }
}

function validateForm() {
    const pm = document.querySelector('input[name="payment_method_id"]:checked');
    const addr = document.querySelector('input[name="selected_address"]:checked');
    if(!addr) { Swal.fire('ข้อมูลไม่ครบ','กรุณาเลือกที่อยู่จัดส่ง','warning'); return; }
    if(!pm) { Swal.fire('ข้อมูลไม่ครบ','กรุณาเลือกวิธีการชำระเงิน','warning'); return; }
    const type = pm.dataset.type;
    const file = document.querySelector('input[name="payment_slip"]');
    if(type !== 'cod' && file.files.length === 0) { Swal.fire('ยังไม่แนบสลิป','กรุณาโอนเงินและแนบหลักฐานการโอน','warning'); return; }
    
    Swal.fire({
        title: 'ยืนยันการสั่งซื้อ?', text: "กรุณาตรวจสอบข้อมูลให้ถูกต้อง", icon: 'question',
        showCancelButton: true, confirmButtonText: 'ยืนยัน', cancelButtonText: 'ยกเลิก', confirmButtonColor: '#AEE2FF'
    }).then((r)=>{
        if(r.isConfirmed){
            Swal.fire({
                title: 'กำลังประมวลผล...',
                text: 'ระบบกำลังบันทึกคำสั่งซื้อของคุณ กรุณารอสักครู่',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            const h=document.createElement('input'); h.type='hidden'; h.name='confirm_order'; h.value='true';
            document.getElementById('checkoutForm').appendChild(h);
            document.getElementById('checkoutForm').submit();
        }
    });
}

function togglePointsRedemption(checkbox) {
    recalculateCheckoutSummary();
}

function recalculateCheckoutSummary() {
    const subtotal = parseFloat(document.getElementById('in_total').value) || 0;
    const disc = parseFloat(document.getElementById('in_disc').value) || 0;
    const shippingFeeText = document.getElementById('shipping_fee_val').innerText.trim();
    const shipping = shippingFeeText === 'ส่งฟรี' ? 0 : parseFloat(shippingFeeText.replace(/[^\d.]/g, '')) || 0;
    
    const baseFinalPrice = Math.max(0, subtotal - disc + shipping);
    const pointsToggle = document.getElementById('use_points_toggle');
    let pointsDiscount = 0;

    if (pointsToggle && pointsToggle.checked) {
        const maxPoints = window.userPointsAvailable || 0;
        const pointsSpendRate = window.pointsSpendRate || 1;
        const pointsNeeded = Math.ceil(baseFinalPrice / pointsSpendRate);
        const pointsSpent = Math.min(maxPoints, pointsNeeded);
        pointsDiscount = pointsSpent * pointsSpendRate;
        
        const pointsSpentValEl = document.getElementById('points_spent_val');
        if (pointsSpentValEl) pointsSpentValEl.innerText = pointsSpent;
        
        document.getElementById('points_discount_val').innerText = pointsDiscount.toFixed(2);
        document.getElementById('points_applied_row').classList.remove('d-none');
        document.getElementById('points_applied_row').classList.add('d-flex');
    } else {
        document.getElementById('points_applied_row').classList.add('d-none');
        document.getElementById('points_applied_row').classList.remove('d-flex');
    }

    const finalPrice = Math.max(0, baseFinalPrice - pointsDiscount);
    document.getElementById('final_total').innerText = finalPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('in_final').value = finalPrice.toFixed(2);
    
    // Update QR payment UI if a radio is selected
    const pm = document.querySelector('input[name="payment_method_id"]:checked');
    if (pm) updatePaymentUI(pm);
}
</script>

<!-- Modal สำหรับดูคูปองส่วนลด -->
<div class="modal fade" id="couponModal" tabindex="-1" aria-labelledby="couponModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);">
            <div class="modal-header border-0 pb-0" style="background: linear-gradient(135deg, #f5f8ff, #ffffff);">
                <h5 class="modal-title fw-bold text-dark animate__animated animate__fadeInDown" id="couponModalLabel"><i class="bi bi-ticket-perforated-fill text-primary me-2"></i>คูปองส่วนลดของฉัน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3" style="max-height: 400px; overflow-y: auto; background: #f8f9fa;">
                <div id="coupon-list-container">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">กำลังโหลด...</span>
                        </div>
                        <div class="text-muted mt-2 small">กำลังโหลดคูปองที่พร้อมใช้...</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-2 pb-3 bg-light d-flex justify-content-end">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>

<script>
let couponModalInstance = null;
function openCouponModal() {
    if (!couponModalInstance) {
        couponModalInstance = new bootstrap.Modal(document.getElementById('couponModal'));
    }
    couponModalInstance.show();
    
    // Fetch coupons via AJAX
    fetch('ajax.php?action=get_available_coupons')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('coupon-list-container').innerHTML = data.html;
                
                // Bind click event to apply coupon buttons
                document.querySelectorAll('.btn-apply-coupon').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const code = this.dataset.code;
                        const couponInput = document.querySelector('input[name="coupon_code"]');
                        if (couponInput) {
                            couponInput.value = code;
                            
                            // Close modal
                            couponModalInstance.hide();
                            
                            // Programmatically trigger coupon application form submission
                            const form = document.getElementById('checkoutForm');
                            const applyHidden = document.createElement('input');
                            applyHidden.type = 'hidden';
                            applyHidden.name = 'apply_coupon';
                            applyHidden.value = 'true';
                            form.appendChild(applyHidden);
                            
                            // Show loading Swal
                            Swal.fire({
                                title: 'กำลังใช้คูปอง...',
                                text: 'กรุณารอสักครู่',
                                allowOutsideClick: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });
                            
                            form.submit();
                        }
                    });
                });
            } else {
                document.getElementById('coupon-list-container').innerHTML = `
                    <div class="text-center py-4 text-danger">
                        <i class="bi bi-exclamation-triangle display-6 d-block mb-2"></i>
                        <div>เกิดข้อผิดพลาดในการโหลดคูปอง</div>
                    </div>`;
            }
        })
        .catch(err => {
            console.error(err);
            document.getElementById('coupon-list-container').innerHTML = `
                <div class="text-center py-4 text-danger">
                    <i class="bi bi-exclamation-triangle display-6 d-block mb-2"></i>
                    <div>เกิดข้อผิดพลาดในการโหลดคูปอง</div>
                </div>`;
        });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>