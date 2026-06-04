<?php
session_start();
include 'db.php';

// ตั้งค่า Timezone ให้ตรงกับไทยเสมอ
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

// --- Logic 1: เพิ่มข้อมูล (Add) ---
if (isset($_POST['add'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $num = mysqli_real_escape_string($conn, $_POST['account_number']);
    $acc_name = mysqli_real_escape_string($conn, $_POST['account_name']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $sql = "INSERT INTO payment_methods (name, type, account_number, account_name, status) VALUES ('$name', '$type', '$num', '$acc_name', '$status')";
    if(mysqli_query($conn, $sql)) {
        $new_id = mysqli_insert_id($conn);
        log_admin_action($conn, 'เพิ่มช่องทางชำระเงิน', [
            'title' => "เพิ่มช่องทางชำระเงิน: $name",
            'sections' => [
                [
                    'title' => 'รายละเอียดช่องทางชำระเงินใหม่',
                    'items' => [
                        "ชื่อช่องทาง: $name",
                        "ประเภท: " . ($type === 'bank' ? 'บัญชีธนาคาร' : ($type === 'promptpay' ? 'พร้อมเพย์' : 'เก็บเงินปลายทาง')),
                        "เลขบัญชี/เบอร์โทร: " . (!empty($num) ? $num : '-'),
                        "ชื่อบัญชี: " . (!empty($acc_name) ? $acc_name : '-'),
                        "สถานะ: " . ($status === 'active' ? 'เปิดใช้งาน' : 'ปิดใช้งาน')
                    ]
                ]
            ]
        ]);
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'เพิ่มช่องทางชำระเงินเรียบร้อยแล้ว',
                'payment' => [
                    'id' => $new_id,
                    'name' => $name,
                    'type' => $type,
                    'account_number' => $num,
                    'account_name' => $acc_name,
                    'status' => $status
                ],
                'csrf_token' => get_csrf_token()
            ]);
            exit();
        }
        header("Location: admin_payments.php"); exit();
    }
}

// --- Logic 2: ลบข้อมูล (Delete) ---
if (isset($_GET['del'])) {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้องหรือหมดเวลาเซสชัน (Invalid CSRF Token)']);
            exit();
        }
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
    $id = intval($_GET['del']);
    $p_q = mysqli_query($conn, "SELECT * FROM payment_methods WHERE id=$id");
    $p_info = mysqli_fetch_assoc($p_q);
    $p_name = $p_info['name'] ?? 'ไม่ระบุ';
    $p_num = $p_info['account_number'] ?? 'ไม่ระบุ';
    $p_type = $p_info['type'] ?? 'ไม่ระบุ';
    $p_acc_name = $p_info['account_name'] ?? 'ไม่ระบุ';
    
    mysqli_query($conn, "DELETE FROM payment_methods WHERE id=$id");
    log_admin_action($conn, 'ลบช่องทางชำระเงิน', [
        'title' => "ลบช่องทางชำระเงิน: $p_name (รหัส #$id)",
        'sections' => [
            [
                'title' => 'รายละเอียดช่องทางที่ลบ',
                'items' => [
                    "รหัสช่องทาง: #$id",
                    "ชื่อช่องทาง: $p_name",
                    "ประเภท: " . ($p_type === 'bank' ? 'บัญชีธนาคาร' : ($p_type === 'promptpay' ? 'พร้อมเพย์' : 'เก็บเงินปลายทาง')),
                    "เลขบัญชี/เบอร์โทร: $p_num",
                    "ชื่อบัญชี: $p_acc_name"
                ]
            ]
        ]
    ]);
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'ลบช่องทางชำระเงินเรียบร้อยแล้ว']);
        exit();
    }
    header("Location: admin_payments.php"); exit();
}

// --- Logic 4: อัปเดตข้อมูล (Update) ---
if (isset($_POST['update'])) {
    $id = intval($_POST['id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $num = mysqli_real_escape_string($conn, $_POST['account_number']);
    $acc_name = mysqli_real_escape_string($conn, $_POST['account_name']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    // ดึงข้อมูลเก่าเพื่อคำนวณ diff log
    $old_q = mysqli_query($conn, "SELECT * FROM payment_methods WHERE id=$id");
    $old_data = mysqli_fetch_assoc($old_q);

    $sql = "UPDATE payment_methods SET name='$name', type='$type', account_number='$num', account_name='$acc_name', status='$status' WHERE id=$id";
    if(mysqli_query($conn, $sql)) {
        // คำนวณส่วนต่างการแก้ไข
        $diff = [];
        if ($old_data) {
            if ($old_data['name'] !== $name) {
                $diff['ชื่อช่องทาง'] = [$old_data['name'], $name];
            }
            if ($old_data['type'] !== $type) {
                $type_map = ['bank' => 'บัญชีธนาคาร', 'promptpay' => 'พร้อมเพย์', 'cod' => 'เก็บเงินปลายทาง'];
                $diff['ประเภท'] = [$type_map[$old_data['type']] ?? $old_data['type'], $type_map[$type] ?? $type];
            }
            if ($old_data['account_number'] !== $num) {
                $diff['เลขบัญชี/เบอร์โทร'] = [$old_data['account_number'] ?: '-', $num ?: '-'];
            }
            if ($old_data['account_name'] !== $acc_name) {
                $diff['ชื่อบัญชี'] = [$old_data['account_name'] ?: '-', $acc_name ?: '-'];
            }
            if ($old_data['status'] !== $status) {
                $status_map = ['active' => 'เปิดใช้งาน', 'inactive' => 'ปิดใช้งาน'];
                $diff['สถานะ'] = [$status_map[$old_data['status']] ?? $old_data['status'], $status_map[$status] ?? $status];
            }
        }

        log_admin_action($conn, 'แก้ไขช่องทางชำระเงิน', [
            'title' => "แก้ไขช่องทางชำระเงิน: $name (รหัส #$id)",
            'diff' => $diff,
            'sections' => [
                [
                    'title' => 'รายละเอียดหลังแก้ไข',
                    'items' => [
                        "ชื่อช่องทาง: $name",
                        "ประเภท: " . ($type === 'bank' ? 'บัญชีธนาคาร' : ($type === 'promptpay' ? 'พร้อมเพย์' : 'เก็บเงินปลายทาง')),
                        "เลขบัญชี/เบอร์โทร: " . (!empty($num) ? $num : '-'),
                        "ชื่อบัญชี: " . (!empty($acc_name) ? $acc_name : '-'),
                        "สถานะ: " . ($status === 'active' ? 'เปิดใช้งาน' : 'ปิดใช้งาน')
                    ]
                ]
            ]
        ]);
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'แก้ไขช่องทางชำระเงินเรียบร้อยแล้ว',
                'payment' => [
                    'id' => $id,
                    'name' => $name,
                    'type' => $type,
                    'account_number' => $num,
                    'account_name' => $acc_name,
                    'status' => $status
                ]
            ]);
            exit();
        }
        header("Location: admin_payments.php"); exit();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ช่องทางการชำระเงิน | Por Mae Bet Taled Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <?php 
        $icon_q = mysqli_query($conn, "SELECT shop_icon FROM shop_settings WHERE id=1");
        $icon_r = mysqli_fetch_assoc($icon_q);
        $favicon = !empty($icon_r['shop_icon']) ? "uploads/".$icon_r['shop_icon'] : "assets/default_icon.png";
    ?>
    <link rel="icon" type="image/x-icon" href="<?= $favicon ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style> 
        body { font-family: 'Kanit'; background: #f8f9fa; } 
        .payment-row { transition: all 0.3s ease; }
        .payment-row.fade-out { opacity: 0; transform: translateX(30px); }
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
            <h2 class="fw-bold mb-4">ช่องทางชำระเงิน</h2>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm rounded-4 p-4 sticky-top" style="top: 20px;">
                        <h5 class="fw-bold mb-3" id="form-title">
                            <i class="bi bi-plus-circle text-primary"></i> เพิ่มช่องทางใหม่
                        </h5>
                        
                        <form id="payment-form" method="POST" onsubmit="submitPaymentForm(event)">
                            <?= get_csrf_input() ?>
                            <input type="hidden" name="id" id="payment_id" value="">
                            
                            <div class="mb-2">
                                <label class="small text-muted fw-bold">เลือกธนาคาร / ช่องทางชำระเงิน</label>
                                <select name="bank_select" id="bankSelect" class="form-select mb-2" onchange="toggleCustomBankName(this)">
                                    <option value="ธนาคารกสิกรไทย">ธนาคารกสิกรไทย (KBANK)</option>
                                    <option value="ธนาคารไทยพาณิชย์">ธนาคารไทยพาณิชย์ (SCB)</option>
                                    <option value="ธนาคารกรุงไทย">ธนาคารกรุงไทย (KTB)</option>
                                    <option value="ธนาคารกรุงเทพ">ธนาคารกรุงเทพ (BBL)</option>
                                    <option value="ธนาคารกรุงศรีอยุธยา">ธนาคารกรุงศรีอยุธยา (BAY)</option>
                                    <option value="ธนาคารทหารไทยธนชาต">ธนาคารทหารไทยธนชาต (TTB)</option>
                                    <option value="ธนาคารออมสิน">ธนาคารออมสิน (GSB)</option>
                                    <option value="พร้อมเพย์ (PromptPay)">พร้อมเพย์ (PromptPay)</option>
                                    <option value="เก็บเงินปลายทาง (COD)">เก็บเงินปลายทาง (COD)</option>
                                    <option value="custom">อื่นๆ (ระบุเอง)</option>
                                </select>
                                <input type="text" name="name" id="customBankName" class="form-control" placeholder="ระบุชื่อธนาคาร/ช่องทางชำระเงิน" required>
                            </div>
                            
                            <div class="mb-2">
                                <label class="small text-muted fw-bold">ประเภท</label>
                                <select name="type" id="payment_type" class="form-select">
                                    <option value="bank">บัญชีธนาคาร</option>
                                    <option value="promptpay">promptpay (QR)</option>
                                    <option value="cod">เก็บเงินปลายทาง</option>
                                </select>
                            </div>
                            
                            <div class="mb-2">
                                <label class="small text-muted fw-bold">ชื่อบัญชี / ชื่อผู้รับโอน</label>
                                <input type="text" name="account_name" id="account_name" class="form-control" placeholder="เช่น นายสมชาย รักดี" required>
                            </div>
                            
                            <div class="mb-2">
                                <label class="small text-muted fw-bold">เลขบัญชี / เบอร์โทรพร้อมเพย์</label>
                                <input type="text" name="account_number" id="account_number" class="form-control" placeholder="ไม่จำเป็นสำหรับ COD">
                            </div>

                            <div class="mb-3">
                                <label class="small text-muted fw-bold">สถานะการใช้งาน</label>
                                <select name="status" id="payment_status" class="form-select">
                                    <option value="active">เปิดใช้งาน (Active)</option>
                                    <option value="inactive">ปิดใช้งาน (Inactive)</option>
                                </select>
                            </div>
                            
                            <div id="form-actions-container">
                                <button type="submit" name="add" id="submit-btn" class="btn btn-dark w-100 rounded-3">บันทึกช่องทาง</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <div class="table-responsive">
                            <table class="table align-middle" style="min-width: 500px;">
                                <thead class="text-muted small">
                                    <tr>
                                        <th>ชื่อช่องทาง</th>
                                        <th>ประเภท</th>
                                        <th>ชื่อบัญชี</th>
                                        <th>เลขบัญชี/เบอร์พร้อมเพย์</th>
                                        <th>สถานะ</th>
                                        <th class="text-end">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody id="payments-tbody">
                                    <?php 
                                    $res = mysqli_query($conn, "SELECT * FROM payment_methods"); 
                                    while($row = mysqli_fetch_assoc($res)): 
                                        
                                        // ตั้งค่า Badge ตามประเภท
                                        $type_badge = 'bg-light text-dark border';
                                        if($row['type'] == 'promptpay') $type_badge = 'bg-info-subtle text-info border border-info-subtle';
                                        elseif($row['type'] == 'cod') $type_badge = 'bg-warning-subtle text-warning border border-warning-subtle';
                                        
                                        // ตั้งค่า Badge ตามสถานะ
                                        $status_badge = 'bg-success';
                                        $status_text = 'เปิดใช้งาน';
                                        if(($row['status'] ?? 'active') == 'inactive') {
                                            $status_badge = 'bg-secondary';
                                            $status_text = 'ปิดใช้งาน';
                                        }
                                    ?>
                                    <tr id="payment-row-<?= $row['id'] ?>" class="payment-row">
                                        <td class="fw-bold payment-name-cell"><?= htmlspecialchars($row['name']) ?></td>
                                        <td class="payment-type-cell"><span class="badge <?= $type_badge ?>"><?= $row['type'] ?></span></td>
                                        <td class="payment-accname-cell"><?= !empty($row['account_name']) ? htmlspecialchars($row['account_name']) : '-' ?></td>
                                        <td class="payment-accnumber-cell"><?= !empty($row['account_number']) ? htmlspecialchars($row['account_number']) : '-' ?></td>
                                        <td class="payment-status-cell"><span class="badge <?= $status_badge ?>"><?= $status_text ?></span></td>
                                        <td class="text-end">
                                            <button onclick='loadEditPayment(<?= json_encode($row) ?>)' class="btn btn-light btn-sm text-primary rounded-circle shadow-sm me-1 edit-payment-btn" title="แก้ไข">
                                                <i class="bi bi-pencil-fill"></i>
                                            </button>
                                            <button onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>', '<?= get_csrf_token() ?>')" class="btn btn-light btn-sm text-danger rounded-circle shadow-sm" title="ลบ">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
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

    function toggleCustomBankName(select) {
        const customInput = document.getElementById('customBankName');
        const typeSelect = document.getElementById('payment_type');
        
        if (select.value === 'custom') {
            customInput.style.display = 'block';
            customInput.required = true;
        } else {
            customInput.style.display = 'none';
            customInput.value = select.value;
            customInput.required = false;
            
            if (typeSelect) {
                if (select.value === 'พร้อมเพย์ (PromptPay)') {
                    typeSelect.value = 'promptpay';
                } else if (select.value === 'เก็บเงินปลายทาง (COD)') {
                    typeSelect.value = 'cod';
                } else {
                    typeSelect.value = 'bank';
                }
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const select = document.getElementById('bankSelect');
        if (select) {
            toggleCustomBankName(select);
        }
    });

    function loadEditPayment(data) {
        document.querySelectorAll('.payment-row').forEach(row => row.classList.remove('table-warning'));
        
        const row = document.getElementById('payment-row-' + data.id);
        if (row) row.classList.add('table-warning');

        document.getElementById('form-title').innerHTML = '<i class="bi bi-pencil-square text-warning"></i> แก้ไขช่องทาง';
        document.getElementById('payment_id').value = data.id;
        
        const select = document.getElementById('bankSelect');
        const customInput = document.getElementById('customBankName');
        
        const isStandard = ['ธนาคารกสิกรไทย','ธนาคารไทยพาณิชย์','ธนาคารกรุงไทย','ธนาคารกรุงเทพ','ธนาคารกรุงศรีอยุธยา','ธนาคารทหารไทยธนชาต','ธนาคารออมสิน','พร้อมเพย์ (PromptPay)','เก็บเงินปลายทาง (COD)'].includes(data.name);
        if (isStandard) {
            select.value = data.name;
            customInput.value = data.name;
            customInput.style.display = 'none';
        } else {
            select.value = 'custom';
            customInput.value = data.name;
            customInput.style.display = 'block';
        }

        document.getElementById('payment_type').value = data.type;
        document.getElementById('account_name').value = data.account_name;
        document.getElementById('account_number').value = data.account_number || '';
        document.getElementById('payment_status').value = data.status || 'active';

        document.getElementById('form-actions-container').innerHTML = `
            <div class="d-flex gap-2">
                <button type="submit" name="update" id="submit-btn" class="btn btn-warning w-100 rounded-3 text-white">อัปเดตข้อมูล</button>
                <button type="button" class="btn btn-secondary rounded-3" onclick="resetPaymentForm()">ยกเลิก</button>
            </div>
        `;
    }

    function resetPaymentForm() {
        document.querySelectorAll('.payment-row').forEach(row => row.classList.remove('table-warning'));
        
        document.getElementById('form-title').innerHTML = '<i class="bi bi-plus-circle text-primary"></i> เพิ่มช่องทางใหม่';
        document.getElementById('payment_id').value = '';
        document.getElementById('payment-form').reset();
        
        const select = document.getElementById('bankSelect');
        if (select) {
            select.value = 'ธนาคารกสิกรไทย';
            toggleCustomBankName(select);
        }

        document.getElementById('form-actions-container').innerHTML = `
            <button type="submit" name="add" id="submit-btn" class="btn btn-dark w-100 rounded-3">บันทึกช่องทาง</button>
        `;
    }

    function submitPaymentForm(e) {
        e.preventDefault();
        const form = document.getElementById('payment-form');
        const submitBtn = document.getElementById('submit-btn');
        submitBtn.disabled = true;

        const isEdit = document.getElementById('payment_id').value !== '';
        const formData = new FormData(form);
        formData.append(isEdit ? 'update' : 'add', '1');
        formData.append('ajax', '1');
        formData.append('csrf_token', currentCsrfToken);

        fetch('admin_payments.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            if (data.status === 'success') {
                Toast.fire({
                    icon: 'success',
                    title: data.message
                });

                if (!isEdit) {
                    currentCsrfToken = data.csrf_token;
                    document.querySelectorAll('input[name="csrf_token"]').forEach(el => el.value = currentCsrfToken);
                }

                // Prepare table cell styling based on values
                let typeBadge = 'bg-light text-dark border';
                if(data.payment.type === 'promptpay') typeBadge = 'bg-info-subtle text-info border border-info-subtle';
                elseif(data.payment.type === 'cod') typeBadge = 'bg-warning-subtle text-warning border border-warning-subtle';

                const statusBadge = data.payment.status === 'inactive' ? 'bg-secondary' : 'bg-success';
                const statusText = data.payment.status === 'inactive' ? 'ปิดใช้งาน' : 'เปิดใช้งาน';

                const accNameText = data.payment.account_name ? escapeHtml(data.payment.account_name) : '-';
                const accNumText = data.payment.account_number ? escapeHtml(data.payment.account_number) : '-';

                if (isEdit) {
                    const row = document.getElementById('payment-row-' + data.payment.id);
                    if (row) {
                        row.querySelector('.payment-name-cell').innerText = data.payment.name;
                        row.querySelector('.payment-type-cell').innerHTML = `<span class="badge ${typeBadge}">${data.payment.type}</span>`;
                        row.querySelector('.payment-accname-cell').innerText = data.payment.account_name || '-';
                        row.querySelector('.payment-accnumber-cell').innerText = data.payment.account_number || '-';
                        row.querySelector('.payment-status-cell').innerHTML = `<span class="badge ${statusBadge}">${statusText}</span>`;
                        
                        const editBtn = row.querySelector('.edit-payment-btn');
                        if (editBtn) {
                            editBtn.setAttribute('onclick', `loadEditPayment(${JSON.stringify(data.payment)})`);
                        }
                    }
                    resetPaymentForm();
                } else {
                    const tbody = document.getElementById('payments-tbody');
                    const tr = document.createElement('tr');
                    tr.id = 'payment-row-' + data.payment.id;
                    tr.className = 'payment-row';
                    tr.innerHTML = `
                        <td class="fw-bold payment-name-cell">${escapeHtml(data.payment.name)}</td>
                        <td class="payment-type-cell"><span class="badge ${typeBadge}">${data.payment.type}</span></td>
                        <td class="payment-accname-cell">${accNameText}</td>
                        <td class="payment-accnumber-cell">${accNumText}</td>
                        <td class="payment-status-cell"><span class="badge ${statusBadge}">${statusText}</span></td>
                        <td class="text-end">
                            <button onclick='loadEditPayment(${JSON.stringify(data.payment)})' class="btn btn-light btn-sm text-primary rounded-circle shadow-sm me-1 edit-payment-btn" title="แก้ไข">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <button onclick="confirmDelete(${data.payment.id}, '${escapeHtmlString(data.payment.name)}', '${currentCsrfToken}')" class="btn btn-light btn-sm text-danger rounded-circle shadow-sm" title="ลบ">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                    resetPaymentForm();
                }
            } else {
                Toast.fire({
                    icon: 'error',
                    title: data.message || 'เกิดข้อผิดพลาด'
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
            title: 'ลบช่องทางชำระเงิน?',
            text: `ยืนยันการลบช่องทางชำระเงิน "${name}" ออกจากระบบ?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'ลบเลย',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`admin_payments.php?del=${id}&csrf_token=${token}&ajax=1`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Toast.fire({
                            icon: 'success',
                            title: data.message
                        });
                        const row = document.getElementById('payment-row-' + id);
                        if (row) {
                            row.classList.add('fade-out');
                            setTimeout(() => row.remove(), 300);
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
