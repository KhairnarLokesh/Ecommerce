-- =====================================================================
-- Kalika Enterprises - Razorpay Integration Migration (Additive)
-- Migration: 001_add_razorpay_payment_tables.sql
-- =====================================================================

-- 1. Alter Orders Table to add payment & gateway tracking
ALTER TABLE `orders` ADD COLUMN `payment_status` ENUM('pending', 'paid', 'failed', 'refund_pending', 'partially_refunded', 'refunded') NOT NULL DEFAULT 'pending' AFTER `prices_include_gst`;
ALTER TABLE `orders` ADD COLUMN `payment_method` ENUM('online', 'cod') NOT NULL DEFAULT 'online' AFTER `payment_status`;
ALTER TABLE `orders` ADD COLUMN `razorpay_order_id` VARCHAR(100) NULL AFTER `payment_method`;
ALTER TABLE `orders` ADD COLUMN `paid_at` TIMESTAMP NULL DEFAULT NULL AFTER `razorpay_order_id`;
ALTER TABLE `orders` ADD COLUMN `expires_at` TIMESTAMP NULL DEFAULT NULL AFTER `paid_at`;
ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('Awaiting Payment', 'Placed', 'Dispatched', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Placed';
ALTER TABLE `orders` ADD UNIQUE KEY `uq_orders_razorpay_order_id` (`razorpay_order_id`);
ALTER TABLE `orders` ADD INDEX `idx_orders_payment_status` (`payment_status`);
ALTER TABLE `orders` ADD INDEX `idx_orders_expires_at` (`expires_at`);

-- 2. Payments Table
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `razorpay_order_id` VARCHAR(100) NOT NULL,
    `razorpay_payment_id` VARCHAR(100) NOT NULL,
    `amount_paise` BIGINT UNSIGNED NOT NULL COMMENT 'Amount in paise (e.g. 200000 = Rs 2000.00)',
    `currency` VARCHAR(10) NOT NULL DEFAULT 'INR',
    `status` VARCHAR(50) NOT NULL COMMENT 'captured, authorized, failed, refunded, etc.',
    `method` VARCHAR(50) NULL COMMENT 'upi, card, netbanking, wallet',
    `signature_verified` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `raw_status_from_gateway` TEXT NULL COMMENT 'JSON response snapshot from Razorpay',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_payments_razorpay_payment_id` (`razorpay_payment_id`),
    INDEX `idx_payments_order_id` (`order_id`),
    INDEX `idx_payments_razorpay_order_id` (`razorpay_order_id`),
    INDEX `idx_payments_status` (`status`),
    CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Refunds Table
CREATE TABLE IF NOT EXISTS `refunds` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `payment_id` INT UNSIGNED NULL,
    `razorpay_refund_id` VARCHAR(100) NOT NULL,
    `amount_paise` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(50) NOT NULL COMMENT 'pending, processed, failed',
    `reason` TEXT NULL,
    `requested_by_admin_id` INT UNSIGNED NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_refunds_razorpay_refund_id` (`razorpay_refund_id`),
    INDEX `idx_refunds_order_id` (`order_id`),
    INDEX `idx_refunds_payment_id` (`payment_id`),
    CONSTRAINT `fk_refunds_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_refunds_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Webhook Events Table (Idempotent Deduplication)
CREATE TABLE IF NOT EXISTS `webhook_events` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_id` VARCHAR(100) NOT NULL,
    `event_type` VARCHAR(100) NOT NULL,
    `payload_hash` VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash of raw webhook payload body',
    `processed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `outcome` VARCHAR(50) NOT NULL COMMENT 'processed, duplicate, ignored, error',
    UNIQUE KEY `uq_webhook_events_event_id` (`event_id`),
    INDEX `idx_webhook_events_type` (`event_type`),
    INDEX `idx_webhook_events_processed` (`processed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Payment Audit Log Table
CREATE TABLE IF NOT EXISTS `payment_audit_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NULL,
    `payment_id` INT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL COMMENT 'order_created, signature_verified, payment_captured, payment_failed, refund_initiated, refund_processed, status_sync, cod_collected',
    `actor_type` ENUM('customer', 'admin', 'webhook', 'system') NOT NULL DEFAULT 'system',
    `actor_id` VARCHAR(100) NULL,
    `old_status` VARCHAR(50) NULL,
    `new_status` VARCHAR(50) NULL,
    `details` TEXT NULL COMMENT 'JSON payload or narrative note',
    `ip` VARCHAR(45) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pal_order_id` (`order_id`),
    INDEX `idx_pal_payment_id` (`payment_id`),
    INDEX `idx_pal_action` (`action`),
    INDEX `idx_pal_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
