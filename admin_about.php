<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');

// แŠเน‡คสิทธิเนŒ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
}

// --- Logic: บันทึกขเน‰อมูล ---
if (isset($_POST['save_about'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    
    // ดึงรูปแเนˆามาเเนˆอน
    $q_old = mysqli_query($conn, "SELECT image FROM about_content WHERE id=1");
    $old_img = mysqli_fetch_assoc($q_old)['image'];
    $image_path = $old_img;

    // ถ้ามีการอัปเน‚หลดรูปใหม่
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array(strtolower($ext), $allowed)) {
            $err = "รองรับเฉพาะไฟล์รูปภาพ (jpg, jpeg, png, gif, webp) เท่านั้น";
        } else {
            $new_name = "about_" . uniqid() . "." . strtolower($ext);
            
            if (!is_dir("uploads")) mkdir("uploads");
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $new_name)) {
                $image_path = "uploads/" . $new_name;
                // (Optional) ลบรูปแเนˆาทิเน‰งถ้ามี
                if (!empty($old_img) && file_exists($old_img)) { unlink($old_img); }
            }
        }
    }

    $sql = "UPDATE about_content SET title='$title', description='$desc', image='$image_path' WHERE id=1";
    
    if (mysqli_query($conn, $sql)) {
        // โœ… เนเเน‰เน„ข 1: บันทึกเสรเน‡จแล้ว ดีดไปหน้าเดิมเžรเน‰อมเนนบคเนˆา success=1
        header("Location: admin_about.php?status=success");
        exit();
    } else {
        $err = "แิดข้อผิดพลาด: " . mysqli_error($conn);
    }
}

$data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM about_content WHERE id=1"));
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการหน้า "เกี่ยวกับเรา" | Admin</title>
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
            <h2 class="fw-bold mb-4">จัดการหน้า "เกี่ยวกับเรา"</h2>

            <div class="card border-0 shadow-sm rounded-4 p-4">
                <form method="POST" enctype="multipart/form-data">
                    <?= get_csrf_input() ?>
                    <div class="row">
                        <div class="col-md-4 mb-4 text-center">
                            <label class="form-label fw-bold d-block">รูปภาพ</label>
                            <?php if(!empty($data['image'])): ?>
                                <img src="<?= $data['image'] ?>" class="img-fluid rounded shadow-sm mb-3" style="max-height: 300px;">
                            <?php else: ?>
                                <div class="bg-light rounded p-5 text-muted border border-dashed mb-3">ไม่มีรูปภาพ</div>
                            <?php endif; ?>
                            <label class="btn btn-outline-primary w-100">
                                <i class="bi bi-upload me-1"></i> เปลี่ยนรูปภาพ
                                <input type="file" name="image" class="d-none" accept="image/*" onchange="previewImage(this)">
                            </label>
                        </div>

                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label fw-bold">หัวข้อ (Title)</label>
                                <input type="text" name="title" class="form-control form-control-lg" value="<?= $data['title'] ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">รายละเอียด (Content)</label>
                                <textarea name="description" class="form-control" rows="10" required><?= $data['description'] ?></textarea>
                                <div class="form-text">สามารถเว้นบรรทัดได้ตามปกติ</div>
                            </div>

                            <div class="text-end">
                                <button type="submit" name="save_about" class="btn btn-dark rounded-pill px-5 py-2 fw-bold">
                                    <i class="bi bi-save me-2"></i> บันทึกการเปลี่ยนแปลง
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
            Swal.fire('เลือกรูปแล้ว', 'อย่าลืมบันทึกนะครับ', 'info');
        }
    }
</script>

<?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'สำเร็จ',
        text: 'บันทึกขเน‰อมูลเรียบร้อยแล้ว',
        confirmButtonColor: '#AEE2FF'
    }).then(() => {
        // ลเน‰างคเนˆา status ออกจาก URL เพื่อไม่ให้ Alert ขึเน‰นเ‹เน‰ำเวลา Refresh
        window.history.replaceState(null, null, window.location.pathname);
    });
</script>
<?php endif; ?>

<?php if(isset($err)): ?>
<script>Swal.fire({icon:'error', title:'ขออภัย', text:'<?=$err?>'});</script>
<?php endif; ?>

</body>
</html>


