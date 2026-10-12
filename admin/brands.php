<?php
/**
 * Kalika Enterprises - Brands Management
 * Phase 7: Add, edit, activate, and deactivate wholesale brands.
 */

declare(strict_types=1);

$adminPage = 'brands';
$adminTitle = 'Brand Management';

require_once __DIR__ . '/includes/header.php';

$error = '';
$success = '';

// Handle Actions (Add, Edit, Toggle)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Security token invalid or expired. Please try again.';
    } else {
        $action = trim($_POST['action'] ?? '');

        if ($action === 'add' || $action === 'edit') {
            $brandId = (int)($_POST['brand_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if (empty($name)) {
                $error = 'Brand name cannot be empty.';
            } else {
                if (empty($slug)) {
                    $slug = strtolower(trim((string)preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
                }

                if ($action === 'add') {
                    // Check duplicate slug
                    $check = $pdo->prepare("SELECT id FROM brands WHERE slug = ? LIMIT 1");
                    $check->execute([$slug]);
                    if ($check->fetch()) {
                        $slug .= '-' . time();
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO brands (name, slug, description, is_active)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $slug, $description, $isActive]);
                    set_flash('success', "Brand '{$name}' created successfully.");
                    redirect(admin_url('brands.php'));
                } else {
                    // Edit
                    $stmt = $pdo->prepare("
                        UPDATE brands
                        SET name = ?, slug = ?, description = ?, is_active = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $slug, $description, $isActive, $brandId]);
                    set_flash('success', "Brand '{$name}' updated successfully.");
                    redirect(admin_url('brands.php'));
                }
            }
        } elseif ($action === 'toggle') {
            $brandId = (int)($_POST['brand_id'] ?? 0);
            $currentStatus = (int)($_POST['current_status'] ?? 0);
            $newStatus = ($currentStatus === 1) ? 0 : 1;

            $stmt = $pdo->prepare("UPDATE brands SET is_active = ? WHERE id = ?");
            $stmt->execute([$newStatus, $brandId]);

            $label = ($newStatus === 1) ? 'activated' : 'deactivated';
            set_flash('success', "Brand has been {$label}.");
            redirect(admin_url('brands.php'));
        }
    }
}

// Fetch all brands with associated product counts
$brands = [];
if ($pdo) {
    try {
        $bStmt = $pdo->query("
            SELECT b.*, COUNT(p.id) AS product_count
            FROM brands b
            LEFT JOIN products p ON p.brand_id = b.id
            GROUP BY b.id
            ORDER BY b.name ASC
        ");
        $brands = $bStmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to load brands: " . $e->getMessage());
    }
}

$editBrand = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($brands as $b) {
        if ((int)$b['id'] === $editId) {
            $editBrand = $b;
            break;
        }
    }
}
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; margin-bottom: 0.25rem;">Brand Management</h1>
            <p style="font-size: 0.9375rem; color: var(--text-muted); margin-bottom: 0;">
                Configure authorized FMCG distributor brands and public visibility.
            </p>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-error" role="alert" style="margin-bottom: 1.5rem;">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="grid" style="grid-template-columns: 1.8fr 1.2fr; align-items: flex-start; gap: 2rem;">
    <!-- Left: Brand Directory Table -->
    <div class="card" style="padding: 1.5rem;">
        <h2 style="font-size: 1.25rem; margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
            Registered Brands (<?= count($brands) ?>)
        </h2>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); font-size: 0.8125rem; color: var(--text-muted); text-transform: uppercase;">
                        <th style="padding: 0.75rem 0.5rem;">Brand Name</th>
                        <th style="padding: 0.75rem 0.5rem;">Slug</th>
                        <th style="padding: 0.75rem 0.5rem; text-align: center;">Products</th>
                        <th style="padding: 0.75rem 0.5rem; text-align: center;">Status</th>
                        <th style="padding: 0.75rem 0.5rem; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($brands as $br): ?>
                        <?php $isActive = (int)$br['is_active'] === 1; ?>
                        <tr style="border-bottom: 1px solid var(--border-light); font-size: 0.9375rem;">
                            <td style="padding: 0.875rem 0.5rem;">
                                <strong style="color: var(--color-primary);"><?= e($br['name']) ?></strong>
                                <?php if (!empty($br['description'])): ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; max-width: 280px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                                        <?= e($br['description']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.875rem 0.5rem; font-size: 0.8125rem; color: var(--text-muted);">
                                <code><?= e($br['slug']) ?></code>
                            </td>
                            <td style="padding: 0.875rem 0.5rem; text-align: center; font-weight: 600;">
                                <?= (int)$br['product_count'] ?>
                            </td>
                            <td style="padding: 0.875rem 0.5rem; text-align: center;">
                                <span class="badge <?= $isActive ? 'badge-stock-in' : 'badge-stock-out' ?>">
                                    <?= $isActive ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td style="padding: 0.875rem 0.5rem; text-align: right; white-space: nowrap;">
                                <a href="<?= admin_url('brands.php?edit=' . (int)$br['id']) ?>" class="btn btn-sm btn-outline" style="margin-right: 0.35rem;">
                                    Edit
                                </a>
                                <form action="<?= admin_url('brands.php') ?>" method="POST" style="display: inline-block; margin: 0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="brand_id" value="<?= (int)$br['id'] ?>">
                                    <input type="hidden" name="current_status" value="<?= (int)$br['is_active'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-outline' : 'btn-primary' ?>" style="font-size: 0.75rem;">
                                        <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right: Add / Edit Brand Form Card -->
    <div class="card" style="padding: 1.5rem;">
        <h2 style="font-size: 1.25rem; margin-bottom: 0.5rem;">
            <?= $editBrand ? 'Edit Brand: ' . e($editBrand['name']) : 'Add New Brand' ?>
        </h2>
        <p style="font-size: 0.8125rem; color: var(--text-muted); margin-bottom: 1.25rem;">
            Brands structure catalogue navigation and filtering on the storefront.
        </p>

        <form action="<?= admin_url('brands.php') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editBrand ? 'edit' : 'add' ?>">
            <?php if ($editBrand): ?>
                <input type="hidden" name="brand_id" value="<?= (int)$editBrand['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label" for="brand_name">Brand Name *</label>
                <input type="text" id="brand_name" name="name" class="form-control" value="<?= e($editBrand['name'] ?? '') ?>" required placeholder="e.g. Campa, Sundar Biscuits">
            </div>

            <div class="form-group">
                <label class="form-label" for="brand_slug">URL Slug</label>
                <input type="text" id="brand_slug" name="slug" class="form-control" value="<?= e($editBrand['slug'] ?? '') ?>" placeholder="leave empty to auto-generate (e.g. campa)">
            </div>

            <div class="form-group">
                <label class="form-label" for="brand_desc">Description / Wholesale Notes</label>
                <textarea id="brand_desc" name="description" class="form-control" rows="3" placeholder="Distributor portfolio notes..."><?= e($editBrand['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" <?= (!$editBrand || (int)$editBrand['is_active'] === 1) ? 'checked' : '' ?>>
                    Active Brand (Visible on catalogue and filters)
                </label>
            </div>

            <div style="display: flex; gap: 0.75rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <?= $editBrand ? 'Save Brand Changes' : 'Create Brand' ?>
                </button>
                <?php if ($editBrand): ?>
                    <a href="<?= admin_url('brands.php') ?>" class="btn btn-outline">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
