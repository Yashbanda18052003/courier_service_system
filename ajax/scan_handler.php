<?php
// ========================================================
// SwiftScan Express - QR Scanner & Proof-of-Delivery Handler
// (ajax/scan_handler.php)
// Handles Live Camera QR Scanning, Driver Dispatch & Doorstep OTP POD
// ========================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'scan_waybill':
        handleScanWaybill();
        break;

    case 'assign_to_driver':
        handleAssignToDriver();
        break;

    case 'verify_pod':
        handleVerifyPod();
        break;

    case 'mark_failed':
        handleMarkFailed();
        break;

    default:
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid or unknown scan action.'
        ]);
        exit;
}

/**
 * Handle Scanned Waybill Lookup (From Camera Scanner or Manual Entry)
 */
function handleScanWaybill() {
    $rawCode = trim($_REQUEST['code'] ?? $_REQUEST['tracking_number'] ?? '');

    // Extract tracking number if scanned code is a full URL: ...?track=CR-2026-XXXXX
    if (filter_var($rawCode, FILTER_VALIDATE_URL)) {
        $parsed = parse_url($rawCode);
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $query);
            if (!empty($query['track'])) {
                $rawCode = $query['track'];
            } elseif (!empty($query['code'])) {
                $rawCode = $query['code'];
            }
        }
    }

    $cleanCode = preg_replace('/[^A-Za-z0-9\-]/', '', $rawCode);

    if (empty($cleanCode)) {
        echo json_encode(['status' => 'error', 'message' => 'Please provide or scan a valid waybill tracking code.']);
        exit;
    }

    try {
        $pdo = getDB();

        $stmt = $pdo->prepare("
            SELECT 
                p.*,
                oh.hub_name AS origin_hub_name,
                oh.hub_code AS origin_hub_code,
                dh.hub_name AS dest_hub_name,
                dh.hub_code AS dest_hub_code,
                ch.hub_name AS current_hub_name,
                d.full_name AS driver_name,
                d.phone AS driver_phone,
                pod.recipient_name AS pod_recipient,
                pod.otp_verified AS pod_otp_verified,
                pod.delivered_at AS pod_delivered_at
            FROM parcels p
            LEFT JOIN hubs oh ON p.origin_hub_id = oh.id
            LEFT JOIN hubs dh ON p.destination_hub_id = dh.id
            LEFT JOIN hubs ch ON p.current_hub_id = ch.id
            LEFT JOIN users d ON p.assigned_driver_id = d.id
            LEFT JOIN proof_of_delivery pod ON p.id = pod.parcel_id
            WHERE UPPER(p.tracking_number) = UPPER(:tn)
            LIMIT 1
        ");
        $stmt->execute([':tn' => $cleanCode]);
        $parcel = $stmt->fetch();

        if (!$parcel) {
            echo json_encode(['status' => 'error', 'message' => "Waybill #{$cleanCode} not found in logistics network."]);
            exit;
        }

        echo json_encode([
            'status' => 'success',
            'data'   => [
                'id'               => (int)$parcel['id'],
                'tracking_number'  => $parcel['tracking_number'],
                'status'           => $parcel['status'],
                'status_label'     => ucfirst(str_replace('_', ' ', $parcel['status'])),
                'receiver_name'    => $parcel['receiver_name'],
                'receiver_phone'   => $parcel['receiver_phone'],
                'receiver_address' => $parcel['receiver_address'],
                'receiver_city'    => $parcel['receiver_city'],
                'receiver_pincode' => $parcel['receiver_pincode'],
                'sender_name'      => $parcel['sender_name'],
                'sender_city'      => $parcel['sender_city'],
                'weight_kg'        => number_format((float)$parcel['weight_kg'], 2) . ' kg',
                'parcel_type'      => $parcel['parcel_type'],
                'shipping_cost'    => '$' . number_format((float)$parcel['shipping_cost'], 2),
                'cost_raw'         => (float)$parcel['shipping_cost'],
                'payment_method'   => strtoupper($parcel['payment_method']),
                'payment_status'   => strtoupper($parcel['payment_status']),
                'is_cod'           => ($parcel['payment_method'] === 'cod' && $parcel['payment_status'] !== 'paid'),
                'origin_hub'       => $parcel['origin_hub_name'] ?? 'Origin Hub',
                'dest_hub'         => $parcel['dest_hub_name'] ?? 'Destination Hub',
                'assigned_driver'  => $parcel['driver_name'],
                'is_delivered'     => ($parcel['status'] === 'delivered')
            ]
        ]);
        exit;

    } catch (PDOException $e) {
        error_log('Scan Waybill DB Error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error while reading waybill data.']);
        exit;
    }
}

/**
 * Handle Driver Self-Dispatch / Assign to Vehicle Run Sheet
 */
function handleAssignToDriver() {
    if (!isLoggedIn()) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please sign in as driver.']);
        exit;
    }

    $currentUser = getCurrentUser();
    $driverId    = (int)($_POST['driver_id'] ?? $currentUser['id']);
    $parcelId    = (int)($_POST['parcel_id'] ?? 0);

    if ($parcelId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parcel ID.']);
        exit;
    }

    try {
        $pdo = getDB();

        // Fetch parcel
        $stmt = $pdo->prepare("SELECT id, tracking_number, status, receiver_city FROM parcels WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $parcelId]);
        $parcel = $stmt->fetch();

        if (!$parcel) {
            echo json_encode(['status' => 'error', 'message' => 'Parcel record not found.']);
            exit;
        }

        if ($parcel['status'] === 'delivered') {
            echo json_encode(['status' => 'error', 'message' => 'This consignment has already been delivered.']);
            exit;
        }

        $pdo->beginTransaction();

        // Update parcel
        $updStmt = $pdo->prepare("
            UPDATE parcels 
            SET assigned_driver_id = :driver_id, status = 'out_for_delivery'
            WHERE id = :id
        ");
        $updStmt->execute([':driver_id' => $driverId, ':id' => $parcelId]);

        // Insert log in tracking_logs
        $logStmt = $pdo->prepare("
            INSERT INTO tracking_logs (parcel_id, status, hub_id, updated_by, location_note, remarks, scan_timestamp)
            VALUES (:parcel_id, 'out_for_delivery', NULL, :updated_by, 'Delivery Van Route', :remarks, NOW())
        ");
        $driverName = $currentUser['full_name'];
        $logStmt->execute([
            ':parcel_id'  => $parcelId,
            ':updated_by' => $currentUser['id'],
            ':remarks'    => "Waybill QR scanned by driver {$driverName}. Dispatched on delivery route for {$parcel['receiver_city']}."
        ]);

        $pdo->commit();

        echo json_encode([
            'status'          => 'success',
            'message'         => "Shipment {$parcel['tracking_number']} added to your active Run Sheet!",
            'tracking_number' => $parcel['tracking_number']
        ]);
        exit;

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Assign Driver DB Error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to assign shipment: ' . $e->getMessage()]);
        exit;
    }
}

/**
 * Handle Doorstep OTP Verification & Proof-of-Delivery (POD)
 */
function handleVerifyPod() {
    if (!isLoggedIn()) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized driver session.']);
        exit;
    }

    $currentUser   = getCurrentUser();
    $userId        = (int)$currentUser['id'];

    $parcelId      = (int)($_POST['parcel_id'] ?? 0);
    $recipientName = trim($_POST['recipient_name'] ?? '');
    $otpEntered    = trim($_POST['otp_entered'] ?? '');
    $notes         = trim($_POST['notes'] ?? 'Handed to recipient in person');
    $codCollected  = isset($_POST['cod_collected']) && $_POST['cod_collected'] == '1';

    if ($parcelId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parcel ID.']);
        exit;
    }

    if (empty($recipientName)) {
        echo json_encode(['status' => 'error', 'message' => 'Please confirm recipient full name.']);
        exit;
    }

    if (empty($otpEntered) || strlen($otpEntered) < 4) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter the 4-digit recipient OTP.']);
        exit;
    }

    try {
        $pdo = getDB();

        $stmt = $pdo->prepare("SELECT * FROM parcels WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $parcelId]);
        $parcel = $stmt->fetch();

        if (!$parcel) {
            echo json_encode(['status' => 'error', 'message' => 'Parcel not found.']);
            exit;
        }

        if ($parcel['status'] === 'delivered') {
            echo json_encode(['status' => 'error', 'message' => 'This shipment has already been confirmed as delivered.']);
            exit;
        }

        // STRICT OTP VALIDATION
        if (trim($parcel['delivery_otp']) !== $otpEntered) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Invalid OTP! The code entered does not match the recipient secret OTP.'
            ]);
            exit;
        }

        $pdo->beginTransaction();

        $amountCollected = 0.00;

        // If Cash on Delivery, ensure COD payment is recorded
        if ($parcel['payment_method'] === 'cod' && $parcel['payment_status'] !== 'paid') {
            $amountCollected = (float)$parcel['shipping_cost'];
            $txnId = 'TXN_' . date('Y') . '_' . str_pad(mt_rand(10000, 99999), 5, '0', STR_PAD_LEFT) . '_COD';

            // Insert into payments
            $payStmt = $pdo->prepare("
                INSERT INTO payments (parcel_id, transaction_id, amount, payment_method, payment_status, card_last4, paid_at)
                VALUES (:parcel_id, :transaction_id, :amount, 'cod', 'paid', 'CASH', NOW())
            ");
            $payStmt->execute([
                ':parcel_id'       => $parcelId,
                ':transaction_id'  => $txnId,
                ':amount'          => $amountCollected
            ]);

            // Update parcel payment status
            $updPay = $pdo->prepare("UPDATE parcels SET payment_status = 'paid' WHERE id = :id");
            $updPay->execute([':id' => $parcelId]);
        }

        // Update Parcel status to 'delivered'
        $updParcel = $pdo->prepare("
            UPDATE parcels 
            SET status = 'delivered', delivered_at = NOW() 
            WHERE id = :id
        ");
        $updParcel->execute([':id' => $parcelId]);

        // Insert Proof of Delivery (POD) record
        $podStmt = $pdo->prepare("
            INSERT INTO proof_of_delivery (parcel_id, recipient_name, otp_verified, payment_collected, notes, delivered_at)
            VALUES (:parcel_id, :recipient_name, 1, :payment_collected, :notes, NOW())
            ON DUPLICATE KEY UPDATE 
                recipient_name = VALUES(recipient_name), 
                otp_verified = 1, 
                payment_collected = VALUES(payment_collected), 
                notes = VALUES(notes), 
                delivered_at = NOW()
        ");
        $podStmt->execute([
            ':parcel_id'          => $parcelId,
            ':recipient_name'     => $recipientName,
            ':payment_collected'  => $amountCollected,
            ':notes'              => $notes
        ]);

        // Insert into tracking_logs
        $logStmt = $pdo->prepare("
            INSERT INTO tracking_logs (parcel_id, status, hub_id, updated_by, location_note, remarks, scan_timestamp)
            VALUES (:parcel_id, 'delivered', NULL, :updated_by, 'Recipient Doorstep', :remarks, NOW())
        ");
        $driverName = $currentUser['full_name'];
        $logNote = "Delivered to {$recipientName} with verified 4-digit OTP. Courier: {$driverName}.";
        if ($amountCollected > 0) {
            $logNote .= " Collected $" . number_format($amountCollected, 2) . " Cash.";
        }
        $logStmt->execute([
            ':parcel_id'  => $parcelId,
            ':updated_by' => $userId,
            ':remarks'    => $logNote
        ]);

        $pdo->commit();

        echo json_encode([
            'status'          => 'success',
            'message'         => 'Delivery successfully completed! Proof of Delivery (POD) verified and logged.',
            'tracking_number' => $parcel['tracking_number'],
            'recipient_name'  => $recipientName,
            'delivered_at'    => date('M d, Y • h:i A')
        ]);
        exit;

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('POD Verify DB Error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Delivery signoff failed: ' . $e->getMessage()]);
        exit;
    }
}

/**
 * Handle Failed Delivery Attempt
 */
function handleMarkFailed() {
    if (!isLoggedIn()) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized driver session.']);
        exit;
    }

    $currentUser = getCurrentUser();
    $userId      = (int)$currentUser['id'];

    $parcelId    = (int)($_POST['parcel_id'] ?? 0);
    $reason      = trim($_POST['reason'] ?? 'Recipient not available at address');

    if ($parcelId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parcel ID.']);
        exit;
    }

    try {
        $pdo = getDB();

        $stmt = $pdo->prepare("SELECT id, tracking_number FROM parcels WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $parcelId]);
        $parcel = $stmt->fetch();

        if (!$parcel) {
            echo json_encode(['status' => 'error', 'message' => 'Parcel not found.']);
            exit;
        }

        $pdo->beginTransaction();

        $upd = $pdo->prepare("UPDATE parcels SET status = 'failed' WHERE id = :id");
        $upd->execute([':id' => $parcelId]);

        $log = $pdo->prepare("
            INSERT INTO tracking_logs (parcel_id, status, hub_id, updated_by, location_note, remarks, scan_timestamp)
            VALUES (:parcel_id, 'failed', NULL, :updated_by, 'Delivery Address', :remarks, NOW())
        ");
        $log->execute([
            ':parcel_id'  => $parcelId,
            ':updated_by' => $userId,
            ':remarks'    => "Delivery attempt unsuccessful. Reason: {$reason}"
        ]);

        $pdo->commit();

        echo json_encode([
            'status'          => 'success',
            'message'         => 'Delivery marked as failed and rescheduled.',
            'tracking_number' => $parcel['tracking_number']
        ]);
        exit;

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Failed Delivery DB Error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Unable to record failed status.']);
        exit;
    }
}
