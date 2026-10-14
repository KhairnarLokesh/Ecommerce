<?php
/**
 * Kalika Enterprises - Initial Admin User Provisioning Script
 * 
 * SECURITY POLICY:
 * This script is strictly command-line only (CLI). It refuses to execute over HTTP/web.
 * Run once to create the primary store owner admin account.
 * Immediately delete this file after use!
 */

declare(strict_types=1);

// Strictly refuse execution over HTTP / Web Server
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "403 Forbidden: Access Denied.\n";
    echo "This administrative provisioning utility can only be executed via the Command Line Interface (CLI).\n";
    echo "Execution over HTTP / Web Server is permanently disabled for system security.\n";
    exit(1);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = get_db_connection(true);
if (!$pdo) {
    echo "\n[ERROR] Database connection failed! Check your .env or config/config.php settings.\n";
    exit(1);
}

echo "======================================================================\n";
echo "Kalika Enterprises - Admin Account Provisioning (CLI Only)\n";
echo "======================================================================\n\n";

// 1. Read username (via argument or interactive prompt)
if (isset($argv[1]) && trim($argv[1]) !== '') {
    $username = trim($argv[1]);
} else {
    echo "Enter Admin Username [admin]: ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    $username = trim((string)$line);
    if ($username === '') {
        $username = 'admin';
    }
}

// 2. Read password (via argument or interactive prompt)
if (isset($argv[2]) && trim($argv[2]) !== '') {
    $password = trim($argv[2]);
} else {
    echo "Enter Strong Admin Password (min 12 chars): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    $password = trim((string)$line);
}

// 3. Validation: Minimum 12 characters
if (strlen($password) < 12) {
    echo "\n[ERROR] Password must be at least 12 characters long! (Provided length: " . strlen($password) . ")\n";
    echo "Please choose a strong passphrase and run this script again.\n\n";
    exit(1);
}

// 4. Hash password with Bcrypt / Default
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

try {
    // 5. Store record with must_change_password = 1, failed_attempts = 0, locked_until = NULL
    $stmt = $pdo->prepare("
        INSERT INTO `admins` (`username`, `password_hash`, `must_change_password`, `failed_attempts`, `locked_until`)
        VALUES (?, ?, 1, 0, NULL)
        ON DUPLICATE KEY UPDATE 
            `password_hash` = VALUES(`password_hash`),
            `must_change_password` = 1,
            `failed_attempts` = 0,
            `locked_until` = NULL
    ");
    $stmt->execute([$username, $hash]);

    echo "\n======================================================================\n";
    echo "[SUCCESS] Administrator account '{$username}' successfully provisioned!\n";
    echo "----------------------------------------------------------------------\n";
    echo "  Username:               {$username}\n";
    echo "  Must Change Password:   1 (Forced change on first login enabled)\n";
    echo "  Failed Attempts:        0 (Unlocked)\n";
    echo "  Password Hash Engine:   Bcrypt (cost 12)\n";
    echo "======================================================================\n\n";
    echo "[CRITICAL SECURITY REMINDER]\n";
    echo "For production safety, DELETE this provisioning script now:\n\n";
    echo "  Linux / cPanel:  rm " . __FILE__ . "\n";
    echo "  Windows:         del \"" . str_replace('/', '\\', __FILE__) . "\"\n\n";
    exit(0);
} catch (PDOException $e) {
    echo "\n[ERROR] Database query failed: " . $e->getMessage() . "\n\n";
    exit(1);
}
