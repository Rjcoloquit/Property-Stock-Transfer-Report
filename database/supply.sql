-- MySQL dump 10.13  Distrib 8.0.42, for Win64 (x86_64)
--
-- Host: localhost    Database: supply
-- ------------------------------------------------------
-- Server version	8.0.42

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
  `recipient` varchar(255) DEFAULT NULL,
  `ptr_no` varchar(50) DEFAULT NULL,
  `record_date` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_records`
--

LOCK TABLES `inventory_records` WRITE;
/*!40000 ALTER TABLE `inventory_records` DISABLE KEYS */;
INSERT INTO `inventory_records` VALUES (1,'2028-04-30','Bottles','0.9% Sodium Chloride Solution for Irrigation 1L','81100',10,0.00,NULL,'Vice Governor Francsio F. Ponce De Leon Memorial Hospital','1','2026-02-12'),(2,NULL,'Bottles','Alcohol 500mL','N/A',1000,156.00,NULL,'Vice Governor Francsio F. Ponce De Leon Memorial Hospital','1','2026-02-12'),(3,'2026-04-30','Bottles','Ambroxol 6mg/ml Drops','1773174',100,0.00,NULL,'Vice Governor Francsio F. Ponce De Leon Memorial Hospital','1','2026-02-12'),(4,'2027-06-30','Bottles','[Co-Amoxiclav] Amoxicillin 400mg + Clavulanic Acid 57mg per 5mL Powder for Oral Suspension 70mL','292403398',5,0.00,'Emerging and Re-emerging Infectious Disease Program','Dumara RHU','2','2026-02-13'),(5,'2026-04-30','Bottles','Ambroxol 6mg/ml Drops','1773174',10,0.00,NULL,'Dumara RHU','2','2026-02-13'),(6,'2025-12-31','Cycle','[POP Pill] Lynestrenol 500mcg Tablet (Cycle of 28s)','L2308',5,0.00,NULL,'Dumara RHU','2','2026-02-13'),(7,'2025-01-31','Box','1cc Syringe 23Gx1','123761273',5,0.00,'COVID-19 Laboratory Network','Aborlan RHU','3','2026-02-13'),(8,'2027-01-31','Box','20uL Pipette Tips','202280129',100,0.00,'Dengue','Bgy. San Manuel','4','2026-02-13');
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
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_batches`
--

LOCK TABLES `product_batches` WRITE;
/*!40000 ALTER TABLE `product_batches` DISABLE KEYS */;
INSERT INTO `product_batches` VALUES (1,1,'292403398',0,'2027-06-30','2026-02-12 07:47:20','2026-02-12 07:47:20'),(2,2,'L2212',0,'2025-12-31','2026-02-12 07:47:20','2026-02-12 07:47:20'),(3,2,'L2308',0,'2026-10-31','2026-02-12 07:47:20','2026-02-12 07:47:20'),(4,3,'81139',85,'2028-09-30','2026-02-12 07:47:20','2026-02-12 07:47:20'),(5,4,'81187',85,'2029-04-30','2026-02-12 07:47:20','2026-02-12 07:47:20'),(6,3,'81190',85,'2029-04-30','2026-02-12 07:47:20','2026-02-12 07:47:20'),(7,5,'81100',55,'2028-04-30','2026-02-12 07:47:20','2026-02-12 07:47:20'),(8,6,'20230108',1100,'2027-01-31','2026-02-12 07:47:20','2026-02-12 07:47:20'),(9,7,'120012210',280,'2025-01-31','2026-02-12 07:47:20','2026-02-12 07:47:20'),(10,8,'20210721',0,'2026-07-31','2026-02-12 07:47:20','2026-02-12 07:47:20'),(11,9,'20221203',1000,'2026-12-31','2026-02-12 07:47:20','2026-02-12 07:47:20'),(12,10,'20230108',950,'2027-01-31','2026-02-12 07:47:20','2026-02-12 07:47:20'),(13,11,'20220627',0,'2027-06-30','2026-02-12 07:47:20','2026-02-12 07:47:20'),(14,12,'NBS23089A95',0,'2025-08-31','2026-02-12 07:47:20','2026-02-12 07:47:20'),(15,13,'320720',0,'2025-05-31','2026-02-12 07:50:35','2026-02-12 07:50:35'),(16,14,'51631',0,'2025-06-30','2026-02-12 07:50:35','2026-02-12 07:50:35'),(17,14,'51634',0,'2025-09-30','2026-02-12 07:50:35','2026-02-12 07:50:35'),(18,14,'51637',0,'2025-11-30','2026-02-12 07:50:35','2026-02-12 07:50:35'),(19,14,'51639',0,'2026-03-31','2026-02-12 07:50:35','2026-02-12 07:50:35'),(20,15,'22081',0,'2025-11-30','2026-02-12 07:50:35','2026-02-12 07:50:35'),(21,16,'37415',0,'2025-07-31','2026-02-12 07:50:35','2026-02-12 07:50:35'),(22,17,'M24301',0,'2026-02-28','2026-02-12 07:50:35','2026-02-12 07:50:35'),(23,18,'ALB310',0,'2026-05-31','2026-02-12 07:50:35','2026-02-12 07:50:35'),(24,19,'N/A',0,NULL,'2026-02-12 07:50:35','2026-02-12 07:50:35'),(25,20,'0',0,'2028-09-30','2026-02-12 07:50:35','2026-02-12 07:50:35'),(26,21,'1773174',0,'2026-04-30','2026-02-12 07:50:35','2026-02-12 07:50:35'),(27,22,'2401127',0,'2029-01-31','2026-02-12 07:50:35','2026-02-12 07:50:35'),(28,23,'GT22535',0,'2025-11-30','2026-02-12 07:50:35','2026-02-12 07:50:35'),(29,24,'2B06133',0,'2027-01-31','2026-02-12 07:50:35','2026-02-12 07:50:35');
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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'[Co-Amoxiclav] Amoxicillin 400mg + Clavulanic Acid 57mg per 5mL Powder for Oral Suspension 70mL','Bottles',0.00,'General Consumption','2026-02-12 07:46:28','2026-02-12 07:46:28'),(2,'[POP Pill] Lynestrenol 500mcg Tablet (Cycle of 28s)','Cycle',0.00,'National Family Planning','2026-02-12 07:46:28','2026-02-12 07:46:28'),(3,'0.9% Sodium Chloride 1L Solution for Infusion','Bottles',0.00,'Food and Waterborne Disease Prevention and Control Program','2026-02-12 07:46:28','2026-02-12 07:46:28'),(4,'0.9% Sodium Chloride 1L Solution for Infusion','Bottles',0.00,'General Consumption','2026-02-12 07:46:28','2026-02-12 07:46:28'),(5,'0.9% Sodium Chloride Solution for Irrigation 1L','Bottles',0.00,'Food and Waterborne Disease Prevention and Control Program','2026-02-12 07:46:28','2026-02-12 07:46:28'),(6,'1250uL Pipette Tips','Box',0.00,'COVID-19 Laboratory Network','2026-02-12 07:46:28','2026-02-12 07:46:28'),(7,'1cc Syringe 23Gx1','Box',0.00,'COVID-19 (PHP Monitoring)','2026-02-12 07:46:28','2026-02-12 07:46:28'),(8,'1cc Syringe 25G x 1','Pieces',0.00,'National Immunization Program','2026-02-12 07:46:28','2026-02-12 07:46:28'),(9,'200uL Pipette Tips','Box',0.00,'COVID-19 Laboratory Network','2026-02-12 07:46:28','2026-02-12 07:46:28'),(10,'20uL Pipette Tips','Box',0.00,'COVID-19 Laboratory Network','2026-02-12 07:46:28','2026-02-12 07:46:28'),(11,'3cc Syringe 23G x 1','Pieces',0.00,'National Immunization Program','2026-02-12 07:46:28','2026-02-12 07:46:28'),(12,'3-in-1 Multi-Function Monitoring System Glucose Hemoblogin and Cholesterol Meter with Strips','Kit',0.00,'Maternal Health Program','2026-02-12 07:46:28','2026-02-12 07:46:28'),(13,'5% Dextrose in 0.3% Sodium Chloride 500 mL Solution for Infusion (IV Infusion)','Bottles',0.00,'General Consumption','2026-02-12 07:50:05','2026-02-12 07:50:05'),(14,'5% Dextrose in 0.9% Sodium Chloride 1 L Solution for Infusion (IV Infusion)','Bottles',0.00,'General Consumption','2026-02-12 07:50:05','2026-02-12 07:50:05'),(15,'5% Dextrose in Lactated Ringers Solution 1 L Solution for Infusion (IV Infusion)','Bottles',0.00,'General Consumption','2026-02-12 07:50:05','2026-02-12 07:50:05'),(16,'Aciclovir 400 mg tablet','Tablet',8.50,'National HIV/ AIDS and STI Prevention and Control Program','2026-02-12 07:50:05','2026-02-12 07:50:05'),(17,'AFB Fast Bacilli Stain Hot Method for TB Microscopy','Kit',2890.00,'National Tuberculosis Control Program','2026-02-12 07:50:05','2026-02-12 07:50:05'),(18,'Albendazole 400mg Tablet','Tablet',0.99,'Integrated Helminth Control Program','2026-02-12 07:50:05','2026-02-12 07:50:05'),(19,'Alcohol 500mL','Bottles',156.00,'Leprosy Control Program','2026-02-12 07:50:05','2026-02-12 07:50:05'),(20,'Alcohol 70% 1L','Bottles',372.00,'Emerging and Re-emerging Infectious Disease Program','2026-02-12 07:50:05','2026-02-12 07:50:05'),(21,'Ambroxol 6mg/ml Drops','Bottles',0.00,'General Consumption','2026-02-12 07:50:05','2026-02-12 07:50:05'),(22,'Amlodipine 10 mg Tablet','Tablet',0.49,'Integrated Non-Communicable Disease Prevention and Control','2026-02-12 07:50:05','2026-02-12 07:50:05'),(23,'Amlodipine 5 mg Tablet','Tablet',0.37,'Integrated Non-Communicable Disease Prevention and Control','2026-02-12 07:50:05','2026-02-12 07:50:05'),(24,'Amoxicillin 100 mg/mL 10 mL Oral Drops','Bottles',0.00,'General Consumption','2026-02-12 07:50:05','2026-02-12 07:50:05');
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
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recipients`
--

LOCK TABLES `recipients` WRITE;
/*!40000 ALTER TABLE `recipients` DISABLE KEYS */;
INSERT INTO `recipients` VALUES (1,'Aborlan Medicare Hospital','2026-02-13 03:36:40'),(2,'Aborlan RHU','2026-02-13 03:36:40'),(3,'Agape Clinic','2026-02-13 03:36:40'),(4,'Agutaya RHU','2026-02-13 03:36:40'),(5,'Araceli RHU','2026-02-13 03:36:40'),(6,'Araceli-Dumaran District Hospital','2026-02-13 03:36:40'),(7,'Balabac District Hospital','2026-02-13 03:36:40'),(8,'Balabac RHU','2026-02-13 03:36:40'),(9,'Bataraza District Hospital','2026-02-13 03:36:40'),(10,'Bataraza RHU','2026-02-13 03:36:40'),(11,'Brookes Point RHU','2026-02-13 03:36:40'),(12,'Busuanga RHU','2026-02-13 03:36:40'),(13,'Cagayancillo RHU','2026-02-13 03:36:40'),(14,'City Health Office','2026-02-13 03:36:40'),(15,'Coron District Hospital','2026-02-13 03:36:40'),(16,'Coron RHU','2026-02-13 03:36:40'),(17,'Culion RHU','2026-02-13 03:36:40'),(18,'Cuyo District Hospital','2026-02-13 03:36:40'),(19,'Cuyo RHU','2026-02-13 03:36:40'),(20,'Dr. Jose Rizal District Hospital','2026-02-13 03:36:40'),(21,'Dumaran RHU','2026-02-13 03:36:40'),(22,'El Nido RHU','2026-02-13 03:36:40'),(23,'Iwahig Penal Colony Clinic','2026-02-13 03:36:40'),(24,'Kalayaan RHU','2026-02-13 03:36:40'),(25,'Linapacan RHU','2026-02-13 03:36:40'),(26,'Magsaysay RHU','2026-02-13 03:36:40'),(27,'Narra Municipal Hospital','2026-02-13 03:36:40'),(28,'Narra RHU','2026-02-13 03:36:40'),(29,'Northern Palawan Provincial Hospital','2026-02-13 03:36:40'),(30,'Ospital ng Palawan','2026-02-13 03:36:40'),(31,'PHO Clinic','2026-02-13 03:36:40'),(32,'PHO TB-DOTS','2026-02-13 03:36:40'),(33,'Provincial Veterinary Office','2026-02-13 03:36:40'),(34,'Quezon Medicare Hospital','2026-02-13 03:36:40'),(35,'Quezon RHU','2026-02-13 03:36:40'),(36,'Rizal RHU','2026-02-13 03:36:40'),(37,'Roxas Medicare Hospital','2026-02-13 03:36:40'),(38,'Roxas RHU','2026-02-13 03:36:40'),(39,'San Vicente District Hospital','2026-02-13 03:36:40'),(42,'San Vicente RHU','2026-02-13 03:36:40'),(43,'Sofronio Española RHU','2026-02-13 03:36:40'),(44,'Sofronio Española Provincial Hospital','2026-02-13 03:36:40'),(45,'Taytay RHU','2026-02-13 03:36:40'),(46,'Vice Governor Francsio F. Ponce De Leon Memorial Hospital','2026-02-13 03:36:40');
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
INSERT INTO `users` VALUES (1,'rjcoloquit','admin','admin@gmail.com','$2y$10$YP8G/JASSBq4DC.N2Sowe.JdUnQkkvXLO.mZmmhRYMh3JFhU7pjeK','Admin','Active','2026-02-12 07:34:29');
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

-- Dump completed on 2026-02-13 11:37:43
