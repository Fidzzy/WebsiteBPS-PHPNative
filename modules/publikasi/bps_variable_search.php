<?php
/**
 * GET modules/publikasi/bps_variable_search.php?keyword=inflasi
 *
 * Alat bantu ADMIN untuk menemukan var_id yang benar sebelum dikonfigurasi
 * di config/bps_indicators.php. Bukan dipakai di dashboard publik.
 *
 * Contoh pemakaian: buka langsung di browser (sambil login sebagai admin)
 * https://domain-anda/projekpbw/modules/publikasi/bps_variable_search.php?keyword=penduduk
 *
 * Response: { success, message, data: [{var_id, title, unit, subject}] }
 */

require_once '../../includes/auth.php';
require_once '../../includes/bps_client.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');

$domain  = trim($_GET['domain'] ?? BPS_DOMAIN);
$keyword = trim($_GET['keyword'] ?? '');

$result = bpsFetchVariableList($domain, $keyword);

echo json_encode([
    'success' => $result['ok'],
    'message' => $result['ok']
        ? (count($result['items']) . ' variabel ditemukan untuk domain ' . $domain . '.')
        : $result['error'],
    'data' => $result['items'],
]);
