-- Tabel galeri untuk fitur Kelola Galeri Kegiatan (tambah/edit/hapus oleh admin)
-- Jalankan file ini setelah hafidz_db.sql

CREATE TABLE `galeri` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `judul`      VARCHAR(255) NOT NULL,
  `gambar`     VARCHAR(255) NOT NULL,   -- nama file di assets/img/
  `urutan`     INT(11) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Migrasi 8 kegiatan yang sebelumnya di-hardcode langsung di page09G.php,
-- supaya tidak hilang setelah galeri dipindah ke database.
-- Catatan: nama file "rapat eval.jpg" pada kode lama sebenarnya salah ketik
-- (file aslinya "rapat eval.jpeg") sudah diperbaiki di sini.

INSERT INTO `galeri` (`judul`, `gambar`, `urutan`) VALUES
('Apel Siaga ST2023 Provinsi Sumatera Utara', 'Apel Siaga.jpg', 1),
('Rapat Evaluasi Persiapan ST 2023', 'rapat eval.jpeg', 2),
('Pendataan Lapangan ST 2023 Kecamatan Medan Selayang', 'PB selayang.jpeg', 3),
('Pendataan Lapangan ST 2023 Kecamatan Medan Tembung', 'tembung.jpeg', 4),
('Workshop Evaluasi Data Hasil ST2023 Provinsi Sumatera Utara', 'Workshop eval.jpg', 5),
('Verifikasi Data Hasil ST2023 di Provinsi Sumatera Utara', 'verdat.jpg', 6),
('Workshop Persiapan Diseminasi Hasil Pengolahan ST2023 di Provinsi Sumatera Utara', 'Workshop disem.jpg', 7),
('Diseminasi Hasil ST2023 dan Pembinaan Statistik Sektoral di Provinsi Sumatera Utara', 'disem.jpg', 8);
