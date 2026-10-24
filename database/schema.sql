-- ========================================================
-- Courier Service System with QR Code Database Schema
-- Database: courier_db
-- ========================================================

CREATE DATABASE IF NOT EXISTS `courier_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `courier_db`;

-- 1. HUBS TABLE (Logistics Warehouses & Sorting Facilities)
CREATE TABLE IF NOT EXISTS `hubs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `hub_name` VARCHAR(150) NOT NULL,
    `hub_code` VARCHAR(20) NOT NULL UNIQUE,
    `city` VARCHAR(100) NOT NULL,
    `state` VARCHAR(100) NOT NULL,
    `pincode` VARCHAR(20) NOT NULL,
    `address` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. USERS TABLE (Admin, Hub Managers, Drivers, Customers)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'hub_manager', 'driver', 'customer') NOT NULL DEFAULT 'customer',
    `hub_id` INT NULL,
    `phone` VARCHAR(20) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_user_hub` FOREIGN KEY (`hub_id`) REFERENCES `hubs`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 3. PARCELS TABLE (Shipment Records)
CREATE TABLE IF NOT EXISTS `parcels` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tracking_number` VARCHAR(50) NOT NULL UNIQUE,
    `sender_id` INT NULL,
    `sender_name` VARCHAR(150) NOT NULL,
    `sender_phone` VARCHAR(20) NOT NULL,
    `sender_address` TEXT NOT NULL,
    `sender_city` VARCHAR(100) NOT NULL,
    `sender_pincode` VARCHAR(20) NOT NULL,
    `receiver_name` VARCHAR(150) NOT NULL,
    `receiver_phone` VARCHAR(20) NOT NULL,
    `receiver_address` TEXT NOT NULL,
    `receiver_city` VARCHAR(100) NOT NULL,
    `receiver_pincode` VARCHAR(20) NOT NULL,
    `weight_kg` DECIMAL(6, 2) NOT NULL DEFAULT 1.00,
    `parcel_type` VARCHAR(50) NOT NULL DEFAULT 'Standard Box',
    `shipping_cost` DECIMAL(10, 2) NOT NULL DEFAULT 45.00,
    `origin_hub_id` INT NULL,
    `destination_hub_id` INT NULL,
    `current_hub_id` INT NULL,
    `assigned_driver_id` INT NULL,
    `delivery_otp` VARCHAR(6) NOT NULL DEFAULT '4821',
    `status` ENUM('booked', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'returned') NOT NULL DEFAULT 'booked',
    `payment_status` ENUM('pending', 'paid') NOT NULL DEFAULT 'pending',
    `payment_method` ENUM('online', 'cod') NOT NULL DEFAULT 'online',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `delivered_at` TIMESTAMP NULL,
    CONSTRAINT `fk_parcel_sender` FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_parcel_origin` FOREIGN KEY (`origin_hub_id`) REFERENCES `hubs`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_parcel_dest` FOREIGN KEY (`destination_hub_id`) REFERENCES `hubs`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_parcel_curr_hub` FOREIGN KEY (`current_hub_id`) REFERENCES `hubs`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_parcel_driver` FOREIGN KEY (`assigned_driver_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 4. PAYMENTS TABLE (Payment Transactions & Invoices)
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `parcel_id` INT NOT NULL,
    `transaction_id` VARCHAR(100) NOT NULL UNIQUE,
    `amount` DECIMAL(10, 2) NOT NULL,
    `payment_method` ENUM('online', 'cod') NOT NULL DEFAULT 'online',
    `payment_status` ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'paid',
    `card_last4` VARCHAR(4) NULL,
    `paid_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_payment_parcel` FOREIGN KEY (`parcel_id`) REFERENCES `parcels`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. TRACKING LOGS TABLE (QR Scan Transit History)
CREATE TABLE IF NOT EXISTS `tracking_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `parcel_id` INT NOT NULL,
    `status` VARCHAR(50) NOT NULL,
    `hub_id` INT NULL,
    `updated_by` INT NULL,
    `location_note` VARCHAR(255) NOT NULL,
    `remarks` TEXT NULL,
    `scan_timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_track_parcel` FOREIGN KEY (`parcel_id`) REFERENCES `parcels`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_track_hub` FOREIGN KEY (`hub_id`) REFERENCES `hubs`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_track_user` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 6. PROOF OF DELIVERY TABLE (POD)
CREATE TABLE IF NOT EXISTS `proof_of_delivery` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `parcel_id` INT NOT NULL UNIQUE,
    `recipient_name` VARCHAR(150) NOT NULL,
    `otp_verified` TINYINT(1) NOT NULL DEFAULT 1,
    `payment_collected` DECIMAL(10, 2) DEFAULT 0.00,
    `signature_data` LONGTEXT NULL,
    `notes` TEXT NULL,
    `delivered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pod_parcel_id` FOREIGN KEY (`parcel_id`) REFERENCES `parcels`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========================================================
-- SEED INITIAL DATA (Hubs, Users, Demo Parcels & Payments)
-- ========================================================

-- Insert 4 Default Logistics Hubs
INSERT INTO `hubs` (`id`, `hub_name`, `hub_code`, `city`, `state`, `pincode`, `address`) VALUES
(1, 'Central Sorting Hub', 'HUB-NYC-01', 'New York', 'NY', '10001', '124 8th Ave, Logistics Center'),
(2, 'West Coast Distribution Center', 'HUB-LAX-02', 'Los Angeles', 'CA', '90001', '450 S Alameda St, Warehouse B'),
(3, 'Midwest Transfer Hub', 'HUB-ORD-03', 'Chicago', 'IL', '60601', '800 N Michigan Ave, Hub Depot'),
(4, 'Miami Coastal Depot', 'HUB-MIA-04', 'Miami', 'FL', '33101', '500 Ocean Logistics Way')
ON DUPLICATE KEY UPDATE `hub_name` = VALUES(`hub_name`);

-- Insert Default Seed Users (Passwords: admin123, hub123, driver123, customer123)
INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `role`, `hub_id`, `phone`) VALUES
(1, 'System Administrator', 'admin@courier.com', '$2y$10$3qLMv2sRHrCXL3b6OXo1P.jurFQWXJLE1rw5hpJg/u0jFHaZ.bYlK', 'admin', 1, '+1-800-555-0100'),
(2, 'Sarah Jenkins (Hub Manager)', 'hub@courier.com', '$2y$10$.EX6p9ebAaoUYoxSgOlzUu66zLe/5IMR/XR4hhTFlXgqdCiJ6Mig6', 'hub_manager', 1, '+1-800-555-0101'),
(3, 'Alex Rivera (Delivery Driver)', 'driver@courier.com', '$2y$10$vN5IG/tkB/a4VqXBc.Hqmes27o29py76hnf4/Mx/Sc0nfCUHXX6Ta', 'driver', 1, '+1-800-555-0102'),
(4, 'John Doe (Customer)', 'customer@courier.com', '$2y$10$GhxJpM8Xvlvtko1oeNNG5ucAxKnKOaQR8oECdzLB6DXvW6YK//3tu', 'customer', NULL, '+1-800-555-0103')
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);

-- Insert Sample Demo Parcels
INSERT INTO `parcels` 
(`id`, `tracking_number`, `sender_id`, `sender_name`, `sender_phone`, `sender_address`, `sender_city`, `sender_pincode`, `receiver_name`, `receiver_phone`, `receiver_address`, `receiver_city`, `receiver_pincode`, `weight_kg`, `parcel_type`, `shipping_cost`, `origin_hub_id`, `destination_hub_id`, `current_hub_id`, `assigned_driver_id`, `delivery_otp`, `status`, `payment_status`, `payment_method`) 
VALUES
(1, 'CR-2026-98214', 4, 'John Doe', '+1-555-0103', '350 5th Ave, Apt 14B', 'New York', '10118', 'Alice Smith', '+1-555-987-6543', '742 Evergreen Terrace', 'Los Angeles', '90001', 2.50, 'Electronics Box', 45.00, 1, 2, 2, 3, '4821', 'delivered', 'paid', 'online'),
(2, 'CR-2026-44109', 4, 'John Doe', '+1-555-0103', '350 5th Ave, Apt 14B', 'New York', '10118', 'Robert Johnson', '+1-555-333-4444', '1000 W Adams St', 'Chicago', '60607', 0.80, 'Legal Document', 28.00, 1, 3, 1, NULL, '7719', 'in_transit', 'paid', 'online'),
(3, 'CR-2026-11852', 4, 'John Doe', '+1-555-0103', '350 5th Ave, Apt 14B', 'New York', '10118', 'Emma Watson', '+1-555-666-7777', '221B Baker St', 'New York', '10001', 1.20, 'Fragile Gift', 32.00, 1, 1, 1, 3, '3190', 'out_for_delivery', 'pending', 'cod')
ON DUPLICATE KEY UPDATE `tracking_number` = VALUES(`tracking_number`);

-- Payments for Demo Parcels
INSERT INTO `payments` (`parcel_id`, `transaction_id`, `amount`, `payment_method`, `payment_status`, `card_last4`) VALUES
(1, 'TXN_2026_98214_ONL', 45.00, 'online', 'paid', '4242'),
(2, 'TXN_2026_44109_ONL', 28.00, 'online', 'paid', '8812')
ON DUPLICATE KEY UPDATE `transaction_id` = VALUES(`transaction_id`);

-- Tracking Logs for Demo Parcels
INSERT INTO `tracking_logs` (`parcel_id`, `status`, `hub_id`, `updated_by`, `location_note`, `remarks`) VALUES
(1, 'booked', 1, 4, 'New York Central Hub', 'Shipment booked online with prepaid payment'),
(1, 'picked_up', 1, 2, 'New York Central Hub', 'Parcel received at hub dropoff counter'),
(1, 'in_transit', 2, 2, 'Los Angeles West Coast Hub', 'Arrived at destination sorting facility'),
(1, 'out_for_delivery', 2, 3, 'Los Angeles Delivery Route', 'Dispatched with driver Alex Rivera for final delivery'),
(1, 'delivered', 2, 3, 'Recipient Doorstep', 'Handed to recipient Alice Smith with verified OTP'),
(2, 'booked', 1, 4, 'New York Central Hub', 'Shipment booked online with prepaid payment'),
(2, 'in_transit', 1, 2, 'New York Central Hub', 'Dispatched on interstate transit vehicle to Chicago'),
(3, 'booked', 1, 4, 'New York Central Hub', 'Shipment booked (Cash on Delivery)'),
(3, 'out_for_delivery', 1, 3, 'Manhattan Delivery Route', 'Out for delivery, collect $32.00 cash upon arrival');

-- Proof of Delivery for Delivered Parcel #1
INSERT INTO `proof_of_delivery` (`parcel_id`, `recipient_name`, `otp_verified`, `payment_collected`, `notes`, `delivered_at`) VALUES
(1, 'Alice Smith', 1, 0.00, 'Delivered directly to recipient at front door', NOW())
ON DUPLICATE KEY UPDATE `recipient_name` = VALUES(`recipient_name`);
