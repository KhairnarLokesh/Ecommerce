<?php
/**
 * Kalika Enterprises - Admin Activity & Audit Trail
 * Read-only log of all create, update, delete actions across catalogue, pricing,
 * GST, inventory, order fulfillment, content settings, and security credentials.
 */

declare(strict_types=1);

$adminPage = 'activity_log';
$adminTitle = 'Activity & Audit Log';

require_once __DIR__ . '/includes/header.php';

// Filter inputs
$actionFilter = trim($_GET['action'] ?? '');
$entityFilter = trim($_GET['entity'] ?? '');
$searchQuery  = trim($_GET['q'] ?? '');
$dateFrom     = trim($_GET['date_from'] ?? '');
$dateTo       = trim($_GET['date_to'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 25;
$offset       = ($page - 1) * $perPage;

$whereClauses = [];
$params = [];

if (!empty($actionFilter)) {
    $whereClauses[] = "l.action = ?";
    $params[] = $actionFilter;
}

if (!empty($entityFilter)) {
    $whereClauses[] = "l.entity = ?";
    $params[] = $entityFilter;
}

if (!empty($dateFrom)) {
    $whereClauses[] = "DATE(l.created_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereClauses[] = "DATE(l.created_at) <= ?";
    $params[] = $dateTo;
}

if (!empty($searchQuery)) {
    $whereClauses[] = "(l.entity_id LIKE ? OR l.old_value LIKE ? OR l.new_value LIKE ? OR l.ip LIKE ? OR a.username LIKE ?)";
    $like = "%{$searchQuery}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(' AND ', $whereClauses) : "";

// Count total matching logs
$totalLogs = 0;
$logs = [];
$kpis = [
    'total'    => 0,
    'products' => 0,
    'orders'   => 0,
    'security' => 0,
];

if ($pdo) {
    try {
        // Summary KPIs
        $kpiStmt = $pdo->query("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN entity IN ('product', 'variant', 'price', 'gst', 'stock') THEN 1 ELSE 0 END) AS products,
                SUM(CASE WHEN entity = 'order_status' THEN 1 ELSE 0 END) AS orders,
                SUM(CASE WHEN entity IN ('admin', 'auth') OR action IN ('password_change', 'logout') THEN 1 ELSE 0 END) AS security
            FROM admin_audit_log
        ");
        if ($kpiRow = $kpiStmt->fetch()) {
            $kpis['total']    = (int)($kpiRow['total'] ?? 0);
            $kpis['products'] = (int)($kpiRow['products'] ?? 0);
            $kpis['orders']   = (int)($kpiRow['orders'] ?? 0);
            $kpis['security'] = (int)($kpiRow['security'] ?? 0);
        }

        // Total count for active filters
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM admin_audit_log l
            LEFT JOIN admins a ON l.admin_id = a.id
            {$whereSql}
        ");
        $countStmt->execute($params);
        $totalLogs = (int)$countStmt->fetchColumn();

        // Paginated rows
        $queryStmt = $pdo->prepare("
            SELECT 
                l.*,
                COALESCE(a.username, 'Admin #' || l.admin_id) AS admin_username
            FROM admin_audit_log l
            LEFT JOIN admins a ON l.admin_id = a.id
            {$whereSql}
            ORDER BY l.created_at DESC, l.id DESC
            LIMIT ? OFFSET ?
        ");

        $execParams = $params;
        $execParams[] = $perPage;
        $execParams[] = $offset;

        // Bind parameters safely for LIMIT and OFFSET
        $paramIndex = 1;
        foreach ($params as $p) {
            $queryStmt->bindValue($paramIndex++, $p, PDO::PARAM_STR);
        }
        $queryStmt->bindValue($paramIndex++, $perPage, PDO::PARAM_INT);
        $queryStmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);

        $queryStmt->execute();
        $logs = $queryStmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to query admin audit log: " . $e->getMessage());
    }
}

$totalPages = max(1, (int)ceil($totalLogs / $perPage));

/**
 * Helper to render formatted diff or state.
 */
function render_audit_diff(?string $oldVal, ?string $newVal): string
{
    if ($oldVal === null && $newVal === null) {
        return '<span style="color:#94a3b8;">None</span>';
    }

    $formatValue = function(?string $raw) {
        if ($raw === null) return '<em>null</em>';
        $json = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            $lines = [];
            foreach ($json as $k => $v) {
                $lines[] = htmlspecialchars($k) . ': ' . (is_array($v) ? json_encode($v) : htmlspecialchars((string)$v));
            }
            return implode(', ', $lines);
        }
        return htmlspecialchars($raw);
    };

    if ($oldVal !== null && $newVal !== null) {
        return '<div style="font-size:0.75rem;line-height:1.4;">' .
               '<div style="color:#ef4444;"><span style="font-weight:600;">Old:</span> ' . $formatValue($oldVal) . '</div>' .
               '<div style="color:#10b981;"><span style="font-weight:600;">New:</span> ' . $formatValue($newVal) . '</div>' .
               '</div>';
    }

    if ($newVal !== null) {
        return '<div style="font-size:0.75rem;color:#0f172a;"><span style="font-weight:600;color:#10b981;">Created:</span> ' . $formatValue($newVal) . '</div>';
    }

    return '<div style="font-size:0.75rem;color:#ef4444;"><span style="font-weight:600;">Removed:</span> ' . $formatValue($oldVal) . '</div>';
}
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; margin-bottom: 0.25rem;">Administrator Activity Log</h1>
            <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0;">
                Immutable operational audit trail recording catalog, price, inventory, order, and credential changes.
            </p>
        </div>
        <div style="font-size: 0.8125rem; background: #e2e8f0; padding: 0.35rem 0.75rem; border-radius: 6px; font-weight: 600; color: #334155;">
            Read-Only Compliance Trail
        </div>
    </div>
</div>

<!-- Metrics Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <div class="card" style="padding: 1.25rem;">
        <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Audit Records</span>
        <div style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin-top: 0.35rem;"><?= number_format($kpis['total']) ?></div>
    </div>
    <div class="card" style="padding: 1.25rem;">
        <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Catalogue &amp; Stock Events</span>
        <div style="font-size: 1.75rem; font-weight: 800; color: #0284c7; margin-top: 0.35rem;"><?= number_format($kpis['products']) ?></div>
    </div>
    <div class="card" style="padding: 1.25rem;">
        <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Order Status Transitions</span>
        <div style="font-size: 1.75rem; font-weight: 800; color: #059669; margin-top: 0.35rem;"><?= number_format($kpis['orders']) ?></div>
    </div>
    <div class="card" style="padding: 1.25rem;">
        <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Security &amp; Password Events</span>
        <div style="font-size: 1.75rem; font-weight: 800; color: #7c3aed; margin-top: 0.35rem;"><?= number_format($kpis['security']) ?></div>
    </div>
</div>

<!-- Filter Bar -->
<div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem; background: #ffffff;">
    <form action="<?= admin_url('activity-log.php') ?>" method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)) auto; gap: 0.75rem; align-items: end;">
        <div>
            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;" for="f_action">Action</label>
            <select name="action" id="f_action" class="form-control" style="font-size: 0.85rem; padding: 0.45rem;">
                <option value="">All Actions</option>
                <option value="create" <?= $actionFilter === 'create' ? 'selected' : '' ?>>Create</option>
                <option value="update" <?= $actionFilter === 'update' ? 'selected' : '' ?>>Update</option>
                <option value="delete" <?= $actionFilter === 'delete' ? 'selected' : '' ?>>Delete</option>
                <option value="password_change" <?= $actionFilter === 'password_change' ? 'selected' : '' ?>>Password Change</option>
                <option value="logout" <?= $actionFilter === 'logout' ? 'selected' : '' ?>>Logout</option>
            </select>
        </div>

        <div>
            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;" for="f_entity">Entity</label>
            <select name="entity" id="f_entity" class="form-control" style="font-size: 0.85rem; padding: 0.45rem;">
                <option value="">All Entities</option>
                <option value="product" <?= $entityFilter === 'product' ? 'selected' : '' ?>>Products</option>
                <option value="variant" <?= $entityFilter === 'variant' ? 'selected' : '' ?>>Variants</option>
                <option value="price" <?= $entityFilter === 'price' ? 'selected' : '' ?>>Pricing</option>
                <option value="gst" <?= $entityFilter === 'gst' ? 'selected' : '' ?>>GST Rates</option>
                <option value="stock" <?= $entityFilter === 'stock' ? 'selected' : '' ?>>Stock &amp; Availability</option>
                <option value="order_status" <?= $entityFilter === 'order_status' ? 'selected' : '' ?>>Order Status</option>
                <option value="settings" <?= $entityFilter === 'settings' ? 'selected' : '' ?>>Content Settings</option>
                <option value="admin" <?= $entityFilter === 'admin' ? 'selected' : '' ?>>Admin Accounts</option>
            </select>
        </div>

        <div>
            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;" for="f_from">Date From</label>
            <input type="date" name="date_from" id="f_from" class="form-control" style="font-size: 0.85rem; padding: 0.45rem;" value="<?= e($dateFrom) ?>">
        </div>

        <div>
            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;" for="f_to">Date To</label>
            <input type="date" name="date_to" id="f_to" class="form-control" style="font-size: 0.85rem; padding: 0.45rem;" value="<?= e($dateTo) ?>">
        </div>

        <div>
            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;" for="f_q">Search Keyword</label>
            <input type="text" name="q" id="f_q" class="form-control" placeholder="ID, details, IP..." style="font-size: 0.85rem; padding: 0.45rem;" value="<?= e($searchQuery) ?>">
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.85rem;">
                Filter
            </button>
            <?php if (!empty($actionFilter) || !empty($entityFilter) || !empty($dateFrom) || !empty($dateTo) || !empty($searchQuery)): ?>
                <a href="<?= admin_url('activity-log.php') ?>" class="btn btn-outline" style="padding: 0.45rem 0.75rem; font-size: 0.85rem; text-decoration: none;">
                    Reset
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Activity Log Table -->
<div class="card" style="padding: 0; overflow: hidden; background: #ffffff;">
    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
        <span style="font-size: 0.875rem; font-weight: 600; color: #475569;">
            Showing <?= count($logs) ?> of <?= number_format($totalLogs) ?> total events
        </span>
        <span style="font-size: 0.8125rem; color: #64748b;">
            Page <?= $page ?> of <?= $totalPages ?>
        </span>
    </div>

    <div style="overflow-x: auto;">
        <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left;">
                    <th style="padding: 0.75rem 1rem; width: 60px;">#</th>
                    <th style="padding: 0.75rem 1rem; width: 155px;">Timestamp</th>
                    <th style="padding: 0.75rem 1rem; width: 110px;">Admin</th>
                    <th style="padding: 0.75rem 1rem; width: 110px;">Action</th>
                    <th style="padding: 0.75rem 1rem; width: 130px;">Entity</th>
                    <th style="padding: 0.75rem 1rem; width: 100px;">Target Ref</th>
                    <th style="padding: 0.75rem 1rem;">Changes / Audit State</th>
                    <th style="padding: 0.75rem 1rem; width: 120px;">IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="8" style="padding: 3rem 1rem; text-align: center; color: #64748b;">
                            No activity logs found matching the specified filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <?php
                            $actionBadge = match ($l['action']) {
                                'create'          => 'background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;',
                                'update'          => 'background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;',
                                'delete'          => 'background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;',
                                'password_change' => 'background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe;',
                                default           => 'background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;',
                            };

                            $entityLabel = match ($l['entity']) {
                                'product'      => 'Product',
                                'variant'      => 'Variant',
                                'price'        => 'Price',
                                'gst'          => 'GST Rate',
                                'stock'        => 'Stock',
                                'order_status' => 'Order Status',
                                'settings'     => 'Settings',
                                'admin'        => 'Admin User',
                                default        => ucfirst((string)$l['entity']),
                            };
                        ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 0.75rem 1rem; font-size: 0.75rem; color: #94a3b8; font-mono;">
                                <?= (int)$l['id'] ?>
                            </td>
                            <td style="padding: 0.75rem 1rem; white-space: nowrap; font-size: 0.8125rem;">
                                <?= date('Y-m-d H:i:s', strtotime($l['created_at'])) ?>
                            </td>
                            <td style="padding: 0.75rem 1rem;">
                                <span style="font-weight: 600; color: #1e293b;">
                                    <?= e($l['admin_username']) ?>
                                </span>
                            </td>
                            <td style="padding: 0.75rem 1rem;">
                                <span style="display: inline-block; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; <?= $actionBadge ?>">
                                    <?= e($l['action']) ?>
                                </span>
                            </td>
                            <td style="padding: 0.75rem 1rem; font-weight: 600; color: #334155;">
                                <?= e($entityLabel) ?>
                            </td>
                            <td style="padding: 0.75rem 1rem; font-family: monospace; font-size: 0.8125rem; color: #0284c7;">
                                <?= e($l['entity_id'] ?? '—') ?>
                            </td>
                            <td style="padding: 0.75rem 1rem; max-width: 380px;">
                                <?= render_audit_diff($l['old_value'], $l['new_value']) ?>
                            </td>
                            <td style="padding: 0.75rem 1rem; font-family: monospace; font-size: 0.75rem; color: #64748b;">
                                <?= e($l['ip']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div style="padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e2e8f0; flex-wrap: wrap; gap: 0.5rem;">
            <div>
                <?php if ($page > 1): ?>
                    <?php
                        $prevParams = $_GET;
                        $prevParams['page'] = $page - 1;
                        $prevUrl = 'activity-log.php?' . http_build_query($prevParams);
                    ?>
                    <a href="<?= admin_url($prevUrl) ?>" class="btn btn-outline" style="font-size: 0.8125rem; padding: 0.35rem 0.75rem;">&larr; Previous</a>
                <?php endif; ?>
            </div>
            <div style="font-size: 0.8125rem; color: #64748b;">
                Page <?= $page ?> of <?= $totalPages ?>
            </div>
            <div>
                <?php if ($page < $totalPages): ?>
                    <?php
                        $nextParams = $_GET;
                        $nextParams['page'] = $page + 1;
                        $nextUrl = 'activity-log.php?' . http_build_query($nextParams);
                    ?>
                    <a href="<?= admin_url($nextUrl) ?>" class="btn btn-outline" style="font-size: 0.8125rem; padding: 0.35rem 0.75rem;">Next &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
