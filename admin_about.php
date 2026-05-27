<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');

// เเธเนเธสิทเธิเน Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

// --- Logic: บันทึกเธเนอมูล ---
if (isset($_POST['save_about'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    
    // ดึเธรูเธเเธเนามาเธเนอเธ
    $q_old = mysqli_query($conn, "SELECT image FROM about_content WHERE id=1");
    $old_img = mysqli_fetch_assoc($q_old)['image'];
    $image_path = $old_img;

    // ถเนามีการอัเธเนหลดรูเธเนหมเน
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $new_name = "about_" . uniqid() . "." . $ext;
        
        if (!is_dir("uploads")) mkdir("uploads");
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $new_name)) {
            $image_path = "uploads/" . $new_name;
            // (Optional) ลเธรูเธเเธเนาทิเนเธถเนามี
            if (!empty($old_img) && file_exists($old_img)) { unlink($old_img); }
        }
    }

    $sql = "UPDATE about_content SET title='$title', description='$desc', image='$image_path' WHERE id=1";
    
    if (mysqli_query($conn, $sql)) {
        // โ… เนเธเนเนเธ 1: บันทึกเสรเนเธแล้ว ดีดไปหน้าเดิมเธรเนอมเนเธเธเธเนา success=1
        header("Location: admin_about.php?status=success");
        exit();
    } else {
        $err = "เเธิดเธเนอผิดพลาด: " . mysqli_error($conn);
    }
}

$data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM about_content WHERE id=1"));
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เนเธเนเนเธหน้าเกี่ยวกับเรา | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style> body { font-family: 'Kanit'; background: #f8f9fa; } </style>
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
            <h2 class="fw-bold mb-4">๐“ เนเธเนเนเธหน้า "เกี่ยวกับเรา"</h2>

            <div class="card border-0 shadow-sm rounded-4 p-4">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-4 mb-4 text-center">
                            <label class="form-label fw-bold d-block">รูเธภาเธปัจจุบัน</label>
                            <?php if(!empty($data['image'])): ?>
                                <img src="<?= $data['image'] ?>" class="img-fluid rounded shadow-sm mb-3" style="max-height: 300px;">
                            <?php else: ?>
                                <div class="bg-light rounded p-5 text-muted border border-dashed mb-3">เนมเนมีรูเธภาเธ</div>
                            <?php endif; ?>
                            <label class="btn btn-outline-primary w-100">
                                <i class="bi bi-upload me-1"></i> เปลี่ยนรูเธภาเธ
                                <input type="file" name="image" class="d-none" accept="image/*" onchange="previewImage(this)">
                            </label>
                        </div>

                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label fw-bold">หัวเธเนอ (Title)</label>
                                <input type="text" name="title" class="form-control form-control-lg" value="<?= $data['title'] ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">รายละเอียด (Content)</label>
                                <textarea name="description" class="form-control" rows="10" required><?= $data['description'] ?></textarea>
                                <div class="form-text">สามารถเวเนเธเธรรทัดได้ตามเธเธติ</div>
                            </div>

                            <div class="text-end">
                                <button type="submit" name="save_about" class="btn btn-dark rounded-pill px-5 py-2 fw-bold">
                                    <i class="bi bi-save me-2"></i> บันทึกการเปลี่ยนเนเธลเธ
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            Swal.fire('เลือกรูเธแล้ว', 'อยเนาลืมเธดบันทึกเธะเธรัเธ', 'info');
        }
    }
</script>

<?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'สำเร็จ',
        text: 'บันทึกเธเนอมูลเรียเธรเนอยแล้ว',
        confirmButtonColor: '#AEE2FF'
    }).then(() => {
        // ลเนาเธเธเนา status ออกจาก URL เเธืเนอเนมเนเนหเน Alert เธึเนเธเธเนำเวลา Refresh
        window.history.replaceState(null, null, window.location.pathname);
    });
</script>
<?php endif; ?>

<?php if(isset($err)): ?>
<script>Swal.fire({icon:'error', title:'เธออภัย', text:'<?=$err?>'});</script>
<?php endif; ?>

</body>
</html>


