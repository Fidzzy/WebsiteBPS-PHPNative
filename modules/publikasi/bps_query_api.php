<?php
/**
 * Proxy publik untuk Query Builder Tabel Dinamis (data_dinamis.php).
 *
 * Semua request ke BPS Web API dilakukan SERVER-SIDE supaya API key tidak
 * pernah terekspos ke browser. Read-only, tanpa perlu login.
 *
 * Actions:
 *   ?action=subcats&domain=1200
 *   ?action=subjects&domain=1200&subcat=1
 *   ?action=subcatcsa&domain=1200
 *   ?action=subjectcsa&domain=1200&subcat=515
 *   ?action=vars&domain=1200&keyword=inflasi&subject=2&subjectcsa=522&page=1
 *   ?action=th&domain=1200&var=762
 *   ?action=turth&domain=1200&var=762
 *   ?action=turvar&domain=1200&var=762
 *   ?action=vervar&domain=1200&var=762
 *   ?action=data&domain=1200&var=762&th=126&vervar=&turvar=&turth=
 *
 * Action subcatcsa/subjectcsa = klasifikasi CSA (dipakai laman
 * query-builder BPS terbaru); subcats/subjects = klasifikasi lama.
 * Response: { success, message, data }
 */

require_once '../../includes/auth.php';
require_once '../../includes/bps_client.php';

header('Content-Type: application/json; charset=utf-8');

function qbRespond(bool $success, string $message, $data = [], array $extra = []): void
{
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data] + $extra);
    exit;
}

function qbGet(string $name, string $default = ''): string
{
    return trim($_GET[$name] ?? $default);
}

function qbDomain(): string
{
    $d = qbGet('domain', BPS_DOMAIN);
    return preg_match('/^\d+$/', $d) ? $d : BPS_DOMAIN;
}

function qbVar(): string
{
    return qbGet('var');
}

// Cache file sederhana per action+parameter (TTL 1 jam). Kegagalan tidak
// di-cache supaya perbaikan di sisi BPS langsung terasa.
function qbCacheGet(string $key, int $ttl = 3600): ?array
{
    $file = __DIR__ . '/../../cache/bps_qb_' . $key . '.json';
    if (is_file($file) && (time() - filemtime($file)) < $ttl) {
        $cached = json_decode((string) file_get_contents($file), true);
        if (is_array($cached)) {
            return $cached;
        }
    }
    return null;
}

function qbCachePut(string $key, array $payload): void
{
    $dir = __DIR__ . '/../../cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($dir . '/bps_qb_' . $key . '.json', json_encode($payload));
}

$action = qbGet('action');
$domain = qbDomain();

switch ($action) {
    case 'subcats': {
        $ck = md5('subcats|' . $domain);
        $cached = qbCacheGet($ck);
        if ($cached !== null) {
            qbRespond(true, 'OK (cache).', $cached);
        }
        $r = bpsFetchSubcats($domain);
        if (!$r['ok']) {
            qbRespond(false, $r['error']);
        }
        qbCachePut($ck, $r['items']);
        qbRespond(true, count($r['items']) . ' kategori ditemukan.', $r['items']);
    }

    case 'subjects': {
        $subcat = qbGet('subcat');
        if ($subcat !== '' && !preg_match('/^\d+$/', $subcat)) {
            qbRespond(false, 'Parameter subcat tidak valid.');
        }
        $ck = md5('subjects|' . $domain . '|' . $subcat);
        $cached = qbCacheGet($ck);
        if ($cached !== null) {
            qbRespond(true, 'OK (cache).', $cached);
        }
        $r = bpsFetchSubjects($domain, $subcat !== '' ? $subcat : null);
        if (!$r['ok']) {
            qbRespond(false, $r['error']);
        }
        qbCachePut($ck, $r['items']);
        qbRespond(true, count($r['items']) . ' subjek ditemukan.', $r['items']);
    }

    case 'vars': {
        $keyword = qbGet('keyword');
        $subject = qbGet('subject');
        $subjectcsa = qbGet('subjectcsa');
        $page = (int) qbGet('page', '1');
        if ($page < 1) {
            $page = 1;
        }
        if ($subject !== '' && !preg_match('/^\d+$/', $subject)) {
            qbRespond(false, 'Parameter subject tidak valid.');
        }
        if ($subjectcsa !== '' && !preg_match('/^\d+$/', $subjectcsa)) {
            qbRespond(false, 'Parameter subjectcsa tidak valid.');
        }
        $ck = md5('vars|' . $domain . '|' . $keyword . '|' . $subject . '|' . $subjectcsa . '|' . $page);
        $cached = qbCacheGet($ck);
        if ($cached !== null) {
            // Cache baru: {items, total}; cache lama: langsung array items.
            if (is_array($cached) && array_key_exists('items', $cached)) {
                $ct = (int) ($cached['total'] ?? count($cached['items']));
                qbRespond(true, 'OK (cache).', $cached['items'], ['total' => $ct, 'pages' => max(1, (int) ceil($ct / 10)), 'page' => $page]);
            } elseif (is_array($cached)) {
                qbRespond(true, 'OK (cache).', $cached, ['total' => count($cached), 'pages' => 1, 'page' => $page]);
            }
        }
        $r = bpsFetchVariablesForBuilder($domain, $keyword, $subject !== '' ? $subject : null, $page, $subjectcsa !== '' ? $subjectcsa : null);
        if (!$r['ok']) {
            qbRespond(false, $r['error']);
        }
        $total = (int) ($r['total'] ?? count($r['items']));
        qbCachePut($ck, ['items' => $r['items'], 'total' => $total]);
        qbRespond(true, count($r['items']) . ' tabel ditemukan.', $r['items'], ['total' => $total, 'pages' => max(1, (int) ceil($total / 10)), 'page' => $page]);
    }

    case 'th': {
        $var = qbVar();
        if (!preg_match('/^\d+$/', $var)) {
            qbRespond(false, 'Parameter var wajib diisi (angka).');
        }
        // Pakai versi cached yang sudah ada (TTL 1 jam).
        $r = bpsFetchPeriodListCached($domain, $var);
        if (!$r['ok']) {
            qbRespond(false, $r['error']);
        }
        $items = array_map(fn($p) => [
            'id' => (string) $p['val'],
            'label' => (string) $p['label'],
            'raw' => $p,
        ], $r['items']);
        qbRespond(true, count($items) . ' periode ditemukan.', $items);
    }

    case 'turth':
    case 'turvar':
    case 'vervar': {
        $var = qbVar();
        if (!preg_match('/^\d+$/', $var)) {
            qbRespond(false, 'Parameter var wajib diisi (angka).');
        }
        $ck = md5($action . '|' . $domain . '|' . $var);
        $cached = qbCacheGet($ck);
        if ($cached !== null) {
            qbRespond(true, 'OK (cache).', $cached);
        }
        if ($action === 'turth') {
            $r = bpsFetchTurthList($domain, $var);
        } elseif ($action === 'turvar') {
            $r = bpsFetchTurvarList($domain, $var);
        } else {
            $r = bpsFetchVervarList($domain, $var);
        }
        if (!$r['ok']) {
            qbRespond(false, $r['error']);
        }
        qbCachePut($ck, $r['items']);
        qbRespond(true, count($r['items']) . ' pilihan ditemukan.', $r['items']);
    }

    case 'subcatcsa': {
        $ck = md5('subcatcsa|' . $domain);
        $cached = qbCacheGet($ck);
        if ($cached !== null) {
            qbRespond(true, 'OK (cache).', $cached);
        }
        $r = bpsFetchSubcatCsa($domain);
        if (!$r['ok']) {
            qbRespond(false, $r['error']);
        }
        qbCachePut($ck, $r['items']);
        qbRespond(true, count($r['items']) . ' kategori ditemukan.', $r['items']);
    }

    case 'subjectcsa': {
        $subcat = qbGet('subcat');
        if ($subcat !== '' && !preg_match('/^\d+$/', $subcat)) {
            qbRespond(false, 'Parameter subcat tidak valid.');
        }
        $ck = md5('subjectcsa|' . $domain . '|' . $subcat);
        $cached = qbCacheGet($ck);
        if ($cached !== null) {
            qbRespond(true, 'OK (cache).', $cached);
        }
        $r = bpsFetchSubjectCsa($domain, $subcat !== '' ? $subcat : null);
        if (!$r['ok']) {
            qbRespond(false, $r['error']);
        }
        qbCachePut($ck, $r['items']);
        qbRespond(true, count($r['items']) . ' subjek ditemukan.', $r['items']);
    }

    case 'data': {
        $var = qbVar();
        $th = qbGet('th');
        if (!preg_match('/^\d+$/', $var)) {
            qbRespond(false, 'Parameter var wajib diisi (angka).');
        }
        // th boleh "1;2" (multi) atau "1:6" (rentang) sesuai dokumentasi BPS.
        if (!preg_match('/^[\d;:]+$/', $th) || $th === '') {
            qbRespond(false, 'Parameter th wajib diisi.');
        }
        $vervar = qbGet('vervar');
        $turvar = qbGet('turvar');
        $turth = qbGet('turth');
        foreach (['vervar' => $vervar, 'turvar' => $turvar, 'turth' => $turth] as $k => $v) {
            if ($v !== '' && !preg_match('/^[\d;:]+$/', $v)) {
                qbRespond(false, "Parameter $k tidak valid.");
            }
        }
        $ck = md5('data|' . $domain . '|' . $var . '|' . $th . '|' . $vervar . '|' . $turvar . '|' . $turth);
        $cached = qbCacheGet($ck);
        if ($cached !== null) {
            qbRespond(true, 'OK (cache).', $cached);
        }
        $r = bpsFetchDynamicDataRaw(
            $domain,
            $var,
            $th,
            $vervar !== '' ? $vervar : null,
            $turvar !== '' ? $turvar : null,
            $turth !== '' ? $turth : null
        );
        if (!$r['ok']) {
            qbRespond(false, $r['error'], $r['json']);
        }
        qbCachePut($ck, $r['json']);
        qbRespond(true, 'Data berhasil diambil.', $r['json']);
    }

    default:
        qbRespond(false, 'Action tidak dikenal. Pilihan: subcats, subjects, subcatcsa, subjectcsa, vars, th, turth, turvar, vervar, data.');
}
