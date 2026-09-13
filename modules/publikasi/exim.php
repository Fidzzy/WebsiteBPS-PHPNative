<?php

/**
 * Halaman publik: Data Ekspor-Impor meniru sumut.bps.go.id/exim.
 *
 * Sumber: BPS Web API (endpoint dataexim, bukan tabel dinamis):
 *   v1/api/dataexim/sumber/{s}/kodehs/{hs}/jenishs/{j}/tahun/{t}/periode/{p}/key/{key}/
 * Sumber selalu diambil DUA-DUANYA (1 = Ekspor dan 2 = Impor) lewat
 *     mode gabungan (sumber=3) di bps_exim_api.php, lalu digabung client-side.
 * periode selalu 1 = Bulanan; jenishs selalu 1 = HS 2 digit.
 *
 * Dua tabel permanen gabungan (kolom Nilai Ekspor | Berat Ekspor |
 * Nilai Impor | Berat Impor), diagregat nasional (seluruh pelabuhan
 * dan negara digabung, kecuali disaring lewat filter):
 *   1. Nasional bulanan tahun terpilih (default tahun berjalan).
 *   2. Rincian HS 2 digit bulan terpilih (default bulan terbaru yang
 *      tersedia, mis. Juli 2026).
 * Filter Tahun/Pelabuhan/Negara/Bulan menyaring kedua tabel
 * client-side tanpa request baru (kecuali Tahun yang ambil ulang).
 * Hasil API dilindungi dari race antar request; unduhan CSV/XLSX mengikuti
 * filter yang tampil; tersedia pencarian dan pagination.
 *
 * Request dilakukan server-side via bps_exim_api.php (API key aman).
 *
 * Halaman ini murni API-driven (tidak pakai database lokal), jadi sengaja
 * TIDAK include config/dbconn.php halaman tetap jalan walau MySQL mati
 * atau database lokal belum di-import, sama seperti laman BPS aslinya yang
 * tidak bergantung pada database lokal pengunjung.
 */
require_once '../../includes/auth.php';

$currentYear = (int) date('Y');
$defaultYear = $currentYear;
$years = range($currentYear, 2014);
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Data Ekspor Impor - BPS Sumut</title>
  <link rel="icon" href="../../assets/img/logo_(1)_1643969039217.png" type="image/png" />
  <link rel="stylesheet" href="../../assets/css/myCSS2.css" />
  <script src="../../assets/js/nav.js" defer></script>
  <style>
    .ex-card {
      background: white;
      padding: 20px;
      border-radius: 8px;
      border: 1px solid #ddd;
      margin-bottom: 20px;
    }

    .ex-card h2 {
      margin-top: 0;
      color: #002b6a;
    }

    .ex-row {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      align-items: flex-end;
      margin-bottom: 12px;
    }

    .ex-row label {
      font-size: 15px;
      color: #555;
      display: block;
      margin-bottom: 4px;
    }

    .ex-row input[type="text"],
    .ex-row select {
      padding: 8px 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
      min-width: 150px;
    }

    .ex-btn {
      padding: 9px 18px;
      border: none;
      border-radius: 4px;
      background: #034f84;
      color: white;
      cursor: pointer;
      font-weight: bold;
      text-decoration: none;
      display: inline-block;
      transition: background .15s ease;
    }

    .ex-btn:hover {
      background: #002b6a;
    }

    .ex-btn:disabled {
      background: #9db3c8;
      cursor: wait;
    }

    .ex-btn-secondary {
      background: #5b6b7c;
    }

    .ex-btn-secondary:hover {
      background: #425060;
    }

    .ex-row input[type="text"]:focus,
    .ex-row select:focus,
    .ex-search:focus,
    .ex-toolbar select:focus {
      outline: none;
      border-color: #034f84;
      box-shadow: 0 0 0 2px rgba(3, 79, 132, .15);
    }

    .ex-status {
      color: #888;
      font-size: 15px;
      margin: 8px 0;
    }

    .ex-error {
      color: #c0392b;
      background: #fdecea;
      padding: 12px;
      border-radius: 4px;
      margin: 8px 0;
    }

    .ex-section {
      margin-bottom: 24px;
    }

    .ex-section:last-of-type {
      margin-bottom: 6px;
    }

    .ex-sec-title {
      margin: 0;
      color: #002b6a;
      font-size: 19px;
      padding-bottom: 8px;
      border-bottom: 3px solid #034f84;
    }

    .ex-sec-sub {
      color: #777;
      font-size: 14px;
      margin: 6px 0 12px 0;
    }

    .ex-search {
      width: 100%;
      max-width: 320px;
      padding: 8px 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
      margin-bottom: 12px;
    }

    .table-wrap {
      overflow-x: auto;
    }

    .data-tabel {
      width: 100%;
      border-collapse: collapse;
    }

    .data-tabel th,
    .data-tabel td {
      border: 1px solid #ddd;
      padding: 8px 10px;
      font-size: 15px;
    }

    .data-tabel th {
      background: #002b6a;
      color: white;
      white-space: nowrap;
    }

    .data-tabel tr:nth-child(even) {
      background: #f9f9f9;
    }

    .data-tabel td.num {
      text-align: right;
      white-space: nowrap;
    }

    .ex-pager {
      display: flex;
      gap: 8px;
      align-items: center;
      margin-top: 10px;
      font-size: 15px;
      color: #555;
      flex-wrap: wrap;
    }

    .ex-toolbar {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
      margin-bottom: 12px;
    }

    .ex-toolbar label {
      font-size: 15px;
      color: #555;
    }

    .ex-toolbar select {
      padding: 8px 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
    }

    .data-tabel thead th {
      position: sticky;
      top: 0;
    }

    .data-tabel tbody tr:hover {
      background: #eef4fb;
    }

    .data-tabel tfoot td {
      background: #eaf1fa;
      font-weight: bold;
      color: #002b6a;
    }

    .ex-spin {
      display: inline-block;
      width: 13px;
      height: 13px;
      border: 2px solid #bcd;
      border-top-color: #034f84;
      border-radius: 50%;
      vertical-align: -2px;
      margin-right: 6px;
      animation: exspin .8s linear infinite;
    }

    @keyframes exspin {
      to {
        transform: rotate(360deg);
      }
    }

    .ex-empty {
      text-align: center;
      color: #888;
      padding: 18px;
    }

    .ex-note {
      color: #888;
      font-size: 14px;
      font-weight: normal;
      margin-left: 6px;
    }

    select:disabled {
      background: #f0f0f0;
      color: #999;
    }

    .ex-meta {
      color: #888;
      font-size: 15px;
      margin-top: 8px;
    }

    .ex-dl {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-top: 10px;
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
          <a href="exim.php" class="current">Data Ekspor Impor</a>
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
    <div class="ex-card">
      <h2>Data Ekspor Impor</h2>
      <div class="ex-row">
        <div>
          <label for="ex-tahun">1. Tahun</label>
          <select id="ex-tahun">
            <?php foreach ($years as $y): ?>
              <option value="<?= $y ?>" <?= $y === $defaultYear ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="ex-pod">2. Pelabuhan <span class="ex-note">(daftar terisi otomatis dari API)</span></label>
          <select id="ex-pod" style="min-width:200px;" disabled>
            <option value="">Semua Pelabuhan</option>
          </select>
        </div>
        <div>
          <label for="ex-ctr">3. Negara/Wilayah <span class="ex-note">(daftar terisi otomatis dari API)</span></label>
          <select id="ex-ctr" style="min-width:200px;" disabled>
            <option value="">Semua Negara/Wilayah</option>
          </select>
        </div>
        <div>
          <label for="ex-bulan">4. Bulan (tabel rincian)</label>
          <select id="ex-bulan">
            <option value="all" selected>Semua Bulan</option>
            <option value="1">Januari</option>
            <option value="2">Februari</option>
            <option value="3">Maret</option>
            <option value="4">April</option>
            <option value="5">Mei</option>
            <option value="6">Juni</option>
            <option value="7">Juli</option>
            <option value="8">Agustus</option>
            <option value="9">September</option>
            <option value="10">Oktober</option>
            <option value="11">November</option>
            <option value="12">Desember</option>
          </select>
        </div>
        <div>
          <button class="ex-btn" onclick="exLoad()">Tampilkan</button>
        </div>
      </div>
      <div class="ex-status" id="ex-status"><span class="ex-spin"></span>Memuat data awal...</div>
    </div>

    <div class="ex-card" id="ex-result-card" style="display:none;">
      <div class="ex-section">
        <h3 class="ex-sec-title" id="ex-monthly-title">Data Bulanan</h3>
        <p class="ex-sec-sub" id="ex-monthly-sub"></p>
        <div class="table-wrap">
          <table class="data-tabel" id="ex-monthly-table">
            <thead>
              <tr>
                <th>Bulan</th>
                <th>Nilai Ekspor (US$)</th>
                <th>Berat Ekspor (KG)</th>
                <th>Nilai Impor (US$)</th>
                <th>Berat Impor (KG)</th>
              </tr>
            </thead>
            <tbody id="ex-monthly-tbody"></tbody>
            <tfoot>
              <tr>
                <td>Total</td>
                <td class="num" id="ex-mt-ev"></td>
                <td class="num" id="ex-mt-ew"></td>
                <td class="num" id="ex-mt-iv"></td>
                <td class="num" id="ex-mt-iw"></td>
              </tr>
            </tfoot>
          </table>
        </div>
        <div class="ex-dl">
          <a class="ex-btn ex-btn-secondary" id="ex-dl-month-csv" href="#">Unduh Tabel Bulanan (CSV)</a>
          <a class="ex-btn ex-btn-secondary" id="ex-dl-month-xlsx" href="#">Unduh Tabel Bulanan (XLSX)</a>
        </div>
      </div>
      <div class="ex-section">
        <h3 class="ex-sec-title" id="ex-detail-title">Rincian HS 2 Digit</h3>
        <p class="ex-sec-sub" id="ex-detail-sub"></p>
        <div class="ex-toolbar">
          <input class="ex-search" id="ex-search" type="text" placeholder="Cari kode HS..." oninput="exSearch()" style="margin-bottom:0;" />
          <label for="ex-perpage">Baris per halaman</label>
          <select id="ex-perpage">
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100" selected>100</option>
          </select>
        </div>
        <div class="table-wrap">
          <table class="data-tabel" id="ex-table">
            <thead>
              <tr>
                <th>Kode HS</th>
                <th>Nilai Ekspor (US$)</th>
                <th>Berat Ekspor (KG)</th>
                <th>Nilai Impor (US$)</th>
                <th>Berat Impor (KG)</th>
              </tr>
            </thead>
            <tbody id="ex-tbody"></tbody>
            <tfoot>
              <tr>
                <td>Total</td>
                <td class="num" id="ex-dt-ev"></td>
                <td class="num" id="ex-dt-ew"></td>
                <td class="num" id="ex-dt-iv"></td>
                <td class="num" id="ex-dt-iw"></td>
              </tr>
            </tfoot>
          </table>
        </div>
        <div class="ex-pager">
          <button class="ex-btn ex-btn-secondary" id="ex-prev" onclick="exPage(-1)">‹ Sebelumnya</button>
          <span id="ex-pageinfo"></span>
          <button class="ex-btn ex-btn-secondary" id="ex-next" onclick="exPage(1)">Berikutnya ›</button>
        </div>
        <div class="ex-dl">
          <a class="ex-btn ex-btn-secondary" id="ex-dl-detail-csv" href="#">Unduh Rincian HS (CSV)</a>
          <a class="ex-btn ex-btn-secondary" id="ex-dl-detail-xlsx" href="#">Unduh Rincian HS (XLSX)</a>
        </div>
      </div>
      <p class="ex-meta">Unduhan mengikuti filter yang tampil (termasuk pelabuhan, negara, dan bulan).</p>
      <p class="ex-meta" id="ex-meta"></p>
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
    var EX_API = 'bps_exim_api.php';
    var EX_EKSPOR = []; // baris ekspor dari API (sumber=1)
    var EX_IMPOR = []; // baris impor dari API (sumber=2)
    var EX_FILTERED = []; // hasil saring pencarian
    var EX_PAGE = 1;
    var EX_PER_PAGE = 100;
    var EX_PARAMS = {};
    var EX_META = null;
    var EX_REQ = 0; // penjaga urutan respons (cegah race antar request)
    var EX_FIRST = true; // pemuatan pertama: otomatis pilih bulan terbaru

    var numFmt = new Intl.NumberFormat('id-ID', {
      maximumFractionDigits: 2
    });

    function exCleanMonth(s) {
      return String(s || '').replace(/^\[\d+\]\s*/, '');
    }

    function exParams() {
      // Urutan filter: 1 Tahun, 2 Pelabuhan, 3 Negara/Wilayah,
      // 4 Bulan (untuk tabel rincian HS). Kode HS tetap 09;15;40.
      // Ekspor+impor selalu diambil dua-duanya (mode gabungan),
      // periode selalu bulanan, HS 2 digit.
      var bulanVal = document.getElementById('ex-bulan').value;
      return {
        kodehs: '09;15;40',
        tahun: document.getElementById('ex-tahun').value,
        bulan: (bulanVal === 'all') ? '' : bulanVal,
        pod: document.getElementById('ex-pod').value,
        ctr: document.getElementById('ex-ctr').value
      };
    }

    var EX_MONTH_NAMES = {
      1: 'Januari',
      2: 'Februari',
      3: 'Maret',
      4: 'April',
      5: 'Mei',
      6: 'Juni',
      7: 'Juli',
      8: 'Agustus',
      9: 'September',
      10: 'Oktober',
      11: 'November',
      12: 'Desember'
    };

    function exMonthNum(bulanStr) {
      var s = String(bulanStr || '');
      var m = s.match(/\[(\d+)\]/);
      if (m) return parseInt(m[1], 10);
      var low = s.toLowerCase();
      var names = ['januari', 'februari', 'maret', 'april', 'mei', 'juni',
        'juli', 'agustus', 'september', 'oktober', 'november', 'desember'
      ];
      for (var i = 0; i < names.length; i++) {
        if (low.indexOf(names[i]) !== -1) return i + 1;
      }
      var n = parseInt(s, 10);
      return isNaN(n) ? 0 : n;
    }

    // Isi dropdown Pelabuhan & Negara dari hasil API gabungan
    // (distinct dari baris ekspor + impor, terurut).
    function exFillPodCtr() {
      var podSel = document.getElementById('ex-pod');
      var ctrSel = document.getElementById('ex-ctr');
      var curPod = podSel.value;
      var curCtr = ctrSel.value;
      var pods = {},
        ctrs = {};
      EX_EKSPOR.concat(EX_IMPOR).forEach(function(r) {
        if (r.pod) pods[r.pod] = true;
        if (r.ctr) ctrs[r.ctr] = true;
      });
      var podList = Object.keys(pods).sort();
      var ctrList = Object.keys(ctrs).sort();
      podSel.innerHTML = '';
      var o0 = document.createElement('option');
      o0.value = '';
      o0.textContent = 'Semua Pelabuhan';
      podSel.appendChild(o0);
      podList.forEach(function(v) {
        var o = document.createElement('option');
        o.value = v;
        o.textContent = v;
        podSel.appendChild(o);
      });
      ctrSel.innerHTML = '';
      var c0 = document.createElement('option');
      c0.value = '';
      c0.textContent = 'Semua Negara/Wilayah';
      ctrSel.appendChild(c0);
      ctrList.forEach(function(v) {
        var o = document.createElement('option');
        o.value = v;
        o.textContent = v;
        ctrSel.appendChild(o);
      });
      if (curPod && pods[curPod]) podSel.value = curPod;
      if (curCtr && ctrs[curCtr]) ctrSel.value = curCtr;
      EX_PARAMS.pod = podSel.value;
      EX_PARAMS.ctr = ctrSel.value;
      podSel.disabled = false;
      ctrSel.disabled = false;
    }

    // Saring satu daftar baris menurut pelabuhan & negara.
    function exBaseFiltered(list) {
      return list.filter(function(r) {
        if (EX_PARAMS.pod && (r.pod || '') !== EX_PARAMS.pod) return false;
        if (EX_PARAMS.ctr && (r.ctr || '') !== EX_PARAMS.ctr) return false;
        return true;
      });
    }

    // Saring satu daftar baris menurut pelabuhan, negara, DAN bulan
    // (dipakai tabel rincian HS).
    function exMonthFiltered(list) {
      return exBaseFiltered(list).filter(function(r) {
        if (EX_PARAMS.bulan) {
          if (String(exMonthNum(r.bulan)) !== String(EX_PARAMS.bulan)) return false;
        }
        return true;
      });
    }

    // Gabungkan ekspor + impor per kode HS.
    function exAggregateRincian(rowsE, rowsI) {
      var groups = {};

      function add(list, isImpor) {
        list.forEach(function(r) {
          var key = r.kodehs || '(Tanpa HS)';
          if (!groups[key]) {
            groups[key] = {
              label: key,
              ev: 0,
              ew: 0,
              iv: 0,
              iw: 0
            };
          }
          var v = parseFloat(r.value) || 0;
          var w = parseFloat(r.netweight) || 0;
          if (isImpor) {
            groups[key].iv += v;
            groups[key].iw += w;
          } else {
            groups[key].ev += v;
            groups[key].ew += w;
          }
        });
      }
      add(rowsE, false);
      add(rowsI, true);
      return Object.keys(groups).sort().map(function(k) {
        return groups[k];
      });
    }

    function exLoad() {
      var p = exParams();
      var statusEl = document.getElementById('ex-status');
      var btn = document.querySelector('.ex-row .ex-btn');
      statusEl.innerHTML = '<span class="ex-spin"></span>Mengambil data dari BPS...';
      document.getElementById('ex-result-card').style.display = 'none';
      if (btn) btn.disabled = true;

      var myReq = ++EX_REQ;
      // Mode gabungan: ekspor + impor diambil sekaligus server-side.
      var url = EX_API + '?sumber=3' +
        '&kodehs=' + encodeURIComponent(p.kodehs) +
        '&jenishs=1' +
        '&tahun=' + encodeURIComponent(p.tahun) +
        '&periode=1';

      fetch(url).then(function(res) {
        return res.json();
      }).then(function(json) {
        if (myReq !== EX_REQ) return; // abaikan respons basi (request lebih baru sudah jalan)
        if (btn) btn.disabled = false;
        if (!json.success) {
          statusEl.innerHTML = '';
          var err = document.createElement('div');
          err.className = 'ex-error';
          err.textContent = 'Gagal memuat data: ' + json.message;
          statusEl.appendChild(err);
          return;
        }
        statusEl.textContent = '';
        EX_PARAMS = p;
        var d = (json.data && typeof json.data === 'object') ? json.data : {};
        EX_EKSPOR = Array.isArray(d.ekspor) ? d.ekspor : [];
        EX_IMPOR = Array.isArray(d.impor) ? d.impor : [];
        EX_META = json.metadata || null;
        EX_PAGE = 1;
        document.getElementById('ex-search').value = '';
        // Pemuatan pertama dengan "Semua Bulan": otomatis sorot bulan
        // terbaru yang tersedia di data (mis. Juli 2026).
        if (EX_FIRST) {
          EX_FIRST = false;
          if (!EX_PARAMS.bulan) {
            var latest = 0;
            EX_EKSPOR.concat(EX_IMPOR).forEach(function(r) {
              var m = exMonthNum(r.bulan);
              if (m > latest) latest = m;
            });
            if (latest >= 1 && latest <= 12) {
              EX_PARAMS.bulan = String(latest);
              document.getElementById('ex-bulan').value = String(latest);
            }
          }
        }
        exRenderAll(EX_META, true);
      }).catch(function() {
        if (myReq !== EX_REQ) return;
        if (btn) btn.disabled = false;
        statusEl.textContent = 'Gagal terhubung ke server. Coba lagi nanti.';
      });
    }

    function exRenderAll(metadata, fresh) {
      if (!EX_EKSPOR.length && !EX_IMPOR.length) {
        var statusEl = document.getElementById('ex-status');
        statusEl.innerHTML = '';
        var none = document.createElement('div');
        none.className = 'ex-error';
        none.textContent = 'Tidak ada data untuk kombinasi filter tersebut. Coba tahun lain.';
        statusEl.appendChild(none);
        return;
      }
      document.getElementById('ex-result-card').style.display = 'block';

      // Isi pilihan pelabuhan & negara hanya dari data baru (bukan saat saring ulang).
      if (fresh) exFillPodCtr();

      // Tabel 1: nasional bulanan gabungan (selalu penuh setahun)
      document.getElementById('ex-monthly-title').textContent =
        'Data Ekspor-Impor Nasional Bulanan ' + EX_PARAMS.tahun;
      document.getElementById('ex-monthly-sub').textContent = exFilterContext();
      exRenderMonthly();

      // Tabel 2: rincian HS 2 digit gabungan untuk bulan terpilih
      document.getElementById('ex-detail-title').textContent =
        'Data Ekspor-Impor Nasional HS 2 Digit - ' + exPeriodLabel();
      document.getElementById('ex-detail-sub').textContent = exFilterContext();

      exApplySearch();

      // Unduhan server-side per tabel (mengikuti filter yang tampil).
      var dlBase = 'bps_exim_download.php?sumber=3' +
        '&kodehs=' + encodeURIComponent(EX_PARAMS.kodehs) +
        '&jenishs=1' +
        '&tahun=' + encodeURIComponent(EX_PARAMS.tahun) +
        '&periode=1' +
        '&pod=' + encodeURIComponent(EX_PARAMS.pod || '') +
        '&ctr=' + encodeURIComponent(EX_PARAMS.ctr || '');
      document.getElementById('ex-dl-month-csv').href = dlBase + '&tabel=bulanan&format=csv';
      document.getElementById('ex-dl-month-xlsx').href = dlBase + '&tabel=bulanan&format=xlsx';
      document.getElementById('ex-dl-detail-csv').href = dlBase + '&tabel=rincian&bulan=' + encodeURIComponent(EX_PARAMS.bulan || '') + '&format=csv';
      document.getElementById('ex-dl-detail-xlsx').href = dlBase + '&tabel=rincian&bulan=' + encodeURIComponent(EX_PARAMS.bulan || '') + '&format=xlsx';

      var metaEl = document.getElementById('ex-meta');
      var ctx = exPeriodLabel();
      if (EX_PARAMS.pod) ctx += ' · ' + EX_PARAMS.pod;
      if (EX_PARAMS.ctr) ctx += ' · ' + EX_PARAMS.ctr;
      metaEl.textContent = (metadata && metadata.source ? metadata.source + ' · ' : '') +
        ctx + ' · ' + EX_EKSPOR.length + ' baris ekspor, ' + EX_IMPOR.length + ' baris impor · cache 6 jam.';

      document.getElementById('ex-result-card').scrollIntoView({
        behavior: 'smooth',
        block: 'nearest'
      });
    }

    // Konteks filter aktif untuk sub-judul tabel (pelabuhan + negara).
    function exFilterContext() {
      var parts = [];
      parts.push(EX_PARAMS.pod ? 'Pelabuhan: ' + EX_PARAMS.pod : 'Semua pelabuhan');
      parts.push(EX_PARAMS.ctr ? 'Negara: ' + EX_PARAMS.ctr : 'Semua negara/wilayah');
      return parts.join(' · ');
    }

    // Label periode untuk judul tabel rincian (mis. "Juli 2026").
    function exPeriodLabel() {
      if (EX_PARAMS.bulan && EX_MONTH_NAMES[EX_PARAMS.bulan]) {
        return EX_MONTH_NAMES[EX_PARAMS.bulan] + ' ' + EX_PARAMS.tahun;
      }
      return 'Januari–Desember ' + EX_PARAMS.tahun;
    }

    // Kelompokkan satu daftar baris per bulan kalender (1-12, terurut).
    function exAggregateMonthly(list) {
      var months = {};
      list.forEach(function(r) {
        var m = exMonthNum(r.bulan);
        if (m < 1 || m > 12) return;
        if (!months[m]) months[m] = {
          m: m,
          value: 0,
          netweight: 0
        };
        months[m].value += parseFloat(r.value) || 0;
        months[m].netweight += parseFloat(r.netweight) || 0;
      });
      var out = Object.keys(months).map(function(k) {
        return months[k];
      });
      out.sort(function(a, b) {
        return a.m - b.m;
      });
      return out;
    }

    // Gambar tabel nasional bulanan gabungan + baris total.
    function exRenderMonthly() {
      var tb = document.getElementById('ex-monthly-tbody');
      tb.innerHTML = '';
      var listE = exAggregateMonthly(exBaseFiltered(EX_EKSPOR));
      var listI = exAggregateMonthly(exBaseFiltered(EX_IMPOR));
      var mapE = {},
        mapI = {},
        months = {};
      listE.forEach(function(r) {
        mapE[r.m] = r;
        months[r.m] = true;
      });
      listI.forEach(function(r) {
        mapI[r.m] = r;
        months[r.m] = true;
      });
      var keys = Object.keys(months).map(Number).sort(function(a, b) {
        return a - b;
      });
      var tot = [0, 0, 0, 0];
      if (!keys.length) {
        var tr0 = document.createElement('tr');
        var td0 = document.createElement('td');
        td0.colSpan = 5;
        td0.className = 'ex-empty';
        td0.textContent = 'Tidak ada data bulanan untuk saringan ini. Ubah tahun, pelabuhan, atau negara.';
        tr0.appendChild(td0);
        tb.appendChild(tr0);
      }
      keys.forEach(function(m) {
        var e = mapE[m] || {
          value: 0,
          netweight: 0
        };
        var im = mapI[m] || {
          value: 0,
          netweight: 0
        };
        tot[0] += e.value;
        tot[1] += e.netweight;
        tot[2] += im.value;
        tot[3] += im.netweight;
        var tr = document.createElement('tr');
        [EX_MONTH_NAMES[m], e.value, e.netweight, im.value, im.netweight].forEach(function(v, i) {
          var td = document.createElement('td');
          if (i === 0) {
            td.textContent = v;
          } else {
            td.className = 'num';
            td.textContent = numFmt.format(v);
          }
          tr.appendChild(td);
        });
        tb.appendChild(tr);
      });
      document.getElementById('ex-mt-ev').textContent = numFmt.format(tot[0]);
      document.getElementById('ex-mt-ew').textContent = numFmt.format(tot[1]);
      document.getElementById('ex-mt-iv').textContent = numFmt.format(tot[2]);
      document.getElementById('ex-mt-iw').textContent = numFmt.format(tot[3]);
    }

    // Saring ulang client-side tanpa request API (filter pelabuhan,
    // negara, bulan tidak butuh ambil ulang karena datanya sudah bulanan).
    function exRefilter() {
      if (!EX_EKSPOR.length && !EX_IMPOR.length) return;
      EX_PARAMS.pod = document.getElementById('ex-pod').value;
      EX_PARAMS.ctr = document.getElementById('ex-ctr').value;
      var bulanVal = document.getElementById('ex-bulan').value;
      EX_PARAMS.bulan = (bulanVal === 'all') ? '' : bulanVal;
      EX_PAGE = 1;
      exRenderAll(EX_META, false);
    }

    function exPerPageChange() {
      EX_PER_PAGE = parseInt(document.getElementById('ex-perpage').value, 10) || 100;
      EX_PAGE = 1;
      exDrawPage();
    }

    function exApplySearch() {
      var q = document.getElementById('ex-search').value.toLowerCase();
      var grouped = exAggregateRincian(
        exMonthFiltered(EX_EKSPOR),
        exMonthFiltered(EX_IMPOR)
      );
      if (!q) {
        EX_FILTERED = grouped;
      } else {
        EX_FILTERED = grouped.filter(function(r) {
          return String(r.label).toLowerCase().indexOf(q) !== -1;
        });
      }
      EX_PAGE = 1;
      exDrawPage();
    }

    function exSearch() {
      exApplySearch();
    }

    function exPage(dir) {
      var max = Math.max(1, Math.ceil(EX_FILTERED.length / EX_PER_PAGE));
      EX_PAGE = Math.min(max, Math.max(1, EX_PAGE + dir));
      exDrawPage();
    }

    function exDrawPage() {
      var tb = document.getElementById('ex-tbody');
      tb.innerHTML = '';
      var start = (EX_PAGE - 1) * EX_PER_PAGE;
      var pageRows = EX_FILTERED.slice(start, start + EX_PER_PAGE);
      // Total dihitung dari SELURUH hasil tersaring (bukan cuma halaman ini).
      var tot = [0, 0, 0, 0];
      EX_FILTERED.forEach(function(r) {
        tot[0] += r.ev;
        tot[1] += r.ew;
        tot[2] += r.iv;
        tot[3] += r.iw;
      });
      if (!pageRows.length) {
        var tr0 = document.createElement('tr');
        var td0 = document.createElement('td');
        td0.colSpan = 5;
        td0.className = 'ex-empty';
        td0.textContent = 'Tidak ada data untuk kombinasi saringan ini. Ubah pelabuhan, negara, bulan, atau pencarian.';
        tr0.appendChild(td0);
        tb.appendChild(tr0);
      }
      pageRows.forEach(function(r) {
        var tr = document.createElement('tr');
        [r.label, r.ev, r.ew, r.iv, r.iw].forEach(function(v, i) {
          var td = document.createElement('td');
          if (i === 0) {
            td.textContent = v;
          } else {
            td.className = 'num';
            td.textContent = numFmt.format(v);
          }
          tr.appendChild(td);
        });
        tb.appendChild(tr);
      });
      document.getElementById('ex-dt-ev').textContent = numFmt.format(tot[0]);
      document.getElementById('ex-dt-ew').textContent = numFmt.format(tot[1]);
      document.getElementById('ex-dt-iv').textContent = numFmt.format(tot[2]);
      document.getElementById('ex-dt-iw').textContent = numFmt.format(tot[3]);
      var max = Math.max(1, Math.ceil(EX_FILTERED.length / EX_PER_PAGE));
      document.getElementById('ex-pageinfo').textContent =
        'Halaman ' + EX_PAGE + ' dari ' + max + ' (' + numFmt.format(EX_FILTERED.length) + ' baris)';
      document.getElementById('ex-prev').disabled = EX_PAGE <= 1;
      document.getElementById('ex-next').disabled = EX_PAGE >= max;
    }

    window.onload = function() {
      document.getElementById('ex-pod').addEventListener('change', exRefilter);
      document.getElementById('ex-ctr').addEventListener('change', exRefilter);
      document.getElementById('ex-bulan').addEventListener('change', exRefilter);
      document.getElementById('ex-perpage').addEventListener('change', exPerPageChange);
      exLoad();
    };
  </script>
</body>

</html>