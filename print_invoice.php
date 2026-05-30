<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

$oid = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($oid <= 0) {
    die("ไม่พบรหัสคำสั่งซื้อ");
}

// ดึงข้อมูลออเดอร์
$order_q = mysqli_query($conn, "SELECT o.*, u.fullname, u.email as user_email FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = '$oid'");
if (!$order_q || mysqli_num_rows($order_q) == 0) {
    die("ไม่พบข้อมูลคำสั่งซื้อ");
}

$order = mysqli_fetch_assoc($order_q);

// ตรวจสอบสิทธิ์ (ต้องเป็นเจ้าของออเดอร์ หรือเป็นแอดมิน)
if ($order['user_id'] != $user_id && $role !== 'admin') {
    die("คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

// ดึงข้อมูลร้านค้า
$shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id = 1"));
$shop_name = $shop['shop_name'] ?? 'Por Mae Bet Taled';
$shop_address = $shop['address'] ?? '';
$shop_phone = $shop['phone'] ?? '';
$shop_email = $shop['shop_email'] ?? '';
$print_remark = $shop['print_remark'] ?? '';
$shop_icon = !empty($shop['shop_icon']) ? "uploads/".$shop['shop_icon'] : "assets/default_icon.png";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบเสร็จรับเงิน ออเดอร์ #<?= str_pad($oid, 5, '0', STR_PAD_LEFT) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background: #fff;
            color: #333;
            margin: 0;
            padding: 20px;
            font-size: 14px;
            line-height: 1.5;
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
            background: #fff;
            border-radius: 12px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .header-table td {
            vertical-align: top;
            border: none;
        }
        .shop-info {
            text-align: left;
        }
        .shop-logo {
            max-width: 60px;
            margin-bottom: 10px;
        }
        .invoice-title {
            text-align: right;
        }
        .invoice-title h2 {
            margin: 0 0 10px 0;
            color: #0d6efd;
            font-size: 26px;
            font-weight: 600;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .details-table td {
            width: 50%;
            vertical-align: top;
            padding: 10px;
            border: 1px solid #f2f2f2;
            background-color: #fafafa;
        }
        .details-title {
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
            font-size: 13px;
            text-transform: uppercase;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th {
            background-color: #f8f9fa;
            color: #333;
            font-weight: 500;
            text-align: left;
            padding: 12px;
            border-bottom: 2px solid #dee2e6;
        }
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: top;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .summary-table td {
            padding: 6px 12px;
            text-align: right;
        }
        .summary-table .label {
            color: #666;
        }
        .summary-table .value {
            font-weight: 500;
            width: 150px;
        }
        .summary-table .final-row td {
            font-size: 18px;
            font-weight: 600;
            color: #dc3545;
            border-top: 2px solid #dee2e6;
            padding-top: 12px;
        }
        .remark-box {
            margin-top: 40px;
            padding: 15px;
            background-color: #fffbeb;
            border: 1px dashed #fef3c7;
            border-radius: 8px;
            font-size: 12px;
            color: #b45309;
        }
        .no-print-area {
            text-align: center;
            margin-bottom: 20px;
        }
        .btn-print {
            background-color: #0d6efd;
            color: #fff;
            border: none;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 500;
            border-radius: 30px;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(13, 110, 253, 0.15);
            transition: all 0.2s;
        }
        .btn-print:hover {
            background-color: #0b5ed7;
            transform: translateY(-1px);
        }
        
        @media print {
            body {
                padding: 0;
            }
            .invoice-box {
                border: none;
                box-shadow: none;
                padding: 0;
            }
            .no-print-area {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-area">
        <button onclick="window.print()" class="btn-print">🖨️ สั่งพิมพ์ใบเสร็จ</button>
    </div>

    <div class="invoice-box">
        <table class="header-table">
            <tr>
                <td class="shop-info">
                    <?php if ($shop_icon): ?>
                        <img src="<?= $shop_icon ?>" class="shop-logo" alt="Logo">
                    <?php endif; ?>
                    <h3 style="margin: 0; font-weight: 600; font-size: 18px;"><?= htmlspecialchars($shop_name) ?></h3>
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">
                        <?= nl2br(htmlspecialchars($shop_address)) ?><br>
                        โทร: <?= htmlspecialchars($shop_phone) ?><br>
                        อีเมล: <?= htmlspecialchars($shop_email) ?>
                    </div>
                </td>
                <td class="invoice-title">
                    <h2>ใบเสร็จรับเงิน</h2>
                    <div style="font-size: 13px; color: #555;">
                        <strong>เลขที่คำสั่งซื้อ:</strong> #<?= str_pad($oid, 5, '0', STR_PAD_LEFT) ?><br>
                        <strong>วันที่สั่งซื้อ:</strong> <?= date('d/m/Y H:i', strtotime($order['order_date'])) ?><br>
                        <strong>สถานะชำระเงิน:</strong> 
                        <?php 
                            if ($order['status'] == 'pending') echo "รอตรวจสอบชำระเงิน";
                            elseif ($order['status'] == 'approved') echo "ชำระเงินแล้ว (เตรียมจัดส่ง)";
                            elseif ($order['status'] == 'shipping') echo "จัดส่งแล้ว";
                            elseif ($order['status'] == 'completed') echo "สำเร็จ";
                            else echo "ยกเลิก";
                        ?>
                    </div>
                </td>
            </tr>
        </table>

        <table class="details-table">
            <tr>
                <td>
                    <div class="details-title">ผู้ส่ง (ร้านค้า)</div>
                    <strong><?= htmlspecialchars($shop_name) ?></strong><br>
                    <?= nl2br(htmlspecialchars($shop_address)) ?><br>
                    โทร: <?= htmlspecialchars($shop_phone) ?>
                </td>
                <td>
                    <div class="details-title">ผู้รับ (ลูกค้า)</div>
                    <strong><?= htmlspecialchars($order['fullname'] ?? 'ผู้ใช้งานถูกลบ/ไม่พบข้อมูล') ?></strong><br>
                    <?= nl2br(htmlspecialchars($order['address'])) ?>
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 50%;">รายการสินค้า</th>
                    <th style="text-align: right; width: 15%;">ราคาต่อชิ้น</th>
                    <th style="text-align: center; width: 15%;">จำนวน</th>
                    <th style="text-align: right; width: 20%;">รวม</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $items = mysqli_query($conn, "SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = '$oid'");
                $subtotal_calc = 0;
                while ($i = mysqli_fetch_assoc($items)):
                    $line_total = $i['price'] * $i['quantity'];
                    $subtotal_calc += $line_total;
                ?>
                <tr>
                    <td>
                        <strong style="font-weight: 500;"><?= htmlspecialchars($i['name']) ?></strong>
                        <?php if (!empty($i['selected_option'])): ?>
                            <div style="font-size: 11px; color: #666; margin-top: 3px;">
                                ตัวเลือก: <?= htmlspecialchars($i['selected_option']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right;">฿<?= number_format($i['price'], 2) ?></td>
                    <td style="text-align: center;"><?= $i['quantity'] ?></td>
                    <td style="text-align: right; font-weight: 500;">฿<?= number_format($line_total, 2) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 50%; vertical-align: top; padding: 10px;">
                    <div style="font-size: 12px; color: #666;">
                        <strong>ช่องทางชำระเงิน:</strong> <?= htmlspecialchars($order['payment_method']) ?><br>
                        <?php if(!empty($order['tracking_no'])): ?>
                            <?php
                            $carrier_lbl = match($order['shipping_carrier'] ?? '') {
                                'thailandpost' => 'ไปรษณีย์ไทย',
                                'kerry', 'kex' => 'KEX Express',
                                'flash' => 'Flash Express',
                                'jnt' => 'J&T Express',
                                default => 'บริการขนส่ง'
                            };
                            ?>
                            <strong>จัดส่งโดย:</strong> <?= $carrier_lbl ?><br>
                            <strong>เลขพัสดุ:</strong> <?= htmlspecialchars($order['tracking_no']) ?><br>
                        <?php endif; ?>
                    </div>
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <table class="summary-table">
                        <tr>
                            <td class="label">ยอดรวมสินค้า:</td>
                            <td class="value">฿<?= number_format($order['total_price'], 2) ?></td>
                        </tr>
                        <?php if (floatval($order['discount_amount']) > 0): ?>
                        <tr>
                            <td class="label">ส่วนลดคูปอง:</td>
                            <td class="value text-success">-฿<?= number_format($order['discount_amount'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if (intval($order['points_spent']) > 0): ?>
                        <tr>
                            <td class="label">ส่วนลดจากแต้ม (ใช้ <?= number_format($order['points_spent']) ?> แต้ม):</td>
                            <td class="value text-success">-฿<?= number_format($order['points_discount'], 2) ?></td>
                        </tr>
                        <?php endif; ?>

                        <?php
                        // คำนวณค่าส่งย้อนกลับจาก ยอดสุทธิ - (ยอดรวมสินค้า - ส่วนลดคูปอง - ส่วนลดแต้ม)
                        $discount_combined = floatval($order['discount_amount']) + floatval($order['points_discount']);
                        $shipping_calc = floatval($order['final_price']) - (floatval($order['total_price']) - $discount_combined);
                        $shipping_calc = max(0, $shipping_calc);
                        ?>
                        <tr>
                            <td class="label">ค่าจัดส่ง:</td>
                            <td class="value"><?= $shipping_calc == 0 ? 'ส่งฟรี' : '฿' . number_format($shipping_calc, 2) ?></td>
                        </tr>
                        <tr class="final-row">
                            <td class="label">ยอดชำระสุทธิ:</td>
                            <td class="value">฿<?= number_format($order['final_price'], 2) ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <?php if (!empty($print_remark)): ?>
            <div class="remark-box">
                <strong>💡 หมายเหตุร้านค้า:</strong> <?= nl2br(htmlspecialchars($print_remark)) ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>
