<?php
// ========================================================
// SwiftScan Express - Dedicated Staff & Operations Gateway
// (staff_login.php)
// Step 8: Separate Authentication Portal for Drivers, Hub Managers & Admins
// ========================================================
$pageTitle = "Operations & Staff Gateway - SwiftScan Express";
require_once __DIR__ . '/includes/header.php';

// If already logged in as a staff member, redirect immediately to their dashboard
if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'customer';
    if (in_array($role, ['driver', 'hub_manager', 'admin'])) {
        header("Location: " . getDashboardUrl($role));
        exit();
    }
}
?>

<div class="py-5" style="background: radial-gradient(circle at center top, #111827 0%, #090d16 100%); min-height: 85vh; display: flex; align-items: center;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">

                <!-- Gateway Security Badge Banner -->
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35);">
                        <i class="bi bi-shield-lock-fill" style="color: var(--primary);"></i>
                        <span class="small fw-bold font-monospace" style="color: var(--primary);">RESTRICTED LOGISTICS TERMINAL</span>
                    </div>
                    <h2 class="fw-extrabold text-white mb-1">Staff Operations Gateway</h2>
                    <p class="text-secondary small mb-0">Authorized access for Delivery Drivers, Sorting Hub Managers & Administrators</p>
                </div>

                <!-- Main Staff Login Card -->
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden" style="border: 1px solid rgba(255, 255, 255, 0.1) !important;">
                    
                    <!-- Role Switcher Pills -->
                    <div class="card-header border-0 py-3 px-4" style="background: #0b0f19;">
                        <div class="small text-secondary fw-bold text-uppercase mb-2" style="font-size: 0.68rem; letter-spacing: 0.5px;">
                            Quick Preset Terminal:
                        </div>
                        <div class="row g-2 text-center" id="staffRoleSelector">
                            <div class="col-4">
                                <button type="button" class="btn btn-outline-success w-100 py-2 small fw-bold staff-role-btn active" data-email="driver@courier.com" data-pwd="driver123" data-role="Driver">
                                    <i class="bi bi-bicycle d-block fs-5 mb-1"></i>
                                    <span>Driver</span>
                                </button>
                            </div>
                            <div class="col-4">
                                <button type="button" class="btn btn-outline-success w-100 py-2 small fw-bold staff-role-btn" data-email="hub@courier.com" data-pwd="hub123" data-role="Hub Manager">
                                    <i class="bi bi-building d-block fs-5 mb-1"></i>
                                    <span>Hub Mgr</span>
                                </button>
                            </div>
                            <div class="col-4">
                                <button type="button" class="btn btn-outline-success w-100 py-2 small fw-bold staff-role-btn" data-email="admin@courier.com" data-pwd="admin123" data-role="Super Admin">
                                    <i class="bi bi-shield-check d-block fs-5 mb-1"></i>
                                    <span>Admin</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4 p-md-5 bg-white">
                        
                        <!-- Role Helper Card -->
                        <div class="p-3 rounded-3 bg-light border mb-4 d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-info-circle-fill fs-5" style="color: var(--primary);"></i>
                                <div>
                                    <div class="fw-bold small text-dark" id="roleHelperTitle">Courier Driver Access</div>
                                    <div class="text-muted" style="font-size: 0.72rem;" id="roleHelperDesc">
                                        Handheld camera QR scanner, run sheet & doorstep OTP confirmation
                                    </div>
                                </div>
                            </div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace px-2 py-1" id="roleHelperBadge">DRIVER</span>
                        </div>

                        <!-- Alert Box -->
                        <div id="staffAlert" class="alert d-none py-2 px-3 small rounded-3 mb-3" role="alert"></div>

                        <!-- Form -->
                        <form id="staffLoginForm" novalidate>
                            <div class="mb-3">
                                <label for="staffEmail" class="form-label small fw-bold text-dark">
                                    <i class="bi bi-person-badge me-1" style="color: var(--primary);"></i> Staff ID / Corporate Email
                                </label>
                                <input type="email" class="form-control font-monospace" id="staffEmail" name="email" value="driver@courier.com" required autocomplete="email">
                            </div>

                            <div class="mb-3">
                                <label for="staffPassword" class="form-label small fw-bold text-dark">
                                    <i class="bi bi-lock me-1" style="color: var(--primary);"></i> Security Password
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control font-monospace" id="staffPassword" name="password" value="driver123" required autocomplete="current-password">
                                    <button class="btn btn-outline-secondary" type="button" id="btnToggleStaffPwd" title="Show / Hide Password">
                                        <i class="bi bi-eye-slash" id="pwdStaffIcon"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-4">
                                <span class="text-muted" style="font-size: 0.72rem;">
                                    <i class="bi bi-shield-shaded me-1 text-success"></i> Connected via TLS 1.3 encrypted tunnel. Unauthorized login attempts are audited.
                                </span>
                            </div>

                            <button type="submit" id="btnStaffSubmit" class="btn btn-dark w-100 py-3 fw-bold fs-6 d-flex align-items-center justify-content-center gap-2 shadow" style="background: #0b0f19; border-color: var(--primary);">
                                <i class="bi bi-key-fill" style="color: var(--primary);"></i>
                                <span>Authenticate & Access Terminal</span>
                            </button>
                        </form>

                    </div>

                    <div class="card-footer bg-light border-0 py-3 text-center">
                        <span class="small text-muted">Need to track a personal parcel or send a shipment? </span>
                        <a href="<?php echo BASE_URL; ?>login.php" class="small fw-bold text-decoration-none" style="color: var(--primary);">
                            Go to Customer Portal &rarr;
                        </a>
                    </div>
                </div>

                <!-- Public live tracking link -->
                <div class="text-center mt-4">
                    <a href="<?php echo BASE_URL; ?>index.php" class="text-secondary small text-decoration-none">
                        <i class="bi bi-search me-1"></i> Public Live Tracking & QR Scan
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password toggle
    $('#btnToggleStaffPwd').on('click', function() {
        const pwdInput = document.getElementById('staffPassword');
        const icon = document.getElementById('pwdStaffIcon');
        const isPwd = pwdInput.type === 'password';
        pwdInput.type = isPwd ? 'text' : 'password';
        icon.className = isPwd ? 'bi bi-eye' : 'bi bi-eye-slash';
    });

    // Role descriptions map
    const roleDescriptions = {
        'driver@courier.com': {
            title: 'Courier Driver Terminal',
            desc: 'Handheld camera QR scanner, run sheet & doorstep OTP confirmation',
            badge: 'DRIVER'
        },
        'hub@courier.com': {
            title: 'Sorting Hub Operations',
            desc: 'Inbound/outbound batch QR scanner, interstate dispatch & depot inventory',
            badge: 'HUB MANAGER'
        },
        'admin@courier.com': {
            title: 'System Command Center',
            desc: 'Fleet command center, real-time revenue analytics & system configuration',
            badge: 'SUPER ADMIN'
        }
    };

    // Role selector buttons
    $('.staff-role-btn').on('click', function() {
        $('.staff-role-btn').removeClass('active');
        $(this).addClass('active');

        const email = $(this).data('email');
        const pwd = $(this).data('pwd');

        $('#staffEmail').val(email);
        $('#staffPassword').val(pwd);

        const info = roleDescriptions[email];
        if (info) {
            $('#roleHelperTitle').text(info.title);
            $('#roleHelperDesc').text(info.desc);
            $('#roleHelperBadge').text(info.badge);
        }

        showToast($(this).data('role') + ' credentials filled!', 'info');
    });

    // Form Submission via AJAX
    $('#staffLoginForm').on('submit', function(e) {
        e.preventDefault();

        const email = $('#staffEmail').val().trim();
        const password = $('#staffPassword').val();
        const $alert = $('#staffAlert');
        const $btn = $('#btnStaffSubmit');

        $alert.addClass('d-none').removeClass('alert-danger alert-success').text('');

        if (!email || !password) {
            $alert.removeClass('d-none').addClass('alert-danger').text('Please provide both staff credentials.');
            return;
        }

        const originalBtnHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2 text-warning"></span> Verifying Staff Authority...');

        $.ajax({
            url: '<?php echo BASE_URL; ?>ajax/auth_handler.php',
            type: 'POST',
            data: {
                action: 'staff_login',
                email: email,
                password: password
            },
            success: function(response) {
                if (response.status === 'success') {
                    $alert.removeClass('d-none').addClass('alert-success').html('<i class="bi bi-check-circle me-1"></i> ' + response.message);
                    showToast('Staff Access Granted! Routing to operations console...', 'success');
                    setTimeout(function() {
                        window.location.href = response.redirect;
                    }, 500);
                } else {
                    $btn.prop('disabled', false).html(originalBtnHtml);
                    $alert.removeClass('d-none').addClass('alert-danger').html('<i class="bi bi-shield-x me-1"></i> ' + (response.message || 'Access Denied.'));
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalBtnHtml);
                $alert.removeClass('d-none').addClass('alert-danger').text('Operations gateway connection failure. Please retry.');
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
