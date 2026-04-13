-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 12, 2026 at 10:53 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `revibe`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'john_doe', 'john@example.com', '$2y$10$examplehashedpassword123', 'user', 1, '2026-04-10 17:37:42', '2026-04-10 17:37:42'),
(2, 'jane_seller', 'jane@example.com', '$2y$10$examplehashedpassword456', 'user', 1, '2026-04-10 17:37:42', '2026-04-10 17:37:42'),
(3, 'Ali', 'ali@mail.com', 'dummy', 'user', 1, '2026-04-11 13:45:31', '2026-04-11 13:45:31'),
(4, 'Sara', 'sara@mail.com', 'dummy', 'user', 1, '2026-04-11 13:45:31', '2026-04-11 13:45:31'),
(5, 'Maya', 'maya@mail.com', 'dummy', 'user', 1, '2026-04-11 13:45:31', '2026-04-11 13:45:31'),
(6, 'Hafiz', 'hafiz@mail.com', 'dummy', 'user', 1, '2026-04-11 13:45:31', '2026-04-11 13:45:31'),
(7, 'Priya', 'priya@mail.com', 'dummy', 'user', 1, '2026-04-11 13:45:31', '2026-04-11 13:45:31'),
(8, 'Wei Lun', 'weilun@mail.com', 'dummy', 'user', 1, '2026-04-11 13:45:31', '2026-04-11 13:45:31'),
(9, 'Nivi', 'nivi@mail.com', 'dummy', 'user', 1, '2026-04-11 13:45:31', '2026-04-11 13:45:31'),
(10, 'admin', 'admin@revibe.com', 'admin123456', 'admin', 1, '2026-04-12 20:36:41', '2026-04-12 20:38:15');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
