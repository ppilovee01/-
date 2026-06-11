<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้องหรือหมดเวลาเซสชัน (Invalid CSRF Token)']);
        exit();
    }
}

// 1. POST: เพิ่มแบนเนอร์ใหม่
if (isset($_POST['upload_banner'])) {
    if (isset($_FILES['banner_img']) && $_FILES['banner_img']['error'] == 0) {
        $ext = pathinfo($_FILES['banner_img']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array(strtolower($ext), $allowed)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'รองรับเฉพาะไฟล์รูปภาพ (jpg, jpeg, png, gif, webp)']);
            exit();
        } else {
            $new_name = "banner_" . uniqid() . "." . strtolower($ext);
            if (!is_dir("uploads")) mkdir("uploads");
            if (move_uploaded_file($_FILES['banner_img']['tmp_name'], "uploads/" . $new_name)) {
                $path = "uploads/" . $new_name;
                
                $title = isset($_POST['title']) ? mysqli_real_escape_string($conn, trim($_POST['title'])) : null;
                $link_url = isset($_POST['link_url']) ? mysqli_real_escape_string($conn, trim($_POST['link_url'])) : null;
                $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;
                $start_date = !empty($_POST['start_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['start_date']) . "'" : "NULL";
                $end_date = !empty($_POST['end_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['end_date']) . "'" : "NULL";

                $title_val = $title !== null && $title !== '' ? "'$title'" : "NULL";
                $link_url_val = $link_url !== null && $link_url !== '' ? "'$link_url'" : "NULL";

                $sql = "INSERT INTO banners (image, title, link_url, sort_order, start_date, end_date, status) 
                        VALUES ('$path', $title_val, $link_url_val, $sort_order, $start_date, $end_date, 'active')";
                
                if (mysqli_query($conn, $sql)) {
                    $new_id = mysqli_insert_id($conn);
                    log_admin_action($conn, 'อัปโหลดแบนเนอร์', "อัปโหลดแบนเนอร์ใหม่ ID #$new_id สำเร็จ พาธรูปภาพ: $path");
                    
                    header('Content-Type: application/json');
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'อัปโหลดแบนเนอร์เรียบร้อย!',
                        'banner' => [
                            'id' => $new_id,
                            'image' => $path,
                            'title' => $title ? htmlspecialchars($title) : 'ไม่มีชื่อแบนเนอร์',
                            'link_url' => $link_url ? htmlspecialchars($link_url) : '',
                            'status' => 'active',
                            'sort_order' => $sort_order,
                            'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                            'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null
                        ],
                        'csrf_token' => get_csrf_token()
                    ]);
                    exit();
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'บันทึกข้อมูลล้มเหลว: ' . mysqli_error($conn)]);
                    exit();
                }
            } else { 
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'อัปโหลดไฟล์ล้มเหลว']);
                exit();
            }
        }
    } else { 
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'กรุณาเลือกไฟล์รูปภาพ']);
        exit();
    }
}

// 2. POST: แก้ไขข้อมูลแบนเนอร์
if (isset($_POST['update_banner'])) {
    $id = intval($_POST['id']);
    
    // ตรวจสอบความถูกต้องของแบนเนอร์
    $check_q = mysqli_query($conn, "SELECT id FROM banners WHERE id=$id");
    if (!$check_q || mysqli_num_rows($check_q) === 0) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลแบนเนอร์ที่ต้องการแก้ไข']);
        exit();
    }

    $title = isset($_POST['title']) ? mysqli_real_escape_string($conn, trim($_POST['title'])) : null;
    $link_url = isset($_POST['link_url']) ? mysqli_real_escape_string($conn, trim($_POST['link_url'])) : null;
    $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;
    $start_date = !empty($_POST['start_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['start_date']) . "'" : "NULL";
    $end_date = !empty($_POST['end_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['end_date']) . "'" : "NULL";
    $status = (isset($_POST['status']) && $_POST['status'] === 'active') ? 'active' : 'inactive';

    $title_val = $title !== null && $title !== '' ? "'$title'" : "NULL";
    $link_url_val = $link_url !== null && $link_url !== '' ? "'$link_url'" : "NULL";

    $sql = "UPDATE banners SET 
                title = $title_val, 
                link_url = $link_url_val, 
                sort_order = $sort_order, 
                start_date = $start_date, 
                end_date = $end_date,
                status = '$status'
            WHERE id = $id";
            
    if (mysqli_query($conn, $sql)) {
        log_admin_action($conn, 'แก้ไขแบนเนอร์', "แก้ไขข้อมูลแบนเนอร์ ID #$id สำเร็จ");
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success', 
            'message' => 'แก้ไขแบนเนอร์เรียบร้อยแล้ว!',
            'csrf_token' => get_csrf_token()
        ]);
        exit();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'แก้ไขข้อมูลล้มเหลว: ' . mysqli_error($conn)]);
        exit();
    }
}

// 3. POST: สลับสถานะแสดงผล (เปิด/ปิด)
if (isset($_POST['toggle_status'])) {
    $id = intval($_POST['id']);
    $q = mysqli_query($conn, "SELECT status FROM banners WHERE id=$id");
    if ($q && mysqli_num_rows($q) > 0) {
        $row = mysqli_fetch_assoc($q);
        $new_status = ($row['status'] === 'active') ? 'inactive' : 'active';
        mysqli_query($conn, "UPDATE banners SET status='$new_status' WHERE id=$id");
        log_admin_action($conn, 'สลับสถานะแบนเนอร์', "สลับสถานะแบนเนอร์ ID #$id เป็น $new_status");
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'new_status' => $new_status, 'csrf_token' => get_csrf_token()]);
        exit();
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลแบนเนอร์']);
    exit();
}

// 4. GET: ลบแบนเนอร์ (เรียกผ่าน AJAX)
if (isset($_GET['delete'])) {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้องหรือหมดเวลาเซสชัน (Invalid CSRF Token)']);
        exit();
    }
    $id = intval($_GET['delete']);
    $q = mysqli_query($conn, "SELECT image FROM banners WHERE id=$id");
    if ($q && mysqli_num_rows($q) > 0) {
        $row = mysqli_fetch_assoc($q);
        if (file_exists($row['image'])) { unlink($row['image']); }
        mysqli_query($conn, "DELETE FROM banners WHERE id=$id");
        log_admin_action($conn, 'ลบแบนเนอร์', "ลบแบนเนอร์ ID #$id สำเร็จ พาธรูปภาพ: {$row['image']}");
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'ลบแบนเนอร์เรียบร้อยแล้ว']);
        exit();
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลแบนเนอร์']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการแบนเนอร์ | Por Mae Bet Taled Admin</title>
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit'; background: #f8f9fa; }
        .banner-preview { width: 100%; height: 170px; object-fit: cover; border-top-left-radius: 16px; border-top-right-radius: 16px; }
        .btn-gradient { background: linear-gradient(135deg, #AEE2FF 0%, #7FB5FF 100%); color: white; border: none; }
        .btn-gradient:hover { color: white; opacity: 0.9; }
        .banner-card { transition: all 0.3s ease; }
        .form-control, .form-select {
            border: 2px solid #f1f5f9;
            background-color: #f8fafc;
            border-radius: 10px !important;
            padding: 10px 14px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #7FB5FF;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(127, 181, 255, 0.15);
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Drawer -->
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

        <!-- Main Content Area -->
        <div class="col-md-10 p-4 p-md-5">
            <h2 class="fw-bold mb-4"><i class="bi bi-images text-primary me-2"></i>จัดการแบนเนอร์สไลด์หน้าแรก</h2>

            <!-- Card: Add Banner Form -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-cloud-arrow-up-fill text-primary me-2"></i>เพิ่มแบนเนอร์ใหม่</h5>
                <form id="upload-banner-form" method="POST" enctype="multipart/form-data" onsubmit="submitUploadBanner(event)">
                    <?= get_csrf_input() ?>
                    <div class="row g-3">
                        <!-- File Upload -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">รูปภาพแบนเนอร์ <span class="text-danger">*</span></label>
                            <input type="file" name="banner_img" class="form-control" accept="image/*" required>
                            <div class="form-text">แนะนำขนาด 1200 x 400 px (สูงสุด 3MB)</div>
                        </div>
                        
                        <!-- Title -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ชื่อ/หัวข้อแบนเนอร์ (อ้างอิงภายใน)</label>
                            <input type="text" name="title" class="form-control" placeholder="เช่น แบนเนอร์โปรโมชั่น Mid Year Sale">
                        </div>

                        <!-- Link URL -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ลิงก์ปลายทาง (เมื่อกดคลิกแบนเนอร์)</label>
                            <input type="url" name="link_url" class="form-control" placeholder="เช่น https://... หรือ product_detail.php?id=5">
                        </div>

                        <!-- Sort Order -->
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">ลำดับการแสดงผล</label>
                            <input type="number" name="sort_order" class="form-control" value="0" min="0">
                        </div>

                        <!-- Start Date -->
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">เริ่มแสดง (เลือกได้)</label>
                            <input type="datetime-local" name="start_date" class="form-control">
                        </div>

                        <!-- End Date -->
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">สิ้นสุดแสดง (เลือกได้)</label>
                            <input type="datetime-local" name="end_date" class="form-control">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" name="upload_banner" class="btn btn-gradient px-4 rounded-3 py-2 fw-semibold">
                            <i class="bi bi-cloud-upload-fill me-2"></i> บันทึกข้อมูลและอัปโหลด
                        </button>
                    </div>
                </form>
            </div>

            <!-- Banners Display Grid -->
            <div class="row g-4 mb-4" id="banners-grid">
                <?php 
                $limit = isset($_GET['limit']) ? max(10, min(100, intval($_GET['limit']))) : 20;
                $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                
                $count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM banners");
                $total_rows = mysqli_fetch_assoc($count_query)['total'] ?? 0;
                $total_pages = ceil($total_rows / $limit);
                if ($total_pages > 0 && $page > $total_pages) {
                    $page = $total_pages;
                }
                $offset = ($page - 1) * $limit;

                $res = mysqli_query($conn, "SELECT * FROM banners ORDER BY sort_order ASC, id DESC LIMIT $limit OFFSET $offset");
                if (mysqli_num_rows($res) > 0): while($row = mysqli_fetch_assoc($res)): 
                ?>
                <div class="col-12 col-md-6 col-lg-4 banner-card" id="banner-card-<?= $row['id'] ?>">
                    <div class="card border-0 shadow-sm h-100 rounded-4 overflow-hidden position-relative d-flex flex-column">
                        <div style="position: relative;">
                            <img src="<?= $row['image'] ?>" class="banner-preview">
                            <!-- Floating Status Badge -->
                            <span class="position-absolute top-0 end-0 m-2 badge <?= $row['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>" id="status-badge-<?= $row['id'] ?>">
                                <?= $row['status'] === 'active' ? 'กำลังแสดง' : 'ปิดการแสดง' ?>
                            </span>
                        </div>
                        
                        <div class="card-body p-3 d-flex flex-column flex-grow-1">
                            <h6 class="fw-bold mb-1 text-dark text-truncate" id="card-title-<?= $row['id'] ?>">
                                <?= htmlspecialchars($row['title'] ?? 'ไม่มีชื่อแบนเนอร์') ?>
                            </h6>
                            <div class="text-muted small mb-2 text-truncate" style="font-size: 0.8rem;" id="card-link-container-<?= $row['id'] ?>">
                                <i class="bi bi-link-45deg me-1"></i> ลิงก์: 
                                <?php if (!empty($row['link_url'])): ?>
                                    <a href="<?= htmlspecialchars($row['link_url']) ?>" target="_blank" class="text-decoration-none text-link" id="card-link-<?= $row['id'] ?>"><?= htmlspecialchars($row['link_url']) ?></a>
                                <?php else: ?>
                                    <span class="text-muted" id="card-link-<?= $row['id'] ?>">ไม่ได้กำหนด</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mt-auto border-top pt-2">
                                <div class="d-flex justify-content-between align-items-center mb-2" style="font-size: 0.78rem;">
                                    <span><i class="bi bi-sort-numeric-down me-1"></i> ลำดับ: <strong class="text-primary" id="card-sort-<?= $row['id'] ?>"><?= $row['sort_order'] ?></strong></span>
                                    <span class="text-muted text-end" id="card-schedule-<?= $row['id'] ?>">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        <?php 
                                        if (!empty($row['start_date']) || !empty($row['end_date'])) {
                                            $start = !empty($row['start_date']) ? date('d/m/Y H:i', strtotime($row['start_date'])) : 'เริ่มเลย';
                                            $end = !empty($row['end_date']) ? date('d/m/Y H:i', strtotime($row['end_date'])) : 'ไม่มีหมดอายุ';
                                            echo "<span title='$start ถึง $end' class='text-primary fw-semibold'>มีกำหนดเวลา</span>";
                                        } else {
                                            echo "แสดงตลอดเวลา";
                                        }
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <!-- Toggle Status Switch -->
                                    <div class="form-check form-switch d-flex align-items-center ps-0 me-auto">
                                        <input class="form-check-input ms-0 cursor-pointer" type="checkbox" role="switch" id="status-switch-<?= $row['id'] ?>" <?= $row['status'] === 'active' ? 'checked' : '' ?> onchange="toggleBannerStatus(<?= $row['id'] ?>)">
                                        <label class="form-check-label small text-muted ms-2 cursor-pointer" for="status-switch-<?= $row['id'] ?>">แสดงผล</label>
                                    </div>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-primary px-3 rounded-pill" onclick='openEditModal(<?= json_encode($row) ?>)'>
                                        <i class="bi bi-pencil-fill me-1"></i> แก้ไข
                                    </button>
                                    <button onclick="confirmDelete(<?= $row['id'] ?>, '<?= get_csrf_token() ?>')" class="btn btn-sm btn-outline-danger rounded-circle">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; else: ?>
                <div class="col-12 text-center py-5 text-muted border rounded-4 bg-white" id="no-banners-placeholder">
                    <i class="bi bi-images display-1 opacity-25 d-block mb-3"></i>
                    ยังไม่มีแบนเนอร์
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?= render_pagination_controls($total_rows, $limit, $page, $offset) ?>
        </div>
    </div>
</div>

<!-- Modal: Edit Banner Form -->
<div class="modal fade" id="editBannerModal" tabindex="-1" aria-labelledby="editBannerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="editBannerModalLabel"><i class="bi bi-pencil-square text-primary me-2"></i>แก้ไขข้อมูลแบนเนอร์</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="edit-banner-form" onsubmit="submitEditBanner(event)">
                <?= get_csrf_input() ?>
                <div class="modal-body py-3">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ชื่อ/หัวข้อแบนเนอร์ (อ้างอิงภายใน)</label>
                        <input type="text" name="title" id="edit_title" class="form-control" placeholder="ชื่ออ้างอิงภายใน">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">ลิงก์ปลายทาง (URL)</label>
                        <input type="url" name="link_url" id="edit_link_url" class="form-control" placeholder="เช่น https://... หรือ product_detail.php?id=5">
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">ลำดับการแสดงผล</label>
                            <input type="number" name="sort_order" id="edit_sort_order" class="form-control" min="0">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">สถานะ</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="active">เปิดการแสดงผล (Active)</option>
                                <option value="inactive">ปิดการแสดงผล (Inactive)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">เริ่มแสดง (เลือกได้)</label>
                            <input type="datetime-local" name="start_date" id="edit_start_date" class="form-control">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">สิ้นสุดแสดง (เลือกได้)</label>
                            <input type="datetime-local" name="end_date" id="edit_end_date" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">บันทึกการแก้ไข</button>
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
    const editModal = new bootstrap.Modal(document.getElementById('editBannerModal'));

    // แปลงรูปแบบวันที่จาก MySQL (YYYY-MM-DD HH:MM:SS) -> datetime-local (YYYY-MM-DDTHH:MM)
    function formatDatetimeLocal(mysqlStr) {
        if (!mysqlStr) return '';
        return mysqlStr.replace(' ', 'T').substring(0, 16);
    }

    // เปิดหน้าต่างแกไข
    function openEditModal(banner) {
        document.getElementById('edit_id').value = banner.id;
        document.getElementById('edit_title').value = banner.title || '';
        document.getElementById('edit_link_url').value = banner.link_url || '';
        document.getElementById('edit_sort_order').value = banner.sort_order || 0;
        document.getElementById('edit_status').value = banner.status || 'active';
        document.getElementById('edit_start_date').value = formatDatetimeLocal(banner.start_date);
        document.getElementById('edit_end_date').value = formatDatetimeLocal(banner.end_date);
        editModal.show();
    }

    // ส่งข้อมูลบันทึกการแก้ไข (Update Banner)
    function submitEditBanner(e) {
        e.preventDefault();
        const form = document.getElementById('edit-banner-form');
        const formData = new FormData(form);
        formData.append('update_banner', '1');
        formData.append('csrf_token', currentCsrfToken);

        fetch(window.location.pathname, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                currentCsrfToken = data.csrf_token;
                document.querySelectorAll('input[name="csrf_token"]').forEach(el => el.value = currentCsrfToken);
                editModal.hide();
                
                Toast.fire({
                    icon: 'success',
                    title: data.message
                });
                
                // โหลดหน้าจอใหม่เพื่อให้ลำดับการเรียงและเงื่อนไขการฟิลเตอร์รีเฟรชข้อมูลที่ถูกต้องตาม MySQL order
                setTimeout(() => { location.reload(); }, 1000);
            } else {
                Toast.fire({
                    icon: 'error',
                    title: data.message || 'เกิดข้อผิดพลาดในการบันทึกข้อมูล'
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

    // อัปโหลดแบนเนอร์ใหม่
    function submitUploadBanner(e) {
        e.preventDefault();
        const form = document.getElementById('upload-banner-form');
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> กำลังอัปโหลด...';

        const formData = new FormData(form);
        formData.append('upload_banner', '1');
        formData.append('ajax', '1');
        formData.append('csrf_token', currentCsrfToken);

        fetch(window.location.pathname, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-cloud-upload-fill me-2"></i> บันทึกข้อมูลและอัปโหลด';

            if (data.status === 'success') {
                form.reset();
                currentCsrfToken = data.csrf_token;
                document.querySelectorAll('input[name="csrf_token"]').forEach(el => el.value = currentCsrfToken);

                Toast.fire({
                    icon: 'success',
                    title: data.message
                });

                // โหลดหน้าใหม่เพื่อให้แบนเนอร์เรียงลำดับ sort_order ได้ตรงจริงตามดาต้าเบส
                setTimeout(() => { location.reload(); }, 1000);
            } else {
                Toast.fire({
                    icon: 'error',
                    title: data.message || 'เกิดข้อผิดพลาดในการอัปโหลด'
                });
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-cloud-upload-fill me-2"></i> บันทึกข้อมูลและอัปโหลด';
            console.error(err);
            Toast.fire({
                icon: 'error',
                title: 'การเชื่อมต่อล้มเหลว'
            });
        });
    }

    // สลับเปิด/ปิดการแสดงผลแบนเนอร์
    function toggleBannerStatus(id) {
        const formData = new FormData();
        formData.append('toggle_status', '1');
        formData.append('id', id);
        formData.append('csrf_token', currentCsrfToken);

        fetch(window.location.pathname, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                currentCsrfToken = data.csrf_token;
                document.querySelectorAll('input[name="csrf_token"]').forEach(el => el.value = currentCsrfToken);
                
                const badge = document.getElementById('status-badge-' + id);
                if (badge) {
                    if (data.new_status === 'active') {
                        badge.innerText = 'กำลังแสดง';
                        badge.className = 'position-absolute top-0 end-0 m-2 badge bg-success';
                    } else {
                        badge.innerText = 'ปิดการแสดง';
                        badge.className = 'position-absolute top-0 end-0 m-2 badge bg-secondary';
                    }
                }
                
                Toast.fire({
                    icon: 'success',
                    title: 'สลับสถานะแสดงผลเรียบร้อยแล้ว'
                });
            } else {
                Toast.fire({
                    icon: 'error',
                    title: data.message || 'สลับสถานะล้มเหลว'
                });
                // รีเซ็ตเช็คบ็อกซ์กลับสภาพเดิม
                const chk = document.getElementById('status-switch-' + id);
                if (chk) chk.checked = !chk.checked;
            }
        })
        .catch(err => {
            console.error(err);
            Toast.fire({
                icon: 'error',
                title: 'การเชื่อมต่อล้มเหลว'
            });
            const chk = document.getElementById('status-switch-' + id);
            if (chk) chk.checked = !chk.checked;
        });
    }

    // ยืนยันการลบ
    function confirmDelete(id, token) {
        Swal.fire({
            title: 'ลบแบนเนอร์?',
            text: "ยืนยันการลบแบนเนอร์นี้ออกจากระบบ?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'ลบเลย',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(window.location.pathname + `?delete=${id}&csrf_token=${token}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Toast.fire({
                            icon: 'success',
                            title: data.message
                        });
                        const card = document.getElementById('banner-card-' + id);
                        if (card) {
                            card.style.transition = 'all 0.3s ease';
                            card.style.opacity = '0';
                            card.style.transform = 'scale(0.8)';
                            setTimeout(() => {
                                card.remove();
                                const grid = document.getElementById('banners-grid');
                                if (grid && grid.querySelectorAll('.banner-card').length === 0) {
                                    grid.innerHTML = `
                                        <div class="col-12 text-center py-5 text-muted border rounded-4 bg-white" id="no-banners-placeholder">
                                            <i class="bi bi-images display-1 opacity-25 d-block mb-3"></i>
                                            ยังไม่มีแบนเนอร์
                                        </div>
                                    `;
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
</script>

<?php if(isset($_SESSION['swal'])): ?>
<script>
    Swal.fire({
        icon: '<?= $_SESSION['swal']['icon'] ?>',
        title: '<?= $_SESSION['swal']['title'] ?>',
        text: '<?= $_SESSION['swal']['text'] ?>',
        confirmButtonColor: '#AEE2FF',
        timer: 1500,
        showConfirmButton: false
    });
</script>
<?php unset($_SESSION['swal']); endif; ?>
</body>
</html>
