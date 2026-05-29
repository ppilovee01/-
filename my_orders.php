<?php
session_start();
include 'db.php';
date_default_timezone_set('Asia/Bangkok');  
// เช็ค Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$action_status = "";

// คำนวณจำนวนสินค้าในตะกร้า
$cart_count = isset($_SESSION['cart']) ? array_sum(is_array($_SESSION['cart']) ? array_column($_SESSION['cart'], 'qty') : $_SESSION['cart']) : 0;

// --- Logic: ลูกค้ายกเลิกออเดอร์ ---
if (isset($_GET['cancel_my_order'])) {
    $oid = mysqli_real_escape_string($conn, $_GET['cancel_my_order']);
    
    // เช็คความเป็นเจ้าของ + สถานะ pending
    $check = mysqli_query($conn, "SELECT id FROM orders WHERE id='$oid' AND user_id='$user_id' AND status='pending'");
    
    if (mysqli_num_rows($check) > 0) {
        // คืนสต็อก
        $items = mysqli_query($conn, "SELECT product_id, quantity FROM order_items WHERE order_id='$oid'");
        while ($item = mysqli_fetch_assoc($items)) {
            mysqli_query($conn, "UPDATE products SET stock = stock + {$item['quantity']} WHERE id='{$item['product_id']}'");
        }
        
        // เปลี่ยนสถานะ
        if(mysqli_query($conn, "UPDATE orders SET status = 'cancelled' WHERE id='$oid'")){
            $action_status = "success";
        }
    } else {
        $action_status = "error";
    }
}

$page_title = "ติดตามคำสั่งซื้อ | Por Mae Bet Taled";
$extra_css = "
<style>
    /* การ์ดออเดอร์ */
    .order-card {
        border: none; border-radius: 16px; background: white;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 25px;
        overflow: hidden; transition: all 0.3s ease;
    }
    .order-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.06); }
    .order-card.cancelled { opacity: 0.7; filter: grayscale(100%); }
    
    .card-header-custom {
        padding: 15px 20px; background: #fff; border-bottom: 1px dashed #eee;
        display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;
    }
    
    /* Badges */
    .status-badge { padding: 6px 12px; border-radius: 30px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
    .status-pending { background: #fff8e1; color: #f59e0b; }
    .status-approved { background: #e0f2fe; color: #0ea5e9; }
    .status-shipping { background: #dcfce7; color: #16a34a; }
    .status-completed { background: #d1e7dd; color: #0f5132; }
    .status-cancelled { background: #f3f4f6; color: #6b7280; }

    /* Timeline */
    .step-progress { display: flex; justify-content: space-between; position: relative; margin: 30px 0; padding: 0 10px; }
    .step-progress::before { content: ''; position: absolute; top: 14px; left: 30px; right: 30px; height: 3px; background: #e5e7eb; z-index: 1; }
    .step-item { position: relative; z-index: 2; text-align: center; width: 33.33%; }
    .step-circle { width: 32px; height: 32px; background: #fff; border: 3px solid #e5e7eb; border-radius: 50%; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; color: transparent; font-size: 14px; transition: 0.3s; }
    .step-item.active .step-circle { background: var(--blue-hover); border-color: var(--blue-hover); color: white; box-shadow: 0 0 0 4px rgba(174,226,255,0.2); }
    .step-text { font-size: 0.8rem; color: #9ca3af; font-weight: 500; }
    .step-item.active .step-text { color: var(--blue-hover); font-weight: 700; }
    
    /* Product List */
    .product-item { display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid #f9f9f9; }
    .product-item:last-child { border-bottom: none; }
    .product-img { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; border: 1px solid #eee; margin-right: 15px; }
    
    /* Buttons */
    .btn-action { font-size: 0.8rem; padding: 5px 15px; border-radius: 20px; text-decoration: none; display: inline-block; cursor: pointer; transition: 0.2s; }
    .btn-view-slip { background: #f3f4f6; color: #4b5563; }
    .btn-view-slip:hover { background: #e5e7eb; color: #1f2937; }
    .btn-cancel { border: 1px solid #ef4444; color: #ef4444; background: white; }
    .btn-cancel:hover { background: #ef4444; color: white; }
    .btn-review { border: 1px solid var(--slate-dark); color: var(--slate-dark); background: white; margin-left: 10px; }
    .btn-review:hover { background: var(--slate-dark); color: white; }

    .tracking-box { background: #fdf2f8; border: 1px dashed var(--blue-hover); border-radius: 10px; padding: 10px; text-align: center; margin-top: 15px; }
    .tracking-number { font-size: 1rem; font-weight: 700; color: var(--blue-hover); letter-spacing: 1px; }
    .hidden { display: none !important; }
</style>
";
include 'header.php';
date_default_timezone_set('Asia/Bangkok');
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
            <h3 class="fw-bold mb-4">📦 ประวัติคำสั่งซื้อ</h3>

            <?php 
            $sql = "SELECT * FROM orders WHERE user_id = '$user_id' ORDER BY id DESC";
            $res = mysqli_query($conn, $sql);

            if (mysqli_num_rows($res) > 0):
                while($row = mysqli_fetch_assoc($res)): 
                    $oid = $row['id'];
                    $status = $row['status'];
                    
                    // Logic Timeline
                    $s1 = ($status != 'cancelled') ? 'active' : ''; 
                    $s2 = ($status == 'approved' || $status == 'shipping' || $status == 'completed') ? 'active' : '';
                    $s3 = ($status == 'shipping' || $status == 'completed') ? 'active' : '';
            ?>
            
            <div class="order-card animate__animated animate__fadeInUp <?= $status == 'cancelled' ? 'cancelled' : '' ?>">
                <div class="card-header-custom">
                    <div>
                        <div class="fw-bold">Order #<?= str_pad($row['id'], 5, '0', STR_PAD_LEFT) ?></div>
                        <div class="text-muted small" style="font-size: 0.75rem;"><i class="bi bi-clock"></i> <?= date('d/m/Y H:i', strtotime($row['order_date'])) ?></div>
                    </div>
                    <div>
                        <?php 
                        if($status == 'pending') echo '<span class="status-badge status-pending"><i class="bi bi-hourglass-split"></i> รอตรวจสอบ</span>';
                        elseif($status == 'approved') echo '<span class="status-badge status-approved"><i class="bi bi-check2-circle"></i> เตรียมส่ง</span>';
                        elseif($status == 'shipping') echo '<span class="status-badge status-shipping"><i class="bi bi-truck"></i> ส่งแล้ว</span>';
                        elseif($status == 'completed') echo '<span class="status-badge status-completed"><i class="bi bi-check-circle-fill"></i> สำเร็จ</span>';
                        else echo '<span class="status-badge status-cancelled"><i class="bi bi-x-circle"></i> ยกเลิก</span>';
                        ?>
                    </div>
                </div>

                <div class="p-3">
                    <?php if($status != 'cancelled'): ?>
                        <div class="step-progress">
                            <div class="step-item <?= $s1 ?>"><div class="step-circle"><i class="bi bi-check"></i></div><div class="step-text">สั่งซื้อ</div></div>
                            <div class="step-item <?= $s2 ?>"><div class="step-circle"><i class="bi bi-box-seam"></i></div><div class="step-text">เตรียมของ</div></div>
                            <div class="step-item <?= $s3 ?>"><div class="step-circle"><i class="bi bi-truck"></i></div><div class="step-text">ขนส่ง</div></div>
                        </div>

                        <?php if(($status == 'shipping' || $status == 'completed') && !empty($row['tracking_no'])): ?>
                            <div class="tracking-box">
                                <div class="small text-muted mb-1">เลขพัสดุ (Tracking)</div>
                                <div class="tracking-number">
                                    <?= $row['tracking_no'] ?> 
                                    <i class="bi bi-copy ms-2 text-secondary" style="cursor:pointer;" onclick="navigator.clipboard.writeText('<?= $row['tracking_no'] ?>'); Swal.fire({toast:true, position:'top-end', icon:'success', title:'คัดลอกแล้ว', showConfirmButton:false, timer:1000})"></i>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <hr class="text-muted opacity-25 my-3">

                    <div class="mb-3">
                        <?php 
                        $items_sql = "SELECT oi.*, p.name, p.image FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = '$oid'";
                        $items_res = mysqli_query($conn, $items_sql);
                        while($item = mysqli_fetch_assoc($items_res)):
                        ?>
                        <div class="product-item">
                            <img src="<?= $item['image'] ?>" class="product-img">
                            <div style="flex:1; min-width:0;">
                                <div class="fw-bold text-dark text-truncate small"><?= $item['name'] ?></div>
                                <?php if(!empty($item['selected_option'])): ?>
                                    <small class="text-muted bg-light border px-2 py-0 rounded-pill d-inline-block mt-1 mb-1">
                                        <?= $item['selected_option'] ?>
                                    </small>
                                    <br>
                                <?php endif; ?>
                                <span class="small text-muted" style="font-size:0.75rem;">x<?= $item['quantity'] ?></span>
                            </div>
                            
                            <div class="text-end">
                                <div class="fw-bold small mb-1">฿<?= number_format($item['price'] * $item['quantity']) ?></div>
                                
                                <?php if($status == 'completed' || $status == 'shipping'): ?>
                                    <a href="product_detail.php?id=<?= $item['product_id'] ?>#review" class="btn-action btn-review text-decoration-none">
                                        <i class="bi bi-star"></i> รีวิว
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>

                    <div class="bg-light rounded-3 p-3 mb-3 d-flex justify-content-between align-items-center">
                        <span class="text-muted small">ยอดสุทธิ</span>
                        <span class="fw-bold fs-5 text-blue" style="color:var(--blue-dark)">฿<?= number_format($row['final_price'] > 0 ? $row['final_price'] : $row['total_price'], 2) ?></span>
                    </div>

                    <div class="text-end">
                        <?php if(!empty($row['payment_slip'])): ?>
                            <span onclick="viewSlip('uploads/<?= $row['payment_slip'] ?>')" class="btn-action btn-view-slip me-2"><i class="bi bi-image"></i> สลิป</span>
                        <?php endif; ?>
                        
                        <?php if($status == 'pending'): ?>
                            <span onclick="confirmCancel(<?= $row['id'] ?>)" class="btn-action btn-cancel">ยกเลิก</span>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
            <?php endwhile; else: ?>
                <div class="text-center py-5 bg-white rounded-4 shadow-sm">
                    <i class="bi bi-basket display-1 text-muted opacity-25"></i>
                    <h5 class="text-muted mt-3">ไม่มีประวัติการสั่งซื้อ</h5>
                    <a href="index.php" class="btn btn-sm btn-outline-danger rounded-pill mt-3 px-4">ไปช้อปปิ้งเลย</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="slipModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content bg-transparent border-0"><div class="modal-body p-0 text-center"><button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button><img id="slipImage" src="" class="img-fluid rounded-3 shadow-lg" style="max-height: 85vh;"></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function viewSlip(url){ new bootstrap.Modal(document.getElementById('slipModal')).show(); document.getElementById('slipImage').src = url; }
    function confirmCancel(id){ Swal.fire({title:'ยืนยันยกเลิก?',icon:'warning',showCancelButton:true,confirmButtonColor:'#ef4444',confirmButtonText:'ยกเลิกออเดอร์'}).then((r)=>{if(r.isConfirmed) window.location.href='?cancel_my_order='+id;}) }
    <?php if($action_status == 'success'): ?>Swal.fire({icon:'success',title:'สำเร็จ',text:'ยกเลิกรายการแล้ว',confirmButtonColor:'#AEE2FF'}).then(()=>{location.href='my_orders.php'});<?php endif; ?>
</script>
</body>
</html>
