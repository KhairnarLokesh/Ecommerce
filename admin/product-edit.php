<?php
/**
 * Kalika Enterprises - Product Add / Edit & Variant Manager
 * Phase 7: Product creation, secure image upload with getimagesize validation,
 *          strict GST activation rule, and child variant CRUD.
 */

declare(strict_types=1);

$adminPage = 'products';
$productId = (int)($_GET['id'] ?? 0);
$isEditing = ($productId > 0);
$adminTitle = $isEditing ? 'Edit Product' : 'Add New Product';

require_once __DIR__ . '/includes/header.php';

$errors = [];
$product = null;
$variants = [];

// Fetch product if editing
if ($isEditing && $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        set_flash('error', 'Product not found.');
        redirect(admin_url('products.php'));
    }

    $varStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY price ASC, id ASC");
    $varStmt->execute([$productId]);
    $variants = $varStmt->fetchAll();
}

// Fetch active brands and categories
$brands = [];
$categories = [];
if ($pdo) {
    $brands = $pdo->query("SELECT id, name FROM brands ORDER BY name ASC")->fetchAll();
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY sort_order ASC, name ASC")->fetchAll();
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Security verification failed. Please try again.';
    } else {
        $formType = trim($_POST['form_type'] ?? 'product');

        // 1. MAIN PRODUCT FORM (SAVE PRODUCT)
        if ($formType === 'product') {
            $name = trim($_POST['name'] ?? '');
            $brandId = (int)($_POST['brand_id'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $catParam = $categoryId > 0 ? $categoryId : null;
            $description = trim($_POST['description'] ?? '');
            $gstInput = trim($_POST['gst_percent'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if (empty($name)) {
                $errors[] = 'Product name is required.';
            }

            if ($brandId <= 0) {
                $errors[] = 'Please select a valid brand.';
            }

            $gstPercent = ($gstInput !== '' && is_numeric($gstInput)) ? (float)$gstInput : null;

            // HARD RULE: A product cannot be activated if GST is empty
            if ($isActive === 1 && $gstPercent === null) {
                $errors[] = 'A product cannot be activated if the GST rate is empty or unconfirmed. Please enter a valid GST percentage (e.g. 5, 12, 18, 28) or deactivate the product.';
            }

            // Image Upload Handling
            $imageFilename = $product['image'] ?? null;
            if (!empty($_FILES['image']['name'])) {
                $file = $_FILES['image'];
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

                $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
                $fileSize = (int)$file['size'];
                $tmpPath = (string)$file['tmp_name'];

                if (!in_array($ext, $allowedExtensions, true)) {
                    $errors[] = 'Invalid image format. Only JPG, PNG, and WebP images are allowed.';
                } elseif ($fileSize > 2 * 1024 * 1024) {
                    $errors[] = 'Image file size exceeds the 2MB limit.';
                } elseif (!is_uploaded_file($tmpPath)) {
                    $errors[] = 'Image upload failed. Please try again.';
                } else {
                    // Verify real image header via getimagesize
                    $imageInfo = @getimagesize($tmpPath);
                    if ($imageInfo === false || !in_array($imageInfo['mime'], $allowedMimes, true)) {
                        $errors[] = 'Uploaded file is not a valid image.';
                    } else {
                        // Generate random, collision-safe filename
                        $newFilename = 'prod_' . bin2hex(random_bytes(8)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
                        $uploadDir = __DIR__ . '/../public/uploads/products';

                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0755, true);
                        }

                        $targetPath = $uploadDir . '/' . $newFilename;
                        if (move_uploaded_file($tmpPath, $targetPath)) {
                            // If replacing an old custom image, clean it up
                            if (!empty($product['image']) && file_exists($uploadDir . '/' . $product['image'])) {
                                @unlink($uploadDir . '/' . $product['image']);
                            }
                            $imageFilename = $newFilename;
                        } else {
                            $errors[] = 'Failed to save image to uploads directory.';
                        }
                    }
                }
            }

            if (empty($errors) && $pdo) {
                if ($isEditing) {
                    $stmt = $pdo->prepare("
                        UPDATE products 
                        SET brand_id = ?, category_id = ?, name = ?, description = ?, image = ?, gst_percent = ?, is_active = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$brandId, $catParam, $name, $description, $imageFilename, $gstPercent, $isActive, $productId]);

                    admin_audit_log('update', 'product', (string)$productId, $product, [
                        'brand_id' => $brandId,
                        'category_id' => $catParam,
                        'name' => $name,
                        'description' => $description,
                        'image' => $imageFilename,
                        'gst_percent' => $gstPercent,
                        'is_active' => $isActive
                    ]);

                    if ($product && (float)($product['gst_percent'] ?? 0) !== (float)($gstPercent ?? 0)) {
                        admin_audit_log('update', 'gst', (string)$productId, $product['gst_percent'] ?? null, $gstPercent);
                    }

                    set_flash('success', "Product '{$name}' updated successfully.");
                    redirect(admin_url("product-edit.php?id={$productId}"));
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO products (brand_id, category_id, name, description, image, gst_percent, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$brandId, $catParam, $name, $description, $imageFilename, $gstPercent, $isActive]);
                    $newId = (int)$pdo->lastInsertId();

                    admin_audit_log('create', 'product', (string)$newId, null, [
                        'brand_id' => $brandId,
                        'category_id' => $catParam,
                        'name' => $name,
                        'description' => $description,
                        'image' => $imageFilename,
                        'gst_percent' => $gstPercent,
                        'is_active' => $isActive
                    ]);

                    if ($gstPercent !== null) {
                        admin_audit_log('create', 'gst', (string)$newId, null, $gstPercent);
                    }

                    set_flash('success', "Product '{$name}' created! Now add at least one size variant below.");
                    redirect(admin_url("product-edit.php?id={$newId}"));
                }
            }
        }

        // 2. VARIANT MANAGEMENT (ADD / UPDATE / DELETE VARIANT)
        elseif ($formType === 'variant_add' && $isEditing) {
            $sizeLabel = trim($_POST['size_label'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $stockQty = (int)($_POST['stock_qty'] ?? 0);
            $stockStatus = ($_POST['stock_status'] ?? 'in_stock') === 'out_of_stock' ? 'out_of_stock' : 'in_stock';
            $isVarActive = isset($_POST['variant_is_active']) ? 1 : 0;

            if (empty($sizeLabel)) {
                $errors[] = 'Variant size label is required (e.g. 500ml Bottle, 70g Pouch).';
            }
            if ($price <= 0) {
                $errors[] = 'Unit price must be greater than ₹0.00.';
            }

            if (empty($errors) && $pdo) {
                $stmt = $pdo->prepare("
                    INSERT INTO product_variants (product_id, size_label, price, stock_qty, stock_status, is_active)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$productId, $sizeLabel, $price, $stockQty, $stockStatus, $isVarActive]);
                $newVarId = (int)$pdo->lastInsertId();

                admin_audit_log('create', 'variant', (string)$newVarId, null, [
                    'product_id' => $productId,
                    'size_label' => $sizeLabel,
                    'price' => $price,
                    'stock_qty' => $stockQty,
                    'stock_status' => $stockStatus,
                    'is_active' => $isVarActive
                ]);
                admin_audit_log('create', 'price', (string)$newVarId, null, $price);
                admin_audit_log('create', 'stock', (string)$newVarId, null, ['qty' => $stockQty, 'status' => $stockStatus]);

                set_flash('success', "Variant '{$sizeLabel}' added.");
                redirect(admin_url("product-edit.php?id={$productId}#variantsSection"));
            }
        } elseif ($formType === 'variant_update' && $isEditing) {
            $variantId = (int)($_POST['variant_id'] ?? 0);
            $sizeLabel = trim($_POST['size_label'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $stockQty = (int)($_POST['stock_qty'] ?? 0);
            $stockStatus = ($_POST['stock_status'] ?? 'in_stock') === 'out_of_stock' ? 'out_of_stock' : 'in_stock';
            $isVarActive = isset($_POST['variant_is_active']) ? 1 : 0;

            if (empty($sizeLabel)) {
                $errors[] = 'Variant size label cannot be empty.';
            }
            if ($price <= 0) {
                $errors[] = 'Unit price must be greater than ₹0.00.';
            }

            if (empty($errors) && $pdo) {
                $oldVarStmt = $pdo->prepare("SELECT * FROM product_variants WHERE id = ? LIMIT 1");
                $oldVarStmt->execute([$variantId]);
                $oldVar = $oldVarStmt->fetch();

                $stmt = $pdo->prepare("
                    UPDATE product_variants
                    SET size_label = ?, price = ?, stock_qty = ?, stock_status = ?, is_active = ?
                    WHERE id = ? AND product_id = ?
                ");
                $stmt->execute([$sizeLabel, $price, $stockQty, $stockStatus, $isVarActive, $variantId, $productId]);

                admin_audit_log('update', 'variant', (string)$variantId, $oldVar, [
                    'size_label' => $sizeLabel,
                    'price' => $price,
                    'stock_qty' => $stockQty,
                    'stock_status' => $stockStatus,
                    'is_active' => $isVarActive
                ]);
                if ($oldVar && (float)$oldVar['price'] !== (float)$price) {
                    admin_audit_log('update', 'price', (string)$variantId, $oldVar['price'], $price);
                }
                if ($oldVar && ((int)$oldVar['stock_qty'] !== (int)$stockQty || $oldVar['stock_status'] !== $stockStatus)) {
                    admin_audit_log('update', 'stock', (string)$variantId, ['qty' => $oldVar['stock_qty'], 'status' => $oldVar['stock_status']], ['qty' => $stockQty, 'status' => $stockStatus]);
                }

                set_flash('success', "Variant '{$sizeLabel}' updated.");
                redirect(admin_url("product-edit.php?id={$productId}#variantsSection"));
            }
        } elseif ($formType === 'variant_delete' && $isEditing) {
            $variantId = (int)($_POST['variant_id'] ?? 0);
            if ($variantId > 0 && $pdo) {
                $oldVarStmt = $pdo->prepare("SELECT * FROM product_variants WHERE id = ? LIMIT 1");
                $oldVarStmt->execute([$variantId]);
                $oldVar = $oldVarStmt->fetch();

                $del = $pdo->prepare("DELETE FROM product_variants WHERE id = ? AND product_id = ?");
                $del->execute([$variantId, $productId]);

                admin_audit_log('delete', 'variant', (string)$variantId, $oldVar, null);

                set_flash('success', "Variant deleted.");
                redirect(admin_url("product-edit.php?id={$productId}#variantsSection"));
            }
        }
    }
}
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="<?= admin_url('products.php') ?>" style="font-size: 0.8125rem; color: var(--text-muted); text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
                <span>Back to Products List</span>
            </a>
            <h1 style="font-size: 1.75rem; margin-top: 0.25rem; margin-bottom: 0.25rem;">
                <?= $isEditing ? 'Edit: ' . e($product['name']) : 'Create New Product' ?>
            </h1>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error" role="alert" style="margin-bottom: 2rem;">
        <ul style="margin: 0; padding-left: 1.25rem;">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Main Product Details Form -->
<div class="card" style="padding: 2rem; margin-bottom: 2.5rem;">
    <h2 style="font-size: 1.25rem; margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
        Product Specifications
    </h2>

    <form action="<?= admin_url('product-edit.php' . ($isEditing ? '?id=' . $productId : '')) ?>" method="POST" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="form_type" value="product">

        <div class="grid grid-cols-2" style="gap: 1.5rem;">
            <!-- Product Name -->
            <div class="form-group">
                <label class="form-label" for="prod_name">Product Name *</label>
                <input type="text" id="prod_name" name="name" class="form-control" value="<?= e($product['name'] ?? $_POST['name'] ?? '') ?>" required placeholder="e.g. Campa Cola, Euro Chips Masala">
            </div>

            <div class="grid grid-cols-2" style="gap: 1.5rem;">
                <!-- Brand Selection -->
                <div class="form-group">
                    <label class="form-label" for="prod_brand">Brand *</label>
                    <select id="prod_brand" name="brand_id" class="form-control" required>
                        <option value="">-- Select Brand --</option>
                        <?php 
                        $selectedBrand = (int)($product['brand_id'] ?? $_POST['brand_id'] ?? 0);
                        foreach ($brands as $b): 
                        ?>
                            <option value="<?= (int)$b['id'] ?>" <?= $selectedBrand === (int)$b['id'] ? 'selected' : '' ?>>
                                <?= e($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Category Selection -->
                <div class="form-group">
                    <label class="form-label" for="prod_category">Category</label>
                    <select id="prod_category" name="category_id" class="form-control">
                        <option value="">-- Select Category (Optional) --</option>
                        <?php 
                        $selectedCategory = (int)($product['category_id'] ?? $_POST['category_id'] ?? 0);
                        foreach ($categories as $c): 
                        ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $selectedCategory === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2" style="gap: 1.5rem;">
            <!-- GST % Input with Critical Notice -->
            <div class="form-group">
                <label class="form-label" for="prod_gst">
                    GST Percentage (%) 
                    <span style="font-size: 0.75rem; color: #b45309; font-weight: normal;">(Required for public activation)</span>
                </label>
                <input type="number" step="0.01" min="0" max="40" id="prod_gst" name="gst_percent" class="form-control" 
                       value="<?= ($product && $product['gst_percent'] !== null) ? (float)$product['gst_percent'] : e($_POST['gst_percent'] ?? '') ?>" 
                       placeholder="e.g. 5, 12, 18, 28 (Leave blank if pending confirmation)">
                <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.25rem;">
                    Per legal policy, products with unconfirmed GST cannot be activated or sold.
                </span>
            </div>

            <!-- Image Upload & Preview -->
            <div class="form-group">
                <label class="form-label" for="prod_image">Product Image (JPG, PNG, WebP, max 2MB)</label>
                <input type="file" id="prod_image" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                <?php if (!empty($product['image'])): ?>
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 0.5rem;">
                        <img src="<?= public_url('uploads/products/' . e($product['image'])) ?>" alt="Current Product Image" style="width: 44px; height: 44px; object-fit: contain; border-radius: var(--radius-sm); border: 1px solid var(--border-light); background: #ffffff;">
                        <span style="font-size: 0.75rem; color: var(--text-muted);">Current: <code><?= e($product['image']) ?></code></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Description -->
        <div class="form-group">
            <label class="form-label" for="prod_desc">Wholesale Packaging &amp; Description</label>
            <textarea id="prod_desc" name="description" class="form-control" rows="3" placeholder="Case packing details, bottle specifications, authorized distribution notes..."><?= e($product['description'] ?? $_POST['description'] ?? '') ?></textarea>
        </div>

        <!-- Active Toggle -->
        <div class="form-group" style="background: #f8fafc; padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light); margin-bottom: 1.5rem;">
            <label style="display: flex; align-items: center; gap: 0.6rem; font-weight: 700; cursor: pointer; color: var(--color-primary); margin-bottom: 0;">
                <input type="checkbox" name="is_active" value="1" <?= (!$product || (int)$product['is_active'] === 1) ? 'checked' : '' ?>>
                Active Product (Publish to customer storefront catalogue)
            </label>
            <span style="font-size: 0.75rem; color: var(--text-muted); margin-left: 1.6rem; display: block; margin-top: 0.2rem;">
                Requires confirmed GST % and at least one active size variant to be visible to customers.
            </span>
        </div>

        <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; padding: 0.75rem 2rem;">
            <span><?= $isEditing ? 'Save Product Details' : 'Create Product &amp; Continue' ?></span>
            <?php if (!$isEditing): ?>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
            <?php endif; ?>
        </button>
    </form>
</div>

<!-- Child Variants Management Section (Available once product is created) -->
<?php if ($isEditing): ?>
    <div class="card" style="padding: 2rem;" id="variantsSection">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
            <div>
                <h2 style="font-size: 1.25rem; margin-bottom: 0.25rem;">Pack / Bottle Size Variants (<?= count($variants) ?>)</h2>
                <p style="font-size: 0.8125rem; color: var(--text-muted); margin-bottom: 0;">
                    Define distinct packing sizes, unit rates (INR), and inventory stock quantities for <?= e($product['name']) ?>.
                </p>
            </div>
        </div>

        <!-- Existing Variants Table -->
        <?php if (empty($variants)): ?>
            <div class="alert alert-warning" role="alert" style="margin-bottom: 1.5rem;">
                This product has no size variants yet! Add at least one variant below so customers can select and order it.
            </div>
        <?php else: ?>
            <div style="overflow-x: auto; margin-bottom: 2rem;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color); font-size: 0.8125rem; color: var(--text-muted); text-transform: uppercase;">
                            <th style="padding: 0.75rem 0.5rem;">Size Label</th>
                            <th style="padding: 0.75rem 0.5rem; width: 140px;">Unit Rate (₹)</th>
                            <th style="padding: 0.75rem 0.5rem; width: 120px;">Stock Qty</th>
                            <th style="padding: 0.75rem 0.5rem; width: 150px;">Stock Status</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: center; width: 90px;">Active</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: right; width: 140px;">Save / Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($variants as $v): ?>
                            <tr style="border-bottom: 1px solid var(--border-light); font-size: 0.9375rem;">
                                <form action="<?= admin_url('product-edit.php?id=' . $productId) ?>" method="POST" style="margin: 0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="form_type" value="variant_update">
                                    <input type="hidden" name="variant_id" value="<?= (int)$v['id'] ?>">

                                    <td style="padding: 0.75rem 0.5rem;">
                                        <input type="text" name="size_label" class="form-control" value="<?= e($v['size_label']) ?>" required style="font-size: 0.875rem;">
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <input type="number" step="0.01" min="0.01" name="price" class="form-control" value="<?= (float)$v['price'] ?>" required style="font-size: 0.875rem;">
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <input type="number" min="0" name="stock_qty" class="form-control" value="<?= (int)$v['stock_qty'] ?>" required style="font-size: 0.875rem;">
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <select name="stock_status" class="form-control" style="font-size: 0.875rem;">
                                            <option value="in_stock" <?= $v['stock_status'] === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                                            <option value="out_of_stock" <?= $v['stock_status'] === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                                        </select>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: center;">
                                        <input type="checkbox" name="variant_is_active" value="1" <?= (int)$v['is_active'] === 1 ? 'checked' : '' ?>>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: right; white-space: nowrap;">
                                        <button type="submit" class="btn btn-sm btn-primary" title="Update variant">
                                            Update
                                        </button>
                                </form>
                                        <form action="<?= admin_url('product-edit.php?id=' . $productId) ?>" method="POST" onsubmit="return confirm('Delete this variant?');" style="display: inline-block; margin: 0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="form_type" value="variant_delete">
                                            <input type="hidden" name="variant_id" value="<?= (int)$v['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline" style="color: var(--color-danger); border-color: var(--border-color); font-size: 0.75rem;" title="Delete variant">
                                                &times;
                                            </button>
                                        </form>
                                    </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Add New Variant Form -->
        <div style="background: #f8fafc; border: 1px dashed var(--border-color); border-radius: var(--radius-md); padding: 1.5rem;">
            <h3 style="font-size: 1rem; margin-bottom: 1rem; color: var(--color-primary);">+ Add New Variant Size</h3>

            <form action="<?= admin_url('product-edit.php?id=' . $productId) ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="form_type" value="variant_add">

                <div class="grid" style="grid-template-columns: 2fr 1fr 1fr 1.5fr 1fr auto; gap: 1rem; align-items: flex-end;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.75rem;">Size Label *</label>
                        <input type="text" name="size_label" class="form-control" placeholder="e.g. 500ml Bottle, 70g Pouch" required style="font-size: 0.875rem;">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.75rem;">Unit Rate (₹) *</label>
                        <input type="number" step="0.01" min="0.01" name="price" class="form-control" placeholder="20.00" required style="font-size: 0.875rem;">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.75rem;">Stock Qty</label>
                        <input type="number" min="0" name="stock_qty" class="form-control" value="100" required style="font-size: 0.875rem;">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.75rem;">Stock Status</label>
                        <select name="stock_status" class="form-control" style="font-size: 0.875rem;">
                            <option value="in_stock" selected>In Stock</option>
                            <option value="out_of_stock">Out of Stock</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0; text-align: center;">
                        <label class="form-label" style="font-size: 0.75rem;">Active</label>
                        <input type="checkbox" name="variant_is_active" value="1" checked style="margin-top: 0.6rem;">
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary" style="font-size: 0.875rem; padding: 0.6rem 1.25rem;">
                            Add Variant
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
