<?php
session_start();
include 'db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

if (!isset($_GET['id'])) { die("เนมเนเธเธ Order ID"); }
$order_id = $_GET['id'];

// 1. เธเนอมูลรเนาเธเธเนา
$shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id=1"));

// 2. เธเนอมูลออเดอรเน
$order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT orders.*, users.email FROM orders JOIN users ON orders.user_id = users.id WHERE orders.id = '$order_id'"));

// 3. รายการสินค้า
$items = mysqli_query($conn, "SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = '$order_id'");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Label #<?= $order_id ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <style>
        /* --- ตั้งค่าหน้าเธระดาษ A4 --- */
        @page { size: A4; margin: 0; }
        
        body { 
            font-family: 'Kanit', sans-serif; 
            background: #555; 
            margin: 0; 
            padding: 20px; 
            display: flex;
            justify-content: center;
        }

        .page { 
            background: white; 
            width: 210mm; 
            min-height: 297mm; 
            padding: 40px; 
            box-sizing: border-box; 
            display: flex; 
            flex-direction: column; 
            position: relative;
            box-shadow: 0 0 15px rgba(0,0,0,0.5);
        }

        /* Responsive Fix สำหรับดูเธเธมือถือ */
        @media screen and (max-width: 768px) {
            body { padding: 10px; display: block; background: #fff; }
            .page { 
                width: 100%; 
                min-height: auto; 
                padding: 20px; 
                box-shadow: none; 
                border: 1px solid #ddd;
            }
            .receiver-name { font-size: 22px !important; }
            .cod-price { font-size: 26px !important; }
        }

        .header { border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-start; }
        .sender-info { font-size: 16px; color: #333; line-height: 1.5; }
        .sender-title { font-size: 14px; font-weight: bold; color: #777; text-transform: uppercase; margin-bottom: 5px; }
        .shop-name { font-size: 24px; font-weight: bold; color: #000; margin-bottom: 5px; }

        .receiver-box { border: 4px solid #000; padding: 30px; border-radius: 15px; margin-bottom: 30px; position: relative; }
        .receiver-title { position: absolute; top: -12px; left: 30px; background: white; padding: 0 10px; font-weight: bold; color: #777; font-size: 16px; }
        .receiver-name { font-size: 28px; font-weight: bold; line-height: 1.4; }
        .receiver-address { font-size: 22px; line-height: 1.4; margin-top: 10px; }
        .receiver-contact { font-size: 16px; color: #555; margin-top: 15px; }

        .info-section { display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
        .cod-box { flex: 1; text-align: center; background: #000; color: white !important; padding: 20px; border-radius: 10px; min-width: 200px; -webkit-print-color-adjust: exact; }
        .cod-title { font-size: 18px; margin-bottom: 5px; }
        .cod-price { font-size: 32px; font-weight: bold; }

        .note-box { flex: 2; border: 2px dashed #AEE2FF; background-color: #F0F8FF !important; color: #d63384; padding: 20px; border-radius: 10px; font-size: 16px; font-weight: bold; display: flex; align-items: center; justify-content: center; text-align: center; min-width: 200px; -webkit-print-color-adjust: exact; }

        .order-details { font-size: 16px; border: 1px solid #ddd; border-radius: 10px; overflow: hidden; }
        .order-header { background: #f8f9fa; padding: 10px 20px; border-bottom: 1px solid #ddd; font-weight: bold; display: flex; justify-content: space-between; -webkit-print-color-adjust: exact; }
        .item-row { padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; }
        .item-row:last-child { border-bottom: none; }

        .footer { margin-top: auto; text-align: center; font-size: 12px; color: #aaa; border-top: 1px solid #eee; padding-top: 20px; }

        .btn-print { position: fixed; bottom: 30px; right: 30px; background: #AEE2FF; color: white; border: none; width: 70px; height: 70px; border-radius: 50%; cursor: pointer; font-size: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); transition: 0.3s; z-index: 999; display: flex; align-items: center; justify-content: center; }
        .btn-print:hover { transform: scale(1.1); background: #7FB5FF; }

        @media print {
            body { background: white; padding: 0; margin: 0; display: block; }
            .page { width: 100%; height: 100%; border: none; box-shadow: none; margin: 0; padding: 40px; }
            .btn-print { display: none; }
        }
    </style>
</head>
<body>
    
    <button onclick="window.print()" class="btn-print" title="สั่งเธิมเธเน">๐–จ๏ธ</button>

    <div class="page">
        <div class="header">
            <div class="sender-section">
                <div class="sender-title">เธูเนสเนเธ (Sender)</div>
                <div class="shop-name"><?= $shop['shop_name'] ?></div>
                <div class="sender-info">
                    <?= nl2br($shop['address']) ?><br>
                    <b>เนทร:</b> <?= $shop['phone'] ?>
                    <?php if($shop['shop_email']) echo "<br><b>Contact:</b> {$shop['shop_email']}"; ?>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:14px; color:#aaa;">Order ID</div>
                <div style="font-size:30px; font-weight:bold;">#<?= $order_id ?></div>
                <div style="font-size:14px; margin-top:5px;"><?= date('d/m/Y H:i') ?></div>
            </div>
        </div>

        <div class="receiver-box">
            <div class="receiver-title">เธูเนรัเธ (Receiver)</div>
            <div class="receiver-name"><?= explode("\n", $order['address'])[0] ?></div> 
            <div class="receiver-address">
                <?php 
                    $addr_lines = explode("\n", $order['address']);
                    if(count($addr_lines) > 1) unset($addr_lines[0]);
                    echo implode("<br>", $addr_lines);
                ?>
            </div>
            <?php if($order['email']): ?>
                <div class="receiver-contact">Email: <?= $order['email'] ?></div>
            <?php endif; ?>
        </div>

        <div class="info-section">
            <?php if(strpos($order['payment_method'], 'COD') !== false || $order['payment_method'] == 'เก็บเเธิเธเธลายทาเธ (COD)'): ?>
                <div class="cod-box">
                    <div class="cod-title">ยอดเก็บเเธิเธเธลายทาเธ (COD)</div>
                    <div class="cod-price">฿<?= number_format($order['final_price'], 2) ?></div>
                </div>
            <?php endif; ?>

            <?php if(!empty($order['admin_note'])): ?>
                <div class="note-box">
                    โ ๏ธ หมายเหตุ: <?= $order['admin_note'] ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="order-details">
            <div class="order-header">
                <span>รายการสินค้า</span>
                <span>จำนวน</span>
            </div>
            
            <?php while($item = mysqli_fetch_assoc($items)): ?>
                <div class="item-row">
                    <span><?= $item['name'] ?></span>
                    <span style="font-weight:bold;">x<?= $item['quantity'] ?></span>
                </div>
            <?php endwhile; ?>
        </div>
        
        <?php if(!empty($shop['print_remark'])): ?>
            <div style="margin-top:20px; font-weight:bold; color:#d63384; text-align:center;">
                *** <?= $shop['print_remark'] ?> ***
            </div>
        <?php endif; ?>

        <div class="footer">
            ขอบคุณที่อุดหเธุเธ <?= $shop['shop_name'] ?> | Por Mae Bet Taled System
        </div>
    </div>

</body>
</html>


