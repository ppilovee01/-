<?php
ob_start();
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');

// Check Admin Role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    header("Location: index.php"); 
    exit(); 
}

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

$error_msg = "";
$success_msg = "";

if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $error_msg = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

if (isset($_POST['update_settings'])) {
    $auto_flash_sale = isset($_POST['auto_flash_sale']) ? 1 : 0;
    $auto_flash_type = mysqli_real_escape_string($conn, $_POST['auto_flash_type'] ?? 'static');
    $auto_flash_discount = intval($_POST['auto_flash_discount'] ?? 20);
    $auto_flash_min_discount = intval($_POST['auto_flash_min_discount'] ?? 10);
    $auto_flash_max_discount = intval($_POST['auto_flash_max_discount'] ?? 50);
    $auto_flash_selection_rule = mysqli_real_escape_string($conn, $_POST['auto_flash_selection_rule'] ?? 'random');
    $auto_flash_count = intval($_POST['auto_flash_count'] ?? 3);
    $auto_flash_duration = intval($_POST['auto_flash_duration'] ?? 2);
    $auto_flash_stock = intval($_POST['auto_flash_stock'] ?? 10);
    
    // ดึงค่าตั้งค่าเดิมมาตรวจสอบส่วนต่าง
    $settings_res = mysqli_query($conn, "SELECT auto_flash_sale, auto_flash_discount, auto_flash_duration, auto_flash_type, auto_flash_min_discount, auto_flash_max_discount, auto_flash_selection_rule, auto_flash_count, auto_flash_stock FROM shop_settings WHERE id = 1");
    $shop_s = mysqli_fetch_assoc($settings_res);
    
    $upd_s = mysqli_query($conn, "UPDATE shop_settings SET 
        auto_flash_sale = '$auto_flash_sale', 
        auto_flash_type = '$auto_flash_type',
        auto_flash_discount = '$auto_flash_discount', 
        auto_flash_min_discount = '$auto_flash_min_discount',
        auto_flash_max_discount = '$auto_flash_max_discount',
        auto_flash_selection_rule = '$auto_flash_selection_rule',
        auto_flash_count = '$auto_flash_count',
        auto_flash_duration = '$auto_flash_duration',
        auto_flash_stock = '$auto_flash_stock'
        WHERE id = 1");
    if ($upd_s) {
        log_admin_action($conn, 'ตั้งค่า Flash Sale อัตโนมัติ', [
            'title' => 'อัปเดตการตั้งค่าระบบ Flash Sale อัตโนมัติ',
            'changes' => [
                ['field' => 'เปิดใช้งานระบบอัตโนมัติ', 'old' => ($shop_s['auto_flash_sale'] ?? 0) ? 'เปิด' : 'ปิด', 'new' => $auto_flash_sale ? 'เปิด' : 'ปิด'],
                ['field' => 'ประเภทส่วนลด', 'old' => $shop_s['auto_flash_type'] ?? 'static', 'new' => $auto_flash_type],
                ['field' => 'ส่วนลดอัตโนมัติ (%)', 'old' => ($shop_s['auto_flash_discount'] ?? 20) . '%', 'new' => $auto_flash_discount . '%'],
                ['field' => 'ส่วนลดขั้นต่ำ Dynamic (%)', 'old' => ($shop_s['auto_flash_min_discount'] ?? 10) . '%', 'new' => $auto_flash_min_discount . '%'],
                ['field' => 'ส่วนลดขั้นสูง Dynamic (%)', 'old' => ($shop_s['auto_flash_max_discount'] ?? 50) . '%', 'new' => $auto_flash_max_discount . '%'],
                ['field' => 'กฎการเลือกสินค้า', 'old' => $shop_s['auto_flash_selection_rule'] ?? 'random', 'new' => $auto_flash_selection_rule],
                ['field' => 'จำนวนสินค้าต่อรอบ', 'old' => $shop_s['auto_flash_count'] ?? 3, 'new' => $auto_flash_count],
                ['field' => 'ระยะเวลารอบ (ชม.)', 'old' => ($shop_s['auto_flash_duration'] ?? 2) . ' ชม.', 'new' => $auto_flash_duration . ' ชม.'],
                ['field' => 'โควตาสินค้าอัตโนมัติ (ชิ้น)', 'old' => ($shop_s['auto_flash_stock'] ?? 10) . ' ชิ้น', 'new' => $auto_flash_stock . ' ชิ้น']
            ]
        ]);
        // Trigger generation check
        checkAndGenerateAutoFlashSale($conn);
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'บันทึกการตั้งค่าระบบอัตโนมัติสำเร็จ!']);
            exit();
        }
        $_SESSION['success_msg'] = "บันทึกการตั้งค่าระบบอัตโนมัติสำเร็จ!";
        header("Location: admin_flash_sale.php");
        exit();
    } else {
        error_log("[admin_flash_sale.php] update_settings error: " . mysqli_error($conn));
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => "บันทึกข้อมูลล้มเหลวเนื่องจากข้อผิดพลาดภายในระบบ"]);
            exit();
        }
        $error_msg = "บันทึกข้อมูลล้มเหลวเนื่องจากข้อผิดพลาดภายในระบบ";
    }
}

// --- Logic 1: Add Campaign ---
if (isset($_POST['add'])) {
    $product_id = intval($_POST['product_id']);
    $flash_price = floatval($_POST['flash_price']);
    $flash_stock = intval($_POST['flash_stock']);
    $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
    $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);
    
    // Check if start_time is before end_time
    if (strtotime($start_time) >= strtotime($end_time)) {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'เวลาเริ่มต้นต้องมาก่อนเวลาสิ้นสุด']);
            exit();
        }
        $error_msg = "เวลาเริ่มต้นต้องมาก่อนเวลาสิ้นสุด";
    } else {
        // Check for overlapping campaigns on same product
        $overlap = mysqli_query($conn, "SELECT id FROM flash_sales 
            WHERE product_id = '$product_id' 
            AND (
                ('$start_time' BETWEEN start_time AND end_time) OR 
                ('$end_time' BETWEEN start_time AND end_time) OR 
                (start_time BETWEEN '$start_time' AND '$end_time')
            )");
        if (mysqli_num_rows($overlap) > 0) {
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'สินค้านี้มีแคมเปญ Flash Sale ในช่วงเวลาดังกล่าวอยู่แล้ว']);
                exit();
            }
            $error_msg = "สินค้านี้มีแคมเปญ Flash Sale ในช่วงเวลาดังกล่าวอยู่แล้ว";
        } else {
            $sql = "INSERT INTO flash_sales (product_id, flash_price, flash_stock, flash_sold, start_time, end_time) 
                    VALUES ('$product_id', '$flash_price', '$flash_stock', 0, '$start_time', '$end_time')";
            if (mysqli_query($conn, $sql)) {
                $p_q = mysqli_query($conn, "SELECT name FROM products WHERE id = '$product_id'");
                $p_name = mysqli_fetch_assoc($p_q)['name'] ?? 'ไม่พบชื่อสินค้า';
                
                log_admin_action($conn, 'สร้างแคมเปญ Flash Sale', [
                    'title' => "สร้างแคมเปญ Flash Sale สำหรับสินค้า '$p_name'",
                    'changes' => [
                        ['field' => 'สินค้า', 'old' => '-', 'new' => "$p_name (รหัส #$product_id)"],
                        ['field' => 'ราคาพิเศษ', 'old' => '-', 'new' => "฿" . number_format($flash_price, 2)],
                        ['field' => 'โควตาจัดสรร', 'old' => '-', 'new' => number_format($flash_stock) . " ชิ้น"],
                        ['field' => 'เวลาเริ่มต้น', 'old' => '-', 'new' => $start_time],
                        ['field' => 'เวลาสิ้นสุด', 'old' => '-', 'new' => $end_time]
                    ]
                ]);
                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'message' => 'สร้างแคมเปญ Flash Sale สำเร็จ!']);
                    exit();
                }
                $_SESSION['success_msg'] = "สร้างแคมเปญ Flash Sale สำเร็จ!";
                header("Location: admin_flash_sale.php");
                exit();
            } else {
                error_log("[admin_flash_sale.php] add_campaign error: " . mysqli_error($conn));
                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => "ไม่สามารถบันทึกข้อมูลได้เนื่องจากข้อผิดพลาดภายในระบบ"]);
                    exit();
                }
                $error_msg = "ไม่สามารถบันทึกข้อมูลได้เนื่องจากข้อผิดพลาดภายในระบบ";
            }
        }
    }
}

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
    $fs_q = mysqli_query($conn, "SELECT fs.product_id, p.name FROM flash_sales fs JOIN products p ON fs.product_id = p.id WHERE fs.id = $id");
    $fs_info = mysqli_fetch_assoc($fs_q);
    $p_name = $fs_info['name'] ?? 'ไม่พบชื่อสินค้า';
    $pid = $fs_info['product_id'] ?? 0;
    
    if (mysqli_query($conn, "DELETE FROM flash_sales WHERE id = $id")) {
        log_admin_action($conn, 'ลบแคมเปญ Flash Sale', [
            'title' => "ลบแคมเปญ Flash Sale ของสินค้า '$p_name' (รหัสแคมเปญ #$id)",
            'sections' => [
                [
                    'title' => 'รายละเอียดแคมเปญที่ถูกลบ',
                    'items' => [
                        "รหัสแคมเปญ: #$id",
                        "สินค้า: $p_name (รหัส #$pid)"
                    ]
                ]
            ]
        ]);
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'ลบแคมเปญเรียบร้อยแล้ว']);
            exit();
        }
        $_SESSION['success_msg'] = "ลบแคมเปญเรียบร้อยแล้ว";
    } else {
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'ลบแคมเปญไม่สำเร็จ']);
            exit();
        }
        $_SESSION['error_msg'] = "ลบแคมเปญไม่สำเร็จ";
    }
    header("Location: admin_flash_sale.php");
    exit();
}

if (isset($_GET['cancel_campaign'])) {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้องหรือหมดเวลาเซสชัน (Invalid CSRF Token)']);
            exit();
        }
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
    $id = intval($_GET['cancel_campaign']);
    $fs_q = mysqli_query($conn, "SELECT fs.product_id, p.name FROM flash_sales fs JOIN products p ON fs.product_id = p.id WHERE fs.id = $id");
    $fs_info = mysqli_fetch_assoc($fs_q);
    $p_name = $fs_info['name'] ?? 'ไม่พบชื่อสินค้า';
    $pid = $fs_info['product_id'] ?? 0;
    
    // Set end_time to current time
    if (mysqli_query($conn, "UPDATE flash_sales SET end_time = NOW() WHERE id = $id")) {
        log_admin_action($conn, 'บังคับปิดแคมเปญ Flash Sale', [
            'title' => "บังคับปิดแคมเปญ Flash Sale ทันที (รหัสแคมเปญ #$id)",
            'sections' => [
                [
                    'title' => 'รายละเอียดการดำเนินการ',
                    'items' => [
                        "รหัสแคมเปญ: #$id",
                        "สินค้า: $p_name (รหัส #$pid)",
                        "ผลลัพธ์: แคมเปญถูกบังคับสิ้นสุดทันทีโดยผู้ดูแลระบบ"
                    ]
                ]
            ]
        ]);
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'สิ้นสุดแคมเปญดังกล่าวทันทีเรียบร้อยแล้ว']);
            exit();
        }
        $_SESSION['success_msg'] = "สิ้นสุดแคมเปญดังกล่าวทันทีเรียบร้อยแล้ว";
    } else {
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถปิดแคมเปญได้']);
            exit();
        }
        $_SESSION['error_msg'] = "ไม่สามารถปิดแคมเปญได้";
    }
    header("Location: admin_flash_sale.php");
    exit();
}

// --- Logic 4: Fetch Edit Data ---
if (isset($_GET['get_edit'])) {
    $id = intval($_GET['get_edit']);
    $res = mysqli_query($conn, "SELECT * FROM flash_sales WHERE id = $id");
    $data = mysqli_fetch_assoc($res);
    header('Content-Type: application/json');
    if ($data) {
        echo json_encode(['status' => 'success', 'data' => $data]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลแคมเปญ']);
    }
    exit();
}

$edit_data = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $res = mysqli_query($conn, "SELECT * FROM flash_sales WHERE id = $id");
    $edit_data = mysqli_fetch_assoc($res);
}

// --- Logic 5: Update ---
if (isset($_POST['update'])) {
    $id = intval($_POST['id']);
    $product_id = intval($_POST['product_id']);
    $flash_price = floatval($_POST['flash_price']);
    $flash_stock = intval($_POST['flash_stock']);
    $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
    $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);
    
    if (strtotime($start_time) >= strtotime($end_time)) {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'เวลาเริ่มต้นต้องมาก่อนเวลาสิ้นสุด']);
            exit();
        }
        $error_msg = "เวลาเริ่มต้นต้องมาก่อนเวลาสิ้นสุด";
    } else {
        // ดึงข้อมูลเก่าเพื่อนำมาเปรียบเทียบในประวัติการแก้ไข (Diff Log)
        $old_q = mysqli_query($conn, "SELECT fs.*, p.name as product_name FROM flash_sales fs LEFT JOIN products p ON fs.product_id = p.id WHERE fs.id = $id");
        $old_data = mysqli_fetch_assoc($old_q);

        // Check overlap excluding current campaign
        $overlap = mysqli_query($conn, "SELECT id FROM flash_sales 
            WHERE product_id = '$product_id' 
            AND id != '$id'
            AND (
                ('$start_time' BETWEEN start_time AND end_time) OR 
                ('$end_time' BETWEEN start_time AND end_time) OR 
                (start_time BETWEEN '$start_time' AND '$end_time')
            )");
        if (mysqli_num_rows($overlap) > 0) {
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'สินค้านี้มีแคมเปญ Flash Sale ในช่วงเวลาดังกล่าวอยู่แล้ว']);
                exit();
            }
            $error_msg = "สินค้านี้มีแคมเปญ Flash Sale ในช่วงเวลาดังกล่าวอยู่แล้ว";
        } else {
            $sql = "UPDATE flash_sales SET 
                    product_id = '$product_id', 
                    flash_price = '$flash_price', 
                    flash_stock = '$flash_stock', 
                    start_time = '$start_time', 
                    end_time = '$end_time' 
                    WHERE id = $id";
            if (mysqli_query($conn, $sql)) {
                $p_q = mysqli_query($conn, "SELECT name FROM products WHERE id = '$product_id'");
                $p_name = mysqli_fetch_assoc($p_q)['name'] ?? 'ไม่พบชื่อสินค้า';
                
                // คำนวณส่วนต่างความเปลี่ยนแปลง (Diffs)
                $diff = [];
                if ($old_data) {
                    if (intval($old_data['product_id']) !== $product_id) {
                        $diff['สินค้าในแคมเปญ'] = [$old_data['product_name'] . " (รหัส #" . $old_data['product_id'] . ")", $p_name . " (รหัส #" . $product_id . ")"];
                    }
                    if (floatval($old_data['flash_price']) !== $flash_price) {
                        $diff['ราคาพิเศษ (Flash Price)'] = ["฿" . number_format($old_data['flash_price'], 2), "฿" . number_format($flash_price, 2)];
                    }
                    if (intval($old_data['flash_stock']) !== $flash_stock) {
                        $diff['โควตาสินค้า (Flash Stock)'] = [intval($old_data['flash_stock']) . " ชิ้น", $flash_stock . " ชิ้น"];
                    }
                    if ($old_data['start_time'] !== $start_time) {
                        $diff['เวลาเริ่มต้น'] = [$old_data['start_time'], $start_time];
                    }
                    if ($old_data['end_time'] !== $end_time) {
                        $diff['เวลาสิ้นสุด'] = [$old_data['end_time'], $end_time];
                    }
                }

                log_admin_action($conn, 'แก้ไขแคมเปญ Flash Sale', [
                    'title' => "แก้ไขแคมเปญ Flash Sale (รหัสแคมเปญ #$id)",
                    'diff' => $diff,
                    'sections' => [
                        [
                            'title' => 'รายละเอียดแคมเปญหลังแก้ไข',
                            'items' => [
                                "รหัสแคมเปญ: #$id",
                                "สินค้า: $p_name (รหัสสินค้า #$product_id)",
                                "ราคาพิเศษ: ฿" . number_format($flash_price, 2),
                                "โควตา: $flash_stock ชิ้น",
                                "ระยะเวลาแคมเปญ: $start_time ถึง $end_time"
                            ]
                        ]
                    ]
                ]);

                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'message' => 'อัปเดตแคมเปญเรียบร้อย!']);
                    exit();
                }
                $_SESSION['success_msg'] = "อัปเดตแคมเปญเรียบร้อย!";
                header("Location: admin_flash_sale.php");
                exit();
            } else {
                error_log("[admin_flash_sale.php] edit_campaign error: " . mysqli_error($conn));
                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => "อัปเดตแคมเปญล้มเหลวเนื่องจากข้อผิดพลาดภายในระบบ"]);
                    exit();
                }
                $error_msg = "อัปเดตแคมเปญล้มเหลวเนื่องจากข้อผิดพลาดภายในระบบ";
            }
        }
    }
}

// Fetch all products for dropdown
$products_list = [];
$p_res = mysqli_query($conn, "SELECT id, name, price FROM products ORDER BY name ASC");
while ($p = mysqli_fetch_assoc($p_res)) {
    $products_list[] = $p;
}

// Fetch shop settings for auto flash sale
$settings_res = mysqli_query($conn, "SELECT auto_flash_sale, auto_flash_discount, auto_flash_duration, auto_flash_type, auto_flash_min_discount, auto_flash_max_discount, auto_flash_selection_rule, auto_flash_count, auto_flash_stock FROM shop_settings WHERE id = 1");
$shop_s = mysqli_fetch_assoc($settings_res);

// Fetch flash sale campaigns (พร้อมระบบแบ่งหน้า)
$limit = isset($_GET['limit']) ? max(10, min(100, intval($_GET['limit']))) : 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM flash_sales fs JOIN products p ON fs.product_id = p.id");
$total_rows = mysqli_fetch_assoc($count_query)['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);
if ($total_pages > 0 && $page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;

$campaigns = [];
$sql_c = "SELECT fs.*, p.name as product_name, p.image as product_image, p.price as original_price 
          FROM flash_sales fs 
          JOIN products p ON fs.product_id = p.id 
          ORDER BY fs.id DESC LIMIT $limit OFFSET $offset";
$c_res = mysqli_query($conn, $sql_c);
while ($c = mysqli_fetch_assoc($c_res)) {
    $campaigns[] = $c;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการ Flash Sale | Por Mae Bet Taled Admin</title>
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit'; background: #f8f9fa; }
        .text-blue { color: #7FB5FF !important; }
        .bg-blue { background-color: #7FB5FF !important; }
        .btn-blue { background: #7FB5FF; color: white; border: none; border-radius: 12px; font-weight: 500; transition: all 0.3s; }
        .btn-blue:hover { background: #5c9dfc; color: white; box-shadow: 0 4px 15px rgba(127, 181, 255, 0.4); }
        .card-modern { background: white; border: none; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); }
        .flash-badge { font-size: 0.75rem; font-weight: 600; padding: 5px 12px; border-radius: 50px; display: inline-block; white-space: nowrap; text-align: center; min-width: 80px; }
        .badge-active { background: #d1e7dd; color: #0f5132; }
        .badge-scheduled { background: #fff3cd; color: #664d03; }
        .badge-expired { background: #f8d7da; color: #842029; }
        .progress-bar-flash { height: 8px; border-radius: 50px; background: linear-gradient(90deg, #AEE2FF, #7FB5FF); }
        .product-thumbnail { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; }
        .campaign-row { transition: all 0.3s ease; }
        @media (min-width: 1200px) {
            .sticky-column-wrapper {
                position: sticky;
                top: 20px;
                max-height: calc(100vh - 40px);
                overflow-y: auto;
                padding-right: 6px;
                z-index: 10;
            }
            /* Custom thin scrollbar for elegant UI */
            .sticky-column-wrapper::-webkit-scrollbar {
                width: 5px;
            }
            .sticky-column-wrapper::-webkit-scrollbar-track {
                background: transparent;
            }
            .sticky-column-wrapper::-webkit-scrollbar-thumb {
                background: rgba(127, 181, 255, 0.4);
                border-radius: 10px;
            }
            .sticky-column-wrapper::-webkit-scrollbar-thumb:hover {
                background: rgba(127, 181, 255, 0.7);
            }
        }

        /* Mobile Responsive Overrides */
        @media (max-width: 767.98px) {
            .card-modern {
                padding: 15px !important;
            }
            .card-modern-mobile {
                background: #ffffff !important;
                border: 1px solid rgba(226, 232, 240, 0.8) !important;
                border-radius: 16px !important;
                box-shadow: 0 8px 25px rgba(127, 181, 255, 0.04) !important;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                position: relative !important;
                overflow: hidden !important;
                border-left: 5px solid #7FB5FF !important;
            }
            .card-modern-mobile:hover, .card-modern-mobile:active {
                transform: translateY(-2px);
                box-shadow: 0 12px 30px rgba(127, 181, 255, 0.1) !important;
                border-color: rgba(127, 181, 255, 0.3) !important;
            }
            .flash-badge {
                min-width: 70px !important;
                padding: 4px 8px !important;
                font-size: 0.7rem !important;
            }
            .card-modern-mobile .btn {
                border-radius: 10px !important;
                font-weight: 500;
                padding: 5px 10px;
                font-size: 0.72rem;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Menu -->
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

        <!-- Main Content -->
        <div class="col-md-10 p-4 p-md-5">
            <h2 class="fw-bold mb-4">จัดการระบบ Flash Sale <span class="text-blue">⚡</span></h2>
            
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger border-0 rounded-3 shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error_msg ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success border-0 rounded-3 shadow-sm mb-4"><i class="bi bi-check-circle-fill me-2"></i><?= $success_msg ?></div>
            <?php endif; ?>

            <div class="row">
                <!-- Left: Form -->
                <div class="col-xl-4 mb-4">
                    <div class="sticky-column-wrapper">
                        <!-- Create/Edit Campaign Card -->
                        <!-- Create/Edit Campaign Card -->
                        <div class="card-modern p-4">
                            <h5 class="fw-bold mb-4" id="form-title">
                                <?php if ($edit_data): ?>
                                    <i class="bi bi-pencil-square text-warning"></i> แก้ไขแคมเปญ Flash Sale
                                <?php else: ?>
                                    <i class="bi bi-lightning-charge-fill text-blue"></i> สร้างแคมเปญใหม่
                                <?php endif; ?>
                            </h5>
                            
                            <form id="campaign-form" method="POST" action="admin_flash_sale.php" onsubmit="submitCampaignForm(event)">
                                <?= get_csrf_input() ?>
                                <input type="hidden" name="id" id="campaign-id" value="<?= $edit_data ? $edit_data['id'] : '' ?>">

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-muted">เลือกสินค้า</label>
                                    <select class="form-select rounded-3" name="product_id" required>
                                        <option value="">-- เลือกสินค้า --</option>
                                        <?php foreach ($products_list as $prod): ?>
                                            <option value="<?= $prod['id'] ?>" <?= ($edit_data && $edit_data['product_id'] == $prod['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($prod['name']) ?> (ปกติ ฿<?= number_format($prod['price']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-muted">ราคา Flash Sale (บาท)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-muted">฿</span>
                                        <input type="number" step="0.01" class="form-control" name="flash_price" value="<?= $edit_data ? $edit_data['flash_price'] : '' ?>" required min="0">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-muted">โควตาจำนวน (ชิ้น)</label>
                                    <input type="number" class="form-control" name="flash_stock" value="<?= $edit_data ? $edit_data['flash_stock'] : '' ?>" required min="1">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-muted">เวลาเริ่มต้น</label>
                                    <input type="datetime-local" class="form-control" name="start_time" value="<?= $edit_data ? date('Y-m-d\TH:i', strtotime($edit_data['start_time'])) : '' ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-muted">เวลาสิ้นสุด</label>
                                    <input type="datetime-local" class="form-control" name="end_time" value="<?= $edit_data ? date('Y-m-d\TH:i', strtotime($edit_data['end_time'])) : '' ?>" required>
                                </div>

                                <?php if ($edit_data): ?>
                                    <button type="submit" name="update" id="submit-btn" class="btn btn-warning w-100 rounded-3 py-2 text-white fw-bold">อัปเดตข้อมูล</button>
                                    <button type="button" id="cancel-edit-btn" class="btn btn-outline-secondary w-100 rounded-3 py-2 mt-2" onclick="exitEditMode()">ยกเลิกแก้ไข</button>
                                <?php else: ?>
                                    <button type="submit" name="add" id="submit-btn" class="btn btn-blue w-100 rounded-3 py-2 fw-bold">สร้างแคมเปญ</button>
                                    <button type="button" id="cancel-edit-btn" class="btn btn-outline-secondary w-100 rounded-3 py-2 mt-2" style="display: none;" onclick="exitEditMode()">ยกเลิกแก้ไข</button>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- Auto Flash Settings Card -->
                        <div class="card-modern p-4 mt-4">
                            <h5 class="fw-bold mb-3"><i class="bi bi-robot text-blue"></i> ระบบรันแคมเปญอัตโนมัติ</h5>
                            <p class="text-muted small mb-3">เมื่อเปิดใช้งาน ระบบจะคำนวณและสร้างคิวรอบแคมเปญ Flash Sale ล่วงหน้าอัตโนมัติ โดยรักษาโควตาจำนวนสินค้าต่อรอบ</p>
                            
                            <form id="settings-form" method="POST" action="admin_flash_sale.php" onsubmit="submitSettingsForm(event)">
                                <?= get_csrf_input() ?>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="auto_flash_sale" id="autoFlashCheck" value="1" <?= (isset($shop_s['auto_flash_sale']) && $shop_s['auto_flash_sale'] == 1) ? 'checked' : '' ?> style="cursor: pointer; width: 2.2em; height: 1.1em;">
                                    <label class="form-check-label fw-bold text-dark small ms-2" for="autoFlashCheck" style="cursor: pointer;">เปิดใช้งานระบบอัตโนมัติ</label>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-muted">รูปแบบการคำนวณส่วนลด</label>
                                    <select class="form-select form-select-sm" name="auto_flash_type" id="autoFlashTypeSelect" onchange="toggleDiscountFields()" required>
                                        <option value="static" <?= (isset($shop_s['auto_flash_type']) && $shop_s['auto_flash_type'] === 'static') ? 'selected' : '' ?>>ส่วนลดคงที่ (Static Discount)</option>
                                        <option value="dynamic" <?= (isset($shop_s['auto_flash_type']) && $shop_s['auto_flash_type'] === 'dynamic') ? 'selected' : '' ?>>ปรับตามความนิยมอัตโนมัติ (Dynamic Discount)</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3" id="static-discount-group" <?= (isset($shop_s['auto_flash_type']) && $shop_s['auto_flash_type'] === 'dynamic') ? 'style="display: none;"' : '' ?>>
                                    <label class="form-label fw-semibold small text-muted">ส่วนลดอัตโนมัติ (%)</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" class="form-control" name="auto_flash_discount" value="<?= isset($shop_s['auto_flash_discount']) ? $shop_s['auto_flash_discount'] : '20' ?>" min="5" max="90">
                                        <span class="input-group-text bg-light text-muted">%</span>
                                    </div>
                                    <span class="text-muted" style="font-size: 0.75rem;">ส่วนลดคงที่ของสินค้าทุกรายการ (5% - 90%)</span>
                                </div>

                                <div id="dynamic-discount-group" <?= (isset($shop_s['auto_flash_type']) && $shop_s['auto_flash_type'] === 'dynamic') ? '' : 'style="display: none;"' ?>>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label fw-semibold small text-muted">ลดขั้นต่ำ (%)</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" class="form-control" name="auto_flash_min_discount" value="<?= isset($shop_s['auto_flash_min_discount']) ? $shop_s['auto_flash_min_discount'] : '10' ?>" min="5" max="90">
                                                <span class="input-group-text bg-light text-muted">%</span>
                                            </div>
                                            <span class="text-muted" style="font-size: 0.7rem;">สำหรับสินค้าขายดีที่สุด</span>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label fw-semibold small text-muted">ลดสูงสุด (%)</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" class="form-control" name="auto_flash_max_discount" value="<?= isset($shop_s['auto_flash_max_discount']) ? $shop_s['auto_flash_max_discount'] : '50' ?>" min="5" max="90">
                                                <span class="input-group-text bg-light text-muted">%</span>
                                            </div>
                                            <span class="text-muted" style="font-size: 0.7rem;">สำหรับสินค้าขายช้าที่สุด</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-muted">เกณฑ์การคัดเลือกสินค้า</label>
                                    <select class="form-select form-select-sm" name="auto_flash_selection_rule" required>
                                        <option value="random" <?= (isset($shop_s['auto_flash_selection_rule']) && $shop_s['auto_flash_selection_rule'] === 'random') ? 'selected' : '' ?>>สุ่มเลือกสินค้า (Random)</option>
                                        <option value="slow_moving" <?= (isset($shop_s['auto_flash_selection_rule']) && $shop_s['auto_flash_selection_rule'] === 'slow_moving') ? 'selected' : '' ?>>สินค้าขายไม่ดีก่อน (Slow-moving first)</option>
                                        <option value="popular" <?= (isset($shop_s['auto_flash_selection_rule']) && $shop_s['auto_flash_selection_rule'] === 'popular') ? 'selected' : '' ?>>สินค้าขายดีก่อน (Popular first)</option>
                                        <option value="high_stock" <?= (isset($shop_s['auto_flash_selection_rule']) && $shop_s['auto_flash_selection_rule'] === 'high_stock') ? 'selected' : '' ?>>สินค้าสต็อกเยอะสุดก่อน (High stock first)</option>
                                    </select>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small text-muted">จำนวนสินค้าต่อรอบ</label>
                                        <input type="number" class="form-control form-control-sm" name="auto_flash_count" value="<?= isset($shop_s['auto_flash_count']) ? $shop_s['auto_flash_count'] : '3' ?>" required min="1" max="8">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small text-muted">ระยะเวลารอบ (ชม.)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" class="form-control" name="auto_flash_duration" value="<?= isset($shop_s['auto_flash_duration']) ? $shop_s['auto_flash_duration'] : '2' ?>" required min="1" max="24">
                                            <span class="input-group-text bg-light text-muted">ชม.</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-muted">โควตาสินค้าอัตโนมัติ (ชิ้นสูงสุด)</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" class="form-control" name="auto_flash_stock" value="<?= isset($shop_s['auto_flash_stock']) ? $shop_s['auto_flash_stock'] : '10' ?>" required min="1" max="100">
                                        <span class="input-group-text bg-light text-muted">ชิ้น</span>
                                    </div>
                                    <div class="form-text" style="font-size: 0.75rem;">ขีดจำกัดสูงสุดจำนวนสินค้าที่จะดึงมาลดราคาต่อแคมเปญ (ดึงเท่าที่มีจริงในระบบแต่ไม่เกินจำนวนนี้)</div>
                                </div>
                                
                                <button type="submit" name="update_settings" class="btn btn-blue w-100 btn-sm py-2 fw-bold">บันทึกการตั้งค่าออโต้</button>
                            </form>
                            
                            <div class="mt-3 p-3 bg-light rounded-3 border-0 small text-muted">
                                <div class="fw-bold text-dark mb-2 d-flex align-items-center gap-1">
                                    <i class="bi bi-info-circle-fill text-blue"></i>
                                    <span>เกณฑ์การเลือกสินค้าอัตโนมัติ:</span>
                                </div>
                                <ul class="ps-3 mb-0" style="font-size: 0.8rem; line-height: 1.5;">
                                    <li>เลือกสินค้าที่มีสต็อกมากกว่า 5 ชิ้นเป็นลำดับแรก</li>
                                    <li>ต้องเป็นสินค้าที่ยังไม่มีแคมเปญ Flash Sale อื่นที่ยังไม่สิ้นสุดครอบคลุมอยู่</li>
                                    <li>หากไม่มีสินค้าตรงตามเงื่อนไขด้านบน จะสุ่มเลือกจากสินค้าใดก็ได้ที่มีสต็อก > 0 และไม่มีแคมเปญทับซ้อน</li>
                                    <li>โควตาสินค้าอัตโนมัติ คิดตามจำนวนสูงสุดที่ตั้งไว้ (ไม่เกินจำนวนสต็อกที่มีอยู่จริง)</li>
                                    <li>ส่วนลดจะอิงตามร้อยละที่ตั้งค่าไว้ข้างต้น</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: List Table -->
                <div class="col-xl-8">
                    <div class="card-modern p-4">
                        <h5 class="fw-bold mb-4"><i class="bi bi-list-stars text-blue"></i> รายการแคมเปญทั้งหมด</h5>
                        
                        <!-- Desktop View (Table) -->
                        <div class="table-responsive d-none d-md-block">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr class="text-muted small">
                                        <th>สินค้า</th>
                                        <th class="text-center">ราคาพิเศษ</th>
                                        <th class="text-center">ความคืบหน้ายอดขาย</th>
                                        <th>ช่วงเวลาแคมเปญ</th>
                                        <th class="text-center">สถานะ</th>
                                        <th class="text-center">การจัดการ</th>
                                    </tr>
                                </thead>
                                <tbody id="campaigns-tbody">
                                    <?php 
                                    $ajax_fetch = isset($_GET['ajax_fetch']);
                                    if ($ajax_fetch) ob_start();
                                    ?>
                                    <?php if (empty($campaigns)): ?>
                                        <tr id="no-campaigns-placeholder">
                                            <td colspan="6" class="text-center py-4 text-muted">ไม่พบข้อมูลแคมเปญ Flash Sale</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($campaigns as $camp): 
                                            $now = time();
                                            $start = strtotime($camp['start_time']);
                                            $end = strtotime($camp['end_time']);
                                            
                                            $status_text = "";
                                            $status_class = "";
                                            $is_active = false;
                                            
                                            if ($now < $start) {
                                                $status_text = "Waiting";
                                                $status_class = "badge-scheduled";
                                            } elseif ($now > $end) {
                                                $status_text = "End";
                                                $status_class = "badge-expired";
                                            } elseif ($camp['flash_sold'] >= $camp['flash_stock']) {
                                                $status_text = "Sold Out";
                                                $status_class = "badge-expired";
                                            } else {
                                                $status_text = "Run";
                                                $status_class = "badge-active";
                                                $is_active = true;
                                            }
                                            
                                            $pct = $camp['flash_stock'] > 0 ? ($camp['flash_sold'] / $camp['flash_stock']) * 100 : 0;
                                            if ($pct > 100) $pct = 100;
                                        ?>
                                            <tr id="campaign-row-<?= $camp['id'] ?>" class="campaign-row">
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="<?= htmlspecialchars($camp['product_image']) ?>" class="product-thumbnail">
                                                        <div>
                                                            <div class="fw-bold text-dark small"><?= htmlspecialchars($camp['product_name']) ?></div>
                                                            <small class="text-muted">ปกติ ฿<?= number_format($camp['original_price']) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <strong class="text-danger">฿<?= number_format($camp['flash_price'], 2) ?></strong>
                                                </td>
                                                <td>
                                                    <div class="small text-muted mb-1 d-flex justify-content-between">
                                                        <span>ขายแล้ว <?= $camp['flash_sold'] ?>/<?= $camp['flash_stock'] ?> ชิ้น</span>
                                                        <span><?= round($pct) ?>%</span>
                                                    </div>
                                                    <div class="progress" style="height: 6px;">
                                                        <div class="progress-bar bg-blue" role="progressbar" style="width: <?= $pct ?>%"></div>
                                                    </div>
                                                </td>
                                                <td style="font-size: 0.85rem;">
                                                    <div><i class="bi bi-play-circle-fill text-success me-1"></i><?= date('d/m/Y H:i', $start) ?></div>
                                                    <div class="mt-1"><i class="bi bi-stop-circle-fill text-danger me-1"></i><?= date('d/m/Y H:i', $end) ?></div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="flash-badge <?= $status_class ?>"><?= $status_text ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <?php if ($is_active): ?>
                                                            <button onclick="cancelCampaign(<?= $camp['id'] ?>, '<?= get_csrf_token() ?>')" class="btn btn-outline-danger btn-sm rounded-3 px-2 cancel-btn" title="จบแคมเปญทันที">
                                                                <i class="bi bi-stop-fill"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button onclick="loadEditCampaign(<?= $camp['id'] ?>)" class="btn btn-light btn-sm rounded-3 text-warning border" title="แก้ไข">
                                                            <i class="bi bi-pencil-fill"></i>
                                                        </button>
                                                        <button onclick="deleteCampaign(<?= $camp['id'] ?>, '<?= get_csrf_token() ?>')" class="btn btn-light btn-sm rounded-3 text-danger border" title="ลบ">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile View (Cards List) -->
                        <div class="d-md-none mt-2" id="campaigns-mobile-list">
                            <?php
                            $ajax_fetch_mob = isset($_GET['ajax_fetch']);
                            if ($ajax_fetch_mob) {
                                $desktop_html = ob_get_clean();
                                ob_start();
                            }
                            ?>
                            <?php if (empty($campaigns)): ?>
                                <div class="text-center py-4 text-muted small" id="no-campaigns-placeholder-mob">ไม่พบข้อมูลแคมเปญ Flash Sale</div>
                            <?php else: ?>
                                <?php foreach ($campaigns as $camp): 
                                    $now = time();
                                    $start = strtotime($camp['start_time']);
                                    $end = strtotime($camp['end_time']);
                                    
                                    $status_text = "";
                                    $status_class = "";
                                    $is_active = false;
                                    
                                    if ($now < $start) {
                                        $status_text = "Waiting";
                                        $status_class = "badge-scheduled";
                                    } elseif ($now > $end) {
                                        $status_text = "End";
                                        $status_class = "badge-expired";
                                    } elseif ($camp['flash_sold'] >= $camp['flash_stock']) {
                                        $status_text = "Sold Out";
                                        $status_class = "badge-expired";
                                    } else {
                                        $status_text = "Run";
                                        $status_class = "badge-active";
                                        $is_active = true;
                                    }
                                    
                                    $pct = $camp['flash_stock'] > 0 ? ($camp['flash_sold'] / $camp['flash_stock']) * 100 : 0;
                                    if ($pct > 100) $pct = 100;
                                ?>
                                    <div class="card-modern-mobile p-3 mb-3 text-start animate__animated animate__fadeIn position-relative" id="campaign-mob-card-<?= $camp['id'] ?>">
                                        <!-- Status Badge (Floating top-right) -->
                                        <div class="position-absolute" style="top: 15px; right: 15px; z-index: 2;">
                                            <span class="flash-badge <?= $status_class ?>"><?= $status_text ?></span>
                                        </div>

                                        <div class="d-flex align-items-start gap-3 mb-2" style="padding-right: 85px;">
                                            <img src="<?= htmlspecialchars($camp['product_image']) ?>" class="product-thumbnail" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px; flex-shrink: 0;">
                                            <div class="flex-grow-1 min-w-0">
                                                <div class="fw-bold text-dark lh-sm" style="font-size: 0.9rem; word-break: break-word; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; min-height: 2.4em; padding-right: 5px;"><?= htmlspecialchars($camp['product_name']) ?></div>
                                                <div class="d-flex align-items-center gap-2 mt-1">
                                                    <small class="text-muted text-decoration-line-through">฿<?= number_format($camp['original_price']) ?></small>
                                                    <strong class="text-danger">฿<?= number_format($camp['flash_price'], 2) ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="small text-muted mb-1 d-flex justify-content-between" style="font-size: 0.8rem;">
                                                <span>ขายแล้ว <?= $camp['flash_sold'] ?>/<?= $camp['flash_stock'] ?> ชิ้น</span>
                                                <span><?= round($pct) ?>%</span>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-blue" role="progressbar" style="width: <?= $pct ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-top pt-2">
                                            <div style="font-size: 0.72rem; line-height: 1.3;" class="text-muted">
                                                <div><i class="bi bi-play-circle-fill text-success me-1"></i><?= date('d/m/Y H:i', $start) ?></div>
                                                <div><i class="bi bi-stop-circle-fill text-danger me-1"></i><?= date('d/m/Y H:i', $end) ?></div>
                                            </div>
                                            <div class="d-flex gap-1 ms-auto">
                                                <?php if ($is_active): ?>
                                                    <button onclick="cancelCampaign(<?= $camp['id'] ?>, '<?= get_csrf_token() ?>')" class="btn btn-outline-danger btn-sm rounded-3 px-2 py-1" style="font-size: 0.72rem;" title="จบแคมเปญทันที">
                                                        <i class="bi bi-stop-fill"></i> จบ
                                                    </button>
                                                <?php endif; ?>
                                                <button onclick="loadEditCampaign(<?= $camp['id'] ?>)" class="btn btn-light btn-sm rounded-3 text-warning border px-2 py-1" style="font-size: 0.72rem;" title="แก้ไข">
                                                    <i class="bi bi-pencil-fill"></i> แก้ไข
                                                </button>
                                                <button onclick="deleteCampaign(<?= $camp['id'] ?>, '<?= get_csrf_token() ?>')" class="btn btn-light btn-sm rounded-3 text-danger border px-2 py-1" style="font-size: 0.72rem;" title="ลบ">
                                                    <i class="bi bi-trash-fill"></i> ลบ
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php
                            if ($ajax_fetch_mob) {
                                $mobile_html = ob_get_clean();
                                ob_end_clean(); // Discard outer buffer
                                header('Content-Type: application/json');
                                echo json_encode(['status' => 'success', 'html' => $desktop_html, 'mobile_html' => $mobile_html]);
                                exit();
                            }
                            ?>
                        </div>
                        <!-- การแบ่งหน้า (Pagination) -->
                        <?= render_pagination_controls($total_rows, $limit, $page, $offset) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        toggleDiscountFields();
    });

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

    function formatDateTimeLocal(dtStr) {
        if (!dtStr) return '';
        return dtStr.replace(' ', 'T').substring(0, 16);
    }

    function loadEditCampaign(id) {
        fetch(window.location.pathname + `?get_edit=${id}&ajax=1`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const camp = data.data;
                document.getElementById('campaign-id').value = camp.id;
                document.querySelector('select[name="product_id"]').value = camp.product_id;
                document.querySelector('input[name="flash_price"]').value = camp.flash_price;
                document.querySelector('input[name="flash_stock"]').value = camp.flash_stock;
                document.querySelector('input[name="start_time"]').value = formatDateTimeLocal(camp.start_time);
                document.querySelector('input[name="end_time"]').value = formatDateTimeLocal(camp.end_time);
                
                document.getElementById('form-title').innerHTML = '<i class="bi bi-pencil-square text-warning"></i> แก้ไขแคมเปญ Flash Sale';
                const submitBtn = document.getElementById('submit-btn');
                submitBtn.innerText = 'อัปเดตข้อมูล';
                submitBtn.name = 'update';
                submitBtn.className = 'btn btn-warning w-100 rounded-3 py-2 text-white fw-bold';
                document.getElementById('cancel-edit-btn').style.display = 'block';
                
                // Scroll to form smoothly on mobile
                document.getElementById('form-title').scrollIntoView({ behavior: 'smooth' });
            } else {
                Swal.fire('ข้อผิดพลาด', data.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            Toast.fire({ icon: 'error', title: 'ดึงข้อมูลแคมเปญล้มเหลว' });
        });
    }

    function exitEditMode() {
        document.getElementById('campaign-id').value = '';
        document.getElementById('campaign-form').reset();
        
        document.getElementById('form-title').innerHTML = '<i class="bi bi-lightning-charge-fill text-blue"></i> สร้างแคมเปญใหม่';
        const submitBtn = document.getElementById('submit-btn');
        submitBtn.innerText = 'สร้างแคมเปญ';
        submitBtn.name = 'add';
        submitBtn.className = 'btn btn-blue w-100 rounded-3 py-2 fw-bold';
        document.getElementById('cancel-edit-btn').style.display = 'none';
    }

    function toggleDiscountFields() {
        const typeSelect = document.getElementById('autoFlashTypeSelect');
        const staticGroup = document.getElementById('static-discount-group');
        const dynamicGroup = document.getElementById('dynamic-discount-group');
        if (typeSelect && typeSelect.value === 'dynamic') {
            if (staticGroup) staticGroup.style.display = 'none';
            if (dynamicGroup) dynamicGroup.style.display = 'block';
        } else {
            if (staticGroup) staticGroup.style.display = 'block';
            if (dynamicGroup) dynamicGroup.style.display = 'none';
        }
    }

    function submitCampaignForm(event) {
        event.preventDefault();
        const form = event.target;
        const btn = document.getElementById('submit-btn');
        const isEdit = document.getElementById('campaign-id').value !== '';
        
        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> กำลังบันทึก...';
        
        const formData = new FormData(form);
        if (isEdit) {
            formData.append('update', '1');
        } else {
            formData.append('add', '1');
        }
        formData.append('ajax', '1');
        
        fetch(window.location.pathname, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            if (data.status === 'success') {
                Toast.fire({
                    icon: 'success',
                    title: data.message
                });
                form.reset();
                if (isEdit) exitEditMode();
                fetchCampaigns();
            } else {
                Swal.fire('ข้อผิดพลาด', data.message, 'error');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            console.error(err);
            Toast.fire({
                icon: 'error',
                title: 'การเชื่อมต่อล้มเหลว'
            });
        });
    }

    function submitSettingsForm(event) {
        event.preventDefault();
        const form = event.target;
        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> กำลังบันทึก...';
        
        const formData = new FormData(form);
        formData.append('update_settings', '1');
        formData.append('ajax', '1');
        
        fetch(window.location.pathname, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            if (data.status === 'success') {
                Toast.fire({
                    icon: 'success',
                    title: data.message
                });
            } else {
                Swal.fire('เกิดข้อผิดพลาด', data.message || 'บันทึกไม่สำเร็จ', 'error');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            console.error(err);
            Toast.fire({
                icon: 'error',
                title: 'การเชื่อมต่อล้มเหลว'
            });
        });
    }

    function fetchCampaigns() {
        fetch(window.location.pathname + '?ajax_fetch=1')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('campaigns-tbody').innerHTML = data.html;
                const mobList = document.getElementById('campaigns-mobile-list');
                if (mobList) {
                    mobList.innerHTML = data.mobile_html;
                }
            }
        })
        .catch(err => console.error(err));
    }

    function cancelCampaign(id, token) {
        Swal.fire({
            title: 'จบแคมเปญทันที?',
            text: "แคมเปญนี้จะสิ้นสุดลงทันที",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'ใช่, บังคับปิด',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(window.location.pathname + `?cancel_campaign=${id}&csrf_token=${token}&ajax=1`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Toast.fire({
                            icon: 'success',
                            title: data.message
                        });
                        fetchCampaigns();
                    } else {
                        Toast.fire({
                            icon: 'error',
                            title: data.message || 'เกิดข้อผิดพลาด'
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

    function deleteCampaign(id, token) {
        Swal.fire({
            title: 'ลบแคมเปญ Flash Sale?',
            text: "ข้อมูลแคมเปญจะถูกลบถาวร",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'ลบเลย',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(window.location.pathname + `?del=${id}&csrf_token=${token}&ajax=1`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Toast.fire({
                            icon: 'success',
                            title: data.message
                        });
                        const row = document.getElementById('campaign-row-' + id);
                        const mobCard = document.getElementById('campaign-mob-card-' + id);
                        if (row) {
                            row.style.transition = 'all 0.3s ease';
                            row.style.opacity = '0';
                            row.style.transform = 'translateX(30px)';
                            setTimeout(() => row.remove(), 300);
                        }
                        if (mobCard) {
                            mobCard.style.transition = 'all 0.3s ease';
                            mobCard.style.opacity = '0';
                            mobCard.style.transform = 'translateX(30px)';
                            setTimeout(() => mobCard.remove(), 300);
                        }
                        setTimeout(() => {
                            const tbody = document.getElementById('campaigns-tbody');
                            if (tbody && tbody.querySelectorAll('.campaign-row').length === 0) {
                                tbody.innerHTML = `
                                    <tr id="no-campaigns-placeholder">
                                        <td colspan="6" class="text-center py-4 text-muted">ไม่พบข้อมูลแคมเปญ Flash Sale</td>
                                    </tr>
                                `;
                            }
                            const mobList = document.getElementById('campaigns-mobile-list');
                            if (mobList && mobList.querySelectorAll('.card-modern-mobile').length === 0) {
                                mobList.innerHTML = `
                                    <div class="text-center py-4 text-muted small" id="no-campaigns-placeholder-mob">ไม่พบข้อมูลแคมเปญ Flash Sale</div>
                                `;
                            }
                        }, 320);
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
</script>
</body>
</html>
