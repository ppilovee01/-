<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

// 1. ยอดขายรวม
$q1 = mysqli_query($conn, "SELECT SUM(final_price) as total FROM orders WHERE status != 'cancelled'");
$r1 = mysqli_fetch_assoc($q1); 
$total_sales = $r1['total'] ?? 0;

// 1.1 กำไรสุทธิ (ยอดขายรวม - ต้นทุนรวม)
$q_cost = mysqli_query($conn, "SELECT SUM(oi.quantity * oi.import_cost) as total_cost 
                               FROM order_items oi 
                               JOIN orders o ON oi.order_id = o.id 
                               WHERE o.status != 'cancelled'");
$r_cost = mysqli_fetch_assoc($q_cost);
$total_cost = $r_cost['total_cost'] ?? 0;
$net_profit = $total_sales - $total_cost;

// 2. ออเดอร์รอตรวจสอบ
$q2 = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
$r2 = mysqli_fetch_assoc($q2); 
$pending_orders = $r2['count'];

// 3. ลูกค้าทั้งหมด
$q3 = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'user'");
$r3 = mysqli_fetch_assoc($q3); 
$total_users = $r3['count'];

// 4. สินค้าใกล้หมด
$q4 = mysqli_query($conn, "SELECT COUNT(*) as count FROM products WHERE stock < 5");
$r4 = mysqli_fetch_assoc($q4); 
$low_stock = $r4['count'];

// 5. กราฟยอดขาย 7 วันล่าสุด
$dates = []; $sales = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $q = mysqli_query($conn, "SELECT SUM(final_price) as total FROM orders WHERE DATE(order_date) = '$d' AND status != 'cancelled'");
    $r = mysqli_fetch_assoc($q);
    $dates[] = date('d/m', strtotime($d));
    $sales[] = $r['total'] ?? 0;
}

// 6. สินค้าขายดี 5 อันดับแรก
$top5_sql = "SELECT p.name, p.image, p.stock, SUM(oi.quantity) as total_qty, SUM(oi.quantity * oi.price) as total_income
             FROM order_items oi
             JOIN products p ON oi.product_id = p.id
             JOIN orders o ON oi.order_id = o.id
             WHERE o.status != 'cancelled'
             GROUP BY oi.product_id
             ORDER BY total_qty DESC LIMIT 5";
$top5_res = mysqli_query($conn, $top5_sql);

// 7. ยอดขายแยกตามหมวดหมู่
$cat_names = [];
$cat_revenues = [];
$cat_sql = "SELECT c.name as cat_name, SUM(oi.quantity * oi.price) as revenue 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            JOIN categories c ON p.category_id = c.id 
            JOIN orders o ON oi.order_id = o.id 
            WHERE o.status != 'cancelled' 
            GROUP BY p.category_id 
            ORDER BY revenue DESC";
$cat_res = mysqli_query($conn, $cat_sql);
while ($cat_row = mysqli_fetch_assoc($cat_res)) {
    $cat_names[] = $cat_row['cat_name'];
    $cat_revenues[] = floatval($cat_row['revenue']);
}

// 8. ดึงรายชื่อสินค้าทั้งหมดสำหรับกรองรายงาน Excel
$all_prods = [];
$ap_res = mysqli_query($conn, "SELECT id, name FROM products ORDER BY name ASC");
while ($ap = mysqli_fetch_assoc($ap_res)) {
    $all_prods[] = $ap;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ภาพรวมร้านค้า | Por Mae Bet Taled Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Kanit'; background: #f8f9fa; }
        .stat-card { border: none; border-radius: 20px; padding: 20px; color: #444; transition: 0.3s; box-shadow: 0 5px 15px rgba(0,0,0,0.05); height: 100%; background: white; border-bottom: 4px solid transparent; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-blue { border-color: #AEE2FF; }
        .stat-orange { border-color: #FFB347; }
        .stat-green { border-color: #77DD77; }
        .stat-red { border-color: #FF6B6B; }
        
        .rank-badge { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; background: #eee; color: #555; font-size: 0.8rem; }
        .rank-1 { background: #FFD700; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.2); }
        .rank-2 { background: #C0C0C0; color: #fff; }
        .rank-3 { background: #CD7F32; color: #fff; }
        
        @media (max-width: 767px) {
            .p-5 { padding: 1.5rem !important; }
            h2.fw-bold { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 p-0 border-end bg-white">
            <button class="btn btn-light w-100 d-md-none border-bottom p-3 fw-bold text-primary text-start" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
                <i class="bi bi-list me-2"></i> เมนูจัดการ
            </button>
            <div class="collapse d-md-block" id="sidebarMenu">
                <div style="min-height: 100vh;">
                    <?php include 'admin_sidebar.php'; ?>
                </div>
            </div>
        </div>

        <div class="col-md-10 p-4 p-md-5">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-2">
                <h2 class="fw-bold m-0">ภาพรวมร้านค้า (Dashboard)</h2>
                <button type="button" class="btn btn-success rounded-pill px-4 shadow-sm border-0 w-100 w-md-auto" data-bs-toggle="modal" data-bs-target="#exportFilterModal" style="background: linear-gradient(45deg, #28a745, #20c997);">
                    <i class="bi bi-file-earmark-spreadsheet-fill me-2"></i> ส่งออกไฟล์ Excel
                </button>
            </div>

            <div class="row g-3 g-md-4 mb-5">
                <div class="col-6 col-md-4 col-xl">
                    <div class="stat-card stat-blue">
                        <div><h6 class="text-muted small mb-1">ยอดขายรวมทั้งหมด</h6><h4 class="fw-bold mb-0 text-primary">฿<?= number_format($total_sales) ?></h4></div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl">
                    <div class="stat-card stat-green">
                        <div><h6 class="text-muted small mb-1">กำไรสุทธิ (FIFO)</h6><h4 class="fw-bold mb-0 text-success">฿<?= number_format($net_profit) ?></h4></div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl">
                    <div class="stat-card stat-orange">
                        <div><h6 class="text-muted small mb-1">รอตรวจสอบยอด</h6><h4 class="fw-bold mb-0 text-warning"><?= $pending_orders ?> รายการ</h4></div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl">
                    <div class="stat-card stat-blue">
                        <div><h6 class="text-muted small mb-1">จำนวนลูกค้า</h6><h4 class="fw-bold mb-0 text-info"><?= $total_users ?> ท่าน</h4></div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl">
                    <div class="stat-card stat-red">
                        <div><h6 class="text-muted small mb-1">สินค้าใกล้หมด</h6><h4 class="fw-bold mb-0 text-danger"><?= $low_stock ?> รายการ</h4></div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- กราฟเส้นแนวโน้มยอดขาย -->
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                        <h5 class="fw-bold mb-4">📈 สถิติยอดขาย 7 วันล่าสุด</h5>
                        <div style="position: relative; height: 300px; width: 100%;">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- กราฟวงกลมยอดขายตามหมวดหมู่ -->
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                        <h5 class="fw-bold mb-4">🍕 สัดส่วนยอดขายตามหมวดหมู่</h5>
                        <div style="position: relative; height: 300px; width: 100%; display: flex; align-items: center; justify-content: center;">
                            <?php if (empty($cat_names)): ?>
                                <span class="text-muted small">ยังไม่มีข้อมูลการขายสินค้าตามหมวดหมู่</span>
                            <?php else: ?>
                                <canvas id="categoryChart"></canvas>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-2">
                <!-- ตารางสินค้าขายดี -->
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                        <h5 class="fw-bold mb-4 text-warning"><i class="bi bi-trophy-fill me-2"></i>สินค้าขายดี Top 5</h5>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <tbody>
                                    <?php 
                                    if(mysqli_num_rows($top5_res) > 0):
                                        $rank = 1; while($item = mysqli_fetch_assoc($top5_res)): 
                                        $rank_class = "rank-".$rank;
                                        $is_out = ($item['stock'] == 0);
                                    ?>
                                    <tr>
                                        <td style="width: 40px;"><div class="rank-badge <?= $rank <= 3 ? $rank_class : '' ?>"><?= $rank ?></div></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?= $item['image'] ?>" style="width: 40px; height: 40px; border-radius: 10px; object-fit: cover; margin-right: 12px;">
                                                <div>
                                                    <div class="fw-bold text-truncate" style="max-width: 150px;"><?= $item['name'] ?></div>
                                                    <div class="small text-muted" style="font-size: 0.75rem;">
                                                        จำหน่ายแล้ว <?= $item['total_qty'] ?> ชิ้น
                                                        <?php if($is_out): ?> <span class="badge bg-danger ms-1" style="font-size:0.6rem;">สินค้าหมด</span> <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end fw-bold text-success small">+฿<?= number_format($item['total_income']) ?></td>
                                    </tr>
                                    <?php $rank++; endwhile; else: ?>
                                    <tr><td colspan="3" class="text-center text-muted py-5">ยังไม่มีข้อมูลการขายในขณะนี้</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ตารางคำสั่งซื้อล่าสุด -->
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                        <h5 class="fw-bold mb-4 text-primary"><i class="bi bi-cart-fill me-2"></i>รายการสั่งซื้อล่าสุด</h5>
                        <div class="table-responsive">
                            <table class="table align-middle table-hover">
                                <thead class="text-muted small">
                                    <tr>
                                        <th>สถานะ</th>
                                        <th>เลขที่</th>
                                        <th>ลูกค้า</th>
                                        <th class="text-end">ยอดชำระ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $last_q = mysqli_query($conn, "SELECT o.*, u.fullname FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.id DESC LIMIT 5");
                                    while($ord = mysqli_fetch_assoc($last_q)):
                                        $st_text = match($ord['status']) { 'pending'=>'รอตรวจสอบ', 'approved'=>'ยืนยันแล้ว', 'shipping'=>'กำลังจัดส่ง', 'completed'=>'สำเร็จ', 'cancelled'=>'ยกเลิก', default=>$ord['status'] };
                                        $st_color = match($ord['status']) { 'pending'=>'bg-warning', 'approved'=>'bg-success', 'shipping'=>'bg-info', 'completed'=>'bg-primary', 'cancelled'=>'bg-danger', default=>'bg-secondary' };
                                    ?>
                                    <tr>
                                        <td><span class="badge rounded-pill <?= $st_color ?> fw-normal"><?= $st_text ?></span></td>
                                        <td class="fw-bold">#<?= $ord['id'] ?></td>
                                        <td><div class="text-truncate" style="max-width: 100px;"><?= $ord['fullname'] ?></div></td>
                                        <td class="text-end fw-bold" style="color: var(--blue-dark);">฿<?= number_format($ord['final_price']) ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    // กราฟยอดขาย 7 วันล่าสุด
    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
        type: 'line', 
        data: {
            labels: <?= json_encode($dates) ?>,
            datasets: [{
                label: 'ยอดขาย (บาท)',
                data: <?= json_encode($sales) ?>,
                borderColor: '#7FB5FF',
                backgroundColor: 'rgba(127, 181, 255, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#7FB5FF',
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [5, 5] }, ticks: { font: { size: 10 } } },
                x: { grid: { display: false }, ticks: { font: { size: 10 } } }
            }
        }
    });

    // กราฟวงกลมสัดส่วนยอดขายตามหมวดหมู่
    <?php if (!empty($cat_names)): ?>
    const catCtx = document.getElementById('categoryChart').getContext('2d');
    new Chart(catCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($cat_names) ?>,
            datasets: [{
                data: <?= json_encode($cat_revenues) ?>,
                backgroundColor: [
                    '#7FB5FF', 
                    '#AEE2FF', 
                    '#FFB347', 
                    '#77DD77', 
                    '#FF9F9F', 
                    '#C5A3FF'
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 15,
                        font: {
                            family: 'Kanit',
                            size: 11
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });
    <?php endif; ?>
</script>
<!-- Modal สำหรับดาวน์โหลดรายงาน Excel แบบกรองข้อมูล -->
<div class="modal fade" id="exportFilterModal" tabindex="-1" aria-labelledby="exportFilterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="exportFilterModalLabel"><i class="bi bi-file-earmark-spreadsheet-fill text-success me-2"></i>ส่งออกรายงานยอดขาย Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="admin_export_excel.php" method="GET" target="_blank">
                <div class="modal-body py-4">
                    <p class="text-muted small mb-4">กำหนดเงื่อนไขในการกรองข้อมูลเพื่อส่งออกรายงาน Excel โดยวิเคราะห์ข้อมูลและราคาทุนจริงตามระบบสต็อก FIFO</p>
                    
                    <div class="row g-3">
                        <!-- วันที่เริ่มต้น -->
                        <div class="col-6">
                            <label class="form-label small text-muted fw-bold">วันที่เริ่มต้น</label>
                            <input type="date" name="start_date" class="form-control rounded-3">
                        </div>
                        <!-- วันที่สิ้นสุด -->
                        <div class="col-6">
                            <label class="form-label small text-muted fw-bold">วันที่สิ้นสุด</label>
                            <input type="date" name="end_date" class="form-control rounded-3">
                        </div>
                        
                        <!-- เลือกสินค้า -->
                        <div class="col-12">
                            <label class="form-label small text-muted fw-bold">เลือกสินค้าเฉพาะเจาะจง</label>
                            <select name="product_id" class="form-select rounded-3">
                                <option value="all" selected>-- สินค้าทั้งหมด --</option>
                                <?php foreach ($all_prods as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- สถานะออเดอร์ -->
                        <div class="col-12">
                            <label class="form-label small text-muted fw-bold">สถานะคำสั่งซื้อ</label>
                            <select name="status" class="form-select rounded-3">
                                <option value="all" selected>-- ทั้งหมด (ยกเว้น ยกเลิก) --</option>
                                <option value="pending">รอตรวจสอบ (Pending)</option>
                                <option value="shipping">กำลังจัดส่ง (Shipping)</option>
                                <option value="completed">สำเร็จ (Completed)</option>
                                <option value="cancelled">ยกเลิก (Cancelled)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 border-0" style="background: linear-gradient(45deg, #28a745, #20c997);" onclick="bootstrap.Modal.getInstance(document.getElementById('exportFilterModal')).hide();">
                        <i class="bi bi-file-earmark-spreadsheet-fill me-1"></i> ดาวน์โหลด Excel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
