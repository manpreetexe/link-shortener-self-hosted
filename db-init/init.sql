CREATE TABLE `links` (
  `link_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(10) UNSIGNED DEFAULT NULL,
  `short_code` VARCHAR(20) NOT NULL,
  `original_url` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `custom_alias` VARCHAR(255) DEFAULT NULL,

  PRIMARY KEY (`link_id`),
  UNIQUE KEY `uniq_short_code` (`short_code`),
  UNIQUE KEY `uniq_custom_alias` (`custom_alias`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
