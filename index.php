<?php
// ========================================================
// SwiftScan Express - Public Landing & Tracking Portal
// (index.php)
// ========================================================
$pageTitle = "SwiftScan Express - Smart QR Logistics & Parcel Courier Network";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero & Instant Tracking Section -->
<section class="py-5" style="background: radial-gradient(circle at top right, #111827 0%, #0b0f19 100%);">
    <div class="container py-lg-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35);">
                    <i class="bi bi-qr-code" style="color: var(--primary);"></i>
                    <span class="small fw-semibold" style="color: var(--primary);">Automated QR Waybill & Live Transit Tracking</span>
                </div>
                <h1 class="display-5 fw-extrabold text-white lh-sm mb-3">
                    Fast, Traceable & Smart <span style="color: var(--primary);">Courier Service</span>
                </h1>
                <p class="lead text-light opacity-75 mb-4 fs-6">
                    Track consignments with millimeter precision. Generate dynamic QR waybills, monitor interstate sorting hubs in real-time, and guarantee delivery with customer OTP proof-of-delivery.
                </p>

                <!-- Tracking Bar Box -->
                <div class="p-3 p-md-4 rounded-4 shadow-lg" style="background: rgba(255, 255, 255, 0.07); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.15);">
                    <form id="trackingForm" onsubmit="return false;">
                        <label class="form-label text-white small fw-bold mb-2">
                            <i class="bi bi-search me-1" style="color: var(--primary);"></i> Enter Waybill / Tracking ID
                        </label>
                        <div class="input-group input-group-lg mb-3">
                            <span class="input-group-text bg-white border-0 text-muted">
                                <i class="bi bi-upc-scan fs-5 text-secondary"></i>
                            </span>
                            <input type="text" id="trackingInput" class="form-control border-0 font-monospace text-uppercase" placeholder="e.g. CR-2026-98214" aria-label="Tracking Number" required>
                            
                            <!-- Camera QR Scan Trigger -->
                            <button type="button" class="btn btn-dark border-0 px-3 d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#globalQrScannerModal" title="Scan QR Code with Camera">
                                <i class="bi bi-camera-fill fs-5" style="color: var(--primary);"></i>
                                <span class="d-none d-md-inline small fw-semibold">Scan QR</span>
                            </button>

                            <!-- Submit Search Button -->
                            <button class="btn btn-express px-4 fw-bold d-flex align-items-center gap-2" type="button" id="btnTrackSubmit">
                                <span>Track</span>
                                <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </form>

                    <!-- Quick Demo Numbers -->
                    <div class="d-flex flex-wrap align-items-center gap-2 pt-1">
                        <span class="small text-secondary fw-semibold">Try sample shipments:</span>
                        <button type="button" class="btn btn-sm btn-outline-light py-0 px-2 rounded-pill font-monospace small demo-track-pill" data-code="CR-2026-98214">
                            CR-2026-98214 <span class="badge bg-success ms-1">Delivered</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-light py-0 px-2 rounded-pill font-monospace small demo-track-pill" data-code="CR-2026-44109">
                            CR-2026-44109 <span class="badge bg-info text-dark ms-1">In Transit</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-light py-0 px-2 rounded-pill font-monospace small demo-track-pill" data-code="CR-2026-11852">
                            CR-2026-11852 <span class="badge bg-warning text-dark ms-1">COD Pending</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Graphic / Quick Highlights -->
            <div class="col-lg-5">
                <div class="p-4 rounded-4" style="background: rgba(11, 15, 25, 0.7); border: 1px solid rgba(16, 185, 129, 0.3);">
                    <div class="d-flex align-items-center justify-content-between pb-3 border-bottom border-secondary mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="p-2 rounded-3 text-white" style="background: var(--primary);">
                                <i class="bi bi-box-seam-fill fs-5"></i>
                            </span>
                            <div>
                                <h6 class="mb-0 text-white fw-bold">Live Fleet Status</h6>
                                <small class="text-secondary">Dispatch network online</small>
                            </div>
                        </div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <i class="bi bi-broadcast me-1"></i> Active
                        </span>
                    </div>

                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-dark border border-secondary">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle p-2 bg-secondary-subtle text-warning">
                                    <i class="bi bi-qr-code fs-5"></i>
                                </div>
                                <div>
                                    <div class="text-white fw-bold small">Instant QR Generation</div>
                                    <div class="text-muted small">Auto-generated printable waybill</div>
                                </div>
                            </div>
                            <span class="badge bg-secondary font-monospace">&lt; 0.2s</span>
                        </div>

                        <div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-dark border border-secondary">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle p-2 bg-secondary-subtle text-warning">
                                    <i class="bi bi-shield-lock-fill fs-5"></i>
                                </div>
                                <div>
                                    <div class="text-white fw-bold small">Doorstep OTP Verification</div>
                                    <div class="text-muted small">Zero fraud Proof-of-Delivery</div>
                                </div>
                            </div>
                            <span class="badge bg-secondary font-monospace">4-Digit</span>
                        </div>

                        <div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-dark border border-secondary">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle p-2 bg-secondary-subtle text-warning">
                                    <i class="bi bi-wallet2 fs-5"></i>
                                </div>
                                <div>
                                    <div class="text-white fw-bold small">Flexible Payments</div>
                                    <div class="text-muted small">Prepaid Online or Doorstep COD</div>
                                </div>
                            </div>
                            <span class="badge bg-secondary font-monospace">Dual Mode</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Tracking Results Container (Populated via AJAX in Step 3) -->
<section id="trackingResultSection" class="py-5 d-none bg-white border-bottom">
    <div class="container">
        <div id="trackingResultContainer">
            <!-- Content will be rendered here dynamically -->
        </div>
    </div>
</section>

<!-- Key System Capabilities -->
<section class="py-5 bg-light">
    <div class="container py-lg-4">
        <div class="text-center max-w-700 mx-auto mb-5">
            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-1 rounded-pill fw-bold mb-2">
                Engineered for High-Velocity Logistics
            </span>
            <h2 class="fw-extrabold text-dark">Smart Solutions for Senders & Couriers</h2>
            <p class="text-muted">Explore how our dynamic QR code architecture automates every milestone from parcel pickup to final doorstep handoff.</p>
        </div>

        <div class="row g-4">
            <!-- Feature 1 -->
            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon-box">
                        <i class="bi bi-qr-code-scan"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Dynamic QR Waybill</h5>
                    <p class="text-muted small mb-0">
                        Every booking generates an encoded high-density QR waybill containing routing hubs, sender, receiver and shipment metadata.
                    </p>
                </div>
            </div>

            <!-- Feature 2 -->
            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon-box">
                        <i class="bi bi-building"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Intelligent Hub Routing</h5>
                    <p class="text-muted small mb-0">
                        Sorting center staff quickly scan incoming shipments to log arrivals, route interstate transfers, and dispatch outbound vans.
                    </p>
                </div>
            </div>

            <!-- Feature 3 -->
            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon-box">
                        <i class="bi bi-phone"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Driver Camera Scanner</h5>
                    <p class="text-muted small mb-0">
                        Drivers use their smartphone camera via WebRTC HTML5 scanner to inspect waybills, collect signatures, and record delivery.
                    </p>
                </div>
            </div>

            <!-- Feature 4 -->
            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon-box">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h5 class="fw-bold mb-2">OTP Proof of Delivery</h5>
                    <p class="text-muted small mb-0">
                        Recipients provide a secret 4-digit code generated at booking. Delivery cannot be marked completed without valid verification.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Network Statistics Counter -->
<section class="py-5 bg-dark text-white border-top border-bottom border-secondary">
    <div class="container py-3">
        <div class="row text-center g-4">
            <div class="col-6 col-md-3">
                <div class="display-6 fw-extrabold text-warning">99.8%</div>
                <div class="small text-secondary text-uppercase fw-semibold mt-1">On-Time Arrival</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="display-6 fw-extrabold text-white">4 Major</div>
                <div class="small text-secondary text-uppercase fw-semibold mt-1">Interstate Hubs</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="display-6 fw-extrabold text-warning">&lt; 3 Sec</div>
                <div class="small text-secondary text-uppercase fw-semibold mt-1">QR Scan Speed</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="display-6 fw-extrabold text-white">100%</div>
                <div class="small text-secondary text-uppercase fw-semibold mt-1">OTP Secure POD</div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action / Portals Section -->
<section class="py-5 bg-white">
    <div class="container py-lg-4">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="p-4 p-lg-5 rounded-4 h-100 d-flex flex-column justify-content-between" style="background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%); border: 1px solid #fed7aa;">
                    <div>
                        <span class="badge bg-warning text-dark fw-bold mb-2">For Customers</span>
                        <h3 class="fw-bold text-dark">Need to Send a Parcel Today?</h3>
                        <p class="text-secondary mb-4">
                            Register your account in seconds, calculate instant shipping rates by package weight, print your QR waybill sticker, and choose online card checkout or Cash on Delivery.
                        </p>
                    </div>
                    <div class="d-flex gap-3">
                        <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-express px-4 py-2">
                            Create Free Account <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                        <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-outline-dark px-4 py-2">
                            Customer Login
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="p-4 p-lg-5 rounded-4 h-100 d-flex flex-column justify-content-between bg-dark text-white border border-secondary">
                    <div>
                        <span class="badge bg-secondary text-warning fw-bold mb-2">Operations Gateway</span>
                        <h3 class="fw-bold text-white">Logistics Staff & Dispatch Center</h3>
                        <p class="text-secondary mb-4">
                            Authorized access for Delivery Drivers, Sorting Hub Managers, and Command Center System Administrators. Fast camera QR scanning and route dispatching.
                        </p>
                    </div>
                    <div>
                        <a href="<?php echo BASE_URL; ?>staff_login.php" class="btn btn-warning px-4 py-2 text-dark fw-bold">
                            <i class="bi bi-shield-lock-fill me-2"></i> Access Staff Gateway
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
