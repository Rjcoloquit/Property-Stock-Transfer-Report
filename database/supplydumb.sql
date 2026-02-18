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
  `quantity` int DEFAULT '0',
  `unit_cost` decimal(10,2) DEFAULT '0.00',
  `program` varchar(255) DEFAULT NULL,
  `recipient` varchar(255) DEFAULT NULL,
  `ptr_no` varchar(50) DEFAULT NULL,
  `record_date` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_records`
--

LOCK TABLES `inventory_records` WRITE;
/*!40000 ALTER TABLE `inventory_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_description` text,
  `uom` varchar(50) DEFAULT NULL,
  `cost_per_unit` decimal(12,2) DEFAULT '0.00',
  `expiry_date` date DEFAULT NULL,
  `program` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'[Co-Amoxiclav] Amoxicillin 400mg + Clavulanic Acid 57mg per 5mL Powder for Oral Suspension 70mL/292403398','Bottles',0.00,'2027-06-01','General Consumption'),(2,'[POP Pill] Lynestrenol 500mcg Tablet (Cycle of 28s)/L2212','Cycle',25.44,'2025-12-01','National Family Planning'),(3,'[POP Pill] Lynestrenol 500mcg Tablet (Cycle of 28s)/L2308','Cycle',25.44,'2026-10-01','National Family Planning'),(4,'0.9% Sodium Chloride 1L Solution for Infusion/81139','Bottles',85.00,'2028-09-01','Food and Waterborne Disease Prevention and Control Program'),(5,'0.9% Sodium Chloride 1L Solution for Infusion/81187','Bottles',85.00,'2029-04-01','General Consumption'),(6,'0.9% Sodium Chloride 1L Solution for Infusion/81190','Bottles',85.00,'2029-04-01','Food and Waterborne Disease Prevention and Control Program'),(7,'0.9% Sodium Chloride Solution for Irrigation 1L/81100','Bottles',55.00,'2028-04-01','Food and Waterborne Disease Prevention and Control Program'),(8,'1250uL Pipette Tips/20230108','Box',1100.00,'2027-01-01','COVID-19 Laboratory Network'),(9,'1cc Syringe 23Gx1/120012210','Box',280.00,'2025-01-01','COVID-19 (PHP Monitoring)'),(10,'1cc Syringe 25G x 1 /20210721','Pieces',0.00,'2026-07-01','National Immunization Program'),(11,'1cc Syringe 23Gx1/120012210','Box',280.00,'2025-01-01','COVID-19 (PHP Monitoring)'),(12,'1cc Syringe 25G x 1 /20210721','Pieces',0.00,'2026-07-01','National Immunization Program'),(13,'200uL Pipette Tips/20221203','Box',1000.00,'2026-12-01','COVID-19 Laboratory Network'),(14,'20uL Pipette Tips/20230108','Box',950.00,'2027-01-01','COVID-19 Laboratory Network'),(15,'3cc Syringe 23G x 1/20220627','Pieces',0.00,'2027-06-01','National Immunization Program'),(16,'3-in-1 Multi-Function Monitoring System Glucose Hemoblogin and Cholesterol Meter with Strips/NBS23089A95','Kit',0.00,'2025-08-01','Maternal Health Program'),(17,'5% Dextrose in 0.3% Sodium Chloride 500 mL Solution for Infusion (IV Infusion)/320720','Bottles',0.00,'2025-05-01','General Consumption'),(18,'5% Dextrose in 0.9% Sodium Chloride 1 L Solution for Infusion (IV Infusion)/51631','Bottles',0.00,'2025-06-01','General Consumption'),(19,'5% Dextrose in 0.9% Sodium Chloride 1 L Solution for Infusion (IV Infusion)/51634','Bottles',0.00,'2025-09-01','General Consumption'),(20,'5% Dextrose in 0.9% Sodium Chloride 1 L Solution for Infusion (IV Infusion)/51637','Bottles',0.00,'2025-11-01','General Consumption'),(21,'5% Dextrose in 0.9% Sodium Chloride 1 L Solution for Infusion (IV Infusion)/51639','Bottles',0.00,'2026-03-01','General Consumption'),(22,'5% Dextrose in Lactated Ringers Solution 1 L Solution for Infusion (IV Infusion)/22081','Bottles',0.00,'2025-11-01','General Consumption'),(23,'Aciclovir 400 mg tablet/37415','Tablet',8.50,'2025-07-01','National HIV/ AIDS and STI Prevention and Control Program'),(24,'AFB Fast Bacilli Stain Hot Method for TB Microscopy/M24301','Kit',2890.00,'2026-02-01','National Tuberculosis Control Program'),(25,'Albendazole 400mg Tablet/ALB310','Tablet',0.99,'2026-05-01','Integrated Helminth Control Program'),(26,'Alcohol 500mL/N/A','Bottles',156.00,NULL,'Leprosy Control Program'),(27,'Alcohol 70% 1L/0','Bottles',372.00,'2028-09-01','Emerging and Re-emerging Infectious Disease Program'),(28,'Ambroxol 6mg/ml Drops/1773174','Bottles',0.00,'2026-04-01','General Consumption'),(29,'Amlodipine 10 mg Tablet/2401127','Tablet',0.49,'2029-01-01','Integrated Non-Communicable Disease Prevention and Control'),(30,'Amlodipine 5 mg Tablet/GT22535','Tablet',0.37,'2025-11-01','Integrated Non-Communicable Disease Prevention and Control'),(31,'[Co-Amoxiclav] Amoxicillin 400mg + Clavulanic Acid 57mg per 5mL Powder for Oral Suspension 70mL/292403398','Bottles',0.00,'2027-06-01','General Consumption'),(32,'[POP Pill] Lynestrenol 500mcg Tablet (Cycle of 28s)/L2212','Cycle',25.44,'2025-12-01','National Family Planning'),(33,'[POP Pill] Lynestrenol 500mcg Tablet (Cycle of 28s)/L2308','Cycle',25.44,'2026-10-01','National Family Planning'),(34,'0.9% Sodium Chloride 1L Solution for Infusion/81139','Bottles',85.00,'2028-09-01','Food and Waterborne Disease Prevention and Control Program'),(35,'0.9% Sodium Chloride 1L Solution for Infusion/81187','Bottles',85.00,'2029-04-01','General Consumption'),(36,'0.9% Sodium Chloride 1L Solution for Infusion/81190','Bottles',85.00,'2029-04-01','Food and Waterborne Disease Prevention and Control Program'),(37,'0.9% Sodium Chloride Solution for Irrigation 1L/81100','Bottles',55.00,'2028-04-01','Food and Waterborne Disease Prevention and Control Program'),(38,'1250uL Pipette Tips/20230108','Box',1100.00,'2027-01-01','COVID-19 Laboratory Network'),(39,'1cc Syringe 23Gx1/120012210','Box',280.00,'2025-01-01','COVID-19 (PHP Monitoring)'),(40,'1cc Syringe 25G x 1 /20210721','Pieces',0.00,'2026-07-01','National Immunization Program'),(41,'200uL Pipette Tips/20221203','Box',1000.00,'2026-12-01','COVID-19 Laboratory Network'),(42,'20uL Pipette Tips/20230108','Box',950.00,'2027-01-01','COVID-19 Laboratory Network'),(43,'3cc Syringe 23G x 1/20220627','Pieces',0.00,'2027-06-01','National Immunization Program'),(44,'3-in-1 Multi-Function Monitoring System Glucose Hemoblogin and Cholesterol Meter with Strips/NBS23089A95','Kit',0.00,'2025-08-01','Maternal Health Program'),(45,'5% Dextrose in 0.3% Sodium Chloride 500 mL Solution for Infusion (IV Infusion)/320720','Bottles',0.00,'2025-05-01','General Consumption'),(46,'5% Dextrose in 0.9% Sodium Chloride 1 L Solution for Infusion (IV Infusion)/51631','Bottles',0.00,'2025-06-01','General Consumption'),(47,'5% Dextrose in 0.9% Sodium Chloride 1 L Solution for Infusion (IV Infusion)/51634','Bottles',0.00,'2025-09-01','General Consumption'),(48,'5% Dextrose in 0.9% Sodium Chloride 1 L Solution for Infusion (IV Infusion)/51637','Bottles',0.00,'2025-11-01','General Consumption'),(49,'5% Dextrose in 0.9% Sodium Chloride 1 L Solution for Infusion (IV Infusion)/51639','Bottles',0.00,'2026-03-01','General Consumption'),(50,'5% Dextrose in Lactated Ringers Solution 1 L Solution for Infusion (IV Infusion)/22081','Bottles',0.00,'2025-11-01','General Consumption'),(51,'Aciclovir 400 mg tablet/37415','Tablet',8.50,'2025-07-01','National HIV/ AIDS and STI Prevention and Control Program'),(52,'AFB Fast Bacilli Stain Hot Method for TB Microscopy/M24301','Kit',2890.00,'2026-02-01','National Tuberculosis Control Program'),(53,'Albendazole 400mg Tablet/ALB310','Tablet',0.99,'2026-05-01','Integrated Helminth Control Program'),(54,'Alcohol 500mL/N/A','Bottles',156.00,NULL,'Leprosy Control Program'),(55,'Alcohol 70% 1L/0','Bottles',372.00,'2028-09-01','Emerging and Re-emerging Infectious Disease Program'),(56,'Ambroxol 6mg/ml Drops/1773174','Bottles',0.00,'2026-04-01','General Consumption'),(57,'Amlodipine 10 mg Tablet/2401127','Tablet',0.49,'2029-01-01','Integrated Non-Communicable Disease Prevention and Control'),(58,'Amlodipine 5 mg Tablet/GT22535','Tablet',0.37,'2025-11-01','Integrated Non-Communicable Disease Prevention and Control');
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
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recipients`
--

LOCK TABLES `recipients` WRITE;
/*!40000 ALTER TABLE `recipients` DISABLE KEYS */;
INSERT INTO `recipients` VALUES (1,'Aborlan Medicare Hospital','2026-02-11 08:26:42'),(2,'Aborlan RHU','2026-02-11 08:26:42'),(3,'Agape Clinic','2026-02-11 08:26:42'),(4,'Agutaya RHU','2026-02-11 08:26:42'),(5,'Araceli RHU','2026-02-11 08:26:42'),(6,'Araceli-Dumaran District Hospital','2026-02-11 08:26:42'),(7,'Balabac District Hospital','2026-02-11 08:26:42'),(8,'Balabac RHU','2026-02-11 08:26:42'),(9,'Bataraza District Hospital','2026-02-11 08:26:42'),(10,'Bataraza RHU','2026-02-11 08:26:42'),(11,'Brookes Point RHU','2026-02-11 08:26:42'),(12,'Busuanga RHU','2026-02-11 08:26:42'),(13,'Cagayancillo RHU','2026-02-11 08:26:42'),(14,'City Health Office','2026-02-11 08:26:42'),(15,'Coron District Hospital','2026-02-11 08:26:42'),(16,'Coron RHU','2026-02-11 08:26:42'),(17,'Culion RHU','2026-02-11 08:26:42'),(18,'Cuyo District Hospital','2026-02-11 08:26:42'),(19,'Dr. Jose Rizal District Hospital','2026-02-11 08:26:42'),(20,'Dumaran RHU','2026-02-11 08:26:42'),(21,'El Nido Community Hospital','2026-02-11 08:26:42'),(22,'El Nido RHU','2026-02-11 08:26:42'),(23,'Iwahig Penal Colony Clinic','2026-02-11 08:26:42'),(24,'Kalayaan RHU','2026-02-11 08:26:42'),(25,'Linapacan RHU','2026-02-11 08:26:42'),(26,'Magsaysay RHU','2026-02-11 08:26:42'),(27,'Narra Municipal Hospital','2026-02-11 08:26:42'),(28,'Narra RHU','2026-02-11 08:26:42'),(29,'Northern Palawan Provincial Hospital','2026-02-11 08:26:42'),(30,'Ospital ng Palawan','2026-02-11 08:26:42'),(31,'PHO Clinic','2026-02-11 08:26:42'),(32,'PHO TB-DOTS','2026-02-11 08:26:42'),(33,'Provincial Veterinary Office','2026-02-11 08:26:42'),(34,'Quezon Medicare Hospital','2026-02-11 08:26:42'),(35,'Quezon RHU','2026-02-11 08:26:42'),(36,'Rizal RHU','2026-02-11 08:26:42'),(37,'Roxas Medicare Hospital','2026-02-11 08:26:42'),(38,'Roxas RHU','2026-02-11 08:26:42'),(39,'San Vicente District Hospital','2026-02-11 08:26:42'),(40,'San Vicente RHU','2026-02-11 08:26:42'),(41,'Sofronio Española Hospital','2026-02-11 08:26:42'),(42,'Sofronio Española RHU','2026-02-11 08:26:42'),(43,'Southern Palawan Provincial Hospital','2026-02-11 08:26:42'),(44,'Taytay RHU','2026-02-11 08:26:42'),(45,'Vice Governor Francisco F. Ponce De Leon Memorial Hospital','2026-02-11 08:26:42');
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
INSERT INTO `users` VALUES (1,'RJ Coloquit','admin','admin@gmail.com','admin','Admin','Active','2026-02-12 07:11:32');
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

-- Dump completed on 2026-02-12 15:13:38
