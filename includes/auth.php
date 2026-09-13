<?php
/**
 * Helper autentikasi & otorisasi.
 * Include file ini di halaman mana pun yang butuh cek login/role.
 * Path ke file ini dari modul: '../../includes/auth.php'
 */

if (session_status() === PHP_SESSION_NONE) {
    // Session cookie di-hardening supaya lebih aman kalau nanti di-hosting:
    // httponly: cookie session tidak bisa dibaca lewat JavaScript (mitigasi XSS)
    // samesite=Lax: cookie tidak ikut terkirim di request cross-site
    //   (mitigasi CSRF), tapi tetap ikut untuk navigasi link biasa
    // secure: cookie hanya dikirim lewat HTTPS (otomatis aktif kalau
    //   request-nya memang lewat HTTPS, supaya tidak merusak dev lokal
    //   yang masih pakai HTTP biasa)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/**
 * True kalau user sudah login.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * True kalau user yang login role-nya admin.
 */
function isAdmin(): bool
{
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin';
}

/**
 * Paksa user harus login. Kalau belum, redirect ke halaman login
 * dan hentikan eksekusi halaman saat ini.
 *
 * CATATAN: halaman publik (dashboard, daftar publikasi, berita, katalog,
 * galeri) TIDAK lagi memanggil ini pengunjung biasa tidak perlu login.
 * Login hanya untuk admin via requireAdmin(). Fungsi ini dipertahankan
 * untuk kompatibilitas kalau ada halaman yang memang butuh login.
 *
 * @param string $loginPath path relatif ke login.php dari file pemanggil
 */
function requireLogin(string $loginPath = '../auth/login.php'): void
{
    if (!isLoggedIn()) {
        header('Location: ' . $loginPath);
        exit;
    }
}

/**
 * Paksa user harus login DAN berrole admin. Kalau tidak, tolak akses.
 *
 * @param string $loginPath path relatif ke login.php dari file pemanggil
 */
function requireAdmin(string $loginPath = '../auth/login.php'): void
{
    requireLogin($loginPath);

    if (!isAdmin()) {
        http_response_code(403);
        exit('<h2 style="font-family:sans-serif;text-align:center;margin-top:60px;">
                403 - Akses ditolak. Halaman ini khusus admin.
              </h2>
              <p style="text-align:center;"><a href="../publikasi/page09A.php">Kembali ke Publikasi</a></p>');
    }
}

/**
 * Ambil (atau buat kalau belum ada) CSRF token untuk session saat ini.
 * Panggil ini di form yang men-generate aksi mengubah data (delete, dsb),
 * lalu sisipkan sebagai hidden input bernama 'csrf_token'.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifikasi token CSRF yang dikirim dari form/request. Kalau tidak valid,
 * hentikan eksekusi dengan status 403.
 *
 * @param string|null $token token yang dikirim user (biasanya dari $_POST['csrf_token'])
 */
function requireValidCsrf(?string $token): void
{
    if (empty($_SESSION['csrf_token']) || !is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('<h2 style="font-family:sans-serif;text-align:center;margin-top:60px;">
                403 - Permintaan ditolak (token keamanan tidak valid atau kedaluwarsa).
              </h2>
              <p style="text-align:center;"><a href="javascript:history.back()">Kembali</a></p>');
    }
}

/**
 * Ambil alamat IP pengunjung. Cuma pakai REMOTE_ADDR (bukan header
 * X-Forwarded-For) supaya tidak gampang dipalsukan lewat header kalau
 * hosting Anda ada di belakang reverse proxy/CDN (Cloudflare, dsb.) dan
 * REMOTE_ADDR jadi IP proxy-nya, sesuaikan fungsi ini untuk baca header
 * X-Forwarded-For dari proxy yang memang dipercaya saja.
 */
function clientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Cek apakah percobaan login untuk kombinasi username+IP ini sudah kena
 * limit (mitigasi brute-force password). Batas: 5 percobaan gagal dalam
 * 15 menit terakhir.
 *
 * Sengaja fail-open (return false kalau query gagal, mis. tabel
 * login_attempts belum di-migrate): rate-limit adalah proteksi tambahan,
 * jangan sampai kegagalan di proteksi tambahan justru mengunci SEMUA
 * user keluar dari login.
 *
 * @param PDO    $pdo
 * @param string $username
 * @return bool true kalau harus diblokir dulu
 */
function tooManyLoginAttempts(PDO $pdo, string $username): bool
{
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE username = :username AND ip_address = :ip
               AND attempted_at > (NOW() - INTERVAL 15 MINUTE)'
        );
        $stmt->execute([':username' => $username, ':ip' => clientIp()]);
        return (int) $stmt->fetchColumn() >= 5;
    } catch (PDOException $e) {
        error_log('tooManyLoginAttempts gagal: ' . $e->getMessage());
        return false;
    }
}

/**
 * Catat satu percobaan login yang gagal.
 * Fail-silent kalau pencatatan gagal (lihat alasan di atas).
 */
function recordFailedLogin(PDO $pdo, string $username): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO login_attempts (username, ip_address) VALUES (:username, :ip)');
        $stmt->execute([':username' => $username, ':ip' => clientIp()]);
    } catch (PDOException $e) {
        error_log('recordFailedLogin gagal: ' . $e->getMessage());
    }
}

/**
 * Bersihkan riwayat percobaan gagal untuk kombinasi ini setelah login sukses.
 * Fail-silent kalau pembersihan gagal (lihat alasan di atas).
 */
function clearLoginAttempts(PDO $pdo, string $username): void
{
    try {
        $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE username = :username AND ip_address = :ip');
        $stmt->execute([':username' => $username, ':ip' => clientIp()]);
    } catch (PDOException $e) {
        error_log('clearLoginAttempts gagal: ' . $e->getMessage());
    }
}
