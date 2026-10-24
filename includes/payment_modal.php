<?php
// ========================================================
// SwiftScan Express - Reusable Online Payment Checkout Modal
// (includes/payment_modal.php)
// Features live card preview, Luhn formatting & test card autofill
// ========================================================
?>

<!-- Online Card Checkout Modal -->
<div class="modal fade" id="onlinePaymentModal" tabindex="-1" aria-labelledby="onlinePaymentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            
            <!-- Modal Header -->
            <div class="modal-header border-0 text-white py-3 px-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 rounded-circle bg-warning-subtle text-warning">
                        <i class="bi bi-credit-card-2-front-fill fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="onlinePaymentModalLabel">Express Checkout</h5>
                        <small class="text-secondary">256-Bit Encrypted Payment Gateway</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" id="btnClosePaymentModal"></button>
            </div>

            <!-- Pre-Payment Body -->
            <div class="modal-body p-4" id="paymentFormBody">
                
                <!-- Invoice Summary Pill -->
                <div class="p-3 rounded-3 bg-light border mb-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Consignment Invoice</div>
                        <div class="fw-bold font-monospace text-dark fs-6" id="payModalTrackingNumber">CR-2026-XXXXX</div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Total Due</div>
                        <div class="fs-4 fw-extrabold font-monospace" style="color: var(--primary);" id="payModalAmount">$0.00</div>
                    </div>
                </div>

                <!-- Interactive Card Graphic Preview -->
                <div class="card p-3 rounded-3 mb-3 text-white shadow-sm border-0" style="background: linear-gradient(135deg, #111827 0%, #0b0f19 100%); border: 1px solid rgba(16, 185, 129, 0.4) !important;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small text-secondary font-monospace"><i class="bi bi-shield-lock-fill me-1" style="color: var(--primary);"></i> SECURE CARD</span>
                        <i class="bi bi-credit-card fs-4" style="color: var(--primary);"></i>
                    </div>
                    <div class="font-monospace fs-5 letter-spacing-2 mb-3 text-center" id="cardPreviewNumber">•••• •••• •••• ••••</div>
                    <div class="d-flex justify-content-between align-items-end text-uppercase" style="font-size: 0.72rem;">
                        <div>
                            <div class="text-secondary">Cardholder</div>
                            <div class="fw-bold font-monospace text-truncate" style="max-width: 170px;" id="cardPreviewHolder">YOUR NAME</div>
                        </div>
                        <div class="text-end">
                            <div class="text-secondary">Expires</div>
                            <div class="fw-bold font-monospace" id="cardPreviewExp">MM/YY</div>
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <form id="onlineCardCheckoutForm" novalidate>
                    <input type="hidden" id="payModalParcelId" name="parcel_id" value="">

                    <!-- Cardholder -->
                    <div class="mb-3">
                        <label for="payCardHolder" class="form-label small fw-bold text-dark mb-1">Cardholder Name</label>
                        <input type="text" class="form-control" id="payCardHolder" name="card_holder" placeholder="Name on card" required>
                    </div>

                    <!-- Card Number -->
                    <div class="mb-3">
                        <label for="payCardNumber" class="form-label small fw-bold text-dark mb-1">Card Number</label>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace" id="payCardNumber" name="card_number" placeholder="4242 •••• •••• 4242" maxlength="19" required>
                            <span class="input-group-text bg-white"><i class="bi bi-credit-card-fill text-secondary"></i></span>
                        </div>
                    </div>

                    <!-- Expiry & CVV -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="payCardExp" class="form-label small fw-bold text-dark mb-1">Expiration</label>
                            <input type="text" class="form-control font-monospace text-center" id="payCardExp" name="card_exp" placeholder="MM/YY" maxlength="5" required>
                        </div>
                        <div class="col-6">
                            <label for="payCardCvv" class="form-label small fw-bold text-dark mb-1">CVV Code</label>
                            <input type="password" class="form-control font-monospace text-center" id="payCardCvv" name="card_cvv" placeholder="•••" maxlength="4" required>
                        </div>
                    </div>

                    <!-- 1-Click Test Card Autofill -->
                    <div class="mb-3 text-center">
                        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-3 rounded-pill small" id="btnAutofillTestCard">
                            <i class="bi bi-magic text-warning me-1"></i> Fill Test Card: <strong>4242 •••• 4242</strong>
                        </button>
                    </div>

                    <!-- Submit Pay Button -->
                    <button type="submit" id="btnSubmitPayment" class="btn btn-express w-100 py-3 fw-bold d-flex align-items-center justify-content-center gap-2 shadow">
                        <i class="bi bi-lock-fill"></i>
                        <span id="btnPayAmountText">Authorize & Pay Now</span>
                    </button>
                </form>
            </div>

            <!-- Payment Success Receipt View (hidden initially) -->
            <div class="modal-body p-4 text-center d-none" id="paymentSuccessReceipt">
                <div class="d-inline-flex p-3 rounded-circle bg-success text-white mb-2 shadow">
                    <i class="bi bi-patch-check-fill fs-1"></i>
                </div>
                <h4 class="fw-bold text-dark mb-1">Payment Successful!</h4>
                <p class="text-muted small mb-3">Your transaction has been authorized and recorded.</p>

                <div class="p-3 bg-light rounded-3 border text-start small mb-3">
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Transaction ID:</span>
                        <span class="fw-bold font-monospace text-dark" id="receiptTxnId">TXN_2026_XXXX</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Amount Paid:</span>
                        <span class="fw-bold font-monospace text-success fs-6" id="receiptAmount">$0.00</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Payment Method:</span>
                        <span class="badge bg-primary">Online Visa/Mastercard</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Card Charged:</span>
                        <span class="font-monospace text-dark" id="receiptCardLast4">•••• 4242</span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Authorized At:</span>
                        <span class="text-dark" id="receiptPaidAt">Just now</span>
                    </div>
                </div>

                <div class="d-flex flex-column gap-2">
                    <a href="#" id="receiptBtnPrint" target="_blank" class="btn btn-express fw-bold py-2">
                        <i class="bi bi-printer-fill me-1"></i> Print Validated QR Waybill
                    </a>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal" id="btnReceiptDone">
                        Return to Dashboard
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Format Card Number with Spaces (4-4-4-4)
    $('#payCardNumber').on('input', function() {
        let val = $(this).val().replace(/\D/g, '').substring(0, 16);
        let formatted = val.match(/.{1,4}/g)?.join(' ') || val;
        $(this).val(formatted);
        $('#cardPreviewNumber').text(formatted || '•••• •••• •••• ••••');
    });

    // 2. Cardholder Name Preview
    $('#payCardHolder').on('input', function() {
        let val = $(this).val();
        $('#cardPreviewHolder').text(val.toUpperCase() || 'YOUR NAME');
    });

    // 3. Format Expiry MM/YY
    $('#payCardExp').on('input', function() {
        let val = $(this).val().replace(/\D/g, '').substring(0, 4);
        if (val.length >= 2) {
            val = val.substring(0, 2) + '/' + val.substring(2);
        }
        $(this).val(val);
        $('#cardPreviewExp').text(val || 'MM/YY');
    });

    // 4. Test Card Autofill
    $('#btnAutofillTestCard').on('click', function() {
        $('#payCardHolder').val('John Doe').trigger('input');
        $('#payCardNumber').val('4242424242424242').trigger('input');
        $('#payCardExp').val('12/28').trigger('input');
        $('#payCardCvv').val('321');
        showToast('Test Visa card details filled!', 'info');
    });

    // 5. Open Modal Helper
    window.openPaymentModal = function(parcelId, trackingNumber, amountFmt) {
        $('#payModalParcelId').val(parcelId);
        $('#payModalTrackingNumber').text(trackingNumber);
        $('#payModalAmount').text(amountFmt);
        $('#btnPayAmountText').text('Authorize & Pay ' + amountFmt);

        // Reset views
        $('#paymentFormBody').removeClass('d-none');
        $('#paymentSuccessReceipt').addClass('d-none');
        $('#onlineCardCheckoutForm')[0].reset();
        $('#cardPreviewNumber').text('•••• •••• •••• ••••');
        $('#cardPreviewHolder').text('YOUR NAME');
        $('#cardPreviewExp').text('MM/YY');

        const modal = new bootstrap.Modal(document.getElementById('onlinePaymentModal'));
        modal.show();
    };

    // 6. Submit Payment Form via AJAX
    $('#onlineCardCheckoutForm').on('submit', function(e) {
        e.preventDefault();

        const $btn = $('#btnSubmitPayment');
        const originalBtnHtml = $btn.html();

        const cardHolder = $('#payCardHolder').val().trim();
        const cardNumber = $('#payCardNumber').val().replace(/\D/g, '');
        const cardExp    = $('#payCardExp').val().trim();
        const cardCvv    = $('#payCardCvv').val().trim();

        if (!cardHolder) {
            showToast('Please enter the cardholder name.', 'warning');
            $('#payCardHolder').focus();
            return;
        }

        if (cardNumber.length < 15) {
            showToast('Please enter a valid 16-digit card number.', 'warning');
            $('#payCardNumber').focus();
            return;
        }

        if (!cardExp || cardExp.length < 5) {
            showToast('Please enter expiration in MM/YY format.', 'warning');
            $('#payCardExp').focus();
            return;
        }

        if (cardCvv.length < 3) {
            showToast('Please enter the 3 or 4 digit CVV code.', 'warning');
            $('#payCardCvv').focus();
            return;
        }

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Processing Gateway Authorization...');

        $.ajax({
            url: '<?php echo BASE_URL; ?>ajax/payment_handler.php',
            type: 'POST',
            data: {
                action: 'process_card_payment',
                parcel_id: $('#payModalParcelId').val(),
                card_holder: cardHolder,
                card_number: cardNumber,
                card_exp: cardExp,
                card_cvv: cardCvv
            },
            success: function(response) {
                $btn.prop('disabled', false).html(originalBtnHtml);

                if (response.status === 'success') {
                    showToast('Payment Authorized Successfully!', 'success');

                    // Populate Receipt View
                    $('#receiptTxnId').text(response.transaction_id);
                    $('#receiptAmount').text(response.amount_paid);
                    $('#receiptCardLast4').text('•••• ' + response.card_last4);
                    $('#receiptPaidAt').text(response.paid_at);
                    $('#receiptBtnPrint').attr('href', '<?php echo BASE_URL; ?>waybill_print.php?code=' + encodeURIComponent(response.tracking_number));

                    // Switch view
                    $('#paymentFormBody').addClass('d-none');
                    $('#paymentSuccessReceipt').removeClass('d-none');

                    // If user closes modal after receipt, reload to reflect paid status
                    $('#btnReceiptDone, #btnClosePaymentModal').off('click.reload').on('click.reload', function() {
                        window.location.reload();
                    });
                } else {
                    showToast(response.message || 'Payment was declined.', 'danger');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalBtnHtml);
                showToast('Payment gateway connection error.', 'danger');
            }
        });
    });
});
</script>
