<?php
session_start();
include 'db.php';

// เช็ค Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }

if (isset($_GET['delete'])) {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้องหรือหมดเวลาเซสชัน (Invalid CSRF Token)']);
            exit();
        }
        die("Error: Invalid CSRF Token. (คำขอไม่ถูกต้องหรือไม่ปลอดภัย)");
    }
    $id = intval($_GET['delete']);
    
    // ดึงข้อมูลรีวิวเพื่อบันทึกประวัติก่อนลบ
    $r_q = mysqli_query($conn, "SELECT r.*, u.fullname, p.name as product_name FROM product_reviews r JOIN users u ON r.user_id = u.id JOIN products p ON r.product_id = p.id WHERE r.id = '$id'");
    $r_info = mysqli_fetch_assoc($r_q);
    $r_comment = $r_info['comment'] ?? 'ไม่ระบุความคิดเห็น';
    $u_name = $r_info['fullname'] ?? 'ไม่ระบุผู้ใช้';
    $p_name = $r_info['product_name'] ?? 'ไม่ระบุสินค้า';
    $r_rating = $r_info['rating'] ?? 0;
    
    // ดึงข้อมูลรูปภาพเพื่อลบไฟล์จากโฟลเดอร์ uploads/
    if (!empty($r_info['image'])) {
        $file_path = $r_info['image'];
        // ตรวจสอบว่ามีไฟล์อยู่จริงแล้วทำการลบ
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
    }
    
    mysqli_query($conn, "DELETE FROM product_reviews WHERE id = '$id'");
    
    log_admin_action($conn, 'ลบรีวิวสินค้า', [
        'title' => "ลบรีวิวสินค้า '$p_name' ของลูกค้า '$u_name'",
        'sections' => [
            [
                'title' => 'รายละเอียดรีวิวที่ถูกลบ',
                'items' => [
                    "รหัสรีวิว: #$id",
                    "สินค้า: $p_name (รหัสสินค้า #" . ($r_info['product_id'] ?? '-') . ")",
                    "ลูกค้า: $u_name (รหัสผู้ใช้ #" . ($r_info['user_id'] ?? '-') . ")",
                    "คะแนน: " . str_repeat('★', $r_rating) . str_repeat('☆', 5 - $r_rating) . " ($r_rating/5)",
                    "ความคิดเห็น: $r_comment",
                    "มีรูปถ่ายประกอบ: " . (!empty($r_info['image']) ? "มี (ลบไฟล์รูปภาพออกจากเซิร์ฟเวอร์เรียบร้อย)" : "ไม่มี")
                ]
            ]
        ]
    ]);
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'ลบรีวิวสินค้าเรียบร้อยแล้ว']);
        exit();
    }
    header("Location: admin_reviews.php"); exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการรีวิว | Por Mae Bet Taled Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style> body { font-family: 'Kanit'; background: #f8f9fa; }         
        /* สไตล์การ์ดมือถือพรีเมียม */
        @media (max-width: 767.98px) {
            .card-modern-mobile {
                background: #ffffff !important;
                border: 1px solid rgba(226, 232, 240, 0.8) !important;
                border-radius: 20px !important;
                box-shadow: 0 10px 30px rgba(127, 181, 255, 0.05) !important;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                position: relative !important;
                overflow: hidden !important;
                border-left: 5px solid #7FB5FF !important; /* Pastel Blue left accent */
            }
            .card-modern-mobile:hover, .card-modern-mobile:active {
                transform: translateY(-3px) scale(1.01);
                box-shadow: 0 15px 35px rgba(127, 181, 255, 0.12) !important;
                border-color: rgba(127, 181, 255, 0.3) !important;
            }
            .card-modern-mobile .btn {
                border-radius: 12px !important;
                font-weight: 500;
                padding: 6px 12px;
                font-size: 0.78rem;
            }
            .card-modern-mobile .btn-light {
                background: #f8fafc !important;
                border: 1px solid #e2e8f0 !important;
                color: #475569 !important;
            }
            .card-modern-mobile .btn-light:hover {
                background: #f1f5f9 !important;
            }
        }
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
            <h3 class="fw-bold mb-4">จัดการรีวิวสินค้า</h3>

            <div class="card border-0 shadow-sm rounded-4 p-4">
                <div class="table-responsive">
                    <table class="table align-middle table-hover">
                        <thead class="bg-light text-secondary small">
                            <tr>
                                <th>สินค้า</th>
                                <th>ลูกค้า</th>
                                <th>คะแนนน</th>
                                <th style="width: 40%;">ความคิดเห็น</th>
                                <th>วันที่</th>
                                <th class="text-end">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="reviews-tbody">
                            <?php 
                            // ดึงรีวิว + ชื่อสินค้า + ชื่อคนรีวิว (เลี่ยงคอลัมน์ image ชนกัน)
                            $sql = "SELECT r.*, r.image as review_image, p.name as product_name, p.image as product_image, u.fullname 
                                    FROM product_reviews r 
                                    JOIN products p ON r.product_id = p.id 
                                    JOIN users u ON r.user_id = u.id 
                                    ORDER BY r.created_at DESC";
                            $res = mysqli_query($conn, $sql);
                            
                            if(mysqli_num_rows($res) > 0):
                                while($row = mysqli_fetch_assoc($res)): 
                            ?>
                            <!-- Desktop Row -->
                            <tr id="review-row-<?= $row['id'] ?>" class="d-none d-md-table-row">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?= $row['product_image'] ?>" class="rounded me-2" style="width:35px; height:35px; object-fit:cover;">
                                        <span class="text-truncate small" style="max-width: 150px;"><?= htmlspecialchars($row['product_name'] ?? '') ?></span>
                                    </div>
                                </td>
                                <td class="small fw-bold"><?= htmlspecialchars($row['fullname'] ?? '') ?></td>
                                <td>
                                    <span class="text-warning small">
                                        <?php for($i=1;$i<=5;$i++) echo $i<=$row['rating'] ? '★' : '☆'; ?>
                                    </span>
                                    <span class="small text-muted">(<?= $row['rating'] ?>)</span>
                                </td>
                                <td class="text-secondary small">
                                    <div class="mb-1"><?= htmlspecialchars($row['comment']) ?></div>
                                    <?php if (!empty($row['review_image']) && file_exists($row['review_image'])): ?>
                                        <div>
                                            <img src="<?= htmlspecialchars($row['review_image']) ?>" class="rounded border shadow-sm" style="width:45px; height:45px; object-fit:cover; cursor:pointer;" onclick="showReviewImage('<?= htmlspecialchars($row['review_image']) ?>', '<?= htmlspecialchars($row['fullname']) ?>')" title="คลิกเพื่อดูรูปภาพขนาดใหญ่">
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                                <td class="text-end">
                                    <button onclick="confirmDelete(<?= $row['id'] ?>, '<?= get_csrf_token() ?>')" class="btn btn-sm btn-outline-danger rounded-circle">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>

                            <!-- Mobile Row -->
                            <tr id="review-mob-row-<?= $row['id'] ?>" class="d-md-none">
                                <td colspan="6" class="p-0 border-0">
                                    <div class="card-modern-mobile p-3 mb-3 text-start">
                                        <div class="d-flex align-items-center gap-3 mb-2">
                                            <img src="<?= $row['product_image'] ?>" class="rounded" style="width: 40px; height: 40px; object-fit: cover;">
                                            <div class="flex-grow-1 min-w-0">
                                                <div class="fw-bold text-dark text-truncate" style="font-size: 0.9rem;"><?= htmlspecialchars($row['product_name'] ?? '') ?></div>
                                                <div class="small text-muted">ผู้รีวิว: <?= htmlspecialchars($row['fullname'] ?? '') ?></div>
                                            </div>
                                            <div>
                                                <span class="text-warning small">
                                                    <?php for($i=1;$i<=5;$i++) echo $i<=$row['rating'] ? '★' : '☆'; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="mb-2 text-secondary small">
                                            <div><?= htmlspecialchars($row['comment']) ?></div>
                                            <?php if (!empty($row['review_image']) && file_exists($row['review_image'])): ?>
                                                <div class="mt-2">
                                                    <img src="<?= htmlspecialchars($row['review_image']) ?>" class="rounded border shadow-sm" style="width:50px; height:50px; object-fit:cover; cursor:pointer;" onclick="showReviewImage('<?= htmlspecialchars($row['review_image']) ?>', '<?= htmlspecialchars($row['fullname']) ?>')" title="คลิกเพื่อดูรูปภาพขนาดใหญ่">
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center border-top pt-2">
                                            <span class="small text-muted" style="font-size: 0.75rem;"><i class="bi bi-clock me-1"></i><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></span>
                                            <button onclick="confirmDelete(<?= $row['id'] ?>, '<?= get_csrf_token() ?>')" class="btn btn-sm btn-outline-danger rounded-3 px-3 py-1">
                                                <i class="bi bi-trash"></i> ลบรีวิว
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr id="no-reviews-placeholder"><td colspan="6" class="text-center py-4 text-muted">ยังไม่มีรีวิว</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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

    function confirmDelete(id, token) {
        Swal.fire({
            title: 'ลบรีวิวนี้?',
            text: "ข้อมูลจะหายไปถาวร",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'ลบเลย',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(window.location.pathname + '?delete=' + id + '&csrf_token=' + token + '&ajax=1')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Toast.fire({
                            icon: 'success',
                            title: data.message
                        });
                        const row = document.getElementById('review-row-' + id);
                        if (row) {
                            row.style.transition = 'all 0.3s ease';
                            row.style.opacity = '0';
                            row.style.transform = 'translateX(30px)';
                            setTimeout(() => {
                                row.remove();
                                const tbody = document.getElementById('reviews-tbody');
                                if (tbody && tbody.querySelectorAll('tr:not(#no-reviews-placeholder)').length === 0) {
                                    tbody.innerHTML = '<tr id="no-reviews-placeholder"><td colspan="6" class="text-center py-4 text-muted">ยังไม่มีรีวิว</td></tr>';
                                }
                            }, 300);
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
        })
    }

    function showReviewImage(src, name) {
        Swal.fire({
            title: 'รูปภาพรีวิวจากคุณ ' + name,
            imageUrl: src,
            imageAlt: 'รีวิวสินค้า',
            confirmButtonText: 'ปิด',
            confirmButtonColor: 'var(--blue-hover)',
            customClass: {
                image: 'img-fluid rounded shadow-sm'
            }
        });
    }
</script>
</body>
</html>
