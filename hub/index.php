<?php
// ========================================================
// SwiftScan Express - Sorting Hub Operations & Batch Scanner
// (hub/index.php)
// Step 10: Inbound/Outbound Batch Transit Scanner & Routing
// ========================================================
$pageTitle = "Hub Operations & Batch Transit Scanner - SwiftScan Express";
require_once __DIR__ . '/../includes/auth_guard.php';

requireRole(['hub_manager', 'admin']);

$currentUser = getCurrentUser();
$myHubId     = (int)($currentUser['hub_id'] ?? 1);

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();

// 1. Fetch Current Operating Hub Details
$hubStmt = $pdo->prepare("SELECT * FROM hubs WHERE id = :id LIMIT 1");
$hubStmt->execute([':id' => $myHubId]);
$myHub = $hubStmt->fetch() ?: [
    'id' => 1, 
    'hub_name' => 'Central Sorting Hub', 
    'hub_code' => 'HUB-NYC-01', 
    'city' => 'New York', 
    'state' => 'NY'
];

// 2. Fetch All Other Hubs for Transfer Routing
$allHubsStmt = $pdo->query("SELECT id, hub_name, hub_code, city, state FROM hubs ORDER BY city ASC");
$allHubs = $allHubsStmt->fetchAll();

// 3. Fetch Fleet Delivery Drivers
$driversStmt = $pdo->query("SELECT id, full_name, phone FROM users WHERE role = 'driver' ORDER BY full_name ASC");
$drivers = $driversStmt->fetchAll();

// 4. Fetch Parcels Currently in this Hub Facility
$inHubStmt = $pdo->prepare("
    SELECT p.*, oh.hub_name AS origin_hub, dh.hub_name AS dest_hub, dh.hub_code AS dest_hub_code, dh.city AS dest_city, d.full_name AS driver_name
    FROM parcels p
    LEFT JOIN hubs oh ON p.origin_hub_id = oh.id
    LEFT JOIN hubs dh ON p.destination_hub_id = dh.id
    LEFT JOIN users d ON p.assigned_driver_id = d.id
    WHERE p.current_hub_id = :hub_id AND p.status NOT IN ('delivered', 'returned')
    ORDER BY p.id DESC
");
$inHubStmt->execute([':hub_id' => $myHubId]);
$inHubParcels = $inHubStmt->fetchAll();

// 5. Partition Parcels into:
// A) Ready for Local Doorstep Delivery (Destination is this hub)
// B) In Transit / Needs Routing to Another Hub
$localDeliveryParcels = [];
$transitRoutingParcels = [];
$outForDeliveryParcels = [];

foreach ($inHubParcels as $p) {
    if ($p['status'] === 'out_for_delivery') {
        $outForDeliveryParcels[] = $p;
    } elseif ((int)$p['destination_hub_id'] === $myHubId) {
        $localDeliveryParcels[] = $p;
    } else {
        $transitRoutingParcels[] = $p;
    }
}
?>

<!-- Hub Operations Top Banner -->
<div class="py-4 bg-dark text-white border-bottom border-secondary">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded-circle text-white shadow-sm" style="background: var(--primary);">
                    <i class="bi bi-building fs-2"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success-subtle text-success font-monospace fw-bold">WAREHOUSE SORTING DEPOT</span>
                        <span class="badge bg-secondary font-monospace"><?php echo htmlspecialchars($myHub['hub_code']); ?></span>
                    </div>
                    <h2 class="fw-extrabold text-white mb-0 mt-1"><?php echo htmlspecialchars($myHub['hub_name']); ?></h2>
                    <small class="text-secondary"><?php echo htmlspecialchars($myHub['city'] . ', ' . $myHub['state']); ?> • Facility Manager: <strong><?php echo htmlspecialchars($currentUser['full_name']); ?></strong></small>
                </div>
            </div>

            <!-- Rapid Batch Camera Scanner Trigger -->
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-express fw-bold px-4 py-2 d-flex align-items-center gap-2 shadow" id="btnLaunchHubScanner">
                    <i class="bi bi-qr-code-scan fs-5"></i>
                    <span>Launch Rapid Batch Scanner</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container py-4">
    <!-- Hub Metrics Counter -->
    <div class="row g-3 mb-4">
        <!-- Total In Facility -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-warning border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">In Facility Inventory</div>
                        <div class="fs-3 fw-extrabold text-dark mt-1" id="statInHubCount"><?php echo count($inHubParcels); ?></div>
                    </div>
                    <div class="p-3 rounded-circle bg-light text-warning">
                        <i class="bi bi-boxes fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Local Delivery Ready -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-success border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Ready for Local Dispatch</div>
                        <div class="fs-3 fw-extrabold text-success mt-1" id="statLocalCount"><?php echo count($localDeliveryParcels); ?></div>
                    </div>
                    <div class="p-3 rounded-circle bg-light text-success">
                        <i class="bi bi-geo-alt-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Needs Interstate Transfer -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-primary border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Interstate Transfers</div>
                        <div class="fs-3 fw-extrabold text-primary mt-1" id="statTransferCount"><?php echo count($transitRoutingParcels); ?></div>
                    </div>
                    <div class="p-3 rounded-circle bg-light text-primary">
                        <i class="bi bi-truck fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Out on Van Routes -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-secondary border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Out with Drivers</div>
                        <div class="fs-3 fw-extrabold text-secondary mt-1"><?php echo count($outForDeliveryParcels); ?></div>
                    </div>
                    <div class="p-3 rounded-circle bg-light text-secondary">
                        <i class="bi bi-bicycle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Manual Quick Check-in Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-white">
        <form id="hubManualCheckinForm" class="row g-2 align-items-center">
            <div class="col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-qr-code text-warning"></i></span>
                    <input type="text" class="form-control border-start-0 font-monospace text-uppercase" id="hubCheckinCodeInput" placeholder="Scan or enter Waybill # to check into this facility (e.g. CR-2026-46980)">
                </div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-dark w-100 fw-bold d-flex align-items-center justify-content-center gap-1">
                    <i class="bi bi-box-arrow-in-down text-warning"></i> Check-in to Facility
                </button>
            </div>
        </form>
    </div>

    <!-- Live Batch Scan Feed (Appended on rapid scan) -->
    <div id="batchFeedContainer" class="d-none mb-4">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden border border-warning">
            <div class="card-header bg-warning text-dark py-2 px-3 d-flex align-items-center justify-content-between">
                <span class="fw-bold small"><i class="bi bi-broadcast me-1"></i> Rapid Batch Scan Feed (<span id="batchCountNum">0</span> Packages Scanned)</span>
                <button type="button" class="btn btn-sm btn-dark py-0 px-2 rounded-pill small" id="btnClearBatchFeed">Clear Feed</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                    <table class="table table-sm align-middle mb-0" id="batchScanTable">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-3">Time</th>
                                <th>Waybill</th>
                                <th>Destination Hub</th>
                                <th>Recipient</th>
                                <th>Action Taken</th>
                            </tr>
                        </thead>
                        <tbody id="batchScanTableBody">
                            <!-- Inbound items prepend here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabbed Hub Management Views -->
    <ul class="nav nav-pills nav-fill mb-3 bg-white p-2 rounded-4 shadow-sm border" id="hubTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold py-2" id="tab-facility-btn" data-bs-toggle="pill" data-bs-target="#tab-facility" type="button" role="tab">
                <i class="bi bi-boxes me-1"></i> Full Inventory (<?php echo count($inHubParcels); ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold py-2" id="tab-local-btn" data-bs-toggle="pill" data-bs-target="#tab-local" type="button" role="tab">
                <i class="bi bi-geo-alt-fill text-success me-1"></i> Ready for Local Dispatch (<?php echo count($localDeliveryParcels); ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold py-2" id="tab-transit-btn" data-bs-toggle="pill" data-bs-target="#tab-transit" type="button" role="tab">
                <i class="bi bi-truck text-primary me-1"></i> Interstate Transfers (<?php echo count($transitRoutingParcels); ?>)
            </button>
        </li>
    </ul>

    <!-- Tab Contents -->
    <div class="tab-content" id="hubTabContent">

        <!-- TAB 1: Full Inventory -->
        <div class="tab-pane fade show active" id="tab-facility" role="tabpanel">
            <?php if (empty($inHubParcels)): ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                    <i class="bi bi-inbox display-4 text-muted mb-2"></i>
                    <h5 class="fw-bold text-dark">No Parcels in Facility Inventory</h5>
                    <p class="text-muted small">Scan incoming waybills using the Rapid Batch Scanner to register consignments in this depot.</p>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-4">Waybill Number</th>
                                    <th>Recipient & City</th>
                                    <th>Destination Hub</th>
                                    <th>Weight & Type</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Hub Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inHubParcels as $p): ?>
                                    <?php $isLocal = ((int)$p['destination_hub_id'] === $myHubId); ?>
                                    <tr>
                                        <td class="ps-4">
                                            <span class="font-monospace fw-bold"><?php echo htmlspecialchars($p['tracking_number']); ?></span>
                                            <div class="text-muted small"><?php echo date('M d, Y', strtotime($p['created_at'])); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($p['receiver_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($p['receiver_city']); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($isLocal): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                    <i class="bi bi-house-door-fill me-1"></i> LOCAL (<?php echo htmlspecialchars($myHub['hub_code']); ?>)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">
                                                    ➔ <?php echo htmlspecialchars($p['dest_hub_code']); ?> (<?php echo htmlspecialchars($p['dest_city']); ?>)
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="small fw-semibold"><?php echo htmlspecialchars($p['parcel_type']); ?></span>
                                            <div class="text-muted small"><?php echo number_format((float)$p['weight_kg'], 2); ?> kg</div>
                                        </td>
                                        <td>
                                            <span class="badge-status badge-status-<?php echo htmlspecialchars($p['status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $p['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <?php if ($isLocal && $p['status'] !== 'out_for_delivery'): ?>
                                                <button type="button" class="btn btn-sm btn-warning fw-bold py-1 px-3 btn-open-assign-driver" 
                                                        data-id="<?php echo $p['id']; ?>" 
                                                        data-tracking="<?php echo htmlspecialchars($p['tracking_number']); ?>"
                                                        data-receiver="<?php echo htmlspecialchars($p['receiver_name']); ?>"
                                                        data-city="<?php echo htmlspecialchars($p['receiver_city']); ?>">
                                                    <i class="bi bi-bicycle me-1"></i> Assign Courier
                                                </button>
                                            <?php elseif (!$isLocal): ?>
                                                <button type="button" class="btn btn-sm btn-outline-dark fw-bold py-1 px-3 btn-open-dispatch-hub" 
                                                        data-id="<?php echo $p['id']; ?>" 
                                                        data-tracking="<?php echo htmlspecialchars($p['tracking_number']); ?>"
                                                        data-desthubid="<?php echo $p['destination_hub_id']; ?>"
                                                        data-desthubname="<?php echo htmlspecialchars($p['dest_hub'] . ' (' . $p['dest_hub_code'] . ')'); ?>">
                                                    <i class="bi bi-truck me-1"></i> Route to Hub
                                                </button>
                                            <?php else: ?>
                                                <span class="small text-muted font-monospace"><i class="bi bi-bicycle me-1"></i> <?php echo htmlspecialchars($p['driver_name'] ?? 'On Route'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB 2: Ready for Local Delivery -->
        <div class="tab-pane fade" id="tab-local" role="tabpanel">
            <?php if (empty($localDeliveryParcels)): ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                    <i class="bi bi-check-circle display-4 text-success mb-2"></i>
                    <h5 class="fw-bold">No Local Delivery Parcels Pending</h5>
                    <p class="text-muted small">All consignments bound for this city have either been dispatched or delivered.</p>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-4">Waybill</th>
                                    <th>Recipient & Street Address</th>
                                    <th>Payment Notice</th>
                                    <th>Delivery OTP</th>
                                    <th class="text-end pe-4">Dispatch Courier</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($localDeliveryParcels as $lp): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <span class="font-monospace fw-bold"><?php echo htmlspecialchars($lp['tracking_number']); ?></span>
                                            <div class="text-muted small"><?php echo number_format((float)$lp['weight_kg'], 2); ?> kg</div>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($lp['receiver_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($lp['receiver_address']); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($lp['payment_method'] === 'cod' && $lp['payment_status'] !== 'paid'): ?>
                                                <span class="badge bg-danger text-white">COD: $<?php echo number_format((float)$lp['shipping_cost'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-success">PAID</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-dark text-warning font-monospace px-2 py-1"><?php echo htmlspecialchars($lp['delivery_otp']); ?></span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <button type="button" class="btn btn-sm btn-warning fw-bold py-1 px-3 btn-open-assign-driver" 
                                                    data-id="<?php echo $lp['id']; ?>" 
                                                    data-tracking="<?php echo htmlspecialchars($lp['tracking_number']); ?>"
                                                    data-receiver="<?php echo htmlspecialchars($lp['receiver_name']); ?>"
                                                    data-city="<?php echo htmlspecialchars($lp['receiver_city']); ?>">
                                                <i class="bi bi-bicycle me-1"></i> Dispatch Driver
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

        <!-- TAB 3: Interstate Transfers -->
        <div class="tab-pane fade" id="tab-transit-btn" role="tabpanel">
            <?php if (empty($transitRoutingParcels)): ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                    <i class="bi bi-truck display-4 text-muted mb-2"></i>
                    <h5 class="fw-bold">No Interstate Transfers Pending</h5>
                    <p class="text-muted small">All parcels currently in the hub are scheduled for local delivery.</p>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-4">Waybill Number</th>
                                    <th>Final Destination Hub</th>
                                    <th>Recipient City</th>
                                    <th>Weight</th>
                                    <th class="text-end pe-4">Transfer Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transitRoutingParcels as $tp): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <span class="font-monospace fw-bold"><?php echo htmlspecialchars($tp['tracking_number']); ?></span>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-primary font-monospace">
                                                <i class="bi bi-arrow-right-circle me-1"></i> <?php echo htmlspecialchars($tp['dest_hub']); ?> (<?php echo htmlspecialchars($tp['dest_hub_code']); ?>)
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($tp['receiver_city']); ?></td>
                                        <td><?php echo number_format((float)$tp['weight_kg'], 2); ?> kg</td>
                                        <td class="text-end pe-4">
                                            <button type="button" class="btn btn-sm btn-dark fw-bold py-1 px-3 btn-open-dispatch-hub" 
                                                    data-id="<?php echo $tp['id']; ?>" 
                                                    data-tracking="<?php echo htmlspecialchars($tp['tracking_number']); ?>"
                                                    data-desthubid="<?php echo $tp['destination_hub_id']; ?>"
                                                    data-desthubname="<?php echo htmlspecialchars($tp['dest_hub'] . ' (' . $tp['dest_hub_code'] . ')'); ?>">
                                                <i class="bi bi-truck me-1"></i> Load on Transfer Truck
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

    </div>
</div>

<!-- Modal 1: Rapid Batch Camera QR Scanner -->
<div class="modal fade" id="hubBatchScannerModal" tabindex="-1" aria-labelledby="hubBatchScannerModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-qr-code-scan text-warning fs-5"></i>
                    <div>
                        <h5 class="modal-title fs-6 fw-bold mb-0">Rapid Batch Intake Scanner</h5>
                        <small class="text-secondary">Depot Intake: <?php echo htmlspecialchars($myHub['hub_name']); ?></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div id="hub-camera-reader" style="width: 100%; min-height: 280px; background: #0f172a; border-radius: 8px; overflow: hidden;" class="border mx-auto"></div>
                <div id="hub-scanner-status" class="small mt-3 text-muted">
                    Point camera at incoming waybill stickers. Scanner stays active for continuous scanning.
                </div>
            </div>
            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <span class="small text-muted font-monospace"><span id="batchCountBadge" class="badge bg-warning text-dark">0</span> Scanned</span>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Done Scanning</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Outbound Transfer Hub Routing Modal -->
<div class="modal fade" id="dispatchHubModal" tabindex="-1" aria-labelledby="dispatchHubModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white py-3 px-4">
                <h5 class="modal-title fs-6 fw-bold mb-0">
                    <i class="bi bi-truck text-warning me-1"></i> Dispatch Transfer Truck
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="dispatchHubForm">
                <input type="hidden" id="dispatchParcelId" name="parcel_id" value="">
                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 border mb-3 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Waybill:</span>
                            <span class="fw-bold font-monospace text-dark" id="dispatchTrackingText">CR-2026-XXXXX</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Final Destination:</span>
                            <span class="fw-bold text-primary font-monospace" id="dispatchFinalDestText">Hub</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="selectTargetHub" class="form-label small fw-bold text-dark">Target Destination Sorting Hub</label>
                        <select class="form-select" id="selectTargetHub" name="target_hub_id" required>
                            <?php foreach ($allHubs as $h): ?>
                                <?php if ((int)$h['id'] !== $myHubId): ?>
                                    <option value="<?php echo $h['id']; ?>">
                                        <?php echo htmlspecialchars($h['city'] . ' - ' . $h['hub_name'] . ' (' . $h['hub_code'] . ')'); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-express btn-sm fw-bold">Confirm Route Dispatch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: Assign to Delivery Courier Modal -->
<div class="modal fade" id="assignDriverModal" tabindex="-1" aria-labelledby="assignDriverModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white py-3 px-4">
                <h5 class="modal-title fs-6 fw-bold mb-0">
                    <i class="bi bi-bicycle text-warning me-1"></i> Dispatch Local Delivery Driver
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="assignDriverForm">
                <input type="hidden" id="assignParcelId" name="parcel_id" value="">
                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 border mb-3 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Waybill:</span>
                            <span class="fw-bold font-monospace text-dark" id="assignTrackingText">CR-2026-XXXXX</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Recipient:</span>
                            <span class="fw-bold text-dark" id="assignReceiverText">Recipient</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="selectDriver" class="form-label small fw-bold text-dark">Select Delivery Courier</label>
                        <select class="form-select" id="selectDriver" name="driver_id" required>
                            <?php foreach ($drivers as $d): ?>
                                <option value="<?php echo $d['id']; ?>">
                                    <?php echo htmlspecialchars($d['full_name'] . ' (' . ($d['phone'] ?? 'Active') . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm fw-bold">Dispatch Driver (Out for Delivery)</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let hubQrScanner = null;
    let scannedCount = 0;
    let lastScannedCode = '';
    let scanCooldown = false;

    // 1. Launch Rapid Batch Scanner
    $('#btnLaunchHubScanner').on('click', function() {
        const modal = new bootstrap.Modal(document.getElementById('hubBatchScannerModal'));
        modal.show();
    });

    $('#hubBatchScannerModal').on('shown.bs.modal', function () {
        $('#hub-scanner-status').html('<span class="spinner-border spinner-border-sm me-1"></span> Initializing high-speed intake camera...');

        if (typeof Html5Qrcode !== 'undefined') {
            hubQrScanner = new Html5Qrcode("hub-camera-reader");
            const config = { fps: 15, qrbox: { width: 240, height: 240 } };

            hubQrScanner.start(
                { facingMode: "environment" },
                config,
                (decodedText) => {
                    // Prevent duplicate rapid firing of the same code within 2 seconds
                    if (scanCooldown || decodedText === lastScannedCode) return;

                    scanCooldown = true;
                    lastScannedCode = decodedText;

                    processHubIntakeScan(decodedText);

                    setTimeout(() => {
                        scanCooldown = false;
                        lastScannedCode = '';
                    }, 2200);
                },
                (err) => {}
            ).catch(err => {
                $('#hub-scanner-status').html('<span class="text-danger small">Camera error. You can enter tracking numbers manually.</span>');
            });
        }
    });

    $('#hubBatchScannerModal').on('hidden.bs.modal', function () {
        if (hubQrScanner) {
            hubQrScanner.stop().then(() => {
                hubQrScanner.clear();
                hubQrScanner = null;
            }).catch(e => console.warn(e));
        }
        if (scannedCount > 0) {
            window.location.reload();
        }
    });

    // 2. Process Intake Scan via AJAX
    function processHubIntakeScan(code) {
        $('#hub-scanner-status').html(`<span class="spinner-border spinner-border-sm me-1"></span> Checking in ${code}...`);

        $.ajax({
            url: '<?php echo BASE_URL; ?>ajax/hub_handler.php',
            type: 'POST',
            data: {
                action: 'hub_scan_intake',
                code: code,
                hub_id: <?php echo $myHubId; ?>
            },
            success: function(response) {
                if (response.status === 'success') {
                    scannedCount++;
                    $('#batchCountNum').text(scannedCount);
                    $('#batchCountBadge').text(scannedCount);
                    $('#batchFeedContainer').removeClass('d-none');

                    const d = response.data;
                    const actionLabel = d.is_dest_hub ? '<span class="badge bg-success">Local Ready</span>' : '<span class="badge bg-primary">In Transit</span>';

                    const rowHtml = `
                        <tr class="table-warning">
                            <td class="ps-3 font-monospace small">${d.scanned_at}</td>
                            <td class="font-monospace fw-bold">${d.tracking_number}</td>
                            <td>${d.dest_hub}</td>
                            <td>${d.receiver_name} (${d.receiver_city})</td>
                            <td>${actionLabel}</td>
                        </tr>
                    `;
                    $('#batchScanTableBody').prepend(rowHtml);

                    showToast(`Checked in: ${d.tracking_number} (${d.receiver_city})`, 'success');
                    $('#hub-scanner-status').html(`<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i> Checked in: ${d.tracking_number}</span>`);
                } else {
                    $('#hub-scanner-status').html(`<span class="text-danger small"><i class="bi bi-exclamation-triangle me-1"></i> ${response.message}</span>`);
                    showToast(response.message || 'Check-in failed.', 'danger');
                }
            },
            error: function() {
                $('#hub-scanner-status').html('<span class="text-danger small">Network error.</span>');
            }
        });
    }

    // 3. Manual Check-in Form
    $('#hubManualCheckinForm').on('submit', function(e) {
        e.preventDefault();
        const code = $('#hubCheckinCodeInput').val().trim();
        if (!code) {
            showToast('Please enter a tracking number.', 'warning');
            return;
        }
        processHubIntakeScan(code);
        $('#hubCheckinCodeInput').val('');
    });

    $('#btnClearBatchFeed').on('click', function() {
        $('#batchScanTableBody').empty();
        $('#batchFeedContainer').addClass('d-none');
        scannedCount = 0;
    });

    // 4. Open Dispatch to Transfer Hub Modal
    $(document).on('click', '.btn-open-dispatch-hub', function() {
        const id = $(this).data('id');
        const tracking = $(this).data('tracking');
        const destHubId = $(this).data('desthubid');
        const destHubName = $(this).data('desthubname');

        $('#dispatchParcelId').val(id);
        $('#dispatchTrackingText').text(tracking);
        $('#dispatchFinalDestText').text(destHubName);
        $('#selectTargetHub').val(destHubId);

        const modal = new bootstrap.Modal(document.getElementById('dispatchHubModal'));
        modal.show();
    });

    $('#dispatchHubForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize() + '&action=dispatch_to_hub';

        $.ajax({
            url: '<?php echo BASE_URL; ?>ajax/hub_handler.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.status === 'success') {
                    showToast(response.message, 'success');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('dispatchHubModal'));
                    if (modal) modal.hide();
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    showToast(response.message || 'Dispatch failed.', 'danger');
                }
            }
        });
    });

    // 5. Open Dispatch Local Driver Modal
    $(document).on('click', '.btn-open-assign-driver', function() {
        const id = $(this).data('id');
        const tracking = $(this).data('tracking');
        const receiver = $(this).data('receiver');
        const city = $(this).data('city');

        $('#assignParcelId').val(id);
        $('#assignTrackingText').text(tracking);
        $('#assignReceiverText').text(receiver + ' (' + city + ')');

        const modal = new bootstrap.Modal(document.getElementById('assignDriverModal'));
        modal.show();
    });

    $('#assignDriverForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize() + '&action=assign_driver_dispatch';

        $.ajax({
            url: '<?php echo BASE_URL; ?>ajax/hub_handler.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.status === 'success') {
                    showToast(response.message, 'success');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('assignDriverModal'));
                    if (modal) modal.hide();
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    showToast(response.message || 'Driver dispatch failed.', 'danger');
                }
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
