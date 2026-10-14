-- =====================================================================
-- Kalika Enterprises - Complete Cloud Database Initialization Script
-- Compatible with: MySQL 8.0, TiDB Cloud Serverless, Aiven MySQL
-- Engine: InnoDB, Charset: utf8mb4
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Users (Retailers / Customers)
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL COMMENT 'Contact person or store name',
    `mobile` VARCHAR(15) NOT NULL UNIQUE COMMENT '10-digit Indian mobile number',
    `email` VARCHAR(150) NULL COMMENT 'Optional contact email',
    `password_hash` VARCHAR(255) NOT NULL COMMENT 'Bcrypt / Argon2 hashed password',
    `address` TEXT NOT NULL COMMENT 'Default retail shop delivery address',
    `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = Active, 0 = Suspended',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_mobile` (`mobile`),
    INDEX `idx_users_is_active` (`is_active`),
    UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    
-- 2. Admins (Store Management / Single-Owner Authentication)
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `must_change_password` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `failed_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` DATETIME NULL DEFAULT NULL,
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_admins_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Categories
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `image` TEXT NULL,
    `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_categories_slug` (`slug`),
    INDEX `idx_categories_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Brands
DROP TABLE IF EXISTS `brands`;
CREATE TABLE `brands` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_brands_slug` (`slug`),
    INDEX `idx_brands_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Products
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `brand_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NULL,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(150) NOT NULL UNIQUE,
    `image` TEXT NULL COMMENT 'Relative path to approved photo or external CDN URL',
    `description` TEXT NULL,
    `gst_percent` DECIMAL(5,2) NULL COMMENT 'NULL indicates GST pending confirmation',
    `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0 if GST pending or unconfirmed',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_products_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
    INDEX `idx_products_brand` (`brand_id`),
    INDEX `idx_products_category` (`category_id`),
    INDEX `idx_products_slug` (`slug`),
    INDEX `idx_products_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Product Variants (Pack sizes, bottles, pouches)
DROP TABLE IF EXISTS `product_variants`;
CREATE TABLE `product_variants` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `size_label` VARCHAR(100) NOT NULL COMMENT 'e.g. 200ml, 500g, 16g',
    `price` DECIMAL(10,2) NOT NULL COMMENT 'Selling rate in INR',
    `stock_qty` INT NOT NULL DEFAULT 0,
    `stock_status` ENUM('in_stock','out_of_stock') NOT NULL DEFAULT 'in_stock',
    `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_variants_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    INDEX `idx_variants_product` (`product_id`),
    INDEX `idx_variants_active_status` (`is_active`, `stock_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Cart Items (Persistent Customer Shopping Cart)
DROP TABLE IF EXISTS `cart_items`;
CREATE TABLE `cart_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `variant_id` INT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_cart_user_variant` (`user_id`, `variant_id`),
    CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cart_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE,
    INDEX `idx_cart_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Orders
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_no` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Formatted e.g. KE-YYYYMMDD-XXXX',
    `user_id` INT UNSIGNED NOT NULL,
    `customer_name` VARCHAR(100) NOT NULL,
    `mobile` VARCHAR(15) NOT NULL,
    `delivery_address` TEXT NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `gst_total` DECIMAL(10,2) NOT NULL,
    `delivery_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Always 0.00 for FREE delivery',
    `grand_total` DECIMAL(10,2) NOT NULL,
    `prices_include_gst` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = Inclusive, 0 = Extra',
    `status` ENUM('Placed','Dispatched','Delivered') NOT NULL DEFAULT 'Placed',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
    INDEX `idx_orders_order_no` (`order_no`),
    INDEX `idx_orders_user` (`user_id`),
    INDEX `idx_orders_status` (`status`),
    INDEX `idx_orders_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Order Items (Immutable Price & Product Snapshot)
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `product_name` VARCHAR(150) NOT NULL,
    `brand_name` VARCHAR(100) NOT NULL,
    `variant_label` VARCHAR(100) NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL COMMENT 'Historical rate at order placement',
    `gst_percent` DECIMAL(5,2) NOT NULL COMMENT 'Historical tax rate applied',
    `quantity` INT UNSIGNED NOT NULL,
    `line_total` DECIMAL(10,2) NOT NULL,
    CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    INDEX `idx_order_items_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Password OTPs (Demo OTP System)
DROP TABLE IF EXISTS `password_otps`;
CREATE TABLE `password_otps` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `otp_hash` VARCHAR(255) NOT NULL COMMENT 'SHA-256 or bcrypt hash of 6-digit OTP',
    `expires_at` TIMESTAMP NOT NULL,
    `used_at` TIMESTAMP NULL DEFAULT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_otps_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_otps_user_expires` (`user_id`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Login Attempts (Brute Force Protection & Rate Limiting)
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `identifier` VARCHAR(100) NOT NULL COMMENT 'Mobile number or admin username',
    `ip` VARCHAR(45) NOT NULL,
    `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_attempts_id_ip` (`identifier`, `ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Settings (Global Business & Operational Parameters)
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `setting_key` VARCHAR(100) PRIMARY KEY,
    `setting_value` TEXT NOT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Contact Messages (Public Inquiries Desk)
DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE `contact_messages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `mobile` VARCHAR(15) NOT NULL,
    `area` VARCHAR(100) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_contact_is_read` (`is_read`),
    INDEX `idx_contact_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- POPULATE SEED DATA
-- =====================================================================

-- 1. SEED CATEGORIES
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image`, `is_active`, `sort_order`) VALUES
(1, 'Cold Drinks & Soft Drinks', 'cold-drinks', 'Carbonated cola, lemon, orange, and spiced sodas', 'campa-cola.jpg', 1, 1),
(2, 'Juices & Mango Drinks', 'juices', 'Thick mango fruit beverages and refreshing juices', 'maaza-mango.jpg', 1, 2),
(3, 'Packaged Drinking Water', 'packaged-water', 'Pure, mineral-enriched packaged drinking water', 'bisleri-mineral-water.jpg', 1, 3),
(4, 'Biscuits & Bakery', 'biscuits', 'Crispy tea-time Marie, chocolate Bourbon, and Coconut biscuits', 'sunder-marie.jpg', 1, 4),
(5, 'Snacks & Chips', 'snacks', 'Savory Indian chatpata potato chips and wafers', 'euro-chips-masti-masala.jpg', 1, 5);

-- 2. SEED BRANDS
INSERT INTO `brands` (`id`, `name`, `slug`, `is_active`, `sort_order`) VALUES
(1, 'Campa', 'campa', 1, 1),
(2, 'Coca-Cola Range', 'coca-cola-range', 1, 2),
(3, 'PepsiCo Range', 'pepsico', 1, 3),
(4, 'Bisleri', 'bisleri', 1, 4),
(5, 'Oxycool', 'oxycool', 1, 5),
(6, 'Sundar Biscuits', 'sundar-biscuits', 1, 6),
(7, 'Euro Chips', 'euro-chips', 1, 7);

-- 3. SEED PRODUCTS (18 Products)
INSERT INTO `products` (`id`, `brand_id`, `category_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`) VALUES
(1, 1, 1, 'Campa Cola', 'campa-cola', 'campa-cola.jpg', 'Classic Indian fizzy cola beverage with bold, refreshing carbonation.', 28.00, 1),
(2, 1, 1, 'Campa Orange', 'campa-orange', 'campa-orange.jpg', 'Sparkling tangy orange fruit-flavoured carbonated beverage.', 28.00, 1),
(3, 1, 1, 'Campa Jeera Up', 'campa-jeera', 'campa-jeera.jpg', 'Digestive spiced jeera masala carbonated refreshment soda.', 28.00, 1),
(4, 1, 1, 'Campa Lemon', 'campa-lemon', 'campa-lemon.jpg', 'Crisp citrus lemon-lime carbonated soda drink.', 28.00, 1),
(5, 2, 1, 'Thums Up Cola', 'thums-up-cola', 'thums-up-cola.jpg', 'Iconic strong fizzy cola beverage with intense taste and high carbonation.', 28.00, 1),
(6, 2, 1, 'Sprite Lemon-Lime', 'sprite-lemon-lime', 'sprite-lemon-lime.jpg', 'Clean, crisp lemon-lime sparkling drink for instant refreshment.', 28.00, 1),
(7, 2, 1, 'Fanta Orange', 'fanta-orange', 'fanta-orange.jpg', 'Vibrantly fruity, sweet and bubbly orange-flavoured carbonated soft drink.', 28.00, 1),
(8, 2, 1, 'Limca Lemon Drink', 'limca-lemon', 'limca-lemon.jpg', 'Cloudy lemon-lime carbonated beverage with crisp citrusy punch.', 28.00, 1),
(9, 2, 2, 'Maaza Mango Drink', 'maaza-mango', 'maaza-mango.jpg', 'Made with real Alphonso mango pulp for thick, rich mango indulgence.', 12.00, 1),
(10, 3, 1, 'Mirinda Orange', 'mirinda-orange', 'mirinda-orange.jpg', 'Deliciously intense sparkling orange soda with vibrant fruity bubbles.', 28.00, 1),
(11, 3, 1, '7UP Lemon-Lime', '7up-lemon-lime', '7up-lemon-lime.jpg', 'Clear, crisp bubbly lemon-lime soda with mood-lifting refreshment.', 28.00, 1),
(12, 3, 2, 'Slice Mango Drink', 'slice-mango', 'slice-mango.jpg', 'Thick and tasty mango fruit drink crafted from handpicked mangoes.', 12.00, 1),
(13, 4, 3, 'Bisleri Mineral Water', 'bisleri-mineral-water', 'bisleri-mineral-water.jpg', 'Pure, ozonated packaged drinking water enriched with vital minerals.', 18.00, 1),
(14, 5, 3, 'Oxycool Mineral Water', 'oxycool-mineral-water', 'oxycool-mineral-water.jpg', 'Multi-stage purified packaged drinking water with essential minerals.', 18.00, 1),
(15, 6, 4, 'Sunder Marie Biscuit', 'sunder-marie-biscuit', 'sunder-marie.jpg', 'Crispy, light golden-baked Marie Go Round tea-time biscuits.', 5.00, 1),
(16, 6, 4, 'Sunder Bourbon Biscuit', 'sunder-bourbon-biscuit', 'sunder-bourbon.jpg', 'Rich chocolate cream filled crunchy Bourbon sandwich biscuits with sugar crystal topping.', 18.00, 1),
(17, 6, 4, 'Sunder Coconut Biscuit', 'sunder-coconut-biscuit', 'sunder-coconut.jpg', 'Crunchy baked Super Coconut biscuits infused with aromatic roasted coconut flakes.', 18.00, 1),
(18, 7, 5, 'Euro Chips Masti Masala', 'euro-chips-masti-masala', 'euro-chips-masti-masala.jpg', 'Crispy, thin-sliced potato chips generously dusted with spicy chatpata Indian masala.', 12.00, 1);

-- 4. SEED PRODUCT VARIANTS
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`) VALUES
(1, 1, '200ml', 10.00, 100, 'in_stock', 1),
(2, 1, '500ml', 20.00, 100, 'in_stock', 1),
(3, 1, '700ml', 35.00, 100, 'in_stock', 1),
(4, 1, '1L', 80.00, 100, 'in_stock', 1),
(5, 2, '200ml', 10.00, 100, 'in_stock', 1),
(6, 2, '500ml', 20.00, 100, 'in_stock', 1),
(7, 2, '700ml', 35.00, 100, 'in_stock', 1),
(8, 2, '1L', 80.00, 100, 'in_stock', 1),
(9, 3, '200ml', 10.00, 100, 'in_stock', 1),
(10, 3, '500ml', 20.00, 100, 'in_stock', 1),
(11, 3, '700ml', 35.00, 100, 'in_stock', 1),
(12, 3, '1L', 80.00, 100, 'in_stock', 1),
(13, 4, '200ml', 10.00, 100, 'in_stock', 1),
(14, 4, '500ml', 20.00, 100, 'in_stock', 1),
(15, 4, '700ml', 35.00, 100, 'in_stock', 1),
(16, 4, '1L', 80.00, 100, 'in_stock', 1),
(17, 5, '250ml', 20.00, 100, 'in_stock', 1),
(18, 5, '750ml', 40.00, 100, 'in_stock', 1),
(19, 5, '1.25L', 65.00, 100, 'in_stock', 1),
(20, 5, '2.25L', 99.00, 100, 'in_stock', 1),
(21, 6, '250ml', 20.00, 100, 'in_stock', 1),
(22, 6, '750ml', 40.00, 100, 'in_stock', 1),
(23, 6, '1.25L', 65.00, 100, 'in_stock', 1),
(24, 6, '2.25L', 99.00, 100, 'in_stock', 1),
(25, 7, '250ml', 20.00, 100, 'in_stock', 1),
(26, 7, '750ml', 40.00, 100, 'in_stock', 1),
(27, 7, '1.25L', 65.00, 100, 'in_stock', 1),
(28, 7, '2.25L', 99.00, 100, 'in_stock', 1),
(29, 8, '250ml', 20.00, 100, 'in_stock', 1),
(30, 8, '750ml', 40.00, 100, 'in_stock', 1),
(31, 8, '1.25L', 65.00, 100, 'in_stock', 1),
(32, 8, '2L', 90.00, 100, 'in_stock', 1),
(33, 9, '150ml', 10.00, 120, 'in_stock', 1),
(34, 9, '250ml', 20.00, 120, 'in_stock', 1),
(35, 9, '600ml', 40.00, 120, 'in_stock', 1),
(36, 9, '1.2L', 75.00, 100, 'in_stock', 1),
(37, 10, '250ml', 20.00, 100, 'in_stock', 1),
(38, 10, '750ml', 40.00, 100, 'in_stock', 1),
(39, 10, '1.25L', 65.00, 100, 'in_stock', 1),
(40, 10, '2.25L', 99.00, 100, 'in_stock', 1),
(41, 11, '250ml', 20.00, 100, 'in_stock', 1),
(42, 11, '750ml', 40.00, 100, 'in_stock', 1),
(43, 11, '1.25L', 65.00, 100, 'in_stock', 1),
(44, 11, '2.25L', 99.00, 100, 'in_stock', 1),
(45, 12, '160ml', 10.00, 120, 'in_stock', 1),
(46, 12, '250ml', 20.00, 120, 'in_stock', 1),
(47, 12, '600ml', 40.00, 120, 'in_stock', 1),
(48, 12, '1.2L', 75.00, 100, 'in_stock', 1),
(49, 13, '250ml', 6.00, 200, 'in_stock', 1),
(50, 13, '500ml', 10.00, 200, 'in_stock', 1),
(51, 13, '1L', 20.00, 200, 'in_stock', 1),
(52, 13, '2L', 30.00, 150, 'in_stock', 1),
(53, 14, '500ml', 10.00, 200, 'in_stock', 1),
(54, 14, '1L', 20.00, 200, 'in_stock', 1),
(55, 14, '2L', 30.00, 150, 'in_stock', 1),
(56, 14, '20L', 80.00, 50, 'in_stock', 1),
(57, 15, '20g', 5.00, 150, 'in_stock', 1),
(58, 15, '45g', 10.00, 150, 'in_stock', 1),
(59, 15, '95g', 20.00, 150, 'in_stock', 1),
(60, 15, '250g', 35.00, 100, 'in_stock', 1),
(61, 16, '150g', 20.00, 120, 'in_stock', 1),
(62, 16, '500g', 65.00, 100, 'in_stock', 1),
(63, 17, '20g', 5.00, 120, 'in_stock', 1),
(64, 17, '50g', 10.00, 120, 'in_stock', 1),
(65, 17, '200g', 30.00, 100, 'in_stock', 1),
(66, 17, '400g', 55.00, 100, 'in_stock', 1),
(67, 18, '16g', 5.00, 120, 'in_stock', 1),
(68, 18, '35g', 10.00, 120, 'in_stock', 1),
(69, 18, '70g', 20.00, 120, 'in_stock', 1),
(70, 18, '160g', 50.00, 100, 'in_stock', 1);

-- 13. Admin Login Log (Authentication Audit & Lockout Tracking)
DROP TABLE IF EXISTS `admin_login_log`;
CREATE TABLE `admin_login_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT UNSIGNED NULL,
    `username_attempted` VARCHAR(100) NOT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'failed' COMMENT 'success, failed, locked',
    `user_agent` VARCHAR(255) NULL,
    `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_admin_login_ip` (`ip`),
    INDEX `idx_admin_login_username` (`username_attempted`),
    INDEX `idx_admin_login_status` (`status`),
    INDEX `idx_admin_login_time` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Admin Audit Log (Activity Trail & Compliance)
DROP TABLE IF EXISTS `admin_audit_log`;
CREATE TABLE `admin_audit_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT UNSIGNED NOT NULL,
    `action` VARCHAR(50) NOT NULL COMMENT 'create, update, delete, password_change',
    `entity` VARCHAR(50) NOT NULL COMMENT 'product, variant, price, gst, stock, order_status, settings, admin',
    `entity_id` VARCHAR(100) NULL,
    `old_value` TEXT NULL,
    `new_value` TEXT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_admin` (`admin_id`),
    INDEX `idx_audit_entity` (`entity`, `entity_id`),
    INDEX `idx_audit_action` (`action`),
    INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. SEED SETTINGS
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('business_name', 'Kalika Enterprises'),
('business_tagline', 'Wholesale Food & Beverage Distribution'),
('business_address', 'Shop No. 4, Raghukul Sankul, Jopul Road, Pimpalgaon (Baswant), Tal. Niphad, Dist. Nashik, Maharashtra – 422201'),
('business_email', 'deepakpawar4312@gmail.com'),
('business_phone', ''),
('service_areas', 'Niphad, Dindori, Chandwad, Sinnar, Yeola'),
('business_gstin', ''),
('business_fssai', ''),
('min_order_value', '2000.00'),
('delivery_charge', '0.00'),
('prices_include_gst', '1'),
('delivery_timeline', '2-3 working days'),
('order_confirmation_timeline', 'Within 24 hours'),
('refund_policy_days', '2'),
('refund_policy_text', 'Returns and refunds are accepted within 2 days of delivery for damaged or expired items subject to inspection. Settlement handled offline.')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- 6. SEED DEFAULT ADMIN USER (Username: admin | Password: Admin@Kalika2026 | must_change_password: 1)
INSERT INTO `admins` (`id`, `username`, `password_hash`, `must_change_password`, `failed_attempts`, `created_at`) VALUES 
(1, 'admin', '$2y$10$DC4jmY.5QX.2KKT07WDGDuARQ7jRdWPhdk8R3FFS8SD6g8Bdlb7QS', 1, 0, NOW())
ON DUPLICATE KEY UPDATE `username` = 'admin';

SET FOREIGN_KEY_CHECKS = 1;
