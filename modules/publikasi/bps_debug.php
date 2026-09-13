<?php
/**
 * GET modules/publikasi/bps_debug.php?var_id=<var_id>
 *
 * Alat bantu ADMIN untuk melihat respons MENTAH dari BPS Web API (model=th
 * dan model=data) untuk satu var_id, tanpa diolah/disederhanakan sama
 * sekali. Dipakai kalau ada indikator yang hasilnya masih aneh setelah
 * dikonfigurasi, supaya struktur aslinya bisa dicek langsung, bukan
 * ditebak-tebak lagi dari dokumentasi.
 *
 * Contoh: modules/publikasi/bps_debug.php?var_id=124
 */

require_once '../../includes/auth.php';
require_once '../../includes/bps_client.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');

$varId = trim($_GET['var_id'] ?? '');

if ($varId === '') {
    echo json_encode(['success' => false, 'message' => 'Parameter var_id wajib diisi. Contoh: ?var_id=124'], JSON_PRETTY_PRINT);
    exit;
}

if (BPS_API_KEY === 'GANTI_DENGAN_API_KEY_ANDA' || BPS_API_KEY === '') {
    echo json_encode(['success' => false, 'message' => 'API key BPS belum dikonfigurasi di config/bps_api.php.'], JSON_PRETTY_PRINT);
    exit;
}

// 1. Respons mentah model=th (daftar periode)
$urlTh = BPS_API_BASE . 'list/model/th/lang/ind/domain/' . urlencode(BPS_DOMAIN)
    . '/var/' . urlencode($varId)
    . '/key/' . urlencode(BPS_API_KEY) . '/';
$rawTh = bpsApiCall($urlTh);

// 2. Kalau model=th berhasil, coba minta model=data pakai th dari hasil di atas.
// BPS membatasi 'th' maksimal 2 tahun per request (dikonfirmasi dari pesan
// error BPS sendiri), jadi default di sini 2 boleh dicoba angka lain
// lewat ?years= kalau mau melihat sendiri batasnya.
$rawData = null;
$urlData = null;
$years = max(1, (int) ($_GET['years'] ?? 2));
if ($rawTh['ok']) {
    $periods = $rawTh['json']['data'][1] ?? [];
    usort($periods, fn($a, $b) => (int) ($b['val'] ?? 0) <=> (int) ($a['val'] ?? 0));
    $recentVals = array_map(fn($p) => (string) ($p['val'] ?? ''), array_slice($periods, 0, $years));
    $thValue = implode(';', array_filter($recentVals));

    $urlData = BPS_API_BASE . 'list/model/data/lang/ind/domain/' . urlencode(BPS_DOMAIN)
        . '/var/' . urlencode($varId)
        . '/th/' . urlencode($thValue)
        . '/key/' . urlencode(BPS_API_KEY) . '/';
    $rawData = bpsApiCall($urlData);
}

echo json_encode([
    'success' => true,
    'info' => 'Ini respons MENTAH dari BPS, belum diolah. Kirim/tempel hasil ini kalau minta bantuan debug lebih lanjut.',
    'domain' => BPS_DOMAIN,
    'var_id' => $varId,
    'step_1_model_th' => [
        'url_dipanggil' => $urlTh,
        'berhasil' => $rawTh['ok'],
        'http_code' => $rawTh['httpCode'],
        'error' => $rawTh['error'],
        'response' => $rawTh['json'],
    ],
    'step_2_model_data' => $rawData === null ? 'Tidak dicoba karena step 1 gagal.' : [
        'url_dipanggil' => $urlData,
        'berhasil' => $rawData['ok'],
        'http_code' => $rawData['httpCode'],
        'error' => $rawData['error'],
        'response' => $rawData['json'],
        // Ini bagian PALING PENTING untuk debug "Tidak ada data untuk
        // kombinasi wilayah/variabel ini" bandingkan langsung daftar
        // key asli di datacontent dengan nilai vervar/turvar/tahun yang
        // tersedia, supaya pola penggabungannya (concatenation) kelihatan
        // tanpa perlu tebak-tebak lagi.
        'analisis_pencocokan_key' => $rawData['ok'] ? [
            'contoh_keys_datacontent' => array_slice(array_keys($rawData['json']['datacontent'] ?? []), 0, 10),
            'daftar_vervar' => $rawData['json']['vervar'] ?? [],
            'daftar_turvar' => $rawData['json']['turvar'] ?? [],
            'daftar_tahun_di_respons_data' => $rawData['json']['tahun'] ?? 'TIDAK ADA FIELD "tahun" DI RESPONS INI - kemungkinan besar ini akar masalahnya kalau kartu menunjukkan "Tidak ada data".',
            'var_id_yang_dipakai' => $varId,
            'catatan' => 'Key di datacontent SEHARUSNYA hasil gabungan salah satu kombinasi dari val milik vervar + "' . $varId . '" + val milik turvar + val milik tahun (dalam urutan itu, sesuai asumsi kode saat ini). Cocokkan manual salah satu contoh_keys_datacontent di atas dengan angka-angka val dari daftar_vervar/daftar_turvar/daftar_tahun_di_respons_data untuk verifikasi urutannya benar atau tidak.',
        ] : 'Tidak dianalisis karena request gagal.',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
