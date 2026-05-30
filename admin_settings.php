<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

// --- การบันทึกข้อมูลตั้งค่าร้านค้า ---
if (isset($_POST['save_settings']) || isset($_POST['test_smtp'])) {
    $name = mysqli_real_escape_string($conn, $_POST['shop_name']);
    $addr = mysqli_real_escape_string($conn, $_POST['address']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['shop_email']);
    $remark = mysqli_real_escape_string($conn, $_POST['print_remark']);
    $smtp_host = mysqli_real_escape_string($conn, $_POST['smtp_host']);
    $smtp_port = intval($_POST['smtp_port']);
    $smtp_user = mysqli_real_escape_string($conn, $_POST['smtp_user']);
    $smtp_pass = str_replace(' ', '', $_POST['smtp_pass']);
    $smtp_pass = mysqli_real_escape_string($conn, $smtp_pass);
    $smtp_secure = mysqli_real_escape_string($conn, $_POST['smtp_secure']);
    $welcome_promo_enabled = isset($_POST['welcome_promo_enabled']) ? intval($_POST['welcome_promo_enabled']) : 1;
    $welcome_promo_coupon = mysqli_real_escape_string($conn, $_POST['welcome_promo_coupon'] ?? '');
    $shipping_fee_fixed = floatval($_POST['shipping_fee_fixed'] ?? 40.00);
    $shipping_free_threshold = floatval($_POST['shipping_free_threshold'] ?? 350.00);
    
    // 1. อัปเดตข้อมูลข้อความ
    $sql = "UPDATE shop_settings SET 
            shop_name='$name', 
            address='$addr', 
            phone='$phone', 
            shop_email='$email', 
            print_remark='$remark', 
            smtp_host='$smtp_host', 
            smtp_port='$smtp_port', 
            smtp_user='$smtp_user', 
            smtp_pass='$smtp_pass', 
            smtp_secure='$smtp_secure', 
            welcome_promo_enabled='$welcome_promo_enabled', 
            welcome_promo_coupon='$welcome_promo_coupon', 
            shipping_fee_fixed='$shipping_fee_fixed', 
            shipping_free_threshold='$shipping_free_threshold' 
            WHERE id=1";
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

    if (isset($_POST['test_smtp'])) {
        include 'mail_sender.php';
        $res = send_test_email($conn);
        if ($res === true) {
            $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'ทดสอบการเชื่อมต่อ SMTP สำเร็จแล้ว! มีอีเมลทดสอบส่งไปยังกล่องจดหมายของคุณเรียบร้อย', 'icon'=>'success'];
        } else {
            $_SESSION['swal'] = ['title'=>'เกิดข้อผิดพลาด', 'text'=>'เชื่อมต่อล้มเหลว: ' . $res, 'icon'=>'error'];
        }
    } else {
        $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'บันทึกข้อมูลร้านค้าเรียบร้อยแล้ว', 'icon'=>'success'];
    }
    header("Location: admin_settings.php"); exit();
}

$shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id=1"));
$icon_show = !empty($shop['shop_icon']) ? "uploads/".$shop['shop_icon'] : "assets/default_icon.png";

// ดึงคูปองที่มีสถานะเปิดใช้งานและยังไม่หมดอายุ
$today = date('Y-m-d');
$coupons_query = mysqli_query($conn, "SELECT code, discount_type, discount_value FROM coupons WHERE status='active' AND expiry_date >= '$today'");
$active_coupons = [];
if ($coupons_query) {
    while ($c = mysqli_fetch_assoc($coupons_query)) {
        $active_coupons[] = $c;
    }
}
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

                    <hr class="my-4">
                    <h5 class="fw-bold text-primary mb-3"><i class="bi bi-gift-fill me-1"></i> ตั้งค่าป๊อปอัปข้อเสนอพิเศษต้อนรับ (Welcome Promo Pop-up Settings)</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted">สถานะป๊อปอัปข้อเสนอพิเศษต้อนรับหน้าแรก</label>
                            <select name="welcome_promo_enabled" class="form-select">
                                <option value="1" <?= ($shop['welcome_promo_enabled'] ?? 1) == 1 ? 'selected' : '' ?>>เปิดใช้งาน (Enabled)</option>
                                <option value="0" <?= ($shop['welcome_promo_enabled'] ?? 1) == 0 ? 'selected' : '' ?>>ปิดใช้งาน (Disabled)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted">คูปองที่ต้องการแนะนำป๊อปอัป</label>
                            <select name="welcome_promo_coupon" class="form-select">
                                <option value="" <?= empty($shop['welcome_promo_coupon']) ? 'selected' : '' ?>>เลือกคูปองที่คุ้มที่สุดโดยอัตโนมัติ</option>
                                <?php foreach ($active_coupons as $c): ?>
                                    <option value="<?= htmlspecialchars($c['code']) ?>" <?= ($shop['welcome_promo_coupon'] ?? '') == $c['code'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['code']) ?> - ลด <?= $c['discount_type'] == 'percent' ? intval($c['discount_value']) . '%' : '฿' . number_format($c['discount_value']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="fw-bold text-primary mb-3"><i class="bi bi-truck me-1"></i> ตั้งค่าเป้าหมายจัดส่งฟรี (Free Shipping Target Settings)</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted">ค่าจัดส่งปกติ (บาท)</label>
                            <input type="number" step="0.01" name="shipping_fee_fixed" class="form-control" value="<?= htmlspecialchars(number_format($shop['shipping_fee_fixed'] ?? 40.00, 2, '.', '')) ?>" placeholder="เช่น 40.00" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted">ยอดซื้อขั้นต่ำเพื่อจัดส่งฟรี (บาท)</label>
                            <input type="number" step="0.01" name="shipping_free_threshold" class="form-control" value="<?= htmlspecialchars(number_format($shop['shipping_free_threshold'] ?? 350.00, 2, '.', '')) ?>" placeholder="เช่น 350.00" required>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="fw-bold text-primary mb-3"><i class="bi bi-envelope-at-fill me-1"></i> ตั้งค่าอีเมลส่งแจ้งเตือน (SMTP Settings)</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-bold text-muted">SMTP Server Host</label>
                            <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($shop['smtp_host'] ?? '') ?>" placeholder="เช่น smtp.gmail.com">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label small fw-bold text-muted">SMTP Port</label>
                            <input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars($shop['smtp_port'] ?? '587') ?>" placeholder="เช่น 587">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label small fw-bold text-muted">ประเภทความปลอดภัย</label>
                            <select name="smtp_secure" class="form-select">
                                <option value="tls" <?= ($shop['smtp_secure'] ?? '') == 'tls' ? 'selected' : '' ?>>TLS (แนะนำ)</option>
                                <option value="ssl" <?= ($shop['smtp_secure'] ?? '') == 'ssl' ? 'selected' : '' ?>>SSL</option>
                                <option value="none" <?= ($shop['smtp_secure'] ?? '') == 'none' ? 'selected' : '' ?>>ไม่มี (None)</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-bold text-muted">SMTP Username (Email)</label>
                            <input type="email" name="smtp_user" class="form-control" value="<?= htmlspecialchars($shop['smtp_user'] ?? '') ?>" placeholder="เช่น shop@gmail.com">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-bold text-muted">SMTP Password (หรือ App Password)</label>
                            <div class="input-group">
                                <input type="password" name="smtp_pass" id="smtpPassInput" class="form-control" value="<?= htmlspecialchars($shop['smtp_pass'] ?? '') ?>" placeholder="รหัสผ่านอีเมลจัดส่ง">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('smtpPassInput', this)" style="border-color: #dee2e6;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-8">
                            <button type="submit" name="save_settings" class="btn btn-pastel-blue rounded-pill px-4 w-100 py-2 fw-bold shadow-sm">
                                <i class="bi bi-save me-1"></i> บันทึกข้อมูลทั้งหมด
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" name="test_smtp" class="btn btn-outline-primary rounded-pill px-4 w-100 py-2 fw-bold shadow-sm bg-white" style="border-color: #AEE2FF; color: #444;">
                                <i class="bi bi-send-check me-1"></i> ทดสอบการส่งอีเมล
                            </button>
                        </div>
                    </div>
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

    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
</script>
</body>
</html>
