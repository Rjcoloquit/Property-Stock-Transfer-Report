-- MySQL dump 10.13  Distrib 8.0.36, for Win64 (x86_64)
--
-- Host: localhost    Database: supply_db
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
  `name_of_office` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `incident_no` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `incident_type` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `incident_datetime` datetime DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `specifics_json` longtext COLLATE utf8mb4_general_ci,
  `persons_involved` text COLLATE utf8mb4_general_ci,
  `remarks` longtext COLLATE utf8mb4_general_ci,
  `action_taken` longtext COLLATE utf8mb4_general_ci,
  `prepared_by_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `prepared_by_designation` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `prepared_by_date` date DEFAULT NULL,
  `submitted_to_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `submitted_to_designation` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `submitted_to_date` date DEFAULT NULL,
  `created_by` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `unit` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `batch_number` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `batch_id` int DEFAULT NULL,
  `quantity` int DEFAULT '0',
  `unit_cost` decimal(10,2) DEFAULT '0.00',
  `program` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `po_no` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `supplier` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `recipient` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ptr_no` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `record_date` date DEFAULT NULL,
  `release_status` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'released',
  `released_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `product_description` text COLLATE utf8mb4_general_ci,
  `uom` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cost_per_unit` decimal(12,2) DEFAULT '0.00',
  `expiry_date` date DEFAULT NULL,
  `program` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `po_no` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `supplier` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `place_of_delivery` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_of_delivery` date DEFAULT NULL,
  `delivery_term` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `payment_term` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `added_by` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `added_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `batch_number` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `stock_quantity` int DEFAULT '0',
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_batches`
--

LOCK TABLES `product_batches` WRITE;
/*!40000 ALTER TABLE `product_batches` DISABLE KEYS */;
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
  `po_no` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `batch_number` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `cost_per_unit` decimal(12,2) DEFAULT '0.00',
  `stock_quantity` int DEFAULT '0',
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_no` (`po_no`),
  KEY `fk_product` (`product_id`),
  CONSTRAINT `fk_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_po_number`
--

LOCK TABLES `product_po_number` WRITE;
/*!40000 ALTER TABLE `product_po_number` DISABLE KEYS */;
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
  `product_description` text COLLATE utf8mb4_general_ci NOT NULL,
  `uom` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `program` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `po_no` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `place_of_delivery` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `supplier` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_of_delivery` date DEFAULT NULL,
  `delivery_term` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `payment_term` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cost_per_unit` decimal(12,2) DEFAULT '0.00',
  `expiry_date` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ptr_print_signatories`
--

DROP TABLE IF EXISTS `ptr_print_signatories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ptr_print_signatories` (
  `ptr_no` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `prepared_by` text COLLATE utf8mb4_general_ci,
  `approved_by` text COLLATE utf8mb4_general_ci,
  `issued_by` text COLLATE utf8mb4_general_ci,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ptr_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ptr_print_signatories`
--

LOCK TABLES `ptr_print_signatories` WRITE;
/*!40000 ALTER TABLE `ptr_print_signatories` DISABLE KEYS */;
/*!40000 ALTER TABLE `ptr_print_signatories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recipients`
--

DROP TABLE IF EXISTS `recipients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recipients` (
  `recipient_id` int NOT NULL AUTO_INCREMENT,
  `recipient_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
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
  `po_contract_no` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `supplier` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `item_description` text COLLATE utf8mb4_general_ci,
  `dosage_form` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `dosage_strength` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `uom` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sku_code` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `entity_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fund_cluster` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `unit_cost` decimal(12,2) DEFAULT NULL,
  `mode_of_procurement` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `end_user_program` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `batch_no` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ledger_rows` longtext COLLATE utf8mb4_general_ci,
  `item_key` varchar(400) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `source_type` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'manual',
  `created_by` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_cards`
--

LOCK TABLES `stock_cards` WRITE;
/*!40000 ALTER TABLE `stock_cards` DISABLE KEYS */;
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
  `full_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('Admin','Encoder') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Encoder',
  `status` enum('Active','Inactive') COLLATE utf8mb4_general_ci DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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

-- Dump completed on 2026-04-14 10:04:11
