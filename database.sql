-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: 23 مارس 2026 الساعة 17:33
-- إصدار الخادم: 8.0.45-0ubuntu0.22.04.1
-- PHP Version: 8.1.2-1ubuntu2.23

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `iptv_db`
--
CREATE DATABASE IF NOT EXISTS `iptv_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `iptv_db`;

-- --------------------------------------------------------

--
-- بنية الجدول `admins`
--

CREATE TABLE `admins` (
  `id` int NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `email`, `last_login`, `created_at`) VALUES
(1, 'admin', '$2y$10$YzE3MTI2ZTQ0YjBmNzJhN.qK5N5gGZWJvMDAwMDAwMDAwMDAwMDE', 'admin@shashety.tv', NULL, '2026-02-19 22:39:34');

-- --------------------------------------------------------

--
-- بنية الجدول `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `role` varchar(20) DEFAULT 'normal',
  `allowed_sections` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- إرجاع أو استيراد بيانات الجدول `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password_hash`, `display_name`, `role`, `allowed_sections`, `is_active`, `created_at`, `last_login`) VALUES
(1, 'admin', '$2y$10$9ke4p3mzv9RcTVvnyDxw3OqhbXS2lyS7RcsenzCJBXCCsau75Ev6G', 'Admin', 'administrator', '[]', 1, '2026-02-26 21:37:19', '2026-03-23 17:32:14');

-- --------------------------------------------------------

--
-- بنية الجدول `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'fas fa-folder',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `display_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `parent_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `icon`, `description`, `display_order`, `is_active`, `created_at`, `updated_at`, `parent_id`) VALUES
(1, 'رياضة', 'sports', 'fas fa-futbol', 'قنوات رياضية متنوعة', 1, 1, '2026-01-26 19:42:38', '2026-02-20 12:53:17', NULL),
(2, 'أفلام', 'movies', 'fas fa-film', 'أحدث الأفلام والسينما', 2, 1, '2026-01-26 19:42:38', '2026-02-20 12:53:17', NULL),
(3, 'منوعات رمضان', 'series', 'fas fa-star', 'مسلسلات عربية وأجنبية', 3, 1, '2026-01-26 19:42:38', '2026-03-15 12:57:54', NULL),
(5, 'إخبارية', 'news', 'fas fa-newspaper', 'آخر الأخبار المحلية والعالمية', 5, 1, '2026-01-26 19:42:38', '2026-02-20 12:53:17', NULL),
(14, 'افلام كارتون', 'cat-1772461463-289', 'fas fa-th-large', '', 0, 1, '2026-03-02 14:24:23', '2026-03-13 22:41:22', NULL),
(19, 'اطفال', 'cat-1773698423-897', 'fas fa-th-large', 'fas fa-child', 0, 1, '2026-03-16 22:00:23', '2026-03-16 22:00:23', NULL),
(21, 'انمي', 'cat-1773698628-624', 'fas fa-th-large', '', 0, 1, '2026-03-16 22:03:48', '2026-03-19 09:03:09', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `channels`
--

CREATE TABLE `channels` (
  `id` int NOT NULL,
  `category_id` int NOT NULL,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stream_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle_url` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `logo_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_icon` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'fas fa-tv',
  `backup_url` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `playlist_id` int DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `quality` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'HD',
  `language` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'عربي',
  `country` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `views_count` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `is_featured` tinyint(1) DEFAULT '0',
  `display_order` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- بنية الجدول `m3u_playlists`
--

CREATE TABLE `m3u_playlists` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `source_type` enum('file','url') DEFAULT 'url',
  `source_url` varchar(1000) DEFAULT '',
  `channels_count` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `channels`
--

INSERT INTO `channels` (`id`, `category_id`, `name`, `slug`, `stream_url`, `subtitle_url`, `logo_url`, `logo_icon`, `description`, `quality`, `language`, `country`, `views_count`, `is_active`, `is_featured`, `display_order`, `created_at`, `updated_at`) VALUES
(7, 5, 'Al-Sharqiya News-1', 'al-sharqiya-news-1', 'https://5d94523502c2d.streamlock.net/alsharqiyalive/mystream/playlist.m3u8', '', '/iptv/uploads/posters/logo_69bbbc2d1f3ac.jpg', 'fas fa-tv', '', 'HD', 'عربي', NULL, 55, 1, 0, 0, '2026-01-26 19:43:06', '2026-03-23 17:27:16');

-- --------------------------------------------------------

--
-- بنية الجدول `episodes`
--

CREATE TABLE `episodes` (
  `id` int NOT NULL,
  `series_id` int NOT NULL,
  `episode_number` int DEFAULT '1',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `stream_url` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `subtitle_url` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `duration` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `display_order` int DEFAULT '0',
  `views_count` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `episodes`
--

INSERT INTO `episodes` (`id`, `series_id`, `episode_number`, `title`, `stream_url`, `subtitle_url`, `duration`, `description`, `display_order`, `views_count`, `created_at`) VALUES
(211, 25, 1, 'alfashafish1', '/iptv/uploads/series/ep_25_69b47b10f078e.mp4', '', '', NULL, 1, 5, '2026-03-13 21:01:05'),
(253, 26, 1, 'hubizbuz1', '/iptv/uploads/series/ep_26_69b48928d8d01.mp4', '', '', NULL, 1, 2, '2026-03-13 22:01:13');

-- --------------------------------------------------------

--
-- بنية الجدول `series`
--

CREATE TABLE `series` (
  `id` int NOT NULL,
  `category_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `poster_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `logo_icon` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'fas fa-film',
  `display_order` int DEFAULT '0',
  `views_count` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `series`
--

INSERT INTO `series` (`id`, `category_id`, `name`, `slug`, `description`, `poster_url`, `logo_icon`, `display_order`, `views_count`, `is_active`, `created_at`) VALUES
(25, 3, 'الفشافيش', '-69ad67c8c3d42', '', 'https://image.tmdb.org/t/p/w500/qc6Ykqed9MRlR2cXoRZhZkD849c.jpg', 'fas fa-film', 0, 33, 1, '2026-03-08 12:12:56'),
(26, 3, 'حبزبوز', '-69ad68931dd67', '', 'https://image.tmdb.org/t/p/w500/fpTjFO3mNx1wxIyxIN25W58YPqy.jpg', 'fas fa-film', 0, 32, 1, '2026-03-08 12:16:19');

-- --------------------------------------------------------

--
-- بنية الجدول `settings`
--

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'site_name', 'SHASHETY PRO', '2026-03-02 14:34:02'),
(2, 'site_description', 'نظام شاشتي احترافي يقدّم بثًا مباشرًا مستقرًا بجودة عالية دون تقطيع، مع مكتبة أفلام حصرية تضمن تجربة مشاهدة متكاملة وموثوقة بأعلى معايير الأداء.', '2026-03-02 14:48:37'),
(3, 'site_logo', '', '2026-02-19 22:39:34'),
(4, 'welcome_title', 'مرحباً بك في عالم البث المباشر', '2026-02-19 22:39:34'),
(5, 'welcome_subtitle', 'شاهد الافلام والمسلسلات عالم البث المباشر', '2026-03-02 14:50:27'),
(6, 'footer_text', 'جميع الحقوق محفوظة © 2026 SHASHETY PRO', '2026-03-02 14:34:02'),
(7, 'contact_phone', '009647512328848', '2026-02-19 22:39:34'),
(8, 'contact_email', 'info@shashety.tv', '2026-02-19 22:39:34'),
(9, 'contact_facebook', 'https://facebook.com/xxkpq', '2026-02-19 22:39:34'),
(10, 'contact_whatsapp', 'https://wa.me/9647512328848', '2026-02-19 22:39:34'),
(11, 'theme_color', '#e50914', '2026-03-02 14:34:02'),
(12, 'show_categories_count', '1', '2026-02-19 22:39:34'),
(13, 'show_channels_count', '1', '2026-02-19 22:39:34'),
(14, 'maintenance_mode', '0', '2026-02-19 22:39:34'),
(15, 'analytics_enabled', '1', '2026-02-19 22:39:34'),
(29, 'tmdb_api_key', '83d373edc8a5aed75134929d1a9b3eab', '2026-02-23 06:33:31'),
(30, 'os_username', 'admin', '2026-03-23 17:31:28'),
(31, 'os_password', '10102525555', '2026-03-23 17:31:28'),
(32, 'os_api_key', '83d373edc8a5aed75134929d1a9b04252', '2026-03-23 17:31:28'),
(33, 'omdb_api_key', 'http://www.omdbapi.com/?i=tt3896198&apikey=c8a63a4c', '2026-02-27 14:25:08'),
(34, 'dlna_enabled', '1', '2026-03-02 11:53:18'),
(35, 'enable_cast', '1', '2026-03-02 14:04:38'),
(46, 'contact_twitter', '', '2026-03-02 14:34:02'),
(47, 'contact_telegram', '', '2026-03-02 14:34:02'),
(48, 'contact_youtube', '', '2026-03-02 14:34:02'),
(49, 'contact_instagram', '', '2026-03-02 14:34:02'),
(51, 'notification_title', '', '2026-03-02 14:34:02'),
(52, 'notification_body', '', '2026-03-02 14:34:02');

-- --------------------------------------------------------

--
-- بنية الجدول `trial_users`
--

CREATE TABLE `trial_users` (
  `id` int NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `max_devices` int DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `trial_users`
--

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','user') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'admin',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$9ke4p3mzv9RcTVvnyDxw3OqhbXS2lyS7RcsenzCJBXCCsau75Ev6G', NULL, 'admin', '2026-03-23 17:28:37', '2026-02-19 22:39:34', '2026-03-23 17:28:37');

-- --------------------------------------------------------

--
-- بنية الجدول `view_stats`
--

CREATE TABLE `view_stats` (
  `id` int NOT NULL,
  `channel_id` int NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `viewed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `view_stats`
--

INSERT INTO `view_stats` (`id`, `channel_id`, `ip_address`, `user_agent`, `viewed_at`) VALUES
(14, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 00:37:27'),
(16, 21, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 00:39:02'),
(17, 22, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 00:42:48'),
(18, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 01:02:18'),
(19, 12, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 12:57:45'),
(20, 13, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-02-20 14:36:57'),
(21, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/144.0.7559.132 Mobile Safari/537.36 Vinebre', '2026-02-20 16:00:48'),
(22, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/144.0.7559.132 Mobile Safari/537.36 Vinebre', '2026-02-20 16:01:01'),
(23, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/144.0.7559.132 Mobile Safari/537.36 Vinebre', '2026-02-20 16:01:43'),
(24, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/144.0.7559.132 Mobile Safari/537.36 Vinebre', '2026-02-20 16:02:06'),
(25, 7, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 16:12:02'),
(26, 7, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 16:16:21'),
(27, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/144.0.7559.133 Mobile Safari/537.36 Vinebre', '2026-02-20 16:17:35'),
(28, 7, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 16:33:56'),
(29, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/144.0.7559.132 Mobile Safari/537.36 Vinebre', '2026-02-20 16:36:30'),
(30, 7, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 16:38:30'),
(31, 7, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 17:00:07'),
(32, 7, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 17:01:59'),
(33, 13, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 17:15:58'),
(34, 7, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-20 17:39:03'),
(35, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/144.0.7559.132 Mobile Safari/537.36 Vinebre', '2026-02-20 17:42:07'),
(36, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36 OPR/95.0.0.0', '2026-02-20 18:18:50'),
(37, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/144.0.7559.132 Mobile Safari/537.36 Vinebre', '2026-02-20 20:47:21'),
(38, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/144.0.7559.133 Mobile Safari/537.36 Vinebre', '2026-02-20 20:49:53'),
(39, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-21 10:07:35'),
(40, 7, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 10:29:53'),
(41, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-02-21 11:27:57'),
(42, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-02-21 11:28:13'),
(43, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 2412DPC0AG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/144.0.7559.132 Mobile Safari/537.36 Vinebre', '2026-02-21 12:28:08'),
(44, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-02-21 12:50:18'),
(45, 7, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 13:29:14'),
(46, 13, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 13:37:22'),
(47, 13, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 13:37:40'),
(48, 16, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:24:24'),
(49, 16, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:28:09'),
(50, 16, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:28:31'),
(51, 16, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:30:18'),
(52, 16, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:30:41'),
(53, 16, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:34:37'),
(54, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/144.0.7559.132 Mobile Safari/537.36 Vinebre', '2026-02-21 14:53:33'),
(55, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-02-21 14:55:53'),
(56, 16, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-21 15:31:26'),
(57, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/144.0.7559.132 Mobile Safari/537.36 Vinebre', '2026-02-21 15:32:58'),
(58, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-21 15:34:05'),
(59, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-02-21 15:34:44'),
(60, 18, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-02-21 16:37:55'),
(61, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-02-21 16:38:05'),
(62, 19, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 16:48:05'),
(63, 19, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 16:51:33'),
(64, 20, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 16:51:36'),
(65, 20, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 16:52:03'),
(66, 18, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 2412DPC0AG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/144.0.7559.132 Mobile Safari/537.36 Vinebre', '2026-02-21 17:07:20'),
(67, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 2412DPC0AG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/144.0.7559.132 Mobile Safari/537.36 Vinebre', '2026-02-21 17:07:24'),
(68, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/144.0.7559.132 Mobile Safari/537.36 Vinebre', '2026-02-21 17:29:33'),
(69, 16, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 22:21:37'),
(70, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-22 09:59:52'),
(71, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-22 10:00:05'),
(72, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-22 12:12:11'),
(73, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 9; SMART TV Build/PPR2.180905.006.A1; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/66.0.3359.158 Safari/537.36 Vinebre', '2026-02-22 12:12:28'),
(74, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 9; SMART TV Build/PPR2.180905.006.A1; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/66.0.3359.158 Safari/537.36 Vinebre', '2026-02-22 12:12:55'),
(75, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-22 13:13:32'),
(76, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-22 16:14:28'),
(77, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-22 20:26:50'),
(78, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-22 20:36:06'),
(79, 21, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-22 22:24:16'),
(80, 21, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-22 22:42:00'),
(81, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-22 22:42:08'),
(82, 21, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-23 05:58:18'),
(83, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-23 06:34:43'),
(84, 22, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-23 06:38:16'),
(85, 22, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-23 06:39:26'),
(86, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-23 07:05:09'),
(87, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-23 08:17:24'),
(88, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 2412DPC0AG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/144.0.7559.132 Mobile Safari/537.36 Vinebre', '2026-02-23 13:00:53'),
(89, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-23 16:38:52'),
(90, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-23 16:47:28'),
(91, 23, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-23 16:51:12'),
(92, 23, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-23 16:51:18'),
(93, 25, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 01:16:15'),
(94, 26, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 01:19:28'),
(95, 25, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 2412DPC0AG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/144.0.7559.132 Mobile Safari/537.36 Vinebre', '2026-02-24 20:34:35'),
(96, 23, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-24 21:58:08'),
(97, 21, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-24 21:58:16'),
(98, 28, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-24 21:58:18'),
(99, 29, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-24 21:58:24'),
(100, 25, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-25 18:55:38'),
(101, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-25 18:55:41'),
(102, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-02-26 01:24:34'),
(103, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-02-26 01:25:06'),
(104, 16, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 01:32:41'),
(105, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/144.0.7559.132 Mobile Safari/537.36 Vinebre', '2026-02-26 15:57:28'),
(106, 16, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 21:40:32'),
(107, 16, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 21:47:13'),
(108, 25, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 21:47:46'),
(109, 16, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 21:48:06'),
(110, 16, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 21:57:07'),
(111, 25, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:00:24'),
(112, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:00:26'),
(113, 25, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:07:33'),
(114, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:07:37'),
(115, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:02'),
(116, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:02'),
(117, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:05'),
(118, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:06'),
(119, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:07'),
(120, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:08'),
(121, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:09'),
(122, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:09'),
(123, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:09'),
(124, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:09'),
(125, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:10'),
(126, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:10'),
(127, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:15'),
(128, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:17'),
(129, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:18'),
(130, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:19'),
(131, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:19'),
(132, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:20'),
(133, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:21'),
(134, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:23'),
(135, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:29:27'),
(136, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:33:45'),
(137, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-02-27 02:33:46'),
(138, 16, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 17:15:01'),
(139, 25, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 17:15:04'),
(140, 7, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 17:15:17'),
(141, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.221228.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-03-01 10:49:35'),
(142, 16, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 14:37:56'),
(143, 25, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-03-01 15:01:59'),
(144, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-03-01 15:27:31'),
(145, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-03-01 15:27:37'),
(146, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79 Mobile Safari/537.36 Vinebre', '2026-03-01 15:29:46'),
(147, 16, '10.5.50.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', '2026-03-01 20:14:28'),
(148, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-02 12:45:47'),
(149, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-02 13:18:54'),
(150, 16, '10.5.50.1', 'Mozilla/5.0 (Linux;  16; 24117RK2CG Build/BP2A.250605.031.A3; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.79  Safari/537.36', '2026-03-04 19:08:22'),
(151, 7, '10.5.50.1', 'Mozilla/5.0 (Linux;  16; 24117RK2CG Build/BP2A.250605.031.A3; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.120  Safari/537.36', '2026-03-08 12:36:10'),
(152, 7, '10.5.50.1', 'Mozilla/5.0 (Linux;  16; 24117RK2CG Build/BP2A.250605.031.A3; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.120  Safari/537.36', '2026-03-08 12:37:42'),
(153, 16, '10.5.50.1', 'Mozilla/5.0 (Linux;  12; Smart TV Pro Build/STT2.221228.001; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.120  Safari/537.36', '2026-03-08 14:59:52'),
(154, 16, '10.5.50.1', 'Mozilla/5.0 (Linux;  12; Smart TV Pro Build/STT2.221228.001; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.120  Safari/537.36', '2026-03-08 15:00:38'),
(155, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.159 Mobile Safari/537.36 Vinebre', '2026-03-12 14:48:26'),
(156, 25, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.159 Mobile Safari/537.36 Vinebre', '2026-03-12 14:48:43'),
(157, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-12 18:42:37'),
(158, 16, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 14; TECNO LH7n Build/UP1A.231005.007; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.120 Mobile Safari/537.36 Vinebre', '2026-03-12 21:43:56'),
(159, 25, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 14; TECNO LH7n Build/UP1A.231005.007; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.120 Mobile Safari/537.36 Vinebre', '2026-03-12 21:43:59'),
(160, 25, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.230203.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.122 Mobile Safari/537.36 Vinebre', '2026-03-14 06:19:48'),
(161, 25, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.230203.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.122 Mobile Safari/537.36 Vinebre', '2026-03-14 06:19:51'),
(162, 7, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 17:35:43'),
(163, 25, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; Smart TV Pro Build/STT2.230203.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.159 Mobile Safari/537.36 Vinebre', '2026-03-15 13:48:24'),
(164, 25, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.159 Mobile Safari/537.36 Vinebre', '2026-03-16 21:57:17'),
(165, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.159 Mobile Safari/537.36 Vinebre', '2026-03-16 21:58:29'),
(166, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.159 Mobile Safari/537.36 Vinebre', '2026-03-16 23:56:36'),
(167, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.159 Mobile Safari/537.36 Vinebre', '2026-03-17 11:06:49'),
(168, 7, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-17 16:41:09'),
(169, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-18 00:55:30'),
(170, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 13; 21051182G Build/TKQ1.221114.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.159 Safari/537.36 Vinebre', '2026-03-18 10:42:43'),
(171, 7, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-18 20:39:19'),
(172, 7, '10.5.50.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 09:05:06'),
(173, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.159 Mobile Safari/537.36 Vinebre', '2026-03-19 11:45:15'),
(174, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 16; 24117RK2CG Build/BP2A.250605.031.A3; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/145.0.7632.159 Mobile Safari/537.36 Vinebre', '2026-03-19 23:24:27'),
(175, 25, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; TGR-W09 Build/HUAWEITGR-W09; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36 Vinebre', '2026-03-20 11:44:24'),
(176, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; TGR-W09 Build/HUAWEITGR-W09; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36 Vinebre', '2026-03-20 11:44:28'),
(177, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; TGR-W09 Build/HUAWEITGR-W09; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36 Vinebre', '2026-03-20 14:16:46'),
(178, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; TGR-W09 Build/HUAWEITGR-W09; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36 Vinebre', '2026-03-20 14:17:10'),
(179, 7, '10.5.50.1', 'Mozilla/5.0 (Linux; Android 12; TGR-W09 Build/HUAWEITGR-W09; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36 Vinebre', '2026-03-20 14:17:33'),
(180, 7, '192.168.1.203', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-23 17:27:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_order` (`display_order`);

--
-- Indexes for table `channels`
--
ALTER TABLE `channels`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_featured` (`is_featured`),
  ADD KEY `idx_order` (`display_order`);

--
-- Indexes for table `episodes`
--
ALTER TABLE `episodes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `series`
--
ALTER TABLE `series`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_key` (`setting_key`);

--
-- Indexes for table `trial_users`
--
ALTER TABLE `trial_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `view_stats`
--
ALTER TABLE `view_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_channel` (`channel_id`),
  ADD KEY `idx_viewed_at` (`viewed_at`);

--
-- Indexes for table `m3u_playlists`
--
ALTER TABLE `m3u_playlists`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `channels`
--
ALTER TABLE `channels`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `episodes`
--
ALTER TABLE `episodes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1808;

--
-- AUTO_INCREMENT for table `series`
--
ALTER TABLE `series`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `trial_users`
--
ALTER TABLE `trial_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `view_stats`
--
ALTER TABLE `view_stats`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=181;

--
-- AUTO_INCREMENT for table `m3u_playlists`
--
ALTER TABLE `m3u_playlists`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- قيود الجداول المحفوظة
--

--
-- القيود للجدول `channels`
--
ALTER TABLE `channels`
  ADD CONSTRAINT `channels_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
