<?php
include '../../config/dbconn.php';
require_once '../../includes/auth.php';
require_once '../../includes/bps_publication.php';
// Halaman publik: daftar publikasi bisa dilihat tanpa login.
// Sumber: Web API BPS (cache 6 jam) + entri admin tabel `publikasi`.

// Baca parameter filter dari query string (?q=&tahun=&urutan=)
$q      = trim($_GET['q'] ?? '');
$tahun  = trim($_GET['tahun'] ?? '');
$urutan = $_GET['urutan'] ?? 'terbaru';

if (!in_array($urutan, ['terbaru', 'terlama', 'terpopuler'], true)) {
  $urutan = 'terbaru';
}

// Pagination server-side: cuma 5/10/20 baris per halaman.
$perPageAllowed = [5, 10, 20];
$perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
if (!in_array($perPage, $perPageAllowed, true)) {
  $perPage = 10;
}
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

// Data API BPS (keyword dicari di sisi API).
$apiResult = bpsFetchPublicationsAll(BPS_DOMAIN, $q);
$apiPubs   = $apiResult['ok'] ? $apiResult['items'] : [];
$apiWarning = $apiResult['error'];
// Data admin dari database lokal.
$where  = [];
$params = [];

if ($q !== '') {
  $where[] = 'judul LIKE :q';
  // Escape wildcard LIKE (%, _, \) supaya keyword user dicari harfiah.
  $params[':q'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
}

$sql = 'SELECT * FROM publikasi';
if (!empty($where)) {
  $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY tanggal_rilis DESC';

/** @var PDO $pdo */
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$manualPubs = array_map('bpsPubNormalizeManualRow', $stmt->fetchAll());

// Gabung kedua sumber, lalu filter tahun + urutkan.
$semua = array_merge($apiPubs, $manualPubs);

// Saring judul secara ketat di sisi server: API BPS mencocokkan keyword
// secara longgar (bisa mengembalikan judul yang tidak mengandung kata
// kunci sama sekali), jadi pastikan tiap kata kunci benar-benar ada di
// judul (tak peduli huruf besar/kecil). Tanpa ini hasil tidak sesuai
// dengan keyword yang diketik user.
if ($q !== '') {
  $kataKunci = preg_split('/\s+/', mb_strtolower($q), -1, PREG_SPLIT_NO_EMPTY);
  $semua = array_values(array_filter($semua, function ($item) use ($kataKunci) {
    $judul = mb_strtolower((string) ($item['title'] ?? ''));
    foreach ($kataKunci as $kata) {
      if (mb_strpos($judul, $kata) === false) {
        return false;
      }
    }
    return true;
  }));
}

// Daftar tahun dropdown diambil sebelum filter dipasang.
$tahunSet = [];
foreach ($semua as $item) {
  if (!empty($item['year']) && ctype_digit((string) $item['year'])) {
    $tahunSet[(string) $item['year']] = true;
  }
}
$tahunList = array_keys($tahunSet);
rsort($tahunList);

if ($tahun !== '' && ctype_digit($tahun)) {
  $semua = array_values(array_filter($semua, fn($item) => ($item['year'] ?? '') === $tahun));
}

// Pembanding tanggal (Y-m-d bisa dibanding leksikografis).
$cmpTanggal = function ($a, $b) {
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
  return strcmp($da, $db);
};

switch ($urutan) {
  case 'terlama':
    usort($semua, $cmpTanggal);
    break;
  case 'terpopuler':
    // Urut per jumlah dilihat; entri API tanpa counter tampil setelahnya.
    usort($semua, function ($a, $b) use ($cmpTanggal) {
      $va = ($a['source'] ?? '') === 'Manual' ? (int) ($a['dilihat'] ?? 0) : 0;
      $vb = ($b['source'] ?? '') === 'Manual' ? (int) ($b['dilihat'] ?? 0) : 0;
      if ($va === $vb) {
        return -$cmpTanggal($a, $b);
      }
      return $vb <=> $va;
    });
    break;
  default: // terbaru
    usort($semua, fn($a, $b) => -$cmpTanggal($a, $b));
    break;
}

$result = $semua;

// Potong sesuai halaman aktif.
$totalItems = count($result);
$totalPages = max(1, (int) ceil($totalItems / $perPage));
if ($page > $totalPages) {
  $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$pageItems = array_slice($result, $offset, $perPage);
$showFrom = $totalItems === 0 ? 0 : $offset + 1;
$showTo = min($offset + $perPage, $totalItems);

$baseParams = ['urutan' => $urutan, 'per_page' => $perPage];
if ($q !== '') {
  $baseParams['q'] = $q;
}
if ($tahun !== '' && ctype_digit($tahun)) {
  $baseParams['tahun'] = $tahun;
}
$pageUrl = function ($p) use ($baseParams) {
  return 'page09A.php?' . http_build_query($baseParams + ['page' => $p]);
};

// Jendela nomor halaman (1 2 3 4 5 ... 100), sama seperti halaman Berita.
$startPage = max(1, $page - 2);
$endPage = min($totalPages, $page + 2);
if ($startPage === 1) {
  $endPage = min($totalPages, 5);
}
if ($endPage === $totalPages) {
  $startPage = max(1, $totalPages - 4);
}
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Daftar Publikasi BPS Sumatera Utara</title>
  <link rel="icon" href="../../assets/img/logo_(1)_1643969039217.png" type="image/png" />
  <link rel="stylesheet" href="../../assets/css/myCSS2.css" />
  <script src="../../assets/js/nav.js" defer></script>
</head>

<body>
  <header>
    <a href="../../index.php" title="Kembali ke Home"><img
      src="https://ppid.bps.go.id/upload/img/logo_(1)_1643969039217.png"
      alt="Logo Web"
      onerror="this.src = '../../assets/img/logo_(1)_1643969039217.png'" /></a>
    <div class="judulweb">
      BADAN PUSAT STATISTIK<br />
      PROVINSI SUMATERA UTARA
    </div>
    <nav>
      <a href="../../index.php">Home</a>

      <div class="nav-dropdown">
        <button class="nav-dropbtn active">Produk ▾</button>
        <div class="nav-dropdown-content">
          <a href="page09A.php" class="current">Publikasi</a>
          <a href="berita.php">Berita Resmi Statistik</a>
          <a href="katalog.php">Statistik Berdasarkan Subjek</a>
          <a href="data_dinamis.php">Tabel Dinamis</a>
          <a href="exim.php">Data Ekspor Impor</a>
          <a href="pers.php">Berita dan Siaran Pers</a>
          <a href="infografis.php">Infografis</a>
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
    <h2 style="text-align: center; margin-bottom: 20px; color: #333">
      Daftar Publikasi BPS Provinsi Sumatera Utara
    </h2>

    <div class="publikasi-layout">
      <aside class="filter-panel">
        <h3 class="filter-panel-title">Filter Publikasi</h3>
        <div class="filter-card">
          <form method="get" action="page09A.php" id="filterForm">
            <label class="filter-label" for="q">Kata Kunci</label>
            <input
              type="text"
              id="q"
              name="q"
              placeholder="Ketik judul, otomatis mencari..."
              autocomplete="off"
              value="<?= htmlspecialchars($q) ?>" />
            <small id="filterStatus" class="filter-status" aria-live="polite"></small>

            <label class="filter-label" for="tahun">Tahun</label>
            <select id="tahun" name="tahun">
              <option value="">Semua Tahun</option>
              <?php foreach ($tahunList as $t): ?>
                <option value="<?= (int) $t ?>" <?= ($tahun !== '' && (int) $tahun === (int) $t) ? 'selected' : '' ?>>
                  <?= (int) $t ?>
                </option>
              <?php endforeach; ?>
            </select>

            <label class="filter-label" for="urutan">Urutkan Berdasarkan</label>
            <select id="urutan" name="urutan">
              <option value="terbaru" <?= $urutan === 'terbaru' ? 'selected' : '' ?>>Terbaru</option>
              <option value="terlama" <?= $urutan === 'terlama' ? 'selected' : '' ?>>Terlama</option>
              <option value="terpopuler" <?= $urutan === 'terpopuler' ? 'selected' : '' ?>>Terpopuler</option>
            </select>

            <label class="filter-label" for="per_page">Tampilkan per Halaman</label>
            <select id="per_page" name="per_page">
              <?php foreach ($perPageAllowed as $opsi): ?>
                <option value="<?= $opsi ?>" <?= $perPage === $opsi ? 'selected' : '' ?>><?= $opsi ?> publikasi</option>
              <?php endforeach; ?>
            </select>

            <button type="submit" class="filter-submit">Tampilkan</button>
          </form>
        </div>
      </aside>

      <div class="publikasi-content" id="hasilPublikasi">
        <?php if ($apiWarning !== ''): ?>
          <p class="api-warning">Peringatan: <?= htmlspecialchars($apiWarning) ?> Menampilkan data yang tersimpan lokal.</p>
        <?php endif; ?>
        <?php if (empty($result)): ?>
          <p class="hasil-kosong">Tidak ada publikasi yang cocok dengan filter ini.</p>
        <?php else: ?>
          <p class="hasil-count">
            Menampilkan <strong><?= number_format($showFrom, 0, ',', '.') ?>–<?= number_format($showTo, 0, ',', '.') ?></strong>
            dari <strong><?= number_format($totalItems, 0, ',', '.') ?> publikasi</strong>
          </p>
          <div class="tabel-publikasi">
            <table class="data-tabel" id="tabelPublikasi">
                <tr>
                  <th>Judul</th>
                  <th>Tanggal Rilis</th>
                  <?php if (isAdmin()): ?>
                    <th width="70">Dilihat</th>
                  <?php endif; ?>
                  <th width="80">Sampul</th>
                <?php if (isAdmin()): ?>
                  <th width="80">Aksi</th>
                <?php endif; ?>
              </tr>
              <?php foreach ($pageItems as $row): ?>
                <?php $isManual = ($row['source'] ?? '') === 'Manual'; ?>
                <tr>
                  <td>
                    <?php if ($isManual): ?>
                      <a href="<?= htmlspecialchars($row['pdf'] ?: '#') ?>" target="_blank" class="judul-link" data-no="<?= (int) ($row['no'] ?? 0) ?>" style="color: #034f84; text-decoration: none; font-weight: bold;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'"><?= htmlspecialchars($row['title']) ?></a>
                    <?php else: ?>
                      <a href="<?= htmlspecialchars($row['pdf'] ?: 'https://sumut.bps.go.id/id/publication') ?>" target="_blank" rel="noopener" style="color: #034f84; text-decoration: none; font-weight: bold;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'"><?= htmlspecialchars($row['title']) ?></a>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($row['rl_date'] !== '' ? $row['rl_date'] : '-') ?></td>
                  <?php if (isAdmin()): ?>
                    <td align="center"><?= $isManual ? (int) ($row['dilihat'] ?? 0) : '<span style="color:#aaa;">–</span>' ?></td>
                  <?php endif; ?>
                  <td align="center">
                    <?php if ($isManual): ?>
                      <?php if (!empty($row['cover_local'])): ?>
                        <img src="../../assets/img/<?= htmlspecialchars($row['cover_local']) ?>" alt="Sampul" width="70" loading="lazy" onerror="this.style.display='none'" />
                      <?php else: ?>
                        <span style="color:#aaa;">–</span>
                      <?php endif; ?>
                    <?php elseif (!empty($row['cover'])): ?>
                      <img src="<?= htmlspecialchars($row['cover']) ?>" alt="Sampul" width="70" loading="lazy" referrerpolicy="no-referrer" onerror="this.style.display='none'" />
                    <?php else: ?>
                      <span style="color:#aaa;">–</span>
                    <?php endif; ?>
                  </td>
                  <?php if (isAdmin()): ?>
                    <td align="center">
                      <?php if ($isManual): ?>
                        <div style="display:grid; justify-content:center; gap:15px;">
                          <a href="page09E.php?no=<?= urlencode((string) ($row['no'] ?? '')) ?>&judul=<?= urlencode($row['title']) ?>&tanggal=<?= urlencode($row['rl_date']) ?>&link=<?= urlencode($row['pdf']) ?>&sampul=<?= urlencode($row['cover_local'] ?? '') ?>" style="display:inline-flex; align-items:center; gap:5px; text-decoration:none; color:#034f84; font-weight:bold;">
                            <img src="../../assets/img/edit.png" style="width:15px;height:15px;" alt="Edit" /> Edit
                          </a>
                          <form method="post" action="page09F.php" onsubmit="return confirm('Yakin ingin menghapus?');" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>" />
                            <input type="hidden" name="no" value="<?= htmlspecialchars((string) ($row['no'] ?? '')) ?>" />
                            <input type="hidden" name="sampul" value="<?= htmlspecialchars($row['cover_local'] ?? '') ?>" />
                            <button type="submit" style="display:inline-flex; align-items:center; gap:5px; background:none; border:none; padding:0; cursor:pointer; text-decoration:none; color:#d9534f; font-weight:bold; font:inherit;">
                              <img src="../../assets/img/delete.png" style="width:15px;height:15px;" alt="Hapus" /> Hapus
                            </button>
                          </form>
                        </div>
                      <?php else: ?>
                        <span style="color:#aaa;">–</span>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </table>
          </div>

          <?php if ($totalPages > 1): ?>
            <div class="pagination">
              <?php if ($page > 1): ?>
                <a href="<?= htmlspecialchars($pageUrl($page - 1)) ?>">&#10094;</a>
              <?php else: ?>
                <span class="disabled">&#10094;</span>
              <?php endif; ?>

              <?php if ($startPage > 1): ?>
                <a href="<?= htmlspecialchars($pageUrl(1)) ?>">1</a>
                <?php if ($startPage > 2): ?>
                  <span class="dots">...</span>
                <?php endif; ?>
              <?php endif; ?>

              <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <a href="<?= htmlspecialchars($pageUrl($i)) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
              <?php endfor; ?>

              <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                  <span class="dots">...</span>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($pageUrl($totalPages)) ?>"><?= $totalPages ?></a>
              <?php endif; ?>

              <?php if ($page < $totalPages): ?>
                <a href="<?= htmlspecialchars($pageUrl($page + 1)) ?>">&#10095;</a>
              <?php else: ?>
                <span class="disabled">&#10095;</span>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>


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
  <script src="../../assets/js/validasiForm.js"></script>
  <script>
    // Pencatat klik judul (counter dilihat) via delegasi agar tetap
    // berfungsi setelah hasil filter dimuat ulang tanpa reload halaman.
    document.addEventListener('click', function(e) {
      var link = e.target.closest ? e.target.closest('.judul-link') : null;
      if (!link) return;
      var no = link.getAttribute('data-no');
      if (!no) return;
      fetch('track_view.php?no=' + encodeURIComponent(no), {
        method: 'POST',
        keepalive: true
      }).catch(function() {});
    });

    // Filter publikasi dinamis: mengetik keyword langsung mencari judul
    // yang sesuai (server-side, seluruh data API + database), tanpa harus
    // menekan tombol "Tampilkan". Dropdown dan paginasi juga dimuat tanpa
    // reload. Tanpa JS / bila fetch gagal, form tetap submit biasa (GET).
    (function() {
      var form = document.getElementById('filterForm');
      var inputQ = document.getElementById('q');
      var selTahun = document.getElementById('tahun');
      var selUrutan = document.getElementById('urutan');
      var selPerPage = document.getElementById('per_page');
      var status = document.getElementById('filterStatus');
      var hasil = document.getElementById('hasilPublikasi');
      if (!form || !inputQ || !hasil) return;

      var DEBOUNCE_MS = 400;
      var timer = null;
      var pengendali = null;

      function bangunParams(page) {
        var p = new URLSearchParams();
        var q = inputQ.value.trim();
        if (q !== '') p.set('q', q);
        if (selTahun && selTahun.value !== '') p.set('tahun', selTahun.value);
        if (selUrutan && selUrutan.value !== '') p.set('urutan', selUrutan.value);
        if (selPerPage && selPerPage.value !== '') p.set('per_page', selPerPage.value);
        p.set('page', String(page || 1));
        return p;
      }

      function setStatus(pesan, isError) {
        if (!status) return;
        status.textContent = pesan || '';
        status.classList.toggle('is-error', !!isError);
      }

      function selesaiLoading() {
        hasil.removeAttribute('aria-busy');
        hasil.classList.remove('is-loading');
      }

      function muatHasil(page, fallbackSubmit, fallbackUrl) {
        var params = bangunParams(page);
        if (pengendali && pengendali.abort) pengendali.abort();
        pengendali = ('AbortController' in window) ? new AbortController() : null;
        hasil.setAttribute('aria-busy', 'true');
        hasil.classList.add('is-loading');
        setStatus('Mencari...', false);
        fetch('page09A.php?' + params.toString(), {
          signal: pengendali ? pengendali.signal : undefined,
          credentials: 'same-origin'
        }).then(function(res) {
          if (!res.ok) throw new Error('HTTP ' + res.status);
          return res.text();
        }).then(function(html) {
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var baru = doc.getElementById('hasilPublikasi');
          if (!baru) throw new Error('fragmen hasil tidak ditemukan');
          hasil.innerHTML = baru.innerHTML;
          // Daftar tahun mengikuti keyword, jadi segarkan opsinya juga.
          var tahunBaru = doc.getElementById('tahun');
          if (tahunBaru && selTahun) {
            var dipilih = selTahun.value;
            selTahun.innerHTML = tahunBaru.innerHTML;
            var ada = Array.prototype.some.call(selTahun.options, function(o) {
              return o.value === dipilih;
            });
            selTahun.value = ada ? dipilih : '';
          }
          history.replaceState(null, '', 'page09A.php?' + params.toString());
          setStatus('', false);
          selesaiLoading();
        }, function(err) {
          if (err && err.name === 'AbortError') return;
          selesaiLoading();
          if (fallbackSubmit) {
            form.submit();
            return;
          }
          if (fallbackUrl) {
            window.location.href = fallbackUrl;
            return;
          }
          setStatus('Gagal memuat hasil. Periksa koneksi lalu coba lagi.', true);
        });
      }

      // Keyword: cari otomatis setelah berhenti mengetik.
      inputQ.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(function() {
          muatHasil(1, false, null);
        }, DEBOUNCE_MS);
      });

      // Dropdown: langsung terapkan tanpa reload.
      ['tahun', 'urutan', 'per_page'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) {
          el.addEventListener('change', function() {
            clearTimeout(timer);
            muatHasil(1, true, null);
          });
        }
      });

      // Tombol "Tampilkan" / Enter: muat via fetch, fallback submit biasa.
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        clearTimeout(timer);
        muatHasil(1, true, null);
      });

      // Paginasi hasil: muat halaman tanpa reload.
      hasil.addEventListener('click', function(e) {
        var a = e.target.closest ? e.target.closest('.pagination a') : null;
        if (!a || !a.href) return;
        e.preventDefault();
        var u;
        try {
          u = new URL(a.href, window.location.href);
        } catch (err) {
          window.location.href = a.href;
          return;
        }
        var pg = parseInt(u.searchParams.get('page') || '1', 10);
        if (isNaN(pg) || pg < 1) pg = 1;
        muatHasil(pg, false, a.href);
      });
    })();
  </script>
</body>

</html>