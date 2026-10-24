<?php
// ========================================================
// Main Page Footer (includes/footer.php)
// Includes Bootstrap 5, jQuery 3.7.1, QR Code & Scanner libraries
// ========================================================
?>
</main>

<footer class="bg-dark text-white pt-5 pb-4 mt-auto border-top border-secondary">
    <div class="container">
        <div class="row g-4 mb-4">
            <!-- Brand & Info -->
            <div class="col-lg-4 col-md-6">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="logo-badge" style="width:36px; height:36px; font-size:1.1rem;">
                        <i class="bi bi-box-seam-fill"></i>
                    </div>
                    <span class="fs-4 fw-bold text-white">Swift<span style="color: var(--primary);">Scan</span></span>
                </div>
                <p class="text-secondary small mb-3">
                    Next-generation intelligent courier network powered by dynamic QR code waybills, automated hub sorting, and instant OTP proof-of-delivery.
                </p>
                <div class="d-flex gap-2">
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle d-flex align-items-center gap-1">
                        <span class="p-1 rounded-circle bg-success"></span> System Operational
                    </span>
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                        v2.4 Express
                    </span>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-2 col-md-6">
                <h6 class="text-white fw-bold mb-3 text-uppercase fs-7 letter-spacing-1">Navigation</h6>
                <ul class="list-unstyled text-secondary small d-flex flex-column gap-2 mb-0">
                    <li><a href="<?php echo BASE_URL; ?>index.php" class="text-secondary text-decoration-none hover-link">Track Shipment</a></li>
                    <li><a href="<?php echo BASE_URL; ?>login.php" class="text-secondary text-decoration-none hover-link">Customer Sign In</a></li>
                    <li><a href="<?php echo BASE_URL; ?>register.php" class="text-secondary text-decoration-none hover-link">Register Account</a></li>
                    <li><a href="<?php echo BASE_URL; ?>staff_login.php" class="text-warning text-decoration-none hover-link fw-semibold"><i class="bi bi-shield-lock me-1"></i>Staff Gateway</a></li>
                </ul>
            </div>

            <!-- Logistics Capabilities -->
            <div class="col-lg-3 col-md-6">
                <h6 class="text-white fw-bold mb-3 text-uppercase fs-7 letter-spacing-1">Operations & Tech</h6>
                <ul class="list-unstyled text-secondary small d-flex flex-column gap-2 mb-0">
                    <li><i class="bi bi-qr-code text-warning me-1"></i> Dynamic Waybill Generator</li>
                    <li><i class="bi bi-camera-fill text-warning me-1"></i> Driver Handheld Camera Scanner</li>
                    <li><i class="bi bi-shield-check text-warning me-1"></i> 4-Digit Recipient OTP Auth</li>
                    <li><i class="bi bi-credit-card-2-front text-warning me-1"></i> Instant COD & Online Checkout</li>
                </ul>
            </div>

            <!-- Contact & Hub Hotline -->
            <div class="col-lg-3 col-md-6">
                <h6 class="text-white fw-bold mb-3 text-uppercase fs-7 letter-spacing-1">24/7 Operations Hub</h6>
                <p class="text-secondary small mb-2"><i class="bi bi-telephone-fill text-warning me-2"></i> +1 (800) 555-SWIFT</p>
                <p class="text-secondary small mb-2"><i class="bi bi-envelope-fill text-warning me-2"></i> dispatch@swiftscan.express</p>
                <p class="text-secondary small mb-0"><i class="bi bi-geo-alt-fill text-warning me-2"></i> Nationwide Central Hub Network</p>
            </div>
        </div>

        <hr class="border-secondary my-4">

        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start small text-secondary">
                &copy; <?php echo date('Y'); ?> <strong>SwiftScan Express Logistics</strong>. Built with PHP, MySQL & QR Systems.
            </div>
            <div class="col-md-6 text-center text-md-end small text-secondary mt-2 mt-md-0">
                <span>Fast. Traceable. Secure.</span>
            </div>
        </div>
    </div>
</footer>

<!-- Global Camera QR Code Scanner Modal -->
<div class="modal fade" id="globalQrScannerModal" tabindex="-1" aria-labelledby="globalQrScannerLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fs-6 fw-bold d-flex align-items-center gap-2" id="globalQrScannerLabel">
                    <i class="bi bi-camera-fill text-warning"></i> Scan Parcel Waybill QR Code
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" id="btnCloseQrScanner"></button>
            </div>
            <div class="modal-body text-center p-4">
                <p class="small text-muted mb-3">Align the parcel QR code within the camera frame below.</p>
                <div id="qr-camera-reader" style="width: 100%; min-height: 280px; background: #0f172a; border-radius: 8px; overflow: hidden;" class="mx-auto border"></div>
                <div id="qr-scan-feedback" class="small mt-3 text-muted">Point your camera at a waybill QR code...</div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close Scanner</button>
            </div>
        </div>
    </div>
</div>

<!-- jQuery 3.7.1 -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

<!-- Bootstrap 5.3.3 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Client-side QR Code Generation Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<!-- Web Camera QR Code Scanner Library -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<!-- Main Application JS -->
<script src="<?php echo BASE_URL; ?>assets/js/app.js"></script>

</body>
</html>
