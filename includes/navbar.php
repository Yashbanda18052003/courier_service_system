<?php
// ========================================================
// Responsive Navbar Component (includes/navbar.php)
// Role-aware navigation with separate Staff Portal link
// ========================================================
$currentUser = getCurrentUser();
$currentRole = $currentUser['role'] ?? null;
?>
<nav class="navbar navbar-expand-lg navbar-custom sticky-top">
    <div class="container">
        <!-- Brand Logo -->
        <a class="brand-box" href="<?php echo BASE_URL; ?>index.php">
            <div class="logo-badge">
                <i class="bi bi-box-seam-fill"></i>
            </div>
            <div>
                <div class="brand-text-title">Swift<span>Scan</span></div>
                <div class="brand-text-sub">Next-Gen QR Logistics</div>
            </div>
        </a>

        <!-- Mobile Toggler -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navbar Links -->
        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2 my-2 my-lg-0">
                <li class="nav-item">
                    <a class="nav-link text-dark fw-semibold" href="<?php echo BASE_URL; ?>index.php">
                        <i class="bi bi-search me-1 text-primary"></i> Track Shipment
                    </a>
                </li>

                <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <a class="nav-link text-dark fw-semibold" href="<?php echo getDashboardUrl($currentRole); ?>">
                            <i class="bi bi-grid-fill me-1 text-primary"></i> Dashboard
                        </a>
                    </li>

                    <li class="nav-item dropdown ms-lg-2">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 p-1" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="d-flex flex-column align-items-end me-1">
                                <span class="fw-bold small text-dark"><?php echo htmlspecialchars($currentUser['full_name']); ?></span>
                                <span class="badge-role badge-role-<?php echo $currentRole; ?>">
                                    <?php echo strtoupper(str_replace('_', ' ', $currentRole)); ?>
                                </span>
                            </div>
                            <div class="rounded-circle bg-light border p-2 d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
                                <i class="bi bi-person-fill text-muted"></i>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                            <li><h6 class="dropdown-header text-muted"><?php echo htmlspecialchars($currentUser['email']); ?></h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?php echo getDashboardUrl($currentRole); ?>">
                                    <i class="bi bi-speedometer2 me-2"></i> My Dashboard
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i> Log Out
                                </a>
                            </li>
                        </ul>
                    </li>

                <?php else: ?>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-outline-secondary btn-sm fw-semibold" href="<?php echo BASE_URL; ?>login.php">
                            Customer Sign In
                        </a>
                    </li>
                    <li class="nav-item ms-lg-1">
                        <a class="btn btn-express btn-sm" href="<?php echo BASE_URL; ?>register.php">
                            Register
                        </a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-dark btn-sm fw-semibold d-flex align-items-center gap-1 shadow-sm" href="<?php echo BASE_URL; ?>staff_login.php">
                            <i class="bi bi-shield-lock-fill text-warning"></i> Staff Portal
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
