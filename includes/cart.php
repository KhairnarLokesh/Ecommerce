<?php
/**
 * Kalika Enterprises - Cart & Calculation Engine
 * Handles persistent authenticated cart, guest session cart, GST breakup, and server-side validation.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

/**
 * Fetch complete cart data with current live prices, variant details, and calculated totals.
 *
 * @param PDO|null $pdo Database connection
 * @return array{
 *   items: array<int, array<string, mixed>>,
 *   subtotal: float,
 *   gst_total: float,
 *   delivery_charge: float,
 *   grand_total: float,
 *   item_count: int,
 *   prices_include_gst: bool,
 *   is_min_reached: bool,
 *   difference_to_min: float
 * }
 */
/**
 * Synchronize guest cart state with an HTTP cookie for serverless persistence.
 */
function cart_sync_cookie(array $cart): void
{
    $filtered = [];
    foreach ($cart as $k => $v) {
        $k = (int)$k;
        $v = (int)$v;
        if ($k > 0 && $v > 0) {
            $filtered[$k] = $v;
        }
    }
    $json = json_encode($filtered);
    $expires = time() + (86400 * 30); // 30 days
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if (!headers_sent()) {
        setcookie('kalika_cart', $json, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    $_COOKIE['kalika_cart'] = $json;
}

/**
 * Fetch complete cart data with current live prices, variant details, and calculated totals.
 * Supports active MySQL PDO connection as well as static fallback catalogue on serverless environments.
 *
 * @param PDO|null $pdo Database connection
 * @return array{
 *   items: array<int, array<string, mixed>>,
 *   subtotal: float,
 *   gst_total: float,
 *   delivery_charge: float,
 *   grand_total: float,
 *   item_count: int,
 *   prices_include_gst: bool,
 *   is_min_reached: bool,
 *   difference_to_min: float
 * }
 */
function cart_get_details(?PDO $pdo = null): array
{
    if ($pdo === null && function_exists('get_db_connection')) {
        $pdo = get_db_connection();
    }

    $minOrder = (float)get_setting('min_order_value', '2000.00');
    $includeGst = (get_setting('prices_include_gst', '1') === '1');

    $items = [];
    $rawCart = [];

    // 1. Collect variant IDs and requested quantities
    if (is_logged_in() && $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT variant_id, quantity FROM cart_items WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $rows = $stmt->fetchAll();
            foreach ($rows as $r) {
                $rawCart[(int)$r['variant_id']] = (int)$r['quantity'];
            }
        } catch (PDOException $e) {
            error_log("Failed to fetch cart_items: " . $e->getMessage());
        }
    } else {
        $rawCart = $_SESSION['cart'] ?? [];
        if (empty($rawCart) && !empty($_COOKIE['kalika_cart'])) {
            $cookieCart = json_decode($_COOKIE['kalika_cart'], true);
            if (is_array($cookieCart)) {
                $rawCart = $cookieCart;
                $_SESSION['cart'] = $rawCart;
            }
        }
    }

    $subtotal = 0.0;
    $gstTotal = 0.0;
    $totalQty = 0;

    // 2. Fetch live data for in-cart variants
    if (!empty($rawCart)) {
        if ($pdo) {
            $variantIds = array_keys($rawCart);
            $inClause = implode(',', array_fill(0, count($variantIds), '?'));

            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        v.id AS variant_id,
                        v.size_label,
                        v.price,
                        v.stock_qty,
                        v.stock_status,
                        v.is_active AS variant_active,
                        p.id AS product_id,
                        p.name AS product_name,
                        p.image AS product_image,
                        p.gst_percent,
                        p.is_active AS product_active,
                        b.name AS brand_name,
                        b.is_active AS brand_active
                    FROM product_variants v
                    INNER JOIN products p ON p.id = v.product_id
                    INNER JOIN brands b ON b.id = p.brand_id
                    WHERE v.id IN ($inClause)
                ");
                $stmt->execute($variantIds);
                $dbRows = $stmt->fetchAll();

                foreach ($dbRows as $row) {
                    $varId = (int)$row['variant_id'];
                    $qty = max(1, min(999, (int)($rawCart[$varId] ?? 1)));
                    $unitPrice = (float)$row['price'];
                    $gstRate = (float)($row['gst_percent'] ?? 0.0);
                    $lineTotal = $unitPrice * $qty;

                    if ($includeGst) {
                        $baseAmount = $lineTotal / (1 + ($gstRate / 100));
                        $itemGst = $lineTotal - $baseAmount;
                    } else {
                        $itemGst = $lineTotal * ($gstRate / 100);
                    }

                    $gstTotal += $itemGst;
                    $subtotal += $lineTotal;
                    $totalQty += $qty;

                    $items[] = [
                        'variant_id'     => $varId,
                        'product_id'     => (int)$row['product_id'],
                        'product_name'   => $row['product_name'],
                        'brand_name'     => $row['brand_name'],
                        'size_label'     => $row['size_label'],
                        'unit_price'     => $unitPrice,
                        'gst_percent'    => $gstRate,
                        'quantity'       => $qty,
                        'line_total'     => $lineTotal,
                        'item_gst'       => $itemGst,
                        'stock_qty'      => (int)$row['stock_qty'],
                        'stock_status'   => $row['stock_status'],
                        'is_available'   => ((int)$row['variant_active'] === 1 && (int)$row['product_active'] === 1 && (int)$row['brand_active'] === 1 && $row['stock_status'] === 'in_stock'),
                        'product_image'  => $row['product_image']
                    ];
                }
            } catch (PDOException $e) {
                error_log("Failed to load cart variant details: " . $e->getMessage());
            }
        } elseif (function_exists('get_catalog_fallback')) {
            // Serverless fallback using verified catalog snapshot
            $fallback = get_catalog_fallback();
            $allVariants = [];
            foreach ($fallback['products'] ?? [] as $prod) {
                foreach ($prod['variants'] ?? [] as $v) {
                    $v['product_id'] = $prod['id'];
                    $v['product_name'] = $prod['name'];
                    $v['brand_name'] = $prod['brand_name'] ?? 'Kalika Enterprises';
                    $v['gst_percent'] = $prod['gst_percent'] ?? 0.0;
                    $v['product_image'] = $prod['image'] ?? '';
                    $v['variant_active'] = $v['is_active'] ?? 1;
                    $v['product_active'] = $prod['is_active'] ?? 1;
                    $v['brand_active'] = 1;
                    $allVariants[(int)$v['id']] = $v;
                }
            }

            foreach ($rawCart as $varId => $qty) {
                $varId = (int)$varId;
                if (!isset($allVariants[$varId])) {
                    continue;
                }
                $row = $allVariants[$varId];
                $qty = max(1, min(999, (int)$qty));
                $unitPrice = (float)$row['price'];
                $gstRate = (float)($row['gst_percent'] ?? 0.0);
                $lineTotal = $unitPrice * $qty;

                if ($includeGst) {
                    $baseAmount = $lineTotal / (1 + ($gstRate / 100));
                    $itemGst = $lineTotal - $baseAmount;
                } else {
                    $itemGst = $lineTotal * ($gstRate / 100);
                }

                $gstTotal += $itemGst;
                $subtotal += $lineTotal;
                $totalQty += $qty;

                $items[] = [
                    'variant_id'     => $varId,
                    'product_id'     => (int)$row['product_id'],
                    'product_name'   => $row['product_name'],
                    'brand_name'     => $row['brand_name'],
                    'size_label'     => $row['size_label'],
                    'unit_price'     => $unitPrice,
                    'gst_percent'    => $gstRate,
                    'quantity'       => $qty,
                    'line_total'     => $lineTotal,
                    'item_gst'       => $itemGst,
                    'stock_qty'      => (int)($row['stock_qty'] ?? 100),
                    'stock_status'   => $row['stock_status'] ?? 'in_stock',
                    'is_available'   => ($row['stock_status'] === 'in_stock'),
                    'product_image'  => $row['product_image']
                ];
            }
        }
    }

    $grandTotal = $includeGst ? $subtotal : ($subtotal + $gstTotal);
    $deliveryCharge = 0.00; // Always FREE on wholesale distribution orders

    return [
        'items'              => $items,
        'subtotal'           => round($subtotal, 2),
        'gst_total'          => round($gstTotal, 2),
        'delivery_charge'    => $deliveryCharge,
        'grand_total'        => round($grandTotal, 2),
        'item_count'         => $totalQty,
        'prices_include_gst' => $includeGst,
        'is_min_reached'     => ($subtotal >= $minOrder),
        'difference_to_min'  => max(0.0, round($minOrder - $subtotal, 2))
    ];
}

/**
 * Add a variant item to the cart (handles both authenticated DB cart and guest session/cookie cart).
 *
 * @param PDO|null $pdo Database connection (optional)
 * @param int $variantId
 * @param int $quantity
 * @return bool True if successfully added
 */
function cart_add(?PDO $pdo, int $variantId, int $quantity = 1): bool
{
    $quantity = max(1, min(999, $quantity));
    $isValid = false;

    if ($pdo) {
        $checkStmt = $pdo->prepare("
            SELECT v.id, v.stock_status
            FROM product_variants v
            INNER JOIN products p ON p.id = v.product_id
            INNER JOIN brands b ON b.id = p.brand_id
            WHERE v.id = ? 
              AND v.is_active = 1 
              AND p.is_active = 1 
              AND p.gst_percent IS NOT NULL 
              AND b.is_active = 1
            LIMIT 1
        ");
        $checkStmt->execute([$variantId]);
        $variant = $checkStmt->fetch();

        if ($variant && $variant['stock_status'] === 'in_stock') {
            $isValid = true;
        }
    } elseif (function_exists('get_catalog_fallback')) {
        $fallback = get_catalog_fallback();
        foreach ($fallback['products'] ?? [] as $prod) {
            foreach ($prod['variants'] ?? [] as $v) {
                if ((int)$v['id'] === $variantId) {
                    if (($v['stock_status'] ?? '') === 'in_stock') {
                        $isValid = true;
                    }
                    break 2;
                }
            }
        }
    }

    if (!$isValid) {
        return false;
    }

    if (is_logged_in() && $pdo) {
        $userId = (int)$_SESSION['user_id'];
        $stmt = $pdo->prepare("
            INSERT INTO cart_items (user_id, variant_id, quantity)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = LEAST(999, quantity + VALUES(quantity))
        ");
        $stmt->execute([$userId, $variantId, $quantity]);
    }

    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $current = (int)($_SESSION['cart'][$variantId] ?? 0);
    $_SESSION['cart'][$variantId] = min(999, $current + $quantity);

    // Persist in cookie for serverless lambda continuity
    cart_sync_cookie($_SESSION['cart']);

    return true;
}

/**
 * Update the quantity of an item in the cart.
 */
function cart_update(?PDO $pdo, int $variantId, int $quantity): void
{
    if ($quantity <= 0) {
        cart_remove($pdo, $variantId);
        return;
    }

    $quantity = min(999, $quantity);

    if (is_logged_in() && $pdo) {
        $userId = (int)$_SESSION['user_id'];
        $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE user_id = ? AND variant_id = ?");
        $stmt->execute([$quantity, $userId, $variantId]);
    }

    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $_SESSION['cart'][$variantId] = $quantity;
    cart_sync_cookie($_SESSION['cart']);
}

/**
 * Remove an item from the cart.
 */
function cart_remove(?PDO $pdo, int $variantId): void
{
    if (is_logged_in() && $pdo) {
        $userId = (int)$_SESSION['user_id'];
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ? AND variant_id = ?");
        $stmt->execute([$userId, $variantId]);
    }

    if (isset($_SESSION['cart'][$variantId])) {
        unset($_SESSION['cart'][$variantId]);
    }
    if (!empty($_COOKIE['kalika_cart'])) {
        $c = json_decode($_COOKIE['kalika_cart'], true);
        if (is_array($c) && isset($c[$variantId])) {
            unset($c[$variantId]);
            cart_sync_cookie($c);
        }
    }
}

/**
 * Clear the entire cart.
 */
function cart_clear(?PDO $pdo): void
{
    if (is_logged_in() && $pdo) {
        $userId = (int)$_SESSION['user_id'];
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
        $stmt->execute([$userId]);
    }
    unset($_SESSION['cart']);
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if (!headers_sent()) {
        setcookie('kalika_cart', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    unset($_COOKIE['kalika_cart']);
}

/**
 * Merge guest session cart into database cart on customer login.
 */
function cart_merge_on_login(PDO $pdo, int $userId): void
{
    if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        return;
    }

    foreach ($_SESSION['cart'] as $variantId => $qty) {
        $variantId = (int)$variantId;
        $qty = max(1, min(999, (int)$qty));

        $stmt = $pdo->prepare("
            INSERT INTO cart_items (user_id, variant_id, quantity)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = LEAST(999, quantity + VALUES(quantity))
        ");
        $stmt->execute([$userId, $variantId, $qty]);
    }

    unset($_SESSION['cart']);
}
