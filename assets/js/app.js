/**
 * SwiftScan Express - Core Application JavaScript (assets/js/app.js)
 * Live Tracking AJAX, QR Scanner, Clipboard copy & Bootstrap Toasts
 */

const BASE_URL = window.location.pathname.startsWith('/courier_service_system') 
    ? '/courier_service_system/' 
    : '/';

$(document).ready(function() {
    // Global AJAX setup
    $.ajaxSetup({
        dataType: 'json',
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error, xhr.responseText);
        }
    });

    // Tracking Button & Enter Key Handlers
    $('#btnTrackSubmit').on('click', function(e) {
        e.preventDefault();
        const code = $('#trackingInput').val();
        performTrack(code);
    });

    $('#trackingInput').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            const code = $(this).val();
            performTrack(code);
        }
    });

    $('#trackingForm').on('submit', function(e) {
        e.preventDefault();
        const code = $('#trackingInput').val();
        performTrack(code);
    });

    // Demo tracking button click handler
    $(document).on('click', '.demo-track-pill', function(e) {
        e.preventDefault();
        const code = $(this).data('code');
        if ($('#trackingInput').length) {
            $('#trackingInput').val(code);
            performTrack(code);
        }
    });

    // Auto-track if URL contains query parameter: ?track=CR-2026-98214
    const urlParams = new URLSearchParams(window.location.search);
    const trackParam = urlParams.get('track') || urlParams.get('code');
    if (trackParam && $('#trackingInput').length) {
        $('#trackingInput').val(trackParam);
        performTrack(trackParam);
    }

    // Copy tracking code to clipboard
    $(document).on('click', '.btn-copy-code', function(e) {
        e.preventDefault();
        const code = $(this).data('code');
        if (navigator.clipboard) {
            navigator.clipboard.writeText(code).then(() => {
                showToast(`Tracking code ${code} copied to clipboard!`, 'success');
            }).catch(() => {
                fallbackCopy(code);
            });
        } else {
            fallbackCopy(code);
        }
    });

    // Global QR Scanner modal logic
    let html5QrCode = null;

    $('#globalQrScannerModal').on('shown.bs.modal', function () {
        $('#qr-scan-feedback').html('<span class="spinner-border spinner-border-sm me-1"></span> Initializing camera...');
        
        try {
            if (typeof Html5Qrcode !== 'undefined') {
                html5QrCode = new Html5Qrcode("qr-camera-reader");
                const config = { fps: 10, qrbox: { width: 220, height: 220 } };

                html5QrCode.start(
                    { facingMode: "environment" },
                    config,
                    (decodedText, decodedResult) => {
                        // Success callback
                        $('#qr-scan-feedback').html(`<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i> QR Scanned: ${decodedText}</span>`);
                        
                        if (typeof window.onQrCodeScanned === 'function') {
                            window.onQrCodeScanned(decodedText);
                        } else if ($('#trackingInput').length) {
                            $('#trackingInput').val(decodedText);
                            performTrack(decodedText);
                        }

                        const modalEl = document.getElementById('globalQrScannerModal');
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                    },
                    (errorMessage) => {
                        // Seeking QR code
                    }
                ).catch(err => {
                    console.warn("Camera init failed:", err);
                    $('#qr-scan-feedback').html(`<span class="text-danger small"><i class="bi bi-exclamation-triangle me-1"></i> Camera access unavailable or denied. Please enter tracking ID manually.</span>`);
                });
            }
        } catch (e) {
            console.error(e);
            $('#qr-scan-feedback').html('<span class="text-danger small">QR Scanner engine could not load.</span>');
        }
    });

    $('#globalQrScannerModal').on('hidden.bs.modal', function () {
        if (html5QrCode) {
            html5QrCode.stop().then(() => {
                html5QrCode.clear();
                html5QrCode = null;
            }).catch(err => {
                console.warn("Camera stop error:", err);
            });
        }
        $('#qr-scan-feedback').text('Point your camera at a waybill QR code...');
    });
});

/**
 * Public Live Shipment Tracking Execution via AJAX
 * @param {string} trackingCode 
 */
function performTrack(trackingCode) {
    if (!trackingCode || !trackingCode.trim()) {
        showToast('Please enter a tracking number or scan a waybill QR code.', 'warning');
        $('#trackingInput').focus();
        return;
    }

    trackingCode = trackingCode.trim().toUpperCase();

    const $btn = $('#btnTrackSubmit');
    const originalBtnHtml = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Tracking...');

    const $resultSection = $('#trackingResultSection');
    const $resultContainer = $('#trackingResultContainer');

    $resultSection.removeClass('d-none');
    $resultContainer.html(`
        <div class="text-center py-5">
            <div class="spinner-border text-warning" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted mt-3 mb-0 fw-semibold">Querying logistics hubs & live waybill data...</p>
        </div>
    `);

    // Smooth scroll to results (skip if noscroll is requested)
    const currentParams = new URLSearchParams(window.location.search);
    if (!currentParams.has('noscroll') && currentParams.get('scroll') !== '0') {
        $('html, body').animate({
            scrollTop: $resultSection.offset().top - 80
        }, 400);
    }

    $.ajax({
        url: BASE_URL + 'ajax/parcel_handler.php',
        type: 'GET',
        data: {
            action: 'track',
            tracking_number: trackingCode
        },
        success: function(response) {
            $btn.prop('disabled', false).html(originalBtnHtml);

            if (response.status === 'success') {
                renderTrackingResult(response.data);
            } else {
                $resultContainer.html(`
                    <div class="alert alert-danger border-0 shadow-sm p-4 rounded-4 mb-0">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-exclamation-triangle-fill fs-1 text-danger"></i>
                            <div>
                                <h5 class="fw-bold mb-1 text-danger">Shipment Not Found</h5>
                                <p class="mb-0 text-dark">${response.message || 'No parcel record matched this tracking number.'}</p>
                            </div>
                        </div>
                    </div>
                `);
            }
        },
        error: function(xhr, status, error) {
            $btn.prop('disabled', false).html(originalBtnHtml);
            $resultContainer.html(`
                <div class="alert alert-danger border-0 shadow-sm p-4 rounded-4 mb-0">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-x-circle-fill fs-1 text-danger"></i>
                        <div>
                            <h5 class="fw-bold mb-1">Server Communication Error</h5>
                            <p class="mb-0">Unable to connect to logistics server. Please check your connection and try again.</p>
                        </div>
                    </div>
                </div>
            `);
        }
    });
}

/**
 * Render complete visual tracking result
 * @param {object} data - { parcel, progress, logs }
 */
function renderTrackingResult(data) {
    const parcel = data.parcel;
    const progress = data.progress;
    const logs = data.logs || [];

    // Milestone Stepper Nodes
    const stages = progress.stages;
    let stepperHtml = '';
    const currentStageNum = progress.current_stage;

    for (let i = 1; i <= 4; i++) {
        const stage = stages[i];
        let stateClass = '';
        let badgeIcon = stage.icon;

        if (i < currentStageNum || progress.is_complete) {
            stateClass = 'completed';
            badgeIcon = 'bi-check-lg';
        } else if (i === currentStageNum && !progress.is_failed) {
            stateClass = 'active';
        }

        stepperHtml += `
            <li class="stepper-node ${stateClass}">
                <div class="stepper-icon">
                    <i class="bi ${badgeIcon}"></i>
                </div>
                <div class="stepper-title">${stage.label}</div>
                <div class="stepper-sub d-none d-md-block">${stage.desc}</div>
            </li>
        `;
    }

    // Alert Banner if delivered or out for delivery
    let alertBannerHtml = '';
    if (parcel.status === 'delivered' && parcel.pod) {
        alertBannerHtml = `
            <div class="alert alert-success border-0 shadow-sm p-3 mb-4 rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-patch-check-fill fs-3 text-success"></i>
                    <div>
                        <div class="fw-bold">Proof of Delivery Verified</div>
                        <div class="small">Received by <strong>${parcel.pod.recipient}</strong> on ${parcel.pod.delivered_at}</div>
                    </div>
                </div>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">
                    <i class="bi bi-shield-check me-1"></i> OTP Authenticated
                </span>
            </div>
        `;
    } else if (parcel.status === 'out_for_delivery') {
        alertBannerHtml = `
            <div class="alert alert-warning border-0 shadow-sm p-3 mb-4 rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-bicycle fs-3 text-warning"></i>
                    <div>
                        <div class="fw-bold text-dark">Courier is on Route for Delivery</div>
                        <div class="small text-dark">Assigned Driver: <strong>${parcel.driver_name || 'Fleet Driver'}</strong>. Please have your 4-digit recipient OTP ready.</div>
                    </div>
                </div>
                ${parcel.payment_method === 'COD' ? '<span class="badge bg-danger text-white px-3 py-2 rounded-pill"><i class="bi bi-cash me-1"></i> Cash Collection Pending: ' + parcel.shipping_cost + '</span>' : ''}
            </div>
        `;
    }

    // Transit History Timeline Logs
    let logsHtml = '';
    if (logs.length === 0) {
        logsHtml = `<div class="text-muted p-3 text-center small">No transit scan logs recorded yet.</div>`;
    } else {
        logsHtml = `<div class="timeline-list">`;
        logs.forEach((log, index) => {
            const isLatest = index === logs.length - 1;
            logsHtml += `
                <div class="timeline-item ${isLatest ? 'active' : ''}">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                        <span class="fw-bold text-dark">${log.status_label}</span>
                        <div class="text-muted small">
                            <span>${log.timestamp}</span>
                            <span class="badge bg-light text-secondary ms-1 border">${log.relative_time}</span>
                        </div>
                    </div>
                    <div class="small text-muted mb-1">
                        <i class="bi bi-geo-alt-fill text-warning me-1"></i>
                        <strong>${log.location_note}</strong>
                        ${log.hub_name ? ` • <span class="text-dark">${log.hub_name}</span>` : ''}
                    </div>
                    ${log.remarks ? `<div class="small text-secondary bg-light p-2 rounded border mt-1">${log.remarks}</div>` : ''}
                </div>
            `;
        });
        logsHtml += `</div>`;
    }

    // Build Complete HTML
    const html = `
        <div class="tracking-result-box shadow-sm mb-4">
            <!-- Header Bar -->
            <div class="tracking-result-header">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <div class="small text-light opacity-75 text-uppercase fw-semibold letter-spacing-1">Waybill Consignment</div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <h3 class="fw-bold text-white font-monospace mb-0">${parcel.tracking_number}</h3>
                            <button type="button" class="btn btn-sm btn-outline-light py-0 px-2 rounded-pill btn-copy-code" data-code="${parcel.tracking_number}" title="Copy Tracking Number">
                                <i class="bi bi-clipboard me-1"></i> Copy
                            </button>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="route-pill">
                            <i class="bi bi-arrow-right-circle text-warning me-1"></i>
                            ${parcel.sender_city} ➔ ${parcel.receiver_city}
                        </span>
                        <span class="badge-status ${parcel.status_badge_class} fs-6 py-2 px-3">
                            ${parcel.status_label}
                        </span>
                    </div>
                </div>
            </div>

            <div class="p-4">
                <!-- Alert Banner -->
                ${alertBannerHtml}

                <!-- Stepper Progress -->
                <div class="tracking-stepper-wrap">
                    <div class="tracking-stepper-track"></div>
                    <div class="tracking-stepper-progress" style="width: ${progress.percentage}%;"></div>
                    <ul class="tracking-stepper">
                        ${stepperHtml}
                    </ul>
                </div>

                <!-- 3 Spec Info Cards -->
                <div class="row g-3 my-3">
                    <!-- Shipment Specs -->
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 bg-light border h-100">
                            <div class="d-flex align-items-center gap-2 mb-2 text-muted small fw-bold text-uppercase">
                                <i class="bi bi-box-seam text-warning fs-5"></i> Package Details
                            </div>
                            <div class="d-flex justify-content-between py-1 border-bottom small">
                                <span class="text-muted">Type:</span>
                                <span class="fw-semibold text-dark">${parcel.parcel_type}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1 border-bottom small">
                                <span class="text-muted">Weight:</span>
                                <span class="fw-semibold text-dark">${parcel.weight_kg}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1 small">
                                <span class="text-muted">Booked Date:</span>
                                <span class="fw-semibold text-dark">${parcel.created_at_formatted}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Hub Routing -->
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 bg-light border h-100">
                            <div class="d-flex align-items-center gap-2 mb-2 text-muted small fw-bold text-uppercase">
                                <i class="bi bi-diagram-3-fill text-warning fs-5"></i> Routing & Courier
                            </div>
                            <div class="d-flex justify-content-between py-1 border-bottom small">
                                <span class="text-muted">Origin Hub:</span>
                                <span class="fw-semibold text-dark text-truncate" style="max-width: 160px;" title="${parcel.origin_hub}">${parcel.origin_hub}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1 border-bottom small">
                                <span class="text-muted">Destination:</span>
                                <span class="fw-semibold text-dark text-truncate" style="max-width: 160px;" title="${parcel.dest_hub}">${parcel.dest_hub}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1 small">
                                <span class="text-muted">Current Location:</span>
                                <span class="fw-semibold text-warning text-truncate" style="max-width: 160px;">${parcel.current_hub}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment & Security -->
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 bg-light border h-100">
                            <div class="d-flex align-items-center gap-2 mb-2 text-muted small fw-bold text-uppercase">
                                <i class="bi bi-shield-lock-fill text-warning fs-5"></i> Payment & Security
                            </div>
                            <div class="d-flex justify-content-between py-1 border-bottom small">
                                <span class="text-muted">Payment Mode:</span>
                                <span class="badge ${parcel.payment_method === 'COD' ? 'bg-warning text-dark' : 'bg-primary'}">${parcel.payment_method}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1 border-bottom small">
                                <span class="text-muted">Payment Status:</span>
                                <span class="badge ${parcel.payment_badge_class}">${parcel.payment_status}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1 small">
                                <span class="text-muted">Total Amount:</span>
                                <span class="fw-bold text-dark fs-6">${parcel.shipping_cost}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timeline & Scannable QR Row -->
                <div class="row g-4 mt-2">
                    <!-- Left: Transit Scan History -->
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm rounded-3">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <h6 class="fw-bold mb-0 text-dark">
                                    <i class="bi bi-clock-history text-warning me-2"></i> Real-time Transit Scan Log
                                </h6>
                                <span class="badge bg-secondary-subtle text-secondary small">${logs.length} Checkpoints</span>
                            </div>
                            <div class="card-body p-4">
                                ${logsHtml}
                            </div>
                        </div>
                    </div>

                    <!-- Right: Dynamic QR Waybill Card -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm rounded-3 text-center h-100">
                            <div class="card-header bg-white py-3 border-bottom">
                                <h6 class="fw-bold mb-0 text-dark">
                                    <i class="bi bi-qr-code text-warning me-2"></i> Scannable Waybill QR
                                </h6>
                            </div>
                            <div class="card-body d-flex flex-column align-items-center justify-content-center p-4">
                                <div id="live-waybill-qr" class="p-2 bg-white rounded border shadow-sm mb-3"></div>
                                <div class="font-monospace fw-bold small text-dark">${parcel.tracking_number}</div>
                                <div class="text-muted small mt-1 mb-3">Scan with mobile camera to verify package</div>
                                <a href="${BASE_URL}waybill_print.php?code=${parcel.tracking_number}" target="_blank" class="btn btn-outline-dark btn-sm w-100">
                                    <i class="bi bi-printer me-1"></i> Print Waybill Label
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    `;

    $('#trackingResultContainer').html(html);

    // Generate Dynamic QR Code inside the card
    setTimeout(() => {
        const qrContainer = document.getElementById('live-waybill-qr');
        if (qrContainer && typeof QRCode !== 'undefined') {
            qrContainer.innerHTML = '';
            new QRCode(qrContainer, {
                text: parcel.tracking_number,
                width: 140,
                height: 140,
                colorDark: "#0f172a",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        }
    }, 50);
}

/**
 * Fallback copy function for browsers without navigator.clipboard
 */
function fallbackCopy(text) {
    const tempInput = document.createElement("input");
    tempInput.value = text;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand("copy");
    document.body.removeChild(tempInput);
    showToast(`Tracking code ${text} copied to clipboard!`, 'success');
}

/**
 * Display a modern Bootstrap Toast Notification
 * @param {string} message - Message text
 * @param {string} type - 'success', 'danger', 'warning', 'info'
 */
function showToast(message, type = 'success') {
    let icon = 'bi-check-circle-fill';
    let bgClass = 'bg-success text-white';

    if (type === 'danger' || type === 'error') {
        icon = 'bi-exclamation-triangle-fill';
        bgClass = 'bg-danger text-white';
    } else if (type === 'warning') {
        icon = 'bi-exclamation-circle-fill';
        bgClass = 'bg-warning text-dark';
    } else if (type === 'info') {
        icon = 'bi-info-circle-fill';
        bgClass = 'bg-info text-dark';
    }

    const toastHtml = `
        <div class="toast align-items-center ${bgClass} border-0 mb-2 shadow" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2">
                    <i class="bi ${icon} fs-5"></i>
                    <span>${message}</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    let container = $('#toast-container');
    if (!container.length) {
        $('body').append('<div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;"></div>');
        container = $('#toast-container');
    }

    const toastElement = $(toastHtml);
    container.append(toastElement);
    const toast = new bootstrap.Toast(toastElement[0], { delay: 4000 });
    toast.show();

    toastElement.on('hidden.bs.toast', function() {
        $(this).remove();
    });
}
