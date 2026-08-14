<?php
// Start secure session if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// ------------------------------------------------------------------
// 1. CSRF Protection
// ------------------------------------------------------------------
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF Token Validation Failed.']);
        exit;
    }
}

// ------------------------------------------------------------------
// 2. Input Sanitization
// ------------------------------------------------------------------
function sanitize_string($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function sanitize_email($email) {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

// ------------------------------------------------------------------
// 3. Password Hashing (BCRYPT / Argon2)
// ------------------------------------------------------------------
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// ------------------------------------------------------------------
// 4. Role-Based Access Control (RBAC) Middleware
// ------------------------------------------------------------------
function require_auth($allowed_roles = []) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized session. Please log in.']);
        exit;
    }

    if (!empty($allowed_roles) && !in_array($_SESSION['user_role'], $allowed_roles, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied: Insufficient permissions.']);
        exit;
    }
}