<?php
session_start();
include 'db.php';
include 'header.php';

$ids = isset($_SESSION['compare']) ? $_SESSION['compare'] : [];
$products = [];
if (!empty($ids)) {
    $ids_str = implode(',', $ids);
    $q = mysqli_query($conn, "SELECT * FROM products WHERE id IN ($ids_str)");
    while ($row = mysqli_fetch_assoc($q)) $products[] = $row;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เปรียบเทียบ | Por Mae Bet Taled</title>
    <style>
        body { background-color: #f8f9fa; font-family: 'Kanit', sans-serif; }
        .compact-container { background: white; border-radius: 15px; box-shadow: 0 2px 15px rgba(0,0,0,0.02); overflow: hidden; }
        .table-responsive { overflow-x: auto; }
        .compare-table-mini { width: 100%; border-collapse: collapse; table-layout: fixed; min-width: 600px; } /* Min-width ทำให้เลื่อนได้ */
        .compare-table-mini th, .compare-table-mini td { padding: 15px 10px; border-bottom: 1px solid #f0f0f0; vertical-align: top; text-align: center; font-size: 0.9rem; }
        .label-row { background-color: #fcfcfc; color: #999; font-weight: 500; font-size: 0.75rem; text-transform: uppercase; padding: 8px !important; }
        .img-mini { width: 80px; height: 80px; object-fit: cover; border-radius: 10px; margin-bottom: 8px; border: 1px solid #eee; }
        .btn-remove-mini { position: absolute; top: 0; right: 5px; color: #ccc; border: none; background: transparent; font-size: 1.2rem; cursor: pointer; }
        .price-mini { color: #AEE2FF; font-weight: 700; font-size: 1rem; }
        .btn-view-mini { padding: 4px 12px; font-size: 0.75rem; border-radius: 50px; background: #333; color: white; text-decoration: none; display: inline-block; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="row">
        <div class="col-lg-3">
            <a class="btn btn-light w-100 d-lg-none mb-3 border shadow-sm fw-bold text-start" 
               data-bs-toggle="collapse" 
               href="#userSidebar" 
               role="button" 
               aria-expanded="false" 
               aria-controls="userSidebar">
                <i class="bi bi-list me-2"></i> เมนูสมาชิก (คลิกเพื่อเปิด)
            </a>
            
            <div class="collapse d-lg-block" id="userSidebar">
                <?php include 'user_sidebar.php'; ?>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-end mb-3">
                <h5 class="fw-bold mb-0 text-dark">⚖️ เปรียบเทียบสินค้า</h5>
                <?php if (!empty($products)): ?><small class="text-muted"><?= count($products) ?> รายการ</small><?php endif; ?>
            </div>

            <?php if (empty($products)): ?>
                <div class="compact-container text-center py-5">
                    <p class="text-secondary small mt-2">ยังไม่มีสินค้าให้เปรียบเทียบ</p>
                    <a href="index.php" class="btn btn-sm btn-dark rounded-pill px-3">ไปเลือกสินค้า</a>
                </div>
            <?php else: ?>
                <div class="compact-container">
                    <div class="table-responsive">
                        <table class="compare-table-mini">
                            <thead>
                                <tr>
                                    <?php foreach ($products as $p): ?>
                                        <th class="position-relative border-0 pt-4">
                                            <button onclick="removeCompare(<?= $p['id'] ?>)" class="btn-remove-mini">×</button>
                                            <img src="<?= $p['image'] ?>" class="img-mini">
                                            <div class="text-truncate px-2 mb-1"><?= $p['name'] ?></div>
                                            <div class="price-mini">฿<?= number_format($p['price']) ?></div>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><?php foreach ($products as $p): ?><td class="label-row">สถานะ</td><?php endforeach; ?></tr>
                                <tr><?php foreach ($products as $p): ?><td><?= $p['stock'] > 0 ? '<span class="text-success">● มีสินค้า</span>' : '<span class="text-danger">● หมด</span>' ?></td><?php endforeach; ?></tr>
                                <tr><?php foreach ($products as $p): ?><td class="label-row">รายละเอียด</td><?php endforeach; ?></tr>
                                <tr><?php foreach ($products as $p): ?><td><div class="text-start small text-muted" style="height:60px; overflow-y:auto;"><?= $p['description'] ?></div></td><?php endforeach; ?></tr>
                                <tr><?php foreach ($products as $p): ?><td class="border-0 pb-4"><a href="product_detail.php?id=<?= $p['id'] ?>" class="btn-view-mini">ดูสินค้า</a></td><?php endforeach; ?></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="text-center mt-3"><a href="index.php" class="text-decoration-none small text-blue">+ เพิ่มสินค้าอื่น</a></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function removeCompare(id) {
    let fd = new FormData(); fd.append('action', 'toggle_compare'); fd.append('product_id', id);
    fetch('ajax_features.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => { location.reload(); });
}
</script>

</body>
</html>
