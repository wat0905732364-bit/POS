-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jun 12, 2026 at 03:20 PM
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
(19, 'A6', 522.00, 10.00, 1, 'paid', '2026-06-05 15:35:09', 0, 0),
(20, 'A6', 918.06, 0.00, 1, 'paid', '2026-06-05 15:38:59', 1, 1),
(21, 'A3', 847.44, 0.00, 1, 'paid', '2026-06-07 15:25:46', 1, 1),
(22, 'A8', 1023.99, 0.00, 1, 'paid', '2026-06-12 11:51:30', 1, 1),
(23, 'A10', 1706.65, 0.00, 1, 'paid', '2026-06-12 12:20:05', 1, 1),
(24, 'A20', 2236.30, 0.00, 1, 'paid', '2026-06-12 12:48:19', 1, 1),
(25, 'A1', 682.66, 0.00, 1, 'paid', '2026-06-12 12:49:33', 1, 1),
(26, 'A3', 2342.23, 0.00, 1, 'paid', '2026-06-12 12:52:19', 1, 1),
(27, 'A3', 1023.99, 0.00, 1, 'paid', '2026-06-12 12:52:41', 1, 1),
(28, 'A8', 4590.30, 0.00, 1, 'paid', '2026-06-12 13:01:13', 1, 1),
(29, 'A6', 4590.30, 0.00, 1, 'paid', '2026-06-12 13:02:58', 1, 1),
(30, 'A3', 4590.30, 0.00, 1, 'paid', '2026-06-12 13:07:04', 1, 1),
(31, 'A9', 4590.30, 0.00, 1, 'paid', '2026-06-12 13:08:30', 1, 1);

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
(166, 31, 'wine jj (ขวด)', 3900.00, 0.00, 1, 'active', NULL);

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
  `open_ml` int(11) DEFAULT 0 COMMENT 'ปริมาณ ml ของขวดที่ถูกเปิดใช้งาน'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `price`, `category`, `stock_qty`, `ml_per_unit`, `inventory_id`, `open_ml`) VALUES
(1, 'ข้าวแกงกระหรี่', 189.00, 'Food', 0, 0, NULL, 0),
(2, 'sing', 160.00, 'Beer', 10, 0, NULL, 0),
(3, 'sing (แก้วเล็ก)', 89.00, 'Beer', 0, 0, NULL, 0),
(4, 'sing (แก้วใหญ่)', 170.00, 'Beer', 0, 0, NULL, 0),
(5, 'sing (ขวด)', 220.00, 'Beer', 0, 0, NULL, 0),
(6, 'wine red (แก้ว)', 390.00, 'Wine', 0, 0, NULL, 0),
(7, 'wine red (ขวด)', 1800.00, 'Wine', 0, 0, NULL, 0),
(8, 'banana (แก้ว)', 390.00, 'Cocktail', 0, 0, NULL, 0),
(49, 'Wlihe wine', 1990.00, 'Wine', 0, 650, NULL, -455),
(50, 'wine iurow (แก้ว)', 290.00, 'Wine', 0, 65, 49, 0),
(52, 'wine green', 3900.00, 'Wine', 5, 750, NULL, 0),
(53, 'wine jj (ขวด)', 3900.00, 'Wine', 0, 0, 49, 0);

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
-- Dumping data for table `stock_logs`
--

INSERT INTO `stock_logs` (`id`, `product_id`, `qty_change`, `type`, `created_at`) VALUES
(1, 2, -5, 'sale', '2026-06-12 11:25:48'),
(3, 50, 10, 'restock', '2026-06-12 11:52:22'),
(4, 2, 20, 'restock', '2026-06-12 11:56:47'),
(6, 49, 20, 'restock', '2026-06-12 11:57:52'),
(7, 49, -195, 'sale', '2026-06-12 12:11:43'),
(8, 49, 172, 'restock', '2026-06-12 12:18:15'),
(9, 49, 10, 'restock', '2026-06-12 12:18:21'),
(10, 49, -130, 'sale', '2026-06-12 12:19:33'),
(11, 49, -325, 'sale', '2026-06-12 12:20:33'),
(12, 49, 445, 'restock', '2026-06-12 12:24:54'),
(13, 2, -10, 'restock', '2026-06-12 12:27:37'),
(15, 49, -1, 'sale', '2026-06-12 12:52:25'),
(16, 53, -1, 'sale', '2026-06-12 13:01:26'),
(17, 49, -1, 'sale', '2026-06-12 13:07:31'),
(18, 49, -1, 'sale', '2026-06-12 13:08:38');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=167;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `stock_logs`
--
ALTER TABLE `stock_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

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
