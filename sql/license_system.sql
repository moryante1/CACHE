-- ════════════════════════════════════════════════════════════════
-- نظام الرخص - License System
-- ════════════════════════════════════════════════════════════════

-- جدول الرخص
CREATE TABLE IF NOT EXISTS `licenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `machine_id` varchar(255) NOT NULL UNIQUE,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `license_type` enum('trial_1day','trial_1week','trial_1month','monthly','yearly','lifetime') NOT NULL,
  `activation_date` datetime NOT NULL,
  `expiry_date` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_check` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `machine_id` (`machine_id`),
  KEY `expiry_date` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول سجل التفعيل
CREATE TABLE IF NOT EXISTS `license_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `machine_id` varchar(255) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `machine_id` (`machine_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════
-- تم! الآن يمكنك إدارة الرخص
-- ════════════════════════════════════════════════════════════════
