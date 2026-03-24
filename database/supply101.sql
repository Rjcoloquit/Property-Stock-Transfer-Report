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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
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
  `batch_number` varchar(100) DEFAULT NULL,
  `batch_id` int DEFAULT NULL,
  `quantity` int DEFAULT '0',
  `unit_cost` decimal(10,2) DEFAULT '0.00',
  `program` varchar(255) DEFAULT NULL,
  `po_no` varchar(100) DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `recipient` varchar(255) DEFAULT NULL,
  `ptr_no` varchar(50) DEFAULT NULL,
  `record_date` date DEFAULT NULL,
  `release_status` varchar(20) NOT NULL DEFAULT 'released',
  `released_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_records`
--

LOCK TABLES `inventory_records` WRITE;
/*!40000 ALTER TABLE `inventory_records` DISABLE KEYS */;
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
  `po_no` varchar(100) DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `place_of_delivery` varchar(255) DEFAULT NULL,
  `date_of_delivery` date DEFAULT NULL,
  `delivery_term` varchar(255) DEFAULT NULL,
  `payment_term` varchar(255) DEFAULT NULL,
  `added_by` varchar(150) DEFAULT NULL,
  `added_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_add_history`
--

LOCK TABLES `item_add_history` WRITE;
/*!40000 ALTER TABLE `item_add_history` DISABLE KEYS */;
INSERT INTO `item_add_history` VALUES (5,1,'Influenza Vaccine (Flu) Vaccine 10 Doses per Vial','Vial',1931.00,'2025-01-28','National Immunization Program','CHD4M-PTR-2025-01-00017','DOH','PHO Palawan','2025-01-20','Full','None','admin','2026-03-13 01:00:24'),(6,1,'[BCG Diluent] Bacille Calmette-Guerin Vaccine Diluent','Ampule',1931.00,'2025-01-28','National Immunization Program','CHD4M-PTR-2025-01-00017','DOH','PHO Palawan','2025-01-20','Full','None','admin','2026-03-16 07:06:43'),(7,2,'[BCG Vaccine] Bacillus Calmette Guerin Vaccine 20-Dose','Vial',139.00,'2025-11-30','National Immunization Program','CHD4M-PTR-2024-11-01348','DOH','PHO Palawan','2025-01-20','Full','None','admin','2026-03-16 07:10:18'),(8,3,'[COC Pill] Levonorgestrel 150mcg + Ethinylestradiol 30mcg + Ferrous Fumarate 60mg Film-Coated Tablet','Cycle',627.80,'2027-04-30','Responsible Parenthood and Reproductive Health Program','CHD4M-PTR-2024-11-01348','DOH','PHO Palawan','2025-01-20','Full','None','admin','2026-03-16 07:14:52');
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_batches`
--

LOCK TABLES `product_batches` WRITE;
/*!40000 ALTER TABLE `product_batches` DISABLE KEYS */;
INSERT INTO `product_batches` VALUES (1,1,'0704S4016',500,'2025-01-28','2026-03-16 07:06:43','2026-03-16 07:06:43'),(2,2,'0375MA079',2300,'2025-11-30','2026-03-16 07:10:18','2026-03-16 07:10:18'),(3,3,'PT-24-031',70,'2027-04-30','2026-03-16 07:14:52','2026-03-16 07:14:52');
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'[BCG Diluent] Bacille Calmette-Guerin Vaccine Diluent','Ampule',1931.00,'National Immunization Program','CHD4M-PTR-2025-01-00017','PHO Palawan','DOH','2025-01-20','Full','None','2026-03-16 07:06:43','2026-03-16 07:06:43'),(2,'[BCG Vaccine] Bacillus Calmette Guerin Vaccine 20-Dose','Vial',139.00,'National Immunization Program','CHD4M-PTR-2024-11-01348','PHO Palawan','DOH','2025-01-20','Full','None','2026-03-16 07:10:18','2026-03-16 07:10:18'),(3,'[COC Pill] Levonorgestrel 150mcg + Ethinylestradiol 30mcg + Ferrous Fumarate 60mg Film-Coated Tablet','Cycle',627.80,'Responsible Parenthood and Reproductive Health Program','CHD4M-PTR-2024-11-01348','PHO Palawan','DOH','2025-01-20','Full','None','2026-03-16 07:14:52','2026-03-16 07:14:52');
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
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
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
-- Table structure for table `stock_cards`
--

DROP TABLE IF EXISTS `stock_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_cards` (
  `id` int NOT NULL AUTO_INCREMENT,
  `po_contract_no` varchar(255) DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `item_description` text,
  `dosage_form` varchar(255) DEFAULT NULL,
  `dosage_strength` varchar(255) DEFAULT NULL,
  `uom` varchar(100) DEFAULT NULL,
  `sku_code` varchar(150) DEFAULT NULL,
  `entity_name` varchar(255) DEFAULT NULL,
  `fund_cluster` varchar(255) DEFAULT NULL,
  `unit_cost` decimal(12,2) DEFAULT NULL,
  `mode_of_procurement` varchar(255) DEFAULT NULL,
  `end_user_program` varchar(255) DEFAULT NULL,
  `batch_no` varchar(120) DEFAULT NULL,
  `ledger_rows` longtext,
  `item_key` varchar(400) DEFAULT NULL,
  `source_type` varchar(30) NOT NULL DEFAULT 'manual',
  `created_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_cards`
--

LOCK TABLES `stock_cards` WRITE;
/*!40000 ALTER TABLE `stock_cards` DISABLE KEYS */;
INSERT INTO `stock_cards` VALUES (7,'CHD4M-PTR-2025-07-00534','DOH','Betamethasone 1mg/g topical 5g cream tube box','Tubes',NULL,'Tubes',NULL,'PHO','PHO',32.50,NULL,NULL,'BV23004','[{\"entry_date\":\"2025-08-01\",\"received\":\"500\",\"issued\":\"0\",\"balance\":\"500.00\",\"total_cost\":\"16250.00\",\"ref_no\":\"PO CHD4M-PTR-2025-07-00534\",\"remarks\":\"DOH/Stock received via Manage Items\"},{\"entry_date\":\"2025-08-01\",\"received\":\"500\",\"issued\":\"0\",\"balance\":\"1000.00\",\"total_cost\":\"32500.00\",\"ref_no\":\"PO CHD4M-PTR-2025-07-00534\",\"remarks\":\"DOH/Stock received via Manage Items\"}]','betamethasone 1mg/g topical 5g cream tube box|tubes|bv23004','release','admin','2026-03-11 08:04:28'),(8,'CHD4M-PTR-2025-07-00471','DOH','Glass Ionomer for Atraumatic Restorative Treatment','Kit',NULL,'Kit',NULL,'PHO','PHO',3348.00,NULL,NULL,'122409-7','[{\"entry_date\":\"2025-08-01\",\"received\":\"30\",\"issued\":\"0\",\"balance\":\"30.00\",\"total_cost\":\"100440.00\",\"ref_no\":\"PO CHD4M-PTR-2025-07-00471\",\"remarks\":\"DOH/Stock received via Manage Items\"},{\"entry_date\":\"2025-08-01\",\"received\":\"30\",\"issued\":\"0\",\"balance\":\"60.00\",\"total_cost\":\"200880.00\",\"ref_no\":\"PO CHD4M-PTR-2025-07-00471\",\"remarks\":\"DOH/Stock received via Manage Items\"}]','glass ionomer for atraumatic restorative treatment|kit|122409-7','release','admin','2026-03-11 08:07:19'),(9,'CHD4M-PTR-2025-07-00471','DOH','Glass Ionomer Sealant','Kit',NULL,'Kit',NULL,'PHO','PHO',6465.00,NULL,NULL,'2412041','[{\"entry_date\":\"2025-08-01\",\"received\":\"30\",\"issued\":\"0\",\"balance\":\"30.00\",\"total_cost\":\"193950.00\",\"ref_no\":\"PO CHD4M-PTR-2025-07-00471\",\"remarks\":\"DOH/Stock received via Manage Items\"},{\"entry_date\":\"2025-08-01\",\"received\":\"30\",\"issued\":\"0\",\"balance\":\"60.00\",\"total_cost\":\"387900.00\",\"ref_no\":\"PO CHD4M-PTR-2025-07-00471\",\"remarks\":\"DOH/Stock received via Manage Items\"}]','glass ionomer sealant|kit|2412041','release','admin','2026-03-11 08:09:01'),(10,'CHD4M-PTR-2025-07-00531','DOH','Ice Pack 0.3L','Pieces',NULL,'Pieces',NULL,'PHO','PHO',0.00,NULL,NULL,'N/A','[{\"entry_date\":\"2025-08-01\",\"received\":\"10\",\"issued\":\"0\",\"balance\":\"10.00\",\"total_cost\":\"0.00\",\"ref_no\":\"PO CHD4M-PTR-2025-07-00531\",\"remarks\":\"DOH/Stock received via Manage Items\"},{\"entry_date\":\"2025-08-01\",\"received\":\"90\",\"issued\":\"0\",\"balance\":\"100.00\",\"total_cost\":\"0.00\",\"ref_no\":\"PO CHD4M-PTR-2025-07-00531\",\"remarks\":\"DOH/Stock received via Manage Items\"}]','ice pack 0.3l|pieces|n/a','release','admin','2026-03-11 08:12:38'),(11,'CHD4M-PTR-2025-07-00531','DOH','Ice Pack 0.4L','Pieces',NULL,'Pieces',NULL,'PHO','PHO',0.00,NULL,NULL,'N/A','[{\"entry_date\":\"2025-08-01\",\"received\":\"65\",\"issued\":\"0\",\"balance\":\"65.00\",\"total_cost\":\"0.00\",\"ref_no\":\"PO CHD4M-PTR-2025-07-00531\",\"remarks\":\"DOH/Stock received via Manage Items\"}]','ice pack 0.4l|pieces|n/a','release','admin','2026-03-11 08:13:06'),(12,'CHD4M-PTR-2025-01-00017','DOH','Influenza Vaccine (Flu) Vaccine 10 Doses per Vial','Vial',NULL,'Vial',NULL,'PHO','PHO',1931.00,NULL,'National Immunization Program','U8319AA','[{\"entry_date\":\"2025-01-20\",\"received\":\"500\",\"issued\":\"0\",\"balance\":\"500.00\",\"total_cost\":\"965500.00\",\"ref_no\":\"PO CHD4M-PTR-2025-01-00017\",\"remarks\":\"DOH/Stock received via Manage Items\"}]','influenza vaccine (flu) vaccine 10 doses per vial|vial|u8319aa','release','admin','2026-03-13 01:00:24'),(13,'CHD4M-PTR-2025-01-00017','DOH','[BCG Diluent] Bacille Calmette-Guerin Vaccine Diluent','Ampule',NULL,'Ampule',NULL,'PHO','PHO',1931.00,NULL,'National Immunization Program','0704S4016','[{\"entry_date\":\"2025-01-20\",\"received\":\"500\",\"issued\":\"0\",\"balance\":\"500.00\",\"total_cost\":\"965500.00\",\"ref_no\":\"PO CHD4M-PTR-2025-01-00017\",\"remarks\":\"DOH/Stock received via Manage Items\"}]','[bcg diluent] bacille calmette-guerin vaccine diluent|ampule|0704s4016|national immunization program|chd4m-ptr-2025-01-00017','release','admin','2026-03-16 07:06:43'),(14,'CHD4M-PTR-2024-11-01348','DOH','[BCG Vaccine] Bacillus Calmette Guerin Vaccine 20-Dose','Vial',NULL,'Vial',NULL,'PHO','PHO',139.00,NULL,'National Immunization Program','0375MA079','[{\"entry_date\":\"2025-01-20\",\"received\":\"2300\",\"issued\":\"0\",\"balance\":\"2300.00\",\"total_cost\":\"319700.00\",\"ref_no\":\"PO CHD4M-PTR-2024-11-01348\",\"remarks\":\"DOH/Stock received via Manage Items\"}]','[bcg vaccine] bacillus calmette guerin vaccine 20-dose|vial|0375ma079|national immunization program|chd4m-ptr-2024-11-01348','release','admin','2026-03-16 07:10:18'),(15,'CHD4M-PTR-2024-11-01348','DOH','[COC Pill] Levonorgestrel 150mcg + Ethinylestradiol 30mcg + Ferrous Fumarate 60mg Film-Coated Tablet','Cycle',NULL,'Cycle',NULL,'PHO','PHO',627.80,NULL,'Responsible Parenthood and Reproductive Health Program','PT-24-031','[{\"entry_date\":\"2025-01-20\",\"received\":\"70\",\"issued\":\"0\",\"balance\":\"70.00\",\"total_cost\":\"43946.00\",\"ref_no\":\"PO CHD4M-PTR-2024-11-01348\",\"remarks\":\"DOH/Stock received via Manage Items\"}]','[coc pill] levonorgestrel 150mcg + ethinylestradiol 30mcg + ferrous fumarate 60mg film-coated tablet|cycle|pt-24-031|responsible parenthood and reproductive health program|chd4m-ptr-2024-11-01348','release','admin','2026-03-16 07:14:52');
/*!40000 ALTER TABLE `stock_cards` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'rj coloquit','admin',NULL,'$2y$10$0gvl9fzphWHEsE8./WBTn.XCnLBh7jTEi4Uo3i81s7oaCA9zG7142','Encoder','Active','2026-02-27 02:35:55'),(2,'Richard Roy','restetutoputo@gmail.com','restetutoputo@gmail.com','$2y$10$C15i9MrLwuLWS6vXyYVbwuCW./RSbyWtCUZdK0U5OTTofB13Kp02O','Encoder','Active','2026-02-27 05:46:14');
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

-- Dump completed on 2026-03-16 16:07:48
