<?php
/**
 * POST /api/auth/login.php
 * Body (JSON atau form): { "username": "...", "password": "..." }
 * Response sukses: { success: true, data: { id, username, nama, role } }
 */

require_once '../../config/dbconn.php';
require_once '../../includes/auth.php';
require_once '../helpers/response.php';

if (get_request_method() !== 'POST') {
    json_response(false, null, 'Method tidak diizinkan. Gunakan POST.', 405);
}

$body     = get_json_or_post_body();
$username = trim($body['username'] ?? '');
$password = $body['password'] ?? '';

if ($username === '' || $password === '') {
    json_response(false, null, 'Username dan password wajib diisi.', 400);
}

try {
    // Mitigasi brute-force: blokir sementara kalau sudah terlalu banyak
    // percobaan gagal untuk username+IP yang sama.
    if (tooManyLoginAttempts($pdo, $username)) {
        json_response(false, null, 'Terlalu banyak percobaan login gagal. Coba lagi dalam beberapa menit.', 429);
    }

    $stmt = $pdo->prepare('SELECT id, username, password, nama, role FROM users WHERE username = :username');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        recordFailedLogin($pdo, $username);
        json_response(false, null, 'Username atau password salah.', 401);
    }

    // Login API hanya khusus admin (role user tidak perlu login).
    if (($user['role'] ?? '') !== 'admin') {
        recordFailedLogin($pdo, $username);
        json_response(false, null, 'Hanya akun admin yang dapat login.', 403);
    }

    clearLoginAttempts($pdo, $username);

    session_regenerate_id(true);

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['nama']     = $user['nama'];
    $_SESSION['role']     = $user['role'];

    json_response(true, [
        'id'         => $user['id'],
        'username'   => $user['username'],
        'nama'       => $user['nama'],
        'role'       => $user['role'],
        'csrf_token' => csrfToken(),
    ], 'Login berhasil.');
} catch (PDOException $e) {
    json_response(false, null, 'Terjadi kesalahan server.', 500);
}
