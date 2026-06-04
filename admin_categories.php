<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้องหรือหมดเวลาเซสชัน (Invalid CSRF Token)']);
            exit();
        }
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
}

if (isset($_POST['add_cat'])) {
    $name = mysqli_real_escape_string($conn, $_POST['cat_name']);
    mysqli_query($conn, "INSERT INTO categories (name) VALUES ('$name')");
    $new_id = mysqli_insert_id($conn);
    log_admin_action($conn, 'เพิ่มหมวดหมู่', [
        'title' => "เพิ่มหมวดหมู่สินค้าใหม่: $name",
        'changes' => [
            ['field' => 'ชื่อหมวดหมู่', 'old' => '-', 'new' => $name]
        ]
    ]);
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success', 
            'message' => 'เพิ่มหมวดหมู่เรียบร้อยแล้ว',
            'id' => $new_id,
            'name' => $name,
            'csrf_token' => get_csrf_token()
        ]);
        exit();
    }
    header("Location: admin_categories.php"); exit();
}

if (isset($_GET['delete'])) {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้องหรือหมดเวลาเซสชัน (Invalid CSRF Token)']);
            exit();
        }
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
    $id = intval($_GET['delete']);
    $c_q = mysqli_query($conn, "SELECT name FROM categories WHERE id=$id");
    $c_name = mysqli_fetch_assoc($c_q)['name'] ?? 'ไม่พบชื่อหมวดหมู่';
    mysqli_query($conn, "DELETE FROM categories WHERE id=$id");
    mysqli_query($conn, "UPDATE products SET category_id = NULL WHERE category_id=$id");
    log_admin_action($conn, 'ลบหมวดหมู่', [
        'title' => "ลบหมวดหมู่สินค้า: $c_name (ID #$id)",
        'sections' => [
            [
                'title' => 'รายละเอียดการลบ',
                'items' => [
                    "รหัสหมวดหมู่: #$id",
                    "ชื่อหมวดหมู่: $c_name",
                    "สินค้าในหมวดหมู่นี้ถูกปรับเป็น 'ไม่มีหมวดหมู่'"
                ]
            ]
        ]
    ]);
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'ลบหมวดหมู่สินค้าเรียบร้อยแล้ว']);
        exit();
    }
    header("Location: admin_categories.php"); exit();
}

if (isset($_POST['edit_cat'])) {
    $id = intval($_POST['edit_id']);
    $name = mysqli_real_escape_string($conn, $_POST['edit_name']);
    
    $old_c_q = mysqli_query($conn, "SELECT name FROM categories WHERE id=$id");
    $old_c = mysqli_fetch_assoc($old_c_q);
    $old_name = $old_c ? $old_c['name'] : '';
    
    mysqli_query($conn, "UPDATE categories SET name='$name' WHERE id=$id");
    log_admin_action($conn, 'แก้ไขหมวดหมู่', [
        'title' => "แก้ไขชื่อหมวดหมู่สินค้า ID #$id",
        'changes' => [
            ['field' => 'ชื่อหมวดหมู่', 'old' => $old_name, 'new' => $name]
        ]
    ]);
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success', 
            'message' => 'แก้ไขชื่อหมวดหมู่สินค้าเรียบร้อยแล้ว',
            'id' => $id,
            'name' => $name
        ]);
        exit();
    }
    header("Location: admin_categories.php"); exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการหมวดหมู่ | Por Mae Bet Taled Admin</title>
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit'; background: #f8f9fa; }
        .table-card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.03); background: white; overflow: hidden; }
        .btn-gradient { background: linear-gradient(135deg, #AEE2FF 0%, #7FB5FF 100%); color: white; border: none; }
        .btn-gradient:hover { color: white; opacity: 0.9; }
        .category-row { transition: all 0.3s ease; }
        .category-row.fade-out { opacity: 0; transform: translateX(30px); }
            
        /* สไตล์การ์ดมือถือพรีเมียม */
        @media (max-width: 767.98px) {
            .card-modern-mobile {
                background: #ffffff !important;
                border: 1px solid rgba(226, 232, 240, 0.8) !important;
                border-radius: 20px !important;
                box-shadow: 0 10px 30px rgba(127, 181, 255, 0.05) !important;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                position: relative !important;
                overflow: hidden !important;
                border-left: 5px solid #7FB5FF !important; /* Pastel Blue left accent */
            }
            .card-modern-mobile:hover, .card-modern-mobile:active {
                transform: translateY(-3px) scale(1.01);
                box-shadow: 0 15px 35px rgba(127, 181, 255, 0.12) !important;
                border-color: rgba(127, 181, 255, 0.3) !important;
            }
            .card-modern-mobile .btn {
                border-radius: 12px !important;
                font-weight: 500;
                padding: 6px 12px;
                font-size: 0.78rem;
            }
            .card-modern-mobile .btn-light {
                background: #f8fafc !important;
                border: 1px solid #e2e8f0 !important;
                color: #475569 !important;
            }
            .card-modern-mobile .btn-light:hover {
                background: #f1f5f9 !important;
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

        <div class="col-md-10 p-4 p-md-5">
            <h2 class="fw-bold mb-4">จัดการหมวดหมู่สินค้า</h2>

            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <h5 class="fw-bold mb-3">สร้างหมวดหมู่ใหม่</h5>
                        <form id="add-cat-form" method="POST" onsubmit="submitAddCat(event)">
                            <?= get_csrf_input() ?>
                            <div class="mb-3">
                                <label class="text-muted small">ชื่อหมวดหมู่</label>
                                <input type="text" name="cat_name" id="add_cat_name" class="form-control" placeholder="ขนม" required>
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
                                <tbody id="categories-tbody">
                                    <?php 
                                    $res = mysqli_query($conn, "SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) as prod_count FROM categories c ORDER BY c.id DESC");
                                    if(mysqli_num_rows($res) > 0): while($row = mysqli_fetch_assoc($res)): 
                                    ?>
                                    <!-- Desktop View -->
                                    <tr id="cat-row-<?= $row['id'] ?>" class="category-row d-none d-md-table-row">
                                        <td class="ps-4 fw-bold cat-name-td"><?= htmlspecialchars($row['name']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= $row['prod_count'] ?> ชิ้น</span></td>
                                        <td class="text-end pe-4">
                                            <button onclick="editCat(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>')" class="btn btn-light text-primary btn-sm rounded-circle shadow-sm me-1" data-bs-toggle="modal" data-bs-target="#editModal"><i class="bi bi-pencil-fill"></i></button>
                                            <button onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>', '<?= get_csrf_token() ?>')" class="btn btn-light btn-sm text-danger rounded-circle shadow-sm"><i class="bi bi-trash-fill"></i></button>
                                        </td>
                                    </tr>

                                    <!-- Mobile View -->
                                    <tr id="cat-mob-row-<?= $row['id'] ?>" class="category-row d-md-none">
                                        <td colspan="3" class="p-0 border-0">
                                            <div class="card-modern-mobile p-3 mb-3 text-start">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <div class="fw-bold text-dark cat-name-td" style="font-size: 0.95rem;"><?= htmlspecialchars($row['name']) ?></div>
                                                    <div><span class="badge bg-light text-dark border"><?= $row['prod_count'] ?> ชิ้น</span></div>
                                                </div>
                                                <div class="d-flex justify-content-end gap-2 border-top pt-2">
                                                    <button onclick="editCat(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>')" class="btn btn-light text-primary btn-sm rounded-3 border px-3" data-bs-toggle="modal" data-bs-target="#editModal"><i class="bi bi-pencil-fill me-1"></i> แก้ไข</button>
                                                    <button onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>', '<?= get_csrf_token() ?>')" class="btn btn-light btn-sm text-danger rounded-3 border px-3"><i class="bi bi-trash-fill me-1"></i> ลบ</button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                        <tr id="no-cats-placeholder"><td colspan="3" class="text-center py-4 text-muted">ยังไม่มีหมวดหมู่</td></tr>
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
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">แก้ไขชื่อหมวดหมู่</h5>
                <button class="btn-close" data-bs-dismiss="modal" id="closeEditModalBtn"></button>
            </div>
            <form id="edit-cat-form" method="POST" onsubmit="submitEditCat(event)">
                <?= get_csrf_input() ?>
                <div class="modal-body">
                    <input type="hidden" name="edit_id" id="edit_id">
                    <input type="text" name="edit_name" id="edit_name" class="form-control" required>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" name="edit_cat" class="btn btn-warning w-100 rounded-pill text-white">บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    let currentCsrfToken = '<?= get_csrf_token() ?>';

    function editCat(id, name) { 
        document.getElementById('edit_id').value = id; 
        document.getElementById('edit_name').value = name; 
    }

    function submitAddCat(e) {
        e.preventDefault();
        const form = document.getElementById('add-cat-form');
        const submitBtn = form.querySelector('button[type="submit"]');
        const catNameInput = document.getElementById('add_cat_name');
        
        submitBtn.disabled = true;
        
        const formData = new FormData(form);
        formData.append('add_cat', '1');
        formData.append('ajax', '1');
        formData.append('csrf_token', currentCsrfToken);
        
        fetch(window.location.pathname, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            if (data.status === 'success') {
                catNameInput.value = '';
                currentCsrfToken = data.csrf_token;
                
                // update hidden csrf values in all forms/buttons
                document.querySelectorAll('input[name="csrf_token"]').forEach(el => el.value = currentCsrfToken);
                
                Toast.fire({
                    icon: 'success',
                    title: data.message
                });
                
                // remove no categories placeholder if exists
                const placeholder = document.getElementById('no-cats-placeholder');
                if (placeholder) placeholder.remove();
                
                // Prepend new row
                const tbody = document.getElementById('categories-tbody');
                const tr = document.createElement('tr');
                tr.id = 'cat-row-' + data.id;
                tr.className = 'category-row';
                tr.innerHTML = `
                    <td class="ps-4 fw-bold cat-name-td">${escapeHtml(data.name)}</td>
                    <td><span class="badge bg-light text-dark border">0 ชิ้น</span></td>
                    <td class="text-end pe-4">
                        <button onclick="editCat(${data.id}, '${escapeHtmlString(data.name)}')" class="btn btn-light text-primary btn-sm rounded-circle shadow-sm me-1" data-bs-toggle="modal" data-bs-target="#editModal"><i class="bi bi-pencil-fill"></i></button>
                        <button onclick="confirmDelete(${data.id}, '${escapeHtmlString(data.name)}', '${currentCsrfToken}')" class="btn btn-light text-danger btn-sm rounded-circle shadow-sm"><i class="bi bi-trash-fill"></i></button>
                    </td>
                `;
                tbody.insertBefore(tr, tbody.firstChild);
            } else {
                Toast.fire({
                    icon: 'error',
                    title: data.message || 'เกิดข้อผิดพลาดในการบันทึก'
                });
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            console.error(err);
            Toast.fire({
                icon: 'error',
                title: 'การเชื่อมต่อล้มเหลว'
            });
        });
    }

    function submitEditCat(e) {
        e.preventDefault();
        const form = document.getElementById('edit-cat-form');
        const submitBtn = form.querySelector('button[type="submit"]');
        const id = document.getElementById('edit_id').value;
        const name = document.getElementById('edit_name').value;
        
        submitBtn.disabled = true;
        
        const formData = new FormData(form);
        formData.append('edit_cat', '1');
        formData.append('ajax', '1');
        formData.append('csrf_token', currentCsrfToken);
        
        fetch(window.location.pathname, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            
            const closeBtn = document.getElementById('closeEditModalBtn');
            if (closeBtn) closeBtn.click();
            
            if (data.status === 'success') {
                Toast.fire({
                    icon: 'success',
                    title: data.message
                });
                
                const row = document.getElementById('cat-row-' + id);
                if (row) {
                    row.querySelector('.cat-name-td').innerText = name;
                    const buttons = row.querySelectorAll('button');
                    if (buttons[0]) {
                        buttons[0].setAttribute('onclick', `editCat(${id}, '${escapeHtmlString(name)}')`);
                    }
                    if (buttons[1]) {
                        buttons[1].setAttribute('onclick', `confirmDelete(${id}, '${escapeHtmlString(name)}', '${currentCsrfToken}')`);
                    }
                }
            } else {
                Toast.fire({
                    icon: 'error',
                    title: data.message || 'เกิดข้อผิดพลาดในการแก้ไข'
                });
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            console.error(err);
            Toast.fire({
                icon: 'error',
                title: 'การเชื่อมต่อล้มเหลว'
            });
        });
    }

    function confirmDelete(id, name, token) {
        Swal.fire({
            title: 'ลบหมวดหมู่หรือไม่?',
            text: `ยืนยันการลบหมวดหมู่ "${name}"? สินค้าในหมวดหมู่นี้จะกลายเป็นไม่มีหมวดหมู่`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'ลบเลย',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(window.location.pathname + `?delete=${id}&csrf_token=${token}&ajax=1`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Toast.fire({
                            icon: 'success',
                            title: data.message
                        });
                        
                        const row = document.getElementById('cat-row-' + id);
                        if (row) {
                            row.classList.add('fade-out');
                            setTimeout(() => {
                                row.remove();
                                const tbody = document.getElementById('categories-tbody');
                                if (tbody.children.length === 0) {
                                    tbody.innerHTML = `<tr id="no-cats-placeholder"><td colspan="3" class="text-center py-4 text-muted">ยังไม่มีหมวดหมู่</td></tr>`;
                                }
                            }, 300);
                        }
                    } else {
                        Toast.fire({
                            icon: 'error',
                            title: data.message || 'ลบไม่สำเร็จ'
                        });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Toast.fire({
                        icon: 'error',
                        title: 'การเชื่อมต่อล้มเหลว'
                    });
                });
            }
        });
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function escapeHtmlString(text) {
        return text.replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }
</script>
</body>
</html>
