<?php
// ========================================================
// SwiftScan Express - Customer Sign In (login.php)
// ========================================================
$pageTitle = "Customer Sign In - SwiftScan Express";
require_once __DIR__ . '/includes/header.php';

// If user is already logged in, redirect to their role dashboard
if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'customer';
    header("Location: " . getDashboardUrl($role));
    exit();
}
?>

<div class="py-5" style="background: radial-gradient(circle at center top, #111827 0%, #0b0f19 100%); min-height: 80vh; display: flex; align-items: center;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                
                <!-- Main Login Card -->
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="card-header border-0 text-center py-4" style="background: linear-gradient(135deg, #0b0f19 0%, #111827 100%); color: #ffffff;">
                        <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-circle mb-2" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4);">
                            <i class="bi bi-person-fill fs-3" style="color: var(--primary);"></i>
                        </div>
                        <h4 class="fw-extrabold mb-1 text-white">Customer Sign In</h4>
                        <p class="text-secondary small mb-0">Manage bookings, track consignments & print QR waybills</p>
                    </div>

                    <div class="card-body p-4 p-md-5 bg-white">
                        
                        <!-- Alert Box -->
                        <?php if (isset($_GET['logged_out'])): ?>
                            <div class="alert alert-success py-2 px-3 small rounded-3 mb-3 d-flex align-items-center gap-2" role="alert">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>You have been safely signed out.</span>
                            </div>
                        <?php endif; ?>
                        <div id="loginAlert" class="alert d-none py-2 px-3 small rounded-3 mb-3" role="alert"></div>

                        <form id="customerLoginForm" novalidate>
                            <div class="mb-3">
                                <label for="loginEmail" class="form-label small fw-bold text-dark">
                                    <i class="bi bi-envelope me-1 text-warning"></i> Email Address
                                </label>
                                <input type="email" class="form-control" id="loginEmail" name="email" placeholder="name@example.com" required autocomplete="email">
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label for="loginPassword" class="form-label small fw-bold text-dark mb-0">
                                        <i class="bi bi-lock me-1 text-warning"></i> Password
                                    </label>
                                </div>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="loginPassword" name="password" placeholder="••••••••" required autocomplete="current-password">
                                    <button class="btn btn-outline-secondary" type="button" id="btnTogglePassword" title="Show / Hide Password">
                                        <i class="bi bi-eye-slash" id="pwdToggleIcon"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="rememberMe" checked>
                                    <label class="form-check-label small text-muted" for="rememberMe">
                                        Remember this browser
                                    </label>
                                </div>
                            </div>

                            <button type="submit" id="btnLoginSubmit" class="btn btn-express w-100 py-2 fw-bold d-flex align-items-center justify-content-center gap-2">
                                <span>Sign In to Dashboard</span>
                                <i class="bi bi-arrow-right"></i>
                            </button>
                        </form>

                        <!-- Demo Customer Credentials Helper -->
                        <div class="mt-4 pt-3 border-top text-center">
                            <span class="small text-muted d-block mb-2">Need a test customer account?</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-3 rounded-pill small" id="btnFillDemoCustomer">
                                <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Fill Demo: <strong>customer@courier.com</strong>
                            </button>
                        </div>

                    </div>

                    <div class="card-footer bg-light border-0 py-3 text-center">
                        <span class="small text-muted">Don't have an account yet? </span>
                        <a href="<?php echo BASE_URL; ?>register.php" class="small fw-bold text-decoration-none" style="color: var(--primary);">
                            Register here
                        </a>
                    </div>
                </div>

                <!-- Staff Operations Gateway Notice -->
                <div class="text-center mt-4">
                    <div class="p-3 rounded-3" style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1);">
                        <span class="text-light small">Are you an authorized Driver, Hub Manager or Administrator?</span>
                        <div class="mt-2">
                            <a href="<?php echo BASE_URL; ?>staff_login.php" class="btn btn-sm btn-outline-warning fw-semibold px-3 rounded-pill">
                                <i class="bi bi-shield-lock-fill me-1"></i> Open Staff & Operations Gateway &rarr;
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password toggle
    const btnTogglePassword = document.getElementById('btnTogglePassword');
    const loginPassword = document.getElementById('loginPassword');
    const pwdToggleIcon = document.getElementById('pwdToggleIcon');

    if (btnTogglePassword && loginPassword) {
        btnTogglePassword.addEventListener('click', function() {
            const isPassword = loginPassword.type === 'password';
            loginPassword.type = isPassword ? 'text' : 'password';
            pwdToggleIcon.className = isPassword ? 'bi bi-eye' : 'bi bi-eye-slash';
        });
    }

    // Demo Fill helper
    const btnFillDemoCustomer = document.getElementById('btnFillDemoCustomer');
    if (btnFillDemoCustomer) {
        btnFillDemoCustomer.addEventListener('click', function() {
            document.getElementById('loginEmail').value = 'customer@courier.com';
            document.getElementById('loginPassword').value = 'customer123';
            showToast('Demo customer credentials filled!', 'info');
        });
    }

    // AJAX Form Submission
    $('#customerLoginForm').on('submit', function(e) {
        e.preventDefault();

        const email = $('#loginEmail').val().trim();
        const password = $('#loginPassword').val();
        const $alert = $('#loginAlert');
        const $btn = $('#btnLoginSubmit');

        $alert.addClass('d-none').removeClass('alert-danger alert-success').text('');

        if (!email || !password) {
            $alert.removeClass('d-none').addClass('alert-danger').text('Please fill in both email and password.');
            return;
        }

        const originalBtnHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Authenticating...');

        $.ajax({
            url: '<?php echo BASE_URL; ?>ajax/auth_handler.php',
            type: 'POST',
            data: {
                action: 'customer_login',
                email: email,
                password: password
            },
            success: function(response) {
                if (response.status === 'success') {
                    $alert.removeClass('d-none').addClass('alert-success').html('<i class="bi bi-check-circle me-1"></i> ' + response.message);
                    showToast('Welcome back! Redirecting...', 'success');
                    setTimeout(function() {
                        window.location.href = response.redirect;
                    }, 500);
                } else {
                    $btn.prop('disabled', false).html(originalBtnHtml);
                    $alert.removeClass('d-none').addClass('alert-danger').html('<i class="bi bi-exclamation-triangle me-1"></i> ' + (response.message || 'Login failed.'));
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalBtnHtml);
                $alert.removeClass('d-none').addClass('alert-danger').text('Server error. Please verify your connection.');
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
