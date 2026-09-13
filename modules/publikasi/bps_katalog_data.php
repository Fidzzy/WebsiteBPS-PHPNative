<?php
/**
 * GET modules/publikasi/bps_katalog_data.php?subject=<kata kunci>
 *
 * Proxy untuk halaman katalog.php mencari beberapa variabel BPS yang
 * cocok dengan subjek yang dipilih user di sidebar, lalu mengembalikan
 * nilai terbaru masing-masing sebagai baris tabel.
 *
 * Beda dengan bps_variable_search.php (admin-only, dipakai untuk
 * mengonfigurasi config/bps_indicators.php), endpoint ini dipakai
 * langsung oleh SEMUA pengunjung lewat halaman katalog data publik
 * (read-only, tidak mengubah apa pun) tanpa perlu login.
 *
 * Response: { success, message, data: [{title, value, unit, year}] }
 */

require_once '../../includes/auth.php';
require_once '../../includes/bps_client.php';

header('Content-Type: application/json; charset=utf-8');

$subject = trim($_GET['subject'] ?? '');

if ($subject === '') {
    echo json_encode(['success' => false, 'message' => 'Parameter subject wajib diisi.', 'data' => []]);
    exit;
}

// Cari daftar variabel yang cocok dengan subjek ini, ambil beberapa yang
// paling relevan (bukan cuma 1 seperti di dashboard) supaya halaman
// katalog terasa seperti benar-benar menjelajahi data, bukan cuma
// menampilkan satu angka.
$searchResult = bpsFetchVariableList(BPS_DOMAIN, $subject);

if (!$searchResult['ok']) {
    echo json_encode(['success' => false, 'message' => $searchResult['error'], 'data' => []]);
    exit;
}

if (empty($searchResult['items'])) {
    echo json_encode(['success' => true, 'message' => 'Tidak ada variabel BPS yang cocok dengan subjek ini.', 'data' => []]);
    exit;
}

// Batasi maksimal 4 indikator per subjek supaya halaman tetap responsif
// (tiap indikator sekarang = 2 request terpisah ke BPS cari periode lalu
// ambil data walau sudah di-cache 1 jam masing-masing).
$candidates = array_slice($searchResult['items'], 0, 4);

$rows = [];
foreach ($candidates as $item) {
    $indicator = bpsFetchIndicatorCached(
        BPS_DOMAIN,
        (string) $item['var_id'],
        null,
        null,
        $item['title'],
        $item['unit']
    );

    if ($indicator['ok']) {
        $rows[] = [
            'title' => $indicator['title'],
            'value' => $indicator['latestValue'],
            'unit'  => $indicator['unit'],
            'year'  => $indicator['latestYear'],
        ];
    }
    // Kalau salah satu indikator gagal diambil, cukup dilewati (bukan
    // menggagalkan seluruh respons) supaya subjek dengan banyak variabel
    // tetap menampilkan yang berhasil.
}

echo json_encode([
    'success' => true,
    'message' => count($rows) . ' indikator ditemukan untuk "' . $subject . '".',
    'data' => $rows,
]);
