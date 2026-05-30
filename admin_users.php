<?php
session_start();
include 'db.php';

// 1. ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// 2. เช็คสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

// --- Logic 1: เพิ่มสมาชิกใหม่ (Anti-F5 Fixed) ---
if (isset($_POST['add_user'])) {
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT); 
    $name = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $role = $_POST['role'];

    // เช็คซ้ำ
    $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$user' OR email='$email'");
    if(mysqli_num_rows($check) > 0) {
        $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>'ชื่อผู้ใช้ หรือ อีเมลนี้ มีคนใช้แล้ว!', 'icon'=>'error'];
    } else {
        $sql = "INSERT INTO users (username, password, fullname, email, role, created_at) VALUES ('$user', '$pass', '$name', '$email', '$role', NOW())";
        if(mysqli_query($conn, $sql)) {
            log_admin_action($conn, 'เพิ่มสมาชิก', "เพิ่มสมาชิกใหม่: Username = $user, ชื่อ = $name, อีเมล = $email, สิทธิ์ = $role");
            $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'เพิ่มสมาชิกใหม่เรียบร้อย', 'icon'=>'success'];
        } else {
            $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>mysqli_error($conn), 'icon'=>'error'];
        }
    }
    header("Location: admin_users.php"); exit();
}

// --- Logic 2: แก้ไขสมาชิก ---
if (isset($_POST['edit_user'])) {
    $id = intval($_POST['edit_id']);
    $name = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $role = $_POST['role'];
    $points = intval($_POST['points']);
    
    // ดึงคะแนนเดิมมาเปรียบเทียบ
    $old_p_q = mysqli_query($conn, "SELECT points FROM users WHERE id='$id'");
    $old_p_row = mysqli_fetch_assoc($old_p_q);
    $old_points = $old_p_row ? intval($old_p_row['points']) : 0;
    
    if (!empty($_POST['password'])) {
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql = "UPDATE users SET fullname='$name', email='$email', role='$role', password='$pass', points='$points' WHERE id='$id'";
    } else {
        $sql = "UPDATE users SET fullname='$name', email='$email', role='$role', points='$points' WHERE id='$id'";
    }

    if(mysqli_query($conn, $sql)) {
        log_admin_action($conn, 'แก้ไขสมาชิก', "แก้ไขข้อมูลสมาชิก ID #$id: ชื่อ = $name, อีเมล = $email, สิทธิ์ = $role, แต้มสะสม = $points" . (!empty($_POST['password']) ? " (เปลี่ยนรหัสผ่าน)" : ""));
        
        // บันทึกธุรกรรมประวัติแต้มสะสมหากแต้มมีการเปลี่ยนแปลง
        if ($points != $old_points) {
            $diff = $points - $old_points;
            $desc = "ผู้ดูแลระบบแก้ไขแต้มสะสมโดยตรง (จาก $old_points เป็น $points แต้ม)";
            mysqli_query($conn, "INSERT INTO point_history (user_id, points, description) VALUES ('$id', '$diff', '$desc')");
        }
        
        $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'อัปเดตข้อมูลเรียบร้อย', 'icon'=>'success'];
    } else {
        $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>mysqli_error($conn), 'icon'=>'error'];
    }
    header("Location: admin_users.php"); exit();
}

// --- Logic 3: ลบสมาชิก ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id == $_SESSION['user_id']) {
        $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>'คุณไม่สามารถลบตัวเองได้!', 'icon'=>'warning'];
    } else {
        $user_q = mysqli_query($conn, "SELECT fullname, username FROM users WHERE id = $id");
        $user_info = mysqli_fetch_assoc($user_q);
        $fullname = $user_info ? $user_info['fullname'] : "ไม่ทราบชื่อ";
        $username = $user_info ? $user_info['username'] : "ไม่ทราบ ID";
        
        $sql = "DELETE FROM users WHERE id = $id";
        if(mysqli_query($conn, $sql)) {
            log_admin_action($conn, 'ลบสมาชิก', "ลบสมาชิก ID #$id (Username: $username, ชื่อ: $fullname)");
            $_SESSION['swal'] = ['title'=>'สำเร็จ', 'text'=>'ลบสมาชิกเรียบร้อยแล้ว', 'icon'=>'success'];
        } else {
            $_SESSION['swal'] = ['title'=>'ผิดพลาด', 'text'=>'ลบไม่ได้ (อาจมีออเดอร์ค้างอยู่)', 'icon'=>'error'];
        }
    }
    header("Location: admin_users.php"); exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสมาชิก | Por Mae Bet Taled Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit'; background: #f8f9fa; }
        .table-card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.03); background: white; overflow: hidden; }
        .user-icon { width: 40px; height: 40px; background: #e9ecef; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #555; }
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
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
                <div>
                    <h2 class="fw-bold m-0">จัดการสมาชิก (Users)</h2>
                    <p class="text-muted small mb-0">เพิ่ม ลบ แก้ไข ข้อมูลสมาชิกและแอดมิน</p>
                    <button class="btn btn-gradient rounded-pill px-4 shadow-sm w-100 w-md-auto" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-person-plus-fill me-2"></i> เพิ่มสมาชิก
                </button>
                </div>
            </div>

            <div class="card table-card p-3">
                <div class="table-responsive">
                    <table class="table align-middle table-hover" style="min-width: 800px;">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>ชื่อผู้ใช้งาน</th>
                                <th>อีเมล</th>
                                <th>สถานะ</th>
                                <th>แต้มสะสม</th>
                                <th>วันที่สมัคร</th>
                                <th class="text-end pe-4">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $sql = "SELECT * FROM users ORDER BY id DESC";
                            $result = mysqli_query($conn, $sql);

                            while($row = mysqli_fetch_assoc($result)):
                                $role_badge = ($row['role'] == 'admin') ? '<span class="badge bg-dark">Admin</span>' : '<span class="badge bg-light text-dark border">User</span>';
                            ?>
                            <tr>
                                <td class="ps-4 text-muted">#<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-icon me-3"><i class="bi bi-person-fill"></i></div>
                                        <div>
                                            <div class="fw-bold"><?= $row['fullname'] ?></div>
                                            <div class="small text-muted">User: <?= $row['username'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= $row['email'] ?></td>
                                <td><?= $role_badge ?></td>
                                <td>🪙 <span class="fw-bold text-warning"><?= number_format($row['points'] ?? 0) ?></span> แต้ม</td>
                                <td class="text-muted small">
                                    <?= isset($row['created_at']) ? date('d/m/Y', strtotime($row['created_at'])) : '-' ?>
                                </td>
                                <td class="text-end pe-4">
                                    <button onclick='editUser(<?= json_encode($row) ?>)' class="btn btn-light text-primary btn-sm rounded-circle shadow-sm me-1" data-bs-toggle="modal" data-bs-target="#editUserModal">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    
                                    <?php if($row['id'] != $_SESSION['user_id']): ?>
                                    <button onclick="confirmBan(<?= $row['id'] ?>, '<?= $row['fullname'] ?>')" class="btn btn-light text-danger btn-sm rounded-circle shadow-sm">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                    <?php endif; ?>
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

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">เพิ่มสมาชิกใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="small text-muted">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Password</label>
                        <div class="input-group">
                            <input type="password" name="password" id="adminAddPass" class="form-control" required>
                            <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePasswordVisibility('adminAddPass', this)" style="background: white; border-color: #ced4da;">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">ชื่อ-นามสกุล</label>
                        <input type="text" name="fullname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Role (สิทธิ์การใช้งาน)</label>
                        <select name="role" class="form-select">
                            <option value="user">User (ลูกค้าทั่วไป)</option>
                            <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" name="add_user" class="btn btn-gradient w-100 rounded-pill">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">แก้ไขข้อมูลสมาชิก</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="small text-muted">Username (แก้ไขไม่ได้)</label>
                        <input type="text" id="edit_username" class="form-control bg-light" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">ชื่อ-นามสกุล</label>
                        <input type="text" name="fullname" id="edit_fullname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">เปลี่ยนรหัสผ่าน (ปล่อยว่างถ้าไม่เปลี่ยน)</label>
                        <div class="input-group">
                            <input type="password" name="password" id="adminEditPass" class="form-control" placeholder="*******">
                            <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePasswordVisibility('adminEditPass', this)" style="background: white; border-color: #ced4da;">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Role</label>
                        <select name="role" id="edit_role" class="form-select">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">แต้มสะสมสมาชิก</label>
                        <input type="number" name="points" id="edit_points" class="form-control" required min="0">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" name="edit_user" class="btn btn-warning w-100 rounded-pill text-white">อัปเดตข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editUser(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_username').value = data.username;
        document.getElementById('edit_fullname').value = data.fullname;
        document.getElementById('edit_email').value = data.email;
        document.getElementById('edit_role').value = data.role;
        document.getElementById('edit_points').value = data.points || 0;
    }

    function confirmBan(id, name) {
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: "ต้องการลบสมาชิก '" + name + "' หรือไม่?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'ลบเลย',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '?delete=' + id;
            }
        })
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
</body>
</html>


