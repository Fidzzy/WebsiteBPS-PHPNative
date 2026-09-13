<?php
/**
 * Proxy publik untuk halaman Data Ekspor-Impor (exim.php).
 *
 * Request ke BPS Web API dilakukan SERVER-SIDE (API key aman).
 * Read-only, tanpa perlu login.
 *
 * GET modules/publikasi/bps_exim_api.php?sumber=1&kodehs=09;15&jenishs=1&tahun=2026&periode=2
 * sumber: 1 = Ekspor, 2 = Impor, 3 = Gabungan (ekspor + impor sekaligus
 *     untuk tabel permanen gabungan di exim.php)
 * kodehs: kode HS 2 digit / penuh, pisahkan ";" untuk multi
 * jenishs: 1 = 2 digit, 2 = Full HS Code
 * tahun: 2014 s.d. tahun berjalan (boleh "2024;2025")
 * periode: 1 = Bulanan, 2 = Tahunan
 *
 * Response: { success, message, metadata, data }
 * sumber 1/2: data = [ {...}, ... ]
 * sumber 3: data = { ekspor: [...], impor: [...] }
 */

require_once '../../includes/auth.php';
require_once '../../includes/bps_client.php';

header('Content-Type: application/json; charset=utf-8');

function eximRespond(bool $success, string $message, $data = [], $metadata = null): void
{
    echo json_encode(['success' => $success, 'message' => $message, 'metadata' => $metadata, 'data' => $data]);
    exit;
}

function eximGet(string $name, string $default = ''): string
{
    return trim($_GET[$name] ?? $default);
}

$sumber = eximGet('sumber', '1');
$kodehs = eximGet('kodehs', '09');
$jenishs = eximGet('jenishs', '1');
$tahun = eximGet('tahun', date('Y'));
$periode = eximGet('periode', '2');

if (!in_array($sumber, ['1', '2', '3'], true)) {
    eximRespond(false, 'Parameter sumber harus 1 (Ekspor), 2 (Impor), atau 3 (Gabungan).');
}
// Kode HS: digit saja + ";" (full HS bisa mengandung huruf? tidak
// angka semua; tetap izinkan alfanumerik agar tidak menolak kode valid).
if ($kodehs === '' || !preg_match('/^[A-Za-z0-9;]+$/', $kodehs)) {
    eximRespond(false, 'Parameter kodehs wajib diisi (mis. 09 atau 09;15).');
}
if (!in_array($jenishs, ['1', '2'], true)) {
    eximRespond(false, 'Parameter jenishs harus 1 (2 digit) atau 2 (Full).');
}
if ($tahun === '' || !preg_match('/^[\d;]+$/', $tahun)) {
    eximRespond(false, 'Parameter tahun wajib diisi (mis. 2026).');
}
if (!in_array($periode, ['1', '2'], true)) {
    eximRespond(false, 'Parameter periode harus 1 (Bulanan) atau 2 (Tahunan).');
}

// Batasi maksimal 5 kode HS per request agar respons tidak raksasa
// (satu HS bulanan saja bisa ~500KB / ribuan baris).
$hsList = array_values(array_filter(array_map('trim', explode(';', $kodehs)), fn($v) => $v !== ''));
if (count($hsList) > 5) {
    eximRespond(false, 'Maksimal 5 kode HS per permintaan (gabungkan unduhan bila perlu lebih).');
}
$kodehs = implode(';', $hsList);

// Cache 6 jam (data exim tidak berubah intraday; hemat kuota & cepat).
$cacheDir = __DIR__ . '/../../cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}
$ck = md5('exim|' . $sumber . '|' . $kodehs . '|' . $jenishs . '|' . $tahun . '|' . $periode);
$cacheFile = $cacheDir . '/bps_exim_' . $ck . '.json';

if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 21600) {
    $cached = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($cached) && ($cached['status'] ?? '') === 'OK') {
        eximRespond(true, 'OK (cache).', $cached['data'] ?? [], $cached['metadata'] ?? null);
    }
}

// Mode gabungan (sumber=3): ambil ekspor + impor sekaligus untuk tabel
// permanen gabungan di exim.php. Kunci cache sudah memuat sumber=3
// sehingga terpisah dari cache arus tunggal.
if ($sumber === '3') {
    $re = bpsFetchEximRaw('1', $kodehs, $jenishs, $tahun, $periode);
    if (!$re['ok']) {
        eximRespond(false, 'Ekspor: ' . $re['error']);
    }
    $ri = bpsFetchEximRaw('2', $kodehs, $jenishs, $tahun, $periode);
    if (!$ri['ok']) {
        eximRespond(false, 'Impor: ' . $ri['error']);
    }
    $dataE = (isset($re['json']['data']) && is_array($re['json']['data'])) ? $re['json']['data'] : [];
    $dataI = (isset($ri['json']['data']) && is_array($ri['json']['data'])) ? $ri['json']['data'] : [];
    $payload = [
        'status' => 'OK',
        'metadata' => $re['json']['metadata'] ?? null,
        'data' => ['ekspor' => $dataE, 'impor' => $dataI],
    ];
    @file_put_contents($cacheFile, json_encode($payload));
    eximRespond(
        true,
        count($dataE) . ' baris ekspor + ' . count($dataI) . ' baris impor ditemukan.',
        ['ekspor' => $dataE, 'impor' => $dataI],
        $payload['metadata']
    );
}

$r = bpsFetchEximRaw($sumber, $kodehs, $jenishs, $tahun, $periode);
if (!$r['ok']) {
    eximRespond(false, $r['error']);
}

@file_put_contents($cacheFile, json_encode($r['json']));

$rows = (isset($r['json']['data']) && is_array($r['json']['data'])) ? $r['json']['data'] : [];
eximRespond(true, count($rows) . ' baris data ditemukan.', $rows, $r['json']['metadata'] ?? null);
