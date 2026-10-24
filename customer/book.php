<?php
// ========================================================
// SwiftScan Express - Book New Consignment & Dynamic Rate Estimator
// (customer/book.php)
// Step 5: Interactive Parcel Booking & Price Calculator
// ========================================================
$pageTitle = "Book New Parcel - SwiftScan Express";
require_once __DIR__ . '/../includes/auth_guard.php';

requireRole(['customer', 'admin']);

$currentUser = getCurrentUser();
$userId = $currentUser['id'];

require_once __DIR__ . '/../includes/header.php';

// Fetch Active Logistics Hubs from DB
$pdo = getDB();
$hubsStmt = $pdo->query("SELECT id, hub_name, hub_code, city, state FROM hubs ORDER BY city ASC");
$hubs = $hubsStmt->fetchAll();

// Fetch customer's latest phone/address if available from past parcels
$prevSenderStmt = $pdo->prepare("
    SELECT sender_phone, sender_address, sender_city, sender_pincode, origin_hub_id 
    FROM parcels 
    WHERE sender_id = :user_id 
    ORDER BY id DESC LIMIT 1
");
$prevSenderStmt->execute([':user_id' => $userId]);
$prevSender = $prevSenderStmt->fetch() ?: [];
?>

<div class="py-4 bg-light border-bottom">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb small mb-1">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>customer/index.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Book New Consignment</li>
                    </ol>
                </nav>
                <h3 class="fw-extrabold text-dark mb-0">
                    <i class="bi bi-box-seam-fill me-2" style="color: var(--primary);"></i> Book Parcel & Dynamic Rate Calculator
                </h3>
                <p class="text-muted small mb-0">Schedule doorstep courier pickup, route across hubs, and generate scannable QR waybill.</p>
            </div>
            <div>
                <a href="<?php echo BASE_URL; ?>customer/index.php" class="btn btn-outline-secondary btn-sm fw-semibold">
                    <i class="bi bi-arrow-left me-1"></i> Back to Shipments
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container py-4">
    <form id="bookingForm" novalidate>
        <div class="row g-4">
            
            <!-- Left Column: Booking Form Fields -->
            <div class="col-lg-8">
                
                <!-- Section 1: Sender & Pickup Information -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
                        <div class="rounded-circle p-2 bg-warning-subtle text-warning">
                            <i class="bi bi-geo-alt-fill"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">1. Sender & Origin Pickup Details</h5>
                            <small class="text-muted">Origin collection address and nearest dispatch hub</small>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="senderName" class="form-label small fw-bold text-dark">Sender Full Name / Company</label>
                                <input type="text" class="form-control" id="senderName" name="sender_name" value="<?php echo htmlspecialchars($currentUser['full_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="senderPhone" class="form-label small fw-bold text-dark">Sender Contact Phone</label>
                                <input type="tel" class="form-control" id="senderPhone" name="sender_phone" value="<?php echo htmlspecialchars($prevSender['sender_phone'] ?? '+1-555-0103'); ?>" placeholder="+1-555-0100" required>
                            </div>
                            <div class="col-12">
                                <label for="senderAddress" class="form-label small fw-bold text-dark">Pickup Street Address</label>
                                <input type="text" class="form-control" id="senderAddress" name="sender_address" value="<?php echo htmlspecialchars($prevSender['sender_address'] ?? '350 5th Ave, Floor 14'); ?>" placeholder="Suite, Apt, Street" required>
                            </div>
                            <div class="col-md-4">
                                <label for="senderCity" class="form-label small fw-bold text-dark">Sender City</label>
                                <input type="text" class="form-control" id="senderCity" name="sender_city" value="<?php echo htmlspecialchars($prevSender['sender_city'] ?? 'New York'); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="senderPincode" class="form-label small fw-bold text-dark">Postal Code</label>
                                <input type="text" class="form-control" id="senderPincode" name="sender_pincode" value="<?php echo htmlspecialchars($prevSender['sender_pincode'] ?? '10001'); ?>" required>
                            </div>
                            <div class="col-md-5">
                                <label for="originHubId" class="form-label small fw-bold text-dark">Origin Dispatch Hub</label>
                                <select class="form-select rate-calc-trigger" id="originHubId" name="origin_hub_id" required>
                                    <option value="">-- Select Origin Hub --</option>
                                    <?php foreach ($hubs as $hub): ?>
                                        <option value="<?php echo $hub['id']; ?>" <?php echo ((int)($prevSender['origin_hub_id'] ?? 1) === (int)$hub['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($hub['city'] . ' - ' . $hub['hub_name'] . ' (' . $hub['hub_code'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Recipient & Destination Delivery Information -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
                        <div class="rounded-circle p-2 bg-primary-subtle text-primary">
                            <i class="bi bi-person-check-fill"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">2. Recipient & Destination Details</h5>
                            <small class="text-muted">Where should our courier deliver this package?</small>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="receiverName" class="form-label small fw-bold text-dark">Recipient Full Name</label>
                                <input type="text" class="form-control" id="receiverName" name="receiver_name" placeholder="Alice Smith" required>
                            </div>
                            <div class="col-md-6">
                                <label for="receiverPhone" class="form-label small fw-bold text-dark">Recipient Phone Number</label>
                                <input type="tel" class="form-control" id="receiverPhone" name="receiver_phone" placeholder="+1-555-987-6543" required>
                            </div>
                            <div class="col-12">
                                <label for="receiverAddress" class="form-label small fw-bold text-dark">Delivery Street Address</label>
                                <input type="text" class="form-control" id="receiverAddress" name="receiver_address" placeholder="742 Evergreen Terrace, Unit 4B" required>
                            </div>
                            <div class="col-md-4">
                                <label for="receiverCity" class="form-label small fw-bold text-dark">Destination City</label>
                                <input type="text" class="form-control" id="receiverCity" name="receiver_city" placeholder="Los Angeles" required>
                            </div>
                            <div class="col-md-3">
                                <label for="receiverPincode" class="form-label small fw-bold text-dark">Postal Code</label>
                                <input type="text" class="form-control" id="receiverPincode" name="receiver_pincode" placeholder="90001" required>
                            </div>
                            <div class="col-md-5">
                                <label for="destinationHubId" class="form-label small fw-bold text-dark">Destination Sorting Hub</label>
                                <select class="form-select rate-calc-trigger" id="destinationHubId" name="destination_hub_id" required>
                                    <option value="">-- Select Destination Hub --</option>
                                    <?php foreach ($hubs as $hub): ?>
                                        <option value="<?php echo $hub['id']; ?>" <?php echo ((int)$hub['id'] === 2) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($hub['city'] . ' - ' . $hub['hub_name'] . ' (' . $hub['hub_code'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Parcel Category & Weight Calculator -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
                        <div class="rounded-circle p-2 bg-success-subtle text-success">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">3. Package Specifications & Weight</h5>
                            <small class="text-muted">Dynamic pricing automatically calculates based on specifications</small>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        
                        <!-- Parcel Type Selection -->
                        <label class="form-label small fw-bold text-dark mb-2">Consignment Category</label>
                        <div class="row g-2 mb-4">
                            <div class="col-6 col-md-3">
                                <input type="radio" class="btn-check rate-calc-trigger" name="parcel_type" id="type_doc" value="Express Document" autocomplete="off">
                                <label class="btn btn-outline-secondary w-100 p-3 text-start h-100 rounded-3" for="type_doc">
                                    <i class="bi bi-file-earmark-text fs-4 text-warning d-block mb-1"></i>
                                    <span class="fw-bold d-block small">Document</span>
                                    <span class="text-muted" style="font-size: 0.72rem;">Flat letters & files</span>
                                </label>
                            </div>
                            <div class="col-6 col-md-3">
                                <input type="radio" class="btn-check rate-calc-trigger" name="parcel_type" id="type_std" value="Standard Box" autocomplete="off" checked>
                                <label class="btn btn-outline-secondary w-100 p-3 text-start h-100 rounded-3" for="type_std">
                                    <i class="bi bi-box-seam fs-4 text-warning d-block mb-1"></i>
                                    <span class="fw-bold d-block small">Standard Box</span>
                                    <span class="text-muted" style="font-size: 0.72rem;">Apparel, goods</span>
                                </label>
                            </div>
                            <div class="col-6 col-md-3">
                                <input type="radio" class="btn-check rate-calc-trigger" name="parcel_type" id="type_fragile" value="Fragile / Electronics" autocomplete="off">
                                <label class="btn btn-outline-secondary w-100 p-3 text-start h-100 rounded-3" for="type_fragile">
                                    <i class="bi bi-laptop fs-4 text-warning d-block mb-1"></i>
                                    <span class="fw-bold d-block small">Electronics</span>
                                    <span class="text-muted" style="font-size: 0.72rem;">Fragile care</span>
                                </label>
                            </div>
                            <div class="col-6 col-md-3">
                                <input type="radio" class="btn-check rate-calc-trigger" name="parcel_type" id="type_cargo" value="Heavy Cargo" autocomplete="off">
                                <label class="btn btn-outline-secondary w-100 p-3 text-start h-100 rounded-3" for="type_cargo">
                                    <i class="bi bi-truck fs-4 text-warning d-block mb-1"></i>
                                    <span class="fw-bold d-block small">Heavy Cargo</span>
                                    <span class="text-muted" style="font-size: 0.72rem;">Bulky shipments</span>
                                </label>
                            </div>
                        </div>

                        <!-- Weight (kg) Input + Quick Increments -->
                        <div class="mb-3">
                            <label for="weightInput" class="form-label small fw-bold text-dark d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-speedometer2 text-warning me-1"></i> Package Weight (Kilograms)</span>
                                <span class="badge bg-light text-secondary border font-monospace" id="weightDisplay">2.50 kg</span>
                            </label>
                            <div class="input-group">
                                <input type="number" step="0.1" min="0.1" max="100" class="form-control form-control-lg font-monospace rate-calc-trigger" id="weightInput" name="weight_kg" value="2.50" required>
                                <span class="input-group-text bg-white fw-bold">KG</span>
                            </div>
                            
                            <!-- Quick Weight Presets -->
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <span class="small text-muted align-self-center">Presets:</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill btn-set-weight" data-weight="0.5">0.5 kg</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill btn-set-weight" data-weight="1.0">1.0 kg</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill btn-set-weight" data-weight="2.5">2.5 kg</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill btn-set-weight" data-weight="5.0">5.0 kg</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill btn-set-weight" data-weight="10.0">10.0 kg</button>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Section 4: Payment Mode Selection -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
                        <div class="rounded-circle p-2 bg-info-subtle text-info">
                            <i class="bi bi-wallet2"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">4. Payment Mode</h5>
                            <small class="text-muted">Select how the shipping fee will be settled</small>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check p-3 rounded-3 border h-100 d-flex align-items-start gap-2 payment-check-box active-choice">
                                    <input class="form-check-input mt-1" type="radio" name="payment_method" id="pay_online" value="online" checked>
                                    <label class="form-check-label w-100" for="pay_online">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span class="fw-bold text-dark"><i class="bi bi-credit-card-2-front text-primary me-1"></i> Prepaid Online Checkout</span>
                                            <span class="badge bg-success-subtle text-success">Instant</span>
                                        </div>
                                        <p class="small text-muted mb-0">Pay now via debit/credit card. Waybill is instantly validated for express dispatch.</p>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check p-3 rounded-3 border h-100 d-flex align-items-start gap-2 payment-check-box">
                                    <input class="form-check-input mt-1" type="radio" name="payment_method" id="pay_cod" value="cod">
                                    <label class="form-check-label w-100" for="pay_cod">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span class="fw-bold text-dark"><i class="bi bi-cash-stack text-warning me-1"></i> Cash on Delivery (COD)</span>
                                            <span class="badge bg-warning text-dark">Doorstep</span>
                                        </div>
                                        <p class="small text-muted mb-0">Delivery driver collects cash directly from the recipient at doorstep upon OTP confirmation.</p>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Sticky Live Cost Estimator -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-lg rounded-4 sticky-top" style="top: 90px; border-top: 4px solid var(--primary) !important;">
                    <div class="card-header bg-white py-3 border-bottom">
                        <div class="d-flex align-items-center justify-content-between">
                            <h5 class="fw-bold mb-0 text-dark">
                                <i class="bi bi-calculator me-1" style="color: var(--primary);"></i> Live Price Breakdown
                            </h5>
                            <span class="badge bg-light text-muted border" id="calcStatus">Updated</span>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <!-- Big Total Banner -->
                        <div class="p-3 rounded-3 text-center mb-4" style="background: linear-gradient(135deg, #0b0f19 0%, #111827 100%); color: #ffffff; border: 1px solid rgba(16, 185, 129, 0.3);">
                            <div class="small text-light opacity-75 text-uppercase fw-bold letter-spacing-1">Estimated Shipping Total</div>
                            <div class="display-5 fw-extrabold my-1 font-monospace" style="color: var(--primary);" id="displayTotalAmount">$58.25</div>
                            <div class="small text-light opacity-75" id="displayEstDays">
                                <i class="bi bi-clock-history me-1"></i> 2 - 3 Business Days
                            </div>
                        </div>

                        <!-- Itemized Breakdown -->
                        <div class="d-flex flex-column gap-2 mb-4">
                            <div class="d-flex justify-content-between small py-1 border-bottom">
                                <span class="text-muted">Category Base Rate:</span>
                                <span class="fw-bold text-dark font-monospace" id="displayBaseFee">$25.00</span>
                            </div>
                            <div class="d-flex justify-content-between small py-1 border-bottom">
                                <span class="text-muted">Excess Weight Surcharge:</span>
                                <span class="fw-bold text-dark font-monospace" id="displayWeightFee">$21.25</span>
                            </div>
                            <div class="d-flex justify-content-between small py-1 border-bottom">
                                <span class="text-muted">Interstate Hub Routing:</span>
                                <span class="fw-bold text-dark font-monospace" id="displayInterstateFee">$12.00</span>
                            </div>
                            <div class="d-flex justify-content-between small py-1">
                                <span class="text-muted">Security & QR Waybill:</span>
                                <span class="badge bg-success-subtle text-success">Included Free</span>
                            </div>
                        </div>

                        <!-- Action Submit Button -->
                        <button type="submit" id="btnBookSubmit" class="btn btn-express w-100 py-3 fw-bold fs-6 d-flex align-items-center justify-content-center gap-2 shadow">
                            <i class="bi bi-qr-code-scan"></i>
                            <span>Confirm & Generate QR Waybill</span>
                        </button>

                        <div class="text-center mt-3">
                            <span class="small text-muted d-block">
                                <i class="bi bi-shield-check text-success me-1"></i> 4-Digit Doorstep OTP Guaranteed
                            </span>
                        </div>

                        <!-- Trust Features -->
                        <div class="mt-4 pt-3 border-top small text-muted">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-check-circle-fill text-warning"></i>
                                <span>High-density scannable waybill QR sticker</span>
                            </div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-check-circle-fill text-warning"></i>
                                <span>Real-time SMS/web milestone tracking</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-check-circle-fill text-warning"></i>
                                <span>Direct driver Proof-of-Delivery signoff</span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<!-- Booking Confirmation Modal -->
<div class="modal fade" id="bookingSuccessModal" tabindex="-1" aria-labelledby="bookingSuccessModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 text-white text-center py-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
                <div class="w-100 text-center">
                    <div class="d-inline-flex p-3 rounded-circle bg-success text-white mb-2 shadow">
                        <i class="bi bi-check-lg fs-2"></i>
                    </div>
                    <h4 class="fw-bold mb-0 text-white">Consignment Booked!</h4>
                    <p class="text-secondary small mb-0">Waybill is generated and registered in central hub dispatch</p>
                </div>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="small text-muted text-uppercase fw-bold mb-1">Waybill Tracking Number</div>
                <div class="display-6 fw-extrabold text-dark font-monospace mb-2" id="modalTrackingNumber">CR-2026-XXXXX</div>
                
                <div class="p-3 bg-light rounded-3 border my-3 text-start small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Total Cost:</span>
                        <span class="fw-bold font-monospace" id="modalTotalCost">$0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Payment Mode:</span>
                        <span class="badge bg-primary" id="modalPaymentMode">ONLINE</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Secret Doorstep OTP:</span>
                        <span class="badge bg-dark text-warning font-monospace px-2" id="modalDeliveryOtp">4821</span>
                    </div>
                </div>

                <p class="small text-muted mb-0">
                    Share the 4-digit OTP with your recipient. Our courier will scan this waybill QR code and verify the OTP upon final doorstep handoff.
                </p>
            </div>
            <div class="modal-footer bg-light p-3 d-flex flex-column gap-2">
                <button type="button" id="modalBtnPayNow" class="btn btn-warning w-100 fw-bold d-flex align-items-center justify-content-center gap-2 shadow-sm d-none">
                    <i class="bi bi-credit-card-2-front-fill"></i> Pay Shipping Fee Online Now
                </button>
                <a href="#" id="modalBtnPrint" target="_blank" class="btn btn-express w-100 fw-bold d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-printer-fill"></i> Print Scannable QR Waybill Sticker
                </a>
                <div class="d-flex w-100 gap-2">
                    <a href="<?php echo BASE_URL; ?>customer/index.php" class="btn btn-outline-secondary w-50 small fw-semibold">
                        <i class="bi bi-grid-fill me-1"></i> My Dashboard
                    </a>
                    <a href="#" id="modalBtnTrack" class="btn btn-outline-dark w-50 small fw-semibold">
                        <i class="bi bi-search me-1"></i> Track Live
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Real-Time Dynamic Shipping Rate Calculator
    function recalculateShippingRate() {
        const weightKg = parseFloat($('#weightInput').val()) || 1.0;
        const parcelType = $('input[name="parcel_type"]:checked').val() || 'Standard Box';
        const originHubId = parseInt($('#originHubId').val()) || 0;
        const destinationHubId = parseInt($('#destinationHubId').val()) || 0;

        $('#calcStatus').text('Calculating...').removeClass('bg-light').addClass('bg-warning text-dark');

        $.ajax({
            url: '<?php echo BASE_URL; ?>ajax/parcel_handler.php',
            type: 'GET',
            data: {
                action: 'calculate_rate',
                weight_kg: weightKg,
                parcel_type: parcelType,
                origin_hub_id: originHubId,
                destination_hub_id: destinationHubId
            },
            success: function(response) {
                if (response.status === 'success') {
                    const d = response.data;
                    $('#displayTotalAmount').text(d.total_fmt);
                    $('#displayBaseFee').text(d.base_fee_fmt);
                    $('#displayWeightFee').text(d.weight_fee_fmt);
                    $('#displayInterstateFee').text(d.interstate_fee_fmt);
                    $('#displayEstDays').html('<i class="bi bi-clock-history me-1"></i> ' + d.estimated_days);
                    $('#weightDisplay').text(d.weight_kg.toFixed(2) + ' kg');
                    $('#calcStatus').text('Updated').removeClass('bg-warning text-dark').addClass('bg-light text-muted');
                }
            },
            error: function() {
                $('#calcStatus').text('Error').removeClass('bg-warning').addClass('bg-danger text-white');
            }
        });
    }

    // Trigger calculation on changes
    $('.rate-calc-trigger').on('change keyup input', function() {
        recalculateShippingRate();
    });

    // Preset weight pills
    $('.btn-set-weight').on('click', function() {
        const w = $(this).data('weight');
        $('#weightInput').val(w);
        recalculateShippingRate();
    });

    // Run initial calculation
    recalculateShippingRate();

    // Payment Box active highlight toggle
    $('input[name="payment_method"]').on('change', function() {
        $('.payment-check-box').removeClass('border-primary bg-light-subtle');
        $(this).closest('.payment-check-box').addClass('border-primary bg-light-subtle');
    });

    // Form Submission for Booking
    $('#bookingForm').on('submit', function(e) {
        e.preventDefault();

        const $btn = $('#btnBookSubmit');
        const originalBtnHtml = $btn.html();

        // Validation
        const receiverName = $('#receiverName').val().trim();
        const receiverPhone = $('#receiverPhone').val().trim();
        const receiverAddress = $('#receiverAddress').val().trim();
        const receiverCity = $('#receiverCity').val().trim();
        const originHub = $('#originHubId').val();
        const destHub = $('#destinationHubId').val();

        if (!receiverName || !receiverPhone || !receiverAddress || !receiverCity) {
            showToast('Please fill in complete recipient delivery details.', 'warning');
            $('#receiverName').focus();
            return;
        }

        if (!originHub || !destHub) {
            showToast('Please select both origin and destination logistics hubs.', 'warning');
            return;
        }

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Booking Consignment & Encoding QR...');

        const formData = $(this).serialize() + '&action=book_parcel';

        $.ajax({
            url: '<?php echo BASE_URL; ?>ajax/parcel_handler.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                $btn.prop('disabled', false).html(originalBtnHtml);

                if (response.status === 'success') {
                    showToast('Shipment successfully booked!', 'success');

                    // Populate and trigger confirmation modal
                    $('#modalTrackingNumber').text(response.tracking_number);
                    $('#modalTotalCost').text('$' + parseFloat(response.total_amount).toFixed(2));
                    $('#modalPaymentMode').text(response.payment_method.toUpperCase());
                    $('#modalDeliveryOtp').text(response.delivery_otp);

                    $('#modalBtnPrint').attr('href', '<?php echo BASE_URL; ?>waybill_print.php?code=' + encodeURIComponent(response.tracking_number));
                    $('#modalBtnTrack').attr('href', '<?php echo BASE_URL; ?>index.php?track=' + encodeURIComponent(response.tracking_number));

                    if (response.payment_method === 'online') {
                        $('#modalBtnPayNow').removeClass('d-none').off('click').on('click', function() {
                            const successModal = bootstrap.Modal.getInstance(document.getElementById('bookingSuccessModal'));
                            if (successModal) successModal.hide();
                            openPaymentModal(response.parcel_id, response.tracking_number, '$' + parseFloat(response.total_amount).toFixed(2));
                        });
                    } else {
                        $('#modalBtnPayNow').addClass('d-none');
                    }

                    const modal = new bootstrap.Modal(document.getElementById('bookingSuccessModal'));
                    modal.show();
                } else {
                    showToast(response.message || 'Unable to book shipment.', 'danger');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalBtnHtml);
                showToast('Server communication error. Please try again.', 'danger');
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/payment_modal.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
