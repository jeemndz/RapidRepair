-- RapidRepair Database Backup
-- Generated at: 2026-03-24 14:45:30
-- Database: rapidrepairs

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- ----------------------------
-- Table structure for owners
-- ----------------------------
DROP TABLE IF EXISTS `owners`;
CREATE TABLE `owners` (
  `tenantID` int NOT NULL AUTO_INCREMENT,
  `ownerName` varchar(255) DEFAULT NULL,
  `shopName` varchar(255) DEFAULT NULL,
  `login_slug` varchar(150) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `first_login` tinyint(1) DEFAULT '1',
  `contactNumber` varchar(50) DEFAULT NULL,
  `shopAddress` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('Pending','Active','Inactive','Suspended') DEFAULT 'Pending',
  `subscription_plan` varchar(50) DEFAULT NULL,
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
INSERT INTO `owners` VALUES ('1', 'Jm Mendoza', 'EDMs Auto Shop', 'edms-auto-shop', 'mendozajohmaverick1@gmail.com', '$2y$12$I0xTTr8Z/X8P/ElgEaUOseomel9I2FlET/F6Qb5W2ct5NeLuAbBbu', '0', '09356394055', 'Baliwag City, Bulacan', '2026-03-23 15:21:52', 'Active', 'medium', 'monthly', '2026-03-23', '2026-04-23', '4999.00', '2026-04-23');

-- ----------------------------
-- Table structure for roles
-- ----------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `role_id` int NOT NULL AUTO_INCREMENT,
  `role_name` varchar(100) NOT NULL,
  `access_scope` varchar(255) NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of roles
-- ----------------------------
INSERT INTO `roles` VALUES ('1', 'Superadmin', 'Global Root, Financials', '1', '2026-03-23 16:00:27', '2026-03-23 16:00:27');
INSERT INTO `roles` VALUES ('2', 'Support', 'Ticket Read/Write, User Reset', '1', '2026-03-23 16:00:27', '2026-03-23 16:00:27');
INSERT INTO `roles` VALUES ('3', 'Auditor', 'Read Only, Log Export', '1', '2026-03-23 16:00:27', '2026-03-23 16:00:35');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for superadmin
-- ----------------------------
DROP TABLE IF EXISTS `superadmin`;
CREATE TABLE `superadmin` (
  `superadmin_id` int NOT NULL AUTO_INCREMENT,
  `fullName` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(100) NOT NULL DEFAULT 'Superadmin',
  `access_scope` varchar(255) NOT NULL DEFAULT 'Global Root',
  `status` varchar(50) NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`superadmin_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of superadmin
-- ----------------------------
INSERT INTO `superadmin` VALUES ('1', 'John Maverick Mendoza', 'mendozajohmaverick1@gmail.com', 'superadd123!', 'Superadmin', 'Global Root', 'Active', '2026-03-15 13:23:38', '2026-03-23 16:07:16');

-- ----------------------------
-- Table structure for superadmin_settings
-- ----------------------------
DROP TABLE IF EXISTS `superadmin_settings`;
CREATE TABLE `superadmin_settings` (
  `id` tinyint NOT NULL,
  `system_name` varchar(255) NOT NULL DEFAULT 'Cobalt Precision',
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
  `user_role` varchar(50) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `tenantID` (`tenantID`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`tenantID`) REFERENCES `owners` (`tenantID`),
  CONSTRAINT `system_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for tenant_customizations
-- ----------------------------
DROP TABLE IF EXISTS `tenant_customizations`;
CREATE TABLE `tenant_customizations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenantID` int NOT NULL,
  `shop_name` varchar(255) NOT NULL,
  `shop_address` varchar(500) DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of tenant_customizations
-- ----------------------------
INSERT INTO `tenant_customizations` VALUES ('1', '1', 'EDM Auto Repair Shop', 'Baliwag City, Bulacan', 'rounded', '#0055ff', '#c2db00', 'Welcome to EDM Auto Repair shop', 'Experience High Quality Service', NULL, NULL, '2026-03-23 15:33:01', '2026-03-23 15:33:01');

-- ----------------------------
-- Table structure for users
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `tenantID` int DEFAULT NULL,
  `fullName` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `contactNumber` varchar(50) DEFAULT NULL,
  `role` enum('client','staff','admin') DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  KEY `tenantID` (`tenantID`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`tenantID`) REFERENCES `owners` (`tenantID`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of users
-- ----------------------------
INSERT INTO `users` VALUES ('1', NULL, 'JM Mendoza', 'mendozajohmaverick1@gmail.com', 'chip', '$2y$10$rILGg8H8hIekJIwn4900Lu4EnuIPmHzyCCGugdvv.8gthVsXkvgHi', NULL, NULL, NULL, 'client');
INSERT INTO `users` VALUES ('2', NULL, 'Jeem Mendoza', 'johnlurjmendoza1@gmail.com', 'jeem', 'jeem123!', NULL, NULL, NULL, 'client');
INSERT INTO `users` VALUES ('3', NULL, 'JM Mendoza', 'ekalamosus224@gmail.com', NULL, '$2y$12$g5n2ylySgvnWmg3uXKJrK.6kHF4o5n.nk4L76.Q8ZhxfeC.AXhIyW', NULL, NULL, '9455823682', 'client');
INSERT INTO `users` VALUES ('4', NULL, 'Ella Payumo', 'maverickjmz15@gmail.com', NULL, '$2y$12$lC7aoy5xNTCCDpXq9kL73e7DxTL6OOkeau35poYSlYJIqfeVjlj7.', NULL, NULL, '09455826382', 'client');
INSERT INTO `users` VALUES ('5', NULL, 'JM Mendoza', 'johnmaverickmendoza1@gmail.com', NULL, '$2y$12$06Q88Edha8o0ZSEqRVKEF.QT.gBeZGoUuoV7zcMLk6FB2WVkXLLlW', NULL, NULL, '09455823682', 'client');

COMMIT;
