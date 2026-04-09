-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 07, 2026 at 05:23 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `eu_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `access_requests`
--

CREATE TABLE `access_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `dept_id` int(11) NOT NULL,
  `file_id` int(11) DEFAULT NULL,
  `target_file_name` varchar(255) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','denied') DEFAULT 'pending',
  `request_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `access_requests`
--

INSERT INTO `access_requests` (`id`, `user_id`, `dept_id`, `file_id`, `target_file_name`, `reason`, `status`, `request_date`) VALUES
(1, 14, 2, NULL, 'adasas', 'das', 'approved', '2026-03-10 16:43:47'),
(2, 14, 3, NULL, '65-ONLINE-EVENTS-MANAGEMENT-SYSTEM-WITH-PRICE-QUOTATION-FOR-ARIELS-CATERING-SERVICES.docx', 'asdas', 'approved', '2026-03-10 17:21:07');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `item_type` varchar(50) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `item_name`, `item_type`, `timestamp`) VALUES
(1, NULL, 'Registration', 'janrelzac0130@gmail.com', 'User', '2026-03-02 09:20:54'),
(2, NULL, 'Login', 'janrelzac0130@gmail.com', 'Session', '2026-03-02 09:20:56'),
(3, NULL, 'Registration', 'vsatisfying30@gmail.com', 'User', '2026-03-02 09:24:14'),
(4, NULL, 'Login', 'vsatisfying30@gmail.com', 'Session', '2026-03-02 09:24:21'),
(5, NULL, 'Login', 'janrelzac0130@gmail.com', 'Session', '2026-03-02 13:56:58'),
(6, NULL, 'Login', 'janrelzac0130@gmail.com', 'Session', '2026-03-04 14:22:15'),
(10, NULL, 'Login', 'vsatisfying30@gmail.com', 'Session', '2026-03-04 16:25:28'),
(11, NULL, 'Login', 'janrelzac0130@gmail.com', 'Session', '2026-03-05 05:37:19'),
(12, NULL, 'Update Staff Profile', 'Zac Janrel', 'User Management', '2026-03-05 16:04:10'),
(13, NULL, 'Update Staff Profile', 'Zac Janrel', 'User Management', '2026-03-05 16:05:25'),
(14, NULL, 'Update Staff Profile', ' ', 'User Management', '2026-03-05 16:24:55'),
(15, NULL, 'Update Staff Profile', 'Zac Janrel', 'User Management', '2026-03-05 16:36:49'),
(16, NULL, 'Update Staff Profile', 'Zac Janrel', 'User Management', '2026-03-05 16:38:06'),
(17, NULL, 'Login', 'philippAnne@GMAIL.COM', 'Session', '2026-03-05 16:41:40'),
(24, NULL, 'Login', 'janrelzac0130@gmail.com', 'Session', '2026-03-06 16:26:44'),
(78, 14, 'Registration', 'vsatisfying30@gmail.com', 'User', '2026-03-08 15:25:35'),
(79, 14, 'Login', 'vsatisfying30@gmail.com', 'Session', '2026-03-08 15:25:37'),
(80, 14, 'Update Staff Profile', 'Zac Chavez (ID: 13)', 'User Management', '2026-03-08 15:27:09'),
(81, 14, 'Login', 'vsatisfying30@gmail.com', 'Session', '2026-03-08 15:33:52'),
(82, NULL, 'Login', 'JAdada@mGmail.com', 'Session', '2026-03-08 15:40:18'),
(83, NULL, 'Login', 'JAdada@mGmail.com', 'Session', '2026-03-08 15:40:49'),
(84, NULL, 'Update Staff Profile', 'ZAC Zac (ID: 15)', 'User Management', '2026-03-08 15:44:01'),
(85, 14, 'Login', 'vsatisfying30@gmail.com', 'Session', '2026-03-09 06:05:18'),
(86, 14, 'Login', 'vsatisfying30@gmail.com', 'Session', '2026-03-10 11:19:32'),
(87, 14, 'Login', 'vsatisfying30@gmail.com', 'Session', '2026-03-10 16:20:33'),
(88, 14, 'Delete Personnel', 'User ID: 12', 'User Management', '2026-03-11 02:04:10'),
(89, 17, 'Login', 'nituragian2@gmail.com', 'Session', '2026-03-11 02:40:55'),
(90, 14, 'Login', 'vsatisfying30@gmail.com', 'Session', '2026-03-28 17:40:48'),
(91, 14, 'Login', 'vsatisfying30@gmail.com', 'Session', '2026-03-28 17:43:39'),
(92, 14, 'Update Staff Profile', 'Satis Vit (ID: 14)', 'User Management', '2026-03-28 18:04:49'),
(93, 14, 'Update Staff Profile', 'Satis Viy (ID: 14)', 'User Management', '2026-03-28 18:05:17'),
(94, 14, 'Delete Personnel', 'User ID: 15', 'User Management', '2026-03-28 18:11:49'),
(95, 14, 'Delete Personnel', 'User ID: 19', 'User Management', '2026-03-28 18:16:59'),
(96, 14, 'Delete Personnel', 'User ID: 18', 'User Management', '2026-03-28 18:17:04'),
(97, 14, 'Delete Personnel', 'User ID: 16', 'User Management', '2026-03-29 08:21:05'),
(98, 14, 'Update Staff Profile', 'Zac Chavez (ID: 13)', 'User Management', '2026-04-03 08:01:08'),
(99, 14, 'Update Staff Profile', 'Zac Chavez (ID: 13)', 'User Management', '2026-04-03 08:01:15'),
(100, 14, 'Update Staff Profile', 'aZAC CHAVEZ (ID: 13)', 'User Management', '2026-04-03 08:33:44'),
(101, 14, 'Update Staff Profile', 'ZAc CHAVEZ (ID: 13)', 'User Management', '2026-04-03 08:33:59'),
(102, 14, 'Update Staff Profile', 'Zac Chavez (ID: 13)', 'User Management', '2026-04-03 08:38:04'),
(103, 14, 'Update Staff Profile', 'Zac Chavez (ID: 13)', 'User Management', '2026-04-03 08:43:27');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `created_at`) VALUES
(1, 'Human Resources Dept.', '2026-03-02 09:12:17'),
(2, 'Collection/Finance Dept.', '2026-03-02 09:12:17'),
(3, 'Accounting Dept.', '2026-03-02 09:12:17'),
(4, 'Marketing Dept.', '2026-03-02 09:12:17'),
(5, 'Documentation Dept', '2026-03-02 09:12:17'),
(6, 'Filing Dept.', '2026-03-02 09:12:17');

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `id` int(11) NOT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `folder_id` int(11) DEFAULT NULL,
  `uploader_id` int(11) DEFAULT NULL,
  `display_name` varchar(255) NOT NULL,
  `storage_name` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_trash` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `archived_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `files`
--

INSERT INTO `files` (`id`, `dept_id`, `user_id`, `folder_id`, `uploader_id`, `display_name`, `storage_name`, `file_type`, `file_size`, `uploaded_at`, `is_trash`, `deleted_at`, `is_archived`, `archived_at`) VALUES
(61, 3, NULL, NULL, NULL, '65-ONLINE-EVENTS-MANAGEMENT-SYSTEM-WITH-PRICE-QUOTATION-FOR-ARIELS-CATERING-SERVICES.pdf', '1772993669_69adbc853bcf0.pdf', 'pdf', 3618492, '2026-03-08 18:14:29', 1, NULL, 0, NULL),
(64, NULL, 999, 60, NULL, 'Trash.html', 'priv_69ae7c143d828_1773042708.html', NULL, 35165, '2026-03-09 07:51:48', 0, NULL, 0, NULL),
(67, 4, NULL, NULL, 14, '513617611_30741501948768297_6380179116711553855_n.jpg', '1774773271_69c8e41764435.jpg', 'jpg', 50786, '2026-03-29 08:34:31', 1, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `folders`
--

CREATE TABLE `folders` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_trash` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `archived_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `folders`
--

INSERT INTO `folders` (`id`, `name`, `parent_id`, `dept_id`, `user_id`, `created_by`, `created_at`, `is_trash`, `deleted_at`, `is_archived`, `archived_at`) VALUES
(38, 'ASDASDA', NULL, 3, NULL, NULL, '2026-03-08 13:41:43', 1, NULL, 0, NULL),
(41, 'dadaadsadaada', NULL, 3, NULL, NULL, '2026-03-08 15:34:12', 1, NULL, 0, NULL),
(45, 'asfds', NULL, 3, NULL, NULL, '2026-03-08 17:47:25', 1, NULL, 0, NULL),
(47, 'ASDASDA', NULL, 5, NULL, NULL, '2026-03-08 17:57:27', 0, NULL, 0, NULL),
(48, 'asdasda', NULL, 3, NULL, NULL, '2026-03-08 17:57:40', 1, NULL, 0, NULL),
(49, 'asdasdaasd', 48, 3, NULL, NULL, '2026-03-08 17:57:46', 0, NULL, 0, NULL),
(52, 'asdasdaasd', NULL, 3, NULL, NULL, '2026-03-08 18:18:42', 1, NULL, 0, NULL),
(53, 'sdfs', 52, 3, NULL, NULL, '2026-03-08 18:18:48', 0, NULL, 0, NULL),
(55, 'sdfssadas', NULL, 3, NULL, NULL, '2026-03-08 18:19:22', 1, NULL, 0, NULL),
(60, 'MEME', NULL, NULL, 999, NULL, '2026-03-09 07:50:31', 0, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `register_state`
--

CREATE TABLE `register_state` (
  `id` int(11) NOT NULL,
  `step` int(11) DEFAULT 0,
  `active` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `register_state`
--

INSERT INTO `register_state` (`id`, `step`, `active`) VALUES
(1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `relay`
--

CREATE TABLE `relay` (
  `id` int(11) NOT NULL,
  `relay_in` int(11) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `relay`
--

INSERT INTO `relay` (`id`, `relay_in`, `active`) VALUES
(1, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `passcode` varchar(255) DEFAULT NULL,
  `has_passcode` tinyint(1) DEFAULT 0,
  `rfid_uid` varchar(100) DEFAULT NULL,
  `fingerprint_template` blob DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `role` enum('Admin','Staff') DEFAULT 'Staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Active','Archived') DEFAULT 'Active',
  `has_rfid` tinyint(1) DEFAULT 0,
  `has_fingerprint` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `dept_id`, `email`, `password`, `approval_status`, `passcode`, `has_passcode`, `rfid_uid`, `fingerprint_template`, `profile_picture`, `role`, `created_at`, `status`, `has_rfid`, `has_fingerprint`) VALUES
(13, 'Zac', 'Chavez', 2, 'janrelzac0130@gmail.com', '', 'pending', '$2y$10$dkD.uMli6w9CxKFOBv7TnOSJe.bgdmSkNgnc.ndqr8vFrhNB4jawq', 1, NULL, NULL, '../uploads/profiles/1772969522_2x2.png', 'Staff', '2026-03-08 11:32:02', 'Active', 0, 0),
(14, 'Satis', 'Viy', 5, 'vsatisfying30@gmail.com', '$2y$10$kWd4bsm0lRerY0x4LT1J8uribevtg/snvB2ybyazeI1IWZuzrHYiW', 'pending', NULL, 0, NULL, NULL, '../uploads/profiles/1774721089_7com.jpeg', 'Staff', '2026-03-08 15:25:35', 'Active', 0, 0),
(17, 'Gian', 'Nitura', 4, 'nituragian2@gmail.com', '$2y$10$e7sXpoHN/a.jhe5TM8CdLukw7tKgNRme3CcaAJuCw99FEzHVzStNq', 'pending', NULL, 0, NULL, NULL, '../uploads/profiles/1773196819_b0deffd6-54af-4f2e-8ff4-c44fb2f64558.jpeg', 'Staff', '2026-03-11 02:40:19', 'Active', 0, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `access_requests`
--
ALTER TABLE `access_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `dept_id` (`dept_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `folder_id` (`folder_id`),
  ADD KEY `uploader_id` (`uploader_id`);

--
-- Indexes for table `folders`
--
ALTER TABLE `folders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dept_id` (`dept_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `folders_ibfk_1` (`parent_id`);

--
-- Indexes for table `register_state`
--
ALTER TABLE `register_state`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `relay`
--
ALTER TABLE `relay`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `dept_id` (`dept_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `access_requests`
--
ALTER TABLE `access_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `folders`
--
ALTER TABLE `folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `relay`
--
ALTER TABLE `relay`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `access_requests`
--
ALTER TABLE `access_requests`
  ADD CONSTRAINT `access_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `access_requests_ibfk_2` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_ibfk_2` FOREIGN KEY (`uploader_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `folders`
--
ALTER TABLE `folders`
  ADD CONSTRAINT `folders_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `folders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `folders_ibfk_2` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folders_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
