<?php
// ========================================================
// SwiftScan Express - Parcel Handler AJAX Endpoint
// (ajax/parcel_handler.php)
// Handles Live Tracking, Real-time Rate Calculator & Booking
// ========================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'track':
        handleTrackShipment();
        break;

    case 'calculate_rate':
        handleCalculateRate();
        break;

    case 'book_parcel':
        handleBookParcel();
        break;

    default:
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid or missing action parameter.'
        ]);
        exit;
}

/**
 * Handle Real-Time Shipping Rate Calculation
 */
function handleCalculateRate() {
    $weightKg         = (float)($_REQUEST['weight_kg'] ?? 1.0);
    $parcelType       = trim($_REQUEST['parcel_type'] ?? 'Standard Box');
    $originHubId      = (int)($_REQUEST['origin_hub_id'] ?? 0);
    $destinationHubId = (int)($_REQUEST['destination_hub_id'] ?? 0);

    $rate = computeShippingRate($weightKg, $parcelType, $originHubId, $destinationHubId);

    echo json_encode([
        'status' => 'success',
        'data' => $rate
    ]);
    exit;
}

/**
 * Pricing Logic Calculation
 */
function computeShippingRate($weightKg, $parcelType, $originHubId, $destinationHubId) {
    if ($weightKg < 0.1) $weightKg = 0.1;
    if ($weightKg > 100) $weightKg = 100.0;

    // Base fee depending on consignment nature
    $baseRates = [
        'Express Document'       => 15.00,
        'Standard Box'           => 25.00,
        'Fragile / Electronics'  => 35.00,
        'Heavy Cargo'            => 50.00
    ];
    $baseFee = $baseRates[$parcelType] ?? 25.00;

    // Weight surcharge: 1st kg included in base, additional kg at $8.50/kg
    $excessWeight = max(0, $weightKg - 1.0);
    $weightFee = round($excessWeight * 8.50, 2);

    // Interstate Hub routing surcharge
    $interstateFee = 0.00;
    $estimatedDays = 'Next Day Express';

    if ($originHubId > 0 && $destinationHubId > 0) {
        if ($originHubId !== $destinationHubId) {
            $interstateFee = 12.00;
            $estimatedDays = '2 - 3 Business Days';
        }
    }

    $totalAmount = round($baseFee + $weightFee + $interstateFee, 2);

    return [
        'base_fee'          => $baseFee,
        'base_fee_fmt'      => '$' . number_format($baseFee, 2),
        'weight_kg'         => $weightKg,
        'weight_fee'        => $weightFee,
        'weight_fee_fmt'    => '$' . number_format($weightFee, 2),
        'interstate_fee'    => $interstateFee,
        'interstate_fee_fmt'=> '$' . number_format($interstateFee, 2),
        'total_amount'      => $totalAmount,
        'total_fmt'         => '$' . number_format($totalAmount, 2),
        'estimated_days'    => $estimatedDays
    ];
}

/**
 * Handle Customer Parcel Booking
 */
function handleBookParcel() {
    if (!isLoggedIn()) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Your session has expired. Please sign in to book a shipment.'
        ]);
        exit;
    }

    $currentUser = getCurrentUser();
    $senderId = (int)$currentUser['id'];

    // Inputs - Sender
    $senderName    = trim($_POST['sender_name'] ?? '');
    $senderPhone   = trim($_POST['sender_phone'] ?? '');
    $senderAddress = trim($_POST['sender_address'] ?? '');
    $senderCity    = trim($_POST['sender_city'] ?? '');
    $senderPincode = trim($_POST['sender_pincode'] ?? '');

    // Inputs - Receiver
    $receiverName    = trim($_POST['receiver_name'] ?? '');
    $receiverPhone   = trim($_POST['receiver_phone'] ?? '');
    $receiverAddress = trim($_POST['receiver_address'] ?? '');
    $receiverCity    = trim($_POST['receiver_city'] ?? '');
    $receiverPincode = trim($_POST['receiver_pincode'] ?? '');

    // Logistics Hubs & Specs
    $originHubId      = (int)($_POST['origin_hub_id'] ?? 0);
    $destinationHubId = (int)($_POST['destination_hub_id'] ?? 0);
    $weightKg         = (float)($_POST['weight_kg'] ?? 1.0);
    $parcelType       = trim($_POST['parcel_type'] ?? 'Standard Box');
    $paymentMethod    = strtolower(trim($_POST['payment_method'] ?? 'online'));

    // Validation
    if (empty($senderName) || empty($senderPhone) || empty($senderAddress) || empty($senderCity)) {
        echo json_encode(['status' => 'error', 'message' => 'Please provide complete sender and pickup details.']);
        exit;
    }

    if (empty($receiverName) || empty($receiverPhone) || empty($receiverAddress) || empty($receiverCity)) {
        echo json_encode(['status' => 'error', 'message' => 'Please provide complete recipient delivery details.']);
        exit;
    }

    if ($originHubId <= 0 || $destinationHubId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Please select both origin and destination sorting hubs.']);
        exit;
    }

    if ($weightKg < 0.1 || $weightKg > 100) {
        echo json_encode(['status' => 'error', 'message' => 'Parcel weight must be between 0.1 kg and 100 kg.']);
        exit;
    }

    if (!in_array($paymentMethod, ['online', 'cod'])) {
        $paymentMethod = 'online';
    }

    try {
        $pdo = getDB();

        // Verify Hubs exist
        $hubCheck = $pdo->prepare("SELECT id, hub_name FROM hubs WHERE id IN (:orig, :dest)");
        $hubCheck->execute([':orig' => $originHubId, ':dest' => $destinationHubId]);
        $hubs = $hubCheck->fetchAll(PDO::FETCH_KEY_PAIR);

        if (count($hubs) < 1) {
            echo json_encode(['status' => 'error', 'message' => 'Selected logistics hub does not exist.']);
            exit;
        }

        // Calculate authoritative cost
        $rateData = computeShippingRate($weightKg, $parcelType, $originHubId, $destinationHubId);
        $shippingCost = $rateData['total_amount'];

        // Generate unique Tracking Number: CR-2026-XXXXX
        $trackingNumber = '';
        $isUnique = false;
        $maxAttempts = 5;

        while (!$isUnique && $maxAttempts > 0) {
            $trackingNumber = 'CR-' . date('Y') . '-' . str_pad(mt_rand(10000, 99999), 5, '0', STR_PAD_LEFT);
            $dupStmt = $pdo->prepare("SELECT id FROM parcels WHERE tracking_number = :tn LIMIT 1");
            $dupStmt->execute([':tn' => $trackingNumber]);
            if (!$dupStmt->fetch()) {
                $isUnique = true;
            }
            $maxAttempts--;
        }

        if (!$isUnique) {
            $trackingNumber = 'CR-' . date('Y') . '-' . time();
        }

        // Generate 4-digit recipient doorstep OTP
        $deliveryOtp = str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);

        // Payment status
        $paymentStatus = 'pending';

        // Insert into parcels
        $insertSql = "
            INSERT INTO parcels (
                tracking_number, sender_id, sender_name, sender_phone, sender_address, sender_city, sender_pincode,
                receiver_name, receiver_phone, receiver_address, receiver_city, receiver_pincode,
                weight_kg, parcel_type, shipping_cost,
                origin_hub_id, destination_hub_id, current_hub_id,
                delivery_otp, status, payment_status, payment_method, created_at
            ) VALUES (
                :tracking_number, :sender_id, :sender_name, :sender_phone, :sender_address, :sender_city, :sender_pincode,
                :receiver_name, :receiver_phone, :receiver_address, :receiver_city, :receiver_pincode,
                :weight_kg, :parcel_type, :shipping_cost,
                :origin_hub_id, :destination_hub_id, :current_hub_id,
                :delivery_otp, 'booked', :payment_status, :payment_method, NOW()
            )
        ";

        $stmt = $pdo->prepare($insertSql);
        $stmt->execute([
            ':tracking_number'    => $trackingNumber,
            ':sender_id'          => $senderId,
            ':sender_name'        => $senderName,
            ':sender_phone'       => $senderPhone,
            ':sender_address'     => $senderAddress,
            ':sender_city'        => $senderCity,
            ':sender_pincode'     => $senderPincode,
            ':receiver_name'      => $receiverName,
            ':receiver_phone'     => $receiverPhone,
            ':receiver_address'   => $receiverAddress,
            ':receiver_city'      => $receiverCity,
            ':receiver_pincode'   => $receiverPincode,
            ':weight_kg'          => $weightKg,
            ':parcel_type'        => $parcelType,
            ':shipping_cost'      => $shippingCost,
            ':origin_hub_id'      => $originHubId,
            ':destination_hub_id' => $destinationHubId,
            ':current_hub_id'     => $originHubId,
            ':delivery_otp'       => $deliveryOtp,
            ':payment_status'     => $paymentStatus,
            ':payment_method'     => $paymentMethod
        ]);

        $parcelId = (int)$pdo->lastInsertId();

        // Insert initial tracking log
        $originHubName = $hubs[$originHubId] ?? ($senderCity . ' Depot');
        $logSql = "
            INSERT INTO tracking_logs (parcel_id, status, hub_id, updated_by, location_note, remarks, scan_timestamp)
            VALUES (:parcel_id, 'booked', :hub_id, :updated_by, :location_note, :remarks, NOW())
        ";
        $logStmt = $pdo->prepare($logSql);
        $logStmt->execute([
            ':parcel_id'     => $parcelId,
            ':hub_id'        => $originHubId,
            ':updated_by'    => $senderId,
            ':location_note' => $originHubName,
            ':remarks'       => "Consignment booked. Waybill QR created ({$paymentMethod} payment mode)."
        ]);

        $base = defined('BASE_URL') ? BASE_URL : '/courier_service_system/';

        echo json_encode([
            'status'          => 'success',
            'message'         => 'Parcel successfully booked! Your QR waybill is ready.',
            'parcel_id'       => $parcelId,
            'tracking_number' => $trackingNumber,
            'total_amount'    => $shippingCost,
            'payment_method'  => $paymentMethod,
            'delivery_otp'    => $deliveryOtp,
            'redirect'        => $base . 'customer/index.php'
        ]);
        exit;

    } catch (PDOException $e) {
        error_log('Booking DB Error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error while saving booking: ' . $e->getMessage()]);
        exit;
    }
}

/**
 * Handle Public Live Shipment Tracking
 */
function handleTrackShipment() {
    $trackingNumber = trim($_REQUEST['tracking_number'] ?? '');

    // Allow URL extraction if user scanned a full tracking URL in QR code
    if (filter_var($trackingNumber, FILTER_VALIDATE_URL)) {
        $parsedUrl = parse_url($trackingNumber);
        if (!empty($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
            if (!empty($queryParams['track'])) {
                $trackingNumber = $queryParams['track'];
            } elseif (!empty($queryParams['code'])) {
                $trackingNumber = $queryParams['code'];
            }
        }
    }

    // Clean tracking number
    $trackingNumber = preg_replace('/[^A-Za-z0-9\-]/', '', $trackingNumber);

    if (empty($trackingNumber)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Please enter a valid tracking number or scan a waybill QR code.'
        ]);
        exit;
    }

    try {
        $pdo = getDB();

        // 1. Fetch Parcel with Hubs, Driver, Payments & POD details
        $sql = "
            SELECT 
                p.id,
                p.tracking_number,
                p.sender_name,
                p.sender_city,
                p.sender_address,
                p.sender_phone,
                p.sender_pincode,
                p.receiver_name,
                p.receiver_city,
                p.receiver_address,
                p.receiver_phone,
                p.receiver_pincode,
                p.weight_kg,
                p.parcel_type,
                p.shipping_cost,
                p.status,
                p.payment_status,
                p.payment_method,
                p.delivery_otp,
                p.created_at,
                p.delivered_at,
                oh.hub_name AS origin_hub_name,
                oh.city AS origin_hub_city,
                dh.hub_name AS dest_hub_name,
                dh.city AS dest_hub_city,
                ch.hub_name AS current_hub_name,
                ch.city AS current_hub_city,
                d.full_name AS driver_name,
                d.phone AS driver_phone,
                pm.transaction_id,
                pm.amount AS payment_amount,
                pm.card_last4,
                pm.paid_at,
                pod.recipient_name AS pod_recipient,
                pod.otp_verified AS pod_otp_verified,
                pod.delivered_at AS pod_delivered_at,
                pod.notes AS pod_notes
            FROM parcels p
            LEFT JOIN hubs oh ON p.origin_hub_id = oh.id
            LEFT JOIN hubs dh ON p.destination_hub_id = dh.id
            LEFT JOIN hubs ch ON p.current_hub_id = ch.id
            LEFT JOIN users d ON p.assigned_driver_id = d.id
            LEFT JOIN payments pm ON p.id = pm.parcel_id
            LEFT JOIN proof_of_delivery pod ON p.id = pod.parcel_id
            WHERE UPPER(p.tracking_number) = UPPER(:tracking_number)
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':tracking_number' => $trackingNumber]);
        $parcel = $stmt->fetch();

        if (!$parcel) {
            echo json_encode([
                'status' => 'error',
                'message' => "No shipment found for Waybill #{$trackingNumber}. Please double check the ID or scan again."
            ]);
            exit;
        }

        // 2. Fetch Chronological Tracking Logs
        $logSql = "
            SELECT 
                tl.id,
                tl.status,
                tl.location_note,
                tl.remarks,
                tl.scan_timestamp,
                h.hub_name,
                h.city AS hub_city,
                u.full_name AS operator_name,
                u.role AS operator_role
            FROM tracking_logs tl
            LEFT JOIN hubs h ON tl.hub_id = h.id
            LEFT JOIN users u ON tl.updated_by = u.id
            WHERE tl.parcel_id = :parcel_id
            ORDER BY tl.scan_timestamp ASC, tl.id ASC
        ";

        $logStmt = $pdo->prepare($logSql);
        $logStmt->execute([':parcel_id' => $parcel['id']]);
        $rawLogs = $logStmt->fetchAll();

        // Format logs for display
        $formattedLogs = [];
        foreach ($rawLogs as $log) {
            $formattedLogs[] = [
                'id'            => $log['id'],
                'status'        => $log['status'],
                'status_label'  => formatStatusLabel($log['status']),
                'location_note' => $log['location_note'],
                'remarks'       => $log['remarks'] ?? '',
                'hub_name'      => $log['hub_name'] ?? '',
                'hub_city'      => $log['hub_city'] ?? '',
                'operator_name' => $log['operator_name'] ?? 'System',
                'operator_role' => $log['operator_role'] ?? 'automated',
                'timestamp'     => date('M d, Y • h:i A', strtotime($log['scan_timestamp'])),
                'relative_time' => timeAgo($log['scan_timestamp'])
            ];
        }

        // 3. Compute Progress Status & Stage
        $stageInfo = getProgressStageInfo($parcel['status']);

        // Mask phone numbers for public privacy
        $maskedPhone = maskPhoneNumber($parcel['receiver_phone']);
        $maskedSenderPhone = maskPhoneNumber($parcel['sender_phone']);

        // Response Data
        echo json_encode([
            'status' => 'success',
            'data' => [
                'parcel' => [
                    'id'                   => (int)$parcel['id'],
                    'tracking_number'      => $parcel['tracking_number'],
                    'status'               => $parcel['status'],
                    'status_label'         => formatStatusLabel($parcel['status']),
                    'status_badge_class'   => getStatusBadgeClass($parcel['status']),
                    'sender_name'          => $parcel['sender_name'],
                    'sender_city'          => $parcel['sender_city'],
                    'sender_phone'         => $maskedSenderPhone,
                    'receiver_name'        => $parcel['receiver_name'],
                    'receiver_city'        => $parcel['receiver_city'],
                    'receiver_phone'       => $maskedPhone,
                    'origin_hub'           => $parcel['origin_hub_name'] ?? ($parcel['sender_city'] . ' Depot'),
                    'dest_hub'             => $parcel['dest_hub_name'] ?? ($parcel['receiver_city'] . ' Hub'),
                    'current_hub'          => $parcel['current_hub_name'] ?? 'In Transit',
                    'weight_kg'            => number_format((float)$parcel['weight_kg'], 2) . ' kg',
                    'parcel_type'          => $parcel['parcel_type'],
                    'shipping_cost'        => '$' . number_format((float)$parcel['shipping_cost'], 2),
                    'payment_status'       => strtoupper($parcel['payment_status']),
                    'payment_method'       => strtoupper($parcel['payment_method']),
                    'payment_badge_class'  => $parcel['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark',
                    'transaction_id'       => $parcel['transaction_id'] ?? 'N/A',
                    'driver_name'          => $parcel['driver_name'] ?? null,
                    'driver_phone'         => $parcel['driver_phone'] ? maskPhoneNumber($parcel['driver_phone']) : null,
                    'pod'                  => $parcel['pod_recipient'] ? [
                        'recipient'    => $parcel['pod_recipient'],
                        'otp_verified' => (bool)$parcel['pod_otp_verified'],
                        'delivered_at' => date('M d, Y • h:i A', strtotime($parcel['pod_delivered_at'])),
                        'notes'        => $parcel['pod_notes']
                    ] : null,
                    'created_at_formatted' => date('M d, Y • h:i A', strtotime($parcel['created_at'])),
                    'delivered_at_formatted' => $parcel['delivered_at'] ? date('M d, Y • h:i A', strtotime($parcel['delivered_at'])) : null,
                ],
                'progress' => $stageInfo,
                'logs' => $formattedLogs
            ]
        ]);
        exit;

    } catch (PDOException $e) {
        error_log('Track Shipment DB Error: ' . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Unable to query tracking data. Please try again shortly.'
        ]);
        exit;
    }
}

/**
 * Format status slug to human readable label
 */
function formatStatusLabel($status) {
    $labels = [
        'booked'           => 'Shipment Booked',
        'picked_up'        => 'Picked Up at Origin Hub',
        'in_transit'       => 'In Transit between Hubs',
        'out_for_delivery' => 'Out for Delivery',
        'delivered'        => 'Successfully Delivered',
        'failed'           => 'Delivery Failed / Rescheduled',
        'returned'         => 'Returned to Origin'
    ];
    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

/**
 * Determine badge CSS class
 */
function getStatusBadgeClass($status) {
    $classes = [
        'booked'           => 'badge-status-booked',
        'picked_up'        => 'bg-info text-dark',
        'in_transit'       => 'badge-status-in_transit',
        'out_for_delivery' => 'badge-status-out_for_delivery',
        'delivered'        => 'badge-status-delivered',
        'failed'           => 'badge-status-failed',
        'returned'         => 'bg-dark text-white'
    ];
    return $classes[$status] ?? 'bg-secondary text-white';
}

/**
 * Calculate multi-stage timeline metadata
 */
function getProgressStageInfo($status) {
    $stages = [
        1 => ['label' => 'Order Booked', 'icon' => 'bi-box-seam', 'desc' => 'Order created & verified'],
        2 => ['label' => 'Hub Transit', 'icon' => 'bi-truck', 'desc' => 'Sorting center checkpoint'],
        3 => ['label' => 'Out for Delivery', 'icon' => 'bi-bicycle', 'desc' => 'Courier dispatched to address'],
        4 => ['label' => 'Delivered', 'icon' => 'bi-patch-check-fill', 'desc' => 'OTP verified doorstep handoff']
    ];

    $currentStage = 1;
    $percentage = 25;
    $isComplete = false;
    $isFailed = false;

    switch ($status) {
        case 'booked':
            $currentStage = 1;
            $percentage = 25;
            break;
        case 'picked_up':
            $currentStage = 2;
            $percentage = 45;
            break;
        case 'in_transit':
            $currentStage = 2;
            $percentage = 60;
            break;
        case 'out_for_delivery':
            $currentStage = 3;
            $percentage = 85;
            break;
        case 'delivered':
            $currentStage = 4;
            $percentage = 100;
            $isComplete = true;
            break;
        case 'failed':
        case 'returned':
            $isFailed = true;
            $percentage = 50;
            break;
    }

    return [
        'current_stage' => $currentStage,
        'percentage'    => $percentage,
        'is_complete'   => $isComplete,
        'is_failed'     => $isFailed,
        'stages'        => $stages
    ];
}

/**
 * Mask sensitive phone number digits for public privacy
 */
function maskPhoneNumber($phone) {
    if (empty($phone)) return 'N/A';
    $len = strlen($phone);
    if ($len <= 5) return $phone;
    return substr($phone, 0, 3) . '****' . substr($phone, -4);
}

/**
 * Compute friendly human relative time ago string
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return round($diff / 60) . ' mins ago';
    if ($diff < 86400) return round($diff / 3600) . ' hours ago';
    if ($diff < 604800) return round($diff / 86400) . ' days ago';
    return date('M d, Y', $time);
}
