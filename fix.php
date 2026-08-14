<?php
try {
    // 1. Connect to MySQL
    $pdo = new PDO("mysql:host=127.0.0.1", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Create Database & Table if missing
    $pdo->exec("CREATE DATABASE IF NOT EXISTS parcel_db");
    $pdo->exec("USE parcel_db");
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) DEFAULT 'admin',
        status VARCHAR(20) DEFAULT 'active'
    )");

    // 3. Reset Admin User with guaranteed hash
    $hash = password_hash('password123', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("REPLACE INTO users (id, full_name, email, password_hash, role, status) VALUES (1, 'Admin User', 'admin@courier.com', ?, 'admin', 'active')");
    $stmt->execute([$hash]);

    echo "
    <div style='font-family: sans-serif; padding: 40px; text-align: center;'>
      <h1 style='color: #16a34a;'>🎉 Login Fixed Automatically!</h1>
      <p style='font-size: 1.2rem;'>Database and admin account are now 100% ready.</p>
      <div style='background: #f1f5f9; display: inline-block; padding: 15px 25px; border-radius: 8px; text-align: left;'>
        <p><strong>Email:</strong> admin@courier.com</p>
        <p><strong>Password:</strong> password123</p>
      </div>
      <br><br>
      <a href='index.php' style='background: #2563eb; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>👉 Go to Login Page</a>
    </div>";

} catch (Exception $e) {
    echo "<h2 style='color:red;'>Error: " . $e->getMessage() . "</h2><p>Make sure Apache and MySQL are turned ON in XAMPP/WAMP Control Panel.</p>";
}