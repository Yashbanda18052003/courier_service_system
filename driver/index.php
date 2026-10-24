<?php
// ========================================================
// SwiftScan Express - Delivery Driver Run Sheet & Scanner
// (driver/index.php)
// Step 9: Live Camera QR Scanner, Active Run Sheet & Doorstep OTP POD
// ========================================================
$pageTitle = "Driver Run Sheet & Delivery Terminal - SwiftScan Express";
require_once __DIR__ . '/../includes/auth_guard.php';

requireRole(['driver', 'admin']);

$currentUser = getCurrentUser();
$driverId    = (int)$currentUser['id'];

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();

// 1. Fetch Active Run Sheet Parcels (Out for Delivery assigned to this driver)
$activeStmt = $pdo->prepare("
    SELECT p.*, oh.hub_name AS origin_hub, dh.hub_name AS dest_hub
    FROM parcels p
    LEFT JOIN hubs oh ON p.origin_hub_id = oh.id
    LEFT JOIN hubs dh ON p.destination_hub_id = dh.id
    WHERE p.assigned_driver_id = :driver_id AND p.status = 'out_for_delivery'
    ORDER BY p.id DESC
");
$activeStmt->execute([':driver_id' => $driverId]);
$activeParcels = $activeStmt->fetchAll();

// 2. Fetch Available Hub Parcels (Ready for driver to claim and add to van)
$availableStmt = $pdo->prepare("
    SELECT p.*, oh.hub_name AS origin_hub, dh.hub_name AS dest_hub
    FROM parcels p
    LEFT JOIN hubs oh ON p.origin_hub_id = oh.id
    LEFT JOIN hubs dh ON p.destination_hub_id = dh.id
    WHERE (p.assigned_driver_id IS NULL OR p.assigned_driver_id = :driver_id)
      AND p.status IN ('booked', 'picked_up', 'in_transit')
    ORDER BY p.id DESC
");
$availableStmt->execute([':driver_id' => $driverId]);
$availableParcels = $availableStmt->fetchAll();

// 3. Fetch Completed Today Deliveries
$deliveredStmt = $pdo->prepare("
    SELECT p.*, pod.recipient_name AS pod_recipient, pod.delivered_at AS pod_delivered_at, pod.payment_collected
    FROM parcels p
    LEFT JOIN proof_of_delivery pod ON p.id = pod.parcel_id
    WHERE p.assigned_driver_id = :driver_id AND p.status = 'delivered'
    ORDER BY p.delivered_at DESC
");
$deliveredStmt->execute([':driver_id' => $driverId]);
$deliveredParcels = $deliveredStmt->fetchAll();

// Calculate total COD pending collection on active run sheet
$pendingCodTotal = 0.0;
foreach ($activeParcels as $ap) {
    if ($ap['payment_method'] === 'cod' && $ap['payment_status'] !== 'paid') {
        $pendingCodTotal += (float)$ap['shipping_cost'];
    }
}
?>

<!-- Driver Console Top Header -->
<div class="py-4 bg-dark text-white border-bottom border-secondary">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded-circle text-white shadow-sm" style="background: var(--primary);">
                    <i class="bi bi-bicycle fs-2"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success-subtle text-success font-monospace fw-bold">COURIER DISPATCH TERMINAL</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <i class="bi bi-broadcast me-1"></i> GPS Active
                        </span>
                    </div>
                    <h2 class="fw-extrabold text-white mb-0 mt-1"><?php echo htmlspecialchars($currentUser['full_name']); ?></h2>
                    <small class="text-secondary">Assigned Facility: <?php echo htmlspecialchars($currentUser['hub_name'] ?? 'Metro Delivery Fleet'); ?></small>
                </div>
            </div>

            <!-- Quick Action: Launch Camera QR Scanner -->
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-express fw-bold px-4 py-2 d-flex align-items-center gap-2 shadow" id="btnOpenDriverQr">
                    <i class="bi bi-camera-fill fs-5"></i>
                    <span>Scan Waybill QR</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Driver Metrics Counter -->
<div class="container py-4">
    <div class="row g-3 mb-4">
        <!-- Active On Run Sheet -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-warning border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Active Deliveries</div>
                        <div class="fs-3 fw-extrabold text-dark mt-1" id="statActiveCount"><?php echo count($activeParcels); ?></div>
                    </div>
                    <div class="p-3 rounded-circle bg-light text-warning">
                        <i class="bi bi-truck fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delivered Today -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-success border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Delivered (POD)</div>
                        <div class="fs-3 fw-extrabold text-success mt-1" id="statDeliveredCount"><?php echo count($deliveredParcels); ?></div>
                    </div>
                    <div class="p-3 rounded-circle bg-light text-success">
                        <i class="bi bi-patch-check-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available to Claim -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-primary border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Available in Hub</div>
                        <div class="fs-3 fw-extrabold text-primary mt-1"><?php echo count($availableParcels); ?></div>
                    </div>
                    <div class="p-3 rounded-circle bg-light text-primary">
                        <i class="bi bi-inbox-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- COD Cash to Collect -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-danger border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Pending COD Cash</div>
                        <div class="fs-3 fw-extrabold text-danger mt-1 font-monospace" id="statCodTotal">$<?php echo number_format($pendingCodTotal, 2); ?></div>
                    </div>
                    <div class="p-3 rounded-circle bg-light text-danger">
                        <i class="bi bi-cash-stack fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Manual Quick Search Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-white">
        <form id="driverSearchForm" class="row g-2 align-items-center">
            <div class="col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0 font-monospace text-uppercase" id="driverManualSearchInput" placeholder="Enter Waybill # or scan QR code to inspect (e.g. CR-2026-11852)">
                </div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-express w-100 fw-bold">
                    <i class="bi bi-arrow-right-circle me-1"></i> Inspect Waybill
                </button>
            </div>
        </form>
    </div>

    <!-- Tabbed Run Sheet Navigation -->
    <ul class="nav nav-pills nav-fill mb-3 bg-white p-2 rounded-4 shadow-sm border" id="driverTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold py-2" id="tab-active-btn" data-bs-toggle="pill" data-bs-target="#tab-active" type="button" role="tab">
                <i class="bi bi-truck me-1"></i> Active Run Sheet (<?php echo count($activeParcels); ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold py-2" id="tab-available-btn" data-bs-toggle="pill" data-bs-target="#tab-available" type="button" role="tab">
                <i class="bi bi-inbox me-1"></i> Hub Inventory (<?php echo count($availableParcels); ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold py-2" id="tab-completed-btn" data-bs-toggle="pill" data-bs-target="#tab-completed" type="button" role="tab">
                <i class="bi bi-check-circle me-1"></i> Completed Deliveries (<?php echo count($deliveredParcels); ?>)
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="driverTabContent">

        <!-- TAB 1: Active Run Sheet -->
        <div class="tab-pane fade show active" id="tab-active" role="tabpanel">
            <?php if (empty($activeParcels)): ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                    <div class="d-inline-flex p-3 rounded-circle bg-light text-muted mb-2">
                        <i class="bi bi-clipboard-check display-4 text-warning"></i>
                    </div>
                    <h5 class="fw-bold text-dark">No Active Parcels on Run Sheet</h5>
                    <p class="text-muted small max-w-500 mx-auto">
                        Your vehicle delivery list is currently clear. Switch to the <strong>Hub Inventory</strong> tab to assign ready packages to your route, or scan a package waybill QR code.
                    </p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($activeParcels as $p): ?>
                        <div class="col-md-6 col-lg-4" id="parcel-card-<?php echo $p['id']; ?>">
                            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white border-top border-4 border-warning">
                                <div class="card-body p-4 d-flex flex-column justify-content-between">
                                    <div>
                                        <!-- Header: Code & Payment Badge -->
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="font-monospace fw-bold text-dark fs-6"><?php echo htmlspecialchars($p['tracking_number']); ?></span>
                                            <?php if ($p['payment_method'] === 'cod' && $p['payment_status'] !== 'paid'): ?>
                                                <span class="badge bg-danger text-white">COD: $<?php echo number_format((float)$p['shipping_cost'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-success">PREPAID</span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Recipient Details -->
                                        <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($p['receiver_name']); ?></h5>
                                        <p class="small text-muted mb-2">
                                            <i class="bi bi-geo-alt-fill text-warning me-1"></i>
                                            <strong><?php echo htmlspecialchars($p['receiver_address']); ?></strong><br>
                                            <span class="ms-3"><?php echo htmlspecialchars($p['receiver_city']); ?> <?php echo htmlspecialchars($p['receiver_pincode']); ?></span>
                                        </p>

                                        <!-- Specs & Contact -->
                                        <div class="d-flex align-items-center gap-2 mb-3">
                                            <a href="tel:<?php echo htmlspecialchars($p['receiver_phone']); ?>" class="btn btn-sm btn-outline-dark py-1 px-2 rounded-pill small">
                                                <i class="bi bi-telephone-fill text-success me-1"></i> Call Recipient
                                            </a>
                                            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($p['receiver_address'] . ', ' . $p['receiver_city']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-1 px-2 rounded-pill small">
                                                <i class="bi bi-map-fill text-primary me-1"></i> Map
                                            </a>
                                            <span class="small text-muted ms-auto"><?php echo number_format((float)$p['weight_kg'], 2); ?> kg</span>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="d-flex gap-2 pt-3 border-top">
                                        <button type="button" class="btn btn-express btn-sm w-100 fw-bold py-2 btn-open-pod shadow-sm"
                                                data-id="<?php echo $p['id']; ?>"
                                                data-tracking="<?php echo htmlspecialchars($p['tracking_number']); ?>"
                                                data-receiver="<?php echo htmlspecialchars($p['receiver_name']); ?>"
                                                data-address="<?php echo htmlspecialchars($p['receiver_address'] . ', ' . $p['receiver_city']); ?>"
                                                data-iscod="<?php echo ($p['payment_method'] === 'cod' && $p['payment_status'] !== 'paid') ? '1' : '0'; ?>"
                                                data-codamount="<?php echo number_format((float)$p['shipping_cost'], 2); ?>">
                                            <i class="bi bi-shield-check me-1"></i> Deliver (Verify OTP)
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-mark-failed" data-id="<?php echo $p['id']; ?>" title="Mark Attempt Failed">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB 2: Hub Inventory (Available to Claim) -->
        <div class="tab-pane fade" id="tab-available" role="tabpanel">
            <?php if (empty($availableParcels)): ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                    <i class="bi bi-check-all display-4 text-success mb-2"></i>
                    <h5 class="fw-bold">No Consignments Waiting at Hub</h5>
                    <p class="text-muted small">All hub sorting packages have already been assigned to delivery vehicles.</p>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-4">Waybill Number</th>
                                    <th>Recipient & Address</th>
                                    <th>Destination City</th>
                                    <th>Package Type</th>
                                    <th>Payment</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($availableParcels as $ap): ?>
                                    <tr id="avail-row-<?php echo $ap['id']; ?>">
                                        <td class="ps-4">
                                            <span class="font-monospace fw-bold"><?php echo htmlspecialchars($ap['tracking_number']); ?></span>
                                            <div class="text-muted small">Status: <?php echo htmlspecialchars($ap['status']); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($ap['receiver_name']); ?></div>
                                            <div class="small text-muted text-truncate" style="max-width: 220px;"><?php echo htmlspecialchars($ap['receiver_address']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars($ap['receiver_city']); ?></span>
                                        </td>
                                        <td>
                                            <span class="small"><?php echo htmlspecialchars($ap['parcel_type']); ?> (<?php echo number_format((float)$ap['weight_kg'], 2); ?> kg)</span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $ap['payment_method'] === 'cod' ? 'bg-warning text-dark' : 'bg-primary'; ?>">
                                                <?php echo strtoupper($ap['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <button type="button" class="btn btn-sm btn-express py-1 px-3 btn-claim-parcel fw-bold" data-id="<?php echo $ap['id']; ?>" data-tracking="<?php echo htmlspecialchars($ap['tracking_number']); ?>">
                                                <i class="bi bi-plus-circle me-1"></i> Add to Run Sheet
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB 3: Completed Deliveries -->
        <div class="tab-pane fade" id="tab-completed" role="tabpanel">
            <?php if (empty($deliveredParcels)): ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                    <i class="bi bi-clock-history display-4 text-muted mb-2"></i>
                    <h5 class="fw-bold">No Completed Deliveries Yet</h5>
                    <p class="text-muted small">Deliveries confirmed with customer OTP will be archived here.</p>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-4">Waybill</th>
                                    <th>Delivered To</th>
                                    <th>Verification</th>
                                    <th>Delivered At</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deliveredParcels as $dp): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <span class="font-monospace fw-bold"><?php echo htmlspecialchars($dp['tracking_number']); ?></span>
                                            <div class="small text-muted"><?php echo htmlspecialchars($dp['receiver_city']); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($dp['pod_recipient'] ?? $dp['receiver_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($dp['receiver_address']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                <i class="bi bi-shield-check me-1"></i> 4-Digit OTP Verified
                                            </span>
                                        </td>
                                        <td>
                                            <div class="small text-dark font-monospace">
                                                <?php echo $dp['delivered_at'] ? date('M d, Y • h:i A', strtotime($dp['delivered_at'])) : 'Verified'; ?>
                                            </div>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="<?php echo BASE_URL; ?>index.php?track=<?php echo urlencode($dp['tracking_number']); ?>" class="btn btn-sm btn-outline-dark py-1">
                                                <i class="bi bi-search me-1"></i> View Audit
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Handheld Camera QR Scanner Modal -->
<div class="modal fade" id="driverCameraScannerModal" tabindex="-1" aria-labelledby="driverCameraScannerModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-camera-fill text-warning fs-5"></i>
                    <h5 class="modal-title fs-6 fw-bold mb-0">Scan Parcel Waybill QR</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" id="btnCloseDriverCamera"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div id="driver-camera-reader" style="width: 100%; min-height: 280px; background: #0f172a; border-radius: 8px; overflow: hidden;" class="border mx-auto"></div>
                <div id="driver-camera-feedback" class="small mt-3 text-muted">Align the waybill QR code within the view frame...</div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close Scanner</button>
            </div>
        </div>
    </div>
</div>

<!-- Doorstep OTP & POD Verification Modal -->
<div class="modal fade" id="driverPodModal" tabindex="-1" aria-labelledby="driverPodModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 text-white py-3 px-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 rounded-circle bg-warning text-dark">
                        <i class="bi bi-shield-check fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0">Doorstep Proof of Delivery</h5>
                        <small class="text-secondary">Secret OTP Verification & Sign-off</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <!-- Parcel Details Summary -->
                <div class="p-3 rounded-3 bg-light border mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small">Waybill Consignment:</span>
                        <span class="fw-bold font-monospace text-dark fs-6" id="podModalTracking">CR-2026-XXXXX</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Delivery Address:</span>
                        <span class="fw-semibold text-dark small text-truncate" style="max-width: 230px;" id="podModalAddress">Address</span>
                    </div>
                </div>

                <!-- COD Alert Banner (Hidden if Prepaid) -->
                <div id="podCodAlertBox" class="alert alert-warning border-0 p-3 rounded-3 mb-3 d-none">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-cash-stack fs-3 text-warning"></i>
                        <div>
                            <div class="fw-bold text-dark">CASH ON DELIVERY (COD) DUE</div>
                            <div class="small text-dark">Collect <strong class="fs-6 font-monospace" id="podModalCodAmount">$0.00</strong> in cash before handoff.</div>
                        </div>
                    </div>
                    <div class="form-check mt-2 pt-2 border-top border-warning-subtle">
                        <input class="form-check-input" type="checkbox" id="podCodCollectedCheck" required>
                        <label class="form-check-label small fw-bold text-dark" for="podCodCollectedCheck">
                            I confirm cash has been collected in full.
                        </label>
                    </div>
                </div>

                <form id="driverPodForm" novalidate>
                    <input type="hidden" id="podParcelId" name="parcel_id" value="">

                    <!-- Recipient Name -->
                    <div class="mb-3">
                        <label for="podRecipientName" class="form-label small fw-bold text-dark mb-1">Recipient Name (Person Receiving)</label>
                        <input type="text" class="form-control" id="podRecipientName" name="recipient_name" required>
                    </div>

                    <!-- Secret 4-Digit OTP Input -->
                    <div class="mb-3">
                        <label for="podOtpInput" class="form-label small fw-bold text-dark mb-1 d-flex justify-content-between">
                            <span><i class="bi bi-key-fill text-warning me-1"></i> Customer 4-Digit Secret OTP</span>
                            <span class="text-muted" style="font-size: 0.72rem;">Customer received this on booking</span>
                        </label>
                        <input type="text" class="form-control form-control-lg font-monospace text-center fw-extrabold letter-spacing-3" 
                               id="podOtpInput" name="otp_entered" placeholder="••••" maxlength="4" required autocomplete="off"
                               style="font-size: 1.75rem; letter-spacing: 10px;">
                    </div>

                    <!-- Remarks -->
                    <div class="mb-4">
                        <label for="podNotes" class="form-label small text-muted mb-1">Delivery Notes (Optional)</label>
                        <input type="text" class="form-control form-control-sm" id="podNotes" name="notes" value="Handed to recipient in person">
                    </div>

                    <button type="submit" id="btnSubmitPod" class="btn btn-warning w-100 py-3 fw-bold fs-6 d-flex align-items-center justify-content-center gap-2 shadow">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>Verify OTP & Record Proof of Delivery</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let driverQrScanner = null;

    // 1. Open Camera Scanner Modal
    $('#btnOpenDriverQr').on('click', function() {
        const modal = new bootstrap.Modal(document.getElementById('driverCameraScannerModal'));
        modal.show();
    });

    $('#driverCameraScannerModal').on('shown.bs.modal', function () {
        $('#driver-camera-feedback').html('<span class="spinner-border spinner-border-sm me-1"></span> Initializing courier camera...');

        if (typeof Html5Qrcode !== 'undefined') {
            driverQrScanner = new Html5Qrcode("driver-camera-reader");
            const config = { fps: 10, qrbox: { width: 240, height: 240 } };

            driverQrScanner.start(
                { facingMode: "environment" },
                config,
                (decodedText) => {
                    // Success callback
                    $('#driver-camera-feedback').html(`<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i> Waybill Identified!</span>`);
                    
                    // Stop camera and close scanner modal
                    const modalEl = document.getElementById('driverCameraScannerModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();

                    // Look up scanned waybill
                    inspectScannedWaybill(decodedText);
                },
                (errorMessage) => {
                    // seeking QR
                }
            ).catch(err => {
                console.warn(err);
                $('#driver-camera-feedback').html('<span class="text-danger small">Camera unavailable. Please enter tracking ID manually.</span>');
            });
        }
    });

    $('#driverCameraScannerModal').on('hidden.bs.modal', function () {
        if (driverQrScanner) {
            driverQrScanner.stop().then(() => {
                driverQrScanner.clear();
                driverQrScanner = null;
            }).catch(e => console.warn(e));
        }
    });

    // 2. Manual Search Form Submission
    $('#driverSearchForm').on('submit', function(e) {
        e.preventDefault();
        const code = $('#driverManualSearchInput').val().trim();
        if (!code) {
            showToast('Please enter a waybill tracking ID.', 'warning');
            return;
        }
        inspectScannedWaybill(code);
    });

    // 3. Inspect Scanned Waybill & Trigger Relevant Action
    function inspectScannedWaybill(code) {
        showToast('Processing waybill ' + code + '...', 'info');

        $.ajax({
            url: '<?php echo BASE_URL; ?>ajax/scan_handler.php',
            type: 'GET',
            data: {
                action: 'scan_waybill',
                code: code
            },
            success: function(response) {
                if (response.status === 'success') {
                    const p = response.data;

                    if (p.is_delivered) {
                        showToast(`Shipment ${p.tracking_number} is already delivered!`, 'warning');
                        return;
                    }

                    // Open POD Modal
                    openPodModal(p.id, p.tracking_number, p.receiver_name, p.receiver_address + ', ' + p.receiver_city, p.is_cod, p.shipping_cost);
                } else {
                    showToast(response.message || 'Waybill not found.', 'danger');
                }
            },
            error: function() {
                showToast('Server communication error.', 'danger');
            }
        });
    }

    // 4. Open POD Modal Helper
    function openPodModal(id, tracking, receiver, address, isCod, codAmount) {
        $('#podParcelId').val(id);
        $('#podModalTracking').text(tracking);
        $('#podRecipientName').val(receiver);
        $('#podModalAddress').text(address);
        $('#podOtpInput').val('');

        if (isCod) {
            $('#podCodAlertBox').removeClass('d-none');
            $('#podModalCodAmount').text(codAmount);
            $('#podCodCollectedCheck').prop('checked', false);
        } else {
            $('#podCodAlertBox').addClass('d-none');
        }

        const modal = new bootstrap.Modal(document.getElementById('driverPodModal'));
        modal.show();
        setTimeout(() => $('#podOtpInput').focus(), 400);
    }

    // Click handler for table "Deliver (Verify OTP)" buttons
    $(document).on('click', '.btn-open-pod', function() {
        const id = $(this).data('id');
        const tracking = $(this).data('tracking');
        const receiver = $(this).data('receiver');
        const address = $(this).data('address');
        const isCod = $(this).data('iscod') == '1';
        const codAmount = '$' + $(this).data('codamount');

        openPodModal(id, tracking, receiver, address, isCod, codAmount);
    });

    // 5. Submit Doorstep POD Verification
    $('#driverPodForm').on('submit', function(e) {
        e.preventDefault();

        const $btn = $('#btnSubmitPod');
        const originalBtnHtml = $btn.html();

        const isCodVisible = !$('#podCodAlertBox').hasClass('d-none');
        const codChecked = $('#podCodCollectedCheck').is(':checked');

        if (isCodVisible && !codChecked) {
            showToast('You must confirm Cash on Delivery collection before signing off.', 'warning');
            $('#podCodCollectedCheck').focus();
            return;
        }

        const otp = $('#podOtpInput').val().trim();
        if (otp.length < 4) {
            showToast('Please enter the 4-digit recipient OTP.', 'warning');
            $('#podOtpInput').focus();
            return;
        }

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Verifying OTP & Finalizing Delivery...');

        const formData = $(this).serialize() + '&action=verify_pod' + (isCodVisible ? '&cod_collected=1' : '');

        $.ajax({
            url: '<?php echo BASE_URL; ?>ajax/scan_handler.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                $btn.prop('disabled', false).html(originalBtnHtml);

                if (response.status === 'success') {
                    showToast('Delivery Confirmed! Proof of Delivery verified.', 'success');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('driverPodModal'));
                    if (modal) modal.hide();

                    setTimeout(() => window.location.reload(), 600);
                } else {
                    showToast(response.message || 'OTP verification failed.', 'danger');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalBtnHtml);
                showToast('Server error while saving delivery.', 'danger');
            }
        });
    });

    // 6. Claim Hub Parcel to Driver Run Sheet
    $(document).on('click', '.btn-claim-parcel', function() {
        const id = $(this).data('id');
        const tracking = $(this).data('tracking');
        const $btn = $(this);

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Adding...');

        $.ajax({
            url: '<?php echo BASE_URL; ?>ajax/scan_handler.php',
            type: 'POST',
            data: {
                action: 'assign_to_driver',
                parcel_id: id
            },
            success: function(response) {
                if (response.status === 'success') {
                    showToast(response.message, 'success');
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    $btn.prop('disabled', false).html('<i class="bi bi-plus-circle me-1"></i> Add to Run Sheet');
                    showToast(response.message || 'Failed to claim parcel.', 'danger');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html('<i class="bi bi-plus-circle me-1"></i> Add to Run Sheet');
                showToast('Server error.', 'danger');
            }
        });
    });

    // 7. Mark Attempt Failed
    $(document).on('click', '.btn-mark-failed', function() {
        const id = $(this).data('id');
        const reason = prompt('Please enter the reason for failed delivery attempt (e.g. Recipient not home, business closed):', 'Recipient not available at address');

        if (reason && reason.trim()) {
            $.ajax({
                url: '<?php echo BASE_URL; ?>ajax/scan_handler.php',
                type: 'POST',
                data: {
                    action: 'mark_failed',
                    parcel_id: id,
                    reason: reason.trim()
                },
                success: function(response) {
                    if (response.status === 'success') {
                        showToast(response.message, 'warning');
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        showToast(response.message, 'danger');
                    }
                }
            });
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
