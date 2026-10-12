<?php
/**
 * Kalika Enterprises - Categories Management
 * Add, edit, activate, and deactivate product categories.
 */

declare(strict_types=1);

$adminPage = 'categories';
$adminTitle = 'Category Management';

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
            $catId = (int)($_POST['category_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if (empty($name)) {
                $error = 'Category name cannot be empty.';
            } else {
                if (empty($slug)) {
                    $slug = strtolower(trim((string)preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
                }

                if ($action === 'add') {
                    // Check duplicate slug
                    $check = $pdo->prepare("SELECT id FROM categories WHERE slug = ? LIMIT 1");
                    $check->execute([$slug]);
                    if ($check->fetch()) {
                        $slug .= '-' . time();
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO categories (name, slug, description, sort_order, is_active)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $slug, $description, $sortOrder, $isActive]);
                    set_flash('success', "Category '{$name}' created successfully.");
                    redirect(admin_url('categories.php'));
                } else {
                    // Edit
                    $stmt = $pdo->prepare("
                        UPDATE categories
                        SET name = ?, slug = ?, description = ?, sort_order = ?, is_active = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $slug, $description, $sortOrder, $isActive, $catId]);
                    set_flash('success', "Category '{$name}' updated successfully.");
                    redirect(admin_url('categories.php'));
                }
            }
        } elseif ($action === 'toggle') {
            $catId = (int)($_POST['category_id'] ?? 0);
            $currentStatus = (int)($_POST['current_status'] ?? 0);
            $newStatus = ($currentStatus === 1) ? 0 : 1;

            $stmt = $pdo->prepare("UPDATE categories SET is_active = ? WHERE id = ?");
            $stmt->execute([$newStatus, $catId]);

            $label = ($newStatus === 1) ? 'activated' : 'deactivated';
            set_flash('success', "Category has been {$label}.");
            redirect(admin_url('categories.php'));
        }
    }
}

// Fetch all categories with associated product counts
$categories = [];
if ($pdo) {
    try {
        $cStmt = $pdo->query("
            SELECT c.*, COUNT(p.id) AS product_count
            FROM categories c
            LEFT JOIN products p ON p.category_id = c.id
            GROUP BY c.id
            ORDER BY c.sort_order ASC, c.name ASC
        ");
        $categories = $cStmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to load categories: " . $e->getMessage());
    }
}

$editCategory = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($categories as $c) {
        if ((int)$c['id'] === $editId) {
            $editCategory = $c;
            break;
        }
    }
}
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; margin-bottom: 0.25rem;">Category Management</h1>
            <p style="font-size: 0.9375rem; color: var(--text-muted); margin-bottom: 0;">
                Configure wholesale product categories and catalogue classifications.
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
    <!-- Left: Category Directory Table -->
    <div class="card" style="padding: 1.5rem;">
        <h2 style="font-size: 1.25rem; margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
            Registered Categories (<?= count($categories) ?>)
        </h2>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); font-size: 0.8125rem; color: var(--text-muted); text-transform: uppercase;">
                        <th style="padding: 0.75rem 0.5rem;">Category Name</th>
                        <th style="padding: 0.75rem 0.5rem;">Slug</th>
                        <th style="padding: 0.75rem 0.5rem; text-align: center;">Order</th>
                        <th style="padding: 0.75rem 0.5rem; text-align: center;">Products</th>
                        <th style="padding: 0.75rem 0.5rem; text-align: center;">Status</th>
                        <th style="padding: 0.75rem 0.5rem; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <?php $isActive = (int)$cat['is_active'] === 1; ?>
                        <tr style="border-bottom: 1px solid var(--border-light); font-size: 0.9375rem;">
                            <td style="padding: 0.875rem 0.5rem;">
                                <strong style="color: var(--color-primary);"><?= e($cat['name']) ?></strong>
                                <?php if (!empty($cat['description'])): ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; max-width: 280px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                                        <?= e($cat['description']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.875rem 0.5rem; font-size: 0.8125rem; color: var(--text-muted);">
                                <code><?= e($cat['slug']) ?></code>
                            </td>
                            <td style="padding: 0.875rem 0.5rem; text-align: center; font-size: 0.8125rem;">
                                <?= (int)$cat['sort_order'] ?>
                            </td>
                            <td style="padding: 0.875rem 0.5rem; text-align: center; font-weight: 600;">
                                <?= (int)$cat['product_count'] ?>
                            </td>
                            <td style="padding: 0.875rem 0.5rem; text-align: center;">
                                <span class="badge <?= $isActive ? 'badge-stock-in' : 'badge-stock-out' ?>">
                                    <?= $isActive ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td style="padding: 0.875rem 0.5rem; text-align: right; white-space: nowrap;">
                                <a href="<?= admin_url('categories.php?edit=' . (int)$cat['id']) ?>" class="btn btn-sm btn-outline" style="margin-right: 0.35rem;">
                                    Edit
                                </a>
                                <form action="<?= admin_url('categories.php') ?>" method="POST" style="display: inline-block; margin: 0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
                                    <input type="hidden" name="current_status" value="<?= (int)$cat['is_active'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-outline' : 'btn-primary' ?>" style="font-size: 0.75rem;">
                                        <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="6" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                                No categories configured yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right: Add / Edit Form Card -->
    <div class="card" style="padding: 1.5rem;">
        <h2 style="font-size: 1.25rem; margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
            <?= $editCategory ? 'Edit Category' : 'Add New Category' ?>
        </h2>

        <form action="<?= admin_url('categories.php') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editCategory ? 'edit' : 'add' ?>">
            <?php if ($editCategory): ?>
                <input type="hidden" name="category_id" value="<?= (int)$editCategory['id'] ?>">
            <?php endif; ?>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="name" class="form-label" style="display: block; font-weight: 600; margin-bottom: 0.35rem; font-size: 0.875rem;">
                    Category Name <span style="color: #ef4444;">*</span>
                </label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    class="form-control" 
                    required 
                    placeholder="e.g. Cold Drinks & Soft Drinks" 
                    value="<?= e($editCategory['name'] ?? '') ?>"
                    style="width: 100%;"
                >
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="slug" class="form-label" style="display: block; font-weight: 600; margin-bottom: 0.35rem; font-size: 0.875rem;">
                    URL Slug
                </label>
                <input 
                    type="text" 
                    id="slug" 
                    name="slug" 
                    class="form-control" 
                    placeholder="e.g. cold-drinks (optional, auto-generated)" 
                    value="<?= e($editCategory['slug'] ?? '') ?>"
                    style="width: 100%;"
                >
                <small style="color: var(--text-muted); font-size: 0.75rem;">Leave blank to auto-create from title.</small>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="sort_order" class="form-label" style="display: block; font-weight: 600; margin-bottom: 0.35rem; font-size: 0.875rem;">
                    Sort Order
                </label>
                <input 
                    type="number" 
                    id="sort_order" 
                    name="sort_order" 
                    class="form-control" 
                    value="<?= (int)($editCategory['sort_order'] ?? 0) ?>"
                    style="width: 100%;"
                >
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="description" class="form-label" style="display: block; font-weight: 600; margin-bottom: 0.35rem; font-size: 0.875rem;">
                    Description
                </label>
                <textarea 
                    id="description" 
                    name="description" 
                    class="form-control" 
                    rows="3" 
                    placeholder="Brief description of this product category"
                    style="width: 100%;"
                ><?= e($editCategory['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9375rem; cursor: pointer;">
                    <input 
                        type="checkbox" 
                        name="is_active" 
                        value="1" 
                        <?= (!isset($editCategory) || (int)$editCategory['is_active'] === 1) ? 'checked' : '' ?>
                    >
                    <span>Active and visible on storefront</span>
                </label>
            </div>

            <div style="display: flex; gap: 0.75rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <?= $editCategory ? 'Save Changes' : 'Create Category' ?>
                </button>
                <?php if ($editCategory): ?>
                    <a href="<?= admin_url('categories.php') ?>" class="btn btn-outline">
                        Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
