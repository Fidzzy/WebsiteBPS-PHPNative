-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 01 Jun 2026 pada 16.47
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `projekpbw`
--

-- 

--
-- Struktur dari tabel `publikasi`
--

CREATE TABLE `publikasi` (
  `no` int(11) NOT NULL,
  `judul` varchar(100) NOT NULL,
  `tanggal_rilis` date NOT NULL,
  `link_publikasi` varchar(255) DEFAULT NULL,
  `sampul` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `publikasi`
--

INSERT INTO `publikasi` (`no`, `judul`, `tanggal_rilis`, `link_publikasi`, `sampul`) VALUES
(1, 'Direktori Perusahaan Pertanian DPP Provinsi Sumatera Utara 2026', '2026-03-09', 'https://sumut.bps.go.id/id/publication/2026/03/09/bf34bb91b0e16e2c5d360735/direktori-perusahaan-pertanian--dpp--provinsi-sumatera-utara-2025.html', 'cover.webp'),
(2, 'Indeks Harga Konsumen 8 Kabupaten/Kota di Provinsi Sumatera Utara Semester 2 2025', '2026-03-09', 'https://sumut.bps.go.id/id/publication/2026/03/09/82d3e8a583d1b26b3956ac62/indeks-harga-konsumen-8-kabupaten-kota-di-provinsi-sumatera-utara-semester-2-2025.html', 'cover2.webp'),
(3, 'Provinsi Sumatera Utara Dalam Angka 2026', '2026-02-27', 'https://sumut.bps.go.id/id/publication/2026/02/27/cc9ebe7b48da2db36146409c/provinsi-sumatera-utara-dalam-angka-2026.html', 'cover3.webp'),
(4, 'Katalog Publikasi BPS Provinsi Sumatera Utara 2025', '2026-01-30', 'https://sumut.bps.go.id/id/publication/2026/01/30/bd1b4c5c374daf75edd03818/katalog-publikasi-bps-provinsi-sumatera-utara-2025.html', 'cover4.webp'),
(5, 'Analisis Hasil Survei Kebutuhan Data BPS Provinsi Sumatera', '2026-01-30', 'https://sumut.bps.go.id/id/publication/2026/01/30/bd1b4c5c374daf75edd03818/katalog-publikasi-bps-provinsi-sumatera-utara-2025.html', 'cover5.webp'),
(6, 'Indeks Pembangunan Manusia Provinsi Sumatera Utara 2025', '2026-04-24', 'https://sumut.bps.go.id/id/publication/2026/04/24/669a2a1d870439ea23e422b3/indeks-pembangunan-manusia-provinsi-sumatera-utara-2025.html', 'cover6.webp');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `publikasi`
--
ALTER TABLE `publikasi`
  ADD PRIMARY KEY (`no`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `publikasi`
--
ALTER TABLE `publikasi`
  MODIFY `no` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2334347;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
