-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               10.4.32-MariaDB - mariadb.org binary distribution
-- Server OS:                    Win64
-- HeidiSQL Version:             12.10.0.7000
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for baladymall_db
CREATE DATABASE IF NOT EXISTS `baladymall_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `baladymall_db`;

-- Dumping structure for table baladymall_db.attributes
CREATE TABLE IF NOT EXISTS `attributes` (
  `attribute_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `attribute_name` varchar(100) NOT NULL COMMENT 'e.g., Color, Size, Material',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attribute_id`),
  UNIQUE KEY `attribute_name` (`attribute_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table baladymall_db.attribute_values
CREATE TABLE IF NOT EXISTS `attribute_values` (
  `attribute_value_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `attribute_id` int(10) unsigned NOT NULL COMMENT 'Foreign Key to attributes table',
  `value` varchar(100) NOT NULL COMMENT 'e.g., Red, Blue, Small, Large',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attribute_value_id`),
  UNIQUE KEY `uk_attribute_value` (`attribute_id`,`value`),
  CONSTRAINT `fk_attrval_attribute` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`attribute_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table baladymall_db.brands
CREATE TABLE IF NOT EXISTS `brands` (
  `brand_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT 'Foreign Key referencing users.user_id (must be a brand_admin role)',
  `brand_name` varchar(100) NOT NULL,
  `brand_logo_url` varchar(255) DEFAULT NULL COMMENT 'URL path to the brand logo image',
  `brand_description` text DEFAULT NULL,
  `brand_contact_email` varchar(100) DEFAULT NULL,
  `brand_contact_phone` varchar(20) DEFAULT NULL,
  `brand_website_url` varchar(255) DEFAULT NULL,
  `facebook_url` varchar(255) DEFAULT NULL,
  `instagram_url` varchar(255) DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 for pending approval, 1 for approved by super admin',
  `commission_rate` decimal(5,2) DEFAULT NULL COMMENT 'e.g., 15.50 for 15.50%. Nullable for flexibility.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`brand_id`),
  UNIQUE KEY `brand_name` (`brand_name`),
  KEY `fk_brand_user` (`user_id`),
  KEY `idx_brand_name` (`brand_name`),
  CONSTRAINT `fk_brand_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table baladymall_db.categories
CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `category_description` text DEFAULT NULL,
  `parent_category_id` int(10) unsigned DEFAULT NULL COMMENT 'For sub-categories, references categories.category_id',
  `category_image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `category_name_parent_id` (`category_name`,`parent_category_id`),
  KEY `fk_parent_category` (`parent_category_id`),
  KEY `idx_category_name` (`category_name`),
  CONSTRAINT `fk_parent_category` FOREIGN KEY (`parent_category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table baladymall_db.orders
CREATE TABLE IF NOT EXISTS `orders` (
  `order_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int(10) unsigned NOT NULL COMMENT 'Foreign Key to users table (role: customer)',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `order_status` enum('pending_payment','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending_payment',
  `total_amount` decimal(10,2) NOT NULL COMMENT 'Final amount paid for the order',
  `subtotal_amount` decimal(10,2) NOT NULL COMMENT 'Total of items before shipping, taxes, discounts',
  `shipping_amount` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `shipping_name` varchar(100) NOT NULL COMMENT 'Full name for the shipment',
  `shipping_phone` varchar(20) NOT NULL COMMENT 'Contact phone for delivery',
  `shipping_address_line1` varchar(255) NOT NULL,
  `shipping_address_line2` varchar(255) DEFAULT NULL,
  `shipping_city` varchar(100) NOT NULL,
  `shipping_governorate` varchar(100) NOT NULL,
  `shipping_postal_code` varchar(20) DEFAULT NULL,
  `shipping_country` varchar(50) NOT NULL DEFAULT 'Egypt',
  `payment_method` varchar(50) DEFAULT NULL COMMENT 'e.g., credit_card, cash_on_delivery, fawry',
  `payment_gateway_transaction_id` varchar(255) DEFAULT NULL COMMENT 'For online payments',
  `notes_to_seller` text DEFAULT NULL COMMENT 'Customer notes for the order',
  `internal_notes` text DEFAULT NULL COMMENT 'Admin notes for the order (not visible to customer)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`order_id`),
  KEY `idx_order_status` (`order_status`),
  KEY `idx_order_customer_date` (`customer_id`,`order_date`),
  CONSTRAINT `fk_order_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table baladymall_db.order_items
CREATE TABLE IF NOT EXISTS `order_items` (
  `order_item_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL COMMENT 'Foreign Key to orders table',
  `product_id` int(10) unsigned NOT NULL COMMENT 'Foreign Key to products table (base product)',
  `variant_id` int(10) unsigned DEFAULT NULL COMMENT 'Foreign Key to product_variants table (if item is a specific variant)',
  `brand_id` int(10) unsigned NOT NULL COMMENT 'Foreign Key to brands table (brand owning this item)',
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `price_at_purchase` decimal(10,2) NOT NULL COMMENT 'Price of a single unit at time of purchase',
  `subtotal_for_item` decimal(10,2) NOT NULL COMMENT 'price_at_purchase * quantity',
  `commission_rate_at_purchase` decimal(5,2) DEFAULT NULL COMMENT 'Brand commission rate for this item at time of purchase',
  `commission_amount_for_item` decimal(10,2) DEFAULT NULL COMMENT 'Calculated commission for this item line',
  `item_status` enum('pending','processing','shipped_by_brand','delivered_to_customer','cancelled','returned') DEFAULT 'pending' COMMENT 'Status of individual item fulfillment',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`order_item_id`),
  KEY `fk_orderitem_product` (`product_id`),
  KEY `fk_orderitem_variant_idx` (`variant_id`),
  KEY `idx_orderitem_order_product` (`order_id`,`product_id`),
  KEY `idx_orderitem_brand_status` (`brand_id`,`item_status`),
  CONSTRAINT `fk_orderitem_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`brand_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_orderitem_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_orderitem_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_orderitem_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`variant_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table baladymall_db.password_resets
CREATE TABLE IF NOT EXISTS `password_resets` (
  `user_id` int(10) unsigned NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table baladymall_db.products
CREATE TABLE IF NOT EXISTS `products` (
  `product_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `brand_id` int(10) unsigned NOT NULL COMMENT 'Foreign Key to brands table',
  `product_name` varchar(255) NOT NULL,
  `product_description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL COMMENT 'Price for simple products (no variants) or a default display price',
  `compare_at_price` decimal(10,2) DEFAULT NULL COMMENT '"Was" price for simple products or a default',
  `stock_quantity` int(11) DEFAULT 0 COMMENT 'Stock for simple products. If requires_variants=1, this is likely 0 or unused here.',
  `main_image_url` varchar(255) DEFAULT NULL COMMENT 'Primary display image URL for the product listing',
  `is_active` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Inactive/Draft, 1=Active (visible to customers)',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Not featured, 1=Featured (e.g., for homepage)',
  `requires_variants` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 if product uses the variants system (size, color etc), 0 for simple product',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`product_id`),
  KEY `idx_product_name` (`product_name`),
  KEY `idx_product_brand_active` (`brand_id`,`is_active`),
  CONSTRAINT `fk_product_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`brand_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table baladymall_db.product_category
CREATE TABLE IF NOT EXISTS `product_category` (
  `product_id` int(10) unsigned NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`product_id`,`category_id`),
  KEY `fk_prodcat_category` (`category_id`),
  CONSTRAINT `fk_prodcat_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_prodcat_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table baladymall_db.product_images
CREATE TABLE IF NOT EXISTS `product_images` (
  `image_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL COMMENT 'Foreign Key to products table',
  `image_url` varchar(255) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL COMMENT 'For SEO and accessibility',
  `sort_order` int(10) unsigned DEFAULT 0 COMMENT 'Determines display order of images',
  `is_primary_for_product` tinyint(1) DEFAULT 0 COMMENT '1 if this is the main display image among multiples for the product detail page',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`image_id`),
  KEY `fk_prodimage_product` (`product_id`),
  CONSTRAINT `fk_prodimage_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table baladymall_db.product_variants
CREATE TABLE IF NOT EXISTS `product_variants` (
  `variant_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL COMMENT 'Foreign Key to products table',
  `variant_sku` varchar(100) DEFAULT NULL COMMENT 'Optional: Stock Keeping Unit for this specific variant, unique if provided',
  `price` decimal(10,2) NOT NULL COMMENT 'Price for this specific variant',
  `compare_at_price` decimal(10,2) DEFAULT NULL COMMENT 'Original price if this variant is on sale',
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `variant_image_url` varchar(255) DEFAULT NULL COMMENT 'Optional: Specific image for this variant (e.g., the red t-shirt image)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active (this specific variant)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`variant_id`),
  UNIQUE KEY `variant_sku` (`variant_sku`),
  KEY `fk_variant_product` (`product_id`),
  CONSTRAINT `fk_variant_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table baladymall_db.site_settings
CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table baladymall_db.users
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'Stores hashed passwords',
  `email` varchar(100) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `role` enum('customer','brand_admin','super_admin') NOT NULL DEFAULT 'customer',
  `phone_number` varchar(20) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 for active, 0 for inactive/banned',
  `email_verified_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp of email verification',
  `shipping_address_line1` varchar(255) DEFAULT NULL,
  `shipping_address_line2` varchar(255) DEFAULT NULL,
  `shipping_city` varchar(100) DEFAULT NULL,
  `shipping_governorate` varchar(100) DEFAULT NULL COMMENT 'e.g., Cairo, Giza, Alexandria',
  `shipping_postal_code` varchar(20) DEFAULT NULL,
  `shipping_country` varchar(50) DEFAULT 'Egypt',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `phone_number` (`phone_number`),
  KEY `idx_email` (`email`),
  KEY `idx_phone_number` (`phone_number`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table baladymall_db.variant_attribute_values
CREATE TABLE IF NOT EXISTS `variant_attribute_values` (
  `variant_id` int(10) unsigned NOT NULL,
  `attribute_value_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`variant_id`,`attribute_value_id`),
  KEY `fk_varattrval_attrval` (`attribute_value_id`),
  CONSTRAINT `fk_varattrval_attrval` FOREIGN KEY (`attribute_value_id`) REFERENCES `attribute_values` (`attribute_value_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_varattrval_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`variant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
