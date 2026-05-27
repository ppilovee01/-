<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');

// แŠเน‡ควเนˆาเป็น Admin เน„หม
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

// ตั้งชื่อไฟล์
$filename = "Por Mae Bet Taled_Sales_Report_" . date('Y-m-d') . ".xls";

// ส่ง Header บอกBrowser วเนˆานีเนˆคือไฟล์ Excel นะ
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
xmlns:x="urn:schemas-microsoft-com:office:excel"
xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style>
    th { background-color: #AEE2FF; color: white; font-weight: bold; border: 1px solid #000; }
    td { border: 1px solid #000; }
</style>
</head>
<body>
    <h2 style="text-align:center;">รายงานยอดขาย Por Mae Bet Taled</h2>
    <p>วันที่ขเน‰อมูล: <?= date('d/m/Y H:i') ?></p>
    
    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>วันที่สั觫ื้อ</th>
                <th>ชื่อลูกค้า</th>
                <th>สินค้าที่สั่ง</th>
                <th>ยอดรวม (บาท)</th>
                <th>ส่วนลด</th>
                <th>ยอดสุทธิ (บาท)</th>
                <th>วิธีการชำระแ‡ิน</th>
                <th>สถานะ</th>
                <th>ที่อยู่จัดส่ง</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql = "SELECT o.*, u.fullname, 
                    GROUP_CONCAT(CONCAT(p.name, ' (x', oi.quantity, ')') SEPARATOR ', ') as items
                    FROM orders o
                    JOIN users u ON o.user_id = u.id
                    LEFT JOIN order_items oi ON o.id = oi.order_id
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE o.status != 'cancelled'
                    GROUP BY o.id
                    ORDER BY o.id DESC";
            
            $res = mysqli_query($conn, $sql);
            
            while($row = mysqli_fetch_assoc($res)):
            ?>
            <tr>
                <td style="text-align:center;"><?= $row['id'] ?></td>
                <td style="text-align:center;"><?= date('d/m/Y H:i', strtotime($row['order_date'])) ?></td>
                <td><?= $row['fullname'] ?></td>
                <td><?= $row['items'] ?></td>
                <td style="text-align:right;"><?= number_format($row['total_price'], 2) ?></td>
                <td style="text-align:right;"><?= number_format($row['discount_amount'], 2) ?></td>
                <td style="text-align:right; background-color:#e6ffe6;"><b><?= number_format($row['final_price'], 2) ?></b></td>
                <td style="text-align:center;"><?= $row['payment_method'] ?></td>
                <td style="text-align:center;"><?= ucfirst($row['status']) ?></td>
                <td><?= $row['address'] ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>


