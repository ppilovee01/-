<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id'];

// นับจำนวนสินค้าในตะกร้า
$cart_count = 0;
if(isset($_SESSION['cart'])) {
    foreach($_SESSION['cart'] as $item) {
        $cart_count += is_array($item) ? $item['qty'] : $item;
    }
}

// ดึงข้อมูล User ล่าสุด
$q = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
$user = mysqli_fetch_assoc($q);

$page_title = "ข้อมูลส่วนตัว | Por Mae Bet Taled";
$extra_css = "
<style>
    .hidden { display: none !important; }

    /* Main Content Card */
    .content-card {
        border: none; border-radius: 16px; background: white;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden;
    }

    /* Custom Tabs */
    .nav-tabs { border-bottom: 2px solid #f0f0f0; padding: 0 20px; }
    .nav-tabs .nav-link {
        border: none; color: #888; font-weight: 500; padding: 15px 20px;
        border-bottom: 3px solid transparent; transition: 0.3s;
    }
    .nav-tabs .nav-link:hover { color: var(--blue-hover); }
    .nav-tabs .nav-link.active {
        color: var(--blue-hover); background: transparent;
        border-bottom-color: var(--blue-hover); font-weight: 700;
    }
    .nav-tabs .nav-link i { margin-right: 8px; font-size: 1.1rem; }

    /* Form Elements */
    .form-control { border-radius: 10px; padding: 10px 15px; border: 1px solid #eee; background-color: #fcfcfc; }
    .form-control:focus { border-color: var(--blue-hover); background-color: white; box-shadow: 0 0 0 4px rgba(174, 226, 255, 0.1); }
    .btn-save {
        background: linear-gradient(135deg, #AEE2FF 0%, #7FB5FF 100%); color: white;
        border: none; border-radius: 50px; padding: 10px 30px; font-weight: 600;
        box-shadow: 0 4px 15px rgba(174, 226, 255, 0.3); transition: 0.3s;
    }
    .btn-save:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(174, 226, 255, 0.5); color: white; }

    /* Address Box */
    .address-item {
        border: 1px solid #eee; border-radius: 12px; padding: 20px;
        background: white; position: relative; transition: 0.2s; height: 100%;
    }
    .address-item:hover { border-color: var(--blue-hover); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
    .btn-del-addr {
        position: absolute; top: 15px; right: 15px;
        width: 30px; height: 30px; border-radius: 50%; background: #F0F8FF;
        color: #dc3545; display: flex; align-items: center; justify-content: center;
        cursor: pointer; transition: 0.2s;
    }
    .btn-del-addr:hover { background: #dc3545; color: white; }
</style>
";
include 'header.php';
?>

<div class="container py-5">
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
            <h3 class="fw-bold mb-4">⚙️ ตั้งค่าบัญชี</h3>

            <div class="content-card">
                <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile-pane" type="button"><i class="bi bi-person-vcard"></i> ข้อมูลส่วนตัว</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="address-tab" data-bs-toggle="tab" data-bs-target="#address-pane" type="button"><i class="bi bi-geo-alt"></i> สมุดที่อยู่</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password-pane" type="button"><i class="bi bi-shield-lock"></i> รหัสผ่าน</button>
                    </li>
                </ul>

                <div class="tab-content p-4" id="myTabContent">
                    
                    <div class="tab-pane fade show active" id="profile-pane" role="tabpanel">
                        <form id="form-profile">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="row g-4">
                                <div class="col-md-12 text-center mb-2">
                                    <div style="width:80px; height:80px; background:#f0f0f0; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:2.5rem; color:#ccc;">
                                        <i class="bi bi-person-circle"></i>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-bold">ชื่อผู้ใช้ (Username)</label>
                                    <input type="text" class="form-control bg-light text-secondary" value="<?= $user['username'] ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-bold">อีเมล</label>
                                    <input type="email" name="email" class="form-control" value="<?= $user['email'] ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-muted small fw-bold">ชื่อ-นามสกุล</label>
                                    <input type="text" name="fullname" class="form-control" value="<?= $user['fullname'] ?>" required>
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn btn-save">บันทึกข้อมูล</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="address-pane" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h6 class="text-muted m-0">ที่อยู่ของคุณ</h6>
                            <button class="btn btn-sm btn-outline-dark rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addAddressModal">
                                <i class="bi bi-plus-lg me-1"></i> เพิ่มที่อยู่
                            </button>
                        </div>

                        <div class="row g-3" id="address-list">
                            <?php 
                            $aq = mysqli_query($conn, "SELECT * FROM user_addresses WHERE user_id='$user_id'");
                            if(mysqli_num_rows($aq) > 0):
                                while($addr = mysqli_fetch_assoc($aq)):
                            ?>
                            <div class="col-md-6 animate__animated animate__fadeIn" id="addr-<?= $addr['id'] ?>">
                                <div class="address-item">
                                    <div class="fw-bold text-dark mb-1 fs-5"><?= $addr['recipient_name'] ?></div>
                                    <div class="text-muted small mb-2"><i class="bi bi-telephone"></i> <?= $addr['phone'] ?></div>
                                    <div class="text-secondary small" style="line-height: 1.5;">
                                        <?= $addr['address_line1'] ?><br>
                                        <?= $addr['subdistrict'] ?> <?= $addr['district'] ?><br>
                                        <?= $addr['province'] ?> <?= $addr['zipcode'] ?>
                                    </div>
                                    <div class="btn-del-addr" onclick="deleteAddress(<?= $addr['id'] ?>)">
                                        <i class="bi bi-trash"></i>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; else: ?>
                                <div id="no-addr-msg" class="text-center py-5 text-muted bg-light rounded-3">
                                    <i class="bi bi-geo-alt display-4 opacity-25"></i>
                                    <p class="mt-2 mb-0">ยังไม่มีที่อยู่จัดส่ง</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="password-pane" role="tabpanel">
                        <form id="form-password" class="py-2">
                            <input type="hidden" name="action" value="change_password">
                            <div class="row g-3 justify-content-center">
                                <div class="col-lg-8">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small fw-bold">รหัสผ่านปัจจุบัน</label>
                                        <div class="input-group">
                                            <input type="password" name="old_password" id="profileOldPass" class="form-control" required>
                                            <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePasswordVisibility('profileOldPass', this)" style="background: #fcfcfc; border-color: #eee;">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label text-muted small fw-bold">รหัสผ่านใหม่</label>
                                            <div class="input-group">
                                                <input type="password" name="new_password" id="profileNewPass" class="form-control" required>
                                                <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePasswordVisibility('profileNewPass', this)" style="background: #fcfcfc; border-color: #eee;">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-muted small fw-bold">ยืนยันรหัสผ่านใหม่</label>
                                            <div class="input-group">
                                                <input type="password" name="confirm_password" id="profileConfirmPass" class="form-control" required>
                                                <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePasswordVisibility('profileConfirmPass', this)" style="background: #fcfcfc; border-color: #eee;">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-save w-100">เปลี่ยนรหัสผ่าน</button>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addAddressModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0"><h5 class="modal-title fw-bold">เพิ่มที่อยู่ใหม่</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <form id="form-add-address">
                <input type="hidden" name="action" value="add_address">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-6"><label class="small text-muted">ชื่อผู้รับ</label><input type="text" name="recipient_name" class="form-control" required></div>
                        <div class="col-6"><label class="small text-muted">เบอร์โทร</label><input type="text" name="phone" class="form-control" required></div>
                        <div class="col-12"><label class="small text-muted">ที่อยู่ (บ้านเลขที่, ซอย, ถนน)</label><input type="text" name="address_line1" class="form-control" required></div>
                        <div class="col-6"><label class="small text-muted">ตำบล/แขวง</label><input type="text" name="subdistrict" class="form-control" required></div>
                        <div class="col-6"><label class="small text-muted">อำเภอ/เขต</label><input type="text" name="district" class="form-control" required></div>
                        <div class="col-6"><label class="small text-muted">จังหวัด</label><input type="text" name="province" class="form-control" required></div>
                        <div class="col-6"><label class="small text-muted">รหัสไปรษณีย์</label><input type="text" name="zipcode" class="form-control" required></div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-save w-100">บันทึกที่อยู่</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // 1. อัปโหลดโปรไฟล์
    document.getElementById('form-profile').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('ajax.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                Swal.fire({ icon: 'success', title: 'สำเร็จ', text: data.message, confirmButtonColor: '#AEE2FF' });
            } else {
                Swal.fire({ icon: 'error', title: 'แจ้งเตือน', text: data.message, confirmButtonColor: '#333' });
            }
        });
    });

    // 2. เปลี่ยนรหัสผ่าน
    document.getElementById('form-password').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const form = this;
        fetch('ajax.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                Swal.fire({ icon: 'success', title: 'สำเร็จ', text: data.message, confirmButtonColor: '#AEE2FF' });
                form.reset();
            } else {
                Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: data.message, confirmButtonColor: '#AEE2FF' });
            }
        });
    });

    // 3. เพิ่มที่อยู่
    document.getElementById('form-add-address').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('ajax.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('addAddressModal')).hide();
                this.reset();
                const list = document.getElementById('address-list');
                const noAddr = document.getElementById('no-addr-msg');
                if(noAddr) noAddr.remove();
                list.insertAdjacentHTML('beforeend', data.html);
                Swal.fire({ icon: 'success', title: 'เรียบร้อย', text: data.message, confirmButtonColor: '#AEE2FF', timer: 1500, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: data.message });
            }
        });
    });

    // 4. ลบที่อยู่ (ไม่ต้องรีเฟรช!)
    function deleteAddress(id) {
        Swal.fire({
            title: 'ลบที่อยู่?', text: "ต้องการลบที่อยู่นี้ใช่ไหม", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'ลบเลย', cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('action', 'delete_address');
                formData.append('address_id', id);
                fetch('ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        // ✅ ลบ Element ออกจากหน้าจอเลย
                        const item = document.getElementById('addr-' + id);
                        if(item) {
                            item.classList.remove('animate__fadeIn');
                            item.classList.add('animate__fadeOut'); 
                            setTimeout(() => item.remove(), 500);
                        }
                        Swal.fire({ icon: 'success', title: 'ลบแล้ว', showConfirmButton: false, timer: 1000 });
                    }
                });
            }
        });
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
