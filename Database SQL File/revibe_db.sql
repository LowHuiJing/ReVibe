-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 11, 2026 at 07:27 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `revibe_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `banned_words`
--

CREATE TABLE `banned_words` (
  `id` int(11) NOT NULL,
  `word` varchar(100) NOT NULL,
  `added_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `banned_words`
--

INSERT INTO `banned_words` (`id`, `word`, `added_at`) VALUES
(1, 'fuck', '2026-04-09 00:36:42'),
(2, 'shit', '2026-04-09 00:36:42'),
(3, 'damn', '2026-04-09 00:36:42'),
(4, 'idiot', '2026-04-09 00:36:42'),
(5, 'stupid', '2026-04-09 00:36:42'),
(6, 'bitch', '2026-04-09 00:36:42'),
(7, 'ass', '2026-04-09 00:36:42'),
(8, 'hell', '2026-04-09 00:36:42'),
(9, 'crap', '2026-04-09 00:36:42'),
(10, 'moron', '2026-04-09 00:36:42'),
(11, 'bodoh', '2026-04-09 00:36:42'),
(12, 'sial', '2026-04-09 00:36:42'),
(13, 'celaka', '2026-04-09 00:36:42'),
(14, 'bangsat', '2026-04-09 00:36:42'),
(15, 'scam', '2026-04-09 00:36:42'),
(16, 'penipu', '2026-04-09 00:36:42'),
(17, 'fraud', '2026-04-09 00:36:42');

-- --------------------------------------------------------

--
-- Table structure for table `blocked_users`
--

CREATE TABLE `blocked_users` (
  `id` int(11) NOT NULL,
  `blocker_id` int(10) UNSIGNED NOT NULL,
  `blocked_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`) VALUES
(1, NULL, 'Electronics', 'electronics'),
(2, NULL, 'Fashion', 'fashion'),
(3, NULL, 'Home & Living', 'home-living'),
(4, NULL, 'Sports & Outdoor', 'sports-outdoor'),
(5, NULL, 'Books & Stationery', 'books-stationery'),
(6, NULL, 'Health & Beauty', 'health-beauty'),
(7, NULL, 'Toys & Games', 'toys-games'),
(8, NULL, 'Automotive', 'automotive'),
(9, NULL, 'Food & Beverage', 'food-beverage'),
(10, NULL, 'Others', 'others'),
(11, 1, 'Mobile Phones', 'mobile-phones'),
(12, 1, 'Laptops', 'laptops'),
(13, 1, 'Tablets', 'tablets'),
(14, 1, 'Headphones & Earphones', 'headphones-earphones'),
(15, 1, 'Cameras', 'cameras'),
(16, 1, 'Gaming Consoles', 'gaming-consoles'),
(17, 1, 'Smart Watches', 'smart-watches'),
(18, 1, 'Computer Accessories', 'computer-accessories'),
(19, 2, 'Men Clothing', 'men-clothing'),
(20, 2, 'Women Clothing', 'women-clothing'),
(21, 2, 'Shoes', 'shoes'),
(22, 2, 'Bags', 'bags'),
(23, 2, 'Accessories', 'fashion-accessories'),
(24, 2, 'Watches', 'watches'),
(25, 2, 'Jewelry', 'jewelry'),
(26, 3, 'Furniture', 'furniture'),
(27, 3, 'Kitchen Appliances', 'kitchen-appliances'),
(28, 3, 'Home Decor', 'home-decor'),
(29, 3, 'Bedding', 'bedding'),
(30, 3, 'Lighting', 'lighting'),
(31, 4, 'Fitness Equipment', 'fitness-equipment'),
(32, 4, 'Cycling', 'cycling'),
(33, 4, 'Camping & Hiking', 'camping-hiking'),
(34, 4, 'Team Sports', 'team-sports'),
(35, 4, 'Outdoor Accessories', 'outdoor-accessories'),
(36, 5, 'Textbooks', 'textbooks'),
(37, 5, 'Novels', 'novels'),
(38, 5, 'Comics & Manga', 'comics-manga'),
(39, 5, 'Office Supplies', 'office-supplies'),
(40, 5, 'School Supplies', 'school-supplies'),
(41, 6, 'Skincare', 'skincare'),
(42, 6, 'Makeup', 'makeup'),
(43, 6, 'Hair Care', 'hair-care'),
(44, 6, 'Personal Care', 'personal-care'),
(45, 6, 'Perfume', 'perfume'),
(46, 7, 'Board Games', 'board-games'),
(47, 7, 'Action Figures', 'action-figures'),
(48, 7, 'Educational Toys', 'educational-toys'),
(49, 7, 'Remote Control Toys', 'rc-toys'),
(50, 8, 'Car Accessories', 'car-accessories'),
(51, 8, 'Motorcycle Accessories', 'motorcycle-accessories'),
(52, 8, 'Car Electronics', 'car-electronics'),
(53, 8, 'Car Care', 'car-care'),
(54, 9, 'Snacks', 'snacks'),
(55, 9, 'Beverages', 'beverages'),
(56, 9, 'Instant Food', 'instant-food'),
(57, 9, 'Organic Food', 'organic-food'),
(58, 10, 'Collectibles', 'collectibles'),
(59, 10, 'Handmade Items', 'handmade-items'),
(60, 10, 'Second-Hand Items', 'second-hand-items'),
(61, 10, 'Miscellaneous', 'miscellaneous');

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `buyer_id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED NOT NULL,
  `last_message` text DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `buyer_unread` int(11) DEFAULT 0,
  `seller_unread` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`id`, `product_id`, `buyer_id`, `seller_id`, `last_message`, `last_message_at`, `buyer_unread`, `seller_unread`, `created_at`) VALUES
(15, 15, 9, 1, 'mm okey wait ah', '2026-04-12 01:21:18', 0, 0, '2026-04-12 01:18:56');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(10) UNSIGNED NOT NULL,
  `body` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `reply_to_id` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `is_flagged` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `conversation_id`, `sender_id`, `body`, `image_url`, `reply_to_id`, `is_deleted`, `is_flagged`, `created_at`) VALUES
(24, 15, 9, 'hi there', '', NULL, 0, 0, '2026-04-12 01:18:59'),
(25, 15, 1, 'yes?', '', NULL, 0, 0, '2026-04-12 01:19:12'),
(26, 15, 9, 'mm okey wait ah', '', NULL, 0, 0, '2026-04-12 01:21:18');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_03_16_071902_create_products_table', 2),
(5, '2026_03_16_071908_create_cart_table', 2);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` enum('message','order','payment','review','offer','product','system') DEFAULT 'system',
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `body`, `link`, `is_read`, `created_at`) VALUES
(1, 3, 'message', 'New message from Sara', 'You have a new message.', '/revibe/messaging.php?conversation_id=1', 0, '2026-04-09 14:25:50'),
(2, 3, 'message', 'New message from Sara', 'You have a new message.', '/revibe/messaging.php?conversation_id=1', 0, '2026-04-09 14:25:58'),
(3, 3, 'message', 'New message from Sara', 'You have a new message.', '/revibe/messaging.php?conversation_id=1', 0, '2026-04-09 14:26:19'),
(4, 4, 'message', 'New message from John', 'You have a new message.', '/revibe/messaging.php?conversation_id=3', 1, '2026-04-09 14:40:17'),
(5, 3, 'message', 'New message from John', 'You have a new message.', '/revibe/messaging.php?conversation_id=4', 0, '2026-04-09 14:40:30'),
(6, 3, 'message', 'New message from John', 'You have a new message.', '/revibe/messaging.php?conversation_id=4', 0, '2026-04-09 14:40:31'),
(7, 3, 'message', 'New message from John', 'You have a new message.', '/revibe/messaging.php?conversation_id=4', 0, '2026-04-09 14:40:38'),
(8, 5, 'message', 'New message from John', 'You have a new message.', '/revibe/messaging.php?conversation_id=6', 0, '2026-04-09 14:43:19'),
(9, 3, 'message', 'New message from John', 'You have a new message.', '/revibe/messaging.php?conversation_id=4', 0, '2026-04-09 16:37:02'),
(10, 3, 'message', 'New message from John', 'You have a new message.', '/revibe/messaging.php?conversation_id=4', 0, '2026-04-10 12:56:44'),
(11, 3, 'message', 'New message from John', 'You have a new message.', '/revibe/messaging.php?conversation_id=7', 0, '2026-04-10 12:58:34'),
(12, 3, 'message', 'New message from John', 'You have a new message.', '/revibe/messaging.php?conversation_id=7', 0, '2026-04-10 12:59:24'),
(13, 3, 'message', 'New message from John', 'You have a new message.', '/revibe/messaging.php?conversation_id=7', 0, '2026-04-10 13:02:23'),
(14, 3, 'message', 'New message from John', 'You have a new message.', '/revibe/messaging.php?conversation_id=7', 0, '2026-04-10 13:04:25'),
(15, 4, 'order', 'Order Confirmed!', 'Your order #1234 has been confirmed.', '/revibe/orders.php', 0, '2026-04-10 15:27:32'),
(16, 4, 'payment', 'Payment Received', 'Payment of RM 46.00 received for Street Jacket.', '/revibe/orders.php', 0, '2026-04-10 15:27:32'),
(17, 4, 'review', 'New Review on your product', 'Someone left a 5-star review!', '/revibe/reviews.php', 1, '2026-04-10 15:27:32'),
(18, 4, 'offer', 'New Offer Received', 'Someone made an offer on Street Sneakers.', '/revibe/product_detail.php?id=2', 0, '2026-04-10 15:27:32'),
(19, 4, 'product', 'Product Approved!', 'Your Street T-Shirt listing has been approved.', '/revibe/product_detail.php?id=3', 1, '2026-04-10 15:27:32'),
(20, 4, 'system', 'Welcome to ReVibe!', 'Start buying and selling preloved fashion.', '/revibe', 1, '2026-04-10 15:27:32'),
(21, 2, 'message', 'New message from Nivi', 'You have a new message.', '/revibe/messaging.php?conversation_id=9', 0, '2026-04-11 22:00:35'),
(22, 2, 'message', 'New message from Nivi', 'You have a new message.', '/revibe/messaging.php?conversation_id=9', 0, '2026-04-11 22:00:44'),
(23, 2, 'message', 'New message from Nivi', 'You have a new message.', '/revibe/messaging.php?conversation_id=9', 0, '2026-04-11 22:22:17'),
(24, 1, 'message', 'New message from Nivi', 'You have a new message.', '/revibe/messaging.php?conversation_id=11', 0, '2026-04-12 00:39:04'),
(25, 1, 'message', 'New message from Nivi', 'You have a new message.', '/revibe/messaging.php?conversation_id=11', 0, '2026-04-12 00:41:27'),
(26, 1, 'message', 'New message from Nivi', 'You have a new message.', '/revibe/messaging.php?conversation_id=11', 0, '2026-04-12 00:52:38'),
(27, 9, 'message', 'New message from john_doe', 'You have a new message.', '/revibe/messaging.php?conversation_id=11', 0, '2026-04-12 00:53:02'),
(28, 1, 'message', 'New message from Nivi', 'You have a new message.', '/revibe/messaging.php?conversation_id=14', 0, '2026-04-12 01:13:26'),
(29, 9, 'message', 'New message from john_doe', 'You have a new message.', '/revibe/messaging.php?conversation_id=14', 0, '2026-04-12 01:13:36'),
(30, 1, 'message', 'New message from Nivi', 'You have a new message.', '/revibe/messaging.php?conversation_id=15', 0, '2026-04-12 01:18:59'),
(31, 9, 'message', 'New message from john_doe', 'You have a new message.', '/revibe/messaging.php?conversation_id=15', 0, '2026-04-12 01:19:12'),
(32, 1, 'message', 'New message from Nivi', 'You have a new message.', '/revibe/messaging.php?conversation_id=15', 1, '2026-04-12 01:21:18');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `seller_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `condition` enum('new','like_new','good','fair','poor') NOT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `status` enum('pending','approved','rejected','sold_out','unlisted') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `seller_id`, `category_id`, `name`, `description`, `price`, `condition`, `stock_quantity`, `status`, `rejection_reason`, `created_at`, `updated_at`) VALUES
(15, 1, 11, 'iPhone 13 Pro 256GB Sierra Blue', 'Used for 1 year, no scratches on screen. Comes with original box, charger, and 2 unused screen protectors. Battery health at 89%. Face ID works perfectly.', 1350.00, 'good', 1, 'approved', NULL, '2026-03-20 08:28:44', '2026-04-11 08:14:30'),
(16, 1, 19, 'Levi\'s 511 Slim Fit Jeans - Size 32x32', 'Worn only a few times. Dark indigo wash, no fading. Great condition, selling because I lost weight. Original price RM299.', 65.00, 'like_new', 2, 'approved', NULL, '2026-03-20 08:28:44', '2026-04-11 05:21:29'),
(17, 1, 12, 'Lenovo ThinkPad X1 Carbon Gen 9', 'Office laptop, barely used. Intel Core i7-1165G7, 16GB RAM, 512GB SSD. Charger included. Minor scuff on lid. Perfect for work or university.', 2800.00, 'good', 1, 'approved', NULL, '2026-03-20 08:28:44', '2026-03-20 08:46:20'),
(18, 1, 14, 'Sony WH-1000XM5 Wireless Headphones', 'Bought 6 months ago. Noise cancellation still works great. Comes with original carrying case, cable, and adapter. Selling because I upgraded.', 620.00, 'like_new', 1, 'pending', NULL, '2026-03-20 08:28:44', '2026-03-20 08:37:48'),
(19, 1, 17, 'Samsung Galaxy Watch 5 Pro 45mm', 'Black titanium, used for 4 months. Comes with original charger. Minor hairline scratch on bezel, not visible during use. Battery lasts 2 days easily.', 480.00, 'good', 1, 'pending', NULL, '2026-03-20 08:28:44', '2026-03-20 08:39:46'),
(20, 1, 16, 'Nintendo Switch OLED White', 'Good condition. Joy-cons included. 3 game cards: Mario Kart 8, Zelda BOTW, Animal Crossing. Dock and all original accessories included.', 950.00, 'good', 1, 'rejected', 'Please take the picture of your product', '2026-03-20 08:28:44', '2026-04-11 11:03:36'),
(21, 1, 21, 'Adidas Ultraboost 22 - Size 42', 'Worn less than 10 times. Core black colorway. No yellowing on sole. Original box included. Selling because size is slightly too big for me.', 220.00, 'like_new', 1, 'unlisted', NULL, '2026-03-20 08:28:44', '2026-03-20 08:36:10'),
(22, 1, 15, 'Canon EOS R50 Mirrorless Camera', 'Used for 6 months for personal photography. Shutter count under 2000. Comes with kit lens 18-45mm, original charger, 2 batteries, and camera bag.', 2200.00, 'good', 0, 'sold_out', NULL, '2026-03-20 08:28:44', '2026-03-20 08:45:44'),
(26, 2, 21, 'Nike Air Force Low White', 'Brand new Nike sneakers, never worn.', 299.00, 'new', 1, 'approved', NULL, '2026-04-11 10:23:13', '2026-04-11 11:10:49');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `image_url` text NOT NULL,
  `media_type` enum('image','video') NOT NULL DEFAULT 'image',
  `is_primary` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image_url`, `media_type`, `is_primary`, `sort_order`, `created_at`) VALUES
(19, 15, 'images/img_69bd05d74d2274.25432555.jpg', 'image', 1, 0, '2026-03-20 08:31:19'),
(20, 15, 'images/img_69bd05d74e3431.83606492.jpg', 'image', 0, 1, '2026-03-20 08:31:19'),
(21, 16, 'images/img_69bd0636bf3739.14086900.webp', 'image', 1, 0, '2026-03-20 08:32:54'),
(22, 17, 'images/img_69bd06a9966509.85123381.jpeg', 'image', 1, 0, '2026-03-20 08:34:49'),
(23, 17, 'images/img_69bd06a996ff67.31951589.webp', 'image', 0, 1, '2026-03-20 08:34:49'),
(24, 17, 'images/img_69bd06a997fbf2.99580444.webp', 'image', 0, 2, '2026-03-20 08:34:49'),
(25, 21, 'images/img_69bd06df92acb6.68443821.jpeg', 'image', 1, 0, '2026-03-20 08:35:43'),
(26, 18, 'images/img_69bd075c5eada8.96072541.avif', 'image', 1, 0, '2026-03-20 08:37:48'),
(27, 18, 'images/img_69bd075c5f4a55.44159317.webp', 'image', 0, 1, '2026-03-20 08:37:48'),
(28, 18, 'images/img_69bd075c606939.71221532.avif', 'image', 0, 2, '2026-03-20 08:37:48'),
(29, 19, 'images/img_69bd07d2ec60a5.12823874.jpg', 'image', 1, 0, '2026-03-20 08:39:46'),
(30, 19, 'images/img_69bd07d2ed0b62.05161286.webp', 'image', 0, 1, '2026-03-20 08:39:46'),
(31, 19, 'images/img_69bd07d2ee6225.94001464.jpg', 'image', 0, 2, '2026-03-20 08:39:46'),
(32, 19, 'images/img_69bd07d2ef2b60.30959470.webp', 'image', 0, 3, '2026-03-20 08:39:46'),
(33, 20, 'images/img_69bd0842ddac73.58026492.jpg', 'image', 1, 0, '2026-03-20 08:41:38'),
(34, 20, 'images/img_69bd0842de0f06.84697698.jpeg', 'image', 0, 1, '2026-03-20 08:41:38'),
(35, 20, 'images/img_69bd0842de5da3.18459212.jpg', 'image', 0, 2, '2026-03-20 08:41:38'),
(37, 22, 'images/img_69bd092c81e4b0.94817360.jpg', 'image', 1, 0, '2026-03-20 08:45:32'),
(65, 26, 'images/img_69da21111a4be1.36300668.jpeg', 'image', 1, 0, '2026-04-11 10:23:13'),
(67, 26, 'images/img_69da2a1a1fa236.22874438.webp', 'image', 0, 1, '2026-04-11 11:01:46'),
(68, 26, 'images/img_69da2a377c7fa7.69862197.webp', 'image', 0, 2, '2026-04-11 11:02:15');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `review_text` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `product_id`, `user_id`, `rating`, `review_text`, `image_url`, `created_at`) VALUES
(23, 1, 6, 5, 'Amazing quality! Exactly as described. Very happy with my purchase.', NULL, '2026-04-10 15:54:41'),
(24, 1, 7, 4, 'Good condition, fast shipping. Would buy again!', NULL, '2026-04-10 15:54:41'),
(25, 1, 8, 3, 'Decent item but color was slightly different from photos.', NULL, '2026-04-10 15:54:41'),
(26, 2, 6, 5, 'Perfect fit and great price. Seller was very responsive.', NULL, '2026-04-10 15:54:41'),
(27, 2, 7, 4, 'Love it! Came well packaged and in great condition.', NULL, '2026-04-10 15:54:41'),
(28, 3, 8, 5, 'Exceeded expectations. Highly recommend this seller!', NULL, '2026-04-10 15:54:41'),
(29, 3, 6, 2, 'Item was okay but took longer than expected to arrive.', NULL, '2026-04-10 15:54:41'),
(30, 2, 4, 4, 'Good', '', '2026-04-10 15:55:09'),
(31, 1, 4, 3, 'Good', '/revibe/images/reviews/review_4_1775807872.jpg', '2026-04-10 15:57:56'),
(32, 1, 9, 5, 'good', '', '2026-04-10 16:04:20'),
(33, 26, 9, 4, 'hey..good', '', '2026-04-11 21:33:33');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('eUDwmDNstfzQAm1QzXvhePrQZ63VBtdTn2YlI1Ap', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 OPR/128.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSTBUS3VBU1B5Q3lveGxIWG9STnl6U01EamZpcGNkaG5MZm13V0xiYSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1773645353);

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
(9, 'Nivi', 'nivi@mail.com', 'dummy', 'user', 1, '2026-04-11 13:45:31', '2026-04-11 13:45:31');
(10, 'admin', 'admin@revibe.com', 'admin123456', 'admin', 1, '2026-04-12 20:36:41', '2026-04-12 20:38:15');

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `banned_words`
--
ALTER TABLE `banned_words`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `word` (`word`);

--
-- Indexes for table `blocked_users`
--
ALTER TABLE `blocked_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_block` (`blocker_id`,`blocked_id`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conversation_id` (`conversation_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_wishlist` (`session_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `banned_words`
--
ALTER TABLE `banned_words`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `blocked_users`
--
ALTER TABLE `blocked_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;


-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE IF NOT EXISTS `user_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `avatar_url` varchar(500) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `website` varchar(300) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_profiles_user_id_unique` (`user_id`),
  CONSTRAINT `user_profiles_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE IF NOT EXISTS `user_settings` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `email_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `sms_notifications` tinyint(1) NOT NULL DEFAULT 0,
  `language` varchar(10) NOT NULL DEFAULT 'en',
  `timezone` varchar(60) NOT NULL DEFAULT 'Asia/Kuala_Lumpur',
  `theme` varchar(20) NOT NULL DEFAULT 'system',
  `privacy_level` enum('public','private') NOT NULL DEFAULT 'public',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_settings_user_id_unique` (`user_id`),
  CONSTRAINT `user_settings_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `status` enum('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  `admin_reply` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `support_tickets_user_id_index` (`user_id`),
  CONSTRAINT `support_tickets_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create empty profile/settings rows for all existing users
INSERT IGNORE INTO `user_profiles` (`user_id`)
SELECT `id` FROM `users`;

INSERT IGNORE INTO `user_settings` (`user_id`)
SELECT `id` FROM `users`;


SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE IF NOT EXISTS `orders` (
  `id`           int(11)       NOT NULL AUTO_INCREMENT,
  `user_id`      bigint(20) UNSIGNED DEFAULT NULL,
  `session_id`   varchar(255)  NOT NULL DEFAULT '',
  `full_name`    varchar(150)  NOT NULL,
  `email`        varchar(150)  NOT NULL DEFAULT '',
  `phone`        varchar(20)   NOT NULL,
  `address`      varchar(255)  NOT NULL,
  `city`         varchar(100)  NOT NULL,
  `state`        varchar(100)  NOT NULL,
  `postcode`     varchar(10)   NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status`       enum('pending','confirmed','processing','shipped','delivered','cancelled','refunded')
                               NOT NULL DEFAULT 'pending',
  `notes`        text          DEFAULT NULL,
  `created_at`   timestamp     NOT NULL DEFAULT current_timestamp(),
  `updated_at`   timestamp     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `ord_users_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE IF NOT EXISTS `order_items` (
  `id`           int(11)       NOT NULL AUTO_INCREMENT,
  `order_id`     int(11)       NOT NULL,
  `product_id`   int(11)       NOT NULL,
  `product_name` varchar(255)  NOT NULL,
  `size`         varchar(20)   DEFAULT NULL,
  `quantity`     int(11)       NOT NULL DEFAULT 1,
  `unit_price`   decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `oi_order_fk`   FOREIGN KEY (`order_id`)   REFERENCES `orders`   (`id`) ON DELETE CASCADE,
  CONSTRAINT `oi_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE IF NOT EXISTS `payments` (
  `id`              int(11)      NOT NULL AUTO_INCREMENT,
  `order_id`        int(11)      NOT NULL,
  `payment_method`  enum('credit_card','debit_card','online_banking','ewallet','cod') NOT NULL,
  `payment_status`  enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `transaction_ref` varchar(100) DEFAULT NULL,
  `amount`          decimal(10,2) NOT NULL,
  `paid_at`         timestamp    NULL DEFAULT NULL,
  `created_at`      timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_order_id` (`order_id`),
  CONSTRAINT `pay_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

CREATE TABLE IF NOT EXISTS `deliveries` (
  `id`               int(11)      NOT NULL AUTO_INCREMENT,
  `order_id`         int(11)      NOT NULL,
  `tracking_number`  varchar(100) DEFAULT NULL,
  `courier`          varchar(100) DEFAULT 'J&T Express',
  `status`           enum('pending','packed','shipped','out_for_delivery','delivered')
                                  NOT NULL DEFAULT 'pending',
  `estimated_date`   date         DEFAULT NULL,
  `delivered_at`     timestamp    NULL DEFAULT NULL,
  `created_at`       timestamp    NOT NULL DEFAULT current_timestamp(),
  `updated_at`       timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `deliveries_order_id` (`order_id`),
  CONSTRAINT `del_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Table structure for table `return_requests`
--

CREATE TABLE IF NOT EXISTS `return_requests` (
  `id`            int(11)  NOT NULL AUTO_INCREMENT,
  `order_id`      int(11)  NOT NULL,
  `order_item_id` int(11)  DEFAULT NULL,
  `reason`        enum('defective','wrong_item','not_as_described','changed_mind','other') NOT NULL,
  `details`       text     DEFAULT NULL,
  `refund_method` enum('original_payment','bank_transfer','store_credit') NOT NULL DEFAULT 'original_payment',
  `status`        enum('pending','reviewing','approved','rejected','completed') NOT NULL DEFAULT 'pending',
  `admin_notes`   text     DEFAULT NULL,
  `created_at`    timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `rr_order_id` (`order_id`),
  KEY `rr_item_id` (`order_item_id`),
  CONSTRAINT `rr_order_fk` FOREIGN KEY (`order_id`)      REFERENCES `orders`      (`id`) ON DELETE CASCADE,
  CONSTRAINT `rr_item_fk`  FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
