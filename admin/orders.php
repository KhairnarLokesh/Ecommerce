<?php
/**
 * Kalika Enterprises - Admin Orders Pipeline & CSV Export
 * Phase 7: Order filtering, fulfillment pipeline, status progression, and CSV data export.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_admin();

$statusFilter = trim($_GET['status'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$isExport = (isset($_GET['export']) && $_GET['export'] === 'csv');

// Handle Quick Status Advance POST from orders list
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Security token invalid or expired.');
    } else {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');

        if ($orderId > 0 && in_array($newStatus, ['Placed', 'Dispatched', 'Delivered'], true) && $pdo) {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $orderId]);
            set_flash('success', "Order #{$orderId} status advanced to '{$newStatus}'.");
        }
    }
    redirect(admin_url('orders.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')));
}

// Build query clauses
$whereClauses = [];
$params = [];

if (!empty($statusFilter) && in_array($statusFilter, ['Placed', 'Dispatched', 'Delivered'], true)) {
    $whereClauses[] = "o.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $whereClauses[] = "(o.order_no LIKE ? OR o.customer_name LIKE ? OR o.mobile LIKE ? OR o.delivery_address LIKE ?)";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
}

if (!empty($dateFrom)) {
    $whereClauses[] = "DATE(o.created_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereClauses[] = "DATE(o.created_at) <= ?";
    $params[] = $dateTo;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(' AND ', $whereClauses) : "";

// Fetch orders
$orders = [];
if ($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                o.*,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
                (SELECT SUM(quantity) FROM order_items oi WHERE oi.order_id = o.id) AS total_units
            FROM orders o
            {$whereSql}
            ORDER BY o.created_at DESC
        ");
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to query orders: " . $e->getMessage());
    }
}

// Handle CSV Export
if ($isExport) {
    $filename = 'kalika_orders_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // CSV Header row
    fputcsv($output, [
        'Order Number',
        'Customer Name',
        'Mobile',
        'Delivery Address',
        'Subtotal (INR)',
        'GST Total (INR)',
        'Delivery Charge (INR)',
        'Grand Total (INR)',
        'Status',
        'Date Placed'
    ]);

    foreach ($orders as $ord) {
        fputcsv($output, [
            $ord['order_no'],
            $ord['customer_name'],
            $ord['mobile'],
            str_replace(["\r", "\n"], ' ', $ord['delivery_address']),
            $ord['subtotal'],
            $ord['gst_total'],
            $ord['delivery_charge'],
            $ord['grand_total'],
            $ord['status'],
            date('Y-m-d H:i:s', strtotime($ord['created_at']))
        ]);
    }

    fclose($output);
    exit;
}

$adminPage = 'orders';
$adminTitle = 'Orders Pipeline';

require_once __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; margin-bottom: 0.25rem;">Wholesale Orders Pipeline</h1>
            <p style="font-size: 0.9375rem; color: var(--text-muted); margin-bottom: 0;">
                Dispatch management, order status tracking, and offline collection records.
            </p>
        </div>
        <div>
            <?php
            $exportParams = $_GET;
            $exportParams['export'] = 'csv';
            $exportUrl = admin_url('orders.php?' . http_build_query($exportParams));
            ?>
            <a href="<?= $exportUrl ?>" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.45rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Export Filtered Orders to CSV
            </a>
        </div>
    </div>
</div>

<!-- Filter Toolbar Card -->
<div class="card" style="padding: 1.25rem 1.5rem; margin-bottom: 2rem;">
    <form action="<?= admin_url('orders.php') ?>" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <!-- Status Filter -->
        <div style="flex: 1; min-width: 140px;">
            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Status:</label>
            <select name="status" class="form-control" style="font-size: 0.875rem;" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="Placed" <?= $statusFilter === 'Placed' ? 'selected' : '' ?>>Placed (New)</option>
                <option value="Dispatched" <?= $statusFilter === 'Dispatched' ? 'selected' : '' ?>>Dispatched (On Van)</option>
                <option value="Delivered" <?= $statusFilter === 'Delivered' ? 'selected' : '' ?>>Delivered (Complete)</option>
            </select>
        </div>

        <!-- Date Range: From -->
        <div style="flex: 1; min-width: 140px;">
            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">From Date:</label>
            <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>" style="font-size: 0.875rem;">
        </div>

        <!-- Date Range: To -->
        <div style="flex: 1; min-width: 140px;">
            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">To Date:</label>
            <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>" style="font-size: 0.875rem;">
        </div>

        <!-- Search Input -->
        <div style="flex: 2; min-width: 220px;">
            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Search Order / Retailer:</label>
            <input type="text" name="q" class="form-control" placeholder="Order No, Mobile, Shop name..." value="<?= e($searchQuery) ?>" style="font-size: 0.875rem;">
        </div>

        <div>
            <button type="submit" class="btn btn-primary btn-sm" style="padding: 0.65rem 1.25rem;">
                Filter
            </button>
            <?php if (!empty($statusFilter) || !empty($searchQuery) || !empty($dateFrom) || !empty($dateTo)): ?>
                <a href="<?= admin_url('orders.php') ?>" class="btn btn-outline btn-sm" style="margin-left: 0.35rem;">
                    Reset
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Orders Table -->
<div class="card" style="padding: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
        <h2 style="font-size: 1.15rem; margin-bottom: 0;">
            Orders Found (<?= count($orders) ?>)
        </h2>
    </div>

    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-color); font-size: 0.8125rem; color: var(--text-muted); text-transform: uppercase;">
                    <th style="padding: 0.75rem 0.5rem;">Order Number</th>
                    <th style="padding: 0.75rem 0.5rem;">Date Placed</th>
                    <th style="padding: 0.75rem 0.5rem;">Customer &amp; Town</th>
                    <th style="padding: 0.75rem 0.5rem; text-align: center;">Items / Qty</th>
                    <th style="padding: 0.75rem 0.5rem; text-align: right;">Grand Total</th>
                    <th style="padding: 0.75rem 0.5rem; text-align: center;">Status</th>
                    <th style="padding: 0.75rem 0.5rem; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="7" style="padding: 3.5rem 1rem; text-align: center; color: var(--text-muted);">
                            No orders found matching the filter parameters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $ord): ?>
                        <?php
                        $st = $ord['status'];
                        $badge = match ($st) {
                            'Delivered'  => 'badge-success',
                            'Dispatched' => 'badge-info',
                            default      => 'badge-warning',
                        };
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-light); font-size: 0.9375rem;">
                            <!-- Order Number -->
                            <td style="padding: 0.875rem 0.5rem; font-weight: 700; color: var(--color-primary);">
                                <a href="<?= admin_url('order-detail.php?id=' . (int)$ord['id']) ?>" style="color: inherit; text-decoration: underline;">
                                    <?= e($ord['order_no']) ?>
                                </a>
                            </td>

                            <!-- Date Placed -->
                            <td style="padding: 0.875rem 0.5rem; font-size: 0.8125rem; color: var(--text-muted);">
                                <?= date('d M Y, h:i A', strtotime($ord['created_at'])) ?>
                            </td>

                            <!-- Customer & Delivery -->
                            <td style="padding: 0.875rem 0.5rem;">
                                <div style="font-weight: 600; color: var(--color-primary);"><?= e($ord['customer_name']) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">+91 <?= e($ord['mobile']) ?></div>
                            </td>

                            <!-- Items / Total Qty -->
                            <td style="padding: 0.875rem 0.5rem; text-align: center; font-size: 0.875rem;">
                                <?= (int)$ord['item_count'] ?> items (<?= (int)$ord['total_units'] ?> units)
                            </td>

                            <!-- Grand Total -->
                            <td style="padding: 0.875rem 0.5rem; text-align: right; font-weight: 800; color: var(--color-primary);">
                                <?= format_currency($ord['grand_total']) ?>
                            </td>

                            <!-- Status Badge & Quick Advance -->
                            <td style="padding: 0.875rem 0.5rem; text-align: center;">
                                <span class="badge <?= $badge ?>">
                                    <?= e($st) ?>
                                </span>
                            </td>

                            <!-- Actions -->
                            <td style="padding: 0.875rem 0.5rem; text-align: right; white-space: nowrap;">
                                <a href="<?= admin_url('order-detail.php?id=' . (int)$ord['id']) ?>" class="btn btn-sm btn-outline" style="margin-right: 0.25rem;">
                                    Manage
                                </a>
                                <a href="<?= public_url('invoice.php?order=' . (int)$ord['id']) ?>" target="_blank" class="btn btn-sm btn-primary">
                                    Invoice
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
