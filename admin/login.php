<?php
/**
 * Kalika Enterprises - Admin Login Portal
 * Phase 7: Rate-limited authentication using login_attempts, bcrypt verification,
 *          isolated admin session namespace, and brute-force lockout.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already authenticated as administrator
if (is_admin_logged_in()) {
    redirect(admin_url('index.php'));
}

$error = '';
$usernameVal = '';
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Security token invalid or expired. Please refresh the page.';
    } else {
        $usernameVal = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($usernameVal) || empty($password)) {
            $error = 'Please enter both your admin username and password.';
        } elseif ($pdo) {
            try {
                // Rate Limiting Check: 5 attempts per 15 minutes by username or IP
                $throttleStmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM login_attempts 
                    WHERE (identifier = ? OR ip = ?)
                      AND attempted_at > (NOW() - INTERVAL 15 MINUTE)
                ");
                $throttleStmt->execute(['admin:' . $usernameVal, $clientIp]);
                $recentAttempts = (int)$throttleStmt->fetchColumn();

                if ($recentAttempts >= 5) {
                    $error = 'Too many failed administrator login attempts. Access is temporarily locked out for 15 minutes.';
                } else {
                    // Check admin record
                    $adminStmt = $pdo->prepare("
                        SELECT id, username, password_hash
                        FROM admins
                        WHERE username = ?
                        LIMIT 1
                    ");
                    $adminStmt->execute([$usernameVal]);
                    $admin = $adminStmt->fetch();

                    if ($admin && password_verify($password, $admin['password_hash'])) {
                        // Clear failed attempts for this identifier & IP
                        $clearStmt = $pdo->prepare("
                            DELETE FROM login_attempts 
                            WHERE identifier = ? OR ip = ?
                        ");
                        $clearStmt->execute(['admin:' . $usernameVal, $clientIp]);

                        // Update last login timestamp
                        $upStmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                        $upStmt->execute([$admin['id']]);

                        // Authenticate admin session
                        login_admin([
                            'id'       => (int)$admin['id'],
                            'username' => $admin['username'],
                        ]);

                        set_flash('success', 'Authenticated successfully as Administrator.');
                        redirect(admin_url('index.php'));
                    } else {
                        // Log failed attempt
                        $logStmt = $pdo->prepare("
                            INSERT INTO login_attempts (identifier, ip)
                            VALUES (?, ?)
                        ");
                        $logStmt->execute(['admin:' . $usernameVal, $clientIp]);

                        $remainingAttempts = 4 - $recentAttempts;
                        if ($remainingAttempts > 0) {
                            $error = 'Invalid admin credentials. (' . $remainingAttempts . ' attempts remaining before 15-minute lockout)';
                        } else {
                            $error = 'Invalid admin credentials. Maximum attempts exceeded. Access locked for 15 minutes.';
                        }
                    }
                }
            } catch (PDOException $e) {
                error_log("Admin login query failed: " . $e->getMessage());
                $error = 'Database connection error. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal Login | <?= e(get_setting('business_name', 'Kalika Enterprises')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= public_url('assets/css/style.css') ?>">
</head>
<body style="background: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem;">

<div style="width: 100%; max-width: 420px;">
    <?= render_flash_messages() ?>

    <div class="card" style="padding: 2.5rem; background: #ffffff; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg);">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="width: 52px; height: 52px; border-radius: 12px; background: #0f172a; color: #f59e0b; display: inline-flex; align-items: center; justify-content: center; font-size: 1.6rem; font-weight: 800; margin-bottom: 1rem; border: 2px solid #334155;">
                KE
            </div>
            <h1 style="font-size: 1.5rem; margin-bottom: 0.25rem;">Admin Operations</h1>
            <p style="font-size: 0.8125rem; color: #64748b; margin-bottom: 0;">Kalika Enterprises Wholesale Distribution</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error" role="alert" style="margin-bottom: 1.5rem;">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form action="<?= admin_url('login.php') ?>" method="POST" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="admin_user">Staff Username *</label>
                <input type="text" id="admin_user" name="username" class="form-control" placeholder="admin" value="<?= e($usernameVal) ?>" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="admin_pass">Staff Password *</label>
                <input type="password" id="admin_pass" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-secondary btn-block" style="margin-top: 1rem; background: #0f172a; color: #ffffff; font-weight: 700; padding: 0.85rem; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <span>Authenticate &amp; Access Dashboard</span>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
        </form>

        <div style="text-align: center; margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid var(--border-light); font-size: 0.8125rem;">
            <a href="<?= public_url('index.php') ?>" style="color: #64748b; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
                <span>Return to Customer Storefront</span>
            </a>
        </div>
    </div>
</div>

</body>
</html>
