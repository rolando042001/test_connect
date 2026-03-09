-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 09, 2026 at 04:55 AM
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
-- Database: `test_connect`
--

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
(1, 0, 0);

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
  `passcode` varchar(8) DEFAULT NULL,
  `rfid_uid` varchar(20) DEFAULT NULL,
  `has_passcode` tinyint(1) NOT NULL DEFAULT 0,
  `has_rfid` tinyint(1) NOT NULL DEFAULT 0,
  `has_fingerprint` tinyint(1) NOT NULL DEFAULT 0
) ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `passcode`, `rfid_uid`, `has_passcode`, `has_rfid`, `has_fingerprint`) VALUES
(1, '98765432', '080D5616', 1, 1, 1),
(2, '14725836', '134FB34C', 1, 1, 1),
(3, '12334369', '2BD8F64C', 1, 1, 1),
(4, '15926378', '2BD8F64C', 1, 1, 1);

--
-- Indexes for dumped tables
--

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
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `relay`
--
ALTER TABLE `relay`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
