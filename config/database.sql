-- 1. Database Creation
CREATE DATABASE IF NOT EXISTS `quick_pharmacy_db`;

USE `quick_pharmacy_db`;

-- 2. User tables
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `phone` VARCHAR(20) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('customer','pharmacy','rider','admin')DEFAULT 'customer',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 3. Pharmacies table
CREATE TABLE IF NOT EXISTS `pharmacies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `pharmacy_name` VARCHAR(150) NOT NULL,
    `address` TEXT NOT NULL,
    `latitude` DECIMAL(10, 8) DEFAULT NULL,
    `longitude` DECIMAL(11, 8) DEFAULT NULL,
    `marketing_wallet` DECIMAL(10, 2) DEFAULT 0.00,
    `is_active` TINYINT(1) DEFAULT 1,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 4. Medicines Inventory
CREATE TABLE IF NOT EXISTS `medicines` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pharmacy_id` INT NOT NULL,
    `medicine_name` VARCHAR(150) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `active_ingredient` VARCHAR(150) NOT NULL,
    `price` DECIMAL(10, 2) NOT NULL,
    `stock_quantity` INT NOT NULL DEFAULT 0,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET = utf8mb4;

-- 5 Customer Wallets
CREATE TABLE IF NOT EXISTS `wallets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `balance` DECIMAL(10, 2) DEFAULT 0.00,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET = utf8mb4;

-- 6 Orders Table
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `pharmacy_id` INT NOT NULL,
    `rider_id` INT DEFAULT NULL,
    `total_amount` DECIMAL(10, 2) NOT NULL,
    `order_status` ENUM('pending','confirmed','dispatched','delivered','cancelled') DEFAULT 'pending',
    `delivery_address` TEXT NOT NULL,
    `delivery_lat` DECIMAL(10, 8) DEFAULT NULL,
    `delivery_lng` DECIMAL(11, 8) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`),
    FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies` (`id`),
    FOREIGN KEY (`rider_id`) REFERENCES `users`(`id`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;

-- 7 Order Items table
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `medicine_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8 Campaigns & Discounts table
CREATE TABLE IF NOT EXISTS `campaigns` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `promo_code` VARCHAR(50) NOT NULL UNIQUE,
    `discount_percentage` DECIMAL(5, 2) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9 Feedback & rating table
CREATE TABLE IF NOT EXISTS `ratings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `customer_id` INT NOT NULL,
    `pharmacy_id` INT NOT NULL,
    `rating` TINYINT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
    `review` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
    FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`),
    FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies` (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

