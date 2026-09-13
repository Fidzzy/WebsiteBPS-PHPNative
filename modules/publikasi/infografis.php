<?php
include '../../config/dbconn.php';
require_once '../../config/bps_api.php';
require_once '../../includes/auth.php';
// Halaman publik: infografis BPS bisa dilihat tanpa login.

// Halaman minimal 1, dibatasi biar tidak sembarangan dikirim ke API BPS
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

// Kata kunci pencarian (maks 100 karakter): dicari server-side via API,
// mencakup seluruh data, bukan cuma halaman aktif.
$q = mb_substr(trim($_GET['q'] ?? ''), 0, 100);

// Filter subjek (1=sosial, 2=ekonomi, 3=lingkungan+multidomain):
// API tak punya parameter subjek, jadi disaring via field `category` di PHP.
$SUBJEK_LIST = [
  '1' => 'Statistik Demografi dan Sosial',
  '2' => 'Statistik Ekonomi',
  '3' => 'Statistik Lingkungan Hidup dan Multidomain',
];
$subjek = trim($_GET['subjek'] ?? '');
if (!isset($SUBJEK_LIST[$subjek])) {
  $subjek = '';
}

// Format URL sesuai Web API BPS:
// https://webapi.bps.go.id/v1/api/list/model/infographic/lang/ind/domain/1200/key/XXX/
// Dengan pencarian: .../domain/1200/keyword/XXX/key/XXX/page/N/
$urlTanpaPage = BPS_API_BASE . 'list/model/infographic/lang/ind/domain/' . urlencode(BPS_DOMAIN) . '/';
if ($q !== '') {
  $urlTanpaPage .= 'keyword/' . urlencode($q) . '/';
}
$urlTanpaPage .= 'key/' . urlencode(BPS_API_KEY);

// Suffix query string agar link paginasi tetap membawa filter yang aktif
$qParam = '';
if ($q !== '') {
  $qParam .= '&q=' . urlencode($q);
}
if ($subjek !== '') {
  $qParam .= '&subjek=' . urlencode($subjek);
}

/**
 * Ambil SEMUA halaman infografis untuk query aktif (paralel via curl_multi)
 * dengan cache file 6 jam di folder cache/ (pola yang sama dengan
 * includes/bps_client.php). Dibutuhkan karena filter subjek harus
 * diterapkan ke seluruh data (semua halaman), sementara API BPS tidak
 * menyediakan parameter filter subjek untuk model infographic.
 *
 * @return array{ok: bool, items: array, error: string}
 */
function ambilSemuaInfografis(string $urlTanpaPage, string $domain, string $q): array
{
  $cacheDir = __DIR__ . '/../../cache';
  if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
  }
  $cacheFile = $cacheDir . '/infografis_all_' . md5($domain . '|' . $q) . '.json';

  if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 21600) {
    $cached = json_decode((string) @file_get_contents($cacheFile), true);
    if (is_array($cached)) {
      return ['ok' => true, 'items' => $cached, 'error' => ''];
    }
  }

  @set_time_limit(120);

  $curlOpts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_FOLLOWLOCATION => true,
  ];

  // Halaman 1 dulu untuk tahu total halaman + validasi respons API
  $ch = curl_init($urlTanpaPage . '/page/1/');
  curl_setopt_array($ch, $curlOpts);
  $resp = curl_exec($ch);
  curl_close($ch);
  $pertama = json_decode((string) $resp, true);
  if (!is_array($pertama) || ($pertama['status'] ?? '') !== 'OK') {
    return ['ok' => false, 'items' => [], 'error' => $pertama['message'] ?? 'Gagal menghubungi API BPS.'];
  }

  $semua = $pertama['data'][1] ?? [];
  $jmlHal = (int) ($pertama['data'][0]['pages'] ?? 1);

  // Sisa halaman diambil paralel per batch agar cepat
  if ($jmlHal > 1) {
    $mh = curl_multi_init();
    foreach (array_chunk(range(2, $jmlHal), 8) as $batch) {
      $handles = [];
      foreach ($batch as $pg) {
        $h = curl_init($urlTanpaPage . '/page/' . $pg . '/');
        curl_setopt_array($h, $curlOpts);
        curl_multi_add_handle($mh, $h);
        $handles[] = $h;
      }
      do {
        $mrc = curl_multi_exec($mh, $active);
      } while ($mrc === CURLM_CALL_MULTI_PERFORM);
      while ($active && $mrc === CURLM_OK) {
        if (curl_multi_select($mh) === -1) {
          usleep(100000);
        }
        do {
          $mrc = curl_multi_exec($mh, $active);
        } while ($mrc === CURLM_CALL_MULTI_PERFORM);
      }
      foreach ($handles as $h) {
        $j = json_decode((string) curl_multi_getcontent($h), true);
        if (is_array($j) && ($j['status'] ?? '') === 'OK' && isset($j['data'][1]) && is_array($j['data'][1])) {
          foreach ($j['data'][1] as $item) {
            $semua[] = $item;
          }
        }
        curl_multi_remove_handle($mh, $h);
        curl_close($h);
      }
    }
    curl_multi_close($mh);
  }

  @file_put_contents($cacheFile, json_encode($semua));

  return ['ok' => true, 'items' => $semua, 'error' => ''];
}

$PER_PAGE = 10;
$tampilOK = false;
$errorMsg = '';
$infoList = [];
$totalHasil = 0;
$totalPages = 1;

if ($subjek !== '') {
  // Mode filter subjek: kumpulkan semua halaman (cache), saring category,
  // urutkan terbaru, lalu paginasi lokal 10 per halaman (sama seperti API).
  $hasil = ambilSemuaInfografis($urlTanpaPage, BPS_DOMAIN, $q);
  if (!$hasil['ok']) {
    $errorMsg = $hasil['error'];
  } else {
    $tersaring = array_values(array_filter($hasil['items'], function ($it) use ($subjek) {
      return (int) ($it['category'] ?? 0) === (int) $subjek;
    }));
    usort($tersaring, function ($a, $b) {
      $tglA = (string) ($a['date'] ?? '');
      $tglB = (string) ($b['date'] ?? '');
      if ($tglA !== $tglB) {
        return strcmp($tglB, $tglA);
      }
      return (int) ($b['inf_id'] ?? 0) <=> (int) ($a['inf_id'] ?? 0);
    });
    $totalHasil = count($tersaring);
    $totalPages = max(1, (int) ceil($totalHasil / $PER_PAGE));
    $page = min($page, $totalPages);
    $infoList = array_slice($tersaring, ($page - 1) * $PER_PAGE, $PER_PAGE);
    $tampilOK = true;
  }
} else {
  // Mode normal: cukup satu halaman dari API (cepat, tanpa cache)
  $url = $urlTanpaPage . '/page/' . $page . '/';

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 15);
  curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  $response = curl_exec($ch);
  $curlError = curl_error($ch);
  $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  $bpsData = json_decode($response, true);
  if (is_array($bpsData) && ($bpsData['status'] ?? '') === 'OK') {
    $tampilOK = true;
    $infoList = $bpsData['data'][1] ?? [];
    $totalHasil = (int) ($bpsData['data'][0]['total'] ?? count($infoList));
    $totalPages = max(1, (int) ($bpsData['data'][0]['pages'] ?? 1));
  } else {
    $errorMsg = $bpsData['message'] ?? ($curlError ?: 'Tidak ada respons');
  }
}

$bulanId = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
function formatTanggalId($rawDate, $bulanId)
{
  $rawDate = trim((string) $rawDate);
  if ($rawDate === '') return '-';
  $dt = DateTime::createFromFormat('Y-m-d', $rawDate);
  if (!$dt) $dt = DateTime::createFromFormat('Y m d', $rawDate);
  if ($dt) {
    return $dt->format('d') . ' ' . $bulanId[(int) $dt->format('m') - 1] . ' ' . $dt->format('Y');
  }
  return $rawDate;
}
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Infografis - BPS Sumut</title>
  <link rel="icon" href="../../assets/img/logo_(1)_1643969039217.png" type="image/png" />
  <link rel="stylesheet" href="../../assets/css/myCSS2.css" />
  <script src="../../assets/js/nav.js" defer></script>
  <style>
    .infografis-toolbar {
      display: flex;
      gap: 10px;
      justify-content: center;
      margin: 15px 0 5px 0;
      flex-wrap: wrap;
    }

    .infografis-toolbar input {
      width: min(360px, 90%);
      padding: 10px 14px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 16px;
    }

    .infografis-toolbar select {
      padding: 10px 12px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 16px;
      background: #fff;
      max-width: 90%;
    }

    .infografis-status {
      text-align: center;
      color: #888;
      font-size: 15px;
      margin-bottom: 5px;
    }

    .infografis-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 20px;
      margin-top: 15px;
    }

    .infografis-card {
      background: white;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      border: 1px solid #eee;
      transition: transform 0.2s;
      display: flex;
      flex-direction: column;
    }

    .infografis-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
    }

    .infografis-card img {
      width: 100%;
      height: 220px;
      object-fit: cover;
      background: #f0f0f0;
      cursor: pointer;
    }

    .infografis-content {
      padding: 15px;
      display: flex;
      flex-direction: column;
      gap: 8px;
      flex: 1;
    }

    .infografis-date {
      color: #888;
      font-size: 14px;
    }

    .infografis-title {
      font-size: 16px;
      font-weight: bold;
      color: #002b6a;
      margin: 0;
      line-height: 1.4;
    }

    .infografis-desc {
      font-size: 15px;
      color: #555;
      line-height: 1.5;
    }

    .infografis-actions {
      display: flex;
      gap: 8px;
      margin-top: auto;
      padding-top: 6px;
    }

    .infografis-btn {
      display: inline-block;
      padding: 6px 12px;
      background-color: #034f84;
      color: white;
      text-decoration: none;
      font-size: 14px;
      border-radius: 4px;
      border: none;
      cursor: pointer;
    }

    .infografis-btn.unduh {
      background-color: #1e7e34;
    }

    .pagination {
      display: flex;
      justify-content: center;
      margin: 30px 0;
      flex-wrap: wrap;
    }

    .pagination a,
    .pagination span {
      display: inline-block;
      padding: 8px 14px;
      border: 1px solid #ddd;
      color: #034f84;
      text-decoration: none;
      font-size: 16px;
      margin-left: -1px;
      background-color: white;
    }

    .pagination a:hover {
      background-color: #f2f2f2;
    }

    .pagination .active {
      background-color: #034f84;
      color: white;
      border-color: #034f84;
      z-index: 1;
    }

    .pagination .disabled {
      color: #999;
      background-color: #f9f9f9;
      pointer-events: none;
    }

    .pagination .dots {
      pointer-events: none;
      color: #034f84;
    }

    .pagination a:first-child,
    .pagination span:first-child {
      border-top-left-radius: 4px;
      border-bottom-left-radius: 4px;
      border-top-right-radius: 4px;
      border-bottom-right-radius: 4px;
    }

    .modal-infografis-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.6);
      z-index: 1000;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .modal-infografis-box {
      background: #fff;
      width: 95%;
      max-width: 720px;
      max-height: 88vh;
      border-radius: 10px;
      overflow: auto;
      padding: 20px;
      position: relative;
    }

    .modal-infografis-close {
      position: sticky;
      top: 0;
      float: right;
      background: #034f84;
      color: #fff;
      border: none;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      font-size: 20px;
      cursor: pointer;
      line-height: 1;
    }

    .modal-infografis-box img {
      width: 100%;
      border-radius: 6px;
      margin: 10px 0;
    }

    .modal-infografis-box .modal-desc {
      font-size: 16px;
      color: #333;
      line-height: 1.7;
    }

    .modal-infografis-box .modal-desc ul {
      padding-left: 20px;
    }
  </style>
</head>

<body>
  <header>
    <a href="../../index.php" title="Kembali ke Home"><img src="https://ppid.bps.go.id/upload/img/logo_(1)_1643969039217.png" alt="Logo Web" onerror="this.src = '../../assets/img/logo_(1)_1643969039217.png'" /></a>
    <div class="judulweb">BADAN PUSAT STATISTIK<br />PROVINSI SUMATERA UTARA</div>
    <nav>
      <a href="../../index.php">Home</a>
      <div class="nav-dropdown">
        <button class="nav-dropbtn active">Produk ▾</button>
        <div class="nav-dropdown-content">
          <a href="page09A.php">Publikasi</a>
          <a href="berita.php">Berita Resmi Statistik</a>
          <a href="katalog.php">Statistik Berdasarkan Subjek</a>
          <a href="data_dinamis.php">Tabel Dinamis</a>
          <a href="exim.php">Data Ekspor Impor</a>
          <a href="pers.php">Berita dan Siaran Pers</a>
          <a href="infografis.php" class="current">Infografis</a>
          <a href="https://sensus.bps.go.id" target="_blank" rel="noopener">Data Sensus</a>
          <a href="https://direktori.web.bps.go.id" target="_blank" rel="noopener">Direktori</a>
          <a href="https://sirusa.web.bps.go.id/metadata" target="_blank" rel="noopener">Metadata</a>
        </div>
      </div>
      <?php if (isAdmin()): ?>
        <a href="page09C.php">Tambah Publikasi</a>
      <?php endif; ?>
      <a href="../galeri/page09G.php">Galeri Kegiatan</a>

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
            <a href="../auth/logout.php" class="btn-logout">logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="../auth/login.php">Login</a>
      <?php endif; ?>
    </nav>
  </header>

  <main>
    <h2 style="text-align: center; margin-bottom: 10px; color: #333">Infografis BPS Provinsi Sumatera Utara</h2>

    <form method="get" action="infografis.php" id="formCariInfografis" class="infografis-toolbar">
      <input type="text" id="cariInfografis" name="q" placeholder="Ketik kata kunci untuk mencari di semua halaman..."
        autocomplete="off" value="<?= htmlspecialchars($q) ?>" />
      <select id="subjekInfografis" name="subjek">
        <option value="">Semua Subjek</option>
        <?php foreach ($SUBJEK_LIST as $id => $nama): ?>
          <option value="<?= $id ?>" <?= ($subjek === (string) $id) ? 'selected' : '' ?>><?= htmlspecialchars($nama) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="infografis-btn">Cari</button>
      <?php if ($q !== '' || $subjek !== ''): ?>
        <a href="infografis.php" style="align-self:center; font-size:15px; color:#d9534f; font-weight:bold; text-decoration:none;">Reset &times;</a>
      <?php endif; ?>
    </form>
    <p class="infografis-status" id="infografisStatus">
      <?php if ($tampilOK && ($q !== '' || $subjek !== '')): ?>
        <?= (int) $totalHasil ?> hasil
        <?php if ($subjek !== ''): ?>
          pada subjek &ldquo;<?= htmlspecialchars($SUBJEK_LIST[$subjek]) ?>&rdquo;
        <?php endif; ?>
        <?php if ($q !== ''): ?>
          untuk &ldquo;<?= htmlspecialchars($q) ?>&rdquo;
        <?php endif; ?>
        (semua halaman).
      <?php endif; ?>
    </p>

    <div class="infografis-grid" id="infografisGrid">
      <?php if ($tampilOK && !empty($infoList)): ?>
        <?php foreach ($infoList as $idx => $info): ?>
          <?php
          $imgUrl = !empty($info['img']) ? $info['img'] : '../../assets/img/logo_(1)_1643969039217.png';
          $judul  = $info['title'] ?? '(Tanpa Judul)';
          $dlLink = $info['dl'] ?? '';
          $tanggal = formatTanggalId($info['date'] ?? '', $bulanId);
          // Cuplikan deskripsi: buang tag HTML, potong 160 karakter
          $descPlain = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($info['desc'] ?? ''))));
          if (mb_strlen($descPlain) > 160) {
            $descPlain = mb_substr($descPlain, 0, 160) . '...';
          }
          ?>
          <div class="infografis-card" data-judul="<?= htmlspecialchars(mb_strtolower($judul)) ?>">
            <img src="<?= htmlspecialchars($imgUrl) ?>" alt="Infografis BPS"
              loading="lazy" onclick="bukaInfografis(<?= (int) $idx ?>)"
              onerror="this.src='../../assets/img/logo_(1)_1643969039217.png'" />
            <div class="infografis-content">
              <div class="infografis-date"><?= htmlspecialchars($tanggal) ?></div>
              <h3 class="infografis-title"><?= htmlspecialchars($judul) ?></h3>
              <?php if ($descPlain !== ''): ?>
                <p class="infografis-desc"><?= htmlspecialchars($descPlain) ?></p>
              <?php endif; ?>
              <div class="infografis-actions">
                <button type="button" class="infografis-btn" onclick="bukaInfografis(<?= (int) $idx ?>)">Lihat &rarr;</button>
                <?php if ($dlLink !== ''): ?>
                  <a href="<?= htmlspecialchars($dlLink) ?>" target="_blank" rel="noopener" class="infografis-btn unduh">Unduh</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php elseif ($tampilOK): ?>
        <div style="grid-column: 1 / -1; text-align:center; padding: 20px;">
          <p style="color:#666; font-size:17px;">Tidak ada infografis yang cocok dengan filter ini. Silakan coba kata kunci atau subjek lain.</p>
        </div>
      <?php else: ?>
        <div style="grid-column: 1 / -1; text-align:center; padding: 20px;">
          <p style="color:red; font-weight:bold; font-size:18px;">Gagal memuat infografis dari API BPS</p>
          <p style="color:#666;">Pesan: <?= htmlspecialchars($errorMsg !== '' ? $errorMsg : 'Tidak ada respons') ?></p>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($tampilOK): ?>
      <?php
      $startPage = max(1, $page - 2);
      $endPage = min($totalPages, $page + 2);
      if ($startPage == 1) $endPage = min($totalPages, 5);
      if ($endPage == $totalPages) $startPage = max(1, $totalPages - 4);
      ?>
      <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?><?= $qParam ?>">&#10094;</a>
          <?php else: ?>
            <span class="disabled">&#10094;</span>
          <?php endif; ?>

          <?php if ($startPage > 1): ?>
            <a href="?page=1<?= $qParam ?>">1</a>
            <?php if ($startPage > 2): ?>
              <span class="dots">...</span>
            <?php endif; ?>
          <?php endif; ?>

          <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <a href="?page=<?= $i ?><?= $qParam ?>" class="<?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>

          <?php if ($endPage < $totalPages): ?>
            <?php if ($endPage < $totalPages - 1): ?>
              <span class="dots">...</span>
            <?php endif; ?>
            <a href="?page=<?= $totalPages ?><?= $qParam ?>"><?= $totalPages ?></a>
          <?php endif; ?>

          <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?><?= $qParam ?>">&#10095;</a>
          <?php else: ?>
            <span class="disabled">&#10095;</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>

  <!-- Modal detail infografis -->
  <div class="modal-infografis-overlay" id="modalInfografis" onclick="if(event.target===this) tutupInfografis()">
    <div class="modal-infografis-box">
      <button type="button" class="modal-infografis-close" onclick="tutupInfografis()">&times;</button>
      <h3 id="modalJudul" style="color:#002b6a; margin-right:40px;"></h3>
      <div id="modalTanggal" style="color:#888; font-size:14px;"></div>
      <img id="modalGambar" src="" alt="Infografis BPS" onerror="this.src='../../assets/img/logo_(1)_1643969039217.png'" />
      <div id="modalDesc" class="modal-desc"></div>
      <div style="margin-top:14px;">
        <a id="modalUnduh" href="#" target="_blank" rel="noopener" class="infografis-btn unduh" style="display:none;">Unduh Infografis</a>
      </div>
    </div>
  </div>

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

  <script>
    // Data mentah halaman ini untuk modal detail (di-escape aman via json_encode PHP)
    var DATA_INFOGRAFIS = <?= json_encode(
                            array_map(function ($it) {
                              return [
                                'title' => $it['title'] ?? '(Tanpa Judul)',
                                'img'   => $it['img'] ?? '',
                                'date'  => $it['date'] ?? '',
                                'desc'  => strip_tags((string) ($it['desc'] ?? ''), '<ul><li><p><br><b><i><strong><em>'),
                                'dl'    => $it['dl'] ?? '',
                              ];
                            }, $infoList),
                            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                          ) ?>;

    function bukaInfografis(idx) {
      var item = DATA_INFOGRAFIS[idx];
      if (!item) return;
      document.getElementById('modalJudul').textContent = item.title;
      document.getElementById('modalTanggal').textContent = item.date || '';
      var gbr = document.getElementById('modalGambar');
      gbr.src = item.img || '../../assets/img/logo_(1)_1643969039217.png';
      // desc sudah difilter aman di PHP, boleh dirender sebagai HTML.
      document.getElementById('modalDesc').innerHTML = item.desc || '<i>Tidak ada deskripsi.</i>';
      var unduh = document.getElementById('modalUnduh');
      if (item.dl) {
        unduh.href = item.dl;
        unduh.style.display = 'inline-block';
      } else {
        unduh.style.display = 'none';
      }
      document.getElementById('modalInfografis').style.display = 'flex';
    }

    function tutupInfografis() {
      document.getElementById('modalInfografis').style.display = 'none';
    }
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') tutupInfografis();
    });

    // Cari otomatis 700ms setelah berhenti mengetik (server-side, seluruh data).
    (function() {
      var input = document.getElementById('cariInfografis');
      var form = document.getElementById('formCariInfografis');
      var subjek = document.getElementById('subjekInfografis');
      if (!input || !form) return;
      var timer = null;
      var awal = input.value;
      input.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(function() {
          if (input.value !== awal) form.submit();
        }, 700);
      });
      if (subjek) {
        subjek.addEventListener('change', function() {
          form.submit();
        });
      }
      input.focus();
      input.setSelectionRange(input.value.length, input.value.length);
    })();
  </script>
</body>

</html>