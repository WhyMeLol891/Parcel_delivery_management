<?php
// api/upload_proof.php
header('Content-Type: application/json');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$parcelId = $_POST['parcel_id'] ?? null;
$remarks  = $_POST['remarks'] ?? 'Delivered with photo proof';

if (!$parcelId || !isset($_FILES['proofImage'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parcel ID and image are required.']);
    exit;
}

$file = $_FILES['proofImage'];

// 1. File Upload Validation
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG and PNG allowed.']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit.']);
    exit;
}

// 2. Ensure target upload directory exists
$uploadDir = '../uploads/proofs/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// 3. Generate unique file name
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'proof_' . $parcelId . '_' . time() . '_' . rand(100,999) . '.' . $ext;
$targetPath = $uploadDir . $filename;
$dbPath = 'uploads/proofs/' . $filename;

// 4. Save file & Update DB
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    $stmt = $pdo->prepare("UPDATE parcels SET proof_photo_path = ?, delivery_remarks = ?, status = 'Delivered' WHERE id = ?");
    $stmt->execute([$dbPath, $remarks, $parcelId]);

    $log = $pdo->prepare("INSERT INTO delivery_logs (parcel_id, status, notes) VALUES (?, 'Delivered', ?)");
    $log->execute([$parcelId, "Photo POD uploaded. Remarks: " . $remarks]);

    echo json_encode([
        'success' => true,
        'message' => 'Proof of delivery uploaded successfully.',
        'photo_path' => $dbPath
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save file on server.']);
}