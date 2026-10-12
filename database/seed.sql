-- =====================================================================
-- Kalika Enterprises - Seed Baseline Data
-- Engine: MySQL / MariaDB (utf8mb4)
-- Note: Admin passwords are NOT seeded here; use create_admin.php
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `product_variants`;
TRUNCATE TABLE `products`;
TRUNCATE TABLE `brands`;
TRUNCATE TABLE `settings`;

-- 1. SEED BRANDS (7 Confirmed Brands)
INSERT INTO `brands` (`id`, `name`, `slug`, `is_active`, `sort_order`) VALUES
(1, 'Campa', 'campa', 1, 1),
(2, 'Sundar Marie', 'sundar-marie', 1, 2),
(3, 'Sundar Bourbon', 'sundar-bourbon', 1, 3),
(4, 'Sundar Coconut', 'sundar-coconut', 1, 4),
(5, 'Sundar Glucose', 'sundar-glucose', 0, 5), -- Inactive until GST confirmation
(6, 'Euro Chips', 'euro-chips', 1, 6),
(7, 'Coca-Cola Range', 'coca-cola-range', 0, 7); -- Inactive until confirmed specs

-- 2. SEED PRODUCTS (13 Products)
INSERT INTO `products` (`id`, `brand_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`) VALUES
-- Campa Range (GST 28% provided, verify)
(1, 1, 'Campa Cola', 'campa-cola', NULL, 'Classic Indian cola cold drink with strong fizzy refreshment.', 28.00, 1),
(2, 1, 'Campa Orange', 'campa-orange', NULL, 'Zesty sparkling orange fruit-flavored beverage.', 28.00, 1),
(3, 1, 'Campa Jeera', 'campa-jeera', NULL, 'Digestive spiced jeera soda beverage.', 28.00, 1),
(4, 1, 'Campa Lemon', 'campa-lemon', NULL, 'Crisp citrus clear lemon lime soda.', 28.00, 1),

-- Sundar Range
(5, 2, 'Sundar Marie', 'sundar-marie-biscuit', NULL, 'Crisp, light tea-time Marie biscuits.', 5.00, 1),
(6, 3, 'Sundar Bourbon', 'sundar-bourbon-biscuit', NULL, 'Rich chocolate cream filled Bourbon sandwich biscuits.', 18.00, 1),
(7, 4, 'Sundar Coconut', 'sundar-coconut-biscuit', NULL, 'Crunchy sweet baked biscuits infused with toasted coconut.', 18.00, 1),
(8, 5, 'Sundar Glucose', 'sundar-glucose-biscuit', NULL, 'Classic energy glucose biscuits. (Seeded inactive: GST rate pending confirmation)', NULL, 0),

-- Euro Chips (GST 12% provided, verify)
(9, 6, 'Euro Chips Salted', 'euro-chips-salted', NULL, 'Classic thin cut lightly salted crispy potato chips.', 12.00, 1),
(10, 6, 'Euro Chips Masala', 'euro-chips-masala', NULL, 'Spicy savory chatpata masala seasoned potato chips.', 12.00, 1),
(11, 6, 'Euro Banana Chips', 'euro-banana-chips', NULL, 'Traditional golden yellow savory crispy banana wafers.', 12.00, 1),

-- Coca-Cola Range (Seeded INACTIVE with NO variants as per master context)
(12, 7, 'Thums Up', 'thums-up', NULL, 'Strong carbonated cola beverage. Specifications unconfirmed.', 28.00, 0),
(13, 7, 'Sprite', 'sprite', NULL, 'Clear lemon-lime carbonated beverage. Specifications unconfirmed.', NULL, 0);

-- 3. SEED PRODUCT VARIANTS (39 Variants)
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`) VALUES
-- Campa Cola (200ml, 500ml, 700ml, 1L)
(1, 1, '200ml', 10.00, 100, 'in_stock', 1),
(2, 1, '500ml', 20.00, 100, 'in_stock', 1),
(3, 1, '700ml', 35.00, 100, 'in_stock', 1),
(4, 1, '1L', 80.00, 100, 'in_stock', 1),

-- Campa Orange (200ml, 500ml, 700ml, 1L)
(5, 2, '200ml', 10.00, 100, 'in_stock', 1),
(6, 2, '500ml', 20.00, 100, 'in_stock', 1),
(7, 2, '700ml', 35.00, 100, 'in_stock', 1),
(8, 2, '1L', 80.00, 100, 'in_stock', 1),

-- Campa Jeera (200ml, 500ml, 700ml, 1L)
(9, 3, '200ml', 10.00, 100, 'in_stock', 1),
(10, 3, '500ml', 20.00, 100, 'in_stock', 1),
(11, 3, '700ml', 35.00, 100, 'in_stock', 1),
(12, 3, '1L', 80.00, 100, 'in_stock', 1),

-- Campa Lemon (200ml, 500ml, 700ml, 1L)
(13, 4, '200ml', 10.00, 100, 'in_stock', 1),
(14, 4, '500ml', 20.00, 100, 'in_stock', 1),
(15, 4, '700ml', 35.00, 100, 'in_stock', 1),
(16, 4, '1L', 80.00, 100, 'in_stock', 1),

-- Sundar Marie (20g Rs5, 45g Rs10, 95g Rs20)
(17, 5, '20g', 5.00, 150, 'in_stock', 1),
(18, 5, '45g', 10.00, 150, 'in_stock', 1),
(19, 5, '95g', 20.00, 150, 'in_stock', 1),

-- Sundar Bourbon (500g Rs65)
(20, 6, '500g', 65.00, 80, 'in_stock', 1),

-- Sundar Coconut (20g Rs5, 50g Rs10, 200g Rs30, 400g Rs55)
(21, 7, '20g', 5.00, 100, 'in_stock', 1),
(22, 7, '50g', 10.00, 100, 'in_stock', 1),
(23, 7, '200g', 30.00, 100, 'in_stock', 1),
(24, 7, '400g', 55.00, 100, 'in_stock', 1),

-- Sundar Glucose (Inactive variants pending GST confirmation)
(25, 8, '20g', 5.00, 100, 'in_stock', 0),
(26, 8, '45g', 10.00, 100, 'in_stock', 0),
(27, 8, '95g', 20.00, 100, 'in_stock', 0),

-- Euro Chips Salted (16g Rs5, 35g Rs10, 70g Rs20, 160g Rs50)
(28, 9, '16g', 5.00, 120, 'in_stock', 1),
(29, 9, '35g', 10.00, 120, 'in_stock', 1),
(30, 9, '70g', 20.00, 120, 'in_stock', 1),
(31, 9, '160g', 50.00, 120, 'in_stock', 1),

-- Euro Chips Masala (16g Rs5, 35g Rs10, 70g Rs20, 160g Rs50)
(32, 10, '16g', 5.00, 120, 'in_stock', 1),
(33, 10, '35g', 10.00, 120, 'in_stock', 1),
(34, 10, '70g', 20.00, 120, 'in_stock', 1),
(35, 10, '160g', 50.00, 120, 'in_stock', 1),

-- Euro Banana Chips (16g Rs5, 35g Rs10, 70g Rs20, 160g Rs50)
(36, 11, '16g', 5.00, 120, 'in_stock', 1),
(37, 11, '35g', 10.00, 120, 'in_stock', 1),
(38, 11, '70g', 20.00, 120, 'in_stock', 1),
(39, 11, '160g', 50.00, 120, 'in_stock', 1);

-- 4. SEED SETTINGS
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
('refund_policy_text', 'Returns and refunds are accepted within 2 days of delivery for damaged or expired items subject to inspection. Settlement handled offline.');

SET FOREIGN_KEY_CHECKS = 1;
