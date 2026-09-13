<?php

/**
 * GET /api/auth/me.php
 * Response kalau login: { success: true, data: { id, username, nama, role } }
 * Response kalau belum login: { success: true, data: null }
 *
 * Endpoint tidak pakai apiRequireLogin() karena tujuannya hanya untuk cek status login
 */

require_once '../../includes/auth.php';
require_once '../helpers/response.php';

if (!isLoggedIn()) {
    json_response(true, null, 'Belum login.');
}

json_response(true, [
    'id'         => $_SESSION['user_id'],
    'username'   => $_SESSION['username'],
    'nama'       => $_SESSION['nama'],
    'role'       => $_SESSION['role'],
    'csrf_token' => csrfToken(),
], 'Sedang login.');
