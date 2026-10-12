-- MySQL dump 10.13  Distrib 8.0.44, for Win64 (x86_64)
--
-- Host: localhost    Database: kalika_enterprises
-- ------------------------------------------------------
-- Server version	8.0.44

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE DATABASE IF NOT EXISTS `kalika_enterprises`;
USE `kalika_enterprises`;

--
-- Table structure for table `brands`
--

DROP TABLE IF EXISTS `brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `brands`
--

LOCK TABLES `brands` WRITE;
/*!40000 ALTER TABLE `brands` DISABLE KEYS */;
INSERT INTO `brands` (`id`, `name`, `slug`, `is_active`, `sort_order`, `created_at`) VALUES (1,'Campa','campa',1,1,'2026-10-11 16:22:18'),(2,'Sundar Biscuits','sundar-biscuits',1,2,'2026-10-11 16:22:18'),(3,'Sundar Bourbon','sundar-bourbon',0,3,'2026-10-11 16:22:18'),(4,'Sundar Coconut','sundar-coconut',0,4,'2026-10-11 16:22:18'),(5,'Sundar Glucose','sundar-glucose',0,5,'2026-10-11 16:22:18'),(6,'Euro Chips','euro-chips',1,3,'2026-10-11 16:22:18'),(7,'Coca-Cola Beverages','coca-cola-range',1,4,'2026-10-11 16:22:18');
/*!40000 ALTER TABLE `brands` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` (`id`, `brand_id`, `name`, `slug`, `image`, `description`, `gst_percent`, `is_active`, `created_at`, `updated_at`) VALUES (1,1,'Campa Cola','campa-cola','campa-cola.jpg','Classic Indian cola cold drink with strong fizzy refreshment.',28.00,1,'2026-10-11 16:22:18','2026-10-11 18:26:16'),(2,1,'Campa Orange','campa-orange','campa-orange.jpg','Zesty sparkling orange fruit-flavored beverage.',28.00,1,'2026-10-11 16:22:18','2026-10-11 18:26:16'),(3,1,'Campa Jeera','campa-jeera','campa-jeera.jpg','Digestive spiced jeera soda beverage.',28.00,1,'2026-10-11 16:22:18','2026-10-11 18:26:16'),(4,1,'Campa Lemon','campa-lemon','campa-lemon.jpg','Crisp citrus clear lemon lime soda.',28.00,1,'2026-10-11 16:22:18','2026-10-11 18:26:16'),(5,2,'Sundar Marie','sundar-marie-biscuit','sundar-marie-biscuit.jpg','Crisp, light tea-time Marie biscuits.',5.00,1,'2026-10-11 16:22:18','2026-10-11 18:26:16'),(6,2,'Sundar Bourbon','sundar-bourbon-biscuit','sundar-bourbon-biscuit.jpg','Rich chocolate cream filled Bourbon sandwich biscuits.',18.00,1,'2026-10-11 16:22:18','2026-10-11 20:07:23'),(7,2,'Sundar Coconut','sundar-coconut-biscuit','sundar-coconut-biscuit.jpg','Crunchy sweet baked biscuits infused with toasted coconut.',18.00,1,'2026-10-11 16:22:18','2026-10-11 20:07:23'),(8,2,'Sundar Glucose','sundar-glucose-biscuit','sundar-glucose.jpg','Classic energy glucose biscuits. (Seeded inactive: GST rate pending confirmation)',5.00,1,'2026-10-11 16:22:18','2026-10-11 20:07:23'),(9,6,'Euro Chips Salted','euro-chips-salted','euro-chips-salted.jpg','Classic thin cut lightly salted crispy potato chips.',12.00,1,'2026-10-11 16:22:18','2026-10-11 18:26:16'),(10,6,'Euro Chips Masala','euro-chips-masala','euro-chips-masala.jpg','Spicy savory chatpata masala seasoned potato chips.',12.00,1,'2026-10-11 16:22:18','2026-10-11 18:26:16'),(11,6,'Euro Banana Chips','euro-banana-chips','euro-banana-chips.jpg','Traditional golden yellow savory crispy banana wafers.',12.00,1,'2026-10-11 16:22:18','2026-10-11 18:26:16'),(12,7,'Coca-Cola Original Taste','coca-cola-original','coca-cola.jpg','World-famous refreshing sparkling cola beverage ready for retail carton delivery.',28.00,1,'2026-10-11 16:22:18','2026-10-12 05:41:22'),(13,7,'Sprite Crisp Lemon-Lime','sprite-lemon-lime','coca-cola.jpg','Clear crisp lemon-lime sparkling soft drink.',28.00,1,'2026-10-11 16:22:18','2026-10-12 05:39:56'),(14,1,'Campa Energy Drink','campa-energy-drink','campa-energy.jpg','High energy carbonated drink with taurine and B-vitamins for instant alertness.',28.00,1,'2026-10-11 20:06:34','2026-10-11 20:06:34'),(15,1,'Campa Mango Fizz','campa-mango-fizz','campa-mango.jpg','Sparkling juicy alphonso mango fruit drink with authentic Indian sweetness.',28.00,1,'2026-10-11 20:06:34','2026-10-11 20:06:34'),(16,1,'Campa Club Soda','campa-club-soda','campa-soda.jpg','Extra fizzy purified club soda for mixers and refreshment.',28.00,1,'2026-10-11 20:06:34','2026-10-11 20:06:34'),(17,1,'Campa Diet Cola','campa-diet-cola','campa-diet-cola.jpg','Zero sugar and zero calorie classic cola with punchy carbonation.',28.00,1,'2026-10-11 20:06:34','2026-10-11 20:06:34'),(18,6,'Euro Cream & Onion Chips','euro-chips-cream-onion','euro-cream-onion.jpg','Gourmet sour cream and herb seasoned crispy potato wafers.',12.00,1,'2026-10-11 20:06:34','2026-10-11 20:06:34'),(19,6,'Euro Tomato Tango Chips','euro-chips-tomato','euro-tomato-chips.jpg','Tangy sun-ripened tomato seasoned potato chips.',12.00,1,'2026-10-11 20:06:34','2026-10-11 20:06:34'),(20,6,'Euro Salted Peanuts','euro-salted-peanuts','euro-chips-salted.jpg','Roasted golden crunch peanuts lightly seasoned with rock salt.',12.00,1,'2026-10-11 20:06:34','2026-10-11 20:06:34'),(21,2,'Sundar Butter Cookies','sundar-butter-cookies','sundar-butter-cookies.jpg','Rich golden bakery butter cookies with melt-in-mouth texture.',18.00,1,'2026-10-11 20:07:23','2026-10-11 20:07:23'),(22,7,'Thums Up Charged Cola','thums-up-cola','thums-up.jpg','Strong fizzy dark carbonated Indian cola with iconic spicy punch.',28.00,1,'2026-10-12 05:39:56','2026-10-12 05:41:22');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_variants`
--

DROP TABLE IF EXISTS `product_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_variants`
--

LOCK TABLES `product_variants` WRITE;
/*!40000 ALTER TABLE `product_variants` DISABLE KEYS */;
INSERT INTO `product_variants` (`id`, `product_id`, `size_label`, `price`, `stock_qty`, `stock_status`, `is_active`, `created_at`) VALUES (1,1,'200ml',10.00,100,'in_stock',1,'2026-10-11 16:22:18'),(2,1,'500ml',20.00,100,'in_stock',1,'2026-10-11 16:22:18'),(3,1,'700ml',35.00,100,'in_stock',1,'2026-10-11 16:22:18'),(4,1,'1L',80.00,100,'in_stock',1,'2026-10-11 16:22:18'),(5,2,'200ml',10.00,100,'in_stock',1,'2026-10-11 16:22:18'),(6,2,'500ml',20.00,100,'in_stock',1,'2026-10-11 16:22:18'),(7,2,'700ml',35.00,100,'in_stock',1,'2026-10-11 16:22:18'),(8,2,'1L',80.00,100,'in_stock',1,'2026-10-11 16:22:18'),(9,3,'200ml',10.00,100,'in_stock',1,'2026-10-11 16:22:18'),(10,3,'500ml',20.00,100,'in_stock',1,'2026-10-11 16:22:18'),(11,3,'700ml',35.00,100,'in_stock',1,'2026-10-11 16:22:18'),(12,3,'1L',80.00,100,'in_stock',1,'2026-10-11 16:22:18'),(13,4,'200ml',10.00,100,'in_stock',1,'2026-10-11 16:22:18'),(14,4,'500ml',20.00,100,'in_stock',1,'2026-10-11 16:22:18'),(15,4,'700ml',35.00,100,'in_stock',1,'2026-10-11 16:22:18'),(16,4,'1L',80.00,100,'in_stock',1,'2026-10-11 16:22:18'),(17,5,'20g',5.00,150,'in_stock',1,'2026-10-11 16:22:18'),(18,5,'45g',10.00,150,'in_stock',1,'2026-10-11 16:22:18'),(19,5,'95g',20.00,150,'in_stock',1,'2026-10-11 16:22:18'),(20,6,'500g',65.00,80,'in_stock',1,'2026-10-11 16:22:18'),(21,7,'20g',5.00,100,'in_stock',1,'2026-10-11 16:22:18'),(22,7,'50g',10.00,100,'in_stock',1,'2026-10-11 16:22:18'),(23,7,'200g',30.00,100,'in_stock',1,'2026-10-11 16:22:18'),(24,7,'400g',55.00,100,'in_stock',1,'2026-10-11 16:22:18'),(25,8,'20g',5.00,100,'in_stock',0,'2026-10-11 16:22:18'),(26,8,'45g',10.00,100,'in_stock',0,'2026-10-11 16:22:18'),(27,8,'95g',20.00,100,'in_stock',0,'2026-10-11 16:22:18'),(28,9,'16g',5.00,120,'in_stock',1,'2026-10-11 16:22:18'),(29,9,'35g',10.00,120,'in_stock',1,'2026-10-11 16:22:18'),(30,9,'70g',20.00,120,'in_stock',1,'2026-10-11 16:22:18'),(31,9,'160g',50.00,120,'in_stock',1,'2026-10-11 16:22:18'),(32,10,'16g',5.00,120,'in_stock',1,'2026-10-11 16:22:18'),(33,10,'35g',10.00,120,'in_stock',1,'2026-10-11 16:22:18'),(34,10,'70g',20.00,120,'in_stock',1,'2026-10-11 16:22:18'),(35,10,'160g',50.00,120,'in_stock',1,'2026-10-11 16:22:18'),(36,11,'16g',5.00,120,'in_stock',1,'2026-10-11 16:22:18'),(37,11,'35g',10.00,120,'in_stock',1,'2026-10-11 16:22:18'),(38,11,'70g',20.00,120,'in_stock',1,'2026-10-11 16:22:18'),(39,11,'160g',50.00,120,'in_stock',1,'2026-10-11 16:22:18'),(40,14,'250ml Can',20.00,120,'in_stock',1,'2026-10-11 20:06:34'),(41,14,'500ml PET',35.00,80,'in_stock',1,'2026-10-11 20:06:34'),(42,15,'200ml',10.00,150,'in_stock',1,'2026-10-11 20:06:34'),(43,15,'500ml',20.00,100,'in_stock',1,'2026-10-11 20:06:34'),(44,15,'1L',80.00,60,'in_stock',1,'2026-10-11 20:06:34'),(45,16,'500ml',15.00,140,'in_stock',1,'2026-10-11 20:06:34'),(46,16,'750ml',20.00,90,'in_stock',1,'2026-10-11 20:06:34'),(47,17,'300ml Can',20.00,75,'in_stock',1,'2026-10-11 20:06:34'),(48,17,'500ml PET',30.00,90,'in_stock',1,'2026-10-11 20:06:34'),(49,18,'30g',10.00,150,'in_stock',1,'2026-10-11 20:06:34'),(50,18,'75g',20.00,100,'in_stock',1,'2026-10-11 20:06:34'),(51,19,'30g',10.00,140,'in_stock',1,'2026-10-11 20:06:34'),(52,19,'75g',20.00,90,'in_stock',1,'2026-10-11 20:06:34'),(53,20,'50g',10.00,120,'in_stock',1,'2026-10-11 20:06:34'),(54,20,'150g',30.00,80,'in_stock',1,'2026-10-11 20:06:34'),(55,21,'150g Box',30.00,100,'in_stock',1,'2026-10-11 20:07:23'),(56,21,'300g Tin',60.00,60,'in_stock',1,'2026-10-11 20:07:23'),(57,12,'250ml Pet (Tray of 24)',20.00,150,'in_stock',1,'2026-10-12 05:39:56'),(58,12,'750ml Pet (Case of 24)',40.00,120,'in_stock',1,'2026-10-12 05:39:56'),(59,12,'2L Family Pack (Case of 9)',95.00,80,'in_stock',1,'2026-10-12 05:39:56'),(60,22,'250ml Pet (Tray of 24)',20.00,150,'in_stock',1,'2026-10-12 05:39:56'),(61,22,'750ml Pet (Case of 24)',40.00,120,'in_stock',1,'2026-10-12 05:39:56'),(62,22,'2L Family Pack (Case of 9)',95.00,80,'in_stock',1,'2026-10-12 05:39:56'),(63,13,'250ml Pet (Tray of 24)',20.00,120,'in_stock',1,'2026-10-12 05:39:56'),(64,13,'750ml Pet (Case of 24)',40.00,100,'in_stock',1,'2026-10-12 05:39:56'),(65,13,'2L Family Pack (Case of 9)',95.00,60,'in_stock',1,'2026-10-12 05:39:56');
/*!40000 ALTER TABLE `product_variants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` (`id`, `username`, `password_hash`, `last_login`, `created_at`) VALUES (1,'admin','$2y$10$DC4jmY.5QX.2KKT07WDGDuARQ7jRdWPhdk8R3FFS8SD6g8Bdlb7QS','2026-10-12 05:34:47','2026-10-11 16:57:13');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-12 12:31:58
