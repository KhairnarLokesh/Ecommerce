<?php
/**
 * Kalika Enterprises - Database Migration Runner
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

if (!$pdo) {
    echo "ERROR: Database connection failed: " . ($GLOBALS['db_connection_error'] ?? 'Unknown') . "\n";
    exit(1);
}

$action = $argv[1] ?? 'migrate';

if ($action === 'rollback') {
    $rollbackFile = __DIR__ . '/migrations/001_rollback_razorpay_payment_tables.sql';
    echo "Executing rollback: $rollbackFile...\n";
    $sql = file_get_contents($rollbackFile);
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $stmtSql) {
        if (empty($stmtSql)) continue;
        try {
            $pdo->exec($stmtSql);
            echo "Executed: " . substr(preg_replace('/\s+/', ' ', $stmtSql), 0, 60) . "...\n";
        } catch (PDOException $e) {
            echo "Rollback note: " . $e->getMessage() . "\n";
        }
    }
    echo "Rollback completed.\n";
    exit(0);
}

echo "Applying migration 001...\n";

// Helper to check column existence
function has_column(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetch();
}

// 1. Add payment_status
if (!has_column($pdo, 'orders', 'payment_status')) {
    $pdo->exec("ALTER TABLE `orders` ADD COLUMN `payment_status` ENUM('pending', 'paid', 'failed', 'refund_pending', 'partially_refunded', 'refunded') NOT NULL DEFAULT 'pending' AFTER `prices_include_gst`");
    echo "Added column `orders`.`payment_status`.\n";
} else {
    echo "Column `orders`.`payment_status` already exists.\n";
}

// 2. Add payment_method
if (!has_column($pdo, 'orders', 'payment_method')) {
    $pdo->exec("ALTER TABLE `orders` ADD COLUMN `payment_method` ENUM('online', 'cod') NOT NULL DEFAULT 'online' AFTER `payment_status`");
    echo "Added column `orders`.`payment_method`.\n";
} else {
    echo "Column `orders`.`payment_method` already exists.\n";
}

// 3. Add razorpay_order_id
if (!has_column($pdo, 'orders', 'razorpay_order_id')) {
    $pdo->exec("ALTER TABLE `orders` ADD COLUMN `razorpay_order_id` VARCHAR(100) NULL AFTER `payment_method`");
    echo "Added column `orders`.`razorpay_order_id`.\n";
} else {
    echo "Column `orders`.`razorpay_order_id` already exists.\n";
}

// 4. Add paid_at
if (!has_column($pdo, 'orders', 'paid_at')) {
    $pdo->exec("ALTER TABLE `orders` ADD COLUMN `paid_at` TIMESTAMP NULL DEFAULT NULL AFTER `razorpay_order_id`");
    echo "Added column `orders`.`paid_at`.\n";
} else {
    echo "Column `orders`.`paid_at` already exists.\n";
}

// 5. Add expires_at
if (!has_column($pdo, 'orders', 'expires_at')) {
    $pdo->exec("ALTER TABLE `orders` ADD COLUMN `expires_at` TIMESTAMP NULL DEFAULT NULL AFTER `paid_at`");
    echo "Added column `orders`.`expires_at`.\n";
} else {
    echo "Column `orders`.`expires_at` already exists.\n";
}

// 6. Modify status column to include Awaiting Payment and Cancelled
try {
    $pdo->exec("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('Awaiting Payment', 'Placed', 'Dispatched', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Placed'");
    echo "Updated `orders`.`status` enum definition.\n";
} catch (PDOException $e) {
    echo "Note updating orders status: " . $e->getMessage() . "\n";
}

// 7. Add indexes if missing
try {
    $pdo->exec("ALTER TABLE `orders` ADD UNIQUE KEY `uq_orders_razorpay_order_id` (`razorpay_order_id`)");
    echo "Added unique index `uq_orders_razorpay_order_id`.\n";
} catch (PDOException $e) {
    // Index may already exist
}

try {
    $pdo->exec("ALTER TABLE `orders` ADD INDEX `idx_orders_payment_status` (`payment_status`)");
    echo "Added index `idx_orders_payment_status`.\n";
} catch (PDOException $e) {
    // Index may already exist
}

try {
    $pdo->exec("ALTER TABLE `orders` ADD INDEX `idx_orders_expires_at` (`expires_at`)");
    echo "Added index `idx_orders_expires_at`.\n";
} catch (PDOException $e) {
    // Index may already exist
}

// 8. Create payments table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `payments` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT UNSIGNED NOT NULL,
        `razorpay_order_id` VARCHAR(100) NOT NULL,
        `razorpay_payment_id` VARCHAR(100) NOT NULL,
        `amount_paise` BIGINT UNSIGNED NOT NULL,
        `currency` VARCHAR(10) NOT NULL DEFAULT 'INR',
        `status` VARCHAR(50) NOT NULL,
        `method` VARCHAR(50) NULL,
        `signature_verified` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
        `raw_status_from_gateway` TEXT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_payments_razorpay_payment_id` (`razorpay_payment_id`),
        INDEX `idx_payments_order_id` (`order_id`),
        INDEX `idx_payments_razorpay_order_id` (`razorpay_order_id`),
        INDEX `idx_payments_status` (`status`),
        CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "Table `payments` verified.\n";

// 9. Create refunds table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `refunds` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT UNSIGNED NOT NULL,
        `payment_id` INT UNSIGNED NULL,
        `razorpay_refund_id` VARCHAR(100) NOT NULL,
        `amount_paise` BIGINT UNSIGNED NOT NULL,
        `status` VARCHAR(50) NOT NULL,
        `reason` TEXT NULL,
        `requested_by_admin_id` INT UNSIGNED NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_refunds_razorpay_refund_id` (`razorpay_refund_id`),
        INDEX `idx_refunds_order_id` (`order_id`),
        INDEX `idx_refunds_payment_id` (`payment_id`),
        CONSTRAINT `fk_refunds_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_refunds_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "Table `refunds` verified.\n";

// 10. Create webhook_events table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `webhook_events` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `event_id` VARCHAR(100) NOT NULL,
        `event_type` VARCHAR(100) NOT NULL,
        `payload_hash` VARCHAR(64) NOT NULL,
        `processed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `outcome` VARCHAR(50) NOT NULL,
        UNIQUE KEY `uq_webhook_events_event_id` (`event_id`),
        INDEX `idx_webhook_events_type` (`event_type`),
        INDEX `idx_webhook_events_processed` (`processed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "Table `webhook_events` verified.\n";

// 11. Create payment_audit_log table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `payment_audit_log` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT UNSIGNED NULL,
        `payment_id` INT UNSIGNED NULL,
        `action` VARCHAR(100) NOT NULL,
        `actor_type` ENUM('customer', 'admin', 'webhook', 'system') NOT NULL DEFAULT 'system',
        `actor_id` VARCHAR(100) NULL,
        `old_status` VARCHAR(50) NULL,
        `new_status` VARCHAR(50) NULL,
        `details` TEXT NULL,
        `ip` VARCHAR(45) NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_pal_order_id` (`order_id`),
        INDEX `idx_pal_payment_id` (`payment_id`),
        INDEX `idx_pal_action` (`action`),
        INDEX `idx_pal_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "Table `payment_audit_log` verified.\n";

echo "\nMigration 001 executed successfully!\n";
