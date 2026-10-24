<?php
// ========================================================
// SwiftScan Express - Hub Operations & Routing AJAX Handler
// (ajax/hub_handler.php)
// Handles Inbound/Outbound Batch QR Scans, Transfers & Dispatches
// ========================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

// Strict Role Check: Hub Managers and Admins only
if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized session. Please sign in as Hub Manager.']);
    exit;
}

$currentUser = getCurrentUser();
if (!in_array($currentUser['role'], ['hub_manager', 'admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Hub Manager authority required.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'hub_scan_intake':
        handleHubScanIntake();
        break;

    case 'dispatch_to_hub':
        handleDispatchToHub();
        break;

    case 'assign_driver_dispatch':
        handleAssignDriverDispatch();
        break;

    default:
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid or unknown hub action.'
        ]);
        exit;
}

/**
 * Handle High-Speed Batch QR Scan Intake at Sorting Facility
 */
function handleHubScanIntake() {
    global $currentUser;

    $rawCode  = trim($_POST['code'] ?? $_POST['tracking_number'] ?? '');
    $hubId    = (int)($_POST['hub_id'] ?? $currentUser['hub_id'] ?? 1);
    $scanType = trim($_POST['scan_type'] ?? 'intake'); // 'intake' (new dropoff) or 'transit_arrival' (interstate transfer arrival)

    // Extract tracking code if scanned code is a full URL: ...?track=CR-2026-XXXXX
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
        echo json_encode(['status' => 'error', 'message' => 'Invalid or missing QR tracking code.']);
        exit;
    }

    try {
        $pdo = getDB();

        // 1. Fetch Hub Info
        $hubStmt = $pdo->prepare("SELECT id, hub_name, hub_code, city FROM hubs WHERE id = :id LIMIT 1");
        $hubStmt->execute([':id' => $hubId]);
        $hub = $hubStmt->fetch();

        if (!$hub) {
            echo json_encode(['status' => 'error', 'message' => 'Operating hub facility not found.']);
            exit;
        }

        // 2. Fetch Parcel Info
        $sql = "
            SELECT p.*, dh.hub_name AS dest_hub_name, dh.hub_code AS dest_hub_code, dh.city AS dest_city
            FROM parcels p
            LEFT JOIN hubs dh ON p.destination_hub_id = dh.id
            WHERE UPPER(p.tracking_number) = UPPER(:tn)
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':tn' => $cleanCode]);
        $parcel = $stmt->fetch();

        if (!$parcel) {
            echo json_encode(['status' => 'error', 'message' => "Waybill #{$cleanCode} is not in database."]);
            exit;
        }

        if ($parcel['status'] === 'delivered') {
            echo json_encode(['status' => 'error', 'message' => "Consignment #{$cleanCode} was already delivered to recipient."]);
            exit;
        }

        $pdo->beginTransaction();

        $parcelId = (int)$parcel['id'];
        $isDestHub = ((int)$parcel['destination_hub_id'] === $hubId);

        // Determine new status based on current status and routing
        $newStatus = 'in_transit';
        $locationNote = $hub['hub_name'] . ' (' . $hub['city'] . ')';
        $remarks = '';

        if ($parcel['status'] === 'booked') {
            $newStatus = 'picked_up';
            $remarks = "Parcel received at {$hub['hub_name']} drop-off intake counter.";
        } elseif ($isDestHub) {
            $newStatus = 'in_transit';
            $remarks = "Arrived at destination sorting center {$hub['hub_name']}. Ready for local courier route assignment.";
        } else {
            $newStatus = 'in_transit';
            $remarks = "Arrived at transit transfer hub {$hub['hub_name']} for sorting.";
        }

        // Update parcel current hub and status
        $updSql = "
            UPDATE parcels 
            SET current_hub_id = :hub_id, status = :status
            WHERE id = :id
        ";
        $updStmt = $pdo->prepare($updSql);
        $updStmt->execute([
            ':hub_id' => $hubId,
            ':status' => $newStatus,
            ':id'     => $parcelId
        ]);

        // Insert audit log
        $logSql = "
            INSERT INTO tracking_logs (parcel_id, status, hub_id, updated_by, location_note, remarks, scan_timestamp)
            VALUES (:parcel_id, :status, :hub_id, :updated_by, :location_note, :remarks, NOW())
        ";
        $logStmt = $pdo->prepare($logSql);
        $logStmt->execute([
            ':parcel_id'     => $parcelId,
            ':status'        => $newStatus,
            ':hub_id'        => $hubId,
            ':updated_by'    => $currentUser['id'],
            ':location_note' => $locationNote,
            ':remarks'       => $remarks
        ]);

        $pdo->commit();

        echo json_encode([
            'status'          => 'success',
            'message'         => "Waybill {$parcel['tracking_number']} checked in at {$hub['hub_name']}!",
            'data' => [
                'parcel_id'        => $parcelId,
                'tracking_number'  => $parcel['tracking_number'],
                'new_status'       => $newStatus,
                'status_label'     => ucfirst(str_replace('_', ' ', $newStatus)),
                'receiver_name'    => $parcel['receiver_name'],
                'receiver_city'    => $parcel['receiver_city'],
                'dest_hub'         => $parcel['dest_hub_name'] . ' (' . $parcel['dest_hub_code'] . ')',
                'is_dest_hub'      => $isDestHub,
                'weight'           => number_format((float)$parcel['weight_kg'], 2) . ' kg',
                'payment_status'   => strtoupper($parcel['payment_status']),
                'payment_method'   => strtoupper($parcel['payment_method']),
                'scanned_at'       => date('h:i:s A')
            ]
        ]);
        exit;

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Hub Intake DB Error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Hub check-in failed: ' . $e->getMessage()]);
        exit;
    }
}

/**
 * Handle Outbound Dispatch to Another Transfer Hub (Interstate Routing)
 */
function handleDispatchToHub() {
    global $currentUser;

    $parcelId    = (int)($_POST['parcel_id'] ?? 0);
    $targetHubId = (int)($_POST['target_hub_id'] ?? 0);

    if ($parcelId <= 0 || $targetHubId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Please select target transfer hub facility.']);
        exit;
    }

    try {
        $pdo = getDB();

        // Fetch Target Hub
        $hubStmt = $pdo->prepare("SELECT id, hub_name, city, hub_code FROM hubs WHERE id = :id LIMIT 1");
        $hubStmt->execute([':id' => $targetHubId]);
        $targetHub = $hubStmt->fetch();

        if (!$targetHub) {
            echo json_encode(['status' => 'error', 'message' => 'Target hub not found.']);
            exit;
        }

        // Fetch Parcel
        $stmt = $pdo->prepare("SELECT id, tracking_number, status FROM parcels WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $parcelId]);
        $parcel = $stmt->fetch();

        if (!$parcel) {
            echo json_encode(['status' => 'error', 'message' => 'Parcel record not found.']);
            exit;
        }

        $pdo->beginTransaction();

        // Update Parcel status to in_transit
        $upd = $pdo->prepare("UPDATE parcels SET status = 'in_transit' WHERE id = :id");
        $upd->execute([':id' => $parcelId]);

        // Insert tracking log
        $log = $pdo->prepare("
            INSERT INTO tracking_logs (parcel_id, status, hub_id, updated_by, location_note, remarks, scan_timestamp)
            VALUES (:parcel_id, 'in_transit', :hub_id, :updated_by, :location_note, :remarks, NOW())
        ");
        $log->execute([
            ':parcel_id'     => $parcelId,
            ':hub_id'        => $currentUser['hub_id'],
            ':updated_by'    => $currentUser['id'],
            ':location_note' => "Interstate Transit ➔ {$targetHub['hub_name']}",
            ':remarks'       => "Sorted and dispatched on transit vehicle to {$targetHub['hub_name']} ({$targetHub['city']})."
        ]);

        $pdo->commit();

        echo json_encode([
            'status'          => 'success',
            'message'         => "Consignment {$parcel['tracking_number']} dispatched to {$targetHub['hub_name']}!",
            'tracking_number' => $parcel['tracking_number']
        ]);
        exit;

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Dispatch Hub DB Error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Transfer dispatch failed.']);
        exit;
    }
}

/**
 * Handle Dispatching Local Parcel to a Delivery Driver
 */
function handleAssignDriverDispatch() {
    global $currentUser;

    $parcelId = (int)($_POST['parcel_id'] ?? 0);
    $driverId = (int)($_POST['driver_id'] ?? 0);

    if ($parcelId <= 0 || $driverId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Please select an authorized delivery courier.']);
        exit;
    }

    try {
        $pdo = getDB();

        // Fetch Driver
        $dStmt = $pdo->prepare("SELECT id, full_name, phone FROM users WHERE id = :id AND role = 'driver' LIMIT 1");
        $dStmt->execute([':id' => $driverId]);
        $driver = $dStmt->fetch();

        if (!$driver) {
            echo json_encode(['status' => 'error', 'message' => 'Driver not found.']);
            exit;
        }

        // Fetch Parcel
        $stmt = $pdo->prepare("SELECT id, tracking_number, receiver_name, receiver_city FROM parcels WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $parcelId]);
        $parcel = $stmt->fetch();

        if (!$parcel) {
            echo json_encode(['status' => 'error', 'message' => 'Parcel record not found.']);
            exit;
        }

        $pdo->beginTransaction();

        // Update Parcel
        $upd = $pdo->prepare("
            UPDATE parcels 
            SET assigned_driver_id = :driver_id, status = 'out_for_delivery'
            WHERE id = :id
        ");
        $upd->execute([':driver_id' => $driverId, ':id' => $parcelId]);

        // Insert log in tracking_logs
        $log = $pdo->prepare("
            INSERT INTO tracking_logs (parcel_id, status, hub_id, updated_by, location_note, remarks, scan_timestamp)
            VALUES (:parcel_id, 'out_for_delivery', :hub_id, :updated_by, 'Delivery Route', :remarks, NOW())
        ");
        $log->execute([
            ':parcel_id'     => $parcelId,
            ':hub_id'        => $currentUser['hub_id'],
            ':updated_by'    => $currentUser['id'],
            ':remarks'       => "Assigned to courier {$driver['full_name']} for final doorstep delivery in {$parcel['receiver_city']}."
        ]);

        $pdo->commit();

        echo json_encode([
            'status'          => 'success',
            'message'         => "Shipment {$parcel['tracking_number']} assigned to {$driver['full_name']} (Out for Delivery)!",
            'tracking_number' => $parcel['tracking_number'],
            'driver_name'     => $driver['full_name']
        ]);
        exit;

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Assign Driver Hub DB Error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Driver dispatch failed.']);
        exit;
    }
}
