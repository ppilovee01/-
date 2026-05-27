<?php
session_start();
include 'db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

// --- ฟังก์ชันซิงค์สต๊อกและราคาจากล็อต (คำนวณ FIFO อัตโนมัติ) ---
function sync_product_stock($conn, $product_id) {
    $q_stock = mysqli_query($conn, "SELECT SUM(stock) as total_stock FROM product_lots WHERE product_id='$product_id' AND stock > 0");
    $tot = mysqli_fetch_assoc($q_stock)['total_stock'];
    if($tot == null) $tot = 0;
    
    $q_price = mysqli_query($conn, "SELECT price FROM product_lots WHERE product_id='$product_id' AND stock > 0 ORDER BY imported_at ASC LIMIT 1");
    $r_price = mysqli_fetch_assoc($q_price);
    
    if ($tot > 0 && $r_price) {
        $price = $r_price['price'];
        mysqli_query($conn, "UPDATE products SET stock='$tot', price='$price' WHERE id='$product_id'");
    } else {
        mysqli_query($conn, "UPDATE products SET stock=0 WHERE id='$product_id'");
    }
}

// --- Logic: เพิ่มล็อตสินค้าใหม่ ---
if (isset($_POST['add_lot'])) {
    $pid = mysqli_real_escape_string($conn, $_POST['lot_product_id']);
    $lot_price = $_POST['lot_price'];
    $lot_stock = $_POST['lot_stock'];
    
    mysqli_query($conn, "INSERT INTO product_lots (product_id, price, stock, imported_at) VALUES ('$pid', '$lot_price', '$lot_stock', NOW())");
    sync_product_stock($conn, $pid); 
    
    $_SESSION['swal'] = ['title' => 'สำเร็จ', 'text' => 'นำเข้าล็อตสินค้าเรียบร้อย', 'icon' => 'success'];
    header("Location: admin.php"); exit();
}

// --- Logic: Save Product ---
if (isset($_POST['save_product'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $cat_id = $_POST['category_id'];
    $cat_val = empty($cat_id) ? "NULL" : "'$cat_id'";
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $options = mysqli_real_escape_string($conn, $_POST['options']); 
    $image_path = $_POST['old_image']; 
    
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
        $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
        $new_name = "prod_" . uniqid() . "." . $ext;
        if (!is_dir("uploads")) mkdir("uploads");
        move_uploaded_file($_FILES['image_file']['tmp_name'], "uploads/" . $new_name);
        $image_path = "uploads/" . $new_name;
    }
    
    if (!empty($_POST['id'])) {
        $id = $_POST['id'];
        $sql = "UPDATE products SET name='$name', category_id=$cat_val, description='$desc', image='$image_path', options='$options' WHERE id='$id'";
        mysqli_query($conn, $sql);
        $action_text = "อัปเดตข้อมูล";
    } else {
        $price = $_POST['price'];
        $stock = $_POST['stock'];
        $sql = "INSERT INTO products (name, price, stock, category_id, image, description, options) VALUES ('$name', '$price', '$stock', $cat_val, '$image_path', '$desc', '$options')";
        mysqli_query($conn, $sql);
        $new_id = mysqli_insert_id($conn);
        
        mysqli_query($conn, "INSERT INTO product_lots (product_id, price, stock, imported_at) VALUES ('$new_id', '$price', '$stock', NOW())");
        $action_text = "เพิ่มสินค้า";
    }
    
    $_SESSION['swal'] = ['title' => 'สำเร็จ', 'text' => "$action_text เรียบร้อย", 'icon' => 'success'];
    header("Location: admin.php"); exit();
}

// --- Logic: ลบสินค้า ---
if (isset($_GET['delete'])) { 
    $del_id = $_GET['delete'];
    mysqli_query($conn, "DELETE FROM product_lots WHERE product_id = '$del_id'");
    mysqli_query($conn, "DELETE FROM products WHERE id = '$del_id'"); 
    header("Location: admin.php"); exit(); 
}

// --- Logic: ลบล็อตย่อย ---
if (isset($_GET['delete_lot']) && isset($_GET['pid'])) {
    $del_lot = $_GET['delete_lot'];
    $pid = $_GET['pid'];
    mysqli_query($conn, "DELETE FROM product_lots WHERE id = '$del_lot'");
    sync_product_stock($conn, $pid);
    $_SESSION['swal'] = ['title' => 'สำเร็จ', 'text' => "ลบล็อตสินค้าย่อยเรียบร้อย", 'icon' => 'success'];
    header("Location: admin.php"); exit();
}

$edit_data = null;
if (isset($_GET['edit'])) { 
    $edit_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM products WHERE id='" . mysqli_real_escape_string($conn, $_GET['edit']) . "'")); 
}

// ดึงข้อมูลสินค้าทั้งหมดเก็บใส่ Array เตรียมไว้
$products_list = [];
$sql = "SELECT p.*, c.name as cat_name, (SELECT COUNT(*) FROM product_lots WHERE product_id = p.id AND stock > 0) as active_lots FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC"; 
$res = mysqli_query($conn, $sql); 
if($res) {
    while($row = mysqli_fetch_assoc($res)) {
        $products_list[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสินค้า | Por Mae Bet Taled Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style> 
        body { font-family: 'Kanit'; background: #f8f9fa; } 
        .btn-main { background-color: #85D1FF; color: white; border: none; }
        .btn-main:hover { background-color: #6BBEFF; color: white; }
        .option-row { background: #f9f9f9; padding: 10px; border-radius: 8px; border: 1px solid #eee; margin-bottom: 10px; }
        
        /* ======== Mobile Responsive CSS ======== */
        @media (max-width: 991px) {
            .table-responsive { border: none !important; }
            .table-responsive table { width: 100%; display: block; }
            .table-responsive thead { display: none; /* ซ่อนหัวตาราง */ }
            .table-responsive tbody { display: block; width: 100%; }
            .table-responsive tr { 
                display: block; 
                background: #fff; 
                border: 1px solid #eaeaea; 
                border-radius: 16px; 
                margin-bottom: 15px; 
                padding: 15px; 
                box-shadow: 0 4px 10px rgba(0,0,0,0.03); 
            }
            .table-responsive td { 
                display: block; 
                width: 100%; 
                border: none !important; 
                padding: 5px 0 !important; 
                text-align: left !important; 
            }
            .td-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px dashed #eee;
            }
            .td-actions > button, .td-actions > a {
                flex: 1;
                justify-content: center;
                margin: 0 !important;
            }
            .btn-circle-mobile {
                flex-grow: 0 !important;
                width: 40px; height: 40px;
                display: flex; align-items: center; justify-content: center;
            }
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

        <div class="col-md-10 p-3 p-md-5">
            <h3 class="fw-bold mb-4 d-none d-md-block">📦 จัดการสินค้าและสต๊อก (FIFO)</h3>
            
            <div class="row">
                <div class="col-lg-4 mb-4 order-first order-lg-last">
                    
                    <button class="btn btn-main w-100 d-lg-none mb-3 shadow-sm rounded-pill fw-bold py-2" type="button" data-bs-toggle="collapse" data-bs-target="#formCollapse" aria-expanded="<?= $edit_data ? 'true' : 'false' ?>">
                        <i class="bi <?= $edit_data ? 'bi-pencil-square' : 'bi-plus-circle-fill' ?> me-1"></i> 
                        <?= $edit_data ? 'กำลังแก้ไขสินค้า (แตะเพื่อย่อ/ขยาย)' : 'เพิ่มสินค้าใหม่' ?>
                    </button>

                    <div class="collapse d-lg-block <?= $edit_data ? 'show' : '' ?>" id="formCollapse">
                        <div class="card border-0 shadow-sm rounded-4 p-4 sticky-top" style="top: 20px; z-index: 10;">
                            <h5 class="fw-bold mb-3 text-dark d-none d-lg-block">
                                <?php if($edit_data): ?><i class="bi bi-pencil-square text-warning"></i> แก้ไขข้อมูลสินค้า
                                <?php else: ?><i class="bi bi-plus-circle-fill text-success"></i> เพิ่มสินค้าใหม่<?php endif; ?>
                            </h5>
                            <form method="POST" enctype="multipart/form-data" onsubmit="prepareOptionsBeforeSubmit()">
                                <input type="hidden" name="id" value="<?= $edit_data['id'] ?? '' ?>">
                                <input type="hidden" name="old_image" value="<?= $edit_data['image'] ?? '' ?>">
                                <div class="mb-3"><label class="fw-bold small text-muted">ชื่อสินค้า</label><input type="text" name="name" class="form-control" value="<?= $edit_data['name'] ?? '' ?>" required></div>
                                <div class="mb-3"><label class="fw-bold small text-muted">หมวดหมู่</label><select name="category_id" class="form-select"><option value="">-- เลือก --</option><?php $cat_q = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC"); while($c = mysqli_fetch_assoc($cat_q)): $sel = ($edit_data && $edit_data['category_id'] == $c['id']) ? 'selected' : ''; echo "<option value='{$c['id']}' $sel>{$c['name']}</option>"; endwhile; ?></select></div>
                                
                                <?php if(!$edit_data): ?>
                                <div class="row g-2 mb-3">
                                    <div class="col-6"><label class="fw-bold small text-muted">ราคาขาย (ล็อตแรก)</label><input type="number" name="price" class="form-control" required></div>
                                    <div class="col-6"><label class="fw-bold small text-muted">จำนวน (ล็อตแรก)</label><input type="number" name="stock" class="form-control" required></div>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info py-2 small mb-3 border-0 rounded-3" style="background-color: #F0F8FF; color: #444;">
                                    <i class="bi bi-info-circle-fill text-primary"></i> การอัปเดตราคาและสต๊อก ให้กดที่ปุ่ม <b>"ประวัติรับเข้า"</b> หรือ <b>"นำเข้าล็อต"</b>
                                </div>
                                <?php endif; ?>

                                <div class="mb-3 p-3 border rounded-3 bg-light">
                                    <label class="fw-bold small text-primary mb-2">ตัวเลือกสินค้า</label>
                                    <div id="option-container"></div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-2 bg-white" onclick="addOptionRow()"><i class="bi bi-plus-lg"></i> เพิ่มตัวเลือก</button>
                                    <input type="hidden" name="options" id="real_options_input" value="<?= $edit_data['options'] ?? '' ?>">
                                </div>
                                <div class="mb-3"><label class="fw-bold small text-muted">รูปภาพ</label><input type="file" name="image_file" class="form-control" accept="image/*"></div>
                                <div class="mb-4"><label class="fw-bold small text-muted">รายละเอียด</label><textarea name="description" class="form-control" rows="3"><?= $edit_data['description'] ?? '' ?></textarea></div>
                                <button type="submit" name="save_product" class="btn btn-main w-100 rounded-pill fw-bold py-2">บันทึกข้อมูล</button>
                                <?php if($edit_data): ?><a href="admin.php" class="btn btn-light w-100 rounded-pill mt-2 text-muted py-2">ยกเลิก</a><?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 order-last order-lg-first">
                    <div class="card border-0 shadow-sm rounded-4 p-lg-4 bg-transparent bg-lg-white">
                        <div class="table-responsive">
                            <table class="table align-middle table-hover m-0">
                                <thead class="bg-light text-secondary small">
                                    <tr><th class="ps-3">สินค้า</th><th>ราคาและสต๊อก</th><th class="text-end pe-3">จัดการ</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach($products_list as $row): 
                                        $hl = ($edit_data && $edit_data['id'] == $row['id']) ? 'table-warning' : ''; 
                                    ?>
                                    <tr class="<?= $hl ?>">
                                        <td class="ps-lg-3">
                                            <div class="d-flex align-items-center">
                                                <img src="<?= $row['image'] ?>" class="rounded-3 me-3 shadow-sm" style="width:60px; height:60px; object-fit:cover;">
                                                <div style="min-width: 120px; flex-grow: 1;">
                                                    <div class="fw-bold text-dark text-truncate" style="max-width: 250px; font-size: 1.05rem;"><?= $row['name'] ?></div>
                                                    <small class="text-muted"><i class="bi bi-tag-fill me-1 opacity-50"></i><?= $row['cat_name'] ?: '-' ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-row flex-lg-column align-items-center align-items-lg-start justify-content-between mt-2 mt-lg-0">
                                                <div>
                                                    <span class="fw-bold text-dark fs-5">฿<?= number_format($row['price']) ?></span><br class="d-none d-lg-block">
                                                    <span class="<?= $row['stock'] <= 5 ? 'text-danger fw-bold' : 'text-success fw-bold' ?> ms-2 ms-lg-0">คงเหลือ <?= number_format($row['stock']) ?> ชิ้น</span>
                                                </div>
                                                
                                                <div class="mt-lg-2">
                                                    <button type="button" class="btn btn-sm btn-outline-info rounded-pill py-1 px-3 w-100 w-lg-auto" style="font-size: 0.8rem;" data-bs-toggle="modal" data-bs-target="#viewLotsModal<?= $row['id'] ?>">
                                                        <i class="bi bi-clock-history"></i> ประวัติรับเข้า (<?= $row['active_lots'] ?> ล็อต)
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end pe-lg-3 td-actions">
                                            <button type="button" onclick="openLotModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>')" class="btn btn-sm btn-primary rounded-pill mb-lg-2 w-100 fw-bold shadow-sm" style="font-size:0.8rem; background-color: #85D1FF; border:none;">
                                                <i class="bi bi-box-arrow-in-down"></i> นำเข้าล็อต
                                            </button>
                                            <a href="?edit=<?= $row['id'] ?>" class="btn btn-sm btn-light text-primary rounded-circle shadow-sm btn-circle-mobile ms-lg-1"><i class="bi bi-pencil-fill"></i></a>
                                            <button onclick="confirmDelete(<?= $row['id'] ?>)" class="btn btn-sm btn-light text-danger rounded-circle shadow-sm btn-circle-mobile ms-2 ms-lg-1"><i class="bi bi-trash-fill"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if(empty($products_list)): ?>
                                        <tr><td colspan="3" class="text-center text-muted py-5 bg-white rounded-4 shadow-sm">ยังไม่มีสินค้าในระบบ</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php foreach($products_list as $row): ?>
<div class="modal fade" id="viewLotsModal<?= $row['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">🕒 ประวัติการรับเข้าสินค้า (Lots)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center mb-3 p-3 bg-light rounded-3">
                    <img src="<?= $row['image'] ?>" class="rounded-3 me-3" style="width:50px; height:50px; object-fit:cover;">
                    <h6 class="text-primary fw-bold mb-0"><?= $row['name'] ?></h6>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered align-middle small text-center m-0">
                        <thead class="bg-light">
                            <tr>
                                <th># ล็อตที่</th>
                                <th>วันที่นำเข้า</th>
                                <th>ราคาขายตั้งไว้</th>
                                <th>คงเหลือ</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $lots_q = mysqli_query($conn, "SELECT * FROM product_lots WHERE product_id = '{$row['id']}' ORDER BY imported_at ASC");
                            $lot_num = 1;
                            if($lots_q && mysqli_num_rows($lots_q) > 0):
                                while($lot = mysqli_fetch_assoc($lots_q)):
                                    $is_empty = ($lot['stock'] <= 0);
                            ?>
                            <tr class="<?= $is_empty ? 'table-secondary text-muted' : '' ?>">
                                <td><?= $lot_num++ ?></td>
                                <td class="fw-bold"><?= date('d/m/Y', strtotime($lot['imported_at'])) ?> <br class="d-lg-none"><span class="text-primary ms-lg-1"><?= date('H:i', strtotime($lot['imported_at'])) ?> น.</span></td>
                                <td>฿<?= number_format($lot['price']) ?></td>
                                <td>
                                    <?php if($is_empty): ?>
                                        <span class="badge bg-secondary">หมดแล้ว</span>
                                    <?php else: ?>
                                        <span class="fw-bold text-success fs-6"><?= $lot['stock'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?delete_lot=<?= $lot['id'] ?>&pid=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger py-1 px-2 rounded-3" onclick="return confirm('ต้องการลบล็อตนี้ทิ้งใช่หรือไม่? (สต๊อกจะหายไปด้วย)')"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="5" class="py-4 text-muted">ไม่พบข้อมูลล็อต</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<div class="modal fade" id="addLotModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">📦 นำเข้าสินค้าล็อตใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="lot_product_id" id="lot_product_id">
                    <div class="mb-4 text-center p-3 bg-light rounded-3">
                        <h6 class="fw-bold text-primary mb-1" id="lot_product_name">ชื่อสินค้า</h6>
                        <small class="text-muted"><i class="bi bi-info-circle me-1"></i> ระบบจะบันทึกเวลาที่นำเข้าให้อัตโนมัติ</small>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="small text-muted fw-bold mb-1">ราคาขายล็อตนี้ (บาท)</label>
                            <input type="number" name="lot_price" class="form-control form-control-lg bg-light" placeholder="เช่น 150" required>
                        </div>
                        <div class="col-6">
                            <label class="small text-muted fw-bold mb-1">จำนวนที่นำเข้า (ชิ้น)</label>
                            <input type="number" name="lot_stock" class="form-control form-control-lg bg-light" placeholder="เช่น 50" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" name="add_lot" class="btn btn-primary w-100 rounded-pill fw-bold py-2" style="background-color: #85D1FF; border:none; font-size: 1.1rem;">บันทึกล็อตใหม่เข้าระบบ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script> 
    function openLotModal(id, name) {
        document.getElementById('lot_product_id').value = id;
        document.getElementById('lot_product_name').innerText = name;
        new bootstrap.Modal(document.getElementById('addLotModal')).show();
    }

    function confirmDelete(id) { Swal.fire({ title:'ยืนยันลบ?', text:'สินค้าและลอตทั้งหมดจะหายไปถาวร', icon:'warning', showCancelButton:true, confirmButtonColor:'#d33', confirmButtonText:'ลบเลย', cancelButtonText:'ยกเลิก' }).then((r)=>{ if(r.isConfirmed) window.location.href='?delete='+id; }) }
    const container = document.getElementById('option-container'); const realInput = document.getElementById('real_options_input');
    window.onload = function() { if(realInput.value) { let groups = realInput.value.split('|'); groups.forEach(group => { let parts = group.split(':'); if(parts.length === 2) addOptionRow(parts[0], parts[1]); }); } };
    function addOptionRow(name = '', values = '') { const div = document.createElement('div'); div.className = 'option-row animate__animated animate__fadeIn'; div.innerHTML = `<div class="d-flex gap-2 mb-1"><input type="text" class="form-control form-control-sm fw-bold opt-name" placeholder="ชื่อตัวเลือก" value="${name}"><button type="button" class="btn btn-sm btn-light text-danger" onclick="this.parentElement.parentElement.remove()"><i class="bi bi-trash"></i></button></div><input type="text" class="form-control form-control-sm opt-values" placeholder="รายการ (คั่นด้วยลูกน้ำ)" value="${values}">`; container.appendChild(div); }
    function prepareOptionsBeforeSubmit() { let rows = document.querySelectorAll('.option-row'); let result = []; rows.forEach(row => { let name = row.querySelector('.opt-name').value.trim(); let values = row.querySelector('.opt-values').value.trim(); if(name && values) { if(values.endsWith(',')) values = values.slice(0, -1); result.push(name + ':' + values); } }); realInput.value = result.join(' | '); }
</script>

<?php if(isset($_SESSION['swal'])): ?>
<script>
    Swal.fire({
        icon: '<?= $_SESSION['swal']['icon'] ?>',
        title: '<?= $_SESSION['swal']['title'] ?>',
        text: '<?= $_SESSION['swal']['text'] ?>',
        confirmButtonColor: '#85D1FF',
        timer: 1500,
        showConfirmButton: false
    });
</script>
<?php unset($_SESSION['swal']); endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>