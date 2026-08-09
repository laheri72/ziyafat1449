<?php
require_once __DIR__ . '/../config/database.php';

$sql1 = "CREATE TABLE IF NOT EXISTS `ziyarat_mail_campaigns` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_tag` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `target_count` INT NOT NULL DEFAULT 30,
  `custom_message` TEXT DEFAULT NULL,
  `status` ENUM('active', 'completed') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$sql2 = "CREATE TABLE IF NOT EXISTS `ziyarat_mail_sent_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `campaign_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `tr_number` VARCHAR(50) NOT NULL,
  `status` ENUM('success', 'failed') NOT NULL,
  `error_message` TEXT DEFAULT NULL,
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_z_campaign` (`campaign_id`),
  INDEX `idx_z_user` (`user_id`),
  INDEX `idx_z_tr` (`tr_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql1) && $conn->query($sql2)) {
    echo "Ziyarat Broadcast tables created successfully!\n";
} else {
    echo "Error creating tables: " . $conn->error . "\n";
}
