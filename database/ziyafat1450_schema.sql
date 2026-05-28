-- Ziyafat1450 Database Schema (Batch 2)
-- This file contains the table structure only (no data)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ========================================
-- Table: amali_activity_log
-- ========================================
CREATE TABLE IF NOT EXISTS `amali_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `activity_type` enum('quran','dua','book') NOT NULL,
  `activity_details` text DEFAULT NULL,
  `activity_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: books_master
-- ========================================
CREATE TABLE IF NOT EXISTS `books_master` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `book_name` varchar(255) NOT NULL,
  `book_name_arabic` varchar(255) DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `total_pages` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: book_transcription
-- ========================================
CREATE TABLE IF NOT EXISTS `book_transcription` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `pages_completed` int(11) DEFAULT 0,
  `started_date` date DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `status` enum('selected','completed') DEFAULT 'selected',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `book_id` (`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: contributions
-- ========================================
CREATE TABLE IF NOT EXISTS `contributions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `amount_usd` decimal(10,2) DEFAULT 0.00,
  `amount_inr` decimal(10,2) DEFAULT 0.00,
  `payment_year` varchar(20) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: duas_master
-- ========================================
CREATE TABLE IF NOT EXISTS `duas_master` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `dua_name` varchar(255) NOT NULL,
  `dua_name_arabic` varchar(255) DEFAULT NULL,
  `category` enum('dua','tasbeeh','namaz') DEFAULT 'dua',
  `target_count` int(11) DEFAULT 100,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: dua_entries
-- ========================================
CREATE TABLE IF NOT EXISTS `dua_entries` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `dua_id` int(11) NOT NULL,
  `count_added` int(11) DEFAULT 0,
  `entry_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `dua_id` (`dua_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: dua_progress
-- ========================================
CREATE TABLE IF NOT EXISTS `dua_progress` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `dua_id` int(11) NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_dua` (`user_id`,`dua_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: quran_progress
-- ========================================
CREATE TABLE IF NOT EXISTS `quran_progress` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `quran_number` int(11) NOT NULL,
  `juz_number` int(11) NOT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `completed_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: system_settings
-- ========================================
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `target_amount_usd` decimal(10,2) DEFAULT 500.00,
  `target_amount_inr` decimal(12,2) DEFAULT 42000.00,
  `exchange_rate` decimal(10,4) DEFAULT 84.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: users
-- ========================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `its_number` varchar(50) NOT NULL,
  `tr_number` varchar(50) DEFAULT NULL,
  `category` enum('Surat','Marol','Karachi','Nairobi','Muntasib') DEFAULT NULL,
  `classification` enum('Talabat','Taalebaat','Muntasebeen','Muntasebaat') NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `admin_type` enum('super_admin','finance_admin','amali_coordinator','surat_amali_coordinator','marol_amali_coordinator','karachi_amali_coordinator','nairobi_amali_coordinator','muntasib_amali_coordinator') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `its_number` (`its_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: user_amali_summary
-- ========================================
CREATE TABLE IF NOT EXISTS `user_amali_summary` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `its_number` varchar(50) NOT NULL,
  `completed_juz` int(11) DEFAULT 0,
  `total_juz_target` int(11) DEFAULT 30,
  `quran_progress_percentage` decimal(5,2) DEFAULT 0.00,
  `completed_qurans` int(11) DEFAULT 0,
  `total_dua_count` int(11) DEFAULT 0,
  `books_completed` int(11) DEFAULT 0,
  `books_in_progress` int(11) DEFAULT 0,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Insert system settings
-- ========================================
INSERT IGNORE INTO `system_settings` (`id`, `target_amount_usd`, `target_amount_inr`, `exchange_rate`)
VALUES (1, 500.00, 42000.00, 84.00);

COMMIT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
