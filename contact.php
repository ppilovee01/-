<?php
session_start();
include 'db.php';
$page_title = "ติดต่อเรา | Por Mae Bet Taled";
$extra_css = "
<style>
    /* Premium overrides for contact.php */
    body.dark-theme .bg-dark {
        background: linear-gradient(135deg, rgba(6, 9, 19, 0.95) 0%, rgba(13, 20, 38, 0.95) 100%) !important;
        border-right: 1px solid rgba(56, 189, 248, 0.15) !important;
    }
</style>
";
include 'header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="row g-0">
                    <div class="col-md-5 bg-dark text-white p-5 d-flex flex-column justify-content-center">
                        <h4 class="fw-bold mb-4">ข้อมูลติดต่อ</h4>
                        <div class="mb-3"><i class="bi bi-geo-alt me-2"></i> สกลนคร, ประเทศไทย</div>
                        <div class="mb-3"><i class="bi bi-envelope me-2"></i> Por Mae Bet Taled@gmail.com</div>
                        <div class="mb-3"><i class="bi bi-telephone me-2"></i> 091-992-2031</div>
                        <div class="mt-4 d-flex gap-3">
                            <a href="#" class="text-white"><i class="bi bi-facebook fs-4"></i></a>
                            <a href="#" class="text-white"><i class="bi bi-line fs-4"></i></a>
                            <a href="#" class="text-white"><i class="bi bi-instagram fs-4"></i></a>
                        </div>
                    </div>
                    <div class="col-md-7 p-5">
                        <h4 class="fw-bold mb-4 text-dark">ส่งข้อความถึงเรา</h4>
                        <form id="contactForm" onsubmit="sendContact(); return false;">
                            <?= get_csrf_input() ?>
                            <div class="mb-3">
                                <label class="form-label small text-muted">ชื่อของคุณ</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted">อีเมล</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted">หัวข้อ</label>
                                <input type="text" name="subject" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted">ข้อความ</label>
                                <textarea name="message" class="form-control" rows="4" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-gradient w-100 rounded-pill">ส่งข้อความ</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let isContactSubmitting = false;
function sendContact() {
    if (isContactSubmitting) return;
    isContactSubmitting = true;
    
    const submitBtn = document.querySelector('#contactForm button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> กำลังส่ง...';
    }
    
    let fd = new FormData(document.getElementById('contactForm'));
    fd.append('action', 'send_contact');
    
    fetch('ajax.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        isContactSubmitting = false;
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'ส่งข้อความ';
        }
        if(data.status === 'success') {
            Swal.fire({icon: 'success', title: 'ส่งเรียบร้อย', text: data.message});
            document.getElementById('contactForm').reset();
        } else {
            Swal.fire({icon: 'error', title: 'ผิดพลาด', text: data.message});
        }
    })
    .catch(err => {
        isContactSubmitting = false;
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'ส่งข้อความ';
        }
        console.error(err);
    });
}
</script>
</body></html>


