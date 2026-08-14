<?php
$host    = '127.0.0.1'; // Use 127.0.0.1 instead of 'localhost' for faster PDO connection
$db      = 'synergy1_derricklim_parcel_delivery_management';
$user    = 'synergy1_yenping';
$pass    = 'R.zb0ZwEuGZ}*fW2';          // Default XAMPP password is empty (''). If using MAMP, it might be 'root'.
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB Connection Failed: ' . $e->getMessage()]);
    exit;
}