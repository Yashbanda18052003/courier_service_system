<?php
// ========================================================
// SwiftScan Express - Payment Processing Engine
// (ajax/payment_handler.php)
// Handles Online Prepaid Checkout, Card Validation & COD
// ========================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get_payment_info':
        handleGetPaymentInfo();
        break;

    case 'process_card_payment':
        handleProcessCardPayment();
        break;

    case 'collect_cod_payment':
        handleCollectCodPayment();
        break;

    default:
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid or missing payment action.'
        ]);
        exit;
}

/**
 * Fetch Payment Info for a Parcel
 */
function handleGetPaymentInfo() {
    $parcelId       = (int)($_REQUEST['parcel_id'] ?? 0);
    $trackingNumber = trim($_REQUEST['tracking_number'] ?? '');

    try {
        $pdo = getDB();

        $sql = "SELECT id, tracking_number, receiver_name, receiver_city, parcel_type, weight_kg, shipping_cost, payment_status, payment_method, sender_id FROM parcels WHERE ";
        $params = [];

        if ($parcelId > 0) {
            $sql .= "id = :id LIMIT 1";
            $params[':id'] = $parcelId;
        } elseif (!empty($trackingNumber)) {
            $sql .= "UPPER(tracking_number) = UPPER(:tn) LIMIT 1";
            $params[':tn'] = $trackingNumber;
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Parcel ID or Tracking Number required.']);
            exit;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $parcel = $stmt->fetch();

        if (!$parcel) {
            echo json_encode(['status' => 'error', 'message' => 'Parcel not found.']);
            exit;
        }

        echo json_encode([
            'status' => 'success',
            'data' => [
                'parcel_id'       => (int)$parcel['id'],
                'tracking_number' => $parcel['tracking_number'],
                'receiver_name'   => $parcel['receiver_name'],
                'receiver_city'   => $parcel['receiver_city'],
                'parcel_type'     => $parcel['parcel_type'],
                'weight_kg'       => number_format((float)$parcel['weight_kg'], 2) . ' kg',
                'amount'          => (float)$parcel['shipping_cost'],
                'amount_fmt'      => '$' . number_format((float)$parcel['shipping_cost'], 2),
                'payment_status'  => $parcel['payment_status'],
                'payment_method'  => $parcel['payment_method'],
                'is_paid'         => $parcel['payment_status'] === 'paid'
            ]
        ]);
        exit;

    } catch (PDOException $e) {
        error_log('Get Payment Info DB Error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error while fetching invoice.']);
        exit;
    }
}

/**
 * Process Online Prepaid Card Checkout
 */
function handleProcessCardPayment() {
    if (!isLoggedIn()) {
        echo json_encode(['status' => 'error', 'message' => 'Session expired. Please sign in to pay.']);
        exit;
    }

    $currentUser = getCurrentUser();
    $userId = (int)$currentUser['id'];

    $parcelId   = (int)($_POST['parcel_id'] ?? 0);
    $cardHolder = trim($_POST['card_holder'] ?? '');
    $cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    $cardExp    = trim($_POST['card_exp'] ?? '');
    $cardCvv    = trim($_POST['card_cvv'] ?? '');

    // Card Validations
    if ($parcelId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parcel selected for payment.']);
        exit;
    }

    if (empty($cardHolder) || strlen($cardHolder) < 3) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter the cardholder name as displayed on the card.']);
        exit;
    }

    if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid 16-digit debit or credit card number.']);
        exit;
    }

    // Expiry check MM/YY
    if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $cardExp, $matches)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid expiration format. Use MM/YY (e.g. 08/28).']);
        exit;
    }

    $expMonth = (int)$matches[1];
    $expYear  = 2000 + (int)$matches[2];
    $currentYear  = (int)date('Y');
    $currentMonth = (int)date('n');

    if ($expYear < $currentYear || ($expYear === $currentYear && $expMonth < $currentMonth)) {
        echo json_encode(['status' => 'error', 'message' => 'The card expiration date has expired.']);
        exit;
    }

    if (strlen($cardCvv) < 3 || strlen($cardCvv) > 4) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CVV security code (3 or 4 digits required).']);
        exit;
    }

    try {
        $pdo = getDB();

        // Fetch parcel
        $stmt = $pdo->prepare("SELECT id, tracking_number, shipping_cost, payment_status, sender_id FROM parcels WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $parcelId]);
        $parcel = $stmt->fetch();

        if (!$parcel) {
            echo json_encode(['status' => 'error', 'message' => 'Parcel record not found.']);
            exit;
        }

        if ($parcel['payment_status'] === 'paid') {
            echo json_encode(['status' => 'error', 'message' => 'This consignment has already been settled and paid.']);
            exit;
        }

        $amount = (float)$parcel['shipping_cost'];
        $last4 = substr($cardNumber, -4);

        // Generate unique Transaction ID: TXN_2026_XXXXX_ONL
        $txnId = 'TXN_' . date('Y') . '_' . str_pad(mt_rand(10000, 99999), 5, '0', STR_PAD_LEFT) . '_ONL';

        $pdo->beginTransaction();

        // 1. Insert into payments table
        $paySql = "
            INSERT INTO payments (parcel_id, transaction_id, amount, payment_method, payment_status, card_last4, paid_at)
            VALUES (:parcel_id, :transaction_id, :amount, 'online', 'paid', :card_last4, NOW())
        ";
        $payStmt = $pdo->prepare($paySql);
        $payStmt->execute([
            ':parcel_id'       => $parcelId,
            ':transaction_id'  => $txnId,
            ':amount'          => $amount,
            ':card_last4'      => $last4
        ]);

        // 2. Update parcel payment status
        $updateSql = "
            UPDATE parcels 
            SET payment_status = 'paid', payment_method = 'online'
            WHERE id = :id
        ";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([':id' => $parcelId]);

        // 3. Log milestone in tracking_logs
        $logSql = "
            INSERT INTO tracking_logs (parcel_id, status, hub_id, updated_by, location_note, remarks, scan_timestamp)
            VALUES (:parcel_id, 'booked', NULL, :updated_by, 'Online Payment Gateway', :remarks, NOW())
        ";
        $logStmt = $pdo->prepare($logSql);
        $logStmt->execute([
            ':parcel_id'  => $parcelId,
            ':updated_by' => $userId,
            ':remarks'    => "Prepaid shipping fee settled via Card ending in {$last4} (TXN: {$txnId})."
        ]);

        $pdo->commit();

        echo json_encode([
            'status'          => 'success',
            'message'         => 'Payment successfully authorized! Waybill marked PAID.',
            'transaction_id'  => $txnId,
            'card_last4'      => $last4,
            'amount_paid'     => '$' . number_format($amount, 2),
            'tracking_number' => $parcel['tracking_number'],
            'paid_at'         => date('M d, Y • h:i A')
        ]);
        exit;

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Process Card DB Error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Payment processing failed: ' . $e->getMessage()]);
        exit;
    }
}

/**
 * Handle Driver Cash on Delivery (COD) Doorstep Collection
 */
function handleCollectCodPayment() {
    if (!isLoggedIn()) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized operation.']);
        exit;
    }

    $currentUser = getCurrentUser();
    $userId = (int)$currentUser['id'];

    // Only drivers, hub managers, or admins can mark COD collected
    if (!in_array($currentUser['role'], ['driver', 'hub_manager', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Only authorized delivery personnel can record COD cash collections.']);
        exit;
    }

    $parcelId = (int)($_POST['parcel_id'] ?? 0);
    $amountCollected = (float)($_POST['amount_collected'] ?? 0.0);

    if ($parcelId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parcel ID.']);
        exit;
    }

    try {
        $pdo = getDB();

        $stmt = $pdo->prepare("SELECT id, tracking_number, shipping_cost, payment_status, payment_method FROM parcels WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $parcelId]);
        $parcel = $stmt->fetch();

        if (!$parcel) {
            echo json_encode(['status' => 'error', 'message' => 'Parcel record not found.']);
            exit;
        }

        if ($parcel['payment_status'] === 'paid') {
            echo json_encode(['status' => 'error', 'message' => 'Payment has already been marked collected for this parcel.']);
            exit;
        }

        if ($amountCollected <= 0) {
            $amountCollected = (float)$parcel['shipping_cost'];
        }

        $txnId = 'TXN_' . date('Y') . '_' . str_pad(mt_rand(10000, 99999), 5, '0', STR_PAD_LEFT) . '_COD';

        $pdo->beginTransaction();

        // 1. Insert into payments table
        $paySql = "
            INSERT INTO payments (parcel_id, transaction_id, amount, payment_method, payment_status, card_last4, paid_at)
            VALUES (:parcel_id, :transaction_id, :amount, 'cod', 'paid', 'CASH', NOW())
        ";
        $payStmt = $pdo->prepare($paySql);
        $payStmt->execute([
            ':parcel_id'      => $parcelId,
            ':transaction_id' => $txnId,
            ':amount'         => $amountCollected
        ]);

        // 2. Update parcel
        $updateSql = "UPDATE parcels SET payment_status = 'paid' WHERE id = :id";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([':id' => $parcelId]);

        // 3. Log milestone in tracking_logs
        $logSql = "
            INSERT INTO tracking_logs (parcel_id, status, hub_id, updated_by, location_note, remarks, scan_timestamp)
            VALUES (:parcel_id, 'out_for_delivery', NULL, :updated_by, 'Doorstep Delivery', :remarks, NOW())
        ";
        $logStmt = $pdo->prepare($logSql);
        $logStmt->execute([
            ':parcel_id'  => $parcelId,
            ':updated_by' => $userId,
            ':remarks'    => "Cash On Delivery collected: $" . number_format($amountCollected, 2) . " by courier {$currentUser['full_name']} (Receipt: {$txnId})."
        ]);

        $pdo->commit();

        echo json_encode([
            'status'          => 'success',
            'message'         => 'COD cash collection recorded successfully!',
            'transaction_id'  => $txnId,
            'amount_collected'=> '$' . number_format($amountCollected, 2),
            'tracking_number' => $parcel['tracking_number']
        ]);
        exit;

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('COD Payment DB Error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'COD collection recording failed: ' . $e->getMessage()]);
        exit;
    }
}
