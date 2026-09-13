<?php
require_once 'config/dbconn.php';
require_once 'includes/auth.php';
require_once 'includes/bps_client.php';
require_once 'includes/bps_publication.php';
// Halaman publik: bisa diakses tanpa login (guest = user biasa).
// Login hanya dipakai admin untuk kelola data (lihat requireAdmin()).

// Indikator BPS (opsional)
// Dikonfigurasi lewat config/bps_indicators.php. Kalau kosong atau API key
// belum diisi, bagian ini otomatis disembunyikan (lihat pengecekan
// $bpsIndicatorCards/$bpsTrendCard di bawah), dashboard tidak akan rusak.
//
// Kolom 'icon' (opsional) di tiap entri config dipakai sebagai ikon kartu:
// 'icon' '<img src="assets/img/inflasi.png" alt="">',
$bpsIndicatorConfigs = require __DIR__ . '/config/bps_indicators.php';
$bpsIndicatorCards = [];
$bpsTrendCard = null; // cuma 1 grafik tren ditampilkan, biar dashboard tidak penuh

foreach ($bpsIndicatorConfigs as $cfg) {
  $varId = trim((string) ($cfg['var_id'] ?? ''));
  // Cuma minta tren panjang (lebih dari 1 jendela 2-tahunan) untuk
  // indikator yang benar-benar akan ditampilkan sebagai grafik -
  // supaya tidak boros request BPS untuk kartu angka tunggal biasa.
  $maxYears = !empty($cfg['show_trend']) ? 6 : 2;

  if ($varId !== '') {
    // Cara B: var_id presisi, hasil pencarian manual admin.
    $result = bpsFetchIndicatorCached(
      BPS_DOMAIN,
      $varId,
      isset($cfg['vervar_id']) ? (string) $cfg['vervar_id'] : null,
      isset($cfg['turvar_id']) ? (string) $cfg['turvar_id'] : null,
      $cfg['title'] ?? '',
      $cfg['unit'] ?? '',
      $maxYears,
      3600,
      isset($cfg['th_id']) ? (string) $cfg['th_id'] : null,
      isset($cfg['turtahun_id']) ? (string) $cfg['turtahun_id'] : null
    );
  } else {
    // Cara A: auto-discovery lewat kata kunci.
    $result = bpsFetchIndicatorByKeywordCached(
      BPS_DOMAIN,
      (string) ($cfg['keyword'] ?? ''),
      $cfg['title'] ?? '',
      $cfg['unit'] ?? '',
      $maxYears
    );
  }

  $card = [
    'title'       => $cfg['title'] ?? $result['title'],
    'unit'        => $cfg['unit'] ?? $result['unit'],
    'icon'        => $cfg['icon'] ?? '', // ikon kartu, cth: 'icon' ''
    'ok'          => $result['ok'],
    'error'       => $result['error'],
    'value'       => $result['latestValue'],
    'year'        => $result['latestYear'],
    'trend'       => $result['trend'],
    'vervarLabel' => $result['vervarLabel'] ?? '',
    'turvarLabel' => $result['turvarLabel'] ?? '',
  ];

  if (!empty($cfg['show_trend']) && $bpsTrendCard === null && $result['ok'] && count($result['trend']) >= 2) {
    $bpsTrendCard = $card;
  } else {
    $bpsIndicatorCards[] = $card;
  }
}

/**
 * Render grafik tren sederhana (line chart) sebagai SVG, dibuat langsung
 * di server dari data BPS tidak butuh library chart JS eksternal.
 */
function renderBpsTrendSvg(array $trend, int $width = 560, int $height = 220): string
{
  $points = array_map(fn($p) => (float) str_replace(',', '.', (string) $p['value']), $trend);
  $labels = array_map(fn($p) => (string) $p['year'], $trend);

  $min = min($points);
  $max = max($points);
  $range = ($max - $min) ?: 1; // hindari pembagian dengan nol kalau semua nilai sama

  $padding = 30;
  $chartW  = $width - ($padding * 2);
  $chartH  = $height - ($padding * 2);
  $count   = count($points);

  $coords = [];
  foreach ($points as $i => $val) {
    $x = $padding + ($count > 1 ? ($i / ($count - 1)) * $chartW : $chartW / 2);
    $y = $padding + $chartH - (($val - $min) / $range) * $chartH;
    $coords[] = [$x, $y];
  }

  $polylinePoints = implode(' ', array_map(fn($c) => round($c[0], 1) . ',' . round($c[1], 1), $coords));

  $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg" style="width:100%; height:auto;">';
  $svg .= '<polyline fill="none" stroke="#034f84" stroke-width="3" points="' . htmlspecialchars($polylinePoints) . '" />';

  foreach ($coords as $i => [$x, $y]) {
    $svg .= '<circle cx="' . round($x, 1) . '" cy="' . round($y, 1) . '" r="4" fill="#002b6a" />';
    $svg .= '<text x="' . round($x, 1) . '" y="' . ($height - 8) . '" font-size="11" fill="#666" text-anchor="middle">' . htmlspecialchars($labels[$i]) . '</text>';
    $svg .= '<text x="' . round($x, 1) . '" y="' . round($y - 10, 1) . '" font-size="11" fill="#002b6a" text-anchor="middle" font-weight="bold">' . htmlspecialchars(number_format($points[$i], 2, ',', '.')) . '</text>';
  }

  $svg .= '</svg>';
  return $svg;
}

// Informasi Terbaru: 6 publikasi, BRS, infografis terbaru (cache file)
try {
  $manualRows = $pdo->query(
    'SELECT * FROM publikasi ORDER BY tanggal_rilis DESC LIMIT 20'
  )->fetchAll();
  $manualNorm = array_map('bpsPubNormalizeManualRow', $manualRows);
} catch (Throwable $e) {
  $manualNorm = [];
}
try {
  $pubPage = bpsFetchPublicationPage(BPS_DOMAIN, 1);
  $apiPubs = $pubPage['ok'] ? $pubPage['items'] : [];
} catch (Throwable $e) {
  $apiPubs = [];
}
$gabungPub = array_merge($apiPubs, $manualNorm);
usort($gabungPub, function ($a, $b) {
  $da = $a['rl_date'] ?? '';
  $db = $b['rl_date'] ?? '';
  if ($da === $db) return 0;
  if ($da === '') return 1;
  if ($db === '') return -1;
  return strcmp($db, $da);
});
$publikasiTerbaru = array_slice($gabungPub, 0, 6);

$namaBulanId = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
function homeFormatTanggalBrs(string $raw): string
{
  global $namaBulanId;
  $raw = trim($raw);
  if ($raw === '') return '-';
  $dt = DateTime::createFromFormat('Y m d', $raw);
  if (!$dt) $dt = DateTime::createFromFormat('Y-m-d', $raw);
  if ($dt) {
    return ltrim($dt->format('d'), '0') . ' ' . $namaBulanId[(int) $dt->format('m') - 1] . ' ' . $dt->format('Y');
  }
  return $raw;
}

// BRS terbaru: halaman 1 API pressrelease, cache 1 jam.
$brsTerbaru = [];
try {
  $brsCacheFile = __DIR__ . '/cache/home_brs.json';
  $brsCacheOk = is_file($brsCacheFile) && (time() - filemtime($brsCacheFile)) < 3600;
  if ($brsCacheOk) {
    $brsCached = json_decode((string) @file_get_contents($brsCacheFile), true);
    if (is_array($brsCached)) $brsTerbaru = $brsCached;
  }
  if (empty($brsTerbaru)) {
    $brsUrl = BPS_API_BASE . 'list/?model=pressrelease&domain=' . urlencode(BPS_DOMAIN)
      . '&lang=ind&key=' . urlencode(BPS_API_KEY) . '&page=1';
    $brsRes = bpsApiCall($brsUrl, 10);
    if ($brsRes['ok']) {
      $rows = $brsRes['json']['data'][1] ?? [];
      foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $brsTerbaru[] = [
          'title' => (string) ($r['title'] ?? '(Tanpa Judul)'),
          'thumbnail' => (string) ($r['thumbnail'] ?? ''),
          'pdf' => (string) ($r['pdf'] ?? ''),
          'subj' => (string) ($r['subj'] ?? ''),
          'rl_date' => (string) ($r['rl_date'] ?? ''),
        ];
        if (count($brsTerbaru) >= 6) break;
      }
      @file_put_contents($brsCacheFile, json_encode($brsTerbaru));
    } elseif ($brsCacheOk === false && is_file($brsCacheFile)) {
      // API gagal tapi masih ada cache lama: pakai apa adanya.
      $brsCached = json_decode((string) @file_get_contents($brsCacheFile), true);
      if (is_array($brsCached)) $brsTerbaru = array_slice($brsCached, 0, 6);
    }
  } else {
    $brsTerbaru = array_slice($brsTerbaru, 0, 6);
  }
} catch (Throwable $e) {
  $brsTerbaru = [];
}

// Infografis terbaru: halaman 1 API infographic, cache 6 jam.
$infografisTerbaru = [];
try {
  $infoCacheFile = __DIR__ . '/cache/home_infografis.json';
  $infoCacheOk = is_file($infoCacheFile) && (time() - filemtime($infoCacheFile)) < 21600;
  if ($infoCacheOk) {
    $infoCached = json_decode((string) @file_get_contents($infoCacheFile), true);
    if (is_array($infoCached)) $infografisTerbaru = $infoCached;
  }
  if (empty($infografisTerbaru)) {
    $infoUrl = BPS_API_BASE . 'list/model/infographic/lang/ind/domain/' . urlencode(BPS_DOMAIN)
      . '/key/' . urlencode(BPS_API_KEY) . '/page/1/';
    $infoRes = bpsApiCall($infoUrl, 10);
    if ($infoRes['ok']) {
      $rows = $infoRes['json']['data'][1] ?? [];
      foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $infografisTerbaru[] = [
          'title' => (string) ($r['title'] ?? '(Tanpa Judul)'),
          'img' => (string) ($r['img'] ?? ''),
          'dl' => (string) ($r['dl'] ?? ''),
          'date' => (string) ($r['date'] ?? ''),
          'desc' => trim(preg_replace('/\s+/', ' ', strip_tags((string) ($r['desc'] ?? '')))),
        ];
        if (count($infografisTerbaru) >= 6) break;
      }
      @file_put_contents($infoCacheFile, json_encode($infografisTerbaru));
    } elseif ($infoCacheOk === false && is_file($infoCacheFile)) {
      $infoCached = json_decode((string) @file_get_contents($infoCacheFile), true);
      if (is_array($infoCached)) $infografisTerbaru = array_slice($infoCached, 0, 6);
    }
  } else {
    $infografisTerbaru = array_slice($infografisTerbaru, 0, 6);
  }
} catch (Throwable $e) {
  $infografisTerbaru = [];
}
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>BPS Sumatera Utara - Dashboard</title>
  <link rel="icon" href="assets/img/logo_(1)_1643969039217.png" type="image/png" />
  <link rel="stylesheet" href="assets/css/myCSS2.css" />
  <script src="assets/js/nav.js" defer></script>
  <style>
    :root {
      --navy: #002b6a;
      --navy-soft: #034f84;
      --ink: #1a2332;
      --muted: #6b7686;
      --line: #e8ecf1;
      --bg-soft: #f6f8fb;
    }

    main {
      max-width: 1180px;
    }

    /* HERO minimalis elegan */
    .dashboard-hero {
      position: relative;
      overflow: hidden;
      background-image: url('assets/img/latar.webp');
      background-size: cover;
      background-position: center;
      color: white;
      padding: 96px 48px 88px 48px;
      border-radius: 18px;
      margin-top: 28px;
      margin-bottom: 0;
      box-shadow: 0 18px 45px rgba(0, 43, 106, 0.22);
      isolation: isolate;
    }

    .dashboard-hero::before {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(100deg, rgba(0, 27, 68, 0.92) 0%, rgba(0, 43, 106, 0.78) 45%, rgba(0, 43, 106, 0.35) 100%);
      z-index: -1;
    }

    .dashboard-hero::after {
      content: "";
      position: absolute;
      left: 48px;
      bottom: 0;
      width: 64px;
      height: 4px;
      border-radius: 4px 4px 0 0;
    }

    .dashboard-hero h1 {
      margin: 0 0 14px 0;
      font-size: clamp(22px, 3.2vw, 34px);
      line-height: 1.35;
      font-weight: 700;
      max-width: 820px;
    }

    .dashboard-hero p {
      margin: 0 0 28px 0;
      font-size: 17px;
      line-height: 1.7;
      opacity: 0.85;
      max-width: 640px;
    }

    .hero-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
    }

    .hero-btn {
      display: inline-block;
      text-decoration: none;
      font-size: 17px;
      font-weight: bold;
      padding: 12px 26px;
      border-radius: 999px;
      transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
    }

    .hero-btn.primary {
      background: white;
      color: var(--navy);
    }

    .hero-btn.primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 22px rgba(0, 0, 0, 0.25);
    }

    .hero-btn.ghost {
      border: 1px solid rgba(255, 255, 255, 0.55);
      color: white;
    }

    .hero-btn.ghost:hover {
      background: rgba(255, 255, 255, 0.12);
    }

    /* Section heading generik */
    .home-section {
      margin-top: 56px;
    }

    .section-head {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 6px;
    }

    .section-head h2 {
      color: var(--navy);
      font-size: 25px;
      margin: 0;
      letter-spacing: 0.2px;
    }

    .section-head h2::after {
      content: "";
      display: block;
      width: 44px;
      height: 3px;
      border-radius: 3px;
      margin-top: 8px;
    }

    .section-sub {
      color: var(--muted);
      font-size: 17px;
      margin: 10px 0 22px 0;
    }

    .section-link {
      font-size: 17px;
      font-weight: bold;
      color: var(--navy-soft);
      text-decoration: none;
      white-space: nowrap;
    }

    .section-link:hover {
      text-decoration: underline;
    }

    /* Indikator: kartu minimalis + ikon */
    .bps-indicator-grid {
      display: flex;
      gap: 16px;
      overflow-x: auto;
      scroll-snap-type: x mandatory;
      scroll-behavior: smooth;
      padding: 4px 2px 14px 2px;
      margin-bottom: 20px;
      scrollbar-width: none;
      -ms-overflow-style: none;
    }

    .bps-indicator-grid::-webkit-scrollbar {
      display: none;
    }

    .bps-carousel-wrap {
      position: relative;
    }

    .bps-carousel-btn {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      z-index: 2;
      width: 38px;
      height: 38px;
      border-radius: 50%;
      border: 1px solid var(--line);
      background: white;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.14);
      color: #002b6a;
      font-size: 20px;
      line-height: 1;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .bps-carousel-btn:hover {
      background: #f0f4fa;
    }

    .bps-carousel-btn.bps-prev {
      left: -8px;
    }

    .bps-carousel-btn.bps-next {
      right: -8px;
    }

    @media (max-width: 640px) {
      .bps-carousel-btn {
        display: none;
      }
    }

    .bps-indicator-card {
      background: white;
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 20px 18px 16px 18px;
      box-shadow: 0 2px 10px rgba(0, 43, 106, 0.05);
      flex: 0 0 225px;
      scroll-snap-align: start;
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .bps-indicator-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 24px rgba(0, 43, 106, 0.12);
    }

    .bps-card-top {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 12px;
    }

    .bps-icon {
      width: 38px;
      height: 38px;
      flex: 0 0 38px;
      border-radius: 10px;
      background: #eef3fa;
      color: var(--navy);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 21px;
    }

    /* Ikon PNG kustom indikator (file 64x64px tampil 22px). */
    .bps-icon img {
      width: 22px;
      height: 22px;
      object-fit: contain;
      display: block;
    }

    .bps-icon:empty {
      display: none;
    }

    .bps-indicator-card .bps-title {
      color: var(--ink);
      font-size: 17px;
      font-weight: bold;
      line-height: 1.45;
    }

    .bps-indicator-card .bps-value {
      font-size: 30px;
      font-weight: 700;
      color: #002b6a;
    }

    .bps-indicator-card .bps-unit {
      font-size: 15px;
      color: #888;
      font-weight: normal;
      margin-left: 4px;
    }

    .bps-indicator-card .bps-meta {
      font-size: 14px;
      color: #999;
      margin-top: 6px;
    }

    .bps-indicator-card.bps-error {
      color: #c0392b;
      font-size: 15px;
    }

    .bps-trend-card {
      background: white;
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 24px 22px;
      box-shadow: 0 2px 10px rgba(0, 43, 106, 0.05);
    }

    .bps-trend-card h3 {
      color: #002b6a;
      font-size: 18px;
      margin: 0 0 15px 0;
    }

    /* Layanan cepat */
    .quick-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 14px;
    }

    .quick-grid-3 {
      grid-template-columns: repeat(3, 1fr);
    }

    @media (max-width: 640px) {
      .quick-grid-3 {
        grid-template-columns: 1fr;
      }
    }

    .quick-card {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 10px;
      background: white;
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 20px 18px;
      text-decoration: none;
      transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
    }

    .quick-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 24px rgba(0, 43, 106, 0.10);
      border-color: #c8d6ea;
    }

    .quick-card .q-icon {
      width: 40px;
      height: 40px;
      border-radius: 10px;
      background: transparent;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 21px;
    }

    /* Ikon PNG di dalam kotak layanan (file 64x64px tampil 22px). */
    .quick-card .q-icon img {
      width: 22px;
      height: 22px;
      object-fit: contain;
      display: block;
    }

    .quick-card strong {
      color: var(--ink);
      font-size: 16px;
    }

    .quick-card span {
      color: var(--muted);
      font-size: 14px;
      line-height: 1.55;
    }

    /* Informasi terbaru: tab Publikasi / BRS / Infografis */
    .info-tabs {
      display: flex;
      gap: 8px;
      border-bottom: 2px solid var(--line);
      margin-bottom: 20px;
    }

    .info-tab-btn {
      appearance: none;
      background: none;
      border: none;
      border-bottom: 3px solid transparent;
      margin-bottom: -2px;
      padding: 12px 22px;
      font-size: 16px;
      font-weight: bold;
      color: var(--muted);
      cursor: pointer;
      font-family: inherit;
    }

    .info-tab-btn:hover {
      color: var(--navy);
    }

    .info-tab-btn.active {
      color: var(--navy);
      border-bottom-color: var(--navy);
    }

    .info-tab-panel {
      display: none;
    }

    .info-tab-panel.active {
      display: block;
    }

    /* Isi tiap tab: 2 kolom sejajar */
    .info-tab-list {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
    }

    .info-tab-list .pub-item,
    .info-tab-list .brs-item,
    .info-tab-list .infografis-mini-item {
      border: 1px solid var(--line);
      border-radius: 10px;
      padding: 12px;
    }

    .info-tab-list .pub-item:first-of-type,
    .info-tab-list .brs-item:first-of-type,
    .info-tab-list .infografis-mini-item:first-of-type {
      border-top: 1px solid var(--line);
    }

    @media (max-width: 700px) {
      .info-tab-list {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 600px) {
      .info-tab-btn {
        flex: 1;
        padding: 12px 8px;
        font-size: 15px;
        text-align: center;
      }
    }

    @media (max-width: 860px) {
      .dashboard-hero {
        padding: 70px 26px 64px 26px;
      }

      .dashboard-hero::after {
        left: 26px;
      }
    }

    .panel {
      background: white;
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 22px;
      box-shadow: 0 2px 10px rgba(0, 43, 106, 0.05);
    }

    .panel h3 {
      margin: 0 0 4px 0;
      color: var(--navy);
      font-size: 18px;
    }

    .panel .panel-sub {
      margin: 0 0 16px 0;
      color: var(--muted);
      font-size: 14px;
    }

    .pub-item {
      display: flex;
      gap: 14px;
      padding: 12px 0;
      border-top: 1px solid var(--line);
      text-decoration: none;
      align-items: center;
    }

    .pub-item:first-of-type {
      border-top: none;
    }

    .pub-cover {
      width: 52px;
      height: 70px;
      object-fit: cover;
      border-radius: 6px;
      border: 1px solid var(--line);
      flex: 0 0 52px;
      background: var(--bg-soft);
    }

    .pub-item strong {
      display: block;
      color: var(--ink);
      font-size: 15px;
      line-height: 1.5;
      margin-bottom: 4px;
    }

    .pub-item:hover strong {
      color: var(--navy-soft);
      text-decoration: underline;
    }

    .pub-item small {
      color: var(--muted);
      font-size: 14px;
    }

    .galeri-strip {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    .galeri-thumb {
      display: block;
      border-radius: 10px;
      overflow: hidden;
      border: 1px solid var(--line);
      text-decoration: none;
      background: var(--bg-soft);
    }

    .galeri-thumb img {
      width: 100%;
      height: 110px;
      object-fit: cover;
      display: block;
      transition: transform 0.25s;
    }

    .galeri-thumb:hover img {
      transform: scale(1.04);
    }

    .galeri-thumb span {
      display: block;
      padding: 8px 10px;
      font-size: 14px;
      color: var(--ink);
      line-height: 1.45;
    }

    .release-list {
      list-style: none;
      margin: 0;
      padding: 0;
    }

    .release-list li {
      display: flex;
      gap: 12px;
      align-items: flex-start;
      padding: 12px 0;
      border-top: 1px solid var(--line);
      font-size: 15px;
      color: var(--ink);
    }

    .release-list li:first-child {
      border-top: none;
    }

    .release-date {
      flex: 0 0 52px;
      text-align: center;
      background: var(--bg-soft);
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 6px 4px;
    }

    .release-date b {
      display: block;
      font-size: 19px;
      color: var(--navy);
    }

    .release-date small {
      font-size: 13px;
      color: var(--muted);
      text-transform: uppercase;
    }

    .empty-note {
      color: var(--muted);
      font-size: 15px;
      background: var(--bg-soft);
      border: 1px dashed #c4cedb;
      border-radius: 8px;
      padding: 16px;
      text-align: center;
    }

    /* Mini BRS ala berita.php */
    .brs-item {
      display: flex;
      gap: 12px;
      padding: 12px 0;
      border-top: 1px solid var(--line);
      text-decoration: none;
      align-items: flex-start;
    }

    .brs-item:first-of-type {
      border-top: none;
    }

    .brs-thumb {
      width: 84px;
      height: 60px;
      object-fit: cover;
      border-radius: 8px;
      border: 1px solid var(--line);
      flex: 0 0 84px;
      background: var(--bg-soft);
    }

    .brs-item strong {
      display: block;
      color: var(--ink);
      font-size: 15px;
      line-height: 1.5;
      margin-bottom: 4px;
    }

    .brs-item:hover strong {
      color: var(--navy-soft);
      text-decoration: underline;
    }

    .brs-item small {
      color: var(--muted);
      font-size: 14px;
    }

    .brs-badge {
      display: inline-block;
      background: #e8f0fe;
      color: var(--navy-soft);
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 13px;
      margin-top: 4px;
    }

    /* Mini infografis ala infografis.php (item) */
    .infografis-mini-item {
      display: flex;
      gap: 12px;
      padding: 12px 0;
      border-top: 1px solid var(--line);
      text-decoration: none;
      align-items: center;
    }

    .infografis-mini-item:first-of-type {
      border-top: none;
    }

    .infografis-mini-item img {
      width: 64px;
      height: 64px;
      object-fit: cover;
      border-radius: 8px;
      border: 1px solid var(--line);
      flex: 0 0 64px;
      background: var(--bg-soft);
    }

    .infografis-mini-item strong {
      display: block;
      color: var(--ink);
      font-size: 15px;
      line-height: 1.5;
      margin-bottom: 2px;
    }

    .infografis-mini-item:hover strong {
      color: var(--navy-soft);
      text-decoration: underline;
    }

    .infografis-mini-item small {
      color: var(--muted);
      font-size: 14px;
    }

    .show-all {
      display: block;
      margin-top: 14px;
      padding-top: 12px;
      border-top: 1px solid var(--line);
      text-align: center;
      font-size: 15px;
      font-weight: bold;
      color: var(--navy-soft);
      text-decoration: none;
    }

    .show-all:hover {
      text-decoration: underline;
    }

    /* Layanan eksternal ala sumut.bps.go.id */
    .ext-strip {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 14px;
    }

    .ext-card {
      display: flex;
      align-items: center;
      gap: 12px;
      background: var(--navy);
      color: white;
      border-radius: 12px;
      padding: 16px 18px;
      text-decoration: none;
      transition: transform 0.2s, background 0.2s;
    }

    .ext-card:hover {
      transform: translateY(-3px);
      background: #013a8b;
    }

    .ext-card .e-icon {
      font-size: 24px;
    }

    .ext-card strong {
      display: block;
      font-size: 16px;
    }

    .ext-card small {
      font-size: 14px;
      opacity: 0.75;
    }

    /* Responsif home */
    @media (max-width: 900px) {
      .dashboard-hero {
        padding: 64px 26px 58px 26px;
        border-radius: 14px;
      }

      .dashboard-hero::after {
        left: 26px;
      }

      .home-section {
        margin-top: 44px;
      }

      .section-head {
        flex-wrap: wrap;
        align-items: flex-start;
      }
    }

    @media (max-width: 600px) {
      .dashboard-hero {
        padding: 48px 20px 44px 20px;
        margin-top: 16px;
      }

      .hero-actions .hero-btn {
        flex: 1 1 100%;
        text-align: center;
      }

      .quick-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
      }

      .quick-card {
        padding: 16px 14px;
      }

      .quick-card span {
        display: none;
      }

      .bps-indicator-card {
        flex-basis: 200px;
      }

      .galeri-strip {
        grid-template-columns: 1fr 1fr;
      }

      .panel {
        padding: 18px 16px;
      }

      .ext-strip {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
      }

      .ext-card {
        padding: 14px;
      }

      .ext-card small {
        display: none;
      }
    }
  </style>
</head>

<body>
  <header>
    <a href="index.php" title="Kembali ke Home"><img src="https://ppid.bps.go.id/upload/img/logo_(1)_1643969039217.png" alt="Logo Web" onerror="this.src = 'assets/img/logo_(1)_1643969039217.png'" /></a>
    <div class="judulweb">BADAN PUSAT STATISTIK<br />PROVINSI SUMATERA UTARA</div>
    <nav>
      <a href="index.php" class="active">Home</a>
      <div class="nav-dropdown">
        <button class="nav-dropbtn">Produk ▾</button>
        <div class="nav-dropdown-content">
          <a href="modules/publikasi/page09A.php">Publikasi</a>
          <a href="modules/publikasi/berita.php">Berita Resmi Statistik</a>
          <a href="modules/publikasi/katalog.php">Statistik Berdasarkan Subjek</a>
          <a href="modules/publikasi/data_dinamis.php">Tabel Dinamis</a>
          <a href="modules/publikasi/exim.php">Data Ekspor Impor</a>
          <a href="modules/publikasi/pers.php">Berita dan Siaran Pers</a>
          <a href="modules/publikasi/infografis.php">Infografis</a>
          <a href="https://sensus.bps.go.id" target="_blank" rel="noopener">Data Sensus</a>
          <a href="https://direktori.web.bps.go.id" target="_blank" rel="noopener">Direktori</a>
          <a href="https://sirusa.web.bps.go.id/metadata" target="_blank" rel="noopener">Metadata</a>
        </div>
      </div>
      <?php if (isAdmin()): ?>
        <a href="modules/publikasi/page09C.php">Tambah Publikasi</a>
      <?php endif; ?>
      <a href="modules/galeri/page09G.php">Galeri Kegiatan</a>

      <?php if (isAdmin()): ?>
        <div class="profile-dropdown">
          <svg class="profile-icon" viewBox="0 0 24 24" fill="white">
            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
          </svg>
          <div class="dropdown-content">
            <div class="user-info">
              <strong><?= htmlspecialchars($_SESSION['nama']) ?></strong>
              <span>role: <?= htmlspecialchars($_SESSION['role']) ?></span>
            </div>
            <a href="modules/auth/logout.php" class="btn-logout">logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="modules/auth/login.php">Login</a>
      <?php endif; ?>
    </nav>
  </header>

  <main>
    <div class="dashboard-hero">
      <h1>Lembaga yang Independen, Tepercaya, dan Berperan Aktif dalam Mendukung Perumusan Kebijakan Berbasis Data Bersama Indonesia Maju Menuju Indonesia Emas 2045</h1>
      <p>Portal data dan Publikasi Statistik: Indikator Strategis, Berita Resmi Statistik, Publikasi, dan Tabel Dinamis Sumatera Utara dalam Satu Tempat.</p>
      <div class="hero-actions">
        <a class="hero-btn primary" href="modules/publikasi/page09A.php">Jelajahi Publikasi</a>
        <a class="hero-btn ghost" href="modules/publikasi/data_dinamis.php">Tabel Dinamis</a>
      </div>
    </div>

    <section class="home-section">
      <div class="section-head">
        <h2>Layanan &amp; Produk Unggulan</h2>
      </div>
      <p class="section-sub">Akses cepat ke produk statistik yang paling sering dicari</p>
      <div class="quick-grid quick-grid-3">
        <a class="quick-card" href="modules/publikasi/page09A.php">
          <span class="q-icon"><img src="assets/img/icon-publikasi.png" alt="" loading="lazy" /></span><strong>Publikasi</strong><span>Katalog publikasi resmi BPS Sumut</span>
        </a>
        <a class="quick-card" href="modules/publikasi/berita.php">
          <span class="q-icon"><img src="assets/img/icon-beritaresmi.png" alt="" loading="lazy" /></span><strong>Berita Resmi Statistik</strong><span>Rilis BRS terbaru setiap bulan</span>
        </a>
        <a class="quick-card" href="modules/publikasi/infografis.php">
          <span class="q-icon"><img src="assets/img/icon-infog.png" alt="" loading="lazy" /></span><strong>Infografis</strong><span>Visual data yang mudah dibagikan</span>
        </a>
      </div>
    </section>

    <?php if (!empty($bpsIndicatorCards) || $bpsTrendCard !== null): ?>
      <section class="home-section">
        <div class="section-head">
          <h2>Indikator Strategis Terkini</h2>
          <a class="section-link" href="modules/publikasi/data_dinamis.php">Lihat tabel dinamis →</a>
        </div>
        <p class="section-sub">Data diambil langsung dari Web API BPS (webapi.bps.go.id), diperbarui otomatis setiap 1 jam.</p>

        <?php if (!empty($bpsIndicatorCards)): ?>
          <div class="bps-carousel-wrap">
            <button type="button" class="bps-carousel-btn bps-prev" onclick="bpsScrollIndicator(-1)" aria-label="Geser ke kiri">&#10094;</button>
            <div class="bps-indicator-grid" id="bpsIndicatorCarousel">
              <?php foreach ($bpsIndicatorCards as $card): ?>
                <?php if ($card['ok']): ?>
                  <div class="bps-indicator-card">
                    <div class="bps-card-top">
                      <?php if (!empty($card['icon'])): ?><span class="bps-icon"><?= $card['icon'] ?></span><?php endif; ?>
                      <div class="bps-title"><?= htmlspecialchars($card['title']) ?></div>
                    </div>
                    <div class="bps-value">
                      <?= htmlspecialchars($card['value']) ?><?php if ($card['unit']): ?><span class="bps-unit"><?= htmlspecialchars($card['unit']) ?></span><?php endif; ?>
                    </div>
                    <div class="bps-meta">
                      <?php
                      // Label periode bisa "2025" atau "Agustus 2026": awalan
                      // "Tahun" hanya untuk label berupa angka tahun murni.
                      $periodLabel = (string) $card['year'];
                      echo ctype_digit($periodLabel) ? ('Tahun ' . htmlspecialchars($periodLabel)) : htmlspecialchars($periodLabel);
                      ?>
                      <?php if ($card['vervarLabel'] && $card['vervarLabel'] !== 'Sumatera Utara'): ?>
                        &middot; <?= htmlspecialchars($card['vervarLabel']) ?>
                      <?php endif; ?>
                      <?php
                      // Sembunyikan label turvar generik ("Total", "Tidak Ada", ...).
                      $turvarLower = strtolower(trim((string) $card['turvarLabel']));
                      $turvarGeneric = ['total', 'tidak ada', '-', 'semua'];
                      ?>
                      <?php if ($card['turvarLabel'] && !in_array($turvarLower, $turvarGeneric, true)): ?>
                        &middot; <?= htmlspecialchars($card['turvarLabel']) ?>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php else: ?>
                  <div class="bps-indicator-card bps-error">
                    <div class="bps-title"><?= htmlspecialchars($card['title'] ?: 'Indikator BPS') ?></div>
                    Data tidak tersedia (<?= htmlspecialchars($card['error']) ?>)
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
            <button type="button" class="bps-carousel-btn bps-next" onclick="bpsScrollIndicator(1)" aria-label="Geser ke kanan">&#10095;</button>
          </div>
        <?php endif; ?>

        <?php if ($bpsTrendCard !== null): ?>
          <div class="bps-trend-card">
            <h3><?= htmlspecialchars($bpsTrendCard['title']) ?><?php if ($bpsTrendCard['unit']): ?> (<?= htmlspecialchars($bpsTrendCard['unit']) ?>)<?php endif; ?></h3>
            <?= renderBpsTrendSvg($bpsTrendCard['trend']) ?>
          </div>
        <?php endif; ?>
      </section>
    <?php else: ?>
      <!-- Bagian indikator disembunyikan bila tidak ada yang dikonfigurasi. -->
    <?php endif; ?>

    <section class="home-section">
      <div class="section-head">
        <h2>Informasi Terbaru</h2>
      </div>
      <p class="section-sub">Sorotan rilis terbaru dari Publikasi, BRS, dan Infografis</p>
      <div class="info-tabs" role="tablist" aria-label="Kategori informasi terbaru">
        <button type="button" class="info-tab-btn active" data-tab="publikasi" role="tab" aria-selected="true">Publikasi</button>
        <button type="button" class="info-tab-btn" data-tab="brs" role="tab" aria-selected="false">BRS</button>
        <button type="button" class="info-tab-btn" data-tab="infografis" role="tab" aria-selected="false">Infografis</button>
      </div>
      <div class="info-tab-panels">
        <div class="panel info-tab-panel active" id="tab-publikasi" role="tabpanel">
          <h3>Publikasi Terbaru</h3>
          <p class="panel-sub">Terbit paling akhir dari katalog kami</p>
          <?php if (!empty($publikasiTerbaru)): ?>
            <div class="info-tab-list">
              <?php foreach ($publikasiTerbaru as $pub): ?>
                <?php $isManualHome = ($pub['source'] ?? '') === 'Manual'; ?>
                <?php $pubHref = $isManualHome ? ('modules/publikasi/page09A.php?q=' . urlencode($pub['title'])) : ($pub['pdf'] !== '' ? $pub['pdf'] : 'modules/publikasi/page09A.php'); ?>
                <a class="pub-item" href="<?= htmlspecialchars($pubHref) ?>" <?= $isManualHome ? '' : 'target="_blank" rel="noopener"' ?>>
                  <?php if ($isManualHome && !empty($pub['cover_local'])): ?>
                    <img class="pub-cover" src="assets/img/<?= htmlspecialchars($pub['cover_local']) ?>" alt="Sampul" loading="lazy" onerror="this.style.display='none'" />
                  <?php elseif (!$isManualHome && !empty($pub['cover'])): ?>
                    <img class="pub-cover" src="<?= htmlspecialchars($pub['cover']) ?>" alt="Sampul" loading="lazy" referrerpolicy="no-referrer" onerror="this.style.display='none'" />
                  <?php endif; ?>
                  <div>
                    <strong><?= htmlspecialchars($pub['title']) ?></strong>
                    <small>
                      <?= $pub['rl_date'] !== '' ? htmlspecialchars(date('d M Y', strtotime($pub['rl_date']))) : 'Tanggal tidak tersedia' ?>
                      &middot; <?= $isManualHome ? (number_format((int) ($pub['dilihat'] ?? 0), 0, ',', '.') . 'x dilihat') : 'Sumber: BPS' ?>
                    </small>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="empty-note">Belum ada publikasi. Tambahkan lewat menu admin.</div>
          <?php endif; ?>
          <a class="show-all" href="modules/publikasi/page09A.php">Tampilkan Keseluruhan &rarr;</a>
        </div>
        <div class="panel info-tab-panel" id="tab-brs" role="tabpanel">
          <h3>BRS Terbaru</h3>
          <p class="panel-sub">Berita Resmi Statistik paling akhir</p>
          <?php if (!empty($brsTerbaru)): ?>
            <div class="info-tab-list">
              <?php foreach ($brsTerbaru as $brs): ?>
                <?php $brsImg = !empty($brs['thumbnail']) ? $brs['thumbnail'] : 'assets/img/logo_(1)_1643969039217.png'; ?>
                <a class="brs-item" href="<?= htmlspecialchars($brs['pdf'] !== '' ? $brs['pdf'] : 'modules/publikasi/berita.php') ?>" target="_blank" rel="noopener">
                  <img class="brs-thumb" src="<?= htmlspecialchars($brsImg) ?>" alt="Thumbnail BRS" loading="lazy" referrerpolicy="no-referrer" onerror="this.src='assets/img/logo_(1)_1643969039217.png'" />
                  <div>
                    <strong><?= htmlspecialchars(mb_strimwidth($brs['title'], 0, 90, '…')) ?></strong>
                    <small><?= htmlspecialchars(homeFormatTanggalBrs($brs['rl_date'] ?? '')) ?></small>
                    <?php if (!empty($brs['subj'])): ?><br /><span class="brs-badge"><?= htmlspecialchars($brs['subj']) ?></span><?php endif; ?>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="empty-note">Belum ada BRS yang dapat ditampilkan.</div>
          <?php endif; ?>
          <a class="show-all" href="modules/publikasi/berita.php">Tampilkan Keseluruhan &rarr;</a>
        </div>
        <div class="panel info-tab-panel" id="tab-infografis" role="tabpanel">
          <h3>Infografis Terbaru</h3>
          <p class="panel-sub">Visual data terbaru yang mudah dibagikan</p>
          <?php if (!empty($infografisTerbaru)): ?>
            <div class="info-tab-list">
              <?php foreach ($infografisTerbaru as $info): ?>
                <?php $infoImg = !empty($info['img']) ? $info['img'] : 'assets/img/logo_(1)_1643969039217.png'; ?>
                <?php
                $tglInfo = trim((string) ($info['date'] ?? ''));
                $tglTampil = $tglInfo;
                $dtInfo = DateTime::createFromFormat('Y-m-d', $tglInfo);
                if (!$dtInfo) $dtInfo = DateTime::createFromFormat('Y m d', $tglInfo);
                if ($dtInfo) {
                  $tglTampil = ltrim($dtInfo->format('d'), '0') . ' ' . $namaBulanId[(int) $dtInfo->format('m') - 1] . ' ' . $dtInfo->format('Y');
                }
                ?>
                <a class="infografis-mini-item" href="modules/publikasi/infografis.php">
                  <img src="<?= htmlspecialchars($infoImg) ?>" alt="Infografis BPS" loading="lazy" referrerpolicy="no-referrer" onerror="this.src='assets/img/logo_(1)_1643969039217.png'" />
                  <div>
                    <strong><?= htmlspecialchars(mb_strimwidth($info['title'], 0, 80, '…')) ?></strong>
                    <small><?= htmlspecialchars($tglTampil !== '' ? $tglTampil : '-') ?></small>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="empty-note">Belum ada infografis yang dapat ditampilkan.</div>
          <?php endif; ?>
          <a class="show-all" href="modules/publikasi/infografis.php">Tampilkan Keseluruhan &rarr;</a>
        </div>
      </div>
    </section>
    <script>
      // Tab Informasi Terbaru: tampilkan 1 kategori dalam 1 waktu.
      (function() {
        var btns = document.querySelectorAll('.info-tab-btn');
        var panels = document.querySelectorAll('.info-tab-panel');
        if (!btns.length || !panels.length) return;
        btns.forEach(function(btn) {
          btn.addEventListener('click', function() {
            var target = 'tab-' + btn.getAttribute('data-tab');
            btns.forEach(function(b) {
              var on = b === btn;
              b.classList.toggle('active', on);
              b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            panels.forEach(function(p) {
              p.classList.toggle('active', p.id === target);
            });
          });
        });
      })();
    </script>

    <section class="home-section">
      <div class="section-head">
        <h2>Layanan Lainnya</h2>
      </div>
      <p class="section-sub">Pintasan ke layanan resmi BPS</p>
      <div class="ext-strip">
        <a class="ext-card" href="https://webapi.bps.go.id/developer" target="_blank" rel="noopener">
          <span class="e-icon"></span>
          <div><strong>WebAPI BPS</strong><small>Akses data via API</small></div>
        </a>
        <a class="ext-card" href="https://pst.bps.go.id/" target="_blank" rel="noopener">
          <span class="e-icon"></span>
          <div><strong>PST</strong><small>Pelayanan Statistik Terpadu</small></div>
        </a>
        <a class="ext-card" href="https://indah.bps.go.id" target="_blank" rel="noopener">
          <span class="e-icon"></span>
          <div><strong>INDAH</strong><small>Indonesia Data Hub</small></div>
        </a>
        <a class="ext-card" href="https://sig.bps.go.id" target="_blank" rel="noopener">
          <span class="e-icon"></span>
          <div><strong>Geoportal</strong><small>Data spasial BPS</small></div>
        </a>
      </div>
    </section>
  </main>

  <footer>
    <div class="footer-main">
      <div class="footer-logo">
        <img
          src="https://ppid.bps.go.id/upload/img/logo_(1)_1643969039217.png"
          alt="Logo BPS"
          onerror="this.src = '../../assets/img/logo_(1)_1643969039217.png'" />
        <span>BADAN PUSAT STATISTIK</span>
      </div>
      <div class="footer-cols">
        <div class="footer-col footer-info">
          <p>
            Badan Pusat Statistik Provinsi Sumatera Utara<br />
            Jl. Asrama No. 179 Medan 20123 Indonesia<br />
            Telp (62-61) 8452343<br />
            Faks (62-61) 8452773<br />
            Mailbox : pst1200@bps.go.id
          </p>
          <img
            id="berakhlak"
            src="https://sumut.bps.go.id/_next/image?url=https%3A%2F%2Fweb-api.bps.go.id%2Fcover.php%3Ff%3DEYdggRLWtt0DRl%2FoNNVbSGM0U1RrVDVtNUlXSWpBS3J0SXpLbUpNUXhXcnkzMUN5aE4rZDIxL0pGeDg5VXVaOGJGdHErblhTdk8ydmJIR2lKWHUxaHExTzkvZWdNaWRSUkJvY3V3PT0%3D&w=3840&q=75"
            alt="BerAKHLAK"
            onerror="this.src = '../../assets/img/berakhlak.webp'" />
          <div class="tigaopsi">
            <a
              href="https://manual-website-bps.readthedocs.io/"
              target="_blank">Manual</a>
            <a href="https://sumut.bps.go.id/id/term-of-use" target="_blank">S&K</a>
            <a href="https://sumut.bps.go.id/id/tautan" target="_blank">Daftar Tautan</a>
          </div>
        </div>
        <div class="footer-col">
          <h4>Tentang Kami</h4>
          <ul>
            <li>
              <a
                href="https://ppid.bps.go.id/app/konten/1200/Profil-BPS.html?_gl=1*1cldsba*_ga*NjQ4ODE2NDI5LjE3NDE3OTI0NDE.*_ga_XXTTVXWHDB*czE3Nzc3MzQwNDgkbzE2JGcwJHQxNzc3NzM0MDQ4JGo2MCRsMCRoMA.."
                target="_blank">Profil BPS</a>
            </li>
            <li>
              <a
                href="https://ppid.bps.go.id/?mfd=1200&_gl=1*hh7m93*_ga*NjQ4ODE2NDI5LjE3NDE3OTI0NDE.*_ga_XXTTVXWHDB*czE3Nzc3MzQwNDgkbzE2JGcwJHQxNzc3NzM0MDQ4JGo2MCRsMCRoMA.."
                target="_blank">PPID</a>
            </li>
            <li>
              <a
                href="https://ppid.bps.go.id/app/konten/0000/Layanan-BPS.html?_gl=1*hh7m93*_ga*NjQ4ODE2NDI5LjE3NDE3OTI0NDE.*_ga_XXTTVXWHDB*czE3Nzc3MzQwNDgkbzE2JGcwJHQxNzc3NzM0MDQ4JGo2MCRsMCRoMA..#pills-3"
                target="_blank">Kebijakan Diseminasi</a>
            </li>
          </ul>
        </div>
        <div class="footer-col">
          <h4>Tautan Lainnya</h4>
          <ul>
            <li>
              <a href="https://www.aseanstats.org/" target="_blank">ASEAN Stats</a>
            </li>
            <li>
              <a href="https://rb.bps.go.id/" target="_blank">Reformasi Birokrasi</a>
            </li>
            <li>
              <a href="https://lpse.bps.go.id/" target="_blank">Layanan Pengadaan Secara Elektronik</a>
            </li>
            <li>
              <a href="https://stis.ac.id/" target="_blank">Politeknik Statistika STIS</a>
            </li>
            <li>
              <a href="https://pusdiklat.bps.go.id/" target="_blank">Pusdiklat BPS</a>
            </li>
            <li>
              <a href="https://jdih.bps.go.id/" target="_blank">JDIH BPS</a>
            </li>
          </ul>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <p>
        Copyright &copy; 2026 Politeknik Statistika STIS &nbsp;|&nbsp; Created
        by Muhammad Hafidz Ar Rasyid Hutagalung (222413683@stis.ac.id)
      </p>
    </div>
  </footer>

  <?php if (!empty($bpsIndicatorCards)): ?>
    <script>
      // Carousel loop mulus: kartu diduplikasi 1x (A + A'), posisi
      // dinormalisasi 1 set saat mencapai ujung sehingga tampak menyambung.
      function bpsScrollIndicator(direction) {
        var track = document.getElementById('bpsIndicatorCarousel');
        if (!track || !window.bpsLoopStep) return;
        window.bpsLoopStep(direction);
        if (window.bpsRestartAutoSlide) {
          window.bpsRestartAutoSlide();
        }
      }

      // Auto-slide tiap 4 detik; berhenti saat hover/sentuh/tab
      // tidak aktif/reduced-motion. Klik panah me-reset jeda.
      (function bpsInfiniteLoop() {
        var track = document.getElementById('bpsIndicatorCarousel');
        if (!track) return;

        var cards = track.querySelectorAll('.bps-indicator-card');
        if (cards.length > 1 && track.scrollWidth > track.clientWidth + 10) {
        // Gandakan kartu untuk loop (salinan disembunyikan dari screen reader).
          Array.prototype.forEach.call(cards, function(node) {
            var clone = node.cloneNode(true);
            clone.setAttribute('aria-hidden', 'true');
            track.appendChild(clone);
          });
        }

        // Lebar 1 set kartu.
        function half() {
          return track.scrollWidth / 2;
        }

        // Langkah geser = 1 kartu + gap CSS.
        function step() {
          var kartu = track.querySelector('.bps-indicator-card');
          var gap = parseFloat(window.getComputedStyle(track).gap) || 16;
          return kartu ? kartu.offsetWidth + gap : Math.max(200, track.clientWidth * 0.9);
        }

        function bisaGeser() {
          return track.scrollWidth > track.clientWidth + 10;
        }

        function atEnd() {
          return track.scrollLeft + track.clientWidth >= track.scrollWidth - 12;
        }

        window.bpsLoopStep = function(direction) {
          if (!bisaGeser()) return;
          var s = step();
          if (direction > 0 && atEnd()) {
            track.scrollLeft = track.scrollLeft - half();
            track.scrollBy({
              left: s,
              behavior: 'smooth'
            });
          } else if (direction < 0 && track.scrollLeft <= 12) {
            track.scrollLeft = track.scrollLeft + half();
            track.scrollBy({
              left: -s,
              behavior: 'smooth'
            });
          } else {
            track.scrollBy({
              left: s * direction,
              behavior: 'smooth'
            });
          }
        };

        // Normalisasi posisi setelah swipe bebas agar ruang loop tak habis.
        var settleTimer = null;
        track.addEventListener('scroll', function() {
          if (settleTimer) clearTimeout(settleTimer);
          settleTimer = setTimeout(function() {
            var h = half();
            if (h > 0 && track.scrollLeft >= h) {
              track.scrollLeft = track.scrollLeft - h;
            }
          }, 160);
        }, {
          passive: true
        });

        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        var timer = null;
        var DELAY_MS = 4000;

        function geserSekali() {
          if (document.hidden || !bisaGeser()) return;
          window.bpsLoopStep(1);
        }

        function mulai() {
          hentikan();
          timer = setInterval(geserSekali, DELAY_MS);
        }

        function hentikan() {
          if (timer) {
            clearInterval(timer);
            timer = null;
          }
        }

        window.bpsRestartAutoSlide = mulai;

        track.addEventListener('mouseenter', hentikan);
        track.addEventListener('mouseleave', mulai);
        track.addEventListener('touchstart', hentikan, {
          passive: true
        });
        track.addEventListener('touchend', mulai, {
          passive: true
        });
        document.addEventListener('visibilitychange', function() {
          if (document.hidden) {
            hentikan();
          } else {
            mulai();
          }
        });

        mulai();
      })();
    </script>
  <?php endif; ?>
</body>

</html>