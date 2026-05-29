<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok'); 

$discord_webhook_url = "https://discord.com/api/webhooks/1473327005234761760/yOg6j2pYCa0DDSnqUxs5yCL3mlODeIrnYNNo1nJJldGFjnvDHQalkSHzd6RM0691w-b4"; 

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id'];

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
                $_SESSION['coupon'] = ['code' => $c['code'], 'type' => $c['discount_type'], 'value' => $c['discount_value']]; 
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
                $sql = "INSERT INTO orders (user_id, total_price, discount_amount, final_price, coupon_code, status, address, payment_slip, payment_method, order_date) 
                        VALUES ('$user_id', '$total', '$disc', '$final', '$coupon', 'pending', '$full_addr', '$slip', '{$pm['name']}', NOW())";
                
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

                        $pr = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name, price FROM products WHERE id='$pid'"));
                        $opts_esc = mysqli_real_escape_string($conn, $opts);
                        
                        // บันทึกรายการลงบิล
                        mysqli_query($conn, "INSERT INTO order_items (order_id, product_id, quantity, price, selected_option) VALUES ('$order_id', '$pid', '$qty', '{$pr['price']}', '$opts_esc')");
                        
                        // === ระบบตัดสต๊อกแบบ FIFO ===
                        $qty_needed = $qty;
                        $lot_query = mysqli_query($conn, "SELECT id, stock FROM product_lots WHERE product_id='$pid' AND stock > 0 ORDER BY imported_at ASC");
                        
                        while ($lot = mysqli_fetch_assoc($lot_query)) {
                            if ($qty_needed <= 0) break;
                            $lot_id = $lot['id'];
                            $lot_stock = $lot['stock'];
                            
                            if ($lot_stock >= $qty_needed) {
                                // ล็อตนี้ของพอ ตัดสต๊อกและจบการทำงาน
                                mysqli_query($conn, "UPDATE product_lots SET stock = stock - $qty_needed WHERE id='$lot_id'");
                                $qty_needed = 0;
                            } else {
                                // ล็อตนี้ของไม่พอ ตัดจนเหลือ 0 แล้วไปเอาล็อตถัดไปต่อ
                                mysqli_query($conn, "UPDATE product_lots SET stock = 0 WHERE id='$lot_id'");
                                $qty_needed -= $lot_stock;
                            }
                        }
                        
                        // ซิงค์ตารางสินค้าหลัก (อัปเดตราคาใหม่ล่าสุด และสต๊อกรวม)
                        $q_tot = mysqli_query($conn, "SELECT SUM(stock) as total_stock FROM product_lots WHERE product_id='$pid' AND stock > 0");
                        $tot = mysqli_fetch_assoc($q_tot)['total_stock'] ?? 0;

                        $q_price = mysqli_query($conn, "SELECT price FROM product_lots WHERE product_id='$pid' AND stock > 0 ORDER BY imported_at ASC LIMIT 1");
                        $r_price = mysqli_fetch_assoc($q_price);

                        if ($tot > 0 && $r_price) {
                            $new_price = $r_price['price'];
                            mysqli_query($conn, "UPDATE products SET stock='$tot', price='$new_price' WHERE id='$pid'");
                        } else {
                            mysqli_query($conn, "UPDATE products SET stock=0 WHERE id='$pid'");
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
?>

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
                                            $line_total = $row['price'] * $qty;
                                            $subtotal += $line_total;
                                ?>
                                <div class="item-row d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom" id="item-row-<?= $key ?>">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= $row['image'] ?>" class="item-img">
                                        <div>
                                            <h6 class="mb-0 fw-bold text-dark"><?= $row['name'] ?></h6>
                                            <?php if($opts): ?>
                                                <small class="text-muted bg-light px-2 py-0 rounded border d-inline-block mt-1"><?= $opts ?></small>
                                            <?php endif; ?>
                                            <div class="text-muted small mt-1">฿<?= number_format($row['price']) ?> / ชิ้น</div>
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
                                $disc = isset($_SESSION['coupon']) ? ($_SESSION['coupon']['type']=='fixed'?$_SESSION['coupon']['value']:$subtotal*$_SESSION['coupon']['value']/100) : 0;
                                $final = max(0, $subtotal - $disc);
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
                            
                            <div class="input-group input-group-sm mb-3">
                                <input type="text" name="coupon_code" class="form-control" placeholder="โค้ดส่วนลด" value="<?= isset($_SESSION['coupon'])?$_SESSION['coupon']['code']:'' ?>">
                                <input type="hidden" name="current_total" id="hidden_total" value="<?=$subtotal?>">
                                <button class="btn btn-dark" type="submit" name="apply_coupon" formnovalidate>ใช้</button>
                            </div>
                            <?php if($disc>0): ?>
                                <div class="d-flex justify-content-between small mb-2 text-success"><span>ส่วนลด</span><span>-฿<span id="discount_val"><?=number_format($disc,2)?></span></span></div>
                                <div class="text-end mb-2"><a href="?remove_coupon=1" class="text-danger small text-decoration-none">ยกเลิกคูปอง</a></div>
                            <?php endif; ?>

                            <div class="d-flex justify-content-between small mb-2 text-muted"><span>ยอดรวม</span><span>฿<span id="subtotal"><?=number_format($subtotal,2)?></span></span></div>
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
function saveAddressCart() {
    const form = document.getElementById('form-add-address-cart');
    const formData = new FormData(form);

    fetch('ajax.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
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
                            
                            const badge = document.getElementById('nav-cart-badge');
                            badge.innerText = data.cart_count;
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
            const h=document.createElement('input'); h.type='hidden'; h.name='confirm_order'; h.value='true';
            document.getElementById('checkoutForm').appendChild(h);
            document.getElementById('checkoutForm').submit();
        }
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>