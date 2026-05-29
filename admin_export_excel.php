<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');

// ตรวจสอบความถูกต้องว่าเป็น Admin หรือไม่
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

// ประกอบเงื่อนไขการกรองข้อมูลแบบไดนามิก
$where_clauses = [];

// กรองด้วยวันที่เริ่มต้น
if (!empty($_GET['start_date'])) {
    $start_date = mysqli_real_escape_string($conn, $_GET['start_date']);
    $where_clauses[] = "o.order_date >= '$start_date 00:00:00'";
}
// กรองด้วยวันที่สิ้นสุด
if (!empty($_GET['end_date'])) {
    $end_date = mysqli_real_escape_string($conn, $_GET['end_date']);
    $where_clauses[] = "o.order_date <= '$end_date 23:59:59'";
}

// กรองด้วยสถานะออเดอร์
if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    $where_clauses[] = "o.status = '$status'";
} else {
    // ค่าเริ่มต้นหากไม่ได้เจาะจงสถานะ ให้ยกเว้นออเดอร์ที่ถูกยกเลิก (ยกเว้นแอดมินสั่งกรองดูออเดอร์ยกเลิกโดยเฉพาะ)
    if (empty($_GET['status']) || $_GET['status'] === 'all') {
        $where_clauses[] = "o.status != 'cancelled'";
    }
}

// กรองด้วยสินค้าเฉพาะตัว
if (!empty($_GET['product_id']) && $_GET['product_id'] !== 'all') {
    $product_id = intval($_GET['product_id']);
    $where_clauses[] = "o.id IN (SELECT DISTINCT order_id FROM order_items WHERE product_id = '$product_id')";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// ดึงข้อมูลรายการสั่งซื้อพร้อมสรุปราคาทุนตามระบบ FIFO
$sql = "SELECT o.*, u.fullname, 
        GROUP_CONCAT(CONCAT(p.name, ' (x', oi.quantity, ')') SEPARATOR ', ') as items,
        (SELECT SUM(quantity * import_cost) FROM order_items WHERE order_id = o.id) as total_import_cost
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        $where_sql
        GROUP BY o.id
        ORDER BY o.id DESC";

$res = mysqli_query($conn, $sql);

// ตั้งชื่อไฟล์รายงาน
$filename = "Por_Mae_Bet_Taled_Sales_Report_" . date('Ymd_His') . ".xls";

// ส่ง Header สั่งดาวน์โหลดไฟล์ Excel
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
    th { background-color: #7FB5FF; color: white; font-weight: bold; border: 1px solid #cccccc; padding: 8px; text-align: center; }
    td { border: 1px solid #cccccc; padding: 6px; }
    .summary-title { background-color: #eaeaea; font-weight: bold; text-align: right; }
</style>
</head>
<body>
    <h2 style="text-align:center; font-family:'Kanit';">รายงานยอดขายและวิเคราะห์กำไรสุทธิ - พ่อแม่ เบ็ดเตล็ด (Por Mae Bet Taled)</h2>
    
    <p style="font-family:'Kanit';">
        <b>เงื่อนไขรายงาน:</b><br>
        - ช่วงเวลาสั่งซื้อ: <?= !empty($_GET['start_date']) ? date('d/m/Y', strtotime($_GET['start_date'])) : 'เริ่มต้นทั้งหมด' ?> ถึง <?= !empty($_GET['end_date']) ? date('d/m/Y', strtotime($_GET['end_date'])) : 'ล่าสุดทั้งหมด' ?><br>
        - สินค้าเจาะจง: <?php 
            if (!empty($_GET['product_id']) && $_GET['product_id'] !== 'all') {
                $p_name_q = mysqli_query($conn, "SELECT name FROM products WHERE id = '".intval($_GET['product_id'])."'");
                $p_name_r = mysqli_fetch_assoc($p_name_q);
                echo htmlspecialchars($p_name_r['name'] ?? 'ไม่ระบุ');
            } else {
                echo 'ทั้งหมด';
            }
        ?><br>
        - สถานะออเดอร์: <?= !empty($_GET['status']) && $_GET['status'] !== 'all' ? match($_GET['status']){'pending'=>'รอตรวจสอบ','shipping'=>'กำลังจัดส่ง','completed'=>'สำเร็จ','cancelled'=>'ยกเลิก'} : 'ทั้งหมด (ไม่รวมรายการยกเลิก)' ?>
    </p>
    <p style="font-family:'Kanit'; font-size: 0.85rem; color: #555;">วันที่และเวลาออกเอกสาร: <?= date('d/m/Y H:i') ?></p>
    
    <table style="font-family:'Kanit';">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>วันที่สั่งซื้อ</th>
                <th>ชื่อลูกค้า</th>
                <th>สินค้าที่สั่ง</th>
                <th>ยอดรวมสินค้า (บาท)</th>
                <th>ส่วนลดคูปอง (บาท)</th>
                <th>ยอดรับชำระสุทธิ (บาท)</th>
                <th>ต้นทุนนำเข้า (FIFO) (บาท)</th>
                <th>กำไรสุทธิ (บาท)</th>
                <th>ช่องทางชำระเงิน</th>
                <th>สถานะคำสั่งซื้อ</th>
                <th>ที่อยู่จัดส่ง</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $grand_total_price = 0;
            $grand_discount = 0;
            $grand_final_price = 0;
            $grand_cost = 0;
            $grand_profit = 0;
            
            if (mysqli_num_rows($res) > 0):
                while($row = mysqli_fetch_assoc($res)):
                    $cost = floatval($row['total_import_cost']);
                    $profit = floatval($row['final_price']) - $cost;
                    
                    $grand_total_price += floatval($row['total_price']);
                    $grand_discount += floatval($row['discount_amount']);
                    $grand_final_price += floatval($row['final_price']);
                    $grand_cost += $cost;
                    $grand_profit += $profit;
                    
                    $status_thai = match($row['status']) { 'pending'=>'รอตรวจสอบ', 'approved'=>'ยืนยันแล้ว', 'shipping'=>'กำลังจัดส่ง', 'completed'=>'สำเร็จ', 'cancelled'=>'ยกเลิก', default=>$row['status'] };
            ?>
            <tr>
                <td style="text-align:center;"><?= $row['id'] ?></td>
                <td style="text-align:center;"><?= date('d/m/Y H:i', strtotime($row['order_date'])) ?></td>
                <td><?= htmlspecialchars($row['fullname']) ?></td>
                <td><?= htmlspecialchars($row['items']) ?></td>
                <td style="text-align:right;"><?= number_format($row['total_price'], 2) ?></td>
                <td style="text-align:right; color:red;"><?= number_format($row['discount_amount'], 2) ?></td>
                <td style="text-align:right; background-color:#e6f3ff; font-weight:bold;"><?= number_format($row['final_price'], 2) ?></td>
                <td style="text-align:right; color:#666666;"><?= number_format($cost, 2) ?></td>
                <td style="text-align:right; background-color:#e6ffe6; font-weight:bold; color:green;"><?= number_format($profit, 2) ?></td>
                <td style="text-align:center;"><?= htmlspecialchars($row['payment_method']) ?></td>
                <td style="text-align:center;"><?= $status_thai ?></td>
                <td><?= htmlspecialchars($row['address']) ?></td>
            </tr>
            <?php endwhile; ?>
            
            <!-- แถวสรุปรวมทั้งหมด (Grand Summary Row) -->
            <tr style="font-weight:bold; background-color:#f2f2f2;">
                <td colspan="4" class="summary-title">รวมยอดรวมทั้งสิ้น (Grand Total):</td>
                <td style="text-align:right; background-color:#eaeaea;"><?= number_format($grand_total_price, 2) ?></td>
                <td style="text-align:right; color:red; background-color:#eaeaea;"><?= number_format($grand_discount, 2) ?></td>
                <td style="text-align:right; background-color:#cce5ff;"><?= number_format($grand_final_price, 2) ?></td>
                <td style="text-align:right; color:#555; background-color:#eaeaea;"><?= number_format($grand_cost, 2) ?></td>
                <td style="text-align:right; background-color:#d4edda; color:green;"><?= number_format($grand_profit, 2) ?></td>
                <td colspan="3" style="background-color:#eaeaea;"></td>
            </tr>
            <?php else: ?>
            <tr>
                <td colspan="12" style="text-align:center; color:#888; py-4;">-- ไม่พบข้อมูลตามเงื่อนไขที่กำหนด --</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
