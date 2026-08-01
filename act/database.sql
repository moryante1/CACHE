-- قاعدة بيانات IPTV محسّنة
CREATE DATABASE IF NOT EXISTS license_server CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE license_server;

DROP TABLE IF EXISTS `licenses`;
DROP TABLE IF EXISTS `verification_logs`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `statistics`;

CREATE TABLE `licenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `machine_id` varchar(255) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `license_key` varchar(100) NOT NULL,
  `license_type` enum('trial_1day','trial_1week','trial_1month','monthly','yearly','lifetime') NOT NULL,
  `activation_date` datetime NOT NULL,
  `expiry_date` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `ip_address` varchar(45) DEFAULT NULL,
  `last_check` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `machine_id` (`machine_id`),
  UNIQUE KEY `license_key` (`license_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `verification_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `machine_id` varchar(255) NOT NULL,
  `license_key` varchar(100) NOT NULL,
  `action` varchar(50) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `response` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `machine_id` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','error','success') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `statistics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `total_checks` int(11) DEFAULT 0,
  `active_licenses` int(11) DEFAULT 0,
  `expired_licenses` int(11) DEFAULT 0,
  `new_activations` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## ✅ **التحقق:**
```
في phpMyAdmin:
license_server → يجب أن ترى:
✅ licenses
✅ verification_logs
✅ notifications  
✅ statistics
```

---

## 🧪 **الاختبار:**
```
http://localhost/act/
→ صفحة تسجيل الدخول ✅
→ كلمة المرور: admin@2024