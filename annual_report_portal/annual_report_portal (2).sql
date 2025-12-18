-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 18, 2025 at 10:44 AM
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
-- Database: `annual_report_portal`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `grant_coordinator_access` (IN `p_user_id` INT, IN `p_granted_by` INT, IN `p_duration_hours` INT, IN `p_can_approve` BOOLEAN)   BEGIN
    DECLARE v_expires_at DATETIME;
    
    -- Calculate expiration time
    SET v_expires_at = DATE_ADD(NOW(), INTERVAL p_duration_hours HOUR);
    
    -- Insert or update coordinator access
    INSERT INTO coordinator_access (user_id, granted_by, can_approve_reports, expires_at)
    VALUES (p_user_id, p_granted_by, p_can_approve, v_expires_at)
    ON DUPLICATE KEY UPDATE
        granted_by = p_granted_by,
        can_approve_reports = p_can_approve,
        expires_at = v_expires_at,
        updated_at = CURRENT_TIMESTAMP;
    
    -- Update user role to coordinator if not already
    UPDATE users SET role = 'coordinator' WHERE id = p_user_id;
    
    -- Log the action
    INSERT INTO activity_log (user_id, activity, ip_address)
    VALUES (p_granted_by, CONCAT('Granted coordinator access to user ID ', p_user_id, ' for ', p_duration_hours, ' hours'), 'SYSTEM');
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `revoke_coordinator_access` (IN `p_user_id` INT, IN `p_revoked_by` INT)   BEGIN
    -- Delete coordinator access
    DELETE FROM coordinator_access WHERE user_id = p_user_id;
    
    -- Change role back to teacher or student
    UPDATE users 
    SET role = CASE 
        WHEN EXISTS (SELECT 1 FROM classes WHERE teacher_id = p_user_id) THEN 'teacher'
        ELSE 'student'
    END
    WHERE id = p_user_id;
    
    -- Log the action
    INSERT INTO activity_log (user_id, activity, ip_address)
    VALUES (p_revoked_by, CONCAT('Revoked coordinator access from user ID ', p_user_id), 'SYSTEM');
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_coordinators_view`
-- (See below for the actual view)
--
CREATE TABLE `active_coordinators_view` (
`id` int(11)
,`name` varchar(100)
,`email` varchar(100)
,`can_approve_reports` tinyint(1)
,`can_view_all_reports` tinyint(1)
,`expires_at` datetime
,`access_granted_at` timestamp
,`hours_remaining` bigint(21)
,`granted_by_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` varchar(20) DEFAULT NULL,
  `activity` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `log_time` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `role`, `activity`, `ip_address`, `action`, `log_time`, `created_at`) VALUES
(1, 5, NULL, '', NULL, 'Report ID 1 approve', '2025-11-12 21:51:54', '2025-11-12 19:23:09'),
(2, 6, 'teacher', 'Uploaded new report: asfdgh', NULL, NULL, '2025-11-13 00:53:56', '2025-11-12 19:23:56'),
(3, 5, 'admin', 'Report ID 2 was rejected', NULL, NULL, '2025-11-13 01:08:52', '2025-11-12 19:38:52'),
(4, 5, 'admin', 'Report ID 5 was rejected', NULL, NULL, '2025-11-13 01:08:56', '2025-11-12 19:38:56'),
(5, 5, 'admin', 'Report ID 4 was rejected', NULL, NULL, '2025-11-13 01:08:57', '2025-11-12 19:38:57'),
(6, 5, 'admin', 'Report ID 3 was approved', NULL, NULL, '2025-11-13 01:08:59', '2025-11-12 19:38:59'),
(7, 6, 'teacher', 'Uploaded new report: report', NULL, NULL, '2025-11-14 13:35:54', '2025-11-14 08:05:54'),
(8, 5, 'admin', 'Approved user ID 7', NULL, NULL, '2025-11-14 13:38:35', '2025-11-14 08:08:35'),
(9, 5, 'admin', 'Report ID 6 was approved', NULL, NULL, '2025-11-14 13:40:04', '2025-11-14 08:10:04'),
(10, 6, 'teacher', 'Submitted report ID 9 for approval', NULL, NULL, '2025-11-15 23:07:08', '2025-11-15 17:37:08'),
(11, 6, 'teacher', 'Submitted report ID 7 for approval', NULL, NULL, '2025-11-15 23:07:15', '2025-11-15 17:37:15'),
(12, 6, 'teacher', 'Edited report ID 9', NULL, NULL, '2025-11-15 23:18:44', '2025-11-15 17:48:44'),
(13, 6, 'teacher', 'Deleted report ID 9', NULL, NULL, '2025-11-15 23:19:24', '2025-11-15 17:49:24'),
(14, 6, 'teacher', 'Edited report ID 10', NULL, NULL, '2025-11-15 23:21:34', '2025-11-15 17:51:34'),
(15, 6, 'teacher', 'Edited report ID 10', NULL, NULL, '2025-11-15 23:23:48', '2025-11-15 17:53:48'),
(16, 6, 'teacher', 'Edited report ID 10', NULL, NULL, '2025-11-15 23:23:51', '2025-11-15 17:53:51'),
(17, 6, 'teacher', 'Edited report ID 10', NULL, NULL, '2025-11-15 23:24:01', '2025-11-15 17:54:01'),
(18, 6, 'teacher', 'Edited report ID 10', NULL, NULL, '2025-11-15 23:26:38', '2025-11-15 17:56:38'),
(19, 6, 'teacher', 'Edited report ID 10', NULL, NULL, '2025-11-15 23:28:06', '2025-11-15 17:58:06'),
(20, 6, 'teacher', 'Edited report ID 10', NULL, NULL, '2025-11-15 23:28:12', '2025-11-15 17:58:12'),
(21, 6, 'teacher', 'Submitted report ID 10 for approval', NULL, NULL, '2025-11-15 23:29:03', '2025-11-15 17:59:03'),
(22, 6, 'teacher', 'Edited report ID 11', NULL, NULL, '2025-11-15 23:30:28', '2025-11-15 18:00:28'),
(23, 6, 'teacher', 'Deleted report ID 11', NULL, NULL, '2025-11-15 23:31:23', '2025-11-15 18:01:23'),
(24, 6, 'teacher', 'Edited report ID 10', NULL, NULL, '2025-11-15 23:32:27', '2025-11-15 18:02:27'),
(25, 6, 'teacher', 'Deleted report ID 5', NULL, NULL, '2025-11-15 23:39:03', '2025-11-15 18:09:03'),
(26, 6, 'teacher', 'Deleted report ID 10', NULL, NULL, '2025-11-15 23:39:07', '2025-11-15 18:09:07'),
(27, 6, 'teacher', 'Edited report ID 12', NULL, NULL, '2025-11-15 23:46:00', '2025-11-15 18:16:00'),
(28, 6, 'teacher', 'Edited report ID 12', NULL, NULL, '2025-11-15 23:46:55', '2025-11-15 18:16:55'),
(29, 6, 'teacher', 'Deleted report ID 12', NULL, NULL, '2025-11-15 23:47:25', '2025-11-15 18:17:25'),
(30, 6, 'teacher', 'Edited report ID 13', NULL, NULL, '2025-11-15 23:47:57', '2025-11-15 18:17:57'),
(31, 6, 'teacher', 'Deleted report ID 13', NULL, NULL, '2025-11-15 23:50:44', '2025-11-15 18:20:44'),
(32, 6, 'teacher', 'Deleted report ID 2', NULL, NULL, '2025-11-15 23:52:54', '2025-11-15 18:22:54'),
(33, 6, 'teacher', 'Updated report ID 14', NULL, NULL, '2025-11-15 23:53:35', '2025-11-15 18:23:35'),
(34, 6, 'teacher', 'Deleted report ID 14', NULL, NULL, '2025-11-15 23:55:20', '2025-11-15 18:25:20'),
(35, 6, 'teacher', 'Deleted report ID 16', NULL, NULL, '2025-11-15 23:57:53', '2025-11-15 18:27:53'),
(36, 6, 'teacher', 'Updated report ID 15', NULL, NULL, '2025-11-15 23:59:13', '2025-11-15 18:29:13'),
(37, 6, 'teacher', 'Submitted report ID 15 for approval', NULL, NULL, '2025-11-15 23:59:35', '2025-11-15 18:29:35'),
(38, 6, 'teacher', 'Updated report ID 15', NULL, NULL, '2025-11-16 00:01:48', '2025-11-15 18:31:48'),
(39, 6, 'teacher', 'Deleted report ID 15', NULL, NULL, '2025-11-16 00:02:02', '2025-11-15 18:32:02'),
(40, 6, 'teacher', 'Updated report ID 8', NULL, NULL, '2025-11-16 00:22:03', '2025-11-15 18:52:03'),
(41, 6, 'teacher', 'Deleted report ID 8', NULL, NULL, '2025-11-16 00:29:09', '2025-11-15 18:59:09'),
(42, 6, 'teacher', 'Updated report ID 7', NULL, NULL, '2025-11-16 00:32:54', '2025-11-15 19:02:54'),
(43, 6, 'teacher', 'Deleted report ID 17', NULL, NULL, '2025-11-16 00:36:52', '2025-11-15 19:06:52'),
(44, 6, 'teacher', 'Updated report ID 7', NULL, NULL, '2025-11-16 00:37:00', '2025-11-15 19:07:00'),
(45, 6, 'teacher', 'Submitted report ID 18 for approval', NULL, NULL, '2025-11-16 00:37:51', '2025-11-15 19:07:51'),
(46, 6, 'teacher', 'Updated report ID 18', NULL, NULL, '2025-11-16 00:38:02', '2025-11-15 19:08:02'),
(47, 6, 'teacher', 'Submitted report ID 18 for approval', NULL, NULL, '2025-11-16 00:40:31', '2025-11-15 19:10:31'),
(48, 5, 'admin', 'Toggled account for user ID 8 (disabled)', NULL, NULL, '2025-11-16 00:56:42', '2025-11-15 19:26:42'),
(49, 5, 'admin', 'Approved user ID 13', NULL, NULL, '2025-11-19 08:57:09', '2025-11-19 03:27:09'),
(50, 5, 'admin', 'Report ID 18 was approved', NULL, NULL, '2025-11-19 08:59:56', '2025-11-19 03:29:56'),
(51, 6, 'teacher', 'Submitted report ID 19 for approval', NULL, NULL, '2025-11-19 09:05:38', '2025-11-19 03:35:38'),
(52, 6, 'teacher', 'Updated report ID 4', NULL, NULL, '2025-11-19 09:05:47', '2025-11-19 03:35:47'),
(53, 6, 'teacher', 'Submitted report ID 4 for approval', NULL, NULL, '2025-11-19 09:05:49', '2025-11-19 03:35:49'),
(54, 5, 'admin', 'Report ID 20 was rejected', NULL, NULL, '2025-11-19 09:07:20', '2025-11-19 03:37:20'),
(55, 5, 'admin', 'Report ID 19 was rejected', NULL, NULL, '2025-11-19 09:07:21', '2025-11-19 03:37:21'),
(56, 5, 'admin', 'Report ID 4 was rejected', NULL, NULL, '2025-11-19 09:07:22', '2025-11-19 03:37:22'),
(57, 5, 'admin', 'Approved user ID 11', NULL, NULL, '2025-11-19 09:47:21', '2025-11-19 04:17:21'),
(58, 6, 'teacher', 'Updated report ID 20', NULL, NULL, '2025-11-19 09:49:48', '2025-11-19 04:19:48'),
(59, 6, 'teacher', 'Submitted report ID 20 for approval', NULL, NULL, '2025-11-19 09:49:51', '2025-11-19 04:19:51'),
(60, 6, 'teacher', 'Updated report ID 19', NULL, NULL, '2025-11-19 09:51:30', '2025-11-19 04:21:30'),
(61, 6, 'teacher', 'Submitted report ID 19 for approval', NULL, NULL, '2025-11-19 09:52:42', '2025-11-19 04:22:42'),
(62, 5, 'admin', 'Approved user ID 14', NULL, NULL, '2025-11-22 12:30:57', '2025-11-22 07:00:57'),
(63, 5, 'admin', 'Created class: class 10 2 (2021-2023)', NULL, NULL, '2025-11-22 13:03:15', '2025-11-22 07:33:15'),
(64, 5, 'admin', 'Assigned teacher ID 14 to class ID 8', NULL, NULL, '2025-11-22 13:03:25', '2025-11-22 07:33:25'),
(65, 5, 'admin', 'Assigned 2 students to class ID 8, teacher ID 14', NULL, NULL, '2025-11-22 13:03:34', '2025-11-22 07:33:34'),
(66, 5, 'admin', 'Approved user ID 9', NULL, NULL, '2025-11-22 13:09:30', '2025-11-22 07:39:30'),
(67, 5, 'admin', 'Approved user ID 8', NULL, NULL, '2025-11-22 13:09:33', '2025-11-22 07:39:33'),
(68, 14, 'teacher', 'Uploaded new report: new one', NULL, NULL, '2025-11-22 13:32:06', '2025-11-22 08:02:06'),
(69, 14, 'teacher', 'Uploaded new report: new one', NULL, NULL, '2025-11-22 13:32:30', '2025-11-22 08:02:30'),
(70, 14, 'teacher', 'Updated report ID 22', NULL, NULL, '2025-11-22 13:35:47', '2025-11-22 08:05:47'),
(71, 14, 'teacher', 'Submitted report for approval: new one', NULL, NULL, '2025-11-22 13:46:22', '2025-11-22 08:16:22'),
(72, 14, 'teacher', 'Submitted report for approval: new one', NULL, NULL, '2025-11-22 13:46:28', '2025-11-22 08:16:28'),
(73, 5, 'admin', 'Admin System Administrator approved report ID 21', NULL, NULL, '2025-11-22 13:47:09', '2025-11-22 08:17:09'),
(74, 5, 'admin', 'Admin System Administrator rejected report ID 19', NULL, NULL, '2025-11-22 13:47:27', '2025-11-22 08:17:27'),
(75, 5, 'admin', 'Admin System Administrator approved report ID 20', NULL, NULL, '2025-11-22 13:47:42', '2025-11-22 08:17:42'),
(76, 14, 'teacher', 'Created report via API: recent', NULL, NULL, '2025-11-22 13:53:33', '2025-11-22 08:23:33'),
(77, 14, 'teacher', 'Updated report: recent', NULL, NULL, '2025-11-22 13:53:51', '2025-11-22 08:23:51'),
(78, 14, 'teacher', 'Submitted report for approval: recent', NULL, NULL, '2025-11-22 13:53:55', '2025-11-22 08:23:55'),
(79, 5, 'admin', 'Assigned teacher ID 6 to class ID 1', NULL, NULL, '2025-11-22 14:12:08', '2025-11-22 08:42:08'),
(80, 5, 'admin', 'Assigned 2 students to class ID 1, teacher ID 6', NULL, NULL, '2025-11-22 14:12:13', '2025-11-22 08:42:13'),
(81, 5, 'admin', 'Assigned coordinator role to user ID 14 until 2025-11-22T02:40', NULL, NULL, '2025-11-22 14:13:13', '2025-11-22 08:43:13'),
(82, 5, 'admin', 'Extended coordinator access for ID 1 until 2025-11-29T02:40', NULL, NULL, '2025-11-22 14:13:23', '2025-11-22 08:43:23'),
(83, 5, 'admin', 'Extended coordinator access for ID 1 until 2025-11-29T02:40', NULL, NULL, '2025-11-22 14:14:27', '2025-11-22 08:44:27'),
(84, 5, 'admin', 'Deleted class ID 2', NULL, NULL, '2025-11-22 14:14:39', '2025-11-22 08:44:39'),
(85, 6, 'teacher', 'Submitted report for approval: anyhtingg', NULL, NULL, '2025-11-22 14:15:44', '2025-11-22 08:45:44'),
(86, 5, 'admin', 'Revoked coordinator access for user ID 14', NULL, NULL, '2025-11-22 14:29:29', '2025-11-22 08:59:29'),
(87, 5, 'admin', 'Assigned coordinator role to user ID 14 until 2025-11-29T09:59', NULL, NULL, '2025-11-22 14:29:41', '2025-11-22 08:59:41'),
(88, 5, 'admin', 'Revoked coordinator access for user ID 14', NULL, NULL, '2025-11-22 14:31:18', '2025-11-22 09:01:18'),
(89, 5, 'admin', 'Assigned coordinator role to user ID 6 until 2025-11-29T10:01', NULL, NULL, '2025-11-22 14:31:31', '2025-11-22 09:01:31'),
(90, 5, 'admin', 'Admin System Administrator approved report ID 22', NULL, NULL, '2025-11-22 15:00:14', '2025-11-22 09:30:14'),
(91, 5, 'admin', 'Admin System Administrator approved report ID 22', NULL, NULL, '2025-11-22 15:02:51', '2025-11-22 09:32:51'),
(92, 5, 'admin', 'Admin System Administrator approved report ID 7', NULL, NULL, '2025-11-22 15:03:12', '2025-11-22 09:33:12'),
(93, 14, 'teacher', 'Created report via API: test', NULL, NULL, '2025-11-22 15:05:10', '2025-11-22 09:35:10'),
(94, 14, 'teacher', 'Created report: test (Status: draft)', NULL, NULL, '2025-11-22 17:30:35', '2025-11-22 12:00:35'),
(95, 14, 'teacher', 'Updated report: test', NULL, NULL, '2025-11-22 17:32:20', '2025-11-22 12:02:20'),
(96, 5, 'admin', 'Admin System Administrator approved report ID 23', NULL, NULL, '2025-12-12 23:59:20', '2025-12-12 18:29:20'),
(97, 5, 'admin', 'Admin System Administrator approved report ID 24', NULL, NULL, '2025-12-12 23:59:29', '2025-12-12 18:29:29'),
(98, 5, 'admin', 'Toggled account for user ID 8 (active)', NULL, NULL, '2025-12-13 00:00:36', '2025-12-12 18:30:36');

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `action_desc` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_logs`
--

INSERT INTO `admin_logs` (`id`, `admin_id`, `action_desc`, `created_at`) VALUES
(1, 5, 'Report ID 1 was approved', '2025-11-12 16:40:55'),
(2, 6, 'Uploaded new report: asfdgh', '2025-11-12 19:17:24'),
(3, 6, 'Uploaded new report: asfdgh', '2025-11-12 19:18:52'),
(4, 6, 'Uploaded new report: asfdgh', '2025-11-12 19:18:57'),
(5, 6, 'Uploaded new report: asfdgh', '2025-11-12 19:23:56'),
(6, 5, 'Report ID 2 was rejected', '2025-11-12 19:38:52'),
(7, 5, 'Report ID 5 was rejected', '2025-11-12 19:38:56'),
(8, 5, 'Report ID 4 was rejected', '2025-11-12 19:38:57'),
(9, 5, 'Report ID 3 was approved', '2025-11-12 19:38:59'),
(10, 6, 'Uploaded new report: report', '2025-11-14 08:05:54'),
(11, 5, 'Approved user ID 7', '2025-11-14 08:08:35'),
(12, 5, 'Report ID 6 was approved', '2025-11-14 08:10:04'),
(13, 6, 'Submitted report ID 9 for approval', '2025-11-15 17:37:08'),
(14, 6, 'Submitted report ID 7 for approval', '2025-11-15 17:37:15'),
(15, 6, 'Edited report ID 9', '2025-11-15 17:48:44'),
(16, 6, 'Deleted report ID 9', '2025-11-15 17:49:24'),
(17, 6, 'Edited report ID 10', '2025-11-15 17:51:34'),
(18, 6, 'Edited report ID 10', '2025-11-15 17:53:48'),
(19, 6, 'Edited report ID 10', '2025-11-15 17:53:51'),
(20, 6, 'Edited report ID 10', '2025-11-15 17:54:01'),
(21, 6, 'Edited report ID 10', '2025-11-15 17:56:38'),
(22, 6, 'Edited report ID 10', '2025-11-15 17:58:06'),
(23, 6, 'Edited report ID 10', '2025-11-15 17:58:12'),
(24, 6, 'Submitted report ID 10 for approval', '2025-11-15 17:59:03'),
(25, 6, 'Edited report ID 11', '2025-11-15 18:00:28'),
(26, 6, 'Deleted report ID 11', '2025-11-15 18:01:23'),
(27, 6, 'Edited report ID 10', '2025-11-15 18:02:27'),
(28, 6, 'Deleted report ID 5', '2025-11-15 18:09:03'),
(29, 6, 'Deleted report ID 10', '2025-11-15 18:09:07'),
(30, 6, 'Edited report ID 12', '2025-11-15 18:16:00'),
(31, 6, 'Edited report ID 12', '2025-11-15 18:16:55'),
(32, 6, 'Deleted report ID 12', '2025-11-15 18:17:25'),
(33, 6, 'Edited report ID 13', '2025-11-15 18:17:57'),
(34, 6, 'Deleted report ID 13', '2025-11-15 18:20:44'),
(35, 6, 'Deleted report ID 2', '2025-11-15 18:22:54'),
(36, 6, 'Updated report ID 14', '2025-11-15 18:23:35'),
(37, 6, 'Deleted report ID 14', '2025-11-15 18:25:20'),
(38, 6, 'Deleted report ID 16', '2025-11-15 18:27:53'),
(39, 6, 'Updated report ID 15', '2025-11-15 18:29:13'),
(40, 6, 'Submitted report ID 15 for approval', '2025-11-15 18:29:35'),
(41, 6, 'Updated report ID 15', '2025-11-15 18:31:48'),
(42, 6, 'Deleted report ID 15', '2025-11-15 18:32:02'),
(43, 6, 'Updated report ID 8', '2025-11-15 18:52:03'),
(44, 6, 'Deleted report ID 8', '2025-11-15 18:59:09'),
(45, 6, 'Updated report ID 7', '2025-11-15 19:02:54'),
(46, 6, 'Deleted report ID 17', '2025-11-15 19:06:52'),
(47, 6, 'Updated report ID 7', '2025-11-15 19:07:00'),
(48, 6, 'Submitted report ID 18 for approval', '2025-11-15 19:07:51'),
(49, 6, 'Updated report ID 18', '2025-11-15 19:08:02'),
(50, 6, 'Submitted report ID 18 for approval', '2025-11-15 19:10:31'),
(51, 5, 'Toggled account for user ID 8 (disabled)', '2025-11-15 19:26:42'),
(52, 5, 'Approved user ID 13', '2025-11-19 03:27:09'),
(53, 5, 'Report ID 18 was approved', '2025-11-19 03:29:56'),
(54, 6, 'Submitted report ID 19 for approval', '2025-11-19 03:35:38'),
(55, 6, 'Updated report ID 4', '2025-11-19 03:35:47'),
(56, 6, 'Submitted report ID 4 for approval', '2025-11-19 03:35:49'),
(57, 5, 'Report ID 20 was rejected', '2025-11-19 03:37:20'),
(58, 5, 'Report ID 19 was rejected', '2025-11-19 03:37:20'),
(59, 5, 'Report ID 4 was rejected', '2025-11-19 03:37:22'),
(60, 5, 'Approved user ID 11', '2025-11-19 04:17:21'),
(61, 6, 'Updated report ID 20', '2025-11-19 04:19:48'),
(62, 6, 'Submitted report ID 20 for approval', '2025-11-19 04:19:51'),
(63, 6, 'Updated report ID 19', '2025-11-19 04:21:30'),
(64, 6, 'Submitted report ID 19 for approval', '2025-11-19 04:22:42'),
(65, 5, 'Approved user ID 14', '2025-11-22 07:00:57'),
(66, 5, 'Created class: class 10 2 (2021-2023)', '2025-11-22 07:33:15'),
(67, 5, 'Assigned teacher ID 14 to class ID 8', '2025-11-22 07:33:25'),
(68, 5, 'Assigned 2 students to class ID 8, teacher ID 14', '2025-11-22 07:33:34'),
(69, 5, 'Approved user ID 9', '2025-11-22 07:39:30'),
(70, 5, 'Approved user ID 8', '2025-11-22 07:39:33'),
(71, 14, 'Uploaded new report: new one', '2025-11-22 08:02:06'),
(72, 14, 'Uploaded new report: new one', '2025-11-22 08:02:30'),
(73, 14, 'Updated report ID 22', '2025-11-22 08:05:47'),
(74, 14, 'Submitted report for approval: new one', '2025-11-22 08:16:22'),
(75, 14, 'Submitted report for approval: new one', '2025-11-22 08:16:28'),
(76, 5, 'Admin System Administrator approved report ID 21', '2025-11-22 08:17:09'),
(77, 5, 'Admin System Administrator rejected report ID 19', '2025-11-22 08:17:27'),
(78, 5, 'Admin System Administrator approved report ID 20', '2025-11-22 08:17:42'),
(79, 14, 'Created report via API: recent', '2025-11-22 08:23:33'),
(80, 14, 'Updated report: recent', '2025-11-22 08:23:51'),
(81, 14, 'Submitted report for approval: recent', '2025-11-22 08:23:55'),
(82, 5, 'Assigned teacher ID 6 to class ID 1', '2025-11-22 08:42:08'),
(83, 5, 'Assigned 2 students to class ID 1, teacher ID 6', '2025-11-22 08:42:13'),
(84, 5, 'Assigned coordinator role to user ID 14 until 2025-11-22T02:40', '2025-11-22 08:43:13'),
(85, 5, 'Extended coordinator access for ID 1 until 2025-11-29T02:40', '2025-11-22 08:43:23'),
(86, 5, 'Extended coordinator access for ID 1 until 2025-11-29T02:40', '2025-11-22 08:44:27'),
(87, 5, 'Deleted class ID 2', '2025-11-22 08:44:39'),
(88, 6, 'Submitted report for approval: anyhtingg', '2025-11-22 08:45:44'),
(89, 5, 'Revoked coordinator access for user ID 14', '2025-11-22 08:59:29'),
(90, 5, 'Assigned coordinator role to user ID 14 until 2025-11-29T09:59', '2025-11-22 08:59:41'),
(91, 5, 'Revoked coordinator access for user ID 14', '2025-11-22 09:01:18'),
(92, 5, 'Assigned coordinator role to user ID 6 until 2025-11-29T10:01', '2025-11-22 09:01:31'),
(93, 5, 'Admin System Administrator approved report ID 22', '2025-11-22 09:30:14'),
(94, 5, 'Admin System Administrator approved report ID 22', '2025-11-22 09:32:51'),
(95, 5, 'Admin System Administrator approved report ID 7', '2025-11-22 09:33:12'),
(96, 14, 'Created report via API: test', '2025-11-22 09:35:10'),
(97, 14, 'Created report: test (Status: draft)', '2025-11-22 12:00:35'),
(98, 14, 'Updated report: test', '2025-11-22 12:02:20'),
(99, 5, 'Admin System Administrator approved report ID 23', '2025-12-12 18:29:20'),
(100, 5, 'Admin System Administrator approved report ID 24', '2025-12-12 18:29:29'),
(101, 5, 'Toggled account for user ID 8 (active)', '2025-12-12 18:30:36');

-- --------------------------------------------------------

--
-- Table structure for table `annual_reports`
--

CREATE TABLE `annual_reports` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `description` longtext DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `status` enum('draft','pending','approved','rejected') DEFAULT 'draft',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `content` longtext DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `submitted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `annual_reports`
--

INSERT INTO `annual_reports` (`id`, `title`, `academic_year`, `description`, `file_path`, `uploaded_by`, `status`, `reviewed_by`, `reviewed_at`, `remarks`, `uploaded_at`, `created_at`, `content`, `updated_at`, `submitted_at`) VALUES
(1, 'project', '2024-2025', '...............', 'uploads/1762964455_mudreka resume.pdf.pdf', 6, 'approved', NULL, NULL, NULL, '2025-11-12 21:50:55', '2025-11-12 16:20:55', NULL, '2025-11-22 08:03:02', NULL),
(3, 'asfdgh', '2024-2026', 'great news about the same thing', '../uploads/1762975132_report.pdf', 6, 'approved', NULL, NULL, NULL, '2025-11-13 00:48:52', '2025-11-12 19:18:52', NULL, '2025-11-22 08:03:02', NULL),
(4, 'asfdgh', '2024-2026', 'great news about the same thing', '../uploads/1762975137_report.pdf', 6, 'rejected', NULL, NULL, NULL, '2025-11-13 00:48:57', '2025-11-12 19:18:57', NULL, '2025-11-22 08:03:02', NULL),
(6, 'report', '2021-2023', 'description', '../uploads/1763107554_Academic Calender AITR Session July-Dec 2023.pdf', 6, 'approved', NULL, NULL, NULL, '2025-11-14 13:35:54', '2025-11-14 08:05:54', NULL, '2025-11-22 08:03:02', NULL),
(7, 'anyhtingg', '2021-2023', 'good one', '', 6, 'approved', NULL, NULL, NULL, '2025-11-15 23:06:50', '2025-11-15 17:36:50', '<p>asadasdasdasddassdasfdfa</p>\\n<p>&lt;?php<br>header(\\\"Access-Control-Allow-Origin: *\\\");<br>header(\\\"Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS\\\");<br>header(\\\"Access-Control-Allow-Headers: Content-Type, Authorization\\\");</p>\\n<p>$conn = new mysqli(\\\"localhost\\\", \\\"root\\\", \\\"\\\", \\\"annual_report_portal\\\");</p>\\n<p>if ($conn-&gt;connect_error) {<br>&nbsp; http_response_code(500);<br>&nbsp; echo json_encode([\\\"status\\\" =&gt; \\\"error\\\", \\\"message\\\" =&gt; \\\"Database connection failed\\\"]);<br>&nbsp; exit();<br>}</p>\\n<p>$conn-&gt;set_charset(\\\"utf8\\\");<br>?&gt;</p>', '2025-11-22 09:33:12', NULL),
(18, 'lookinng', '2010-2023', 'good one', '', 6, 'approved', NULL, NULL, NULL, '2025-11-16 00:37:45', '2025-11-15 19:07:45', '<p>aaaaaaas</p>', '2025-11-22 08:03:02', NULL),
(19, 'new report', '2022-2024', '788', '', 6, 'rejected', NULL, NULL, NULL, '2025-11-19 09:04:55', '2025-11-19 03:34:55', '<table style=\\\"border-collapse: collapse; width: 45.6536%; height: 179.4px;\\\" border=\\\"1\\\"><colgroup><col style=\\\"width: 12.5647%;\\\"><col style=\\\"width: 12.5647%;\\\"><col style=\\\"width: 12.5647%;\\\"><col style=\\\"width: 12.5647%;\\\"><col style=\\\"width: 12.5647%;\\\"><col style=\\\"width: 12.5647%;\\\"><col style=\\\"width: 12.5647%;\\\"><col style=\\\"width: 12.0103%;\\\"></colgroup>\\n<tbody>\\n<tr style=\\\"height: 22.175px;\\\">\\n<td style=\\\"height: 22.175px;\\\">new</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n</tr>\\n<tr style=\\\"height: 24.175px;\\\">\\n<td style=\\\"height: 24.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 24.175px;\\\">new</td>\\n<td style=\\\"height: 24.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 24.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 24.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 24.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 24.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 24.175px;\\\">&nbsp;</td>\\n</tr>\\n<tr style=\\\"height: 22.175px;\\\">\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">new</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n</tr>\\n<tr style=\\\"height: 22.175px;\\\">\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">new</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n</tr>\\n<tr style=\\\"height: 22.175px;\\\">\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">new</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n</tr>\\n<tr style=\\\"height: 22.175px;\\\">\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">new</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n</tr>\\n<tr style=\\\"height: 22.175px;\\\">\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">new</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n</tr>\\n<tr style=\\\"height: 22.175px;\\\">\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">new</td>\\n</tr>\\n</tbody>\\n</table>', '2025-11-22 08:17:27', NULL),
(20, 'new report', '2022-2024', 'new', '', 6, 'approved', NULL, NULL, NULL, '2025-11-19 09:04:59', '2025-11-19 03:34:59', '<table style=\\\"border-collapse: collapse; width: 45.6536%; height: 179.4px;\\\" border=\\\"1\\\"><colgroup><col style=\\\"width: 12.5647%;\\\"><col style=\\\"width: 12.5647%;\\\"><col style=\\\"width: 12.5647%;\\\"><col style=\\\"width: 12.5647%;\\\"><col style=\\\"width: 12.5647%;\\\"><col style=\\\"width: 12.5647%;\\\"><col style=\\\"width: 12.5647%;\\\"><col style=\\\"width: 12.0103%;\\\"></colgroup>\\n<tbody>\\n<tr style=\\\"height: 22.175px;\\\">\\n<td style=\\\"height: 22.175px;\\\">new</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n</tr>\\n<tr style=\\\"height: 24.175px;\\\">\\n<td style=\\\"height: 24.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 24.175px;\\\">new</td>\\n<td style=\\\"height: 24.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 24.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 24.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 24.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 24.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 24.175px;\\\">&nbsp;</td>\\n</tr>\\n<tr style=\\\"height: 22.175px;\\\">\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">new</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n</tr>\\n<tr style=\\\"height: 22.175px;\\\">\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">new</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n</tr>\\n<tr style=\\\"height: 22.175px;\\\">\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">new</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n</tr>\\n<tr style=\\\"height: 22.175px;\\\">\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">new</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n</tr>\\n<tr style=\\\"height: 22.175px;\\\">\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">new</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n</tr>\\n<tr style=\\\"height: 22.175px;\\\">\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">&nbsp;</td>\\n<td style=\\\"height: 22.175px;\\\">new</td>\\n</tr>\\n</tbody>\\n</table>', '2025-11-22 08:17:42', NULL),
(21, 'new one', '2022', 'one to zero', 'uploads/1763798526_users__2_.pdf', 14, 'approved', NULL, NULL, NULL, '2025-11-22 13:32:06', '2025-11-22 08:02:06', NULL, '2025-11-22 08:17:09', NULL),
(22, 'new one', '2022', 'one to zero', 'uploads/1763798550_users__2_.pdf', 14, 'approved', NULL, NULL, NULL, '2025-11-22 13:32:30', '2025-11-22 08:02:30', NULL, '2025-11-22 09:30:14', NULL),
(23, 'recent', '2022-2024', 'now it is good', '', 14, 'approved', NULL, NULL, NULL, '2025-11-22 13:53:33', '2025-11-22 08:23:33', '<table style=\"border-collapse: collapse; width: 99.9831%;\" border=\"1\"><colgroup><col style=\"width: 12.4684%;\"><col style=\"width: 12.4684%;\"><col style=\"width: 12.4684%;\"><col style=\"width: 12.4684%;\"><col style=\"width: 12.4684%;\"><col style=\"width: 12.4684%;\"><col style=\"width: 12.4684%;\"><col style=\"width: 12.4684%;\"></colgroup>\r\n<tbody>\r\n<tr>\r\n<td>dasd</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n</tr>\r\n<tr>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n</tr>\r\n<tr>\r\n<td>asdfs</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n</tr>\r\n<tr>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n</tr>\r\n<tr>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n</tr>\r\n<tr>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n</tr>\r\n</tbody>\r\n</table>', '2025-12-12 18:29:20', NULL),
(24, 'test', '2024-2025', NULL, '', 14, 'approved', NULL, NULL, NULL, '2025-11-22 15:05:10', '2025-11-22 09:35:10', '<table style=\"border-collapse: collapse; width: 99.9831%; height: 156.8px;\" border=\"1\"><colgroup><col style=\"width: 10.0253%;\"><col style=\"width: 10.0253%;\"><col style=\"width: 10.0253%;\"><col style=\"width: 10.0253%;\"><col style=\"width: 10.0253%;\"><col style=\"width: 10.0253%;\"><col style=\"width: 10.0253%;\"><col style=\"width: 10.0253%;\"><col style=\"width: 10.0253%;\"><col style=\"width: 10.0253%;\"></colgroup>\n<tbody>\n<tr style=\"height: 19.6px;\">\n<td style=\"height: 19.6px;\">what&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n</tr>\n<tr style=\"height: 19.6px;\">\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n</tr>\n<tr style=\"height: 19.6px;\">\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n</tr>\n<tr style=\"height: 19.6px;\">\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n</tr>\n<tr style=\"height: 19.6px;\">\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n</tr>\n<tr style=\"height: 19.6px;\">\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n</tr>\n<tr style=\"height: 19.6px;\">\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n</tr>\n<tr style=\"height: 19.6px;\">\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">&nbsp;</td>\n<td style=\"height: 19.6px;\">what</td>\n</tr>\n</tbody>\n</table>', '2025-12-12 18:29:29', NULL),
(25, 'test', '2025-2026', 'new', NULL, 14, 'draft', NULL, NULL, NULL, '2025-11-22 17:30:35', '2025-11-22 12:00:35', '<table style=\"border-collapse: collapse; width: 99.9831%;\" border=\"1\"><colgroup><col style=\"width: 19.9663%;\"><col style=\"width: 19.9663%;\"><col style=\"width: 19.9663%;\"><col style=\"width: 19.9663%;\"><col style=\"width: 19.9663%;\"></colgroup>\r\n<tbody>\r\n<tr>\r\n<td>asds</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n</tr>\r\n<tr>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n</tr>\r\n<tr>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n</tr>\r\n<tr>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n</tr>\r\n<tr>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n<td>&nbsp;</td>\r\n</tr>\r\n</tbody>\r\n</table>', '2025-11-22 12:00:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `approvals`
--

CREATE TABLE `approvals` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` enum('approved','rejected') NOT NULL,
  `comment` text DEFAULT NULL,
  `action_date` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `approvals`
--

INSERT INTO `approvals` (`id`, `report_id`, `admin_id`, `action`, `comment`, `action_date`, `created_at`) VALUES
(1, 1, 5, '', NULL, '2025-11-12 21:51:54', '2025-12-12 18:29:13'),
(2, 1, 5, '', '', '2025-11-12 22:10:55', '2025-12-12 18:29:13'),
(5, 4, 5, '', '', '2025-11-13 01:08:57', '2025-12-12 18:29:13'),
(6, 3, 5, '', '', '2025-11-13 01:08:59', '2025-12-12 18:29:13'),
(7, 6, 5, '', 'need more work', '2025-11-14 13:40:04', '2025-12-12 18:29:13'),
(8, 18, 5, '', 'not looking make it better', '2025-11-19 08:59:56', '2025-12-12 18:29:13'),
(9, 20, 5, '', '', '2025-11-19 09:07:20', '2025-12-12 18:29:13'),
(10, 19, 5, '', '', '2025-11-19 09:07:20', '2025-12-12 18:29:13'),
(11, 4, 5, '', '', '2025-11-19 09:07:22', '2025-12-12 18:29:13'),
(12, 21, 5, '', '', '2025-11-22 13:47:09', '2025-12-12 18:29:13'),
(13, 19, 5, '', 'don\'t know', '2025-11-22 13:47:27', '2025-12-12 18:29:13'),
(14, 20, 5, '', 'don\'t know', '2025-11-22 13:47:42', '2025-12-12 18:29:13'),
(15, 22, 5, '', '', '2025-11-22 15:00:14', '2025-12-12 18:29:13'),
(16, 22, 5, '', '', '2025-11-22 15:02:51', '2025-12-12 18:29:13'),
(17, 7, 5, '', 'file is missing', '2025-11-22 15:03:12', '2025-12-12 18:29:13'),
(18, 23, 5, '', '', '2025-12-12 23:59:20', '2025-12-12 18:29:20'),
(19, 24, 5, '', '', '2025-12-12 23:59:29', '2025-12-12 18:29:29');

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `class_name` varchar(100) NOT NULL,
  `grade` int(11) NOT NULL,
  `section` varchar(10) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT '2024-2025',
  `room_number` varchar(20) DEFAULT NULL,
  `max_students` int(11) DEFAULT 40,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `class_name`, `grade`, `section`, `teacher_id`, `academic_year`, `room_number`, `max_students`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Grade 1 - Section A', 1, 'A', 6, '2024-2025', 'R-1A', 40, 5, '2025-11-22 07:10:48', '2025-11-22 07:10:48'),
(3, 'Grade 2 - Section A', 2, 'A', 6, '2024-2025', 'R-2A', 40, 5, '2025-11-22 07:10:48', '2025-11-22 07:10:48'),
(4, 'Grade 2 - Section B', 2, 'B', 6, '2024-2025', 'R-2B', 40, 5, '2025-11-22 07:10:48', '2025-11-22 07:10:48'),
(5, 'Grade 3 - Section A', 3, 'A', 6, '2024-2025', 'R-3A', 40, 5, '2025-11-22 07:10:48', '2025-11-22 07:10:48'),
(8, 'class 10', 0, '2', NULL, '2021-2023', NULL, 40, 5, '2025-11-22 07:33:15', '2025-11-22 07:33:15');

-- --------------------------------------------------------

--
-- Stand-in structure for view `class_overview`
-- (See below for the actual view)
--
CREATE TABLE `class_overview` (
`id` int(11)
,`class_name` varchar(100)
,`grade` int(11)
,`section` varchar(10)
,`room_number` varchar(20)
,`academic_year` varchar(20)
,`teacher_name` varchar(100)
,`teacher_email` varchar(100)
,`student_count` bigint(21)
,`max_students` int(11)
,`active_students` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `class_students`
--

CREATE TABLE `class_students` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `enrollment_date` date DEFAULT curdate(),
  `status` enum('active','transferred','dropped') DEFAULT 'active',
  `added_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `class_teachers`
--

CREATE TABLE `class_teachers` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class_teachers`
--

INSERT INTO `class_teachers` (`id`, `class_id`, `teacher_id`, `assigned_at`) VALUES
(1, 8, 14, '2025-11-22 07:33:25'),
(2, 1, 6, '2025-11-22 08:42:08');

-- --------------------------------------------------------

--
-- Table structure for table `coordinators`
--

CREATE TABLE `coordinators` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `access_start` datetime NOT NULL,
  `access_end` datetime NOT NULL,
  `permissions` varchar(255) DEFAULT 'approve_reports',
  `status` enum('active','revoked','expired') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `coordinators`
--

INSERT INTO `coordinators` (`id`, `user_id`, `access_start`, `access_end`, `permissions`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 14, '2025-11-22 09:59:00', '2025-11-29 09:59:00', 'approve_reports,view_analytics,view_users', 'revoked', 5, '2025-11-22 08:43:13', '2025-11-22 09:01:18'),
(2, 6, '2025-11-22 10:01:00', '2025-11-29 10:01:00', 'approve_reports,view_analytics,view_users', 'active', 5, '2025-11-22 09:01:31', '2025-11-22 09:01:31');

-- --------------------------------------------------------

--
-- Table structure for table `coordinator_access`
--

CREATE TABLE `coordinator_access` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `granted_by` int(11) NOT NULL,
  `can_approve_reports` tinyint(1) DEFAULT 1,
  `can_view_all_reports` tinyint(1) DEFAULT 1,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coordinator_audit_log`
--

CREATE TABLE `coordinator_audit_log` (
  `id` int(11) NOT NULL,
  `coordinator_id` int(11) NOT NULL,
  `action_type` enum('report_approved','report_rejected','report_viewed','access_granted','access_revoked') NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `is_read`, `created_at`) VALUES
(1, 1, '📄 New report submitted by Teacher ID 6: asfdgh', 1, '2025-11-13 00:53:56'),
(2, 1, '📄 New report submitted by Teacher ID 6: report', 1, '2025-11-14 13:35:54'),
(3, 7, '✅ Your account has been approved by the Admin.', 0, '2025-11-14 13:38:35'),
(4, 1, '📄 A new report (ID: 9) was submitted for approval by Teacher ID 6.', 1, '2025-11-15 23:07:08'),
(5, 1, '📄 A new report (ID: 7) was submitted for approval by Teacher ID 6.', 1, '2025-11-15 23:07:15'),
(6, 1, '📄 A new report (ID: 10) was submitted for approval by Teacher ID 6.', 1, '2025-11-15 23:29:03'),
(7, 1, '📄 A new report (ID: 15) was submitted for approval by Teacher ID 6.', 1, '2025-11-15 23:59:35'),
(8, 1, '📄 A new report (ID: 18) was submitted for approval by Teacher ID 6.', 1, '2025-11-16 00:37:51'),
(9, 1, '📄 A new report (ID: 18) was submitted for approval by Teacher ID 6.', 1, '2025-11-16 00:40:31'),
(10, 13, '✅ Your account has been approved by the Admin.', 0, '2025-11-19 08:57:09'),
(11, 1, '📄 A new report (ID: 19) was submitted for approval by Teacher ID 6.', 1, '2025-11-19 09:05:38'),
(12, 1, '📄 A new report (ID: 4) was submitted for approval by Teacher ID 6.', 1, '2025-11-19 09:05:49'),
(13, 11, '✅ Your account has been approved by the Admin.', 0, '2025-11-19 09:47:21'),
(14, 1, '📄 A new report (ID: 20) was submitted for approval by Teacher ID 6.', 1, '2025-11-19 09:49:51'),
(15, 1, '📄 A new report (ID: 19) was submitted for approval by Teacher ID 6.', 1, '2025-11-19 09:52:42'),
(16, 14, '✅ Your account has been approved by the Admin.', 1, '2025-11-22 12:30:57'),
(17, 9, '✅ Your account has been approved by the Admin.', 0, '2025-11-22 13:09:30'),
(18, 8, '✅ Your account has been approved by the Admin.', 0, '2025-11-22 13:09:33'),
(19, 1, '📄 New report submitted by Teacher ID 14: new one', 1, '2025-11-22 13:32:06'),
(20, 1, '📄 New report submitted by Teacher ID 14: new one', 1, '2025-11-22 13:32:30'),
(21, 1, '📄 New report submitted by Mudreka Sabir: new one', 1, '2025-11-22 13:46:22'),
(22, 1, '📄 New report submitted by Mudreka Sabir: new one', 1, '2025-11-22 13:46:28'),
(23, 14, '✅ Your report \'new one\' has been approved!', 1, '2025-11-22 13:47:09'),
(24, 6, '❌ Your report \'new report\' was rejected. Reason: don\'t know', 1, '2025-11-22 13:47:27'),
(25, 6, '✅ Your report \'new report\' has been approved!', 1, '2025-11-22 13:47:42'),
(26, 1, '📄 New report submitted by Mudreka Sabir: recent', 1, '2025-11-22 13:53:55'),
(27, 14, '🎉 You have been assigned as Coordinator until 22 Nov 2025', 1, '2025-11-22 14:13:13'),
(28, 14, '✅ Your coordinator access extended until 29 Nov 2025', 1, '2025-11-22 14:13:23'),
(29, 14, '✅ Your coordinator access extended until 29 Nov 2025', 1, '2025-11-22 14:14:27'),
(30, 1, '📄 New report submitted by Mudreka Sabir: anyhtingg', 1, '2025-11-22 14:15:44'),
(31, 14, '⚠️ Your coordinator access has been revoked.', 1, '2025-11-22 14:29:29'),
(32, 14, '🎉 You have been assigned as Coordinator until 29 Nov 2025', 1, '2025-11-22 14:29:41'),
(33, 14, '⚠️ Your coordinator access has been revoked.', 1, '2025-11-22 14:31:18'),
(34, 6, '🎉 You have been assigned as Coordinator until 29 Nov 2025', 1, '2025-11-22 14:31:31'),
(35, 14, '✅ Your report \'new one\' has been approved!', 1, '2025-11-22 15:00:14'),
(36, 14, '✅ Your report \'new one\' has been approved!', 1, '2025-11-22 15:02:51'),
(37, 6, '✅ Your report \'anyhtingg\' has been approved!', 1, '2025-11-22 15:03:12'),
(38, 1, '📄 New report submitted by Teacher ID 14: test', 1, '2025-11-22 15:05:10'),
(39, 14, '✅ Your report \'recent\' has been approved!', 0, '2025-12-12 23:59:20'),
(40, 14, '✅ Your report \'test\' has been approved!', 0, '2025-12-12 23:59:29');

-- --------------------------------------------------------

--
-- Table structure for table `report_versions`
--

CREATE TABLE `report_versions` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `version_no` int(11) NOT NULL,
  `description` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_assignments`
--

CREATE TABLE `student_assignments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_assignments`
--

INSERT INTO `student_assignments` (`id`, `student_id`, `teacher_id`, `class_id`, `assigned_by`, `assigned_at`) VALUES
(1, 1, 14, 8, 5, '2025-11-22 07:33:34'),
(2, 10, 14, 8, 5, '2025-11-22 07:33:34'),
(4, 15, 6, 1, 5, '2025-11-22 08:42:13'),
(5, 10, 6, 1, 5, '2025-11-22 08:42:13');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','teacher','student','coordinator') NOT NULL DEFAULT 'student',
  `approval_status` enum('pending','approved','rejected') DEFAULT 'approved',
  `created_at` datetime DEFAULT current_timestamp(),
  `account_status` enum('active','disabled') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `approval_status`, `created_at`, `account_status`, `last_login`, `updated_at`) VALUES
(1, 'Mudreka Sabir', 'mudrekasabir8@gmail.com', '$2y$10$9RtzQGpvz1Jav7N0jqEPc.mz17pUNA0WOZw1ICts1umum1vfr4/re', 'student', 'approved', '2025-11-12 21:04:13', 'active', '2025-12-13 20:45:07', '2025-12-13 15:15:07'),
(5, 'System Administrator', 'admin@example.com', '$2y$10$m5mvd/3FtjrsHy6PiWS8zOmiR8.ESstNMspbpzuQN00iiLnERCMmq', 'admin', 'approved', '2025-11-12 21:31:17', 'active', '2025-12-13 19:51:44', '2025-12-13 14:21:44'),
(6, 'Mudreka Sabir', 'mudrekasabir@gmail.com', '$2y$10$Yq48UJX7aok.Eb5U1Y8K.OHqZ/PNWMYVjZ49tKU3HRqAt./gvpcjK', 'teacher', 'approved', '2025-11-12 21:47:23', 'active', '2025-12-13 20:45:49', '2025-12-13 15:15:49'),
(7, 'kshitij sharma', '123@gmail.com', '$2y$10$w6DWi1WMTW3O3wnG6kQjlu2HZfSzTW1B4IkucNiNfoQn6agm0veZ6', 'teacher', 'approved', '2025-11-13 01:16:12', 'active', '2025-12-12 23:47:58', '2025-12-12 18:35:46'),
(8, 'mohini pandey', '2@gmail.com', '$2y$10$aeXdBoDtjNjklR7rhRE4q.qBwYdKw5rxinj1Yq4PnwtH9iKjvLrhq', 'teacher', 'approved', '2025-11-13 01:16:26', 'active', NULL, '2025-12-12 18:35:46'),
(9, 'mohini pandey', 'mohini@gmail.com', '$2y$10$PbNUorxXxQlrWwGTz708aeOGHbXPOaj6seR4ZCO6aIrCz9PWkGv8S', 'teacher', 'approved', '2025-11-13 10:43:03', 'active', NULL, '2025-12-12 18:35:46'),
(10, 'palakpaneer', 'paneer@gmail.com', '$2y$10$EDDtPs80yW5mfTn0OgTqLOmPGrJy2ZDxEBxv81aZWje6MYYtgepze', 'student', 'approved', '2025-11-13 10:43:36', 'active', NULL, '2025-12-12 18:35:46'),
(11, 'mohini', '999@gmail.com', '$2y$10$RuT.OjMuNyq.WYvWZkcT/uU9ehDgjTDevlh9VV9Yyq0O4GUc4vIIK', 'teacher', 'approved', '2025-11-17 13:49:33', 'active', NULL, '2025-12-12 18:35:46'),
(12, 'pahal ', 'pahalpunjabi230424@acropolis.in', '$2y$10$iz.szslArMOVRemqC6ONPOtJm/y9XonMMcNUfOzajTbX6Mb6QH5r6', 'student', 'approved', '2025-11-17 13:58:53', 'active', NULL, '2025-12-12 18:35:46'),
(13, 'krishna', 'krishna@gmail.com', '$2y$10$b8euD/3FX.bbR0cBRJvo7ew216kIUq5xilc/kBB.gPk1JR9mbOn2a', 'teacher', 'approved', '2025-11-19 08:56:25', 'active', NULL, '2025-12-12 18:35:46'),
(14, 'Mudreka Sabir', '1234@gmail.com', '$2y$10$x2RSJa6p06tpdos10cNVTegOfjJgn9s6vDGJcknxsUVYdht/48jVy', 'teacher', 'approved', '2025-11-22 12:26:32', 'active', '2025-11-22 16:55:47', '2025-12-12 18:35:46'),
(15, 'Mudreka Sabir', '0@gmail.com', '$2y$10$b302lqQp8ShDxtIvfR1GF.maQ5InVpYz9WgTyQ98Je/v2c7032l8i', 'student', 'approved', '2025-11-22 14:03:06', 'active', '2025-11-22 14:49:31', '2025-12-12 18:35:46');

-- --------------------------------------------------------

--
-- Structure for view `active_coordinators_view`
--
DROP TABLE IF EXISTS `active_coordinators_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_coordinators_view`  AS SELECT `u`.`id` AS `id`, `u`.`name` AS `name`, `u`.`email` AS `email`, `ca`.`can_approve_reports` AS `can_approve_reports`, `ca`.`can_view_all_reports` AS `can_view_all_reports`, `ca`.`expires_at` AS `expires_at`, `ca`.`created_at` AS `access_granted_at`, timestampdiff(HOUR,current_timestamp(),`ca`.`expires_at`) AS `hours_remaining`, `granted`.`name` AS `granted_by_name` FROM ((`users` `u` join `coordinator_access` `ca` on(`u`.`id` = `ca`.`user_id`)) left join `users` `granted` on(`ca`.`granted_by` = `granted`.`id`)) WHERE `u`.`role` = 'coordinator' AND `ca`.`expires_at` > current_timestamp() ORDER BY `ca`.`expires_at` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `class_overview`
--
DROP TABLE IF EXISTS `class_overview`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `class_overview`  AS SELECT `c`.`id` AS `id`, `c`.`class_name` AS `class_name`, `c`.`grade` AS `grade`, `c`.`section` AS `section`, `c`.`room_number` AS `room_number`, `c`.`academic_year` AS `academic_year`, `t`.`name` AS `teacher_name`, `t`.`email` AS `teacher_email`, count(distinct `cs`.`student_id`) AS `student_count`, `c`.`max_students` AS `max_students`, count(distinct case when `cs`.`status` = 'active' then `cs`.`student_id` end) AS `active_students` FROM ((`classes` `c` left join `users` `t` on(`c`.`teacher_id` = `t`.`id`)) left join `class_students` `cs` on(`c`.`id` = `cs`.`class_id`)) GROUP BY `c`.`id` ORDER BY `c`.`grade` ASC, `c`.`section` ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_user_date` (`user_id`,`created_at`);

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `annual_reports`
--
ALTER TABLE `annual_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `idx_reports_status` (`status`);

--
-- Indexes for table `approvals`
--
ALTER TABLE `approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_class` (`grade`,`section`,`academic_year`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_teacher` (`teacher_id`),
  ADD KEY `idx_grade` (`grade`);

--
-- Indexes for table `class_students`
--
ALTER TABLE `class_students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment` (`class_id`,`student_id`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `idx_class` (`class_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `class_teachers`
--
ALTER TABLE `class_teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_class_teacher` (`class_id`,`teacher_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `coordinators`
--
ALTER TABLE `coordinators`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_access_period` (`access_start`,`access_end`),
  ADD KEY `fk_coord_creator` (`created_by`);

--
-- Indexes for table `coordinator_access`
--
ALTER TABLE `coordinator_access`
  ADD PRIMARY KEY (`id`),
  ADD KEY `granted_by` (`granted_by`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `idx_user_active` (`user_id`,`expires_at`);

--
-- Indexes for table `coordinator_audit_log`
--
ALTER TABLE `coordinator_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_coordinator` (`coordinator_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `report_versions`
--
ALTER TABLE `report_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `student_assignments`
--
ALTER TABLE `student_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_class` (`student_id`,`class_id`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `idx_teacher` (`teacher_id`),
  ADD KEY `idx_class` (`class_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `email_2` (`email`),
  ADD KEY `idx_users_role_approved` (`role`,`approval_status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT for table `annual_reports`
--
ALTER TABLE `annual_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `approvals`
--
ALTER TABLE `approvals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `class_students`
--
ALTER TABLE `class_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `class_teachers`
--
ALTER TABLE `class_teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `coordinators`
--
ALTER TABLE `coordinators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `coordinator_access`
--
ALTER TABLE `coordinator_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `coordinator_audit_log`
--
ALTER TABLE `coordinator_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `report_versions`
--
ALTER TABLE `report_versions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_assignments`
--
ALTER TABLE `student_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `annual_reports`
--
ALTER TABLE `annual_reports`
  ADD CONSTRAINT `annual_reports_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `approvals`
--
ALTER TABLE `approvals`
  ADD CONSTRAINT `approvals_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `annual_reports` (`id`),
  ADD CONSTRAINT `approvals_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `classes_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `class_students`
--
ALTER TABLE `class_students`
  ADD CONSTRAINT `class_students_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_students_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_students_ibfk_3` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `class_teachers`
--
ALTER TABLE `class_teachers`
  ADD CONSTRAINT `class_teachers_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_teachers_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `coordinators`
--
ALTER TABLE `coordinators`
  ADD CONSTRAINT `fk_coord_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_coord_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `coordinator_access`
--
ALTER TABLE `coordinator_access`
  ADD CONSTRAINT `coordinator_access_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `coordinator_access_ibfk_2` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `coordinator_audit_log`
--
ALTER TABLE `coordinator_audit_log`
  ADD CONSTRAINT `coordinator_audit_log_ibfk_1` FOREIGN KEY (`coordinator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `report_versions`
--
ALTER TABLE `report_versions`
  ADD CONSTRAINT `report_versions_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `annual_reports` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_assignments`
--
ALTER TABLE `student_assignments`
  ADD CONSTRAINT `student_assignments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_assignments_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_assignments_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_assignments_ibfk_4` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
