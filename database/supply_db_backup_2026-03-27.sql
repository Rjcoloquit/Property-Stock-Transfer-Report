-- MySQL dump 10.13  Distrib 8.0.36, for Win64 (x86_64)
--
-- Host: localhost    Database: supply
-- ------------------------------------------------------
-- Server version	5.7.0
-- Note: Collation changed from utf8mb4_0900_ai_ci to utf8mb4_general_ci for compatibility

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_records`
--

LOCK TABLES `inventory_records` WRITE;
/*!40000 ALTER TABLE `inventory_records` DISABLE KEYS */;
INSERT INTO `inventory_records` VALUES (1,'2028-03-30','Vial','PAEL','456',3,100,45.00,'Daddy\'s Boy','EA456','DOH','Rizal RHU','03/0001','2026-03-24','released','2026-03-24 14:08:09'),(3,'2027-03-16','Vial','PAEL','456',4,50,35.00,'Daddy\'s Boy','PO-342','DOH','Dr. Jose Rizal District Hospital','03/0002','2026-03-24','released','2026-03-24 14:14:26'),(4,'2027-03-16','Vial','PAEL','456',3,400,45.00,'Daddy\'s Boy','EA456','DOH','PHO TB-DOTS','03/0003','2026-03-24','released','2026-03-24 14:17:15');
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_add_history`
--

LOCK TABLES `item_add_history` WRITE;
/*!40000 ALTER TABLE `item_add_history` DISABLE KEYS */;
INSERT INTO `item_add_history` VALUES (1,1,'RJ','Box',30.00,'2028-03-22','Comscie','167','DOH','PHO','2026-03-24','Full','None','admin','2026-03-24 06:04:01'),(2,2,'LAU','Bottle',25.00,'2027-03-18','Bading','0069','DOH','PHO','2026-03-24','Full','None','admin','2026-03-24 06:05:26'),(3,3,'PAEL','Vial',45.00,'2028-03-30','Daddy\'s Boy','EA456','DOH','PHO','2026-03-24','Full','None','admin','2026-03-24 06:07:10'),(4,4,'PAEL','Vial',35.00,'2027-03-16','Daddy\'s Boy','PO-342','DOH','PHO','2026-03-24','Full','None','admin','2026-03-24 06:09:49'),(5,5,'KEN','Bottle',15.00,'2028-03-13','Supot','PO-548','DOH','PHO','2026-03-25','Full','None','admin','2026-03-25 06:00:22');
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_batches`
--

LOCK TABLES `product_batches` WRITE;
/*!40000 ALTER TABLE `product_batches` DISABLE KEYS */;
INSERT INTO `product_batches` VALUES (1,1,'0956',3000,'2028-03-22','2026-03-24 06:04:01','2026-03-24 06:04:01'),(2,2,'6969',150,'2027-03-18','2026-03-24 06:05:26','2026-03-24 06:05:26'),(3,3,'456',6000,'2028-03-30','2026-03-24 06:07:10','2026-03-24 06:07:10'),(4,4,'456',500,'2027-03-16','2026-03-24 06:09:49','2026-03-24 06:09:49'),(5,5,'0023',6900,'2028-03-13','2026-03-25 06:00:22','2026-03-25 06:02:29');
/*!40000 ALTER TABLE `product_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_po_number`
--

DROP TABLE IF EXISTS `product_po_number`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_po_number` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `po_no` varchar(100) NOT NULL,
  `batch_number` varchar(100) NOT NULL,
  `cost_per_unit` decimal(12,2) DEFAULT '0.00',
  `stock_quantity` int DEFAULT '0',
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_no` (`po_no`),
  KEY `fk_product` (`product_id`),
  CONSTRAINT `fk_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_po_number`
--

LOCK TABLES `product_po_number` WRITE;
/*!40000 ALTER TABLE `product_po_number` DISABLE KEYS */;
INSERT INTO `product_po_number` VALUES (1,1,'167','0956',30.00,3000,'2028-03-22','2026-03-24 06:04:01','2026-03-24 06:04:01'),(2,2,'0069','6969',25.00,150,'2027-03-18','2026-03-24 06:05:26','2026-03-24 06:05:26'),(3,3,'EA456','456',45.00,5500,'2028-03-30','2026-03-24 06:07:10','2026-03-24 06:17:15'),(4,4,'PO-342','456',35.00,450,'2027-03-16','2026-03-24 06:09:49','2026-03-24 06:14:26'),(5,5,'PO-548','6969',15.00,6900,'2028-03-13','2026-03-25 06:00:22','2026-03-25 06:00:22'),(9,5,'PO-54800','6969',15.00,6900,'2028-03-13','2026-03-25 06:04:28','2026-03-25 06:04:28');
/*!40000 ALTER TABLE `product_po_number` ENABLE KEYS */;
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
  `program` varchar(255) DEFAULT NULL,
  `po_no` varchar(100) DEFAULT NULL,
  `place_of_delivery` varchar(255) DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `date_of_delivery` date DEFAULT NULL,
  `delivery_term` varchar(100) DEFAULT NULL,
  `payment_term` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cost_per_unit` decimal(12,2) DEFAULT '0.00',
  `expiry_date` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'RJ','Box','Comscie','167','PHO','DOH','2026-03-24','Full','None','2026-03-24 06:04:01','2026-03-24 06:04:01',30.00,'2028-03-22'),(2,'LAU','Bottle','Bading','0069','PHO','DOH','2026-03-24','Full','None','2026-03-24 06:05:26','2026-03-24 06:05:26',25.00,'2027-03-18'),(3,'PAEL','Vial','Daddy\'s Boy','EA456','PHO','DOH','2026-03-24','Full','None','2026-03-24 06:07:10','2026-03-24 06:07:10',45.00,'2028-03-30'),(4,'PAEL','Vial','Daddy\'s Boy','PO-342','PHO','DOH','2026-03-24','Full','None','2026-03-24 06:09:49','2026-03-24 06:09:49',35.00,'2027-03-16'),(5,'KEN-supot','Bottle','Supot','PO-548','PHO','DOH','2026-03-25','Full','None','2026-03-25 06:00:22','2026-03-25 06:06:55',15.00,'2028-03-13');
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
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_cards`
--

LOCK TABLES `stock_cards` WRITE;
/*!40000 ALTER TABLE `stock_cards` DISABLE KEYS */;
INSERT INTO `stock_cards` VALUES (1,'167','DOH','RJ','Box',NULL,'Box',NULL,'PHO','PHO',30.00,NULL,'Comscie','0956','[{\"entry_date\":\"2026-03-24\",\"received\":\"3000\",\"issued\":\"0\",\"balance\":\"3000.00\",\"total_cost\":\"90000.00\",\"ref_no\":\"PO 167\",\"remarks\":\"DOH/Stock received via Manage Items\"}]','rj|box|0956|comscie|167','release','admin','2026-03-24 06:04:01'),(2,'0069','DOH','LAU','Bottle',NULL,'Bottle',NULL,'PHO','PHO',25.00,NULL,'Bading','6969','[{\"entry_date\":\"2026-03-24\",\"received\":\"150\",\"issued\":\"0\",\"balance\":\"150.00\",\"total_cost\":\"3750.00\",\"ref_no\":\"PO 0069\",\"remarks\":\"DOH/Stock received via Manage Items\"}]','lau|bottle|6969|bading|0069','release','admin','2026-03-24 06:05:26'),(3,'EA456','DOH','PAEL','Vial',NULL,'Vial',NULL,'PHO','PHO',45.00,NULL,'Daddy\'s Boy','456','[{\"entry_date\":\"2026-03-24\",\"received\":\"6000\",\"issued\":\"0\",\"balance\":\"6000.00\",\"total_cost\":\"270000.00\",\"ref_no\":\"PO EA456\",\"remarks\":\"DOH/Stock received via Manage Items\"},{\"entry_date\":\"2026-03-24\",\"received\":\"0\",\"issued\":\"100\",\"balance\":\"5900.00\",\"total_cost\":\"4500.00\",\"ref_no\":\"03/0001\",\"remarks\":\"Rizal RHU / DOH\"},{\"entry_date\":\"2026-03-24\",\"received\":\"0\",\"issued\":\"400\",\"balance\":\"5500.00\",\"total_cost\":\"18000.00\",\"ref_no\":\"03/0003\",\"remarks\":\"PHO TB-DOTS / DOH\"}]','pael|vial|456|daddy\'s boy|ea456','release','admin','2026-03-24 06:07:10'),(4,'PO-342','DOH','PAEL','Vial',NULL,'Vial',NULL,'PHO','PHO',35.00,NULL,'Daddy\'s Boy','456','[{\"entry_date\":\"2026-03-24\",\"received\":\"500\",\"issued\":\"0\",\"balance\":\"500.00\",\"total_cost\":\"17500.00\",\"ref_no\":\"PO PO-342\",\"remarks\":\"DOH/Stock received via Manage Items\"},{\"entry_date\":\"2026-03-24\",\"received\":\"0\",\"issued\":\"50\",\"balance\":\"450.00\",\"total_cost\":\"1750.00\",\"ref_no\":\"03/0002\",\"remarks\":\"Dr. Jose Rizal District Hospital / DOH\"}]','pael|vial|456|daddy\'s boy|po-342','release','admin','2026-03-24 06:09:49'),(5,'PO-548','DOH','KEN','Bottle',NULL,'Bottle',NULL,'PHO','PHO',15.00,NULL,'Supot','6969','[{\"entry_date\":\"2026-03-25\",\"received\":\"6900\",\"issued\":\"0\",\"balance\":\"6900.00\",\"total_cost\":\"103500.00\",\"ref_no\":\"PO PO-548\",\"remarks\":\"DOH/Stock received via Manage Items\"}]','ken|bottle|6969|supot|po-548','release','admin','2026-03-25 06:00:22');
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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

-- Dump completed on 2026-03-27 14:36:31
-- Fixed: replaced utf8mb4_0900_ai_ci with utf8mb4_general_ci for MySQL 5.7 / MariaDB compatibility
