<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/security.php';

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $input = json_decode(file_get_contents('php://input'), true);

    verify_csrf_token($input['csrf_token'] ?? '');

    $email    = sanitize_email($input['email'] ?? '');
    $password = $input['password'] ?? '';

    // Prepared Statement prevents SQL Injection
    $stmt = $pdo->prepare("SELECT id, full_name, email, password_hash, role, status FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && verify_password($password, $user['password_hash'])) {
        if ($user['status'] !== 'active') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Account is inactive. Contact Administrator.']);
            exit;
        }

        // Prevent Session Fixation
        session_regenerate_id(true);

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];

        // Audit Log Entry
        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, 'user_login', ?)");
        $logStmt->execute([$user['id'], $_SERVER['REMOTE_ADDR']]);

        echo json_encode([
            'success'  => true,
            'message'  => 'Login successful.',
            'role'     => $user['role'],
            'redirect' => 'dashboard.php'
        ]);
        exit;
    }

    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    exit;
}

if ($action === 'logout') {
    session_unset();
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
    exit;
}