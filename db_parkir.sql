-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 19, 2026 at 08:40 AM
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
-- Database: `db_parkir`
--

-- --------------------------------------------------------

--
-- Table structure for table `tb_area_parkir`
--

CREATE TABLE `tb_area_parkir` (
  `id_area` int(11) NOT NULL,
  `nama_area` varchar(100) NOT NULL,
  `kapasitas` int(11) NOT NULL DEFAULT 0,
  `terisi` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_area_parkir`
--

INSERT INTO `tb_area_parkir` (`id_area`, `nama_area`, `kapasitas`, `terisi`) VALUES
(1, 'Parkir Utama', 50, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tb_kendaraan`
--

CREATE TABLE `tb_kendaraan` (
  `id_kendaraan` int(11) NOT NULL,
  `plat_nomor` varchar(15) NOT NULL,
  `jenis_kendaraan` varchar(20) NOT NULL,
  `warna` varchar(20) DEFAULT NULL,
  `pemilik` varchar(100) DEFAULT NULL,
  `id_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_kendaraan`
--

INSERT INTO `tb_kendaraan` (`id_kendaraan`, `plat_nomor`, `jenis_kendaraan`, `warna`, `pemilik`, `id_user`) VALUES
(1, 'AE 1234 AB', 'mobil', 'merah', 'rni', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tb_log_aktivitas`
--

CREATE TABLE `tb_log_aktivitas` (
  `id_log` int(11) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `aktivitas` varchar(255) NOT NULL,
  `waktu_aktivitas` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_log_aktivitas`
--

INSERT INTO `tb_log_aktivitas` (`id_log`, `id_user`, `aktivitas`, `waktu_aktivitas`) VALUES
(1, 1, 'Logout dari sistem', '2026-10-19 09:32:44'),
(2, 1, 'Login ke sistem', '2026-10-19 09:50:35'),
(3, 1, 'Logout dari sistem', '2026-10-19 09:51:00'),
(4, 4, 'Login ke sistem', '2026-10-19 09:52:40'),
(5, 4, 'Logout dari sistem', '2026-10-19 09:52:48'),
(6, 5, 'Login ke sistem', '2026-10-19 09:53:04'),
(7, 5, 'Logout dari sistem', '2026-10-19 09:53:19'),
(8, 1, 'Login ke sistem', '2026-10-19 09:53:33'),
(9, 1, 'Menambahkan kendaraan AE 1234 AB', '2026-10-19 09:54:28'),
(10, 1, 'Kendaraan masuk: AE 1234 AB', '2026-10-19 09:55:44'),
(11, 1, 'Logout dari sistem', '2026-10-19 09:56:15'),
(12, 4, 'Login ke sistem', '2026-10-19 09:56:26'),
(13, 4, 'Logout dari sistem', '2026-10-19 09:56:42'),
(14, 5, 'Login ke sistem', '2026-10-19 09:56:54'),
(15, 5, 'Logout dari sistem', '2026-10-19 09:57:01'),
(16, 1, 'Login ke sistem', '2026-10-19 09:57:12'),
(17, 1, 'Kendaraan keluar: AE 1234 AB', '2026-10-19 09:57:21'),
(18, 1, 'Logout dari sistem', '2026-10-19 10:01:33'),
(19, 4, 'Login ke sistem', '2026-10-19 10:01:45'),
(20, 4, 'Logout dari sistem', '2026-10-19 10:44:20'),
(21, 1, 'Login ke sistem', '2026-10-19 10:44:30'),
(22, 1, 'Logout dari sistem', '2026-10-19 10:45:30'),
(23, 5, 'Login ke sistem', '2026-10-19 10:45:40'),
(24, 5, 'Logout dari sistem', '2026-10-19 10:47:29'),
(25, 4, 'Login ke sistem', '2026-10-19 10:47:43'),
(26, 4, 'Logout dari sistem', '2026-10-19 10:49:36'),
(27, 1, 'Login ke sistem', '2026-10-19 10:49:49'),
(28, 1, 'Logout dari sistem', '2026-10-19 10:52:35'),
(29, 4, 'Login ke sistem', '2026-10-19 10:52:47'),
(30, 4, 'Logout dari sistem', '2026-10-19 10:55:51'),
(31, 5, 'Login ke sistem', '2026-10-19 10:56:19'),
(32, 5, 'Logout dari sistem', '2026-10-19 10:56:43'),
(33, 1, 'Login ke sistem', '2026-10-19 11:00:30'),
(34, 1, 'Logout dari sistem', '2026-10-19 11:00:50'),
(35, 1, 'Login ke sistem', '2026-10-19 11:04:43'),
(36, 1, 'Kendaraan masuk: AE 1234 AB', '2026-10-19 11:10:42'),
(37, 1, 'Kendaraan keluar: AE 1234 AB', '2026-10-19 11:10:56'),
(38, 1, 'Logout dari sistem', '2026-10-19 11:30:11'),
(39, 1, 'Login ke sistem', '2026-10-19 11:37:33'),
(40, 1, 'Logout dari sistem', '2026-10-19 11:57:58'),
(41, 1, 'Login ke sistem', '2026-10-19 12:43:35'),
(42, 1, 'Logout dari sistem', '2026-10-19 12:47:35'),
(43, 4, 'Login ke sistem', '2026-10-19 12:54:26'),
(44, 4, 'Logout dari sistem', '2026-10-19 13:09:38'),
(45, 1, 'Login ke sistem', '2026-10-19 13:09:52'),
(46, 1, 'Logout dari sistem', '2026-10-19 13:10:50'),
(47, 4, 'Login ke sistem', '2026-10-19 13:11:01'),
(48, 4, 'Logout dari sistem', '2026-10-19 13:20:15'),
(49, 1, 'Login ke sistem', '2026-10-19 13:20:26'),
(50, 1, 'Logout dari sistem', '2026-10-19 13:20:33'),
(51, 4, 'Login ke sistem', '2026-10-19 13:20:55'),
(52, 4, 'Logout dari sistem', '2026-10-19 13:26:41'),
(53, 1, 'Login ke sistem', '2026-10-19 13:26:51'),
(54, 1, 'Logout dari sistem', '2026-10-19 13:26:58'),
(55, 5, 'Login ke sistem', '2026-10-19 13:27:07'),
(56, 5, 'Logout dari sistem', '2026-10-19 13:27:51'),
(57, 5, 'Login ke sistem', '2026-10-19 13:28:45');

-- --------------------------------------------------------

--
-- Table structure for table `tb_tarif`
--

CREATE TABLE `tb_tarif` (
  `id_tarif` int(11) NOT NULL,
  `jenis_kendaraan` varchar(30) NOT NULL,
  `tarif_per_jam` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tarif_maksimal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status_aktif` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_tarif`
--

INSERT INTO `tb_tarif` (`id_tarif`, `jenis_kendaraan`, `tarif_per_jam`, `tarif_maksimal`, `status_aktif`) VALUES
(1, 'Motor', 2000.00, 10000.00, 1),
(2, 'Mobil', 5000.00, 25000.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tb_transaksi`
--

CREATE TABLE `tb_transaksi` (
  `id_parkir` int(11) NOT NULL,
  `id_kendaraan` int(11) NOT NULL,
  `waktu_masuk` datetime NOT NULL DEFAULT current_timestamp(),
  `waktu_keluar` datetime DEFAULT NULL,
  `id_tarif` int(11) DEFAULT NULL,
  `durasi_jam` int(11) DEFAULT NULL,
  `biaya_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('masuk','keluar') NOT NULL DEFAULT 'masuk',
  `id_user` int(11) DEFAULT NULL,
  `id_area` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_transaksi`
--

INSERT INTO `tb_transaksi` (`id_parkir`, `id_kendaraan`, `waktu_masuk`, `waktu_keluar`, `id_tarif`, `durasi_jam`, `biaya_total`, `status`, `id_user`, `id_area`) VALUES
(1, 1, '2026-10-19 09:55:44', '2026-10-19 09:57:21', 2, 1, 5000.00, 'keluar', 1, 1),
(2, 1, '2026-10-19 11:10:42', '2026-10-19 11:10:56', 2, 1, 5000.00, 'keluar', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tb_user`
--

CREATE TABLE `tb_user` (
  `id_user` int(11) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','petugas','owner') NOT NULL,
  `status_aktif` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_user`
--

INSERT INTO `tb_user` (`id_user`, `nama_lengkap`, `username`, `password`, `role`, `status_aktif`) VALUES
(1, 'Administrator', 'admin1', '$2y$10$WapUq7.V.E.5Bn48NeEXQepn.D9xQLEYFauJOUvKQ5cMB7T44yymS', 'admin', 1),
(4, 'Petugas Parkir', 'petugas1', '$2y$10$JC.UHiJVU94CMj6Vq0TrkO06enCkoqoLehnZKkI.mWe1iQGzgw9Yq', 'petugas', 1),
(5, 'Pemilik Parkir', 'owner1', '$2y$10$KeX9NwDRuW1BvQoHZUI8S.kJe9ftwJhsUaEJ9Gqhm6nJCSDoDpC1m', 'owner', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tb_area_parkir`
--
ALTER TABLE `tb_area_parkir`
  ADD PRIMARY KEY (`id_area`);

--
-- Indexes for table `tb_kendaraan`
--
ALTER TABLE `tb_kendaraan`
  ADD PRIMARY KEY (`id_kendaraan`),
  ADD UNIQUE KEY `unik_plat_nomor` (`plat_nomor`);

--
-- Indexes for table `tb_log_aktivitas`
--
ALTER TABLE `tb_log_aktivitas`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `idx_user` (`id_user`),
  ADD KEY `idx_waktu` (`waktu_aktivitas`);

--
-- Indexes for table `tb_tarif`
--
ALTER TABLE `tb_tarif`
  ADD PRIMARY KEY (`id_tarif`);

--
-- Indexes for table `tb_transaksi`
--
ALTER TABLE `tb_transaksi`
  ADD PRIMARY KEY (`id_parkir`),
  ADD KEY `idx_kendaraan` (`id_kendaraan`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `tb_user`
--
ALTER TABLE `tb_user`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `unik_username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tb_area_parkir`
--
ALTER TABLE `tb_area_parkir`
  MODIFY `id_area` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tb_kendaraan`
--
ALTER TABLE `tb_kendaraan`
  MODIFY `id_kendaraan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tb_log_aktivitas`
--
ALTER TABLE `tb_log_aktivitas`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `tb_tarif`
--
ALTER TABLE `tb_tarif`
  MODIFY `id_tarif` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tb_transaksi`
--
ALTER TABLE `tb_transaksi`
  MODIFY `id_parkir` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tb_user`
--
ALTER TABLE `tb_user`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
