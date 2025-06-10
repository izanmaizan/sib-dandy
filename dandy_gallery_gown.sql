-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 08, 2025 at 11:03 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dandy_gallery_gown`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int NOT NULL,
  `booking_code` varchar(20) NOT NULL,
  `user_id` int NOT NULL,
  `package_id` int NOT NULL,
  `service_type` varchar(50) NOT NULL,
  `booking_date` date NOT NULL,
  `usage_date` date NOT NULL,
  `venue_address` text,
  `special_request` text COMMENT 'Semua permintaan khusus termasuk ukuran dress, warna, style makeup, dll',
  `total_amount` decimal(12,2) NOT NULL,
  `down_payment` decimal(12,2) DEFAULT '0.00',
  `remaining_payment` decimal(12,2) DEFAULT '0.00',
  `status` enum('pending','confirmed','paid','in_progress','completed','cancelled') DEFAULT 'pending',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `booking_code`, `user_id`, `package_id`, `service_type`, `booking_date`, `usage_date`, `venue_address`, `special_request`, `total_amount`, `down_payment`, `remaining_payment`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'BK202506056724', 2, 9, 'Baju Pengantin', '2025-06-05', '2025-06-06', 'Lubuk Begalung', 'Ukuran dress: L\nWarna dress: Hitam', 1500000.00, 450000.00, 1050000.00, 'paid', NULL, '2025-06-05 04:12:18', '2025-06-08 08:12:38'),
(2, 'BK202506054019', 2, 3, 'Baju Pengantin', '2025-06-06', '2025-06-06', 'Lubeg', 'Ukuran dress: L\nWarna dress: Light Pink\nbaju', 3500000.00, 1050000.00, 2450000.00, 'paid', 'Booking dikonfirmasi oleh admin. Silakan lakukan pembayaran.', '2025-06-05 19:30:37', '2025-06-08 08:12:38'),
(3, 'BK202506052966', 2, 8, 'Makeup Pengantin', '2025-06-06', '2025-06-07', 'lubeg', 'Style makeup: Natural\nnatural aja', 2500000.00, 750000.00, 1750000.00, 'paid', NULL, '2025-06-05 19:56:58', '2025-06-08 08:12:38'),
(4, 'BK202506062371', 2, 3, 'Baju Pengantin', '2025-06-06', '2025-06-07', 'Pekan Baru', 'Ukuran dress: XXL\nWarna dress: Light Pink', 3500000.00, 1050000.00, 2450000.00, 'paid', 'Booking dikonfirmasi oleh admin. Silakan lakukan pembayaran.', '2025-06-06 00:34:16', '2025-06-08 08:12:38'),
(5, 'BK202506064421', 2, 4, 'Baju Pengantin', '2025-06-06', '2025-06-08', 'lubeg', 'Ukuran dress: L\nWarna dress: Gold', 4000000.00, 1200000.00, 2800000.00, 'pending', NULL, '2025-06-06 02:42:19', '2025-06-08 08:12:38'),
(6, 'BK202506061100', 2, 3, 'Baju Pengantin', '2025-06-06', '2025-06-08', 'lubeg', 'Ukuran dress: XL\nWarna dress: White', 3500000.00, 1050000.00, 2450000.00, 'confirmed', 'Booking dikonfirmasi oleh admin. Silakan lakukan pembayaran DP.', '2025-06-06 09:12:58', '2025-06-08 08:12:38'),
(7, 'BK202506080741', 2, 9, 'Baju Pengantin', '2025-06-08', '2025-06-12', 'Kota Solok', 'Ukuran dress: L\nWarna dress: Merah', 1500000.00, 450000.00, 1050000.00, 'completed', '', '2025-06-08 05:52:51', '2025-06-08 08:12:38');

-- --------------------------------------------------------

--
-- Table structure for table `gallery`
--

CREATE TABLE `gallery` (
  `id` int NOT NULL,
  `service_type` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `image` varchar(255) NOT NULL,
  `description` text,
  `is_featured` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `gallery`
--

INSERT INTO `gallery` (`id`, `service_type`, `title`, `image`, `description`, `is_featured`, `created_at`) VALUES
(7, 'Baju Pengantin', 'Minang Nan Lamo', 'gallery_1749354350.png', 'New Collection', 1, '2025-06-08 03:45:50'),
(8, 'Makeup Pengantin', 'PREWEDDING MOMENT', 'gallery_1749361810.png', 'PREWEDDING MOMENT\r\n\r\nFor : @mhrnpratiwi\r\n\r\nStudio : @duajiwa_studio_padang\r\n\r\nMua&Wadrobe : @dandygallery_gown\r\n\r\nTeam//\r\n@harsavisual_ @dorai.moto @duajiwa_picture @bima_fernando @desfikasarii @paaanzy @visualbysenjakala @adyy.ig\r\n\r\nHome Photo Studio\r\n-\r\nDua Jiwa Photo Studio Padang\r\n-\r\nFor Photoshoot :\r\nWisuda | Prewedding | Couple | Product | etc\r\n\r\nFor Booking :\r\n0823 8562 8004\r\n0822 6873 3995\r\n\r\nAlamat :\r\nJl. Ombilin kelurahan No.2, RW.3, Rimbo kaluang, Kec.Padang Barat (Dekat Gor H.Agus Salim)', 1, '2025-06-08 05:50:10'),
(9, 'Baju Pengantin', 'Wedding dan prewedding', 'gallery_1749361870.png', 'Wedding dan prewedding\r\nInfo pricelist fast respon wa\r\n082284509097\r\n\r\nFor @wp_image\r\nAttire @dandygallery_gown\r\nMua @inggrid_nathasya', 1, '2025-06-08 05:51:10');

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

CREATE TABLE `packages` (
  `id` int NOT NULL,
  `service_type` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text,
  `price` decimal(12,2) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `includes` text,
  `size_available` varchar(255) DEFAULT NULL,
  `color_available` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `packages`
--

INSERT INTO `packages` (`id`, `service_type`, `name`, `description`, `price`, `image`, `includes`, `size_available`, `color_available`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Baju Pengantin', 'Baju Adat Basuntiang Minang PREMIUM', 'New Collaction', 1500000.00, 'package_1_1749360035.png', 'Gaun pengantin, Veil panjang, Aksesoris kepala, Sarung tangan, Sepatu putih', 'S,M,L,XL,XXL', 'Hitam, Emas, Merah', 1, '2025-06-04 10:04:24', '2025-06-08 08:12:16'),
(2, 'Baju Pengantin', 'Baju Adat Minang Basuntiang', 'New Collection \"Baju Adat Minang Basuntiang \"', 1500000.00, 'package_2_1749360101.png', 'Gaun pengantin, Veil pendek, Aksesoris minimalis, Sepatu nude', 'S,M,L,XL', 'Hitam, Emas, Merah', 1, '2025-06-04 10:04:24', '2025-06-08 08:12:16'),
(3, 'Baju Pengantin', 'Baju Adat Minang Premium Batingkuluak Tanduak', 'KOLEKSI TERBARU', 1500000.00, 'package_3_1749360159.png', 'Gaun pengantin, Veil cathedral, Mahkota, Sarung tangan panjang, Sepatu putih', 'S,M,L,XL,XXL', 'Merah, Emas', 1, '2025-06-04 10:04:24', '2025-06-08 08:12:16'),
(4, 'Baju Pengantin', 'Adat Minang Batingkuluak Tanduak', 'NEW COLLECTION', 1500000.00, 'package_4_1749360264.png', 'Kebaya pengantin, Jarik, Aksesoris emas, Sanggul tradisional, Alas kaki', 'S,M,L,XL', 'Emas,Kuning', 1, '2025-06-04 10:04:24', '2025-06-08 08:12:16'),
(5, 'Baju Pengantin', 'Baju Adat Basuntiang Minang', 'NEW COLLECTION', 1500000.00, 'package_5_1749360335.png', 'Makeup wajah lengkap, Hairdo simple, Touch up kit, Foto dokumentasi', NULL, NULL, 1, '2025-06-04 10:04:24', '2025-06-08 08:12:16'),
(6, 'Makeup Pengantin', 'Makeup Pengantin Natural', 'Makeup pengantin natural untuk lebih alami', 850000.00, 'package_6_1749361026.png', 'Makeup wajah lengkap, Hairdo glamour, Bulu mata palsu, Touch up kit, Foto dokumentasi', NULL, NULL, 1, '2025-06-04 10:04:24', '2025-06-08 08:12:16'),
(7, 'Makeup Pengantin', 'Makeup Pengantin Standar', 'Makeup pengantin Minang', 1200000.00, 'package_7_1749361116.png', 'Makeup Korean style, Hairdo Korean, Aksesoris rambut, Touch up kit, Konsultasi style', NULL, NULL, 1, '2025-06-04 10:04:24', '2025-06-08 08:12:16'),
(8, 'Makeup Pengantin', 'Makeup Pengantin Prewedding', 'Makeup pengantin prewedding', 750000.00, 'package_8_1749361171.png', 'Makeup tradisional, Sanggul ukel, Aksesoris emas, Paes lengkap, Dokumentasi', NULL, NULL, 1, '2025-06-04 10:04:24', '2025-06-08 08:12:16'),
(9, 'Baju Pengantin', 'Minang Nan Lamo', 'New Collection', 1500000.00, 'package_9_1749357780.png', 'Sunting dan lain-lain', 'S,M,L,XL', 'Merah,Kuning,Hitam,Biru', 1, '2025-06-05 03:53:45', '2025-06-08 08:12:16');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int NOT NULL,
  `booking_id` int NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','transfer') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `status` enum('pending','verified','rejected') DEFAULT 'pending',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `booking_id`, `payment_date`, `amount`, `payment_method`, `payment_proof`, `status`, `notes`, `created_at`) VALUES
(1, 1, '2025-06-05', 750000.00, 'transfer', 'payment_1749097843_1.png', 'verified', '', '2025-06-05 04:30:43'),
(2, 1, '2025-06-05', 750000.00, 'transfer', 'payment_1749151726_1.jpeg', 'pending', 'lunas', '2025-06-05 19:28:46'),
(3, 2, '2025-06-05', 1050000.00, 'transfer', 'payment_1749152091_2.jpeg', 'verified', 'terimakasih telah membayar dp', '2025-06-05 19:34:51'),
(4, 2, '2025-06-05', 2450000.00, 'transfer', 'payment_1749152277_2.jpeg', 'verified', '', '2025-06-05 19:37:57'),
(5, 3, '2025-06-06', 750000.00, 'transfer', 'payment_1749169072_3.jpeg', 'verified', '', '2025-06-06 00:17:52'),
(6, 4, '2025-06-06', 3500000.00, 'transfer', 'payment_1749202483_4.jpeg', 'verified', '', '2025-06-06 09:34:43'),
(7, 4, '2025-06-06', 3500000.00, 'cash', 'payment_1749203105_4.jpeg', 'rejected', '', '2025-06-06 09:45:05'),
(8, 4, '2025-06-06', 3500000.00, 'cash', 'payment_1749203135_4.jpeg', 'rejected', '', '2025-06-06 09:45:35'),
(9, 7, '2025-06-08', 1500000.00, 'transfer', 'payment_1749362074_7.png', 'verified', '', '2025-06-08 05:54:34'),
(10, 7, '2025-06-08', 1500000.00, 'transfer', 'payment_1749362463_7.png', 'verified', '', '2025-06-08 06:01:03');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','customer') DEFAULT 'customer',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `phone`, `role`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$TZR7JMhFmGj99Db.rRgqcez3sQzVaiGwxRJpxzK/IDTgYStDiUjZS', 'Administrator', 'admin@dandygallery.com', NULL, 'admin', '2025-06-04 10:04:24', '2025-06-05 02:17:35'),
(2, 'demo', '$2y$10$0qGap22OQVqu/iXWskjEZOmmpT6H/LLB/JTGDFC0cirSpXJLdO5zm', 'Demonstrasi', 'demo@dandygallery.com', '0812345678', 'customer', '2025-06-05 02:29:06', '2025-06-05 02:29:06');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_code` (`booking_code`),
  ADD KEY `package_id` (`package_id`),
  ADD KEY `service_type_id` (`service_type`),
  ADD KEY `bookings_ibfk_1` (`user_id`);

--
-- Indexes for table `gallery`
--
ALTER TABLE `gallery`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_type_id` (`service_type`);

--
-- Indexes for table `packages`
--
ALTER TABLE `packages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_type_id` (`service_type`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

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
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `gallery`
--
ALTER TABLE `gallery`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
