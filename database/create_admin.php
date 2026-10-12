<?php
/**
 * Kalika Enterprises - Initial Admin User Provisioning Script
 * 
 * SECURITY NOTICE:
 * This script is intended to be executed ONCE during initial deployment.
 * Delete or rename this file immediately after creating your administrator account!
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = get_db_connection(true);
$isCli = (php_sapi_name() === 'cli');

$message = '';
$error = '';

if ($isCli) {
    echo "=====================================================\n";
    echo "Kalika Enterprises - Admin Creation Utility (CLI)\n";
    echo "=====================================================\n\n";

    // Read username
    if (isset($argv[1])) {
        $username = trim($argv[1]);
    } else {
        echo "Enter Admin Username [admin]: ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        $username = trim($line) ?: 'admin';
    }

    // Read password
    if (isset($argv[2])) {
        $password = trim($argv[2]);
    } else {
        echo "Enter Strong Admin Password (min 8 chars): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        $password = trim($line);
    }

    if (strlen($password) < 8) {
        echo "\n[ERROR] Password must be at least 8 characters long!\n";
        exit(1);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO `admins` (`username`, `password_hash`)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`)
        ");
        $stmt->execute([$username, $hash]);

        echo "\n[SUCCESS] Admin account '{$username}' successfully created/updated!\n";
        echo "[SECURITY WARNING] Please DELETE this script now from the database/ directory:\n";
        echo "rm " . __FILE__ . "\n\n";
        exit(0);
    } catch (PDOException $e) {
        echo "\n[ERROR] Database error: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    // Web interface for shared hosting / browser invocation
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || strlen($password) < 8) {
            $error = 'Username cannot be empty and password must be at least 8 characters long.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO `admins` (`username`, `password_hash`)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`)
                ");
                $stmt->execute([$username, $hash]);
                $message = "Admin account '<strong>" . htmlspecialchars($username) . "</strong>' created successfully!";
            } catch (PDOException $e) {
                $error = 'Database error: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Kalika Admin Account Setup</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 1rem; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 2rem; max-width: 440px; width: 100%; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        h1 { font-size: 1.5rem; margin-top: 0; color: #f59e0b; }
        .warning-box { background: #451a03; border-left: 4px solid #f59e0b; padding: 0.75rem 1rem; font-size: 0.8125rem; color: #fef3c7; border-radius: 4px; margin-bottom: 1.5rem; }
        .success-box { background: #064e3b; border-left: 4px solid #10b981; padding: 0.75rem 1rem; font-size: 0.875rem; color: #ecfdf5; border-radius: 4px; margin-bottom: 1.5rem; }
        .error-box { background: #7f1d1d; border-left: 4px solid #ef4444; padding: 0.75rem 1rem; font-size: 0.875rem; color: #fee2e2; border-radius: 4px; margin-bottom: 1.5rem; }
        label { display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.35rem; color: #cbd5e1; }
        input[type="text"], input[type="password"] { width: 100%; padding: 0.65rem; border-radius: 6px; border: 1px solid #475569; background: #0f172a; color: #ffffff; font-size: 1rem; box-sizing: border-box; margin-bottom: 1rem; }
        button { width: 100%; padding: 0.75rem; background: #f59e0b; color: #0f172a; border: none; border-radius: 6px; font-weight: 700; font-size: 1rem; cursor: pointer; }
        button:hover { background: #d97706; }
        .delete-notice { margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #334155; font-size: 0.75rem; color: #ef4444; text-align: center; }
    </style>
</head>
<body>

<div class="card">
    <h1>Create Admin Account</h1>

    <div class="warning-box">
        <strong>SECURITY NOTICE:</strong> This is a one-time utility. Once you have created your admin login, you MUST delete this file (<code>database/create_admin.php</code>) before going live.
    </div>

    <?php if ($message): ?>
        <div class="success-box">
            <?= $message ?>
            <p style="margin: 0.5rem 0 0;"><a href="../admin/login.php" style="color: #6ee7b7; font-weight: bold;">Proceed to Admin Login &rarr;</a></p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-box"><?= $error ?></div>
    <?php endif; ?>

    <?php if (!$message): ?>
    <form method="POST">
        <div>
            <label for="username">Admin Username</label>
            <input type="text" id="username" name="username" value="admin" required autofocus>
        </div>
        <div>
            <label for="password">Strong Password (min 8 chars)</label>
            <input type="password" id="password" name="password" placeholder="••••••••••••" minlength="8" required>
        </div>
        <button type="submit">Create Administrator</button>
    </form>
    <?php endif; ?>

    <div class="delete-notice">
        Delete <code>database/create_admin.php</code> after use to secure the installation.
    </div>
</div>

</body>
</html>
