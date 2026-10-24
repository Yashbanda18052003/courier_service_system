<?php
// ========================================================
// SwiftScan Express - Customer Portal Dashboard
// (customer/index.php)
// Protected Area - Requires 'customer' role
// ========================================================
$pageTitle = "My Shipments & Dashboard - SwiftScan Express";
require_once __DIR__ . '/../includes/auth_guard.php';

// Strict Role Check: Customers only (or admin)
requireRole(['customer', 'admin']);

$currentUser = getCurrentUser();
$userId = $currentUser['id'];

require_once __DIR__ . '/../includes/header.php';

// Fetch Customer's Bookings and Statistics
$pdo = getDB();

// 1. Compute Stats
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_parcels,
        SUM(CASE WHEN status IN ('booked', 'picked_up', 'in_transit', 'out_for_delivery') THEN 1 ELSE 0 END) AS active_parcels,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered_parcels,
        SUM(CASE WHEN payment_method = 'cod' AND payment_status = 'pending' THEN 1 ELSE 0 END) AS pending_cod
    FROM parcels
    WHERE sender_id = :user_id
");
$statsStmt->execute([':user_id' => $userId]);
$stats = $statsStmt->fetch() ?: ['total_parcels' => 0, 'active_parcels' => 0, 'delivered_parcels' => 0, 'pending_cod' => 0];

// 2. Fetch Customer's Parcels
$parcelsStmt = $pdo->prepare("
    SELECT 
        p.*,
        oh.hub_name AS origin_hub_name,
        dh.hub_name AS dest_hub_name,
        d.full_name AS driver_name
    FROM parcels p
    LEFT JOIN hubs oh ON p.origin_hub_id = oh.id
    LEFT JOIN hubs dh ON p.destination_hub_id = dh.id
    LEFT JOIN users d ON p.assigned_driver_id = d.id
    WHERE p.sender_id = :user_id
    ORDER BY p.id DESC
");
$parcelsStmt->execute([':user_id' => $userId]);
$parcels = $parcelsStmt->fetchAll();
?>

<div class="py-4 bg-light border-bottom">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-warning text-dark fw-bold px-2 py-1 rounded-pill">Customer Portal</span>
                    <span class="text-muted small">Account ID #<?php echo htmlspecialchars($userId); ?></span>
                </div>
                <h2 class="fw-extrabold text-dark mb-0 mt-1">
                    Welcome back, <?php echo htmlspecialchars($currentUser['full_name']); ?>!
                </h2>
                <p class="text-muted small mb-0">Track active consignments, verify delivery OTPs, or book a new shipment.</p>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <a href="<?php echo BASE_URL; ?>customer/book.php" class="btn btn-express fw-bold d-flex align-items-center gap-2 shadow-sm">
                    <i class="bi bi-plus-circle-fill"></i>
                    <span>Book New Parcel</span>
                </a>
                <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-outline-dark fw-semibold d-flex align-items-center gap-1">
                    <i class="bi bi-search"></i>
                    <span>Track Live</span>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container py-4">
    <!-- Stat Counter Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-primary border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Total Parcels</div>
                        <div class="fs-3 fw-extrabold text-dark mt-1"><?php echo (int)($stats['total_parcels'] ?? 0); ?></div>
                    </div>
                    <div class="p-3 rounded-circle bg-light text-primary">
                        <i class="bi bi-box-seam fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-warning border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">In Transit</div>
                        <div class="fs-3 fw-extrabold text-warning mt-1"><?php echo (int)($stats['active_parcels'] ?? 0); ?></div>
                    </div>
                    <div class="p-3 rounded-circle bg-light text-warning">
                        <i class="bi bi-truck fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-success border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Delivered</div>
                        <div class="fs-3 fw-extrabold text-success mt-1"><?php echo (int)($stats['delivered_parcels'] ?? 0); ?></div>
                    </div>
                    <div class="p-3 rounded-circle bg-light text-success">
                        <i class="bi bi-patch-check-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-danger border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Pending COD</div>
                        <div class="fs-3 fw-extrabold text-danger mt-1"><?php echo (int)($stats['pending_cod'] ?? 0); ?></div>
                    </div>
                    <div class="p-3 rounded-circle bg-light text-danger">
                        <i class="bi bi-cash-stack fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- My Shipments Section -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="bi bi-boxes text-warning me-2"></i> My Consignments & Waybills
                </h5>
                <small class="text-muted">Real-time status, recipient OTPs, and printable QR waybills</small>
            </div>
            <div>
                <input type="text" id="filterParcelTable" class="form-control form-control-sm" placeholder="Filter by tracking ID or recipient..." style="min-width: 250px;">
            </div>
        </div>

        <div class="card-body p-0">
            <?php if (empty($parcels)): ?>
                <!-- Empty State -->
                <div class="text-center py-5 px-3">
                    <div class="d-inline-flex p-4 rounded-circle bg-light text-muted mb-3">
                        <i class="bi bi-inbox fs-1 text-warning"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">No Parcels Booked Yet</h5>
                    <p class="text-muted small max-w-500 mx-auto mb-4">
                        You have not scheduled any deliveries yet. Create your first shipment with our instant price estimator and generate your scannable QR waybill.
                    </p>
                    <a href="<?php echo BASE_URL; ?>customer/book.php" class="btn btn-express px-4 py-2 fw-bold">
                        <i class="bi bi-plus-circle me-1"></i> Book Your First Parcel
                    </a>
                </div>
            <?php else: ?>
                <!-- Table of Shipments -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="customerShipmentTable">
                        <thead class="table-light small text-uppercase">
                            <tr>
                                <th class="ps-4">Waybill / Tracking</th>
                                <th>Recipient & Destination</th>
                                <th>Package Specs</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th class="text-center">Delivery OTP</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parcels as $p): ?>
                                <tr>
                                    <!-- Waybill ID -->
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="font-monospace fw-bold text-dark"><?php echo htmlspecialchars($p['tracking_number']); ?></span>
                                            <button type="button" class="btn btn-sm btn-link p-0 text-muted btn-copy-code" data-code="<?php echo htmlspecialchars($p['tracking_number']); ?>" title="Copy Tracking Number">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </div>
                                        <div class="text-muted small"><?php echo date('M d, Y', strtotime($p['created_at'])); ?></div>
                                    </td>

                                    <!-- Recipient & Destination -->
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['receiver_name']); ?></div>
                                        <div class="small text-muted">
                                            <i class="bi bi-geo-alt-fill text-warning me-1"></i>
                                            <?php echo htmlspecialchars($p['receiver_city']); ?>
                                        </div>
                                    </td>

                                    <!-- Specs -->
                                    <td>
                                        <div class="small fw-semibold"><?php echo htmlspecialchars($p['parcel_type']); ?></div>
                                        <div class="text-muted small"><?php echo number_format((float)$p['weight_kg'], 2); ?> kg</div>
                                    </td>

                                    <!-- Payment -->
                                    <td>
                                        <div class="fw-bold text-dark">$<?php echo number_format((float)$p['shipping_cost'], 2); ?></div>
                                        <div class="d-flex align-items-center gap-1 flex-wrap mt-1">
                                            <span class="badge <?php echo $p['payment_method'] === 'cod' ? 'bg-warning text-dark' : 'bg-primary'; ?> py-1 px-2" style="font-size: 0.68rem;">
                                                <?php echo strtoupper($p['payment_method']); ?>
                                            </span>
                                            <span class="badge <?php echo $p['payment_status'] === 'paid' ? 'bg-success' : 'bg-secondary'; ?> py-1 px-2" style="font-size: 0.68rem;">
                                                <?php echo strtoupper($p['payment_status']); ?>
                                            </span>
                                            <?php if ($p['payment_status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-warning btn-sm py-0 px-2 fw-bold text-dark btn-pay-now shadow-sm rounded-pill mt-1" 
                                                        data-id="<?php echo $p['id']; ?>" 
                                                        data-tracking="<?php echo htmlspecialchars($p['tracking_number']); ?>" 
                                                        data-amount="$<?php echo number_format((float)$p['shipping_cost'], 2); ?>"
                                                        style="font-size: 0.72rem;">
                                                    <i class="bi bi-credit-card-2-front me-1"></i> Pay Now
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Status -->
                                    <td>
                                        <span class="badge-status badge-status-<?php echo htmlspecialchars($p['status']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $p['status'])); ?>
                                        </span>
                                    </td>

                                    <!-- Delivery OTP (Sender has secret OTP to share with recipient) -->
                                    <td class="text-center">
                                        <span class="badge bg-dark font-monospace text-warning px-3 py-2 fs-6 shadow-sm" title="Share this code with the recipient for doorstep verification">
                                            <?php echo htmlspecialchars($p['delivery_otp']); ?>
                                        </span>
                                        <div class="text-muted" style="font-size: 0.65rem;">Share with Recipient</div>
                                    </td>

                                    <!-- Actions -->
                                    <td class="text-end pe-4">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?php echo BASE_URL; ?>index.php?track=<?php echo urlencode($p['tracking_number']); ?>" class="btn btn-outline-secondary" title="Live Timeline">
                                                <i class="bi bi-search me-1"></i> Track
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>waybill_print.php?code=<?php echo urlencode($p['tracking_number']); ?>" target="_blank" class="btn btn-outline-dark" title="Print QR Waybill">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Quick search filter for parcels table
    $('#filterParcelTable').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $('#customerShipmentTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    // Pay Now button click opens checkout modal
    $(document).on('click', '.btn-pay-now', function() {
        const id = $(this).data('id');
        const tracking = $(this).data('tracking');
        const amount = $(this).data('amount');
        if (typeof openPaymentModal === 'function') {
            openPaymentModal(id, tracking, amount);
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/payment_modal.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
