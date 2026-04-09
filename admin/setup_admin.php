<?php
session_start();
require_once '../config/db_config.php';

date_default_timezone_set('Asia/Manila');

$error_message = '';
$success_message = '';

function sanitizeOutput($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function adminTableExists(PDO $pdo): bool {
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    return $stmt && $stmt->rowCount() > 0;
}

function ensureAdminTable(PDO $pdo): void {
    $sql = "
        CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            full_name VARCHAR(100) DEFAULT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'admin',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_login TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql);
}

function activeAdminCount(PDO $pdo): int {
    $hasIsActive = $pdo->query("SHOW COLUMNS FROM admin_users LIKE 'is_active'")->rowCount() > 0;
    if ($hasIsActive) {
        return (int) $pdo->query("SELECT COUNT(*) FROM admin_users WHERE is_active = 1")->fetchColumn();
    }
    return (int) $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
}

try {
    ensureAdminTable($pdo);
    $adminCount = activeAdminCount($pdo);
} catch (Exception $e) {
    $adminCount = 0;
    $error_message = 'Unable to initialize admin setup. Please check database configuration.';
}

if ($adminCount > 0) {
    header('Location: login.php?setup=done');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($username === '' || $email === '' || $password === '') {
        $error_message = 'Username, email, and password are required.';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $error_message = 'Username must be 3-50 characters and can include letters, numbers, dot, underscore, and dash.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error_message = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $error_message = 'Password confirmation does not match.';
    } else {
        try {
            $exists = $pdo->prepare('SELECT id FROM admin_users WHERE username = :username OR email = :email LIMIT 1');
            $exists->execute(['username' => $username, 'email' => $email]);
            if ($exists->fetch(PDO::FETCH_ASSOC)) {
                $error_message = 'Username or email already exists.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $insert = $pdo->prepare('INSERT INTO admin_users (username, password, email, full_name, role, is_active, created_at, updated_at) VALUES (:username, :password, :email, :full_name, :role, 1, NOW(), NOW())');
                $insert->execute([
                    'username' => $username,
                    'password' => $passwordHash,
                    'email' => $email,
                    'full_name' => $fullName !== '' ? $fullName : $username,
                    'role' => 'admin'
                ]);

                $success_message = 'First admin account created successfully. You can now sign in.';
            }
        } catch (Exception $e) {
            $error_message = 'Failed to create admin account. Please try again.';
            error_log('setup_admin.php error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initial Admin Setup</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f5f7fb; margin: 0; }
        .wrap { max-width: 520px; margin: 48px auto; padding: 0 16px; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); padding: 24px; }
        h1 { margin: 0 0 8px; font-size: 1.5rem; color: #0f172a; }
        p { margin: 0 0 16px; color: #475569; }
        label { display: block; margin: 12px 0 6px; font-weight: 600; color: #334155; }
        input { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; }
        .btn { margin-top: 16px; width: 100%; border: none; border-radius: 10px; padding: 12px; background: #178a4a; color: #fff; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #126c3a; }
        .alert { border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; font-size: 14px; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .alert-success { background: #dcfce7; color: #166534; }
        .links { margin-top: 12px; text-align: center; }
        .links a { color: #0f766e; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Initial Admin Setup</h1>
            <p>Create the first admin account for dashboard access.</p>

            <?php if ($error_message !== ''): ?>
                <div class="alert alert-error"><?php echo sanitizeOutput($error_message); ?></div>
            <?php endif; ?>

            <?php if ($success_message !== ''): ?>
                <div class="alert alert-success"><?php echo sanitizeOutput($success_message); ?></div>
                <div class="links"><a href="login.php">Go to admin login</a></div>
            <?php else: ?>
                <form method="POST" action="">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required maxlength="50" autocomplete="username">

                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required maxlength="100" autocomplete="email">

                    <label for="full_name">Full Name (optional)</label>
                    <input type="text" id="full_name" name="full_name" maxlength="100" autocomplete="name">

                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">

                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">

                    <button class="btn" type="submit">Create Admin Account</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
