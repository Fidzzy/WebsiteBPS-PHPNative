<?php
/**
 * Unduhan publik Data Ekspor-Impor.
 *
 * Arus tunggal (seperti sebelumnya):
 * GET .../bps_exim_download.php?format=csv|xlsx&sumber=1&kodehs=09&jenishs=1&tahun=2026&periode=2[&pod=...&ctr=...&bulan=1..12]
 * Kolom: No | Arus | Tahun | Bulan | Kode HS | Pelabuhan | Negara | Nilai (US$) | Berat Bersih (KG)
 *
 * Gabungan (tabel permanen di exim.php):
 * GET .../bps_exim_download.php?format=csv|xlsx&sumber=3&tabel=bulanan|rincian&kodehs=09&jenishs=1&tahun=2026&periode=1[&pod=...&ctr=...&bulan=1..12]
 * Kolom: Bulan/Kode HS | Nilai Ekspor (US$) | Berat Ekspor (KG) | Nilai Impor (US$) | Berat Impor (KG)
 *
 * Parameter pod/ctr/bulan bersifat OPSIONAL dan disaring server-side agar
 * isi berkas sama dengan tabel tersaring di halaman exim.php.
 */

require_once '../../includes/auth.php';
require_once '../../includes/bps_client.php';
require_once '../../includes/xlsx_helper.php';

function exdlBad(string $msg): void
{
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

function exdlGet(string $name, string $default = ''): string
{
    return trim($_GET[$name] ?? $default);
}

$format = strtolower(exdlGet('format', 'csv'));
if (!in_array($format, ['csv', 'xlsx'], true)) {
    exdlBad('Parameter format harus csv atau xlsx.');
}

$sumber = exdlGet('sumber', '1');
$kodehs = exdlGet('kodehs', '09');
$jenishs = exdlGet('jenishs', '1');
$tahun = exdlGet('tahun', date('Y'));
$periode = exdlGet('periode', '2');

if (!in_array($sumber, ['1', '2', '3'], true)) {
    exdlBad('Parameter sumber harus 1 (Ekspor), 2 (Impor), atau 3 (Gabungan).');
}
if ($kodehs === '' || !preg_match('/^[A-Za-z0-9;]+$/', $kodehs)) {
    exdlBad('Parameter kodehs wajib diisi.');
}
if (!in_array($jenishs, ['1', '2'], true)) {
    exdlBad('Parameter jenishs harus 1 atau 2.');
}
if ($tahun === '' || !preg_match('/^[\d;]+$/', $tahun)) {
    exdlBad('Parameter tahun wajib diisi.');
}
if (!in_array($periode, ['1', '2'], true)) {
    exdlBad('Parameter periode harus 1 atau 2.');
}

// Filter tampilan opsional (agar unduhan = tabel tersaring di exim.php).
$podFilter = mb_substr(exdlGet('pod', ''), 0, 200);
$ctrFilter = mb_substr(exdlGet('ctr', ''), 0, 200);
$bulanFilter = exdlGet('bulan', '');
if ($bulanFilter !== '' && !preg_match('/^(?:[1-9]|1[0-2])$/', $bulanFilter)) {
    exdlBad('Parameter bulan harus 1-12 atau kosong.');
}
$bulanFilter = $bulanFilter === '' ? 0 : (int) $bulanFilter;

/**
 * Petakan label bulan API ("[01] Januari", "Februari", "1", ...) ke 1-12.
 */
function exdlMonthNum($bulanStr): int
{
    $s = (string) $bulanStr;
    if (preg_match('/\[(\d+)\]/', $s, $m)) {
        return (int) $m[1];
    }
    $low = mb_strtolower($s);
    $names = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];
    foreach ($names as $i => $nm) {
        if (mb_strpos($low, $nm) !== false) {
            return $i + 1;
        }
    }
    if (preg_match('/\b(1[0-2]|[1-9])\b/', $s, $m2)) {
        return (int) $m2[1];
    }
    return 0;
}
/**
 * Tulis berkas unduhan (CSV atau XLSX) lalu hentikan eksekusi.
 */
function exdlOutput(string $format, string $baseName, array $header, array $rows, array $widths, array $numCols): void
{
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $baseName . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    $bin = xlsxBuildBinary($header, $rows, $widths, $numCols);
    if ($bin === '') {
        exdlBad('Ekstensi ZipArchive PHP tidak aktif, unduhan XLSX tidak tersedia. Gunakan format CSV.');
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $baseName . '.xlsx"');
    header('Content-Length: ' . strlen($bin));
    echo $bin;
    exit;
}

$monthNamesId = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
$hsList = array_values(array_filter(array_map('trim', explode(';', $kodehs)), fn($v) => $v !== ''));
if (count($hsList) > 5) {
    exdlBad('Maksimal 5 kode HS per unduhan.');
}

$cacheDir = __DIR__ . '/../../cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}

// Mode gabungan (sumber=3): agregat ekspor + impor berdampingan untuk
// tabel permanen di exim.php. Memakai cache yang SAMA dengan
// bps_exim_api.php?sumber=3 (kunci md5 identik).
if ($sumber === '3') {
    $tabel = strtolower(exdlGet('tabel', 'bulanan'));
    if (!in_array($tabel, ['bulanan', 'rincian'], true)) {
        exdlBad('Parameter tabel harus bulanan atau rincian.');
    }
    $ck3 = md5('exim|3|' . implode(';', $hsList) . '|' . $jenishs . '|' . $tahun . '|' . $periode);
    $cacheFile3 = $cacheDir . '/bps_exim_' . $ck3 . '.json';
    $combo = null;
    if (is_file($cacheFile3) && (time() - filemtime($cacheFile3)) < 21600) {
        $cached3 = json_decode((string) file_get_contents($cacheFile3), true);
        if (is_array($cached3) && ($cached3['status'] ?? '') === 'OK'
            && isset($cached3['data']['ekspor'], $cached3['data']['impor'])
            && is_array($cached3['data']['ekspor']) && is_array($cached3['data']['impor'])) {
            $combo = $cached3['data'];
        }
    }
    if ($combo === null) {
        $re = bpsFetchEximRaw('1', implode(';', $hsList), $jenishs, $tahun, $periode);
        if (!$re['ok']) {
            exdlBad('Gagal mengambil data ekspor dari BPS: ' . $re['error']);
        }
        $ri = bpsFetchEximRaw('2', implode(';', $hsList), $jenishs, $tahun, $periode);
        if (!$ri['ok']) {
            exdlBad('Gagal mengambil data impor dari BPS: ' . $ri['error']);
        }
        $combo = [
            'ekspor' => (isset($re['json']['data']) && is_array($re['json']['data'])) ? $re['json']['data'] : [],
            'impor' => (isset($ri['json']['data']) && is_array($ri['json']['data'])) ? $ri['json']['data'] : [],
        ];
        @file_put_contents($cacheFile3, json_encode([
            'status' => 'OK',
            'metadata' => $re['json']['metadata'] ?? null,
            'data' => $combo,
        ]));
    }

    if ($tabel === 'bulanan') {
        $mE = [];
        $mI = [];
        foreach (['ekspor' => &$mE, 'impor' => &$mI] as $arus => &$acc) {
            foreach ($combo[$arus] as $d) {
                if (!is_array($d)) {
                    continue;
                }
                if ($podFilter !== '' && (string) ($d['pod'] ?? '') !== $podFilter) {
                    continue;
                }
                if ($ctrFilter !== '' && (string) ($d['ctr'] ?? '') !== $ctrFilter) {
                    continue;
                }
                $m = exdlMonthNum($d['bulan'] ?? '');
                if ($m < 1 || $m > 12) {
                    continue;
                }
                $acc[$m][0] = ($acc[$m][0] ?? 0) + (float) ($d['value'] ?? 0);
                $acc[$m][1] = ($acc[$m][1] ?? 0) + (float) ($d['netweight'] ?? 0);
            }
        }
        unset($acc);
        $months = array_unique(array_merge(array_keys($mE), array_keys($mI)));
        sort($months, SORT_NUMERIC);
        $header = ['Bulan', 'Nilai Ekspor (US$)', 'Berat Ekspor (KG)', 'Nilai Impor (US$)', 'Berat Impor (KG)'];
        $rows = [];
        $tot = [0, 0, 0, 0];
        foreach ($months as $m) {
            $ev = $mE[$m][0] ?? 0;
            $ew = $mE[$m][1] ?? 0;
            $iv = $mI[$m][0] ?? 0;
            $iw = $mI[$m][1] ?? 0;
            $rows[] = [$monthNamesId[$m], $ev, $ew, $iv, $iw];
            $tot[0] += $ev;
            $tot[1] += $ew;
            $tot[2] += $iv;
            $tot[3] += $iw;
        }
        $rows[] = ['TOTAL', $tot[0], $tot[1], $tot[2], $tot[3]];
        exdlOutput(
            $format,
            'exim-gabung-bulanan-' . preg_replace('/[^0-9]/', '', $tahun),
            $header,
            $rows,
            [16, 20, 20, 20, 20],
            [1, 2, 3, 4]
        );
    }

    // tabel=rincian: agregat per kode HS (bulan disaring bila diisi).
    $g = [];
    $addArus = function (array $list, int $idx) use (&$g, $podFilter, $ctrFilter, $periode, $bulanFilter): void {
        foreach ($list as $d) {
            if (!is_array($d)) {
                continue;
            }
            if ($podFilter !== '' && (string) ($d['pod'] ?? '') !== $podFilter) {
                continue;
            }
            if ($ctrFilter !== '' && (string) ($d['ctr'] ?? '') !== $ctrFilter) {
                continue;
            }
            if ($periode === '1' && $bulanFilter > 0 && exdlMonthNum($d['bulan'] ?? '') !== $bulanFilter) {
                continue;
            }
            $k = (string) ($d['kodehs'] ?? '(Tanpa HS)');
            if (!isset($g[$k])) {
                $g[$k] = [0, 0, 0, 0];
            }
            $g[$k][$idx] += (float) ($d['value'] ?? 0);
            $g[$k][$idx + 1] += (float) ($d['netweight'] ?? 0);
        }
    };
    $addArus($combo['ekspor'], 0);
    $addArus($combo['impor'], 2);
    ksort($g, SORT_STRING);
    $header = ['Kode HS', 'Nilai Ekspor (US$)', 'Berat Ekspor (KG)', 'Nilai Impor (US$)', 'Berat Impor (KG)'];
    $rows = [];
    $tot = [0, 0, 0, 0];
    foreach ($g as $k => $v) {
        $rows[] = [$k, $v[0], $v[1], $v[2], $v[3]];
        $tot[0] += $v[0];
        $tot[1] += $v[1];
        $tot[2] += $v[2];
        $tot[3] += $v[3];
    }
    $rows[] = ['TOTAL', $tot[0], $tot[1], $tot[2], $tot[3]];
    exdlOutput(
        $format,
        'exim-gabung-rincian-' . preg_replace('/[^0-9]/', '', $tahun) . ($bulanFilter > 0 ? '-b' . $bulanFilter : ''),
        $header,
        $rows,
        [44, 20, 20, 20, 20],
        [1, 2, 3, 4]
    );
}
$ck = md5('exim|' . $sumber . '|' . implode(';', $hsList) . '|' . $jenishs . '|' . $tahun . '|' . $periode);
$cacheFile = $cacheDir . '/bps_exim_' . $ck . '.json';
$json = null;
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 21600) {
    $cached = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($cached) && ($cached['status'] ?? '') === 'OK') {
        $json = $cached;
    }
}
if ($json === null) {
    $r = bpsFetchEximRaw($sumber, implode(';', $hsList), $jenishs, $tahun, $periode);
    if (!$r['ok']) {
        exdlBad('Gagal mengambil data dari BPS: ' . $r['error']);
    }
    $json = $r['json'];
    // Write-through: simpan hasil segar ke cache yang SAMA dengan yang
    // dipakai bps_exim_api.php (kunci md5 identik), supaya unduhan
    // berikutnya / tampilan tabel tidak mengulang request ke BPS.
    @file_put_contents($cacheFile, json_encode($json));
}

$arusLabel = $sumber === '1' ? 'Ekspor' : 'Impor';
$data = (isset($json['data']) && is_array($json['data'])) ? $json['data'] : [];

$header = ['No', 'Arus', 'Tahun', 'Bulan', 'Kode HS', 'Pelabuhan', 'Negara', 'Nilai (US$)', 'Berat Bersih (KG)'];
$rows = [];
$no = 1;
foreach ($data as $d) {
    if (!is_array($d)) {
        continue;
    }
    if ($podFilter !== '' && (string) ($d['pod'] ?? '') !== $podFilter) {
        continue;
    }
    if ($ctrFilter !== '' && (string) ($d['ctr'] ?? '') !== $ctrFilter) {
        continue;
    }
    if ($periode === '1' && $bulanFilter > 0 && exdlMonthNum($d['bulan'] ?? '') !== $bulanFilter) {
        continue;
    }
    $rows[] = [
        $no++,
        $arusLabel,
        (string) ($d['tahun'] ?? ''),
        (string) ($d['bulan'] ?? '-'),
        (string) ($d['kodehs'] ?? ''),
        (string) ($d['pod'] ?? ''),
        (string) ($d['ctr'] ?? ''),
        (string) ($d['value'] ?? ''),
        (string) ($d['netweight'] ?? ''),
    ];
}

$baseName = 'exim-' . strtolower($arusLabel) . '-' . preg_replace('/[^0-9]/', '', $tahun);

exdlOutput($format, $baseName, $header, $rows, [6, 10, 10, 16, 40, 24, 22, 18, 18], [0, 7, 8]);
