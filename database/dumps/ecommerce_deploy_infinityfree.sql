-- InfinityFree-compatible MySQL/MariaDB dump
-- Generated for phpMyAdmin import on InfinityFree free hosting.
--
-- Import steps:
--   1. Create an empty database in the InfinityFree control panel
--      (SQL schema-create / USE statements are stripped; pick the DB in phpMyAdmin).
--   2. Open phpMyAdmin -> select that database (left sidebar).
--   3. Import this file (or use BigDump if phpMyAdmin times out).
--   4. Point Laravel .env at the InfinityFree DB host/user/name/password.
--
-- Changes applied vs local MariaDB dump:
--   - Removed schema-create / USE / DEFINER statements
--   - Replaced MariaDB JSON CHECK columns with plain LONGTEXT
--   - Normalized MySQL-8-only collations to utf8mb4_unicode_ci
--   - Normalized timestamp defaults to CURRENT_TIMESTAMP
--   - Split large multi-row INSERT batches
--   - Cleared sessions rows (host-specific)
--
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
-- Table structure for table `addresses`
--

DROP TABLE IF EXISTS `addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `addresses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `label` varchar(255) NOT NULL DEFAULT 'Home',
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `line1` varchar(255) NOT NULL,
  `line2` varchar(255) DEFAULT NULL,
  `city` varchar(255) NOT NULL,
  `region` varchar(255) DEFAULT NULL,
  `postal_code` varchar(255) DEFAULT NULL,
  `country` varchar(255) NOT NULL DEFAULT 'Tanzania',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `addresses_user_id_foreign` (`user_id`),
  CONSTRAINT `addresses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `addresses`
--

LOCK TABLES `addresses` WRITE;
/*!40000 ALTER TABLE `addresses` DISABLE KEYS */;
/*!40000 ALTER TABLE `addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `resource_type` varchar(80) DEFAULT NULL,
  `resource_id` varchar(64) DEFAULT NULL,
  `old_values` longtext DEFAULT NULL,
  `new_values` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `request_id` varchar(64) DEFAULT NULL,
  `result` varchar(32) NOT NULL DEFAULT 'success',
  `reason` varchar(255) DEFAULT NULL,
  `category` varchar(32) NOT NULL DEFAULT 'business',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_logs_actor_user_id_created_at_index` (`actor_user_id`,`created_at`),
  KEY `audit_logs_action_index` (`action`),
  KEY `audit_logs_resource_type_index` (`resource_type`),
  KEY `audit_logs_resource_id_index` (`resource_id`),
  KEY `audit_logs_request_id_index` (`request_id`),
  KEY `audit_logs_result_index` (`result`),
  KEY `audit_logs_category_index` (`category`),
  CONSTRAINT `audit_logs_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'USER_ROLE_CHANGED','user','5','{\"roles\":[\"super_admin\"],\"legacy_role\":\"admin\"}','{\"roles\":[\"admin\"],\"legacy_role\":\"admin\"}','127.0.0.1','Symfony','b546bb88-d2de-412a-ab56-f7ba653ab395','success',NULL,'security','2026-08-04 01:11:31'),(2,5,'LOGIN_SUCCESS','user','5',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2fc9b33d-21be-4f85-b93e-8172fef54310','success',NULL,'security','2026-08-04 04:16:44'),(3,1,'LOGIN_SUCCESS','user','1',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','a415f016-6393-471f-acc7-743669e8e345','success',NULL,'security','2026-08-04 04:17:59'),(4,1,'STEP_UP_REQUIRED',NULL,NULL,NULL,'{\"path\":\"admin\\/users\\/3\",\"method\":\"PUT\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','1bac962b-1521-41db-8d71-252d056a5a30','recorded',NULL,'security','2026-08-04 04:19:37'),(5,1,'STEP_UP_CONFIRMED','user','1',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','3e5fa630-07f4-4b2d-a46f-588c76fb5187','success',NULL,'security','2026-08-04 04:19:47'),(6,1,'STEP_UP_CONFIRMED','user','1',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','e998cc90-3efe-4bf7-b853-431129575921','success',NULL,'security','2026-08-04 04:22:12'),(7,1,'MFA_ENROLLMENT_STARTED','user','1',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','c4feaae2-c10a-4406-8db0-6cd507722c7c','success',NULL,'security','2026-08-04 04:22:37'),(8,1,'ORDER_CONFIRMED','order','2','{\"status\":\"pending\"}','{\"status\":\"confirmed\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','20241c88-6227-484a-83fc-15f85ba87fb5','success',NULL,'business','2026-08-04 05:04:23');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_slug_unique` (`slug`),
  KEY `categories_parent_id_foreign` (`parent_id`),
  CONSTRAINT `categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,NULL,'Electronics','electronics','devices','https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=900&q=80','Phones, laptops, audio & gadgets',1,1,'2026-07-24 03:58:27','2026-07-24 03:58:27'),(2,NULL,'Fashion','fashion','apparel','https://images.unsplash.com/photo-1445205170230-053b83016050?auto=format&fit=crop&w=900&q=80','Apparel, sneakers & accessories',2,1,'2026-07-24 03:58:27','2026-07-24 03:58:27'),(3,NULL,'Home & Living','home','home','https://images.unsplash.com/photo-1586023492125-27b2c045efd7?auto=format&fit=crop&w=900&q=80','Furniture and everyday essentials',3,1,'2026-07-24 03:58:27','2026-07-24 03:58:27'),(4,NULL,'Beauty','beauty','spa','https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=900&q=80','Skincare, fragrance & wellness',4,1,'2026-07-24 03:58:27','2026-07-24 03:58:27'),(5,NULL,'Sports','sports','sports','https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=900&q=80','Fitness gear and outdoor kit',5,1,'2026-07-24 03:58:27','2026-07-24 03:58:27');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chargebacks`
--

DROP TABLE IF EXISTS `chargebacks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chargebacks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(40) NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `payment_transaction_id` bigint(20) unsigned DEFAULT NULL,
  `vendor_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'TZS',
  `status` varchar(32) NOT NULL DEFAULT 'received',
  `provider` varchar(64) NOT NULL DEFAULT 'internal',
  `provider_reference` varchar(128) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `settlement_hold_id` bigint(20) unsigned DEFAULT NULL,
  `ledger_transaction_id` bigint(20) unsigned DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `received_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `resolved_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chargebacks_reference_unique` (`reference`),
  UNIQUE KEY `chargebacks_provider_provider_reference_unique` (`provider`,`provider_reference`),
  KEY `chargebacks_order_id_foreign` (`order_id`),
  KEY `chargebacks_payment_transaction_id_foreign` (`payment_transaction_id`),
  KEY `chargebacks_vendor_id_foreign` (`vendor_id`),
  KEY `chargebacks_ledger_transaction_id_foreign` (`ledger_transaction_id`),
  KEY `chargebacks_created_by_foreign` (`created_by`),
  KEY `chargebacks_resolved_by_foreign` (`resolved_by`),
  KEY `chargebacks_status_order_id_index` (`status`,`order_id`),
  KEY `chargebacks_settlement_hold_id_foreign` (`settlement_hold_id`),
  CONSTRAINT `chargebacks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chargebacks_ledger_transaction_id_foreign` FOREIGN KEY (`ledger_transaction_id`) REFERENCES `ledger_transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chargebacks_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chargebacks_payment_transaction_id_foreign` FOREIGN KEY (`payment_transaction_id`) REFERENCES `payment_transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chargebacks_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chargebacks_settlement_hold_id_foreign` FOREIGN KEY (`settlement_hold_id`) REFERENCES `settlement_holds` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chargebacks_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chargebacks`
--

LOCK TABLES `chargebacks` WRITE;
/*!40000 ALTER TABLE `chargebacks` DISABLE KEYS */;
/*!40000 ALTER TABLE `chargebacks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `checkout_idempotency_keys`
--

DROP TABLE IF EXISTS `checkout_idempotency_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `checkout_idempotency_keys` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(64) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `consumed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `checkout_idempotency_keys_token_unique` (`token`),
  KEY `checkout_idempotency_keys_order_id_foreign` (`order_id`),
  KEY `checkout_idempotency_keys_user_id_consumed_at_index` (`user_id`,`consumed_at`),
  KEY `checkout_idempotency_keys_consumed_at_index` (`consumed_at`),
  CONSTRAINT `checkout_idempotency_keys_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `checkout_idempotency_keys_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `checkout_idempotency_keys`
--

LOCK TABLES `checkout_idempotency_keys` WRITE;
/*!40000 ALTER TABLE `checkout_idempotency_keys` DISABLE KEYS */;
/*!40000 ALTER TABLE `checkout_idempotency_keys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commission_configs`
--

DROP TABLE IF EXISTS `commission_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `commission_configs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scope` varchar(32) NOT NULL,
  `scope_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(32) NOT NULL DEFAULT 'percentage',
  `rate` decimal(8,4) NOT NULL DEFAULT 0.1000,
  `fixed_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commission_configs_scope_scope_id_unique` (`scope`,`scope_id`),
  KEY `commission_configs_updated_by_foreign` (`updated_by`),
  CONSTRAINT `commission_configs_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commission_configs`
--

LOCK TABLES `commission_configs` WRITE;
/*!40000 ALTER TABLE `commission_configs` DISABLE KEYS */;
INSERT INTO `commission_configs` VALUES (1,'platform',NULL,'percentage',0.1000,0.00,1,NULL,'2026-08-04 00:09:02','2026-08-04 00:09:02');
/*!40000 ALTER TABLE `commission_configs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coupons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'percent',
  `value` decimal(10,2) NOT NULL,
  `min_order` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupons_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupons`
--

LOCK TABLES `coupons` WRITE;
/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
INSERT INTO `coupons` VALUES (1,'SANA10','percent',10.00,50000.00,'2026-10-24 03:58:28',1,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(2,'FLASH50K','fixed',50000.00,300000.00,'2026-08-24 03:58:28',1,'2026-07-24 03:58:28','2026-07-24 03:58:28');
/*!40000 ALTER TABLE `coupons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dispute_messages`
--

DROP TABLE IF EXISTS `dispute_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dispute_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dispute_id` bigint(20) unsigned NOT NULL,
  `author_id` bigint(20) unsigned NOT NULL,
  `author_role` varchar(32) NOT NULL,
  `body` text NOT NULL,
  `evidence_ref` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dispute_messages_author_id_foreign` (`author_id`),
  KEY `dispute_messages_dispute_id_index` (`dispute_id`),
  CONSTRAINT `dispute_messages_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dispute_messages_dispute_id_foreign` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dispute_messages`
--

LOCK TABLES `dispute_messages` WRITE;
/*!40000 ALTER TABLE `dispute_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `dispute_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `disputes`
--

DROP TABLE IF EXISTS `disputes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disputes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(40) NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `order_item_id` bigint(20) unsigned DEFAULT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `vendor_id` bigint(20) unsigned NOT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'open',
  `subject` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `resolution_code` varchar(40) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `settlement_hold_id` bigint(20) unsigned DEFAULT NULL,
  `return_request_id` bigint(20) unsigned DEFAULT NULL,
  `opened_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `resolved_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `disputes_reference_unique` (`reference`),
  KEY `disputes_order_id_foreign` (`order_id`),
  KEY `disputes_order_item_id_foreign` (`order_item_id`),
  KEY `disputes_return_request_id_foreign` (`return_request_id`),
  KEY `disputes_resolved_by_foreign` (`resolved_by`),
  KEY `disputes_customer_id_status_index` (`customer_id`,`status`),
  KEY `disputes_vendor_id_status_index` (`vendor_id`,`status`),
  KEY `disputes_settlement_hold_id_foreign` (`settlement_hold_id`),
  CONSTRAINT `disputes_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `disputes_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `disputes_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `disputes_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `disputes_return_request_id_foreign` FOREIGN KEY (`return_request_id`) REFERENCES `return_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `disputes_settlement_hold_id_foreign` FOREIGN KEY (`settlement_hold_id`) REFERENCES `settlement_holds` (`id`) ON DELETE SET NULL,
  CONSTRAINT `disputes_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disputes`
--

LOCK TABLES `disputes` WRITE;
/*!40000 ALTER TABLE `disputes` DISABLE KEYS */;
/*!40000 ALTER TABLE `disputes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fulfillment_status_histories`
--

DROP TABLE IF EXISTS `fulfillment_status_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fulfillment_status_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_item_id` bigint(20) unsigned NOT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `from_status` varchar(32) NOT NULL,
  `to_status` varchar(32) NOT NULL,
  `actor_role` varchar(32) DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fulfillment_status_histories_order_item_id_created_at_index` (`order_item_id`,`created_at`),
  KEY `fulfillment_status_histories_actor_user_id_index` (`actor_user_id`),
  CONSTRAINT `fulfillment_status_histories_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fulfillment_status_histories_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fulfillment_status_histories`
--

LOCK TABLES `fulfillment_status_histories` WRITE;
/*!40000 ALTER TABLE `fulfillment_status_histories` DISABLE KEYS */;
INSERT INTO `fulfillment_status_histories` VALUES (1,2,1,'pending','confirmed','admin',NULL,'2026-08-04 05:03:50','2026-08-04 05:03:50'),(2,1,1,'pending','confirmed','admin',NULL,'2026-08-04 05:04:58','2026-08-04 05:04:58');
/*!40000 ALTER TABLE `fulfillment_status_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_movements`
--

DROP TABLE IF EXISTS `inventory_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_movements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(32) NOT NULL,
  `quantity_before` int(11) NOT NULL,
  `quantity_delta` int(11) NOT NULL,
  `quantity_after` int(11) NOT NULL,
  `reserved_before` int(10) unsigned NOT NULL DEFAULT 0,
  `reserved_after` int(10) unsigned NOT NULL DEFAULT 0,
  `reason` varchar(500) NOT NULL,
  `reference_type` varchar(64) DEFAULT NULL,
  `reference_id` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `inventory_movements_actor_user_id_foreign` (`actor_user_id`),
  KEY `inventory_movements_product_created_index` (`product_id`,`created_at`),
  CONSTRAINT `inventory_movements_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_movements_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_movements`
--

LOCK TABLES `inventory_movements` WRITE;
/*!40000 ALTER TABLE `inventory_movements` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_movements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ledger_accounts`
--

DROP TABLE IF EXISTS `ledger_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ledger_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(32) NOT NULL,
  `vendor_id` bigint(20) unsigned DEFAULT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'TZS',
  `is_system` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ledger_accounts_code_unique` (`code`),
  KEY `ledger_accounts_vendor_id_foreign` (`vendor_id`),
  KEY `ledger_accounts_type_vendor_id_index` (`type`,`vendor_id`),
  CONSTRAINT `ledger_accounts_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ledger_accounts`
--

LOCK TABLES `ledger_accounts` WRITE;
/*!40000 ALTER TABLE `ledger_accounts` DISABLE KEYS */;
INSERT INTO `ledger_accounts` VALUES (1,'PLATFORM_CASH','Platform Cash','asset',NULL,'TZS',1,'2026-08-04 00:09:02','2026-08-04 00:09:02'),(2,'VENDOR_PAYABLE','Vendor Payable Clearing','liability',NULL,'TZS',1,'2026-08-04 00:09:02','2026-08-04 00:09:02'),(3,'PLATFORM_REVENUE','Platform Commission Revenue','revenue',NULL,'TZS',1,'2026-08-04 00:09:02','2026-08-04 00:09:02'),(4,'REFUND_LIABILITY','Refund Liability','liability',NULL,'TZS',1,'2026-08-04 00:09:02','2026-08-04 00:09:02'),(5,'PAYOUT_CLEARING','Payout Clearing','liability',NULL,'TZS',1,'2026-08-04 00:09:02','2026-08-04 00:09:02');
/*!40000 ALTER TABLE `ledger_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ledger_entries`
--

DROP TABLE IF EXISTS `ledger_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ledger_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ledger_transaction_id` bigint(20) unsigned NOT NULL,
  `ledger_account_id` bigint(20) unsigned NOT NULL,
  `debit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'TZS',
  `vendor_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ledger_entries_ledger_transaction_id_foreign` (`ledger_transaction_id`),
  KEY `ledger_entries_vendor_id_foreign` (`vendor_id`),
  KEY `ledger_entries_ledger_account_id_vendor_id_index` (`ledger_account_id`,`vendor_id`),
  CONSTRAINT `ledger_entries_ledger_account_id_foreign` FOREIGN KEY (`ledger_account_id`) REFERENCES `ledger_accounts` (`id`),
  CONSTRAINT `ledger_entries_ledger_transaction_id_foreign` FOREIGN KEY (`ledger_transaction_id`) REFERENCES `ledger_transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ledger_entries_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ledger_entries`
--

LOCK TABLES `ledger_entries` WRITE;
/*!40000 ALTER TABLE `ledger_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `ledger_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ledger_transactions`
--

DROP TABLE IF EXISTS `ledger_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ledger_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(64) NOT NULL,
  `idempotency_key` varchar(128) DEFAULT NULL,
  `type` varchar(64) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'TZS',
  `description` varchar(500) DEFAULT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `payment_transaction_id` bigint(20) unsigned DEFAULT NULL,
  `payment_refund_id` bigint(20) unsigned DEFAULT NULL,
  `vendor_id` bigint(20) unsigned DEFAULT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `reverses_transaction_id` bigint(20) unsigned DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `posted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ledger_transactions_reference_unique` (`reference`),
  UNIQUE KEY `ledger_transactions_idempotency_key_unique` (`idempotency_key`),
  KEY `ledger_transactions_payment_transaction_id_foreign` (`payment_transaction_id`),
  KEY `ledger_transactions_payment_refund_id_foreign` (`payment_refund_id`),
  KEY `ledger_transactions_vendor_id_foreign` (`vendor_id`),
  KEY `ledger_transactions_actor_user_id_foreign` (`actor_user_id`),
  KEY `ledger_transactions_reverses_transaction_id_foreign` (`reverses_transaction_id`),
  KEY `ledger_transactions_type_posted_at_index` (`type`,`posted_at`),
  KEY `ledger_transactions_order_id_index` (`order_id`),
  CONSTRAINT `ledger_transactions_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ledger_transactions_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ledger_transactions_payment_refund_id_foreign` FOREIGN KEY (`payment_refund_id`) REFERENCES `payment_refunds` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ledger_transactions_payment_transaction_id_foreign` FOREIGN KEY (`payment_transaction_id`) REFERENCES `payment_transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ledger_transactions_reverses_transaction_id_foreign` FOREIGN KEY (`reverses_transaction_id`) REFERENCES `ledger_transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ledger_transactions_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ledger_transactions`
--

LOCK TABLES `ledger_transactions` WRITE;
/*!40000 ALTER TABLE `ledger_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `ledger_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_05_06_073940_create_vendors_table',1),(5,'2026_05_06_073950_create_orders_table',1),(6,'2026_05_06_073950_create_products_table',1),(7,'2026_05_06_073951_create_order_items_table',1),(8,'2026_06_14_160225_add_role_to_users_table',1),(9,'2026_06_14_213810_add_role_to_users_table',1),(10,'2026_07_24_100000_enhance_marketplace_schema',1),(11,'2026_08_02_120000_add_remember_token_to_users_table',2),(12,'2026_08_02_183000_add_user_id_to_vendors_table',2),(13,'2026_08_02_190000_add_fulfillment_status_to_order_items_table',2),(14,'2026_08_02_200000_create_notifications_table',2),(15,'2026_08_02_200100_create_fulfillment_status_histories_table',2),(16,'2026_08_02_210000_add_payment_status_to_orders_table',2),(17,'2026_08_02_210100_create_payment_transactions_table',2),(18,'2026_08_02_210200_create_payment_status_histories_table',2),(19,'2026_08_02_220000_create_checkout_idempotency_keys_table',2),(20,'2026_08_02_230000_create_payment_notification_receipts_table',2),(21,'2026_08_03_100000_create_rbac_and_audit_tables',2),(22,'2026_08_03_100100_add_review_moderation_fields',2),(23,'2026_08_03_120000_add_mfa_columns_to_users_table',2),(24,'2026_08_03_180000_add_catalog_ops_and_inventory_movements',2),(25,'2026_08_03_200000_phase4_vendor_lifecycle_and_order_snapshots',2);
INSERT INTO `migrations` VALUES (26,'2026_08_03_210000_phase5_payment_attempts_refunds_reconciliation',2),(27,'2026_08_03_220000_phase6_finance_ledger_and_payouts',2),(28,'2026_08_03_230000_phase7_marketplace_operations',2);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES ('4928fc53-f57e-44fd-bc30-810f4bd050f9','App\\Notifications\\CustomerOrderItemFulfillmentUpdated','App\\Models\\User',5,'{\"title\":\"Order SN-YEPIKLGP: Confirmed\",\"body\":\"Your order item \\\"Shark\\\" has been confirmed.\",\"order_id\":1,\"order_item_id\":1,\"fulfillment_status\":\"confirmed\",\"previous_status\":\"pending\"}',NULL,'2026-08-04 05:04:58','2026-08-04 05:04:58'),('d7916dad-be36-403f-8c6d-85157954d3b5','App\\Notifications\\CustomerOrderItemFulfillmentUpdated','App\\Models\\User',5,'{\"title\":\"Order SN-Y3Y2U7EY: Confirmed\",\"body\":\"Your order item \\\"Shark\\\" has been confirmed.\",\"order_id\":2,\"order_item_id\":2,\"fulfillment_status\":\"confirmed\",\"previous_status\":\"pending\"}',NULL,'2026-08-04 05:03:51','2026-08-04 05:03:51');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `vendor_id` bigint(20) unsigned DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `product_sku` varchar(64) DEFAULT NULL,
  `vendor_store_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `fulfillment_status` varchar(32) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_id_foreign` (`order_id`),
  KEY `order_items_product_id_foreign` (`product_id`),
  KEY `order_items_fulfillment_status_index` (`fulfillment_status`),
  KEY `order_items_vendor_id_index` (`vendor_id`),
  CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,35,5,'Shark',NULL,'Tech Haven',1,20000.00,'confirmed','2026-07-25 14:18:05','2026-08-04 05:04:58'),(2,2,35,5,'Shark',NULL,'Tech Haven',1,20000.00,'confirmed','2026-08-03 19:16:49','2026-08-04 05:03:50'),(3,2,22,5,'Sony WH-1000XM5 Headphones','SKU-PEGCQPEM','Tech Haven',1,780000.00,'pending','2026-08-03 19:16:49','2026-08-03 19:16:49');
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'TZS',
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `payment_status` varchar(32) NOT NULL DEFAULT 'pending',
  `inventory_state` varchar(32) NOT NULL DEFAULT 'none',
  `payment_method` varchar(255) DEFAULT NULL,
  `shipping_method` varchar(255) DEFAULT NULL,
  `shipping_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `coupon_code` varchar(255) DEFAULT NULL,
  `shipping_address` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_order_number_unique` (`order_number`),
  KEY `orders_user_id_foreign` (`user_id`),
  KEY `orders_payment_status_index` (`payment_status`),
  KEY `orders_inventory_state_index` (`inventory_state`),
  CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,'SN-YEPIKLGP',5,31600.00,'TZS','pending','pending','none','airtel','standard',8000.00,3600.00,0.00,NULL,'{\"full_name\":\"Administrator\",\"phone\":\"0697780405\",\"line1\":\"P. O. Box 162, Same, Kilimanjaro\",\"line2\":null,\"city\":\"Kilimanjaro\",\"region\":null,\"country\":\"Tanzania\"}','2026-07-25 14:18:05','2026-07-25 14:18:05'),(2,'SN-Y3Y2U7EY',5,944000.00,'TZS','confirmed','pending','none','paypal','standard',0.00,144000.00,0.00,NULL,'{\"full_name\":\"Administrator\",\"phone\":\"0697780405\",\"line1\":\"same-kilimanjaro\",\"line2\":null,\"city\":\"arusha\",\"region\":null,\"country\":\"Tanzania\"}','2026-08-03 19:16:49','2026-08-04 05:04:23');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_notification_receipts`
--

DROP TABLE IF EXISTS `payment_notification_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_notification_receipts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(40) NOT NULL,
  `notification_key` varchar(128) NOT NULL,
  `merchant_reference` varchar(64) DEFAULT NULL,
  `tracking_id` varchar(128) DEFAULT NULL,
  `notification_type` varchar(40) DEFAULT NULL,
  `received_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL DEFAULT NULL,
  `processing_status` varchar(32) NOT NULL,
  `failure_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_notification_receipts_notification_key_unique` (`notification_key`),
  KEY `payment_notification_receipts_provider_processing_status_index` (`provider`,`processing_status`),
  KEY `payment_notification_receipts_merchant_reference_index` (`merchant_reference`),
  KEY `payment_notification_receipts_tracking_id_index` (`tracking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_notification_receipts`
--

LOCK TABLES `payment_notification_receipts` WRITE;
/*!40000 ALTER TABLE `payment_notification_receipts` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_notification_receipts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_reconciliations`
--

DROP TABLE IF EXISTS `payment_reconciliations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_reconciliations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_transaction_id` bigint(20) unsigned DEFAULT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `provider` varchar(40) DEFAULT NULL,
  `local_status` varchar(32) DEFAULT NULL,
  `provider_status` varchar(64) DEFAULT NULL,
  `severity` varchar(16) NOT NULL DEFAULT 'medium',
  `status` varchar(32) NOT NULL DEFAULT 'open',
  `detail` text DEFAULT NULL,
  `context` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_reconciliations_payment_transaction_id_foreign` (`payment_transaction_id`),
  KEY `payment_reconciliations_order_id_foreign` (`order_id`),
  KEY `payment_reconciliations_status_severity_index` (`status`,`severity`),
  CONSTRAINT `payment_reconciliations_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_reconciliations_payment_transaction_id_foreign` FOREIGN KEY (`payment_transaction_id`) REFERENCES `payment_transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_reconciliations`
--

LOCK TABLES `payment_reconciliations` WRITE;
/*!40000 ALTER TABLE `payment_reconciliations` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_reconciliations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_refunds`
--

DROP TABLE IF EXISTS `payment_refunds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_refunds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_transaction_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `return_request_id` bigint(20) unsigned DEFAULT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `reference` varchar(64) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'TZS',
  `status` varchar(32) NOT NULL DEFAULT 'requested',
  `provider_reference` varchar(128) DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_refunds_reference_unique` (`reference`),
  KEY `payment_refunds_payment_transaction_id_foreign` (`payment_transaction_id`),
  KEY `payment_refunds_actor_user_id_foreign` (`actor_user_id`),
  KEY `payment_refunds_order_id_status_index` (`order_id`,`status`),
  KEY `payment_refunds_status_index` (`status`),
  KEY `payment_refunds_return_request_id_foreign` (`return_request_id`),
  CONSTRAINT `payment_refunds_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_refunds_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_refunds_payment_transaction_id_foreign` FOREIGN KEY (`payment_transaction_id`) REFERENCES `payment_transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_refunds_return_request_id_foreign` FOREIGN KEY (`return_request_id`) REFERENCES `return_requests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_refunds`
--

LOCK TABLES `payment_refunds` WRITE;
/*!40000 ALTER TABLE `payment_refunds` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_refunds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_status_histories`
--

DROP TABLE IF EXISTS `payment_status_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_status_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_transaction_id` bigint(20) unsigned NOT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `from_status` varchar(32) NOT NULL,
  `to_status` varchar(32) NOT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_status_histories_payment_transaction_id_created_at_index` (`payment_transaction_id`,`created_at`),
  KEY `payment_status_histories_actor_user_id_index` (`actor_user_id`),
  CONSTRAINT `payment_status_histories_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_status_histories_payment_transaction_id_foreign` FOREIGN KEY (`payment_transaction_id`) REFERENCES `payment_transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_status_histories`
--

LOCK TABLES `payment_status_histories` WRITE;
/*!40000 ALTER TABLE `payment_status_histories` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_status_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_transactions`
--

DROP TABLE IF EXISTS `payment_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `attempt_number` int(10) unsigned NOT NULL DEFAULT 1,
  `reference` varchar(64) NOT NULL,
  `idempotency_key` varchar(128) DEFAULT NULL,
  `provider` varchar(40) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `refunded_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'TZS',
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `failure_code` varchar(64) DEFAULT NULL,
  `failure_reason` varchar(500) DEFAULT NULL,
  `provider_reference` varchar(128) DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `initiated_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_transactions_reference_unique` (`reference`),
  UNIQUE KEY `payment_transactions_provider_reference_unique` (`provider_reference`),
  UNIQUE KEY `payment_transactions_idempotency_key_unique` (`idempotency_key`),
  KEY `payment_transactions_order_id_status_index` (`order_id`,`status`),
  KEY `payment_transactions_status_index` (`status`),
  KEY `payment_transactions_provider_index` (`provider`),
  KEY `payment_transactions_order_attempt_index` (`order_id`,`attempt_number`),
  CONSTRAINT `payment_transactions_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_transactions`
--

LOCK TABLES `payment_transactions` WRITE;
/*!40000 ALTER TABLE `payment_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `group` varchar(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_unique` (`name`),
  KEY `permissions_group_index` (`group`)
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'admin.access','Admin Access','admin','2026-08-04 00:10:32','2026-08-04 00:10:32'),(2,'dashboard.view','Dashboard View','dashboard','2026-08-04 00:10:32','2026-08-04 00:10:32'),(3,'products.view','Products View','products','2026-08-04 00:10:32','2026-08-04 00:10:32'),(4,'products.create','Products Create','products','2026-08-04 00:10:32','2026-08-04 00:10:32'),(5,'products.update','Products Update','products','2026-08-04 00:10:32','2026-08-04 00:10:32'),(6,'products.delete','Products Delete','products','2026-08-04 00:10:32','2026-08-04 00:10:32'),(7,'products.publish','Products Publish','products','2026-08-04 00:10:32','2026-08-04 00:10:32'),(8,'products.unpublish','Products Unpublish','products','2026-08-04 00:10:32','2026-08-04 00:10:32'),(9,'products.manage_any','Products Manage Any','products','2026-08-04 00:10:32','2026-08-04 00:10:32'),(10,'categories.view','Categories View','categories','2026-08-04 00:10:32','2026-08-04 00:10:32'),(11,'categories.create','Categories Create','categories','2026-08-04 00:10:32','2026-08-04 00:10:32'),(12,'categories.update','Categories Update','categories','2026-08-04 00:10:32','2026-08-04 00:10:32'),(13,'categories.delete','Categories Delete','categories','2026-08-04 00:10:32','2026-08-04 00:10:32'),(14,'inventory.view','Inventory View','inventory','2026-08-04 00:10:32','2026-08-04 00:10:32'),(15,'inventory.adjust','Inventory Adjust','inventory','2026-08-04 00:10:32','2026-08-04 00:10:32'),(16,'inventory.history','Inventory History','inventory','2026-08-04 00:10:32','2026-08-04 00:10:32'),(17,'orders.view','Orders View','orders','2026-08-04 00:10:32','2026-08-04 00:10:32'),(18,'orders.update','Orders Update','orders','2026-08-04 00:10:32','2026-08-04 00:10:32'),(19,'orders.cancel','Orders Cancel','orders','2026-08-04 00:10:32','2026-08-04 00:10:32'),(20,'orders.refund','Orders Refund','orders','2026-08-04 00:10:32','2026-08-04 00:10:32'),(21,'orders.manage_any','Orders Manage Any','orders','2026-08-04 00:10:32','2026-08-04 00:10:32'),(22,'customers.view','Customers View','customers','2026-08-04 00:10:32','2026-08-04 00:10:32'),(23,'customers.update','Customers Update','customers','2026-08-04 00:10:32','2026-08-04 00:10:32'),(24,'customers.suspend','Customers Suspend','customers','2026-08-04 00:10:32','2026-08-04 00:10:32'),(25,'vendors.view','Vendors View','vendors','2026-08-04 00:10:32','2026-08-04 00:10:32');
INSERT INTO `permissions` VALUES (26,'vendors.create','Vendors Create','vendors','2026-08-04 00:10:32','2026-08-04 00:10:32'),(27,'vendors.update','Vendors Update','vendors','2026-08-04 00:10:32','2026-08-04 00:10:32'),(28,'vendors.approve','Vendors Approve','vendors','2026-08-04 00:10:32','2026-08-04 00:10:32'),(29,'vendors.reject','Vendors Reject','vendors','2026-08-04 00:10:32','2026-08-04 00:10:32'),(30,'vendors.suspend','Vendors Suspend','vendors','2026-08-04 00:10:32','2026-08-04 00:10:32'),(31,'coupons.view','Coupons View','coupons','2026-08-04 00:10:32','2026-08-04 00:10:32'),(32,'coupons.create','Coupons Create','coupons','2026-08-04 00:10:32','2026-08-04 00:10:32'),(33,'coupons.update','Coupons Update','coupons','2026-08-04 00:10:32','2026-08-04 00:10:32'),(34,'coupons.delete','Coupons Delete','coupons','2026-08-04 00:10:32','2026-08-04 00:10:32'),(35,'coupons.activate','Coupons Activate','coupons','2026-08-04 00:10:32','2026-08-04 00:10:32'),(36,'coupons.deactivate','Coupons Deactivate','coupons','2026-08-04 00:10:32','2026-08-04 00:10:32'),(37,'reviews.view','Reviews View','reviews','2026-08-04 00:10:32','2026-08-04 00:10:32'),(38,'reviews.create','Reviews Create','reviews','2026-08-04 00:10:32','2026-08-04 00:10:32'),(39,'reviews.moderate','Reviews Moderate','reviews','2026-08-04 00:10:32','2026-08-04 00:10:32'),(40,'reviews.approve','Reviews Approve','reviews','2026-08-04 00:10:32','2026-08-04 00:10:32'),(41,'reviews.reject','Reviews Reject','reviews','2026-08-04 00:10:32','2026-08-04 00:10:32'),(42,'reviews.hide','Reviews Hide','reviews','2026-08-04 00:10:32','2026-08-04 00:10:32'),(43,'reviews.restore','Reviews Restore','reviews','2026-08-04 00:10:32','2026-08-04 00:10:32'),(44,'reviews.flag','Reviews Flag','reviews','2026-08-04 00:10:32','2026-08-04 00:10:32'),(45,'users.view','Users View','users','2026-08-04 00:10:32','2026-08-04 00:10:32'),(46,'users.create','Users Create','users','2026-08-04 00:10:32','2026-08-04 00:10:32'),(47,'users.update','Users Update','users','2026-08-04 00:10:32','2026-08-04 00:10:32'),(48,'users.suspend','Users Suspend','users','2026-08-04 00:10:32','2026-08-04 00:10:32'),(49,'roles.view','Roles View','roles','2026-08-04 00:10:32','2026-08-04 00:10:32'),(50,'roles.create','Roles Create','roles','2026-08-04 00:10:32','2026-08-04 00:10:32');
INSERT INTO `permissions` VALUES (51,'roles.update','Roles Update','roles','2026-08-04 00:10:32','2026-08-04 00:10:32'),(52,'roles.delete','Roles Delete','roles','2026-08-04 00:10:32','2026-08-04 00:10:32'),(53,'permissions.view','Permissions View','permissions','2026-08-04 00:10:32','2026-08-04 00:10:32'),(54,'permissions.assign','Permissions Assign','permissions','2026-08-04 00:10:32','2026-08-04 00:10:32'),(55,'payments.view','Payments View','payments','2026-08-04 00:10:32','2026-08-04 00:10:32'),(56,'payments.manage','Payments Manage','payments','2026-08-04 00:10:32','2026-08-04 00:10:32'),(57,'refunds.create','Refunds Create','refunds','2026-08-04 00:10:32','2026-08-04 00:10:32'),(58,'payouts.view','Payouts View','payouts','2026-08-04 00:10:32','2026-08-04 00:10:32'),(59,'payouts.approve','Payouts Approve','payouts','2026-08-04 00:10:32','2026-08-04 00:10:32'),(60,'payouts.reject','Payouts Reject','payouts','2026-08-04 00:10:32','2026-08-04 00:10:32'),(61,'payouts.process','Payouts Process','payouts','2026-08-04 00:10:32','2026-08-04 00:10:32'),(62,'transactions.view','Transactions View','transactions','2026-08-04 00:10:32','2026-08-04 00:10:32'),(63,'ledger.view','Ledger View','ledger','2026-08-04 00:10:32','2026-08-04 00:10:32'),(64,'finance.reports.view','Finance Reports View','finance','2026-08-04 00:10:32','2026-08-04 00:10:32'),(65,'commission.manage','Commission Manage','commission','2026-08-04 00:10:32','2026-08-04 00:10:32'),(66,'settlement_holds.view','Settlement Holds View','settlement_holds','2026-08-04 00:10:32','2026-08-04 00:10:32'),(67,'settlement_holds.manage','Settlement Holds Manage','settlement_holds','2026-08-04 00:10:32','2026-08-04 00:10:32'),(68,'returns.view','Returns View','returns','2026-08-04 00:10:32','2026-08-04 00:10:32'),(69,'returns.manage','Returns Manage','returns','2026-08-04 00:10:32','2026-08-04 00:10:32'),(70,'returns.approve','Returns Approve','returns','2026-08-04 00:10:32','2026-08-04 00:10:32'),(71,'disputes.view','Disputes View','disputes','2026-08-04 00:10:32','2026-08-04 00:10:32'),(72,'disputes.respond','Disputes Respond','disputes','2026-08-04 00:10:32','2026-08-04 00:10:32'),(73,'disputes.resolve','Disputes Resolve','disputes','2026-08-04 00:10:32','2026-08-04 00:10:32'),(74,'disputes.manage','Disputes Manage','disputes','2026-08-04 00:10:32','2026-08-04 00:10:32'),(75,'chargebacks.view','Chargebacks View','chargebacks','2026-08-04 00:10:32','2026-08-04 00:10:32');
INSERT INTO `permissions` VALUES (76,'chargebacks.create','Chargebacks Create','chargebacks','2026-08-04 00:10:32','2026-08-04 00:10:32'),(77,'chargebacks.manage','Chargebacks Manage','chargebacks','2026-08-04 00:10:32','2026-08-04 00:10:32'),(78,'chargebacks.resolve','Chargebacks Resolve','chargebacks','2026-08-04 00:10:32','2026-08-04 00:10:32'),(79,'audit_logs.view','Audit Logs View','audit_logs','2026-08-04 00:10:32','2026-08-04 00:10:32'),(80,'security_events.view','Security Events View','security_events','2026-08-04 00:10:32','2026-08-04 00:10:32'),(81,'settings.view','Settings View','settings','2026-08-04 00:10:32','2026-08-04 00:10:32'),(82,'settings.update','Settings Update','settings','2026-08-04 00:10:32','2026-08-04 00:10:32'),(83,'wishlist.view','Wishlist View','wishlist','2026-08-04 00:10:32','2026-08-04 00:10:32'),(84,'wishlist.manage','Wishlist Manage','wishlist','2026-08-04 00:10:32','2026-08-04 00:10:32'),(85,'addresses.view','Addresses View','addresses','2026-08-04 00:10:32','2026-08-04 00:10:32'),(86,'addresses.manage','Addresses Manage','addresses','2026-08-04 00:10:32','2026-08-04 00:10:32'),(87,'profile.view','Profile View','profile','2026-08-04 00:10:32','2026-08-04 00:10:32'),(88,'profile.update','Profile Update','profile','2026-08-04 00:10:32','2026-08-04 00:10:32'),(89,'vendor.access','Vendor Access','vendor','2026-08-04 00:10:32','2026-08-04 00:10:32');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_questions`
--

DROP TABLE IF EXISTS `product_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `author_name` varchar(255) DEFAULT NULL,
  `question` text NOT NULL,
  `answer` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_questions_product_id_foreign` (`product_id`),
  KEY `product_questions_user_id_foreign` (`user_id`),
  CONSTRAINT `product_questions_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_questions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_questions`
--

LOCK TABLES `product_questions` WRITE;
/*!40000 ALTER TABLE `product_questions` DISABLE KEYS */;
INSERT INTO `product_questions` VALUES (1,19,NULL,'Buyer','Does this include warranty and local delivery to Dar es Salaam?','Yes ΓÇö 12-month seller warranty and delivery within Dar in 1ΓÇô3 business days.','2026-07-24 03:58:27','2026-07-24 03:58:27'),(2,20,NULL,'Buyer','Does this include warranty and local delivery to Dar es Salaam?','Yes ΓÇö 12-month seller warranty and delivery within Dar in 1ΓÇô3 business days.','2026-07-24 03:58:27','2026-07-24 03:58:27'),(3,21,NULL,'Buyer','Does this include warranty and local delivery to Dar es Salaam?','Yes ΓÇö 12-month seller warranty and delivery within Dar in 1ΓÇô3 business days.','2026-07-24 03:58:28','2026-07-24 03:58:28'),(4,22,NULL,'Buyer','Does this include warranty and local delivery to Dar es Salaam?','Yes ΓÇö 12-month seller warranty and delivery within Dar in 1ΓÇô3 business days.','2026-07-24 03:58:28','2026-07-24 03:58:28'),(5,23,NULL,'Buyer','Does this include warranty and local delivery to Dar es Salaam?','Yes ΓÇö 12-month seller warranty and delivery within Dar in 1ΓÇô3 business days.','2026-07-24 03:58:28','2026-07-24 03:58:28'),(6,24,NULL,'Buyer','Does this include warranty and local delivery to Dar es Salaam?','Yes ΓÇö 12-month seller warranty and delivery within Dar in 1ΓÇô3 business days.','2026-07-24 03:58:28','2026-07-24 03:58:28'),(7,25,NULL,'Buyer','Does this include warranty and local delivery to Dar es Salaam?','Yes ΓÇö 12-month seller warranty and delivery within Dar in 1ΓÇô3 business days.','2026-07-24 03:58:28','2026-07-24 03:58:28'),(8,26,NULL,'Buyer','Does this include warranty and local delivery to Dar es Salaam?','Yes ΓÇö 12-month seller warranty and delivery within Dar in 1ΓÇô3 business days.','2026-07-24 03:58:28','2026-07-24 03:58:28'),(9,27,NULL,'Buyer','Does this include warranty and local delivery to Dar es Salaam?','Yes ΓÇö 12-month seller warranty and delivery within Dar in 1ΓÇô3 business days.','2026-07-24 03:58:28','2026-07-24 03:58:28'),(10,28,NULL,'Buyer','Does this include warranty and local delivery to Dar es Salaam?','Yes ΓÇö 12-month seller warranty and delivery within Dar in 1ΓÇô3 business days.','2026-07-24 03:58:28','2026-07-24 03:58:28'),(11,29,NULL,'Buyer','Does this include warranty and local delivery to Dar es Salaam?','Yes ΓÇö 12-month seller warranty and delivery within Dar in 1ΓÇô3 business days.','2026-07-24 03:58:28','2026-07-24 03:58:28'),(12,30,NULL,'Buyer','Does this include warranty and local delivery to Dar es Salaam?','Yes ΓÇö 12-month seller warranty and delivery within Dar in 1ΓÇô3 business days.','2026-07-24 03:58:28','2026-07-24 03:58:28'),(13,31,NULL,'Buyer','Does this include warranty and local delivery to Dar es Salaam?','Yes ΓÇö 12-month seller warranty and delivery within Dar in 1ΓÇô3 business days.','2026-07-24 03:58:28','2026-07-24 03:58:28'),(14,32,NULL,'Buyer','Does this include warranty and local delivery to Dar es Salaam?','Yes ΓÇö 12-month seller warranty and delivery within Dar in 1ΓÇô3 business days.','2026-07-24 03:58:28','2026-07-24 03:58:28'),(15,33,NULL,'Buyer','Does this include warranty and local delivery to Dar es Salaam?','Yes ΓÇö 12-month seller warranty and delivery within Dar in 1ΓÇô3 business days.','2026-07-24 03:58:28','2026-07-24 03:58:28');
/*!40000 ALTER TABLE `product_questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `vendor_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `compare_at_price` decimal(12,2) DEFAULT NULL,
  `stock` int(11) NOT NULL,
  `reorder_level` int(10) unsigned NOT NULL DEFAULT 5,
  `reserved_quantity` int(10) unsigned NOT NULL DEFAULT 0,
  `rating_avg` decimal(3,2) NOT NULL DEFAULT 0.00,
  `rating_count` int(10) unsigned NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_flash_sale` tinyint(1) NOT NULL DEFAULT 0,
  `flash_ends_at` timestamp NULL DEFAULT NULL,
  `is_new` tinyint(1) NOT NULL DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `gallery` longtext DEFAULT NULL,
  `specs` longtext DEFAULT NULL,
  `variants` longtext DEFAULT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'published',
  `published_at` timestamp NULL DEFAULT NULL,
  `sold_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_slug_unique` (`slug`),
  UNIQUE KEY `products_sku_unique` (`sku`),
  KEY `products_vendor_id_foreign` (`vendor_id`),
  KEY `products_category_id_brand_index` (`category_id`,`brand`),
  KEY `products_is_featured_is_flash_sale_is_new_index` (`is_featured`,`is_flash_sale`,`is_new`),
  KEY `products_price_index` (`price`),
  KEY `products_status_index` (`status`),
  CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (19,5,1,'Samsung Galaxy S25 Ultra','samsung-galaxy-s25-ultra-ptfm','Samsung','Flagship Galaxy S25 Ultra with pro-grade camera, S Pen, and all-day battery for creators and power users.',3200000.00,3600000.00,24,5,0,4.80,19,1,1,'2026-07-24 21:58:27',1,'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?auto=format&fit=crop&w=1000&q=80','[\"https:\\/\\/images.unsplash.com\\/photo-1610945415295-d9bbf067e59c?auto=format&fit=crop&w=1000&q=80\",\"https:\\/\\/images.unsplash.com\\/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=1000&q=80\",\"https:\\/\\/images.unsplash.com\\/photo-1592899677977-9c10ca588bbd?auto=format&fit=crop&w=1000&q=80\"]','{\"Display\":\"6.9\\\" Dynamic AMOLED\",\"Chip\":\"Snapdragon 8 Elite\",\"RAM\":\"12GB\",\"Storage\":\"256GB\",\"Camera\":\"200MP\"}','{\"colors\":[\"Titanium Black\",\"Silver\",\"Blue\"],\"storage\":[\"256GB\",\"512GB\"]}','SKU-I3UPSUBM','published','2026-07-24 03:58:27',186,'2026-07-24 03:58:27','2026-07-24 03:58:27',NULL),(20,9,1,'iPhone 16 Pro Max','iphone-16-pro-max-hrgt','Apple','The ultimate iPhone with titanium design, cinematic camera control, and blazing A18 Pro performance.',3800000.00,4100000.00,18,5,0,5.00,21,1,1,'2026-07-24 21:58:27',1,'https://images.unsplash.com/photo-1695048133142-1a20484d2569?auto=format&fit=crop&w=1000&q=80','[\"https:\\/\\/images.unsplash.com\\/photo-1695048133142-1a20484d2569?auto=format&fit=crop&w=1000&q=80\",\"https:\\/\\/images.unsplash.com\\/photo-1510557880182-3d4d3cba35a5?auto=format&fit=crop&w=1000&q=80\"]','{\"Display\":\"6.9\\\" Super Retina XDR\",\"Chip\":\"A18 Pro\",\"Camera\":\"48MP Fusion\",\"Battery\":\"All-day\"}','{\"colors\":[\"Natural Titanium\",\"Black Titanium\"],\"storage\":[\"256GB\",\"512GB\",\"1TB\"]}','SKU-7D9XSLKU','published','2026-07-24 03:58:27',240,'2026-07-24 03:58:27','2026-07-24 03:58:27',NULL),(21,9,1,'MacBook Pro 14\" M4','macbook-pro-14-m4-gp3v','Apple','Pro laptop for video, code, and design ΓÇö silent, powerful, and built for all-day creative work.',5200000.00,5600000.00,12,5,0,4.60,37,1,0,NULL,1,'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=1000&q=80','[\"https:\\/\\/images.unsplash.com\\/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=1000&q=80\",\"https:\\/\\/images.unsplash.com\\/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=1000&q=80\"]','{\"Chip\":\"Apple M4\",\"RAM\":\"16GB\",\"Storage\":\"512GB SSD\",\"Display\":\"Liquid Retina XDR\"}','{\"colors\":[\"Space Black\",\"Silver\"]}','SKU-LQKZE2WX','published','2026-07-24 03:58:27',95,'2026-07-24 03:58:27','2026-07-24 03:58:27',NULL),(22,5,1,'Sony WH-1000XM5 Headphones','sony-wh-1000xm5-headphones-nll0','Sony','Premium noise-cancelling headphones with crystal clarity for flights, offices, and deep focus.',780000.00,920000.00,39,5,0,4.10,218,1,1,'2026-07-24 21:58:27',0,'https://images.unsplash.com/photo-1618366712010-f4ae9c647dcb?auto=format&fit=crop&w=1000&q=80','[\"https:\\/\\/images.unsplash.com\\/photo-1618366712010-f4ae9c647dcb?auto=format&fit=crop&w=1000&q=80\",\"https:\\/\\/images.unsplash.com\\/photo-1546435770-a3e426bf472b?auto=format&fit=crop&w=1000&q=80\"]','{\"ANC\":\"Industry-leading\",\"Battery\":\"30 hours\",\"Connectivity\":\"Bluetooth 5.3\"}','{\"colors\":[\"Black\",\"Silver\"]}','SKU-PEGCQPEM','published','2026-07-24 03:58:28',311,'2026-07-24 03:58:28','2026-08-03 19:16:49',NULL),(23,5,1,'iPad Air 11\" M2','ipad-air-11-m2-br3g','Apple','Thin, powerful tablet for notes, streaming, and creative apps ΓÇö with Apple Pencil support.',1800000.00,1990000.00,30,5,0,4.10,73,0,0,NULL,1,'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?auto=format&fit=crop&w=1000&q=80','[\"https:\\/\\/images.unsplash.com\\/photo-1544244015-0df4b3ffc6b0?auto=format&fit=crop&w=1000&q=80\"]','{\"Chip\":\"M2\",\"Display\":\"11\\\" Liquid Retina\",\"Pencil\":\"Apple Pencil Pro\"}','{\"colors\":[\"Blue\",\"Purple\",\"Starlight\"]}','SKU-NGYWDRJO','published','2026-07-24 03:58:28',140,'2026-07-24 03:58:28','2026-07-24 03:58:28',NULL),(24,6,2,'Nike Air Max 270','nike-air-max-270-zure','Nike','Iconic Air Max comfort with bold street style ΓÇö everyday sneakers that turn heads.',285000.00,340000.00,55,5,0,4.50,210,1,1,'2026-07-24 21:58:27',0,'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1000&q=80','[\"https:\\/\\/images.unsplash.com\\/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1000&q=80\",\"https:\\/\\/images.unsplash.com\\/photo-1606107557195-0e29a4b5b4aa?auto=format&fit=crop&w=1000&q=80\"]','{\"Type\":\"Lifestyle sneaker\",\"Upper\":\"Mesh & synthetic\",\"Sole\":\"Max Air unit\"}','{\"sizes\":[\"40\",\"41\",\"42\",\"43\",\"44\"],\"colors\":[\"Red\\/White\",\"Black\"]}','SKU-MYIZMQ9F','published','2026-07-24 03:58:28',420,'2026-07-24 03:58:28','2026-07-24 03:58:28',NULL),(25,6,2,'Adidas Ultraboost Light','adidas-ultraboost-light-g2rk','Adidas','Energy-returning Ultraboost Light for long runs and all-day city miles.',320000.00,380000.00,48,5,0,4.00,231,0,1,'2026-07-24 21:58:27',0,'https://images.unsplash.com/photo-1608231387042-66d1773070a5?auto=format&fit=crop&w=1000&q=80','[\"https:\\/\\/images.unsplash.com\\/photo-1608231387042-66d1773070a5?auto=format&fit=crop&w=1000&q=80\"]','{\"Use\":\"Running \\/ lifestyle\",\"Cushion\":\"Boost Light\",\"Fit\":\"Primeknit\"}','{\"sizes\":[\"40\",\"41\",\"42\",\"43\",\"44\",\"45\"]}','SKU-W4R0DQUF','published','2026-07-24 03:58:28',275,'2026-07-24 03:58:28','2026-07-24 03:58:28',NULL),(26,6,2,'Levi\'s 501 Original Jeans','levis-501-original-jeans-xbyd','Levi\'s','The original straight-leg jean ΓÇö durable denim with timeless Americana style.',145000.00,175000.00,80,5,0,4.70,55,0,0,NULL,0,'https://images.unsplash.com/photo-1542272604-787c3835535d?auto=format&fit=crop&w=1000&q=80','[\"https:\\/\\/images.unsplash.com\\/photo-1542272604-787c3835535d?auto=format&fit=crop&w=1000&q=80\"]','{\"Fit\":\"Straight\",\"Material\":\"100% cotton denim\",\"Rise\":\"Mid\"}','{\"sizes\":[\"28\",\"30\",\"32\",\"34\",\"36\"],\"colors\":[\"Indigo\",\"Black\"]}','SKU-JORY9MK8','published','2026-07-24 03:58:28',190,'2026-07-24 03:58:28','2026-07-24 03:58:28',NULL),(27,6,2,'Leather Bomber Jacket','leather-bomber-jacket-gire','SANA Atelier','Premium leather bomber with quilted lining ΓÇö sharp enough for nights out, tough enough for daily wear.',420000.00,510000.00,22,5,0,4.90,34,1,0,NULL,1,'https://images.unsplash.com/photo-1551028719-00167b16eac5?auto=format&fit=crop&w=1000&q=80','[\"https:\\/\\/images.unsplash.com\\/photo-1551028719-00167b16eac5?auto=format&fit=crop&w=1000&q=80\"]','{\"Material\":\"Genuine leather\",\"Lining\":\"Quilted\",\"Fit\":\"Regular\"}','{\"sizes\":[\"S\",\"M\",\"L\",\"XL\"],\"colors\":[\"Brown\",\"Black\"]}','SKU-KAWGZIDR','published','2026-07-24 03:58:28',68,'2026-07-24 03:58:28','2026-07-24 03:58:28',NULL),(28,7,3,'Scandinavian Oak Dining Set','scandinavian-oak-dining-set-gty6','NordHome','Solid oak dining table with six chairs ΓÇö warm Scandinavian lines for family meals and hosting.',980000.00,1150000.00,8,5,0,4.90,192,1,0,NULL,0,'https://images.unsplash.com/photo-1617806118233-18e1de247200?auto=format&fit=crop&w=1000&q=80','[\"https:\\/\\/images.unsplash.com\\/photo-1617806118233-18e1de247200?auto=format&fit=crop&w=1000&q=80\"]','{\"Seats\":\"6\",\"Material\":\"Solid oak\",\"Finish\":\"Natural matte\"}','{\"colors\":[\"Natural Oak\",\"Walnut\"]}','SKU-CJCRNBLQ','published','2026-07-24 03:58:28',34,'2026-07-24 03:58:28','2026-07-24 03:58:28',NULL),(29,7,3,'Ergonomic Mesh Office Chair','ergonomic-mesh-office-chair-41vc','WorkWell','All-day ergonomic chair with breathable mesh and adjustable lumbar support for hybrid work.',365000.00,420000.00,35,5,0,4.20,22,0,1,'2026-07-24 21:58:27',0,'https://images.unsplash.com/photo-1580480055273-228ff5388ef8?auto=format&fit=crop&w=1000&q=80','[\"https:\\/\\/images.unsplash.com\\/photo-1580480055273-228ff5388ef8?auto=format&fit=crop&w=1000&q=80\"]','{\"Support\":\"Lumbar adjustable\",\"Arms\":\"3D adjustable\",\"Wheels\":\"Silent caster\"}','{\"colors\":[\"Black\",\"Grey\"]}','SKU-ZNELZKUO','published','2026-07-24 03:58:28',155,'2026-07-24 03:58:28','2026-07-24 03:58:28',NULL),(30,7,3,'Minimalist Ceramic Table Lamp','minimalist-ceramic-table-lamp-xwh2','Lumen','Soft ambient lamp with ceramic base and linen shade ΓÇö perfect bedside or desk glow.',89000.00,110000.00,60,5,0,4.10,236,0,0,NULL,1,'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=1000&q=80','[\"https:\\/\\/images.unsplash.com\\/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=1000&q=80\"]','{\"Bulb\":\"E27 LED included\",\"Height\":\"45cm\",\"Material\":\"Ceramic + linen\"}','{\"colors\":[\"Ivory\",\"Sage\",\"Charcoal\"]}','SKU-WUY0Y8JF','published','2026-07-24 03:58:28',210,'2026-07-24 03:58:28','2026-07-24 03:58:28',NULL),(31,8,4,'Vitamin C Brightening Set','vitamin-c-brightening-set-bzq9','GlowLab','Complete brightening routine with stable vitamin C for clearer, more radiant skin.',98000.00,125000.00,90,5,0,4.30,217,1,1,'2026-07-24 21:58:27',0,'https://images.unsplash.com/photo-1556228578-0d85b1a4d571?auto=format&fit=crop&w=1000&q=80','[\"https:\\/\\/images.unsplash.com\\/photo-1556228578-0d85b1a4d571?auto=format&fit=crop&w=1000&q=80\"]','{\"Includes\":\"Cleanser, serum, moisturizer\",\"Skin\":\"All types\",\"Concern\":\"Dullness\"}','[]','SKU-25TIQDP5','published','2026-07-24 03:58:28',330,'2026-07-24 03:58:28','2026-07-24 03:58:28',NULL),(32,8,4,'Luxury Eau de Parfum 100ml','luxury-eau-de-parfum-100ml-cejj','Noir Atelier','Long-lasting unisex fragrance with citrus opening and warm woody dry-down.',210000.00,260000.00,40,5,0,4.70,205,0,0,NULL,1,'https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&w=1000&q=80','[\"https:\\/\\/images.unsplash.com\\/photo-1541643600914-78b084683601?auto=format&fit=crop&w=1000&q=80\"]','{\"Notes\":\"Bergamot, cedar, amber\",\"Concentration\":\"EDP\",\"Size\":\"100ml\"}','[]','SKU-68WIJXMU','published','2026-07-24 03:58:28',120,'2026-07-24 03:58:28','2026-07-24 03:58:28',NULL),(33,8,5,'Premium Yoga Mat Pro','premium-yoga-mat-pro-jhzc','FlexForm','Extra-grip yoga mat with dense cushioning for studio classes and home practice.',125000.00,155000.00,70,5,0,4.00,194,0,1,'2026-07-24 21:58:27',0,'https://images.unsplash.com/photo-1601925260368-ae2f83cf8b7f?auto=format&fit=crop&w=1000&q=80','[\"https:\\/\\/images.unsplash.com\\/photo-1601925260368-ae2f83cf8b7f?auto=format&fit=crop&w=1000&q=80\"]','{\"Thickness\":\"6mm\",\"Material\":\"Eco TPE\",\"Grip\":\"Non-slip\"}','{\"colors\":[\"Ocean\",\"Slate\",\"Coral\"]}','SKU-PLZLMYOT','published','2026-07-24 03:58:28',260,'2026-07-24 03:58:28','2026-07-24 03:58:28',NULL),(35,5,1,'Shark','shark-a2rTc','Infinix','well designed product',20000.00,NULL,5,5,0,0.00,0,0,0,NULL,0,NULL,NULL,NULL,NULL,NULL,'published','2026-07-25 14:08:36',2,'2026-07-25 14:08:36','2026-08-03 19:16:49',NULL);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `return_items`
--

DROP TABLE IF EXISTS `return_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `return_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `return_request_id` bigint(20) unsigned NOT NULL,
  `order_item_id` bigint(20) unsigned NOT NULL,
  `vendor_id` bigint(20) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `unit_price` decimal(14,2) NOT NULL,
  `line_amount` decimal(14,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'TZS',
  `restockable` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `return_items_return_request_id_order_item_id_unique` (`return_request_id`,`order_item_id`),
  KEY `return_items_order_item_id_foreign` (`order_item_id`),
  KEY `return_items_vendor_id_foreign` (`vendor_id`),
  CONSTRAINT `return_items_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `return_items_return_request_id_foreign` FOREIGN KEY (`return_request_id`) REFERENCES `return_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `return_items_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `return_items`
--

LOCK TABLES `return_items` WRITE;
/*!40000 ALTER TABLE `return_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `return_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `return_requests`
--

DROP TABLE IF EXISTS `return_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `return_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(40) NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `vendor_id` bigint(20) unsigned NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'requested',
  `reason_code` varchar(40) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `restocked` tinyint(1) NOT NULL DEFAULT 0,
  `payment_refund_id` bigint(20) unsigned DEFAULT NULL,
  `settlement_hold_id` bigint(20) unsigned DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at` timestamp NULL DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `refunded_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `received_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `return_requests_reference_unique` (`reference`),
  KEY `return_requests_payment_refund_id_foreign` (`payment_refund_id`),
  KEY `return_requests_approved_by_foreign` (`approved_by`),
  KEY `return_requests_received_by_foreign` (`received_by`),
  KEY `return_requests_customer_id_status_index` (`customer_id`,`status`),
  KEY `return_requests_vendor_id_status_index` (`vendor_id`,`status`),
  KEY `return_requests_order_id_status_index` (`order_id`,`status`),
  KEY `return_requests_settlement_hold_id_foreign` (`settlement_hold_id`),
  CONSTRAINT `return_requests_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `return_requests_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `return_requests_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `return_requests_payment_refund_id_foreign` FOREIGN KEY (`payment_refund_id`) REFERENCES `payment_refunds` (`id`) ON DELETE SET NULL,
  CONSTRAINT `return_requests_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `return_requests_settlement_hold_id_foreign` FOREIGN KEY (`settlement_hold_id`) REFERENCES `settlement_holds` (`id`) ON DELETE SET NULL,
  CONSTRAINT `return_requests_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `return_requests`
--

LOCK TABLES `return_requests` WRITE;
/*!40000 ALTER TABLE `return_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `return_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `vendor_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `author_name` varchar(255) DEFAULT NULL,
  `rating` tinyint(3) unsigned NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'APPROVED',
  `verified_purchase` tinyint(1) NOT NULL DEFAULT 0,
  `moderated_at` timestamp NULL DEFAULT NULL,
  `moderated_by` bigint(20) unsigned DEFAULT NULL,
  `moderation_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reviews_user_id_foreign` (`user_id`),
  KEY `reviews_product_id_rating_index` (`product_id`,`rating`),
  KEY `reviews_vendor_id_foreign` (`vendor_id`),
  KEY `reviews_order_id_foreign` (`order_id`),
  KEY `reviews_moderated_by_foreign` (`moderated_by`),
  KEY `reviews_status_index` (`status`),
  CONSTRAINT `reviews_moderated_by_foreign` FOREIGN KEY (`moderated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reviews_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reviews_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reviews_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
INSERT INTO `reviews` VALUES (1,19,NULL,NULL,NULL,'Amina K.',5,'Excellent quality','Arrived quickly and exactly as described. Would buy again from this seller.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:27','2026-07-24 03:58:27'),(2,19,NULL,NULL,NULL,'James M.',4,'Great value','Solid product for the price. Packaging was secure and support responded fast.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:27','2026-07-24 03:58:27'),(3,20,NULL,NULL,NULL,'Amina K.',5,'Excellent quality','Arrived quickly and exactly as described. Would buy again from this seller.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:27','2026-07-24 03:58:27'),(4,20,NULL,NULL,NULL,'James M.',4,'Great value','Solid product for the price. Packaging was secure and support responded fast.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:27','2026-07-24 03:58:27'),(5,21,NULL,NULL,NULL,'Amina K.',5,'Excellent quality','Arrived quickly and exactly as described. Would buy again from this seller.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:27','2026-07-24 03:58:27'),(6,21,NULL,NULL,NULL,'James M.',4,'Great value','Solid product for the price. Packaging was secure and support responded fast.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:27','2026-07-24 03:58:27'),(7,22,NULL,NULL,NULL,'Amina K.',5,'Excellent quality','Arrived quickly and exactly as described. Would buy again from this seller.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(8,22,NULL,NULL,NULL,'James M.',4,'Great value','Solid product for the price. Packaging was secure and support responded fast.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(9,23,NULL,NULL,NULL,'Amina K.',5,'Excellent quality','Arrived quickly and exactly as described. Would buy again from this seller.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(10,23,NULL,NULL,NULL,'James M.',4,'Great value','Solid product for the price. Packaging was secure and support responded fast.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(11,24,NULL,NULL,NULL,'Amina K.',5,'Excellent quality','Arrived quickly and exactly as described. Would buy again from this seller.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(12,24,NULL,NULL,NULL,'James M.',4,'Great value','Solid product for the price. Packaging was secure and support responded fast.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(13,25,NULL,NULL,NULL,'Amina K.',5,'Excellent quality','Arrived quickly and exactly as described. Would buy again from this seller.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(14,25,NULL,NULL,NULL,'James M.',4,'Great value','Solid product for the price. Packaging was secure and support responded fast.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(15,26,NULL,NULL,NULL,'Amina K.',5,'Excellent quality','Arrived quickly and exactly as described. Would buy again from this seller.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(16,26,NULL,NULL,NULL,'James M.',4,'Great value','Solid product for the price. Packaging was secure and support responded fast.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(17,27,NULL,NULL,NULL,'Amina K.',5,'Excellent quality','Arrived quickly and exactly as described. Would buy again from this seller.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(18,27,NULL,NULL,NULL,'James M.',4,'Great value','Solid product for the price. Packaging was secure and support responded fast.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(19,28,NULL,NULL,NULL,'Amina K.',5,'Excellent quality','Arrived quickly and exactly as described. Would buy again from this seller.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(20,28,NULL,NULL,NULL,'James M.',4,'Great value','Solid product for the price. Packaging was secure and support responded fast.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(21,29,NULL,NULL,NULL,'Amina K.',5,'Excellent quality','Arrived quickly and exactly as described. Would buy again from this seller.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(22,29,NULL,NULL,NULL,'James M.',4,'Great value','Solid product for the price. Packaging was secure and support responded fast.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(23,30,NULL,NULL,NULL,'Amina K.',5,'Excellent quality','Arrived quickly and exactly as described. Would buy again from this seller.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(24,30,NULL,NULL,NULL,'James M.',4,'Great value','Solid product for the price. Packaging was secure and support responded fast.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(25,31,NULL,NULL,NULL,'Amina K.',5,'Excellent quality','Arrived quickly and exactly as described. Would buy again from this seller.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28');
INSERT INTO `reviews` VALUES (26,31,NULL,NULL,NULL,'James M.',4,'Great value','Solid product for the price. Packaging was secure and support responded fast.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(27,32,NULL,NULL,NULL,'Amina K.',5,'Excellent quality','Arrived quickly and exactly as described. Would buy again from this seller.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(28,32,NULL,NULL,NULL,'James M.',4,'Great value','Solid product for the price. Packaging was secure and support responded fast.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(29,33,NULL,NULL,NULL,'Amina K.',5,'Excellent quality','Arrived quickly and exactly as described. Would buy again from this seller.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28'),(30,33,NULL,NULL,NULL,'James M.',4,'Great value','Solid product for the price. Packaging was secure and support responded fast.','APPROVED',0,NULL,NULL,NULL,'2026-07-24 03:58:28','2026-07-24 03:58:28');
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_permissions_role_id_permission_id_unique` (`role_id`,`permission_id`),
  KEY `role_permissions_permission_id_foreign` (`permission_id`),
  CONSTRAINT `role_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=293 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,1,1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(2,1,2,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(3,1,3,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(4,1,4,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(5,1,5,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(6,1,6,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(7,1,7,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(8,1,8,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(9,1,9,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(10,1,10,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(11,1,11,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(12,1,12,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(13,1,13,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(14,1,14,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(15,1,15,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(16,1,16,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(17,1,17,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(18,1,18,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(19,1,19,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(20,1,20,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(21,1,21,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(22,1,22,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(23,1,23,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(24,1,24,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(25,1,25,'2026-08-04 00:10:32','2026-08-04 00:10:32');
INSERT INTO `role_permissions` VALUES (26,1,26,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(27,1,27,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(28,1,28,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(29,1,29,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(30,1,30,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(31,1,31,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(32,1,32,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(33,1,33,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(34,1,34,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(35,1,35,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(36,1,36,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(37,1,37,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(38,1,38,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(39,1,39,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(40,1,40,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(41,1,41,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(42,1,42,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(43,1,43,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(44,1,44,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(45,1,45,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(46,1,46,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(47,1,47,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(48,1,48,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(49,1,49,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(50,1,50,'2026-08-04 00:10:32','2026-08-04 00:10:32');
INSERT INTO `role_permissions` VALUES (51,1,51,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(52,1,52,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(53,1,53,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(54,1,54,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(55,1,55,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(56,1,56,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(57,1,57,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(58,1,58,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(59,1,59,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(60,1,60,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(61,1,61,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(62,1,62,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(63,1,63,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(64,1,64,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(65,1,65,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(66,1,66,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(67,1,67,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(68,1,68,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(69,1,69,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(70,1,70,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(71,1,71,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(72,1,72,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(73,1,73,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(74,1,74,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(75,1,75,'2026-08-04 00:10:32','2026-08-04 00:10:32');
INSERT INTO `role_permissions` VALUES (76,1,76,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(77,1,77,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(78,1,78,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(79,1,79,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(80,1,80,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(81,1,81,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(82,1,82,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(83,1,83,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(84,1,84,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(85,1,85,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(86,1,86,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(87,1,87,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(88,1,88,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(89,1,89,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(90,2,1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(91,2,2,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(92,2,3,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(93,2,4,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(94,2,5,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(95,2,6,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(96,2,7,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(97,2,8,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(98,2,9,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(99,2,10,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(100,2,11,'2026-08-04 00:10:32','2026-08-04 00:10:32');
INSERT INTO `role_permissions` VALUES (101,2,12,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(102,2,13,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(103,2,14,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(104,2,15,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(105,2,16,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(106,2,17,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(107,2,18,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(108,2,19,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(109,2,21,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(110,2,22,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(111,2,23,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(112,2,24,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(113,2,25,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(114,2,26,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(115,2,27,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(116,2,28,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(117,2,29,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(118,2,30,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(119,2,31,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(120,2,32,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(121,2,33,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(122,2,34,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(123,2,35,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(124,2,36,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(125,2,37,'2026-08-04 00:10:32','2026-08-04 00:10:32');
INSERT INTO `role_permissions` VALUES (126,2,39,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(127,2,40,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(128,2,41,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(129,2,42,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(130,2,43,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(131,2,44,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(132,2,45,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(133,2,46,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(134,2,47,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(135,2,48,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(136,2,55,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(137,2,56,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(138,2,62,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(139,2,57,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(140,2,58,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(141,2,59,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(142,2,63,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(143,2,64,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(144,2,68,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(145,2,69,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(146,2,70,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(147,2,71,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(148,2,72,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(149,2,73,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(150,2,74,'2026-08-04 00:10:32','2026-08-04 00:10:32');
INSERT INTO `role_permissions` VALUES (151,2,75,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(152,2,76,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(153,2,77,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(154,2,66,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(155,2,67,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(156,2,79,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(157,2,81,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(158,3,1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(159,3,2,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(160,3,3,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(161,3,4,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(162,3,5,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(163,3,7,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(164,3,8,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(165,3,9,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(166,3,10,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(167,3,11,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(168,3,12,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(169,3,14,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(170,4,1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(171,4,2,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(172,4,14,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(173,4,15,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(174,4,16,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(175,4,3,'2026-08-04 00:10:32','2026-08-04 00:10:32');
INSERT INTO `role_permissions` VALUES (176,5,1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(177,5,2,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(178,5,17,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(179,5,18,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(180,5,19,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(181,5,21,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(182,5,3,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(183,5,14,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(184,5,22,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(185,5,55,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(186,5,68,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(187,5,70,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(188,5,71,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(189,5,72,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(190,6,1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(191,6,2,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(192,6,22,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(193,6,23,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(194,6,17,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(195,6,37,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(196,6,55,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(197,6,68,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(198,6,70,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(199,6,71,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(200,6,72,'2026-08-04 00:10:32','2026-08-04 00:10:32');
INSERT INTO `role_permissions` VALUES (201,6,73,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(202,6,75,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(203,6,66,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(204,7,1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(205,7,2,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(206,7,25,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(207,7,26,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(208,7,27,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(209,7,28,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(210,7,29,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(211,7,30,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(212,7,3,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(213,8,1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(214,8,2,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(215,8,31,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(216,8,32,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(217,8,33,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(218,8,34,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(219,8,35,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(220,8,36,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(221,8,3,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(222,8,10,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(223,9,1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(224,9,2,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(225,9,37,'2026-08-04 00:10:32','2026-08-04 00:10:32');
INSERT INTO `role_permissions` VALUES (226,9,39,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(227,9,40,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(228,9,41,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(229,9,42,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(230,9,43,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(231,9,44,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(232,9,3,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(233,10,1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(234,10,2,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(235,10,55,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(236,10,56,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(237,10,62,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(238,10,57,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(239,10,58,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(240,10,59,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(241,10,60,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(242,10,61,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(243,10,63,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(244,10,64,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(245,10,65,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(246,10,66,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(247,10,67,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(248,10,75,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(249,10,76,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(250,10,77,'2026-08-04 00:10:32','2026-08-04 00:10:32');
INSERT INTO `role_permissions` VALUES (251,10,78,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(252,10,68,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(253,10,17,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(254,11,1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(255,11,2,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(256,11,79,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(257,11,80,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(258,11,17,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(259,11,3,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(260,11,25,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(261,11,22,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(262,11,55,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(263,11,62,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(264,11,63,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(265,11,64,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(266,11,58,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(267,11,68,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(268,11,71,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(269,11,75,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(270,11,66,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(271,11,37,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(272,12,89,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(273,12,3,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(274,12,4,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(275,12,5,'2026-08-04 00:10:32','2026-08-04 00:10:32');
INSERT INTO `role_permissions` VALUES (276,12,6,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(277,12,14,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(278,12,15,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(279,12,17,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(280,12,18,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(281,12,37,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(282,12,87,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(283,12,88,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(284,13,17,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(285,13,83,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(286,13,84,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(287,13,38,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(288,13,37,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(289,13,85,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(290,13,86,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(291,13,87,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(292,13,88,'2026-08-04 00:10:32','2026-08-04 00:10:32');
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'super_admin','Super Admin','System role: super_admin',1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(2,'admin','Admin','System role: admin',1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(3,'product_manager','Product Manager','System role: product_manager',1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(4,'inventory_manager','Inventory Manager','System role: inventory_manager',1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(5,'order_manager','Order Manager','System role: order_manager',1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(6,'customer_support','Customer Support','System role: customer_support',1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(7,'vendor_manager','Vendor Manager','System role: vendor_manager',1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(8,'marketing_manager','Marketing Manager','System role: marketing_manager',1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(9,'review_moderator','Review Moderator','System role: review_moderator',1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(10,'finance_manager','Finance Manager','System role: finance_manager',1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(11,'auditor','Auditor','System role: auditor',1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(12,'vendor','Vendor','System role: vendor',1,'2026-08-04 00:10:32','2026-08-04 00:10:32'),(13,'customer','Customer','System role: customer',1,'2026-08-04 00:10:32','2026-08-04 00:10:32');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `security_events`
--

DROP TABLE IF EXISTS `security_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `security_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `event` varchar(80) NOT NULL,
  `severity` varchar(16) NOT NULL DEFAULT 'medium',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `request_id` varchar(64) DEFAULT NULL,
  `context` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `security_events_actor_user_id_foreign` (`actor_user_id`),
  KEY `security_events_event_index` (`event`),
  KEY `security_events_severity_index` (`severity`),
  KEY `security_events_request_id_index` (`request_id`),
  CONSTRAINT `security_events_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_events`
--

LOCK TABLES `security_events` WRITE;
/*!40000 ALTER TABLE `security_events` DISABLE KEYS */;
INSERT INTO `security_events` VALUES (1,1,'STEP_UP_REQUIRED','medium','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','53758521-4587-4ad0-9db0-1d607fb27888','{\"path\":\"admin\\/users\\/3\",\"method\":\"PUT\"}','2026-08-04 04:19:37');
/*!40000 ALTER TABLE `security_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settlement_holds`
--

DROP TABLE IF EXISTS `settlement_holds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settlement_holds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(40) NOT NULL,
  `vendor_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `order_item_id` bigint(20) unsigned DEFAULT NULL,
  `reason_code` varchar(40) NOT NULL,
  `source_type` varchar(40) DEFAULT NULL,
  `source_id` varchar(64) DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'TZS',
  `status` varchar(24) NOT NULL DEFAULT 'active',
  `reason` text DEFAULT NULL,
  `held_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `releases_at` timestamp NULL DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `released_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settlement_holds_reference_unique` (`reference`),
  KEY `settlement_holds_order_id_foreign` (`order_id`),
  KEY `settlement_holds_order_item_id_foreign` (`order_item_id`),
  KEY `settlement_holds_created_by_foreign` (`created_by`),
  KEY `settlement_holds_released_by_foreign` (`released_by`),
  KEY `settlement_holds_vendor_id_status_index` (`vendor_id`,`status`),
  KEY `settlement_holds_source_type_source_id_index` (`source_type`,`source_id`),
  CONSTRAINT `settlement_holds_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `settlement_holds_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `settlement_holds_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `settlement_holds_released_by_foreign` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `settlement_holds_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settlement_holds`
--

LOCK TABLES `settlement_holds` WRITE;
/*!40000 ALTER TABLE `settlement_holds` DISABLE KEYS */;
/*!40000 ALTER TABLE `settlement_holds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_roles`
--

DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_roles_user_id_role_id_unique` (`user_id`,`role_id`),
  KEY `user_roles_role_id_foreign` (`role_id`),
  CONSTRAINT `user_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_roles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_roles`
--

LOCK TABLES `user_roles` WRITE;
/*!40000 ALTER TABLE `user_roles` DISABLE KEYS */;
INSERT INTO `user_roles` VALUES (1,1,1,'2026-08-04 00:10:33','2026-08-04 00:10:33'),(2,2,13,'2026-08-04 00:10:33','2026-08-04 00:10:33'),(3,3,13,'2026-08-04 00:10:33','2026-08-04 00:10:33'),(4,4,12,'2026-08-04 00:10:33','2026-08-04 00:10:33'),(6,5,2,'2026-08-04 01:11:29','2026-08-04 01:11:29');
/*!40000 ALTER TABLE `user_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `role` enum('admin','vendor','customer') NOT NULL DEFAULT 'customer',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `mfa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `mfa_secret` text DEFAULT NULL,
  `mfa_confirmed_at` timestamp NULL DEFAULT NULL,
  `mfa_recovery_codes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Aminieli Mrindoko','admin@gmail.com',NULL,'+255697780405',NULL,'$2y$12$dV3.GiOM6NwIxsyVkIVfi.2wCQZvJgzgw63X30bXnOSvjITZ3RVTS',NULL,'admin',1,0,'eyJpdiI6IlpkS1p2aE1DRWRTVmRUT2hNVGNnZWc9PSIsInZhbHVlIjoiTTIvT2oyT2UvU0tyanhYaGVrMVZUZGFXREwzM0pzRHBsUFZvRXlSQXZBaGNNcHozejNKR25pOU5NZFBwQnhZUSIsIm1hYyI6IjRkM2M3MGNkYzIwMWRjOTZkMTNlZGIyMmFiZTFmOWEwYmQxYzZjZGEwMDdhMTE2M2YxZmU0Y2IzNWU2NGQ5OWYiLCJ0YWciOiIifQ==',NULL,'eyJpdiI6Im1CaWp0ZnRoSW94Ui9KQzhzU2k1aWc9PSIsInZhbHVlIjoiMEYxV0s4aEk3d1U1dmd5T0hzaGx0SXBOWVc5ZHlZRVBMU004VmxKbE9LME9kUFBZTjF1d2hwb3VSWldGaWhqY3RzK25jZDFiOFZJQ3Q4YkN6N2xqZVVMTkVVOUxGamg4V0JsWWFRWFM1VnV1dTYxdGVvbVMxK0h1cGpYaElsVTQ1ZllqMEFIVFdTbmJTMWp6eldPNHN2ZmRUTkNsYmU0UTBuYm9kWVhhbjh6K2pnVVB3Wmt2NUNBZ0ZPdS94cHc2RmFaK0krdzkxM25KbXJ1NS9uOEtlQzdWTFFKWkNXaStxVU1VcWFUS0hsU1Zva0Y0TEZwNTlQU3QzNkxVUy9rVXdRUXZML1U1K0NuNGF6ZVY4VDhsY1V1Q2gzTWJNdGNoTTgzWk5STkF6ZElsZnBCUW02Y0YwelZ1bG9kbkhtM2lmZDhjYkxKWDBRSkswWXVDUGRnRGIwRHJ6N2Y1UTVlcGd0bHluMCtISllJanpseDlKN05lak9ha3I2NzYrekkrb0NnT0JnaEc5MHdjYkJ1QjB2VWJRNFFaaU9qVzVxcEtVMWhKK3kzbmYzQzg5clhNTGErbnc4aEJPTjl6Y29lcWxCSmVyMXJTQmRiWEJTcTZhQVpuKzFyMWMxSlduTTA0Y01UUWJDZTIzMFhuNHdPajdyWmlYMUMxeUZ2eURRempaT3ltTURMUEt3ZGcwUU94dDV3K0lPeWRJUHNhSFBlOTdkVXJVTWFPZ01CQ1JSY0FLL1ZySjhyTXozTHg1SDMyN04wc1dQTlAvcGwyc1pZN3gzVnUxNFFObDlPK1V2MXRJdndHNkwzMTAvTStJVHdsMW9YTWdrUXdZZlhna0lTc3JZWU9FT0JaVUduS1BUdk1WTjczaTltTXc3NGVMVWNJVkJwSXNTVzB0UW89IiwibWFjIjoiNGU2NzJhMWY2MDJhZTYyY2EyYWY0YzYwZjdkNjUzYWRhNmQ2NGU5OGNhMzAwY2JiZjY1MWY4ZDhjOWRkMzUxNiIsInRhZyI6IiJ9','2026-06-16 18:32:34','2026-08-04 04:22:37'),(2,'Test User','test@example.com',NULL,'+255700000002',NULL,'$2y$12$f3dSlPQApo/ODTbS9oADBeTOWrh1oi5lt6kTCCy6Qkce7JasPRyDW',NULL,'customer',1,0,NULL,NULL,NULL,'2026-06-16 18:32:34','2026-07-24 03:58:27'),(3,'Aminieli','amini@gmail.com',NULL,'0697780405','avatars/bsAqewRZojHQggEuWCmvxPMnO97HWAsivYaCPfLT.jpg','$2y$12$z2KWQFa.MvT1VX2gfKpQzu1sSvCddFsNB8hg4QZG9dnKaP1svmG6u',NULL,'customer',1,0,NULL,NULL,NULL,'2026-06-16 19:49:50','2026-07-24 17:04:34'),(4,'Seller Demo','seller@example.com',NULL,'+255700000003',NULL,'$2y$12$BGkAyvtpfbsMepDuGVNc3ulR1MLhB6L60eLRFbQzels8tgijpD7sC',NULL,'vendor',1,0,NULL,NULL,NULL,'2026-07-24 03:58:27','2026-07-24 03:58:27'),(5,'Administrator','admin2@example.com',NULL,'0697780405','avatars/WNWnkmE0zW8CYsCdPL0IuirxFbS7SRVY466HdqJM.jpg','$2y$12$bnvHumyQS2uKBwiMuwczeeedSgkXO/DV7D.17vjOePLbAI28E/U/y',NULL,'admin',1,0,NULL,NULL,NULL,'2026-07-25 10:42:59','2026-07-25 14:15:47');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vendor_entitlements`
--

DROP TABLE IF EXISTS `vendor_entitlements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_entitlements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `order_item_id` bigint(20) unsigned NOT NULL,
  `vendor_id` bigint(20) unsigned NOT NULL,
  `payment_transaction_id` bigint(20) unsigned DEFAULT NULL,
  `gross_amount` decimal(14,2) NOT NULL,
  `commission_rate` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `commission_type` varchar(32) NOT NULL DEFAULT 'percentage',
  `commission_amount` decimal(14,2) NOT NULL,
  `net_amount` decimal(14,2) NOT NULL,
  `refunded_gross` decimal(14,2) NOT NULL DEFAULT 0.00,
  `refunded_commission` decimal(14,2) NOT NULL DEFAULT 0.00,
  `refunded_net` decimal(14,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'TZS',
  `status` varchar(32) NOT NULL DEFAULT 'earned',
  `available_at` timestamp NULL DEFAULT NULL,
  `calculation_snapshot` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_entitlements_order_item_id_unique` (`order_item_id`),
  KEY `vendor_entitlements_order_id_foreign` (`order_id`),
  KEY `vendor_entitlements_vendor_id_status_index` (`vendor_id`,`status`),
  KEY `vendor_entitlements_payment_transaction_id_index` (`payment_transaction_id`),
  CONSTRAINT `vendor_entitlements_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_entitlements_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_entitlements_payment_transaction_id_foreign` FOREIGN KEY (`payment_transaction_id`) REFERENCES `payment_transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_entitlements_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vendor_entitlements`
--

LOCK TABLES `vendor_entitlements` WRITE;
/*!40000 ALTER TABLE `vendor_entitlements` DISABLE KEYS */;
/*!40000 ALTER TABLE `vendor_entitlements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vendor_payouts`
--

DROP TABLE IF EXISTS `vendor_payouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_payouts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(64) NOT NULL,
  `idempotency_key` varchar(128) DEFAULT NULL,
  `vendor_id` bigint(20) unsigned NOT NULL,
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `processed_by` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'TZS',
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `provider` varchar(40) NOT NULL DEFAULT 'stub',
  `provider_reference` varchar(128) DEFAULT NULL,
  `destination_token` varchar(128) DEFAULT NULL,
  `failure_code` varchar(64) DEFAULT NULL,
  `failure_reason` varchar(500) DEFAULT NULL,
  `ledger_transaction_id` bigint(20) unsigned DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `requested_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_payouts_reference_unique` (`reference`),
  UNIQUE KEY `vendor_payouts_idempotency_key_unique` (`idempotency_key`),
  UNIQUE KEY `vendor_payouts_provider_reference_unique` (`provider_reference`),
  KEY `vendor_payouts_requested_by_foreign` (`requested_by`),
  KEY `vendor_payouts_approved_by_foreign` (`approved_by`),
  KEY `vendor_payouts_processed_by_foreign` (`processed_by`),
  KEY `vendor_payouts_ledger_transaction_id_foreign` (`ledger_transaction_id`),
  KEY `vendor_payouts_vendor_id_status_index` (`vendor_id`,`status`),
  KEY `vendor_payouts_status_index` (`status`),
  CONSTRAINT `vendor_payouts_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_payouts_ledger_transaction_id_foreign` FOREIGN KEY (`ledger_transaction_id`) REFERENCES `ledger_transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_payouts_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_payouts_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_payouts_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vendor_payouts`
--

LOCK TABLES `vendor_payouts` WRITE;
/*!40000 ALTER TABLE `vendor_payouts` DISABLE KEYS */;
/*!40000 ALTER TABLE `vendor_payouts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vendors`
--

DROP TABLE IF EXISTS `vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `store_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(32) NOT NULL DEFAULT 'approved',
  `financial_status` varchar(32) NOT NULL DEFAULT 'active',
  `application_notes` text DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `rating_avg` decimal(3,2) NOT NULL DEFAULT 4.50,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendors_user_id_unique` (`user_id`),
  KEY `vendors_reviewed_by_foreign` (`reviewed_by`),
  KEY `vendors_status_index` (`status`),
  KEY `vendors_financial_status_index` (`financial_status`),
  CONSTRAINT `vendors_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendors_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vendors`
--

LOCK TABLES `vendors` WRITE;
/*!40000 ALTER TABLE `vendors` DISABLE KEYS */;
INSERT INTO `vendors` VALUES (5,4,'Tech Haven','techhaven@sana.com','https://images.unsplash.com/photo-1560472354-b33ff0c44a43?auto=format&fit=crop&w=200&q=80','Dar es Salaam','Authorized electronics & gadgets',1,'approved','active',NULL,NULL,NULL,4.80,'2026-07-24 03:58:27','2026-07-24 03:58:27'),(6,NULL,'Fashion Plus','fashionplus@sana.com','https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=200&q=80','Arusha','Global streetwear & premium fashion',1,'approved','active',NULL,NULL,NULL,4.60,'2026-07-24 03:58:27','2026-07-24 03:58:27'),(7,NULL,'Home Essentials','homeessentials@sana.com','https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=200&q=80','Mwanza','Modern furniture for every room',1,'approved','active',NULL,NULL,NULL,4.50,'2026-07-24 03:58:27','2026-07-24 03:58:27'),(8,NULL,'Beauty & Wellness','beauty@sana.com','https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=200&q=80','Dodoma','Clean beauty and self-care',1,'approved','active',NULL,NULL,NULL,4.40,'2026-07-24 03:58:27','2026-07-25 14:07:00'),(9,NULL,'Apple Authorized','apple@sana.com','https://images.unsplash.com/photo-1611186871348-b1ce696e52c9?auto=format&fit=crop&w=200&q=80','Dar es Salaam','Genuine Apple products & accessories',1,'approved','active',NULL,NULL,NULL,4.90,'2026-07-24 03:58:27','2026-07-24 03:58:27');
/*!40000 ALTER TABLE `vendors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wishlists`
--

DROP TABLE IF EXISTS `wishlists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wishlists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wishlists_user_id_product_id_unique` (`user_id`,`product_id`),
  KEY `wishlists_product_id_foreign` (`product_id`),
  CONSTRAINT `wishlists_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wishlists_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wishlists`
--

LOCK TABLES `wishlists` WRITE;
/*!40000 ALTER TABLE `wishlists` DISABLE KEYS */;
INSERT INTO `wishlists` VALUES (1,5,28,'2026-07-25 14:21:46','2026-07-25 14:21:46');
/*!40000 ALTER TABLE `wishlists` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-04  8:43:48
