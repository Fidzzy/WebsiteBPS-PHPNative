<?php
/**
 * Helper untuk konsumsi BPS Web API (dynamic table / Data Statistik Terkini).
 *
 * Struktur response di sini mengikuti implementasi resmi BPS di package
 * Python "stadata" (https://github.com/bps-statistics/stadata), supaya
 * parsing-nya sesuai dokumentasi API yang sebenarnya, bukan tebakan.
 *
 * Referensi struktur:
 * model=var list variabel dinamis yang tersedia untuk suatu domain.
 *   Response: { status, data: [ {page,pages,...}, [ {var_id,title,unit,...}, ... ] ] }
 * model=data nilai data untuk satu var_id.
 *   Response: {
 *     status, datacontent: { "<vervar><var><turvar><tahun>": "nilai", ... },
 *     vervar: [{val,label}, ...],   // wilayah (kab/kota/provinsi)
 *     turvar: [{val,label}, ...],   // turunan variabel (mis. laki-laki/perempuan)
 *     tahun:  [{val,label}, ...],   // tahun data tersedia
 *     turtahun: [{val,label}, ...]  // opsional, turunan periode (bulan/triwulan)
 *   }
 *
 * PENTING: var_id BERBEDA-BEDA per tabel/subjek dan tidak universal antar
 * domain. Jangan menebak var_id cari dulu var_id yang benar lewat
 * bpsFetchVariableList() (bisa dipakai admin lewat
 * modules/publikasi/bps_variable_search.php) sebelum memasukkannya ke
 * config/bps_indicators.php.
 */

require_once __DIR__ . '/../config/bps_api.php';

/**
 * Panggil BPS Web API mentah lewat cURL, dengan pengaturan aman
 * (SSL verification aktif, timeout, dsb.) konsisten dengan proxy lain
 * di project ini (bps_search.php, bps_detail.php).
 *
 * @return array{ok: bool, json: array|null, httpCode: int, error: string}
 */
function bpsApiCall(string $url, int $timeoutSeconds = 15): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    // WAF BPS memblokir request tanpa header browser (terbukti: curl
    // default dibalas halaman "Perimeter WAF Block", sedangkan dengan
    // User-Agent + Referer browser respons JSON OK). Konsisten dengan
    // unduhan PDF/sampul di modules/publikasi/bps_detail.php.
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_REFERER, 'https://sumut.bps.go.id/');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'json' => null, 'httpCode' => $httpCode, 'error' => $error];
    }

    $json = json_decode($response, true);
    if (!is_array($json) || ($json['status'] ?? '') !== 'OK') {
        return ['ok' => false, 'json' => $json, 'httpCode' => $httpCode, 'error' => $json['message'] ?? 'Respons tidak valid dari BPS.'];
    }

    return ['ok' => true, 'json' => $json, 'httpCode' => $httpCode, 'error' => ''];
}

/**
 * Cari daftar variabel dinamis yang tersedia untuk domain tertentu,
 * opsional difilter kata kunci judul. Dipakai untuk MENEMUKAN var_id yang
 * benar sebelum dikonfigurasi di config/bps_indicators.php.
 *
 * @return array{ok: bool, items: array, error: string}
 *         items: list of ['var_id'=>, 'title'=>, 'unit'=>, 'subject'=>]
 */
function bpsFetchVariableList(string $domain, string $keyword = '', int $page = 1): array
{
    if (BPS_API_KEY === 'GANTI_DENGAN_API_KEY_ANDA' || BPS_API_KEY === '') {
        return ['ok' => false, 'items' => [], 'error' => 'API key BPS belum dikonfigurasi di config/bps_api.php.'];
    }

    $url = BPS_API_BASE . 'list/model/var/lang/ind/domain/' . urlencode($domain)
        . '/keyword/' . urlencode($keyword)
        . '/page/' . $page
        . '/key/' . urlencode(BPS_API_KEY) . '/';

    $result = bpsApiCall($url);
    if (!$result['ok']) {
        return ['ok' => false, 'items' => [], 'error' => $result['error'] ?: 'Gagal mengambil daftar variabel dari BPS.'];
    }

    $rows = $result['json']['data'][1] ?? [];
    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'var_id'  => $row['var_id'] ?? '',
            'title'   => $row['title'] ?? '',
            'unit'    => $row['unit'] ?? '',
            'subject' => $row['sub_name'] ?? '',
        ];
    }

    return ['ok' => true, 'items' => $items, 'error' => ''];
}

/**
 * Cari daftar periode (tahun) yang benar-benar tersedia untuk satu var_id,
 * lewat endpoint model=th BPS Web API. WAJIB dipanggil sebelum
 * bpsFetchIndicator(), karena BPS mewajibkan parameter 'th' diisi di
 * request model=data request tanpa 'th' akan ditolak dengan error
 * "'th' parameter is required...".
 *
 * Referensi struktur & path parameter (bukan query string) dikonfirmasi
 * dari source code client resmi open-source (digimetalab/dml-bps-mcp,
 * lihat src/client/endpoints.ts: PATH_PARAMS termasuk 'th', dan
 * src/client/types.ts: BpsPeriod { th_id, th_name, val }).
 *
 * @return array{ok: bool, items: array<array{th_id:int|string, label:string, val:int|string}>, error: string}
 */
function bpsFetchPeriodList(string $domain, string $varId): array
{
    if (BPS_API_KEY === 'GANTI_DENGAN_API_KEY_ANDA' || BPS_API_KEY === '') {
        return ['ok' => false, 'items' => [], 'error' => 'API key BPS belum dikonfigurasi.'];
    }

    $url = BPS_API_BASE . 'list/model/th/lang/ind/domain/' . urlencode($domain)
        . '/var/' . urlencode($varId)
        . '/key/' . urlencode(BPS_API_KEY) . '/';

    $result = bpsApiCall($url, 8);
    if (!$result['ok']) {
        return ['ok' => false, 'items' => [], 'error' => $result['error'] ?: 'Gagal mengambil daftar periode dari BPS.'];
    }

    $rows = $result['json']['data'][1] ?? [];
    $items = [];
    foreach ($rows as $row) {
        // Bentuk baris bervariasi: {th_id, th_name, val} ATAU {th_id, th}
        // (terbukti dari respons langsung, mis. var 762: th_id=126,
        // th="2026"). 'val' untuk request model=data = th_id.
        $val = $row['val'] ?? ($row['th_id'] ?? '');
        $label = $row['th_name'] ?? ($row['th'] ?? ($row['th_id'] ?? ''));
        $items[] = [
            'th_id' => $row['th_id'] ?? '',
            'label' => $label,
            'val'   => $val,
        ];
    }

    // Urutkan dari yang paling baru (val terbesar) supaya gampang ambil
    // "N periode terakhir".
    usort($items, fn($a, $b) => (int) $b['val'] <=> (int) $a['val']);

    return ['ok' => true, 'items' => $items, 'error' => ''];
}

/**
 * Versi ter-cache dari bpsFetchPeriodList() (TTL 1 jam) dipakai supaya
 * bpsFetchIndicator() tidak perlu memanggil model=th berulang-ulang untuk
 * var_id yang sama dalam window waktu singkat (mis. banyak user buka
 * dashboard/katalog dalam 1 jam yang sama).
 */
function bpsFetchPeriodListCached(string $domain, string $varId, int $ttlSeconds = 3600): array
{
    $cacheDir = __DIR__ . '/../cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }

    // 'v2|' = versi format cache: label periode kini memakai field 'th'
    // ("2026"), bukan th_id ("126") cache lama harus dianggap basi.
    $cacheKey  = md5('v2|period|' . $domain . '|' . $varId);
    $cacheFile = $cacheDir . '/bps_indicator_' . $cacheKey . '.json';

    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttlSeconds) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $data = bpsFetchPeriodList($domain, $varId);

    if ($data['ok']) {
        @file_put_contents($cacheFile, json_encode($data));
    }

    return $data;
}

/**
 * Ambil data dinamis untuk satu var_id pada domain tertentu, lalu susun
 * jadi bentuk yang gampang dipakai: nilai terbaru + deret waktu (trend)
 * beberapa tahun terakhir.
 *
 * Kalau $vervarId / $turvarId tidak diisi, fungsi ini otomatis memilih
 * kombinasi PERTAMA yang punya data (biasanya representasi paling umum/
 * "total" untuk tabel itu) cara ini tidak selalu 100% tepat untuk semua
 * tabel (ada tabel yang breakdown-nya bukan "total" dulu), jadi kalau
 * hasilnya kurang pas, isi $vervarId/$turvarId manual di
 * config/bps_indicators.php (bisa dilihat pilihannya lewat
 * bps_variable_search.php atau respons mentah endpoint ini saat testing).
 *
 * @param string      $domain    kode domain BPS, mis. '1200' untuk Sumut
 * @param string      $varId     var_id yang mau diambil datanya
 * @param string|null $vervarId  filter wilayah tertentu (opsional)
 * @param string|null $turvarId  filter turunan variabel tertentu (opsional)
 * @param string      $titleFallback judul yang dipakai kalau var_id ini
 *        tidak terbaca sendiri dari respons (BPS API model=data tidak
 * selalu menyertakan judul variabel di responsnya) isi dari
 *        config/bps_indicators.php.
 * @param string $unitFallback satuan (mis. "persen", "jiwa") isi
 *        dari config/bps_indicators.php, karena model=data juga tidak
 *        selalu menyertakan info satuan.
 *
 * @return array{
 *   ok: bool, title: string, unit: string, error: string,
 *   latestYear: string, latestValue: string,
 *   vervarLabel: string, turvarLabel: string,
 *   trend: array<array{year:string, value:string}>
 * }
 */
/**
 * Ambil data untuk SATU jendela periode (maks 2 tahun, batas dari BPS) -
 * helper internal, dipakai oleh bpsFetchIndicator() untuk membangun tren
 * lebih panjang dengan menggabungkan beberapa jendela.
 *
 * @return array sama seperti bpsFetchIndicator(), tanpa field 'trend'
 *         digabung dari luar (trend di sini cuma berisi tahun-tahun dalam
 *         jendela ini saja).
 */
function bpsFetchIndicatorWindow(
    string $domain,
    string $varId,
    string $thValue,
    ?string $vervarId,
    ?string $turvarId,
    string $titleFallback,
    string $unitFallback,
    ?string $turtahunId = null
): array {
    $empty = [
        'ok' => false, 'title' => $titleFallback, 'unit' => $unitFallback, 'error' => '',
        'vervarLabel' => '', 'turvarLabel' => '', 'trend' => [],
    ];

    $url = BPS_API_BASE . 'list/model/data/lang/ind/domain/' . urlencode($domain)
        . '/var/' . urlencode($varId)
        . '/th/' . urlencode($thValue)
        . '/key/' . urlencode(BPS_API_KEY) . '/';

    $result = bpsApiCall($url, 8);
    if (!$result['ok']) {
        $empty['error'] = $result['error'] ?: 'Gagal mengambil data indikator dari BPS.';
        return $empty;
    }

    $json = $result['json'];
    $datacontent = $json['datacontent'] ?? [];
    $vervarList  = $json['vervar'] ?? [];
    $turvarList  = $json['turvar'] ?? [];
    $tahunList   = $json['tahun'] ?? [];
    // "turtahun" (turunan periode, mis. bulan/triwulan) SELALU ada di respons
    // model=data BPS, bahkan untuk tabel tahunan biasa isinya cuma 1 entri
    // {val:0, label:"Tahun"} kalau tabelnya tidak punya breakdown periode.
    // WAJIB ikut dipakai waktu membangun key datacontent (lihat di bawah),
    // walau kelihatannya "kosong makna" kalau tidak, key yang dibangun
    // tidak akan pernah cocok dengan key asli di datacontent dan hasilnya
    // selalu terlihat seperti "tidak ada data" padahal datanya ada.
    $turtahunList = $json['turtahun'] ?? [['val' => 0, 'label' => 'Tahun']];

    if (empty($datacontent) || empty($vervarList) || empty($turvarList) || empty($tahunList) || empty($turtahunList)) {
        $empty['error'] = 'Struktur data indikator tidak lengkap/tidak dikenali.';
        return $empty;
    }

    $title = $titleFallback !== '' ? $titleFallback : ('Var ' . $varId);
    $unit  = $unitFallback;

    usort($tahunList, fn($a, $b) => (int) $b['val'] <=> (int) $a['val']);

    $vervarCandidates = $vervarId !== null
        ? array_values(array_filter($vervarList, fn($v) => (string) $v['val'] === (string) $vervarId))
        : $vervarList;
    $turvarCandidates = $turvarId !== null
        ? array_values(array_filter($turvarList, fn($v) => (string) $v['val'] === (string) $turvarId))
        : $turvarList;

    if (empty($vervarCandidates) || empty($turvarCandidates)) {
        $empty['error'] = 'vervar_id/turvar_id yang dikonfigurasi tidak ditemukan di data BPS.';
        return $empty;
    }

    $chosenVervar = null;
    $chosenTurvar = null;
    $trend = [];

    // $turtahunList berisi >1 entri kalau tabelnya sub-tahunan (bulanan/
    // triwulan/dsb; mis. var Inflasi: Januari s.d. Desember). Untuk tabel
    // seperti itu JANGAN ambil turtahun pertama (Januari) ambil periode
    // TERBARU yang ada datanya di tiap tahun, supaya kartu "nilai terbaru"
    // benar-benar bulan terbaru (mis. Agustus 2026), bukan Januari.
    // Untuk tabel tahunan biasa turtahun cuma 1 entri {0,"Tahun"} sehingga
    // perilaku lama tidak berubah.
    //
    // Kalau $turtahunId diisi (mis. "32" = Triwulan II), cuma turtahun itu
    // yang dipakai WAJIB untuk tabel yang mencampur triwulan/bulan DENGAN
    // baris agregat "Tahunan" (mis. var 183 Pertumbuhan Ekonomi), supaya
    // tahun-tahun lama tidak kepilih "Tahunan" sementara tahun terbaru
    // kepilih triwulan (dua jenis angka yang tidak sebanding dicampur).
    $turtahunPool = ($turtahunId !== null && $turtahunId !== '')
        ? array_values(array_filter($turtahunList, fn($t) => (string) $t['val'] === (string) $turtahunId))
        : $turtahunList;
    if (empty($turtahunPool)) {
        $empty['error'] = 'turtahun_id yang dikonfigurasi tidak ditemukan di data BPS.';
        return $empty;
    }
    $subAnnual = count($turtahunList) > 1;
    $turtahunDesc = array_reverse($turtahunPool);

    // PENTING: key datacontent BPS adalah gabungan 5 komponen -
    // vervar + var + turvar + tahun + turtahun (dalam urutan itu, semua
    // sebagai string val tanpa pemisah) bukan cuma 4 (tanpa turtahun)
    // seperti sebelumnya. Referensi konkret dari dokumentasi resmi BPS,
    // key "7315310990" untuk vervar=7315, var=31, turvar=0, tahun=99,
    // turtahun=0 "7315"."31"."0"."99"."0" = "7315310990". Tanpa
    // turtahun di akhir, key yang dibangun di sini tidak akan pernah
    // cocok dengan key asli di datacontent, sehingga selalu dianggap
    // "tidak ada data" walau datanya sebenarnya tersedia.
    foreach ($vervarCandidates as $vervar) {
        foreach ($turvarCandidates as $turvar) {
            $foundAny = false;
            $series = [];
            // $tahunList sudah diurut baru lama; untuk tiap tahun ambil
            // SATU titik: periode terbaru (bulan terbesar) yang ada datanya.
            foreach ($tahunList as $tahun) {
                foreach ($turtahunDesc as $turtahun) {
                    $key = $vervar['val'] . $varId . $turvar['val'] . $tahun['val'] . $turtahun['val'];
                    if (isset($datacontent[$key]) && $datacontent[$key] !== '') {
                        // Label titik: "Agustus 2026" untuk tabel bulanan
                        // (bulannya jangan dibuang, kalau tidak tren runtuh
                        // jadi Januari tiap tahun), "2026" untuk tahunan.
                        $yearLabel = $subAnnual
                            ? (trim((string) $turtahun['label']) . ' ' . trim((string) $tahun['label']))
                            : $tahun['label'];
                        $series[] = ['year' => $yearLabel, 'value' => $datacontent[$key]];
                        $foundAny = true;
                        break;
                    }
                }
            }
            if ($foundAny) {
                $chosenVervar = $vervar;
                $chosenTurvar = $turvar;
                $trend = array_reverse($series);
                break 2;
            }
        }
    }

    if ($chosenVervar === null) {
        $empty['error'] = 'Tidak ada data untuk kombinasi wilayah/variabel ini.';
        return $empty;
    }

    return [
        'ok'          => true,
        'title'       => $title,
        'unit'        => $unit,
        'error'       => '',
        'vervarLabel' => $chosenVervar['label'] ?? '',
        'turvarLabel' => $chosenTurvar['label'] ?? '',
        'trend'       => $trend,
    ];
}

/**
 * Ranking kronologis untuk label periode ("2026", "Agustus 2026", ...).
 * Dipakai untuk mengurutkan tren lama baru: urutan numerik (int) biasa
 * rusak untuk label bulanan karena "Agustus 2026" di-cast jadi 0 semua.
 * Return tahun*100 + indeks bulan (0 kalau label murni tahunan).
 */
function bpsPeriodLabelRank(string $label): int
{
    // Nama lengkap dicek sebelum singkatannya ('juli' sebelum 'jul')
    // supaya tidak salah match substring.
    static $months = [
        'januari' => 1, 'jan' => 1,
        'februari' => 2, 'feb' => 2,
        'maret' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4,
        'mei' => 5,
        'juni' => 6, 'jun' => 6,
        'juli' => 7, 'jul' => 7,
        'agustus' => 8, 'agu' => 8, 'ags' => 8,
        'september' => 9, 'sep' => 9, 'sept' => 9,
        'oktober' => 10, 'okt' => 10, 'oct' => 10,
        'november' => 11, 'nov' => 11,
        'desember' => 12, 'des' => 12, 'dec' => 12,
    ];

    $year = 0;
    if (preg_match_all('/\d{4}/', $label, $m) && !empty($m[0])) {
        $year = (int) end($m[0]);
    } else {
        $year = (int) $label;
    }

    $month = 0;
    $lower = strtolower(trim($label));
    foreach ($months as $name => $num) {
        if (strpos($lower, $name) !== false) {
            $month = $num;
            break;
        }
    }

    return $year * 100 + $month;
}

/**
 * Ambil data dinamis untuk satu var_id pada domain tertentu, lalu susun
 * jadi bentuk yang gampang dipakai: nilai terbaru + deret waktu (trend)
 * beberapa tahun terakhir.
 *
 * Kalau $vervarId / $turvarId tidak diisi, fungsi ini otomatis memilih
 * kombinasi PERTAMA yang punya data (biasanya representasi paling umum/
 * "total" untuk tabel itu) cara ini tidak selalu 100% tepat untuk semua
 * tabel (ada tabel yang breakdown-nya bukan "total" dulu), jadi kalau
 * hasilnya kurang pas, isi $vervarId/$turvarId manual di
 * config/bps_indicators.php (bisa dilihat pilihannya lewat
 * bps_variable_search.php atau respons mentah endpoint ini saat testing).
 *
 * PENTING: BPS membatasi parameter 'th' maksimal 2 tahun PER REQUEST
 * (dikonfirmasi dari pesan error BPS sendiri). Kalau $maxYears > 2,
 * fungsi ini otomatis memecah jadi beberapa request 2-tahunan dan
 * menggabungkan hasilnya dipakai seperlunya (cuma untuk indikator yang
 * benar-benar butuh grafik tren) supaya tidak boros request ke BPS.
 *
 * @param string      $domain    kode domain BPS, mis. '1200' untuk Sumut
 * @param string      $varId     var_id yang mau diambil datanya
 * @param string|null $vervarId  filter wilayah tertentu (opsional)
 * @param string|null $turvarId  filter turunan variabel tertentu (opsional)
 * @param string      $titleFallback judul yang dipakai kalau var_id ini
 *        tidak terbaca sendiri dari respons (BPS API model=data tidak
 * selalu menyertakan judul variabel di responsnya) isi dari
 *        config/bps_indicators.php.
 * @param string $unitFallback satuan (mis. "persen", "jiwa") isi
 *        dari config/bps_indicators.php, karena model=data juga tidak
 *        selalu menyertakan info satuan.
 * @param int         $maxYears  jumlah tahun yang diinginkan untuk tren
 *        (default 2 = cukup untuk kartu angka tunggal + 1 pembanding).
 *        Isi lebih besar (mis. 6) untuk indikator dengan show_trend.
 * @param string|null $thOverride  pin periode (th_id) secara manual, mis.
 * "126" LEWATI pencarian otomatis lewat model=th sepenuhnya.
 *        Dipakai untuk indikator yang tabelnya mencampur beberapa jenis
 *        periodisitas berbeda (mis. bulanan m-to-m DAN tahunan y-on-y di
 *        var_id yang sama) sehingga "ambil N periode terbaru secara
 * otomatis" bisa salah pilih isi manual dari
 *        bps_variable_search.php/model=th kalau begitu.
 * @param string|null $turtahunId  pin turunan periode, mis. "32" =
 *        Triwulan II. Dipakai untuk tabel yang mencampur triwulan/bulan
 * dengan baris agregat "Tahunan" (mis. var 183) tanpa pin,
 *        tahun lama kepilih "Tahunan" sementara tahun terbaru kepilih
 *        triwulan. Diteruskan ke bpsFetchIndicatorWindow().
 *
 * @return array{
 *   ok: bool, title: string, unit: string, error: string,
 *   latestYear: string, latestValue: string,
 *   vervarLabel: string, turvarLabel: string,
 *   trend: array<array{year:string, value:string}>
 * }
 */
function bpsFetchIndicator(
    string $domain,
    string $varId,
    ?string $vervarId = null,
    ?string $turvarId = null,
    string $titleFallback = '',
    string $unitFallback = '',
    int $maxYears = 2,
    ?string $thOverride = null,
    ?string $turtahunId = null
): array {
    $empty = [
        'ok' => false, 'title' => $titleFallback, 'unit' => $unitFallback, 'error' => '',
        'latestYear' => '', 'latestValue' => '',
        'vervarLabel' => '', 'turvarLabel' => '', 'trend' => [],
    ];

    if (BPS_API_KEY === 'GANTI_DENGAN_API_KEY_ANDA' || BPS_API_KEY === '') {
        $empty['error'] = 'API key BPS belum dikonfigurasi.';
        return $empty;
    }

    if ($thOverride !== null && $thOverride !== '') {
        // Periode dipin manual (lihat dokblok $thOverride di atas) lewati
        // model=th sepenuhnya, langsung 1 window berisi th yang diminta.
        $periodVals = array_map('trim', explode(';', $thOverride));
    } else {
    // BPS Web API MEWAJIBKAN parameter 'th' (periode/tahun) diisi di
    // request model=data, jadi cari dulu periode yang benar-benar
    // tersedia lewat model=th, baru minta datanya. Tanpa ini, BPS
    // menolak dengan error "'th' parameter is required...".
    $periodResult = bpsFetchPeriodListCached($domain, $varId);

    if ($periodResult['ok'] && !empty($periodResult['items'])) {
        // Periode sudah diurutkan baru lama oleh bpsFetchPeriodListCached.
        $recentPeriods = array_slice($periodResult['items'], 0, max(2, $maxYears));
        $periodVals = array_map(fn($p) => (string) $p['val'], $recentPeriods);
    } else {
        // Fallback kalau pencarian periode sendiri gagal (mis. timeout) -
        // tebak N tahun kalender terakhir. Best-effort; kalau variabelnya
        // sudah lama tidak update, ini mungkin meleset dan hasilnya kosong.
        $currentYear = (int) date('Y');
        $periodVals = [];
        for ($i = 0; $i < max(2, $maxYears); $i++) {
            $periodVals[] = (string) ($currentYear - $i);
        }
    }
    }

    // Pecah jadi kelompok berisi maksimal 2 tahun (batas BPS per request).
    $windows = array_chunk($periodVals, 2);

    $mergedTrend = [];
    $lastResult = null;
    $anyOk = false;

    foreach ($windows as $windowVals) {
        $thValue = implode(';', $windowVals);
        $windowResult = bpsFetchIndicatorWindow($domain, $varId, $thValue, $vervarId, $turvarId, $titleFallback, $unitFallback, $turtahunId);
        $lastResult = $windowResult;

        if ($windowResult['ok']) {
            $anyOk = true;
            foreach ($windowResult['trend'] as $point) {
                // Dedupe kalau ada tahun yang kebetulan muncul di lebih
                // dari satu jendela (seharusnya tidak terjadi, tapi jaga-jaga).
                $mergedTrend[$point['year']] = $point;
            }
        }
    }

    if (!$anyOk) {
        $empty['error'] = $lastResult['error'] ?? 'Gagal mengambil data indikator dari BPS.';
        return $empty;
    }

    // Urutkan gabungan tren dari lama ke baru secara kronologis.
    // Label bisa berupa tahun murni ("2026") ATAU bulan+tahun
    // ("Agustus 2026") untuk tabel bulanan (int) biasa akan membuat
    // semua label bulanan jadi 0 dan urutannya acak, jadi pakai
    // bpsPeriodLabelRank() yang paham nama bulan Indonesia.
    uksort($mergedTrend, fn($a, $b) => bpsPeriodLabelRank($a) <=> bpsPeriodLabelRank($b));
    $trend = array_values($mergedTrend);
    $latest = end($trend);

    return [
        'ok'          => true,
        'title'       => $lastResult['title'],
        'unit'        => $lastResult['unit'],
        'error'       => '',
        'latestYear'  => $latest['year'] ?? '',
        'latestValue' => $latest['value'] ?? '',
        'vervarLabel' => $lastResult['vervarLabel'],
        'turvarLabel' => $lastResult['turvarLabel'],
        'trend'       => $trend,
    ];
}

/**
 * Dari daftar hasil pencarian variabel (bpsFetchVariableList), pilih yang
 * paling mungkin jadi "indikator utama/headline" untuk suatu kata kunci.
 *
 * Heuristik (tidak sempurna, tapi cukup masuk akal tanpa akses API
 * langsung untuk verifikasi): variabel dengan kata "menurut"/"berdasarkan"
 * di judulnya biasanya tabel breakdown/silang (mis. "Penduduk Menurut Jenis
 * Kelamin dan Kelompok Umur"), bukan angka ringkasan tunggal jadi
 * diprioritaskan yang TIDAK mengandung kata itu. Dari sisa kandidat,
 * ambil yang judulnya paling pendek (biasanya indikator paling umum/dasar,
 * bukan yang sudah dipecah ke sub-kategori spesifik).
 */
function bpsPickHeadlineVariable(array $items): ?array
{
    if (empty($items)) {
        return null;
    }

    $breakdownWords = ['menurut', 'berdasarkan', 'per kabupaten', 'per kecamatan'];
    $general = array_values(array_filter($items, function ($item) use ($breakdownWords) {
        // strtolower/strpos (bukan mb_strtolower/mb_strpos) sengaja dipakai
        // supaya tidak butuh extension mbstring (belum tentu aktif di semua
        // hosting) aman dipakai di sini karena cuma mencocokkan kata
        // kunci ASCII, dan strtolower() tidak merusak byte lanjutan UTF-8
        // (huruf non-ASCII tidak akan pernah "match" kata kunci ASCII ini).
        $titleLower = strtolower($item['title'] ?? '');
        foreach ($breakdownWords as $word) {
            if (strpos($titleLower, $word) !== false) {
                return false;
            }
        }
        return true;
    }));

    $candidates = !empty($general) ? $general : $items;

    usort($candidates, fn($a, $b) => strlen($a['title'] ?? '') <=> strlen($b['title'] ?? ''));

    return $candidates[0];
}

/**
 * Cari & ambil indikator otomatis berdasarkan kata kunci (tanpa perlu tahu
 * var_id-nya lebih dulu). Dipakai supaya dashboard "langsung jalan" begitu
 * API key diisi, tanpa perlu admin cari var_id manual satu-satu.
 *
 * Untuk hasil yang presisi (bukan auto-pilih), tetap disarankan isi
 * var_id manual di config/bps_indicators.php setelah tahu var_id yang
 * tepat lewat bps_variable_search.php.
 */
function bpsFetchIndicatorByKeyword(string $domain, string $keyword, string $titleOverride = '', string $unitOverride = '', int $maxYears = 2): array
{
    $empty = [
        'ok' => false, 'title' => $titleOverride ?: $keyword, 'unit' => $unitOverride, 'error' => '',
        'latestYear' => '', 'latestValue' => '',
        'vervarLabel' => '', 'turvarLabel' => '', 'trend' => [], 'varId' => '',
    ];

    $searchResult = bpsFetchVariableList($domain, $keyword);
    if (!$searchResult['ok']) {
        $empty['error'] = $searchResult['error'];
        return $empty;
    }

    $picked = bpsPickHeadlineVariable($searchResult['items']);
    if ($picked === null) {
        $empty['error'] = 'Tidak ada variabel BPS yang cocok dengan kata kunci "' . $keyword . '".';
        return $empty;
    }

    $indicator = bpsFetchIndicator(
        $domain,
        (string) $picked['var_id'],
        null,
        null,
        $titleOverride ?: $picked['title'],
        $unitOverride ?: $picked['unit'],
        $maxYears
    );
    $indicator['varId'] = $picked['var_id'];

    return $indicator;
}

/**
 * Versi ter-cache dari bpsFetchIndicatorByKeyword() (TTL 1 jam, sama
 * seperti bpsFetchIndicatorCached()).
 */
function bpsFetchIndicatorByKeywordCached(string $domain, string $keyword, string $titleOverride = '', string $unitOverride = '', int $maxYears = 2, int $ttlSeconds = 3600): array
{
    $cacheDir = __DIR__ . '/../cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }

    // 'v2|' = versi format cache: sejak perbaikan pemilihan bulan
    // terbaru (dulu selalu Januari untuk tabel bulanan), cache lama
    // harus dianggap basi walau TTL-nya belum habis.
    $cacheKey  = md5('v2|keyword|' . $domain . '|' . $keyword . '|' . $titleOverride . '|' . $unitOverride . '|' . $maxYears);
    $cacheFile = $cacheDir . '/bps_indicator_' . $cacheKey . '.json';

    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttlSeconds) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $data = bpsFetchIndicatorByKeyword($domain, $keyword, $titleOverride, $unitOverride, $maxYears);

    if ($data['ok']) {
        @file_put_contents($cacheFile, json_encode($data));
    }

    return $data;
}

/**
 * Bungkus bpsFetchIndicator() dengan cache file sederhana (TTL 1 jam),
 * supaya dashboard yang dibuka berkali-kali oleh banyak user tidak
 * membombardir BPS API dengan request yang sama berulang-ulang (dan kena
 * rate limit).
 */
function bpsFetchIndicatorCached(
    string $domain,
    string $varId,
    ?string $vervarId,
    ?string $turvarId,
    string $titleFallback = '',
    string $unitFallback = '',
    int $maxYears = 2,
    int $ttlSeconds = 3600,
    ?string $thOverride = null,
    ?string $turtahunId = null
): array {
    $cacheDir = __DIR__ . '/../cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }

    // 'v2|' = versi format cache (lihat komentar yang sama di
    // bpsFetchIndicatorByKeywordCached()).
    $cacheKey  = md5('v2|' . $domain . '|' . $varId . '|' . ($vervarId ?? '') . '|' . ($turvarId ?? '') . '|' . $maxYears . '|' . ($thOverride ?? '') . '|' . ($turtahunId ?? ''));
    $cacheFile = $cacheDir . '/bps_indicator_' . $cacheKey . '.json';

    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttlSeconds) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $data = bpsFetchIndicator($domain, $varId, $vervarId, $turvarId, $titleFallback, $unitFallback, $maxYears, $thOverride, $turtahunId);

    // Cuma simpan ke cache kalau berhasil kalau gagal (mis. API key
    // salah), jangan cache kegagalannya supaya begitu dibetulkan langsung
    // kepakai tanpa perlu tunggu TTL habis.
    if ($data['ok']) {
        @file_put_contents($cacheFile, json_encode($data));
    }

    return $data;
}

//
// Helper QUERY BUILDER (dipakai halaman publik data_dinamis.php).
// Alur meniru sumut.bps.go.id/query-builder: kategori subjek subjek
// tabel/indikator (var) tahun (th) turunan tahun (turth)
// karakteristik (turvar) judul baris (vervar) submit (model=data).
// Semua fungsi di bawah mengembalikan {ok, items, error} dengan items
// ternormalisasi [{id, label}] + field 'raw' (baris asli BPS) untuk jaga-jaga
// kalau nama field BPS berbeda.
//

/**
 * Cek API key terisi. Return string error kalau belum, atau '' kalau OK.
 */
function bpsQueryCheckKey(): string
{
    if (BPS_API_KEY === 'GANTI_DENGAN_API_KEY_ANDA' || BPS_API_KEY === '') {
        return 'API key BPS belum dikonfigurasi di config/bps_api.php.';
    }
    return '';
}

/**
 * Normalisasi satu baris BPS jadi {id, label} dengan daftar kandidat key.
 */
function bpsQueryNormalizeRow(array $row, array $idKeys, array $labelKeys): array
{
    $id = '';
    foreach ($idKeys as $k) {
        if (isset($row[$k]) && (string) $row[$k] !== '') {
            $id = (string) $row[$k];
            break;
        }
    }
    $label = '';
    foreach ($labelKeys as $k) {
        if (isset($row[$k]) && (string) $row[$k] !== '') {
            $label = (string) $row[$k];
            break;
        }
    }
    if ($label === '' && $id !== '') {
        $label = $id;
    }
    return ['id' => $id, 'label' => $label, 'raw' => $row];
}

/**
 * Fetch generik list model BPS (subcat/subject/vervar/turvar/turth).
 *
 * $urlTemplate memakai placeholder {PAGE}, mis.
 * 'list/model/subject/lang/ind/domain/1200/page/{PAGE}/key/XXX/'.
 * Semua halaman diambil (dibatasi $maxPages) karena BPS memaginasi
 * 10 baris per halaman (mis. subject domain 1200 = 38 baris = 4 halaman,
 * turth var 762 = 12 baris = 2 halaman).
 *
 * Kalau BPS menjawab status OK tapi data kosong (mis. "data": "" dengan
 * data-availability "list-not-available" terbukti terjadi untuk
 * turvar var 762 dan th var 4), hasilnya ok dengan items kosong.
 */
function bpsQueryFetchList(string $urlTemplate, array $idKeys, array $labelKeys, int $maxPages = 10): array
{
    $keyError = bpsQueryCheckKey();
    if ($keyError !== '') {
        return ['ok' => false, 'items' => [], 'error' => $keyError];
    }

    $items = [];
    $page = 1;

    do {
        $url = str_replace('{PAGE}', (string) $page, $urlTemplate);
        $result = bpsApiCall(BPS_API_BASE . $url, 12);
        if (!$result['ok']) {
            // Halaman pertama gagal = gagal total; halaman lanjut gagal =
            // pakai yang sudah terkumpul.
            if ($page === 1) {
                return ['ok' => false, 'items' => [], 'error' => $result['error'] ?: 'Gagal mengambil data dari BPS.'];
            }
            break;
        }

        // $json['data'] bisa berupa string "" (kosong) jangan langsung
        // diindeks, cek is_array dulu supaya tidak warning/error.
        $data = $result['json']['data'] ?? null;
        $rows = (is_array($data) && isset($data[1]) && is_array($data[1])) ? $data[1] : [];
        $info = (is_array($data) && isset($data[0]) && is_array($data[0])) ? $data[0] : [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $norm = bpsQueryNormalizeRow($row, $idKeys, $labelKeys);
            if ($norm['id'] === '' && $norm['label'] === '') {
                continue;
            }
            $items[] = $norm;
        }

        $pages = max(1, (int) ($info['pages'] ?? 1));
        $page++;
    } while ($page <= min($pages, max(1, $maxPages)));

    return ['ok' => true, 'items' => $items, 'error' => ''];
}

/** Kategori subjek CSA (model=subcatcsa) dipakai laman query-builder
 * BPS terbaru: 514 Statistik Demografi dan Sosial, 515 Statistik Ekonomi,
 * 516 Statistik Lingkungan Hidup dan Multi-domain.
 * Diurut subcat_id menaik, sama seperti laman BPS. */
function bpsFetchSubcatCsa(string $domain): array
{
    $r = bpsQueryFetchList(
        'list/model/subcatcsa/lang/ind/domain/' . urlencode($domain)
            . '/page/{PAGE}/key/' . urlencode(BPS_API_KEY) . '/',
        ['subcat_id', 'subcatid', 'id', 'val'],
        ['title', 'subcat', 'subcat_name', 'label', 'name']
    );
    if ($r['ok']) {
        usort($r['items'], fn($a, $b) => ((int) $a['id']) <=> ((int) $b['id']));
    }
    return $r;
}

/** Subjek CSA (model=subjectcsa), opsional difilter kategori CSA.
 * Baris: {sub_id, title, subcat_id, subcat, ntabel, ...}.
 * Diurut (subcat_id, sub_id) menaik, sama seperti laman query-builder BPS. */
function bpsFetchSubjectCsa(string $domain, ?string $subcatId = null): array
{
    $path = 'list/model/subjectcsa/lang/ind/domain/' . urlencode($domain);
    if ($subcatId !== null && $subcatId !== '') {
        $path .= '/subcat/' . urlencode($subcatId);
    }
    $path .= '/page/{PAGE}/key/' . urlencode(BPS_API_KEY) . '/';

    $r = bpsQueryFetchList(
        $path,
        ['sub_id', 'subject_id', 'id', 'val'],
        ['title', 'subject', 'sub_name', 'label', 'name']
    );
    if ($r['ok']) {
        foreach ($r['items'] as &$it) {
            $raw = (isset($it['raw']) && is_array($it['raw'])) ? $it['raw'] : [];
            $it['subcat_id'] = (string) ($raw['subcat_id'] ?? '');
            $it['subcat'] = (string) ($raw['subcat'] ?? '');
            $it['ntabel'] = (int) ($raw['ntabel'] ?? 0);
        }
        unset($it);
        usort($r['items'], function ($a, $b) {
            $c = ((int) $a['subcat_id']) <=> ((int) $b['subcat_id']);
            return $c !== 0 ? $c : (((int) $a['id']) <=> ((int) $b['id']));
        });
    }
    return $r;
}

/** Kategori subjek lama (model=subcat). Dipertahankan untuk kompatibilitas;
 * laman query-builder terbaru memakai versi CSA (bpsFetchSubcatCsa). */
function bpsFetchSubcats(string $domain): array
{
    $r = bpsQueryFetchList(
        'list/model/subcat/lang/ind/domain/' . urlencode($domain)
            . '/page/{PAGE}/key/' . urlencode(BPS_API_KEY) . '/',
        ['subcat_id', 'subcatid', 'id', 'val'],
        ['subcat', 'subcat_name', 'title', 'label', 'name']
    );
    if ($r['ok']) {
        usort($r['items'], fn($a, $b) => ((int) $a['id']) <=> ((int) $b['id']));
    }
    return $r;
}

/** Subjek (model=subject), opsional difilter kategori.
 * Baris: {sub_id, title, ...} + subcat_id/subcat diteruskan apa adanya
 * (dipakai UI untuk menyaring instan per kategori, seperti laman BPS).
 * Diurut (subcat_id, sub_id) menaik, sama seperti laman query-builder BPS. */
function bpsFetchSubjects(string $domain, ?string $subcatId = null): array
{
    $path = 'list/model/subject/lang/ind/domain/' . urlencode($domain);
    if ($subcatId !== null && $subcatId !== '') {
        $path .= '/subcat/' . urlencode($subcatId);
    }
    $path .= '/page/{PAGE}/key/' . urlencode(BPS_API_KEY) . '/';

    $r = bpsQueryFetchList(
        $path,
        ['sub_id', 'subject_id', 'id', 'val'],
        ['title', 'subject', 'sub_name', 'label', 'name']
    );
    if ($r['ok']) {
        foreach ($r['items'] as &$it) {
            $raw = (isset($it['raw']) && is_array($it['raw'])) ? $it['raw'] : [];
            $it['subcat_id'] = (string) ($raw['subcat_id'] ?? '');
            $it['subcat'] = (string) ($raw['subcat'] ?? '');
        }
        unset($it);
        usort($r['items'], function ($a, $b) {
            $c = ((int) $a['subcat_id']) <=> ((int) $b['subcat_id']);
            return $c !== 0 ? $c : (((int) $a['id']) <=> ((int) $b['id']));
        });
    }
    return $r;
}

/**
 * Daftar tabel/indikator (model=var) untuk query builder: bisa difilter
 * subjek DAN/ATAU kata kunci. Mengambil SATU halaman ($page, 10 baris)
 * supaya mendukung infinite-scroll di UI. Return {ok, items, error, total}.
 *
 * $subjectId = subjek lama (/subject/), $subjectCsaId = subjek CSA
 * (/subjectcsa/, dipakai laman query-builder BPS terbaru). Bila keduanya
 * diisi, versi CSA yang dipakai.
 *
 * CATATAN: endpoint var BPS tidak mendukung filter kategori langsung
 * (/subcatcsa/ diabaikan server terbukti: semua kategori mengembalikan
 * hasil identik). Sama seperti laman query-builder BPS (hanya subjectcsa
 * yang dikirim), daftar tabel disaring via SUBJEK; kategori dipakai untuk
 * menyaring dropdown subjek.
 */
function bpsFetchVariablesForBuilder(string $domain, string $keyword = '', ?string $subjectId = null, int $page = 1, ?string $subjectCsaId = null): array
{
    $keyError = bpsQueryCheckKey();
    if ($keyError !== '') {
        return ['ok' => false, 'items' => [], 'error' => $keyError, 'total' => 0];
    }

    if ($page < 1) {
        $page = 1;
    }

    $buildPath = function (int $p, bool $withSubject) use ($domain, $keyword, $subjectId, $subjectCsaId): string {
        $path = 'list/model/var/lang/ind/domain/' . urlencode($domain);
        if ($withSubject) {
            if ($subjectCsaId !== null && $subjectCsaId !== '') {
                $path .= '/subjectcsa/' . urlencode($subjectCsaId);
            } elseif ($subjectId !== null && $subjectId !== '') {
                $path .= '/subject/' . urlencode($subjectId);
            }
        }
        return $path . '/keyword/' . urlencode($keyword)
            . '/page/' . $p
            . '/key/' . urlencode(BPS_API_KEY) . '/';
    };

    $fetchPage = function (int $p, bool $withSubject) use ($buildPath): array {
        $result = bpsApiCall(BPS_API_BASE . $buildPath($p, $withSubject), 12);
        if (!$result['ok']) {
            return ['ok' => false, 'items' => [], 'error' => $result['error'] ?: 'Gagal mengambil daftar tabel dari BPS.', 'total' => 0];
        }
        $data = $result['json']['data'] ?? null;
        $rows = (is_array($data) && isset($data[1]) && is_array($data[1])) ? $data[1] : [];
        $info = (is_array($data) && isset($data[0]) && is_array($data[0])) ? $data[0] : [];
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = [
                'var_id'  => (string) ($row['var_id'] ?? ''),
                'title'   => (string) ($row['title'] ?? ''),
                'unit'    => (string) ($row['unit'] ?? ''),
                'subject' => (string) ($row['sub_name'] ?? ''),
                'raw'     => $row,
            ];
        }
        return ['ok' => true, 'items' => $items, 'error' => '', 'total' => (int) ($info['total'] ?? count($items))];
    };

    $useSubject = ($subjectId !== null && $subjectId !== '')
        || ($subjectCsaId !== null && $subjectCsaId !== '');
    $r = $fetchPage($page, $useSubject);

    // Kalau kombinasi subject+keyword ditolak BPS, ulangi tanpa subject.
    if (!$r['ok'] && $useSubject && $keyword !== '') {
        $r = $fetchPage($page, false);
    }

    return $r;
}

/** Judul baris (model=vervar) untuk satu var. Baris: {kode_ver_id, vervar, item_ver_id, ...}.
 * PENTING: id yang dipakai di key datacontent model=data adalah kode_ver_id
 * (terbukti var 762: val 1..9), BUKAN item_ver_id (1823...) jadi
 * kode_ver_id harus diutamakan. */
function bpsFetchVervarList(string $domain, string $varId): array
{
    return bpsQueryFetchList(
        'list/model/vervar/lang/ind/domain/' . urlencode($domain)
            . '/var/' . urlencode($varId)
            . '/page/{PAGE}/key/' . urlencode(BPS_API_KEY) . '/',
        ['kode_ver_id', 'vervar_id', 'item_ver_id', 'val', 'id'],
        ['label', 'vervar', 'item', 'title', 'name']
    );
}

/** Karakteristik (model=turvar) untuk satu var. Bisa kosong (mis. var 762). */
function bpsFetchTurvarList(string $domain, string $varId): array
{
    return bpsQueryFetchList(
        'list/model/turvar/lang/ind/domain/' . urlencode($domain)
            . '/var/' . urlencode($varId)
            . '/page/{PAGE}/key/' . urlencode(BPS_API_KEY) . '/',
        ['kode_tur_id', 'tur_id', 'turvar_id', 'val', 'id'],
        ['label', 'turvar', 'tur', 'title', 'name']
    );
}

/** Turunan tahun (model=turth) untuk satu var. Baris: {turth_id, turth, ...}. */
function bpsFetchTurthList(string $domain, string $varId): array
{
    return bpsQueryFetchList(
        'list/model/turth/lang/ind/domain/' . urlencode($domain)
            . '/var/' . urlencode($varId)
            . '/page/{PAGE}/key/' . urlencode(BPS_API_KEY) . '/',
        ['turth_id', 'val', 'id'],
        ['label', 'turth', 'th', 'title', 'name']
    );
}

/**
 * Pecah parameter 'th' model=data menjadi potongan-potongan yang masing-masing
 * memuat maksimal $max tahun. API BPS menolak request dengan lebih dari
 * 2 tahun ("The maximum allowed number of years for the 'th' parameter
 * is 2"), jadi "126;125;124" ["126;125", "124"]. Bentuk rentang ("1:6")
 * tidak bisa dipecah tanpa tahu daftar id, sehingga dikirim apa adanya
 * dalam satu request.
 * Return array string siap pakai (selalu ≥1 elemen bila input tak kosong).
 */
function bpsSplitTh(string $th, int $max = 2): array
{
    $th = trim($th);
    if ($th === '') {
        return [];
    }
    if ($max < 1) {
        $max = 1;
    }
    $parts = array_values(array_filter(array_map('trim', explode(';', $th)), fn($v) => $v !== ''));
    if (count($parts) <= $max && strpos($th, ':') === false) {
        return [$th];
    }
    // Ada rentang ':' yang tidak bisa dipecah aman satu request.
    $hasRange = false;
    foreach ($parts as $p) {
        if (strpos($p, ':') !== false) {
            $hasRange = true;
            break;
        }
    }
    if ($hasRange) {
        return [$th];
    }
    $chunks = [];
    foreach (array_chunk($parts, $max) as $c) {
        $chunks[] = implode(';', $c);
    }
    return $chunks;
}

/**
 * Ambil data mentah model=data untuk query builder, dengan filter
 * vervar/turvar/turth opsional (sesuai dokumentasi model=data BPS).
 *
 * CATATAN: API BPS membatasi maksimal 2 tahun per request 'th', jadi daftar
 * tahun yang lebih panjang diambil bertahap (potongan ≤2) lalu digabung:
 * datacontent digabung, daftar tahun/turtahun/vervar/turvar di-union
 * berdasarkan 'val'. Batas "maksimal 2" HANYA untuk jumlah TABEL yang
 * dipilih di UI, bukan jumlah tahun/kolom per tabel.
 *
 * Return {ok, json, error} json adalah respons BPS apa adanya
 * (vervar, turvar, tahun, turtahun, datacontent, ...).
 */
function bpsFetchDynamicDataRaw(
    string $domain,
    string $varId,
    string $th,
    ?string $vervar = null,
    ?string $turvar = null,
    ?string $turth = null
): array {
    $keyError = bpsQueryCheckKey();
    if ($keyError !== '') {
        return ['ok' => false, 'json' => null, 'error' => $keyError];
    }

    // API BPS: maksimal 2 tahun per request pecah otomatis bila lebih.
    $thChunks = bpsSplitTh($th, 2);
    if (!$thChunks) {
        return ['ok' => false, 'json' => null, 'error' => 'Parameter th wajib diisi.'];
    }

    $merged = null;
    foreach ($thChunks as $thPart) {
        $path = 'list/model/data/lang/ind/domain/' . urlencode($domain)
            . '/var/' . urlencode($varId)
            . '/th/' . urlencode($thPart);
        if ($turvar !== null && $turvar !== '') {
            $path .= '/turvar/' . urlencode($turvar);
        }
        if ($vervar !== null && $vervar !== '') {
            $path .= '/vervar/' . urlencode($vervar);
        }
        if ($turth !== null && $turth !== '') {
            $path .= '/turth/' . urlencode($turth);
        }
        $path .= '/key/' . urlencode(BPS_API_KEY) . '/';

        $result = bpsApiCall(BPS_API_BASE . $path, 20);
        if (!$result['ok']) {
            return ['ok' => false, 'json' => $result['json'], 'error' => $result['error'] ?: 'Gagal mengambil data dari BPS.'];
        }
        $json = $result['json'];
        if ($merged === null) {
            $merged = $json;
            continue;
        }
        // Gabung datacontent (key unik per vervar+turvar+tahun+turth).
        $dc = (isset($merged['datacontent']) && is_array($merged['datacontent'])) ? $merged['datacontent'] : [];
        $dcNew = (isset($json['datacontent']) && is_array($json['datacontent'])) ? $json['datacontent'] : [];
        foreach ($dcNew as $k => $v) {
            $dc[$k] = $v;
        }
        $merged['datacontent'] = $dc;
        // Union daftar dimensi berdasarkan 'val' (hindari duplikat).
        foreach (['tahun', 'turtahun', 'vervar', 'turvar'] as $listKey) {
            $base = (isset($merged[$listKey]) && is_array($merged[$listKey])) ? $merged[$listKey] : [];
            $add = (isset($json[$listKey]) && is_array($json[$listKey])) ? $json[$listKey] : [];
            $seen = [];
            foreach ($base as $row) {
                if (is_array($row) && array_key_exists('val', $row)) {
                    $seen[(string) $row['val']] = true;
                }
            }
            foreach ($add as $row) {
                if (is_array($row) && array_key_exists('val', $row)) {
                    if (!isset($seen[(string) $row['val']])) {
                        $seen[(string) $row['val']] = true;
                        $base[] = $row;
                    }
                } else {
                    $base[] = $row;
                }
            }
            $merged[$listKey] = $base;
        }
    }

    return ['ok' => true, 'json' => $merged, 'error' => ''];
}

//
// EKSPOR-IMPOR (dataexim) meniru halaman sumut.bps.go.id/exim.
// Format URL resmi (terbukti via tes langsung):
//   v1/api/dataexim/sumber/{s}/kodehs/{hs}/jenishs/{j}/tahun/{t}/periode/{p}/key/{key}/
// sumber: 1 = Ekspor, 2 = Impor
// periode: 1 = bulanan, 2 = tahunan
// kodehs: kode HS, pisahkan ";" untuk multi (mis. "09;15")
// jenishs: 1 = 2 digit, 2 = Full HS Code
// tahun: tahun data (mis. "2024"), tersedia sejak 2014
// Respons: {status, data-availability, metadata{...}, data:[{tahun,
// (bulan,) kodehs, pod, ctr, value, netweight}, ...]}
//

/**
 * Ambil data ekspor/impor mentah dari BPS Web API.
 * Return {ok, json, error}.
 */
function bpsFetchEximRaw(
    string $sumber,
    string $kodehs,
    string $jenishs,
    string $tahun,
    string $periode
): array {
    $keyError = bpsQueryCheckKey();
    if ($keyError !== '') {
        return ['ok' => false, 'json' => null, 'error' => $keyError];
    }

    // urlencode per segmen; ";" sebagai pemisah multi-kode dibiarkan agar
    // tetap terbaca sebagai pemisah oleh BPS (rawurlencode me-encode ";"
    // jadi %3B kembalikan lagi).
    $encMulti = function (string $v): string {
        return str_replace('%3B', ';', rawurlencode($v));
    };

    $path = 'dataexim/sumber/' . urlencode($sumber)
        . '/kodehs/' . $encMulti($kodehs)
        . '/jenishs/' . urlencode($jenishs)
        . '/tahun/' . $encMulti($tahun)
        . '/periode/' . urlencode($periode)
        . '/key/' . urlencode(BPS_API_KEY) . '/';

    // Data bulanan bisa besar (satu HS ~500KB), beri timeout longgar.
    $result = bpsApiCall(BPS_API_BASE . $path, 40);
    if (!$result['ok']) {
        return ['ok' => false, 'json' => $result['json'], 'error' => $result['error'] ?: 'Gagal mengambil data ekspor-impor dari BPS.'];
    }

    return ['ok' => true, 'json' => $result['json'], 'error' => ''];
}
