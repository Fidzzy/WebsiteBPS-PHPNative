-- Tabel untuk membatasi percobaan login (mitigasi brute-force).
-- Jalankan setelah users.sql.

CREATE TABLE `login_attempts` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `username`     VARCHAR(50) NOT NULL,
  `ip_address`   VARCHAR(45) NOT NULL, -- cukup untuk IPv4 & IPv6
  `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_username_ip_time` (`username`, `ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
