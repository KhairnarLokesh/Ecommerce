<?php
/**
 * Kalika Enterprises - Admin Database Viewer
 * Phase 7/10: Visual database explorer for inspecting MySQL tables and records.
 */

declare(strict_types=1);

$adminPage = 'database';
$adminTitle = 'MySQL Database Explorer';

require_once __DIR__ . '/includes/header.php';

$allowedTables = [
    'brands'           => 'FMCG Brands',
    'products'         => 'Wholesale Products',
    'product_variants' => 'SKUs & Price Variants',
    'settings'         => 'System & Enterprise Settings',
    'admins'           => 'Staff Administrators',
    'users'            => 'Registered Retailers',
    'cart_items'       => 'Persistent Shopping Cart',
    'orders'           => 'Order Pipeline',
    'order_items'      => 'Order Line Snapshots',
    'password_otps'    => 'Demo OTP History',
    'contact_messages' => 'Contact Inquiries',
    'login_attempts'   => 'Security & Rate Limits',
];

$activeTable = $_GET['table'] ?? 'products';
if (!array_key_exists($activeTable, $allowedTables)) {
    $activeTable = 'products';
}

// Fetch row counts for all tables
$counts = [];
foreach (array_keys($allowedTables) as $tbl) {
    try {
        $counts[$tbl] = (int)$pdo->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
    } catch (PDOException $e) {
        $counts[$tbl] = 0;
    }
}

// Fetch schema description and rows for active table
$columns = [];
$rows = [];
$totalRows = $counts[$activeTable] ?? 0;
$serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

try {
    $colStmt = $pdo->query("DESCRIBE `{$activeTable}`");
    $columns = $colStmt->fetchAll(PDO::FETCH_ASSOC);

    $rowStmt = $pdo->query("SELECT * FROM `{$activeTable}` ORDER BY 1 DESC LIMIT 100");
    $rows = $rowStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = $e->getMessage();
}
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin-bottom: 0.25rem;">
                MySQL Database Explorer
            </h1>
            <p style="color: #64748b; font-size: 0.875rem; margin: 0;">
                Connected to: <strong style="color: #0f172a;"><?= e(DB_NAME) ?></strong> on <strong style="color: #0f172a;"><?= e(DB_HOST) ?>:<?= e(DB_PORT) ?></strong> 
                (MySQL <?= e($serverVersion) ?>)
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <span class="badge" style="background: #ecfdf5; color: #047857; font-weight: 700; padding: 0.4rem 0.8rem; border-radius: 999px; border: 1px solid #a7f3d0;">
                ● MySQL Active
            </span>
            <a href="<?= admin_url('stock.php') ?>" class="btn btn-sm btn-outline">
                Edit Stock
            </a>
            <a href="<?= admin_url('products.php') ?>" class="btn btn-sm btn-outline">
                Manage Products
            </a>
        </div>
    </div>
</div>

<!-- Table Selector Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 0.75rem; margin-bottom: 2rem;">
    <?php foreach ($allowedTables as $tbl => $label): ?>
        <?php $isActive = ($tbl === $activeTable); ?>
        <a href="?table=<?= urlencode($tbl) ?>" style="text-decoration: none; color: inherit;">
            <div style="background: <?= $isActive ? '#0f172a' : '#ffffff' ?>; color: <?= $isActive ? '#ffffff' : '#334155' ?>; border: 1px solid <?= $isActive ? '#0f172a' : '#e2e8f0' ?>; border-radius: 10px; padding: 0.85rem 1rem; transition: all 0.15s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8; margin-bottom: 0.25rem;">
                    <?= e($label) ?>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: baseline;">
                    <code style="font-size: 0.85rem; font-weight: 700; color: <?= $isActive ? '#f59e0b' : '#0284c7' ?>;">
                        <?= e($tbl) ?>
                    </code>
                    <span style="font-size: 0.8rem; font-weight: 700; background: <?= $isActive ? '#334155' : '#f1f5f9' ?>; padding: 0.1rem 0.45rem; border-radius: 999px;">
                        <?= $counts[$tbl] ?? 0 ?>
                    </span>
                </div>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<!-- Table Data View -->
<div style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <h2 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin: 0;">
                Table: <code style="color: #2563eb;"><?= e($activeTable) ?></code> 
                <span style="font-size: 0.85rem; font-weight: 500; color: #64748b; margin-left: 0.5rem;">
                    (Showing <?= count($rows) ?> of <?= $totalRows ?> rows)
                </span>
            </h2>
            <p style="color: #64748b; font-size: 0.8rem; margin: 0.25rem 0 0;">
                <?= e($allowedTables[$activeTable]) ?>
            </p>
        </div>
        <div>
            <a href="?table=<?= urlencode($activeTable) ?>" class="btn btn-sm" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1;">
                &circlearrowright; Refresh Table
            </a>
        </div>
    </div>

    <?php if (empty($rows)): ?>
        <div style="padding: 3rem; text-align: center; color: #64748b;">
            <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">Table <code><?= e($activeTable) ?></code> is currently empty.</p>
            <p style="font-size: 0.875rem;">Records will appear here as users and orders are generated.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.825rem;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left;">
                        <?php foreach ($columns as $col): ?>
                            <th style="padding: 0.75rem 1rem; color: #475569; font-weight: 700; white-space: nowrap;">
                                <?= e($col['Field']) ?>
                                <span style="font-size: 0.65rem; color: #94a3b8; display: block; font-weight: normal;">
                                    <?= e($col['Type']) ?>
                                </span>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.1s ease;">
                            <?php foreach ($columns as $col): ?>
                                <?php 
                                    $colName = $col['Field'];
                                    $val = $row[$colName] ?? null;
                                ?>
                                <td style="padding: 0.65rem 1rem; color: #1e293b; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php if ($val === null): ?>
                                        <span style="color: #94a3b8; font-style: italic;">NULL</span>
                                    <?php elseif ($colName === 'password_hash'): ?>
                                        <span style="font-family: monospace; color: #64748b; font-size: 0.75rem;">[Bcrypt Hash Masked]</span>
                                    <?php elseif ($colName === 'is_active'): ?>
                                        <span style="padding: 0.15rem 0.5rem; border-radius: 999px; font-weight: 700; font-size: 0.7rem; background: <?= $val ? '#ecfdf5' : '#fef2f2' ?>; color: <?= $val ? '#047857' : '#b91c1c' ?>;">
                                            <?= $val ? 'Active (1)' : 'Inactive (0)' ?>
                                        </span>
                                    <?php elseif ($colName === 'stock_status'): ?>
                                        <span style="padding: 0.15rem 0.5rem; border-radius: 999px; font-weight: 700; font-size: 0.7rem; background: <?= $val === 'in_stock' ? '#ecfdf5' : '#fef2f2' ?>; color: <?= $val === 'in_stock' ? '#047857' : '#b91c1c' ?>;">
                                            <?= e((string)$val) ?>
                                        </span>
                                    <?php elseif ($colName === 'price' || $colName === 'subtotal' || $colName === 'total_amount'): ?>
                                        <strong style="color: #0f172a;">₹<?= number_format((float)$val, 2) ?></strong>
                                    <?php else: ?>
                                        <?= e((string)$val) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Desktop GUI Help Callout -->
<div style="margin-top: 2rem; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 1.25rem 1.5rem; display: flex; gap: 1rem; align-items: flex-start;">
    <div style="color: #2563eb; flex-shrink: 0; padding-top: 2px;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="9" y1="18" x2="15" y2="18"></line>
            <line x1="10" y1="22" x2="14" y2="22"></line>
            <path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"></path>
        </svg>
    </div>
    <div style="font-size: 0.875rem; color: #1e3a8a;">
        <strong style="font-size: 0.95rem; display: block; margin-bottom: 0.25rem;">
            Want to use MySQL Workbench on your desktop?
        </strong>
        You have <strong>MySQL Workbench 8.0</strong> installed on your Windows PC!
        Press the <kbd style="background: #ffffff; padding: 2px 6px; border-radius: 4px; border: 1px solid #cbd5e1;">Windows Key</kbd>, type <strong>MySQL Workbench</strong>, click to open it, and connect to <code>Local instance MySQL80</code> with password <code>root</code> to edit raw SQL.
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
