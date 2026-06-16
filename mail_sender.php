<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

function send_order_email($conn, $order_id) {
    // 1. ดึงข้อมูลตั้งค่าร้านค้า (SMTP Credentials)
    $shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id = 1"));
    $smtp_user = getenv('SMTP_USER') ?: ($shop['smtp_user'] ?? '');
    $smtp_pass = getenv('SMTP_PASS') ?: ($shop['smtp_pass'] ?? '');
    if (empty($smtp_user) || empty($smtp_pass)) {
        // ถ้ายังไม่ได้ตั้งค่า SMTP ให้ข้ามการส่งเมลโดยไม่เอ๋อ
        return false;
    }

    // 2. ดึงข้อมูลออเดอร์และลูกค้า
    $order_id = mysqli_real_escape_string($conn, $order_id);
    $q_order = mysqli_query($conn, "SELECT o.*, u.email, u.fullname FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = '$order_id'");
    $order = mysqli_fetch_assoc($q_order);
    if (!$order || empty($order['email'])) return false;

    // 3. ดึงรายการสินค้าในออเดอร์
    $q_items = mysqli_query($conn, "SELECT oi.*, p.name, p.image FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = '$order_id'");
    $items = [];
    while ($r = mysqli_fetch_assoc($q_items)) {
        $items[] = $r;
    }

    // 4. เตรียมรายละเอียดอีเมล
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Timeout    = 5; // Timeout 5 วินาทีเพื่อป้องกันระบบค้างหากโฮสต์ผิดพลาด
        $mail->Host       = getenv('SMTP_HOST') ?: ($shop['smtp_host'] ?? '');
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = str_replace(' ', '', $smtp_pass);
        
        $secure = strtolower(getenv('SMTP_SECURE') ?: ($shop['smtp_secure'] ?? 'tls'));
        if ($secure === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPAutoTLS = false;
            $mail->SMTPSecure = '';
        }
        
        $mail->Port       = getenv('SMTP_PORT') !== false ? intval(getenv('SMTP_PORT')) : intval($shop['smtp_port'] ?? 587);

        // Recipients
        $mail->setFrom($smtp_user, $shop['shop_name'] ?? 'Shop');
        $mail->addAddress($order['email'], $order['fullname']);

        // ตั้งหัวข้ออีเมลตามสถานะออเดอร์
        $status = $order['status'];
        $order_num = str_pad($order_id, 5, '0', STR_PAD_LEFT);
        
        if ($status === 'approved') {
            $mail->Subject = "📦 ยืนยันคำสั่งซื้อ #$order_num ได้รับยอดโอนเงินเรียบร้อยแล้ว";
        } elseif ($status === 'shipping') {
            $mail->Subject = "🚚 คำสั่งซื้อ #$order_num กำลังจัดส่งสินค้าแล้ว!";
        } elseif ($status === 'completed') {
            $mail->Subject = "✅ คำสั่งซื้อ #$order_num สำเร็จเรียบร้อยแล้ว";
        } else {
            $mail->Subject = "🛍️ รายละเอียดคำสั่งซื้อ #$order_num - " . $shop['shop_name'];
        }

        // 5. สร้างเทมเพลตจดหมาย HTML (สวยๆ ธีมพาสเทล)
        $status_texts = [
            'pending' => 'รอการตรวจสอบสลิป',
            'approved' => 'ชำระเงินสำเร็จ (กำลังเตรียมพัสดุ)',
            'shipping' => 'กำลังจัดส่งสินค้า',
            'completed' => 'คำสั่งซื้อเสร็จสมบูรณ์',
            'cancelled' => 'คำสั่งซื้อยกเลิก'
        ];
        $status_desc = $status_texts[$status] ?? $status;

        $items_html = '';
        foreach ($items as $item) {
            $items_html .= "
            <tr>
                <td style='padding: 12px; border-bottom: 1px solid #f1f5f9;'>
                    <div style='font-weight: 600; color: #1e293b;'>{$item['name']}</div>
                    " . (!empty($item['selected_option']) ? "<div style='font-size: 0.8rem; color: #64748b; margin-top: 4px;'>ตัวเลือก: {$item['selected_option']}</div>" : "") . "
                </td>
                <td style='padding: 12px; border-bottom: 1px solid #f1f5f9; text-align: center; color: #64748b;'>x{$item['quantity']}</td>
                <td style='padding: 12px; border-bottom: 1px solid #f1f5f9; text-align: right; font-weight: 600; color: #1e293b;'>฿" . number_format($item['price'] * $item['quantity'], 2) . "</td>
            </tr>";
        }

        $tracking_html = '';
        if (!empty($order['tracking_no'])) {
            $carrier_label = match($order['shipping_carrier'] ?? '') {
                'thailandpost' => 'ไปรษณีย์ไทย',
                'kerry', 'kex' => 'KEX Express',
                'flash' => 'Flash Express',
                'jnt' => 'J&T Express',
                default => 'บริการขนส่งหลัก'
            };
            $tracking_html = "
            <div style='background-color: #f0fdf4; border: 1px dashed #bbf7d0; border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 25px;'>
                <div style='font-size: 0.9rem; color: #166534; font-weight: 600; margin-bottom: 4px;'>🚚 จัดส่งสินค้าแล้วโดย {$carrier_label}</div>
                <div style='font-size: 1.6rem; color: #15803d; font-weight: 800; letter-spacing: 1px;'>{$order['tracking_no']}</div>
                <div style='font-size: 0.8rem; color: #166534; margin-top: 6px;'>คุณสามารถนำเลขพัสดุไปเช็คสถานะการจัดส่งได้ทันที</div>
            </div>";
        }

        $mail_body = "
        <div style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 40px 20px; color: #1e293b; line-height: 1.6;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 24px; box-shadow: 0 10px 30px rgba(174, 226, 255, 0.15); border: 1px solid #e2e8f0; overflow: hidden;'>
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #AEE2FF 0%, #7FB5FF 100%); padding: 30px; text-align: center; color: #ffffff;'>
                    <h2 style='margin: 0; font-size: 1.5rem; font-weight: 800;'>{$shop['shop_name']}</h2>
                    <p style='margin: 5px 0 0 0; font-size: 0.9rem; opacity: 0.9;'>ขอขอบพระคุณสำหรับคำสั่งซื้อของคุณ</p>
                </div>
                
                <div style='padding: 30px;'>
                    <!-- Greeting & Status -->
                    <div style='margin-bottom: 25px;'>
                        <div style='font-size: 1.1rem; font-weight: 600; margin-bottom: 8px;'>สวัสดีคุณ {$order['fullname']},</div>
                        <p style='margin: 0; color: #64748b;'>เราได้อัปเดตสถานะของใบสั่งซื้อ #$order_num เป็น <b style='color: #7FB5FF;'>$status_desc</b> เรียบร้อยแล้วครับ</p>
                    </div>

                    <!-- Tracking Section -->
                    $tracking_html

                    <!-- Order Details Table -->
                    <table style='width: 100%; border-collapse: collapse; margin-bottom: 25px;'>
                        <thead>
                            <tr style='background-color: #f8fafc;'>
                                <th style='padding: 12px; text-align: left; font-weight: 600; color: #64748b; font-size: 0.85rem; border-bottom: 2px solid #e2e8f0;'>รายการ</th>
                                <th style='padding: 12px; text-align: center; font-weight: 600; color: #64748b; font-size: 0.85rem; border-bottom: 2px solid #e2e8f0;'>จำนวน</th>
                                <th style='padding: 12px; text-align: right; font-weight: 600; color: #64748b; font-size: 0.85rem; border-bottom: 2px solid #e2e8f0;'>ราคารวม</th>
                            </tr>
                        </thead>
                        <tbody>
                            $items_html
                        </tbody>
                    </table>

                    <!-- Summary Block -->
                    <div style='background-color: #f8fafc; border-radius: 12px; padding: 20px; margin-bottom: 25px;'>
                        <div style='display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.9rem; color: #64748b;'>
                            <span style='flex-grow: 1; text-align: left;'>ยอดรวมราคาสินค้า:</span>
                            <span style='text-align: right;'>฿" . number_format($order['total_price'], 2) . "</span>
                        </div>
                        " . ($order['discount_amount'] > 0 ? "
                        <div style='display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.9rem; color: #16a34a;'>
                            <span style='flex-grow: 1; text-align: left;'>คูปองส่วนลด:</span>
                            <span style='text-align: right;'>-฿" . number_format($order['discount_amount'], 2) . "</span>
                        </div>" : "") . "
                        <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 12px 0;'>
                        <div style='display: flex; justify-content: space-between; font-weight: 700; font-size: 1.1rem; color: #1e293b;'>
                            <span style='flex-grow: 1; text-align: left;'>ยอดชำระสุทธิ:</span>
                            <span style='text-align: right; color: #7FB5FF;'>฿" . number_format($order['final_price'], 2) . "</span>
                        </div>
                    </div>

                    <!-- Shipping Address -->
                    <div style='margin-bottom: 30px; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;'>
                        <div style='font-weight: 600; margin-bottom: 8px; color: #1e293b;'>📍 ที่อยู่จัดส่งพัสดุ</div>
                        <div style='font-size: 0.9rem; color: #64748b; white-space: pre-line; line-height: 1.5;'>{$order['address']}</div>
                    </div>

                    <!-- Footer Note -->
                    <div style='text-align: center; font-size: 0.8rem; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 25px;'>
                        <p style='margin: 0 0 5px 0;'>หากมีข้อสงสัยเพิ่มเติม สามารถติดต่อร้านค้าได้ที่เบอร์ {$shop['phone']} หรืออีเมล {$shop['shop_email']}</p>
                        <p style='margin: 0;'>ขอบคุณที่ไว้วางใจใช้บริการช้อปปิ้งกับเรา</p>
                    </div>
                </div>
            </div>
        </div>";

        $mail->isHTML(true);
        $mail->Body    = $mail_body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer error: " . $mail->ErrorInfo);
        return false;
    }
}

function send_test_email($conn) {
    // ดึงข้อมูลตั้งค่าร้านค้า (SMTP Credentials)
    $shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id = 1"));
    $smtp_user = getenv('SMTP_USER') ?: ($shop['smtp_user'] ?? '');
    $smtp_pass = getenv('SMTP_PASS') ?: ($shop['smtp_pass'] ?? '');
    if (empty($smtp_user) || empty($smtp_pass)) {
        return "กรุณากรอกข้อมูล SMTP และรหัสผ่านก่อนทดสอบ";
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Timeout    = 5; // Timeout 5 วินาทีเพื่อป้องกันระบบค้างหากโฮสต์ผิดพลาด
        $mail->Host       = getenv('SMTP_HOST') ?: ($shop['smtp_host'] ?? '');
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = str_replace(' ', '', $smtp_pass);
        
        $secure = strtolower(getenv('SMTP_SECURE') ?: ($shop['smtp_secure'] ?? 'tls'));
        if ($secure === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPAutoTLS = false;
            $mail->SMTPSecure = '';
        }
        
        $mail->Port       = getenv('SMTP_PORT') !== false ? intval(getenv('SMTP_PORT')) : intval($shop['smtp_port'] ?? 587);

        // Recipients
        $mail->setFrom($smtp_user, $shop['shop_name'] ?? 'Shop');
        $mail->addAddress($smtp_user, "Admin Connection Test");

        // Content
        $mail->isHTML(true);
        $mail->Subject = "⚙️ ทดสอบการเชื่อมต่อระบบ SMTP - " . $shop['shop_name'];
        $mail->Body    = "
        <div style='font-family: Arial, sans-serif; padding: 25px; background-color: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0; max-width: 500px;'>
            <h3 style='color: #7FB5FF; margin-top: 0;'>🎉 เชื่อมต่อระบบ SMTP สำเร็จเรียบร้อย!</h3>
            <p style='color: #475569; font-size: 0.95rem; line-height: 1.5;'>ระบบสามารถเชื่อมต่อกับโฮสต์และลงชื่อเข้าใช้งานด้วยชื่อผู้ใช้งานและรหัสผ่านที่คุณตั้งค่าไว้ได้อย่างถูกต้องแล้วครับ</p>
            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
            <small style='color: #94a3b8;'>ส่งโดยอัตโนมัติจากระบบหลังบ้านของร้าน " . $shop['shop_name'] . "</small>
        </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "PHPMailer Error: " . $mail->ErrorInfo;
    }
}

function send_password_reset_email($conn, $email, $otp) {
    // 1. ดึงข้อมูลตั้งค่าร้านค้า (SMTP Credentials)
    $shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id = 1"));
    $smtp_user = getenv('SMTP_USER') ?: ($shop['smtp_user'] ?? '');
    $smtp_pass = getenv('SMTP_PASS') ?: ($shop['smtp_pass'] ?? '');
    if (empty($smtp_user) || empty($smtp_pass)) {
        return false;
    }

    // 2. ดึงข้อมูลผู้ใช้งาน
    $email = mysqli_real_escape_string($conn, $email);
    $q_user = mysqli_query($conn, "SELECT fullname FROM users WHERE email = '$email'");
    $user = mysqli_fetch_assoc($q_user);
    $fullname = $user ? $user['fullname'] : "ลูกค้า";

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Timeout    = 5;
        $mail->Host       = getenv('SMTP_HOST') ?: ($shop['smtp_host'] ?? '');
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = str_replace(' ', '', $smtp_pass);
        
        $secure = strtolower(getenv('SMTP_SECURE') ?: ($shop['smtp_secure'] ?? 'tls'));
        if ($secure === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPAutoTLS = false;
            $mail->SMTPSecure = '';
        }
        
        $mail->Port       = getenv('SMTP_PORT') !== false ? intval(getenv('SMTP_PORT')) : intval($shop['smtp_port'] ?? 587);

        // Recipients
        $mail->setFrom($smtp_user, $shop['shop_name'] ?? 'Shop');
        $mail->addAddress($email, $fullname);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "🔑 รหัส OTP สำหรับรีเซ็ตรหัสผ่านของคุณ - " . $shop['shop_name'];
        
        $mail_body = "
        <div style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 40px 20px; color: #1e293b; line-height: 1.6;'>
            <div style='max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 24px; box-shadow: 0 10px 30px rgba(174, 226, 255, 0.15); border: 1px solid #e2e8f0; overflow: hidden;'>
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #AEE2FF 0%, #7FB5FF 100%); padding: 30px; text-align: center; color: #ffffff;'>
                    <h2 style='margin: 0; font-size: 1.4rem; font-weight: 800;'>รีเซ็ตรหัสผ่านใหม่</h2>
                    <p style='margin: 5px 0 0 0; font-size: 0.9rem; opacity: 0.9;'>{$shop['shop_name']}</p>
                </div>
                
                <div style='padding: 30px; text-align: center;'>
                    <p style='font-size: 1rem; color: #475569; text-align: left;'>สวัสดีคุณ <b>{$fullname}</b>,</p>
                    <p style='font-size: 0.95rem; color: #64748b; text-align: left; margin-bottom: 25px;'>คุณได้ส่งคำขอเพื่อตั้งรหัสผ่านใหม่ กรุณาใช้รหัสยืนยันตัวตน (OTP) ด้านล่างนี้เพื่อดำเนินการต่อ:</p>
                    
                    <!-- OTP Display Block -->
                    <div style='background-color: #f0f8ff; border: 2px dashed #7FB5FF; border-radius: 16px; padding: 20px; margin-bottom: 25px; display: inline-block; min-width: 200px;'>
                        <div style='font-size: 0.8rem; color: #7FB5FF; font-weight: 700; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px;'>รหัสยืนยัน OTP</div>
                        <div style='font-size: 2.2rem; color: #1d4ed8; font-weight: 800; letter-spacing: 4px; line-height: 1;'>{$otp}</div>
                    </div>
                    
                    <p style='font-size: 0.8rem; color: #94a3b8; margin-bottom: 30px;'>* รหัสยืนยันตัวตนนี้จะมีอายุการใช้งาน 15 นาที เพื่อความปลอดภัยของบัญชี กรุณาอย่าส่งต่อรหัสนี้ให้ผู้อื่น</p>
                    
                    <!-- Footer Note -->
                    <div style='text-align: center; font-size: 0.8rem; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 20px;'>
                        <p style='margin: 0;'>หากคุณไม่ได้ส่งคำขอนี้ สามารถละเลยอีเมลฉบับนี้ได้ทันที</p>
                        <p style='margin: 5px 0 0 0;'>ติดต่อเรา: {$shop['shop_email']}</p>
                    </div>
                </div>
            </div>
        </div>";

        $mail->Body = $mail_body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer error (OTP): " . $mail->ErrorInfo);
        return false;
    }
}

function send_admin_2fa_otp($conn, $email, $fullname, $otp) {
    // 1. ดึงข้อมูลตั้งค่าร้านค้า (SMTP Credentials)
    $shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id = 1"));
    $smtp_user = getenv('SMTP_USER') ?: ($shop['smtp_user'] ?? '');
    $smtp_pass = getenv('SMTP_PASS') ?: ($shop['smtp_pass'] ?? '');
    if (empty($smtp_user) || empty($smtp_pass)) {
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Timeout    = 5;
        $mail->Host       = getenv('SMTP_HOST') ?: ($shop['smtp_host'] ?? '');
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = str_replace(' ', '', $smtp_pass);
        
        $secure = strtolower(getenv('SMTP_SECURE') ?: ($shop['smtp_secure'] ?? 'tls'));
        if ($secure === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPAutoTLS = false;
            $mail->SMTPSecure = '';
        }
        
        $mail->Port       = getenv('SMTP_PORT') !== false ? intval(getenv('SMTP_PORT')) : intval($shop['smtp_port'] ?? 587);

        // Recipients
        $mail->setFrom($smtp_user, $shop['shop_name'] ?? 'Shop');
        $mail->addAddress($email, $fullname);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "🛡️ รหัสยืนยันตน 2FA (OTP) สำหรับเข้าสู่ระบบแอดมิน - " . $shop['shop_name'];
        
        $mail_body = "
        <div style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 40px 20px; color: #1e293b; line-height: 1.6;'>
            <div style='max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 24px; box-shadow: 0 10px 30px rgba(133, 209, 255, 0.15); border: 1px solid #e2e8f0; overflow: hidden;'>
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #85D1FF 0%, #6BBEFF 100%); padding: 30px; text-align: center; color: #ffffff;'>
                    <h2 style='margin: 0; font-size: 1.4rem; font-weight: 800;'>ยืนยันตนเข้าสู่ระบบแอดมิน</h2>
                    <p style='margin: 5px 0 0 0; font-size: 0.9rem; opacity: 0.9;'>{$shop['shop_name']}</p>
                </div>
                
                <div style='padding: 30px; text-align: center;'>
                    <p style='font-size: 1rem; color: #475569; text-align: left;'>สวัสดีคุณ <b>{$fullname}</b> (แอดมิน),</p>
                    <p style='font-size: 0.95rem; color: #64748b; text-align: left; margin-bottom: 25px;'>มีการตรวจพบการพยายามเข้าสู่ระบบจัดการร้านค้าหลังบ้านของคุณผ่านอุปกรณ์ใหม่ กรุณาใช้รหัส OTP ด้านล่างเพื่อยืนยันตน:</p>
                    
                    <!-- OTP Display Block -->
                    <div style='background-color: #f0f8ff; border: 2px dashed #85D1FF; border-radius: 16px; padding: 20px; margin-bottom: 25px; display: inline-block; min-width: 200px;'>
                        <div style='font-size: 0.8rem; color: #6BBEFF; font-weight: 700; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px;'>รหัสยืนยัน OTP (เข้าสู่ระบบ)</div>
                        <div style='font-size: 2.2rem; color: #0284c7; font-weight: 800; letter-spacing: 4px; line-height: 1;'>{$otp}</div>
                    </div>
                    
                    <p style='font-size: 0.8rem; color: #94a3b8; margin-bottom: 30px;'>* รหัสยืนยันตัวตนนี้จะมีอายุการใช้งาน 5 นาที หากคุณไม่ได้ลงชื่อเข้าใช้ด้วยตัวเอง โปรดเปลี่ยนรหัสผ่านทันทีเพื่อความปลอดภัย</p>
                    
                    <!-- Footer Note -->
                    <div style='text-align: center; font-size: 0.8rem; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 20px;'>
                        <p style='margin: 0;'>หากมีข้อสงสัย ติดต่อเจ้าหน้าที่: {$shop['shop_email']}</p>
                    </div>
                </div>
            </div>
        </div>";

        $mail->Body = $mail_body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer error (Admin 2FA OTP): " . $mail->ErrorInfo);
        return false;
    }
}

function send_custom_reply($conn, $to_email, $to_name, $original_subject, $reply_content) {
    // 1. ดึงข้อมูลตั้งค่าร้านค้า (SMTP Credentials)
    $shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id = 1"));
    $smtp_user = getenv('SMTP_USER') ?: ($shop['smtp_user'] ?? '');
    $smtp_pass = getenv('SMTP_PASS') ?: ($shop['smtp_pass'] ?? '');
    if (empty($smtp_user) || empty($smtp_pass)) {
        return "กรุณาตั้งค่าระบบอีเมล SMTP ในเมนูตั้งค่าร้านค้าก่อนใช้งานระบบตอบกลับ";
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Timeout    = 5;
        $mail->Host       = getenv('SMTP_HOST') ?: ($shop['smtp_host'] ?? '');
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = str_replace(' ', '', $smtp_pass);
        
        $secure = strtolower(getenv('SMTP_SECURE') ?: ($shop['smtp_secure'] ?? 'tls'));
        if ($secure === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPAutoTLS = false;
            $mail->SMTPSecure = '';
        }
        
        $mail->Port       = getenv('SMTP_PORT') !== false ? intval(getenv('SMTP_PORT')) : intval($shop['smtp_port'] ?? 587);

        // Recipients
        $mail->setFrom($smtp_user, $shop['shop_name'] ?? 'Shop');
        $mail->addAddress($to_email, $to_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Re: " . $original_subject . " - " . ($shop['shop_name'] ?? 'Shop');
        
        // HTML Body template
        $mail_body = "
        <div style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 40px 20px; color: #1e293b; line-height: 1.6;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 24px; box-shadow: 0 10px 30px rgba(174, 226, 255, 0.15); border: 1px solid #e2e8f0; overflow: hidden;'>
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #AEE2FF 0%, #7FB5FF 100%); padding: 30px; text-align: center; color: #ffffff;'>
                    <h2 style='margin: 0; font-size: 1.5rem; font-weight: 800;'>ตอบกลับข้อความติดต่อ</h2>
                    <p style='margin: 5px 0 0 0; font-size: 0.9rem; opacity: 0.9;'>{$shop['shop_name']}</p>
                </div>
                
                <div style='padding: 30px;'>
                    <p style='font-size: 1.1rem; font-weight: 600; margin-bottom: 8px; color: #1e293b;'>สวัสดีคุณ {$to_name},</p>
                    <p style='color: #475569;'>ทางทีมงานแอดมินของ <b>{$shop['shop_name']}</b> ได้ทำการตรวจสอบข้อความติดต่อของคุณในหัวข้อ \"<b>{$original_subject}</b>\" เรียบร้อยแล้วครับ</p>
                    
                    <div style='background-color: #f8fafc; border-left: 4px solid #7FB5FF; border-radius: 4px; padding: 20px; margin: 25px 0; color: #1e293b;'>
                        <div style='font-weight: 700; font-size: 0.9rem; color: #64748b; margin-bottom: 10px;'>✉️ ข้อความตอบกลับจากแอดมิน:</div>
                        <div style='white-space: pre-wrap; font-size: 0.95rem; line-height: 1.6;'>{$reply_content}</div>
                    </div>
                    
                    <p style='color: #64748b; font-size: 0.9rem;'>หากคุณมีความคิดเห็นหรือคำถามเพิ่มเติม สามารถตอบกลับอีเมลนี้หรือติดต่อเราได้ตามข้อมูลการติดต่อด้านล่างครับ</p>
                    
                    <!-- Footer Note -->
                    <div style='text-align: center; font-size: 0.8rem; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 25px; margin-top: 30px;'>
                        <p style='margin: 0 0 5px 0;'>เบอร์โทรติดต่อ: {$shop['phone']} • อีเมล: {$shop['shop_email']}</p>
                        <p style='margin: 0;'>ขอแสดงความนับถือ, {$shop['shop_name']}</p>
                    </div>
                </div>
            </div>
        </div>";

        $mail->Body = $mail_body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return "PHPMailer error: " . $mail->ErrorInfo;
    }
}

function send_direct_custom_email($conn, $to_email, $to_name, $subject, $body_content, $attachments = [], $promo_image = null, $coupon_data = null) {
    // 1. ดึงข้อมูลตั้งค่าร้านค้า (SMTP Credentials)
    $shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id = 1"));
    $smtp_user = getenv('SMTP_USER') ?: ($shop['smtp_user'] ?? '');
    $smtp_pass = getenv('SMTP_PASS') ?: ($shop['smtp_pass'] ?? '');
    if (empty($smtp_user) || empty($smtp_pass)) {
        return "กรุณาตั้งค่าระบบอีเมล SMTP ในเมนูตั้งค่าร้านค้าก่อนส่งอีเมล";
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Timeout    = 15; // 15 วินาทีเพื่อป้องกันระบบค้างเนื่องจากมีไฟล์แนบ
        $mail->Host       = getenv('SMTP_HOST') ?: ($shop['smtp_host'] ?? '');
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = str_replace(' ', '', $smtp_pass);
        
        $secure = strtolower(getenv('SMTP_SECURE') ?: ($shop['smtp_secure'] ?? 'tls'));
        if ($secure === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPAutoTLS = false;
            $mail->SMTPSecure = '';
        }
        
        $mail->Port       = getenv('SMTP_PORT') !== false ? intval(getenv('SMTP_PORT')) : intval($shop['smtp_port'] ?? 587);

        // Recipients
        $mail->setFrom($smtp_user, $shop['shop_name'] ?? 'Shop');
        $mail->addAddress($to_email, $to_name);

        // Attachments
        if (!empty($attachments)) {
            foreach ($attachments as $att) {
                if (isset($att['tmp_name']) && file_exists($att['tmp_name'])) {
                    $mail->addAttachment($att['tmp_name'], $att['name']);
                }
            }
        }

        // Promo Image Embedding (Content-ID inline image)
        if ($promo_image !== null && isset($promo_image['tmp_name']) && file_exists($promo_image['tmp_name'])) {
            $mail->addEmbeddedImage($promo_image['tmp_name'], 'promo_image', $promo_image['name']);
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        
        // HTML Body template
        $promo_img_html = '';
        if ($promo_image !== null) {
            $promo_img_html = "
            <div style='text-align: center; border-bottom: 1px solid #e2e8f0; line-height: 0;'>
                <img src='cid:promo_image' style='max-width: 100%; height: auto; display: block;' alt='Promotion Banner'>
            </div>";
        }

        // Coupon Box HTML
        $coupon_html = '';
        if ($coupon_data !== null) {
            $code = htmlspecialchars($coupon_data['code']);
            
            $desc = ($coupon_data['discount_type'] === 'percentage') 
                ? "ส่วนลดพิเศษ " . floatval($coupon_data['discount_value']) . "%"
                : "ส่วนลดพิเศษ ฿" . number_format($coupon_data['discount_value']);
                
            if ($coupon_data['min_spend'] > 0) {
                $desc .= " เมื่อช้อปขั้นต่ำ ฿" . number_format($coupon_data['min_spend']);
            }
            
            if ($coupon_data['max_discount'] > 0 && $coupon_data['discount_type'] === 'percentage') {
                $desc .= " (ลดสูงสุด ฿" . number_format($coupon_data['max_discount']) . ")";
            }
            
            $exp_text = ($coupon_data['expiry_date']) 
                ? "หมดเขต: " . date('d/m/Y', strtotime($coupon_data['expiry_date']))
                : "ไม่มีวันหมดอายุ";
                
            $coupon_html = "
            <div style='margin: 25px 0; padding: 25px; text-align: center; border: 2px dashed #7FB5FF; background-color: #f0f7ff; border-radius: 16px;'>
                <div style='font-size: 0.8rem; color: #7FB5FF; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;'>คูปองส่วนลดพิเศษสำหรับคุณ</div>
                <div style='font-size: 2rem; color: #1d4ed8; font-weight: 800; letter-spacing: 2px; line-height: 1.2; margin-bottom: 8px;'>$code</div>
                <div style='font-size: 0.95rem; color: #475569; font-weight: 600; margin-bottom: 4px;'>$desc</div>
                <div style='font-size: 0.8rem; color: #94a3b8;'>$exp_text</div>
            </div>";
        }

        $mail_body = "
        <div style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 40px 20px; color: #1e293b; line-height: 1.6;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 24px; box-shadow: 0 10px 30px rgba(174, 226, 255, 0.15); border: 1px solid #e2e8f0; overflow: hidden;'>
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #AEE2FF 0%, #7FB5FF 100%); padding: 30px; text-align: center; color: #ffffff;'>
                    <h2 style='margin: 0; font-size: 1.5rem; font-weight: 800;'>" . htmlspecialchars($shop['shop_name'] ?? 'Shop') . "</h2>
                </div>
                
                $promo_img_html
                
                <div style='padding: 30px;'>
                    <p style='font-size: 1.1rem; font-weight: 600; margin-bottom: 8px; color: #1e293b;'>สวัสดีคุณ {$to_name},</p>
                    
                    <div style='margin: 25px 0; color: #475569; font-size: 1rem; line-height: 1.6; white-space: pre-wrap;'>" . nl2br(htmlspecialchars($body_content)) . "</div>
                    
                    $coupon_html
                    
                    <!-- Footer Note -->
                    <div style='text-align: center; font-size: 0.8rem; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 25px; margin-top: 30px;'>
                        <p style='margin: 0 0 5px 0;'>เบอร์โทรติดต่อ: " . htmlspecialchars($shop['phone'] ?? '-') . " • อีเมล: " . htmlspecialchars($shop['shop_email'] ?? '-') . "</p>
                        <p style='margin: 0;'>ขอแสดงความนับถือ, " . htmlspecialchars($shop['shop_name'] ?? 'Shop') . "</p>
                    </div>
                </div>
            </div>
        </div>";

        $mail->Body = $mail_body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return "PHPMailer error: " . $mail->ErrorInfo;
    }
}
?>
