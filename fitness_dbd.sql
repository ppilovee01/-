-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 17, 2026 at 04:40 PM
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
-- Database: `fitness_dbd`
--

-- --------------------------------------------------------

--
-- Table structure for table `about_content`
--

CREATE TABLE `about_content` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `about_content`
--

INSERT INTO `about_content` (`id`, `title`, `description`, `image`, `updated_at`) VALUES
(1, 'สู่การสเนเธตเนอสุเธภาเธดี', '\"สวัสดีเธรัเธ เธมชื่อ เธาย เธมสัเธ เธุเธเขียน เธูเนเธเนอตัเนเธ Por Mae Bet Taled\r\n\r\nเธุดเริเนมตเนเธของรเนาเธนี้ เนมเนได้มาจากหเนอเธเนอรเนหรูหรา เนตเนมาจากเธเธเเธาะมอเตอรเนเนเธเธเนเนละการหน้าเธอเธอมเธิวเตอรเน...\r\n\r\nเธมเเธเนเธเดเนเธ Gen Z เธเธหเธึเนเธที่ทำเธาเธมาหลาเธหลาย ตัเนเธเนตเนเเธเนเธ Rider สเนเธอาหารที่ตเนอเธเนเธเนเธเธัเธเวลา ไปเธเธถึเธการเเธเนเธ Developer เธัเนเธเขียนเนเธเนดหน้าเธอมเธิวเตอรเนทัเนเธวัน การเนเธเนเธีวิตที่เรเนเธรีเธทำเนหเนเธมตระหเธัเธได้วเนา \'สุเธภาเธเธือตเนเธทุเธที่เนเธเธที่สุด\'\r\n\r\nเธมเริเนมหัเธมาดูเนลตัวเอเธ ทำอาหารเธลีเธเธิเธเอเธ เนละออกเธำลัเธเธาย เนตเนเธมเธเธวเนาอุปกรณ์ฟิตเนสดีเน มัเธมีราคาที่เข้าถึเธยาเธ หรือเนมเนเธเนคุณภาเธเนมเนตรเธเธเธ เธัเนเธเธึเธเเธเนเธเธุดเริเนมตเนเธที่ทำเนหเนเธมเนเธเนทัเธษะที่มี สรเนาเธเวเนเธเนเธตเน Por Mae Bet Taled เธึเนเธมา\r\n\r\nPor Mae Bet Taled เนมเนเนเธเนเนเธเนรเนาเธเธายอุปกรณ์ออกเธำลัเธเธาย เนตเนเธือความตัเนเธเนเธที่อยาเธเนหเนทุเธเธเธ เนมเนวเนาเธะมีอาเธีเธอะเนร หรือมีเวลาเธำเธัดเนเธเนเนหเธ เธเนสามารถสรเนาเธสุเธภาเธดีได้ที่บ้าน ในราคาที่สมเหตุสมเธล\r\n\r\nขอบคุณที่สนับสเธุเธรเนาเธเลเนเธเน ของเธมเธรัเธ\"', 'uploads/about_698f51b5217b1.jpg', '2026-03-17 15:40:28');

-- --------------------------------------------------------

--
-- Table structure for table `banners`
--

CREATE TABLE `banners` (
  `id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `banners`
--

INSERT INTO `banners` (`id`, `image`, `created_at`) VALUES
(15, 'uploads/banner_69b97435e2fe4.jpg', '2026-03-17 15:33:09');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(6, 'อาหารเนหเนเธเนละเเธรืเนอเธเธรุเธ'),
(7, 'เเธรืเนอเธดืเนมเนละเธเธม'),
(8, 'ของเนเธเนสเนวเธตัว'),
(9, 'อุปกรณ์ทำความสะอาด'),
(10, 'ยาสามัเธเธระเธำบ้าน'),
(11, 'เเธรืเนอเธเขียนเนละอุปกรณ์สำเธัเธเธาเธ'),
(12, 'เเธเนดเตลเนดอืเนเธเน ในเธรัวเรือเธ');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('unread','read') DEFAULT 'unread'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `subject`, `message`, `created_at`, `status`) VALUES
(1, 'komsan bonkelan', 'ppilovee01@gmail.com', 'เธอมเสีย', 'เนมเนเธอเธ', '2026-02-16 15:28:01', 'read');

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `discount_type` enum('fixed','percent') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `min_spend` decimal(10,2) DEFAULT 0.00,
  `expiry_date` date DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`id`, `code`, `discount_type`, `discount_value`, `min_spend`, `expiry_date`, `status`) VALUES
(1, 'WELCOME100', 'fixed', 100.00, 500.00, '2026-02-28', 'active'),
(2, 'SALE10', 'percent', 10.00, 0.00, '2026-12-31', 'active'),
(3, 'เอเธ', 'fixed', 5000.00, 0.00, '2026-02-26', 'active'),
(6, 'W', 'fixed', 50.00, 0.00, '2026-01-02', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `user_id`, `message`, `created_at`) VALUES
(1, 9, 'เธาเธ', '2026-02-09 17:34:47'),
(2, 9, 'เนมเนเธอเธ', '2026-02-09 17:56:47');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `final_price` decimal(10,2) DEFAULT 0.00,
  `coupon_code` varchar(50) DEFAULT NULL,
  `status` enum('pending','approved','shipping','completed') DEFAULT 'pending',
  `payment_method` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `payment_slip` varchar(255) DEFAULT NULL,
  `tracking_no` varchar(100) DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `total_price`, `discount_amount`, `final_price`, `coupon_code`, `status`, `payment_method`, `address`, `payment_slip`, `tracking_no`, `order_date`, `admin_note`) VALUES
(1, 2, 1500.00, 0.00, 0.00, NULL, 'shipping', NULL, NULL, NULL, 'เนมเนเธอเธ', '2026-01-21 08:13:16', NULL),
(2, 2, 500.00, 50.00, 450.00, 'SALE10', 'shipping', NULL, 'เธมสัเธ  (0919922031)\n107 หมูเน7 เธเนาหวเนาเธ เนเธวเธ/ตำเธล เเธียเธเเธรือ เเธต/อำเภอ เมือเธ เธ.สเธลเธเธร 47000', '', 'TH-4124545-414584', '2026-01-21 08:39:58', NULL),
(3, 2, 500.00, 0.00, 500.00, '', 'pending', NULL, 'เธมสัเธ  (0919922031)\n110 หมูเน 5 เนเธวเธ/ตำเธล เเธียเธเเธรือ เเธต/อำเภอ เมือเธ เธ.สเธลเธเธร 47000', '', NULL, '2026-01-21 09:03:15', NULL),
(4, 2, 1000.00, 0.00, 1000.00, '', 'pending', NULL, 'เธมสัเธ  (0919922031)\n110 หมูเน 5 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', 'slip_697097c776b81.jpg', NULL, '2026-01-21 09:09:27', NULL),
(5, 2, 500.00, 0.00, 500.00, '', 'pending', NULL, 'เธมสัเธ  (0919922031)\n110 หมูเน 5 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', 'slip_69709895a0535.jpg', NULL, '2026-01-21 09:12:53', NULL),
(6, 2, 500.00, 0.00, 500.00, '', 'approved', NULL, 'เธมสัเธ  (0919922031)\n110 หมูเน 5 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', 'slip_697098b42847f.jpg', NULL, '2026-01-21 09:13:24', NULL),
(7, 2, 500.00, 0.00, 500.00, '', '', 'เก็บเเธิเธเธลายทาเธ (COD)', 'เธมสัเธ  (0919922031)\n110 หมูเน 5 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', '', NULL, '2026-01-21 10:04:25', NULL),
(8, 2, 5200.00, 520.00, 4680.00, 'SALE10', '', 'เธาย เธมสัเธ เธุเธเขียน', 'เธมสัเธ  (0919922031)\n110 หมูเน 5 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', 'slip_6970ad897ef73.jpg', NULL, '2026-01-21 10:42:17', NULL),
(9, 2, 5.00, 0.00, 5.00, '', '', 'เก็บเเธิเธเธลายทาเธ (COD)', 'เธมสัเธ  (0919922031)\n110 หมูเน 5 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', '', NULL, '2026-01-21 10:56:20', NULL),
(10, 2, 5.00, 0.00, 5.00, '', '', 'เก็บเเธิเธเธลายทาเธ (COD)', 'เธมสัเธ  (0919922031)\n110 หมูเน 5 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', '', NULL, '2026-01-21 10:56:33', NULL),
(11, 2, 5.00, 0.00, 5.00, '', '', 'เก็บเเธิเธเธลายทาเธ (COD)', 'เธมสัเธ  (0919922031)\n110 หมูเน 5 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', '', NULL, '2026-01-21 10:56:59', NULL),
(12, 9, 5200.00, 0.00, 5200.00, '', 'approved', 'เก็บเเธิเธเธลายทาเธ (COD)', 'เธมสัเธ  (0919922031)\n107 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', '', NULL, '2026-01-21 11:32:08', NULL),
(13, 9, 5.00, 0.00, 5.00, '', 'shipping', 'เก็บเเธิเธเธลายทาเธ (COD)', 'เธมสัเธ  (0660100839)\n107 424523 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', '', 'TH-4124545-414584', '2026-01-21 11:35:42', NULL),
(14, 10, 7700.00, 0.00, 7700.00, '', 'approved', 'เก็บเเธิเธเธลายทาเธ (COD)', 'เธมสัเธ  (0919922031)\n107 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', '', NULL, '2026-01-23 14:59:37', NULL),
(15, 9, 2500.00, 0.00, 2500.00, '', 'pending', 'เก็บเเธิเธเธลายทาเธ (COD)', 'เธมสัเธ  (0660100839)\n107 424523 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', '', NULL, '2026-01-23 15:39:48', NULL),
(16, 9, 15200.00, 0.00, 15200.00, '', 'pending', 'เก็บเเธิเธเธลายทาเธ (COD)', 'เธมสัเธ  (0660100839)\n107 424523 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', '', NULL, '2026-02-07 15:47:14', NULL),
(17, 9, 2500.00, 0.00, 2500.00, '', '', 'เก็บเเธิเธเธลายทาเธ (COD)', 'เธมสัเธ  (0660100839)\n107 424523 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', '', NULL, '2026-02-07 15:49:17', NULL),
(18, 9, 200.00, 0.00, 200.00, '', '', 'เก็บเเธิเธเธลายทาเธ (COD)', 'เธมสัเธ  (0660100839)\n107 424523 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', '', NULL, '2026-02-07 15:51:09', NULL),
(19, 9, 1.00, 5000.00, 0.00, 'เอเธ', '', 'เก็บเเธิเธเธลายทาเธ (COD)', 'เธมสัเธ  (0660100839)\n107 424523 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', '', NULL, '2026-02-09 17:00:20', NULL),
(20, 9, 1.00, 5000.00, 0.00, 'เอเธ', '', 'เก็บเเธิเธเธลายทาเธ (COD)', 'เธมสัเธ  (0660100839)\n107 424523 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', '', NULL, '2026-02-09 17:20:10', NULL),
(21, 9, 1.00, 0.10, 0.90, 'SALE10', '', 'เก็บเเธิเธเธลายทาเธ (COD)', 'เธมสัเธ  (0660100839)\n107 424523 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', '', NULL, '2026-02-09 17:21:27', NULL),
(22, 9, 5200.00, 5000.00, 200.00, 'เอเธ', 'approved', 'เธาย เธมสัเธ เธุเธเขียน', 'เธมสัเธ  (0660100839)\n107 424523 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', 'slip_698c4da4390e2.png', NULL, '2026-02-11 09:36:36', 'ลูกค้าติดต่อรเนาเธเเธืเนอเธือสินค้าได้'),
(23, 9, 2500.00, 5000.00, 0.00, 'เอเธ', '', 'เธาย เธมสัเธ เธุเธเขียน', 'เธมสัเธ  (0660100839)\n107 424523 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', 'slip_698c59a526675.jpg', NULL, '2026-02-11 10:27:49', NULL),
(24, 9, 5200.00, 0.00, 5200.00, '', '', 'เธาย เธมสัเธ เธุเธเขียน', 'เธมสัเธ  (0660100839)\n107 424523 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', 'slip_698c5a0383e98.jpg', NULL, '2026-02-11 10:29:23', 'ระวัเธของเนตเธ'),
(25, 9, 447.00, 0.00, 447.00, '', 'shipping', 'เก็บเเธิเธเธลายทาเธ (COD)', 'เธมสัเธ  (0660100839)\n107 424523 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', '', 'TH-4124545-4654045601', '2026-02-12 15:30:10', 'ระวัเธของเนตเธ'),
(26, 9, 1390.00, 0.00, 1390.00, '', '', 'เก็บเเธิเธเธลายทาเธ (COD)', 'เธมสัเธ  (0660100839)\n107 424523 เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', '', NULL, '2026-02-13 15:52:09', NULL),
(27, 9, 1390.00, 0.00, 1390.00, '', 'completed', 'เก็บเเธิเธเธลายทาเธ (COD)', 'เธมสัเธ  (0919922031)\n107\r\nบ้านเธเนาหวเนาเธ เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', '', 'TH-4124545-465', '2026-02-16 16:52:02', NULL),
(28, 9, 1390.00, 0.00, 1390.00, '', 'approved', 'เก็บเเธิเธเธลายทาเธ (COD)', 'เธมสัเธ  (0919922031)\n107\r\nบ้านเธเนาหวเนาเธ เเธียเธเเธรือ เมือเธ สเธลเธเธร 47000', '', NULL, '2026-03-17 14:30:15', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `selected_option` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`, `selected_option`) VALUES
(1, 8, 3, 1, 5200.00, NULL),
(2, 9, 4, 1, 5.00, NULL),
(3, 10, 4, 1, 5.00, NULL),
(4, 11, 4, 1, 5.00, NULL),
(5, 12, 3, 1, 5200.00, NULL),
(6, 13, 4, 1, 5.00, NULL),
(7, 14, 3, 1, 5200.00, NULL),
(8, 14, 2, 1, 2500.00, NULL),
(9, 15, 2, 1, 2500.00, NULL),
(10, 16, 3, 1, 5200.00, NULL),
(11, 16, 2, 4, 2500.00, NULL),
(12, 17, 2, 1, 2500.00, NULL),
(13, 18, 5, 1, 200.00, NULL),
(14, 19, 6, 1, 1.00, NULL),
(15, 20, 6, 1, 1.00, NULL),
(16, 21, 6, 1, 1.00, NULL),
(17, 22, 3, 1, 5200.00, NULL),
(18, 23, 2, 1, 2500.00, NULL),
(19, 24, 3, 1, 5200.00, NULL),
(20, 25, 14, 1, 328.00, 'เธเนำหเธัเธ: 15เนล'),
(21, 25, 12, 1, 119.00, ''),
(22, 27, 23, 1, 1390.00, 'สี: ดำ'),
(23, 28, 23, 1, 1390.00, 'สี: ดำ');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('bank','promptpay','cod') NOT NULL,
  `account_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `name`, `type`, `account_name`, `account_number`, `status`) VALUES
(2, 'เก็บเเธิเธเธลายทาเธ (COD)', 'cod', '-', '-', 'active'),
(3, 'เธาย เธมสัเธ เธุเธเขียน', 'promptpay', NULL, '0919922031', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `stock` int(11) DEFAULT 100,
  `options` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `price`, `description`, `image`, `stock`, `options`) VALUES
(27, 6, 'เนวเนว เสเนเธหมีเนรสหมูสัเธ (สม.) 6เธอเธ', 46.00, 'เสเนเธหมีเนเธึเนเธสำเร็จรูเธรสหมูสัเธ 1 เนเธเนเธ 6 เธอเธ เธเนำหเธเนเธ 55 เธรัมตเนอเธอเธ\r\n\r\nอ.ย.73-1-05836-2-0008\r\n\r\n\r\n\r\nMinced Pork Flavour Instant Rice Vermicelli WaiWai\r\n\r\nสเนวเธเธระเธอเธที่สำเธัเธ / Ingredients\r\n\r\nเนเธเนเธเธเนาวเเธเนา	Rice Flour	77%\r\n\r\nเธเนำมัเธเเธียวเธระเทียม	Garlic Oil	7.27%\r\n\r\nเเธลือเสริมเนอเนอดีเธ	Iodized Salt	5.45%\r\n\r\nเเธืเนอหมูเธเธ	Dried Pork	2.0%\r\n\r\nรสหมูเธเธ	Pork Flavour	1.36\r\n\r\nเนตเนเธรสเลียเธเธรรมเธาติ เนเธเนเนมเนเธเนเธเดียมเธลูตาเมตเเธเนเธวัตถุเธรุเธเนตเนเธรสอาหาร\r\n\r\nเนมเนเนเธเนวัตถุเธัเธเสีย\r\n\r\nเธเนอมูลสำหรับเธูเนเนเธเนอาหาร : มีเธเนาวสาลี, เนเธรตีเธจากถัเนวเหลือเธ อาเธมี เธุเนเธ เธลา เนเธเน เธม ', 'uploads/prod_69b6d9b864450.webp', 100, ''),
(28, 6, 'อะยัมเธลาเนมเธเเธอเรลในเธอสมะเเธือเทศ 155เธรัม', 36.00, 'อะยัมเธลาเนมเธเเธอเรลในเธอสมะเเธือเทศ 155เธรัม Ayam Mackerel In Tomato Sauce 155g. [หมายเลเธเธารเนเนเธเนด 8851826601115 ]', 'uploads/prod_69b6d9e9dbb50.webp', 100, ''),
(29, 6, 'ตรทอเธ เธเนาวเธาวหอมมะลิ 100% 5 เธเธ.', 250.00, 'เธัตรทอเธ เธเนาวเธาวหอมมะลิ 100% 5 เธเธ.\r\n\r\n \r\n\r\n เธัตรทอเธ เธเนาวหอมมะลิ 100% เเธรดเธรีเมีเนยม เธัตรทอเธเธัดสรรวัตถุดิเธจากเนหลเนเธเเธาะเธลูเธที่ดี เมลเนดเรียวยาว สีเธาวเธวล หุเธอรเนอย เธลิเนเธหอม เเธืเนอเธุเนม เธลิตดเนวยเธระเธวเธการเธัดเเธเนเธเธิเศษ เมลเนดเธเนาวเธึเธสะอาดเธวเนา เนละเเธาสวยเธวเนาเธเนาวเธรรมดาถึเธ 3 เทเนา\r\n\r\n \r\n\r\n - เธัตรทอเธ เธเนาวหอมมะลิ 100% เเธรดเธรีเมีเนยม เธเนาวคุณภาเธดีเนสเธอรเนอย\r\n\r\n - สะอาด เธลอดภัย เนสเนเนเธทุเธเธัเนเธตอเธในการเธลิต\r\n\r\n - หุเธอรเนอย เธลิเนเธหอม เเธืเนอเธุเนม เธเนารัเธเธระทาเธ\r\n\r\n - เธเนาวหอมมะลิคุณภาเธที่ได้รัเธราเธวัล \"Best Rice Award\"\r\n\r\n - เข้าเธัเธได้ดีเธัเธอาหารทุเธเธเธิด รัเธเธระทาเธได้ทุเธวัน', 'uploads/prod_69b6da3f66c55.webp', 100, ''),
(30, 7, 'เธเนำดืเนมเธริสตัลเธเธาด (1500 มล. x 6 เธวด)', 60.00, 'เธเนำดืเนมเธริสตัลเธเธาด (1500 มล. x 6 เธวด)', 'uploads/prod_69b6da9653c27.webp', 100, ''),
(31, 8, 'เธอหเนเธสัเธ เเธเธีเน เธรีมอาเธเธเนำเนละสระเธมเดเนเธ เเธดเนทมเน เเธลเนเธ เนเธมมิเนเธ เนเธมมิเนเธ วอเธ 400 มล.', 199.00, 'อหเนเธสัเธ เเธเธีเน เเธดเนทมเน เเธลเธเน เนเธมมิเนเธ วอเธ เนอเธดเน เนชมเธู\r\n\r\nเธลิตภัณฑเนทำความสะอาดเธิวเนละสระเธม\r\n\r\nJOHNSONโ€Sยฎ baby เธเธเธเนอเธเธิวเนละเสเนเธเธมอัเธเธอเธเธาเธของลูเธเธเนอย เนละเธเนวเธเวลาเนหเนเธความเธูเธเธัเธอัเธลเนำเธเนา\r\n\r\nอาเธเธเนำอุเนเธ + เธวดสัมเธัสอยเนาเธอเนอเธเนยเธ + เธเนวเธเวลาเธัเธเธเนอเธ\r\n\r\n- เธเนาเธการทดสอเธเนดยเธุมารเนเธทยเน เนเธทยเนเธิวหเธัเธ เนละเธัเธษุเนเธทยเน\r\n\r\n- เธเนาเธการเธิสูเธเธเนทาเธเธลิเธิเธแล้ววเนา เธลอดภัยสำหรับเธิวเดเนเธแรกเเธิด เนละเธิวเธอเธเธาเธ\r\n\r\n- เธเนาเธการทดสอเธวเนาเนมเนเธเนอเนหเนเเธิดการเนเธเน เนละมีเธเนา pH ที่เหมาะเธัเธเธิว\r\n\r\nเธิสูเธเธเนทาเธเธลิเธิเธแล้ววเนาเธิเธวัตรเธเนอเธเข้าเธอเธเธเนวยเนหเนลูเธเธเนอยหลัเธดีเธึเนเธ*\r\n\r\n', 'uploads/prod_69b6dac2cc859.webp', 100, '');

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL DEFAULT 5,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `product_reviews`
--

INSERT INTO `product_reviews` (`id`, `product_id`, `user_id`, `rating`, `comment`, `created_at`) VALUES
(1, 14, 9, 2, 'เธเนดีเธะ', '2026-02-12 15:30:51');

-- --------------------------------------------------------

--
-- Table structure for table `shop_settings`
--

CREATE TABLE `shop_settings` (
  `id` int(11) NOT NULL,
  `shop_name` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `shop_email` varchar(255) DEFAULT NULL,
  `print_remark` text DEFAULT NULL,
  `shop_icon` varchar(255) DEFAULT 'default_icon.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `shop_settings`
--

INSERT INTO `shop_settings` (`id`, `shop_name`, `address`, `phone`, `shop_email`, `print_remark`, `shop_icon`) VALUES
(1, 'Por Mae Bet Taled', '107 หมูเน7 ต.เเธียเธเเธรือ อ.เมือเธ เธ.สเธลเธเธร 47000', '0919922031', 'ppilovee01@gmail.com', 'เธรุณาถเนายวิดีเนอเธณะเนเธะเธัสดุ ขอบคุณที่เนเธเนเธริการทาเธรเนาเธ ', 'favicon_1773761547.webp');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` datetime DEFAULT current_timestamp(),
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `fullname`, `email`, `role`, `created_at`, `reset_token`, `reset_expiry`) VALUES
(9, 'a', '$2y$10$jIwzTa6bKxXBMEgQTZtnw.5pLO5Ic9yi96amnRsabBv7Mobq28NT2', 'เนมเนเธอเธ', 'ppilovee01@gmail.com', 'admin', '2026-02-10 00:40:54', '3adf1e1f7e3756b4c140ce75a536c97f5af85e6f473147ea3e9e8198ce1581a6', '2026-02-10 20:39:14'),
(10, 's', '$2y$10$Fao09pG6W0wFloXjAGJP9.STxgUCDqugibQ49kP7lvcaVzIHfGt4y', 's', 's@s.com', 'user', '2026-02-10 00:40:54', NULL, NULL),
(11, 'd', '$2y$10$ygD9gYKx0aG943rGz2u5UOKPsL6yF9aAxkvCANDnq.r5EsfrW/D36', 'd', 'd@d.com', 'user', '2026-02-10 00:40:54', NULL, NULL),
(13, 'g', '$2y$10$aNticmZDDNDyiBPzr6vN3eVzDkAz/0q2epyjm4KlvzG43l8xG5hiy', 'g', 'g@g.com', 'user', '2026-02-10 19:39:40', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_addresses`
--

CREATE TABLE `user_addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `recipient_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `subdistrict` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `zipcode` varchar(10) DEFAULT NULL,
  `is_default` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `user_addresses`
--

INSERT INTO `user_addresses` (`id`, `user_id`, `recipient_name`, `phone`, `address_line1`, `subdistrict`, `district`, `province`, `zipcode`, `is_default`) VALUES
(1, 2, 'เธมสัเธ ', '0919922031', '107 หมูเน7 เธเนาหวเนาเธ', 'เเธียเธเเธรือ', 'เมือเธ', 'สเธลเธเธร', '47000', 0),
(2, 2, 'เธมสัเธ ', '0919922031', '110 หมูเน 5', 'เเธียเธเเธรือ', 'เมือเธ', 'สเธลเธเธร', '47000', 0),
(7, 10, 'เธมสัเธ ', '0919922031', '107', 'เเธียเธเเธรือ', 'เมือเธ', 'สเธลเธเธร', '47000', 0),
(13, 9, 'เธมสัเธ ', '0919922031', '107\r\nบ้านเธเนาหวเนาเธ', 'เเธียเธเเธรือ', 'เมือเธ', 'สเธลเธเธร', '47000', 0);

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `wishlist`
--

INSERT INTO `wishlist` (`id`, `user_id`, `product_id`, `created_at`) VALUES
(22, 9, 24, '2026-02-16 15:24:25'),
(23, 9, 23, '2026-02-16 15:24:26'),
(24, 9, 22, '2026-02-16 15:24:34'),
(25, 9, 21, '2026-02-16 15:24:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `about_content`
--
ALTER TABLE `about_content`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shop_settings`
--
ALTER TABLE `shop_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `banners`
--
ALTER TABLE `banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `shop_settings`
--
ALTER TABLE `shop_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


