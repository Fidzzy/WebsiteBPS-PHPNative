<?php
/**
 * Builder XLSX minimal (Office Open XML, inline strings) tanpa library.
 * Dipakai endpoint unduhan server-side. Return binary string, atau '' bila
 * ZipArchive tidak tersedia / gagal.
 *
 * @param string[] $header
 * @param array    $rows   tiap baris = array sel (string|int|float)
 * @param int[]    $widths lebar kolom (sejumlah $header)
 * @param int[]    $numCols indeks kolom yang numerik (disimpan sbg angka)
 */
function xlsxBuildBinary(array $header, array $rows, array $widths, array $numCols = []): string
{
    if (!class_exists('ZipArchive')) {
        return '';
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

    $sheetData = '';
    $emitRow = function (array $cells, int $r, bool $isHeader) use (&$sheetData, $xmlEsc, $colLetter, $numCols): void {
        $sheetData .= '<row r="' . $r . '">';
        foreach ($cells as $i => $v) {
            $ref = $colLetter($i) . $r;
            $isNum = !$isHeader && in_array($i, $numCols, true)
                && preg_match('/^-?\d+(\.\d+)?$/', (string) $v);
            if ($isNum) {
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
        $emitRow(array_values($row), $r++, false);
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
        return '';
    }
    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();
    $bin = (string) file_get_contents($tmp);
    @unlink($tmp);
    return $bin;
}
