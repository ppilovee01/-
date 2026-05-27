<?php
ob_start(); // เเธิด Buffer เธเนอเธเธัเธ Error เนทรเธ
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');

// เธิด Error เเธืเนอเนหเนสเนเธ JSON ได้สมเธูรณเน
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'เเธิดเธเนอผิดพลาด'];

if (!isset($_SESSION['user_id'])) {
    $response = ['status' => 'error', 'message' => 'เธรุณาเข้าสู่ระบบ'];
    ob_end_clean(); echo json_encode($response); exit();
}

$user_id = $_SESSION['user_id'];
$action = isset($_POST['action']) ? $_POST['action'] : '';

// --- 1. อัปവเธเนอมูลสเนวเธตัว ---
if ($action == 'update_profile') {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // เเธเนเธอีเมลเธเนำ
    $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' AND id != '$user_id'");
    if (mysqli_num_rows($check) > 0) {
        $response = ['status' => 'error', 'message' => 'อีเมลนี้มีเธูเนเนเธเนเธาเธแล้ว'];
    } else {
        $sql = "UPDATE users SET fullname = '$fullname', email = '$email' WHERE id = '$user_id'";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['fullname'] = $fullname;
            $response = ['status' => 'success', 'message' => 'บันทึกเธเนอมูลเรียเธรเนอย', 'fullname' => $fullname];
        } else {
            $response = ['status' => 'error', 'message' => 'บันทึกเธเนอมูลเนมเนสำเร็จ'];
        }
    }
}

// --- 2. เปลี่ยนรหัสเธเนาเธ ---
elseif ($action == 'change_password') {
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    $res = mysqli_query($conn, "SELECT password FROM users WHERE id = '$user_id'");
    $row = mysqli_fetch_assoc($res);

    if (!password_verify($old_pass, $row['password'])) {
        $response = ['status' => 'error', 'message' => 'รหัสเธเนาเธเดิมเนมเนถูเธตเนอเธ'];
    } elseif ($new_pass !== $confirm_pass) {
        $response = ['status' => 'error', 'message' => 'รหัสเธเนาเธเนหมเนเนมเนตรเธเธัเธ'];
    } elseif (strlen($new_pass) < 4) {
        $response = ['status' => 'error', 'message' => 'รหัสเธเนาเธตเนอเธมีอยเนาเธเธเนอย 4 ตัวอัเธษร'];
    } else {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET password = '$hash' WHERE id = '$user_id'");
        $response = ['status' => 'success', 'message' => 'เปลี่ยนรหัสเธเนาเธสำเร็จ!'];
    }
}

// --- 3. เเธิเนมที่อยูเน ---
elseif ($action == 'add_address') {
    $name = mysqli_real_escape_string($conn, $_POST['recipient_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $addr = mysqli_real_escape_string($conn, $_POST['address_line1']);
    $sub = mysqli_real_escape_string($conn, $_POST['subdistrict']);
    $dist = mysqli_real_escape_string($conn, $_POST['district']);
    $prov = mysqli_real_escape_string($conn, $_POST['province']);
    $zip = mysqli_real_escape_string($conn, $_POST['zipcode']);

    $sql = "INSERT INTO user_addresses (user_id, recipient_name, phone, address_line1, subdistrict, district, province, zipcode) 
            VALUES ('$user_id', '$name', '$phone', '$addr', '$sub', '$dist', '$prov', '$zip')";
    
    if (mysqli_query($conn, $sql)) {
        $new_id = mysqli_insert_id($conn);
        // สรเนาเธ HTML การเนดที่อยูเนเเธืเนอสเนเธกลับไปเนเธะ
        $html = '
        <div class="col-md-6 animate__animated animate__fadeIn" id="addr-'.$new_id.'">
            <div class="address-item h-100">
                <div class="fw-bold text-dark mb-1 fs-5">'.$name.'</div>
                <div class="text-muted small mb-2"><i class="bi bi-telephone"></i> '.$phone.'</div>
                <div class="small text-secondary" style="line-height: 1.5;">
                    '.$addr.'<br>
                    '.$sub.' '.$dist.'<br>
                    '.$prov.' '.$zip.'
                </div>
                <div class="btn-del-addr" onclick="deleteAddress('.$new_id.')">
                    <i class="bi bi-trash"></i>
                </div>
            </div>
        </div>';
        
        $response = ['status' => 'success', 'message' => 'เเธิเนมที่อยูเนเรียเธรเนอย', 'html' => $html];
    } else {
        $response = ['status' => 'error', 'message' => 'เเธิดเธเนอผิดพลาด'];
    }
}

// --- 4. ลเธที่อยูเน ---
elseif ($action == 'delete_address') {
    $addr_id = $_POST['address_id'];
    if(mysqli_query($conn, "DELETE FROM user_addresses WHERE id='$addr_id' AND user_id='$user_id'")) {
        $response = ['status' => 'success', 'message' => 'ลเธที่อยูเนแล้ว'];
    } else {
        $response = ['status' => 'error', 'message' => 'ลเธเนมเนสำเร็จ'];
    }
}

// ลเนาเธ Buffer เนละสเนเธ JSON
ob_end_clean();
echo json_encode($response);
exit();
?>


