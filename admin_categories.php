<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

if (isset($_POST['add_cat'])) {
    $name = mysqli_real_escape_string($conn, $_POST['cat_name']);
    mysqli_query($conn, "INSERT INTO categories (name) VALUES ('$name')");
    log_admin_action($conn, 'เพิ่มหมวดหมู่', "เพิ่มหมวดหมู่สินค้าใหม่: $name");
    header("Location: admin_categories.php"); exit();
}
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $c_q = mysqli_query($conn, "SELECT name FROM categories WHERE id=$id");
    $c_name = mysqli_fetch_assoc($c_q)['name'] ?? 'ไม่พบชื่อหมวดหมู่';
    mysqli_query($conn, "DELETE FROM categories WHERE id=$id");
    mysqli_query($conn, "UPDATE products SET category_id = NULL WHERE category_id=$id");
    log_admin_action($conn, 'ลบหมวดหมู่', "ลบหมวดหมู่สินค้า: $c_name (ID #$id)");
    header("Location: admin_categories.php"); exit();
}
if (isset($_POST['edit_cat'])) {
    $id = $_POST['edit_id'];
    $name = mysqli_real_escape_string($conn, $_POST['edit_name']);
    mysqli_query($conn, "UPDATE categories SET name='$name' WHERE id=$id");
    log_admin_action($conn, 'แก้ไขหมวดหมู่', "แก้ไขชื่อหมวดหมู่สินค้า ID #$id เป็น: $name");
    header("Location: admin_categories.php"); exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการหมวดหมู่ | Por Mae Bet Taled Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit'; background: #f8f9fa; }
        .table-card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.03); background: white; overflow: hidden; }
        .btn-gradient { background: linear-gradient(135deg, #AEE2FF 0%, #7FB5FF 100%); color: white; border: none; }
        .btn-gradient:hover { color: white; opacity: 0.9; }
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
            <h2 class="fw-bold mb-4">จัดการหมวดหมู่สินค้า</h2>

            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <h5 class="fw-bold mb-3">สร้างหมวดหมู่ใหม่</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="text-muted small">ชื่อหมวดหมู่</label>
                                <input type="text" name="cat_name" class="form-control" placeholder="ขนม" required>
                            </div>
                            <button type="submit" name="add_cat" class="btn btn-gradient w-100 rounded-pill">บันทึก</button>
                        </form>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card table-card p-3">
                        <div class="table-responsive">
                            <table class="table align-middle table-hover">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">ชื่อหมวดหมู่</th>
                                        <th>จำนวนสินค้า</th>
                                        <th class="text-end pe-4">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $res = mysqli_query($conn, "SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) as prod_count FROM categories c ORDER BY c.id DESC");
                                    if(mysqli_num_rows($res) > 0): while($row = mysqli_fetch_assoc($res)): 
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-bold"><?= $row['name'] ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= $row['prod_count'] ?> ชิ้น</span></td>
                                        <td class="text-end pe-4">
                                            <button onclick="editCat(<?= $row['id'] ?>, '<?= $row['name'] ?>')" class="btn btn-light text-primary btn-sm rounded-circle shadow-sm me-1" data-bs-toggle="modal" data-bs-target="#editModal"><i class="bi bi-pencil-fill"></i></button>
                                            <a href="?delete=<?= $row['id'] ?>" class="btn btn-light text-danger btn-sm rounded-circle shadow-sm" onclick="return confirm('ยืนยันลบ?');"><i class="bi bi-trash-fill"></i></a>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                        <tr><td colspan="3" class="text-center py-4 text-muted">ยังไม่มีหมวดหมู่</td></tr>
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

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0"><h5 class="modal-title fw-bold">เนเเน‰เน„ขชื่อหมวดหมู่</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="edit_id" id="edit_id">
                    <input type="text" name="edit_name" id="edit_name" class="form-control" required>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" name="edit_cat" class="btn btn-warning w-100 rounded-pill text-white">บันทึกการเนเเน‰เน„ข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editCat(id, name) { document.getElementById('edit_id').value = id; document.getElementById('edit_name').value = name; }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


