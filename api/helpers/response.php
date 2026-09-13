<?php


/**
 * Kirim response JSON dan hentikan eksekusi.
 *
 * @param bool   $success true/false
 * @param mixed  $data    payload (array/object/null)
 * @param string $message pesan singkat untuk client
 * @param int    $code    HTTP status code
 */
function json_response(bool $success, $data = null, string $message = '', int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}


function get_request_method(): string
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'POST') {
        if (!empty($_POST['_method'])) {
            return strtoupper($_POST['_method']);
        }
        if (!empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            return strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        }
    }

    return $method;
}

/**
 * Ambil body request sebagai array asosiatif
 * Mendukung JSON (application/json) maupun form biasa ($_POST)
 */
function get_json_or_post_body(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') !== false) {
        $raw    = file_get_contents('php://input');
        $parsed = json_decode($raw, true);
        return is_array($parsed) ? $parsed : [];
    }

    return $_POST;
}

/**
 * Wajib login untuk endpoint API. Kalau tidak, balas 401 JSON (bukan redirect
 * seperti requireLogin() versi halaman web biasa).
 */
function apiRequireLogin(): void
{
    if (!isLoggedIn()) {
        json_response(false, null, 'Belum login. Silakan login terlebih dahulu.', 401);
    }
}

/**
 * Wajib login + role admin untuk endpoint API. Balas 403 JSON kalau bukan admin.
 */
function apiRequireAdmin(): void
{
    apiRequireLogin();
    if (!isAdmin()) {
        json_response(false, null, 'Akses ditolak. Endpoint ini khusus admin.', 403);
    }
}

/**
 * Wajib token CSRF valid untuk request yang mengubah data (POST/PUT/DELETE).
 * Endpoint ini pakai autentikasi session cookie, jadi tanpa token CSRF,
 * request bisa dipalsukan lewat form di situs lain yang memanfaatkan
 * cookie session admin yang sedang login (classic CSRF).
 *
 * Ambil token dari field "csrf_token" pada response
 * POST /api/auth/login.php atau GET /api/auth/me.php, lalu kirim balik
 * lewat salah satu dari:
 * Header: X-CSRF-Token: <token>
 * Body: { "csrf_token": "<token>" } (JSON)
 * Form field: csrf_token=<token> (multipart/form-data)
 *
 * @param array $body body request yang sudah di-parse (dari get_json_or_post_body()),
 *                     dipakai supaya php://input tidak perlu dibaca dua kali.
 */
function apiRequireCsrf(array $body = []): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($body['csrf_token'] ?? ($_POST['csrf_token'] ?? null));

    if (empty($_SESSION['csrf_token']) || !is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        json_response(
            false,
            null,
            'Token CSRF tidak valid atau tidak dikirim. Ambil token dari field "csrf_token" pada response login (/api/auth/login.php) atau /api/auth/me.php, lalu kirim lewat header X-CSRF-Token.',
            403
        );
    }
}
