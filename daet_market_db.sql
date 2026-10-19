-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 16, 2025 at 01:36 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `daet_market_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_profiles`
--

CREATE TABLE `admin_profiles` (
  `profile_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `profile_photo` varchar(500) DEFAULT NULL,
  `site_name` varchar(255) DEFAULT 'Market Clearance Portal',
  `site_logo` varchar(500) DEFAULT NULL,
  `intro_video` varchar(500) DEFAULT NULL,
  `intro_picture` varchar(500) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `dark_mode` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_profiles`
--

INSERT INTO `admin_profiles` (`profile_id`, `admin_id`, `profile_photo`, `site_name`, `site_logo`, `intro_video`, `intro_picture`, `updated_at`, `dark_mode`) VALUES
(1, 1, 'admin_1_1761843890.jpg', 'Daet Public Market Clearance', NULL, NULL, NULL, '2025-12-14 20:42:44', 0);

-- --------------------------------------------------------

--
-- Table structure for table `application_documents`
--

CREATE TABLE `application_documents` (
  `document_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `stall_number` int(11) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `application_documents`
--

INSERT INTO `application_documents` (`document_id`, `application_id`, `vendor_id`, `stall_number`, `file_name`, `original_name`, `file_path`, `file_type`, `file_size`, `mime_type`, `upload_date`, `is_active`) VALUES
(44, 1, 1, 1, '1_1765736038_0_0.jpg', 'dasdas.jpg', 'uploads/applications/1_1765736038_0_0.jpg', 'jpg', 232394, 'image/jpeg', '2025-12-14 18:13:58', 1),
(45, 1, 1, 1, '1_1765736038_0_1.jpg', 'palengke.jpg', 'uploads/applications/1_1765736038_0_1.jpg', 'jpg', 562010, 'image/jpeg', '2025-12-14 18:13:58', 1),
(46, 6, 6, 1, '6_1765779287_0_0.jpg', 'webcam-toy-photo43.jpg', 'uploads/applications/6_1765779287_0_0.jpg', 'jpg', 62991, 'image/jpeg', '2025-12-15 06:14:47', 1),
(47, 8, 8, 1, '8_1765783071_0_3008.jpg', 'dasdas.jpg', 'uploads/applications/8_1765783071_0_3008.jpg', 'jpg', 232394, 'image/jpeg', '2025-12-15 07:17:51', 0),
(48, 8, 8, 1, '8_1765783071_4_5827.jpg', 'dasdas.jpg', 'uploads/applications/8_1765783071_4_5827.jpg', 'jpg', 232394, 'image/jpeg', '2025-12-15 07:17:51', 0),
(49, 8, 8, 1, '8_1765783090_2_3014.jpg', 'receipt_12_1765727776_693ede2073c0e.jpg', 'uploads/applications/8_1765783090_2_3014.jpg', 'jpg', 232394, 'image/jpeg', '2025-12-15 07:18:10', 0),
(50, 8, 8, 1, '8_1765783090_4_7778.jpg', 'receipt_12_1765727776_693ede2073c0e.jpg', 'uploads/applications/8_1765783090_4_7778.jpg', 'jpg', 232394, 'image/jpeg', '2025-12-15 07:18:10', 0),
(51, 8, 8, 1, '8_1765783317_0_2389.jpg', 'dasdas.jpg', 'uploads/applications/8_1765783317_0_2389.jpg', 'jpg', 232394, 'image/jpeg', '2025-12-15 07:21:57', 1),
(52, 10, 10, 1, '10_1765807385_0_3325.jpg', 'receipt_12_1765724763 (5).jpg', 'uploads/applications/10_1765807385_0_3325.jpg', 'jpg', 232394, 'image/jpeg', '2025-12-15 14:03:05', 1),
(53, 10, 10, 1, '10_1765807417_0_3965.jpg', '421.jpg', 'uploads/applications/10_1765807417_0_3965.jpg', 'jpg', 165099, 'image/jpeg', '2025-12-15 14:03:37', 1),
(54, 12, 12, 1, '12_1765808103_0_0.jpg', 'dasdsadsadsasa.jpg', 'uploads/applications/12_1765808103_0_0.jpg', 'jpg', 186453, 'image/jpeg', '2025-12-15 14:15:03', 1),
(55, 12, 12, 1, '12_1765808154_0_8025.jpg', 'dasdas.jpg', 'uploads/applications/12_1765808154_0_8025.jpg', 'jpg', 232394, 'image/jpeg', '2025-12-15 14:15:54', 1),
(56, 11, 11, 1, '11_1765808176_0_7614.jpg', 'receipt_12_1765724763 (1).jpg', 'uploads/applications/11_1765808176_0_7614.jpg', 'jpg', 232394, 'image/jpeg', '2025-12-15 14:16:16', 1),
(57, 12, 12, 1, '12_1765808365_0_8545.jpg', 'receipt_12_1765724675 (2).jpg', 'uploads/applications/12_1765808365_0_8545.jpg', 'jpg', 232394, 'image/jpeg', '2025-12-15 14:19:25', 1),
(58, 15, 15, 1, '15_1765885389_0_2.jpg', 'receipt_12_1765724763 (6).jpg', 'uploads/applications/15_1765885389_0_2.jpg', 'jpg', 232394, 'image/jpeg', '2025-12-16 11:43:09', 1),
(59, 8, 8, 1, '8_1765887215_0_8722.jpg', 'receipt_12_1765724763 (6).jpg', 'uploads/applications/8_1765887215_0_8722.jpg', 'jpg', 232394, 'image/jpeg', '2025-12-16 12:13:35', 1);

-- --------------------------------------------------------

--
-- Table structure for table `application_requirements`
--

CREATE TABLE `application_requirements` (
  `requirement_id` int(11) NOT NULL,
  `application_type` enum('new','renewal') NOT NULL,
  `requirement_name` varchar(500) NOT NULL,
  `requirement_description` text DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `application_requirements`
--

INSERT INTO `application_requirements` (`requirement_id`, `application_type`, `requirement_name`, `requirement_description`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 'new', '1', 'dsa', 0, 1, '2025-12-14 17:36:47', '2025-12-15 06:37:06'),
(4, 'new', '2', '12', 1, 1, '2025-12-14 17:36:47', '2025-12-15 06:52:54'),
(5, 'renewal', 'dsa', 'dsa', 0, 1, '2025-12-14 17:36:52', '2025-12-14 17:36:52'),
(6, 'renewal', '312', '123', 2, 1, '2025-12-14 17:36:52', '2025-12-15 14:31:06'),
(7, 'new', '3', 'dasdsa', 2, 1, '2025-12-15 04:30:08', '2025-12-15 06:52:54'),
(8, 'renewal', '1231', '131111111', 1, 1, '2025-12-15 04:50:02', '2025-12-15 14:31:06'),
(9, 'new', '4', '', 3, 1, '2025-12-15 06:52:54', '2025-12-15 06:52:54'),
(10, 'renewal', '11', '11', 3, 1, '2025-12-15 14:31:06', '2025-12-15 14:31:06');

-- --------------------------------------------------------

--
-- Table structure for table `approval_actions`
--

CREATE TABLE `approval_actions` (
  `action_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action_type` enum('approve','reject','pending_review','request_changes') NOT NULL,
  `admin_notes` text DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `status_before` enum('pending','approved','rejected') NOT NULL,
  `status_after` enum('pending','approved','rejected') NOT NULL,
  `action_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `approval_actions`
--

INSERT INTO `approval_actions` (`action_id`, `vendor_id`, `admin_id`, `action_type`, `admin_notes`, `rejection_reason`, `status_before`, `status_after`, `action_date`, `ip_address`, `user_agent`) VALUES
(81, 1, 1, '', 'incomplete po', 'Incomplete Documentation', 'pending', '', '2025-12-14 18:15:08', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),
(82, 2, 1, '', '1', 'Incomplete Documentation', 'pending', '', '2025-12-14 18:40:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),
(83, 4, 1, '', '', 'Incomplete Documentation', 'pending', '', '2025-12-15 04:51:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(84, 6, 1, '', '', 'Incorrect Information', 'pending', '', '2025-12-15 06:53:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(85, 7, 1, '', '', 'Missing Required Documents', 'pending', '', '2025-12-15 06:54:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(86, 5, 1, '', '', 'Invalid Business License', 'pending', '', '2025-12-15 06:54:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(87, 8, 1, '', '', 'Incorrect Information', 'pending', '', '2025-12-15 07:21:01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(88, 6, 1, 'approve', '', NULL, 'pending', 'approved', '2025-12-15 07:28:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(89, 3, 1, '', '', 'Invalid Business License', 'pending', '', '2025-12-15 07:34:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(90, 2, 1, 'approve', '', NULL, 'pending', 'approved', '2025-12-15 07:34:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(91, 8, 1, '', '', 'Incomplete Documentation', 'pending', '', '2025-12-15 07:40:53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(92, 1, 1, '', 'Incomplete Reason: Incomplete Documentation', 'Incomplete Documentation', 'pending', '', '2025-12-15 07:46:28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(93, 9, 1, 'approve', '', NULL, 'pending', 'approved', '2025-12-15 12:17:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Table structure for table `approval_notifications`
--

CREATE TABLE `approval_notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `notification_type` enum('approval','rejection','incomplete','pending_review','profile_required','deadline_reminder','resubmission') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `action_required` tinyint(1) DEFAULT 0,
  `action_url` varchar(500) DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `approval_notifications`
--

INSERT INTO `approval_notifications` (`notification_id`, `user_id`, `vendor_id`, `notification_type`, `title`, `message`, `is_read`, `priority`, `action_required`, `action_url`, `expires_at`, `created_at`, `read_at`) VALUES
(73, 15, 1, 'incomplete', 'Application Incomplete', 'Your application for daniel llyod m. prado has been marked as incomplete.\n\nReason: Incomplete Documentation\n\nNotes: incomplete po\n\nYou can resubmit your application after addressing the issues.', 1, 'high', 0, 'application.php', NULL, '2025-12-14 18:15:08', '2025-12-14 18:29:54'),
(82, 15, 2, 'incomplete', 'Application Incomplete', 'Your application for daniel llyod m. prado has been marked as incomplete.\n\nReason: Incomplete Documentation\n\nNotes: 1\n\nYou can resubmit your application after addressing the issues.', 1, 'high', 0, 'application.php', NULL, '2025-12-14 18:40:59', '2025-12-14 19:04:44'),
(87, 15, NULL, '', 'Receipt Approved', 'Your receipt submitted on Dec 15, 2025 has been approved.', 0, '', 0, NULL, NULL, '2025-12-14 19:15:12', NULL),
(88, 15, 4, 'incomplete', 'Application Incomplete', 'Your application for daniel llyod m. prado has been marked as incomplete.\n\nReason: Incomplete Documentation\n\nYou can resubmit your application after addressing the issues.', 1, 'high', 0, 'application.php', NULL, '2025-12-15 04:51:04', '2025-12-15 04:51:21'),
(89, 17, 6, 'incomplete', 'Application Incomplete', 'Your application for althea prado has been marked as incomplete.\n\nReason: Incorrect Information\n\nYou can resubmit your application after addressing the issues.', 1, 'high', 0, 'application.php', NULL, '2025-12-15 06:53:58', '2025-12-15 06:54:43'),
(90, 17, 7, 'incomplete', 'Application Incomplete', 'Your application for althea prado has been marked as incomplete.\n\nReason: Missing Required Documents\n\nYou can resubmit your application after addressing the issues.', 1, 'high', 0, 'application.php', NULL, '2025-12-15 06:54:02', '2025-12-15 06:54:43'),
(91, 15, 5, 'incomplete', 'Application Incomplete', 'Your application for daniel llyod m. prado has been marked as incomplete.\n\nReason: Invalid Business License\n\nYou can resubmit your application after addressing the issues.', 1, 'high', 0, 'application.php', NULL, '2025-12-15 06:54:04', '2025-12-15 11:56:15'),
(92, 17, 6, 'pending_review', 'New Application Updated', 'Your application for althea prado has been updated and is now pending review. Please wait for admin approval.', 0, 'medium', 0, 'application.php', NULL, '2025-12-15 06:55:06', NULL),
(93, 17, 6, 'pending_review', 'New Application Updated', 'Your application for althea prado has been updated and is now pending review. Please wait for admin approval.', 0, 'medium', 0, 'application.php', NULL, '2025-12-15 07:00:26', NULL),
(94, 17, 6, 'pending_review', 'New Application Updated', 'Your application for althea prado has been updated and is now pending review. Please wait for admin approval.', 0, 'medium', 0, 'application.php', NULL, '2025-12-15 07:06:17', NULL),
(95, 18, 8, 'pending_review', 'New Application Updated', 'Your application for dsa has been updated and is now pending review. Please wait for admin approval.', 0, 'medium', 0, 'application.php', NULL, '2025-12-15 07:17:51', NULL),
(96, 18, 8, 'pending_review', 'New Application Updated', 'Your application for dsa has been updated and is now pending review. Please wait for admin approval.', 0, 'medium', 0, 'application.php', NULL, '2025-12-15 07:18:10', NULL),
(97, 18, 8, 'incomplete', 'Application Incomplete', 'Your application for dsa has been marked as incomplete.\n\nReason: Incorrect Information\n\nYou can resubmit your application after addressing the issues.', 1, 'high', 0, 'application.php', NULL, '2025-12-15 07:21:01', '2025-12-15 07:21:17'),
(98, 18, 8, 'pending_review', 'New Application Updated', 'Your application for dsa has been updated and is now pending review. Please wait for admin approval.', 0, 'medium', 0, 'application.php', NULL, '2025-12-15 07:21:57', NULL),
(99, 17, 6, 'approval', 'Application Approved!', 'Your application for althea prado has been approved.\n\nYou can now proceed with your market clearance activities.', 1, 'high', 0, 'menu.php', NULL, '2025-12-15 07:28:57', '2025-12-15 08:02:43'),
(100, 15, 3, 'incomplete', 'Application Incomplete', 'Your application for daniel llyod m. prado has been marked as incomplete.\n\nReason: Invalid Business License\n\nYou can resubmit your application after addressing the issues.', 1, 'high', 0, 'application.php', NULL, '2025-12-15 07:34:19', '2025-12-15 11:56:15'),
(101, 15, 2, 'approval', 'Application Approved!', 'Your application for daniel llyod m. prado has been approved.\n\nYou can now proceed with your market clearance activities.', 1, 'high', 0, 'menu.php', NULL, '2025-12-15 07:34:33', '2025-12-15 11:56:15'),
(102, 18, 8, 'incomplete', 'Application Incomplete', 'Your application for dsa has been marked as incomplete.\n\nReason: Incomplete Documentation\n\nYou can resubmit your application after addressing the issues.', 1, 'high', 0, 'application.php', NULL, '2025-12-15 07:40:53', '2025-12-15 11:57:14'),
(103, 15, 1, 'incomplete', 'Application Incomplete', 'Your application for daniel llyod m. prado has been marked as incomplete.\n\nReason: Incomplete Documentation\n\nYou can resubmit your application after addressing the issues.', 1, 'high', 0, 'application.php', NULL, '2025-12-15 07:46:28', '2025-12-15 11:56:15'),
(104, 17, NULL, 'approval', 'Receipt Approved', 'Your receipt has been approved by the admin.\n\nAdmin Notes: please process to.....', 1, 'medium', 0, 'notification.php', NULL, '2025-12-15 08:03:28', '2025-12-15 08:05:41'),
(106, 17, 9, 'approval', 'Application Approved!', 'Your application for althea prado has been approved.\n\nYou can now proceed with your market clearance activities.', 1, 'high', 0, 'menu.php', NULL, '2025-12-15 12:17:06', '2025-12-15 12:48:08'),
(107, 22, 10, 'pending_review', 'New Application Updated', 'Your application for milo hershey has been updated and is now pending review. Please wait for admin approval.', 0, 'medium', 0, 'application.php', NULL, '2025-12-15 14:03:05', NULL),
(108, 22, 10, 'pending_review', 'New Application Updated', 'Your application for milo hershey has been updated and is now pending review. Please wait for admin approval.', 0, 'medium', 0, 'application.php', NULL, '2025-12-15 14:03:37', NULL),
(109, 23, 12, 'pending_review', 'New Application Updated', 'Your application for jefferson anoba has been updated and is now pending review. Please wait for admin approval.', 0, 'medium', 0, 'application.php', NULL, '2025-12-15 14:15:54', NULL),
(110, 23, 12, 'pending_review', 'New Application Updated', 'Your application for jefferson anoba has been updated and is now pending review. Please wait for admin approval.', 0, 'medium', 0, 'application.php', NULL, '2025-12-15 14:16:03', NULL),
(111, 23, 11, 'pending_review', 'New Application Updated', 'Your application for jefferson anoba has been updated and is now pending review. Please wait for admin approval.', 0, 'medium', 0, 'application.php', NULL, '2025-12-15 14:16:16', NULL),
(112, 23, 12, 'pending_review', 'New Application Updated', 'Your application for jefferson anoba has been updated and is now pending review. Please wait for admin approval.', 0, 'medium', 0, 'application.php', NULL, '2025-12-15 14:19:25', NULL),
(113, 18, 15, 'pending_review', 'Renewal Submitted', 'Your renewal application for dsa has been submitted and is now pending review. Please wait for admin approval. Section: Dry Goods Section, Block: BOT Block E', 0, 'medium', 0, 'renewal.php', NULL, '2025-12-16 11:43:09', NULL),
(114, 18, 8, 'pending_review', 'New Application Updated', 'Your application for dsa has been updated and is now pending review. Please wait for the approval.', 0, 'medium', 0, 'application.php', NULL, '2025-12-16 12:13:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `approval_settings`
--

CREATE TABLE `approval_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `approval_settings`
--

INSERT INTO `approval_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `is_active`, `updated_at`, `updated_by`) VALUES
(1, 'auto_approve_threshold', '90', 'number', 'Compliance score threshold for auto-approval', 1, '2025-10-29 03:42:08', NULL),
(2, 'max_resubmissions', '3', 'number', 'Maximum number of resubmissions allowed', 1, '2025-10-29 03:42:08', NULL),
(3, 'review_deadline_days', '7', 'number', 'Default review deadline in days', 1, '2025-10-29 03:42:08', NULL),
(4, 'notification_retention_days', '30', 'number', 'Days to retain notifications', 1, '2025-10-29 03:42:08', NULL),
(5, 'enable_auto_approval', 'false', 'boolean', 'Enable automatic approval for high-scoring applications', 1, '2025-10-29 03:42:08', NULL),
(6, 'enable_email_notifications', 'true', 'boolean', 'Enable email notifications', 1, '2025-10-29 03:42:08', NULL),
(7, 'approval_workflow_enabled', 'true', 'boolean', 'Enable multi-step approval workflow', 1, '2025-10-29 03:42:08', NULL),
(8, 'audit_log_retention_days', '365', 'number', 'Days to retain audit logs', 1, '2025-10-29 03:42:08', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `approval_workflow`
--

CREATE TABLE `approval_workflow` (
  `workflow_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `current_step` int(11) DEFAULT 1,
  `total_steps` int(11) DEFAULT 3,
  `step_name` varchar(100) NOT NULL,
  `step_status` enum('pending','in_progress','completed','rejected') DEFAULT 'pending',
  `assigned_to` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `log_id` int(11) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` int(11) NOT NULL,
  `action_type` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `email_type` varchar(50) NOT NULL DEFAULT 'password_reset',
  `subject` varchar(255) NOT NULL,
  `status` enum('pending','sent','failed','error') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `error_details` text DEFAULT NULL,
  `smtp_host` varchar(255) DEFAULT NULL,
  `smtp_username` varchar(255) DEFAULT NULL,
  `attempts` int(11) DEFAULT 1,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `email_logs`
--

INSERT INTO `email_logs` (`id`, `recipient_email`, `recipient_name`, `email_type`, `subject`, `status`, `error_message`, `error_details`, `smtp_host`, `smtp_username`, `attempts`, `sent_at`, `created_at`, `updated_at`) VALUES
(1, 'danielmantesprado@gmail.com', 'daniel llyod m. prado', 'password_reset', 'Password Reset Code - Daet Public Market', 'error', 'Gmail credentials not properly configured in email_config.php. Issues: smtp_password is still set to placeholder \'your-app-password\'', '[\"smtp_password is still set to placeholder \'your-app-password\'\"]', 'smtp.gmail.com', 'danielmantesprado@gmail.com', 1, NULL, '2025-12-06 14:09:41', '2025-12-06 14:09:41'),
(2, 'danielmantesprado@gmail.com', 'daniel llyod m. prado', 'password_reset', 'Password Reset Code - Daet Public Market', 'failed', 'PHPMailer Exception: SMTP Error: Could not authenticate.; PHP mail() function returned false (often blocked by Gmail)', '[\"PHPMailer Exception: SMTP Error: Could not authenticate.\",\"PHP mail() function returned false (often blocked by Gmail)\"]', 'smtp.gmail.com', 'danielmantesprado@gmail.com', 1, NULL, '2025-12-06 14:23:07', '2025-12-06 14:23:11'),
(3, 'danielmantesprado@gmail.com', 'daniel llyod m. prado', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'danielmantesprado@gmail.com', 1, '2025-12-06 22:42:15', '2025-12-06 14:42:11', '2025-12-06 14:42:15'),
(4, 'danielmantesprado@gmail.com', 'daniel llyod m. prado', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'danielmantesprado@gmail.com', 1, '2025-12-06 22:44:39', '2025-12-06 14:44:35', '2025-12-06 14:44:39'),
(5, 'danielmantesprado@gmail.com', 'daniel llyod m. prado', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'danielmantesprado@gmail.com', 1, '2025-12-06 22:55:31', '2025-12-06 14:55:28', '2025-12-06 14:55:31'),
(6, 'danielmantesprado@gmail.com', 'daniel llyod m. prado', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'danielmantesprado@gmail.com', 1, '2025-12-06 22:59:07', '2025-12-06 14:59:01', '2025-12-06 14:59:07'),
(7, 'danielmantesprado@gmail.com', 'daniel llyod m. prado', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'danielmantesprado@gmail.com', 1, '2025-12-06 22:59:59', '2025-12-06 14:59:55', '2025-12-06 14:59:59'),
(8, 'danielmantesprado@gmail.com', 'daniel llyod m. prado', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'danielmantesprado@gmail.com', 1, '2025-12-06 23:00:10', '2025-12-06 15:00:07', '2025-12-06 15:00:10'),
(9, 'danielmantesprado@gmail.com', 'daniel llyod m. prado', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'danielmantesprado@gmail.com', 1, '2025-12-06 23:09:45', '2025-12-06 15:09:41', '2025-12-06 15:09:45'),
(10, 'danielmantesprado@gmail.com', 'daniel llyod m. prado', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'danielmantesprado@gmail.com', 1, '2025-12-06 23:15:38', '2025-12-06 15:15:32', '2025-12-06 15:15:38'),
(11, 'danielmantesprado@gmail.com', 'daniel llyod m. prado', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'danielmantesprado@gmail.com', 1, '2025-12-06 23:18:26', '2025-12-06 15:18:22', '2025-12-06 15:18:26'),
(12, 'danielmantesprado@gmail.com', 'daniel llyod m. prado', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'danielmantesprado@gmail.com', 1, '2025-12-06 23:24:24', '2025-12-06 15:24:21', '2025-12-06 15:24:24'),
(13, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:07:03', '2025-12-06 16:07:00', '2025-12-06 16:07:03'),
(14, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:18:51', '2025-12-06 16:18:37', '2025-12-06 16:18:51'),
(15, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:19:06', '2025-12-06 16:18:52', '2025-12-06 16:19:06'),
(16, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:19:18', '2025-12-06 16:19:06', '2025-12-06 16:19:18'),
(17, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:19:30', '2025-12-06 16:19:18', '2025-12-06 16:19:30'),
(18, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:19:42', '2025-12-06 16:19:30', '2025-12-06 16:19:42'),
(19, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:19:55', '2025-12-06 16:19:42', '2025-12-06 16:19:55'),
(20, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:20:08', '2025-12-06 16:19:55', '2025-12-06 16:20:08'),
(21, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:20:20', '2025-12-06 16:20:08', '2025-12-06 16:20:20'),
(22, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:20:33', '2025-12-06 16:20:20', '2025-12-06 16:20:33'),
(23, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:20:45', '2025-12-06 16:20:33', '2025-12-06 16:20:45'),
(24, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:20:58', '2025-12-06 16:20:45', '2025-12-06 16:20:58'),
(25, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:21:11', '2025-12-06 16:20:58', '2025-12-06 16:21:11'),
(26, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:21:23', '2025-12-06 16:21:11', '2025-12-06 16:21:23'),
(27, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:21:36', '2025-12-06 16:21:23', '2025-12-06 16:21:36'),
(28, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:21:48', '2025-12-06 16:21:36', '2025-12-06 16:21:48'),
(29, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:22:04', '2025-12-06 16:21:48', '2025-12-06 16:22:04'),
(30, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:22:16', '2025-12-06 16:22:04', '2025-12-06 16:22:16'),
(31, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:22:28', '2025-12-06 16:22:16', '2025-12-06 16:22:28'),
(32, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:22:41', '2025-12-06 16:22:28', '2025-12-06 16:22:41'),
(33, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:25:20', '2025-12-06 16:24:48', '2025-12-06 16:25:20'),
(34, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:25:36', '2025-12-06 16:25:20', '2025-12-06 16:25:36'),
(35, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:25:56', '2025-12-06 16:25:36', '2025-12-06 16:25:56'),
(36, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:26:09', '2025-12-06 16:25:57', '2025-12-06 16:26:09'),
(37, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:26:59', '2025-12-06 16:26:28', '2025-12-06 16:26:59'),
(38, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:27:31', '2025-12-06 16:26:59', '2025-12-06 16:27:31'),
(39, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:29:47', '2025-12-06 16:29:43', '2025-12-06 16:29:47'),
(40, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:29:50', '2025-12-06 16:29:47', '2025-12-06 16:29:50'),
(41, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:29:54', '2025-12-06 16:29:50', '2025-12-06 16:29:54'),
(42, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:29:57', '2025-12-06 16:29:54', '2025-12-06 16:29:57'),
(43, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:30:00', '2025-12-06 16:29:57', '2025-12-06 16:30:00'),
(44, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:38:33', '2025-12-06 16:38:29', '2025-12-06 16:38:33'),
(45, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:39:00', '2025-12-06 16:38:57', '2025-12-06 16:39:00'),
(46, 'danielmantesprado@gmail.com', 'daniel llyod m. prado', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:40:22', '2025-12-06 16:40:18', '2025-12-06 16:40:22'),
(47, 'danielmantesprado@gmail.com', 'daniel llyod m. prado', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:49:30', '2025-12-06 16:49:26', '2025-12-06 16:49:30'),
(48, 'danielmantesprado@gmail.com', 'daniel llyod m. prado', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:52:14', '2025-12-06 16:52:11', '2025-12-06 16:52:14'),
(49, 'danielmantesprado@gmail.com', 'daniel llyod m. prado', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:52:27', '2025-12-06 16:52:24', '2025-12-06 16:52:27'),
(50, 'fannyuser443@gmail.com', 'fanny user', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 00:56:03', '2025-12-06 16:56:00', '2025-12-06 16:56:03'),
(51, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 18:02:20', '2025-12-07 10:02:14', '2025-12-07 10:02:20'),
(52, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 18:02:39', '2025-12-07 10:02:36', '2025-12-07 10:02:39'),
(53, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 18:05:15', '2025-12-07 10:05:13', '2025-12-07 10:05:15'),
(54, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 18:08:25', '2025-12-07 10:08:22', '2025-12-07 10:08:25'),
(55, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 18:08:34', '2025-12-07 10:08:30', '2025-12-07 10:08:34'),
(56, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 18:12:21', '2025-12-07 10:12:18', '2025-12-07 10:12:21'),
(57, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 18:12:26', '2025-12-07 10:12:22', '2025-12-07 10:12:26'),
(58, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 18:12:41', '2025-12-07 10:12:37', '2025-12-07 10:12:41'),
(59, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 18:12:46', '2025-12-07 10:12:43', '2025-12-07 10:12:46'),
(60, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 18:12:50', '2025-12-07 10:12:47', '2025-12-07 10:12:50'),
(61, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 18:15:59', '2025-12-07 10:15:56', '2025-12-07 10:15:59'),
(62, 'daetpublicmarket@gmail.com', 'Admin', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 18:16:09', '2025-12-07 10:16:06', '2025-12-07 10:16:09'),
(63, 'danielmantesprado@gmail.com', 'daniel llyod m. prado', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 18:22:49', '2025-12-07 10:22:46', '2025-12-07 10:22:49'),
(64, 'fannyuser443@gmail.com', 'fanny user', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-07 19:06:48', '2025-12-07 11:06:44', '2025-12-07 11:06:48'),
(65, 'luckiedoguiles850@gmail.com', 'Luckie Angelo', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-09 23:05:27', '2025-12-09 15:05:23', '2025-12-09 15:05:27'),
(66, 'danielmantesprado@gmail.com', 'daniel llyod m. prado', 'password_reset', 'Password Reset Code - Daet Public Market', 'sent', NULL, NULL, 'smtp.gmail.com', 'daetpublicmarket@gmail.com', 1, '2025-12-13 23:56:26', '2025-12-13 15:56:20', '2025-12-13 15:56:26');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `attempt_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `failure_reason` varchar(100) DEFAULT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`attempt_id`, `email`, `ip_address`, `success`, `failure_reason`, `attempted_at`) VALUES
(1, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-10-29 02:56:35'),
(2, 'admin@gmail.com', 'unknown', 0, 'invalid_password', '2025-10-29 02:58:48'),
(3, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 03:00:51'),
(4, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 03:01:11'),
(5, 'danielmantesprado@gmail.com', '::1', 0, 'user_not_found', '2025-10-29 03:02:07'),
(6, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 03:02:44'),
(7, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-10-29 03:07:51'),
(8, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 03:07:55'),
(9, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 04:04:04'),
(10, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 04:28:56'),
(11, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 04:30:22'),
(12, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 04:30:38'),
(13, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 04:47:19'),
(14, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 04:48:19'),
(15, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 11:32:17'),
(16, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 11:41:26'),
(17, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 11:46:28'),
(18, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-10-29 12:01:27'),
(19, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 12:01:33'),
(20, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 12:07:19'),
(21, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 12:10:36'),
(22, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 12:11:53'),
(23, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 12:17:59'),
(24, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 12:23:14'),
(25, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 12:23:44'),
(26, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-10-29 12:52:39'),
(27, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 12:56:02'),
(28, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 13:12:07'),
(29, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 13:25:42'),
(30, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 13:49:56'),
(31, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 13:51:04'),
(32, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 13:55:43'),
(33, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 13:56:40'),
(34, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 13:57:58'),
(35, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-10-29 13:58:21'),
(36, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 13:58:23'),
(37, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 15:29:01'),
(38, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 15:29:23'),
(39, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 15:29:52'),
(40, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 15:31:15'),
(41, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 15:32:00'),
(42, 'danielprado17@gmail.com', '::1', 0, 'user_not_found', '2025-10-29 15:57:44'),
(43, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 15:57:50'),
(44, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 16:00:46'),
(45, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 16:21:13'),
(46, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 16:22:48'),
(47, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 16:29:50'),
(48, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 16:32:10'),
(49, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 16:36:05'),
(50, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 16:38:21'),
(51, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 16:42:40'),
(52, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 16:43:03'),
(53, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 16:44:33'),
(54, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 16:44:51'),
(55, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 16:55:16'),
(56, 'admin@gmail.com', '::1', 1, NULL, '2025-10-29 16:55:36'),
(57, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-29 17:07:59'),
(58, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-30 14:07:28'),
(59, 'admin@gmail.com', '::1', 1, NULL, '2025-10-30 14:08:01'),
(60, 'althea@gmail.com', '::1', 1, NULL, '2025-10-30 15:40:11'),
(61, 'admin@gmail.com', '::1', 1, NULL, '2025-10-30 15:40:25'),
(62, 'admin@gmail.com', '::1', 1, NULL, '2025-10-30 16:33:33'),
(63, 'aujrcpradodaniellllyod12humss7p@gmail.com', '::1', 1, NULL, '2025-10-30 16:37:56'),
(64, 'aujrcpradodaniellllyod12humss7p@gmail.com', '::1', 0, 'invalid_password', '2025-10-30 16:43:05'),
(65, 'aujrcpradodaniellllyod12humss7p@gmail.com', '::1', 0, 'invalid_password', '2025-10-30 16:43:09'),
(66, 'aujrcpradodaniellllyod12humss7p@gmail.com', '::1', 1, NULL, '2025-10-30 16:43:16'),
(67, 'aujrcpradodaniellllyod12humss7p@gmail.com', '::1', 1, NULL, '2025-10-30 16:51:17'),
(68, 'admin@gmail.com', '::1', 1, NULL, '2025-10-30 16:56:19'),
(69, 'aujrcpradodaniellllyod12humss7p@gmail.com', '::1', 1, NULL, '2025-10-30 17:06:58'),
(70, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-10-30 17:14:44'),
(71, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-11-01 06:18:49'),
(72, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-11-01 11:44:58'),
(73, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-11-01 11:45:17'),
(74, 'admin@gmail.com', '::1', 1, NULL, '2025-11-01 11:45:22'),
(75, 'admin@gmail.com', '::1', 1, NULL, '2025-11-01 11:46:25'),
(76, 'admin@gmail.com', '::1', 1, NULL, '2025-11-01 11:47:29'),
(77, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-11-01 11:56:00'),
(78, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-11-01 11:56:02'),
(79, 'admin@gmail.com', '::1', 1, NULL, '2025-11-01 11:56:05'),
(80, 'altheamantes12@gmail.com', '::1', 1, NULL, '2025-11-01 11:57:52'),
(81, 'danielmantes17@gmail.com', '::1', 1, NULL, '2025-11-01 12:09:23'),
(82, 'admin@gmail.com', '::1', 1, NULL, '2025-11-01 12:10:17'),
(83, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-11-01 12:10:29'),
(84, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-11-01 12:10:35'),
(85, 'admin@gmail.com', '::1', 1, NULL, '2025-11-01 12:10:38'),
(86, 'admin@gmail.com', '::1', 1, NULL, '2025-11-01 12:10:57'),
(87, 'admin@gmail.com', '::1', 1, NULL, '2025-11-01 12:11:06'),
(88, 'danielmantes17@gmail.com', '::1', 1, NULL, '2025-11-01 12:17:11'),
(89, 'admin@gmail.com', '::1', 1, NULL, '2025-11-01 12:49:17'),
(90, 'danielmantes17@gmail.com', '::1', 1, NULL, '2025-11-01 12:50:45'),
(91, 'admin@gmail.com', '::1', 1, NULL, '2025-11-01 12:51:18'),
(92, 'danielmantes17@gmail.com', '::1', 1, NULL, '2025-11-01 12:51:49'),
(93, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-11-01 12:52:18'),
(94, 'admin@gmail.com', '::1', 1, NULL, '2025-11-01 12:52:21'),
(95, 'danielmantes17@gmail.com', '::1', 1, NULL, '2025-11-01 12:52:40'),
(96, 'admin@gmail.com', '::1', 1, NULL, '2025-11-01 12:53:03'),
(97, 'admin@gmail.com', '::1', 1, NULL, '2025-11-01 12:53:17'),
(98, 'danielprado17@gmail.com', '::1', 0, 'user_not_found', '2025-11-01 12:53:43'),
(99, 'danielprado17@gmail.com', '::1', 0, 'user_not_found', '2025-11-01 12:53:46'),
(100, 'danielprado17@gmail.com', '::1', 0, 'user_not_found', '2025-11-01 12:53:48'),
(101, 'danielprado17@gmail.com', '::1', 0, 'user_not_found', '2025-11-01 12:53:51'),
(102, 'danielmantes17@gmail.com', '::1', 1, NULL, '2025-11-01 12:54:02'),
(103, 'admin@gmail.com', '::1', 1, NULL, '2025-11-01 12:54:21'),
(104, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-11-01 12:55:14'),
(105, 'admin@gmail.com', '::1', 1, NULL, '2025-11-01 12:55:40'),
(106, 'danielmantes17@gmail.com', '::1', 1, NULL, '2025-11-01 12:58:55'),
(107, 'danielmantesprado@gmail.com', '::1', 0, 'invalid_password', '2025-11-01 13:02:47'),
(108, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-11-01 13:02:50'),
(109, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-11-01 13:03:34'),
(110, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-11-01 13:03:36'),
(111, 'admin@gmail.com', '::1', 1, NULL, '2025-11-01 13:03:38'),
(112, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-11-01 13:04:06'),
(113, 'danielmantes17@gmail.com', '::1', 1, NULL, '2025-11-01 13:04:53'),
(114, 'admin@gmail.com', '::1', 1, NULL, '2025-11-01 13:05:07'),
(115, 'danielmantes17@gmail.com', '::1', 1, NULL, '2025-11-01 13:05:23'),
(116, 'danielmantes17@gmail.com', '::1', 1, NULL, '2025-11-03 06:15:08'),
(117, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-11-03 06:16:03'),
(118, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-11-03 06:16:07'),
(119, 'admin@gmail.com', '::1', 1, NULL, '2025-11-03 06:16:09'),
(120, 'danielmantes17@gmail.com', '::1', 1, NULL, '2025-11-03 06:16:34'),
(121, 'admin@gmail.com', '::1', 1, NULL, '2025-11-03 06:51:31'),
(122, 'admin@gmail.com', '::1', 1, NULL, '2025-11-03 11:02:03'),
(123, 'admin@gmail.com', '::1', 1, NULL, '2025-11-03 11:22:55'),
(124, 'admin@gmail.com', '::1', 1, NULL, '2025-11-03 11:36:47'),
(125, 'admin@gmail.com', '::1', 1, NULL, '2025-11-06 14:49:52'),
(126, 'admin@gmail.com', '::1', 1, NULL, '2025-11-06 15:00:53'),
(127, 'admin@gmail.com', '::1', 1, NULL, '2025-11-19 14:01:17'),
(128, 'danielmantes17@gmail.com', '::1', 0, 'invalid_password', '2025-11-19 14:14:35'),
(129, 'danielmantes17@gmail.com', '::1', 0, 'invalid_password', '2025-11-19 14:14:42'),
(130, 'danielmantes17@gmail.com', '::1', 0, 'invalid_password', '2025-11-19 14:14:45'),
(131, 'danielmantes17@gmail.com', '::1', 0, 'invalid_password', '2025-11-19 14:14:50'),
(132, 'danielmantes17@gmail.com', '::1', 0, 'invalid_password', '2025-11-19 14:14:54'),
(133, 'danielmantesprado@gmail.com', '::1', 0, 'invalid_password', '2025-11-19 14:17:16'),
(134, 'althea123@gmail.com', '::1', 1, NULL, '2025-11-19 14:18:39'),
(135, 'althea123@gmail.com', '::1', 1, NULL, '2025-11-19 14:25:13'),
(136, 'admin@gmail.com', '::1', 1, NULL, '2025-11-19 14:28:52'),
(137, 'admin@gmail.com', '::1', 1, NULL, '2025-11-19 14:29:01'),
(138, 'althea123@gmail.com', '::1', 1, NULL, '2025-11-19 14:30:44'),
(139, 'althea123@gmail.com', '::1', 1, NULL, '2025-11-19 14:39:07'),
(140, 'admin@gmail.com', '::1', 1, NULL, '2025-11-19 14:45:50'),
(141, 'admin@gmail.com', '::1', 1, NULL, '2025-11-19 14:49:00'),
(142, 'althea123@gmail.com', '::1', 1, NULL, '2025-11-19 14:49:22'),
(143, 'althea123@gmail.com', '::1', 1, NULL, '2025-11-20 02:04:48'),
(144, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-11-20 02:24:10'),
(145, 'admin@gmail.com', '::1', 1, NULL, '2025-11-20 02:24:14'),
(146, 'althea123@gmail.com', '::1', 1, NULL, '2025-11-20 03:01:32'),
(147, 'althea123@gmail.com', '::1', 1, NULL, '2025-11-20 03:37:47'),
(148, 'althea123@gmail.com', '::1', 1, NULL, '2025-11-20 04:00:18'),
(149, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-01 06:00:49'),
(150, 'althea123@gmail.com', '::1', 0, 'invalid_password', '2025-12-01 06:07:48'),
(151, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-01 06:08:00'),
(152, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-01 06:26:34'),
(153, 'danielmantesprado@gmail.com', '::1', 0, 'invalid_password', '2025-12-01 06:43:36'),
(154, 'danielprado17@gmail.com', '::1', 0, 'user_not_found', '2025-12-01 06:43:43'),
(155, 'althea@gmail.com', '::1', 0, 'invalid_password', '2025-12-01 06:43:50'),
(156, 'altheamantes12@gmail.com', '::1', 0, 'invalid_password', '2025-12-01 06:43:57'),
(157, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-01 06:44:06'),
(158, 'admin@gmail.com', '::1', 1, NULL, '2025-12-01 06:47:15'),
(159, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-01 06:47:40'),
(160, 'admin@gmail.com', '::1', 1, NULL, '2025-12-01 08:03:44'),
(161, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-01 08:11:01'),
(162, 'admin@gmail.com', '::1', 1, NULL, '2025-12-01 08:22:03'),
(163, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-01 08:22:23'),
(164, 'admin@gmail.com', '::1', 1, NULL, '2025-12-01 08:28:17'),
(165, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-01 08:28:33'),
(166, 'althea123@gmail.com', '::1', 0, 'invalid_password', '2025-12-01 08:42:49'),
(167, 'althea123@gmail.com', '::1', 0, 'invalid_password', '2025-12-01 08:42:52'),
(168, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-01 08:42:55'),
(169, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-01 09:13:19'),
(170, 'admin@gmail.com', '::1', 1, NULL, '2025-12-01 09:13:41'),
(171, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-01 09:14:00'),
(172, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-02 13:17:19'),
(173, 'admin@gmail.com', '::1', 1, NULL, '2025-12-02 13:17:48'),
(174, 'althea123@gmail.com', '::1', 0, 'invalid_password', '2025-12-02 13:18:04'),
(175, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-02 13:18:07'),
(176, 'admin@gmail.com', '::1', 1, NULL, '2025-12-02 13:18:41'),
(177, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-02 13:18:54'),
(178, 'admin@gmail.com', '::1', 1, NULL, '2025-12-02 14:02:27'),
(179, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-02 14:02:45'),
(180, 'danielmantesprado@gmail.com', '::1', 0, 'invalid_password', '2025-12-02 14:37:32'),
(181, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-02 14:37:38'),
(182, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-12-02 16:31:40'),
(183, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-12-02 16:31:42'),
(184, 'admin@gmail.com', '::1', 1, NULL, '2025-12-02 16:31:45'),
(185, 'admin@gmail.com', '::1', 1, NULL, '2025-12-02 17:07:43'),
(186, 'althea123@gmail.com', '::1', 0, 'user_not_found', '2025-12-02 17:07:59'),
(187, 'admin@gmail.com', '::1', 1, NULL, '2025-12-02 17:08:21'),
(188, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-02 17:11:39'),
(189, 'admin@gmail.com', '::1', 1, NULL, '2025-12-02 17:13:31'),
(190, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-02 17:14:12'),
(191, 'admin@gmail.com', '::1', 1, NULL, '2025-12-02 17:14:35'),
(192, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-02 17:15:20'),
(193, 'admin@gmail.com', '::1', 1, NULL, '2025-12-02 17:15:53'),
(194, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-02 17:33:44'),
(195, 'admin@gmail.com', '::1', 1, NULL, '2025-12-02 17:33:57'),
(196, 'admin@gmail.com', '::1', 1, NULL, '2025-12-02 17:34:18'),
(197, 'danielmantesprado@gmail.com', '::1', 0, 'invalid_password', '2025-12-02 17:34:36'),
(198, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-02 17:34:41'),
(199, 'admin@gmail.com', '::1', 1, NULL, '2025-12-02 17:35:11'),
(200, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-02 17:36:02'),
(201, 'admin@gmail.com', '::1', 1, NULL, '2025-12-02 17:39:20'),
(202, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-02 17:51:59'),
(203, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-12-02 17:52:26'),
(204, 'admin@gmail.com', '::1', 1, NULL, '2025-12-02 17:52:29'),
(205, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-02 17:52:56'),
(206, 'admin@gmail.com', '::1', 1, NULL, '2025-12-02 17:53:17'),
(207, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-02 18:14:05'),
(208, 'admin@gmail.com', '::1', 1, NULL, '2025-12-02 18:14:24'),
(209, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-02 18:15:25'),
(210, 'admin@gmail.com', '::1', 1, NULL, '2025-12-02 18:22:05'),
(211, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-02 18:24:08'),
(212, 'admin@gmail.com', '::1', 1, NULL, '2025-12-02 18:24:58'),
(213, 'admin@gmail.com', '::1', 1, NULL, '2025-12-03 00:59:48'),
(214, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-03 01:30:29'),
(215, 'admin@gmail.com', '::1', 1, NULL, '2025-12-03 01:31:00'),
(216, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-03 01:31:17'),
(217, 'admin@gmail.com', '::1', 1, NULL, '2025-12-03 01:31:39'),
(218, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-03 01:58:13'),
(219, 'admin@gmail.com', '::1', 1, NULL, '2025-12-03 01:59:31'),
(220, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-03 02:16:45'),
(221, 'admin@gmail.com', '::1', 1, NULL, '2025-12-03 02:17:02'),
(222, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-03 02:40:47'),
(223, 'admin@gmail.com', '::1', 1, NULL, '2025-12-03 02:45:44'),
(224, 'danielmantesprado@gmail.com', '::1', 0, 'invalid_password', '2025-12-03 02:58:10'),
(225, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-03 02:58:13'),
(226, 'admin@gmail.com', '::1', 1, NULL, '2025-12-03 03:01:44'),
(227, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-03 03:04:44'),
(228, 'admin@gmail.com', '::1', 1, NULL, '2025-12-03 03:08:31'),
(229, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-03 03:08:44'),
(230, 'admin@gmail.com', '::1', 1, NULL, '2025-12-03 03:11:38'),
(231, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-03 03:15:50'),
(232, 'admin@gmail.com', '::1', 1, NULL, '2025-12-03 03:16:37'),
(233, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-03 03:17:23'),
(234, 'admin@gmail.com', '::1', 1, NULL, '2025-12-03 03:50:08'),
(235, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-03 04:23:40'),
(236, 'admin@gmail.com', '::1', 1, NULL, '2025-12-03 06:01:11'),
(237, 'admin@gmail.com', '::1', 1, NULL, '2025-12-04 17:06:09'),
(238, 'admin@gmail.com', '::1', 1, NULL, '2025-12-04 17:06:20'),
(239, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-04 17:19:09'),
(240, 'admin@gmail.com', '::1', 1, NULL, '2025-12-04 17:22:12'),
(241, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-04 17:26:26'),
(242, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-12-04 17:26:53'),
(243, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-12-04 17:26:57'),
(244, 'admin@gmail.com', '::1', 1, NULL, '2025-12-04 17:27:00'),
(245, 'admin@gmail.com', '::1', 1, NULL, '2025-12-05 03:42:35'),
(246, 'admin@gmail.com', '::1', 1, NULL, '2025-12-05 13:25:25'),
(247, 'danielmantesprado@gmail.com', '::1', 0, 'user_not_found', '2025-12-06 13:36:07'),
(248, 'danielmantesprado@gmail.com', '::1', 0, 'user_not_found', '2025-12-06 13:36:11'),
(249, 'danielmantesprado@gmail.com', '::1', 0, 'user_not_found', '2025-12-06 13:36:15'),
(250, 'danielmantesprado@gmail.com', '::1', 0, 'user_not_found', '2025-12-06 13:36:20'),
(251, 'danielmantesprado@gmail.com', '::1', 0, 'user_not_found', '2025-12-06 13:36:27'),
(252, 'admin@gmail.com', '::1', 1, NULL, '2025-12-06 13:36:44'),
(253, 'admin@gmail.com', '::1', 1, NULL, '2025-12-06 14:09:30'),
(254, 'admin@gmail.com', '::1', 1, NULL, '2025-12-06 15:10:51'),
(255, 'admin@gmail.com', '::1', 0, 'invalid_password', '2025-12-06 15:20:22'),
(256, 'admin@gmail.com', '::1', 1, NULL, '2025-12-06 15:20:24'),
(257, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-06 15:24:14'),
(258, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-06 15:24:59'),
(259, 'daetpublicmarket@gmail.com', '::1', 0, 'user_not_found', '2025-12-06 15:58:44'),
(260, 'daetpublicmarket@gmail.com', '::1', 0, 'user_not_found', '2025-12-06 15:58:49'),
(261, 'admin@gmail.com', '::1', 1, NULL, '2025-12-06 15:59:02'),
(262, 'daetpublicmarket@gmail.com', '::1', 0, 'user_not_found', '2025-12-06 16:02:56'),
(263, 'daetpublicmarket@gmail.com', '::1', 0, 'user_not_found', '2025-12-06 16:02:59'),
(264, 'daetpublicmarket@gmail.com', '::1', 0, 'user_not_found', '2025-12-06 16:03:01'),
(265, 'daetpublicmarket@gmail.com', '::1', 0, 'user_not_found', '2025-12-06 16:03:04'),
(266, 'danielmantesprado@gmail.com', '::1', 0, 'invalid_password', '2025-12-06 16:03:52'),
(267, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-06 16:03:59'),
(268, 'daetpublicmarket@gmail.com', '::1', 0, 'invalid_password', '2025-12-06 16:05:34'),
(269, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-06 16:05:37'),
(270, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-06 16:05:59'),
(271, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-06 16:06:21'),
(272, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-06 16:06:39'),
(273, 'daetpublicmarket@gmail.com', '::1', 0, 'invalid_password', '2025-12-06 16:07:40'),
(274, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-06 16:07:44'),
(275, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-06 16:08:21'),
(276, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-06 16:11:17'),
(277, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-06 16:54:35'),
(278, 'fannyuser443@gmail.com', '::1', 1, NULL, '2025-12-06 16:56:56'),
(279, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-07 10:17:37'),
(280, 'danielmantesprado@gmail.com', '::1', 0, 'invalid_password', '2025-12-07 10:22:23'),
(281, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-07 10:23:29'),
(282, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-07 10:29:01'),
(283, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-07 10:33:10'),
(284, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-07 10:57:16'),
(285, 'fannyuser443@gmail.com', '::1', 0, 'invalid_password', '2025-12-07 11:06:38'),
(286, 'fannyuser443@gmail.com', '::1', 1, NULL, '2025-12-07 11:07:48'),
(287, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-07 11:08:55'),
(288, 'fannyuser443@gmail.com', '::1', 1, NULL, '2025-12-07 11:09:10'),
(289, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-07 11:09:46'),
(290, 'fannyuser443@gmail.com', '::1', 1, NULL, '2025-12-07 11:10:02'),
(291, 'danielmantesprado@gmail.com', '::1', 0, 'invalid_password', '2025-12-09 14:52:47'),
(292, 'danielmantesprado@gmail.com', '::1', 0, 'invalid_password', '2025-12-09 14:52:50'),
(293, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-09 14:52:53'),
(294, 'luckiedoguiles850@gmail.com', '::1', 1, NULL, '2025-12-09 14:57:50'),
(295, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-09 15:08:55'),
(296, 'luckiedoguiles850@gmail.com', '::1', 1, NULL, '2025-12-09 15:14:56'),
(297, 'admin@gmail.com', '::1', 0, 'user_not_found', '2025-12-09 15:21:17'),
(298, 'admin@gmail.com', '::1', 0, 'user_not_found', '2025-12-09 15:21:33'),
(299, 'admin@gmail.com', '::1', 0, 'user_not_found', '2025-12-09 15:22:09'),
(300, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-09 15:23:42'),
(301, 'danielmantesprado@gmail.com', '::1', 0, 'invalid_password', '2025-12-13 15:55:46'),
(302, 'danielmantesprado@gmail.com', '::1', 0, 'invalid_password', '2025-12-13 15:55:51'),
(303, 'danielmantesprado@gmail.com', '::1', 0, 'invalid_password', '2025-12-13 15:55:56'),
(304, 'danielmantesprado@gmail.com', '::1', 0, 'invalid_password', '2025-12-13 15:55:59'),
(305, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-13 15:57:27'),
(306, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-13 16:07:09'),
(307, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-13 16:11:15'),
(308, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-14 12:58:40'),
(309, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-14 13:14:51'),
(310, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-14 13:33:03'),
(311, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-14 14:07:53'),
(312, 'danielmantesprado@gmail.com', '::1', 0, 'invalid_password', '2025-12-14 14:29:30'),
(313, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-14 14:29:33'),
(314, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-14 14:30:46'),
(315, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-14 14:31:02'),
(316, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-14 14:31:54'),
(317, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-14 14:57:22'),
(318, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-14 15:05:10'),
(319, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-14 15:05:31'),
(320, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-14 15:17:47'),
(321, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-14 15:26:00'),
(322, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-14 15:26:30'),
(323, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-14 15:38:58'),
(324, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-14 15:39:20'),
(325, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-14 15:46:09'),
(326, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-14 15:46:27'),
(327, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-14 15:56:05'),
(328, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-14 15:56:25'),
(329, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-14 17:34:56'),
(330, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-14 17:35:48'),
(331, 'danielmantesprado@gmail.com', '::1', 0, 'invalid_password', '2025-12-14 17:37:17'),
(332, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-14 17:37:21'),
(333, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-14 18:05:01'),
(334, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-14 18:06:04'),
(335, 'danielmantesprado@gmail.com', '::1', 0, 'user_not_found', '2025-12-14 18:07:46'),
(336, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-14 18:13:14'),
(337, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-14 18:14:13'),
(338, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-14 18:29:52'),
(339, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-14 18:30:30'),
(340, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-14 19:03:36'),
(341, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-14 19:13:36'),
(342, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-14 19:14:56'),
(343, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-14 20:10:06'),
(344, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-14 20:28:05'),
(345, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-14 20:41:26'),
(346, 'daetpublicmarket@gmail.com', '::1', 0, 'invalid_password', '2025-12-14 20:42:23'),
(347, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-14 20:42:28'),
(348, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-14 20:55:15'),
(349, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-14 22:29:08'),
(350, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-15 04:04:28'),
(351, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 04:04:56'),
(352, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-15 04:20:03'),
(353, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 04:23:42'),
(354, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-15 04:30:17'),
(355, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 04:49:46'),
(356, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-15 04:50:25'),
(357, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 04:50:56'),
(358, 'danielmantesprado@gmail.com', '::1', 0, 'invalid_password', '2025-12-15 04:51:16'),
(359, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-15 04:51:19'),
(360, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 05:20:53'),
(361, 'john@gmail.com', '::1', 1, NULL, '2025-12-15 05:51:53'),
(362, 'jhon@gmail.com', '::1', 1, NULL, '2025-12-15 05:59:23'),
(363, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 06:13:12'),
(364, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-15 06:14:24'),
(365, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 06:24:39'),
(366, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-15 06:35:10'),
(367, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 06:36:15'),
(368, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-15 06:37:48'),
(369, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 06:38:17'),
(370, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-15 06:54:40'),
(371, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 06:55:25'),
(372, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-15 07:00:00'),
(373, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 07:00:44'),
(374, 'althea123@gmail.com', '::1', 0, 'invalid_password', '2025-12-15 07:05:48'),
(375, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-15 07:05:51'),
(376, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 07:06:28'),
(377, 'jhon@gmail.com', '::1', 1, NULL, '2025-12-15 07:17:30'),
(378, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 07:18:24'),
(379, 'jhon@gmail.com', '::1', 1, NULL, '2025-12-15 07:21:15'),
(380, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 07:22:10'),
(381, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-15 08:02:40'),
(382, 'daetpublicmarket@gmail.com', '::1', 0, 'invalid_password', '2025-12-15 08:03:07'),
(383, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 08:03:10'),
(384, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-15 08:05:37'),
(385, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 08:16:44'),
(386, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 11:55:21'),
(387, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-15 11:55:46'),
(388, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-15 11:56:53'),
(389, 'jhon@gmail.com', '::1', 1, NULL, '2025-12-15 11:57:12'),
(390, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 11:59:09'),
(391, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 11:59:37'),
(392, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-15 12:03:36'),
(393, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 12:03:57'),
(394, 'althea123@gmail.com', '::1', 0, 'invalid_password', '2025-12-15 12:48:00'),
(395, 'althea123@gmail.com', '::1', 1, NULL, '2025-12-15 12:48:03'),
(396, 'jhon@gmail.com', '::1', 1, NULL, '2025-12-15 12:49:26'),
(397, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 12:53:53'),
(398, 'jhon@gmail.com', '::1', 1, NULL, '2025-12-15 12:54:07'),
(399, 'john@gmail.com', '::1', 1, NULL, '2025-12-15 13:50:16'),
(400, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 13:50:27'),
(401, 'jhon@gmail.com', '::1', 0, 'invalid_password', '2025-12-15 13:51:49'),
(402, 'jhon@gmail.com', '::1', 1, NULL, '2025-12-15 13:51:52'),
(403, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 13:53:19'),
(404, 'milo@gmail.com', '::1', 1, NULL, '2025-12-15 14:02:11'),
(405, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 14:04:07'),
(406, 'jefferson@gmail.com', '::1', 1, NULL, '2025-12-15 14:10:00'),
(407, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 14:10:16'),
(408, 'jefferson@gmail.com', '::1', 1, NULL, '2025-12-15 14:14:24'),
(409, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 14:26:17'),
(410, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-15 14:26:45'),
(411, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 14:30:38'),
(412, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-15 14:31:46'),
(413, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 14:34:09'),
(414, 'jhon@gmail.com', '::1', 1, NULL, '2025-12-15 14:35:07'),
(415, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-15 14:40:43'),
(416, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-15 14:41:15'),
(417, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-16 09:15:01'),
(418, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-16 11:21:51'),
(419, 'danielmantesprado@gmail.com', '::1', 1, NULL, '2025-12-16 11:22:37'),
(420, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-16 11:36:30'),
(421, 'jhon@gmail.com', '::1', 1, NULL, '2025-12-16 11:43:00'),
(422, 'daetpublicmarket@gmail.com', '::1', 1, NULL, '2025-12-16 11:43:32'),
(423, 'jhon@gmail.com', '::1', 0, 'invalid_password', '2025-12-16 11:43:52'),
(424, 'jhon@gmail.com', '::1', 1, NULL, '2025-12-16 11:43:55');

-- --------------------------------------------------------

--
-- Table structure for table `login_sessions`
--

CREATE TABLE `login_sessions` (
  `session_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `market_slots`
--

CREATE TABLE `market_slots` (
  `id` int(11) NOT NULL,
  `block_name` varchar(50) NOT NULL,
  `slot_number` int(11) NOT NULL,
  `status` enum('available','occupied','second_floor') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `market_slots`
--

INSERT INTO `market_slots` (`id`, `block_name`, `slot_number`, `status`, `created_at`, `updated_at`) VALUES
(1, 'BOT BLOCK A', 2, 'occupied', '2025-12-14 13:22:30', '2025-12-14 14:14:06'),
(10, 'BOT BLOCK B', 1, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(11, 'BOT BLOCK B', 2, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(12, 'BOT BLOCK B', 3, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(13, 'BOT BLOCK B', 4, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(14, 'BOT BLOCK B', 5, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(15, 'BOT BLOCK B', 6, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(16, 'BOT BLOCK B', 7, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(17, 'BOT BLOCK B', 8, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(18, 'BOT BLOCK B', 9, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(19, 'BOT BLOCK B', 10, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(20, 'BOT BLOCK B', 11, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(21, 'BOT BLOCK B', 12, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(22, 'BOT BLOCK B', 13, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(23, 'BOT BLOCK B', 14, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(24, 'BOT BLOCK B', 15, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(25, 'BOT BLOCK B', 16, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(26, 'BOT BLOCK B', 17, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(27, 'BOT BLOCK B', 18, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(28, 'BOT BLOCK B', 19, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(29, 'BOT BLOCK B', 20, 'occupied', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(30, 'BOT BLOCK B', 21, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(31, 'BOT BLOCK B', 22, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(32, 'BOT BLOCK B', 23, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(33, 'BOT BLOCK B', 24, 'occupied', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(34, 'BOT BLOCK B', 25, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(35, 'BOT BLOCK B', 26, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(36, 'BOT BLOCK B', 27, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(37, 'BOT BLOCK B', 28, 'occupied', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(38, 'BOT BLOCK B', 29, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(39, 'BOT BLOCK B', 30, 'occupied', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(40, 'BOT BLOCK B', 31, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(41, 'BOT BLOCK B', 32, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(42, 'BOT BLOCK B', 33, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(43, 'BOT BLOCK B', 34, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(44, 'BOT BLOCK B', 35, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(45, 'BOT BLOCK B', 36, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(46, 'BOT BLOCK B', 37, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(47, 'BOT BLOCK B', 38, 'occupied', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(48, 'BOT BLOCK B', 39, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(49, 'BOT BLOCK B', 40, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(50, 'BOT BLOCK A', 1, 'second_floor', '2025-12-14 13:33:03', '2025-12-14 14:14:01'),
(52, 'BOT BLOCK A', 3, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(53, 'BOT BLOCK A', 4, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(54, 'BOT BLOCK A', 5, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(55, 'BOT BLOCK A', 6, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(56, 'BOT BLOCK A', 7, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(57, 'BOT BLOCK A', 8, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(58, 'BOT BLOCK A', 9, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(59, 'BOT BLOCK A', 10, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(60, 'BOT BLOCK A', 11, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(61, 'BOT BLOCK A', 12, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(62, 'BOT BLOCK A', 13, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(63, 'BOT BLOCK A', 14, 'occupied', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(64, 'BOT BLOCK A', 15, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(65, 'BOT BLOCK A', 16, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(66, 'BOT BLOCK A', 17, 'occupied', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(67, 'BOT BLOCK A', 18, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(68, 'BOT BLOCK A', 19, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(69, 'BOT BLOCK A', 20, 'occupied', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(70, 'BOT BLOCK A', 21, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(71, 'BOT BLOCK A', 22, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(72, 'BOT BLOCK A', 24, 'occupied', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(73, 'BOT BLOCK A', 25, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(74, 'BOT BLOCK A', 26, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(75, 'BOT BLOCK A', 27, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(76, 'BOT BLOCK A', 28, 'occupied', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(77, 'BOT BLOCK A', 37, 'occupied', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(78, 'BOT BLOCK A', 38, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(79, 'BOT BLOCK A', 40, 'occupied', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(80, 'BOT BLOCK A', 41, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(81, 'BOT BLOCK A', 42, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(82, 'BOT BLOCK A', 43, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(83, 'BOT BLOCK A', 44, 'occupied', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(84, 'BOT BLOCK A', 45, 'occupied', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(85, 'BOT BLOCK A', 46, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(86, 'BOT BLOCK A', 47, 'occupied', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(87, 'BOT BLOCK A', 61, 'occupied', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(88, 'BOT BLOCK A', 62, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(89, 'BOT BLOCK A', 63, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(90, 'BOT BLOCK A', 64, 'available', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(91, 'BOT BLOCK A', 65, 'occupied', '2025-12-14 13:33:03', '2025-12-14 13:33:03'),
(92, 'BOT BLOCK A', 66, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(93, 'BOT BLOCK A', 67, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(94, 'BOT BLOCK A', 68, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(95, 'BOT BLOCK A', 69, 'occupied', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(96, 'BOT BLOCK A', 70, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(97, 'BOT BLOCK A', 71, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(98, 'BOT BLOCK A', 72, 'occupied', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(99, 'BOT BEHIND BLOCK C', 1, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(100, 'BOT BEHIND BLOCK C', 2, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(101, 'BOT BEHIND BLOCK C', 3, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(102, 'BOT BEHIND BLOCK C', 4, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(103, 'BOT BEHIND BLOCK C', 5, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(104, 'BOT BEHIND BLOCK C', 6, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(105, 'BOT BEHIND BLOCK C', 7, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(106, 'BOT BEHIND BLOCK C', 8, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(107, 'BOT BEHIND BLOCK C', 9, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(108, 'BOT BEHIND BLOCK C', 10, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(109, 'BOT BEHIND BLOCK C', 11, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(110, 'BOT BEHIND BLOCK C', 12, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(111, 'BOT BEHIND BLOCK C', 13, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(112, 'BOT BEHIND BLOCK C', 14, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(113, 'BOT BEHIND BLOCK C', 15, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(114, 'BOT BEHIND BLOCK C', 16, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(115, 'BOT BEHIND BLOCK C', 17, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(116, 'BOT BEHIND BLOCK C', 18, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(117, 'BOT BEHIND BLOCK C', 19, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(118, 'BOT BEHIND BLOCK C', 20, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(119, 'BOT BEHIND BLOCK C', 21, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(120, 'BOT BEHIND BLOCK C', 23, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(121, 'BOT BEHIND BLOCK C', 24, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(122, 'BOT BEHIND BLOCK C', 25, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(123, 'BOT BEHIND BLOCK C', 35, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(124, 'BOT BEHIND BLOCK C', 40, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(125, 'BOT BEHIND BLOCK C', 41, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(126, 'BOT BEHIND BLOCK C', 42, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(127, 'BOT BEHIND BLOCK C', 43, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(128, 'BOT BEHIND BLOCK C', 44, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(129, 'BOT BEHIND BLOCK C', 45, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(130, 'BOT BEHIND BLOCK C', 46, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(131, 'BOT BEHIND BLOCK C', 47, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(132, 'BOT BEHIND BLOCK C', 50, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(133, 'BOT BEHIND BLOCK C', 51, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(134, 'BOT BEHIND BLOCK C', 52, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(135, 'BOT BEHIND BLOCK C', 53, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(136, 'BOT BEHIND BLOCK C', 54, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(137, 'BOT BEHIND BLOCK C', 55, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(138, 'BOT BEHIND BLOCK C', 56, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(139, 'BOT BEHIND BLOCK C', 57, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(140, 'BOT BEHIND BLOCK C', 58, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(141, 'BOT BEHIND BLOCK C', 59, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(142, 'BOT BEHIND BLOCK C', 60, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(143, 'BOT BEHIND BLOCK C', 70, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(144, 'BOT BEHIND BLOCK C', 71, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(145, 'BOT BEHIND BLOCK C', 72, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(146, 'BOT BEHIND BLOCK C', 73, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(147, 'BOT BEHIND BLOCK C', 74, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(148, 'BOT BEHIND BLOCK C', 75, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(149, 'BOT BEHIND BLOCK C', 76, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(150, 'BOT BEHIND BLOCK C', 77, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(151, 'BOT BEHIND BLOCK C', 78, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(152, 'BOT BEHIND BLOCK C', 79, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(153, 'BOT BEHIND BLOCK C', 80, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(154, 'BOT BEHIND BLOCK C', 81, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(155, 'BOT BEHIND BLOCK C', 82, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(156, 'BOT BEHIND BLOCK C', 83, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(157, 'BOT BEHIND BLOCK C', 84, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(158, 'BOT BEHIND BLOCK C', 85, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(159, 'BOT BEHIND BLOCK C', 86, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(160, 'BOT BEHIND BLOCK C', 87, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(161, 'BOT BEHIND BLOCK C', 88, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(162, 'BOT BEHIND BLOCK C', 89, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(163, 'BOT BEHIND BLOCK C', 90, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(164, 'BOT BEHIND BLOCK C', 91, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(165, 'BOT BEHIND BLOCK C', 92, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(166, 'BOT BEHIND BLOCK C', 93, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(167, 'BOT BEHIND BLOCK C', 94, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(168, 'BOT BEHIND BLOCK C', 95, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(169, 'BOT BEHIND BLOCK C', 96, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(170, 'BOT BEHIND BLOCK C', 97, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(171, 'BOT BEHIND BLOCK C', 98, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(172, 'BOT BEHIND BLOCK C', 99, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(173, 'BOT BEHIND BLOCK C', 100, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(174, 'BOT BEHIND BLOCK C', 101, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(175, 'BOT BEHIND BLOCK C', 102, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(176, 'BOT BEHIND BLOCK C', 103, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(177, 'BOT BEHIND BLOCK C', 104, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(178, 'BOT BEHIND BLOCK C', 105, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(179, 'BOT BEHIND BLOCK C', 106, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(180, 'BOT BEHIND BLOCK C', 107, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(181, 'BOT BEHIND BLOCK C', 108, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(182, 'BOT BEHIND BLOCK C', 109, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(183, 'BOT BEHIND BLOCK C', 110, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(184, 'BOT BEHIND BLOCK C', 111, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(185, 'BOT BEHIND BLOCK C', 112, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(186, 'BOT BEHIND BLOCK C', 113, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(187, 'BOT BEHIND BLOCK C', 114, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(188, 'BOT BEHIND BLOCK C', 115, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(189, 'BOT BEHIND BLOCK C', 116, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(190, 'BOT BEHIND BLOCK C', 117, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(191, 'BOT BEHIND BLOCK C', 118, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(192, 'BOT BEHIND BLOCK C', 119, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(193, 'BOT BEHIND BLOCK C', 120, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(194, 'BOT BEHIND BLOCK C', 121, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(195, 'BOT BEHIND BLOCK C', 122, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(196, 'BOT BEHIND BLOCK C', 123, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(197, 'BOT BEHIND BLOCK C', 124, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(198, 'BOT BEHIND BLOCK C', 125, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(199, 'BOT BEHIND BLOCK C', 126, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(200, 'BOT BEHIND BLOCK C', 127, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(201, 'BOT BEHIND BLOCK C', 128, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(202, 'BOT BEHIND BLOCK C', 129, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(203, 'BOT BEHIND BLOCK C', 130, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(204, 'BOT BEHIND BLOCK C', 131, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(205, 'BOT BEHIND BLOCK C', 132, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(206, 'BOT BEHIND BLOCK C', 133, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(207, 'BOT BEHIND BLOCK C', 134, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(208, 'BOT BEHIND BLOCK C', 135, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(209, 'BOT BEHIND BLOCK C', 136, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(210, 'BOT BEHIND BLOCK C', 137, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(211, 'BOT BLOCK E', 1, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(212, 'BOT BLOCK E', 2, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(213, 'BOT BLOCK E', 3, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(214, 'BOT BLOCK E', 4, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(215, 'BOT BLOCK E', 5, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(216, 'BOT BLOCK E', 6, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(217, 'BOT BLOCK E', 7, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(218, 'BOT BLOCK E', 8, 'occupied', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(219, 'BOT BLOCK E', 9, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(220, 'BOT BLOCK E', 10, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(221, 'BOT BLOCK E', 11, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(222, 'BOT BLOCK E', 12, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(223, 'BOT BLOCK E', 13, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(224, 'BOT BLOCK E', 14, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(225, 'BOT BLOCK E', 15, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(226, 'BOT BLOCK E', 16, 'occupied', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(227, 'BOT BLOCK E', 23, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(228, 'SPECIAL AREA SECTION', 1, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(229, 'SPECIAL AREA SECTION', 2, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(230, 'SPECIAL AREA SECTION', 3, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(231, 'SPECIAL AREA SECTION', 4, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(232, 'SPECIAL AREA SECTION', 5, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(233, 'SPECIAL AREA SECTION', 6, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(234, 'SPECIAL AREA SECTION', 7, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(235, 'SPECIAL AREA SECTION', 8, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(236, 'SPECIAL AREA SECTION', 9, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(237, 'SPECIAL AREA SECTION', 10, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(238, 'SPECIAL AREA SECTION', 11, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(239, 'SPECIAL AREA SECTION', 12, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(240, 'SPECIAL AREA SECTION', 13, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(241, 'SPECIAL AREA SECTION', 14, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(242, 'SPECIAL AREA SECTION', 15, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(243, 'SPECIAL AREA SECTION', 16, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(244, 'SPECIAL AREA SECTION', 17, 'available', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(245, 'SPECIAL AREA SECTION', 23, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(246, 'SPECIAL AREA SECTION', 24, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(247, 'SPECIAL AREA SECTION', 25, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(248, 'SPECIAL AREA SECTION', 26, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(249, 'SPECIAL AREA SECTION', 27, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(250, 'SPECIAL AREA SECTION', 28, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(251, 'SPECIAL AREA SECTION', 30, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(252, 'SPECIAL AREA SECTION', 31, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(253, 'SPECIAL AREA SECTION', 32, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(254, 'SPECIAL AREA SECTION', 33, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(255, 'SPECIAL AREA SECTION', 34, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(256, 'SPECIAL AREA SECTION', 35, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(257, 'SPECIAL AREA SECTION', 36, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(258, 'SPECIAL AREA SECTION', 37, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(259, 'SPECIAL AREA SECTION', 38, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(260, 'SPECIAL AREA SECTION', 39, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(261, 'SPECIAL AREA SECTION', 40, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(262, 'SPECIAL AREA SECTION', 41, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(263, 'SPECIAL AREA SECTION', 42, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(264, 'SPECIAL AREA SECTION', 43, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(265, 'SPECIAL AREA SECTION', 44, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(266, 'SPECIAL AREA SECTION', 45, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(267, 'SPECIAL AREA SECTION', 46, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(268, 'SPECIAL AREA SECTION', 47, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(269, 'SPECIAL AREA SECTION', 48, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(270, 'SPECIAL AREA SECTION', 49, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(271, 'SPECIAL AREA SECTION', 50, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(272, 'SPECIAL AREA SECTION', 51, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(273, 'SPECIAL AREA SECTION', 52, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(274, 'SPECIAL AREA SECTION', 53, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(275, 'SPECIAL AREA SECTION', 54, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(276, 'SPECIAL AREA SECTION', 55, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(277, 'SPECIAL AREA SECTION', 56, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(278, 'SPECIAL AREA SECTION', 57, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(279, 'SPECIAL AREA SECTION', 58, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(280, 'SPECIAL AREA SECTION', 59, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(281, 'SPECIAL AREA SECTION', 60, 'second_floor', '2025-12-14 13:33:04', '2025-12-14 13:33:04'),
(554, 'SPECIAL AREA SECTION TOP', 1, 'occupied', '2025-12-14 13:57:15', '2025-12-14 14:30:53'),
(555, 'SPECIAL AREA SECTION TOP', 2, 'second_floor', '2025-12-14 13:57:15', '2025-12-15 04:19:47'),
(556, 'SPECIAL AREA SECTION TOP', 3, 'occupied', '2025-12-14 13:57:15', '2025-12-15 14:26:26'),
(557, 'SPECIAL AREA SECTION TOP', 4, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(558, 'SPECIAL AREA SECTION TOP', 5, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(559, 'SPECIAL AREA SECTION TOP', 6, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(560, 'SPECIAL AREA SECTION TOP', 7, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(561, 'SPECIAL AREA SECTION TOP', 8, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(562, 'FRUITS VEG', 1, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(563, 'FRUITS VEG', 2, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(564, 'FRUITS VEG', 3, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(565, 'FRUITS VEG', 4, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(566, 'FRUITS VEG', 5, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(567, 'FRUITS VEG', 6, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(568, 'FRUITS VEG', 7, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(569, 'FRUITS VEG', 8, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(570, 'FRUITS VEG', 9, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(571, 'FRUITS VEG', 10, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(572, 'FRUITS VEG', 11, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(573, 'FRUITS VEG', 12, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(574, 'FRUITS VEG', 13, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(575, 'FRUITS VEG', 14, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(576, 'FRUITS VEG', 15, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(577, 'FRUITS VEG', 16, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(578, 'FRUITS VEG', 17, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(579, 'FRUITS VEG', 18, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(580, 'FRUITS VEG', 19, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(581, 'FRUITS VEG', 20, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(582, 'FRUITS VEG', 21, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(583, 'FRUITS VEG', 22, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(584, 'FRUITS VEG', 23, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(585, 'FRUITS VEG', 24, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(586, 'FRUITS VEG', 25, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(587, 'FRUITS VEG', 26, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(588, 'FRUITS VEG', 27, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(589, 'FRUITS VEG', 28, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(612, 'BOT BLOCK A', 23, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(618, 'BOT BLOCK A', 29, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(619, 'BOT BLOCK A', 30, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(620, 'BOT BLOCK A', 31, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(621, 'BOT BLOCK A', 32, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(622, 'BOT BLOCK A', 33, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(623, 'BOT BLOCK A', 34, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(624, 'BOT BLOCK A', 35, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(625, 'BOT BLOCK A', 36, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(628, 'BOT BLOCK A', 39, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(637, 'BOT BLOCK A', 48, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(638, 'BOT BLOCK A', 49, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(639, 'BOT BLOCK A', 50, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(640, 'BOT BLOCK A', 51, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(641, 'BOT BLOCK A', 52, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(642, 'BOT BLOCK A', 53, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(643, 'BOT BLOCK A', 54, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(644, 'BOT BLOCK A', 55, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(645, 'BOT BLOCK A', 56, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(646, 'BOT BLOCK A', 57, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(647, 'BOT BLOCK A', 58, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(648, 'BOT BLOCK A', 59, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(649, 'BOT BLOCK A', 60, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(662, 'BOT BLOCK A', 73, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(663, 'BOT BLOCK A', 74, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(664, 'BOT BLOCK A', 75, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(665, 'BOT BLOCK A', 76, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(666, 'BOT BLOCK A', 77, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(667, 'BOT BLOCK A', 78, 'available', '2025-12-14 13:57:15', '2025-12-14 13:57:15'),
(668, 'BOT BLOCK A', 79, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(669, 'BOT BLOCK A', 80, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(670, 'BOT BLOCK A', 81, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(671, 'BOT BLOCK A', 82, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(672, 'BOT BLOCK A', 83, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(673, 'BOT BLOCK A', 84, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(674, 'BOT BLOCK A', 85, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(675, 'BOT BLOCK A', 86, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(676, 'BOT BLOCK A', 87, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(677, 'BOT BLOCK A', 88, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(718, 'BOT BLOCK B', 41, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(719, 'BOT BLOCK B', 42, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(720, 'BOT BLOCK B', 43, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(721, 'BOT BLOCK B', 44, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(722, 'BOT BLOCK B', 45, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(723, 'BOT BLOCK B', 46, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(724, 'BOT BLOCK B', 47, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(725, 'BOT BLOCK B', 48, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(726, 'BOT BLOCK B', 49, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(727, 'BOT BLOCK B', 50, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(728, 'BOT BLOCK B', 51, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(729, 'BOT BLOCK B', 52, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(730, 'BOT BLOCK B', 53, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(731, 'BOT BLOCK B', 54, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(732, 'BOT BLOCK B', 55, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(733, 'BOT BLOCK B', 56, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(734, 'BOT BLOCK B', 57, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(735, 'BOT BLOCK B', 58, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(736, 'BOT BLOCK B', 59, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(737, 'BOT BLOCK B', 60, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(738, 'BOT BLOCK B', 61, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(739, 'BOT BLOCK B', 62, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(740, 'BOT BLOCK B', 63, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(741, 'BOT BLOCK B', 64, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(742, 'BOT BLOCK B', 65, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(743, 'BOT BLOCK B', 66, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(744, 'BOT BLOCK B', 67, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(745, 'BOT BLOCK B', 68, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(746, 'BOT BLOCK B', 69, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(747, 'BOT BLOCK B', 70, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(748, 'BOT BLOCK B', 71, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(749, 'BOT BLOCK B', 72, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(750, 'BOT BLOCK B', 73, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(751, 'BOT BLOCK B', 74, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(752, 'BOT BLOCK B', 75, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(753, 'BOT BLOCK B', 76, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(754, 'BOT BLOCK B', 77, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(755, 'BOT BLOCK B', 78, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(756, 'BOT BLOCK B', 79, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(757, 'BOT BLOCK B', 80, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(758, 'BOT BLOCK B', 81, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(759, 'BOT BLOCK B', 82, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(760, 'BOT BLOCK B', 83, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(761, 'BOT BLOCK B', 84, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(762, 'BOT BLOCK B', 85, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(763, 'BOT BLOCK B', 86, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(764, 'BOT BLOCK B', 87, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(765, 'BOT BLOCK B', 88, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(766, 'BOT BLOCK C', 1, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(767, 'BOT BLOCK C', 2, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(768, 'BOT BLOCK C', 3, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(769, 'BOT BLOCK C', 4, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(770, 'BOT BLOCK C', 5, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(771, 'BOT BLOCK C', 6, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(772, 'BOT BLOCK C', 7, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(773, 'BOT BLOCK C', 8, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(774, 'BOT BLOCK C', 9, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(775, 'BOT BLOCK C', 10, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(776, 'BOT BLOCK C', 11, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(777, 'BOT BLOCK C', 12, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(778, 'BOT BLOCK C', 13, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(779, 'BOT BLOCK C', 14, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(780, 'BOT BLOCK C', 15, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(781, 'BOT BLOCK C', 16, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(782, 'BOT BLOCK C', 17, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(783, 'BOT BLOCK C', 18, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(784, 'BOT BLOCK C', 19, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(785, 'BOT BLOCK C', 20, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(786, 'BOT BLOCK C', 21, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(787, 'BOT BLOCK C', 22, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(788, 'BOT BLOCK C', 23, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(789, 'BOT BLOCK C', 24, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(790, 'BOT BLOCK C', 25, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(791, 'BOT BLOCK C', 26, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(792, 'BOT BLOCK C', 27, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(793, 'BOT BLOCK C', 28, 'available', '2025-12-14 13:57:16', '2025-12-14 13:57:16'),
(802, 'BOT BEHIND BLOCK B', 1, 'available', '2025-12-14 13:57:17', '2025-12-14 13:57:17'),
(803, 'BOT BEHIND BLOCK B', 2, 'available', '2025-12-14 13:57:17', '2025-12-14 13:57:17'),
(804, 'BOT BEHIND BLOCK B', 3, 'available', '2025-12-14 13:57:17', '2025-12-14 13:57:17'),
(805, 'BOT BEHIND BLOCK B', 4, 'available', '2025-12-14 13:57:17', '2025-12-14 13:57:17'),
(806, 'BOT BEHIND BLOCK B', 5, 'available', '2025-12-14 13:57:17', '2025-12-14 13:57:17'),
(807, 'BOT BEHIND BLOCK B', 6, 'available', '2025-12-14 13:57:17', '2025-12-14 13:57:17'),
(808, 'BOT BEHIND BLOCK B', 7, 'available', '2025-12-14 13:57:18', '2025-12-14 13:57:18'),
(809, 'BOT BEHIND BLOCK B', 8, 'available', '2025-12-14 13:57:18', '2025-12-14 13:57:18'),
(810, 'BOT BEHIND BLOCK B', 9, 'available', '2025-12-14 13:57:18', '2025-12-14 13:57:18'),
(811, 'BOT BEHIND BLOCK B', 10, 'available', '2025-12-14 13:57:18', '2025-12-14 13:57:18'),
(812, 'BOT BEHIND BLOCK B', 11, 'available', '2025-12-14 13:57:18', '2025-12-14 13:57:18'),
(813, 'BOT BEHIND BLOCK B', 12, 'available', '2025-12-14 13:57:18', '2025-12-14 13:57:18'),
(814, 'BOT BEHIND BLOCK B', 13, 'available', '2025-12-14 13:57:18', '2025-12-14 13:57:18'),
(815, 'BOT BEHIND BLOCK B', 14, 'available', '2025-12-14 13:57:18', '2025-12-14 13:57:18'),
(832, 'BOT BLOCK E', 17, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(833, 'BOT BLOCK E', 18, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(834, 'BOT BLOCK E', 19, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(835, 'BOT BLOCK E', 20, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(836, 'BOT BLOCK E', 21, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(837, 'BOT BLOCK E', 22, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(839, 'DRY GOODS SECTION', 1, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(840, 'DRY GOODS SECTION', 2, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(841, 'DRY GOODS SECTION', 3, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(842, 'DRY GOODS SECTION', 4, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(843, 'DRY GOODS SECTION', 5, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(844, 'DRY GOODS SECTION', 6, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(845, 'DRY GOODS SECTION', 7, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(846, 'DRY GOODS SECTION', 8, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(847, 'DRY GOODS SECTION', 9, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(848, 'DRY GOODS SECTION', 10, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(849, 'DRY GOODS SECTION', 11, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(850, 'DRY GOODS SECTION', 12, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(851, 'DRY GOODS SECTION', 13, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(852, 'DRY GOODS SECTION', 14, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(853, 'DRY GOODS SECTION', 15, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(854, 'DRY GOODS SECTION', 16, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(855, 'DRY GOODS SECTION', 17, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(856, 'DRY GOODS SECTION', 18, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(857, 'DRY GOODS SECTION', 19, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(858, 'DRY GOODS SECTION', 20, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(859, 'DRY GOODS SECTION', 21, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(860, 'DRY GOODS SECTION', 22, 'occupied', '2025-12-14 13:57:19', '2025-12-14 14:29:07'),
(861, 'DRY GOODS SECTION', 23, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(862, 'DRY GOODS SECTION', 24, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(863, 'DRY GOODS SECTION', 25, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(864, 'DRY GOODS SECTION', 26, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(865, 'DRY GOODS SECTION', 27, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(866, 'DRY GOODS SECTION', 28, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(867, 'SERVICE SECTION', 1, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(868, 'SERVICE SECTION', 2, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(869, 'SERVICE SECTION', 3, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(870, 'SERVICE SECTION', 4, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(871, 'SERVICE SECTION', 5, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(872, 'SERVICE SECTION', 6, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(873, 'SERVICE SECTION', 7, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(874, 'SERVICE SECTION', 8, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(875, 'SERVICE SECTION', 9, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(876, 'SERVICE SECTION', 10, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(877, 'SERVICE SECTION', 11, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(878, 'SERVICE SECTION', 12, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(879, 'SERVICE SECTION', 13, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(880, 'SERVICE SECTION', 14, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(881, 'SERVICE SECTION', 15, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(882, 'SERVICE SECTION', 16, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(883, 'SERVICE SECTION', 17, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(884, 'SERVICE SECTION', 18, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(885, 'SERVICE SECTION', 19, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(886, 'SERVICE SECTION', 20, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(887, 'SERVICE SECTION', 21, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(888, 'SERVICE SECTION', 22, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(889, 'SERVICE SECTION', 23, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(890, 'SERVICE SECTION', 24, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(891, 'SERVICE SECTION', 25, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(892, 'SERVICE SECTION', 26, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(893, 'SERVICE SECTION', 27, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(894, 'SERVICE SECTION', 28, 'available', '2025-12-14 13:57:19', '2025-12-14 13:57:19'),
(912, 'SPECIAL AREA SECTION', 18, 'available', '2025-12-14 13:57:20', '2025-12-14 13:57:20'),
(913, 'SPECIAL AREA SECTION', 19, 'available', '2025-12-14 13:57:20', '2025-12-14 13:57:20'),
(914, 'SPECIAL AREA SECTION', 20, 'available', '2025-12-14 13:57:20', '2025-12-14 13:57:20'),
(915, 'SPECIAL AREA SECTION', 21, 'available', '2025-12-14 13:57:20', '2025-12-14 13:57:20'),
(916, 'SPECIAL AREA SECTION', 22, 'available', '2025-12-14 13:57:20', '2025-12-14 13:57:20'),
(923, 'SPECIAL AREA SECTION', 29, 'available', '2025-12-14 13:57:20', '2025-12-14 13:57:20');

-- --------------------------------------------------------

--
-- Table structure for table `media_files`
--

CREATE TABLE `media_files` (
  `media_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` enum('video','image') NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `section` varchar(100) DEFAULT 'homepage'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `media_files`
--

INSERT INTO `media_files` (`media_id`, `file_name`, `file_path`, `file_type`, `file_size`, `mime_type`, `title`, `description`, `is_active`, `display_order`, `uploaded_by`, `uploaded_at`, `updated_at`, `section`) VALUES
(1, 'market_ad.mp4', 'assets/videos/market_ad.mp4', 'video', 0, 'video/mp4', 'Market Overview Video', 'Welcome video showcasing the Daet Public Market', 1, 1, 1, '2025-10-29 02:56:16', '2025-10-29 02:56:16', 'homepage'),
(2, 'market.jpg', 'assets/imgs/market.jpg', 'image', 0, 'image/jpeg', 'Market Price Update', 'Latest market prices and information', 1, 1, 1, '2025-10-29 02:56:16', '2025-10-29 02:56:16', 'homepage'),
(3, 'video_1761707308.mp4', 'assets/videos/video_1761707308.mp4', 'video', 3005877, 'video/mp4', 'Market Overview Video', 'Welcome video showcasing the Daet Public Market', 1, 0, 1, '2025-10-29 03:08:28', '2025-10-29 03:08:28', 'overview_video'),
(4, 'video_1761707318.mp4', 'assets/videos/video_1761707318.mp4', 'video', 10792977, 'video/mp4', 'Market Price Video', 'Market price updates and information video', 1, 0, 1, '2025-10-29 03:08:38', '2025-10-29 03:08:38', 'price_video'),
(5, 'video_1761712064.mp4', 'assets/videos/video_1761712064.mp4', 'video', 1364608, 'video/mp4', 'Market Overview Video', 'Welcome video showcasing the Daet Public Market', 1, 0, 1, '2025-10-29 04:27:44', '2025-10-29 04:27:44', 'overview_video'),
(6, 'video_1761712074.mp4', 'assets/videos/video_1761712074.mp4', 'video', 1364608, 'video/mp4', 'Market Price Video', 'Market price updates and information video', 1, 0, 1, '2025-10-29 04:27:54', '2025-10-29 04:27:54', 'price_video'),
(7, 'video_1761712569.mp4', 'assets/videos/video_1761712569.mp4', 'video', 3005877, 'video/mp4', 'Market Overview Video', 'Welcome video showcasing the Daet Public Market', 1, 0, 1, '2025-10-29 04:36:09', '2025-10-29 04:36:09', 'overview_video'),
(8, 'video_1761712580.mp4', 'assets/videos/video_1761712580.mp4', 'video', 10792977, 'video/mp4', 'Market Price Video', 'Market price updates and information video', 1, 0, 1, '2025-10-29 04:36:20', '2025-10-29 04:36:20', 'price_video'),
(9, 'video_1763605529.mp4', 'assets/videos/video_1763605529.mp4', 'video', 10356312, 'video/mp4', 'Market Price Video', 'Market price updates and information video', 1, 0, 1, '2025-11-20 02:25:29', '2025-11-20 02:25:29', 'price_video'),
(10, 'video_1763607465.mp4', 'assets/videos/video_1763607465.mp4', 'video', 853722, 'video/mp4', 'Market Price Video', 'Market price information video', 1, 0, 1, '2025-11-20 02:57:45', '2025-11-20 02:57:45', 'third_video'),
(11, 'video_1763607477.mp4', 'assets/videos/video_1763607477.mp4', 'video', 853722, 'video/mp4', 'Market Price Video', 'Market price updates and information video', 1, 0, 1, '2025-11-20 02:57:57', '2025-11-20 02:57:57', 'price_video'),
(12, 'video_1763607484.mp4', 'assets/videos/video_1763607484.mp4', 'video', 30395, 'video/mp4', 'Market Price Video', 'Market price updates and information video', 1, 0, 1, '2025-11-20 02:58:04', '2025-11-20 02:58:04', 'price_video'),
(13, 'video_1763607650.mp4', 'assets/videos/video_1763607650.mp4', 'video', 853722, 'video/mp4', 'Market Price Video', 'Market price information video', 1, 0, 1, '2025-11-20 03:00:50', '2025-11-20 03:00:50', 'third_video'),
(14, 'video_1763607656.mp4', 'assets/videos/video_1763607656.mp4', 'video', 30395, 'video/mp4', 'Market Price Video', 'Market price updates and information video', 1, 0, 1, '2025-11-20 03:00:56', '2025-11-20 03:00:56', 'price_video'),
(15, 'video_1763607663.mp4', 'assets/videos/video_1763607663.mp4', 'video', 10356312, 'video/mp4', 'Market Overview Video', 'Welcome video showcasing the Daet Public Market', 1, 0, 1, '2025-11-20 03:01:04', '2025-11-20 03:01:04', 'overview_video');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `notification_type` enum('approval','rejection','review','reminder') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_codes`
--

CREATE TABLE `password_reset_codes` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempts` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `receipts`
--

CREATE TABLE `receipts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `receipt_file_path` varchar(500) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `section` varchar(100) DEFAULT NULL,
  `block` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `receipts`
--

INSERT INTO `receipts` (`id`, `user_id`, `user_name`, `user_email`, `receipt_file_path`, `notes`, `status`, `admin_notes`, `reviewed_by`, `reviewed_at`, `created_at`, `section`, `block`) VALUES
(1, 12, 'daniel llyod m. prado', 'danielmantesprado@gmail.com', 'uploads/receipts/receipt_12_1765724675.jpg', 'Invalid Reason: Invalid Receipt Format', 'rejected', NULL, 1, '2025-12-15 08:03:45', '2025-12-14 15:04:35', NULL, NULL),
(2, 12, 'daniel llyod m. prado', 'danielmantesprado@gmail.com', 'uploads/receipts/receipt_12_1765724763.jpg', '', 'pending', NULL, NULL, NULL, '2025-12-14 15:06:03', NULL, NULL),
(3, 12, 'daniel llyod m. prado', 'danielmantesprado@gmail.com', 'uploads/receipts/receipt_12_1765725454_693ed50e61d99.jpg', '', 'approved', '1', 1, '2025-12-14 18:47:58', '2025-12-14 15:17:34', NULL, NULL),
(4, 12, 'daniel llyod m. prado', 'danielmantesprado@gmail.com', 'uploads/receipts/receipt_12_1765725979_693ed71bf38e2.jpg', '', 'approved', '', 1, '2025-12-14 18:58:52', '2025-12-14 15:26:20', NULL, NULL),
(5, 12, 'daniel llyod m. prado', 'danielmantesprado@gmail.com', 'uploads/receipts/receipt_12_1765726748_693eda1c13a69.jpg', '', 'rejected', '', 1, '2025-12-14 19:00:13', '2025-12-14 15:39:08', 'Meat and Chicken Section', 'BOT Behind Block B'),
(6, 12, 'daniel llyod m. prado', 'danielmantesprado@gmail.com', 'uploads/receipts/receipt_12_1765727178_693edbca2cf57.png', '', 'rejected', '', 1, '2025-12-14 18:17:19', '2025-12-14 15:46:18', 'Meat and Chicken Section', 'BOT Behind Block B'),
(7, 12, 'daniel llyod m. prado', 'danielmantesprado@gmail.com', 'uploads/receipts/receipt_12_1765727776_693ede2073c0e.jpg', '', 'rejected', '', 1, '2025-12-14 18:17:16', '2025-12-14 15:56:16', 'Meat and Chicken Section', 'BOT Behind Block B'),
(8, 15, 'daniel llyod m. prado', 'danielmantesprado@gmail.com', 'uploads/receipts/receipt_15_1765739683_693f0ca3adb84.jpg', '', 'approved', '', 1, '2025-12-14 19:15:12', '2025-12-14 19:14:43', 'Fresh Fish Section', 'BOT Behind Block C'),
(9, 17, 'althea prado', 'althea123@gmail.com', 'uploads/receipts/receipt_17_1765785776_693fc0b0ea8bb.jpg', 'please process to.....', 'approved', NULL, 1, '2025-12-15 08:03:28', '2025-12-15 08:02:56', 'Dry Goods Section', 'BOT Block A-1'),
(10, 23, 'jefferson anoba', 'jefferson@gmail.com', 'uploads/receipts/receipt_23_1765808083_694017d3dedee.jpg', '', 'pending', NULL, NULL, NULL, '2025-12-15 14:14:43', 'Meat and Chicken Section', 'BOT Block E');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `user_type` enum('admin','vendor','staff') DEFAULT 'vendor',
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `email_verified` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` int(11) DEFAULT 0,
  `account_locked_until` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password_hash`, `full_name`, `phone_number`, `user_type`, `status`, `email_verified`, `created_at`, `updated_at`, `last_login`, `failed_login_attempts`, `account_locked_until`, `reset_token`, `reset_token_expires`) VALUES
(1, 'daetpublicmarket@gmail.com', '$2y$10$kBeqoxMxM10v1q8h8Qy26ecCqOX0HtzxoosBgiqf8VVoMlBHK4Dn.', 'Admin', NULL, 'admin', 'active', 1, '2025-10-29 02:56:16', '2025-12-16 11:43:32', '2025-12-16 11:43:32', 0, NULL, NULL, NULL),
(15, 'danielmantesprado@gmail.com', '$2y$12$vyZnWwzBqV6qZkoqfQX47OAy9n1qA0prH1CF8vHgjwTTHKMcx5X6u', 'daniel llyod m. prado', '09511874805', 'vendor', 'active', 1, '2025-12-14 18:12:50', '2025-12-16 11:22:37', '2025-12-16 11:22:37', 0, NULL, NULL, NULL),
(16, 'john@gmail.com', '$2y$12$b6IfJUxIFMaxjvIvU/OF5.WrdyE3doNhVcU17u/xgMHU36LkKqcuG', 'Jhon Lloyd bacalla', '09511874805', 'vendor', 'active', 1, '2025-12-15 05:51:28', '2025-12-15 13:50:16', '2025-12-15 13:50:16', 0, NULL, NULL, NULL),
(17, 'althea123@gmail.com', '$2y$12$4yFI/bYcovEvTlWo22tn8OO4dlI0fjCt6Gr4T7J4BXElkFZ.IiMyG', 'althea prado', '09516218332', 'vendor', 'active', 1, '2025-12-15 05:57:35', '2025-12-15 12:48:03', '2025-12-15 12:48:03', 0, NULL, NULL, NULL),
(18, 'jhon@gmail.com', '$2y$12$TG2VW2Yi.uVZu2ATPZWFQeQT/WI2LZYMYAD.KlLvprMGspy7jmpLm', 'dsa', '09923979346', 'vendor', 'active', 1, '2025-12-15 05:59:04', '2025-12-16 11:43:55', '2025-12-16 11:43:55', 0, NULL, NULL, NULL),
(19, 'althea1235@gmail.com', '$2y$12$erf9Xu1ou4xzYJJyxqsh6Oxt5G.tVYQ.R/jAEeFnzOxDixQN3j67i', 'althea mantes prado', '095111874806', 'vendor', 'active', 1, '2025-12-15 06:06:32', '2025-12-15 06:06:32', NULL, 0, NULL, NULL, NULL),
(20, 'althea12356@gmail.com', '$2y$12$K49WEolXsnMi0dMjRbpq8OY6m/awgPS0tGFq5DQzeaV1EhXDWRen.', 'althea prado', '095111874807', 'vendor', 'active', 1, '2025-12-15 06:07:34', '2025-12-15 06:07:34', NULL, 0, NULL, NULL, NULL),
(21, 'althea12366@gmail.com', '$2y$12$zyi/gjUx1fRFR/zryQFSWe8obaD6RjoIPzAwqabjfVtlobFbt2dWO', 'althea mantes prada', '095111874804', 'vendor', 'active', 1, '2025-12-15 06:12:56', '2025-12-15 06:12:56', NULL, 0, NULL, NULL, NULL),
(22, 'milo@gmail.com', '$2y$12$uQcTxs9CESdkZDkFd4GUIepCV/PYCXyIlUgZCZNGbiexooeQk39zC', 'milo hershey', '095111874802', 'vendor', 'active', 1, '2025-12-15 13:59:46', '2025-12-15 14:02:11', '2025-12-15 14:02:11', 0, NULL, NULL, NULL),
(23, 'jefferson@gmail.com', '$2y$12$D.qabHgoGdGdkJoP5aKgm.u9Qv7OYkp9U5leHJUKIyWGg7e3zIA5O', 'jefferson anoba', '095111874804', 'vendor', 'active', 1, '2025-12-15 14:09:47', '2025-12-15 14:14:25', '2025-12-15 14:14:25', 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_activity_log`
--

CREATE TABLE `user_activity_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_activity_log`
--

INSERT INTO `user_activity_log` (`log_id`, `user_id`, `activity_type`, `details`, `ip_address`, `created_at`) VALUES
(65, 1, 'application_submitted', 'Submitted new application: Admin - Dry Goods Section', '::1', '2025-12-04 17:29:14'),
(66, 1, 'application_updated', 'Updated application: Admin - Eateries and Cooked Food Section', '::1', '2025-12-04 17:29:52'),
(67, 1, 'renewal_submitted', 'Submitted renewal application: Admin - Eateries and Cooked Food Section', '::1', '2025-12-04 17:34:08'),
(68, 17, 'application_updated', 'Updated application: althea prado - Dry Goods Section', '::1', '2025-12-15 06:55:06'),
(69, 17, 'application_updated', 'Updated application: althea prado - Dry Goods Section', '::1', '2025-12-15 07:00:26'),
(70, 17, 'application_updated', 'Updated application: althea prado - Dry Goods Section', '::1', '2025-12-15 07:06:17'),
(71, 18, 'application_updated', 'Updated application: dsa - Dry Goods Section', '::1', '2025-12-15 07:17:51'),
(72, 18, 'application_updated', 'Updated application: dsa - Dry Goods Section', '::1', '2025-12-15 07:18:10'),
(73, 18, 'application_updated', 'Updated application: dsa - Dry Goods Section', '::1', '2025-12-15 07:21:57'),
(74, 22, 'application_updated', 'Updated application: milo hershey - Fresh Fish Section', '::1', '2025-12-15 14:03:05'),
(75, 22, 'application_updated', 'Updated application: milo hershey - Fresh Fish Section', '::1', '2025-12-15 14:03:37'),
(76, 23, 'application_updated', 'Updated application: jefferson anoba - Groceries Sari-Sari Section', '::1', '2025-12-15 14:15:54'),
(77, 23, 'application_updated', 'Updated application: jefferson anoba - Groceries Sari-Sari Section', '::1', '2025-12-15 14:16:03'),
(78, 23, 'application_updated', 'Updated application: jefferson anoba - Meat and Chicken Section', '::1', '2025-12-15 14:16:16'),
(79, 23, 'application_updated', 'Updated application: jefferson anoba - Groceries Sari-Sari Section', '::1', '2025-12-15 14:19:25'),
(80, 18, 'application_updated', 'Updated application: dsa - Dry Goods Section', '::1', '2025-12-16 12:13:35');

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `profile_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `profile_type` enum('vendor','admin','staff') NOT NULL,
  `profile_image` varchar(500) DEFAULT NULL,
  `business_license` varchar(500) DEFAULT NULL,
  `tax_certificate` varchar(500) DEFAULT NULL,
  `id_document` varchar(500) DEFAULT NULL,
  `additional_documents` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_documents`)),
  `profile_status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`profile_id`, `user_id`, `vendor_id`, `profile_type`, `profile_image`, `business_license`, `tax_certificate`, `id_document`, `additional_documents`, `profile_status`, `created_at`, `updated_at`) VALUES
(75, 1, NULL, 'vendor', NULL, NULL, NULL, NULL, NULL, 'active', '2025-11-01 12:53:30', '2025-12-05 05:39:15'),
(158, 15, 1, 'vendor', NULL, NULL, NULL, NULL, NULL, 'inactive', '2025-12-14 18:13:17', '2025-12-15 07:46:28'),
(162, 18, 8, 'vendor', NULL, NULL, NULL, NULL, NULL, 'inactive', '2025-12-15 05:59:39', '2025-12-15 07:21:01'),
(163, 17, 9, 'vendor', NULL, NULL, NULL, NULL, NULL, 'active', '2025-12-15 06:53:58', '2025-12-15 12:17:06'),
(173, 22, NULL, 'vendor', NULL, NULL, NULL, NULL, NULL, 'active', '2025-12-15 14:02:33', '2025-12-15 14:02:33'),
(174, 23, NULL, 'vendor', NULL, NULL, NULL, NULL, NULL, 'active', '2025-12-15 14:15:08', '2025-12-15 14:15:08');

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `vendor_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vendor_name` varchar(255) NOT NULL,
  `section` varchar(100) NOT NULL,
  `block` varchar(100) DEFAULT NULL,
  `application_type` enum('new','renewal') NOT NULL,
  `status` enum('pending','approved','incomplete') DEFAULT 'pending',
  `submitted_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_date` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `approval_priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `review_deadline` date DEFAULT NULL,
  `last_reminder_sent` timestamp NULL DEFAULT NULL,
  `profile_uploaded` tinyint(1) DEFAULT 0,
  `profile_upload_date` timestamp NULL DEFAULT NULL,
  `compliance_score` int(11) DEFAULT 0,
  `risk_level` enum('low','medium','high') DEFAULT 'medium',
  `resubmission_count` int(11) DEFAULT 0,
  `last_resubmission_date` timestamp NULL DEFAULT NULL,
  `auto_approve_eligible` tinyint(1) DEFAULT 0,
  `expiration_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendors`
--

INSERT INTO `vendors` (`vendor_id`, `user_id`, `vendor_name`, `section`, `block`, `application_type`, `status`, `submitted_date`, `reviewed_date`, `reviewed_by`, `admin_notes`, `approval_priority`, `review_deadline`, `last_reminder_sent`, `profile_uploaded`, `profile_upload_date`, `compliance_score`, `risk_level`, `resubmission_count`, `last_resubmission_date`, `auto_approve_eligible`, `expiration_date`) VALUES
(1, 15, 'daniel llyod m. prado', 'Fresh Fish Section', 'Daet Elevated Plaza', 'new', 'incomplete', '2025-12-15 04:34:57', '2025-12-15 07:46:28', 1, 'Incomplete Reason: Incomplete Documentation', 'medium', NULL, NULL, 0, NULL, 0, 'medium', 0, NULL, 0, NULL),
(2, 15, 'daniel llyod m. prado', 'Fresh Fish Section', 'BOT Behind Block C', 'new', 'approved', '2025-12-14 18:30:03', '2025-12-15 07:34:33', 1, '', 'medium', NULL, NULL, 0, NULL, 0, 'medium', 0, NULL, 0, NULL),
(3, 15, 'daniel llyod m. prado', 'Groceries Sari-Sari Section', 'BOT Block A-1', 'new', 'incomplete', '2025-12-14 20:10:29', '2025-12-15 07:34:19', 1, 'Incomplete Reason: Invalid Business License', 'medium', NULL, NULL, 0, NULL, 0, 'medium', 0, NULL, 0, NULL),
(4, 15, 'daniel llyod m. prado', 'Fruits and Vegetables Section', 'BOT Block E', 'new', 'incomplete', '2025-12-15 04:46:16', '2025-12-15 04:51:04', 1, 'Incomplete Reason: Incomplete Documentation', 'medium', NULL, NULL, 0, NULL, 0, 'medium', 0, NULL, 0, NULL),
(5, 15, 'daniel llyod m. prado', 'Fresh Fish Section', 'BOT Block E', 'new', 'incomplete', '2025-12-15 05:03:47', '2025-12-15 06:54:04', 1, 'Incomplete Reason: Invalid Business License', 'medium', NULL, NULL, 0, NULL, 0, 'medium', 0, NULL, 0, NULL),
(6, 17, 'althea prado', 'Dry Goods Section', 'BOT Block A-1', 'new', 'approved', '2025-12-15 07:06:17', '2025-12-15 07:28:56', 1, '', 'medium', NULL, NULL, 0, NULL, 0, 'medium', 0, NULL, 0, NULL),
(7, 17, 'althea prado', 'Dried and Salted Fish Section', 'BOT Block E', 'new', 'incomplete', '2025-12-15 06:17:52', '2025-12-15 06:54:02', 1, 'Incomplete Reason: Missing Required Documents', 'medium', NULL, NULL, 0, NULL, 0, 'medium', 0, NULL, 0, NULL),
(8, 18, 'dsa', 'Dry Goods Section', 'BOT Block E', 'new', 'pending', '2025-12-16 12:13:35', NULL, NULL, NULL, 'medium', NULL, NULL, 0, NULL, 0, 'medium', 0, NULL, 0, NULL),
(9, 17, 'althea prado', 'Eateries and Cooked Food Section', 'Market Tienda Bldg', 'new', 'approved', '2025-12-15 12:03:43', '2025-12-15 12:17:06', 1, '', 'medium', NULL, NULL, 0, NULL, 0, 'medium', 0, NULL, 0, NULL),
(10, 22, 'milo hershey', 'Fresh Fish Section', 'BOT Behind Block C', 'new', 'pending', '2025-12-15 14:03:37', NULL, NULL, NULL, 'medium', NULL, NULL, 0, NULL, 0, 'medium', 0, NULL, 0, NULL),
(11, 23, 'jefferson anoba', 'Meat and Chicken Section', 'BOT Block E', 'new', 'pending', '2025-12-15 14:16:16', NULL, NULL, NULL, 'medium', NULL, NULL, 0, NULL, 0, 'medium', 0, NULL, 0, NULL),
(12, 23, 'jefferson anoba', 'Groceries Sari-Sari Section', 'BOT Block A-1', 'new', 'pending', '2025-12-15 14:19:25', NULL, NULL, NULL, 'medium', NULL, NULL, 0, NULL, 0, 'medium', 0, NULL, 0, NULL),
(13, 15, 'daniel llyod m. prado', 'Groceries Sari-Sari Section', 'BOT Behind Block C', 'new', 'pending', '2025-12-15 14:41:57', NULL, NULL, NULL, 'medium', NULL, NULL, 0, NULL, 0, 'medium', 0, NULL, 0, NULL),
(14, 15, 'daniel llyod m. prado', 'Fruits and Vegetables Section', 'Daet Elevated Plaza', 'new', 'pending', '2025-12-16 11:28:49', NULL, NULL, NULL, 'medium', NULL, NULL, 0, NULL, 0, 'medium', 0, NULL, 0, NULL),
(15, 18, 'dsa', 'Dry Goods Section', 'BOT Block E', 'renewal', 'pending', '2025-12-16 11:43:09', NULL, NULL, NULL, 'medium', NULL, NULL, 0, NULL, 0, 'medium', 0, NULL, 0, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_profiles`
--
ALTER TABLE `admin_profiles`
  ADD PRIMARY KEY (`profile_id`),
  ADD UNIQUE KEY `unique_admin` (`admin_id`);

--
-- Indexes for table `application_documents`
--
ALTER TABLE `application_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `idx_application_id` (`application_id`),
  ADD KEY `idx_vendor_id` (`vendor_id`),
  ADD KEY `idx_upload_date` (`upload_date`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_app_docs_vendor_id_active` (`vendor_id`,`is_active`),
  ADD KEY `idx_app_docs_upload_date` (`upload_date`);

--
-- Indexes for table `application_requirements`
--
ALTER TABLE `application_requirements`
  ADD PRIMARY KEY (`requirement_id`),
  ADD KEY `idx_application_type` (`application_type`),
  ADD KEY `idx_display_order` (`display_order`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `approval_actions`
--
ALTER TABLE `approval_actions`
  ADD PRIMARY KEY (`action_id`),
  ADD KEY `idx_vendor_id` (`vendor_id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_action_date` (`action_date`),
  ADD KEY `idx_approval_actions_vendor_id` (`vendor_id`),
  ADD KEY `idx_approval_actions_admin_id` (`admin_id`),
  ADD KEY `idx_approval_actions_action_date` (`action_date`);

--
-- Indexes for table `approval_notifications`
--
ALTER TABLE `approval_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_vendor_id` (`vendor_id`),
  ADD KEY `idx_notification_type` (`notification_type`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `approval_settings`
--
ALTER TABLE `approval_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_setting_key` (`setting_key`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `approval_workflow`
--
ALTER TABLE `approval_workflow`
  ADD PRIMARY KEY (`workflow_id`),
  ADD KEY `idx_vendor_id` (`vendor_id`),
  ADD KEY `idx_current_step` (`current_step`),
  ADD KEY `idx_step_status` (`step_status`),
  ADD KEY `idx_assigned_to` (`assigned_to`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_table_record` (`table_name`,`record_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recipient` (`recipient_email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_email_type` (`email_type`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_attempted_at` (`attempted_at`),
  ADD KEY `idx_success` (`success`);

--
-- Indexes for table `login_sessions`
--
ALTER TABLE `login_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `idx_token` (`session_token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `market_slots`
--
ALTER TABLE `market_slots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_slot` (`block_name`,`slot_number`);

--
-- Indexes for table `media_files`
--
ALTER TABLE `media_files`
  ADD PRIMARY KEY (`media_id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `idx_file_type` (`file_type`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_display_order` (`display_order`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_notification_type` (`notification_type`);

--
-- Indexes for table `password_reset_codes`
--
ALTER TABLE `password_reset_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `receipts`
--
ALTER TABLE `receipts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_section` (`section`),
  ADD KEY `idx_block` (`block`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_user_type` (`user_type`),
  ADD KEY `idx_reset_token` (`reset_token`),
  ADD KEY `idx_users_user_id` (`user_id`),
  ADD KEY `idx_users_email` (`email`);

--
-- Indexes for table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_activity_type` (`activity_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`profile_id`),
  ADD UNIQUE KEY `unique_user_profile` (`user_id`,`profile_type`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_vendor_id` (`vendor_id`),
  ADD KEY `idx_profile_type` (`profile_type`),
  ADD KEY `idx_profile_status` (`profile_status`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`vendor_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_application_type` (`application_type`),
  ADD KEY `idx_section` (`section`),
  ADD KEY `idx_submitted_date` (`submitted_date`),
  ADD KEY `idx_vendors_status_priority` (`status`,`approval_priority`),
  ADD KEY `idx_vendors_review_deadline` (`review_deadline`),
  ADD KEY `idx_vendors_profile_uploaded` (`profile_uploaded`),
  ADD KEY `idx_vendors_compliance_score` (`compliance_score`),
  ADD KEY `idx_vendors_risk_level` (`risk_level`),
  ADD KEY `idx_vendors_resubmission_count` (`resubmission_count`),
  ADD KEY `idx_vendors_status_submitted_date` (`status`,`submitted_date`),
  ADD KEY `idx_vendors_type_submitted_date` (`application_type`,`submitted_date`),
  ADD KEY `idx_vendors_user_id` (`user_id`),
  ADD KEY `idx_vendors_status_type_date` (`status`,`application_type`,`submitted_date`),
  ADD KEY `idx_vendors_reviewed_date` (`reviewed_date`),
  ADD KEY `idx_vendors_reviewed_by` (`reviewed_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_profiles`
--
ALTER TABLE `admin_profiles`
  MODIFY `profile_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT for table `application_documents`
--
ALTER TABLE `application_documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `application_requirements`
--
ALTER TABLE `application_requirements`
  MODIFY `requirement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `approval_actions`
--
ALTER TABLE `approval_actions`
  MODIFY `action_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `approval_notifications`
--
ALTER TABLE `approval_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=115;

--
-- AUTO_INCREMENT for table `approval_settings`
--
ALTER TABLE `approval_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `approval_workflow`
--
ALTER TABLE `approval_workflow`
  MODIFY `workflow_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=425;

--
-- AUTO_INCREMENT for table `login_sessions`
--
ALTER TABLE `login_sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `market_slots`
--
ALTER TABLE `market_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32247;

--
-- AUTO_INCREMENT for table `media_files`
--
ALTER TABLE `media_files`
  MODIFY `media_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_codes`
--
ALTER TABLE `password_reset_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `receipts`
--
ALTER TABLE `receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `user_profiles`
--
ALTER TABLE `user_profiles`
  MODIFY `profile_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=175;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `vendor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_profiles`
--
ALTER TABLE `admin_profiles`
  ADD CONSTRAINT `admin_profiles_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `application_documents`
--
ALTER TABLE `application_documents`
  ADD CONSTRAINT `application_documents_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `vendors` (`vendor_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `application_documents_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`vendor_id`) ON DELETE CASCADE;

--
-- Constraints for table `approval_actions`
--
ALTER TABLE `approval_actions`
  ADD CONSTRAINT `approval_actions_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`vendor_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `approval_actions_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `approval_notifications`
--
ALTER TABLE `approval_notifications`
  ADD CONSTRAINT `approval_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `approval_notifications_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`vendor_id`) ON DELETE CASCADE;

--
-- Constraints for table `approval_settings`
--
ALTER TABLE `approval_settings`
  ADD CONSTRAINT `approval_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `approval_workflow`
--
ALTER TABLE `approval_workflow`
  ADD CONSTRAINT `approval_workflow_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`vendor_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `approval_workflow_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `login_sessions`
--
ALTER TABLE `login_sessions`
  ADD CONSTRAINT `login_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `media_files`
--
ALTER TABLE `media_files`
  ADD CONSTRAINT `media_files_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`vendor_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  ADD CONSTRAINT `user_activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_profiles_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`vendor_id`) ON DELETE SET NULL;

--
-- Constraints for table `vendors`
--
ALTER TABLE `vendors`
  ADD CONSTRAINT `vendors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendors_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
