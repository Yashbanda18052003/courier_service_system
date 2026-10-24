<?php
// ========================================================
// SwiftScan Express - Super Administrator Command Center
// (admin/index.php)
// Step 11: Real-time Revenue, Master Consignments & Hub Config
// ========================================================
$pageTitle = "Administrator Command Center & Analytics - SwiftScan Express";
require_once __DIR__ . '/../includes/auth_guard.php';

requireRole(['admin']);

$currentUser = getCurrentUser();
require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();

// 1. Fetch Financial & Operational KPI Metrics
$finStmt = $pdo->query("
    SELECT 
        COALESCE(SUM(amount), 0) AS gross_revenue,
        COALESCE(SUM(CASE WHEN payment_method = 'online' THEN amount ELSE 0 END), 0) AS online_revenue,
        COALESCE(SUM(CASE WHEN payment_method = 'cod' THEN amount ELSE 0 END), 0) AS cod_revenue,
        COUNT(id) AS total_transactions
    FROM payments 
    WHERE payment_status = 'paid'
");
$finStats = $finStmt->fetch() ?: ['gross_revenue' => 0, 'online_revenue' => 0, 'cod_revenue' => 0, 'total_transactions' => 0];

$opsStmt = $pdo->query("
    SELECT 
        COUNT(*) AS total_parcels,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered_count,
        SUM(CASE WHEN status IN ('picked_up', 'in_transit', 'out_for_delivery') THEN 1 ELSE 0 END) AS in_transit_count,
        SUM(CASE WHEN status = 'booked' THEN 1 ELSE 0 END) AS booked_count
    FROM parcels
");
$opsStats = $opsStmt->fetch() ?: ['total_parcels' => 0, 'delivered_count' => 0, 'in_transit_count' => 0, 'booked_count' => 0];

$totalParcels = (int)$opsStats['total_parcels'];
$deliveredParcels = (int)$opsStats['delivered_count'];
$successRate = ($totalParcels > 0) ? round(($deliveredParcels / $totalParcels) * 100, 1) : 100.0;

// 2. Fetch Master Consignments
$parcelsStmt = $pdo->query("
    SELECT 
        p.*,
        oh.hub_name AS origin_hub_name,
        oh.hub_code AS origin_hub_code,
        dh.hub_name AS dest_hub_name,
        dh.hub_code AS dest_hub_code,
        ch.hub_name AS current_hub_name,
        d.full_name AS driver_name
    FROM parcels p
    LEFT JOIN hubs oh ON p.origin_hub_id = oh.id
    LEFT JOIN hubs dh ON p.destination_hub_id = dh.id
    LEFT JOIN hubs ch ON p.current_hub_id = ch.id
    LEFT JOIN users d ON p.assigned_driver_id = d.id
    ORDER BY p.id DESC
");
$masterParcels = $parcelsStmt->fetchAll();

// 3. Fetch Payments Ledger
$payLedgerStmt = $pdo->query("
    SELECT pm.*, p.tracking_number, p.receiver_name, p.shipping_cost
    FROM payments pm
    LEFT JOIN parcels p ON pm.parcel_id = p.id
    ORDER BY pm.id DESC
");
$paymentLedger = $payLedgerStmt->fetchAll();

// 4. Fetch Logistics Hubs with active inventory counts
$hubsStmt = $pdo->query("
    SELECT 
        h.*,
        COUNT(p.id) AS current_inventory_count
    FROM hubs h
    LEFT JOIN parcels p ON h.id = p.current_hub_id AND p.status NOT IN ('delivered', 'returned')
    GROUP BY h.id
    ORDER BY h.id ASC
");
$hubs = $hubsStmt->fetchAll();

// 5. Fetch Users Directory
$usersStmt = $pdo->query("
    SELECT u.*, h.hub_name, h.hub_code
    FROM users u
    LEFT JOIN hubs h ON u.hub_id = h.id
    ORDER BY u.role ASC, u.id ASC
");
$users = $usersStmt->fetchAll();
?>

<!-- Admin Executive Command Banner -->
<div class="py-4 bg-dark text-white border-bottom border-secondary">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded-circle bg-danger text-white">
                    <i class="bi bi-shield-check fs-2"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger text-white font-monospace fw-bold">ROOT COMMAND CENTER</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <span class="p-1 rounded-circle bg-success me-1"></span> Live Network Active
                        </span>
                    </div>
                    <h2 class="fw-extrabold text-white mb-0 mt-1"><?php echo htmlspecialchars($currentUser['full_name']); ?></h2>
                    <small class="text-secondary">System Administration, Real-time Revenue & Master Dispatch Operations</small>
                </div>
            </div>

            <!-- Quick Action Buttons -->
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-warning fw-bold px-3 py-2 shadow-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#createHubModal">
                    <i class="bi bi-building-add"></i>
                    <span>+ New Hub</span>
                </button>
                <button type="button" class="btn btn-outline-light fw-bold px-3 py-2 shadow-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#createUserModal">
                    <i class="bi bi-person-plus-fill"></i>
                    <span>+ Staff Account</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container py-4">
    <!-- Executive Financial & Volume KPI Cards -->
    <div class="row g-3 mb-4">
        <!-- Gross Revenue -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-success border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Gross Revenue</div>
                        <div class="fs-3 fw-extrabold text-success mt-1 font-monospace">$<?php echo number_format((float)$finStats['gross_revenue'], 2); ?></div>
                        <small class="text-muted"><?php echo (int)$finStats['total_transactions']; ?> Settled Transactions</small>
                    </div>
                    <div class="p-3 rounded-circle bg-light text-success">
                        <i class="bi bi-wallet2 fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Online Card Sales -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-primary border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Prepaid Online Sales</div>
                        <div class="fs-3 fw-extrabold text-primary mt-1 font-monospace">$<?php echo number_format((float)$finStats['online_revenue'], 2); ?></div>
                        <small class="text-muted">Instant Card Gateway</small>
                    </div>
                    <div class="p-3 rounded-circle bg-light text-primary">
                        <i class="bi bi-credit-card-2-front fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cash on Delivery (COD) -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-warning border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Doorstep COD Cash</div>
                        <div class="fs-3 fw-extrabold text-warning mt-1 font-monospace">$<?php echo number_format((float)$finStats['cod_revenue'], 2); ?></div>
                        <small class="text-muted">Courier Doorstep Receipts</small>
                    </div>
                    <div class="p-3 rounded-circle bg-light text-warning">
                        <i class="bi bi-cash-stack fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delivery Success Rate -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-danger border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Success Rate</div>
                        <div class="fs-3 fw-extrabold text-dark mt-1 font-monospace"><?php echo $successRate; ?>%</div>
                        <small class="text-muted"><?php echo $deliveredParcels; ?> of <?php echo $totalParcels; ?> Delivered</small>
                    </div>
                    <div class="p-3 rounded-circle bg-light text-danger">
                        <i class="bi bi-patch-check-fill fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Management Tabs Suite -->
    <ul class="nav nav-pills nav-fill mb-3 bg-white p-2 rounded-4 shadow-sm border" id="adminTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold py-2" id="tab-parcels-btn" data-bs-toggle="pill" data-bs-target="#tab-parcels" type="button" role="tab">
                <i class="bi bi-boxes me-1 text-warning"></i> Master Consignments (<?php echo count($masterParcels); ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold py-2" id="tab-ledger-btn" data-bs-toggle="pill" data-bs-target="#tab-ledger" type="button" role="tab">
                <i class="bi bi-receipt me-1 text-success"></i> Payments Ledger (<?php echo count($paymentLedger); ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold py-2" id="tab-hubs-btn" data-bs-toggle="pill" data-bs-target="#tab-hubs" type="button" role="tab">
                <i class="bi bi-building me-1 text-primary"></i> Regional Hubs (<?php echo count($hubs); ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold py-2" id="tab-users-btn" data-bs-toggle="pill" data-bs-target="#tab-users" type="button" role="tab">
                <i class="bi bi-people-fill me-1 text-danger"></i> Users & Staff (<?php echo count($users); ?>)
            </button>
        </li>
    </ul>

    <!-- Tab Contents -->
    <div class="tab-content" id="adminTabContent">

        <!-- TAB 1: Master Consignments Directory -->
        <div class="tab-pane fade show active" id="tab-parcels" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">Master Consignment Records</h5>
                        <small class="text-muted">Real-time status, OTP codes, payments, and administrative overrides</small>
                    </div>
                    <div>
                        <input type="text" id="adminParcelFilter" class="form-control form-control-sm font-monospace" placeholder="Search tracking ID, recipient, city..." style="min-width: 280px;">
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="adminParcelTable">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-4">Waybill Number</th>
                                    <th>Sender & Receiver</th>
                                    <th>Route (Origin ➔ Dest)</th>
                                    <th>Weight & Cost</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th class="text-center">OTP</th>
                                    <th class="text-end pe-4">Admin Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($masterParcels as $p): ?>
                                    <tr>
                                        <!-- Tracking Code -->
                                        <td class="ps-4">
                                            <span class="font-monospace fw-bold text-dark"><?php echo htmlspecialchars($p['tracking_number']); ?></span>
                                            <div class="text-muted small"><?php echo date('M d, Y', strtotime($p['created_at'])); ?></div>
                                        </td>

                                        <!-- Sender & Receiver -->
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['receiver_name']); ?> (<?php echo htmlspecialchars($p['receiver_city']); ?>)</div>
                                            <div class="small text-muted">From: <?php echo htmlspecialchars($p['sender_name']); ?></div>
                                        </td>

                                        <!-- Route -->
                                        <td>
                                            <span class="font-monospace small">
                                                <?php echo htmlspecialchars($p['origin_hub_code'] ?? 'NYC'); ?> ➔ <?php echo htmlspecialchars($p['dest_hub_code'] ?? 'LAX'); ?>
                                            </span>
                                            <div class="small text-muted text-truncate" style="max-width: 140px;">Cur: <?php echo htmlspecialchars($p['current_hub_name'] ?? 'Transit'); ?></div>
                                        </td>

                                        <!-- Specs -->
                                        <td>
                                            <div class="fw-bold text-dark font-monospace">$<?php echo number_format((float)$p['shipping_cost'], 2); ?></div>
                                            <div class="small text-muted"><?php echo number_format((float)$p['weight_kg'], 2); ?> kg</div>
                                        </td>

                                        <!-- Payment -->
                                        <td>
                                            <span class="badge <?php echo $p['payment_method'] === 'cod' ? 'bg-warning text-dark' : 'bg-primary'; ?> py-1 px-2" style="font-size: 0.68rem;">
                                                <?php echo strtoupper($p['payment_method']); ?>
                                            </span>
                                            <span class="badge <?php echo $p['payment_status'] === 'paid' ? 'bg-success' : 'bg-secondary'; ?> py-1 px-2" style="font-size: 0.68rem;">
                                                <?php echo strtoupper($p['payment_status']); ?>
                                            </span>
                                        </td>

                                        <!-- Status -->
                                        <td>
                                            <span class="badge-status badge-status-<?php echo htmlspecialchars($p['status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $p['status'])); ?>
                                            </span>
                                        </td>

                                        <!-- OTP -->
                                        <td class="text-center">
                                            <span class="badge bg-dark text-warning font-monospace px-2 py-1"><?php echo htmlspecialchars($p['delivery_otp']); ?></span>
                                        </td>

                                        <!-- Actions -->
                                        <td class="text-end pe-4">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo BASE_URL; ?>index.php?track=<?php echo urlencode($p['tracking_number']); ?>" target="_blank" class="btn btn-outline-secondary" title="Live Timeline">
                                                    <i class="bi bi-search"></i>
                                                </a>
                                                <a href="<?php echo BASE_URL; ?>waybill_print.php?code=<?php echo urlencode($p['tracking_number']); ?>" target="_blank" class="btn btn-outline-dark" title="Print QR Waybill">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger btn-open-override" 
                                                        data-id="<?php echo $p['id']; ?>" 
                                                        data-tracking="<?php echo htmlspecialchars($p['tracking_number']); ?>"
                                                        data-status="<?php echo $p['status']; ?>"
                                                        title="Admin Status Override">
                                                    <i class="bi bi-sliders"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: Financial Ledger -->
        <div class="tab-pane fade" id="tab-ledger" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-receipt text-success me-1"></i> Financial Invoices & Revenue Ledger
                    </h5>
                    <small class="text-muted">Direct receipts for card checkouts and cash collected on delivery</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-4">Transaction Reference</th>
                                    <th>Waybill Consignment</th>
                                    <th>Amount Paid</th>
                                    <th>Payment Channel</th>
                                    <th>Payment Instrument</th>
                                    <th>Settled Timestamp</th>
                                    <th class="text-end pe-4">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paymentLedger as $pm): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <span class="font-monospace fw-bold text-dark"><?php echo htmlspecialchars($pm['transaction_id']); ?></span>
                                        </td>
                                        <td>
                                            <a href="<?php echo BASE_URL; ?>index.php?track=<?php echo urlencode($pm['tracking_number']); ?>" target="_blank" class="text-decoration-none font-monospace fw-semibold">
                                                <?php echo htmlspecialchars($pm['tracking_number']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="fw-bold font-monospace text-success fs-6">$<?php echo number_format((float)$pm['amount'], 2); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $pm['payment_method'] === 'online' ? 'bg-primary' : 'bg-warning text-dark'; ?> text-uppercase font-monospace">
                                                <?php echo htmlspecialchars($pm['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($pm['card_last4'] && $pm['card_last4'] !== 'CASH'): ?>
                                                <span class="font-monospace small text-dark"><i class="bi bi-credit-card me-1 text-primary"></i>•••• <?php echo htmlspecialchars($pm['card_last4']); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark border"><i class="bi bi-cash me-1 text-success"></i> Cash Handover</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="small text-muted font-monospace"><?php echo date('M d, Y • h:i A', strtotime($pm['paid_at'])); ?></span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                <i class="bi bi-check-circle me-1"></i> VERIFIED
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: Regional Hubs Network -->
        <div class="tab-pane fade" id="tab-hubs" role="tabpanel">
            <div class="row g-4">
                <?php foreach ($hubs as $h): ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white border-top border-4 border-primary">
                            <div class="card-body p-4 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="badge bg-dark font-monospace text-warning px-2 py-1"><?php echo htmlspecialchars($h['hub_code']); ?></span>
                                        <span class="badge bg-primary-subtle text-primary font-monospace"><?php echo (int)$h['current_inventory_count']; ?> In Stock</span>
                                    </div>
                                    <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($h['hub_name']); ?></h5>
                                    <p class="small text-muted mb-2">
                                        <i class="bi bi-geo-alt-fill text-warning me-1"></i>
                                        <strong><?php echo htmlspecialchars($h['city'] . ', ' . $h['state']); ?></strong> (<?php echo htmlspecialchars($h['pincode']); ?>)
                                    </p>
                                    <div class="small text-secondary mb-3"><?php echo htmlspecialchars($h['address']); ?></div>
                                </div>
                                <div class="pt-2 border-top text-muted small d-flex justify-content-between align-items-center">
                                    <span>Facility ID #<?php echo $h['id']; ?></span>
                                    <span class="badge bg-success-subtle text-success">Operational</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TAB 4: Users Directory -->
        <div class="tab-pane fade" id="tab-users" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">Staff & Customer Directory</h5>
                        <small class="text-muted">System security roles and hub affiliations</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-dark fw-bold px-3 py-1" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="bi bi-plus-circle me-1"></i> Add Account
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-4">User</th>
                                    <th>Email Address</th>
                                    <th>Security Role</th>
                                    <th>Assigned Hub</th>
                                    <th>Phone</th>
                                    <th class="text-end pe-4">Member Since</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($u['full_name']); ?></div>
                                            <div class="text-muted small">User ID #<?php echo $u['id']; ?></div>
                                        </td>
                                        <td>
                                            <span class="font-monospace small text-dark"><?php echo htmlspecialchars($u['email']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge-role badge-role-<?php echo htmlspecialchars($u['role']); ?>">
                                                <?php echo strtoupper(str_replace('_', ' ', $u['role'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($u['hub_name']): ?>
                                                <span class="small fw-semibold text-dark"><?php echo htmlspecialchars($u['hub_name']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted small">Global / Public</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="small font-monospace"><?php echo htmlspecialchars($u['phone'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <span class="small text-muted font-monospace"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal 1: Add New Logistics Hub -->
<div class="modal fade" id="createHubModal" tabindex="-1" aria-labelledby="createHubModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white py-3 px-4">
                <h5 class="modal-title fs-6 fw-bold mb-0">
                    <i class="bi bi-building-add text-warning me-2"></i> Register New Logistics Hub
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createHubForm" novalidate>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="hubName" class="form-label small fw-bold text-dark mb-1">Hub Facility Name</label>
                        <input type="text" class="form-control" id="hubName" name="hub_name" placeholder="e.g. Texas Regional Depot" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="hubCode" class="form-label small fw-bold text-dark mb-1">Hub Code (Unique)</label>
                            <input type="text" class="form-control font-monospace text-uppercase" id="hubCode" name="hub_code" placeholder="HUB-DFW-05" required>
                        </div>
                        <div class="col-6">
                            <label for="hubCity" class="form-label small fw-bold text-dark mb-1">City</label>
                            <input type="text" class="form-control" id="hubCity" name="city" placeholder="Dallas" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="hubState" class="form-label small fw-bold text-dark mb-1">State / Province</label>
                            <input type="text" class="form-control text-uppercase" id="hubState" name="state" placeholder="TX" maxlength="10" required>
                        </div>
                        <div class="col-6">
                            <label for="hubPincode" class="form-label small fw-bold text-dark mb-1">Postal Code</label>
                            <input type="text" class="form-control font-monospace" id="hubPincode" name="pincode" placeholder="75201" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="hubAddress" class="form-label small fw-bold text-dark mb-1">Street Address</label>
                        <textarea class="form-control" id="hubAddress" name="address" rows="2" placeholder="Warehouse address" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-express btn-sm fw-bold">Provision Hub Facility</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Create Staff User Account -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white py-3 px-4">
                <h5 class="modal-title fs-6 fw-bold mb-0">
                    <i class="bi bi-person-plus-fill text-warning me-2"></i> Provision Corporate Account
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createUserForm" novalidate>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="newUserName" class="form-label small fw-bold text-dark mb-1">Full Name</label>
                        <input type="text" class="form-control" id="newUserName" name="full_name" placeholder="Employee Name" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label for="newUserEmail" class="form-label small fw-bold text-dark mb-1">Email Address</label>
                            <input type="email" class="form-control font-monospace" id="newUserEmail" name="email" placeholder="name@courier.com" required>
                        </div>
                        <div class="col-5">
                            <label for="newUserPhone" class="form-label small fw-bold text-dark mb-1">Phone</label>
                            <input type="tel" class="form-control" id="newUserPhone" name="phone" placeholder="+1-555-0199">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="newUserRole" class="form-label small fw-bold text-dark mb-1">Security Role</label>
                            <select class="form-select" id="newUserRole" name="role" required>
                                <option value="driver">Delivery Driver</option>
                                <option value="hub_manager">Hub Manager</option>
                                <option value="admin">Administrator</option>
                                <option value="customer">Customer</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="newUserHub" class="form-label small fw-bold text-dark mb-1">Assigned Hub</label>
                            <select class="form-select" id="newUserHub" name="hub_id">
                                <option value="">None (Global)</option>
                                <?php foreach ($hubs as $h): ?>
                                    <option value="<?php echo $h['id']; ?>"><?php echo htmlspecialchars($h['hub_name'] . ' (' . $h['hub_code'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="newUserPassword" class="form-label small fw-bold text-dark mb-1">Temporary Password</label>
                        <input type="text" class="form-control font-monospace" id="newUserPassword" name="password" value="staff123" required>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark btn-sm fw-bold">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: Administrative Status Override -->
<div class="modal fade" id="overrideStatusModal" tabindex="-1" aria-labelledby="overrideStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3 px-4">
                <h5 class="modal-title fs-6 fw-bold mb-0">
                    <i class="bi bi-shield-lock-fill me-2"></i> Master Status Override
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="overrideStatusForm">
                <input type="hidden" id="overrideParcelId" name="parcel_id" value="">
                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 border mb-3 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Waybill:</span>
                            <span class="fw-bold font-monospace text-dark" id="overrideTrackingText">CR-2026-XXXXX</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Current Status:</span>
                            <span class="badge bg-secondary font-monospace" id="overrideCurrentStatusText">BOOKED</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="overrideNewStatus" class="form-label small fw-bold text-dark mb-1">Set New System Status</label>
                        <select class="form-select font-monospace" id="overrideNewStatus" name="new_status" required>
                            <option value="booked">Booked (Initial Order)</option>
                            <option value="picked_up">Picked Up (At Origin Hub)</option>
                            <option value="in_transit">In Transit (Interstate Network)</option>
                            <option value="out_for_delivery">Out for Delivery (On Courier Route)</option>
                            <option value="delivered">Delivered (Completed)</option>
                            <option value="failed">Failed / Reschedule</option>
                            <option value="returned">Returned to Origin</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="overrideRemarks" class="form-label small fw-bold text-dark mb-1">Administrative Remarks</label>
                        <input type="text" class="form-control" id="overrideRemarks" name="remarks" value="Direct administrative correction by root command." required>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold">Force Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Quick search filter for Master Parcels table
    $('#adminParcelFilter').on('keyup', function() {
        const val = $(this).val().toLowerCase();
        $('#adminParcelTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
        });
    });

    // 1. Create Hub via AJAX
    $('#createHubForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize() + '&action=create_hub';

        $.ajax({
            url: '<?php echo BASE_URL; ?>ajax/admin_handler.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.status === 'success') {
                    showToast(response.message, 'success');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('createHubModal'));
                    if (modal) modal.hide();
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    showToast(response.message || 'Failed to create hub.', 'danger');
                }
            }
        });
    });

    // 2. Create User via AJAX
    $('#createUserForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize() + '&action=create_user';

        $.ajax({
            url: '<?php echo BASE_URL; ?>ajax/admin_handler.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.status === 'success') {
                    showToast(response.message, 'success');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('createUserModal'));
                    if (modal) modal.hide();
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    showToast(response.message || 'Failed to create user.', 'danger');
                }
            }
        });
    });

    // 3. Open Override Modal
    $(document).on('click', '.btn-open-override', function() {
        const id = $(this).data('id');
        const tracking = $(this).data('tracking');
        const status = $(this).data('status');

        $('#overrideParcelId').val(id);
        $('#overrideTrackingText').text(tracking);
        $('#overrideCurrentStatusText').text(status.toUpperCase());
        $('#overrideNewStatus').val(status);

        const modal = new bootstrap.Modal(document.getElementById('overrideStatusModal'));
        modal.show();
    });

    // Submit Override Form
    $('#overrideStatusForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize() + '&action=override_status';

        $.ajax({
            url: '<?php echo BASE_URL; ?>ajax/admin_handler.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.status === 'success') {
                    showToast(response.message, 'success');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('overrideStatusModal'));
                    if (modal) modal.hide();
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    showToast(response.message || 'Override failed.', 'danger');
                }
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
