-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 23, 2026 at 09:51 AM
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
-- Database: `campus_ecommerce`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_recovery_requests`
--

CREATE TABLE `account_recovery_requests` (
  `request_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_response` text DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `account_recovery_requests`
--

INSERT INTO `account_recovery_requests` (`request_id`, `user_id`, `reason`, `status`, `admin_response`, `reviewed_by`, `created_at`, `reviewed_at`) VALUES
(1, 11, 'plsssss', 'approved', 'ok', 1, '2026-09-11 07:01:22', '2026-09-11 07:05:06');

-- --------------------------------------------------------

--
-- Table structure for table `account_warning_reads`
--

CREATE TABLE `account_warning_reads` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `last_seen_log_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `account_warning_reads`
--

INSERT INTO `account_warning_reads` (`user_id`, `last_seen_log_id`, `updated_at`) VALUES
(12, 16, '2026-09-14 07:47:36'),
(13, 19, '2026-09-14 07:42:26'),
(14, 8, '2026-09-14 07:44:55'),
(15, 20, '2026-09-14 07:42:55');

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `log_id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_user_id` int(10) UNSIGNED DEFAULT NULL,
  `target_product_id` int(10) UNSIGNED DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_logs`
--

INSERT INTO `admin_logs` (`log_id`, `admin_id`, `action`, `target_user_id`, `target_product_id`, `details`, `created_at`) VALUES
(2, 1, 'suspend_user', 14, NULL, 'User suspended', '2026-09-08 08:50:37'),
(3, 1, 'unsuspend_user', 14, NULL, 'User suspension removed', '2026-09-08 08:51:31'),
(4, 1, 'suspend_user', 14, NULL, 'User suspended', '2026-09-08 08:56:54'),
(5, 1, 'unsuspend_user', 14, NULL, 'User suspension removed', '2026-09-08 08:56:59'),
(6, 1, 'delete_listing', NULL, 26, 'Listing marked as deleted by administrator.', '2026-09-08 09:02:25'),
(8, 1, 'warn_user', 14, NULL, 'Warning reason: you have cancelled the same item for so many times', '2026-09-10 16:40:39'),
(9, 1, 'delete_listing', NULL, 25, 'Listing marked as deleted by administrator.', '2026-09-10 16:43:32'),
(10, 1, 'suspend_user', 11, NULL, 'User suspended', '2026-09-11 06:16:22'),
(11, 1, 'unsuspend_user', 11, NULL, 'User suspension removed', '2026-09-11 06:16:29'),
(12, 1, 'suspend_user', 11, NULL, 'Suspension reason: hello', '2026-09-11 07:00:59'),
(13, 1, 'approve_recovery_request', 11, NULL, 'Administrator response: ok', '2026-09-11 07:05:06'),
(14, 1, 'add_school', NULL, NULL, 'School added: Taylor\'s University (student.taylor.edu.my).', '2026-09-11 07:44:18'),
(15, 1, 'warn_user', 12, NULL, 'Warning reason: test', '2026-09-11 07:57:16'),
(16, 1, 'warn_user', 12, NULL, 'Warning reason: warning 2', '2026-09-11 07:58:03'),
(17, 1, 'delete_listing', NULL, 15, 'Listing marked as deleted by administrator.', '2026-09-14 06:58:40'),
(18, 1, 'delete_listing', NULL, 28, 'Removal reason: too old', '2026-09-14 07:31:00'),
(19, 1, 'warn_user', 13, NULL, 'Warning reason: test test', '2026-09-14 07:38:17'),
(20, 1, 'warn_user', 15, NULL, 'Warning reason: testing123', '2026-09-14 07:42:50'),
(21, 1, 'delete_listing', NULL, 30, 'Removal reason: testing', '2026-09-14 07:44:00'),
(22, 1, 'delete_listing', NULL, 34, 'Removal reason: too expensive', '2026-09-14 09:28:28');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(10) UNSIGNED NOT NULL,
  `category_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`) VALUES
(1, 'Books & Study Materials'),
(2, 'Electronics'),
(4, 'Fashion & Accessories'),
(3, 'Lifestyle Goods'),
(5, 'Sports');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `message_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `sender_id` int(10) UNSIGNED NOT NULL,
  `receiver_id` int(10) UNSIGNED NOT NULL,
  `message_text` varchar(500) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`message_id`, `product_id`, `sender_id`, `receiver_id`, `message_text`, `is_read`, `created_at`) VALUES
(20, 14, 12, 11, 'hi', 1, '2026-09-02 14:19:19'),
(21, 14, 13, 11, 'hi', 1, '2026-09-02 14:19:37'),
(22, 14, 12, 11, 'i want', 1, '2026-09-02 14:20:37'),
(23, 14, 13, 11, 'i want this too', 1, '2026-09-02 14:27:21'),
(24, 14, 11, 13, 'ok', 1, '2026-09-02 14:28:10'),
(25, 14, 11, 12, 'got it', 1, '2026-09-02 14:28:16'),
(26, 14, 12, 11, 'sure?', 1, '2026-09-02 14:28:45'),
(27, 14, 13, 11, 'how much', 1, '2026-09-02 14:29:32'),
(28, 14, 13, 11, 'can cheaper?', 1, '2026-09-02 14:29:37'),
(29, 14, 11, 13, 'nono不能', 1, '2026-09-02 14:32:42'),
(30, 14, 11, 13, '哈哈哈', 1, '2026-09-02 14:33:12'),
(31, 14, 12, 11, 'can ?', 1, '2026-09-02 14:43:20'),
(32, 14, 13, 11, 'why', 1, '2026-09-02 14:43:34'),
(33, 14, 13, 11, 'hello', 1, '2026-09-02 14:51:42'),
(34, 14, 13, 11, 'reply me', 1, '2026-09-02 14:51:47'),
(35, 14, 13, 11, 'leh', 1, '2026-09-02 14:51:48'),
(36, 15, 11, 13, 'hii', 1, '2026-09-02 15:39:11'),
(37, 16, 12, 14, 'hi', 1, '2026-09-02 16:41:43'),
(38, 15, 14, 13, 'hihiihhi', 1, '2026-09-02 16:43:21'),
(39, 15, 13, 14, 'gghrt', 1, '2026-09-02 16:43:59'),
(40, 13, 14, 11, 'gfhd', 1, '2026-09-02 16:44:20'),
(41, 13, 13, 11, 'hi', 1, '2026-09-02 22:12:47'),
(42, 13, 11, 13, 'yes', 1, '2026-09-02 22:13:33'),
(43, 15, 14, 13, 'hello world', 1, '2026-09-02 23:21:20'),
(44, 13, 12, 11, 'hi', 1, '2026-09-04 06:30:18'),
(45, 13, 11, 12, 'hioo', 1, '2026-09-04 06:30:50'),
(46, 13, 12, 11, 'hi', 1, '2026-09-06 07:12:19'),
(47, 18, 12, 14, 'hi', 1, '2026-09-06 07:45:54'),
(48, 18, 12, 14, 'can cheap?', 1, '2026-09-06 07:45:58'),
(49, 13, 12, 11, 'sada', 1, '2026-09-06 08:49:10'),
(50, 18, 12, 14, 'dghfhfg', 1, '2026-09-06 09:11:58'),
(51, 25, 14, 13, 'hi', 1, '2026-09-06 09:14:01'),
(52, 32, 12, 15, 'hi', 1, '2026-09-14 06:32:32'),
(53, 32, 15, 12, 'hey', 1, '2026-09-14 06:33:34'),
(54, 19, 11, 13, 'hi', 0, '2026-09-20 07:00:11'),
(55, 33, 14, 15, 'hi', 0, '2026-09-22 05:46:52'),
(56, 33, 14, 15, 'i want to know more about this item.', 0, '2026-09-22 05:47:13');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(10) UNSIGNED NOT NULL,
  `order_ref` varchar(50) NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `buyer_id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED NOT NULL,
  `price_amount` decimal(10,2) NOT NULL,
  `handover_location` varchar(150) NOT NULL,
  `status` enum('Awaiting Handover','Completed','Cancelled') NOT NULL DEFAULT 'Awaiting Handover',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `order_ref`, `product_id`, `buyer_id`, `seller_id`, `price_amount`, `handover_location`, `status`, `created_at`, `updated_at`) VALUES
(3, 'ORD-20260901-7418863A', 12, 13, 11, 30.00, 'Student Lounge', 'Completed', '2026-09-01 10:19:33', '2026-09-01 10:26:28'),
(4, 'ORD-20260902-3E5BFC22', 13, 12, 11, 20.00, 'Student Lounge', 'Cancelled', '2026-09-02 15:04:25', '2026-09-02 15:21:27'),
(5, 'ORD-20260902-CF10A02A', 14, 12, 11, 15.00, 'Student Lounge', 'Completed', '2026-09-02 15:06:47', '2026-09-02 15:27:57'),
(6, 'ORD-20260902-61D621DA', 13, 13, 11, 20.00, 'Student Lounge', 'Cancelled', '2026-09-02 15:23:05', '2026-09-02 15:27:32'),
(7, 'ORD-20260903-D8F55441', 16, 11, 14, 30.00, 'Student Lounge', 'Cancelled', '2026-09-02 22:46:13', '2026-09-02 22:46:22'),
(8, 'ORD-20260903-7B36D1EF', 13, 13, 11, 20.00, 'Student Lounge', 'Cancelled', '2026-09-02 22:55:41', '2026-09-02 22:56:19'),
(9, 'ORD-20260903-462A4150', 16, 11, 14, 30.00, 'Student Lounge', 'Completed', '2026-09-02 23:18:29', '2026-09-02 23:18:47'),
(10, 'ORD-20260903-366BCC10', 13, 14, 11, 20.00, 'Student Lounge', 'Cancelled', '2026-09-02 23:19:42', '2026-09-02 23:19:58'),
(11, 'ORD-20260904-FC7C562C', 15, 11, 13, 50.00, 'Student Lounge', 'Cancelled', '2026-09-04 06:38:45', '2026-09-06 07:59:10'),
(12, 'ORD-20260906-26BD70BF', 13, 12, 11, 20.00, 'Student Lounge', 'Cancelled', '2026-09-06 07:34:03', '2026-09-06 07:34:28'),
(13, 'ORD-20260906-7BAB2DF5', 13, 14, 11, 20.00, 'Student Lounge', 'Cancelled', '2026-09-06 07:35:47', '2026-09-06 07:36:00'),
(14, 'ORD-20260906-91C593E7', 13, 14, 11, 20.00, 'Student Lounge', 'Cancelled', '2026-09-06 07:56:03', '2026-09-06 07:59:59'),
(15, 'ORD-20260914-0BB342D8', 29, 12, 15, 45.00, 'Student Lounge', 'Completed', '2026-09-14 06:34:34', '2026-09-14 06:47:36'),
(16, 'ORD-20260914-21DBE6BE', 32, 12, 15, 26.00, 'Student Lounge', 'Completed', '2026-09-14 06:50:44', '2026-09-14 06:52:22'),
(17, 'ORD-20260914-F0E565D4', 30, 12, 15, 50.00, 'Student Lounge', 'Cancelled', '2026-09-14 06:51:47', '2026-09-14 06:52:19'),
(18, 'ORD-20260920-0BAA845C', 36, 11, 15, 99.00, 'Student Lounge', 'Awaiting Handover', '2026-09-20 06:57:58', '2026-09-20 06:57:58');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED NOT NULL,
  `university_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `item_condition` enum('Brand New','Like New','Well Used','For Parts') NOT NULL,
  `status` enum('Available','Sold','Deleted') NOT NULL DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `seller_id`, `university_id`, `category_id`, `title`, `description`, `price`, `item_condition`, `status`, `created_at`, `updated_at`) VALUES
(12, 11, 1, 1, 'python', 'python book for beginner', 30.00, 'Well Used', 'Sold', '2026-09-01 07:53:09', '2026-09-01 10:19:33'),
(13, 11, 1, 5, 'football', 'nike football condition new', 20.00, 'Brand New', 'Deleted', '2026-09-01 10:40:57', '2026-09-06 08:49:31'),
(14, 11, 1, 4, 'navy shirt', 'navy shirt clean fit', 15.00, 'Well Used', 'Sold', '2026-09-01 10:41:59', '2026-09-02 15:06:47'),
(15, 13, 2, 2, 'smart phone', 'this is honor smartphone', 50.00, 'Well Used', 'Deleted', '2026-09-02 15:38:33', '2026-09-14 06:58:40'),
(16, 14, 3, 3, 'pan cooker', 'pan cooker with good condition', 30.00, 'Like New', 'Sold', '2026-09-02 16:41:07', '2026-09-02 23:18:29'),
(17, 11, 1, 2, 'cooker', 'cooker gooddddddddddddddddddddd', 40.00, 'Well Used', 'Available', '2026-09-04 06:32:57', '2026-09-04 06:32:57'),
(18, 14, 3, 1, 'Java Book', 'java programming books', 15.00, 'Brand New', 'Available', '2026-09-06 07:44:31', '2026-09-06 07:44:31'),
(19, 13, 2, 2, 'stand fan', 'stand fan with good condition. very strong wind', 40.00, 'Well Used', 'Available', '2026-09-06 07:51:22', '2026-09-06 07:51:22'),
(20, 13, 2, 4, 'women shirt', '啊啊啊啊啊啊啊啊啊啊啊啊啊啊啊啊1231231232312', 60.00, 'Well Used', 'Available', '2026-09-06 07:54:38', '2026-09-06 07:54:38'),
(21, 11, 1, 3, 'chair', 'comfortable chair with very good condition', 35.00, 'Like New', 'Available', '2026-09-06 07:58:28', '2026-09-06 07:58:28'),
(22, 14, 3, 3, 'portable table', 'portable table, very convince', 27.00, 'Well Used', 'Available', '2026-09-06 08:02:19', '2026-09-06 08:02:19'),
(23, 14, 3, 2, 'speaker', 'speaker for tv, pc, computer', 100.00, 'Brand New', 'Available', '2026-09-06 08:06:45', '2026-09-06 08:06:45'),
(24, 13, 2, 2, 'rice cooker', 'rice cooker with multi-function', 125.00, 'Well Used', 'Available', '2026-09-06 08:09:35', '2026-09-06 08:09:35'),
(25, 13, 2, 2, 'stir fried pan cooker', 'stir fried pan cooker', 99.00, 'For Parts', 'Deleted', '2026-09-06 08:11:27', '2026-09-10 16:43:32'),
(26, 11, 1, 3, 'bottle', 'water bottle with good quality', 10.00, 'Like New', 'Deleted', '2026-09-06 08:13:48', '2026-09-08 09:02:25'),
(27, 11, 1, 2, 'teapot', 'teapot from USA. Very durable.', 30.00, 'For Parts', 'Deleted', '2026-09-06 08:16:42', '2026-09-06 08:48:16'),
(28, 15, 5, 3, 'nike basketball', 'nike basketball with very good condition', 60.00, 'Like New', 'Deleted', '2026-09-14 06:08:58', '2026-09-14 07:31:00'),
(29, 15, 5, 3, 'tennis racket', 'wilson steam 105 tennis racket', 45.00, 'Well Used', 'Sold', '2026-09-14 06:12:35', '2026-09-14 06:34:34'),
(30, 15, 5, 3, 'portable sofa', 'Portable Inflatable Sofa', 50.00, 'For Parts', 'Deleted', '2026-09-14 06:15:07', '2026-09-14 07:44:00'),
(31, 15, 5, 2, 'computer monitor', 'ASUS 24-inch monitor', 300.00, 'Brand New', 'Available', '2026-09-14 06:18:04', '2026-09-14 06:18:04'),
(32, 15, 5, 1, 'Marvel comics', 'Marvel comics, English comics', 26.00, 'Well Used', 'Sold', '2026-09-14 06:21:29', '2026-09-14 06:50:44'),
(33, 15, 5, 3, 'montigo bottle', 'Montigo-18oz-Ace-Bottle-Medium-(530ml)', 100.00, 'Brand New', 'Available', '2026-09-14 07:50:44', '2026-09-14 07:50:44'),
(34, 14, 3, 4, 'nike shoes', 'brooks-koepka-debuts-stunning-louis-vuitton-x-nike-sneakers', 250.00, 'Well Used', 'Deleted', '2026-09-14 07:53:44', '2026-09-14 09:28:28'),
(35, 14, 3, 4, 'handbag', 'women-s-leather_________________________', 189.00, 'Well Used', 'Available', '2026-09-14 07:55:51', '2026-09-14 07:55:51'),
(36, 15, 5, 2, 'smart watch', 'best-apple-deals-of-the-week', 99.00, 'For Parts', 'Sold', '2026-09-14 07:57:54', '2026-09-20 06:57:58'),
(37, 11, 1, 4, 'sunglass', 'sunglass for men and women', 69.00, 'Well Used', 'Available', '2026-09-14 08:00:10', '2026-09-14 08:00:10');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `image_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`image_id`, `product_id`, `image_url`, `is_primary`, `created_at`) VALUES
(13, 12, 'uploads/products/1788249189_0_cover_image.jpg', 1, '2026-09-01 07:53:09'),
(14, 12, 'uploads/products/1788249189_1_OIP.webp', 0, '2026-09-01 07:53:09'),
(15, 13, 'uploads/products/1788259257_0_OIP__1_.webp', 1, '2026-09-01 10:40:57'),
(16, 13, 'uploads/products/1788259257_1_skysports-premier-league-ball_6831748.jpg', 0, '2026-09-01 10:40:57'),
(17, 14, 'uploads/products/1788259319_0_OIP__2_.webp', 1, '2026-09-01 10:41:59'),
(18, 15, 'uploads/products/1788363513_0_OIP__3_.webp', 1, '2026-09-02 15:38:33'),
(19, 15, 'uploads/products/1788363513_1_1758515178252f9cbb0ca5d3f4b09b1764bc83483f879.png', 0, '2026-09-02 15:38:33'),
(20, 16, 'uploads/products/1788367267_0_5-l-vinod-outer-lid-pressure-cooker-1000x1000.webp', 1, '2026-09-02 16:41:07'),
(21, 16, 'uploads/products/1788367267_1_OIP__4_.webp', 0, '2026-09-02 16:41:07'),
(22, 17, 'uploads/products/1788503577_0_419vqBIvG2L.jpg', 1, '2026-09-04 06:32:57'),
(23, 17, 'uploads/products/1788503577_1_71Hh3g-wbhL._AC_.jpg', 0, '2026-09-04 06:32:57'),
(24, 18, 'uploads/products/1788680671_0_51xe6s8vSAL.jpg', 1, '2026-09-06 07:44:31'),
(25, 18, 'uploads/products/1788680671_1_51xiwRkS2oL.jpg', 0, '2026-09-06 07:44:31'),
(26, 18, 'uploads/products/1788680671_2_OIP.webp', 0, '2026-09-06 07:44:31'),
(27, 19, 'uploads/products/1788681082_0_OIP__1_.webp', 1, '2026-09-06 07:51:22'),
(28, 19, 'uploads/products/1788681082_1_heller_pedestal_fan_brushed_ch_1660707371_f8252256.jpg', 0, '2026-09-06 07:51:22'),
(29, 20, 'uploads/products/1788681278_0_a502b2_ba6dc272ca784421a55b5c2d83ca6bb3_mv2.jpg', 1, '2026-09-06 07:54:38'),
(30, 21, 'uploads/products/1788681508_0_OIP__2_.webp', 1, '2026-09-06 07:58:28'),
(31, 21, 'uploads/products/1788681508_1_71W145IfhAL._AC_SL1500_.jpg', 0, '2026-09-06 07:58:28'),
(32, 22, 'uploads/products/1788681739_0_51a45306-b5c6-4656-9080-5c885ea0ada5_3.f8f0462e9b8ecb40d6b4f0d4fd2b396f.webp', 1, '2026-09-06 08:02:19'),
(33, 22, 'uploads/products/1788681739_1_TAB24_1_4FTHEAVYDUTYFOLDINGTABLEPORTABLEPLASTICNEWCAMPINGGARDENPARTYCATERING.webp', 0, '2026-09-06 08:02:19'),
(34, 23, 'uploads/products/1788682005_0_81iDCnwWeOL._AC_SX425_.jpg', 1, '2026-09-06 08:06:45'),
(35, 23, 'uploads/products/1788682005_1_81b1vgAABmL.jpg', 0, '2026-09-06 08:06:45'),
(36, 24, 'uploads/products/1788682175_0_OIP__1_.webp', 1, '2026-09-06 08:09:35'),
(37, 24, 'uploads/products/1788682175_1_OIP.webp', 0, '2026-09-06 08:09:35'),
(38, 24, 'uploads/products/1788682175_2_829486178245_01-2-scaled.jpg', 0, '2026-09-06 08:09:35'),
(39, 25, 'uploads/products/1788682287_0_OVENTE-Electric-Skillet-and-Frying-Pan-12-Round-Cooker-with-Nonstick-Coating-Black-SK11112B_423ccbbc-2cd6-472e-a176-af775532d427.b7641406bc8ed3c4a52623b8f8ec758b.webp', 1, '2026-09-06 08:11:27'),
(40, 25, 'uploads/products/1788682287_1_71mBRe44jML._SL1500_.jpg', 0, '2026-09-06 08:11:27'),
(41, 26, 'uploads/products/1788682428_0_614sXIjZB9L.jpg', 1, '2026-09-06 08:13:48'),
(42, 27, 'uploads/products/1788682602_0_dssh003_04.webp', 1, '2026-09-06 08:16:42'),
(43, 28, 'uploads/products/1789366138_1_OIP.webp', 1, '2026-09-14 06:08:58'),
(44, 28, 'uploads/products/1789366138_0_realistic-basketball-ball-transparent-background-png.webp', 0, '2026-09-14 06:08:58'),
(46, 29, 'uploads/products/1789366355_0_wi_ra_002024_large.jpg', 1, '2026-09-14 06:12:35'),
(47, 29, 'uploads/products/1789366355_1_OIP__1_.webp', 0, '2026-09-14 06:12:35'),
(48, 30, 'uploads/products/1789366507_0_Inflatable-Sofa-Bed-Foldable-Thickened-Flocked-PVC-Blow-Up-Sofa-5-in-1-Air-Couch-Mattress-For-Camping-Pump-Not-Included_2bd8ecae-6be1-457c-8ab0-507de0346ec8.9c9e8a8a44beb2aa40bbff3c5ceb642d.webp', 1, '2026-09-14 06:15:07'),
(49, 30, 'uploads/products/1789366507_1_260212120848_67f05b6747d7f129df9c20ba11b9168b.jpeg', 0, '2026-09-14 06:15:07'),
(50, 31, 'uploads/products/1789366684_0_24inmonitors-2048px-232071-3x2-1-1.webp', 0, '2026-09-14 06:18:04'),
(51, 31, 'uploads/products/1789366684_1_27-inch-monitor-2048px-233796-3x2-1-1.jpg', 1, '2026-09-14 06:18:04'),
(52, 31, 'uploads/products/1789366684_2_u7J39qNGGSHcJjJCTeUuiR.jpg', 0, '2026-09-14 06:18:04'),
(53, 32, 'uploads/products/1789366889_0_Marvel_Comics_Vol_1_1000.webp', 0, '2026-09-14 06:21:29'),
(54, 32, 'uploads/products/1789366889_1_marvel-comics.jpg', 1, '2026-09-14 06:21:29'),
(55, 33, 'uploads/products/1789372244_0_MTAce18ozMTStoneGrey.webp', 0, '2026-09-14 07:50:44'),
(56, 33, 'uploads/products/1789372244_1_my-11134207-7r98s-lwfj5d2fzfxm6d.jpg', 1, '2026-09-14 07:50:44'),
(57, 34, 'uploads/products/1789372424_0_QL7KT5eCXfEjjVimJFH7rg-415-80.jpg', 1, '2026-09-14 07:53:44'),
(58, 35, 'uploads/products/1789372551_0_Women-Bag-Pu-Leather-Tote-Brand-Name-Bag-Luxury-Ladies-Handbag-Lady-Bags-Solid-Female-Messenger.webp', 1, '2026-09-14 07:55:51'),
(59, 35, 'uploads/products/1789372551_1_2016-Modern-Style-Fashion-Women-Handbag-Famous-Designer-Brand-Bags-Pu-Leather-Tote-Bag-Vintage-Single.webp', 0, '2026-09-14 07:55:51'),
(60, 36, 'uploads/products/1789372674_0_apple-watch-se-blue.webp', 1, '2026-09-14 07:57:54'),
(61, 36, 'uploads/products/1789372674_1_smartwatch-selection-guide-choosing-the-right-one-1704802642.jpg', 0, '2026-09-14 07:57:54'),
(62, 37, 'uploads/products/1789372810_0_OIP__1_.webp', 1, '2026-09-14 08:00:10'),
(63, 37, 'uploads/products/1789372810_1_OIP.webp', 0, '2026-09-14 08:00:10');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `reviewer_id` int(10) UNSIGNED NOT NULL,
  `reviewee_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`review_id`, `order_id`, `reviewer_id`, `reviewee_id`, `rating`, `comment`, `created_at`) VALUES
(2, 3, 13, 11, 2, NULL, '2026-09-02 13:50:18'),
(3, 5, 12, 11, 5, 'seller is good', '2026-09-02 15:29:28'),
(4, 9, 11, 14, 4, NULL, '2026-09-02 23:18:53'),
(5, 15, 12, 15, 5, 'condition very good.', '2026-09-14 06:48:00');

-- --------------------------------------------------------

--
-- Table structure for table `seller_listing_notification_reads`
--

CREATE TABLE `seller_listing_notification_reads` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `last_seen_log_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seller_listing_notification_reads`
--

INSERT INTO `seller_listing_notification_reads` (`user_id`, `last_seen_log_id`, `updated_at`) VALUES
(11, 6, '2026-09-14 07:44:40'),
(13, 17, '2026-09-14 07:38:37'),
(14, 22, '2026-09-14 09:30:28'),
(15, 21, '2026-09-14 07:44:20');

-- --------------------------------------------------------

--
-- Table structure for table `universities`
--

CREATE TABLE `universities` (
  `university_id` int(10) UNSIGNED NOT NULL,
  `university_name` varchar(150) NOT NULL,
  `email_domain` varchar(100) NOT NULL,
  `theme_color` varchar(20) DEFAULT '#0056b3',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `universities`
--

INSERT INTO `universities` (`university_id`, `university_name`, `email_domain`, `theme_color`, `created_at`) VALUES
(1, 'New Era University College', 'student.newera.edu.my', '#0056b3', '2026-08-17 08:02:16'),
(2, 'Tunku Abdul Rahman University of Management and Technology', 'student.tarc.edu.my', '#c8102e', '2026-08-17 08:02:16'),
(3, 'University Tunku Abdul Rahman', 'student.utar.edu.my', '#b9259f', '2026-08-17 00:02:16'),
(5, 'Taylor\'s University', 'student.taylor.edu.my', '#ff8c00', '2026-09-11 07:44:18');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `university_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `role` enum('student','admin') NOT NULL DEFAULT 'student',
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `default_handover_location` varchar(150) DEFAULT NULL,
  `trust_score` decimal(3,2) NOT NULL DEFAULT 5.00,
  `completed_trades` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `warning_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `university_id`, `full_name`, `student_id`, `email`, `phone_number`, `password_hash`, `avatar_url`, `role`, `status`, `default_handover_location`, `trust_score`, `completed_trades`, `warning_count`, `created_at`, `updated_at`) VALUES
(1, 1, 'Platform Administrator', 'ADMIN-00001', 'admin@campus-exchange.edu', '0123456789', '$2y$10$5JFbY3DZ0wMyFJfJwbJZTuvfc4FR9fCEuHSEVPwDTJ2hGb.kM2woW', NULL, 'admin', 'active', 'Admin Office', 5.00, 0, 0, '2026-08-17 08:35:15', '2026-08-17 08:35:15'),
(11, 1, 'Andy lee', '2470034', 'andy.lee@student.newera.edu.my', '0111100110', '$2y$10$sn.SQCSWjIaI06ClVW21feATUevOgFY16NASjrSg0Etsk.1PSqxJG', NULL, 'student', 'active', NULL, 3.50, 2, 0, '2026-09-01 07:05:48', '2026-09-11 07:05:06'),
(12, 1, 'Andy ng', '2470089', 'andy.ng@student.newera.edu.my', '0123456789', '$2y$10$WBWAMkMU35Gux4D7w9dKTeH6DefhKYBD4s1BrTc5rzr/0LRgsjxU6', NULL, 'student', 'active', NULL, 5.00, 0, 2, '2026-09-01 07:47:02', '2026-09-11 07:58:03'),
(13, 2, 'Andy lim', '2470067', 'andy.lim@student.tarc.edu.my', '0123456798', '$2y$10$GdO976iSTzVRmd3d4kpGsuSWPAX9UDDOq3qPUCTtBk6XKu8spcQQ.', NULL, 'student', 'active', NULL, 5.00, 0, 1, '2026-09-01 09:43:09', '2026-09-14 07:38:17'),
(14, 3, 'Andy tan', '2470055', 'andy.tan@student.utar.edu.my', '0123456987', '$2y$10$FQsff231L0zbiFP8lstVJ.iIaWG7tvoP1CjTjzs/FkHnxNCQjuDQu', NULL, 'student', 'active', NULL, 4.00, 1, 3, '2026-09-02 15:59:59', '2026-09-10 16:40:39'),
(15, 5, 'Andy low', '24B0066', 'andy.low@student.taylor.edu.my', '0123456765', '$2y$10$/X2co/yCDiO.7uc926/cJ.fE2JJC1FZeFb5B4fy93LX6Cym8K3S1q', NULL, 'student', 'active', NULL, 5.00, 2, 1, '2026-09-11 07:45:50', '2026-09-14 07:42:50'),
(16, 5, 'andy wong', '24B0045', 'andy.wong@student.taylor.edu.my', '0129876535', '$2y$10$xrk.3J6DlgXFEUjYfMTUluaeFl9odoYIC9NSmkIwxC9dTi38hom7q', NULL, 'student', 'active', NULL, 5.00, 0, 0, '2026-09-14 09:24:29', '2026-09-14 09:24:29');

-- --------------------------------------------------------

--
-- Table structure for table `wallets`
--

CREATE TABLE `wallets` (
  `wallet_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wallets`
--

INSERT INTO `wallets` (`wallet_id`, `user_id`, `balance`, `updated_at`) VALUES
(11, 11, 16.00, '2026-09-20 06:57:58'),
(12, 12, 64.00, '2026-09-14 06:52:19'),
(13, 13, 20.00, '2026-09-02 22:56:19'),
(14, 14, 30.00, '2026-09-06 07:59:59'),
(15, 15, 121.00, '2026-09-14 06:52:22'),
(16, 16, 0.00, '2026-09-14 09:24:29');

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `transaction_id` int(10) UNSIGNED NOT NULL,
  `transaction_ref` varchar(50) NOT NULL,
  `wallet_id` int(10) UNSIGNED NOT NULL,
  `transaction_type` enum('TOPUP','WITHDRAWAL','ESCROW_HOLD','ESCROW_RELEASE','REFUND') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `resulting_balance` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wallet_transactions`
--

INSERT INTO `wallet_transactions` (`transaction_id`, `transaction_ref`, `wallet_id`, `transaction_type`, `amount`, `resulting_balance`, `created_at`) VALUES
(1, 'TXN-20260901-1037', 13, 'TOPUP', 50.00, 50.00, '2026-09-01 10:13:26'),
(2, 'TXN-20260901-6890', 12, 'TOPUP', 30.00, 30.00, '2026-09-01 10:17:53'),
(3, 'TXN-20260901-F1C04F11', 13, 'ESCROW_HOLD', -30.00, 20.00, '2026-09-01 10:19:33'),
(4, 'TXN-20260901-3B74CB3C', 11, 'ESCROW_RELEASE', 30.00, 30.00, '2026-09-01 10:26:28'),
(5, 'TXN-20260902-BD9FB2D9', 12, 'ESCROW_HOLD', -20.00, 10.00, '2026-09-02 15:04:25'),
(6, 'TXN-20260902-5532', 12, 'TOPUP', 10.00, 20.00, '2026-09-02 15:06:17'),
(7, 'TXN-20260902-2D775A91', 12, 'ESCROW_HOLD', -15.00, 5.00, '2026-09-02 15:06:47'),
(8, 'TXN-20260902-503D7D73', 12, 'REFUND', 20.00, 25.00, '2026-09-02 15:21:27'),
(9, 'TXN-20260902-9013B25F', 13, 'ESCROW_HOLD', -20.00, 0.00, '2026-09-02 15:23:05'),
(10, 'TXN-20260902-0AD0E96E', 13, 'REFUND', 20.00, 20.00, '2026-09-02 15:27:32'),
(11, 'TXN-20260902-E696CCCE', 11, 'ESCROW_RELEASE', 15.00, 45.00, '2026-09-02 15:27:57'),
(12, 'TXN-20260903-F5D8CC28', 11, 'ESCROW_HOLD', -30.00, 15.00, '2026-09-02 22:46:13'),
(13, 'TXN-20260903-ADB50D8A', 11, 'REFUND', 30.00, 45.00, '2026-09-02 22:46:22'),
(14, 'TXN-20260903-D49D8F5A', 13, 'ESCROW_HOLD', -20.00, 0.00, '2026-09-02 22:55:41'),
(15, 'TXN-20260903-6105F9AC', 13, 'REFUND', 20.00, 20.00, '2026-09-02 22:56:19'),
(16, 'TXN-20260903-57EDF18C', 11, 'ESCROW_HOLD', -30.00, 15.00, '2026-09-02 23:18:29'),
(17, 'TXN-20260903-E8F14F62', 14, 'ESCROW_RELEASE', 30.00, 30.00, '2026-09-02 23:18:47'),
(18, 'TXN-20260903-0C099101', 14, 'ESCROW_HOLD', -20.00, 10.00, '2026-09-02 23:19:42'),
(19, 'TXN-20260903-5EE2B911', 14, 'REFUND', 20.00, 30.00, '2026-09-02 23:19:58'),
(20, 'TXN-20260904-4659', 11, 'TOPUP', 100.00, 115.00, '2026-09-04 06:37:12'),
(21, 'TXN-20260904-23DD6248', 11, 'ESCROW_HOLD', -50.00, 65.00, '2026-09-04 06:38:45'),
(22, 'TXN-20260906-4F9842E3', 12, 'ESCROW_HOLD', -20.00, 5.00, '2026-09-06 07:34:03'),
(23, 'TXN-20260906-CC5DD524', 12, 'REFUND', 20.00, 25.00, '2026-09-06 07:34:28'),
(24, 'TXN-20260906-6AC030B5', 14, 'ESCROW_HOLD', -20.00, 10.00, '2026-09-06 07:35:47'),
(25, 'TXN-20260906-26AA96A5', 14, 'REFUND', 20.00, 30.00, '2026-09-06 07:36:00'),
(26, 'TXN-20260906-417E458B', 14, 'ESCROW_HOLD', -20.00, 10.00, '2026-09-06 07:56:03'),
(27, 'TXN-20260906-5D5A03B7', 11, 'REFUND', 50.00, 115.00, '2026-09-06 07:59:10'),
(28, 'TXN-20260906-13E68C49', 14, 'REFUND', 20.00, 30.00, '2026-09-06 07:59:59'),
(29, 'TXN-20260911-1104', 15, 'TOPUP', 50.00, 50.00, '2026-09-11 07:46:51'),
(30, 'TXN-20260914-6481', 12, 'TOPUP', 50.00, 75.00, '2026-09-14 06:34:18'),
(31, 'TXN-20260914-68B50FE3', 12, 'ESCROW_HOLD', -45.00, 30.00, '2026-09-14 06:34:34'),
(32, 'TXN-20260914-0E5A1C43', 15, 'ESCROW_RELEASE', 45.00, 95.00, '2026-09-14 06:47:36'),
(33, 'TXN-20260914-45B5EA2B', 12, 'ESCROW_HOLD', -26.00, 4.00, '2026-09-14 06:50:44'),
(34, 'TXN-20260914-6952', 12, 'TOPUP', 10.00, 14.00, '2026-09-14 06:51:10'),
(35, 'TXN-20260914-1919', 12, 'TOPUP', 50.00, 64.00, '2026-09-14 06:51:16'),
(36, 'TXN-20260914-616F9B4D', 12, 'ESCROW_HOLD', -50.00, 14.00, '2026-09-14 06:51:47'),
(37, 'TXN-20260914-0713D1CF', 12, 'REFUND', 50.00, 64.00, '2026-09-14 06:52:19'),
(38, 'TXN-20260914-6A34D2EF', 15, 'ESCROW_RELEASE', 26.00, 121.00, '2026-09-14 06:52:22'),
(39, 'TXN-20260920-54B9BB67', 11, 'ESCROW_HOLD', -99.00, 16.00, '2026-09-20 06:57:58');

-- --------------------------------------------------------

--
-- Table structure for table `watchlists`
--

CREATE TABLE `watchlists` (
  `watchlist_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `watchlists`
--

INSERT INTO `watchlists` (`watchlist_id`, `user_id`, `product_id`, `created_at`) VALUES
(5, 13, 12, '2026-09-01 10:14:52'),
(6, 12, 12, '2026-09-01 10:15:13'),
(10, 13, 17, '2026-09-06 08:34:08'),
(11, 13, 21, '2026-09-06 08:34:14'),
(12, 13, 13, '2026-09-06 08:34:15'),
(13, 16, 35, '2026-09-14 09:27:18'),
(14, 16, 37, '2026-09-14 09:27:19'),
(15, 16, 34, '2026-09-14 09:27:21');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_recovery_requests`
--
ALTER TABLE `account_recovery_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_recovery_user` (`user_id`),
  ADD KEY `idx_recovery_status` (`status`),
  ADD KEY `fk_recovery_admin` (`reviewed_by`);

--
-- Indexes for table `account_warning_reads`
--
ALTER TABLE `account_warning_reads`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_admin_id` (`admin_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name_unique` (`category_name`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_sender_id` (`sender_id`),
  ADD KEY `idx_receiver_id` (`receiver_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD UNIQUE KEY `order_ref_unique` (`order_ref`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_buyer_id` (`buyer_id`),
  ADD KEY `idx_seller_id` (`seller_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `idx_seller_id` (`seller_id`),
  ADD KEY `idx_university_id` (`university_id`),
  ADD KEY `idx_category_id` (`category_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `order_id_unique` (`order_id`),
  ADD KEY `idx_reviewer_id` (`reviewer_id`),
  ADD KEY `idx_reviewee_id` (`reviewee_id`);

--
-- Indexes for table `seller_listing_notification_reads`
--
ALTER TABLE `seller_listing_notification_reads`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `universities`
--
ALTER TABLE `universities`
  ADD PRIMARY KEY (`university_id`),
  ADD UNIQUE KEY `email_domain_unique` (`email_domain`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `student_id_unique` (`student_id`),
  ADD UNIQUE KEY `email_unique` (`email`),
  ADD KEY `idx_university_id` (`university_id`);

--
-- Indexes for table `wallets`
--
ALTER TABLE `wallets`
  ADD PRIMARY KEY (`wallet_id`),
  ADD UNIQUE KEY `user_id_unique` (`user_id`);

--
-- Indexes for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD UNIQUE KEY `transaction_ref_unique` (`transaction_ref`),
  ADD KEY `idx_wallet_id` (`wallet_id`);

--
-- Indexes for table `watchlists`
--
ALTER TABLE `watchlists`
  ADD PRIMARY KEY (`watchlist_id`),
  ADD UNIQUE KEY `unique_user_product` (`user_id`,`product_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_recovery_requests`
--
ALTER TABLE `account_recovery_requests`
  MODIFY `request_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `message_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `image_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `universities`
--
ALTER TABLE `universities`
  MODIFY `university_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `wallets`
--
ALTER TABLE `wallets`
  MODIFY `wallet_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `transaction_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `watchlists`
--
ALTER TABLE `watchlists`
  MODIFY `watchlist_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `account_recovery_requests`
--
ALTER TABLE `account_recovery_requests`
  ADD CONSTRAINT `fk_recovery_admin` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_recovery_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `account_warning_reads`
--
ALTER TABLE `account_warning_reads`
  ADD CONSTRAINT `fk_account_warning_reads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `fk_admin_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `fk_chat_messages_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_chat_messages_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_chat_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_orders_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  ADD CONSTRAINT `fk_orders_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  ADD CONSTRAINT `fk_products_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_products_university` FOREIGN KEY (`university_id`) REFERENCES `universities` (`university_id`);

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_reviews_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_reviewee` FOREIGN KEY (`reviewee_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_reviews_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `seller_listing_notification_reads`
--
ALTER TABLE `seller_listing_notification_reads`
  ADD CONSTRAINT `fk_listing_notification_reads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_university` FOREIGN KEY (`university_id`) REFERENCES `universities` (`university_id`);

--
-- Constraints for table `wallets`
--
ALTER TABLE `wallets`
  ADD CONSTRAINT `fk_wallets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `fk_wallet_transactions_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`wallet_id`) ON DELETE CASCADE;

--
-- Constraints for table `watchlists`
--
ALTER TABLE `watchlists`
  ADD CONSTRAINT `fk_watchlists_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_watchlists_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
