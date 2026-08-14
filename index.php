<?php
session_start();

// If already logged in, go straight to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Handle Login Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    try {
        $pdo = new PDO("mysql:host=127.0.0.1;dbname=synergy1_derricklim_parcel_delivery_management;charset=utf8mb4", "synergy1_yenping", "R.zb0ZwEuGZ}*fW2");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password!';
        }
    } catch (PDOException $e) {
        $error = 'Database Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Courier Management System</title>
    <style>
        * { box-sizing: border-box; font-family: system-ui, sans-serif; }
        body { background: #f1f5f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 100%; max-width: 380px; }
        h2 { margin-top: 0; text-align: center; color: #1e293b; }
        .group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; color: #475569; }
        input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1rem; }
        button { width: 100%; background: #2563eb; color: white; padding: 12px; border: none; border-radius: 6px; font-size: 1rem; font-weight: bold; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        .alert { background: #fef2f2; color: #dc2626; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.9rem; text-align: center; }
    </style>
</head>
<body>

<div class="card">
    <h2>⚡ Parcel System Login</h2>

    <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php" autocomplete="off">
        <div class="group">
            <label>Email Address</label>
            <!-- Blank input box -->
            <input type="email" name="email" placeholder="Enter your email" required autocomplete="off">
        </div>
        <div class="group">
            <label>Password</label>
            <!-- Blank input box -->
            <input type="password" name="password" placeholder="Enter your password" required autocomplete="new-password">
        </div>
        <button type="submit">Log In</button>
    </form>
</div>

</body>
</html>