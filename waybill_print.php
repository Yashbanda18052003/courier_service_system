<?php
// ========================================================
// SwiftScan Express - Dynamic QR Code Waybill & Print Layout
// (waybill_print.php)
// Step 7: Standard 4x6 Courier Thermal Waybill Sticker
// ========================================================
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_guard.php';

if (!defined('BASE_URL')) {
    define('BASE_URL', '/courier_service_system/');
}

$trackingNumber = trim($_GET['code'] ?? $_GET['tracking'] ?? $_GET['tracking_number'] ?? '');
$parcelId       = (int)($_GET['parcel_id'] ?? 0);

$pdo = getDB();
$sql = "
    SELECT 
        p.*,
        oh.hub_name AS origin_hub_name,
        oh.hub_code AS origin_hub_code,
        oh.city AS origin_hub_city,
        oh.state AS origin_hub_state,
        dh.hub_name AS dest_hub_name,
        dh.hub_code AS dest_hub_code,
        dh.city AS dest_hub_city,
        dh.state AS dest_hub_state,
        pm.transaction_id,
        pm.paid_at,
        pm.card_last4
    FROM parcels p
    LEFT JOIN hubs oh ON p.origin_hub_id = oh.id
    LEFT JOIN hubs dh ON p.destination_hub_id = dh.id
    LEFT JOIN payments pm ON p.id = pm.parcel_id
    WHERE (p.id = :id OR UPPER(p.tracking_number) = UPPER(:tn))
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $parcelId, ':tn' => $trackingNumber]);
$parcel = $stmt->fetch();

$pageTitle = $parcel 
    ? "Waybill #{$parcel['tracking_number']} - SwiftScan Express" 
    : "Waybill Not Found - SwiftScan Express";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Logistics Theme CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">

    <style>
        body {
            background-color: #f1f5f9;
        }
        @media print {
            body {
                background-color: #ffffff;
            }
        }
    </style>
</head>
<body class="py-4">

<?php if (!$parcel): ?>
    <!-- Error / Not Found View -->
    <div class="container py-5 text-center">
        <div class="max-w-500 mx-auto bg-white p-5 rounded-4 shadow-sm border">
            <i class="bi bi-exclamation-triangle-fill text-danger display-3 mb-3"></i>
            <h4 class="fw-bold text-dark">Waybill Not Found</h4>
            <p class="text-muted small">No parcel shipment record was found matching this tracking reference.</p>
            <div class="d-flex justify-content-center gap-2 mt-4">
                <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-express px-4">
                    <i class="bi bi-search me-1"></i> Track Parcel
                </a>
                <a href="<?php echo BASE_URL; ?>customer/index.php" class="btn btn-outline-secondary px-3">
                    Dashboard
                </a>
            </div>
        </div>
    </div>
<?php else: ?>

    <!-- Print Navigation Bar (Hidden on print) -->
    <div class="container shipping-label-container mb-3 no-print">
        <div class="d-flex align-items-center justify-content-between bg-dark text-white p-3 rounded-3 shadow-sm">
            <div class="d-flex align-items-center gap-2">
                <div class="p-1 rounded bg-warning text-dark">
                    <i class="bi bi-box-seam-fill fs-5"></i>
                </div>
                <div>
                    <div class="fw-bold small text-white">4" x 6" Waybill Sticker</div>
                    <div class="text-secondary" style="font-size: 0.72rem;">Thermal & Standard Laser Format</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-warning btn-sm fw-bold px-3 d-flex align-items-center gap-1 shadow" onclick="window.print();">
                    <i class="bi bi-printer-fill"></i>
                    <span>Print Label</span>
                </button>
                <a href="<?php echo BASE_URL; ?>index.php?track=<?php echo urlencode($parcel['tracking_number']); ?>" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-search me-1"></i> Track
                </a>
                <a href="<?php echo BASE_URL; ?>customer/index.php" class="btn btn-outline-secondary btn-sm text-light">
                    <i class="bi bi-arrow-left"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Official 4x6 Thermal Courier Shipping Label Sticker -->
    <div class="container shipping-label-container">
        <div class="shipping-label-card">
            
            <!-- SECTION 1: Brand Header & Logistics Class -->
            <div class="label-section d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-black text-white p-1 rounded d-flex align-items-center justify-content-center" style="width:32px; height:32px;">
                        <i class="bi bi-box-seam-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-extrabold text-uppercase font-monospace fs-6 lh-1">SwiftScan Express</div>
                        <div class="text-muted" style="font-size: 0.65rem;">SMART QR LOGISTICS NETWORK</div>
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge bg-black text-white font-monospace px-2 py-1 fs-7">PRIORITY EXPRESS</span>
                    <div class="text-muted font-monospace" style="font-size: 0.65rem;">
                        <?php echo date('d-M-Y H:i', strtotime($parcel['created_at'])); ?>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: Hub Routing Codes -->
            <div class="label-section bg-light py-2">
                <div class="row align-items-center g-2">
                    <div class="col-6">
                        <div class="text-uppercase text-muted" style="font-size: 0.65rem; font-weight: 700;">ORIGIN FACILITY</div>
                        <div class="fw-bold font-monospace text-dark text-truncate">
                            <?php echo htmlspecialchars($parcel['origin_hub_code'] ?? 'HUB-NYC-01'); ?>
                        </div>
                        <div class="text-muted small" style="font-size: 0.7rem;">
                            <?php echo htmlspecialchars($parcel['origin_hub_city'] ?? $parcel['sender_city']); ?>, <?php echo htmlspecialchars($parcel['origin_hub_state'] ?? 'US'); ?>
                        </div>
                    </div>
                    <div class="col-6 text-end">
                        <div class="text-uppercase text-muted" style="font-size: 0.65rem; font-weight: 700;">DESTINATION HUB</div>
                        <div class="hub-routing-box font-monospace d-inline-block">
                            <?php echo htmlspecialchars($parcel['dest_hub_code'] ?? 'HUB-LAX-02'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: Addresses (SHIP FROM & SHIP TO) -->
            <div class="label-section">
                <!-- FROM -->
                <div class="mb-3 pb-2 border-bottom">
                    <div class="text-uppercase text-muted fw-bold" style="font-size: 0.65rem;">SHIP FROM:</div>
                    <div class="fw-bold text-dark small"><?php echo htmlspecialchars($parcel['sender_name']); ?></div>
                    <div class="text-muted small" style="font-size: 0.78rem; line-height: 1.3;">
                        <?php echo htmlspecialchars($parcel['sender_address']); ?><br>
                        <?php echo htmlspecialchars($parcel['sender_city']); ?> <?php echo htmlspecialchars($parcel['sender_pincode']); ?> • Tel: <?php echo htmlspecialchars($parcel['sender_phone']); ?>
                    </div>
                </div>

                <!-- TO (Very prominent) -->
                <div>
                    <div class="text-uppercase text-dark fw-bold fs-7">SHIP TO:</div>
                    <div class="display-6 fw-extrabold text-dark font-monospace mb-1" style="font-size: 1.25rem;">
                        <?php echo htmlspecialchars($parcel['receiver_name']); ?>
                    </div>
                    <div class="fw-bold text-dark fs-6" style="line-height: 1.3;">
                        <?php echo htmlspecialchars($parcel['receiver_address']); ?>
                    </div>
                    <div class="display-6 fw-extrabold text-dark font-monospace mt-1" style="font-size: 1.35rem; letter-spacing: 0.5px;">
                        <?php echo strtoupper(htmlspecialchars($parcel['receiver_city'])); ?> <?php echo htmlspecialchars($parcel['receiver_pincode']); ?>
                    </div>
                    <div class="small text-muted mt-1">
                        Contact: <strong><?php echo htmlspecialchars($parcel['receiver_phone']); ?></strong>
                    </div>
                </div>
            </div>

            <!-- SECTION 4: Package Weight & Category -->
            <div class="label-section bg-light py-2">
                <div class="row text-center g-2 font-monospace">
                    <div class="col-4 border-end">
                        <div class="text-muted" style="font-size: 0.65rem;">WEIGHT</div>
                        <div class="fw-extrabold text-dark fs-6"><?php echo number_format((float)$parcel['weight_kg'], 2); ?> KG</div>
                    </div>
                    <div class="col-4 border-end">
                        <div class="text-muted" style="font-size: 0.65rem;">PACKAGE TYPE</div>
                        <div class="fw-bold text-dark text-truncate small" title="<?php echo htmlspecialchars($parcel['parcel_type']); ?>">
                            <?php echo htmlspecialchars($parcel['parcel_type']); ?>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted" style="font-size: 0.65rem;">PIECES</div>
                        <div class="fw-extrabold text-dark fs-6">1 of 1</div>
                    </div>
                </div>
            </div>

            <!-- SECTION 5: Dynamic High-Density QR Code & 1D Barcode -->
            <div class="label-section py-3">
                <div class="row align-items-center g-3">
                    <!-- Left: QR Code -->
                    <div class="col-5 text-center border-end">
                        <div id="waybillQrCode" class="d-inline-block p-1 bg-white border border-dark rounded"></div>
                        <div class="text-muted mt-1" style="font-size: 0.65rem; font-weight: 700;">SCAN FOR ROUTING & POD</div>
                    </div>

                    <!-- Right: Barcode & Tracking Number -->
                    <div class="col-7 text-center">
                        <div class="barcode-sim mb-2 border border-dark"></div>
                        <div class="fw-extrabold font-monospace text-dark fs-5 letter-spacing-1">
                            <?php echo htmlspecialchars($parcel['tracking_number']); ?>
                        </div>
                        <div class="text-muted text-uppercase" style="font-size: 0.65rem;">TRACKING ID / WAYBILL NUMBER</div>
                    </div>
                </div>
            </div>

            <!-- SECTION 6: Payment Notice & Doorstep OTP Instruction -->
            <div class="label-section py-2">
                <?php if ($parcel['payment_status'] === 'paid'): ?>
                    <!-- Prepaid Notice -->
                    <div class="p-2 border border-2 border-dark rounded bg-light d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-patch-check-fill fs-4 text-dark"></i>
                            <div>
                                <div class="fw-extrabold font-monospace fs-7 text-dark">PREPAID - DO NOT COLLECT CASH</div>
                                <div class="text-muted" style="font-size: 0.65rem;">TXN: <?php echo htmlspecialchars($parcel['transaction_id'] ?? 'PREPAID_ONLINE'); ?></div>
                            </div>
                        </div>
                        <span class="badge bg-black text-white font-monospace fs-7">PAID</span>
                    </div>
                <?php else: ?>
                    <!-- Cash On Delivery (COD) Notice -->
                    <div class="p-2 border border-3 border-dark rounded text-center" style="background: repeating-linear-gradient(45deg, #ffffff, #ffffff 10px, #fef08a 10px, #fef08a 20px);">
                        <div class="fw-extrabold font-monospace text-dark fs-6">
                            CASH ON DELIVERY (COD): COLLECT $<?php echo number_format((float)$parcel['shipping_cost'], 2); ?>
                        </div>
                        <div class="text-dark small fw-bold">Courier must collect exact amount before releasing shipment</div>
                    </div>
                <?php endif; ?>

                <!-- Doorstep OTP Instruction -->
                <div class="mt-2 text-center text-muted" style="font-size: 0.68rem; line-height: 1.2;">
                    <strong>SECURITY INSTRUCTION:</strong> Courier must scan this waybill QR code and verify the recipient's 4-digit secret OTP to record successful delivery.
                </div>
            </div>

        </div>
    </div>

    <!-- Client-side QR Code Generation Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Universal tracking link encoded inside QR code
        // Any phone camera opening this URL routes directly to the live tracking page!
        const trackingUrl = "<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . 'index.php?track=' . urlencode($parcel['tracking_number']); ?>";

        const qrContainer = document.getElementById('waybillQrCode');
        if (qrContainer && typeof QRCode !== 'undefined') {
            new QRCode(qrContainer, {
                text: trackingUrl,
                width: 135,
                height: 135,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        }

        // Check if autoprint query parameter was passed: ?autoprint=1
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('autoprint') === '1') {
            setTimeout(function() {
                window.print();
            }, 600);
        }
    });
    </script>

<?php endif; ?>

</body>
</html>
