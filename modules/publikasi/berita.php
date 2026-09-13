<?php
include '../../config/dbconn.php';
require_once '../../config/bps_api.php';
require_once '../../includes/auth.php';
// Halaman publik: berita BPS bisa dilihat tanpa login.

// Halaman minimal 1, dibatasi biar tidak sembarangan dikirim ke API BPS
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

// Kata kunci pencarian (maks 100 karakter): dicari server-side via API,
// mencakup seluruh data, bukan cuma halaman aktif.
$q = mb_substr(trim($_GET['q'] ?? ''), 0, 100);

// Filter bulan & tahun rilis BRS (parameter month & year API).
$bulan = trim($_GET['bulan'] ?? '');
if (!ctype_digit($bulan) || (int) $bulan < 1 || (int) $bulan > 12) {
  $bulan = '';
} else {
  $bulan = (string) (int) $bulan;
}
$tahun = trim($_GET['tahun'] ?? '');
$tahunMaks = (int) date('Y');
if (!ctype_digit($tahun) || strlen($tahun) !== 4 || (int) $tahun < 2000 || (int) $tahun > $tahunMaks) {
  $tahun = '';
}
$daftarBulan = [
  '1' => 'Januari',
  '2' => 'Februari',
  '3' => 'Maret',
  '4' => 'April',
  '5' => 'Mei',
  '6' => 'Juni',
  '7' => 'Juli',
  '8' => 'Agustus',
  '9' => 'September',
  '10' => 'Oktober',
  '11' => 'November',
  '12' => 'Desember',
];
$daftarTahun = range($tahunMaks, $tahunMaks - 10);

$url = BPS_API_BASE . "list/"
  . "?model=pressrelease"
  . "&domain=" . urlencode(BPS_DOMAIN)
  . "&lang=ind"
  . "&key=" . urlencode(BPS_API_KEY)
  . "&page=" . $page;
if ($q !== '') {
  $url .= "&keyword=" . urlencode($q);
}
if ($bulan !== '') {
  $url .= "&month=" . urlencode($bulan);
}
if ($tahun !== '') {
  $url .= "&year=" . urlencode($tahun);
}

// Suffix query string agar link paginasi tetap membawa filter yang aktif
$qParam = '';
if ($q !== '') {
  $qParam .= '&q=' . urlencode($q);
}
if ($bulan !== '') {
  $qParam .= '&bulan=' . urlencode($bulan);
}
if ($tahun !== '') {
  $qParam .= '&tahun=' . urlencode($tahun);
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Timeout 15 detik
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
// Verifikasi SSL diaktifkan kembali sebelumnya di-nonaktifkan sehingga
// rentan man-in-the-middle attack saat menghubungi API BPS.
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);
curl_close($ch);

$bpsData = json_decode($response, true);
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Berita Resmi Statistik (BRS) - BPS Sumut</title>
  <link rel="icon" href="../../assets/img/logo_(1)_1643969039217.png" type="image/png" />
  <link rel="stylesheet" href="../../assets/css/myCSS2.css" />
  <script src="../../assets/js/nav.js" defer></script>
  <style>
    .news-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }

    .news-card {
      background: white;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      border: 1px solid #eee;
      transition: transform 0.2s;
    }

    .news-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
    }

    .news-card img {
      width: 100%;
      height: 160px;
      object-fit: cover;
      background: #f0f0f0;
    }

    .news-content {
      padding: 15px;
    }

    .news-date {
      color: #888;
      font-size: 14px;
      margin-bottom: 8px;
    }

    .news-title {
      font-size: 18px;
      font-weight: bold;
      color: #002b6a;
      margin: 0 0 10px 0;
      line-height: 1.4;
    }

    .news-btn {
      display: inline-block;
      padding: 6px 12px;
      background-color: #034f84;
      color: white;
      text-decoration: none;
      font-size: 14px;
      border-radius: 4px;
    }

    .pagination {
      display: flex;
      justify-content: center;
      margin: 30px 0;
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
          <a href="berita.php" class="current">Berita Resmi Statistik</a>
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
    <h2 style="text-align: center; margin-bottom: 10px; color: #333">Berita Resmi Statistik Terkini</h2>

    <form method="get" action="berita.php" id="formCariBerita" style="display:flex; gap:8px; justify-content:center; margin:15px 0 5px 0; flex-wrap:wrap;">
      <input
        type="text"
        id="cariBerita"
        name="q"
        placeholder="Ketik kata kunci untuk mencari di semua halaman..."
        autocomplete="off"
        value="<?= htmlspecialchars($q) ?>"
        style="width:min(320px, 90%); padding:10px 14px; border:1px solid #ccc; border-radius:6px; font-size:16px;" />
      <select id="bulanBerita" name="bulan" style="padding:10px 12px; border:1px solid #ccc; border-radius:6px; font-size:16px; background:#fff; max-width:90%;">
        <option value="">Semua Bulan</option>
        <?php foreach ($daftarBulan as $num => $nama): ?>
          <option value="<?= $num ?>" <?= ($bulan === (string) $num) ? 'selected' : '' ?>><?= htmlspecialchars($nama) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="tahunBerita" name="tahun" style="padding:10px 12px; border:1px solid #ccc; border-radius:6px; font-size:16px; background:#fff; max-width:90%;">
        <option value="">Semua Tahun</option>
        <?php foreach ($daftarTahun as $th): ?>
          <option value="<?= $th ?>" <?= ($tahun === (string) $th) ? 'selected' : '' ?>><?= $th ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" style="background-color:#034f84; color:#fff; border:none; padding:0 18px; border-radius:6px; cursor:pointer; font-weight:bold;">Cari</button>
      <?php if ($q !== '' || $bulan !== '' || $tahun !== ''): ?>
        <a href="berita.php" style="align-self:center; font-size:15px; color:#d9534f; font-weight:bold; text-decoration:none;">Reset &times;</a>
      <?php endif; ?>
    </form>
    <p style="text-align:center; color:#888; font-size:15px; margin-bottom:5px;" id="statusCariBerita">
      <?php if (($q !== '' || $bulan !== '' || $tahun !== '') && $bpsData && ($bpsData['status'] ?? '') === 'OK'): ?>
        <?= (int) ($bpsData['data'][0]['total'] ?? 0) ?> hasil
        <?php if ($bulan !== '' || $tahun !== ''): ?>
          periode <?= $bulan !== '' ? htmlspecialchars($daftarBulan[$bulan]) : '' ?><?= ($bulan !== '' && $tahun !== '') ? ' ' : '' ?><?= $tahun !== '' ? htmlspecialchars($tahun) : '' ?>
        <?php endif; ?>
        <?php if ($q !== ''): ?>
          untuk &ldquo;<?= htmlspecialchars($q) ?>&rdquo;
        <?php endif; ?>
        (semua halaman).
      <?php endif; ?>
    </p>

    <div class="news-container">
      <?php if ($bpsData && isset($bpsData['status']) && $bpsData['status'] === 'OK'): ?>
        <?php
        // data[0] = info paginasi, data[1] = array berita
        $newsList = $bpsData['data'][1] ?? [];
        ?>

        <?php foreach ($newsList as $news): ?>
          <?php
          // Nama kolom resmi dari API BPS
          $imgUrl  = !empty($news['thumbnail']) ? $news['thumbnail'] : 'https://ppid.bps.go.id/upload/img/logo_(1)_1643969039217.png';
          $judul   = $news['title'] ?? '(Tanpa Judul)';
          $pdfLink = $news['pdf'] ?? '#';
          $subjek  = $news['subj'] ?? '';

          // Format tanggal: dari "2026 08 31" menjadi "31 Agustus 2026"
          $rawDate = $news['rl_date'] ?? '';
          $tanggal = $rawDate;
          if ($rawDate !== '') {
            $dt = DateTime::createFromFormat('Y m d', $rawDate);
            if ($dt) {
              $bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
              $tanggal = $dt->format('d') . ' ' . $bulan[(int)$dt->format('m') - 1] . ' ' . $dt->format('Y');
            }
          }
          ?>
          <div class="news-card">
            <img src="<?= htmlspecialchars($imgUrl) ?>" alt="Thumbnail BRS" onerror="this.src='../../assets/img/logo_(1)_1643969039217.png'">
            <div class="news-content">
              <div class="news-date"> <?= htmlspecialchars($tanggal) ?></div>
              <h3 class="news-title" style="font-size:16px;"><?= htmlspecialchars($judul) ?></h3>
              <?php if ($subjek): ?>
                <span style="display:inline-block; background:#e8f0fe; color:#034f84; padding:3px 8px; border-radius:3px; font-size:13px; margin-bottom:8px;"><?= htmlspecialchars($subjek) ?></span>
              <?php endif; ?>
              <br>
              <a href="<?= htmlspecialchars($pdfLink) ?>" target="_blank" class="news-btn">Baca PDF &rarr;</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="width:100%; text-align:center; padding: 20px;">
          <p style="color:red; font-weight:bold; font-size:18px;">
            Gagal memuat berita dari API BPS
          </p>
          <p style="color:#666;">Pesan: <?= htmlspecialchars($bpsData['message'] ?? 'Tidak ada respons') ?></p>
        </div>
        <?php endif; ?>
        </div>

        <?php if ($bpsData && isset($bpsData['status']) && $bpsData['status'] === 'OK'): ?>
          <?php
          // Ambil total halaman dari API BPS (data[0] menyimpan info paginasi)
          $totalPages = $bpsData['data'][0]['pages'] ?? 1;

          // Hitung jendela halaman yang ditampilkan agar sesuai gambar (1 2 3 4 5 ... 100)
          $startPage = max(1, $page - 2);
          $endPage = min($totalPages, $page + 2);

          if ($startPage == 1) {
            $endPage = min($totalPages, 5);
          }
          if ($endPage == $totalPages) {
            $startPage = max(1, $totalPages - 4);
          }
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
                <?php $activeClass = ($i == $page) ? 'active' : ''; ?>
                <a href="?page=<?= $i ?><?= $qParam ?>" class="<?= $activeClass ?>"><?= $i ?></a>
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
    // Cari otomatis 700ms setelah berhenti mengetik (server-side, seluruh data).
    (function() {
      var input = document.getElementById('cariBerita');
      var form = document.getElementById('formCariBerita');
      if (!input || !form) return;
      var timer = null;
      var awal = input.value;
      input.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(function() {
          if (input.value !== awal) form.submit();
        }, 700);
      });
      ['bulanBerita', 'tahunBerita'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) {
          el.addEventListener('change', function() {
            form.submit();
          });
        }
      });
      // Kembalikan fokus ke kolom cari setelah reload.
      input.focus();
      input.setSelectionRange(input.value.length, input.value.length);
    })();
  </script>
</body>

</html>