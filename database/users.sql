-- Tabel users untuk fitur login/logout admin & non-admin
-- Jalankan file ini setelah hafidz_db.sql (database `projekpbw` harus sudah ada)
--
--  PERINGATAN SEBELUM HOSTING / GO-LIVE:
-- Akun contoh di bawah ini punya password default yang DITULIS POLOS
-- di komentar file ini, dan file ini ada di repo publik GitHub. Siapa
-- pun yang buka repo ini otomatis tahu password admin-nya.
--
-- WAJIB dilakukan sebelum website online:
--   1. Login sebagai admin, lalu segera GANTI password akun "admin"
--      (atau hapus akun ini dan buat akun admin baru dengan password kuat).
--   2. Kalau ada fitur ganti password, pastikan tidak dipakai lewat
--      akun contoh ini di server produksi.
--   3. Jangan jalankan INSERT contoh di bawah ini di database produksi
--      tanpa mengganti passwordnya lebih dulu.

CREATE TABLE `users` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(50) NOT NULL,
  `password`   VARCHAR(255) NOT NULL,   -- disimpan dengan password_hash(), JANGAN plaintext
  `nama`       VARCHAR(100) NOT NULL,
  `role`       ENUM('admin','user') NOT NULL DEFAULT 'user',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Akun contoh untuk langsung dicoba:
--   admin / admin123 role admin (bisa CRUD)
--   user / user123 role user (cuma bisa lihat)
-- Password di bawah ini SUDAH di-hash pakai bcrypt (kompatibel dengan
-- password_hash()/password_verify() PHP), bukan plaintext.

INSERT INTO `users` (`username`, `password`, `nama`, `role`) VALUES
('admin', '$2b$10$YUOBCXJ1AeyRIzaHMW6GIObE3lZCcnBlOZODXMb4a1tuyUi2ldEMi', 'Administrator', 'admin'),
('user',  '$2b$10$8F8VJ54ZThxswZm0pU944OpaFfhKxRKM/gCaYD7gzhZv0OXAqBBcy', 'User Biasa', 'user');
