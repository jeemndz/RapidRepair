-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: rapidrepair
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `appointment`
--

DROP TABLE IF EXISTS `appointment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointment` (
  `appointment_id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `serviceType` varchar(100) DEFAULT NULL,
  `appointmentDate` date DEFAULT NULL,
  `appointmentTime` time DEFAULT NULL,
  `mechanicAssigned` enum('Mechanic 1','Mechanic 2','Mechanic 3') DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pending','Approved','Completed','Cancelled') DEFAULT 'Pending',
  `dateCreated` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`appointment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment`
--

LOCK TABLES `appointment` WRITE;
/*!40000 ALTER TABLE `appointment` DISABLE KEYS */;
INSERT INTO `appointment` VALUES (12,34,3,'Power Steering | Rack & Pinion','2026-01-30','11:41:00','Mechanic 3','','Completed','2026-01-30 11:41:39'),(13,34,4,'Underchassis Repair','2026-01-31','12:45:00','Mechanic 2','','Cancelled','2026-01-30 11:44:39'),(14,34,3,'PMS','2026-01-30','11:49:00','Mechanic 3','12312312','Completed','2026-01-30 11:50:00'),(15,34,11,'Underchassis Repair','2026-01-24','15:58:00','Mechanic 2','aweq','Completed','2026-01-30 12:55:26'),(16,34,3,'Power Steering | Rack & Pinion','2026-01-23','13:06:00','Mechanic 2','','Completed','2026-01-30 13:03:52'),(17,34,11,'Underchassis Repair','2026-01-31','07:31:00','Mechanic 1','wqeqwe','Completed','2026-01-30 13:30:39'),(18,34,3,'Transmission','2026-01-30','16:45:00','Mechanic 1','wewqeq','Completed','2026-01-30 16:45:55'),(19,33,1,'Preventive Maintenance Service (PMS)','2026-01-31','08:59:00','Mechanic 2','','Completed','2026-01-30 17:59:19'),(20,34,11,'Change Brake Oil','2026-02-27','12:35:00','Mechanic 2','wala po','','2026-01-30 22:35:08'),(21,34,5,'Change Brake Oil','2026-02-12','11:00:00','Mechanic 2','wala po kuya','Completed','2026-01-30 23:01:01'),(22,33,10,'Belt & Timing','2026-01-31','08:20:00','Mechanic 2','','Completed','2026-01-30 23:20:46'),(23,33,2,'Preventive Maintenance Service (PMS)','2026-02-25','15:30:00','Mechanic 2','','Completed','2026-01-30 23:27:33'),(24,33,2,'Belt & Timing','2026-02-04','16:00:00','Mechanic 1','NOTES','Completed','2026-01-31 02:14:32'),(25,33,1,'Power Steering | Rack & Pinion','2026-02-25','16:22:00','Mechanic 2','323123','Completed','2026-01-31 02:22:16'),(26,33,10,'Belt & Timing','2026-02-19','12:50:00','Mechanic 2','jvjhvhhjhv','Completed','2026-01-31 04:50:39'),(27,34,5,'Change Brake Oil','2026-02-19','08:56:00','Mechanic 3','','Completed','2026-01-31 04:53:39'),(28,38,13,'Belt & Timing','2026-01-31','09:33:00','Mechanic 1','wqeqw','Completed','2026-01-31 05:30:06');
/*!40000 ALTER TABLE `appointment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `client_information`
--

DROP TABLE IF EXISTS `client_information`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_information` (
  `client_id` int(11) NOT NULL AUTO_INCREMENT,
  `firstName` varchar(50) NOT NULL,
  `lastName` varchar(50) NOT NULL,
  `contactNumber` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(250) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `dateRegistered` datetime NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`client_id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_user_client` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `client_information`
--

LOCK TABLES `client_information` WRITE;
/*!40000 ALTER TABLE `client_information` DISABLE KEYS */;
INSERT INTO `client_information` VALUES (33,'Ella','Payumo','09356394055','ella@gmail.com','Lapnit, San Ildefonso, Bulacan','New User','2026-01-19 00:00:00',8),(34,'Jm','Mendoza','09356394055','mendozajohmaverick1@gmail.com','Ulingao, San Rafael, Bulacan','None','2026-01-19 00:00:00',9),(36,'Amiel','Carl Santos','0995 907 9137','amiel@gmail.com','VDF, Baliwag, Bulacan','Kupal','2026-01-21 00:00:00',11),(37,'Leala','Rivera','0934 234 2343','leala@gmail.com','Subic, Baliwag, Bulacan','Nagtitinda ng balot\r\n','2026-01-21 00:00:00',12),(38,'Eunice','Diestro','0943 453 3434 ','younice@gmail.com','San Miguel, Bulacan','Wala maliit','2026-01-21 00:00:00',13),(41,'Jiem','Mendoza',NULL,NULL,NULL,NULL,'2026-01-31 00:00:00',18);
/*!40000 ALTER TABLE `client_information` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `monthly_reports`
--

DROP TABLE IF EXISTS `monthly_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `monthly_reports` (
  `reportID` int(11) NOT NULL AUTO_INCREMENT,
  `reportMonth` varchar(15) DEFAULT NULL,
  `totalClients` int(11) DEFAULT NULL,
  `totalVehicleServiced` int(11) DEFAULT NULL,
  `totalAppointments` int(11) DEFAULT NULL,
  `totalRevenue` decimal(10,2) DEFAULT NULL,
  `totalServicedRendered` int(11) DEFAULT NULL,
  `mostAvailedService` varchar(100) DEFAULT NULL,
  `generatedBy` varchar(100) DEFAULT NULL,
  `dategenerated` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`reportID`),
  UNIQUE KEY `reportMonth` (`reportMonth`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `monthly_reports`
--

LOCK TABLES `monthly_reports` WRITE;
/*!40000 ALTER TABLE `monthly_reports` DISABLE KEYS */;
INSERT INTO `monthly_reports` VALUES (2,'January 2026',5,4,9,60678.00,8,'Underchassis Repair','Staff','2026-01-30 17:49:54','Auto-generated from live tables');
/*!40000 ALTER TABLE `monthly_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `paymentAmount` decimal(10,2) DEFAULT NULL,
  `amountPaid` decimal(10,2) DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT NULL,
  `paymentMethod` enum('Cash','GCash') DEFAULT NULL,
  `paymentDate` datetime DEFAULT NULL,
  `paymentStatus` enum('Paid','Unpaid','Partial') DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `referenceNumber` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`payment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (17,34,12,15,4786.00,4786.00,0.00,'Cash','2026-01-30 11:42:11','Paid','','RR-00015'),(18,34,13,16,7741.00,7741.00,0.00,'Cash','2026-01-30 11:47:20','Paid','','RR-00016'),(19,34,14,17,5000.00,5000.00,0.00,'GCash','2026-01-30 11:51:02','Paid','','RR-00017'),(20,34,15,18,5988.00,5988.00,0.00,'Cash','2026-01-30 12:58:11','Paid','','RR-00018'),(21,34,17,19,5230.00,5230.00,0.00,'Cash','2026-01-30 14:03:20','Paid','','RR-00019'),(22,34,16,20,5633.00,5633.00,0.00,'Cash','2026-01-30 14:03:27','Paid','','RR-00020'),(23,34,18,21,4500.00,4500.00,0.00,'GCash','2026-01-30 19:06:15','Paid','fully paid','RR-00021'),(24,33,19,22,3000.00,3000.00,0.00,'GCash','2026-01-30 22:36:17','Paid','Paid Gcash','RR-00022'),(25,34,20,23,5000.00,5000.00,0.00,'Cash','2026-01-30 22:37:24','Paid','','RR-00023'),(26,34,21,24,2800.00,2800.00,0.00,'Cash','2026-01-30 23:01:49','Paid','','RR-00024'),(27,33,22,25,5000.00,5000.00,0.00,'GCash','2026-01-30 23:21:34','Paid','done','RR-00025'),(28,33,23,26,6000.00,6000.00,0.00,'GCash','2026-01-30 23:28:26','Paid','','RR-00026'),(29,33,24,27,5000.00,5000.00,0.00,'Cash','2026-01-31 02:15:37','Paid','','RR-00027'),(30,33,25,28,2600.00,2600.00,0.00,'Cash','2026-01-31 02:24:23','Paid','','RR-00028'),(31,33,26,29,5444.00,5444.00,0.00,'Cash','2026-01-31 04:52:58','Paid','','RR-00029'),(33,38,28,31,5000.00,5000.00,0.00,'GCash','2026-01-31 05:31:21','Paid','','RR-00031');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_types`
--

DROP TABLE IF EXISTS `service_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_types` (
  `service_id` int(11) NOT NULL AUTO_INCREMENT,
  `service_name` varchar(150) NOT NULL,
  `labor_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`service_id`),
  UNIQUE KEY `service_name` (`service_name`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_types`
--

LOCK TABLES `service_types` WRITE;
/*!40000 ALTER TABLE `service_types` DISABLE KEYS */;
INSERT INTO `service_types` VALUES (1,'Preventive Maintenance Service (PMS)',3000.00,1,'2026-01-30 16:41:37'),(2,'Underchassis Repair',6000.00,1,'2026-01-30 16:41:51'),(5,'Differential',2000.00,1,'2026-01-30 16:42:20'),(6,'Axle Bearings',2000.00,1,'2026-01-30 16:42:30'),(7,'Power Steering | Rack & Pinion',2000.00,1,'2026-01-30 16:43:06'),(8,'Shock Absorber',3500.00,1,'2026-01-30 17:42:06'),(9,'Change Oil',2000.00,1,'2026-01-30 17:42:17'),(10,'Change Brake Oil',2000.00,1,'2026-01-30 17:42:27'),(11,'Transmission',2500.00,1,'2026-01-30 17:42:35'),(12,'Belt & Timing',2000.00,1,'2026-01-30 17:42:44'),(13,'Suspension Repair',4000.00,1,'2026-01-30 17:42:50');
/*!40000 ALTER TABLE `service_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services` (
  `service_id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `serviceCategory` varchar(150) NOT NULL,
  `serviceDescription` text DEFAULT NULL,
  `partsUsed` text DEFAULT NULL,
  `laborFee` decimal(10,2) DEFAULT 0.00,
  `partsCost` decimal(10,2) DEFAULT 0.00,
  `totalCost` decimal(10,2) DEFAULT 0.00,
  `serviceDate` date NOT NULL,
  `mechanicAssigned` enum('Mechanic 1','Mechanic 2','Mechanic 3') NOT NULL,
  `status` enum('Ready for payment','Paid') DEFAULT 'Ready for payment',
  PRIMARY KEY (`service_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES (15,34,12,3,'Power Steering | Rack & Pinion','q2e12','e2qe1',2000.00,2786.00,4786.00,'2026-01-30','','Paid'),(16,34,13,4,'Underchassis Repair','dwqdq','dqwdqw',5654.00,2087.00,7741.00,'2026-01-31','','Paid'),(17,34,14,3,'PMS','werwerwe','fdgsdgewtr',3000.00,2000.00,5000.00,'2026-01-30','','Paid'),(18,34,15,11,'Underchassis Repair','qsdasd','dsad',2555.00,3433.00,5988.00,'2026-01-24','Mechanic 2','Paid'),(19,34,17,11,'Differential','sadasd','dasd',2986.00,2244.00,5230.00,'2026-01-31','Mechanic 1','Paid'),(20,34,16,3,'Power Steering | Rack & Pinion','234sddfds','erwr',3200.00,2433.00,5633.00,'2026-01-23','Mechanic 2','Paid'),(21,34,18,3,'Transmission','saewqqw','Wala naman',2500.00,2000.00,4500.00,'2026-01-30','Mechanic 1','Paid'),(22,33,19,1,'Preventive Maintenance Service (PMS)','','',3000.00,0.00,3000.00,'2026-01-31','Mechanic 2','Paid'),(23,34,20,11,'Change Brake Oil','ewqeqw','sdwgfreqw',2000.00,3000.00,5000.00,'2026-02-27','Mechanic 2','Paid'),(24,34,21,5,'Change Brake Oil','wala naman','New break Oil',2000.00,800.00,2800.00,'2026-02-12','Mechanic 2','Paid'),(25,33,22,10,'Belt & Timing','Belt and Timing done','new belt',2000.00,3000.00,5000.00,'2026-01-31','Mechanic 2','Paid'),(26,33,23,2,'Preventive Maintenance Service (PMS)','qewqe','safwerew',3000.00,3000.00,6000.00,'2026-02-25','Mechanic 2','Paid'),(27,33,24,2,'Belt & Timing','service','NEW BELT',2000.00,3000.00,5000.00,'2026-02-04','Mechanic 1','Paid'),(28,33,25,1,'Power Steering | Rack & Pinion','2312','3213123',2000.00,600.00,2600.00,'2026-02-25','Mechanic 2','Paid'),(29,33,26,10,'Belt & Timing','sadsa','dqwewq',2000.00,3444.00,5444.00,'2026-02-19','Mechanic 2','Paid'),(30,34,27,5,'Change Brake Oil','423523','342523',2000.00,45345453.00,45347453.00,'2026-02-19','Mechanic 3','Paid'),(31,38,28,13,'Belt & Timing','dasdas','sdas',2000.00,3000.00,5000.00,'2026-01-31','Mechanic 1','Paid');
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `fullName` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `role` enum('client','staff','admin') NOT NULL DEFAULT 'client',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (8,'Ella Payumo','ella@gmail.com','elli','$2y$10$8jTFMiflCncZxSTjjOQoS.FF0c49Q..XDBY.vbvf4TqoDgZsRaLUW',NULL,NULL,'client'),(9,'Jm Mendoza','mendozajohmaverick1@gmail.com','jeem','$2y$10$y.hoUc13COcZYvB.k/DZ4.M4DNSBVn2gys79QqRmU.1.cgG8i9vOe','aee37ce6c99ea6a39cff50d06fec8a39726caa440437a1fc687de4e5c8693caa','2026-01-20 07:40:03','client'),(10,'Chipipoy Alkaboom','chipipoy123@gmail.com','chip','$2y$10$vuF62TR8FcKE44mZ8Ktkye2g3/QwzLkO6GgoipJynEA4y4/bYI.IO',NULL,NULL,'client'),(11,'Amiel Carl Santos','amiel@gmail.com','Amiel','$2y$10$SXZ2dQCRq2ruMD60JIoA1.nJBm5BPH4N.zZAwQ.6A/yPdBntTHzxS',NULL,NULL,'client'),(12,'Leala Rivera','leala@gmail.com','leala','$2y$10$MP97edXUFTjCNfqXSjfOX.h3FLduWBNrB1XjjJU1gcilvHeH0fABK',NULL,NULL,'client'),(13,'Eunice Diestro','younice@gmail.com','younice','$2y$10$TBaApJZN9AQfBUATPoQiLutRQf91n62XsVacXWrzhLb/28ZWT/k9a',NULL,NULL,'client'),(15,'Staff','staffrepair@gmail.com','staff','staff123',NULL,NULL,'staff'),(16,'Admin','rapidrepair@gmai.com','admin','admin123',NULL,NULL,'admin'),(17,'Carl Micko','carlmicko1@gmail.com','micko','$2y$10$SlXmUY8TJq8XmUAmk1flouOq/3QYfpJl5/0CIM5AyGcKQkPgiZk6W',NULL,NULL,'client'),(18,'Jiem Mendoza','johnlurjmendoza1@gmail.com','jiem','$2y$10$rgkIkrF4cmJ16HR0Gwr0/eir92.dHg/wk4v43etn56s9Ackr1gwCS',NULL,NULL,'client');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicleinfo`
--

DROP TABLE IF EXISTS `vehicleinfo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicleinfo` (
  `vehicle_id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `plateNumber` varchar(20) NOT NULL,
  `vehicleBrand` varchar(50) DEFAULT NULL,
  `vehicleModel` varchar(50) DEFAULT NULL,
  `vehicleYear` year(4) DEFAULT NULL,
  `engineNumber` varchar(50) DEFAULT NULL,
  `fuelType` enum('Gasoline','Diesel','Electric','Hybrid') DEFAULT NULL,
  `color` varchar(30) DEFAULT NULL,
  `mileage` int(11) DEFAULT NULL,
  `dateAdded` datetime DEFAULT current_timestamp(),
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `transmissiontype` enum('Manual','Automatic','Hybrid','IMT','CVT','DCT') DEFAULT NULL,
  PRIMARY KEY (`vehicle_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicleinfo`
--

LOCK TABLES `vehicleinfo` WRITE;
/*!40000 ALTER TABLE `vehicleinfo` DISABLE KEYS */;
INSERT INTO `vehicleinfo` VALUES (1,33,'09DXSX','Toyota','2324324',2023,'09234723','Gasoline','Red',20000,'2026-01-19 15:52:08','Active','IMT'),(2,33,'23XSD3','Nissan','Sentra',1993,'2346574','Gasoline','Blue',15000,'2026-01-19 16:09:31','Active','Manual'),(5,34,'85S3CD','Nissan','Sentra',2025,'45365443','Gasoline','Green',43566,'2026-01-20 22:58:58','Active','Automatic'),(8,36,'NOG957','Toyota','Avanza 9',2009,'09247232','Gasoline','Blue-Gray',50000,'2026-01-21 08:19:26','Active','Manual'),(10,33,'23DAS21','Nissan','Terra',2023,'3443532','Gasoline','Red',9273,'2026-01-23 22:51:45','Active','Manual'),(11,34,'21SDF34','Chery','Tiggo 2',2023,'434234','Diesel','White',1000000,'2026-01-30 12:19:30','Active','Hybrid'),(13,38,'ABS 3242','Mazda','Mazda6',2025,'12423426334','Electric','Blue',8832,'2026-01-31 00:29:22','Active','Automatic'),(14,34,'DJS 3453','Honda','Civic',2024,'3424323423','Gasoline','White',500,'2026-01-31 02:29:25','Active','Automatic');
/*!40000 ALTER TABLE `vehicleinfo` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-31  5:32:14
