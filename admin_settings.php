<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

// --- การบันทึกข้อมูลตั้งค่าร้านค้า ---
if (isset($_POST['save_settings'])) {
    $name = mysqli_real_escape_string($conn, $_POST['shop_name']);
    $addr = mysqli_real_escape_string($conn, $_POST['address']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['shop_email']);
    $remark = mysqli_real_escape_string($conn, $_POST['print_remark']);
    
    // 1. อัปเดตข้อมูลข้อความ
    $sql = "UPDATE shop_settings SET shop_name='$name', address='$addr', phone='$phone', shop_email='$email', print_remark='$remark' WHERE id=1";
    mysqli_query($conn, $sql);

    // 2. อัปเดต Icon (ถ้ามีการอัปโหลดใหม่)
    if (isset($_FILES['shop_icon']) && $_FILES['shop_icon']['error'] == 0) {
        $ext = pathinfo($_FILES['shop_icon']['name'], PATHINFO_EXTENSION);
        $new_icon = "favicon_" . time() . "." . $ext;
        
        if (!is_dir("uploads")) mkdir("uploads");
        
        if (move_uploaded_file($_FILES['shop_icon']['tmp_name'], "uploads/" . $new_icon)) {
            mysqli_query($conn, "UPDATE shop_settings SET shop_icon='$new_icon' WHERE id=1");
        }
    }

    $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'บันทึกข้อมูลร้านค้าเรียบร้อยแล้ว', 'icon'=>'success'];
    header("Location: admin_settings.php"); exit();
}

$shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id=1"));
$icon_show = !empty($shop['shop_icon']) ? "uploads/".$shop['shop_icon'] : "assets/default_icon.png";
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าร้านค้า | Por Mae Bet Taled Admin</title>
    <link rel="icon" type="image/x-icon" href="<?= $icon_show ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style> 
        body { font-family: 'Kanit'; background: #f8f9fa; } 
        .btn-pastel-blue { background-color: #AEE2FF; color: #444; border: none; transition: 0.3s; }
        .btn-pastel-blue:hover { background-color: #7FB5FF; color: white; }
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
            <h3 class="fw-bold mb-4">⚙️ ตั้งค่าร้านค้า (Shop Settings)</h3>

            <div class="card border-0 shadow-sm rounded-4 p-4">
                <form method="POST" enctype="multipart/form-data">
                    
                    <div class="d-flex flex-column flex-sm-row align-items-center mb-4 p-3 border rounded bg-light">
                        <div class="me-sm-4 text-center mb-3 mb-sm-0">
                            <label class="form-label fw-bold d-block mb-2">ไอคอนเว็บไซต์ (Icon)</label>
                            <img id="iconPreview" src="<?= $icon_show ?>" class="rounded shadow-sm bg-white" style="width: 64px; height: 64px; object-fit: contain; border:1px solid #ddd;">
                        </div>
                        <div class="flex-grow-1 w-100">
                            <label for="iconInput" class="form-label small text-muted">เปลี่ยนรูปไอคอน (รองรับไฟล์ .png, .ico, .jpg)</label>
                            <input type="file" name="shop_icon" id="iconInput" class="form-control" accept="image/*" onchange="previewIcon(event)">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">ชื่อร้านค้า</label>
                            <input type="text" name="shop_name" class="form-control" value="<?= htmlspecialchars($shop['shop_name']) ?>" placeholder="ชื่อร้านค้าของคุณ" required>
                        </div>
                        <div class="col-12 col-md-3 mb-3">
                            <label class="form-label fw-bold">เบอร์โทรศัพท์ติดต่อ</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($shop['phone']) ?>" placeholder="เช่น 081-234-5678" required>
                        </div>
                        <div class="col-12 col-md-3 mb-3">
                            <label class="form-label fw-bold">อีเมล หรือ ไลน์ไอดี</label>
                            <input type="text" name="shop_email" class="form-control" value="<?= htmlspecialchars($shop['shop_email']) ?>" placeholder="เช่น contact@example.com">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">ที่อยู่ร้านค้า (สำหรับจัดส่ง)</label>
                        <textarea name="address" class="form-control" rows="3" placeholder="ระบุที่อยู่ที่ต้องการให้ปรากฏในใบเสร็จหรือใบปะหน้า" required><?= htmlspecialchars($shop['address']) ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-danger">หมายเหตุเพิ่มเติม (จะปรากฏท้ายใบปะหน้าพัสดุ)</label>
                        <textarea name="print_remark" class="form-control" rows="2" placeholder="เช่น กรุณาถ่ายวิดีโอขณะเปิดกล่องพัสดุ"><?= htmlspecialchars($shop['print_remark']) ?></textarea>
                    </div>

                    <button type="submit" name="save_settings" class="btn btn-pastel-blue rounded-pill px-4 w-100 py-2 fw-bold shadow-sm">
                        <i class="bi bi-save me-1"></i> บันทึกข้อมูลทั้งหมด
                    </button>
                </form>
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
        confirmButtonColor: '#AEE2FF'
    });
</script>
<?php unset($_SESSION['swal']); endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function previewIcon(event) {
        const output = document.getElementById('iconPreview');
        output.src = URL.createObjectURL(event.target.files[0]);
    }
</script>
</body>
</html>
