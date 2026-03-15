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
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment`
--

LOCK TABLES `appointment` WRITE;
/*!40000 ALTER TABLE `appointment` DISABLE KEYS */;
INSERT INTO `appointment` VALUES (12,34,3,'Power Steering | Rack & Pinion','2026-01-30','11:41:00','Mechanic 3','','Completed','2026-01-30 11:41:39'),(13,34,4,'Underchassis Repair','2026-01-31','12:45:00','Mechanic 2','','Cancelled','2026-01-30 11:44:39'),(14,34,3,'PMS','2026-01-30','11:49:00','Mechanic 3','12312312','Completed','2026-01-30 11:50:00'),(15,34,11,'Underchassis Repair','2026-01-24','15:58:00','Mechanic 2','aweq','Completed','2026-01-30 12:55:26'),(16,34,3,'Power Steering | Rack & Pinion','2026-01-23','13:06:00','Mechanic 2','','Completed','2026-01-30 13:03:52'),(17,34,11,'Underchassis Repair','2026-01-31','07:31:00','Mechanic 1','wqeqwe','Completed','2026-01-30 13:30:39'),(18,34,3,'Transmission','2026-01-30','16:45:00','Mechanic 1','wewqeq','Completed','2026-01-30 16:45:55'),(19,33,1,'Preventive Maintenance Service (PMS)','2026-01-31','08:59:00','Mechanic 2','','Completed','2026-01-30 17:59:19'),(20,34,11,'Change Brake Oil','2026-02-27','12:35:00','Mechanic 2','wala po','Completed','2026-01-30 22:35:08'),(21,34,5,'Change Brake Oil','2026-02-12','11:00:00','Mechanic 2','wala po kuya','Completed','2026-01-30 23:01:01'),(22,33,10,'Belt & Timing','2026-01-31','08:20:00','Mechanic 2','','Completed','2026-01-30 23:20:46'),(23,33,2,'Preventive Maintenance Service (PMS)','2026-02-25','15:30:00','Mechanic 2','','Completed','2026-01-30 23:27:33'),(24,33,2,'Belt & Timing','2026-02-04','16:00:00','Mechanic 1','NOTES','Completed','2026-01-31 02:14:32'),(25,33,1,'Power Steering | Rack & Pinion','2026-02-25','16:22:00','Mechanic 2','323123','Completed','2026-01-31 02:22:16'),(26,33,10,'Belt & Timing','2026-02-19','12:50:00','Mechanic 2','jvjhvhhjhv','Completed','2026-01-31 04:50:39'),(27,34,5,'Change Brake Oil','2026-02-19','08:56:00','Mechanic 3','','Completed','2026-01-31 04:53:39'),(28,38,13,'Belt & Timing','2026-01-31','09:33:00','Mechanic 1','wqeqw','Completed','2026-01-31 05:30:06'),(29,41,15,'Suspension Repair','2026-01-31','11:00:00','Mechanic 1','qweqw','Completed','2026-01-31 08:18:44'),(30,38,13,'Axle Bearings','2026-02-28','11:31:00','Mechanic 1','','Completed','2026-02-01 20:03:28'),(32,34,5,'Belt & Timing','2026-02-11','10:28:00','Mechanic 1','qweqweq','Cancelled','2026-02-01 20:28:47'),(34,34,5,'Preventive Maintenance Service (PMS)','2026-02-21','10:32:00','Mechanic 1','','Completed','2026-02-01 20:32:10'),(35,34,11,'Belt & Timing','2026-02-22','11:44:00','Mechanic 2','wqeqweqw','Completed','2026-02-01 20:44:07'),(36,38,13,'Belt & Timing','2026-02-02','13:14:00','Mechanic 1','','Completed','2026-02-01 22:15:01'),(37,34,11,'Belt & Timing','2026-02-19','16:03:00','Mechanic 1','asdeqwe','Completed','2026-02-01 23:03:13'),(38,34,11,'Belt & Timing','2026-02-24','14:53:00','Mechanic 3','2342342','Completed','2026-02-01 23:53:07'),(39,38,13,'Change Brake Oil','2026-02-24','17:06:00','Mechanic 1','','Completed','2026-02-02 02:06:10'),(40,34,11,'Belt & Timing','2026-02-20','16:36:00','Mechanic 2','wqweqwe','Completed','2026-02-02 02:36:46'),(41,34,5,'Change Brake Oil','2026-02-10','15:38:00','Mechanic 1','','Completed','2026-02-02 02:38:25'),(42,36,8,'Belt & Timing','2026-02-25','16:48:00','Mechanic 3','','Completed','2026-02-02 02:48:38'),(43,36,8,'Power Steering | Rack & Pinion','2026-02-19','16:51:00','Mechanic 1','','Completed','2026-02-02 02:51:43'),(44,38,13,'Change Brake Oil','2026-02-25','17:00:00','Mechanic 2','','Completed','2026-02-02 03:00:54'),(45,38,13,'Belt & Timing','2026-02-09','17:04:00','Mechanic 2','','Cancelled','2026-02-02 03:04:49'),(46,34,14,'Differential','2026-02-24','19:25:00','Mechanic 3','','Completed','2026-02-02 03:25:06'),(47,34,5,'Belt & Timing','2026-02-17','18:28:00','Mechanic 2','','Approved','2026-02-02 03:28:43'),(48,36,8,'Shock Absorber','2026-02-19','17:31:00','Mechanic 1','','Completed','2026-02-02 03:29:20');
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
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `client_information`
--

LOCK TABLES `client_information` WRITE;
/*!40000 ALTER TABLE `client_information` DISABLE KEYS */;
INSERT INTO `client_information` VALUES (33,'Ella','Payumo','09356394055','ella@gmail.com','Lapnit, San Ildefonso, Bulacan','New User','2026-01-19 00:00:00',8),(34,'Jm','Mendoza','09356394052','mendozajohmaverick1@gmail.com','Ulingao, San Rafael, Bulacan','None','2026-01-19 00:00:00',9),(36,'Amiel','Carl Santos','0995 907 9137','amiel@gmail.com','VDF, Baliwag, Bulacan','Kupal','2026-01-21 00:00:00',11),(38,'Eunice','Diestro','0943 453 3434 ','younice@gmail.com','San Miguel, Bulacan','Wala maliit','2026-01-21 00:00:00',13),(41,'Jiem','Mendoza','0932 324 2343','jiemmendoza@gmail.com','San Rafael Bulacan','Wala lang','2026-01-31 00:00:00',18),(44,'Airwin','Del Rosario','09234232334','airwin@gmail.com','San Luis, Pampanga','dqwdqwe','2026-02-02 00:00:00',20);
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `monthly_reports`
--

LOCK TABLES `monthly_reports` WRITE;
/*!40000 ALTER TABLE `monthly_reports` DISABLE KEYS */;
INSERT INTO `monthly_reports` VALUES (2,'January 2026',5,4,9,60678.00,8,'Underchassis Repair','Staff','2026-01-30 17:49:54','Auto-generated from live tables'),(3,'February 2026',0,6,13,14600.00,8,'Belt & Timing','Staff','2026-02-01 16:28:46','Auto-generated from live tables');
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
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (17,34,12,15,4786.00,4786.00,0.00,'Cash','2026-01-30 11:42:11','Paid','','RR-00015'),(18,34,13,16,7741.00,7741.00,0.00,'Cash','2026-01-30 11:47:20','Paid','','RR-00016'),(19,34,14,17,5000.00,5000.00,0.00,'GCash','2026-01-30 11:51:02','Paid','','RR-00017'),(20,34,15,18,5988.00,5988.00,0.00,'Cash','2026-01-30 12:58:11','Paid','','RR-00018'),(21,34,17,19,5230.00,5230.00,0.00,'Cash','2026-01-30 14:03:20','Paid','','RR-00019'),(22,34,16,20,5633.00,5633.00,0.00,'Cash','2026-01-30 14:03:27','Paid','','RR-00020'),(23,34,18,21,4500.00,4500.00,0.00,'GCash','2026-01-30 19:06:15','Paid','fully paid','RR-00021'),(24,33,19,22,3000.00,3000.00,0.00,'GCash','2026-01-30 22:36:17','Paid','Paid Gcash','RR-00022'),(25,34,20,23,5000.00,5000.00,0.00,'Cash','2026-01-30 22:37:24','Paid','','RR-00023'),(26,34,21,24,2800.00,2800.00,0.00,'Cash','2026-01-30 23:01:49','Paid','','RR-00024'),(27,33,22,25,5000.00,5000.00,0.00,'GCash','2026-01-30 23:21:34','Paid','done','RR-00025'),(28,33,23,26,6000.00,6000.00,0.00,'GCash','2026-01-30 23:28:26','Paid','','RR-00026'),(29,33,24,27,5000.00,5000.00,0.00,'Cash','2026-01-31 02:15:37','Paid','','RR-00027'),(30,33,25,28,2600.00,2600.00,0.00,'Cash','2026-01-31 02:24:23','Paid','','RR-00028'),(31,33,26,29,5444.00,5444.00,0.00,'Cash','2026-01-31 04:52:58','Paid','','RR-00029'),(33,38,28,31,5000.00,5000.00,0.00,'GCash','2026-01-31 05:31:21','Paid','','RR-00031'),(34,34,27,32,2600.00,2600.00,0.00,'Cash','2026-01-31 08:13:16','Paid','','RR-00032'),(35,41,29,33,4600.00,4600.00,0.00,'GCash','2026-02-01 19:06:35','Paid','2323212','RR-00033'),(36,38,30,34,10000.00,10000.00,0.00,'Cash','2026-02-01 20:21:08','Paid','','RR-00034'),(37,38,36,35,12000.00,12000.00,0.00,'Cash','2026-02-02 00:04:11','Paid','','RR-00035'),(38,34,37,36,5000.00,5000.00,0.00,'Cash','2026-02-02 00:04:33','Paid','','RR-00036'),(39,34,38,38,5000.00,5000.00,0.00,'Cash','2026-02-02 02:24:25','Paid','qweqw','RR-00038'),(40,38,39,37,2300.00,2300.00,0.00,'Cash','2026-02-02 02:29:24','Paid','','RR-00037'),(41,34,35,39,2000.00,2000.00,0.00,'Cash','2026-02-02 02:30:03','Paid','','RR-00039'),(42,34,41,41,2500.00,2500.00,0.00,'Cash','2026-02-02 02:47:04','Paid','','RR-00041'),(43,34,34,40,5000.00,5000.00,0.00,'Cash','2026-02-02 02:47:39','Paid','','RR-00040'),(44,36,43,44,7000.00,7000.00,0.00,'Cash','2026-02-02 02:52:32','Paid','','RR-00044'),(45,36,42,43,2500.00,2500.00,0.00,'Cash','2026-02-02 02:54:35','Paid','','RR-00043'),(46,38,44,45,2500.00,2500.00,0.00,'Cash','2026-02-02 03:04:09','Paid','','RR-00045'),(47,34,46,47,7000.00,7000.00,0.00,'Cash','2026-02-02 03:33:05','Paid','','RR-00047');
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
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES (15,34,12,3,'Power Steering | Rack & Pinion','q2e12','e2qe1',2000.00,2786.00,4786.00,'2026-01-30','','Paid'),(16,34,13,4,'Underchassis Repair','dwqdq','dqwdqw',5654.00,2087.00,7741.00,'2026-01-31','','Paid'),(17,34,14,3,'PMS','werwerwe','fdgsdgewtr',3000.00,2000.00,5000.00,'2026-01-30','','Paid'),(18,34,15,11,'Underchassis Repair','qsdasd','dsad',2555.00,3433.00,5988.00,'2026-01-24','Mechanic 2','Paid'),(19,34,17,11,'Differential','sadasd','dasd',2986.00,2244.00,5230.00,'2026-01-31','Mechanic 1','Paid'),(20,34,16,3,'Power Steering | Rack & Pinion','234sddfds','erwr',3200.00,2433.00,5633.00,'2026-01-23','Mechanic 2','Paid'),(21,34,18,3,'Transmission','saewqqw','Wala naman',2500.00,2000.00,4500.00,'2026-01-30','Mechanic 1','Paid'),(22,33,19,1,'Preventive Maintenance Service (PMS)','','',3000.00,0.00,3000.00,'2026-01-31','Mechanic 2','Paid'),(23,34,20,11,'Change Brake Oil','ewqeqw','sdwgfreqw',2000.00,3000.00,5000.00,'2026-02-27','Mechanic 2','Paid'),(24,34,21,5,'Change Brake Oil','wala naman','New break Oil',2000.00,800.00,2800.00,'2026-02-12','Mechanic 2','Paid'),(25,33,22,10,'Belt & Timing','Belt and Timing done','new belt',2000.00,3000.00,5000.00,'2026-01-31','Mechanic 2','Paid'),(26,33,23,2,'Preventive Maintenance Service (PMS)','qewqe','safwerew',3000.00,3000.00,6000.00,'2026-02-25','Mechanic 2','Paid'),(27,33,24,2,'Belt & Timing','service','NEW BELT',2000.00,3000.00,5000.00,'2026-02-04','Mechanic 1','Paid'),(28,33,25,1,'Power Steering | Rack & Pinion','2312','3213123',2000.00,600.00,2600.00,'2026-02-25','Mechanic 2','Paid'),(29,33,26,10,'Belt & Timing','sadsa','dqwewq',2000.00,3444.00,5444.00,'2026-02-19','Mechanic 2','Paid'),(31,38,28,13,'Belt & Timing','dasdas','sdas',2000.00,3000.00,5000.00,'2026-01-31','Mechanic 1','Paid'),(32,34,27,5,'Change Brake Oil','WALA po','Brake Oil',2000.00,600.00,2600.00,'2026-02-19','Mechanic 3','Paid'),(33,41,29,15,'Suspension Repair','wala naman po','New Bolts',4000.00,600.00,4600.00,'2026-01-31','Mechanic 1','Paid'),(34,38,30,13,'Axle Bearings','sdqwd','weqw',2000.00,8000.00,10000.00,'2026-02-28','Mechanic 1','Paid'),(35,38,36,13,'Belt & Timing','qweqweq','wqeqw',2000.00,10000.00,12000.00,'2026-02-02','Mechanic 1','Paid'),(36,34,37,11,'Belt & Timing','3242','423432',2000.00,3000.00,5000.00,'2026-02-19','Mechanic 1','Paid'),(37,38,39,13,'Change Brake Oil','2sfasdfa','dsasdfsda',2000.00,300.00,2300.00,'2026-02-24','Mechanic 1','Paid'),(38,34,38,11,'Belt & Timing','eqweqw','ewqeq',2000.00,3000.00,5000.00,'2026-02-24','Mechanic 3','Paid'),(39,34,35,11,'Belt & Timing','wqeqw','wqeq',2000.00,0.00,2000.00,'2026-02-22','Mechanic 2','Paid'),(40,34,34,5,'Preventive Maintenance Service (PMS)','wqeqwe','wqeqwe',3000.00,2000.00,5000.00,'2026-02-21','Mechanic 1','Paid'),(41,34,41,5,'Change Brake Oil','','21312',2000.00,500.00,2500.00,'2026-02-10','Mechanic 1','Paid'),(42,34,40,11,'Belt & Timing','ewqe','ewqeq',2000.00,50.00,2050.00,'2026-02-20','Mechanic 2','Ready for payment'),(43,36,42,8,'Belt & Timing','werwer','2werwer',2000.00,500.00,2500.00,'2026-02-25','Mechanic 3','Paid'),(44,36,43,8,'Power Steering | Rack & Pinion','323','232',2000.00,5000.00,7000.00,'2026-02-19','Mechanic 1','Paid'),(45,38,44,13,'Change Brake Oil','','',2000.00,500.00,2500.00,'2026-02-25','Mechanic 2','Paid'),(46,38,45,13,'Belt & Timing','sdfsdf','23123',2000.00,600.00,2600.00,'2026-02-09','Mechanic 2','Ready for payment'),(47,34,46,14,'Differential','erwe','ewfwe',2000.00,5000.00,7000.00,'2026-02-24','Mechanic 3','Paid');
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `user_role` varchar(50) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_action` (`action`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_logs`
--

LOCK TABLES `system_logs` WRITE;
/*!40000 ALTER TABLE `system_logs` DISABLE KEYS */;
INSERT INTO `system_logs` VALUES (1,16,'Admin','admin','Update Booking Status','appointment',NULL,'Status set to: ','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:06:20'),(2,16,'Admin','admin','Update Booking Status','appointment',39,'Status updated to \'Completed\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:08:12'),(3,16,'Admin','admin','Record Payment','payments',39,'Payment recorded for service #38 | ₱5,000.00 via Cash','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:24:25'),(4,16,'Admin','admin','Update Service Status','services',38,'Service marked as Paid','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:24:25'),(5,16,'Admin','admin','Update Appointment Status','appointment',38,'Appointment marked as Completed after payment','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:24:25'),(6,16,'Admin','admin','Record Payment','payments',40,'Payment recorded for service #37 | ₱2,300.00 via Cash','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:29:24'),(7,16,'Admin','admin','Update Service Status','services',37,'Service marked as Paid','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:29:24'),(8,16,'Admin','admin','Update Appointment Status','appointment',39,'Appointment marked as Completed after payment','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:29:24'),(9,15,'Staff','staff','Record Payment','payments',41,'Payment recorded for service #39 | ₱2,000.00 via Cash','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:30:03'),(10,15,'Staff','staff','Update Service Status','services',39,'Service marked as Paid','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:30:03'),(11,15,'Staff','staff','Update Appointment Status','appointment',35,'Appointment marked as Completed after payment','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:30:03'),(12,16,'Admin','admin','Update Vehicle','vehicleinfo',13,'Vehicle #13 updated. Client #38 | Fuel: Electric → Gasoline','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:34:24'),(13,16,'Admin','admin','Update Vehicle','vehicleinfo',13,'Vehicle #13 updated. Client #38 | Trans: Manual → Automatic','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:36:04'),(14,16,'Admin','admin','Update Booking Status','appointment',41,'Status updated to \'Approved\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:38:45'),(15,16,'Admin','admin','Update Booking Status','appointment',41,'Status updated to \'Completed\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:38:49'),(16,16,'Admin','admin','Update Booking Status','appointment',40,'Status updated to \'Completed\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:40:48'),(17,16,'Admin','admin','Update Booking Status','appointment',41,'Status updated to \'Approved\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:44:43'),(18,16,'Admin','admin','Update Booking Status','appointment',41,'Status updated to \'Completed\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:44:47'),(19,16,'Admin','admin','Update Booking Status','appointment',40,'Status updated to \'Approved\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:45:28'),(20,16,'Admin','admin','Update Booking Status','appointment',40,'Status updated to \'Completed\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:45:31'),(21,16,'Admin','admin','Update Booking Status','appointment',34,'Status updated to \'Completed\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:46:21'),(22,16,'Admin','admin','Record Payment','payments',42,'Payment recorded for service #41 | ₱2,500.00 via Cash','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:47:04'),(23,16,'Admin','admin','Update Service Status','services',41,'Service marked as Paid','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:47:04'),(24,16,'Admin','admin','Update Appointment Status','appointment',41,'Appointment marked as Completed after payment','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:47:04'),(25,16,'Admin','admin','Record Payment','payments',43,'Payment recorded for service #40 | ₱5,000.00 via Cash','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:47:39'),(26,16,'Admin','admin','Update Service Status','services',40,'Service marked as Paid','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:47:39'),(27,16,'Admin','admin','Update Appointment Status','appointment',34,'Appointment marked as Completed after payment','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:47:39'),(28,15,'Staff','staff','Update Booking Status','appointment',42,'Status updated to \'Approved\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:48:42'),(29,15,'Staff','staff','Update Booking Status','appointment',42,'Status updated to \'Completed\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:48:45'),(30,16,'Admin','admin','Update Booking Status','appointment',43,'Status updated to \'Approved\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:51:47'),(31,16,'Admin','admin','Update Booking Status','appointment',43,'Status updated to \'Completed\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:51:52'),(32,16,'Admin','admin','Record Payment','payments',44,'Payment recorded for service #44 | ₱7,000.00 via Cash','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:52:32'),(33,16,'Admin','admin','Update Service Status','services',44,'Service marked as Paid','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:52:32'),(34,16,'Admin','admin','Update Appointment Status','appointment',43,'Appointment marked as Completed after payment','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:52:32'),(35,16,'Admin','admin','Record Payment','payments',45,'Payment recorded for service #43 | ₱2,500.00 via Cash','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:54:35'),(36,16,'Admin','admin','Update Service Status','services',43,'Service marked as Paid','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:54:35'),(37,16,'Admin','admin','Update Appointment Status','appointment',42,'Appointment marked as Completed after payment','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 18:54:35'),(38,16,'Admin','admin','Update Booking Status','appointment',44,'Status updated to \'Approved\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:01:20'),(39,16,'Admin','admin','Update Booking Status','appointment',44,'Status updated to \'Completed\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:01:24'),(40,16,'Admin','admin','Create Invoice','services',45,'Invoice created for appointment #44 | client #38 | vehicle #13 | category: Change Brake Oil | total: ₱2,500.00','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:01:43'),(41,16,'Admin','admin','Update Appointment Status','appointment',44,'Status set to \'Invoiced\' after invoice creation','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:01:43'),(42,15,'Staff','staff','Record Payment','payments',46,'Payment recorded for service #45 | ₱2,500.00 via Cash','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:04:09'),(43,15,'Staff','staff','Update Service Status','services',45,'Service marked as Paid','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:04:09'),(44,15,'Staff','staff','Update Appointment Status','appointment',44,'Appointment marked as Completed after payment','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:04:09'),(45,15,'Staff','staff','Update Booking Status','appointment',45,'Status updated to \'Approved\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:05:18'),(46,15,'Staff','staff','Update Booking Status','appointment',45,'Status updated to \'Completed\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:05:24'),(47,15,'Staff','staff','Update Vehicle','vehicleinfo',15,'Vehicle #15 updated. Client #41 | Trans: Automatic → Manual','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:06:00'),(48,15,'Staff','staff','Create Invoice','services',46,'Invoice created for appointment #45 | client #38 | vehicle #13 | category: Belt & Timing | total: ₱2,600.00','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:06:48'),(49,15,'Staff','staff','Update Appointment Status','appointment',45,'Status set to \'Invoiced\' after invoice creation','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:06:48'),(50,9,'Jm Mendoza','client','View Profile','client_profile',NULL,'Client opened profile page','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:11:12'),(51,15,'Staff','staff','Update Vehicle','vehicleinfo',13,'Vehicle #13 updated. Client #38 | Fuel: Gasoline → Diesel','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:13:43'),(52,9,'Jm Mendoza','client','Register Vehicle','vehicleinfo',16,'Client registered vehicle: DFD 3243 (Subaru WRX)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:15:34'),(53,9,'Jm Mendoza','client','Create Booking','appointment',47,'Client requested booking: Vehicle 85S3CD | Service Belt & Timing | 2026-02-17 18:28 | Mechanic Mechanic 2 | Notes: —','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:28:43'),(54,16,'Admin','admin','Update Booking Status','appointment',48,'Status updated to \'Approved\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:29:36'),(55,16,'Admin','admin','Update Booking Status','appointment',48,'Status updated to \'Completed\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:31:38'),(56,16,'Admin','admin','Update Booking Status','appointment',46,'Status updated to \'Approved\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:31:54'),(57,16,'Admin','admin','Update Booking Status','appointment',47,'Status updated to \'Approved\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:32:10'),(58,16,'Admin','admin','Update Booking Status','appointment',46,'Status updated to \'Completed\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:32:14'),(59,16,'Admin','admin','Update Booking Status','appointment',45,'Status updated to \'Cancelled\'','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:32:38'),(60,16,'Admin','admin','Record Payment','payments',47,'Payment recorded for service #47 | ₱7,000.00 via Cash','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:33:05'),(61,16,'Admin','admin','Update Service Status','services',47,'Service marked as Paid','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:33:05'),(62,16,'Admin','admin','Update Appointment Status','appointment',46,'Appointment marked as Completed after payment','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:33:05'),(63,9,'Jm Mendoza','client','View Profile','client_profile',NULL,'Client opened profile page','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:33:36'),(64,20,'Airwin Del Rosario','client','View Profile','client_profile',NULL,'Client opened profile page','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:35:27'),(65,20,'Airwin Del Rosario','client','Update Profile','client_information',44,'Client updated profile details (contact/email/address/notes)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:36:07'),(66,20,'Airwin Del Rosario','client','Update Profile','client_information',44,'Client updated profile details (contact/email/address/notes)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:36:31'),(67,20,'Airwin Del Rosario','client','Register Vehicle','vehicleinfo',17,'Client registered vehicle: GFE 3568 (Geely Coolray)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:37:02'),(68,16,'Admin','admin','Update Vehicle','vehicleinfo',15,'Vehicle #15 updated. Client #41 | Fuel: Gasoline → Diesel','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:38:55'),(69,16,'Admin','admin','Delete Vehicle','vehicleinfo',15,'Vehicle deleted by : Plate OSD 3424 | Nissan Sentra | Client ID 41','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:41:07'),(70,16,'Admin','admin','Delete Vehicle','vehicleinfo',2,'Vehicle deleted by role=admin | ID=2 | Plate=23XSD3 | Vehicle=Nissan Sentra | ClientID=33','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:43:12'),(72,18,'Jiem Mendoza','client','View Profile','client_profile',NULL,'Client opened profile page','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:55:41'),(73,18,'Jiem Mendoza','client','Register Vehicle','vehicleinfo',18,'Client registered vehicle: GGF 4323 (Hyundai Accent)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-01 19:56:45');
/*!40000 ALTER TABLE `system_logs` ENABLE KEYS */;
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
  `last_login` datetime DEFAULT NULL,
  `dateRegistered` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (8,'Ella Payumo','ella@gmail.com','elli','$2y$10$8jTFMiflCncZxSTjjOQoS.FF0c49Q..XDBY.vbvf4TqoDgZsRaLUW',NULL,NULL,'client',NULL,'2026-02-02 00:38:18'),(9,'Jm Mendoza','mendozajohmaverick1@gmail.com','jeem','$2y$10$y.hoUc13COcZYvB.k/DZ4.M4DNSBVn2gys79QqRmU.1.cgG8i9vOe','aee37ce6c99ea6a39cff50d06fec8a39726caa440437a1fc687de4e5c8693caa','2026-01-20 07:40:03','client','2026-02-01 21:33:31','2026-02-02 00:38:18'),(10,'Chipipoy Alkaboom','chipipoy123@gmail.com','chip','$2y$10$vuF62TR8FcKE44mZ8Ktkye2g3/QwzLkO6GgoipJynEA4y4/bYI.IO',NULL,NULL,'client',NULL,'2026-02-02 00:38:18'),(11,'Amiel Carl Santos','amiel@gmail.com','Amiel','$2y$10$SXZ2dQCRq2ruMD60JIoA1.nJBm5BPH4N.zZAwQ.6A/yPdBntTHzxS',NULL,NULL,'client',NULL,'2026-02-02 00:38:18'),(12,'Leala Rivera','leala@gmail.com','leala','$2y$10$MP97edXUFTjCNfqXSjfOX.h3FLduWBNrB1XjjJU1gcilvHeH0fABK',NULL,NULL,'client',NULL,'2026-02-02 00:38:18'),(13,'Eunice Diestro','younice@gmail.com','younice','$2y$10$TBaApJZN9AQfBUATPoQiLutRQf91n62XsVacXWrzhLb/28ZWT/k9a',NULL,NULL,'client',NULL,'2026-02-02 00:38:18'),(15,'Staff','staffrepair@gmail.com','staff','staff123',NULL,NULL,'staff',NULL,'2026-02-02 00:38:18'),(16,'Admin','rapidrepair@gmai.com','admin','admin123',NULL,NULL,'admin',NULL,'2026-02-02 00:38:18'),(17,'Carl Micko','carlmicko1@gmail.com','micko','$2y$10$SlXmUY8TJq8XmUAmk1flouOq/3QYfpJl5/0CIM5AyGcKQkPgiZk6W',NULL,NULL,'client',NULL,'2026-02-02 00:38:18'),(18,'Jiem Mendoza','johnlurjmendoza1@gmail.com','jiem','$2y$10$rgkIkrF4cmJ16HR0Gwr0/eir92.dHg/wk4v43etn56s9Ackr1gwCS','e933c7a8bc34b5291d1628e1595cffd979e70f8725d25dd318c14f959d5d7eb9','2026-02-01 18:57:37','client',NULL,'2026-02-02 00:38:18'),(19,'Elli Payumo','elli@gmail.com','elli2','$2y$10$LcT0oNr1YYgEhWwjRuJ9MeQwBofseqvK2x9Wf0QPahfeu36X/aCea',NULL,NULL,'admin',NULL,'2026-02-02 00:39:39'),(20,'Airwin Del Rosario','airwin@gmail.com','Airwin','$2y$10$4MnuxhRGafLWn1vkLcR2w.phoskED1eI4tTYvLcLDYZWVBbbUvMBW',NULL,NULL,'client',NULL,'2026-02-02 03:35:15');
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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicleinfo`
--

LOCK TABLES `vehicleinfo` WRITE;
/*!40000 ALTER TABLE `vehicleinfo` DISABLE KEYS */;
INSERT INTO `vehicleinfo` VALUES (1,33,'09DXSX','Toyota','2324324',2023,'09234723','Gasoline','Red',20000,'2026-01-19 15:52:08','Active','IMT'),(5,34,'85S3CD','Nissan','Sentra',2025,'45365443','Gasoline','Green',43566,'2026-01-20 22:58:58','Active','Automatic'),(8,36,'NOG957','Toyota','Avanza 9',2009,'09247232','Gasoline','Blue-Gray',50000,'2026-01-21 08:19:26','Active','Manual'),(10,33,'23DAS21','Nissan','Terra',2023,'3443532','Gasoline','Red',9273,'2026-01-23 22:51:45','Active','Manual'),(11,34,'21SDF34','Chery','Tiggo 2',2023,'434234','Gasoline','White',1000000,'2026-01-30 12:19:30','Active','Hybrid'),(13,38,'ABS 3242','Mazda','Mazda6',2025,'12423426334','Diesel','Blue',235345,'2026-01-31 00:29:22','Active','Automatic'),(14,34,'DJS 3453','Honda','Civic',2024,'3424323423','Electric','Red',23423,'2026-01-31 02:29:25','Inactive','Hybrid'),(17,44,'GFE 3568','Geely','Coolray',1993,'3424323423345','Hybrid','Blue-Gray',234343,'2026-02-02 03:37:02','Active','Hybrid'),(18,41,'GGF 4323','Hyundai','Accent',2025,'4324234','Gasoline','Red',12300,'2026-02-02 03:56:45','Active','Automatic');
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

-- Dump completed on 2026-02-02  4:01:30
