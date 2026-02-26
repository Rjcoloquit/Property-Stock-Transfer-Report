-- MySQL dump 10.13  Distrib 8.0.36, for Win64 (x86_64)
--
-- Host: localhost    Database: supply
-- ------------------------------------------------------
-- Server version	8.0.36

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `inventory_records`
--

DROP TABLE IF EXISTS `inventory_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_records` (
  `id` int NOT NULL AUTO_INCREMENT,
  `expiration_date` date DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `description` text,
  `batch_number` varchar(100) DEFAULT NULL,
  `quantity` int DEFAULT '0',
  `unit_cost` decimal(10,2) DEFAULT '0.00',
  `program` varchar(255) DEFAULT NULL,
  `po_no` varchar(100) DEFAULT NULL,
  `recipient` varchar(255) DEFAULT NULL,
  `ptr_no` varchar(50) DEFAULT NULL,
  `record_date` date DEFAULT NULL,
  `release_status` varchar(20) NOT NULL DEFAULT 'released',
  `released_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_records`
--

LOCK TABLES `inventory_records` WRITE;
/*!40000 ALTER TABLE `inventory_records` DISABLE KEYS */;
INSERT INTO `inventory_records` VALUES (1,'2028-09-30','Bottles','0.9% Sodium Chloride 1L Solution for Infusion','81139',5,85.00,'COVID-19 (PHP Monitoring)',NULL,'Coron District Hospital','02/0001','2026-02-18','released',NULL),(2,'2027-01-07','Box','20uL Pipette Tips','20230108',5,950.00,'Food and Waterborne Disease Prevention and Control Program',NULL,'Coron District Hospital','02/0001','2026-02-18','released',NULL),(3,'2028-09-30','Bottles','0.9% Sodium Chloride 1L Solution for Infusion','81139',5,85.00,'COVID-19 (PHP Monitoring)','69','Agutaya RHU','02/0002','2026-02-18','released',NULL);
/*!40000 ALTER TABLE `inventory_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `item_add_history`
--

DROP TABLE IF EXISTS `item_add_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_add_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int DEFAULT NULL,
  `product_description` text,
  `uom` varchar(50) DEFAULT NULL,
  `cost_per_unit` decimal(12,2) DEFAULT '0.00',
  `expiry_date` date DEFAULT NULL,
  `program` varchar(255) DEFAULT NULL,
  `added_by` varchar(150) DEFAULT NULL,
  `added_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_add_history`
--

LOCK TABLES `item_add_history` WRITE;
/*!40000 ALTER TABLE `item_add_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `item_add_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_batches`
--

DROP TABLE IF EXISTS `product_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_batches` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `batch_number` varchar(100) NOT NULL,
  `stock_quantity` int DEFAULT '0',
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_batch` (`product_id`,`batch_number`),
  CONSTRAINT `fk_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_batches`
--

LOCK TABLES `product_batches` WRITE;
/*!40000 ALTER TABLE `product_batches` DISABLE KEYS */;
INSERT INTO `product_batches` VALUES (39,1,'292403398',0,'2027-06-30','2026-02-18 05:49:00','2026-02-18 05:49:00'),(40,2,'23H07',0,'2028-08-31','2026-02-18 05:49:00','2026-02-18 05:49:00'),(41,3,'23H10',0,'2028-08-31','2026-02-18 05:49:00','2026-02-18 05:49:00'),(42,4,'23K12',0,'2028-11-30','2026-02-18 05:49:00','2026-02-18 05:49:00'),(43,5,'23K13',0,'2028-11-30','2026-02-18 05:49:00','2026-02-18 05:49:00'),(44,6,'23L06',0,'2028-12-31','2026-02-18 05:49:00','2026-02-18 05:49:00'),(45,7,'23L07',0,'2028-12-31','2026-02-18 05:49:00','2026-02-18 05:49:00'),(46,8,'L2212',0,'2025-12-31','2026-02-18 05:49:00','2026-02-18 05:49:00'),(47,9,'L2308',0,'2026-10-12','2026-02-18 05:49:00','2026-02-18 05:49:00'),(48,10,'81139',70,'2028-09-30','2026-02-18 05:49:00','2026-02-18 07:23:47'),(49,11,'81187',85,'2029-04-30','2026-02-18 05:49:00','2026-02-18 05:49:00'),(50,12,'81190',85,'2029-04-30','2026-02-18 05:49:00','2026-02-18 05:49:00'),(51,13,'81100',55,'2028-04-30','2026-02-18 05:49:00','2026-02-18 05:49:00'),(52,14,'20230108',1100,'2027-01-07','2026-02-18 05:49:00','2026-02-18 05:49:00'),(53,15,'120012210',280,'2025-01-10','2026-02-18 05:49:00','2026-02-18 05:49:00'),(54,16,'20210721',0,'2026-07-31','2026-02-18 05:49:00','2026-02-18 05:49:00'),(55,17,'20221203',1000,'2026-12-02','2026-02-18 05:49:00','2026-02-18 05:49:00'),(56,18,'20230108',940,'2027-01-07','2026-02-18 05:49:00','2026-02-18 06:35:24'),(57,19,'20220627',0,'2027-06-30','2026-02-18 05:49:00','2026-02-18 05:49:00'),(58,20,'NBS23089A95',0,'2025-08-08','2026-02-18 05:49:00','2026-02-18 05:49:00'),(59,21,'320720',0,'2025-05-31','2026-02-18 05:49:00','2026-02-18 05:49:00'),(60,22,'51631',0,'2025-06-30','2026-02-18 05:49:00','2026-02-18 05:49:00'),(61,23,'51634',0,'2025-09-30','2026-02-18 05:49:00','2026-02-18 05:49:00'),(62,24,'51637',0,'2025-11-30','2026-02-18 05:49:00','2026-02-18 05:49:00'),(63,25,'51639',0,'2026-03-31','2026-02-18 05:49:00','2026-02-18 05:49:00'),(64,26,'22081',0,'2025-11-30','2026-02-18 05:49:00','2026-02-18 05:49:00'),(65,27,'37415',0,'2025-07-31','2026-02-18 05:49:00','2026-02-18 05:49:00'),(66,28,'M24301',0,'2026-02-23','2026-02-18 05:49:00','2026-02-18 05:49:00'),(67,29,'ALB310',0,'2026-05-31','2026-02-18 05:49:00','2026-02-18 05:49:00'),(68,30,'N/A',0,NULL,'2026-02-18 05:49:00','2026-02-18 05:49:00'),(69,31,'N/A',0,'2028-09-30','2026-02-18 05:49:00','2026-02-18 05:49:00'),(70,32,'1773174',0,'2026-04-01','2026-02-18 05:49:00','2026-02-18 05:49:00'),(71,33,'2401127',0,'2029-01-31','2026-02-18 05:49:00','2026-02-18 05:49:00'),(72,34,'GT22535',0,'2025-11-30','2026-02-18 05:49:00','2026-02-18 05:49:00'),(73,35,'2B06133',0,'2027-01-31','2026-02-18 05:49:00','2026-02-18 05:49:00'),(74,36,'B04086',0,'2027-04-30','2026-02-18 05:49:00','2026-02-18 05:49:00'),(75,37,'2BB076',0,'2026-02-28','2026-02-18 05:49:00','2026-02-18 05:49:00'),(76,38,'70624O518',0,'2027-05-31','2026-02-18 05:49:00','2026-02-18 05:49:00');
/*!40000 ALTER TABLE `product_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_description` text NOT NULL,
  `uom` varchar(50) NOT NULL,
  `cost_per_unit` decimal(12,2) DEFAULT '0.00',
  `program` varchar(255) DEFAULT NULL,
  `po_no` varchar(100) DEFAULT NULL,
  `place_of_delivery` varchar(255) DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `date_of_delivery` date DEFAULT NULL,
  `delivery_term` varchar(100) DEFAULT NULL,
  `payment_term` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'[Co-Amoxiclav] Amoxicillin 400mg + Clavulanic Acid 57mg per 5mL Powder for Oral Suspension 70mL','Bottles',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(2,'[COC Pill] Levonorgestrel 150mcg + Ethinylestradiol 30mcg + Ferrous Fumarate 75mg Film-Coated Tablet','Cycle',21.05,'National Family Planning',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(3,'[COC Pill] Levonorgestrel 150mcg + Ethinylestradiol 30mcg + Ferrous Fumarate 75mg Film-Coated Tablet','Cycle',21.05,'National Family Planning',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(4,'[COC Pill] Levonorgestrel 150mcg + Ethinylestradiol 30mcg + Ferrous Fumarate 75mg Film-Coated Tablet','Cycle',21.05,'National Family Planning',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(5,'[COC Pill] Levonorgestrel 150mcg + Ethinylestradiol 30mcg + Ferrous Fumarate 75mg Film-Coated Tablet','Cycle',21.05,'National Family Planning',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(6,'[COC Pill] Levonorgestrel 150mcg + Ethinylestradiol 30mcg + Ferrous Fumarate 75mg Film-Coated Tablet','Cycle',21.05,'Responsible Parenthood and Reproductive Health Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(7,'[COC Pill] Levonorgestrel 150mcg + Ethinylestradiol 30mcg + Ferrous Fumarate 75mg Film-Coated Tablet','Cycle',21.05,'Responsible Parenthood and Reproductive Health Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(8,'[POP Pill] Lynestrenol 500mcg Tablet (Cycle of 28s)','Cycle',25.44,'National Family Planning',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(9,'[POP Pill] Lynestrenol 500mcg Tablet (Cycle of 28s)','Cycle',25.44,'National Family Planning',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(10,'0.9% Sodium Chloride 1L Solution for Infusion','Bottles',85.00,'Food and Waterborne Disease Prevention and Control Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(11,'0.9% Sodium Chloride 1L Solution for Infusion','Bottles',85.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(12,'0.9% Sodium Chloride 1L Solution for Infusion','Bottles',85.00,'Food and Waterborne Disease Prevention and Control Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(13,'0.9% Sodium Chloride Solution for Irrigation 1L','Bottles',55.00,'Food and Waterborne Disease Prevention and Control Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(14,'1250uL Pipette Tips','Box',1100.00,'COVID-19 Laboratory Network',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(15,'1cc Syringe 23Gx1','Box',280.00,'COVID-19 (PHP Monitoring)',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(16,'1cc Syringe 25G x 1','Pieces',0.00,'National Immunization Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(17,'200uL Pipette Tips','Box',1000.00,'COVID-19 Laboratory Network',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(18,'20uL Pipette Tips','Box',950.00,'COVID-19 Laboratory Network',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(19,'3cc Syringe 23G x 1','Pieces',0.00,'National Immunization Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(20,'3-in-1 Multi-Function Monitoring System Glucose Hemoblogin and Cholesterol Meter with Strips','Kit',0.00,'Maternal Health Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(21,'5% Dextrose in 0.3% Sodium Chloride 500 mL Solution for Infusion (IV Infusion)','Bottles',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(22,'5% Dextrose in 0.9% Sodium Chloride 1 L Solution for Infusion (IV Infusion)','Bottles',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(23,'5% Dextrose in 0.9% Sodium Chloride 1 L Solution for Infusion (IV Infusion)','Bottles',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(24,'5% Dextrose in 0.9% Sodium Chloride 1 L Solution for Infusion (IV Infusion)','Bottles',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(25,'5% Dextrose in 0.9% Sodium Chloride 1 L Solution for Infusion (IV Infusion)','Bottles',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(26,'5% Dextrose in Lactated Ringers Solution 1 L Solution for Infusion (IV Infusion)','Bottles',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(27,'Aciclovir 400 mg tablet','Tablet',8.50,'National HIV/ AIDS and STI Prevention and Control Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(28,'AFB Fast Bacilli Stain Hot Method for TB Microscopy','Kit',2890.00,'National Tuberculosis Control Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(29,'Albendazole 400mg Tablet','Tablet',0.99,'Integrated Helminth Control Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(30,'Alcohol 500mL','Bottles',156.00,'Leprosy Control Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(31,'Alcohol 70% 1L','Bottles',372.00,'Emerging and Re-emerging Infectious Disease Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(32,'Ambroxol 6mg/ml Drops','Bottles',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(33,'Amlodipine 10 mg Tablet','Tablet',0.49,'Integrated Non-Communicable Disease Prevention and Control',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(34,'Amlodipine 5 mg Tablet','Tablet',0.37,'Integrated Non-Communicable Disease Prevention and Control',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(35,'Amoxicillin 100 mg/mL 10 mL Oral Drops','Bottles',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(36,'Amoxicillin 250 mg Capsule','Capsule',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(37,'Amoxicillin 250mg/5mL Oral Suspension','Bottles',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01'),(38,'Amoxicillin 500 mg Capsule','Capsule',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-18 05:28:01','2026-02-18 05:28:01');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recipients`
--

DROP TABLE IF EXISTS `recipients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recipients` (
  `recipient_id` int NOT NULL AUTO_INCREMENT,
  `recipient_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`recipient_id`),
  UNIQUE KEY `recipient_name` (`recipient_name`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recipients`
--

LOCK TABLES `recipients` WRITE;
/*!40000 ALTER TABLE `recipients` DISABLE KEYS */;
INSERT INTO `recipients` VALUES (1,'Aborlan Medicare Hospital','2026-02-18 05:30:20'),(2,'Aborlan RHU','2026-02-18 05:30:20'),(3,'Agape Clinic','2026-02-18 05:30:20'),(4,'Agutaya RHU','2026-02-18 05:30:20'),(5,'Araceli RHU','2026-02-18 05:30:20'),(6,'Araceli-Dumaran District Hospital','2026-02-18 05:30:20'),(7,'Balabac District Hospital','2026-02-18 05:30:20'),(8,'Balabac RHU','2026-02-18 05:30:20'),(9,'Bataraza District Hospital','2026-02-18 05:30:20'),(10,'Bataraza RHU','2026-02-18 05:30:20'),(11,'Brookes Point RHU','2026-02-18 05:30:20'),(12,'Busuanga RHU','2026-02-18 05:30:20'),(13,'Cagayancillo RHU','2026-02-18 05:30:20'),(14,'City Health Office','2026-02-18 05:30:20'),(15,'Coron District Hospital','2026-02-18 05:30:20'),(16,'Coron RHU','2026-02-18 05:30:20'),(17,'Culion RHU','2026-02-18 05:30:20'),(18,'Cuyo District Hospital','2026-02-18 05:30:20'),(19,'Cuyo RHU','2026-02-18 05:30:20'),(20,'Dr. Jose Rizal District Hospital','2026-02-18 05:30:20'),(21,'Dumara RHU','2026-02-18 05:30:20'),(22,'El Nido RHU','2026-02-18 05:30:20'),(23,'Iwahig Penal Colony Clinic','2026-02-18 05:30:20'),(24,'Kalayaan RHU','2026-02-18 05:30:20'),(25,'Linapacan RHU','2026-02-18 05:30:20'),(26,'Magsaysay RHU','2026-02-18 05:30:20'),(27,'Narra Municipal Hospital','2026-02-18 05:30:20'),(28,'Narra RHU','2026-02-18 05:30:20'),(29,'Northern Palawan Provincial Hospital','2026-02-18 05:30:20'),(30,'Ospital ng Palawan','2026-02-18 05:30:20'),(31,'PHO Clinic','2026-02-18 05:30:20'),(32,'PHO TB-DOTS','2026-02-18 05:30:20'),(33,'Provincial Veterinary Office','2026-02-18 05:30:20'),(34,'Quezon Medicare Hospital','2026-02-18 05:30:20'),(35,'Quezon RHU','2026-02-18 05:30:20'),(36,'Rizal RHU','2026-02-18 05:30:20'),(37,'Roxas Medicare Hospital','2026-02-18 05:30:20'),(38,'Roxas RHU','2026-02-18 05:30:20'),(39,'San Vicente District Hospital','2026-02-18 05:30:20'),(40,'San Vicente RHU','2026-02-18 05:30:20'),(41,'Sofronio Española RHU','2026-02-18 05:30:20'),(42,'Sofronio Española Provincial Hospital','2026-02-18 05:30:20'),(43,'Taytay RHU','2026-02-18 05:30:20'),(44,'Vice Governor Francsio F. Ponce De Leon Memorial Hospital','2026-02-18 05:30:20');
/*!40000 ALTER TABLE `recipients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Admin','Encoder') NOT NULL DEFAULT 'Encoder',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','admin','admin@gmail.com','$2y$10$hRN.rq8iqMh8U/W3q6ZTiunUGf6ibsO5VmsdISCUv/BRKAyrxecY6','Admin','Active','2026-02-19 00:27:00');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-20  9:33:29
