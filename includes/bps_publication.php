<?php
/**
 * Helper daftar publikasi dari BPS Web API (model=publication).
 *
 * Halaman "Publikasi" (modules/publikasi/page09A.php) mengambil SEMUA data
 * publikasi langsung dari web asli BPS (webapi.bps.go.id) lewat endpoint:
 *   list/model/publication/lang/ind/domain/{domain}[/keyword/{kw}]/page/{n}/key/{key}/
 * sehingga fitur lama "Tarik Data Publikasi" (import satu-satu ke database
 * lokal via bps_search.php/bps_detail.php) sudah tidak diperlukan lagi.
 *
 * Entri MANUAL admin (tabel `publikasi`, via page09C.php) tetap didukung dan
 * digabung dengan hasil API di halaman daftar lihat
 * bpsMergeManualPublications().
 *
 * Cache: hasil per halaman + hasil gabungan disimpan di cache/ (TTL default
 * 6 jam) supaya 99 halaman (~989 publikasi) tidak di-fetch ulang setiap
 * ada pengunjung. Fetch gabungan memakai request paralel (curl_multi,
 * 10 request sekaligus, ~1-2 detik per batch).
 */

require_once __DIR__ . '/bps_client.php';

/** TTL default cache daftar publikasi (6 jam publikasi jarang berubah). */
const BPS_PUB_CACHE_TTL = 21600;

/** Maksimal halaman API yang diambil (pengaman kalau total membengkak). */
const BPS_PUB_MAX_PAGES = 150;

function bpsPubCacheDir(): string
{
    $dir = __DIR__ . '/../cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function bpsPubPageCacheFile(string $domain, int $page, string $keyword): string
{
    return bpsPubCacheDir() . '/bps_pubpage_' . md5($domain . '|' . $page . '|' . $keyword) . '.json';
}

function bpsPubAllCacheFile(string $domain, string $keyword): string
{
    return bpsPubCacheDir() . '/bps_puball_' . md5($domain . '|' . $keyword) . '.json';
}

/**
 * Normalisasi satu baris publikasi BPS jadi bentuk baku aplikasi.
 *
 * @return array{pub_id:string, title:string, rl_date:string, year:string,
 *               cover:string, pdf:string, size:string, source:string}
 */
function bpsPubNormalizeRow(array $row): array
{
    // Tanggal rilis utama = rl_date, fallback sch_date (jadwal rilis).
    $date = trim((string) ($row['rl_date'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $ts = $date !== '' ? strtotime($date) : false;
        $date = $ts ? date('Y-m-d', $ts) : '';
    }
    if ($date === '') {
        $sch = trim((string) ($row['sch_date'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $sch)) {
            $date = $sch;
        } else {
            $ts = $sch !== '' ? strtotime($sch) : false;
            $date = $ts ? date('Y-m-d', $ts) : '';
        }
    }

    return [
        'pub_id'  => (string) ($row['pub_id'] ?? ''),
        'title'   => (string) ($row['title'] ?? ''),
        'rl_date' => $date,
        'year'    => $date !== '' ? substr($date, 0, 4) : '',
        // cover & pdf dari API sudah URL absolut (https://webapi.bps.go.id/...).
        'cover'   => (string) ($row['cover'] ?? ''),
        'pdf'     => (string) ($row['pdf'] ?? ''),
        'size'    => (string) ($row['size'] ?? ''),
        'source'  => 'BPS',
    ];
}

/**
 * Ambil SATU halaman daftar publikasi (10 baris), ter-cache.
 *
 * @return array{ok:bool, items:array, total:int, pages:int, page:int, error:string}
 */
function bpsFetchPublicationPage(string $domain, int $page = 1, string $keyword = '', int $ttlSeconds = BPS_PUB_CACHE_TTL): array
{
    $fail = ['ok' => false, 'items' => [], 'total' => 0, 'pages' => 0, 'page' => $page, 'error' => ''];

    $keyError = bpsQueryCheckKey();
    if ($keyError !== '') {
        $fail['error'] = $keyError;
        return $fail;
    }

    if ($page < 1) {
        $page = 1;
    }

    $cacheFile = bpsPubPageCacheFile($domain, $page, $keyword);
    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttlSeconds) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['items'])) {
            $cached['page'] = $page;
            return $cached;
        }
    }

    $path = 'list/model/publication/lang/ind/domain/' . urlencode($domain);
    if ($keyword !== '') {
        // Segmen path: pakai rawurlencode supaya spasi jadi %20
        // (urlencode menghasilkan + yang di path dibaca harfiah).
        $path .= '/keyword/' . rawurlencode($keyword);
    }
    $path .= '/page/' . $page . '/key/' . urlencode(BPS_API_KEY) . '/';

    $result = bpsApiCall(BPS_API_BASE . $path, 20);
    if (!$result['ok']) {
        $fail['error'] = $result['error'] ?: 'Gagal mengambil daftar publikasi dari BPS.';
        return $fail;
    }

    $data = $result['json']['data'] ?? null;
    $rows = (is_array($data) && isset($data[1]) && is_array($data[1])) ? $data[1] : [];
    $info = (is_array($data) && isset($data[0]) && is_array($data[0])) ? $data[0] : [];

    $items = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $norm = bpsPubNormalizeRow($row);
        if ($norm['title'] === '') {
            continue;
        }
        $items[] = $norm;
    }

    $out = [
        'ok'    => true,
        'items' => $items,
        'total' => (int) ($info['total'] ?? count($items)),
        'pages' => max(1, (int) ($info['pages'] ?? 1)),
        'page'  => (int) ($info['page'] ?? $page),
        'error' => '',
    ];
    @file_put_contents($cacheFile, json_encode($out));

    return $out;
}

/**
 * Request paralel (curl_multi) untuk sekumpulan URL dipakai mengambil
 * banyak halaman publikasi sekaligus (10 koneksi bersamaan).
 *
 * @param string[] $urls
 * @return array<string, array{http:int, body:string}> key = URL
 */
function bpsPubMultiGet(array $urls, int $timeoutSeconds = 25): array
{
    $mh = curl_multi_init();
    $handles = [];
    foreach ($urls as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_REFERER, 'https://sumut.bps.go.id/');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_multi_add_handle($mh, $ch);
        $handles[$url] = $ch;
    }

    do {
        $mrc = curl_multi_exec($mh, $active);
        if ($active) {
            curl_multi_select($mh, 5);
        }
    } while ($active && $mrc === CURLM_OK);

    $out = [];
    foreach ($handles as $url => $ch) {
        $out[$url] = [
            'http' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'body' => (string) curl_multi_getcontent($ch),
        ];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);

    return $out;
}

/**
 * Ambil SEMUA halaman daftar publikasi (atau semua halaman hasil keyword),
 * ter-cache sebagai satu kesatuan. Urutan hasil: tanggal rilis terbaru dulu.
 *
 * Catatan: 989 publikasi ≈ 99 request; paralel 10 sekaligus ≈ ±15 detik
 * saat cache dingin (sekali per 6 jam). Per halaman juga di-cache terpisah
 * supaya proses yang terputus tidak mengulang dari nol.
 *
 * @return array{ok:bool, items:array, total:int, error:string, partial:bool}
 * partial=true artinya sebagian halaman gagal items berisi yang
 *         berhasil diambil (tidak di-cache), tampilkan $error sebagai warning.
 */
function bpsFetchPublicationsAll(string $domain, string $keyword = '', int $ttlSeconds = BPS_PUB_CACHE_TTL): array
{
    $fail = ['ok' => false, 'items' => [], 'total' => 0, 'error' => '', 'partial' => false];

    $keyError = bpsQueryCheckKey();
    if ($keyError !== '') {
        $fail['error'] = $keyError;
        return $fail;
    }

    // Pengambilan 99 halaman butuh waktu longgarkan batas eksekusi request
    // ini saja (di-suppress, aman kalau fungsi ini dimatikan di hosting).
    @set_time_limit(180);

    $cacheFile = bpsPubAllCacheFile($domain, $keyword);
    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttlSeconds) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['items'])) {
            $cached['partial'] = false;
            return $cached;
        }
    }

    // Halaman 1 dulu (sekalian memberi tahu total halaman).
    $first = bpsFetchPublicationPage($domain, 1, $keyword, $ttlSeconds);
    if (!$first['ok']) {
        $fail['error'] = $first['error'];
        return $fail;
    }

    $pages = min(max(1, $first['pages']), BPS_PUB_MAX_PAGES);
    $all = $first['items'];
    $errors = [];

    if ($pages > 1) {
        $urls = [];
        for ($p = 2; $p <= $pages; $p++) {
            $path = 'list/model/publication/lang/ind/domain/' . urlencode($domain);
            if ($keyword !== '') {
                $path .= '/keyword/' . rawurlencode($keyword);
            }
            $path .= '/page/' . $p . '/key/' . urlencode(BPS_API_KEY) . '/';
            $urls[$p] = BPS_API_BASE . $path;
        }

        // Ambil per batch 10 halaman paralel.
        foreach (array_chunk($urls, 10, true) as $batch) {
            $responses = bpsPubMultiGet(array_values($batch), 25);
            foreach ($batch as $p => $url) {
                $resp = $responses[$url] ?? null;
                $json = is_array($resp) ? json_decode($resp['body'], true) : null;
                if (!is_array($json) || ($json['status'] ?? '') !== 'OK') {
                    $errors[] = $p;
                    continue;
                }
                $data = $json['data'] ?? null;
                $rows = (is_array($data) && isset($data[1]) && is_array($data[1])) ? $data[1] : [];
                $items = [];
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $norm = bpsPubNormalizeRow($row);
                    if ($norm['title'] === '') {
                        continue;
                    }
                    $items[] = $norm;
                    $all[] = $norm;
                }
                // Simpan cache per halaman (lihat komentar fungsi ini).
                @file_put_contents(
                    bpsPubPageCacheFile($domain, $p, $keyword),
                    json_encode(['ok' => true, 'items' => $items, 'total' => $first['total'], 'pages' => $pages, 'page' => $p, 'error' => ''])
                );
            }
        }
    }

    // Terbaru dulu; yang tanpa tanggal ditaruh paling belakang.
    usort($all, function ($a, $b) {
        $da = $a['rl_date'] ?? '';
        $db = $b['rl_date'] ?? '';
        if ($da === $db) {
            return 0;
        }
        if ($da === '') {
            return 1;
        }
        if ($db === '') {
            return -1;
        }
        return strcmp($db, $da);
    });

    if (!empty($errors)) {
        return [
            'ok' => true,
            'items' => $all,
            'total' => count($all),
            'error' => 'Sebagian halaman BPS gagal dimuat (' . count($errors) . ' dari ' . $pages . '), data mungkin belum lengkap. Coba muat ulang beberapa saat lagi.',
            'partial' => true,
        ];
    }

    $out = ['ok' => true, 'items' => $all, 'total' => count($all), 'error' => '', 'partial' => false];
    @file_put_contents($cacheFile, json_encode($out));

    return $out;
}

/**
 * Petakan baris tabel `publikasi` lokal (entri manual admin) ke bentuk yang
 * sama dengan hasil API, supaya bisa digabung & diurutkan bersama.
 *
 * @param array $row baris dari tabel publikasi (no, judul, tanggal_rilis,
 *                   link_publikasi, sampul, dilihat)
 */
function bpsPubNormalizeManualRow(array $row): array
{
    $date = (string) ($row['tanggal_rilis'] ?? '');
    return [
        'no' => (int) ($row['no'] ?? 0),
        'pub_id' => '',
        'title' => (string) ($row['judul'] ?? ''),
        'rl_date' => $date,
        'year' => $date !== '' ? substr($date, 0, 4) : '',
        // Sampul manual = file lokal di assets/img/.
        'cover' => '',
        'cover_local' => (string) ($row['sampul'] ?? ''),
        'pdf' => (string) ($row['link_publikasi'] ?? ''),
        'size' => '',
        'dilihat' => (int) ($row['dilihat'] ?? 0),
        'source' => 'Manual',
    ];
}
