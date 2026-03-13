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
-- Table structure for table `incident_reports`
--

DROP TABLE IF EXISTS `incident_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `incident_reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name_of_office` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `incident_no` varchar(120) DEFAULT NULL,
  `incident_type` varchar(255) DEFAULT NULL,
  `incident_datetime` datetime DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `specifics_json` longtext,
  `persons_involved` text,
  `remarks` longtext,
  `action_taken` longtext,
  `prepared_by_name` varchar(255) DEFAULT NULL,
  `prepared_by_designation` varchar(255) DEFAULT NULL,
  `prepared_by_date` date DEFAULT NULL,
  `submitted_to_name` varchar(255) DEFAULT NULL,
  `submitted_to_designation` varchar(255) DEFAULT NULL,
  `submitted_to_date` date DEFAULT NULL,
  `created_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incident_reports`
--

LOCK TABLES `incident_reports` WRITE;
/*!40000 ALTER TABLE `incident_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `incident_reports` ENABLE KEYS */;
UNLOCK TABLES;

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
  `quantity` int DEFAULT '0',
  `unit_cost` decimal(10,2) DEFAULT '0.00',
  `program` varchar(255) DEFAULT NULL,
  `po_no` varchar(100) DEFAULT NULL,
  `recipient` varchar(255) DEFAULT NULL,
  `ptr_no` varchar(50) DEFAULT NULL,
  `record_date` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_records`
--

LOCK TABLES `inventory_records` WRITE;
/*!40000 ALTER TABLE `inventory_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_records` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_batches`
--

LOCK TABLES `product_batches` WRITE;
/*!40000 ALTER TABLE `product_batches` DISABLE KEYS */;
INSERT INTO `product_batches` VALUES (1,1,'292403398',0,'2027-06-30','2026-02-26 00:58:15','2026-02-26 00:58:15'),(2,2,'23H07',0,'2028-08-31','2026-02-26 00:58:15','2026-02-26 00:58:15'),(3,3,'23H10',0,'2028-08-31','2026-02-26 00:58:15','2026-02-26 00:58:15'),(4,4,'23K12',0,'2028-11-30','2026-02-26 00:58:15','2026-02-26 00:58:15'),(5,5,'23K13',0,'2028-11-30','2026-02-26 00:58:15','2026-02-26 00:58:15'),(6,6,'23L06',0,'2028-12-31','2026-02-26 00:58:15','2026-02-26 00:58:15'),(7,7,'23L07',0,'2028-12-31','2026-02-26 00:58:15','2026-02-26 00:58:15'),(8,8,'L2212',0,'2025-12-31','2026-02-26 00:58:15','2026-02-26 00:58:15'),(9,9,'L2308',0,'2026-10-12','2026-02-26 00:58:15','2026-02-26 00:58:15'),(10,10,'81139',85,'2028-09-30','2026-02-26 00:58:15','2026-02-26 00:58:15'),(11,11,'81187',85,'2029-04-30','2026-02-26 00:58:15','2026-02-26 00:58:15'),(12,12,'81190',85,'2029-04-30','2026-02-26 00:58:15','2026-02-26 00:58:15'),(13,13,'81100',55,'2028-04-30','2026-02-26 00:58:15','2026-02-26 00:58:15'),(14,14,'20230108',1100,'2027-01-07','2026-02-26 00:58:15','2026-02-26 00:58:15'),(15,15,'120012210',280,'2025-01-10','2026-02-26 00:58:15','2026-02-26 00:58:15'),(16,16,'20210721',0,'2026-07-31','2026-02-26 00:58:15','2026-02-26 00:58:15'),(17,17,'20221203',1000,'2026-12-02','2026-02-26 00:58:15','2026-02-26 00:58:15'),(18,18,'20230108',950,'2027-01-07','2026-02-26 00:58:15','2026-02-26 00:58:15'),(19,19,'20220627',0,'2027-06-30','2026-02-26 00:58:15','2026-02-26 00:58:15'),(20,20,'NBS23089A95',0,'2025-08-08','2026-02-26 00:58:15','2026-02-26 00:58:15'),(21,21,'320720',0,'2025-05-31','2026-02-26 00:58:15','2026-02-26 00:58:15'),(22,22,'51631',0,'2025-06-30','2026-02-26 00:58:15','2026-02-26 00:58:15'),(23,23,'51634',0,'2025-09-30','2026-02-26 00:58:15','2026-02-26 00:58:15'),(24,24,'51637',0,'2025-11-30','2026-02-26 00:58:15','2026-02-26 00:58:15'),(25,25,'51639',0,'2026-03-31','2026-02-26 00:58:15','2026-02-26 00:58:15'),(26,26,'22081',0,'2025-11-30','2026-02-26 00:58:15','2026-02-26 00:58:15'),(27,27,'37415',0,'2025-07-31','2026-02-26 00:58:15','2026-02-26 00:58:15'),(28,28,'M24301',0,'2026-02-23','2026-02-26 00:58:15','2026-02-26 00:58:15'),(29,29,'ALB310',0,'2026-05-31','2026-02-26 00:58:15','2026-02-26 00:58:15'),(30,30,'N/A',0,NULL,'2026-02-26 00:58:15','2026-02-26 00:58:15'),(31,31,'N/A',0,'2028-09-30','2026-02-26 00:58:15','2026-02-26 00:58:15'),(32,32,'1773174',0,'2026-04-01','2026-02-26 00:58:15','2026-02-26 00:58:15'),(33,33,'2401127',0,'2029-01-31','2026-02-26 00:58:15','2026-02-26 00:58:15'),(34,34,'GT22535',0,'2025-11-30','2026-02-26 00:58:15','2026-02-26 00:58:15'),(35,35,'2B06133',0,'2027-01-31','2026-02-26 00:58:15','2026-02-26 00:58:15'),(36,36,'B04086',0,'2027-04-30','2026-02-26 00:58:15','2026-02-26 00:58:15'),(37,37,'2BB076',0,'2026-02-28','2026-02-26 00:58:15','2026-02-26 00:58:15'),(38,38,'70624O518',0,'2027-05-31','2026-02-26 00:58:15','2026-02-26 00:58:15');
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
INSERT INTO `products` VALUES (1,'[Co-Amoxiclav] Amoxicillin 400mg + Clavulanic Acid 57mg per 5mL Powder for Oral Suspension 70mL','Bottles',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(2,'[COC Pill] Levonorgestrel 150mcg + Ethinylestradiol 30mcg + Ferrous Fumarate 75mg Film-Coated Tablet','Cycle',21.05,'National Family Planning',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(3,'[COC Pill] Levonorgestrel 150mcg + Ethinylestradiol 30mcg + Ferrous Fumarate 75mg Film-Coated Tablet','Cycle',21.05,'National Family Planning',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(4,'[COC Pill] Levonorgestrel 150mcg + Ethinylestradiol 30mcg + Ferrous Fumarate 75mg Film-Coated Tablet','Cycle',21.05,'National Family Planning',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(5,'[COC Pill] Levonorgestrel 150mcg + Ethinylestradiol 30mcg + Ferrous Fumarate 75mg Film-Coated Tablet','Cycle',21.05,'National Family Planning',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(6,'[COC Pill] Levonorgestrel 150mcg + Ethinylestradiol 30mcg + Ferrous Fumarate 75mg Film-Coated Tablet','Cycle',21.05,'Responsible Parenthood and Reproductive Health Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(7,'[COC Pill] Levonorgestrel 150mcg + Ethinylestradiol 30mcg + Ferrous Fumarate 75mg Film-Coated Tablet','Cycle',21.05,'Responsible Parenthood and Reproductive Health Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(8,'[POP Pill] Lynestrenol 500mcg Tablet (Cycle of 28s)','Cycle',25.44,'National Family Planning',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(9,'[POP Pill] Lynestrenol 500mcg Tablet (Cycle of 28s)','Cycle',25.44,'National Family Planning',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(10,'0.9% Sodium Chloride 1L Solution for Infusion','Bottles',85.00,'Food and Waterborne Disease Prevention and Control Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(11,'0.9% Sodium Chloride 1L Solution for Infusion','Bottles',85.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(12,'0.9% Sodium Chloride 1L Solution for Infusion','Bottles',85.00,'Food and Waterborne Disease Prevention and Control Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(13,'0.9% Sodium Chloride Solution for Irrigation 1L','Bottles',55.00,'Food and Waterborne Disease Prevention and Control Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(14,'1250uL Pipette Tips','Box',1100.00,'COVID-19 Laboratory Network',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(15,'1cc Syringe 23Gx1','Box',280.00,'COVID-19 (PHP Monitoring)',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(16,'1cc Syringe 25G x 1','Pieces',0.00,'National Immunization Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(17,'200uL Pipette Tips','Box',1000.00,'COVID-19 Laboratory Network',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(18,'20uL Pipette Tips','Box',950.00,'COVID-19 Laboratory Network',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(19,'3cc Syringe 23G x 1','Pieces',0.00,'National Immunization Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(20,'3-in-1 Multi-Function Monitoring System Glucose Hemoblogin and Cholesterol Meter with Strips','Kit',0.00,'Maternal Health Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(21,'5% Dextrose in 0.3% Sodium Chloride 500 mL Solution for Infusion (IV Infusion)','Bottles',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(22,'5% Dextrose in 0.9% Sodium Chloride 1 L Solution for Infusion (IV Infusion)','Bottles',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(23,'5% Dextrose in 0.9% Sodium Chloride 1 L Solution for Infusion (IV Infusion)','Bottles',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(24,'5% Dextrose in 0.9% Sodium Chloride 1 L Solution for Infusion (IV Infusion)','Bottles',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(25,'5% Dextrose in 0.9% Sodium Chloride 1 L Solution for Infusion (IV Infusion)','Bottles',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(26,'5% Dextrose in Lactated Ringers Solution 1 L Solution for Infusion (IV Infusion)','Bottles',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(27,'Aciclovir 400 mg tablet','Tablet',8.50,'National HIV/ AIDS and STI Prevention and Control Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(28,'AFB Fast Bacilli Stain Hot Method for TB Microscopy','Kit',2890.00,'National Tuberculosis Control Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(29,'Albendazole 400mg Tablet','Tablet',0.99,'Integrated Helminth Control Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(30,'Alcohol 500mL','Bottles',156.00,'Leprosy Control Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(31,'Alcohol 70% 1L','Bottles',372.00,'Emerging and Re-emerging Infectious Disease Program',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(32,'Ambroxol 6mg/ml Drops','Bottles',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(33,'Amlodipine 10 mg Tablet','Tablet',0.49,'Integrated Non-Communicable Disease Prevention and Control',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(34,'Amlodipine 5 mg Tablet','Tablet',0.37,'Integrated Non-Communicable Disease Prevention and Control',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(35,'Amoxicillin 100 mg/mL 10 mL Oral Drops','Bottles',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(36,'Amoxicillin 250 mg Capsule','Capsule',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(37,'Amoxicillin 250mg/5mL Oral Suspension','Bottles',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10'),(38,'Amoxicillin 500 mg Capsule','Capsule',0.00,'General Consumption',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 00:58:10','2026-02-26 00:58:10');
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
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recipients`
--

LOCK TABLES `recipients` WRITE;
/*!40000 ALTER TABLE `recipients` DISABLE KEYS */;
INSERT INTO `recipients` VALUES (1,'Aborlan Medicare Hospital','2026-02-26 00:58:22'),(2,'Aborlan RHU','2026-02-26 00:58:22'),(3,'Agape Clinic','2026-02-26 00:58:22'),(4,'Agutaya RHU','2026-02-26 00:58:22'),(5,'Araceli RHU','2026-02-26 00:58:22'),(6,'Araceli-Dumaran District Hospital','2026-02-26 00:58:22'),(7,'Balabac District Hospital','2026-02-26 00:58:22'),(8,'Balabac RHU','2026-02-26 00:58:22'),(9,'Bataraza District Hospital','2026-02-26 00:58:22'),(10,'Bataraza RHU','2026-02-26 00:58:22'),(11,'Brookes Point RHU','2026-02-26 00:58:22'),(12,'Busuanga RHU','2026-02-26 00:58:22'),(13,'Cagayancillo RHU','2026-02-26 00:58:22'),(14,'City Health Office','2026-02-26 00:58:22'),(15,'Coron District Hospital','2026-02-26 00:58:22'),(16,'Coron RHU','2026-02-26 00:58:22'),(17,'Culion RHU','2026-02-26 00:58:22'),(18,'Cuyo District Hospital','2026-02-26 00:58:22'),(19,'Cuyo RHU','2026-02-26 00:58:22'),(20,'Dr. Jose Rizal District Hospital','2026-02-26 00:58:22'),(21,'Dumara RHU','2026-02-26 00:58:22'),(22,'El Nido RHU','2026-02-26 00:58:22'),(23,'Iwahig Penal Colony Clinic','2026-02-26 00:58:22'),(24,'Kalayaan RHU','2026-02-26 00:58:22'),(25,'Linapacan RHU','2026-02-26 00:58:22'),(26,'Magsaysay RHU','2026-02-26 00:58:22'),(27,'Narra Municipal Hospital','2026-02-26 00:58:22'),(28,'Narra RHU','2026-02-26 00:58:22'),(29,'Northern Palawan Provincial Hospital','2026-02-26 00:58:22'),(30,'Ospital ng Palawan','2026-02-26 00:58:22'),(31,'PHO Clinic','2026-02-26 00:58:22'),(32,'PHO TB-DOTS','2026-02-26 00:58:22'),(33,'Provincial Veterinary Office','2026-02-26 00:58:22'),(34,'Quezon Medicare Hospital','2026-02-26 00:58:22'),(35,'Quezon RHU','2026-02-26 00:58:22'),(36,'Rizal RHU','2026-02-26 00:58:22'),(37,'Roxas Medicare Hospital','2026-02-26 00:58:22'),(38,'Roxas RHU','2026-02-26 00:58:22'),(39,'San Vicente District Hospital','2026-02-26 00:58:22'),(40,'San Vicente RHU','2026-02-26 00:58:22'),(41,'Sofronio Española RHU','2026-02-26 00:58:22'),(42,'Sofronio Española Provincial Hospital','2026-02-26 00:58:22'),(43,'Taytay RHU','2026-02-26 00:58:22'),(44,'Vice Governor Francsio F. Ponce De Leon Memorial Hospital','2026-02-26 00:58:22');
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
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

-- Dump completed on 2026-02-26  9:00:49
