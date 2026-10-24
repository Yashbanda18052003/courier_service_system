<?php
// ========================================================
// SwiftScan Express - Customer Sign Up (register.php)
// Instant Account Creation & Automatic Dashboard Redirect
// ========================================================
$pageTitle = "Register Customer Account - SwiftScan Express";
require_once __DIR__ . '/includes/header.php';

// If user is already logged in, redirect to their role dashboard
if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'customer';
    header("Location: " . getDashboardUrl($role));
    exit();
}
?>

<div class="py-5" style="background: radial-gradient(circle at center top, #111827 0%, #0b0f19 100%); min-height: 85vh; display: flex; align-items: center;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">

                <!-- Registration Card -->
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="card-header border-0 text-center py-4" style="background: linear-gradient(135deg, #0b0f19 0%, #111827 100%); color: #ffffff;">
                        <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-circle mb-2" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4);">
                            <i class="bi bi-box-seam-fill fs-3" style="color: var(--primary);"></i>
                        </div>
                        <h4 class="fw-extrabold mb-1 text-white">Create Customer Account</h4>
                        <p class="text-secondary small mb-0">Book parcels, generate instant QR waybills & track shipments</p>
                    </div>

                    <div class="card-body p-4 p-md-5 bg-white">
                        
                        <!-- Alert Message Container -->
                        <div id="registerAlert" class="alert d-none py-2 px-3 small rounded-3 mb-3" role="alert"></div>

                        <form id="customerRegisterForm" novalidate>
                            <!-- Full Name -->
                            <div class="mb-3">
                                <label for="regFullName" class="form-label small fw-bold text-dark">
                                    <i class="bi bi-person me-1 text-warning"></i> Full Name / Business Name
                                </label>
                                <input type="text" class="form-control" id="regFullName" name="full_name" placeholder="John Doe or Acme Inc." required>
                            </div>

                            <!-- Email and Phone in 2 columns -->
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label for="regEmail" class="form-label small fw-bold text-dark">
                                        <i class="bi bi-envelope me-1 text-warning"></i> Email Address
                                    </label>
                                    <input type="email" class="form-control" id="regEmail" name="email" placeholder="john@example.com" required autocomplete="email">
                                </div>
                                <div class="col-md-6">
                                    <label for="regPhone" class="form-label small fw-bold text-dark">
                                        <i class="bi bi-telephone me-1 text-warning"></i> Phone Number
                                    </label>
                                    <input type="tel" class="form-control" id="regPhone" name="phone" placeholder="+1-555-0199" required autocomplete="tel">
                                </div>
                            </div>

                            <!-- Passwords in 2 columns -->
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label for="regPassword" class="form-label small fw-bold text-dark">
                                        <i class="bi bi-lock me-1 text-warning"></i> Password
                                    </label>
                                    <input type="password" class="form-control" id="regPassword" name="password" placeholder="Min. 6 chars" required autocomplete="new-password">
                                </div>
                                <div class="col-md-6">
                                    <label for="regConfirmPassword" class="form-label small fw-bold text-dark">
                                        <i class="bi bi-lock-fill me-1 text-warning"></i> Confirm Password
                                    </label>
                                    <input type="password" class="form-control" id="regConfirmPassword" name="confirm_password" placeholder="Re-type password" required autocomplete="new-password">
                                </div>
                            </div>

                            <!-- Terms Checkbox -->
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="termsCheck" required checked>
                                <label class="form-check-label small text-muted" for="termsCheck">
                                    I agree to SwiftScan Express <a href="#" class="text-decoration-none">Terms of Logistics Service</a> & Privacy Policy
                                </label>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" id="btnRegisterSubmit" class="btn btn-express w-100 py-2 fw-bold d-flex align-items-center justify-content-center gap-2">
                                <span>Complete Registration & Go to Dashboard</span>
                                <i class="bi bi-arrow-right"></i>
                            </button>
                        </form>

                    </div>

                    <div class="card-footer bg-light border-0 py-3 text-center">
                        <span class="small text-muted">Already have a customer account? </span>
                        <a href="<?php echo BASE_URL; ?>login.php" class="small fw-bold text-decoration-none" style="color: var(--primary);">
                            Sign In Here
                        </a>
                    </div>
                </div>

                <!-- Staff Portal notice -->
                <div class="text-center mt-3">
                    <span class="text-secondary small">Looking for staff or driver terminal? <a href="<?php echo BASE_URL; ?>staff_login.php" class="text-warning text-decoration-none fw-semibold">Open Staff Gateway &rarr;</a></span>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    $('#customerRegisterForm').on('submit', function(e) {
        e.preventDefault();

        const fullName        = $('#regFullName').val().trim();
        const email           = $('#regEmail').val().trim();
        const phone           = $('#regPhone').val().trim();
        const password        = $('#regPassword').val();
        const confirmPassword = $('#regConfirmPassword').val();
        const termsChecked    = $('#termsCheck').is(':checked');
        const $alert          = $('#registerAlert');
        const $btn            = $('#btnRegisterSubmit');

        $alert.addClass('d-none').removeClass('alert-danger alert-success').text('');

        // Client-side quick checks
        if (!fullName || fullName.length < 3) {
            $alert.removeClass('d-none').addClass('alert-danger').text('Please enter your full name (at least 3 characters).');
            $('#regFullName').focus();
            return;
        }

        if (!email) {
            $alert.removeClass('d-none').addClass('alert-danger').text('Please enter your email address.');
            $('#regEmail').focus();
            return;
        }

        if (!phone) {
            $alert.removeClass('d-none').addClass('alert-danger').text('Please enter your contact phone number.');
            $('#regPhone').focus();
            return;
        }

        if (!password || password.length < 6) {
            $alert.removeClass('d-none').addClass('alert-danger').text('Password must be at least 6 characters long.');
            $('#regPassword').focus();
            return;
        }

        if (password !== confirmPassword) {
            $alert.removeClass('d-none').addClass('alert-danger').text('Passwords do not match. Please re-enter.');
            $('#regConfirmPassword').focus();
            return;
        }

        if (!termsChecked) {
            $alert.removeClass('d-none').addClass('alert-danger').text('You must agree to the Terms of Service to create an account.');
            return;
        }

        const originalBtnHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Creating Account & Starting Session...');

        $.ajax({
            url: '<?php echo BASE_URL; ?>ajax/auth_handler.php',
            type: 'POST',
            data: {
                action: 'customer_register',
                full_name: fullName,
                email: email,
                phone: phone,
                password: password,
                confirm_password: confirmPassword
            },
            success: function(response) {
                if (response.status === 'success') {
                    $alert.removeClass('d-none').addClass('alert-success').html('<i class="bi bi-check-circle me-1"></i> ' + response.message);
                    showToast('Account created successfully! Loading your dashboard...', 'success');
                    setTimeout(function() {
                        window.location.href = response.redirect;
                    }, 500);
                } else {
                    $btn.prop('disabled', false).html(originalBtnHtml);
                    $alert.removeClass('d-none').addClass('alert-danger').html('<i class="bi bi-exclamation-triangle me-1"></i> ' + (response.message || 'Registration failed.'));
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalBtnHtml);
                $alert.removeClass('d-none').addClass('alert-danger').text('Server communication error. Please try again.');
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
