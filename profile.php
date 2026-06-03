<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id'];

$cart_count = 0;
if(isset($_SESSION['cart'])) {
    foreach($_SESSION['cart'] as $item) {
        $cart_count += is_array($item) ? $item['qty'] : $item;
    }
}

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

    /* Timeline styles */
    .timeline-activity {
        position: relative;
        padding-left: 20px;
        border-left: 2px solid #E2E8F0;
        margin-left: 10px;
    }
    .timeline-item {
        position: relative;
        margin-bottom: 20px;
    }
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -26px;
        top: 6px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background-color: #7FB5FF;
        border: 2px solid white;
        box-shadow: 0 0 0 3px rgba(127, 181, 255, 0.2);
    }
    .timeline-time {
        font-size: 0.75rem;
        color: #94a3b8;
        font-weight: 500;
        margin-bottom: 3px;
    }
    .timeline-title {
        font-size: 0.88rem;
        font-weight: 600;
        color: #1e293b;
        margin: 2px 0;
    }
    .timeline-desc {
        font-size: 0.8rem;
        color: #64748b;
    }
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
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-pane" type="button"><i class="bi bi-clock-history"></i> ประวัติบัญชี & แต้มสะสม</button>
                    </li>
                </ul>

                <div class="tab-content p-4" id="myTabContent">
                    
                    <div class="tab-pane fade show active" id="profile-pane" role="tabpanel">
                        <form id="form-profile">
                            <?= get_csrf_input() ?>
                            <input type="hidden" name="action" value="update_profile">
                            <div class="row g-4">
                                <div class="col-md-12 text-center mb-2">
                                    <div style="width:80px; height:80px; background:#f0f0f0; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:2.5rem; color:#ccc;">
                                        <i class="bi bi-person-circle"></i>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-bold">ชื่อผู้ใช้ (Username)</label>
                                    <input type="text" class="form-control bg-light text-secondary" value="<?= htmlspecialchars($user['username'] ?? '') ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-bold">อีเมล</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-muted small fw-bold">ชื่อ-นามสกุล</label>
                                    <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname'] ?? '') ?>" required>
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
                            <div class="col-md-6 animate__animated animate__fadeIn" id="addr-<?= intval($addr['id']) ?>">
                                <div class="address-item">
                                    <div class="fw-bold text-dark mb-1 fs-5"><?= htmlspecialchars($addr['recipient_name'] ?? '') ?></div>
                                    <div class="text-muted small mb-2"><i class="bi bi-telephone"></i> <?= htmlspecialchars($addr['phone'] ?? '') ?></div>
                                    <div class="text-secondary small" style="line-height: 1.5;">
                                        <?= htmlspecialchars($addr['address_line1'] ?? '') ?><br>
                                        <?= htmlspecialchars($addr['subdistrict'] ?? '') ?> <?= htmlspecialchars($addr['district'] ?? '') ?><br>
                                        <?= htmlspecialchars($addr['province'] ?? '') ?> <?= htmlspecialchars($addr['zipcode'] ?? '') ?>
                                    </div>
                                    <div class="btn-del-addr" onclick="deleteAddress(<?= intval($addr['id']) ?>)">
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
                            <?= get_csrf_input() ?>
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
                    
                    <div class="tab-pane fade" id="history-pane" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-lg-4 animate__animated animate__fadeInLeft">
                                <div class="card border-0 rounded-4 p-4 text-white shadow-sm mb-4" style="background: linear-gradient(135deg, #FFE07D 0%, #FFB100 100%);">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <h6 class="text-white-50 mb-1 fw-bold">คะแนนสะสมของคุณ</h6>
                                            <h2 class="fw-bold mb-0 text-white" style="font-size: 2.2rem; text-shadow: 0 2px 4px rgba(0,0,0,0.15);">
                                                <i class="bi bi-coin me-1 text-white"></i><?= number_format($user['points'] ?? 0) ?> <span style="font-size: 1.1rem;">แต้ม</span>
                                            </h2>
                                        </div>
                                        <div class="opacity-25 display-5">
                                            <i class="bi bi-trophy-fill"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card border-0 shadow-sm rounded-4 p-4 bg-light mb-4">
                                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-info-circle me-1 text-primary"></i> กฎการสะสมคะแนน</h6>
                                    <ul class="small text-secondary ps-3 mb-0" style="line-height: 1.6;">
                                        <?php
                                        $shop_q = mysqli_query($conn, "SELECT points_earn_rate, points_spend_rate FROM shop_settings WHERE id = 1");
                                        $shop_info = mysqli_fetch_assoc($shop_q);
                                        $earn_rate = intval($shop_info['points_earn_rate'] ?? 100);
                                        $spend_rate = floatval($shop_info['points_spend_rate'] ?? 1.0);
                                        ?>
                                        <li class="mb-2">ทุกๆ ยอดซื้อ <strong>฿<?= number_format($earn_rate) ?></strong> จะได้รับ <strong>1 คะแนน</strong></li>
                                        <li><strong>1 คะแนน</strong> แลกส่วนลดแทนเงินสดได้ <strong>฿<?= number_format($spend_rate, 2) ?></strong></li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="col-lg-8 animate__animated animate__fadeInRight">
                                <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-receipt text-primary me-1"></i> ประวัติคะแนนสะสม (Points Ledger)</h6>
                                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                                    <div class="table-responsive">
                                        <table class="table align-middle table-hover mb-0" style="font-size: 0.9rem;">
                                            <thead class="bg-light">
                                                <tr class="text-secondary small">
                                                    <th class="ps-4">วัน-เวลา</th>
                                                    <th>คะแนน</th>
                                                    <th class="pe-4">รายละเอียด</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $pt_q = mysqli_query($conn, "SELECT * FROM point_history WHERE user_id = '$user_id' ORDER BY id DESC LIMIT 50");
                                                if (mysqli_num_rows($pt_q) > 0):
                                                    while ($pt = mysqli_fetch_assoc($pt_q)):
                                                        $is_earned = $pt['points'] > 0;
                                                        $points_badge = $is_earned 
                                                            ? '<span class="fw-bold text-success">+' . number_format($pt['points']) . ' แต้ม</span>' 
                                                            : '<span class="fw-bold text-danger">' . number_format($pt['points']) . ' แต้ม</span>';
                                                ?>
                                                <tr>
                                                    <td class="ps-4 text-muted small">
                                                        <?= date('d/m/Y H:i', strtotime($pt['created_at'])) ?> น.
                                                    </td>
                                                    <td>
                                                        <?= $points_badge ?>
                                                    </td>
                                                    <td class="pe-4 text-secondary">
                                                        <?= htmlspecialchars($pt['description']) ?>
                                                    </td>
                                                </tr>
                                                <?php 
                                                    endwhile;
                                                else:
                                                ?>
                                                <tr>
                                                    <td colspan="3" class="text-center py-4 text-muted small">
                                                        ยังไม่มีประวัติคะแนนสะสม
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-clock-history text-primary me-1"></i> ประวัติการเข้าใช้และตั้งค่าบัญชี (Activity Logs)</h6>
                                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-2">
                                    <div class="timeline-activity">
                                        <?php
                                        $act_q = mysqli_query($conn, "SELECT * FROM admin_logs WHERE admin_id = '$user_id' ORDER BY id DESC LIMIT 20");
                                        if (mysqli_num_rows($act_q) > 0):
                                            while ($act = mysqli_fetch_assoc($act_q)):
                                        ?>
                                        <div class="timeline-item">
                                            <div class="timeline-time"><i class="bi bi-clock me-1"></i><?= date('d/m/Y H:i:s', strtotime($act['created_at'])) ?> น. <span class="ms-2">IP: <?= htmlspecialchars($act['ip_address'] ?: '-') ?></span></div>
                                            <div class="timeline-title"><?= htmlspecialchars($act['action_type']) ?></div>
                                            <div class="timeline-desc"><?= htmlspecialchars($act['details']) ?></div>
                                        </div>
                                        <?php 
                                            endwhile;
                                        else:
                                        ?>
                                        <p class="text-muted small mb-0">ยังไม่มีประวัติการใช้งานบัญชี</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
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
                <?= get_csrf_input() ?>
                <input type="hidden" name="action" value="add_address">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="small text-muted">ชื่อผู้รับ</label>
                            <input type="text" name="recipient_name" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="small text-muted">เบอร์โทร</label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="small text-muted">ที่อยู่ (บ้านเลขที่, ซอย, ถนน)</label>
                            <input type="text" name="address_line1" class="form-control" required>
                        </div>
                        
                        <div class="col-6">
                            <label class="small text-muted">จังหวัด</label>
                            <select name="province" id="addr_province" class="form-select" required>
                                <option value="">เลือกจังหวัด...</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="small text-muted">อำเภอ/เขต</label>
                            <select name="district" id="addr_district" class="form-select" required disabled>
                                <option value="">เลือกอำเภอ...</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="small text-muted">ตำบล/แขวง</label>
                            <select name="subdistrict" id="addr_subdistrict" class="form-select" required disabled>
                                <option value="">เลือกตำบล...</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="small text-muted">รหัสไปรษณีย์</label>
                            <input type="text" name="zipcode" id="addr_zipcode" class="form-control" readonly required>
                        </div>
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
    // ==========================================
    // ระบบดึงข้อมูลที่อยู่ประเทศไทย (อัปเดตใช้ฐานข้อมูล V2 ล่าสุด)
    // ==========================================
    document.addEventListener('DOMContentLoaded', function() {
        const provSelect = document.getElementById('addr_province');
        const distSelect = document.getElementById('addr_district');
        const subSelect = document.getElementById('addr_subdistrict');
        const zipInput = document.getElementById('addr_zipcode');
        
        if(provSelect && distSelect && subSelect && zipInput) {
            provSelect.innerHTML = '<option value="">กำลังโหลดข้อมูล...</option>';
            
            let provinces = [];
            let districts = [];
            let subdistricts = [];

            // ดึงข้อมูล 3 ไฟล์พร้อมกันจาก API โครงสร้างใหม่
            Promise.all([
                fetch('https://raw.githubusercontent.com/kongvut/thai-province-data/master/api/latest/province.json').then(res => res.json()),
                fetch('https://raw.githubusercontent.com/kongvut/thai-province-data/master/api/latest/district.json').then(res => res.json()),
                fetch('https://raw.githubusercontent.com/kongvut/thai-province-data/master/api/latest/sub_district.json').then(res => res.json())
            ]).then(data => {
                provinces = data[0];
                districts = data[1];
                subdistricts = data[2];

                provSelect.innerHTML = '<option value="">เลือกจังหวัด...</option>';
                provinces.forEach(prov => {
                    let opt = new Option(prov.name_th, prov.name_th);
                    opt.setAttribute('data-id', prov.id); // เก็บ ID ไว้ดึงอำเภอ
                    provSelect.add(opt);
                });
            }).catch(err => {
                console.error('Fetch Error:', err);
                provSelect.innerHTML = '<option value="">โหลดข้อมูลล้มเหลว</option>';
            });

            // 1. เมื่อเลือก "จังหวัด"
            provSelect.addEventListener('change', function() {
                distSelect.innerHTML = '<option value="">เลือกอำเภอ/เขต...</option>';
                subSelect.innerHTML = '<option value="">เลือกตำบล/แขวง...</option>';
                zipInput.value = '';
                distSelect.disabled = true;
                subSelect.disabled = true;

                if(!this.value) return;

                const selectedOption = this.options[this.selectedIndex];
                const provId = selectedOption.getAttribute('data-id');

                // กรองเฉพาะอำเภอที่อยู่ในจังหวัดนี้
                const filteredDistricts = districts.filter(d => d.province_id == provId);
                filteredDistricts.forEach(dist => {
                    let opt = new Option(dist.name_th, dist.name_th);
                    opt.setAttribute('data-id', dist.id);
                    distSelect.add(opt);
                });
                distSelect.disabled = false;
            });

            // 2. เมื่อเลือก "อำเภอ"
            distSelect.addEventListener('change', function() {
                subSelect.innerHTML = '<option value="">เลือกตำบล/แขวง...</option>';
                zipInput.value = '';
                subSelect.disabled = true;

                if(!this.value) return;

                const selectedOption = this.options[this.selectedIndex];
                const distId = selectedOption.getAttribute('data-id');

                // กรองเฉพาะตำบลที่อยู่ในอำเภอนี้
                const filteredSubs = subdistricts.filter(s => s.district_id == distId);
                filteredSubs.forEach(sub => {
                    let opt = new Option(sub.name_th, sub.name_th);
                    opt.setAttribute('data-zip', sub.zip_code);
                    subSelect.add(opt);
                });
                subSelect.disabled = false;
            });

            // 3. เมื่อเลือก "ตำบล" (เติมรหัสไปรษณีย์)
            subSelect.addEventListener('change', function() {
                zipInput.value = '';
                if(!this.value) return;

                const selectedOption = this.options[this.selectedIndex];
                const zip = selectedOption.getAttribute('data-zip');
                if (zip) {
                    zipInput.value = zip;
                }
            });
        }
    });

    // ==========================================
    // ส่วนล่างคือโค้ดระบบบันทึกโปรไฟล์เดิมของคุณ (ไม่ต้องแก้)
    // ==========================================
    
    // 1. อัปโหลดโปรไฟล์
    let isProfileSubmitting = false;
    document.getElementById('form-profile').addEventListener('submit', function(e) {
        e.preventDefault();
        if (isProfileSubmitting) return;
        isProfileSubmitting = true;
        
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> กำลังบันทึก...';
        }
        
        const formData = new FormData(this);
        fetch('ajax.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            isProfileSubmitting = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'บันทึกข้อมูล';
            }
            if(data.status === 'success') {
                Swal.fire({ icon: 'success', title: 'สำเร็จ', text: data.message, confirmButtonColor: '#AEE2FF' });
            } else {
                Swal.fire({ icon: 'error', title: 'แจ้งเตือน', text: data.message, confirmButtonColor: '#333' });
            }
        })
        .catch(err => {
            isProfileSubmitting = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'บันทึกข้อมูล';
            }
            console.error(err);
        });
    });

    // 2. เปลี่ยนรหัสผ่าน
    let isPasswordSubmitting = false;
    document.getElementById('form-password').addEventListener('submit', function(e) {
        e.preventDefault();
        if (isPasswordSubmitting) return;
        isPasswordSubmitting = true;
        
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> กำลังบันทึก...';
        }
        
        const formData = new FormData(this);
        const form = this;
        fetch('ajax.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            isPasswordSubmitting = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'เปลี่ยนรหัสผ่าน';
            }
            if(data.status === 'success') {
                Swal.fire({ icon: 'success', title: 'สำเร็จ', text: data.message, confirmButtonColor: '#AEE2FF' });
                form.reset();
            } else {
                Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: data.message, confirmButtonColor: '#AEE2FF' });
            }
        })
        .catch(err => {
            isPasswordSubmitting = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'เปลี่ยนรหัสผ่าน';
            }
            console.error(err);
        });
    });

    // 3. เพิ่มที่อยู่
    let isAddressSubmitting = false;
    document.getElementById('form-add-address').addEventListener('submit', function(e) {
        e.preventDefault();
        if (isAddressSubmitting) return;
        isAddressSubmitting = true;
        
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> กำลังบันทึก...';
        }
        
        const formData = new FormData(this);
        fetch('ajax.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            isAddressSubmitting = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'บันทึกที่อยู่';
            }
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
        })
        .catch(err => {
            isAddressSubmitting = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'บันทึกที่อยู่';
            }
            console.error(err);
        });
    });

    // 4. ลบที่อยู่
    function deleteAddress(id) {
        Swal.fire({
            title: 'ลบที่อยู่?', text: "ต้องการลบที่อยู่นี้ใช่ไหม", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'ลบเลย', cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('action', 'delete_address');
                formData.append('address_id', id);
                formData.append('csrf_token', '<?= get_csrf_token() ?>');
                fetch('ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
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