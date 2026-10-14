-- =====================================================================
-- Kalika Enterprises - Seed Baseline Data (Updated with 18 Catalogue Items & Categories)
-- Engine: MySQL / MariaDB (utf8mb4)
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `product_variants`;
TRUNCATE TABLE `products`;
TRUNCATE TABLE `brands`;
TRUNCATE TABLE `categories`;

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

-- 6. SEED ENTERPRISE OWNER ADMIN USERS (Simple Password: admin123 | must_change_password: 0)
INSERT INTO `admins` (`id`, `username`, `password_hash`, `must_change_password`, `failed_attempts`, `created_at`) VALUES 
(1, 'admin', '$2y$10$saxaqCfCcXTwDeinLGRiluNef5rwb05KWWSpVXQEMlhVf5TulwTRG', 0, 0, NOW()),
(2, 'deepak', '$2y$10$saxaqCfCcXTwDeinLGRiluNef5rwb05KWWSpVXQEMlhVf5TulwTRG', 0, 0, NOW()),
(3, 'deepakpawar', '$2y$10$saxaqCfCcXTwDeinLGRiluNef5rwb05KWWSpVXQEMlhVf5TulwTRG', 0, 0, NOW())
ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`), `must_change_password` = 0;

SET FOREIGN_KEY_CHECKS = 1;
