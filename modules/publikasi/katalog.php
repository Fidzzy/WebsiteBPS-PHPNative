<?php
include '../../config/dbconn.php';
require_once '../../includes/auth.php';
// Halaman publik: katalog data bisa dilihat tanpa login.
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Katalog Data - BPS Sumut</title>
  <link rel="icon" href="../../assets/img/logo_(1)_1643969039217.png" type="image/png" />
  <link rel="stylesheet" href="../../assets/css/myCSS2.css" />
  <script src="../../assets/js/nav.js" defer></script>
  <style>
    .catalog-layout {
      display: flex;
      gap: 20px;
    }

    .catalog-sidebar {
      width: 250px;
      background: white;
      padding: 15px;
      border-radius: 8px;
      border: 1px solid #ddd;
    }

    .catalog-sidebar h4 {
      margin-top: 0;
      color: #002b6a;
    }

    .catalog-sidebar ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .catalog-sidebar ul li {
      margin-bottom: 10px;
    }

    .catalog-sidebar ul li a {
      color: #555;
      text-decoration: none;
      display: block;
      padding: 8px;
      border-radius: 4px;
    }

    .catalog-sidebar ul li a:hover,
    .catalog-sidebar ul li a.active {
      background: #f0f0f0;
      color: #034f84;
      font-weight: bold;
    }

    .catalog-content {
      flex: 1;
    }

    .table-container {
      background: white;
      padding: 20px;
      border-radius: 8px;
      border: 1px solid #ddd;
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
          <a href="katalog.php" class="current">Statistik Berdasarkan Subjek</a>
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
    <h2 style="text-align: center; margin-bottom: 20px; color: #333">Katalog Data Statistik</h2>

    <div class="catalog-layout">
      <div class="catalog-sidebar">
        <h4>Subjek Statistik</h4>
        <ul id="subject-list">
          <li><a href="#" class="active" onclick="fetchData(this, 'penduduk', 'Sosial &amp; Kependudukan'); return false;">Sosial &amp; Kependudukan</a></li>
          <li><a href="#" onclick="fetchData(this, 'ekonomi', 'Ekonomi &amp; Perdagangan'); return false;">Ekonomi &amp; Perdagangan</a></li>
          <li><a href="#" onclick="fetchData(this, 'pertanian', 'Pertanian &amp; Pertambangan'); return false;">Pertanian &amp; Pertambangan</a></li>
          <li><a href="#" onclick="fetchData(this, 'inflasi', 'Inflasi &amp; Harga'); return false;">Inflasi &amp; Harga</a></li>
          <li><a href="#" onclick="fetchData(this, 'kemiskinan', 'Kemiskinan'); return false;">Kemiskinan</a></li>
        </ul>
      </div>

      <div class="catalog-content">
        <div class="table-container">
          <h3 id="table-title">Memuat data API...</h3>
          <p id="table-status" style="color:#888; font-size:15px; margin-top:-8px;"></p>
          <table class="data-tabel" id="data-table" style="display:none; width: 100%;">
            <thead>
              <tr>
                <th>Indikator</th>
                <th>Nilai Terbaru</th>
                <th>Satuan</th>
                <th>Tahun</th>
              </tr>
            </thead>
            <tbody id="table-body">
            </tbody>
          </table>
        </div>
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

  <script>
    // Sebelumnya fungsi ini menampilkan data contoh statis. Sekarang
    // benar-benar mengambil data dari BPS Web API lewat proxy PHP
    // (bps_katalog_data.php) API key tetap di server, tidak pernah
    // terekspos ke browser.
    //
    // Elemen tabel dibangun lewat createElement()/textContent (bukan
    // menempel string HTML ke innerHTML) supaya judul indikator dari BPS
    // tidak pernah bisa ditafsirkan sebagai HTML/script, konsisten dengan
    // perbaikan yang sama di assets/js/page11A_suggestion.js.
    function fetchData(linkEl, keyword, displayLabel) {
      const dataTable = document.getElementById('data-table');
      const tableTitle = document.getElementById('table-title');
      const tableBody = document.getElementById('table-body');
      const tableStatus = document.getElementById('table-status');

      dataTable.style.display = 'none';
      tableStatus.textContent = '';
      tableTitle.textContent = 'Memuat data API...';

      // Update active sidebar link. linkEl dilewatkan eksplisit lewat
      // parameter (bukan lewat variabel global 'event') karena fungsi ini
      // juga dipanggil otomatis dari window.onload di bawah, di luar
      // konteks klik asli memakai 'event' global di situasi itu bisa
      // merujuk ke objek yang tidak punya classList sama sekali, yang
      // sebelumnya melempar error JS SEBELUM sempat sampai ke fetch(),
      // sehingga halaman macet permanen di "Memuat data API...".
      const links = document.querySelectorAll('#subject-list a');
      links.forEach(l => l.classList.remove('active'));
      if (linkEl) {
        linkEl.classList.add('active');
      }

      fetch('bps_katalog_data.php?subject=' + encodeURIComponent(keyword))
        .then(res => res.json())
        .then(json => {
          tableTitle.textContent = 'Data: ' + displayLabel;
          tableBody.innerHTML = ''; // aman: cuma mengosongkan, bukan menyisipkan data

          if (!json.success) {
            tableStatus.textContent = 'Gagal memuat data: ' + json.message;
            return;
          }

          if (!json.data || json.data.length === 0) {
            tableStatus.textContent = json.message || 'Tidak ada data untuk subjek ini.';
            return;
          }

          tableStatus.textContent = json.message || '';
          dataTable.style.display = 'table';

          json.data.forEach(row => {
            const tr = document.createElement('tr');

            const tdTitle = document.createElement('td');
            tdTitle.textContent = row.title;

            const tdValue = document.createElement('td');
            tdValue.textContent = row.value;

            const tdUnit = document.createElement('td');
            tdUnit.textContent = row.unit || '-';

            const tdYear = document.createElement('td');
            tdYear.textContent = row.year;

            tr.append(tdTitle, tdValue, tdUnit, tdYear);
            tableBody.appendChild(tr);
          });
        })
        .catch(() => {
          tableTitle.textContent = 'Data: ' + displayLabel;
          tableStatus.textContent = 'Gagal terhubung ke server. Coba lagi nanti.';
        });
    }

    window.onload = () => {
      const firstLink = document.querySelector('#subject-list a');
      fetchData(firstLink, 'penduduk', 'Sosial & Kependudukan');
    };
  </script>
</body>

</html>