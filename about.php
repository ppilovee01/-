<?php
session_start();
include 'db.php';

$q = mysqli_query($conn, "SELECT * FROM about_content WHERE id=1");
$about = mysqli_fetch_assoc($q);

$page_title = htmlspecialchars($about['title']) . " | Por Mae Bet Taled";
$extra_css = "
<style>
    .about-img { width: 100%; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); object-fit: cover; transition: var(--transition-smooth); }
    .text-content { font-size: 1.1rem; line-height: 1.8; color: #555; white-space: pre-line; }
    @media (max-width: 768px) {
        .text-content { font-size: 1rem; }
        h1.display-5 { font-size: 2rem; }
    }
    
    /* Dark Theme overrides */
    body.dark-theme .text-content {
        color: var(--text-primary) !important;
    }
    body.dark-theme .about-img {
        border: 1px solid rgba(56, 189, 248, 0.2) !important;
        box-shadow: 0 10px 30px rgba(56, 189, 248, 0.15) !important;
    }
</style>
";
include 'header.php';
?>

<div class="container py-5">
    <div class="row align-items-center">
        <div class="col-lg-6 mb-4 mb-lg-0 animate__animated animate__fadeInLeft">
            <?php if(!empty($about['image'])): ?>
                <img src="<?= $about['image'] ?>" alt="About Us" class="about-img">
            <?php else: ?>
                <img src="https://via.placeholder.com/600x400?text=About+Us" alt="About Us" class="about-img">
            <?php endif; ?>
        </div>

        <div class="col-lg-6 animate__animated animate__fadeInRight">
            <div class="ps-lg-4">
                <h6 class="text-uppercase fw-bold mb-3" style="color:#AEE2FF; letter-spacing: 2px;">ABOUT US</h6>
                <h1 class="display-5 fw-bold mb-4 text-dark"><?= $about['title'] ?></h1>
                <div class="text-content"><?= $about['description'] ?></div>
                <div class="mt-5">
                    <a href="index.php#shop" class="btn btn-gradient rounded-pill px-4 py-2 shadow-sm">
                        เลือกชมสินค้า <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="py-4 bg-white border-top mt-5 text-center text-muted small">
    <div class="container">© 2026 Por Mae Bet Taled. All rights reserved.</div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
