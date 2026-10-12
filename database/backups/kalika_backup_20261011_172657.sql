-- =====================================================
-- Kalika Enterprises - Automated Database Backup
-- Generated: 2026-10-11 17:26:57
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `admins`
INSERT INTO `admins` (`id`, `username`, `password_hash`, `last_login`, `created_at`) VALUES ('1', 'admin', '\\.dxXfNJElRgwlhKdh19CrktBPxk6Kp12', NULL, '2026-10-11 22:27:13');

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
INSERT INTO `brands` (`id`, `name`, `slug`, `is_active`, `sort_order`, `created_at`) VALUES ('1', 'Campa', 'campa', '1', '1', '2026-10-11 21:52:18');
INSERT INTO `brands` (`id`, `name`, `slug`, `is_active`, `sort_order`, `created_at`) VALUES ('2', 'Sundar Marie', 'sundar-marie', '1', '2', '2026-10-11 21:52:18');
INSERT INTO `brands` (`id`, `name`, `slug`, `is_active`, `sort_order`, `created_at`) VALUES ('3', 'Sundar Bourbon', 'sundar-bourbon', '1', '3', '2026-10-11 21:52:18');
INSERT INTO `brands` (`id`, `name`, `slug`, `is_active`, `sort_order`, `created_at`) VALUES ('4', 'Sundar Coconut', 'sundar-coconut', '1', '4', '2026-10-11 21:52:18');
INSERT INTO `brands` (`id`, `name`, `slug`, `is_active`, `sort_order`, `created_at`) VALUES ('5', 'Sundar Glucose', 'sundar-glucose', '0', '5', '2026-10-11 21:52:18');
INSERT INTO `brands` (`id`, `name`, `slug`, `is_active`, `sort_order`, `created_at`) VALUES ('6', 'Euro Chips', 'euro-chips', '1', '6', '2026-10-11 21:52:18');
INSERT INTO `brands` (`id`, `name`, `slug`, `is_active`, `sort_order`, `created_at`) VALUES ('7', 'Coca-Cola Range', 'coca-cola-range', '0', '7', '2026-10-11 21:52:18');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


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
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `product_variants`
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('1', '1', '200ml', '10.00', '100', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('2', '1', '500ml', '20.00', '100', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('3', '1', '700ml', '35.00', '100', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('4', '1', '1L', '80.00', '100', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('5', '2', '200ml', '10.00', '100', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('6', '2', '500ml', '20.00', '100', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('7', '2', '700ml', '35.00', '100', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('8', '2', '1L', '80.00', '100', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('9', '3', '200ml', '10.00', '100', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('10', '3', '500ml', '20.00', '100', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('11', '3', '700ml', '35.00', '100', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('12', '3', '1L', '80.00', '100', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('13', '4', '200ml', '10.00', '100', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('14', '4', '500ml', '20.00', '100', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('15', '4', '700ml', '35.00', '100', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('16', '4', '1L', '80.00', '100', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('17', '5', '20g', '5.00', '150', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('18', '5', '45g', '10.00', '150', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('19', '5', '95g', '20.00', '150', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('20', '6', '500g', '65.00', '80', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('21', '7', '20g', '5.00', '100', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('22', '7', '50g', '10.00', '100', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('23', '7', '200g', '30.00', '100', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('24', '7', '400g', '55.00', '100', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('25', '8', '20g', '5.00', '100', 'in_stock', '0', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('26', '8', '45g', '10.00', '100', 'in_stock', '0', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('27', '8', '95g', '20.00', '100', 'in_stock', '0', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('28', '9', '16g', '5.00', '120', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('29', '9', '35g', '10.00', '120', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('30', '9', '70g', '20.00', '120', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('31', '9', '160g', '50.00', '120', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('32', '10', '16g', '5.00', '120', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('33', '10', '35g', '10.00', '120', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('34', '10', '70g', '20.00', '120', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('35', '10', '160g', '50.00', '120', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('36', '11', '16g', '5.00', '120', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('37', '11', '35g', '10.00', '120', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('38', '11', '70g', '20.00', '120', 'in_stock', '1', '2026-10-11 21:52:18');
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES ('39', '11', '160g', '50.00', '120', 'in_stock', '1', '2026-10-11 21:52:18');

-- -----------------------------------------------------
-- Table structure for `products`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `brand_id` int unsigned NOT NULL,
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
  CONSTRAINT `fk_products_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `products`
INSERT INTO `products` (`id`, `brand_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('1', '1', 'Campa Cola', 'campa-cola', NULL, 'Classic Indian cola cold drink with strong fizzy refreshment.', '28.00', '1', '2026-10-11 21:52:18', '2026-10-11 21:52:18');
INSERT INTO `products` (`id`, `brand_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('2', '1', 'Campa Orange', 'campa-orange', NULL, 'Zesty sparkling orange fruit-flavored beverage.', '28.00', '1', '2026-10-11 21:52:18', '2026-10-11 21:52:18');
INSERT INTO `products` (`id`, `brand_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('3', '1', 'Campa Jeera', 'campa-jeera', NULL, 'Digestive spiced jeera soda beverage.', '28.00', '1', '2026-10-11 21:52:18', '2026-10-11 21:52:18');
INSERT INTO `products` (`id`, `brand_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('4', '1', 'Campa Lemon', 'campa-lemon', NULL, 'Crisp citrus clear lemon lime soda.', '28.00', '1', '2026-10-11 21:52:18', '2026-10-11 21:52:18');
INSERT INTO `products` (`id`, `brand_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('5', '2', 'Sundar Marie', 'sundar-marie-biscuit', NULL, 'Crisp, light tea-time Marie biscuits.', '5.00', '1', '2026-10-11 21:52:18', '2026-10-11 21:52:18');
INSERT INTO `products` (`id`, `brand_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('6', '3', 'Sundar Bourbon', 'sundar-bourbon-biscuit', NULL, 'Rich chocolate cream filled Bourbon sandwich biscuits.', '18.00', '1', '2026-10-11 21:52:18', '2026-10-11 21:52:18');
INSERT INTO `products` (`id`, `brand_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('7', '4', 'Sundar Coconut', 'sundar-coconut-biscuit', NULL, 'Crunchy sweet baked biscuits infused with toasted coconut.', '18.00', '1', '2026-10-11 21:52:18', '2026-10-11 21:52:18');
INSERT INTO `products` (`id`, `brand_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('8', '5', 'Sundar Glucose', 'sundar-glucose-biscuit', NULL, 'Classic energy glucose biscuits. (Seeded inactive: GST rate pending confirmation)', NULL, '0', '2026-10-11 21:52:18', '2026-10-11 21:52:18');
INSERT INTO `products` (`id`, `brand_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('9', '6', 'Euro Chips Salted', 'euro-chips-salted', NULL, 'Classic thin cut lightly salted crispy potato chips.', '12.00', '1', '2026-10-11 21:52:18', '2026-10-11 21:52:18');
INSERT INTO `products` (`id`, `brand_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('10', '6', 'Euro Chips Masala', 'euro-chips-masala', NULL, 'Spicy savory chatpata masala seasoned potato chips.', '12.00', '1', '2026-10-11 21:52:18', '2026-10-11 21:52:18');
INSERT INTO `products` (`id`, `brand_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('11', '6', 'Euro Banana Chips', 'euro-banana-chips', NULL, 'Traditional golden yellow savory crispy banana wafers.', '12.00', '1', '2026-10-11 21:52:18', '2026-10-11 21:52:18');
INSERT INTO `products` (`id`, `brand_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('12', '7', 'Thums Up', 'thums-up', NULL, 'Strong carbonated cola beverage. Specifications unconfirmed.', '28.00', '0', '2026-10-11 21:52:18', '2026-10-11 21:52:18');
INSERT INTO `products` (`id`, `brand_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES ('13', '7', 'Sprite', 'sprite', NULL, 'Clear lemon-lime carbonated beverage. Specifications unconfirmed.', NULL, '0', '2026-10-11 21:52:18', '2026-10-11 21:52:18');

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
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('business_address', 'Shop No. 4, Raghukul Sankul, Jopul Road, Pimpalgaon (Baswant), Tal. Niphad, Dist. Nashik, Maharashtra ÔÇô 422201', '2026-10-11 21:52:18');
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
