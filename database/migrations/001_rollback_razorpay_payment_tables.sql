-- =====================================================================
-- Kalika Enterprises - Razorpay Integration Rollback Script
-- Migration: 001_rollback_razorpay_payment_tables.sql
-- =====================================================================

-- Drop newly added tables in reverse foreign key order
DROP TABLE IF EXISTS `payment_audit_log`;
DROP TABLE IF EXISTS `webhook_events`;
DROP TABLE IF EXISTS `refunds`;
DROP TABLE IF EXISTS `payments`;

-- Revert columns on orders table
ALTER TABLE `orders`
    DROP INDEX `uq_orders_razorpay_order_id`,
    DROP INDEX `idx_orders_payment_status`,
    DROP INDEX `idx_orders_expires_at`,
    DROP COLUMN `expires_at`,
    DROP COLUMN `paid_at`,
    DROP COLUMN `razorpay_order_id`,
    DROP COLUMN `payment_method`,
    DROP COLUMN `payment_status`,
    MODIFY COLUMN `status` ENUM('Placed', 'Dispatched', 'Delivered') NOT NULL DEFAULT 'Placed';
