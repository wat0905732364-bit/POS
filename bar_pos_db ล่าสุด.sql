-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jun 15, 2026 at 05:25 PM
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
-- Database: `bar_pos_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(11) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `points` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `phone`, `name`, `points`, `created_at`) VALUES
(1, '0993157603', 'ธนวัฒน์ ทิพย์กองลาศ', 459, '2026-06-12 15:43:41');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `receipt_no` varchar(20) DEFAULT NULL,
  `table_number` varchar(10) DEFAULT NULL,
  `cashier_name` varchar(100) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `promo_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `points_earned` int(11) DEFAULT 0,
  `points_used` int(11) DEFAULT 0,
  `is_percent` tinyint(1) DEFAULT 1,
  `status` enum('active','paid','voided') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `apply_tax` tinyint(1) DEFAULT 0,
  `apply_sc` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `receipt_no`, `table_number`, `cashier_name`, `member_id`, `total_amount`, `discount_amount`, `promo_amount`, `points_earned`, `points_used`, `is_percent`, `status`, `created_at`, `apply_tax`, `apply_sc`) VALUES
(8, NULL, 'A1', NULL, NULL, 517.88, 0.00, 0.00, 0, 0, 1, 'paid', '2026-05-31 16:35:57', 0, 0),
(9, NULL, 'A1', NULL, NULL, 1918.51, 0.00, 0.00, 0, 0, 1, 'paid', '2026-05-31 16:36:11', 0, 0),
(10, NULL, 'A2', NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 1, 'voided', '2026-05-31 16:48:41', 0, 0),
(11, NULL, 'A1', NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 1, 'voided', '2026-05-31 16:58:27', 0, 0),
(12, NULL, 'A3', NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 1, 'voided', '2026-05-31 16:58:49', 0, 0),
(13, NULL, 'A1', NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 1, 'voided', '2026-05-31 16:59:00', 0, 0),
(14, NULL, 'A1', NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 1, 'voided', '2026-05-31 17:03:20', 0, 0),
(15, NULL, 'A1', NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 1, 'voided', '2026-05-31 17:03:30', 0, 0),
(16, NULL, 'A1', NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 1, 'voided', '2026-06-02 15:13:26', 0, 0),
(17, NULL, 'A5', NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 1, 'voided', '2026-06-02 15:30:47', 0, 0),
(18, NULL, 'A4', NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 1, 'voided', '2026-06-02 15:36:03', 0, 0),
(19, NULL, 'A6', NULL, NULL, 522.00, 10.00, 0.00, 0, 0, 1, 'paid', '2026-06-05 15:35:09', 0, 0),
(20, NULL, 'A6', NULL, NULL, 918.06, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-05 15:38:59', 1, 1),
(21, NULL, 'A3', NULL, NULL, 847.44, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-07 15:25:46', 1, 1),
(22, NULL, 'A8', NULL, NULL, 1023.99, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-12 11:51:30', 1, 1),
(23, NULL, 'A10', NULL, NULL, 1706.65, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-12 12:20:05', 1, 1),
(24, NULL, 'A20', NULL, NULL, 2236.30, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-12 12:48:19', 1, 1),
(25, NULL, 'A1', NULL, NULL, 682.66, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-12 12:49:33', 1, 1),
(26, NULL, 'A3', NULL, NULL, 2342.23, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-12 12:52:19', 1, 1),
(27, NULL, 'A3', NULL, NULL, 1023.99, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-12 12:52:41', 1, 1),
(28, NULL, 'A8', NULL, NULL, 4590.30, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-12 13:01:13', 1, 1),
(29, NULL, 'A6', NULL, NULL, 4590.30, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-12 13:02:58', 1, 1),
(30, NULL, 'A3', NULL, NULL, 4590.30, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-12 13:07:04', 1, 1),
(31, NULL, 'A9', NULL, NULL, 4590.30, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-12 13:08:30', 1, 1),
(32, NULL, 'G01', NULL, 1, 45903.00, 0.00, 0.00, 459, 0, 1, 'paid', '2026-06-12 15:44:17', 1, 1),
(33, NULL, 'A20', NULL, NULL, 400.18, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-13 13:14:07', 1, 1),
(34, NULL, 'A09', NULL, NULL, 2236.30, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-13 13:32:42', 1, 1),
(35, NULL, 'A99', NULL, NULL, 2236.30, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-13 15:40:51', 1, 1),
(36, NULL, 'A1', NULL, NULL, 800.36, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-13 15:52:48', 1, 1),
(37, NULL, 'A87', NULL, NULL, 8803.96, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-13 16:08:38', 1, 1),
(38, NULL, 'A99', NULL, NULL, 682.66, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-13 16:10:17', 1, 1),
(39, NULL, 'A43', NULL, NULL, 341.33, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-13 16:11:14', 1, 1),
(40, NULL, 'A22', NULL, NULL, 1706.65, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-13 16:11:40', 1, 1),
(41, NULL, 'A44', NULL, NULL, 1706.65, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-13 16:13:50', 1, 1),
(42, 'INV-260615-001', 'A3', NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 1, 'voided', '2026-06-15 12:07:13', 1, 1),
(43, 'INV-260615-002', 'A12', NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 1, 'voided', '2026-06-15 12:11:09', 1, 1),
(44, '003', 'A21', 'นพ', NULL, 2342.23, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-15 12:16:28', 1, 1),
(45, '004', 'A55', 'ผู้จัดการร้าน', NULL, 341.33, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-15 14:33:54', 1, 1),
(46, '005', 'H7', 'ผู้จัดการร้าน', NULL, 459.03, 0.00, 0.00, 0, 0, 1, 'paid', '2026-06-15 14:39:22', 1, 1),
(47, '006', 'A1', NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 1, 'active', '2026-06-15 14:58:57', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `item_discount` decimal(10,2) DEFAULT 0.00,
  `quantity` int(11) DEFAULT 1,
  `status` enum('active','voided') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `item_name`, `price`, `item_discount`, `quantity`, `status`, `created_by`) VALUES
(1, 9, 'sing (ขวด)', 220.00, 0.00, 1, 'active', 1),
(2, 9, 'sing (ขวด)', 220.00, 0.00, 1, 'active', 1),
(3, 9, 'sing (ขวด)', 220.00, 0.00, 1, 'active', 1),
(4, 8, 'sing (ขวด)', 220.00, 0.00, 1, 'active', 1),
(5, 8, 'sing (ขวด)', 220.00, 0.00, 1, 'active', 1),
(6, 9, 'sing (แก้วใหญ่)', 80.00, 0.00, 1, 'active', 1),
(8, 9, 'Koiawwue (Tequila 20ml + Gin 10ml + Whiskey 30ml)', 890.00, 0.00, 1, 'active', 1),
(9, 10, 'Koieu (Vodka 20ml + Vodka 10ml + Whiskey 10ml)', 890.00, 0.00, 1, 'voided', 1),
(10, 13, 'Koieu (Vodka 20ml + Vodka 10ml + Whiskey 10ml)', 890.00, 0.00, 1, 'voided', 1),
(11, 13, 'Koieu (Vodka 20ml + Vodka 10ml + Whiskey 10ml)', 890.00, 0.00, 1, 'voided', 1),
(12, 13, 'Koieu (Vodka 20ml + Vodka 10ml + Whiskey 10ml)', 890.00, 0.00, 1, 'voided', 1),
(13, 13, 'Koieu (Vodka 20ml + Vodka 10ml + Whiskey 10ml)', 890.00, 0.00, 1, 'voided', 1),
(25, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(26, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(27, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(28, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(29, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(30, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(31, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(32, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(33, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(34, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(35, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(36, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(37, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(38, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(39, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(40, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(41, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(42, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(43, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(44, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(45, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(46, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(47, 17, 'banana (แก้ว)', 390.00, 0.00, 1, 'voided', 1),
(48, 18, 'wine red (ขวด)', 1800.00, 0.00, 1, 'voided', NULL),
(49, 18, 'wine red (ขวด)', 1800.00, 0.00, 1, 'voided', NULL),
(50, 18, 'wine red (ขวด)', 1800.00, 0.00, 1, 'voided', NULL),
(51, 18, 'wine red (ขวด)', 1800.00, 0.00, 1, 'voided', NULL),
(52, 18, 'wine red (ขวด)', 1800.00, 0.00, 1, 'voided', NULL),
(53, 18, 'wine red (ขวด)', 1800.00, 0.00, 1, 'voided', NULL),
(54, 18, 'wine red (ขวด)', 1800.00, 0.00, 1, 'voided', NULL),
(55, 18, 'wine red (ขวด)', 1800.00, 0.00, 1, 'voided', NULL),
(56, 18, 'wine red (ขวด)', 1800.00, 0.00, 1, 'voided', NULL),
(57, 18, 'wine red (ขวด)', 1800.00, 0.00, 1, 'voided', NULL),
(58, 18, 'wine red (ขวด)', 1800.00, 0.00, 1, 'voided', NULL),
(59, 18, 'wine red (แก้ว)', 390.00, 0.00, 1, 'voided', NULL),
(60, 18, 'wine red (แก้ว)', 390.00, 0.00, 1, 'voided', NULL),
(61, 18, 'wine red (แก้ว)', 390.00, 0.00, 1, 'voided', NULL),
(62, 18, 'wine red (แก้ว)', 390.00, 0.00, 1, 'voided', NULL),
(63, 18, 'wine red (แก้ว)', 390.00, 0.00, 1, 'voided', NULL),
(64, 18, 'wine red (แก้ว)', 390.00, 0.00, 1, 'voided', NULL),
(65, 18, 'wine red (แก้ว)', 390.00, 0.00, 1, 'voided', NULL),
(66, 18, 'wine red (แก้ว)', 390.00, 0.00, 1, 'voided', NULL),
(67, 18, 'wine red (แก้ว)', 390.00, 0.00, 1, 'voided', NULL),
(68, 20, 'wine red (แก้ว)', 390.00, 0.00, 1, 'active', NULL),
(69, 20, 'wine red (แก้ว)', 390.00, 0.00, 1, 'active', NULL),
(80, 21, 'sing', 160.00, 16.00, 1, 'active', NULL),
(81, 21, 'sing', 160.00, 16.00, 1, 'active', NULL),
(82, 21, 'sing', 160.00, 16.00, 1, 'active', NULL),
(83, 21, 'sing', 160.00, 16.00, 1, 'active', NULL),
(84, 21, 'sing', 160.00, 16.00, 1, 'active', NULL),
(145, 22, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(146, 22, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(147, 22, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(148, 19, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(149, 19, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(150, 23, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(151, 23, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(152, 23, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(153, 23, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(154, 23, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(155, 24, 'wine iurow (ขวด)', 1900.00, 0.00, 1, 'active', NULL),
(156, 25, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(157, 25, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(158, 26, 'Wlihe wine', 1990.00, 0.00, 1, 'active', NULL),
(159, 27, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(160, 27, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(161, 27, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(163, 28, 'wine jj (ขวด)', 3900.00, 0.00, 1, 'active', NULL),
(164, 29, 'wine jj (ขวด)', 3900.00, 0.00, 1, 'active', NULL),
(165, 30, 'wine jj (ขวด)', 3900.00, 0.00, 1, 'active', NULL),
(166, 31, 'wine jj (ขวด)', 3900.00, 0.00, 1, 'active', NULL),
(175, 32, 'wine jj (ขวด)', 3900.00, 0.00, 1, 'active', NULL),
(176, 32, 'wine jj (ขวด)', 3900.00, 0.00, 1, 'active', NULL),
(177, 32, 'wine jj (ขวด)', 3900.00, 0.00, 1, 'active', NULL),
(178, 32, 'wine jj (ขวด)', 3900.00, 0.00, 1, 'active', NULL),
(179, 32, 'wine jj (ขวด)', 3900.00, 0.00, 1, 'active', NULL),
(180, 32, 'wine jj (ขวด)', 3900.00, 0.00, 1, 'active', NULL),
(181, 32, 'wine jj (ขวด)', 3900.00, 0.00, 1, 'active', NULL),
(182, 32, 'wine jj (ขวด)', 3900.00, 0.00, 1, 'active', NULL),
(183, 32, 'wine jj (ขวด)', 3900.00, 0.00, 1, 'active', NULL),
(184, 32, 'wine jj (ขวด)', 3900.00, 0.00, 1, 'active', NULL),
(211, 34, 'SING', 190.00, 0.00, 1, 'active', NULL),
(212, 34, 'SING', 190.00, 0.00, 1, 'active', NULL),
(213, 34, 'SING', 190.00, 0.00, 1, 'active', NULL),
(214, 34, 'SING', 190.00, 0.00, 1, 'active', NULL),
(215, 34, 'SING', 190.00, 0.00, 1, 'active', NULL),
(216, 34, 'SING', 190.00, 0.00, 1, 'active', NULL),
(217, 34, 'SING', 190.00, 0.00, 1, 'active', NULL),
(218, 34, 'SING', 190.00, 0.00, 1, 'active', NULL),
(219, 34, 'SING', 190.00, 0.00, 1, 'active', NULL),
(220, 34, 'SING', 190.00, 0.00, 1, 'active', NULL),
(221, 35, 'SING', 190.00, 0.00, 1, 'active', NULL),
(222, 35, 'SING', 190.00, 0.00, 1, 'active', NULL),
(223, 35, 'SING', 190.00, 0.00, 1, 'active', NULL),
(224, 35, 'SING', 190.00, 0.00, 1, 'active', NULL),
(225, 35, 'SING', 190.00, 0.00, 1, 'active', NULL),
(226, 35, 'SING', 190.00, 0.00, 1, 'active', NULL),
(227, 35, 'SING', 190.00, 0.00, 1, 'active', NULL),
(228, 35, 'SING', 190.00, 0.00, 1, 'active', NULL),
(229, 35, 'SING', 190.00, 0.00, 1, 'active', NULL),
(230, 35, 'SING', 190.00, 0.00, 1, 'active', NULL),
(231, 33, 'sing (แก้วใหญ่)', 170.00, 0.00, 1, 'active', NULL),
(232, 33, 'sing (แก้วใหญ่)', 170.00, 0.00, 1, 'active', NULL),
(233, 36, 'sing (แก้วใหญ่)', 170.00, 0.00, 1, 'active', NULL),
(234, 36, 'sing (แก้วใหญ่)', 170.00, 0.00, 1, 'active', NULL),
(235, 36, 'sing (แก้วใหญ่)', 170.00, 0.00, 1, 'active', NULL),
(236, 36, 'sing (แก้วใหญ่)', 170.00, 0.00, 1, 'active', NULL),
(237, 37, 'sing (แก้วใหญ่)', 170.00, 0.00, 1, 'active', NULL),
(238, 37, 'sing (แก้วใหญ่)', 170.00, 0.00, 1, 'active', NULL),
(239, 37, 'sing (แก้วใหญ่)', 170.00, 0.00, 1, 'active', NULL),
(240, 37, 'sing (แก้วใหญ่)', 170.00, 0.00, 1, 'active', NULL),
(242, 37, 'wine jj (ขวด)', 3900.00, 0.00, 1, 'active', NULL),
(243, 37, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(244, 37, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(245, 37, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(246, 37, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(247, 37, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(248, 37, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(249, 37, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(250, 37, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(251, 37, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(252, 37, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(253, 38, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(254, 38, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(255, 39, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(256, 40, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(257, 40, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(258, 40, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(259, 40, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(260, 40, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(261, 41, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(262, 41, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(263, 41, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(264, 41, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(265, 41, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(270, 44, 'Wlihe wine', 1990.00, 0.00, 1, 'active', NULL),
(272, 45, 'wine iurow (แก้ว)', 290.00, 0.00, 1, 'active', NULL),
(273, 46, 'mojito', 390.00, 0.00, 1, 'active', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `stock_qty` int(11) DEFAULT 0,
  `ml_per_unit` int(11) DEFAULT 0 COMMENT 'ปริมาณที่ใช้ต่อหน่วย (ml)',
  `inventory_id` int(11) DEFAULT NULL COMMENT 'ID สินค้าหลักที่ใช้ตัดสต็อก',
  `show_on_pos` tinyint(1) NOT NULL DEFAULT 1,
  `open_ml` int(11) DEFAULT 0 COMMENT 'ปริมาณ ml ของขวดที่ถูกเปิดใช้งาน'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `price`, `category`, `stock_qty`, `ml_per_unit`, `inventory_id`, `show_on_pos`, `open_ml`) VALUES
(1, 'ข้าวแกงกระหรี่', 189.00, 'FOOD', 0, 0, NULL, 1, 0),
(3, 'sing (แก้วเล็ก)', 89.00, 'BEER', 0, 100, 59, 1, 0),
(4, 'sing (แก้วใหญ่)', 170.00, 'BEER', 0, 250, 59, 1, 0),
(49, 'Wlihe wine', 1990.00, 'WINE', 4, 650, NULL, 1, 585),
(50, 'wine iurow (แก้ว)', 290.00, 'WINE', 0, 65, 49, 1, 0),
(52, 'wine green', 3900.00, 'WINE', 5, 750, NULL, 1, 0),
(53, 'wine jj (ขวด)', 3900.00, 'WINE', 0, 0, 49, 1, 0),
(59, 'SING(ถัง)', 190.00, 'BEER', 1, 0, NULL, 0, 1000),
(60, 'Gintonic', 999.00, 'GINTONIC', 9, 750, NULL, 1, 705),
(61, 'mojito', 390.00, 'GINTONIC', 0, 45, 60, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

CREATE TABLE `promotions` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `promo_type` varchar(50) NOT NULL,
  `target_category` varchar(100) DEFAULT NULL,
  `target_item` text DEFAULT NULL,
  `condition_qty` int(11) DEFAULT 0,
  `reward_qty` int(11) DEFAULT 0,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `promotions`
--

INSERT INTO `promotions` (`id`, `name`, `promo_type`, `target_category`, `target_item`, `condition_qty`, `reward_qty`, `discount_percent`, `start_time`, `end_time`, `is_active`) VALUES
(1, 'HappyHose', 'buy_x_get_y', 'Beer', NULL, 2, 1, 0.00, '17:00:00', '22:00:00', 1),
(2, 'Mojito', 'buy_x_get_y', 'GINTONIC', '[\"mojito\"]', 2, 1, 0.00, '17:00:00', '02:00:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `stock_logs`
--

CREATE TABLE `stock_logs` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `qty_change` int(11) NOT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'unit',
  `type` enum('restock','sale') DEFAULT 'restock',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_logs`
--

INSERT INTO `stock_logs` (`id`, `product_id`, `qty_change`, `unit`, `type`, `created_at`) VALUES
(3, 50, 10, 'unit', 'restock', '2026-06-12 11:52:22'),
(6, 49, 20, 'unit', 'restock', '2026-06-12 11:57:52'),
(7, 49, -195, 'unit', 'sale', '2026-06-12 12:11:43'),
(8, 49, 172, 'unit', 'restock', '2026-06-12 12:18:15'),
(9, 49, 10, 'unit', 'restock', '2026-06-12 12:18:21'),
(10, 49, -130, 'unit', 'sale', '2026-06-12 12:19:33'),
(11, 49, -325, 'unit', 'sale', '2026-06-12 12:20:33'),
(12, 49, 445, 'unit', 'restock', '2026-06-12 12:24:54'),
(15, 49, -1, 'unit', 'sale', '2026-06-12 12:52:25'),
(16, 53, -1, 'unit', 'sale', '2026-06-12 13:01:26'),
(17, 49, -1, 'unit', 'sale', '2026-06-12 13:07:31'),
(18, 49, -1, 'unit', 'sale', '2026-06-12 13:08:38'),
(19, 49, 10, 'unit', 'restock', '2026-06-12 15:44:43'),
(20, 49, -10, 'unit', 'sale', '2026-06-12 15:46:09'),
(21, 49, 10, 'unit', 'restock', '2026-06-13 15:07:49'),
(22, 59, -10, 'unit', 'sale', '2026-06-13 15:40:26'),
(23, 59, -10, 'unit', 'sale', '2026-06-13 15:41:01'),
(24, 59, 10, 'unit', 'restock', '2026-06-13 15:41:27'),
(25, 49, -1, 'unit', 'sale', '2026-06-13 16:09:35'),
(26, 59, -1000, 'ml', 'sale', '2026-06-13 16:09:43'),
(27, 49, -650, 'ml', 'sale', '2026-06-13 16:09:43'),
(28, 49, -1, 'unit', 'sale', '2026-06-13 16:09:43'),
(29, 49, -1, 'unit', 'sale', '2026-06-13 16:10:24'),
(30, 49, -130, 'ml', 'sale', '2026-06-13 16:10:29'),
(31, 49, -65, 'ml', 'sale', '2026-06-13 16:11:21'),
(32, 49, -1, 'unit', 'sale', '2026-06-13 16:11:44'),
(33, 49, -325, 'ml', 'sale', '2026-06-13 16:11:49'),
(34, 49, -325, 'ml', 'sale', '2026-06-13 16:14:00'),
(35, 49, -1, 'unit', 'sale', '2026-06-15 12:30:24'),
(36, 49, -1, 'unit', 'sale', '2026-06-15 14:34:04'),
(37, 49, -65, 'ml', 'sale', '2026-06-15 14:34:57'),
(38, 60, 10, 'unit', 'restock', '2026-06-15 14:37:28'),
(39, 60, -1, 'unit', 'sale', '2026-06-15 14:39:26'),
(40, 60, -45, 'ml', 'sale', '2026-06-15 14:39:30');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('cashier','manager') NOT NULL DEFAULT 'cashier',
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `name`) VALUES
(1, 'admin', '$2y$10$0xzuoMS6h.bcEdAJ1HYIX..NOV2xLvL9A8JlcburOFNuTe.M8fVei', 'manager', 'ผู้จัดการร้าน'),
(2, 'staff', '$2y$10$.g.50tzq5HPmkqBlOKgLo.ut6prNFbh71ZrP3Gtffbd6JEMQ0mr1y', 'cashier', 'พนักงานขาย (แคชเชียร์)'),
(3, 'nop', '$2y$10$AK99qm5wCjJoRvbkijJ0BeUqfJwT90gDaZX6mXFOAdzjo/AZFs.NO', 'cashier', 'นพ');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock_logs`
--
ALTER TABLE `stock_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=289;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `stock_logs`
--
ALTER TABLE `stock_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
