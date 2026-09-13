<?php
require_once '../../config/dbconn.php';
require_once '../../includes/auth.php';

try {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        header('Location: login.php?status=gagal&pesan=' . urlencode('Username dan password wajib diisi.'));
        exit;
    }

    // Mitigasi brute-force: blokir sementara kalau sudah terlalu banyak
    // percobaan gagal untuk username+IP yang sama.
    if (tooManyLoginAttempts($pdo, $username)) {
        header('Location: login.php?status=gagal&pesan=' . urlencode('Terlalu banyak percobaan login gagal. Coba lagi dalam beberapa menit.'));
        exit;
    }

    /** @var PDO $pdo */
    $stmt = $pdo->prepare('SELECT id, username, password, nama, role FROM users WHERE username = :username');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    // password_verify() cocok baik untuk hash yang dibuat via password_hash()
    // maupun via bcrypt library lain (format hash sama-sama $2y$/$2b$).
    if (!$user || !password_verify($password, $user['password'])) {
        recordFailedLogin($pdo, $username);
        header('Location: login.php?status=gagal&pesan=' . urlencode('Username atau password salah.'));
        exit;
    }

    // Login hanya khusus admin. Pengunjung biasa (role user) tidak perlu
    // login untuk melihat data, jadi akun non-admin ditolak di sini.
    if (($user['role'] ?? '') !== 'admin') {
        recordFailedLogin($pdo, $username);
        header('Location: login.php?status=gagal&pesan=' . urlencode('Hanya akun admin yang dapat login. Pengunjung tidak perlu login untuk melihat data.'));
        exit;
    }

    clearLoginAttempts($pdo, $username);

    // Regenerasi session ID untuk cegah session fixation
    session_regenerate_id(true);

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['nama']     = $user['nama'];
    $_SESSION['role']     = $user['role'];

    header('Location: ../publikasi/page09A.php');
    exit;
} catch (PDOException $e) {
    header('Location: login.php?status=gagal&pesan=' . urlencode('Terjadi kesalahan sistem, coba lagi.'));
    exit;
}
