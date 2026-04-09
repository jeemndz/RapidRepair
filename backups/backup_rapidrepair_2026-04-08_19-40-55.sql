-- RapidRepair Database Backup
-- Generated at: 2026-04-08 19:40:55
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
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of appointment_services
-- ----------------------------
INSERT INTO `appointment_services` VALUES ('85', '70', '2', '3', '1000.00', '60', NULL, '2026-04-06 16:23:30');
INSERT INTO `appointment_services` VALUES ('86', '70', '2', '5', '1500.00', '60', NULL, '2026-04-06 16:23:30');
INSERT INTO `appointment_services` VALUES ('87', '71', '2', '5', '1500.00', '60', NULL, '2026-04-06 16:24:14');
INSERT INTO `appointment_services` VALUES ('88', '71', '2', '1', '3000.00', '120', NULL, '2026-04-06 16:24:14');
INSERT INTO `appointment_services` VALUES ('89', '71', '2', '4', '5000.00', '360', NULL, '2026-04-06 16:24:14');
INSERT INTO `appointment_services` VALUES ('90', '72', '2', '5', '1500.00', '60', NULL, '2026-04-06 22:55:45');
INSERT INTO `appointment_services` VALUES ('91', '72', '2', '1', '3000.00', '120', NULL, '2026-04-06 22:55:45');

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
  `status` enum('Pending','Confirmed','In Progress','Completed','Cancelled') DEFAULT 'Pending',
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
) ENGINE=InnoDB AUTO_INCREMENT=104 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of appointments
-- ----------------------------
INSERT INTO `appointments` VALUES ('70', '2', '5', '2', '2026-04-07', '10:30:00', 'Confirmed', NULL, '2500.00', '2026-04-06 16:23:30', '2026-04-06 16:23:43');
INSERT INTO `appointments` VALUES ('71', '2', '5', '4', '2026-04-07', '10:30:00', 'Confirmed', NULL, '9500.00', '2026-04-06 16:24:14', '2026-04-06 16:24:25');
INSERT INTO `appointments` VALUES ('72', '2', '5', '2', '2026-04-07', '10:30:00', 'In Progress', NULL, '4500.00', '2026-04-06 22:55:44', '2026-04-06 23:54:04');

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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of client_info
-- ----------------------------
INSERT INTO `client_info` VALUES ('4', 'Jm', 'Mendoza', 'mendozajohmaverick1@gmail.com', '$2y$10$.qOt3byUTgdzT.zRcPOMae.I.u41rSiG4XqLlN5lOgy8HfSTsJ6xK', '2026-04-02 08:42:36', '$2y$10$.qOt3byUTgdzT.zRcPOMae.I.u41rSiG4XqLlN5lOgy8HfSTsJ6xK');
INSERT INTO `client_info` VALUES ('5', 'Airwin Maui', 'Del Rosario', 'airwin@gmail.com', '$2y$12$nRF0.Fa5S75KnqAwaRmNh.G6/QDRwVcq4AL9wtVJF4HzniJFMZGS2', '2026-04-07 02:37:43', '$2y$12$nRF0.Fa5S75KnqAwaRmNh.G6/QDRwVcq4AL9wtVJF4HzniJFMZGS2');
INSERT INTO `client_info` VALUES ('6', 'Carl MIcko', 'Tibay', 'meloce1143@azucore.com', '$2y$12$gIaogeyOgUJ7380bnHYINuu6HpDOxD1cYeJ7cbvbR8D55BsK9WizW', '2026-04-07 03:24:01', '$2y$12$gIaogeyOgUJ7380bnHYINuu6HpDOxD1cYeJ7cbvbR8D55BsK9WizW');

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of inventory_items
-- ----------------------------
INSERT INTO `inventory_items` VALUES ('1', '2', 'Engine Oil', NULL, 'Cooling System', '9', '100', '600.00', 'Honda Gears', 'Inactive', '2026-04-03 04:43:52', '2026-04-06 04:49:03');
INSERT INTO `inventory_items` VALUES ('2', '2', 'Oil Filter', NULL, 'Maintenance', '99', '10', '850.00', 'RXS Auto Parts', 'Active', '2026-04-05 10:40:30', '2026-04-05 10:40:51');

-- ----------------------------
-- Table structure for owners
-- ----------------------------
DROP TABLE IF EXISTS `owners`;
CREATE TABLE `owners` (
  `tenantID` int NOT NULL AUTO_INCREMENT,
  `ownerName` varchar(150) DEFAULT NULL,
  `shopName` varchar(75) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `login_slug` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `first_login` tinyint(1) DEFAULT '1',
  `contactNumber` varchar(20) DEFAULT NULL,
  `shopAddress` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('Pending','Active','Inactive','Suspended') DEFAULT 'Pending',
  `subscription_plan` varchar(40) DEFAULT NULL,
  `billing_cycle` varchar(20) DEFAULT NULL,
  `subscription_start` date DEFAULT NULL,
  `subscription_end` date DEFAULT NULL,
  `plan_price` decimal(10,2) DEFAULT NULL,
  `next_billing_date` date DEFAULT NULL,
  PRIMARY KEY (`tenantID`),
  UNIQUE KEY `login_slug` (`login_slug`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of owners
-- ----------------------------
INSERT INTO `owners` VALUES ('1', 'Jm Mendoza', 'EDMs Auto Shop', 'edmshoprr123', 'edms-auto-shop', 'mendozajohmaverick1@gmail.com', '$2y$12$I0xTTr8Z/X8P/ElgEaUOseomel9I2FlET/F6Qb5W2ct5NeLuAbBbu', '0', '09356394055', 'Baliwag City Bulacan', '2026-03-23 15:21:52', 'Active', 'medium', 'quarterly', '2026-03-26', '2026-06-26', '14997.00', '2026-06-26');
INSERT INTO `owners` VALUES ('2', 'Amiel Carl Santos', 'RDM', 'rdmshoprr123', 'rdm', 'johnlurjmendoza1@gmail.com', '$2y$12$MH8esN/9ydDp1DaoER3rxOmNCpRflY4cZnUtWNOrVYXBKydO53Ny.', '0', '09356394055', 'San Rafael Bulacan', '2026-03-25 16:40:56', 'Active', 'basic', 'monthly', '2026-03-26', '2026-04-26', '999.00', '2026-04-26');
INSERT INTO `owners` VALUES ('3', 'Juan Tamad', 'RXS Auto Shop', 'rxsautoshop', 'rxs-auto-shop', 'meloce1143@azucore.com', 'BtmqeUEyihJb', '1', '09356394055', 'San Rafael Bulacan', '2026-04-08 17:33:01', 'Active', 'basic', 'monthly', '2026-04-08', '2026-05-08', '999.00', '2026-05-08');
INSERT INTO `owners` VALUES ('4', 'Pedro Penduko', 'ABCDE', 'abcde', 'abcde', 'boyim21535@azucore.com', 'DdPF4hNHBQcG', '1', '09356394055', 'Baliwag City, Bulacan', '2026-04-08 17:40:10', 'Inactive', 'basic', 'monthly', '2026-04-08', '2026-05-08', '999.00', '2026-05-08');

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of payments
-- ----------------------------
INSERT INTO `payments` VALUES ('1', '2', '5', '70', '2500.00', '2500.00', '0.00', 'Cash', '2026-04-06 23:51:02', 'Paid', 'AP-00001', NULL, 'none', '2026-04-06 23:51:02', '2026-04-06 23:51:02');
INSERT INTO `payments` VALUES ('2', '2', '5', '71', '9500.00', '9500.00', '0.00', 'Cash', '2026-04-06 23:51:02', 'Paid', 'AP-00002', NULL, 'none', '2026-04-06 23:51:02', '2026-04-06 23:51:02');
INSERT INTO `payments` VALUES ('3', '2', '5', '72', '4500.00', '4500.00', '0.00', 'Cash', '2026-04-06 23:51:02', 'Paid', 'AP-00003', NULL, 'none', '2026-04-06 23:51:02', '2026-04-06 23:51:02');

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
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of repair_job_services
-- ----------------------------
INSERT INTO `repair_job_services` VALUES ('17', '16', '2', '3', '1000.00', '60', NULL, 'In Progress', NULL, NULL, '2026-04-06 16:23:44', '2026-04-06 16:23:44');
INSERT INTO `repair_job_services` VALUES ('18', '16', '2', '5', '1500.00', '60', NULL, 'In Progress', NULL, NULL, '2026-04-06 16:23:44', '2026-04-06 16:23:44');
INSERT INTO `repair_job_services` VALUES ('19', '17', '2', '5', '1500.00', '60', NULL, 'In Progress', NULL, NULL, '2026-04-06 16:24:26', '2026-04-06 16:24:26');
INSERT INTO `repair_job_services` VALUES ('20', '17', '2', '1', '3000.00', '120', NULL, 'In Progress', NULL, NULL, '2026-04-06 16:24:26', '2026-04-06 16:24:26');
INSERT INTO `repair_job_services` VALUES ('21', '17', '2', '4', '5000.00', '360', NULL, 'In Progress', NULL, NULL, '2026-04-06 16:24:26', '2026-04-06 16:24:26');
INSERT INTO `repair_job_services` VALUES ('22', '18', '2', '5', '1500.00', '60', NULL, 'In Progress', NULL, NULL, '2026-04-06 23:54:04', '2026-04-06 23:54:04');
INSERT INTO `repair_job_services` VALUES ('23', '18', '2', '1', '3000.00', '120', NULL, 'In Progress', NULL, NULL, '2026-04-06 23:54:04', '2026-04-06 23:54:04');

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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of repair_jobs
-- ----------------------------
INSERT INTO `repair_jobs` VALUES ('16', '2', '70', '5', '2', 'RR-00001', 'Bay 1', 'seniortech', 'Completed', 'Normal', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 16:30:50', NULL, '0.00', '0.00', '2500.00', '2026-04-06 16:23:43', '2026-04-06 16:30:50');
INSERT INTO `repair_jobs` VALUES ('17', '2', '71', '5', '4', 'RR-00002', 'Bay 3', 'seniortech', 'Completed', 'Normal', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0.00', '0.00', '9500.00', '2026-04-06 16:24:26', '2026-04-06 23:53:34');
INSERT INTO `repair_jobs` VALUES ('18', '2', '72', '5', '2', 'RR-00003', NULL, NULL, 'Completed', 'Normal', NULL, NULL, NULL, NULL, '2026-04-06 23:54:04', '2026-04-06 23:54:04', NULL, '2026-04-06 23:54:27', NULL, '4500.00', '0.00', '4500.00', '2026-04-06 23:54:04', '2026-04-06 23:54:27');

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
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of roles
-- ----------------------------
INSERT INTO `roles` VALUES ('9', 'Senior Technician', 'Jm', 'Mendoza', 'seniortech', 'maverickjmz15@gmail.com', '$2y$10$cmR.9dgWdCQiQHAJKMQl2uosJbtCJMIqowOnpJTOYcXQVTCT6t.Pi', 'Rapair Jobs, Appointments, Inventory', '1', 'Active', '2', '2026-04-03 10:05:28', '2026-04-03 10:05:28');

-- ----------------------------
-- Table structure for services
-- ----------------------------
DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
  `service_id` int NOT NULL AUTO_INCREMENT,
  `tenantID` int NOT NULL,
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
  CONSTRAINT `fk_services_tenant` FOREIGN KEY (`tenantID`) REFERENCES `owners` (`tenantID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of services
-- ----------------------------
INSERT INTO `services` VALUES ('1', '2', 'Shock Absorber', 'Maintenance of vehicle suspension', '3000.00', '120', 'Suspension', 'Active', '2026-03-26 08:37:59', '2026-03-26 08:37:59');
INSERT INTO `services` VALUES ('2', '1', 'Axle Bearings', 'Change in Axle Bearings', '2000.00', '240', 'Transmission', 'Active', '2026-04-01 17:55:43', '2026-04-01 17:55:43');
INSERT INTO `services` VALUES ('3', '2', 'Change Brake Oil', 'New break oil will be applied for better breaking', '1000.00', '60', 'Maintenance', 'Active', '2026-04-03 07:59:40', '2026-04-06 16:38:36');
INSERT INTO `services` VALUES ('4', '2', 'Underchassis Repair', 'Repairs on underchassis', '5000.00', '360', 'Other', 'Active', '2026-04-03 11:20:40', '2026-04-03 11:20:40');
INSERT INTO `services` VALUES ('5', '2', 'Preventive Maintenance Service (PMS)', 'Monthly Maintenance', '1500.00', '60', 'Maintenance', 'Active', '2026-04-03 11:21:10', '2026-04-03 11:21:10');

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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of stock_movements
-- ----------------------------
INSERT INTO `stock_movements` VALUES ('1', '2', '1', 'IN', '10', 'Manual', NULL, 'Initial stock when item was created.', '2026-04-03 04:43:52');
INSERT INTO `stock_movements` VALUES ('2', '2', '2', 'IN', '100', 'Manual', NULL, 'Initial stock when item was created.', '2026-04-05 10:40:30');
INSERT INTO `stock_movements` VALUES ('3', '2', '2', 'OUT', '1', 'RepairJob', NULL, NULL, '2026-04-05 10:40:51');
INSERT INTO `stock_movements` VALUES ('4', '2', '1', 'OUT', '1', 'RepairJob', NULL, NULL, '2026-04-06 04:49:03');

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of subscription_plans
-- ----------------------------
INSERT INTO `subscription_plans` VALUES ('1', 'basic', 'Basic Plan', '999.00', '[\"1 Shop\",\"2 Staff\"]', '1', '2026-03-21 17:26:21');
INSERT INTO `subscription_plans` VALUES ('2', 'medium', 'Standard Plan', '4999.00', '[\"1 shop\",\"3-5 staffs\",\"Unlock other features\",\"Include Inventory Management System\"]', '1', '2026-03-21 19:23:25');
INSERT INTO `subscription_plans` VALUES ('3', 'premium-plan', 'Premium Plan', '15000.00', '[\"1 Shop\",\"10-20 Staffs\",\"Unlocked All Features\",\"Unlocked All Mobile Application Feaatures\"]', '1', '2026-03-22 05:22:07');

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
  `status` enum('active','cancelled','expired') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`subscription_id`),
  KEY `idx_tenantID` (`tenantID`),
  KEY `idx_plan_id` (`plan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of subscriptions
-- ----------------------------
INSERT INTO `subscriptions` VALUES ('1', '3', '3', 'yearly', '2026-04-02', '2027-04-02', '2027-04-02', '180000.00', 'active', '2026-03-25 17:48:02', '2026-04-02 08:20:29');
INSERT INTO `subscriptions` VALUES ('2', '5', '3', 'quarterly', '2026-04-02', '2026-07-02', '2026-07-02', '45000.00', 'active', '2026-03-26 18:51:25', '2026-04-02 09:15:48');
INSERT INTO `subscriptions` VALUES ('3', '003', '1', 'monthly', '2026-04-08', '2026-05-08', '2026-05-08', '999.00', 'active', '2026-04-02 08:41:14', '2026-04-08 17:33:01');
INSERT INTO `subscriptions` VALUES ('4', '4', '1', 'monthly', '2026-04-07', '2026-05-07', '2026-05-07', '999.00', 'active', '2026-04-07 02:39:40', '2026-04-07 02:39:40');
INSERT INTO `subscriptions` VALUES ('5', '005', '1', 'monthly', '2026-04-07', '2026-05-07', '2026-05-07', '999.00', 'active', '2026-04-07 03:10:20', '2026-04-07 03:10:20');
INSERT INTO `subscriptions` VALUES ('6', '006', '1', 'monthly', '2026-04-07', '2026-05-07', '2026-05-07', '999.00', 'active', '2026-04-07 03:10:24', '2026-04-07 03:10:24');
INSERT INTO `subscriptions` VALUES ('7', '7', '2', 'monthly', '2026-04-07', '2026-05-07', '2026-05-07', '4999.00', 'active', '2026-04-07 03:32:53', '2026-04-07 03:32:53');
INSERT INTO `subscriptions` VALUES ('8', '008', '1', 'monthly', '2026-04-08', '2026-05-08', '2026-05-08', '999.00', 'active', '2026-04-08 17:27:12', '2026-04-08 17:27:12');
INSERT INTO `subscriptions` VALUES ('9', '004', '1', 'monthly', '2026-04-08', '2026-05-08', '2026-05-08', '999.00', 'active', '2026-04-08 17:40:10', '2026-04-08 17:40:10');

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
  PRIMARY KEY (`superadmin_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of superadmin
-- ----------------------------
INSERT INTO `superadmin` VALUES ('1', 'John Maverick Mendoza', 'jeemndzsuperadd', 'mendozajohmaverick1@gmail.com', 'superadd123!', 'Superadmin', 'Global Root', 'Active', '2026-03-15 13:23:38', '2026-04-01 07:29:30');
INSERT INTO `superadmin` VALUES ('2', 'Amiel Carl Santos', 'amielsuperadd123!', 'boyim21535@azucore.com', 'amielsuperadd1!', 'Superadmin', 'Global Root', 'Active', '2026-04-08 17:34:57', '2026-04-08 17:36:55');

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
) ENGINE=InnoDB AUTO_INCREMENT=127 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of tenant_customizations
-- ----------------------------
INSERT INTO `tenant_customizations` VALUES ('1', '1', 'EDM Auto Repair Shop', 'Baliwag City, Bulacan', 'rounded', '#0055ff', '#c2db00', 'Welcome to EDM Auto Repair shop', 'Experience High Quality Service', 'tenant_1/logo_1774371906_6451e132.png', 'tenant_1/hero_1774371906_78eff44c.png', '2026-03-23 15:33:01', '2026-03-24 17:05:06');
INSERT INTO `tenant_customizations` VALUES ('2', '2', 'RDM Auto Repair Shop', 'Baliwag City, Bulacan', 'sharp', '#930108', '#0038a8', 'Welcome to RDM Auto Repair shop', 'Welcome po dito lahat ng kotseng may sira', 'tenant_2/logo_1774457156_32e5c1f6.png', 'tenant_2/hero_1774457156_c62f92ce.png', '2026-03-25 16:45:56', '2026-03-27 01:12:51');

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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of users
-- ----------------------------
INSERT INTO `users` VALUES ('5', '2', 'JM Mendoza', 'mndzjeem1', 'San Rafael, Bulacan', 'johnmaverickmendoza1@gmail.com', '$2y$12$06Q88Edha8o0ZSEqRVKEF.QT.gBeZGoUuoV7zcMLk6FB2WVkXLLlW', NULL, NULL, '09455823682', 'client');
INSERT INTO `users` VALUES ('8', '1', 'Amiel Carl Santos', NULL, NULL, 'johnlurjmendoza1@gmail.com', '$2y$12$IPr3WEHHnp2cAV.y/QuNa.6gUNIaEkA1jRkppCgHhPqZ5rYWeMRe.', NULL, NULL, '09455823682', 'client');
INSERT INTO `users` VALUES ('9', '2', 'Ella Payumo', 'elaaavp24', NULL, 'ellapayumo@gmail.com', '$2y$12$LjPBSfG7H2rz16hnv8S84.eLTg63JlOzGx5L5nVLmJr5my0GxYUwm', NULL, NULL, '09356394055', 'client');
INSERT INTO `users` VALUES ('10', '2', 'Airwin Maui Del Rosario', NULL, 'San Rafael Bulacan', 'mendozajohmaverick1@gmail.com', '$2y$12$ko4oFKRJGiHcKuaTrABd3.GrwAA2w5oe7JKmkSLl32cV8MJdLYfdC', NULL, NULL, '09356394055', 'client');

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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of vehicleinformation
-- ----------------------------
INSERT INTO `vehicleinformation` VALUES ('1', '2', '5', 'Honda', 'City', '2024', 'Gasoline', 'Automatic', 'ABC2625', '8000', '234235345234', 'ABC-37626', 'Red', 'Active', '2026-03-26 08:00:00');
INSERT INTO `vehicleinformation` VALUES ('2', '2', '5', 'Isuzu', 'mu-X', '2023', 'Gasoline', 'Automatic', 'ASDXDAS34242', '8000', NULL, 'SADA-3242', 'Silver', 'Active', '2026-03-26 08:00:00');
INSERT INTO `vehicleinformation` VALUES ('3', '1', '8', 'Suzuki', 'S-Presso', '2024', 'Gasoline', 'Automatic', 'KSHXV275251', '12000', NULL, 'KFH-86267', 'Red', 'Active', '2026-04-01 08:00:00');
INSERT INTO `vehicleinformation` VALUES ('4', '2', '5', 'Mitsubishi', 'Xpander Cross', '2026', 'Gasoline', 'Automatic', 'JXI72625', '6000', NULL, 'JWB-2861', 'White', 'Active', '2026-04-01 08:00:00');
INSERT INTO `vehicleinformation` VALUES ('5', '2', '5', 'Omoda', 'C5', '2024', 'Gasoline', 'Automatic', 'BFD6532', '6500', NULL, 'JGF-6536', 'Blue', 'Active', '2026-04-05 08:00:00');
INSERT INTO `vehicleinformation` VALUES ('6', '2', '9', 'BYD', 'Han EV', '2024', 'Hybrid', 'Automatic', 'JAGA62625', '6500', NULL, 'AHG-7255', 'blue', 'Active', '2026-04-06 08:00:00');

COMMIT;
