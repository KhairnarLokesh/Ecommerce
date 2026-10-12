-- =====================================================
-- Kalika Enterprises - Automated Database Backup
-- Generated: 2026-10-12 09:43:37
-- Host: localhost | Database: kalika_enterprises
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- -----------------------------------------------------
-- Table structure for `admins`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_admins_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `admins`
INSERT INTO `admins` (`id`, `username`, `password_hash`, `last_login`, `created_at`) VALUES ('1', 'admin', '$2y$10$DC4jmY.5QX.2KKT07WDGDuARQ7jRdWPhdk8R3FFS8SD6g8Bdlb7QS', '2026-10-12 11:04:47', '2026-10-11 22:27:13');

-- -----------------------------------------------------
-- Table structure for `brands`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `brands`;
CREATE TABLE `brands` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint unsigned NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_brands_slug` (`slug`),
  KEY `idx_brands_active_sort` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `brands`
INSERT INTO `brands` (`id`, `name`, `slug`, `is_active`, `sort_order`, `created_at`) VALUES ('1', 'Campa', 'campa', '1', '1', '2026-10-12 15:11:51');
INSERT INTO `brands` (`id`, `name`, `slug`, `is_active`, `sort_order`, `created_at`) VALUES ('2', 'Coca-Cola Range', 'coca-cola-range', '1', '2', '2026-10-12 15:11:51');
INSERT INTO `brands` (`id`, `name`, `slug`, `is_active`, `sort_order`, `created_at`) VALUES ('3', 'PepsiCo Range', 'pepsico', '1', '3', '2026-10-12 15:11:51');
INSERT INTO `brands` (`id`, `name`, `slug`, `is_active`, `sort_order`, `created_at`) VALUES ('4', 'Bisleri', 'bisleri', '1', '4', '2026-10-12 15:11:51');
INSERT INTO `brands` (`id`, `name`, `slug`, `is_active`, `sort_order`, `created_at`) VALUES ('5', 'Oxycool', 'oxycool', '1', '5', '2026-10-12 15:11:51');
INSERT INTO `brands` (`id`, `name`, `slug`, `is_active`, `sort_order`, `created_at`) VALUES ('6', 'Sundar Biscuits', 'sundar-biscuits', '1', '6', '2026-10-12 15:11:51');
INSERT INTO `brands` (`id`, `name`, `slug`, `is_active`, `sort_order`, `created_at`) VALUES ('7', 'Euro Chips', 'euro-chips', '1', '7', '2026-10-12 15:11:51');

-- -----------------------------------------------------
-- Table structure for `cart_items`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `cart_items`;
CREATE TABLE `cart_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `variant_id` int unsigned NOT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_user_variant` (`user_id`,`variant_id`),
  KEY `fk_cart_variant` (`variant_id`),
  KEY `idx_cart_user` (`user_id`),
  CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cart_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------
-- Table structure for `categories`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint unsigned NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_categories_slug` (`slug`),
  KEY `idx_categories_active_sort` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `categories`
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image`, `is_active`, `sort_order`, `created_at`) VALUES ('1', 'Cold Drinks & Soft Drinks', 'cold-drinks', 'Carbonated cola, lemon, orange, and spiced sodas', 'campa-cola.jpg', '1', '1', '2026-10-12 15:11:51');
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image`, `is_active`, `sort_order`, `created_at`) VALUES ('2', 'Juices & Mango Drinks', 'juices', 'Thick mango fruit beverages and refreshing juices', 'maaza-mango.jpg', '1', '2', '2026-10-12 15:11:51');
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image`, `is_active`, `sort_order`, `created_at`) VALUES ('3', 'Packaged Drinking Water', 'packaged-water', 'Pure, mineral-enriched packaged drinking water', 'bisleri-mineral-water.jpg', '1', '3', '2026-10-12 15:11:51');
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image`, `is_active`, `sort_order`, `created_at`) VALUES ('4', 'Biscuits & Bakery', 'biscuits', 'Crispy tea-time Marie, chocolate Bourbon, and Coconut biscuits', 'sunder-marie.jpg', '1', '4', '2026-10-12 15:11:51');
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image`, `is_active`, `sort_order`, `created_at`) VALUES ('5', 'Snacks & Chips', 'snacks', 'Savory Indian chatpata potato chips and wafers', 'euro-chips-masti-masala.jpg', '1', '5', '2026-10-12 15:11:51');

-- -----------------------------------------------------
-- Table structure for `contact_messages`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE `contact_messages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `area` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contact_is_read` (`is_read`),
  KEY `idx_contact_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------
-- Table structure for `login_attempts`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `identifier` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Mobile number or admin username',
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_id_ip` (`identifier`,`ip`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------
-- Table structure for `order_items`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `product_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant_label` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_price` decimal(10,2) NOT NULL COMMENT 'Historical rate at order placement',
  `gst_percent` decimal(5,2) NOT NULL COMMENT 'Historical tax rate applied',
  `quantity` int unsigned NOT NULL,
  `line_total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------
-- Table structure for `orders`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Formatted e.g. KE-YYYYMMDD-XXXX',
  `user_id` int unsigned NOT NULL,
  `customer_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `delivery_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `gst_total` decimal(10,2) NOT NULL,
  `delivery_charge` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Always 0.00 for FREE delivery',
  `grand_total` decimal(10,2) NOT NULL,
  `prices_include_gst` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '1 = Inclusive, 0 = Extra',
  `status` enum('Placed','Dispatched','Delivered') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Placed',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_no` (`order_no`),
  KEY `idx_orders_order_no` (`order_no`),
  KEY `idx_orders_user` (`user_id`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_created` (`created_at`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------
-- Table structure for `password_otps`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `password_otps`;
CREATE TABLE `password_otps` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `otp_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SHA-256 or bcrypt hash of 6-digit OTP',
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `attempts` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otps_user_expires` (`user_id`,`expires_at`),
  CONSTRAINT `fk_otps_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------
-- Table structure for `product_variants`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `product_variants`;
CREATE TABLE `product_variants` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `size_label` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g. 200ml, 500g, 16g',
  `price` decimal(10,2) NOT NULL COMMENT 'Selling rate in INR',
  `stock_qty` int NOT NULL DEFAULT '0',
  `stock_status` enum('in_stock','out_of_stock') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_stock',
  `is_active` tinyint unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_variants_product` (`product_id`),
  KEY `idx_variants_active_status` (`is_active`,`stock_status`),
  CONSTRAINT `fk_variants_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `product_variants`
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('1', '1', '200ml', '10.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('2', '1', '500ml', '20.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('3', '1', '700ml', '35.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('4', '1', '1L', '80.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('5', '2', '200ml', '10.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('6', '2', '500ml', '20.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('7', '2', '700ml', '35.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('8', '2', '1L', '80.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('9', '3', '200ml', '10.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('10', '3', '500ml', '20.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('11', '3', '700ml', '35.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('12', '3', '1L', '80.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('13', '4', '200ml', '10.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('14', '4', '500ml', '20.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('15', '4', '700ml', '35.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('16', '4', '1L', '80.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('17', '5', '250ml', '20.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('18', '5', '750ml', '40.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('19', '5', '1.25L', '65.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('20', '5', '2.25L', '99.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('21', '6', '250ml', '20.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('22', '6', '750ml', '40.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('23', '6', '1.25L', '65.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('24', '6', '2.25L', '99.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('25', '7', '250ml', '20.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('26', '7', '750ml', '40.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('27', '7', '1.25L', '65.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('28', '7', '2.25L', '99.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('29', '8', '250ml', '20.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('30', '8', '750ml', '40.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('31', '8', '1.25L', '65.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('32', '8', '2L', '90.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('33', '9', '150ml', '10.00', '120', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('34', '9', '250ml', '20.00', '120', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('35', '9', '600ml', '40.00', '120', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('36', '9', '1.2L', '75.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('37', '10', '250ml', '20.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('38', '10', '750ml', '40.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('39', '10', '1.25L', '65.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('40', '10', '2.25L', '99.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('41', '11', '250ml', '20.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('42', '11', '750ml', '40.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('43', '11', '1.25L', '65.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('44', '11', '2.25L', '99.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('45', '12', '160ml', '10.00', '120', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('46', '12', '250ml', '20.00', '120', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('47', '12', '600ml', '40.00', '120', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('48', '12', '1.2L', '75.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('49', '13', '250ml', '6.00', '200', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('50', '13', '500ml', '10.00', '200', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('51', '13', '1L', '20.00', '200', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('52', '13', '2L', '30.00', '150', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('53', '14', '500ml', '10.00', '200', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('54', '14', '1L', '20.00', '200', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('55', '14', '2L', '30.00', '150', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('56', '14', '20L', '80.00', '50', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('57', '15', '20g', '5.00', '150', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('58', '15', '45g', '10.00', '150', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('59', '15', '95g', '20.00', '150', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('60', '15', '250g', '35.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('61', '16', '150g', '20.00', '120', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('62', '16', '500g', '65.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('63', '17', '20g', '5.00', '120', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('64', '17', '50g', '10.00', '120', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('65', '17', '200g', '30.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('66', '17', '400g', '55.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('67', '18', '16g', '5.00', '120', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('68', '18', '35g', '10.00', '120', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('69', '18', '70g', '20.00', '120', 'in_stock', '1', '2026-10-12 15:11:51');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('70', '18', '160g', '50.00', '100', 'in_stock', '1', '2026-10-12 15:11:51');

-- -----------------------------------------------------
-- Table structure for `products`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `brand_id` int unsigned NOT NULL,
  `category_id` int unsigned DEFAULT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Relative path to approved photo or NULL for placeholder',
  `description` text COLLATE utf8mb4_unicode_ci,
  `gst_percent` decimal(5,2) DEFAULT NULL COMMENT 'NULL indicates GST pending confirmation',
  `is_active` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '0 if GST pending or unconfirmed',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_products_brand` (`brand_id`),
  KEY `idx_products_slug` (`slug`),
  KEY `idx_products_active` (`is_active`),
  KEY `idx_products_category` (`category_id`),
  CONSTRAINT `fk_products_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `products`
INSERT INTO `products` (`id`, `brand_id`, `category_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('1', '1', '1', 'Campa Cola', 'campa-cola', 'campa-cola.jpg', 'Classic Indian fizzy cola beverage with bold, refreshing carbonation.', '28.00', '1', '2026-10-12 15:11:51', '2026-10-12 15:11:51');
INSERT INTO `products` (`id`, `brand_id`, `category_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('2', '1', '1', 'Campa Orange', 'campa-orange', 'campa-orange.jpg', 'Sparkling tangy orange fruit-flavoured carbonated beverage.', '28.00', '1', '2026-10-12 15:11:51', '2026-10-12 15:11:51');
INSERT INTO `products` (`id`, `brand_id`, `category_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('3', '1', '1', 'Campa Jeera Up', 'campa-jeera', 'campa-jeera.jpg', 'Digestive spiced jeera masala carbonated refreshment soda.', '28.00', '1', '2026-10-12 15:11:51', '2026-10-12 15:11:51');
INSERT INTO `products` (`id`, `brand_id`, `category_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('4', '1', '1', 'Campa Lemon', 'campa-lemon', 'campa-lemon.jpg', 'Crisp citrus lemon-lime carbonated soda drink.', '28.00', '1', '2026-10-12 15:11:51', '2026-10-12 15:11:51');
INSERT INTO `products` (`id`, `brand_id`, `category_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('5', '2', '1', 'Thums Up Cola', 'thums-up-cola', 'thums-up-cola.jpg', 'Iconic strong fizzy cola beverage with intense taste and high carbonation.', '28.00', '1', '2026-10-12 15:11:51', '2026-10-12 15:11:51');
INSERT INTO `products` (`id`, `brand_id`, `category_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('6', '2', '1', 'Sprite Lemon-Lime', 'sprite-lemon-lime', 'sprite-lemon-lime.jpg', 'Clean, crisp lemon-lime sparkling drink for instant refreshment.', '28.00', '1', '2026-10-12 15:11:51', '2026-10-12 15:11:51');
INSERT INTO `products` (`id`, `brand_id`, `category_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('7', '2', '1', 'Fanta Orange', 'fanta-orange', 'fanta-orange.jpg', 'Vibrantly fruity, sweet and bubbly orange-flavoured carbonated soft drink.', '28.00', '1', '2026-10-12 15:11:51', '2026-10-12 15:11:51');
INSERT INTO `products` (`id`, `brand_id`, `category_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('8', '2', '1', 'Limca Lemon Drink', 'limca-lemon', 'limca-lemon.jpg', 'Cloudy lemon-lime carbonated beverage with crisp citrusy punch.', '28.00', '1', '2026-10-12 15:11:51', '2026-10-12 15:11:51');
INSERT INTO `products` (`id`, `brand_id`, `category_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('9', '2', '2', 'Maaza Mango Drink', 'maaza-mango', 'maaza-mango.jpg', 'Made with real Alphonso mango pulp for thick, rich mango indulgence.', '12.00', '1', '2026-10-12 15:11:51', '2026-10-12 15:11:51');
INSERT INTO `products` (`id`, `brand_id`, `category_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('10', '3', '1', 'Mirinda Orange', 'mirinda-orange', 'mirinda-orange.jpg', 'Deliciously intense sparkling orange soda with vibrant fruity bubbles.', '28.00', '1', '2026-10-12 15:11:51', '2026-10-12 15:11:51');
INSERT INTO `products` (`id`, `brand_id`, `category_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('11', '3', '1', '7UP Lemon-Lime', '7up-lemon-lime', '7up-lemon-lime.jpg', 'Clear, crisp bubbly lemon-lime soda with mood-lifting refreshment.', '28.00', '1', '2026-10-12 15:11:51', '2026-10-12 15:11:51');
INSERT INTO `products` (`id`, `brand_id`, `category_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('12', '3', '2', 'Slice Mango Drink', 'slice-mango', 'slice-mango.jpg', 'Thick and tasty mango fruit drink crafted from handpicked mangoes.', '12.00', '1', '2026-10-12 15:11:51', '2026-10-12 15:11:51');
INSERT INTO `products` (`id`, `brand_id`, `category_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('13', '4', '3', 'Bisleri Mineral Water', 'bisleri-mineral-water', 'bisleri-mineral-water.jpg', 'Pure, ozonated packaged drinking water enriched with vital minerals.', '18.00', '1', '2026-10-12 15:11:51', '2026-10-12 15:11:51');
INSERT INTO `products` (`id`, `brand_id`, `category_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('14', '5', '3', 'Oxycool Mineral Water', 'oxycool-mineral-water', 'oxycool-mineral-water.jpg', 'Multi-stage purified packaged drinking water with essential minerals.', '18.00', '1', '2026-10-12 15:11:51', '2026-10-12 15:11:51');
INSERT INTO `products` (`id`, `brand_id`, `category_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('15', '6', '4', 'Sunder Marie Biscuit', 'sunder-marie-biscuit', 'sunder-marie.jpg', 'Crispy, light golden-baked Marie Go Round tea-time biscuits.', '5.00', '1', '2026-10-12 15:11:51', '2026-10-12 15:11:51');
INSERT INTO `products` (`id`, `brand_id`, `category_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('16', '6', '4', 'Sunder Bourbon Biscuit', 'sunder-bourbon-biscuit', 'sunder-bourbon.jpg', 'Rich chocolate cream filled crunchy Bourbon sandwich biscuits with sugar crystal topping.', '18.00', '1', '2026-10-12 15:11:51', '2026-10-12 15:11:51');
INSERT INTO `products` (`id`, `brand_id`, `category_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('17', '6', '4', 'Sunder Coconut Biscuit', 'sunder-coconut-biscuit', 'sunder-coconut.jpg', 'Crunchy baked Super Coconut biscuits infused with aromatic roasted coconut flakes.', '18.00', '1', '2026-10-12 15:11:51', '2026-10-12 15:11:51');
INSERT INTO `products` (`id`, `brand_id`, `category_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('18', '7', '5', 'Euro Chips Masti Masala', 'euro-chips-masti-masala', 'euro-chips-masti-masala.jpg', 'Crispy, thin-sliced potato chips generously dusted with spicy chatpata Indian masala.', '12.00', '1', '2026-10-12 15:11:51', '2026-10-12 15:11:51');

-- -----------------------------------------------------
-- Table structure for `settings`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `settings`
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('business_address', 'Shop No. 4, Raghukul Sankul, Jopul Road, Pimpalgaon (Baswant), Tal. Niphad, Dist. Nashik, Maharashtra - 422201', '2026-10-11 23:36:44');
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('business_email', 'deepakpawar4312@gmail.com', '2026-10-11 21:52:18');
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('business_fssai', '', '2026-10-11 21:52:18');
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('business_gstin', '', '2026-10-11 21:52:18');
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('business_name', 'Kalika Enterprises', '2026-10-11 21:52:18');
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('business_phone', '', '2026-10-11 21:52:18');
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('business_tagline', 'Wholesale Food & Beverage Distribution', '2026-10-11 21:52:18');
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('delivery_charge', '0.00', '2026-10-11 21:52:18');
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('delivery_timeline', '2-3 working days', '2026-10-11 21:52:18');
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('min_order_value', '2000.00', '2026-10-11 21:52:18');
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('order_confirmation_timeline', 'Within 24 hours', '2026-10-11 21:52:18');
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('prices_include_gst', '1', '2026-10-11 21:52:18');
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('refund_policy_days', '2', '2026-10-11 21:52:18');
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('refund_policy_text', 'Returns and refunds are accepted within 2 days of delivery for damaged or expired items subject to inspection. Settlement handled offline.', '2026-10-11 21:52:18');
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('service_areas', 'Niphad, Dindori, Chandwad, Sinnar, Yeola', '2026-10-11 21:52:18');

-- -----------------------------------------------------
-- Table structure for `users`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Contact person or store name',
  `mobile` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '10-digit Indian mobile number',
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional contact email',
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Bcrypt / Argon2 hashed password',
  `address` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Default retail shop delivery address',
  `is_active` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '1 = Active, 0 = Suspended',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mobile` (`mobile`),
  KEY `idx_users_mobile` (`mobile`),
  KEY `idx_users_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;
-- Backup completed successfully.
