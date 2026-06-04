<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
}

if (isset($_POST['upload_banner'])) {
    if (isset($_FILES['banner_img']) && $_FILES['banner_img']['error'] == 0) {
        $ext = pathinfo($_FILES['banner_img']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array(strtolower($ext), $allowed)) {
            $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>'รองรับเฉพาะไฟล์รูปภาพ (jpg, jpeg, png, gif, webp)', 'icon'=>'error'];
        } else {
            $new_name = "banner_" . uniqid() . "." . strtolower($ext);
            if (!is_dir("uploads")) mkdir("uploads");
            if (move_uploaded_file($_FILES['banner_img']['tmp_name'], "uploads/" . $new_name)) {
                $path = "uploads/" . $new_name;
                mysqli_query($conn, "INSERT INTO banners (image) VALUES ('$path')");
                $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'อัปโหลดแบนเนอร์เรียบร้อย!', 'icon'=>'success'];
            } else { 
                $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>'อัปโหลดไฟล์ล้มเหลว', 'icon'=>'error'];
            }
        }
    } else { 
        $_SESSION['swal'] = ['title'=>'แจ้งเตือน', 'text'=>'กรุณาเลือกไฟล์รูปภาพ', 'icon'=>'warning'];
    }
    header("Location: admin_banners.php"); exit();
}

if (isset($_GET['delete'])) {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
    $id = intval($_GET['delete']);
    $q = mysqli_query($conn, "SELECT image FROM banners WHERE id=$id");
    if ($q && mysqli_num_rows($q) > 0) {
        $row = mysqli_fetch_assoc($q);
        if (file_exists($row['image'])) { unlink($row['image']); }
        mysqli_query($conn, "DELETE FROM banners WHERE id=$id");
    }
    header("Location: admin_banners.php"); exit();
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
        .banner-preview { width: 100%; height: 150px; object-fit: cover; border-radius: 10px; border: 1px solid #ddd; }
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
            <h2 class="fw-bold mb-4">จัดการแบนเนอร์</h2>

            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <form method="POST" enctype="multipart/form-data" class="d-flex flex-column flex-md-row gap-3 align-items-end">
                    <?= get_csrf_input() ?>
                    <div class="flex-grow-1 w-100">
                        <label class="form-label fw-bold">อัปโหลดรูปใหม่</label>
                        <input type="file" name="banner_img" class="form-control" accept="image/*" required>
                        <div class="form-text">แนะนำขนาด 1200 x 400 pixel</div>
                        <button type="submit" name="upload_banner" class="btn btn-gradient px-4 rounded-3 w-100 w-md-auto">
                        <i class="bi bi-cloud-upload-fill me-2"></i> อัปโหลด
                    </button>
                    </div>
                </form>
            </div>

            <div class="row g-4">
                <?php 
                $res = mysqli_query($conn, "SELECT * FROM banners ORDER BY id DESC");
                if (mysqli_num_rows($res) > 0): while($row = mysqli_fetch_assoc($res)): 
                ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card border-0 shadow-sm h-100 rounded-4 overflow-hidden position-relative group-action">
                        <img src="<?= $row['image'] ?>" class="banner-preview">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center">
                            <small class="text-muted">ID: <?= $row['id'] ?></small>
                            <a href="?delete=<?= $row['id'] ?>&csrf_token=<?= get_csrf_token() ?>" class="btn btn-sm btn-outline-danger rounded-circle" onclick="return confirm('ลบรูปนี้?')">
                                <i class="bi bi-trash-fill"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; else: ?>
                <div class="col-12 text-center py-5 text-muted border rounded-4 bg-white">
                    <i class="bi bi-images display-1 opacity-25 d-block mb-3"></i>
                    ยังไม่มีเนบนแ™อรเนŒ
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


