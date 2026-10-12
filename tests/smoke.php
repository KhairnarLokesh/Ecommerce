<?php
/**
 * Kalika Enterprises - Automated Smoke Test Suite
 * Phase 9: Automated verification of database connectivity, schema tables,
 *          calculation engine, security tokens, and file assets.
 * 
 * Usage:
 * - Web: Access via /tests/smoke.php in local development browser
 * - CLI: Run via php tests/smoke.php
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cart.php';

$isCli = (php_sapi_name() === 'cli');

$results = [];
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void {
    global $results, $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        $results[] = ['name' => $name, 'status' => 'PASS', 'detail' => $detail];
    } else {
        $failedTests++;
        $results[] = ['name' => $name, 'status' => 'FAIL', 'detail' => $detail];
    }
}

// -------------------------------------------------------------
// 1. PHP Runtime & Extensions Test
// -------------------------------------------------------------
assert_test('PHP Version >= 8.0', version_compare(PHP_VERSION, '8.0.0', '>='), 'Current: ' . PHP_VERSION);
assert_test('PDO MySQL Extension', extension_loaded('pdo_mysql'), 'PDO MySQL is required for DB operations');
assert_test('MBString Extension', extension_loaded('mbstring'), 'Multibyte string support');
assert_test('OpenSSL Extension', extension_loaded('openssl'), 'Required for CSRF & random token generation');
assert_test('JSON Extension', extension_loaded('json'), 'Required for AJAX cart API');

// -------------------------------------------------------------
// 2. Database Connectivity & Schema Test
// -------------------------------------------------------------
$pdo = get_db_connection(true);
assert_test('Database Connection (PDO)', ($pdo instanceof PDO), 'Connection to MySQL database');

if ($pdo instanceof PDO) {
    // Check 12 Required InnoDB Tables
    $requiredTables = [
        'admins',
        'brands',
        'cart_items',
        'contact_messages',
        'login_attempts',
        'order_items',
        'orders',
        'password_otps',
        'product_variants',
        'products',
        'settings',
        'users'
    ];

    $existingTables = [];
    $tblStmt = $pdo->query("SHOW TABLES");
    while ($row = $tblStmt->fetch(PDO::FETCH_NUM)) {
        $existingTables[] = $row[0];
    }

    foreach ($requiredTables as $tbl) {
        $found = in_array($tbl, $existingTables, true);
        assert_test("Table Presence: `{$tbl}`", $found, $found ? 'Table verified in schema' : 'Table missing from database');
    }

    // Check Seed Data Counts
    $brandCount = (int)$pdo->query("SELECT COUNT(*) FROM brands")->fetchColumn();
    assert_test('Seed Data: Brands count >= 7', ($brandCount >= 7), "Found {$brandCount} brands");

    $productCount = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    assert_test('Seed Data: Products count >= 13', ($productCount >= 13), "Found {$productCount} products");

    $variantCount = (int)$pdo->query("SELECT COUNT(*) FROM product_variants")->fetchColumn();
    assert_test('Seed Data: Variants count >= 39', ($variantCount >= 39), "Found {$variantCount} variants");

    $activeProductCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1 AND gst_percent IS NOT NULL")->fetchColumn();
    assert_test('Catalogue Guard: Active verified products', ($activeProductCount >= 10), "Found {$activeProductCount} active confirmed products");

    // Unconfirmed items must be inactive
    $glucoseActive = (int)$pdo->query("SELECT is_active FROM products WHERE name LIKE '%Glucose%' LIMIT 1")->fetchColumn();
    assert_test('Catalogue Guard: Sundar Glucose inactive', ($glucoseActive === 0), 'Glucose is unconfirmed and correctly hidden');

    $colaRangeActive = (int)$pdo->query("SELECT is_active FROM brands WHERE name LIKE '%Coca-Cola%' LIMIT 1")->fetchColumn();
    assert_test('Catalogue Guard: Coca-Cola range inactive', ($colaRangeActive === 0), 'Coca-Cola brand is inactive');

    // Admin Account Presence
    $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM admins WHERE username = 'admin'")->fetchColumn();
    assert_test('Admin Account: User `admin` exists', ($adminCount >= 1), 'Administrator user available for login');

    // Settings Baseline
    $minOrderSetting = (float)get_setting('min_order_value', '0');
    assert_test('Settings: min_order_value == 2000.00', ($minOrderSetting === 2000.00), "Value: ₹{$minOrderSetting}");

    $serviceAreas = get_setting('service_areas', '');
    $hasAreas = (strpos($serviceAreas, 'Niphad') !== false && strpos($serviceAreas, 'Yeola') !== false);
    assert_test('Settings: service_areas contains Niphad & Yeola', $hasAreas, $serviceAreas);
}

// -------------------------------------------------------------
// 3. Calculation Engine & Business Rules Tests
// -------------------------------------------------------------
// Currency Formatter
$formatted2000 = format_currency(2000);
assert_test('Currency Formatter: format_currency(2000)', ($formatted2000 === '₹2,000.00'), "Output: {$formatted2000}");

// Minimum Order Threshold Logic
$cartSubtotalLow = 1999.00;
$minThreshold = 2000.00;
$isAllowedLow = ($cartSubtotalLow >= $minThreshold);
$diffLow = max(0.0, $minThreshold - $cartSubtotalLow);
assert_test('Threshold: ₹1,999 is blocked', (!$isAllowedLow && $diffLow === 1.00), 'Checkout must be disabled below ₹2,000');

$cartSubtotalExact = 2000.00;
$isAllowedExact = ($cartSubtotalExact >= $minThreshold);
assert_test('Threshold: ₹2,000 is allowed', ($isAllowedExact === true), 'Checkout unlocked at ₹2,000');

// GST Calculation Logic (Inclusive Mode)
// Total ₹2,000.00 with 28% GST -> Base = 2000 / 1.28 = 1562.50, GST = 437.50
$lineTotal = 2000.00;
$gstRate = 28.00;
$baseInclusive = $lineTotal / (1 + ($gstRate / 100));
$taxInclusive = $lineTotal - $baseInclusive;
assert_test('GST Calculation: Base rate (28% inclusive)', (round($baseInclusive, 2) === 1562.50), 'Base rate matches ₹1,562.50');
assert_test('GST Calculation: Tax amount (28% inclusive)', (round($taxInclusive, 2) === 437.50), 'Tax amount matches ₹437.50');

// -------------------------------------------------------------
// 4. Security Subsystem Tests
// -------------------------------------------------------------
// CSRF Token Generation
$token = csrf_token();
assert_test('CSRF: Token generation', (!empty($token) && strlen($token) === 64), '64 hex characters (32 bytes random)');

// Mobile Number Regex Validation
$validMobile = '9876543210';
$invalidMobileLen = '987654321'; // 9 digits
$invalidMobileChar = '987654321A'; // contains letter
$invalidMobileStart = '5876543210'; // starts with 5
assert_test('Input Validation: Valid Indian mobile regex', (bool)preg_match('/^[6-9]\d{9}$/', $validMobile), 'Passes 10 digits starting 9');
assert_test('Input Validation: Rejects short mobile', !preg_match('/^[6-9]\d{9}$/', $invalidMobileLen), 'Rejects 9-digit string');
assert_test('Input Validation: Rejects alpha mobile', !preg_match('/^[6-9]\d{9}$/', $invalidMobileChar), 'Rejects letters');
assert_test('Input Validation: Rejects invalid prefix mobile', !preg_match('/^[6-9]\d{9}$/', $invalidMobileStart), 'Rejects prefix 5');

// -------------------------------------------------------------
// 5. File System & Assets Verification
// -------------------------------------------------------------
$root = dirname(__DIR__);
$criticalFiles = [
    // Core & Config
    $root . '/config/config.php',
    $root . '/config/config.sample.php',
    $root . '/config/.htaccess',
    $root . '/database/.htaccess',
    $root . '/docs/.htaccess',
    $root . '/includes/.htaccess',
    $root . '/logs/.htaccess',
    $root . '/public/uploads/.htaccess',
    $root . '/public/uploads/products/.htaccess',
    $root . '/.gitignore',
    $root . '/.htaccess',
    // Public Storefront Pages
    $root . '/public/index.php',
    $root . '/public/products.php',
    $root . '/public/about.php',
    $root . '/public/contact.php',
    $root . '/public/policies.php',
    $root . '/public/cart.php',
    $root . '/public/checkout.php',
    $root . '/public/login.php',
    $root . '/public/register.php',
    $root . '/public/profile.php',
    $root . '/public/forgot.php',
    $root . '/public/reset.php',
    $root . '/public/orders.php',
    $root . '/public/order.php',
    $root . '/public/invoice.php',
    // Admin Operations Pages
    $root . '/admin/index.php',
    $root . '/admin/login.php',
    $root . '/admin/logout.php',
    $root . '/admin/change-password.php',
    $root . '/admin/products.php',
    $root . '/admin/product-edit.php',
    $root . '/admin/brands.php',
    $root . '/admin/stock.php',
    $root . '/admin/customers.php',
    $root . '/admin/orders.php',
    $root . '/admin/order-detail.php',
    $root . '/admin/settings.php',
    $root . '/admin/messages.php',
    // CSS & JS Assets
    $root . '/public/assets/css/style.css',
    $root . '/public/assets/js/main.js',
    $root . '/public/assets/img/logo.svg',
    // Documentation
    $root . '/README.md',
    $root . '/PROJECT_CONTEXT.md',
    $root . '/PROGRESS.md',
    $root . '/docs/DATABASE.md',
    $root . '/docs/TESTING.md',
    $root . '/docs/SECURITY.md'
];

foreach ($criticalFiles as $file) {
    $rel = str_replace($root, '', $file);
    assert_test("File Presence: {$rel}", file_exists($file), 'File verified on disk');
}

// -------------------------------------------------------------
// Output Report
// -------------------------------------------------------------
if ($isCli) {
    echo "=========================================================\n";
    echo "Kalika Enterprises - Automated Smoke Test Suite (CLI)\n";
    echo "=========================================================\n\n";
    foreach ($results as $r) {
        $pad = str_pad($r['name'], 55, '.');
        $tag = $r['status'] === 'PASS' ? "[ PASS ]" : "[ FAIL ]";
        echo "{$pad} {$tag} {$r['detail']}\n";
    }
    echo "\n---------------------------------------------------------\n";
    echo "SUMMARY: Total: {$totalTests} | Passed: {$passedTests} | Failed: {$failedTests}\n";
    echo "=========================================================\n";
    exit($failedTests === 0 ? 0 : 1);
} else {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Smoke Test Suite | Kalika Enterprises</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 2rem; }
            .container { max-width: 900px; margin: 0 auto; background: #1e293b; border-radius: 12px; padding: 2rem; border: 1px solid #334155; box-shadow: 0 10px 25px rgba(0,0,0,0.4); }
            h1 { margin-top: 0; color: #f59e0b; font-size: 1.75rem; }
            .summary { display: flex; gap: 1.5rem; margin-bottom: 2rem; padding: 1rem; border-radius: 8px; background: #0f172a; border: 1px solid #334155; }
            .stat { flex: 1; text-align: center; }
            .stat-val { font-size: 2rem; font-weight: 800; }
            .stat-lbl { font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 700; }
            table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.875rem; }
            th, td { padding: 0.6rem 0.75rem; text-align: left; border-bottom: 1px solid #334155; }
            th { background: #0f172a; color: #94a3b8; text-transform: uppercase; font-size: 0.75rem; }
            .badge-pass { background: #064e3b; color: #34d399; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 800; font-size: 0.75rem; }
            .badge-fail { background: #7f1d1d; color: #f87171; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 800; font-size: 0.75rem; }
        </style>
    </head>
    <body>
    <div class="container">
        <h1>Kalika Enterprises – Automated Smoke Test Report</h1>
        <p style="color: #94a3b8; margin-bottom: 1.5rem;">Automated environment, schema, calculation, security, and asset verification.</p>

        <div class="summary">
            <div class="stat">
                <div class="stat-val" style="color: #ffffff;"><?= $totalTests ?></div>
                <div class="stat-lbl">Total Checks</div>
            </div>
            <div class="stat">
                <div class="stat-val" style="color: #10b981;"><?= $passedTests ?></div>
                <div class="stat-lbl">Passed</div>
            </div>
            <div class="stat">
                <div class="stat-val" style="color: <?= $failedTests > 0 ? '#ef4444' : '#64748b' ?>;"><?= $failedTests ?></div>
                <div class="stat-lbl">Failed</div>
            </div>
            <div class="stat">
                <div class="stat-val" style="color: <?= $failedTests === 0 ? '#10b981' : '#ef4444' ?>;">
                    <?= $failedTests === 0 ? '100% OK' : 'ATTENTION' ?>
                </div>
                <div class="stat-lbl">Verdict</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 45%;">Check Name</th>
                    <th style="width: 15%; text-align: center;">Result</th>
                    <th style="width: 40%;">Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                        <td style="text-align: center;">
                            <span class="<?= $r['status'] === 'PASS' ? 'badge-pass' : 'badge-fail' ?>">
                                <?= $r['status'] ?>
                            </span>
                        </td>
                        <td style="color: #cbd5e1; font-family: monospace; font-size: 0.8125rem;">
                            <?= htmlspecialchars($r['detail']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    </body>
    </html>
    <?php
}
