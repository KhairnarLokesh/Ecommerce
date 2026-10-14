<?php
/**
 * Kalika Enterprises - Customers Management Directory
 * Phase 7: List, search, review purchase history, and manage active status of retail customer accounts.
 */

declare(strict_types=1);

$adminPage = 'customers';
$adminTitle = 'Customer Directory';

require_once __DIR__ . '/includes/header.php';

$error = '';
$searchQuery = trim($_GET['q'] ?? '');

// Handle Account Status Toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Security verification failed. Please try again.';
    } else {
        $action = trim($_POST['action'] ?? '');
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($action === 'toggle' && $userId > 0 && $pdo) {
            $stmt = $pdo->prepare("SELECT id, name, is_active FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $uRow = $stmt->fetch();

            if ($uRow) {
                $newActive = ((int)$uRow['is_active'] === 1) ? 0 : 1;
                $up = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                $up->execute([$newActive, $userId]);

                $label = ($newActive === 1) ? 'activated' : 'deactivated';
                set_flash('success', "Customer account '{$uRow['name']}' has been {$label}.");
            }
            redirect(admin_url('customers.php' . (!empty($searchQuery) ? '?q=' . urlencode($searchQuery) : '')));
        }
    }
}

// Build query
$whereClauses = [];
$params = [];

if (!empty($searchQuery)) {
    $whereClauses[] = "(u.name LIKE ? OR u.mobile LIKE ? OR u.email LIKE ? OR u.address LIKE ?)";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(' AND ', $whereClauses) : "";

$customers = [];
if ($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.name,
                u.mobile,
                u.email,
                u.address,
                u.is_active,
                u.created_at,
                COUNT(o.id) AS order_count,
                COALESCE(SUM(o.grand_total), 0) AS lifetime_spend
            FROM users u
            LEFT JOIN orders o ON o.user_id = u.id
            {$whereSql}
            GROUP BY u.id
            ORDER BY u.created_at DESC
        ");
        $stmt->execute($params);
        $customers = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to load customers: " . $e->getMessage());
    }
}
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; margin-bottom: 0.25rem;">Retailer &amp; Customer Accounts</h1>
            <p style="font-size: 0.9375rem; color: var(--text-muted); margin-bottom: 0;">
                Directory of verified shop owners and commercial retail buyers across the distribution region.
            </p>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-error" role="alert" style="margin-bottom: 1.5rem;">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<!-- Search Toolbar -->
<div class="card" style="padding: 1.25rem 1.5rem; margin-bottom: 2rem;">
    <form action="<?= admin_url('customers.php') ?>" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; justify-content: space-between;">
        <div style="flex-grow: 1; max-width: 440px;">
            <div class="input-group">
                <input type="text" name="q" class="form-control" placeholder="Search by name, 10-digit mobile, email, or address..." value="<?= e($searchQuery) ?>" style="font-size: 0.875rem;">
                <button type="submit" class="btn btn-primary" title="Search customers">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <span>Search</span>
                </button>
            </div>
        </div>

        <?php if (!empty($searchQuery)): ?>
            <a href="<?= admin_url('customers.php') ?>" class="btn btn-sm btn-outline">
                &times; Clear Search
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Customers Table -->
<div class="card" style="padding: 1.5rem;">
    <h2 style="font-size: 1.15rem; margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
        Registered Accounts (<?= count($customers) ?>)
    </h2>

    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-color); font-size: 0.8125rem; color: var(--text-muted); text-transform: uppercase;">
                    <th style="padding: 0.75rem 0.5rem;">Store / Customer</th>
                    <th style="padding: 0.75rem 0.5rem;">Contact Mobile</th>
                    <th style="padding: 0.75rem 0.5rem;">Registered Address</th>
                    <th style="padding: 0.75rem 0.5rem; text-align: center;">Orders Placed</th>
                    <th style="padding: 0.75rem 0.5rem; text-align: right;">Lifetime Spend</th>
                    <th style="padding: 0.75rem 0.5rem; text-align: center;">Status</th>
                    <th style="padding: 0.75rem 0.5rem; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="7" style="padding: 3rem 1rem; text-align: center; color: var(--text-muted);">
                            No customer accounts match your search query.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $cust): ?>
                        <?php $isActive = ((int)$cust['is_active'] === 1); ?>
                        <tr style="border-bottom: 1px solid var(--border-light); font-size: 0.9375rem;">
                            <!-- Name & Email -->
                            <td style="padding: 0.875rem 0.5rem;">
                                <strong style="color: var(--color-primary);"><?= e($cust['name']) ?></strong>
                                <?php if (!empty($cust['email'])): ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($cust['email']) ?></div>
                                <?php endif; ?>
                                <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.15rem;">
                                    Member since <?= date('d M Y', strtotime($cust['created_at'])) ?>
                                </div>
                            </td>

                            <!-- Mobile -->
                            <td style="padding: 0.875rem 0.5rem; font-weight: 600;">
                                +91 <?= e($cust['mobile']) ?>
                            </td>

                            <!-- Address -->
                            <td style="padding: 0.875rem 0.5rem; font-size: 0.8125rem; color: var(--text-main); max-width: 240px;">
                                <div style="line-height: 1.35;"><?= nl2br(e($cust['address'])) ?></div>
                            </td>

                            <!-- Orders Count -->
                            <td style="padding: 0.875rem 0.5rem; text-align: center; font-weight: 700;">
                                <a href="<?= admin_url('orders.php?q=' . urlencode($cust['mobile'])) ?>" style="color: inherit; text-decoration: underline;">
                                    <?= (int)$cust['order_count'] ?> order(s)
                                </a>
                            </td>

                            <!-- Lifetime Spend -->
                            <td style="padding: 0.875rem 0.5rem; text-align: right; font-weight: 800; color: var(--color-primary);">
                                <?= format_currency($cust['lifetime_spend']) ?>
                            </td>

                            <!-- Status Badge -->
                            <td style="padding: 0.875rem 0.5rem; text-align: center;">
                                <span class="badge <?= $isActive ? 'badge-stock-in' : 'badge-stock-out' ?>">
                                    <?= $isActive ? 'Active' : 'Suspended' ?>
                                </span>
                            </td>

                            <!-- Actions -->
                            <td style="padding: 0.875rem 0.5rem; text-align: right; white-space: nowrap;">
                                <a href="<?= admin_url('orders.php?q=' . urlencode($cust['mobile'])) ?>" class="btn btn-sm btn-outline" style="margin-right: 0.35rem;">
                                    Orders
                                </a>
                                <form action="<?= admin_url('customers.php' . (!empty($searchQuery) ? '?q=' . urlencode($searchQuery) : '')) ?>" method="POST" style="display: inline-block; margin: 0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="user_id" value="<?= (int)$cust['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-outline' : 'btn-primary' ?>" style="font-size: 0.75rem;">
                                        <?= $isActive ? 'Suspend' : 'Activate' ?>
                                    </button>
                                </form>
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
