-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jun 07, 2026 at 04:14 PM
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
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `table_number` varchar(10) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `is_percent` tinyint(1) DEFAULT 1,
  `status` enum('active','paid','voided') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `apply_tax` tinyint(1) DEFAULT 0,
  `apply_sc` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `table_number`, `total_amount`, `discount_amount`, `is_percent`, `status`, `created_at`, `apply_tax`, `apply_sc`) VALUES
(8, 'A1', 517.88, 0.00, 1, 'paid', '2026-05-31 16:35:57', 0, 0),
(9, 'A1', 1918.51, 0.00, 1, 'paid', '2026-05-31 16:36:11', 0, 0),
(10, 'A2', 0.00, 0.00, 1, 'voided', '2026-05-31 16:48:41', 0, 0),
(11, 'A1', 0.00, 0.00, 1, 'voided', '2026-05-31 16:58:27', 0, 0),
(12, 'A3', 0.00, 0.00, 1, 'voided', '2026-05-31 16:58:49', 0, 0),
(13, 'A1', 0.00, 0.00, 1, 'voided', '2026-05-31 16:59:00', 0, 0),
(14, 'A1', 0.00, 0.00, 1, 'voided', '2026-05-31 17:03:20', 0, 0),
(15, 'A1', 0.00, 0.00, 1, 'voided', '2026-05-31 17:03:30', 0, 0),
(16, 'A1', 0.00, 0.00, 1, 'voided', '2026-06-02 15:13:26', 0, 0),
(17, 'A5', 0.00, 0.00, 1, 'voided', '2026-06-02 15:30:47', 0, 0),
(18, 'A4', 0.00, 0.00, 1, 'voided', '2026-06-02 15:36:03', 0, 0),
(19, 'A6', 0.00, 10.00, 1, 'active', '2026-06-05 15:35:09', 1, 1),
(20, 'A6', 918.06, 0.00, 1, 'paid', '2026-06-05 15:38:59', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `status` enum('active','voided') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `item_name`, `price`, `quantity`, `status`, `created_by`) VALUES
(1, 9, 'sing (ขวด)', 220.00, 1, 'active', 1),
(2, 9, 'sing (ขวด)', 220.00, 1, 'active', 1),
(3, 9, 'sing (ขวด)', 220.00, 1, 'active', 1),
(4, 8, 'sing (ขวด)', 220.00, 1, 'active', 1),
(5, 8, 'sing (ขวด)', 220.00, 1, 'active', 1),
(6, 9, 'sing (แก้วใหญ่)', 80.00, 1, 'active', 1),
(8, 9, 'Koiawwue (Tequila 20ml + Gin 10ml + Whiskey 30ml)', 890.00, 1, 'active', 1),
(9, 10, 'Koieu (Vodka 20ml + Vodka 10ml + Whiskey 10ml)', 890.00, 1, 'voided', 1),
(10, 13, 'Koieu (Vodka 20ml + Vodka 10ml + Whiskey 10ml)', 890.00, 1, 'voided', 1),
(11, 13, 'Koieu (Vodka 20ml + Vodka 10ml + Whiskey 10ml)', 890.00, 1, 'voided', 1),
(12, 13, 'Koieu (Vodka 20ml + Vodka 10ml + Whiskey 10ml)', 890.00, 1, 'voided', 1),
(13, 13, 'Koieu (Vodka 20ml + Vodka 10ml + Whiskey 10ml)', 890.00, 1, 'voided', 1),
(25, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(26, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(27, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(28, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(29, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(30, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(31, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(32, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(33, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(34, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(35, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(36, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(37, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(38, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(39, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(40, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(41, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(42, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(43, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(44, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(45, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(46, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(47, 17, 'banana (แก้ว)', 390.00, 1, 'voided', 1),
(48, 18, 'wine red (ขวด)', 1800.00, 1, 'voided', NULL),
(49, 18, 'wine red (ขวด)', 1800.00, 1, 'voided', NULL),
(50, 18, 'wine red (ขวด)', 1800.00, 1, 'voided', NULL),
(51, 18, 'wine red (ขวด)', 1800.00, 1, 'voided', NULL),
(52, 18, 'wine red (ขวด)', 1800.00, 1, 'voided', NULL),
(53, 18, 'wine red (ขวด)', 1800.00, 1, 'voided', NULL),
(54, 18, 'wine red (ขวด)', 1800.00, 1, 'voided', NULL),
(55, 18, 'wine red (ขวด)', 1800.00, 1, 'voided', NULL),
(56, 18, 'wine red (ขวด)', 1800.00, 1, 'voided', NULL),
(57, 18, 'wine red (ขวด)', 1800.00, 1, 'voided', NULL),
(58, 18, 'wine red (ขวด)', 1800.00, 1, 'voided', NULL),
(59, 18, 'wine red (แก้ว)', 390.00, 1, 'voided', NULL),
(60, 18, 'wine red (แก้ว)', 390.00, 1, 'voided', NULL),
(61, 18, 'wine red (แก้ว)', 390.00, 1, 'voided', NULL),
(62, 18, 'wine red (แก้ว)', 390.00, 1, 'voided', NULL),
(63, 18, 'wine red (แก้ว)', 390.00, 1, 'voided', NULL),
(64, 18, 'wine red (แก้ว)', 390.00, 1, 'voided', NULL),
(65, 18, 'wine red (แก้ว)', 390.00, 1, 'voided', NULL),
(66, 18, 'wine red (แก้ว)', 390.00, 1, 'voided', NULL),
(67, 18, 'wine red (แก้ว)', 390.00, 1, 'voided', NULL),
(68, 20, 'wine red (แก้ว)', 390.00, 1, 'active', NULL),
(69, 20, 'wine red (แก้ว)', 390.00, 1, 'active', NULL),
(70, 19, 'wine red (แก้ว)', 390.00, 1, 'active', NULL),
(71, 19, 'wine red (แก้ว)', 390.00, 1, 'active', NULL),
(72, 19, 'wine red (แก้ว)', 390.00, 1, 'active', NULL),
(73, 19, 'wine red (แก้ว)', 390.00, 1, 'active', NULL),
(74, 19, 'wine red (แก้ว)', 390.00, 1, 'active', NULL),
(75, 19, 'wine red (แก้ว)', 390.00, 1, 'active', NULL),
(76, 19, 'wine red (แก้ว)', 390.00, 1, 'active', NULL),
(77, 19, 'wine red (แก้ว)', 390.00, 1, 'active', NULL);

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
  `inventory_id` int(11) DEFAULT NULL COMMENT 'ID สินค้าหลักที่ใช้ตัดสต็อก'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `price`, `category`, `stock_qty`, `ml_per_unit`, `inventory_id`) VALUES
(1, 'ข้าวแกงกระหรี่', 189.00, 'Food', 0, 0, NULL),
(2, 'sing', 160.00, 'Beer', 5, 0, NULL),
(3, 'sing (แก้วเล็ก)', 160.00, 'Beer', 0, 0, NULL),
(4, 'sing (แก้วใหญ่)', 80.00, 'Beer', 0, 0, NULL),
(5, 'sing (ขวด)', 220.00, 'Beer', 0, 0, NULL),
(6, 'wine red (แก้ว)', 390.00, 'Wine', 0, 0, NULL),
(7, 'wine red (ขวด)', 1800.00, 'Wine', 0, 0, NULL),
(8, 'banana (แก้ว)', 390.00, 'Cocktail', 0, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `stock_logs`
--

CREATE TABLE `stock_logs` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `qty_change` int(11) NOT NULL,
  `type` enum('restock','sale') DEFAULT 'restock',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

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
-- Indexes for table `stock_logs`
--
ALTER TABLE `stock_logs`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `stock_logs`
--
ALTER TABLE `stock_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
