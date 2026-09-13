<?php
/**
 * Unduhan publik hasil Query Builder Tabel Dinamis.
 *
 * GET modules/publikasi/bps_query_download.php?format=csv|xlsx&domain=1200&var=762&th=126&vervar=&turvar=&turth=
 * var & th wajib (th boleh "126;125").
 * vervar/turvar/turth kosong = semua (sama seperti proxy data).
 *
 * Data diambil server-side (API key aman), diflatkan jadi baris:
 *   No | Tabel | Satuan | Judul Baris | Karakteristik | Tahun | Periode | Nilai
 * format=xlsx menghasilkan file Excel asli (Office Open XML) via ZipArchive.
 */

require_once '../../includes/auth.php';
require_once '../../includes/bps_client.php';

function dlBad(string $msg): void
{
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

function dlGet(string $name, string $default = ''): string
{
    return trim($_GET[$name] ?? $default);
}

$format = strtolower(dlGet('format', 'csv'));
if (!in_array($format, ['csv', 'xlsx'], true)) {
    dlBad('Parameter format harus csv atau xlsx.');
}

$domain = dlGet('domain', BPS_DOMAIN);
if (!preg_match('/^\d+$/', $domain)) {
    $domain = BPS_DOMAIN;
}
$var = dlGet('var');
$th = dlGet('th');
if (!preg_match('/^\d+$/', $var)) {
    dlBad('Parameter var wajib diisi (angka).');
}
if ($th === '' || !preg_match('/^[\d;:]+$/', $th)) {
    dlBad('Parameter th wajib diisi.');
}
$vervar = dlGet('vervar');
$turvar = dlGet('turvar');
$turth = dlGet('turth');
foreach (['vervar' => $vervar, 'turvar' => $turvar, 'turth' => $turth] as $k => $v) {
    if ($v !== '' && !preg_match('/^[\d;:]+$/', $v)) {
        dlBad("Parameter $k tidak valid.");
    }
}

// Pakai cache yang sama dengan proxy data bila ada (kunci identik).
$ck = md5('data|' . $domain . '|' . $var . '|' . $th . '|' . $vervar . '|' . $turvar . '|' . $turth);
$cacheFile = __DIR__ . '/../../cache/bps_qb_' . $ck . '.json';
$json = null;
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
    $cached = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($cached) && ($cached['status'] ?? '') === 'OK') {
        $json = $cached;
    }
}
if ($json === null) {
    $r = bpsFetchDynamicDataRaw(
        $domain, $var, $th,
        $vervar !== '' ? $vervar : null,
        $turvar !== '' ? $turvar : null,
        $turth !== '' ? $turth : null
    );
    if (!$r['ok']) {
        dlBad('Gagal mengambil data dari BPS: ' . $r['error']);
    }
    $json = $r['json'];
}

$title = ($json['var'][0]['label'] ?? ('Tabel var ' . $var));
$unit = (string) ($json['var'][0]['unit'] ?? '');
$dc = (isset($json['datacontent']) && is_array($json['datacontent'])) ? $json['datacontent'] : [];
$vervarList = $json['vervar'] ?? [];
$turvarList = $json['turvar'] ?? [];
$tahunList = $json['tahun'] ?? [];
$turtahunList = $json['turtahun'] ?? [['val' => 0, 'label' => 'Tahun']];
$subAnnual = count($turtahunList) > 1;

$header = ['No', 'Tabel', 'Satuan', 'Judul Baris', 'Karakteristik', 'Tahun', 'Periode', 'Nilai'];
$rows = [];
$no = 1;
foreach ($vervarList as $vv) {
    foreach ($turvarList as $tv) {
        foreach ($tahunList as $t) {
            foreach ($turtahunList as $tt) {
                $key = (string) ($vv['val'] ?? '') . $var
                    . (string) ($tv['val'] ?? '')
                    . (string) ($t['val'] ?? '')
                    . (string) ($tt['val'] ?? '');
                if (!isset($dc[$key]) || $dc[$key] === '') {
                    continue;
                }
                $tahunLabel = (string) ($t['label'] ?? $t['val'] ?? '');
                $ttLabel = (string) ($tt['label'] ?? '');
                $periode = ($subAnnual && $ttLabel !== '' && $ttLabel !== 'Tahun')
                    ? ($ttLabel . ' ' . $tahunLabel)
                    : $tahunLabel;
                $rows[] = [
                    $no++,
                    $title,
                    $unit,
                    (string) ($vv['label'] ?? $vv['val'] ?? ''),
                    (string) ($tv['label'] ?? $tv['val'] ?? ''),
                    $tahunLabel,
                    $periode,
                    (string) $dc[$key],
                ];
            }
        }
    }
}

$safeVar = preg_replace('/[^0-9]/', '', $var);
$baseName = 'tabel-bps-var' . ($safeVar !== '' ? $safeVar : 'data');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $baseName . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel baca UTF-8
    fputcsv($out, $header);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

// XLSX (Office Open XML minimal, inline strings)
if (!class_exists('ZipArchive')) {
    dlBad('Ekstensi ZipArchive PHP tidak aktif, unduhan XLSX tidak tersedia. Gunakan format CSV.');
}

$xmlEsc = fn($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_COMPAT, 'UTF-8');
$colLetter = function (int $i): string {
    $s = '';
    $i++;
    while ($i > 0) {
        $m = ($i - 1) % 26;
        $s = chr(65 + $m) . $s;
        $i = (int) (($i - 1) / 26);
    }
    return $s;
};

// Kolom A=No (6), B=Tabel (50), C=Satuan (14), D=Baris (30),
// E=Karakteristik (24), F=Tahun (12), G=Periode (18), H=Nilai (16)
$widths = [6, 50, 14, 30, 24, 12, 18, 16];

$sheetData = '';
$emitRow = function (array $cells, int $r, bool $isHeader) use (&$sheetData, $xmlEsc, $colLetter): void {
    $sheetData .= '<row r="' . $r . '">';
    foreach ($cells as $i => $v) {
        $ref = $colLetter($i) . $r;
        // Nilai numerik murni (titik desimal) disimpan sebagai angka.
        if (!$isHeader && $i === 0 && is_numeric($v)) {
            $sheetData .= '<c r="' . $ref . '" s="0"><v>' . $v . '</v></c>';
        } elseif (!$isHeader && $i === 7 && preg_match('/^-?\d+(\.\d+)?$/', (string) $v)) {
            $sheetData .= '<c r="' . $ref . '" s="0"><v>' . $v . '</v></c>';
        } else {
            $sheetData .= '<c r="' . $ref . '" t="inlineStr" s="' . ($isHeader ? '1' : '0') . '"><is><t>'
                . $xmlEsc($v) . '</t></is></c>';
        }
    }
    $sheetData .= '</row>';
};

$emitRow($header, 1, true);
$r = 2;
foreach ($rows as $row) {
    $emitRow($row, $r++, false);
}

$colsXml = '<cols>';
foreach ($widths as $i => $w) {
    $colsXml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
}
$colsXml .= '</cols>';

$files = [
    '[Content_Types].xml' =>
        '<?xml version="1.0" encoding="UTF-8"?>' .
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml" ContentType="application/xml"/>' .
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
        '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
        '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
        '</Types>',
    '_rels/.rels' =>
        '<?xml version="1.0" encoding="UTF-8"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
        '</Relationships>',
    'xl/workbook.xml' =>
        '<?xml version="1.0" encoding="UTF-8"?>' .
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<sheets><sheet name="Data" sheetId="1" r:id="rId1"/></sheets>' .
        '</workbook>',
    'xl/_rels/workbook.xml.rels' =>
        '<?xml version="1.0" encoding="UTF-8"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
        '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="../styles.xml"/>' .
        '</Relationships>',
    'xl/worksheets/sheet1.xml' =>
        '<?xml version="1.0" encoding="UTF-8"?>' .
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        $colsXml .
        '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' .
        '<sheetData>' . $sheetData . '</sheetData>' .
        '</worksheet>',
    'xl/styles.xml' =>
        '<?xml version="1.0" encoding="UTF-8"?>' .
        '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<fonts><font><sz val="11"/><name val="Calibri"/></font>' .
        '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>' .
        '<fills><fill><patternFill patternType="none"/></fill>' .
        '<fill><patternFill patternType="gray125"/></fill>' .
        '<fill><patternFill patternType="solid"><fgColor rgb="FF002B6A"/><bgColor indexed="64"/></patternFill></fill></fills>' .
        '<borders><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
        '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
        '<cellXfs count="2">' .
        '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' .
        '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFill="1"/>' .
        '</cellXfs>' .
        '</styleSheet>',
];

$tmp = tempnam(sys_get_temp_dir(), 'bpsxlsx_');
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    @unlink($tmp);
    dlBad('Gagal membuat berkas XLSX.');
}
foreach ($files as $name => $content) {
    $zip->addFromString($name, $content);
}
$zip->close();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $baseName . '.xlsx"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
exit;
