<?php
// ========================================================
// SwiftScan Express - Dedicated Payment & Checkout Page
// (customer/payment.php)
// ========================================================
$pageTitle = "Consignment Checkout & Payment - SwiftScan Express";
require_once __DIR__ . '/../includes/auth_guard.php';

requireRole(['customer', 'admin']);

$currentUser = getCurrentUser();
$userId = $currentUser['id'];

require_once __DIR__ . '/../includes/header.php';

$parcelId = (int)($_GET['parcel_id'] ?? 0);
$code     = trim($_GET['code'] ?? '');

$pdo = getDB();
$sql = "
    SELECT p.*, oh.hub_name AS origin_hub, dh.hub_name AS dest_hub, pm.transaction_id, pm.paid_at, pm.card_last4
    FROM parcels p
    LEFT JOIN hubs oh ON p.origin_hub_id = oh.id
    LEFT JOIN hubs dh ON p.destination_hub_id = dh.id
    LEFT JOIN payments pm ON p.id = pm.parcel_id
    WHERE (p.id = :id OR UPPER(p.tracking_number) = UPPER(:code))
    LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $parcelId, ':code' => $code]);
$parcel = $stmt->fetch();
?>

<div class="py-4 bg-light border-bottom">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>customer/index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Invoice Payment</li>
            </ol>
        </nav>
        <h3 class="fw-extrabold text-dark mb-0">
            <i class="bi bi-credit-card-2-front text-warning me-2"></i> Shipping Fee Payment & Settlement
        </h3>
        <p class="text-muted small mb-0">256-Bit Encrypted Payment Terminal for SwiftScan Express Logistics</p>
    </div>
</div>

<div class="container py-5">
    <?php if (!$parcel): ?>
        <div class="max-w-600 mx-auto text-center bg-white p-5 rounded-4 shadow-sm border">
            <i class="bi bi-exclamation-triangle-fill text-danger display-3 mb-3"></i>
            <h4 class="fw-bold">Consignment Not Found</h4>
            <p class="text-muted">The shipment invoice you are attempting to pay could not be located. Please check the ID or return to your dashboard.</p>
            <a href="<?php echo BASE_URL; ?>customer/index.php" class="btn btn-express px-4 py-2 mt-2">
                <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    <?php elseif ($parcel['payment_status'] === 'paid'): ?>
        <!-- Already Paid Invoice -->
        <div class="max-w-600 mx-auto bg-white p-5 rounded-4 shadow-sm border text-center">
            <div class="d-inline-flex p-3 rounded-circle bg-success text-white mb-3 shadow">
                <i class="bi bi-patch-check-fill display-4"></i>
            </div>
            <h3 class="fw-bold text-dark mb-1">Waybill is Fully Settled</h3>
            <p class="text-muted small mb-4">Payment has been received and verified for this shipment.</p>

            <div class="p-3 bg-light rounded-3 border text-start small mb-4">
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Waybill Number:</span>
                    <span class="fw-bold font-monospace"><?php echo htmlspecialchars($parcel['tracking_number']); ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Transaction ID:</span>
                    <span class="fw-bold font-monospace text-dark"><?php echo htmlspecialchars($parcel['transaction_id'] ?? 'PREPAID'); ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Amount Settled:</span>
                    <span class="fw-bold font-monospace text-success fs-6">$<?php echo number_format((float)$parcel['shipping_cost'], 2); ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Payment Mode:</span>
                    <span class="badge bg-primary text-uppercase"><?php echo htmlspecialchars($parcel['payment_method']); ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Settlement Date:</span>
                    <span><?php echo $parcel['paid_at'] ? date('M d, Y • h:i A', strtotime($parcel['paid_at'])) : 'Verified'; ?></span>
                </div>
            </div>

            <div class="d-flex justify-content-center gap-3">
                <a href="<?php echo BASE_URL; ?>waybill_print.php?code=<?php echo urlencode($parcel['tracking_number']); ?>" target="_blank" class="btn btn-express px-4 py-2">
                    <i class="bi bi-printer-fill me-1"></i> Print Waybill Label
                </a>
                <a href="<?php echo BASE_URL; ?>customer/index.php" class="btn btn-outline-secondary px-4 py-2">
                    <i class="bi bi-grid-fill me-1"></i> My Dashboard
                </a>
            </div>
        </div>

    <?php else: ?>
        <!-- Pending Invoice Checkout Form -->
        <div class="row g-4 justify-content-center">
            <!-- Left: Invoice Summary -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="bi bi-receipt text-warning me-2"></i> Consignment Invoice
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="p-3 rounded-3 text-center mb-4 text-white" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
                            <div class="small text-light opacity-75 text-uppercase">Total Amount Payable</div>
                            <div class="display-5 fw-extrabold text-warning font-monospace my-1">
                                $<?php echo number_format((float)$parcel['shipping_cost'], 2); ?>
                            </div>
                            <div class="badge bg-warning text-dark font-monospace">PENDING SETTLEMENT</div>
                        </div>

                        <div class="d-flex flex-column gap-2 small">
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">Waybill Number:</span>
                                <span class="fw-bold font-monospace"><?php echo htmlspecialchars($parcel['tracking_number']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">Recipient Name:</span>
                                <span class="fw-semibold text-dark"><?php echo htmlspecialchars($parcel['receiver_name']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">Destination:</span>
                                <span class="text-dark"><?php echo htmlspecialchars($parcel['receiver_city']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">Package Specs:</span>
                                <span><?php echo htmlspecialchars($parcel['parcel_type']); ?> (<?php echo number_format((float)$parcel['weight_kg'], 2); ?> kg)</span>
                            </div>
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">Origin Hub:</span>
                                <span><?php echo htmlspecialchars($parcel['origin_hub'] ?? 'Central Sorting Hub'); ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-2">
                                <span class="text-muted">Destination Hub:</span>
                                <span><?php echo htmlspecialchars($parcel['dest_hub'] ?? 'Regional Distribution Center'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Interactive Card Payment Form -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-lg rounded-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="bi bi-credit-card-2-front text-primary me-2"></i> Card Payment
                        </h5>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <i class="bi bi-shield-lock-fill me-1"></i> SSL 256-Bit
                        </span>
                    </div>

                    <div class="card-body p-4">
                        <!-- Card Graphic Preview -->
                        <div class="card p-3 rounded-3 mb-4 text-white shadow-sm border-0" style="background: linear-gradient(135deg, #111827 0%, #0b0f19 100%); border: 1px solid rgba(16, 185, 129, 0.4) !important;">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="small text-secondary font-monospace"><i class="bi bi-shield-lock-fill me-1" style="color: var(--primary);"></i> SECURE CARD</span>
                                <i class="bi bi-credit-card fs-4" style="color: var(--primary);"></i>
                            </div>
                            <div class="font-monospace fs-5 letter-spacing-2 mb-3 text-center" id="cardGraphicNum">•••• •••• •••• ••••</div>
                            <div class="d-flex justify-content-between align-items-end text-uppercase" style="font-size: 0.72rem;">
                                <div>
                                    <div class="text-secondary">Cardholder</div>
                                    <div class="fw-bold font-monospace text-truncate" style="max-width: 170px;" id="cardGraphicHolder">YOUR NAME</div>
                                </div>
                                <div class="text-end">
                                    <div class="text-secondary">Expires</div>
                                    <div class="fw-bold font-monospace" id="cardGraphicExp">MM/YY</div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Form -->
                        <form id="dedicatedPaymentForm" novalidate>
                            <input type="hidden" name="parcel_id" value="<?php echo $parcel['id']; ?>">

                            <div class="mb-3">
                                <label for="cardHolderName" class="form-label small fw-bold text-dark mb-1">Cardholder Name</label>
                                <input type="text" class="form-control" id="cardHolderName" name="card_holder" placeholder="John Doe" required>
                            </div>

                            <div class="mb-3">
                                <label for="cardInputNumber" class="form-label small fw-bold text-dark mb-1">Card Number</label>
                                <div class="input-group">
                                    <input type="text" class="form-control font-monospace" id="cardInputNumber" name="card_number" placeholder="4242 •••• •••• 4242" maxlength="19" required>
                                    <span class="input-group-text bg-white"><i class="bi bi-credit-card-fill text-secondary"></i></span>
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label for="cardInputExp" class="form-label small fw-bold text-dark mb-1">Expiration</label>
                                    <input type="text" class="form-control font-monospace text-center" id="cardInputExp" name="card_exp" placeholder="MM/YY" maxlength="5" required>
                                </div>
                                <div class="col-6">
                                    <label for="cardInputCvv" class="form-label small fw-bold text-dark mb-1">CVV Security Code</label>
                                    <input type="password" class="form-control font-monospace text-center" id="cardInputCvv" name="card_cvv" placeholder="•••" maxlength="4" required>
                                </div>
                            </div>

                            <div class="mb-4 text-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-3 rounded-pill small" id="btnFillTest">
                                    <i class="bi bi-magic text-warning me-1"></i> Auto-fill Test Visa: <strong>4242 •••• 4242</strong>
                                </button>
                            </div>

                            <button type="submit" id="btnSubmitDedicatedPay" class="btn btn-express w-100 py-3 fw-bold fs-6 d-flex align-items-center justify-content-center gap-2 shadow">
                                <i class="bi bi-lock-fill"></i>
                                <span>Authorize & Pay $<?php echo number_format((float)$parcel['shipping_cost'], 2); ?></span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Card formatting
    $('#cardInputNumber').on('input', function() {
        let val = $(this).val().replace(/\D/g, '').substring(0, 16);
        let formatted = val.match(/.{1,4}/g)?.join(' ') || val;
        $(this).val(formatted);
        $('#cardGraphicNum').text(formatted || '•••• •••• •••• ••••');
    });

    $('#cardHolderName').on('input', function() {
        let val = $(this).val();
        $('#cardGraphicHolder').text(val.toUpperCase() || 'YOUR NAME');
    });

    $('#cardInputExp').on('input', function() {
        let val = $(this).val().replace(/\D/g, '').substring(0, 4);
        if (val.length >= 2) {
            val = val.substring(0, 2) + '/' + val.substring(2);
        }
        $(this).val(val);
        $('#cardGraphicExp').text(val || 'MM/YY');
    });

    $('#btnFillTest').on('click', function() {
        $('#cardHolderName').val('John Doe').trigger('input');
        $('#cardInputNumber').val('4242424242424242').trigger('input');
        $('#cardInputExp').val('12/28').trigger('input');
        $('#cardInputCvv').val('321');
        showToast('Test Visa credentials populated!', 'info');
    });

    $('#dedicatedPaymentForm').on('submit', function(e) {
        e.preventDefault();

        const $btn = $('#btnSubmitDedicatedPay');
        const originalBtnHtml = $btn.html();

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Processing Authorization...');

        const formData = $(this).serialize() + '&action=process_card_payment';

        $.ajax({
            url: '<?php echo BASE_URL; ?>ajax/payment_handler.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.status === 'success') {
                    showToast('Payment Authorized Successfully! Redirecting...', 'success');
                    setTimeout(function() {
                        window.location.reload();
                    }, 800);
                } else {
                    $btn.prop('disabled', false).html(originalBtnHtml);
                    showToast(response.message || 'Payment failed.', 'danger');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalBtnHtml);
                showToast('Payment gateway error. Please try again.', 'danger');
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
