<?php
// api/parcels.php
header('Content-Type: application/json');
require_once '../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

// Helper: Auto-Generate Tracking Number
function generateTrackingNumber() {
    return 'TRK-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(2))) . rand(1000, 9000);
}

// ----------------------------------------------------
// 1. READ (GET): Fetch all parcels or search
// ----------------------------------------------------
if ($method === 'GET') {
    $search = $_GET['search'] ?? '';
    
    if (!empty($search)) {
        $stmt = $pdo->prepare("SELECT * FROM parcels WHERE tracking_number LIKE ? OR recipient_name LIKE ? ORDER BY id DESC");
        $stmt->execute(["%$search%", "%$search%"]);
    } else {
        $stmt = $pdo->query("SELECT * FROM parcels ORDER BY id DESC");
    }
    
    $parcels = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $parcels]);
    exit;
}

// ----------------------------------------------------
// 2. CREATE (POST): Create parcel + Auto Tracking ID
// ----------------------------------------------------
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $recipient = trim($input['recipient_name'] ?? '');
    $phone     = trim($input['phone'] ?? '');
    $address   = trim($input['address'] ?? '');

    if (empty($recipient) || empty($phone) || empty($address)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    $trackingNo = generateTrackingNumber();

    $stmt = $pdo->prepare("INSERT INTO parcels (tracking_number, recipient_name, phone, address, status) VALUES (?, ?, ?, ?, 'Pending')");
    $stmt->execute([$trackingNo, $recipient, $phone, $address]);
    $parcelId = $pdo->lastInsertId();

    // Add Initial Audit Log
    $logStmt = $pdo->prepare("INSERT INTO delivery_logs (parcel_id, status, notes) VALUES (?, 'Pending', 'Parcel record created.')");
    $logStmt->execute([$parcelId]);

    echo json_encode([
        'success' => true,
        'message' => 'Parcel created successfully.',
        'tracking_number' => $trackingNo,
        'parcel_id' => $parcelId
    ]);
    exit;
}

// ----------------------------------------------------
// 3. EDIT / ASSIGN RIDER (PUT)
// ----------------------------------------------------
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parcel ID required.']);
        exit;
    }

    // Assign Rider action
    if (isset($input['rider_id'])) {
        $stmt = $pdo->prepare("UPDATE parcels SET assigned_rider_id = ?, status = 'Assigned' WHERE id = ?");
        $stmt->execute([$input['rider_id'], $id]);

        $logStmt = $pdo->prepare("INSERT INTO delivery_logs (parcel_id, status, notes) VALUES (?, 'Assigned', ?)");
        $logStmt->execute([$id, "Assigned to rider ID: " . $input['rider_id']]);

        echo json_encode(['success' => true, 'message' => 'Rider assigned successfully.']);
        exit;
    }
}

// ----------------------------------------------------
// 4. DELETE (DELETE)
// ----------------------------------------------------
if ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parcel ID required.']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM parcels WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Parcel deleted.']);
    exit;
}