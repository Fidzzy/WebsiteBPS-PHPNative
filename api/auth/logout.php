<?php
/**
 * POST /api/auth/logout.php
 * Response: { success: true, message: "Logout berhasil." }
 */

require_once '../../includes/auth.php';
require_once '../helpers/response.php';

if (get_request_method() !== 'POST') {
    json_response(false, null, 'Method tidak diizinkan. Gunakan POST.', 405);
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

json_response(true, null, 'Logout berhasil.');
