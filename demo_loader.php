<?php
$page_title = "ทดสอบระบบความคืบหน้าหน้าเว็บ (Progress Bar & Percentages Demo)";
include 'header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Header Section -->
            <div class="text-center mb-5 animate__animated animate__fadeInDown">
                <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill mb-2 fw-semibold" style="color: var(--blue-hover) !important; background-color: var(--blue-light) !important;">Feature Showcase</span>
                <h1 class="fw-bold" style="color: var(--slate-dark);">ระบบแถบโหลดความคืบหน้าและเปอร์เซ็นต์</h1>
                <p class="text-muted mx-auto" style="max-width: 650px;">
                    ระบบจะทำงานอัตโนมัติเมื่อกดเปลี่ยนหน้า เพื่อกำจัดจอกะพริบขาว (White Screen Flash) และแจ้งความคืบหน้าการดึงข้อมูล 
                    คุณสามารถทดสอบหน้าตา ความลื่นไหล และการตอบสนองได้จากกล่องจำลองด้านล่างนี้
                </p>
            </div>

            <div class="row g-4">
                <!-- Left Column: Interactive Inline Preview -->
                <div class="col-md-6 animate__animated animate__fadeInLeft">
                    <div class="card card-modern h-100 border-0 shadow-sm p-4" style="border-radius: var(--radius-md); background: white;">
                        <h4 class="fw-bold mb-3 d-flex align-items-center" style="color: var(--slate-dark);">
                            <i class="bi bi-sliders me-2 text-primary" style="color: var(--blue-hover) !important;"></i> 
                            จำลองความคืบหน้า (Inline Preview)
                        </h4>
                        <p class="text-muted small mb-4">
                            ทดลองเลื่อนสไลเดอร์หรือคลิกปุ่มเปอร์เซ็นต์เพื่อเปลี่ยนสถานะความคืบหน้าของแถบโหลดในฝั่งขวามือแบบเรียลไทม์
                        </p>

                        <!-- Controls -->
                        <div class="mb-4">
                            <label for="demo-slider" class="form-label fw-semibold text-secondary d-flex justify-content-between">
                                <span>ปรับเปอร์เซ็นต์ด้วยตนเอง:</span>
                                <span id="slider-val-display" class="badge bg-primary text-white" style="background-color: var(--blue-hover) !important;">50%</span>
                            </label>
                            <input type="range" class="form-range" id="demo-slider" min="0" max="100" value="50">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold text-secondary mb-2 d-block">ปุ่มเลือกเปอร์เซ็นต์ด่วน:</label>
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-outline-secondary btn-sm px-3 rounded-pill btn-pct-set" data-pct="0">0% (เริ่มต้น)</button>
                                <button class="btn btn-outline-secondary btn-sm px-3 rounded-pill btn-pct-set" data-pct="25">25%</button>
                                <button class="btn btn-outline-secondary btn-sm px-3 rounded-pill btn-pct-set" data-pct="50">50%</button>
                                <button class="btn btn-outline-secondary btn-sm px-3 rounded-pill btn-pct-set" data-pct="75">75%</button>
                                <button class="btn btn-outline-secondary btn-sm px-3 rounded-pill btn-pct-set" data-pct="90">90% (โหลดข้อมูลเสร็จ)</button>
                                <button class="btn btn-outline-secondary btn-sm px-3 rounded-pill btn-pct-set" data-pct="100">100% (สมบูรณ์)</button>
                            </div>
                        </div>

                        <hr class="my-4 text-muted opacity-25">

                        <h5 class="fw-bold mb-3" style="color: var(--slate-dark);">ทดสอบตัวโหลดแบบเต็มจอ (Full Screen)</h5>
                        <p class="text-muted small mb-3">
                            เปิดการแสดงผลแบบเต็มจอและเริ่มจำลองเวลาการดาวน์โหลดของเว็บไซต์จริง
                        </p>
                        
                        <div class="d-grid gap-2">
                            <button id="btn-trigger-fast" class="btn btn-gradient text-white d-flex align-items-center justify-content-center">
                                <i class="bi bi-lightning-charge-fill me-2"></i> ทดสอบโหลดความเร็วปกติ (ใช้ย้ายหน้าจริง)
                            </button>
                            <button id="btn-trigger-medium" class="btn btn-outline-custom d-flex align-items-center justify-content-center">
                                <i class="bi bi-hourglass-split me-2"></i> โหลดช้าปานกลาง (จำลอง 2.5 วินาที)
                            </button>
                            <button id="btn-trigger-slow" class="btn btn-outline-custom d-flex align-items-center justify-content-center">
                                <i class="bi bi-cloud-download me-2"></i> โหลดช้าพิเศษ (จำลอง 5.0 วินาที)
                            </button>
                            <button id="btn-trigger-manual" class="btn btn-dark d-flex align-items-center justify-content-center rounded-pill" style="padding: 10px 28px;">
                                <i class="bi bi-eye-fill me-2"></i> แสดงเต็มจอแบบค้างไว้ (สำหรับตรวจงาน)
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Interactive Demo Card (Replica of the Loader) -->
                <div class="col-md-6 animate__animated animate__fadeInRight">
                    <div class="card card-modern border-0 shadow-sm p-4 d-flex align-items-center justify-content-center" style="border-radius: var(--radius-md); background: #f8fafc; min-height: 400px; border: 2px dashed rgba(174, 226, 255, 0.4) !important;">
                        
                        <div class="text-center bg-white p-5 rounded-4 shadow-sm border border-light-subtle w-100" style="max-width: 320px;">
                            <!-- Spinner Container -->
                            <div class="preloader-spinner mb-4" id="preview-spinner" style="margin: 0 auto 20px;"></div>
                            
                            <!-- Status Text -->
                            <div class="preloader-text mb-3" id="preview-loader-text" style="font-size: 1.05rem;">กำลังโหลดข้อมูล...</div>
                            
                            <!-- Progress Bar -->
                            <div class="preloader-progress-bar mb-2" style="height: 6px; background-color: #e2e8f0; border-radius: 10px; overflow: hidden;">
                                <div class="preloader-progress-fill" id="preview-progress-fill" style="width: 50%; height: 100%; background: linear-gradient(90deg, var(--blue-main), var(--blue-hover)); transition: width 0.1s ease-out;"></div>
                            </div>
                            
                            <!-- Percentage Text -->
                            <div class="preloader-percentage fw-bold text-muted" id="preview-percentage" style="font-size: 1.1rem; color: var(--blue-hover) !important;">50%</div>
                        </div>

                        <div class="mt-4 text-center">
                            <span class="text-muted small"><i class="bi bi-info-circle me-1"></i> กล่องด้านบนนี้แสดงหน้าตาตัวโหลดจริงตามสัดส่วนการลากสไลเดอร์</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Extra UI elements for the Fullscreen Test Close Button -->
<div id="loader-close-container" class="position-fixed bottom-0 start-50 translate-middle-x mb-4 hidden" style="z-index: 100000;">
    <button id="btn-close-loader" class="btn btn-danger px-4 py-2 rounded-pill shadow-lg fw-bold animate__animated animate__bounceIn">
        <i class="bi bi-x-circle me-2"></i> ปิดหน้ากากโหลดเต็มจอ (กลับสู่หน้าทดสอบ)
    </button>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Controls for the Inline Preview Card
    const slider = document.getElementById('demo-slider');
    const sliderVal = document.getElementById('slider-val-display');
    const previewProgress = document.getElementById('preview-progress-fill');
    const previewPctText = document.getElementById('preview-percentage');
    const previewSpinner = document.getElementById('preview-spinner');
    const previewStatusText = document.getElementById('preview-loader-text');

    // 2. Controls for the Full Screen Overlay Test
    const globalLoader = document.getElementById('global-loader');
    const progressFill = document.getElementById('loader-progress');
    const percentageText = document.getElementById('loader-percentage');
    const statusLabel = document.getElementById('loader-text');
    const closeBtnContainer = document.getElementById('loader-close-container');
    const closeBtn = document.getElementById('btn-close-loader');

    // Diagnostic logging to troubleshoot layout / script binding issues
    console.log("Demo Loader initialized. Elements found:", {
        slider: !!slider,
        sliderVal: !!sliderVal,
        previewProgress: !!previewProgress,
        previewPctText: !!previewPctText,
        previewSpinner: !!previewSpinner,
        previewStatusText: !!previewStatusText,
        globalLoader: !!globalLoader,
        progressFill: !!progressFill,
        percentageText: !!percentageText,
        statusLabel: !!statusLabel,
        closeBtnContainer: !!closeBtnContainer,
        closeBtn: !!closeBtn
    });

    function updateInlineLoader(value) {
        if (slider) slider.value = value;
        if (sliderVal) sliderVal.innerText = value + '%';
        if (previewProgress) previewProgress.style.width = value + '%';
        if (previewPctText) previewPctText.innerText = value + '%';

        // ใช้ฟังก์ชัน getLoadingStatusMessage ที่ถูกประกาศแบบโกลบอลใน header.php
        if (previewStatusText && typeof window.getLoadingStatusMessage === 'function') {
            previewStatusText.innerText = window.getLoadingStatusMessage(parseInt(value), 'รายละเอียดสินค้า');
        } else if (previewStatusText) {
            previewStatusText.innerText = 'กำลังโหลดข้อมูล... ' + value + '%';
        }
        
        // Spin control: speed up spin if progress is high, stop spin if 100%
        if (previewSpinner) {
            if (parseInt(value) === 100) {
                previewSpinner.style.animationPlayState = 'paused';
                previewSpinner.style.borderColor = 'var(--blue-hover)';
            } else {
                previewSpinner.style.animationPlayState = 'running';
                previewSpinner.style.borderColor = '#f1f5f9';
                previewSpinner.style.borderTopColor = 'var(--blue-hover)';
                previewSpinner.style.borderBottomColor = 'var(--blue-main)';
            }
        }
    }

    // Initialize with slider value
    if (slider) {
        updateInlineLoader(slider.value);
        slider.addEventListener('input', () => {
            updateInlineLoader(slider.value);
        });
    }

    document.querySelectorAll('.btn-pct-set').forEach(btn => {
        btn.addEventListener('click', () => {
            const pct = btn.getAttribute('data-pct');
            updateInlineLoader(pct);
        });
    });

    function showFullscreenLoader() {
        if (!globalLoader) {
            console.error("global-loader element not found!");
            return;
        }
        globalLoader.classList.remove('hidden');
        globalLoader.classList.add('active');
        if (progressFill) progressFill.style.width = '0%';
        if (percentageText) percentageText.innerText = '0%';
    }

    function hideFullscreenLoader() {
        if (globalLoader) {
            globalLoader.classList.remove('active');
            globalLoader.classList.add('hidden');
        }
        if (closeBtnContainer) {
            closeBtnContainer.classList.add('hidden');
        }
    }

    // Trigger Fast (Transition Mode)
    const btnFast = document.getElementById('btn-trigger-fast');
    if (btnFast) {
        btnFast.addEventListener('click', () => {
            showFullscreenLoader();
            
            const pageThai = 'รายละเอียดสินค้า';
            if (statusLabel && typeof window.getLoadingStatusMessage === 'function') {
                statusLabel.innerText = window.getLoadingStatusMessage(0, pageThai);
            }

            // Simulating the exact transition logic
            let progress = 0;
            const interval = setInterval(() => {
                if (progress < 90) {
                    progress += Math.floor(Math.random() * 20) + 15;
                    if (progress > 90) progress = 90;
                    if (progressFill) progressFill.style.width = progress + '%';
                    if (percentageText) percentageText.innerText = progress + '%';
                    if (statusLabel && typeof window.getLoadingStatusMessage === 'function') {
                        statusLabel.innerText = window.getLoadingStatusMessage(progress, pageThai);
                    }
                } else {
                    clearInterval(interval);
                }
            }, 60);

            setTimeout(() => {
                if (progressFill) progressFill.style.width = '100%';
                if (percentageText) percentageText.innerText = '100%';
                if (statusLabel && typeof window.getLoadingStatusMessage === 'function') {
                    statusLabel.innerText = window.getLoadingStatusMessage(100, pageThai);
                }
                setTimeout(() => {
                    hideFullscreenLoader();
                    Swal.fire({
                        icon: 'success',
                        title: 'ทดสอบการโหลดสำเร็จ',
                        text: 'ตัวอย่างแถบโหลดความคืบหน้าเมื่อใช้ย้ายหน้าจริง',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }, 150);
            }, 500);
        });
    }

    // Trigger Medium (2.5 seconds)
    const btnMedium = document.getElementById('btn-trigger-medium');
    if (btnMedium) {
        btnMedium.addEventListener('click', () => {
            showFullscreenLoader();
            
            const pageThai = 'ตะกร้าสินค้า';
            if (statusLabel && typeof window.getLoadingStatusMessage === 'function') {
                statusLabel.innerText = window.getLoadingStatusMessage(0, pageThai);
            }
            
            let progress = 0;
            const interval = setInterval(() => {
                if (progress < 95) {
                    progress += Math.floor(Math.random() * 6) + 3;
                    if (progress > 95) progress = 95;
                    if (progressFill) progressFill.style.width = progress + '%';
                    if (percentageText) percentageText.innerText = progress + '%';
                    if (statusLabel && typeof window.getLoadingStatusMessage === 'function') {
                        statusLabel.innerText = window.getLoadingStatusMessage(progress, pageThai);
                    }
                } else {
                    clearInterval(interval);
                }
            }, 100);

            setTimeout(() => {
                clearInterval(interval);
                if (progressFill) progressFill.style.width = '100%';
                if (percentageText) percentageText.innerText = '100%';
                if (statusLabel && typeof window.getLoadingStatusMessage === 'function') {
                    statusLabel.innerText = window.getLoadingStatusMessage(100, pageThai);
                }
                setTimeout(() => {
                    hideFullscreenLoader();
                }, 200);
            }, 2500);
        });
    }

    // Trigger Slow (5 seconds)
    const btnSlow = document.getElementById('btn-trigger-slow');
    if (btnSlow) {
        btnSlow.addEventListener('click', () => {
            showFullscreenLoader();
            
            const pageThai = 'ระบบแอดมิน';
            if (statusLabel && typeof window.getLoadingStatusMessage === 'function') {
                statusLabel.innerText = window.getLoadingStatusMessage(0, pageThai);
            }
            
            let progress = 0;
            const interval = setInterval(() => {
                if (progress < 98) {
                    progress += Math.floor(Math.random() * 4) + 1;
                    if (progress > 98) progress = 98;
                    if (progressFill) progressFill.style.width = progress + '%';
                    if (percentageText) percentageText.innerText = progress + '%';
                    if (statusLabel && typeof window.getLoadingStatusMessage === 'function') {
                        statusLabel.innerText = window.getLoadingStatusMessage(progress, pageThai);
                    }
                } else {
                    clearInterval(interval);
                }
            }, 80);

            setTimeout(() => {
                clearInterval(interval);
                if (progressFill) progressFill.style.width = '100%';
                if (percentageText) percentageText.innerText = '100%';
                if (statusLabel && typeof window.getLoadingStatusMessage === 'function') {
                    statusLabel.innerText = window.getLoadingStatusMessage(100, pageThai);
                }
                setTimeout(() => {
                    hideFullscreenLoader();
                }, 200);
            }, 5000);
        });
    }

    // Trigger Manual / Indefinite
    const btnManual = document.getElementById('btn-trigger-manual');
    if (btnManual) {
        btnManual.addEventListener('click', () => {
            showFullscreenLoader();
            
            const pageThai = 'แดชบอร์ดผู้ดูแล';
            if (progressFill) progressFill.style.width = '75%';
            if (percentageText) percentageText.innerText = '75%';
            
            if (statusLabel && typeof window.getLoadingStatusMessage === 'function') {
                statusLabel.innerText = window.getLoadingStatusMessage(75, pageThai) + ' (โหมดจำลองแมนนวล)';
            } else if (statusLabel) {
                statusLabel.innerText = 'กำลังโหลดข้อมูล... 75% (โหมดจำลองแมนนวล)';
            }
            
            // Show floating close button
            if (closeBtnContainer) {
                closeBtnContainer.classList.remove('hidden');
            }
        });
    }

    // Close button for manual fullscreen test
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            hideFullscreenLoader();
        });
    }
});
</script>

<footer class="py-4 bg-white border-top mt-5 text-center text-muted small">
    <div class="container">© 2026 Por Mae Bet Taled. All rights reserved.</div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
