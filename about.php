<?php
session_start();
include 'db.php';
include 'header.php';

$q = mysqli_query($conn, "SELECT * FROM about_content WHERE id=1");
$about = mysqli_fetch_assoc($q);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $about['title'] ?> | Por Mae Bet Taled</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/x-icon" href="<?= isset($current_favicon) ? $current_favicon : 'assets/default_icon.png' ?>">
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #f8f9fa; }
        .about-img { width: 100%; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); object-fit: cover; }
        .text-content { font-size: 1.1rem; line-height: 1.8; color: #555; white-space: pre-line; }
        @media (max-width: 768px) {
            .text-content { font-size: 1rem; }
            h1.display-5 { font-size: 2rem; }
        }
    </style>
</head>
<body>

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
                    <a href="index.php#shop" class="btn btn-dark rounded-pill px-4 py-2 shadow-sm">
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
