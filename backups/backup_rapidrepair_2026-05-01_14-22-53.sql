-- RapidRepair Database Backup
-- Generated at: 2026-05-01 14:22:53
-- Database: rapidrepairs

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- ----------------------------
-- Table structure for appointment_services
-- ----------------------------
DROP TABLE IF EXISTS `appointment_services`;
CREATE TABLE `appointment_services` (
  `appointment_service_id` int NOT NULL AUTO_INCREMENT,
  `appointment_id` int NOT NULL,
  `tenantID` int NOT NULL,
  `service_id` int NOT NULL,
  `service_price` decimal(10,2) NOT NULL,
  `duration_minutes` int NOT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`appointment_service_id`),
  KEY `idx_app_services_appointment` (`appointment_id`),
  KEY `idx_app_services_tenant` (`tenantID`),
  KEY `idx_app_services_service` (`service_id`),
  CONSTRAINT `appointment_services_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE,
  CONSTRAINT `appointment_services_ibfk_2` FOREIGN KEY (`tenantID`) REFERENCES `owners` (`tenantID`),
  CONSTRAINT `appointment_services_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`)
) ENGINE=InnoDB AUTO_INCREMENT=132 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of appointment_services
-- ----------------------------
INSERT INTO `appointment_services` VALUES ('130', '131', '2', '5', '1500.00', '60', NULL, '2026-04-29 18:17:50');
INSERT INTO `appointment_services` VALUES ('131', '132', '4', '19', '500.00', '60', '', '2026-05-01 08:31:28');

-- ----------------------------
-- Table structure for appointments
-- ----------------------------
DROP TABLE IF EXISTS `appointments`;
CREATE TABLE `appointments` (
  `appointment_id` int NOT NULL AUTO_INCREMENT,
  `tenantID` int NOT NULL,
  `user_id` int NOT NULL,
  `vehicle_id` int NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('Pending','Confirmed','For Diagnosis','Diagnosing','For Approval','In Progress','Completed','Cancelled') DEFAULT 'Pending',
  `notes` text,
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`appointment_id`),
  KEY `idx_appointments_tenant` (`tenantID`),
  KEY `idx_appointments_user` (`user_id`),
  KEY `idx_appointments_vehicle` (`vehicle_id`),
  KEY `idx_appointments_date` (`appointment_date`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`tenantID`) REFERENCES `owners` (`tenantID`),
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicleinformation` (`vehicle_id`)
) ENGINE=InnoDB AUTO_INCREMENT=133 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of appointments
-- ----------------------------
INSERT INTO `appointments` VALUES ('131', '2', '5', '9', '2026-04-29', '09:00:00', 'Confirmed', NULL, '1500.00', '2026-04-29 18:17:50', '2026-04-29 18:18:02');
INSERT INTO `appointments` VALUES ('132', '4', '22', '11', '2026-05-01', '09:00:00', 'For Approval', 'Check my vehicle', '1800.00', '2026-05-01 08:31:28', '2026-05-01 10:28:41');

-- ----------------------------
-- Table structure for client_info
-- ----------------------------
DROP TABLE IF EXISTS `client_info`;
CREATE TABLE `client_info` (
  `clientID` int NOT NULL AUTO_INCREMENT,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `password` varchar(100) NOT NULL,
  PRIMARY KEY (`clientID`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of client_info
-- ----------------------------
INSERT INTO `client_info` VALUES ('9', 'Jm', 'Mendoza', 'pitigad423@donumart.com', '$2y$10$bQm/A4SmfMrqoJa6z2uLPOR8ZfYxYkLt5oXD/hXmUyHewOt9o.4P.', '2026-04-21 17:46:56', '$2y$10$bQm/A4SmfMrqoJa6z2uLPOR8ZfYxYkLt5oXD/hXmUyHewOt9o.4P.');
INSERT INTO `client_info` VALUES ('17', 'Jm', 'Mendoza', 'mekeyik251@hacknapp.com', '$2y$10$I6yRGbd..JL/waRBb54.deu.eYpPzfdXWhs.Bbymx1GRJf2MT2GaC', '2026-04-28 16:08:02', '$2y$10$I6yRGbd..JL/waRBb54.deu.eYpPzfdXWhs.Bbymx1GRJf2MT2GaC');
INSERT INTO `client_info` VALUES ('18', 'Jm', 'Mendoza', 'hemej25804@hacknapp.com', '$2y$10$JRp/aS9DfhBHrz0/ppCwqOdpTknWa6anU8030Y.pECduPIHKDlCXa', '2026-04-29 13:12:51', '$2y$10$JRp/aS9DfhBHrz0/ppCwqOdpTknWa6anU8030Y.pECduPIHKDlCXa');
INSERT INTO `client_info` VALUES ('19', 'Jm', 'Mendoza', 'bejisep986@donumart.com', '$2y$12$cKHMbALuz/3WOBaE1WQnLuJ.gTHxemLGZoKs/DRtwGmXdhhB7tYg.', '2026-04-29 18:36:51', '$2y$12$cKHMbALuz/3WOBaE1WQnLuJ.gTHxemLGZoKs/DRtwGmXdhhB7tYg.');
INSERT INTO `client_info` VALUES ('20', 'Jm', 'Mendoza', 'nanitix435@hacknapp.com', '$2y$12$9FfPb65TgnOlakHe1/Q6P.kWgaxpYhlEXr9gFavbOF/4TJLZ03q42', '2026-04-29 23:29:37', '$2y$12$9FfPb65TgnOlakHe1/Q6P.kWgaxpYhlEXr9gFavbOF/4TJLZ03q42');
INSERT INTO `client_info` VALUES ('22', 'Jm', 'Mendoza', 'jekide9322@cadinr.com', '$2y$12$lxG5o42evEf0xkxYHUHnPeRTNKrUqEdTjUvszD64h3vEleZK8k1R2', '2026-04-30 15:27:27', '$2y$12$lxG5o42evEf0xkxYHUHnPeRTNKrUqEdTjUvszD64h3vEleZK8k1R2');
INSERT INTO `client_info` VALUES ('23', 'Jm', 'Mendoza', 'xagove4294@cadinr.com', '$2y$12$2IkkOEULC.JkcdppsnbZbucIybkJ5aXzM6xQrWEyeNuFslKYLlULq', '2026-04-30 18:53:12', '$2y$12$2IkkOEULC.JkcdppsnbZbucIybkJ5aXzM6xQrWEyeNuFslKYLlULq');

-- ----------------------------
-- Table structure for diagnostic_report_services
-- ----------------------------
DROP TABLE IF EXISTS `diagnostic_report_services`;
CREATE TABLE `diagnostic_report_services` (
  `report_service_id` int NOT NULL AUTO_INCREMENT,
  `diagnostic_id` int NOT NULL,
  `tenantID` int NOT NULL,
  `service_id` int NOT NULL,
  `parent_service_id` int DEFAULT NULL,
  `service_name` varchar(100) NOT NULL,
  `service_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `duration_minutes` int DEFAULT '0',
  `approval_status` enum('Pending','Approved','Declined') DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`report_service_id`),
  KEY `diagnostic_id` (`diagnostic_id`),
  KEY `tenantID` (`tenantID`),
  KEY `service_id` (`service_id`),
  KEY `parent_service_id` (`parent_service_id`),
  KEY `approval_status` (`approval_status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of diagnostic_report_services
-- ----------------------------
INSERT INTO `diagnostic_report_services` VALUES ('1', '1', '4', '30', '21', 'Coolant Flush', '800.00', '60', 'Pending', '2026-05-01 10:28:41', '2026-05-01 10:28:41');
INSERT INTO `diagnostic_report_services` VALUES ('2', '1', '4', '37', '33', 'Oil Change', '1000.00', '60', 'Pending', '2026-05-01 10:28:41', '2026-05-01 10:28:41');

-- ----------------------------
-- Table structure for diagnostic_reports
-- ----------------------------
DROP TABLE IF EXISTS `diagnostic_reports`;
CREATE TABLE `diagnostic_reports` (
  `diagnostic_id` int NOT NULL AUTO_INCREMENT,
  `appointment_id` int NOT NULL,
  `repair_job_id` int DEFAULT NULL,
  `tenantID` int NOT NULL,
  `mechanic_name` varchar(100) DEFAULT NULL,
  `problem_description` text,
  `findings` text,
  `recommended_action` text,
  `estimated_total` decimal(10,2) DEFAULT NULL,
  `customer_approval` enum('Pending','Approved','Declined') DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `diagnosis_status` enum('Draft','Submitted','Approved','Declined') DEFAULT 'Draft',
  `approved_at` datetime DEFAULT NULL,
  `declined_at` datetime DEFAULT NULL,
  `customer_notes` text,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`diagnostic_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of diagnostic_reports
-- ----------------------------
INSERT INTO `diagnostic_reports` VALUES ('1', '132', '37', '4', 'Jeemadmin1!', 'Check my vehicle', 'Found some issues specifically need for a oil change and Coolant Flush', 'None', '1800.00', 'Pending', '2026-05-01 10:28:41', 'Submitted', NULL, NULL, NULL, '2026-05-01 10:28:41');

-- ----------------------------
-- Table structure for inventory_items
-- ----------------------------
DROP TABLE IF EXISTS `inventory_items`;
CREATE TABLE `inventory_items` (
  `item_id` int NOT NULL AUTO_INCREMENT,
  `tenantID` int NOT NULL,
  `part_name` varchar(150) NOT NULL,
  `part_code` varchar(50) DEFAULT NULL,
  `category` enum('Engine','Electrical','Maintenance','Brakes','Suspension','Transmission','Cooling System','Diagnostics','Fluids','Electronics','Other') DEFAULT 'Other',
  `stock_quantity` int NOT NULL DEFAULT '0',
  `reorder_level` int NOT NULL DEFAULT '10',
  `unit_price` decimal(10,2) NOT NULL,
  `supplier_name` varchar(150) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`item_id`),
  UNIQUE KEY `part_code` (`part_code`),
  KEY `idx_tenant` (`tenantID`),
  KEY `idx_stock` (`stock_quantity`),
  CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`tenantID`) REFERENCES `owners` (`tenantID`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of inventory_items
-- ----------------------------
INSERT INTO `inventory_items` VALUES ('1', '2', 'Engine Oil', NULL, 'Cooling System', '7', '100', '600.00', 'Honda Gears', 'Inactive', '2026-04-03 04:43:52', '2026-04-27 11:50:24');
INSERT INTO `inventory_items` VALUES ('2', '2', 'Oil Filter', NULL, 'Maintenance', '99', '10', '850.00', 'RXS Auto Parts', 'Active', '2026-04-05 10:40:30', '2026-04-05 10:40:51');
INSERT INTO `inventory_items` VALUES ('3', '2', 'Air Filter', NULL, 'Cooling System', '0', '10', '500.00', 'RXS Auto Parts', 'Active', '2026-04-09 17:04:49', '2026-04-09 17:05:03');
INSERT INTO `inventory_items` VALUES ('4', '2', 'Fuel Filter', NULL, 'Maintenance', '15', '10', '450.00', NULL, 'Active', '2026-04-09 18:30:44', '2026-04-09 18:30:44');

-- ----------------------------
-- Table structure for owners
-- ----------------------------
DROP TABLE IF EXISTS `owners`;
CREATE TABLE `owners` (
  `tenantID` int NOT NULL AUTO_INCREMENT,
  `invite_code` char(6) NOT NULL,
  `ownerName` varchar(150) DEFAULT NULL,
  `shopName` varchar(75) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `login_slug` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `first_login` tinyint(1) DEFAULT '1',
  `contactNumber` varchar(50) DEFAULT NULL,
  `shopAddress` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('Pending','Active','Inactive','Suspended') DEFAULT 'Pending',
  `subscription_plan` varchar(40) DEFAULT NULL,
  `billing_cycle` varchar(20) DEFAULT NULL,
  `subscription_start` date DEFAULT NULL,
  `subscription_end` date DEFAULT NULL,
  `plan_price` decimal(10,2) DEFAULT NULL,
  `billing_notification_sent` tinyint(1) DEFAULT '0',
  `next_billing_date` date DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `business_permit_image` varchar(255) DEFAULT NULL,
  `valid_id_image` varchar(255) DEFAULT NULL,
  `bir_certificate_image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`tenantID`),
  UNIQUE KEY `login_slug` (`login_slug`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of owners
-- ----------------------------
INSERT INTO `owners` VALUES ('1', '234223', 'Jm Mendoza', 'EDMs Auto Shop', 'edmshoprr123', 'edms-auto-shop', 'mendozajohmaverick1@gmail.com', '$2y$12$I0xTTr8Z/X8P/ElgEaUOseomel9I2FlET/F6Qb5W2ct5NeLuAbBbu', '0', '09356394055', 'Baliwag City Bulacan', '2026-03-23 15:21:52', 'Active', 'medium', 'quarterly', '2026-03-26', '2026-06-26', '14997.00', '0', '2026-06-26', 'd814e7a8edb09867856846d54461659e5e0541052242cc3a56b1679f5d8dd50e', '2026-05-01 07:37:20', NULL, NULL, NULL);
INSERT INTO `owners` VALUES ('2', '643323', 'Amiel Carl Santos', 'RDM', 'rdmshoprr123', 'rdm', 'johnlurjmendoza1@gmail.com', '$2y$12$MH8esN/9ydDp1DaoER3rxOmNCpRflY4cZnUtWNOrVYXBKydO53Ny.', '0', '09356394055', 'San Rafael Bulacan', '2026-03-25 16:40:56', 'Active', 'basic', 'monthly', '2026-03-26', '2026-04-26', '999.00', '0', '2026-04-26', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `owners` VALUES ('3', '345632', 'Juan Tamad', 'RXS Auto Shop', 'rxsautoshop', 'rxs-auto-shop', 'meloce1143@azucore.com', 'BtmqeUEyihJb', '1', '09356394055', 'San Rafael Bulacan', '2026-04-08 17:33:01', 'Active', 'basic', 'monthly', '2026-04-08', '2026-05-08', '999.00', '0', '2026-05-08', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `owners` VALUES ('4', '317498', 'Jm Mendoza', 'AutoMotivation Auto Repair Shop', 'automotivationautore', 'automotivation-auto-repair-shop', 'xagove4294@cadinr.com', '$2y$12$XYKqa8D8bMArk4QV3Nv3E.oNRxr9/33e8CsDg5JT9b6gVLO1ht5hK', '0', '9356394055', '218, Ulingao, San Rafael, Bulacan', '2026-05-01 04:42:02', 'Active', 'basic', 'quarterly', '2026-05-01', '2026-08-01', '2997.00', '0', '2026-08-01', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `owners` VALUES ('5', '788648', 'Collin Philipp', 'AutoMatik Repair Shop', NULL, 'automatik-repair-shop', 'hasevom391@inraud.com', '2TDhDX6TDHoY', '1', '9356394055', '218, Ulingao, San Rafael, Bulacan', '2026-05-01 11:06:06', 'Pending', 'basic', 'monthly', NULL, NULL, NULL, '0', NULL, NULL, NULL, 'uploads/tenant_documents/005_business_permit_image_1777633565_2874.jpg', 'uploads/tenant_documents/005_valid_id_image_1777633565_7661.jpg', 'uploads/tenant_documents/005_bir_certificate_image_1777633565_6351.jpg');
INSERT INTO `owners` VALUES ('6', '145413', 'Juan Tamad', 'Kaloy\'s', NULL, 'kaloy-s', 'djqbdhjqw@gmail.com', 'mBPFvD4W3Unt', '1', '9356394055', 'San Rafael Bulacan', '2026-05-01 11:23:53', 'Pending', 'medium', 'yearly', NULL, NULL, NULL, '0', NULL, NULL, NULL, 'uploads/tenant_documents/006_business_permit_image_1777634632_7051.jpg', 'uploads/tenant_documents/006_valid_id_image_1777634632_9841.jpg', 'uploads/tenant_documents/006_bir_certificate_image_1777634632_6718.png');

-- ----------------------------
-- Table structure for payment_methods
-- ----------------------------
DROP TABLE IF EXISTS `payment_methods`;
CREATE TABLE `payment_methods` (
  `payment_method_id` int NOT NULL AUTO_INCREMENT,
  `tenantID` int NOT NULL,
  `method_type` enum('card','wallet','bank_transfer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `card_brand` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_last_four` char(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_expiry_month` int DEFAULT NULL,
  `card_expiry_year` int DEFAULT NULL,
  `wallet_provider` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wallet_identifier` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_method_id`),
  KEY `idx_tenant_primary` (`tenantID`,`is_primary`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `payment_methods_ibfk_1` FOREIGN KEY (`tenantID`) REFERENCES `owners` (`tenantID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for payments
-- ----------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `payment_id` int NOT NULL AUTO_INCREMENT,
  `tenantID` int NOT NULL,
  `user_id` int NOT NULL,
  `appointment_id` int NOT NULL,
  `paymentAmount` decimal(10,2) NOT NULL,
  `amountPaid` decimal(10,2) NOT NULL DEFAULT '0.00',
  `balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `paymentMethod` enum('Cash','GCash','Card','Bank Transfer') NOT NULL,
  `paymentDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `paymentStatus` enum('Pending','Partial','Paid','Failed','Refunded') DEFAULT 'Pending',
  `referenceNumber` varchar(100) DEFAULT NULL,
  `gcashReferenceNumber` varchar(100) DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `idx_payments_tenant` (`tenantID`),
  KEY `idx_payments_user` (`user_id`),
  KEY `idx_payments_appointment` (`appointment_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`tenantID`) REFERENCES `owners` (`tenantID`),
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of payments
-- ----------------------------
INSERT INTO `payments` VALUES ('21', '4', '22', '132', '500.00', '0.00', '500.00', 'Cash', '2026-05-01 08:31:28', 'Pending', 'AP-00132', NULL, '', '2026-05-01 08:31:28', '2026-05-01 08:31:28');

-- ----------------------------
-- Table structure for repair_job_services
-- ----------------------------
DROP TABLE IF EXISTS `repair_job_services`;
CREATE TABLE `repair_job_services` (
  `repair_job_service_id` int NOT NULL AUTO_INCREMENT,
  `repair_job_id` int NOT NULL,
  `tenantID` int NOT NULL,
  `service_id` int NOT NULL,
  `service_price` decimal(10,2) NOT NULL,
  `estimated_duration_minutes` int DEFAULT NULL,
  `actual_duration_minutes` int DEFAULT NULL,
  `service_status` enum('Pending','In Progress','Paused','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `technician_name` varchar(100) DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`repair_job_service_id`),
  KEY `idx_rjs_job` (`repair_job_id`),
  KEY `idx_rjs_tenant` (`tenantID`),
  KEY `idx_rjs_service` (`service_id`),
  CONSTRAINT `repair_job_services_ibfk_1` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`repair_job_id`) ON DELETE CASCADE,
  CONSTRAINT `repair_job_services_ibfk_2` FOREIGN KEY (`tenantID`) REFERENCES `owners` (`tenantID`),
  CONSTRAINT `repair_job_services_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`)
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of repair_job_services
-- ----------------------------
INSERT INTO `repair_job_services` VALUES ('52', '36', '2', '5', '1500.00', '60', NULL, 'In Progress', NULL, NULL, '2026-04-29 18:18:02', '2026-04-29 18:18:02');
INSERT INTO `repair_job_services` VALUES ('53', '37', '4', '19', '500.00', '60', NULL, 'In Progress', NULL, NULL, '2026-05-01 08:32:41', '2026-05-01 08:32:41');

-- ----------------------------
-- Table structure for repair_jobs
-- ----------------------------
DROP TABLE IF EXISTS `repair_jobs`;
CREATE TABLE `repair_jobs` (
  `repair_job_id` int NOT NULL AUTO_INCREMENT,
  `tenantID` int NOT NULL,
  `appointment_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `vehicle_id` int NOT NULL,
  `job_order_no` varchar(50) NOT NULL,
  `bay_no` varchar(20) DEFAULT NULL,
  `assigned_technician` varchar(100) DEFAULT NULL,
  `job_status` enum('Queued','In Progress','Diagnostics','Waiting for Parts','Quality Check','Ready for Pickup','Completed','Cancelled') NOT NULL DEFAULT 'Queued',
  `priority` enum('Low','Normal','High','Urgent') NOT NULL DEFAULT 'Normal',
  `concern` text,
  `diagnosis_notes` text,
  `progress_notes` text,
  `internal_notes` text,
  `check_in_time` datetime DEFAULT NULL,
  `work_started_at` datetime DEFAULT NULL,
  `estimated_finish_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `labor_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parts_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`repair_job_id`),
  UNIQUE KEY `job_order_no` (`job_order_no`),
  KEY `appointment_id` (`appointment_id`),
  KEY `idx_repair_jobs_tenant` (`tenantID`),
  KEY `idx_repair_jobs_status` (`job_status`),
  KEY `idx_repair_jobs_user` (`user_id`),
  KEY `idx_repair_jobs_vehicle` (`vehicle_id`),
  CONSTRAINT `repair_jobs_ibfk_1` FOREIGN KEY (`tenantID`) REFERENCES `owners` (`tenantID`),
  CONSTRAINT `repair_jobs_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`),
  CONSTRAINT `repair_jobs_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `repair_jobs_ibfk_4` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicleinformation` (`vehicle_id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of repair_jobs
-- ----------------------------
INSERT INTO `repair_jobs` VALUES ('36', '2', '131', '5', '9', 'RR-00001', 'Bay 1', 'jiem123', 'Completed', 'Normal', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-29 18:18:25', NULL, '0.00', '0.00', '0.00', '2026-04-29 18:18:02', '2026-04-29 18:18:25');
INSERT INTO `repair_jobs` VALUES ('37', '4', '132', '22', '11', 'RR-00132', 'Bay 1', 'Jeemadmin1!', 'Diagnostics', 'Normal', 'Check my vehicle', 'Found some issues specifically need for a oil change and Coolant Flush', NULL, NULL, NULL, '2026-05-01 09:30:15', NULL, NULL, NULL, '0.00', '0.00', '1800.00', '2026-05-01 08:31:28', '2026-05-01 10:28:41');

-- ----------------------------
-- Table structure for roles
-- ----------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `role_id` int NOT NULL AUTO_INCREMENT,
  `role_name` varchar(100) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(100) DEFAULT NULL,
  `access_scope` varchar(255) NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `tenantID` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of roles
-- ----------------------------
INSERT INTO `roles` VALUES ('10', 'Senior Mechanic', 'Juan', 'Tamad', 'juantamad', 'juantamad@gmail.com', '$2y$10$7E4pewvYMtC4OftHiu3xnOSQqVlUiaH3lSxXOo9yo.M6Z833nIPfy', 'Rapair Jobs, Appointments, Inventory', '1', 'Active', '1', '2026-04-09 17:29:21', '2026-04-09 17:29:21', NULL, NULL);
INSERT INTO `roles` VALUES ('16', 'Senior Technician', 'Jm', 'Mendoza', 'jiem123', 'mendozajohmaverick1@gmail.com', '$2y$10$uWIxG6HEedpvmbNgrahgDepDAtkCzr81ug1k0Z2RspDaYuztDiRw6', 'Dashboard,Repair Jobs,Vehicles,Inventory', '1', 'Active', '2', '2026-04-23 12:42:32', '2026-04-23 12:42:32', NULL, NULL);
INSERT INTO `roles` VALUES ('17', 'Admin', 'Jm', 'Mendoza', 'Jeemadmin1!', 'johnmaverickmendoza1@gmail.com', '$2y$12$nbIiDpNhsKy3BsIfkzbHwuYcn1hbwoylwDXJMMOMJ3L9sZvFvOekC', 'Dashboard,Appointments,Repair Jobs,Vehicles,Inventory,Customers,Payments,Billing,Reports,Settings,Logs', '1', 'Active', '4', '2026-05-01 05:25:47', '2026-05-01 05:25:47', NULL, NULL);

-- ----------------------------
-- Table structure for services
-- ----------------------------
DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
  `service_id` int NOT NULL AUTO_INCREMENT,
  `tenantID` int NOT NULL,
  `parent_service_id` int DEFAULT NULL,
  `service_type` enum('Main','Sub') NOT NULL DEFAULT 'Main',
  `service_name` varchar(100) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `duration_minutes` int DEFAULT NULL,
  `category` enum('Engine','Electrical','Maintenance','Brakes','Suspension','Transmission','Cooling System','Diagnostics','Other') DEFAULT 'Other',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`service_id`),
  UNIQUE KEY `unique_service_per_tenant` (`tenantID`,`service_name`),
  KEY `idx_tenant` (`tenantID`),
  KEY `fk_services_parent` (`parent_service_id`),
  CONSTRAINT `fk_services_parent` FOREIGN KEY (`parent_service_id`) REFERENCES `services` (`service_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_services_tenant` FOREIGN KEY (`tenantID`) REFERENCES `owners` (`tenantID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of services
-- ----------------------------
INSERT INTO `services` VALUES ('2', '1', NULL, 'Main', 'Axle Bearings', 'Change in Axle Bearings', '2000.00', '240', 'Transmission', 'Active', '2026-04-01 17:55:43', '2026-04-01 17:55:43');
INSERT INTO `services` VALUES ('5', '2', NULL, 'Main', 'Preventive Maintenance Service (PMS)', 'Monthly Maintenance', '1500.00', '60', 'Maintenance', 'Inactive', '2026-04-03 11:21:10', '2026-04-30 12:53:31');
INSERT INTO `services` VALUES ('10', '2', NULL, 'Main', 'Engine Services', '', '500.00', '30', 'Engine', 'Active', '2026-04-30 12:24:51', '2026-04-30 12:24:51');
INSERT INTO `services` VALUES ('11', '2', '10', 'Sub', 'Engine Tune Up', '', '1600.00', '60', 'Engine', 'Active', '2026-04-30 12:24:51', '2026-04-30 15:39:10');
INSERT INTO `services` VALUES ('12', '2', '10', 'Sub', 'Fuel Injection Cleaning', 'Cleaning of Fuel Injection', '1000.00', '60', 'Engine', 'Active', '2026-04-30 12:48:46', '2026-04-30 12:49:05');
INSERT INTO `services` VALUES ('13', '2', '10', 'Sub', 'Spark Plug Replacement', '', '800.00', '30', 'Engine', 'Active', '2026-04-30 12:52:55', '2026-04-30 12:52:55');
INSERT INTO `services` VALUES ('14', '2', NULL, 'Main', 'Suspension Services', '', '500.00', '30', 'Suspension', 'Active', '2026-04-30 15:38:20', '2026-04-30 15:38:20');
INSERT INTO `services` VALUES ('15', '2', '14', 'Sub', 'Shock Absorber Replacement', '', '2000.00', '119', 'Suspension', 'Active', '2026-04-30 15:38:20', '2026-04-30 15:38:20');
INSERT INTO `services` VALUES ('17', '2', NULL, 'Main', 'Maintenance Services', '', '800.00', '30', 'Maintenance', 'Active', '2026-04-30 15:40:01', '2026-04-30 15:40:01');
INSERT INTO `services` VALUES ('18', '2', '17', 'Sub', 'Oil Change', '', '1000.00', '30', 'Maintenance', 'Active', '2026-04-30 15:40:33', '2026-04-30 15:40:33');
INSERT INTO `services` VALUES ('19', '4', NULL, 'Main', 'Diagnostics', '', '1000.00', '0', 'Diagnostics', 'Active', '2026-05-01 05:13:13', '2026-05-01 08:35:20');
INSERT INTO `services` VALUES ('21', '4', NULL, 'Main', 'Cooling System Services', '', '500.00', '60', 'Cooling System', 'Active', '2026-05-01 05:14:14', '2026-05-01 05:14:14');
INSERT INTO `services` VALUES ('22', '4', '21', 'Sub', 'Radiator Repair', '', '1500.00', '118', 'Cooling System', 'Active', '2026-05-01 05:14:14', '2026-05-01 05:14:14');
INSERT INTO `services` VALUES ('23', '4', NULL, 'Main', 'Transmission Services', '', '500.00', '30', 'Transmission', 'Active', '2026-05-01 05:15:51', '2026-05-01 05:15:51');
INSERT INTO `services` VALUES ('24', '4', '23', 'Sub', 'Transmission Fluid Change', '', '1000.00', '60', 'Transmission', 'Active', '2026-05-01 05:15:51', '2026-05-01 05:15:51');
INSERT INTO `services` VALUES ('25', '4', '23', 'Sub', 'Clutch Replacement', '', '1999.99', '60', 'Transmission', 'Active', '2026-05-01 05:15:51', '2026-05-01 05:15:51');
INSERT INTO `services` VALUES ('26', '4', '23', 'Sub', 'Gearbox Repair', '', '1000.00', '60', 'Transmission', 'Active', '2026-05-01 05:15:51', '2026-05-01 05:15:51');
INSERT INTO `services` VALUES ('27', '4', '23', 'Sub', 'Transmission Diagnostic', '', '800.00', '60', 'Transmission', 'Active', '2026-05-01 05:15:51', '2026-05-01 05:15:51');
INSERT INTO `services` VALUES ('28', '4', '21', 'Sub', 'Thermostat Replacement', '', '1599.00', '60', 'Cooling System', 'Active', '2026-05-01 05:19:08', '2026-05-01 05:19:08');
INSERT INTO `services` VALUES ('29', '4', '21', 'Sub', 'Water Pump Replacement', '', '2000.00', '0', 'Cooling System', 'Active', '2026-05-01 05:19:32', '2026-05-01 05:19:32');
INSERT INTO `services` VALUES ('30', '4', '21', 'Sub', 'Coolant Flush', '', '800.00', '60', 'Cooling System', 'Active', '2026-05-01 05:19:48', '2026-05-01 05:19:48');
INSERT INTO `services` VALUES ('33', '4', NULL, 'Main', 'Maintenance Services', '', '500.00', '0', 'Maintenance', 'Active', '2026-05-01 05:23:28', '2026-05-01 05:23:28');
INSERT INTO `services` VALUES ('34', '4', '33', 'Sub', 'Preventive Maintenance Service (PMS)', '', '7000.00', '60', 'Maintenance', 'Active', '2026-05-01 05:23:28', '2026-05-01 05:23:28');
INSERT INTO `services` VALUES ('35', '4', '33', 'Sub', 'Cabin Filter Replacement', '', '3000.00', '60', 'Maintenance', 'Active', '2026-05-01 05:24:12', '2026-05-01 05:24:12');
INSERT INTO `services` VALUES ('36', '4', '33', 'Sub', 'Air Filter Replacement', '', '2000.00', '60', 'Maintenance', 'Active', '2026-05-01 05:24:29', '2026-05-01 05:24:29');
INSERT INTO `services` VALUES ('37', '4', '33', 'Sub', 'Oil Change', '', '1000.00', '60', 'Maintenance', 'Active', '2026-05-01 05:24:41', '2026-05-01 05:24:41');

-- ----------------------------
-- Table structure for stock_movements
-- ----------------------------
DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
  `movement_id` int NOT NULL AUTO_INCREMENT,
  `tenantID` int NOT NULL,
  `item_id` int NOT NULL,
  `movement_type` enum('IN','OUT','ADJUSTMENT') NOT NULL,
  `quantity` int NOT NULL,
  `reference_type` enum('Purchase','RepairJob','Manual') DEFAULT 'Manual',
  `reference_id` int DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`movement_id`),
  KEY `idx_item` (`item_id`),
  KEY `idx_tenant` (`tenantID`),
  CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`tenantID`) REFERENCES `owners` (`tenantID`),
  CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of stock_movements
-- ----------------------------
INSERT INTO `stock_movements` VALUES ('1', '2', '1', 'IN', '10', 'Manual', NULL, 'Initial stock when item was created.', '2026-04-03 04:43:52');
INSERT INTO `stock_movements` VALUES ('2', '2', '2', 'IN', '100', 'Manual', NULL, 'Initial stock when item was created.', '2026-04-05 10:40:30');
INSERT INTO `stock_movements` VALUES ('3', '2', '2', 'OUT', '1', 'RepairJob', NULL, NULL, '2026-04-05 10:40:51');
INSERT INTO `stock_movements` VALUES ('4', '2', '1', 'OUT', '1', 'RepairJob', NULL, NULL, '2026-04-06 04:49:03');
INSERT INTO `stock_movements` VALUES ('5', '2', '3', 'IN', '1', 'Manual', NULL, 'Initial stock when item was created.', '2026-04-09 17:04:49');
INSERT INTO `stock_movements` VALUES ('6', '2', '3', 'OUT', '1', 'RepairJob', NULL, NULL, '2026-04-09 17:05:03');
INSERT INTO `stock_movements` VALUES ('7', '2', '4', 'IN', '15', 'Manual', NULL, 'Initial stock when item was created.', '2026-04-09 18:30:44');
INSERT INTO `stock_movements` VALUES ('8', '2', '1', 'OUT', '2', 'RepairJob', NULL, NULL, '2026-04-27 11:50:24');

-- ----------------------------
-- Table structure for subscription_payments
-- ----------------------------
DROP TABLE IF EXISTS `subscription_payments`;
CREATE TABLE `subscription_payments` (
  `payment_id` int NOT NULL AUTO_INCREMENT,
  `tenantID` int NOT NULL,
  `subscription_id` int DEFAULT NULL,
  `plan_id` int DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_provider` varchar(50) DEFAULT 'PayMongo',
  `payment_method` enum('Cash','GCash','Card','PayMaya','GrabPay','QRPH','Bank Transfer') DEFAULT 'Card',
  `payment_status` enum('Pending','Paid','Failed','Refunded') DEFAULT 'Pending',
  `checkout_session_id` varchar(100) DEFAULT NULL,
  `paymongo_payment_id` varchar(100) DEFAULT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `gcash_reference` varchar(100) DEFAULT NULL,
  `billing_period_start` date DEFAULT NULL,
  `billing_period_end` date DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `next_billing_date` date DEFAULT NULL,
  `raw_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `idx_tenantID` (`tenantID`),
  KEY `idx_subscription_id` (`subscription_id`),
  KEY `idx_checkout_session_id` (`checkout_session_id`),
  KEY `idx_paymongo_payment_id` (`paymongo_payment_id`),
  KEY `idx_payment_status` (`payment_status`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of subscription_payments
-- ----------------------------
INSERT INTO `subscription_payments` VALUES ('11', '4', '27', '1', '2997.00', 'PayMongo', 'GCash', 'Paid', 'cs_d65020f906a0608bd40d8fec', 'pay_Nm6b1phyMYMEKQSf4VzVQT6H', 'pay_Nm6b1phyMYMEKQSf4VzVQT6H', 'pay_Nm6b1phyMYMEKQSf4VzVQT6H', '2026-05-01', '2026-08-01', '2026-05-01 04:42:44', '2026-08-01', '{\"data\": {\"id\": \"evt_Z4XTCLZaDDiAmtrg6wqXgNSL\", \"type\": \"event\", \"attributes\": {\"data\": {\"id\": \"cs_d65020f906a0608bd40d8fec\", \"type\": \"checkout_session\", \"attributes\": {\"status\": \"active\", \"billing\": {\"name\": \"JM Mendoza\", \"email\": \"xagove4294@cadinr.com\", \"phone\": \"09171234567\", \"address\": {\"city\": null, \"line1\": null, \"line2\": null, \"state\": null, \"country\": null, \"postal_code\": null}}, \"paid_at\": 1777610563, \"livemode\": false, \"merchant\": \"Jm Mendoza\", \"metadata\": null, \"payments\": [{\"id\": \"pay_Nm6b1phyMYMEKQSf4VzVQT6H\", \"type\": \"payment\", \"attributes\": {\"fee\": 7493, \"taxes\": [], \"amount\": 299700, \"payout\": null, \"source\": {\"id\": \"src_7yizeFQmeYbkerVDZkvQqEZE\", \"type\": \"gcash\", \"provider\": {\"id\": null}, \"provider_id\": null}, \"status\": \"paid\", \"billing\": {\"name\": \"JM Mendoza\", \"email\": \"xagove4294@cadinr.com\", \"phone\": \"09171234567\", \"address\": {\"city\": null, \"line1\": null, \"line2\": null, \"state\": null, \"country\": null, \"postal_code\": null}}, \"paid_at\": 1777610563, \"refunds\": [], \"currency\": \"PHP\", \"disputed\": false, \"livemode\": false, \"metadata\": null, \"promotion\": null, \"access_url\": null, \"created_at\": 1777610564, \"net_amount\": 292207, \"tax_amount\": null, \"updated_at\": 1777610564, \"credited_at\": 1778115600, \"description\": \"Tenant ID: 4 | Plan ID: 1 | Billing Cycle: quarterly\", \"foreign_fee\": null, \"available_at\": 1777971600, \"payment_intent_id\": \"pi_jq3kJppgS3i9gUDFZSBL9ncs\", \"instant_settlement\": null, \"statement_descriptor\": \"Jm Mendoza\", \"balance_transaction_id\": \"bal_txn_37MSPyhDn6f3cW5cgjeEZWVX\", \"external_reference_number\": null, \"digital_withholding_vat_amount\": 0}}], \"cancel_url\": \"http://localhost/RapidRepair/clientapplication/paymongo/payment_failed.php?source=clientpayment\", \"client_key\": \"cs_d65020f906a0608bd40d8fec_client_6373849e8a2a250cb521f3e9\", \"created_at\": 1777610552, \"line_items\": [{\"name\": \"Basic Plan Plan - Quarterly Billing\", \"amount\": 299700, \"images\": [], \"currency\": \"PHP\", \"quantity\": 1, \"description\": \"Basic Plan Plan - Quarterly Billing\"}], \"updated_at\": 1777610561, \"customer_id\": null, \"description\": \"Tenant ID: 4 | Plan ID: 1 | Billing Cycle: quarterly\", \"success_url\": \"http://localhost/RapidRepair/clientapplication/paymongo/payment_success.php?source=clientpayment\", \"checkout_url\": \"https://checkout.paymongo.com/d65020f906a0608bd40d8fec\", \"customer_email\": null, \"payment_intent\": {\"id\": \"pi_jq3kJppgS3i9gUDFZSBL9ncs\", \"type\": \"payment_intent\", \"attributes\": {\"amount\": 299700, \"status\": \"succeeded\", \"currency\": \"PHP\", \"livemode\": false, \"metadata\": null, \"payments\": [{\"id\": \"pay_Nm6b1phyMYMEKQSf4VzVQT6H\", \"type\": \"payment\", \"attributes\": {\"fee\": 7493, \"taxes\": [], \"amount\": 299700, \"payout\": null, \"source\": {\"id\": \"src_7yizeFQmeYbkerVDZkvQqEZE\", \"type\": \"gcash\", \"provider\": {\"id\": null}, \"provider_id\": null}, \"status\": \"paid\", \"billing\": {\"name\": \"JM Mendoza\", \"email\": \"xagove4294@cadinr.com\", \"phone\": \"09171234567\", \"address\": {\"city\": null, \"line1\": null, \"line2\": null, \"state\": null, \"country\": null, \"postal_code\": null}}, \"paid_at\": 1777610563, \"refunds\": [], \"currency\": \"PHP\", \"disputed\": false, \"livemode\": false, \"metadata\": null, \"promotion\": null, \"access_url\": null, \"created_at\": 1777610564, \"net_amount\": 292207, \"tax_amount\": null, \"updated_at\": 1777610564, \"credited_at\": 1778115600, \"description\": \"Tenant ID: 4 | Plan ID: 1 | Billing Cycle: quarterly\", \"foreign_fee\": null, \"available_at\": 1777971600, \"payment_intent_id\": \"pi_jq3kJppgS3i9gUDFZSBL9ncs\", \"instant_settlement\": null, \"statement_descriptor\": \"Jm Mendoza\", \"balance_transaction_id\": \"bal_txn_37MSPyhDn6f3cW5cgjeEZWVX\", \"external_reference_number\": null, \"digital_withholding_vat_amount\": 0}}], \"client_key\": \"pi_jq3kJppgS3i9gUDFZSBL9ncs_client_zbYD9gY5s7NndBTTpdFVVciv\", \"created_at\": 1777610552, \"updated_at\": 1777610564, \"description\": \"Tenant ID: 4 | Plan ID: 1 | Billing Cycle: quarterly\", \"next_action\": null, \"capture_type\": \"automatic\", \"original_amount\": 299700, \"last_payment_error\": null, \"setup_future_usage\": null, \"statement_descriptor\": \"Jm Mendoza\", \"payment_method_allowed\": [\"card\", \"gcash\", \"qrph\", \"grab_pay\", \"paymaya\"], \"payment_method_options\": {\"card\": {\"request_three_d_secure\": \"any\"}}}}, \"show_line_items\": true, \"reference_number\": null, \"show_description\": true, \"send_email_receipt\": false, \"payment_method_used\": \"gcash\", \"payment_method_types\": [\"card\", \"gcash\", \"paymaya\", \"grab_pay\", \"qrph\"], \"billing_information_fields_editable\": \"enabled\"}}, \"type\": \"checkout_session.payment.paid\", \"livemode\": false, \"created_at\": 1777610564, \"updated_at\": 1777610564, \"previous_data\": {}, \"pending_webhooks\": 1}}}', '2026-05-01 04:42:44', '2026-05-01 04:42:44');
INSERT INTO `subscription_payments` VALUES ('13', '5', '29', '1', '999.00', 'PayMongo', 'GCash', 'Paid', 'cs_fe75c9ab84ad82c81c0200c3', 'pay_pPiPczUR6wikCTAFg42BGxPD', 'pay_pPiPczUR6wikCTAFg42BGxPD', 'pay_pPiPczUR6wikCTAFg42BGxPD', '2026-05-01', '2026-06-01', '2026-05-01 11:06:16', '2026-06-01', '{\"data\": {\"id\": \"evt_zJormnkx1AL2qvrCXWR83Bxa\", \"type\": \"event\", \"attributes\": {\"data\": {\"id\": \"cs_fe75c9ab84ad82c81c0200c3\", \"type\": \"checkout_session\", \"attributes\": {\"status\": \"active\", \"billing\": {\"name\": \"Customer\", \"email\": \"hasevom391@inraud.com\", \"phone\": \"09171234567\", \"address\": {\"city\": null, \"line1\": null, \"line2\": null, \"state\": null, \"country\": null, \"postal_code\": null}}, \"paid_at\": 1777633575, \"livemode\": false, \"merchant\": \"Jm Mendoza\", \"metadata\": null, \"payments\": [{\"id\": \"pay_pPiPczUR6wikCTAFg42BGxPD\", \"type\": \"payment\", \"attributes\": {\"fee\": 2498, \"taxes\": [], \"amount\": 99900, \"payout\": null, \"source\": {\"id\": \"src_FsAfFhBcLfaYwYRqiUPxS6RV\", \"type\": \"gcash\", \"provider\": {\"id\": null}, \"provider_id\": null}, \"status\": \"paid\", \"billing\": {\"name\": \"Customer\", \"email\": \"hasevom391@inraud.com\", \"phone\": \"09171234567\", \"address\": {\"city\": null, \"line1\": null, \"line2\": null, \"state\": null, \"country\": null, \"postal_code\": null}}, \"paid_at\": 1777633575, \"refunds\": [], \"currency\": \"PHP\", \"disputed\": false, \"livemode\": false, \"metadata\": null, \"promotion\": null, \"access_url\": null, \"created_at\": 1777633576, \"net_amount\": 97402, \"tax_amount\": null, \"updated_at\": 1777633576, \"credited_at\": 1778115600, \"description\": \"Tenant ID: 5 | Plan ID: 1 | Billing Cycle: monthly\", \"foreign_fee\": null, \"available_at\": 1778058000, \"payment_intent_id\": \"pi_Fmt4gNHYa2xmNzdAm18ZFLZe\", \"instant_settlement\": null, \"statement_descriptor\": \"Jm Mendoza\", \"balance_transaction_id\": \"bal_txn_KGe1EjpotHANGE4S79wu5kHv\", \"external_reference_number\": null, \"digital_withholding_vat_amount\": 0}}], \"cancel_url\": \"http://localhost/RapidRepair/clientapplication/paymongo/payment_failed.php?source=clientpayment\", \"client_key\": \"cs_fe75c9ab84ad82c81c0200c3_client_2f1a5821be36dd9a3a1f19ab\", \"created_at\": 1777633569, \"line_items\": [{\"name\": \"Basic Plan Plan - Monthly Billing\", \"amount\": 99900, \"images\": [], \"currency\": \"PHP\", \"quantity\": 1, \"description\": \"Basic Plan Plan - Monthly Billing\"}], \"updated_at\": 1777633574, \"customer_id\": null, \"description\": \"Tenant ID: 5 | Plan ID: 1 | Billing Cycle: monthly\", \"success_url\": \"http://localhost/RapidRepair/clientapplication/paymongo/payment_success.php?source=clientpayment\", \"checkout_url\": \"https://checkout.paymongo.com/fe75c9ab84ad82c81c0200c3\", \"customer_email\": null, \"payment_intent\": {\"id\": \"pi_Fmt4gNHYa2xmNzdAm18ZFLZe\", \"type\": \"payment_intent\", \"attributes\": {\"amount\": 99900, \"status\": \"succeeded\", \"currency\": \"PHP\", \"livemode\": false, \"metadata\": null, \"payments\": [{\"id\": \"pay_pPiPczUR6wikCTAFg42BGxPD\", \"type\": \"payment\", \"attributes\": {\"fee\": 2498, \"taxes\": [], \"amount\": 99900, \"payout\": null, \"source\": {\"id\": \"src_FsAfFhBcLfaYwYRqiUPxS6RV\", \"type\": \"gcash\", \"provider\": {\"id\": null}, \"provider_id\": null}, \"status\": \"paid\", \"billing\": {\"name\": \"Customer\", \"email\": \"hasevom391@inraud.com\", \"phone\": \"09171234567\", \"address\": {\"city\": null, \"line1\": null, \"line2\": null, \"state\": null, \"country\": null, \"postal_code\": null}}, \"paid_at\": 1777633575, \"refunds\": [], \"currency\": \"PHP\", \"disputed\": false, \"livemode\": false, \"metadata\": null, \"promotion\": null, \"access_url\": null, \"created_at\": 1777633576, \"net_amount\": 97402, \"tax_amount\": null, \"updated_at\": 1777633576, \"credited_at\": 1778115600, \"description\": \"Tenant ID: 5 | Plan ID: 1 | Billing Cycle: monthly\", \"foreign_fee\": null, \"available_at\": 1778058000, \"payment_intent_id\": \"pi_Fmt4gNHYa2xmNzdAm18ZFLZe\", \"instant_settlement\": null, \"statement_descriptor\": \"Jm Mendoza\", \"balance_transaction_id\": \"bal_txn_KGe1EjpotHANGE4S79wu5kHv\", \"external_reference_number\": null, \"digital_withholding_vat_amount\": 0}}], \"client_key\": \"pi_Fmt4gNHYa2xmNzdAm18ZFLZe_client_2W4ptu7gqBX7zNvvFuTv1Q4u\", \"created_at\": 1777633569, \"updated_at\": 1777633576, \"description\": \"Tenant ID: 5 | Plan ID: 1 | Billing Cycle: monthly\", \"next_action\": null, \"capture_type\": \"automatic\", \"original_amount\": 99900, \"last_payment_error\": null, \"setup_future_usage\": null, \"statement_descriptor\": \"Jm Mendoza\", \"payment_method_allowed\": [\"qrph\", \"grab_pay\", \"gcash\", \"paymaya\", \"card\"], \"payment_method_options\": {\"card\": {\"request_three_d_secure\": \"any\"}}}}, \"show_line_items\": true, \"reference_number\": null, \"show_description\": true, \"send_email_receipt\": false, \"payment_method_used\": \"gcash\", \"payment_method_types\": [\"card\", \"gcash\", \"paymaya\", \"grab_pay\", \"qrph\"], \"billing_information_fields_editable\": \"enabled\"}}, \"type\": \"checkout_session.payment.paid\", \"livemode\": false, \"created_at\": 1777633576, \"updated_at\": 1777633576, \"previous_data\": {}, \"pending_webhooks\": 1}}}', '2026-05-01 11:06:16', '2026-05-01 11:06:16');
INSERT INTO `subscription_payments` VALUES ('14', '6', '30', '2', '35988.00', 'PayMongo', 'QRPH', 'Paid', 'cs_1b149f50f8c4f185d141508c', 'pay_Y6JYoW4hfFyryppMW9Eww1Ks', 'pay_Y6JYoW4hfFyryppMW9Eww1Ks', NULL, '2026-05-01', '2027-05-01', '2026-05-01 11:24:24', '2027-05-01', '{\"data\": {\"id\": \"evt_SmDChdvcCWHwWuaW1CqxwY7T\", \"type\": \"event\", \"attributes\": {\"data\": {\"id\": \"cs_1b149f50f8c4f185d141508c\", \"type\": \"checkout_session\", \"attributes\": {\"status\": \"active\", \"billing\": {\"name\": \"Customer\", \"email\": \"djqbdhjqw@gmail.com\", \"phone\": \"09171234567\", \"address\": {\"city\": null, \"line1\": null, \"line2\": null, \"state\": null, \"country\": null, \"postal_code\": null}}, \"paid_at\": 1777634663, \"livemode\": false, \"merchant\": \"Jm Mendoza\", \"metadata\": null, \"payments\": [{\"id\": \"pay_Y6JYoW4hfFyryppMW9Eww1Ks\", \"type\": \"payment\", \"attributes\": {\"fee\": 53982, \"taxes\": [], \"amount\": 3598800, \"payout\": null, \"source\": {\"id\": \"qrph_C63SFuoYHkLC11ugYsPhD7wQ\", \"type\": \"qrph\", \"provider\": {\"id\": \"TEST_TRANSACTION_000000\", \"code_id\": \"code_7bCh827ihGCmXvJrQnyGPJvB\", \"bank_institution_code\": null}, \"provider_id\": \"TEST_TRANSACTION_000000\"}, \"status\": \"paid\", \"billing\": {\"name\": \"Customer\", \"email\": \"djqbdhjqw@gmail.com\", \"phone\": \"09171234567\", \"address\": {\"city\": null, \"line1\": null, \"line2\": null, \"state\": null, \"country\": null, \"postal_code\": null}}, \"paid_at\": 1777634663, \"refunds\": [], \"currency\": \"PHP\", \"disputed\": false, \"livemode\": false, \"metadata\": null, \"promotion\": null, \"access_url\": null, \"created_at\": 1777634664, \"net_amount\": 3544818, \"tax_amount\": null, \"updated_at\": 1777634664, \"credited_at\": 1778115600, \"description\": \"Tenant ID: 6 | Plan ID: 2 | Billing Cycle: yearly\", \"foreign_fee\": null, \"available_at\": 1777971600, \"payment_intent_id\": \"pi_U21DwJwFTRtzydwksKjrZ73W\", \"instant_settlement\": null, \"statement_descriptor\": \"Jm Mendoza\", \"balance_transaction_id\": \"bal_txn_AdYz6wRZmqEUzggoq9P7FQC5\", \"external_reference_number\": null, \"digital_withholding_vat_amount\": 0}}], \"cancel_url\": \"http://localhost/RapidRepair/clientapplication/paymongo/payment_failed.php?source=clientpayment\", \"client_key\": \"cs_1b149f50f8c4f185d141508c_client_c00e56b074dc17b76f9545a8\", \"created_at\": 1777634656, \"line_items\": [{\"name\": \"Standard Plan Plan - Yearly Billing\", \"amount\": 3598800, \"images\": [], \"currency\": \"PHP\", \"quantity\": 1, \"description\": \"Standard Plan Plan - Yearly Billing\"}], \"updated_at\": 1777634661, \"customer_id\": null, \"description\": \"Tenant ID: 6 | Plan ID: 2 | Billing Cycle: yearly\", \"success_url\": \"http://localhost/RapidRepair/clientapplication/paymongo/payment_success.php?source=clientpayment\", \"checkout_url\": \"https://checkout.paymongo.com/1b149f50f8c4f185d141508c\", \"customer_email\": null, \"payment_intent\": {\"id\": \"pi_U21DwJwFTRtzydwksKjrZ73W\", \"type\": \"payment_intent\", \"attributes\": {\"amount\": 3598800, \"status\": \"succeeded\", \"currency\": \"PHP\", \"livemode\": false, \"metadata\": null, \"payments\": [{\"id\": \"pay_Y6JYoW4hfFyryppMW9Eww1Ks\", \"type\": \"payment\", \"attributes\": {\"fee\": 53982, \"taxes\": [], \"amount\": 3598800, \"payout\": null, \"source\": {\"id\": \"qrph_C63SFuoYHkLC11ugYsPhD7wQ\", \"type\": \"qrph\", \"provider\": {\"id\": \"TEST_TRANSACTION_000000\", \"code_id\": \"code_7bCh827ihGCmXvJrQnyGPJvB\", \"bank_institution_code\": null}, \"provider_id\": \"TEST_TRANSACTION_000000\"}, \"status\": \"paid\", \"billing\": {\"name\": \"Customer\", \"email\": \"djqbdhjqw@gmail.com\", \"phone\": \"09171234567\", \"address\": {\"city\": null, \"line1\": null, \"line2\": null, \"state\": null, \"country\": null, \"postal_code\": null}}, \"paid_at\": 1777634663, \"refunds\": [], \"currency\": \"PHP\", \"disputed\": false, \"livemode\": false, \"metadata\": null, \"promotion\": null, \"access_url\": null, \"created_at\": 1777634664, \"net_amount\": 3544818, \"tax_amount\": null, \"updated_at\": 1777634664, \"credited_at\": 1778115600, \"description\": \"Tenant ID: 6 | Plan ID: 2 | Billing Cycle: yearly\", \"foreign_fee\": null, \"available_at\": 1777971600, \"payment_intent_id\": \"pi_U21DwJwFTRtzydwksKjrZ73W\", \"instant_settlement\": null, \"statement_descriptor\": \"Jm Mendoza\", \"balance_transaction_id\": \"bal_txn_AdYz6wRZmqEUzggoq9P7FQC5\", \"external_reference_number\": null, \"digital_withholding_vat_amount\": 0}}], \"client_key\": \"pi_U21DwJwFTRtzydwksKjrZ73W_client_uFDy6wwzn2LcNx7AQjHijymj\", \"created_at\": 1777634656, \"updated_at\": 1777634664, \"description\": \"Tenant ID: 6 | Plan ID: 2 | Billing Cycle: yearly\", \"next_action\": null, \"capture_type\": \"automatic\", \"original_amount\": 3598800, \"last_payment_error\": null, \"setup_future_usage\": null, \"statement_descriptor\": \"Jm Mendoza\", \"payment_method_allowed\": [\"grab_pay\", \"qrph\", \"card\", \"paymaya\", \"gcash\"], \"payment_method_options\": {\"card\": {\"request_three_d_secure\": \"any\"}}}}, \"show_line_items\": true, \"reference_number\": null, \"show_description\": true, \"send_email_receipt\": false, \"payment_method_used\": \"qrph\", \"payment_method_types\": [\"card\", \"gcash\", \"paymaya\", \"grab_pay\", \"qrph\"], \"billing_information_fields_editable\": \"enabled\"}}, \"type\": \"checkout_session.payment.paid\", \"livemode\": false, \"created_at\": 1777634664, \"updated_at\": 1777634664, \"previous_data\": {}, \"pending_webhooks\": 1}}}', '2026-05-01 11:24:24', '2026-05-01 11:24:24');

-- ----------------------------
-- Table structure for subscription_plans
-- ----------------------------
DROP TABLE IF EXISTS `subscription_plans`;
CREATE TABLE `subscription_plans` (
  `plan_id` int NOT NULL AUTO_INCREMENT,
  `plan_code` varchar(50) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `monthly_price` decimal(10,2) NOT NULL,
  `plan_features` longtext,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`plan_id`),
  UNIQUE KEY `plan_code` (`plan_code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of subscription_plans
-- ----------------------------
INSERT INTO `subscription_plans` VALUES ('1', 'basic', 'Basic Plan', '999.00', '[\"1 Shop\",\"2 Staff\",\"Access to modules\"]', '1', '2026-03-21 17:26:21');
INSERT INTO `subscription_plans` VALUES ('2', 'medium', 'Standard Plan', '2999.00', '[\"1 shop\",\"3-5 staffs\",\"Unlock other features\",\"Include Inventory Management System\"]', '1', '2026-03-21 19:23:25');
INSERT INTO `subscription_plans` VALUES ('3', 'premium-plan', 'Premium Plan', '8999.00', '[\"1 Shop\",\"10-20 Staffs\",\"Unlocked All Features\",\"Unlocked All Mobile Application Feaatures\",\"Unlimited Data Storage\"]', '1', '2026-03-22 05:22:07');
INSERT INTO `subscription_plans` VALUES ('4', 'medium-1', 'Medium', '1000.00', '[\"Unlimited user accounts\",\"24\\/7 technical support\"]', '0', '2026-04-27 11:57:23');

-- ----------------------------
-- Table structure for subscriptions
-- ----------------------------
DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE `subscriptions` (
  `subscription_id` int NOT NULL AUTO_INCREMENT,
  `tenantID` varchar(20) NOT NULL,
  `plan_id` int NOT NULL,
  `billing_cycle` enum('monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `next_billing_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','active','cancelled','expired') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`subscription_id`),
  KEY `idx_tenantID` (`tenantID`),
  KEY `idx_plan_id` (`plan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of subscriptions
-- ----------------------------
INSERT INTO `subscriptions` VALUES ('29', '5', '1', 'monthly', '2026-05-01', '2026-06-01', '2026-06-01', '999.00', 'active', '2026-05-01 11:06:16', '2026-05-01 11:06:16');
INSERT INTO `subscriptions` VALUES ('30', '6', '2', 'yearly', '2026-05-01', '2027-05-01', '2027-05-01', '35988.00', 'active', '2026-05-01 11:24:24', '2026-05-01 11:24:24');

-- ----------------------------
-- Table structure for superadmin
-- ----------------------------
DROP TABLE IF EXISTS `superadmin`;
CREATE TABLE `superadmin` (
  `superadmin_id` int NOT NULL AUTO_INCREMENT,
  `fullName` varchar(100) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `access_scope` varchar(100) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`superadmin_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of superadmin
-- ----------------------------
INSERT INTO `superadmin` VALUES ('1', 'John Maverick Mendoza', 'jeemndzsuperadd', 'mendozajohmaverick1@gmail.com', '$2y$10$LvD0ASN3Zpqr0fkCh5bCUOa8xTMITwtNcBO52fjTX2ISMKAOWGssC', 'Superadmin', 'Global Root', 'Active', '2026-03-15 13:23:38', '2026-04-09 03:42:46', NULL, NULL);
INSERT INTO `superadmin` VALUES ('3', 'Amiel Carl Santos', 'Amielsuperadd', 'boyim21535@azucore.com', '$2y$10$NMfiE479SAzV0BR8oPYcQezuVnKJnjMILoVJ9LWXQXzcvc8fLc4sy', 'Superadmin', 'Global Root', 'Active', '2026-04-09 03:49:34', '2026-04-09 03:49:34', NULL, NULL);
INSERT INTO `superadmin` VALUES ('4', 'Ella Payumo', 'Ellisuperadd3', 'mexihob544@donumart.com', '$2y$10$QobSHsKlZLKuzux5Zk8VSuhDRxvxxwUmwTxIF3tKbCcFqcS7MiqzK', 'Superadmin', 'Global Root', 'Active', '2026-04-25 15:47:15', '2026-04-25 15:49:41', NULL, NULL);

-- ----------------------------
-- Table structure for superadmin_settings
-- ----------------------------
DROP TABLE IF EXISTS `superadmin_settings`;
CREATE TABLE `superadmin_settings` (
  `id` tinyint NOT NULL,
  `system_name` varchar(150) DEFAULT NULL,
  `primary_color` varchar(7) NOT NULL DEFAULT '#1152d4',
  `logo_path` varchar(255) DEFAULT NULL,
  `max_tenants` int NOT NULL DEFAULT '250',
  `storage_limit_gb` int NOT NULL DEFAULT '50',
  `auto_approval` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of superadmin_settings
-- ----------------------------
INSERT INTO `superadmin_settings` VALUES ('1', 'Cobalt Precision', '#1152d4', NULL, '250', '50', '1', '2026-03-23 16:00:27');

-- ----------------------------
-- Table structure for system_logs
-- ----------------------------
DROP TABLE IF EXISTS `system_logs`;
CREATE TABLE `system_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `tenantID` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `user_role` varchar(100) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB AUTO_INCREMENT=366 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of system_logs
-- ----------------------------
INSERT INTO `system_logs` VALUES ('1', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 17:31:40');
INSERT INTO `system_logs` VALUES ('2', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Tenant logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 17:36:09');
INSERT INTO `system_logs` VALUES ('3', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 17:44:27');
INSERT INTO `system_logs` VALUES ('4', '1', '1', 'EDMs Auto Shop', 'admin', 'LOGIN', 'tenant', '1', 'Tenant logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 17:51:09');
INSERT INTO `system_logs` VALUES ('5', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Tenant logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 01:05:17');
INSERT INTO `system_logs` VALUES ('6', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Tenant logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 01:06:05');
INSERT INTO `system_logs` VALUES ('7', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Tenant logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 01:10:29');
INSERT INTO `system_logs` VALUES ('8', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Tenant logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 01:13:33');
INSERT INTO `system_logs` VALUES ('9', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Tenant logged in', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 06:32:44');
INSERT INTO `system_logs` VALUES ('10', '3', '3', 'Kaloy\\\'s', 'admin', 'LOGIN', 'tenant', '3', 'Tenant logged in (first login)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 07:50:13');
INSERT INTO `system_logs` VALUES ('11', '3', '3', 'Kaloy\\\'s', 'admin', 'LOGIN', 'tenant', '3', 'Tenant logged in (first login)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 08:04:19');
INSERT INTO `system_logs` VALUES ('12', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Tenant logged in', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 09:06:27');
INSERT INTO `system_logs` VALUES ('13', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Tenant logged in', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 15:57:46');
INSERT INTO `system_logs` VALUES ('14', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Tenant logged in', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 16:10:32');
INSERT INTO `system_logs` VALUES ('15', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 16:12:39');
INSERT INTO `system_logs` VALUES ('16', '1', '1', 'EDMs Auto Shop', 'admin', 'LOGIN', 'tenant', '1', 'Tenant logged in', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 16:15:52');
INSERT INTO `system_logs` VALUES ('17', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Tenant logged in', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 19:40:20');
INSERT INTO `system_logs` VALUES ('18', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Tenant logged in', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 19:42:24');
INSERT INTO `system_logs` VALUES ('19', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 19:51:20');
INSERT INTO `system_logs` VALUES ('20', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Tenant logged in', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 19:51:30');
INSERT INTO `system_logs` VALUES ('21', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 19:51:54');
INSERT INTO `system_logs` VALUES ('22', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Tenant logged in', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 19:52:00');
INSERT INTO `system_logs` VALUES ('23', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 19:57:34');
INSERT INTO `system_logs` VALUES ('24', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Tenant logged in', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 19:57:47');
INSERT INTO `system_logs` VALUES ('25', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 20:17:57');
INSERT INTO `system_logs` VALUES ('26', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Tenant logged in', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 20:18:06');
INSERT INTO `system_logs` VALUES ('27', '1', '1', 'EDMs Auto Shop', 'admin', 'LOGIN', 'tenant', '1', 'Tenant logged in', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 09:37:28');
INSERT INTO `system_logs` VALUES ('28', '1', '1', 'EDMs Auto Shop', 'admin', 'LOGOUT', 'tenant', '1', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 09:40:15');
INSERT INTO `system_logs` VALUES ('29', '3', '3', 'Kaloy\\\'s', 'admin', 'LOGIN', 'tenant', '3', 'Tenant logged in (first login)', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 09:47:49');
INSERT INTO `system_logs` VALUES ('30', '3', '3', 'Kaloy\\\'s', 'admin', 'LOGIN', 'tenant', '3', 'Tenant logged in', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 09:54:38');
INSERT INTO `system_logs` VALUES ('31', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Tenant logged in', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 10:03:33');
INSERT INTO `system_logs` VALUES ('32', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Tenant logged in', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:42:59');
INSERT INTO `system_logs` VALUES ('33', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:59:11');
INSERT INTO `system_logs` VALUES ('34', '1', '1', 'EDMs Auto Shop', 'admin', 'LOGIN', 'tenant', '1', 'Tenant logged in', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:59:25');
INSERT INTO `system_logs` VALUES ('35', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Tenant logged in', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 05:13:43');
INSERT INTO `system_logs` VALUES ('36', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 05:18:12');
INSERT INTO `system_logs` VALUES ('37', '1', '1', 'EDMs Auto Shop', 'admin', 'LOGIN', 'tenant', '1', 'Tenant logged in', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 05:18:25');
INSERT INTO `system_logs` VALUES ('38', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Tenant logged in', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 05:54:36');
INSERT INTO `system_logs` VALUES ('39', '2', '9', 'Jm Mendoza', 'Senior Technician', 'LOGIN', 'role', '9', 'Staff member logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 10:12:46');
INSERT INTO `system_logs` VALUES ('40', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 10:12:52');
INSERT INTO `system_logs` VALUES ('41', '2', '9', 'Jm Mendoza', 'Senior Technician', 'LOGIN', 'role', '9', 'Staff member logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 10:13:00');
INSERT INTO `system_logs` VALUES ('42', '2', '2', '', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:22:29');
INSERT INTO `system_logs` VALUES ('43', '1', '1', 'EDMs Auto Shop', 'admin', 'LOGIN', 'tenant', '1', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:24:40');
INSERT INTO `system_logs` VALUES ('44', '1', '1', 'EDMs Auto Shop', 'admin', 'LOGOUT', 'tenant', '1', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:24:43');
INSERT INTO `system_logs` VALUES ('45', '1', '1', 'EDMs Auto Shop', 'admin', 'LOGIN', 'tenant', '1', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:24:55');
INSERT INTO `system_logs` VALUES ('46', '1', '1', 'EDMs Auto Shop', 'admin', 'LOGIN', 'tenant', '1', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:28:01');
INSERT INTO `system_logs` VALUES ('47', '1', '1', 'EDMs Auto Shop', 'admin', 'LOGIN', 'tenant', '1', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:29:11');
INSERT INTO `system_logs` VALUES ('48', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:29:36');
INSERT INTO `system_logs` VALUES ('49', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:32:20');
INSERT INTO `system_logs` VALUES ('50', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:32:23');
INSERT INTO `system_logs` VALUES ('51', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:32:40');
INSERT INTO `system_logs` VALUES ('52', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:32:41');
INSERT INTO `system_logs` VALUES ('53', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:33:31');
INSERT INTO `system_logs` VALUES ('54', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:33:31');
INSERT INTO `system_logs` VALUES ('55', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:33:42');
INSERT INTO `system_logs` VALUES ('56', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:33:46');
INSERT INTO `system_logs` VALUES ('57', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:33:57');
INSERT INTO `system_logs` VALUES ('58', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:33:58');
INSERT INTO `system_logs` VALUES ('59', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:37:03');
INSERT INTO `system_logs` VALUES ('60', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:37:07');
INSERT INTO `system_logs` VALUES ('61', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:37:15');
INSERT INTO `system_logs` VALUES ('62', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:37:16');
INSERT INTO `system_logs` VALUES ('63', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:37:24');
INSERT INTO `system_logs` VALUES ('64', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:37:24');
INSERT INTO `system_logs` VALUES ('65', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:37:33');
INSERT INTO `system_logs` VALUES ('66', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:37:34');
INSERT INTO `system_logs` VALUES ('67', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:39:47');
INSERT INTO `system_logs` VALUES ('68', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:39:53');
INSERT INTO `system_logs` VALUES ('69', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:39:58');
INSERT INTO `system_logs` VALUES ('70', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:41:34');
INSERT INTO `system_logs` VALUES ('71', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:41:39');
INSERT INTO `system_logs` VALUES ('72', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:42:24');
INSERT INTO `system_logs` VALUES ('73', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:42:47');
INSERT INTO `system_logs` VALUES ('74', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:42:58');
INSERT INTO `system_logs` VALUES ('75', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:44:39');
INSERT INTO `system_logs` VALUES ('76', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:47:28');
INSERT INTO `system_logs` VALUES ('77', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:47:31');
INSERT INTO `system_logs` VALUES ('78', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:47:39');
INSERT INTO `system_logs` VALUES ('79', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:47:42');
INSERT INTO `system_logs` VALUES ('80', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:48:14');
INSERT INTO `system_logs` VALUES ('81', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:48:18');
INSERT INTO `system_logs` VALUES ('82', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:48:26');
INSERT INTO `system_logs` VALUES ('83', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:48:30');
INSERT INTO `system_logs` VALUES ('84', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:48:41');
INSERT INTO `system_logs` VALUES ('85', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:52:39');
INSERT INTO `system_logs` VALUES ('86', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:52:53');
INSERT INTO `system_logs` VALUES ('87', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:52:57');
INSERT INTO `system_logs` VALUES ('88', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:53:17');
INSERT INTO `system_logs` VALUES ('89', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:53:19');
INSERT INTO `system_logs` VALUES ('90', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:53:25');
INSERT INTO `system_logs` VALUES ('91', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:53:29');
INSERT INTO `system_logs` VALUES ('92', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:53:43');
INSERT INTO `system_logs` VALUES ('93', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:53:48');
INSERT INTO `system_logs` VALUES ('94', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:59:40');
INSERT INTO `system_logs` VALUES ('95', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 12:59:42');
INSERT INTO `system_logs` VALUES ('96', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 13:01:55');
INSERT INTO `system_logs` VALUES ('97', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 13:01:58');
INSERT INTO `system_logs` VALUES ('98', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 13:06:11');
INSERT INTO `system_logs` VALUES ('99', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 13:06:16');
INSERT INTO `system_logs` VALUES ('100', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 13:06:34');
INSERT INTO `system_logs` VALUES ('101', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 13:07:23');
INSERT INTO `system_logs` VALUES ('102', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 13:20:11');
INSERT INTO `system_logs` VALUES ('103', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 16:37:35');
INSERT INTO `system_logs` VALUES ('104', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 10:38:40');
INSERT INTO `system_logs` VALUES ('105', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 19:00:02');
INSERT INTO `system_logs` VALUES ('106', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 19:00:24');
INSERT INTO `system_logs` VALUES ('107', '1', '1', 'EDMs Auto Shop', 'admin', 'LOGIN', 'tenant', '1', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 19:00:39');
INSERT INTO `system_logs` VALUES ('108', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-06 04:48:32');
INSERT INTO `system_logs` VALUES ('109', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-06 15:26:04');
INSERT INTO `system_logs` VALUES ('110', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-06 15:28:12');
INSERT INTO `system_logs` VALUES ('111', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-06 15:28:14');
INSERT INTO `system_logs` VALUES ('112', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-06 15:38:18');
INSERT INTO `system_logs` VALUES ('113', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-06 16:30:10');
INSERT INTO `system_logs` VALUES ('114', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 02:29:16');
INSERT INTO `system_logs` VALUES ('115', '4', '4', 'ABCD', 'admin', 'LOGIN', 'tenant', '4', 'Owner logged in (first login)', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 02:41:51');
INSERT INTO `system_logs` VALUES ('116', '4', '4', 'ABCD', 'admin', 'LOGIN', 'tenant', '4', 'Owner logged in (first login)', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 02:51:20');
INSERT INTO `system_logs` VALUES ('117', '4', '4', 'ABCD', 'admin', 'LOGOUT', 'tenant', '4', 'Tenant logged out', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 02:53:59');
INSERT INTO `system_logs` VALUES ('118', '4', '4', 'ABCD', 'admin', 'LOGIN', 'tenant', '4', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 02:54:43');
INSERT INTO `system_logs` VALUES ('119', '6', '6', 'ABCDE', 'admin', 'LOGIN', 'tenant', '6', 'Owner logged in (first login)', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 03:12:51');
INSERT INTO `system_logs` VALUES ('120', '6', '6', 'ABCDE', 'admin', 'LOGOUT', 'tenant', '6', 'Tenant logged out', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 03:14:37');
INSERT INTO `system_logs` VALUES ('121', '6', '6', 'ABCDE', 'admin', 'LOGIN', 'tenant', '6', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 03:15:07');
INSERT INTO `system_logs` VALUES ('122', '7', '7', 'ASDA', 'admin', 'LOGIN', 'tenant', '7', 'Owner logged in (first login)', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 03:34:19');
INSERT INTO `system_logs` VALUES ('123', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Create Tenant', 'Tenant', '3', 'Created new tenant: RXS Auto Shop, Owner: Juan Tamad, Subscription: basic, Status: Active', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 17:33:06');
INSERT INTO `system_logs` VALUES ('124', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Create Superadmin Account', 'Superadmin', '2', 'Created new Superadmin: Amiel Carl Santos (amielsuperadd123!), Access Scope: Global Root', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 17:34:57');
INSERT INTO `system_logs` VALUES ('125', NULL, NULL, 'Amiel Carl Santos', 'superadmin', 'Create Tenant', 'Tenant', '4', 'Created new tenant: ABCDE, Owner: Pedro Penduko, Subscription: basic, Status: Active', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 17:40:15');
INSERT INTO `system_logs` VALUES ('126', NULL, NULL, 'Amiel Carl Santos', 'superadmin', 'Update Tenant Information', 'Tenant', '4', 'Updated: Shop Name, Address, Owner Name, Email, Contact, Status', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 17:40:30');
INSERT INTO `system_logs` VALUES ('127', NULL, NULL, 'Amiel Carl Santos', 'superadmin', 'Create Backup', 'Backup File', NULL, 'Created database backup: backup_rapidrepair_2026-04-08_19-40-55.sql, Size: 68456 bytes', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 17:40:57');
INSERT INTO `system_logs` VALUES ('128', NULL, NULL, 'Amiel Carl Santos', 'superadmin', 'Update Subscription Plan', 'Subscription Plan', '2', 'Updated plan: Name=Standard Plan, Price=8,999.00/month', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 17:41:17');
INSERT INTO `system_logs` VALUES ('129', NULL, NULL, NULL, NULL, 'Forgot Password Request', 'Superadmin', NULL, 'Password reset requested for: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 03:20:10');
INSERT INTO `system_logs` VALUES ('130', NULL, NULL, NULL, NULL, 'Password Reset Completed', 'Superadmin', NULL, 'Password successfully reset for: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 03:28:41');
INSERT INTO `system_logs` VALUES ('131', NULL, NULL, NULL, NULL, 'Superadmin Login Failed', 'Superadmin', NULL, 'Failed login attempt with username/email: jeemndzsuperadd (incorrect password)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 03:28:50');
INSERT INTO `system_logs` VALUES ('132', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 03:28:56');
INSERT INTO `system_logs` VALUES ('133', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 03:29:22');
INSERT INTO `system_logs` VALUES ('134', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 03:29:35');
INSERT INTO `system_logs` VALUES ('135', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 03:31:39');
INSERT INTO `system_logs` VALUES ('136', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 03:32:49');
INSERT INTO `system_logs` VALUES ('137', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 03:33:35');
INSERT INTO `system_logs` VALUES ('138', NULL, NULL, NULL, NULL, 'Superadmin Login Failed', 'Superadmin', NULL, 'Failed login attempt with username/email: mendozajohmaverick1@gmail.com (incorrect password)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 03:41:18');
INSERT INTO `system_logs` VALUES ('139', NULL, NULL, NULL, NULL, 'Forgot Password Request', 'Superadmin', NULL, 'Password reset requested for: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 03:42:20');
INSERT INTO `system_logs` VALUES ('140', NULL, NULL, NULL, NULL, 'Password Reset Completed', 'Superadmin', NULL, 'Password successfully reset for: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 03:42:47');
INSERT INTO `system_logs` VALUES ('141', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 03:42:56');
INSERT INTO `system_logs` VALUES ('142', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 03:43:38');
INSERT INTO `system_logs` VALUES ('143', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 03:43:54');
INSERT INTO `system_logs` VALUES ('144', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 03:44:50');
INSERT INTO `system_logs` VALUES ('145', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 03:45:57');
INSERT INTO `system_logs` VALUES ('146', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Delete Superadmin Account', 'Superadmin', '2', 'Deleted Superadmin account: Amiel Carl Santos', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 03:46:06');
INSERT INTO `system_logs` VALUES ('147', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Create Superadmin Account', 'Superadmin', '3', 'Created new Superadmin: Amiel Carl Santos (Amielsuperadd), Access Scope: Global Root', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 03:49:35');
INSERT INTO `system_logs` VALUES ('148', NULL, NULL, 'Amiel Carl Santos', 'superadmin', 'Superadmin Login', 'Superadmin', '3', 'Successful login via username/email: Amielsuperadd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 03:50:23');
INSERT INTO `system_logs` VALUES ('149', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 04:01:44');
INSERT INTO `system_logs` VALUES ('150', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 04:16:43');
INSERT INTO `system_logs` VALUES ('151', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 04:55:30');
INSERT INTO `system_logs` VALUES ('152', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Update Tenant Information', 'Tenant', '5', 'Updated: Shop Name, Address, Owner Name, Email, Contact, Status', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 05:18:01');
INSERT INTO `system_logs` VALUES ('153', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 05:21:49');
INSERT INTO `system_logs` VALUES ('154', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Accept Applicant', 'Applicant', '6', 'Applicant approved and activated. Owner: Pedro Penduko, Shop: Pedring\'s Auto Works, Plan: Basic Plan, Billing Cycle: monthly, Amount: PHP 999.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 05:33:02');
INSERT INTO `system_logs` VALUES ('155', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Send Billing Notification', 'Billing', '6', 'Billing reminder sent for next billing date: 2026-04-14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 05:59:21');
INSERT INTO `system_logs` VALUES ('156', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 10:16:56');
INSERT INTO `system_logs` VALUES ('157', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 10:17:28');
INSERT INTO `system_logs` VALUES ('158', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 10:18:11');
INSERT INTO `system_logs` VALUES ('159', NULL, NULL, NULL, NULL, 'Superadmin Login Failed', 'Superadmin', NULL, 'Failed login attempt with username/email: mendozajohmaverick1@gmail.com (incorrect password)', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 10:34:57');
INSERT INTO `system_logs` VALUES ('160', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 10:35:08');
INSERT INTO `system_logs` VALUES ('161', NULL, NULL, NULL, NULL, 'Superadmin Login Failed', 'Superadmin', NULL, 'Failed login attempt with username/email: mendozajohmaverick1@gmail.com (incorrect password)', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 10:36:28');
INSERT INTO `system_logs` VALUES ('162', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 10:36:34');
INSERT INTO `system_logs` VALUES ('163', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Update Subscription Plan', 'Subscription Plan', '1', 'Updated plan: Name=Basic Plan, Price=999.00/month', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 10:36:44');
INSERT INTO `system_logs` VALUES ('164', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 12:45:03');
INSERT INTO `system_logs` VALUES ('165', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 13:10:15');
INSERT INTO `system_logs` VALUES ('166', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 13:54:25');
INSERT INTO `system_logs` VALUES ('167', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 14:00:15');
INSERT INTO `system_logs` VALUES ('168', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 14:24:46');
INSERT INTO `system_logs` VALUES ('169', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 14:25:00');
INSERT INTO `system_logs` VALUES ('170', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 14:25:04');
INSERT INTO `system_logs` VALUES ('171', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 14:25:11');
INSERT INTO `system_logs` VALUES ('172', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 17:24:35');
INSERT INTO `system_logs` VALUES ('173', '1', '1', 'EDMs Auto Shop', 'admin', 'LOGIN', 'tenant', '1', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 17:27:37');
INSERT INTO `system_logs` VALUES ('174', '1', '1', 'EDMs Auto Shop', 'admin', 'LOGOUT', 'tenant', '1', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 17:47:00');
INSERT INTO `system_logs` VALUES ('175', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 17:47:11');
INSERT INTO `system_logs` VALUES ('176', NULL, NULL, NULL, NULL, 'Superadmin Login Failed', 'Superadmin', NULL, 'Failed login attempt with username/email: mendozajohmaverick1@gmail.com (incorrect password)', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 23:54:38');
INSERT INTO `system_logs` VALUES ('177', NULL, NULL, NULL, NULL, 'Superadmin Login Failed', 'Superadmin', NULL, 'Failed login attempt with username/email: jeemndzsuperadd (incorrect password)', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 23:54:44');
INSERT INTO `system_logs` VALUES ('178', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 23:54:52');
INSERT INTO `system_logs` VALUES ('179', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 23:54:53');
INSERT INTO `system_logs` VALUES ('180', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 00:00:13');
INSERT INTO `system_logs` VALUES ('181', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login Failed', 'Superadmin', NULL, 'Failed login attempt with username/email: jeemndzsuperadd (incorrect password)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 00:02:00');
INSERT INTO `system_logs` VALUES ('182', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 00:02:09');
INSERT INTO `system_logs` VALUES ('183', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 00:08:50');
INSERT INTO `system_logs` VALUES ('184', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 02:00:42');
INSERT INTO `system_logs` VALUES ('185', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 02:02:46');
INSERT INTO `system_logs` VALUES ('186', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-10 02:03:19');
INSERT INTO `system_logs` VALUES ('187', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-10 02:03:20');
INSERT INTO `system_logs` VALUES ('188', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 02:04:24');
INSERT INTO `system_logs` VALUES ('189', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 02:04:50');
INSERT INTO `system_logs` VALUES ('190', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-10 02:36:10');
INSERT INTO `system_logs` VALUES ('191', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-13 13:32:05');
INSERT INTO `system_logs` VALUES ('192', NULL, NULL, NULL, NULL, 'Superadmin Login Failed', 'Superadmin', NULL, 'Failed login attempt - account not found for: amielsuperadd123!', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-17 15:59:31');
INSERT INTO `system_logs` VALUES ('193', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-17 15:59:45');
INSERT INTO `system_logs` VALUES ('194', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 07:20:46');
INSERT INTO `system_logs` VALUES ('195', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 07:36:36');
INSERT INTO `system_logs` VALUES ('196', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 07:37:16');
INSERT INTO `system_logs` VALUES ('197', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 07:51:56');
INSERT INTO `system_logs` VALUES ('198', '2', '11', 'James Davis', 'Senior Technician', 'LOGIN', 'role', '11', 'Staff member logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 07:52:10');
INSERT INTO `system_logs` VALUES ('199', '2', '2', '', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 07:54:18');
INSERT INTO `system_logs` VALUES ('200', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 07:54:27');
INSERT INTO `system_logs` VALUES ('201', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 07:58:32');
INSERT INTO `system_logs` VALUES ('202', '2', '11', 'James Davis', 'Senior Technician', 'LOGIN', 'role', '11', 'Staff member logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 07:58:42');
INSERT INTO `system_logs` VALUES ('203', '2', '11', 'James Davis', 'Senior Technician', 'LOGIN', 'role', '11', 'Staff member logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 07:59:39');
INSERT INTO `system_logs` VALUES ('204', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 08:00:29');
INSERT INTO `system_logs` VALUES ('205', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 08:01:34');
INSERT INTO `system_logs` VALUES ('206', '2', '12', 'James Davis', 'Senior Technician', 'LOGIN', 'role', '12', 'Staff member logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 08:01:45');
INSERT INTO `system_logs` VALUES ('207', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 08:02:36');
INSERT INTO `system_logs` VALUES ('208', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 08:02:49');
INSERT INTO `system_logs` VALUES ('209', '2', '12', 'James Davis', 'Senior Technician', 'LOGIN', 'role', '12', 'Staff member logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 08:03:00');
INSERT INTO `system_logs` VALUES ('210', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 08:03:24');
INSERT INTO `system_logs` VALUES ('211', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 08:06:25');
INSERT INTO `system_logs` VALUES ('212', '2', '13', 'James Davis', 'Senior Technician', 'LOGIN', 'role', '13', 'Staff member logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 08:06:47');
INSERT INTO `system_logs` VALUES ('213', '2', '2', '', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 08:08:49');
INSERT INTO `system_logs` VALUES ('214', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 08:13:53');
INSERT INTO `system_logs` VALUES ('215', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Session destroyed - back button detected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 08:16:23');
INSERT INTO `system_logs` VALUES ('216', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 08:16:39');
INSERT INTO `system_logs` VALUES ('217', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 14:26:05');
INSERT INTO `system_logs` VALUES ('218', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-21 17:18:10');
INSERT INTO `system_logs` VALUES ('219', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-21 17:37:52');
INSERT INTO `system_logs` VALUES ('220', NULL, NULL, NULL, NULL, 'Superadmin Login Failed', 'Superadmin', NULL, 'Failed login attempt with username/email: jeemndzsuperadd (incorrect password)', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-21 17:39:32');
INSERT INTO `system_logs` VALUES ('221', NULL, NULL, NULL, NULL, 'Superadmin Login Failed', 'Superadmin', NULL, 'Failed login attempt with username/email: jeemndzsuperadd (incorrect password)', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-21 17:39:41');
INSERT INTO `system_logs` VALUES ('222', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-21 17:39:58');
INSERT INTO `system_logs` VALUES ('223', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Accept Applicant', 'Applicant', '7', 'Applicant approved and activated. Owner: Jm Mendoza, Shop: Jeem Workz, Plan: Basic Plan, Billing Cycle: quarterly, Amount: PHP 2,997.00', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-21 17:50:44');
INSERT INTO `system_logs` VALUES ('224', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 13:16:53');
INSERT INTO `system_logs` VALUES ('225', NULL, NULL, NULL, NULL, 'Superadmin Login Failed', 'Superadmin', NULL, 'Failed login attempt with username/email: jeemndzsuperadd (incorrect password)', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 05:43:02');
INSERT INTO `system_logs` VALUES ('226', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 05:43:10');
INSERT INTO `system_logs` VALUES ('227', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Reject Applicant', 'Applicant', '9', 'Applicant rejected. Owner: Jm Mendoza, Shop: Airwins Auto Repair Shop', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 05:56:59');
INSERT INTO `system_logs` VALUES ('228', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 06:31:00');
INSERT INTO `system_logs` VALUES ('229', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Update Subscription Plan', 'Subscription Plan', '1', 'Updated plan: Name=Basic Plan, Price=4,999.00/month', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 06:53:21');
INSERT INTO `system_logs` VALUES ('230', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 10:01:00');
INSERT INTO `system_logs` VALUES ('231', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 10:33:52');
INSERT INTO `system_logs` VALUES ('232', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 13:22:51');
INSERT INTO `system_logs` VALUES ('233', '1', '1', 'EDMs Auto Shop', 'admin', 'LOGIN', 'tenant', '1', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 13:23:19');
INSERT INTO `system_logs` VALUES ('234', '1', '1', 'EDMs Auto Shop', 'admin', 'LOGOUT', 'tenant', '1', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 13:24:02');
INSERT INTO `system_logs` VALUES ('235', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 13:24:15');
INSERT INTO `system_logs` VALUES ('236', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 00:09:54');
INSERT INTO `system_logs` VALUES ('237', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 00:10:09');
INSERT INTO `system_logs` VALUES ('238', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Reject Applicant', 'Applicant', '8', 'Applicant rejected. Owner: Jerome Harvey Dagta, Shop: Jerome Auto Workz', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 00:11:06');
INSERT INTO `system_logs` VALUES ('239', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 00:11:43');
INSERT INTO `system_logs` VALUES ('240', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-25 15:38:10');
INSERT INTO `system_logs` VALUES ('241', NULL, NULL, NULL, NULL, 'Superadmin Login Failed', 'Superadmin', NULL, 'Failed login attempt with username/email: Amielsuperadd (incorrect password)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-25 15:38:46');
INSERT INTO `system_logs` VALUES ('242', NULL, NULL, NULL, NULL, 'Superadmin Login Failed', 'Superadmin', NULL, 'Failed login attempt with username/email: Amielsuperadd (incorrect password)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-25 15:39:06');
INSERT INTO `system_logs` VALUES ('243', NULL, NULL, NULL, NULL, 'Forgot Password Request - No Account', 'Superadmin', NULL, 'Password reset requested for non-existent account: lifix85687@donumart.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-25 15:39:28');
INSERT INTO `system_logs` VALUES ('244', NULL, NULL, NULL, NULL, 'Forgot Password Request - No Account', 'Superadmin', NULL, 'Password reset requested for non-existent account: mexihob544@donumart.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-25 15:40:23');
INSERT INTO `system_logs` VALUES ('245', NULL, NULL, NULL, NULL, 'Forgot Password Request - No Account', 'Superadmin', NULL, 'Password reset requested for non-existent account: mexihob544@donumart.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-25 15:41:41');
INSERT INTO `system_logs` VALUES ('246', NULL, NULL, 'Amiel Carl Santos', 'superadmin', 'Superadmin Login', 'Superadmin', '3', 'Successful login via username/email: Amielsuperadd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-25 15:42:10');
INSERT INTO `system_logs` VALUES ('247', NULL, NULL, NULL, NULL, 'Forgot Password Request - No Account', 'Superadmin', NULL, 'Password reset requested for non-existent account: johnlurjmendoza1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-25 15:43:10');
INSERT INTO `system_logs` VALUES ('248', NULL, NULL, 'Amiel Carl Santos', 'superadmin', 'Superadmin Login', 'Superadmin', '3', 'Successful login via username/email: Amielsuperadd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-25 15:46:13');
INSERT INTO `system_logs` VALUES ('249', NULL, NULL, 'Amiel Carl Santos', 'superadmin', 'Create Superadmin Account', 'Superadmin', '4', 'Created new Superadmin: Ella Payumo (Ellisuperadd3), Access Scope: Global Root', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-25 15:47:15');
INSERT INTO `system_logs` VALUES ('250', NULL, NULL, NULL, NULL, 'Superadmin Login Failed', 'Superadmin', NULL, 'Failed login attempt - account not found for: Ellisuperadd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-25 15:47:52');
INSERT INTO `system_logs` VALUES ('251', NULL, NULL, 'Ella Payumo', 'superadmin', 'Superadmin Login', 'Superadmin', '4', 'Successful login via username/email: Ellisuperadd3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-25 15:48:11');
INSERT INTO `system_logs` VALUES ('252', NULL, NULL, NULL, NULL, 'Forgot Password Request', 'Superadmin', NULL, 'Password reset requested for: mexihob544@donumart.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-25 15:48:40');
INSERT INTO `system_logs` VALUES ('253', NULL, NULL, NULL, NULL, 'Password Reset Completed', 'Superadmin', NULL, 'Password successfully reset for: mexihob544@donumart.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-25 15:49:41');
INSERT INTO `system_logs` VALUES ('254', NULL, NULL, 'Ella Payumo', 'superadmin', 'Superadmin Login', 'Superadmin', '4', 'Successful login via username/email: Ellisuperadd3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-25 15:49:54');
INSERT INTO `system_logs` VALUES ('255', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-25 16:08:11');
INSERT INTO `system_logs` VALUES ('256', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 11:40:39');
INSERT INTO `system_logs` VALUES ('257', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 11:55:56');
INSERT INTO `system_logs` VALUES ('258', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 11:56:52');
INSERT INTO `system_logs` VALUES ('259', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Create Subscription Plan', 'Subscription Plan', '4', 'Created new subscription plan: Name=Medium, Price=1,000.00/month, Status=Active', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 11:57:23');
INSERT INTO `system_logs` VALUES ('260', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Toggle Subscription Plan Status', 'Subscription Plan', '4', 'Plan status changed to: Inactive', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 11:57:30');
INSERT INTO `system_logs` VALUES ('261', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Toggle Subscription Plan Status', 'Subscription Plan', '1', 'Plan status changed to: Inactive', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 11:57:32');
INSERT INTO `system_logs` VALUES ('262', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Toggle Subscription Plan Status', 'Subscription Plan', '1', 'Plan status changed to: Active', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 11:57:55');
INSERT INTO `system_logs` VALUES ('263', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Toggle Subscription Plan Status', 'Subscription Plan', '1', 'Plan status changed to: Inactive', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 11:57:56');
INSERT INTO `system_logs` VALUES ('264', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Toggle Subscription Plan Status', 'Subscription Plan', '4', 'Plan status changed to: Active', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 11:58:06');
INSERT INTO `system_logs` VALUES ('265', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Toggle Subscription Plan Status', 'Subscription Plan', '1', 'Plan status changed to: Active', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 11:58:09');
INSERT INTO `system_logs` VALUES ('266', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 12:06:10');
INSERT INTO `system_logs` VALUES ('267', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 12:07:05');
INSERT INTO `system_logs` VALUES ('268', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Create Backup', 'Backup File', NULL, 'Created database backup: backup_rapidrepair_2026-04-27_12-08-27.sql, Size: 139551 bytes', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 12:08:27');
INSERT INTO `system_logs` VALUES ('269', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 12:09:24');
INSERT INTO `system_logs` VALUES ('270', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 12:11:49');
INSERT INTO `system_logs` VALUES ('271', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 12:24:54');
INSERT INTO `system_logs` VALUES ('272', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 12:24:54');
INSERT INTO `system_logs` VALUES ('273', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 12:29:46');
INSERT INTO `system_logs` VALUES ('274', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 12:48:38');
INSERT INTO `system_logs` VALUES ('275', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 12:48:48');
INSERT INTO `system_logs` VALUES ('276', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Reject Applicant', 'Applicant', '6', 'Applicant rejected. Owner: Jm Mendoza, Shop: AutoCare Garage', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 13:01:24');
INSERT INTO `system_logs` VALUES ('277', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 13:10:55');
INSERT INTO `system_logs` VALUES ('278', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 13:11:40');
INSERT INTO `system_logs` VALUES ('279', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Reject Applicant', 'Applicant', '7', 'Applicant rejected. Owner: Carl Micko Tibay, Shop: AutoTribe Auto Repair Shop', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 13:11:52');
INSERT INTO `system_logs` VALUES ('280', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 01:42:08');
INSERT INTO `system_logs` VALUES ('281', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Update Tenant Information', 'Tenant', '7', 'Updated: Shop Name, Address, Owner Name, Email, Contact, Status', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 01:42:25');
INSERT INTO `system_logs` VALUES ('282', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Update Tenant Information', 'Tenant', '6', 'Updated: Shop Name, Address, Owner Name, Email, Contact, Status', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 01:42:31');
INSERT INTO `system_logs` VALUES ('283', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Update Tenant Information', 'Tenant', '2', 'Updated: Shop Name, Address, Owner Name, Email, Contact, Status', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 01:43:32');
INSERT INTO `system_logs` VALUES ('284', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 05:59:55');
INSERT INTO `system_logs` VALUES ('285', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Reject Applicant', 'Applicant', '8', 'Applicant rejected. Owner: Jeem Mendoza, Shop: AutoTribe Auto Repair Shop', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 06:00:31');
INSERT INTO `system_logs` VALUES ('286', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 06:52:32');
INSERT INTO `system_logs` VALUES ('287', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Reject Applicant', 'Applicant', '6', 'Applicant rejected. Owner: Collin Philipp, Shop: AutoMotivation Auto Repair Shop', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 06:52:37');
INSERT INTO `system_logs` VALUES ('288', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 09:36:43');
INSERT INTO `system_logs` VALUES ('289', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Reject Applicant', 'Applicant', '6', 'Applicant rejected. Owner: Collin Philipp, Shop: AutoMotivation Auto Repair Shop', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 09:36:57');
INSERT INTO `system_logs` VALUES ('290', '14', '14', '', 'admin', 'LOGOUT', 'tenant', '14', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 10:05:32');
INSERT INTO `system_logs` VALUES ('291', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 10:29:23');
INSERT INTO `system_logs` VALUES ('292', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Toggle Subscription Plan Status', 'Subscription Plan', '4', 'Plan status changed to: Inactive', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 10:33:39');
INSERT INTO `system_logs` VALUES ('293', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 15:48:59');
INSERT INTO `system_logs` VALUES ('294', NULL, NULL, NULL, NULL, 'Superadmin Login Failed', 'Superadmin', NULL, 'Failed login attempt with username/email: jeemndzsuperadd (incorrect password)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 16:02:20');
INSERT INTO `system_logs` VALUES ('295', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 16:02:29');
INSERT INTO `system_logs` VALUES ('296', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 13:17:38');
INSERT INTO `system_logs` VALUES ('297', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 13:17:39');
INSERT INTO `system_logs` VALUES ('298', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 13:33:50');
INSERT INTO `system_logs` VALUES ('299', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 16:04:42');
INSERT INTO `system_logs` VALUES ('300', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 17:21:45');
INSERT INTO `system_logs` VALUES ('301', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 17:38:21');
INSERT INTO `system_logs` VALUES ('302', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 18:17:19');
INSERT INTO `system_logs` VALUES ('303', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 19:18:44');
INSERT INTO `system_logs` VALUES ('304', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 19:20:48');
INSERT INTO `system_logs` VALUES ('305', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 23:30:41');
INSERT INTO `system_logs` VALUES ('306', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 23:31:48');
INSERT INTO `system_logs` VALUES ('307', NULL, NULL, NULL, NULL, 'Superadmin Login Failed', 'Superadmin', NULL, 'Failed login attempt with username/email: jeemndzsuperadd (incorrect password)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 23:36:00');
INSERT INTO `system_logs` VALUES ('308', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 23:36:09');
INSERT INTO `system_logs` VALUES ('309', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Create Tenant', 'Tenant', '5', 'Created new tenant: AutoMatik Repair Shop, Owner: Jm Mendoza, Subscription: basic, Status: Active', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 23:43:59');
INSERT INTO `system_logs` VALUES ('310', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Create Tenant', 'Tenant', '5', 'Created new tenant: AutoMatik Repair Shop, Owner: JM Mendoza, Subscription: basic, Status: Active', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 23:47:06');
INSERT INTO `system_logs` VALUES ('311', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 23:48:32');
INSERT INTO `system_logs` VALUES ('312', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 23:58:54');
INSERT INTO `system_logs` VALUES ('313', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 00:07:35');
INSERT INTO `system_logs` VALUES ('314', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 00:18:26');
INSERT INTO `system_logs` VALUES ('315', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Update Subscription Plan', 'Subscription Plan', '1', 'Updated plan: Name=Basic Plan, Price=999.00/month', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 00:27:56');
INSERT INTO `system_logs` VALUES ('316', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Update Subscription Plan', 'Subscription Plan', '2', 'Updated plan: Name=Standard Plan, Price=2,999.00/month', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 00:28:06');
INSERT INTO `system_logs` VALUES ('317', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Update Subscription Plan', 'Subscription Plan', '3', 'Updated plan: Name=Premium Plan, Price=15,000.00/month', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 00:28:38');
INSERT INTO `system_logs` VALUES ('318', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Update Subscription Plan', 'Subscription Plan', '3', 'Updated plan: Name=Premium Plan, Price=15,000.00/month', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 00:28:42');
INSERT INTO `system_logs` VALUES ('319', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Update Subscription Plan', 'Subscription Plan', '3', 'Updated plan: Name=Premium Plan, Price=8,999.00/month', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 00:29:07');
INSERT INTO `system_logs` VALUES ('320', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 00:29:19');
INSERT INTO `system_logs` VALUES ('321', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 00:36:31');
INSERT INTO `system_logs` VALUES ('322', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 00:38:47');
INSERT INTO `system_logs` VALUES ('323', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 00:39:21');
INSERT INTO `system_logs` VALUES ('324', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 00:39:57');
INSERT INTO `system_logs` VALUES ('325', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 00:45:49');
INSERT INTO `system_logs` VALUES ('326', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 11:04:49');
INSERT INTO `system_logs` VALUES ('327', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 11:49:50');
INSERT INTO `system_logs` VALUES ('328', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 11:51:37');
INSERT INTO `system_logs` VALUES ('329', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 11:57:05');
INSERT INTO `system_logs` VALUES ('330', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 12:00:51');
INSERT INTO `system_logs` VALUES ('331', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 14:41:01');
INSERT INTO `system_logs` VALUES ('332', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 14:57:17');
INSERT INTO `system_logs` VALUES ('333', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 16:03:31');
INSERT INTO `system_logs` VALUES ('334', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 16:09:20');
INSERT INTO `system_logs` VALUES ('335', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 16:35:52');
INSERT INTO `system_logs` VALUES ('336', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 18:19:56');
INSERT INTO `system_logs` VALUES ('337', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Reject Applicant', 'Applicant', '4', 'Applicant rejected. Owner: Jm Mendoza, Shop: AutoMotivation Auto Repair Shop', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 18:26:28');
INSERT INTO `system_logs` VALUES ('338', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 18:41:17');
INSERT INTO `system_logs` VALUES ('339', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Send Billing Notification', 'Billing', '2', 'Billing reminder sent for next billing date: 2026-04-26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 18:57:10');
INSERT INTO `system_logs` VALUES ('340', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Suspend Account - Unpaid Invoice', 'Account', '2', 'Account suspended due to unpaid billing. Shop: RDM, Owner: Amiel Carl Santos, Overdue Date: 2026-04-26, Amount: PHP 4,999.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 18:57:35');
INSERT INTO `system_logs` VALUES ('341', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: mendozajohmaverick1@gmail.com', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 20:25:09');
INSERT INTO `system_logs` VALUES ('342', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Accept Applicant', 'Applicant', '4', 'Applicant approved and activated. Owner: Jm Mendoza, Shop: AutoMotivation Auto Repair Shop, Plan: Basic Plan, Billing Cycle: monthly, Amount: PHP 999.00', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 20:25:24');
INSERT INTO `system_logs` VALUES ('343', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Update Tenant Information', 'Tenant', '2', 'Updated: Shop Name, Address, Owner Name, Email, Contact, Status', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 20:34:54');
INSERT INTO `system_logs` VALUES ('344', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 03:56:11');
INSERT INTO `system_logs` VALUES ('345', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 04:12:01');
INSERT INTO `system_logs` VALUES ('346', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 04:22:26');
INSERT INTO `system_logs` VALUES ('347', '2', '2', 'RDM', 'admin', 'LOGIN', 'tenant', '2', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 04:22:33');
INSERT INTO `system_logs` VALUES ('348', '2', '2', 'RDM', 'admin', 'LOGOUT', 'tenant', '2', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 04:36:01');
INSERT INTO `system_logs` VALUES ('349', '1', '1', 'EDMs Auto Shop', 'admin', 'LOGIN', 'tenant', '1', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 04:37:59');
INSERT INTO `system_logs` VALUES ('350', '1', '1', 'EDMs Auto Shop', 'admin', 'LOGOUT', 'tenant', '1', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 04:38:15');
INSERT INTO `system_logs` VALUES ('351', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 04:40:03');
INSERT INTO `system_logs` VALUES ('352', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 04:43:22');
INSERT INTO `system_logs` VALUES ('353', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Accept Applicant', 'Applicant', '4', 'Applicant approved and activated. Owner: Jm Mendoza, Shop: AutoMotivation Auto Repair Shop, Plan: Basic Plan, Billing Cycle: quarterly, Amount: PHP 2,997.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 04:44:46');
INSERT INTO `system_logs` VALUES ('354', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Accept Applicant', 'Applicant', '4', 'Applicant approved and activated. Owner: Jm Mendoza, Shop: AutoMotivation Auto Repair Shop, Plan: Basic Plan, Billing Cycle: quarterly, Amount: PHP 2,997.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 04:44:52');
INSERT INTO `system_logs` VALUES ('355', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Accept Applicant', 'Applicant', '4', 'Applicant approved and activated. Owner: Jm Mendoza, Shop: AutoMotivation Auto Repair Shop, Plan: Basic Plan, Billing Cycle: quarterly, Amount: PHP 2,997.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 04:44:58');
INSERT INTO `system_logs` VALUES ('356', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Accept Applicant', 'Applicant', '4', 'Applicant approved and activated. Owner: Jm Mendoza, Shop: AutoMotivation Auto Repair Shop, Plan: Basic Plan, Billing Cycle: quarterly, Amount: PHP 2,997.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 04:45:05');
INSERT INTO `system_logs` VALUES ('357', '4', '4', 'AutoMotivation Auto Repair Shop', 'admin', 'LOGIN', 'tenant', '4', 'Owner logged in (first login)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 04:46:10');
INSERT INTO `system_logs` VALUES ('358', '4', '4', 'AutoMotivation Auto Repair Shop', 'admin', 'LOGIN', 'tenant', '4', 'Owner logged in (first login)', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 04:47:31');
INSERT INTO `system_logs` VALUES ('359', '4', '4', 'AutoMotivation Auto Repair Shop', 'admin', 'LOGIN', 'tenant', '4', 'Owner logged in', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 08:31:59');
INSERT INTO `system_logs` VALUES ('360', '4', '4', 'AutoMotivation Auto Repair Shop', 'admin', 'LOGIN', 'tenant', '4', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 09:29:59');
INSERT INTO `system_logs` VALUES ('361', '4', '4', 'AutoMotivation Auto Repair Shop', 'admin', 'LOGIN', 'tenant', '4', 'Owner logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 09:30:00');
INSERT INTO `system_logs` VALUES ('362', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 10:57:00');
INSERT INTO `system_logs` VALUES ('363', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Reject Applicant', 'Applicant', '5', 'Applicant rejected. Owner: Collin Philipp, Shop: AutoMotivation Auto Repair Shop', '169.254.129.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 10:57:21');
INSERT INTO `system_logs` VALUES ('364', '4', '4', 'AutoMotivation Auto Repair Shop', 'admin', 'LOGOUT', 'tenant', '4', 'Tenant logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 11:38:31');
INSERT INTO `system_logs` VALUES ('365', NULL, NULL, 'John Maverick Mendoza', 'superadmin', 'Superadmin Login', 'Superadmin', '1', 'Successful login via username/email: jeemndzsuperadd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-01 11:38:44');

-- ----------------------------
-- Table structure for tenant_customizations
-- ----------------------------
DROP TABLE IF EXISTS `tenant_customizations`;
CREATE TABLE `tenant_customizations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenantID` int NOT NULL,
  `shop_name` varchar(50) NOT NULL,
  `shop_address` varchar(201) DEFAULT NULL,
  `corner_radius` enum('sharp','rounded','pill') DEFAULT 'rounded',
  `primary_color` varchar(7) DEFAULT '#1152d4',
  `accent_color` varchar(7) DEFAULT '#1152d4',
  `welcome_heading` varchar(255) DEFAULT NULL,
  `welcome_subtext` text,
  `logo_path` varchar(500) DEFAULT NULL,
  `hero_image_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant` (`tenantID`),
  CONSTRAINT `tenant_customizations_ibfk_1` FOREIGN KEY (`tenantID`) REFERENCES `owners` (`tenantID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of tenant_customizations
-- ----------------------------
INSERT INTO `tenant_customizations` VALUES ('1', '1', 'EDM Auto Repair Shop', 'Baliwag City, Bulacan', 'rounded', '#0055ff', '#c2db00', 'Welcome to EDM Auto Repair shop', 'Experience High Quality Service', 'tenant_1/logo_1774371906_6451e132.png', 'tenant_1/hero_1774371906_78eff44c.png', '2026-03-23 15:33:01', '2026-03-24 17:05:06');
INSERT INTO `tenant_customizations` VALUES ('2', '2', 'RDM Auto Repair Shop', 'Baliwag City, Bulacan', 'sharp', '#930108', '#0038a8', 'Welcome to RDM Auto Repair shop', 'Welcome po dito lahat ng kotseng may sira', 'tenant_2/logo_1774457156_32e5c1f6.png', 'tenant_2/hero_1774457156_c62f92ce.png', '2026-03-25 16:45:56', '2026-03-27 01:12:51');
INSERT INTO `tenant_customizations` VALUES ('7', '4', 'AutoMotivation Auto Repair Shop', 'Baliwag City, Bulacan', 'rounded', '#020d45', '#002b80', 'Welcome to AutoMotivation Auto Repair Shop', 'Car Repair Ano tara!?', 'tenant_4/logo_1777610982_bee1e2f2.png', 'tenant_4/hero_1777610982_35bf3e4d.jpg', '2026-05-01 04:49:42', '2026-05-01 04:49:42');

-- ----------------------------
-- Table structure for users
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `tenantID` int DEFAULT NULL,
  `fullName` varchar(100) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `address` text,
  `email` varchar(180) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `contactNumber` varchar(20) DEFAULT NULL,
  `role` enum('client') NOT NULL DEFAULT 'client',
  PRIMARY KEY (`user_id`),
  KEY `tenantID` (`tenantID`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`tenantID`) REFERENCES `owners` (`tenantID`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of users
-- ----------------------------
INSERT INTO `users` VALUES ('5', '2', 'JM Mendoza', 'mndzjeem1', 'San Rafael, Bulacan', 'johnmaverickmendoza1@gmail.com', '$2y$12$06Q88Edha8o0ZSEqRVKEF.QT.gBeZGoUuoV7zcMLk6FB2WVkXLLlW', NULL, NULL, '09455823682', 'client');
INSERT INTO `users` VALUES ('8', '1', 'Amiel Carl Santos', NULL, NULL, 'johnlurjmendoza1@gmail.com', '$2y$12$IPr3WEHHnp2cAV.y/QuNa.6gUNIaEkA1jRkppCgHhPqZ5rYWeMRe.', NULL, NULL, '09455823682', 'client');
INSERT INTO `users` VALUES ('9', '2', 'Ella Payumo', 'elaaavp24', NULL, 'ellapayumo@gmail.com', '$2y$12$LjPBSfG7H2rz16hnv8S84.eLTg63JlOzGx5L5nVLmJr5my0GxYUwm', NULL, NULL, '09356394055', 'client');
INSERT INTO `users` VALUES ('10', '2', 'Airwin Maui Del Rosario', NULL, 'San Rafael Bulacan', 'mendozajohmaverick1@gmail.com', '$2y$12$ko4oFKRJGiHcKuaTrABd3.GrwAA2w5oe7JKmkSLl32cV8MJdLYfdC', NULL, NULL, '09356394055', 'client');
INSERT INTO `users` VALUES ('11', '2', 'Carl Micko Tibay', NULL, 'San Rafael Bulacan', 'carlmicko1@gmail.com', '$2y$10$Py5pQbEzsgBpXwc4Lp2SKuK.Id2cpGMfazQWFsj5iqtN7n.rcVdBu', NULL, NULL, '09356394055', 'client');
INSERT INTO `users` VALUES ('17', '2', 'Harold Dela Cruz', 'arolddc1', 'Pampanga', 'pelic71133@azurecore.com', '$2y$12$2w0BwWOSLtEPNcL9Gjg0EuW7Hpp2lwS5twXeM1pjSHUSzUOGvcjBS', NULL, NULL, '09356394287', 'client');
INSERT INTO `users` VALUES ('18', '1', 'Eunice Diestro', 'eunice123', 'San Miguel,Bulacan', 'eunice@gmail.com', '$2y$12$HmOAamZu9WAtMWJ0osiuIunAjUKdCteQKwf5GO5SRITeI5V4Ay.lS', NULL, NULL, '93654251866', 'client');
INSERT INTO `users` VALUES ('19', '2', 'John Hil Victoria', 'johnhil1', 'Baliwag', 'johnhil@gmail.com', '$2y$12$FoiNJTaZ3yvW0az46/4u..KM0Dv0.5Nam1Y0FK4mu/gVjYu9/DVjK', NULL, NULL, '09608242185', 'client');
INSERT INTO `users` VALUES ('20', '2', 'Jerome Dagta', NULL, 'Malolos, Bulacan', 'jerome@gmail.com', '$2y$10$vRiN8MfjiVL2r7GgXMlSXeunW2QTxG2CgF89JZSlKoGRhxiB7xemO', NULL, NULL, '093212132312', 'client');
INSERT INTO `users` VALUES ('21', '2', 'Jm Mendoza', NULL, '218, Ulingao, San Rafael, Bulacan', 'jekide9322@cadinr.com', '$2y$10$nmCU0X35YL.f/450MKSmpuvPzLOshrU6AzxNnJsdvbGpwWoe5F0wC', NULL, NULL, '09356394055', 'client');
INSERT INTO `users` VALUES ('22', '4', 'Jm Mendoza', NULL, '218, Ulingao, San Rafael, Bulacan', 'hasevom391@inraud.com', '$2y$12$ac6Jl2i6cIPIcDjGvbh84OFCir8jF/ZuSGlqgUi.axH1ynIJ/zVaW', NULL, NULL, '09356394055', 'client');

-- ----------------------------
-- Table structure for vehicleinformation
-- ----------------------------
DROP TABLE IF EXISTS `vehicleinformation`;
CREATE TABLE `vehicleinformation` (
  `vehicle_id` int NOT NULL AUTO_INCREMENT,
  `tenantID` int NOT NULL,
  `user_id` int NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `year_model` year DEFAULT NULL,
  `fuel_type` enum('Gasoline','Diesel','Electric','Hybrid') NOT NULL,
  `transmission_type` enum('Manual','Automatic','CVT','DCT','AMT') NOT NULL,
  `engine_number` varchar(100) DEFAULT NULL,
  `mileage_km` int DEFAULT NULL,
  `vin_number` varchar(100) DEFAULT NULL,
  `plate_number` varchar(50) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `date_added` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`vehicle_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of vehicleinformation
-- ----------------------------
INSERT INTO `vehicleinformation` VALUES ('1', '2', '5', 'Honda', 'City', '2024', 'Gasoline', 'Automatic', 'ABC2625', '8000', '234235345234', 'ABC-37626', 'Red', 'Active', '2026-03-26 08:00:00');
INSERT INTO `vehicleinformation` VALUES ('2', '2', '5', 'Isuzu', 'mu-X', '2023', 'Gasoline', 'Automatic', 'ASDXDAS34242', '8000', NULL, 'SADA-3242', 'Silver', 'Active', '2026-03-26 08:00:00');
INSERT INTO `vehicleinformation` VALUES ('3', '1', '8', 'Suzuki', 'S-Presso', '2024', 'Gasoline', 'Automatic', 'KSHXV275251', '12000', NULL, 'KFH-86267', 'Red', 'Active', '2026-04-01 08:00:00');
INSERT INTO `vehicleinformation` VALUES ('4', '2', '5', 'Mitsubishi', 'Xpander Cross', '2026', 'Gasoline', 'Automatic', 'JXI72625', '6000', NULL, 'JWB-2861', 'White', 'Active', '2026-04-01 08:00:00');
INSERT INTO `vehicleinformation` VALUES ('5', '2', '5', 'Omoda', 'C5', '2024', 'Gasoline', 'Automatic', 'BFD6532', '6500', NULL, 'JGF-6536', 'Blue', 'Inactive', '2026-04-05 08:00:00');
INSERT INTO `vehicleinformation` VALUES ('6', '2', '9', 'BYD', 'Han EV', '2024', 'Hybrid', 'Automatic', 'JAGA62625', '6500', NULL, 'AHG-7255', 'blue', 'Active', '2026-04-06 08:00:00');
INSERT INTO `vehicleinformation` VALUES ('7', '2', '17', 'Mini', 'Countryman', '2024', 'Gasoline', 'Automatic', 'FEH2443356', '2500', NULL, 'GDD-3452', 'Green', 'Active', '2026-04-09 08:00:00');
INSERT INTO `vehicleinformation` VALUES ('8', '2', '17', 'Honda', 'Civic', '2026', 'Gasoline', 'Automatic', 'HGG645775', '2500', NULL, 'HBG-7554', 'Red', 'Active', '2026-04-09 08:00:00');
INSERT INTO `vehicleinformation` VALUES ('9', '2', '5', 'BYD', 'Seal', '2025', 'Electric', 'Automatic', 'KDB839551', '6500', NULL, 'HDB-8736', 'Silver', 'Active', '2026-04-09 08:00:00');
INSERT INTO `vehicleinformation` VALUES ('10', '2', '19', 'Toyota', 'Hilux', '2016', 'Diesel', 'Manual', 'ABC12345', '150000', NULL, 'DCQ 6656', 'Red', 'Active', '2026-04-10 08:00:00');
INSERT INTO `vehicleinformation` VALUES ('11', '4', '22', 'Hyundai', 'Santa Fe', '2025', 'Gasoline', 'Automatic', 'PJ8264682973772', '6000', NULL, 'ABC 1234', 'Blue', 'Active', '2026-05-01 08:00:00');

COMMIT;
