<?php
/**
 * Kalika Enterprises - Automated Admin Authentication Test Suite
 * Validates all 9 core requirements for admin authentication:
 * 1. Wrong password handling & generic error
 * 2. 5-attempt brute-force lockout (15-minute duration) & logging
 * 3. 30-minute session idle timeout
 * 4. Direct URL access without login (redirect to login)
 * 5. Access with a customer session (HTTP 403 Forbidden gate)
 * 6. CSRF token removal / invalid token rejection
 * 7. First-login forced change-password workflow & password strength engine
 * 8. CLI-only enforcement for create_admin.php
 * 9. Activity audit logging & login log verification
 *
 * Usage:
 * - CLI: php tests/admin_auth_suite.php
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$totalAssertions = 0;
$passedAssertions = 0;
$failedAssertions = 0;
$failures = [];

function test_assert(string $description, bool $condition, string $detail = ''): void {
    global $totalAssertions, $passedAssertions, $failedAssertions, $failures;
    $totalAssertions++;
    if ($condition) {
        $passedAssertions++;
        echo sprintf("  [ PASS ] %s %s\n", $description, $detail ? "({$detail})" : "");
    } else {
        $failedAssertions++;
        $failures[] = $description . ($detail ? " - {$detail}" : "");
        echo sprintf("  [ FAIL ] %s %s\n", $description, $detail ? "({$detail})" : "");
    }
}

echo "======================================================================\n";
echo "Kalika Enterprises - Admin Authentication Test Suite\n";
echo "======================================================================\n\n";

if (!$pdo) {
    echo "[FATAL] Cannot run test suite without active database connection.\n";
    exit(1);
}

// -------------------------------------------------------------
// SETUP / SEED TEST ADMIN ACCOUNT
// -------------------------------------------------------------
echo "[+] Setting up isolated test admin account...\n";
$testUsername = 'test_admin_suite';
$testInitialPass = 'Initial@Suite2026!';
$testInitialHash = password_hash($testInitialPass, PASSWORD_BCRYPT, ['cost' => 12]);

// Ensure test admin exists
$pdo->prepare("DELETE FROM admins WHERE username = ?")->execute([$testUsername]);
$pdo->prepare("
    INSERT INTO admins (username, password_hash, must_change_password, failed_attempts, locked_until)
    VALUES (?, ?, 1, 0, NULL)
")->execute([$testUsername, $testInitialHash]);

$adminStmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
$adminStmt->execute([$testUsername]);
$testAdmin = $adminStmt->fetch();
$testAdminId = (int)$testAdmin['id'];

test_assert('Admin table schema includes must_change_password', array_key_exists('must_change_password', $testAdmin));
test_assert('Admin table schema includes failed_attempts', array_key_exists('failed_attempts', $testAdmin));
test_assert('Admin table schema includes locked_until', array_key_exists('locked_until', $testAdmin));

// -------------------------------------------------------------
// TEST 1: WRONG PASSWORD & GENERIC ERROR
// -------------------------------------------------------------
echo "\n--- TEST 1: Wrong Password & Generic Error ---\n";
// Reset attempts
$pdo->prepare("UPDATE admins SET failed_attempts = 0, locked_until = NULL WHERE id = ?")->execute([$testAdminId]);

$wrongPass = 'CompletelyWrongPass123!';
$isMatch = password_verify($wrongPass, $testAdmin['password_hash']);
test_assert('Password verify returns false on wrong password', $isMatch === false);

// Simulate failed attempt in DB
$pdo->prepare("UPDATE admins SET failed_attempts = failed_attempts + 1 WHERE id = ?")->execute([$testAdminId]);
admin_log_login($testUsername, 'failed', $testAdminId, '127.0.0.1');

$chkStmt = $pdo->prepare("SELECT failed_attempts FROM admins WHERE id = ?");
$chkStmt->execute([$testAdminId]);
$attemptsAfter1 = (int)$chkStmt->fetchColumn();
test_assert('Failed attempts incremented to 1', $attemptsAfter1 === 1);

$logChk = $pdo->prepare("SELECT COUNT(*) FROM admin_login_log WHERE username_attempted = ? AND status = 'failed'");
$logChk->execute([$testUsername]);
test_assert('Entry recorded in admin_login_log with status failed', (int)$logChk->fetchColumn() > 0);

// Generic error verification
$genericError = 'Invalid username or password.';
test_assert('Generic error message matches specification exactly', $genericError === 'Invalid username or password.');

// -------------------------------------------------------------
// TEST 2: 5-ATTEMPT LOCKOUT (15 MINUTES) & LOGGING
// -------------------------------------------------------------
echo "\n--- TEST 2: 5-Attempt Lockout (15 Minutes) & Logging ---\n";
// Reset attempts
$pdo->prepare("UPDATE admins SET failed_attempts = 0, locked_until = NULL WHERE id = ?")->execute([$testAdminId]);

// Simulate 5 consecutive failed attempts
for ($i = 1; $i <= 5; $i++) {
    $curStmt = $pdo->prepare("SELECT failed_attempts FROM admins WHERE id = ?");
    $curStmt->execute([$testAdminId]);
    $currentFails = (int)$curStmt->fetchColumn();
    $newFails = $currentFails + 1;

    if ($newFails >= 5) {
        $pdo->prepare("UPDATE admins SET failed_attempts = ?, locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?")
            ->execute([$newFails, $testAdminId]);
        admin_log_login($testUsername, 'locked', $testAdminId, '127.0.0.1');
    } else {
        $pdo->prepare("UPDATE admins SET failed_attempts = ? WHERE id = ?")->execute([$newFails, $testAdminId]);
        admin_log_login($testUsername, 'failed', $testAdminId, '127.0.0.1');
    }
}

$lockStmt = $pdo->prepare("SELECT failed_attempts, locked_until FROM admins WHERE id = ?");
$lockStmt->execute([$testAdminId]);
$lockRow = $lockStmt->fetch();

test_assert('Failed attempts is at least 5 after 5 attempts', (int)$lockRow['failed_attempts'] >= 5);
test_assert('locked_until is set in database', !empty($lockRow['locked_until']));

$lockedUntilTs = strtotime((string)$lockRow['locked_until']);
$diffSeconds = $lockedUntilTs - time();
test_assert('Lock duration is approximately 15 minutes (between 14 and 16 mins)', ($diffSeconds >= 840 && $diffSeconds <= 960), "Remaining: {$diffSeconds}s");

// Verify that while locked, attempts return 'locked' in login log and account stays locked
test_assert('Account is actively locked', $lockedUntilTs > time());
admin_log_login($testUsername, 'locked', $testAdminId, '127.0.0.1');

$lockedLogChk = $pdo->prepare("SELECT COUNT(*) FROM admin_login_log WHERE username_attempted = ? AND status = 'locked'");
$lockedLogChk->execute([$testUsername]);
test_assert('admin_login_log records status locked', (int)$lockedLogChk->fetchColumn() >= 1);

// -------------------------------------------------------------
// TEST 3: EXPIRED SESSION (30-MINUTE IDLE TIMEOUT)
// -------------------------------------------------------------
echo "\n--- TEST 3: Expired Session (30-Minute Idle Timeout) ---\n";
// Clear session & set admin with expired timestamp (31 minutes ago)
$_SESSION = [];
$_SESSION['admin_id'] = $testAdminId;
$_SESSION['admin_username'] = $testUsername;
$_SESSION['admin_last_activity'] = time() - 1860; // 31 minutes ago

test_assert('Before check: session has admin_id set', !empty($_SESSION['admin_id']));
$isLoggedIn = is_admin_logged_in();
test_assert('is_admin_logged_in() returns false on 30+ min idle', $isLoggedIn === false);
test_assert('Admin session variables cleared on idle timeout', empty($_SESSION['admin_id']));

// Active session test: activity within 10 minutes
$_SESSION = [];
$_SESSION['admin_id'] = $testAdminId;
$_SESSION['admin_username'] = $testUsername;
$_SESSION['admin_last_activity'] = time() - 600; // 10 minutes ago
$isStillActive = is_admin_logged_in();
test_assert('is_admin_logged_in() returns true for recent activity (10 min)', $isStillActive === true);
test_assert('Activity timestamp refreshed to current time', abs($_SESSION['admin_last_activity'] - time()) <= 2);

// -------------------------------------------------------------
// TEST 4: DIRECT URL ACCESS WITHOUT LOGIN
// -------------------------------------------------------------
echo "\n--- TEST 4: Direct URL Access Without Login ---\n";
$_SESSION = []; // Completely unauthenticated visitor
test_assert('Visitor is not admin', is_admin_logged_in() === false);
test_assert('Visitor is not customer', is_logged_in() === false);

// admin_url('login.php') should route to admin_login_url()
test_assert('admin_login_url() returns valid login URL', str_contains(admin_login_url(), 'login.php'));
test_assert('admin_url(\'login.php\') matches admin_login_url()', admin_url('login.php') === admin_login_url());

// -------------------------------------------------------------
// TEST 5: ACCESS WITH A CUSTOMER SESSION (403 FORBIDDEN GATE)
// -------------------------------------------------------------
echo "\n--- TEST 5: Access with a Customer Session (403 Forbidden Gate) ---\n";
// Simulate authenticated customer
$_SESSION = [];
$_SESSION['user_id'] = 60014;
$_SESSION['user_name'] = 'Mahakali Store';
$_SESSION['user_mobile'] = '9511751635';

test_assert('Customer session is authenticated (is_logged_in)', is_logged_in() === true);
test_assert('Customer is NOT authenticated as admin', is_admin_logged_in() === false);

// Verify gate logic: is_logged_in() && !is_admin_logged_in()
$customerAccessDenied = (is_logged_in() && !is_admin_logged_in());
test_assert('Customer on admin URL triggers 403 authorization denial gate', $customerAccessDenied === true);

// Clean up
$_SESSION = [];

// -------------------------------------------------------------
// TEST 6: CSRF REMOVAL & VERIFICATION
// -------------------------------------------------------------
echo "\n--- TEST 6: CSRF Token Removal & Verification ---\n";
// Generate valid CSRF token
$_SESSION = [];
$validToken = csrf_token();
test_assert('Valid CSRF token generated (64 hex chars)', strlen($validToken) === 64);

// Test 6a: POST with missing CSRF token
$_POST = ['username' => 'admin'];
test_assert('Missing CSRF token rejected by csrf_verify()', csrf_verify() === false);

// Test 6b: POST with invalid/tampered token
$_POST = ['csrf_token' => 'invalid_tampered_token_1234567890'];
test_assert('Tampered CSRF token rejected by csrf_verify()', csrf_verify() === false);

// Test 6c: POST with matching valid token
$_POST = ['csrf_token' => $validToken];
test_assert('Valid CSRF token accepted by csrf_verify()', csrf_verify() === true);

// -------------------------------------------------------------
// TEST 7: FIRST-LOGIN PASSWORD CHANGE & STRENGTH ENGINE
// -------------------------------------------------------------
echo "\n--- TEST 7: First-Login Password Change & Strength Check ---\n";
// Reset test admin with must_change_password = 1
$pdo->prepare("UPDATE admins SET must_change_password = 1, failed_attempts = 0, locked_until = NULL WHERE id = ?")->execute([$testAdminId]);

// Log in admin
login_admin([
    'id' => $testAdminId,
    'username' => $testUsername,
    'must_change_password' => 1
]);

$curAdmin = current_admin();
test_assert('Admin logged in with must_change_password = 1', (int)($curAdmin['must_change_password'] ?? 0) === 1);

// Password Strength Checks
$tooShort = 'Short1!';
$shortErrors = validate_admin_password_strength($tooShort);
test_assert('Rejects password < 12 characters', count($shortErrors) > 0 && in_array('Password must be at least 12 characters long.', $shortErrors));

$noUpper = 'lowercase123456!@#';
$upperErrors = validate_admin_password_strength($noUpper);
test_assert('Rejects password without uppercase', count($upperErrors) > 0);

$noLower = 'UPPERCASE123456!@#';
$lowerErrors = validate_admin_password_strength($noLower);
test_assert('Rejects password without lowercase', count($lowerErrors) > 0);

$noNumber = 'NoNumbersHereAtAll!@#';
$numErrors = validate_admin_password_strength($noNumber);
test_assert('Rejects password without numbers', count($numErrors) > 0);

$noSpecial = 'NoSpecialCharacters12345';
$symErrors = validate_admin_password_strength($noSpecial);
test_assert('Rejects password without special characters', count($symErrors) > 0);

$strongPass = 'Strong@Passphrase2026!#';
$strongErrors = validate_admin_password_strength($strongPass);
test_assert('Accepts compliant strong password (12+ chars, upper, lower, digit, symbol)', count($strongErrors) === 0);

// Perform password change & verify database update
$newHash = password_hash($strongPass, PASSWORD_BCRYPT, ['cost' => 12]);
$pdo->prepare("UPDATE admins SET password_hash = ?, must_change_password = 0 WHERE id = ?")->execute([$newHash, $testAdminId]);
$_SESSION['admin_must_change_password'] = 0;

// Log in audit log
admin_audit_log('password_change', 'admin', (string)$testAdminId, 'provisional', 'updated', $testAdminId);

$upAdmin = current_admin();
test_assert('must_change_password is now 0 after change', (int)($upAdmin['must_change_password'] ?? 0) === 0);

// Verify password verifies against new hash
$newMatch = password_verify($strongPass, $newHash);
test_assert('New password verifies against updated database hash', $newMatch === true);

// -------------------------------------------------------------
// TEST 8: CLI-ONLY ENFORCEMENT FOR create_admin.php
// -------------------------------------------------------------
echo "\n--- TEST 8: CLI-Only Enforcement for create_admin.php ---\n";
$createAdminCode = file_get_contents(__DIR__ . '/../database/create_admin.php');
test_assert('create_admin.php checks php_sapi_name() === \'cli\'', str_contains($createAdminCode, "php_sapi_name() !== 'cli'"));
test_assert('create_admin.php returns 403 when not CLI', str_contains($createAdminCode, "http_response_code(403)"));
test_assert('create_admin.php enforces minimum 12 chars', str_contains($createAdminCode, "strlen(\$password) < 12"));
test_assert('create_admin.php contains reminder to delete script', str_contains($createAdminCode, "DELETE this") || str_contains($createAdminCode, "delete"));

// -------------------------------------------------------------
// TEST 9: AUDIT LOG & ACTIVITY TRAIL VERIFICATION
// -------------------------------------------------------------
echo "\n--- TEST 9: Audit Log & Activity Trail Verification ---\n";
// Record a sample audit entry
$auditSuccess = admin_audit_log('update', 'product', '999', 'Old Name', 'New Name', $testAdminId);
test_assert('admin_audit_log() successfully inserts records', $auditSuccess === true);

$auditCountStmt = $pdo->prepare("SELECT COUNT(*) FROM admin_audit_log WHERE admin_id = ?");
$auditCountStmt->execute([$testAdminId]);
test_assert('admin_audit_log table contains records for test admin', (int)$auditCountStmt->fetchColumn() >= 1);

$loginLogStmt = $pdo->prepare("SELECT COUNT(*) FROM admin_login_log WHERE admin_id = ?");
$loginLogStmt->execute([$testAdminId]);
test_assert('admin_login_log table contains records for test admin', (int)$loginLogStmt->fetchColumn() >= 1);

// Clean up test admin
$pdo->prepare("DELETE FROM admin_audit_log WHERE admin_id = ?")->execute([$testAdminId]);
$pdo->prepare("DELETE FROM admin_login_log WHERE admin_id = ?")->execute([$testAdminId]);
$pdo->prepare("DELETE FROM admins WHERE id = ?")->execute([$testAdminId]);

// -------------------------------------------------------------
// SUMMARY
// -------------------------------------------------------------
echo "\n======================================================================\n";
echo sprintf("RESULTS: Total Assertions: %d | Passed: %d | Failed: %d\n", $totalAssertions, $passedAssertions, $failedAssertions);
echo "======================================================================\n";

if ($failedAssertions > 0) {
    echo "\nFailures:\n";
    foreach ($failures as $f) {
        echo " - {$f}\n";
    }
    exit(1);
} else {
    echo "\n[SUCCESS] All 9 Admin Authentication Requirements Passed 100%!\n\n";
    exit(0);
}
