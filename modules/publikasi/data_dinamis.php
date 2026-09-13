<?php

/**
 * Halaman publik: Tabel Dinamis (Query Builder) BPS, meniru query-builder
 * sumut.bps.go.id. Klasifikasi subjek memakai CSA (subcatcsa/subjectcsa).
 *
 * Batas "maksimal 2" hanya untuk jumlah TABEL. API membatasi maksimal
 * 2 tahun per request 'th'; tahun yang lebih banyak diambil server
 * bertahap lalu digabung (lihat bpsSplitTh di bps_client.php).
 *
 * Semua request ke BPS lewat proxy server-side bps_query_api.php
 * (API key tidak pernah ke browser). Deep-link lama tetap didukung:
 * ?var=4&th=1 otomatis masuk Data Terpilih lalu disubmit.
 */
include '../../config/dbconn.php';
require_once '../../includes/auth.php';
require_once '../../config/bps_api.php';

$preVar = trim($_GET['var'] ?? '');
if (!preg_match('/^\d+$/', $preVar)) {
  $preVar = '';
}
$preTh = trim($_GET['th'] ?? '');
if (!preg_match('/^[\d;]+$/', $preTh)) {
  $preTh = '';
}
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Tabel Dinamis (Query Builder) - BPS Sumut</title>
  <link rel="icon" href="../../assets/img/logo_(1)_1643969039217.png" type="image/png" />
  <link rel="stylesheet" href="../../assets/css/myCSS2.css" />
  <script src="../../assets/js/nav.js" defer></script>
  <style>
    .qb-card {
      background: white;
      padding: 20px;
      border-radius: 8px;
      border: 1px solid #ddd;
      margin-bottom: 20px;
    }

    .qb-card h2 {
      margin-top: 0;
      color: #002b6a;
    }

    .qb-card h3 {
      color: #034f84;
      margin: 0 0 10px 0;
      font-size: 18px;
    }

    .qb-row {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      align-items: flex-end;
      margin-bottom: 12px;
    }

    .qb-row label {
      font-size: 15px;
      color: #555;
      display: block;
      margin-bottom: 4px;
    }

    .qb-row input[type="text"],
    .qb-row select {
      padding: 8px 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
      min-width: 200px;
    }

    .qb-btn {
      padding: 9px 18px;
      border: none;
      border-radius: 4px;
      background: #034f84;
      color: white;
      cursor: pointer;
      font-weight: bold;
    }

    .qb-btn:hover:not(:disabled) {
      background: #002b6a;
    }

    .qb-btn:disabled {
      background: #aaa;
      cursor: not-allowed;
    }

    .qb-btn-secondary {
      background: #666;
    }

    .qb-status {
      color: #888;
      font-size: 15px;
      margin: 8px 0;
    }

    .qb-error {
      color: #c0392b;
      background: #fdecea;
      padding: 12px;
      border-radius: 4px;
      margin: 8px 0;
    }

    .qb-hint {
      background: #facc15;
      padding: 8px 16px;
      border-radius: 6px;
      font-size: 16px;
      margin: 0 0 12px 0;
      color: #222;
    }

    /* Panel filter ala BPS: kartu abuamek dengan label caption + kotak scroll. */
    .qb-panel {
      display: flex;
      flex-direction: column;
      gap: 8px;
      min-width: 0;
    }

    .qb-panel>.qb-cap {
      font-size: 15px;
      color: #555;
    }

    .qb-box {
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 12px;
      height: 100%;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 4px;
      background: #fff;
      min-height: 120px;
    }

    .qb-box-ok {
      border-color: #22c55e !important;
    }

    .qb-box-err {
      border-color: #ef4444 !important;
    }

    .qb-dim-empty {
      color: #888;
      font-size: 15px;
      text-align: center;
      margin: auto;
      padding: 12px;
    }

    .qb-opt {
      display: flex;
      gap: 8px;
      align-items: center;
      padding: 8px;
      font-size: 15px;
      background: #f3f4f6;
      border-radius: 6px;
      cursor: pointer;
      user-select: none;
    }

    .qb-opt:hover {
      background: #e5e7eb;
    }

    .qb-sa {
      display: inline-flex;
      gap: 4px;
      align-items: center;
      font-size: 15px;
      color: #0284c7;
      cursor: pointer;
    }

    .qb-err-ic {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: #ef4444;
      color: white;
      font-weight: bold;
      font-size: 13px;
      width: 20px;
      height: 20px;
      border-radius: 6px;
    }

    /* Kartu tabel: klik untuk pilih (seperti laman BPS), bukan checkbox. */
    .qb-tcard {
      padding: 8px;
      border-radius: 6px;
      margin-bottom: 8px;
      cursor: pointer;
      background: #f3f4f6;
      font-size: 14px;
      color: #111;
      transition: background .15s;
    }

    .qb-tcard:hover {
      background: #e5e7eb;
    }

    .qb-tcard.sel {
      background: #034f84;
      color: white;
    }

    .qb-grid {
      display: flex;
      gap: 16px;
      margin-bottom: 16px;
    }

    .qb-grid .grow-2 {
      flex: 2;
      min-width: 0;
    }

    .qb-grid .grow-1 {
      flex: 1;
      min-width: 0;
    }

    @media (max-width: 768px) {
      .qb-grid {
        flex-direction: column;
      }
    }

    #qb-var-list {
      height: 340px;
    }

    .qb-more-loading {
      text-align: center;
      color: #888;
      font-size: 15px;
      padding: 8px;
    }

    .qb-table-search {
      padding: 6px;
      font-size: 14px;
      border: 1px solid #ccc;
      border-radius: 6px;
      min-width: 140px;
    }

    /* Data Terpilih: badge hitungan warna seperti laman BPS. */
    .qb-picked-item {
      display: flex;
      gap: 8px;
      align-items: flex-start;
      background: #f3f4f6;
      border-radius: 6px;
      padding: 8px;
      margin-bottom: 8px;
      cursor: pointer;
    }

    .qb-picked-item:hover {
      background: #e5e7eb;
    }

    .qb-picked-item .t {
      font-size: 16px;
      color: #111;
    }

    .qb-badge {
      display: inline-block;
      padding: 4px 8px;
      font-size: 14px;
      border-radius: 6px;
      margin: 2px 4px 2px 0;
      white-space: nowrap;
    }

    .qb-b-blue {
      background: #bfdbfe;
    }

    .qb-b-green {
      background: #bbf7d0;
    }

    .qb-b-red {
      background: #fecaca;
    }

    .qb-b-yellow {
      background: #fef08a;
    }

    .qb-del {
      border: none;
      background: transparent;
      color: #ef4444;
      cursor: pointer;
      font-size: 18px;
      padding: 4px 8px;
      opacity: 0;
    }

    .qb-picked-item:hover .qb-del {
      opacity: 1;
    }

    @media (max-width: 768px) {
      .qb-del {
        opacity: 1;
      }
    }

    .qb-maxwarn {
      background: #facc15;
      border-radius: 4px;
      padding: 4px 8px;
      margin-left: 8px;
      font-size: 16px;
      transition: opacity .2s;
    }

    /* Modal detail data terpilih. */
    .qb-modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .25);
      z-index: 999;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }

    .qb-modal {
      background: white;
      border-radius: 16px;
      padding: 24px;
      width: 100%;
      max-width: 800px;
      max-height: 90vh;
      overflow-y: auto;
    }

    .qb-modal h3 {
      margin-top: 0;
      color: #111;
      font-size: 20px;
    }

    .qb-chip {
      display: inline-block;
      padding: 4px 8px;
      font-size: 14px;
      border-radius: 4px;
      margin: 2px 4px 2px 0;
    }

    .qb-tabs {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin: 10px 0;
    }

    .qb-tab {
      padding: 6px 12px;
      border: 1px solid #034f84;
      border-radius: 20px;
      background: white;
      color: #034f84;
      cursor: pointer;
      font-size: 15px;
    }

    .qb-tab.active {
      background: #034f84;
      color: white;
    }

    .table-wrap {
      overflow-x: auto;
      margin-top: 10px;
    }

    .data-tabel {
      width: 100%;
      border-collapse: collapse;
    }

    .data-tabel th,
    .data-tabel td {
      border: 1px solid #ddd;
      padding: 8px 10px;
      font-size: 16px;
    }

    .data-tabel th {
      background: #002b6a;
      color: white;
    }

    .data-tabel tr:nth-child(even) {
      background: #f9f9f9;
    }

    .qb-api-url {
      font-family: monospace;
      font-size: 14px;
      background: #f4f6fa;
      padding: 10px;
      border-radius: 4px;
      word-break: break-all;
      margin-top: 12px;
    }

    .qb-meta {
      color: #888;
      font-size: 15px;
      margin-top: 8px;
    }

    .qb-result-title {
      color: #002b6a;
      margin: 18px 0 4px 0;
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
          <a href="data_dinamis.php" class="current">Tabel Dinamis</a>
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
    <form method="POST" onsubmit="return false;">
      <div class="qb-card">
        <p class="qb-hint">Hanya dapat memilih maksimal 2 data.</p>
        <div class="qb-row">
          <div>
            <label for="qb-subcat">Kategori Subjek</label>
            <select id="qb-subcat">
              <option value="">Semua</option>
            </select>
          </div>
          <div>
            <label for="qb-subject">Subjek</label>
            <select id="qb-subject">
              <option value="">Semua</option>
            </select>
          </div>
        </div>
        <div class="qb-status" id="qb-search-status">Memuat kategori &amp; subjek...</div>

        <div class="qb-grid">
          <div class="qb-panel grow-2">
            <div class="qb-cap" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
              <span>Tabel / Indikator</span>
              <input type="text" id="qb-table-search" class="qb-table-search" placeholder="Cari tabel..." />
              <span id="qb-cat-hint" style="display:none; font-size:14px; color:#0284c7;">Pilih Subjek untuk menyaring tabel sesuai kategori.</span>
            </div>
            <div class="qb-box" id="qb-var-list"></div>
          </div>
          <div class="grow-1" style="display:flex; flex-direction:column; gap:16px; min-width:0;">
            <div class="qb-panel" style="flex:1; min-height:0;">
              <div class="qb-cap" style="display:flex; gap:8px; align-items:center;">
                <span>Tahun</span>
                <label class="qb-sa" id="qb-sa-th-wrap" style="display:none;"><input type="checkbox" id="qb-sa-th" /> Pilih semua</label>
                <span class="qb-err-ic" id="qb-err-th" title="Pilih minimal 1 tahun" style="display:none;">!</span>
              </div>
              <div class="qb-box" id="qb-box-th" style="max-height:220px;"></div>
            </div>
            <div class="qb-panel" style="flex:1; min-height:0;">
              <div class="qb-cap" style="display:flex; gap:8px; align-items:center;">
                <span>Turunan Tahun</span>
                <label class="qb-sa" id="qb-sa-turth-wrap" style="display:none;"><input type="checkbox" id="qb-sa-turth" /> Pilih semua</label>
                <span class="qb-err-ic" id="qb-err-turth" title="Pilih minimal 1 turunan tahun" style="display:none;">!</span>
              </div>
              <div class="qb-box" id="qb-box-turth" style="max-height:220px;"></div>
            </div>
          </div>
        </div>

        <div class="qb-grid">
          <div class="qb-panel grow-1">
            <div class="qb-cap" style="display:flex; gap:8px; align-items:center;">
              <span>Karakteristik</span>
              <label class="qb-sa" id="qb-sa-turvar-wrap" style="display:none;"><input type="checkbox" id="qb-sa-turvar" /> Pilih semua</label>
              <span class="qb-err-ic" id="qb-err-turvar" title="Pilih minimal 1 karakteristik" style="display:none;">!</span>
            </div>
            <div class="qb-box" id="qb-box-turvar" style="height:190px;"></div>
          </div>
          <div class="qb-panel grow-1">
            <div class="qb-cap" style="display:flex; gap:8px; align-items:center;">
              <span>Judul Baris</span>
              <label class="qb-sa" id="qb-sa-vervar-wrap" style="display:none;"><input type="checkbox" id="qb-sa-vervar" /> Pilih semua</label>
              <span class="qb-err-ic" id="qb-err-vervar" title="Pilih minimal 1 judul baris" style="display:none;">!</span>
            </div>
            <div class="qb-box" id="qb-box-vervar" style="height:190px;"></div>
          </div>
        </div>

        <div class="qb-row" style="justify-content:center;">
          <div>
            <button type="button" class="qb-btn" id="qb-add-btn" onclick="qbAddCurrent()" disabled>Tambah</button>
          </div>
          <div>
            <button type="button" class="qb-btn qb-btn-secondary" onclick="qbResetAll()">Atur Ulang</button>
          </div>
        </div>

        <div style="margin-top:12px;">
          <div class="qb-cap" style="font-size:15px; color:#555; margin-bottom:8px;">
            <span>Data Terpilih</span><span class="qb-maxwarn" id="qb-maxwarn" style="opacity:0;">Anda telah mencapai batas maksimum 2 data.</span>
          </div>
          <div id="qb-picked-list"></div>
        </div>

        <div class="qb-row" style="justify-content:center; margin-top:12px;">
          <div>
            <button type="button" class="qb-btn" id="qb-submit-btn" onclick="qbSubmit()" disabled>Submit</button>
          </div>
        </div>
      </div>
    </form>

    <!-- HASIL -->
    <div class="qb-card" id="qb-result-card" style="display:none;">
      <h2>Hasil Tabel Dinamis</h2>
      <div id="qb-results"></div>
    </div>

    <!-- MODAL detail data terpilih -->
    <div class="qb-modal-overlay" id="qb-modal" style="display:none;">
      <div class="qb-modal">
        <h3 id="qb-modal-title"></h3>
        <p class="qb-cap" style="font-size:15px; color:#555;">Tahun</p>
        <div id="qb-modal-year" style="margin-bottom:12px;"></div>
        <p class="qb-cap" style="font-size:15px; color:#555;">Turunan Tahun</p>
        <div id="qb-modal-turyear" style="margin-bottom:12px;"></div>
        <p class="qb-cap" style="font-size:15px; color:#555;">Karakteristik</p>
        <div id="qb-modal-char" style="margin-bottom:12px;"></div>
        <p class="qb-cap" style="font-size:15px; color:#555;">Judul Baris</p>
        <div id="qb-modal-vervar" style="margin-bottom:12px; max-height:220px; overflow-y:auto;"></div>
        <div class="qb-row" style="justify-content:center;">
          <div>
            <button type="button" class="qb-btn qb-btn-secondary" onclick="qbCloseModal()">Tutup</button>
          </div>
          <div>
            <button type="button" class="qb-btn" id="qb-modal-del" style="background:#ef4444;">Hapus</button>
          </div>
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
    var QB_API = 'bps_query_api.php';
    var QB_DL = 'bps_query_download.php';
    var QB_DOMAIN = <?= json_encode(BPS_DOMAIN) ?>;
    var QB_MAX_VARS = 2;
    // Batas "maksimal 2" hanya untuk jumlah TABEL (Data Terpilih).
    // Jumlah tahun/kolom per tabel TIDAK dibatasi (server mengambil
    // bertahap otomatis bila th lebih dari 2 lihat bpsSplitTh).
    // Struktur disamakan dengan query-builder BPS (sumut.bps.go.id):
    // kartu tabel klik-pilih + panel Tahun/Turunan/Karakteristik/Judul
    // Baris + Tambah/Atur Ulang + Data Terpilih (badge) + Submit.
    var qbTable = null; // {var_id, title, unit} tabel yang sedang diatur
    var qbCharExist = true; // false bila tabel tak punya karakteristik
    // [{table:{id,title,unit}, year:[{value,label}], turyear:[...],
    //   characteristic:[...], vervar:[{value,label}]}]
    var qbPicked = [];
    var qbValid = {}; // year/turyear/characteristic/vervar true/false
    var qbQuery = {}; // var_id {th, turvar, vervar, turth} utk unduhan
    var qbSubmitting = false;
    var qbModalIdx = -1;
    // Semua subjek (sekali muat) + saring instan per kategori
    // sama seperti laman query-builder BPS (tanpa request ulang).
    var qbAllSubjects = [];

    function qbFillSubjectBySubcat() {
      var sc = document.getElementById('qb-subcat').value;
      var items = !sc ?
        qbAllSubjects :
        qbAllSubjects.filter(function(s) {
          return String(s.subcat_id) === String(sc);
        });
      qbFillSelect('qb-subject', items, 'Semua');
    }
    // Paging daftar tabel (10 per halaman API) + nomor urut request.
    var qbSearchSeq = 0;
    var qbVarPage = 1;
    var qbVarPages = 1;
    var qbVarTotal = 0;
    var qbVarLoading = false;
    var qbTableIds = {};
    // Nomor urut pemuatan dimensi (hasil basi diabaikan).
    var qbDimSeq = 0;
    var qbDimsPending = 0;

    function esc(s) {
      return String(s === undefined || s === null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function qbGet(url) {
      return fetch(url).then(function(res) {
        return res.json();
      });
    }

    function qbFillSelect(selId, items, placeholder) {
      var sel = document.getElementById(selId);
      sel.innerHTML = '';
      var opt0 = document.createElement('option');
      opt0.value = '';
      opt0.textContent = placeholder;
      sel.appendChild(opt0);
      items.forEach(function(it) {
        var o = document.createElement('option');
        o.value = it.id;
        o.textContent = it.label;
        sel.appendChild(o);
      });
    }

    function qbInit() {
      qbDimYear = qbMakeDim({
        box: 'qb-box-th',
        sa: 'qb-sa-th',
        saWrap: 'qb-sa-th-wrap',
        err: 'qb-err-th',
        name: 'year[]',
        empty: 'Pilih tabel terlebih dahulu.',
        emptyNone: 'Tidak ada pilihan tahun dari BPS.'
      });
      qbDimTur = qbMakeDim({
        box: 'qb-box-turth',
        sa: 'qb-sa-turth',
        saWrap: 'qb-sa-turth-wrap',
        err: 'qb-err-turth',
        name: 'turyear[]',
        empty: 'Pilih tabel terlebih dahulu.',
        emptyNone: 'Tidak ada turunan tahun dari BPS.'
      });
      qbDimChar = qbMakeDim({
        box: 'qb-box-turvar',
        sa: 'qb-sa-turvar',
        saWrap: 'qb-sa-turvar-wrap',
        err: 'qb-err-turvar',
        name: 'characteristic[]',
        empty: 'Pilih tabel terlebih dahulu.',
        emptyNone: 'Tidak ada karakteristik dari BPS.'
      });
      qbDimVer = qbMakeDim({
        box: 'qb-box-vervar',
        sa: 'qb-sa-vervar',
        saWrap: 'qb-sa-vervar-wrap',
        err: 'qb-err-vervar',
        name: 'vervar[]',
        empty: 'Pilih tabel terlebih dahulu.',
        emptyNone: 'Tidak ada judul baris dari BPS.'
      });

      var statusEl = document.getElementById('qb-search-status');
      var preVar = <?= json_encode($preVar) ?>;
      var preTh = <?= json_encode($preTh) ?>;
      qbGet(QB_API + '?action=subcatcsa').then(function(json) {
        if (json.success) qbFillSelect('qb-subcat', json.data, 'Semua');
      }).catch(function() {});
      qbGet(QB_API + '?action=subjectcsa').then(function(json) {
        if (json.success) {
          qbAllSubjects = json.data;
          qbFillSubjectBySubcat();
          statusEl.textContent = '';
        } else {
          statusEl.textContent = 'Gagal memuat subjek: ' + json.message;
        }
        qbSearchTables(true);
        if (preVar) qbDeepLink(preVar, preTh);
      }).catch(function() {
        statusEl.textContent = 'Gagal terhubung ke server. Coba lagi nanti.';
      });

      // Ganti kategori subjek tersaring instan.
      document.getElementById('qb-subcat').addEventListener('change', function() {
        qbFillSubjectBySubcat();
        qbSearchTables(true);
      });
      document.getElementById('qb-subject').addEventListener('change', function() {
        qbSearchTables(true);
      });
      // Cari tabel (debounce 300ms).
      var qbTblTimer = null;
      var tblSearch = document.getElementById('qb-table-search');
      tblSearch.addEventListener('input', function() {
        clearTimeout(qbTblTimer);
        qbTblTimer = setTimeout(function() {
          qbSearchTables(true);
        }, 300);
      });
      tblSearch.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          clearTimeout(qbTblTimer);
          qbSearchTables(true);
        }
      });
      document.getElementById('qb-var-list').addEventListener('scroll', function() {
        var box = this;
        if (box.scrollTop + box.clientHeight >= box.scrollHeight - 80) qbLoadVarPage();
      });
      qbRenderPicked();
    }

    function qbSearchTables(reset) {
      // API hanya menyaring tabel via Subjek.
      var sc = document.getElementById('qb-subcat').value;
      var sj = document.getElementById('qb-subject').value;
      document.getElementById('qb-cat-hint').style.display = (sc && !sj) ? '' : 'none';
      if (reset) {
        qbSearchSeq++;
        qbVarPage = 1;
        qbVarPages = 1;
        qbVarTotal = 0;
        qbTableIds = {};
        document.getElementById('qb-var-list').innerHTML = '';
      }
      qbLoadVarPage();
    }

    function qbLoadVarPage() {
      if (qbVarLoading || qbVarPage > qbVarPages) return;
      var mySeq = qbSearchSeq;
      qbVarLoading = true;
      var kw = document.getElementById('qb-table-search').value.trim();
      var subject = document.getElementById('qb-subject').value;
      var statusEl = document.getElementById('qb-search-status');
      var listEl = document.getElementById('qb-var-list');
      if (qbVarPage === 1) {
        listEl.innerHTML = '';
        statusEl.textContent = 'Mencari tabel...';
      }
      var loadingEl = document.createElement('div');
      loadingEl.className = 'qb-more-loading';
      loadingEl.textContent = 'Memuat...';
      listEl.appendChild(loadingEl);

      var url = QB_API + '?action=vars&keyword=' + encodeURIComponent(kw) +
        '&subjectcsa=' + encodeURIComponent(subject) +
        '&page=' + qbVarPage;
      qbGet(url).then(function(json) {
        loadingEl.remove();
        qbVarLoading = false;
        if (mySeq !== qbSearchSeq) return;
        if (!json.success) {
          statusEl.textContent = 'Gagal mencari tabel: ' + json.message;
          return;
        }
        qbVarTotal = json.total || json.data.length;
        qbVarPages = json.pages || 1;
        if (qbVarPage === 1 && !json.data.length) {
          listEl.innerHTML = '<div class="qb-dim-empty">Tabel tidak tersedia. Coba kata kunci lain.</div>';
          statusEl.textContent = '';
          return;
        }
        statusEl.textContent = '';
        json.data.forEach(function(item) {
          if (qbTableIds[item.var_id]) return;
          qbTableIds[item.var_id] = true;
          qbAppendTableCard(item);
        });
        qbVarPage++;
        if (listEl.scrollHeight <= listEl.clientHeight + 10) qbLoadVarPage();
      }).catch(function() {
        loadingEl.remove();
        qbVarLoading = false;
        if (mySeq !== qbSearchSeq) return;
        statusEl.textContent = 'Gagal terhubung ke server. Coba lagi nanti.';
      });
    }

    function qbAppendTableCard(item) {
      var listEl = document.getElementById('qb-var-list');
      var d = document.createElement('div');
      d.className = 'qb-tcard' + (qbTable && String(qbTable.var_id) === String(item.var_id) ? ' sel' : '');
      d.textContent = item.title || ('Var ' + item.var_id);
      d.setAttribute('data-var', item.var_id);
      d.setAttribute('title', item.title || ('Var ' + item.var_id));
      d.addEventListener('click', function() {
        if (qbPicked.length >= QB_MAX_VARS && (!qbTable || String(qbTable.var_id) !== String(item.var_id))) return;
        if (qbTable && String(qbTable.var_id) === String(item.var_id)) qbSelectTable(null);
        else qbSelectTable(item);
      });
      listEl.appendChild(d);
    }

    function qbRefreshCardSel() {
      document.querySelectorAll('#qb-var-list .qb-tcard').forEach(function(el) {
        var on = qbTable && String(qbTable.var_id) === el.getAttribute('data-var');
        el.classList.toggle('sel', !!on);
      });
    }

    function qbSelectTable(item) {
      qbTable = item ? {
        var_id: String(item.var_id),
        title: item.title || ('Var ' + item.var_id),
        unit: item.unit || ''
      } : null;
      qbRefreshCardSel();
      qbClearValidity();
      if (!qbTable) {
        qbDimYear.clear();
        qbDimTur.clear();
        qbDimChar.clear();
        qbDimVer.clear();
        document.getElementById('qb-add-btn').disabled = true;
        return;
      }
      qbLoadDims(qbTable.var_id, null);
    }

    // Panel dimensi generik.
    // defaults: null = tak ada dicentang; 'ALL' = semua dicentang;
    // array id = yang cocok saja. singleAuto: centang otomatis bila 1 item.
    function qbMakeDim(o) {
      var box = document.getElementById(o.box);
      var sa = document.getElementById(o.sa);
      var saWrap = document.getElementById(o.saWrap);
      var err = document.getElementById(o.err);

      function syncSa() {
        var all = box.querySelectorAll('input[data-val]');
        var checked = box.querySelectorAll('input[data-val]:checked');
        sa.checked = all.length > 0 && all.length === checked.length;
      }
      sa.addEventListener('change', function() {
        box.querySelectorAll('input[data-val]').forEach(function(c) {
          c.checked = sa.checked;
        });
      });
      return {
        load: function(items, defaults, singleAuto) {
          box.innerHTML = '';
          saWrap.style.display = 'none';
          if (!items.length) {
            box.innerHTML = '<div class="qb-dim-empty">' + o.emptyNone + '</div>';
            return;
          }
          saWrap.style.display = '';
          sa.checked = false;
          var def = (defaults === 'ALL') ? items.map(function(x) {
            return String(x.id);
          }) : (defaults || []);
          if (singleAuto && items.length === 1) def = [String(items[0].id)];
          items.forEach(function(it) {
            var lab = document.createElement('label');
            lab.className = 'qb-opt';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = o.name;
            cb.setAttribute('data-val', it.id);
            cb.setAttribute('data-label', it.label);
            cb.checked = def.indexOf(String(it.id)) !== -1;
            cb.addEventListener('change', syncSa);
            lab.appendChild(cb);
            var sp = document.createElement('span');
            sp.textContent = it.label;
            lab.appendChild(sp);
            box.appendChild(lab);
          });
          syncSa();
        },
        get: function() {
          var out = [];
          box.querySelectorAll('input[data-val]:checked').forEach(function(c) {
            out.push({
              value: c.getAttribute('data-val'),
              label: c.getAttribute('data-label')
            });
          });
          return out;
        },
        loading: function() {
          box.innerHTML = '<div class="qb-dim-empty">Memuat...</div>';
          saWrap.style.display = 'none';
        },
        clear: function() {
          box.innerHTML = '<div class="qb-dim-empty">' + o.empty + '</div>';
          saWrap.style.display = 'none';
        },
        setValid: function(v) {
          box.classList.remove('qb-box-ok', 'qb-box-err');
          if (v === true) box.classList.add('qb-box-ok');
          if (v === false) box.classList.add('qb-box-err');
          err.style.display = v === false ? '' : 'none';
        }
      };
    }

    var qbDimYear = null,
      qbDimTur = null,
      qbDimChar = null,
      qbDimVer = null;

    function qbClearValidity() {
      qbValid = {};
      if (qbDimYear) {
        qbDimYear.setValid(undefined);
        qbDimTur.setValid(undefined);
        qbDimChar.setValid(undefined);
        qbDimVer.setValid(undefined);
      }
    }

    // Muat 4 dimensi tabel terpilih.
    function qbLoadDims(varId, preThVals) {
      var mySeq = ++qbDimSeq;
      qbCharExist = true;
      qbClearValidity();
      document.getElementById('qb-add-btn').disabled = true;
      qbDimYear.loading();
      qbDimTur.loading();
      qbDimChar.loading();
      qbDimVer.loading();
      qbDimsPending = 4;
      var done = function() {
        if (mySeq !== qbDimSeq) return;
        qbDimsPending--;
        if (qbDimsPending === 0) {
          document.getElementById('qb-add-btn').disabled = qbPicked.length >= QB_MAX_VARS;
        }
      };

      qbGet(QB_API + '?action=th&var=' + encodeURIComponent(varId)).then(function(json) {
        if (mySeq !== qbDimSeq) return;
        var items = json.success ? json.data : [];
        qbDimYear.load(items, preThVals || null, false);
      }).catch(function() {
        if (mySeq !== qbDimSeq) return;
        qbDimYear.load([], null, false);
      }).finally(done);

      qbGet(QB_API + '?action=turth&var=' + encodeURIComponent(varId)).then(function(json) {
        if (mySeq !== qbDimSeq) return;
        var items = json.success ? json.data : [];
        qbDimTur.load(items, null, true);
      }).catch(function() {
        if (mySeq !== qbDimSeq) return;
        qbDimTur.load([], null, false);
      }).finally(done);

      qbGet(QB_API + '?action=turvar&var=' + encodeURIComponent(varId)).then(function(json) {
        if (mySeq !== qbDimSeq) return;
        var items = json.success ? json.data : [];
        if (!items.length) {
          qbCharExist = false;
          document.getElementById('qb-box-turvar').innerHTML =
            '<div class="qb-dim-empty">Tabel ini tidak memerlukan karakteristik.</div>';
          return;
        }
        qbCharExist = true;
        qbDimChar.load(items, null, true);
      }).catch(function() {
        if (mySeq !== qbDimSeq) return;
        qbDimChar.load([], null, false);
      }).finally(done);

      qbGet(QB_API + '?action=vervar&var=' + encodeURIComponent(varId)).then(function(json) {
        if (mySeq !== qbDimSeq) return;
        var items = json.success ? json.data : [];
        qbDimVer.load(items, 'ALL', false);
      }).catch(function() {
        if (mySeq !== qbDimSeq) return;
        qbDimVer.load([], null, false);
      }).finally(done);
    }

    // Tambah ke Data Terpilih.
    function qbAddCurrent() {
      if (!qbTable || qbDimsPending > 0) return;
      var years = qbDimYear.get();
      var turs = qbDimTur.get();
      var chars = qbDimChar.get();
      var vers = qbDimVer.get();
      var vYear = years.length > 0;
      var vTur = turs.length > 0;
      var vChar = !qbCharExist || chars.length > 0;
      var vVer = vers.length > 0;
      qbValid = {
        year: vYear,
        turyear: vTur,
        characteristic: vChar,
        vervar: vVer
      };
      qbDimYear.setValid(vYear);
      qbDimTur.setValid(vTur);
      qbDimChar.setValid(vChar);
      qbDimVer.setValid(vVer);
      if (!(vYear && vTur && vChar && vVer)) return;
      if (qbPicked.length >= QB_MAX_VARS) return;
      if (qbPicked.some(function(p) {
          return p.table.id === qbTable.var_id;
        })) {
        document.getElementById('qb-search-status').textContent =
          'Tabel ini sudah ada di Data Terpilih. Hapus dulu bila ingin mengubah dimensinya.';
        return;
      }
      years.sort(function(a, b) {
        return String(a.value).localeCompare(String(b.value), undefined, {
          numeric: true
        });
      });
      qbPicked.push({
        table: {
          id: qbTable.var_id,
          title: qbTable.title,
          unit: qbTable.unit
        },
        year: years,
        turyear: turs,
        characteristic: chars,
        vervar: vers
      });
      qbSelectTable(null);
      qbRenderPicked();
    }

    function qbRemovePicked(i) {
      qbPicked.splice(i, 1);
      if (qbModalIdx === i) qbCloseModal();
      else if (qbModalIdx > i) qbModalIdx--;
      qbRenderPicked();
    }

    function qbRenderPicked() {
      var list = document.getElementById('qb-picked-list');
      list.innerHTML = '';
      document.getElementById('qb-maxwarn').style.opacity = qbPicked.length >= QB_MAX_VARS ? '1' : '0';
      qbPicked.forEach(function(p, i) {
        var wrap = document.createElement('div');
        wrap.className = 'qb-picked-item';
        var del = document.createElement('button');
        del.type = 'button';
        del.className = 'qb-del';
        del.textContent = '✕';
        del.title = 'Hapus';
        del.addEventListener('click', function(e) {
          e.stopPropagation();
          qbRemovePicked(i);
        });
        var info = document.createElement('div');
        info.style.flex = '1';
        info.style.minWidth = '0';
        var t = document.createElement('p');
        t.className = 't';
        t.textContent = p.table.title;
        var badges = document.createElement('div');
        [
          [p.year.length + ' Tahun', 'qb-b-blue'],
          [p.turyear.length + ' Turunan Tahun', 'qb-b-green'],
          [p.characteristic.length + ' Karakteristik', 'qb-b-red'],
          [p.vervar.length + ' Judul Baris', 'qb-b-yellow']
        ].forEach(function(b) {
          var s = document.createElement('span');
          s.className = 'qb-badge ' + b[1];
          s.textContent = b[0];
          badges.appendChild(s);
        });
        info.appendChild(t);
        info.appendChild(badges);
        info.addEventListener('click', function() {
          qbOpenModal(i);
        });
        wrap.appendChild(del);
        wrap.appendChild(info);
        list.appendChild(wrap);
      });
      document.getElementById('qb-submit-btn').disabled = !qbPicked.length || qbSubmitting;
      document.getElementById('qb-add-btn').disabled = !qbTable || qbDimsPending > 0 || qbPicked.length >= QB_MAX_VARS;
    }

    function qbChipList(elId, pairs, cls) {
      var el = document.getElementById(elId);
      el.innerHTML = '';
      if (!pairs.length) {
        el.innerHTML = '<span style="color:#888; font-size:15px;">-</span>';
        return;
      }
      pairs.forEach(function(p) {
        var s = document.createElement('span');
        s.className = 'qb-chip ' + cls;
        s.textContent = p.label || p.value;
        el.appendChild(s);
      });
    }

    function qbOpenModal(i) {
      var p = qbPicked[i];
      if (!p) return;
      qbModalIdx = i;
      document.getElementById('qb-modal-title').textContent = p.table.title;
      qbChipList('qb-modal-year', p.year, 'qb-b-blue');
      qbChipList('qb-modal-turyear', p.turyear, 'qb-b-green');
      qbChipList('qb-modal-char', p.characteristic, 'qb-b-red');
      qbChipList('qb-modal-vervar', p.vervar, 'qb-b-yellow');
      document.getElementById('qb-modal-del').onclick = function() {
        qbRemovePicked(i);
      };
      document.getElementById('qb-modal').style.display = 'flex';
    }

    function qbCloseModal() {
      qbModalIdx = -1;
      document.getElementById('qb-modal').style.display = 'none';
    }

    function qbResetAll() {
      qbSelectTable(null);
      qbPicked = [];
      qbModalIdx = -1;
      qbCloseModal();
      document.getElementById('qb-table-search').value = '';
      document.getElementById('qb-result-card').style.display = 'none';
      document.getElementById('qb-results').innerHTML = '';
      document.getElementById('qb-search-status').textContent = '';
      qbGet(QB_API + '?action=subjectcsa').then(function(json) {
        if (json.success) {
          qbAllSubjects = json.data;
          qbFillSubjectBySubcat();
        }
      }).catch(function() {});
      qbSearchTables(true);
      qbRenderPicked();
    }

    // Deep-link ?var=4&th=1: langsung muat dimensi tabel.
    function qbDeepLink(varId, preTh) {
      qbTable = {
        var_id: String(varId),
        title: 'Tabel var ' + varId,
        unit: ''
      };
      qbRefreshCardSel();
      document.getElementById('qb-search-status').textContent =
        'Tabel var ' + varId + ' dimuat via link langsung. Atur dimensi lalu klik "Tambah".';
      qbLoadDims(String(varId), preTh ? String(preTh).split(';') : null);
    }

    // Cocokkan entri dimensi respons dengan pilihan user (cek semua kandidat key).
    function qbEntryMatches(entry, selected) {
      if (!selected.length) return true;
      var cands = [entry.val, entry.turth_id, entry.tur_id, entry.turvar_id,
        entry.item_ver_id, entry.vervar_id, entry.th_id, entry.id
      ];
      for (var i = 0; i < cands.length; i++) {
        if (cands[i] !== undefined && selected.indexOf(String(cands[i])) !== -1) return true;
      }
      return false;
    }

    function qbSubmit() {
      var resCard = document.getElementById('qb-result-card');
      var resEl = document.getElementById('qb-results');
      if (qbSubmitting) return;
      if (!qbPicked.length) return;
      qbSubmitting = true;
      document.getElementById('qb-submit-btn').disabled = true;
      resCard.style.display = 'block';
      resEl.innerHTML = '';

      var st = document.createElement('div');
      st.className = 'qb-status';
      st.textContent = 'Mengambil data...';
      resEl.appendChild(st);

      var pending = qbPicked.length;
      var finishOne = function() {
        pending--;
        if (pending === 0) {
          st.remove();
          qbSubmitting = false;
          document.getElementById('qb-submit-btn').disabled = !qbPicked.length;
        }
      };
      qbPicked.forEach(function(pick) {
        var varId = pick.table.id;
        var thVals = pick.year.map(function(x) {
          return x.value;
        });
        var turthVals = pick.turyear.map(function(x) {
          return x.value;
        });
        var turvarVals = pick.characteristic.map(function(x) {
          return x.value;
        });
        var vervarVals = pick.vervar.map(function(x) {
          return x.value;
        });
        // Tahun boleh lebih dari 2: server mengambil bertahap otomatis.
        // Filter dimensi lain yang kosong = semua.
        qbQuery[varId] = {
          th: thVals.join(';'),
          turvar: turvarVals.join(';'),
          vervar: vervarVals.join(';'),
          turth: turthVals.join(';')
        };
        var q = QB_API + '?action=data&var=' + encodeURIComponent(varId) +
          '&th=' + encodeURIComponent(thVals.join(';')) +
          (turvarVals.length ? '&turvar=' + encodeURIComponent(turvarVals.join(';')) : '') +
          (vervarVals.length ? '&vervar=' + encodeURIComponent(vervarVals.join(';')) : '') +
          (turthVals.length ? '&turth=' + encodeURIComponent(turthVals.join(';')) : '');

        qbGet(q).then(function(json) {
          if (!json.success) {
            var err = document.createElement('div');
            err.className = 'qb-error';
            err.textContent = 'Tabel var ' + varId + ' gagal: ' + json.message;
            resEl.appendChild(err);
          } else {
            qbRenderVar(varId, {
              title: pick.table.title
            }, json.data, {
              th: thVals,
              turth: turthVals,
              turvar: turvarVals,
              vervar: vervarVals
            }, resEl, q);
          }
          finishOne();
        }).catch(function() {
          var err2 = document.createElement('div');
          err2.className = 'qb-error';
          err2.textContent = 'Tabel var ' + varId + ': gagal terhubung ke server.';
          resEl.appendChild(err2);
          finishOne();
        });
      });
      resCard.scrollIntoView({
        behavior: 'smooth'
      });
    }

    function qbRenderVar(varId, item, json, sel, resEl, queryUrl) {
      var dc = json.datacontent || {};
      var vervarList = json.vervar || [];
      var turvarList = json.turvar || [];
      var tahunList = json.tahun || [];
      var turtahunList = json.turtahun || [{
        val: 0,
        label: 'Tahun'
      }];

      var vervars = vervarList.filter(function(e) {
        return qbEntryMatches(e, sel.vervar);
      });
      var turvars = turvarList.filter(function(e) {
        return qbEntryMatches(e, sel.turvar);
      });
      var tahuns = tahunList.filter(function(e) {
        return qbEntryMatches(e, sel.th);
      });
      var turths = turtahunList.filter(function(e) {
        return qbEntryMatches(e, sel.turth);
      });
      if (!tahuns.length) tahuns = tahunList;
      if (!turths.length) turths = turtahunList;

      // Kolom periode = kombinasi tahun x turtahun yang punya ≥1 sel terisi.
      var periods = [];
      tahuns.forEach(function(t) {
        turths.forEach(function(tt) {
          var has = vervars.some(function(vv) {
            return turvars.some(function(tv) {
              var key = String(vv.val) + String(varId) + String(tv.val) + String(t.val) + String(tt.val);
              return dc[key] !== undefined && dc[key] !== '';
            });
          });
          if (has) periods.push({
            tahun: t,
            turth: tt
          });
        });
      });

      var h = document.createElement('h3');
      h.className = 'qb-result-title';
      // Judul & satuan asli dari respons model=data (field "var"),
      // jatuh kembali ke judul hasil pencarian / var_id.
      var realVar = (json.var && json.var[0]) || {};
      h.textContent = realVar.label || item.title || ('Tabel var ' + varId);
      resEl.appendChild(h);
      if (realVar.unit) {
        var u = document.createElement('div');
        u.className = 'qb-meta';
        u.textContent = 'Satuan: ' + realVar.unit +
          (realVar.subj ? ' · Subjek: ' + realVar.subj : '') +
          (json.last_update ? ' · Update: ' + json.last_update : '');
        resEl.appendChild(u);
      }

      if (!vervars.length || !periods.length) {
        var none = document.createElement('div');
        none.className = 'qb-error';
        none.textContent = 'Tidak ada data untuk kombinasi dimensi yang dipilih pada var ' + varId + '.';
        resEl.appendChild(none);
        return;
      }

      var subAnnual = turtahunList.length > 1;
      var periodLabel = function(p) {
        var tl = p.tahun.label || p.tahun.val;
        if (subAnnual && p.turth.label && p.turth.label !== 'Tahun') return p.turth.label + ' ' + tl;
        return tl;
      };

      // Tab karakteristik kalau >1.
      var activeIdx = 0;
      var tabsEl = null;
      var tableWrap = document.createElement('div');
      if (turvars.length > 1) {
        tabsEl = document.createElement('div');
        tabsEl.className = 'qb-tabs';
        turvars.forEach(function(tv, i) {
          var b = document.createElement('button');
          b.className = 'qb-tab' + (i === 0 ? ' active' : '');
          b.textContent = tv.label || tv.val;
          b.addEventListener('click', function() {
            activeIdx = i;
            tabsEl.querySelectorAll('.qb-tab').forEach(function(x) {
              x.classList.remove('active');
            });
            b.classList.add('active');
            drawTable();
          });
          tabsEl.appendChild(b);
        });
        resEl.appendChild(tabsEl);
      }

      function cellVal(vv, tv, p) {
        var key = String(vv.val) + String(varId) + String(tv.val) + String(p.tahun.val) + String(p.turth.val);
        return dc[key] !== undefined ? dc[key] : '';
      }

      function drawTable() {
        tableWrap.innerHTML = '';
        var tv = turvars[activeIdx] || turvars[0];
        var wrap = document.createElement('div');
        wrap.className = 'table-wrap';
        var tbl = document.createElement('table');
        tbl.className = 'data-tabel';
        var thead = document.createElement('thead');
        var hr = document.createElement('tr');
        ['Judul Baris'].forEach(function(c) {
          var th = document.createElement('th');
          th.textContent = c;
          hr.appendChild(th);
        });
        periods.forEach(function(p) {
          var th = document.createElement('th');
          th.textContent = periodLabel(p);
          hr.appendChild(th);
        });
        thead.appendChild(hr);
        tbl.appendChild(thead);
        var tb = document.createElement('tbody');
        vervars.forEach(function(vv) {
          var tr = document.createElement('tr');
          var td0 = document.createElement('td');
          td0.textContent = vv.label || vv.val;
          tr.appendChild(td0);
          periods.forEach(function(p) {
            var td = document.createElement('td');
            td.textContent = cellVal(vv, tv, p);
            tr.appendChild(td);
          });
          tb.appendChild(tr);
        });
        tbl.appendChild(tb);
        wrap.appendChild(tbl);
        tableWrap.appendChild(wrap);

        // Tombol unduh: data PENUH (semua karakteristik & baris terpilih,
        // bukan cuma tab aktif) via endpoint server CSV dan XLSX asli.
        var dlRow = document.createElement('div');
        dlRow.style.marginTop = '10px';
        dlRow.style.display = 'flex';
        dlRow.style.gap = '8px';
        dlRow.style.flexWrap = 'wrap';
        var qParam = qbQuery[varId] || {
          th: '',
          turvar: '',
          vervar: '',
          turth: ''
        };
        var dlBase = QB_DL + '?domain=' + encodeURIComponent(QB_DOMAIN) +
          '&var=' + encodeURIComponent(varId) +
          '&th=' + encodeURIComponent(qParam.th) +
          '&turvar=' + encodeURIComponent(qParam.turvar) +
          '&vervar=' + encodeURIComponent(qParam.vervar) +
          '&turth=' + encodeURIComponent(qParam.turth);
        [
          ['csv', 'Unduh CSV'],
          ['xlsx', 'Unduh XLSX']
        ].forEach(function(pair) {
          var a = document.createElement('a');
          a.className = 'qb-btn qb-btn-secondary';
          a.style.textDecoration = 'none';
          a.style.display = 'inline-block';
          a.href = dlBase + '&format=' + pair[0];
          a.textContent = pair[1];
          dlRow.appendChild(a);
        });
        tableWrap.appendChild(dlRow);

        var meta = document.createElement('p');
        meta.className = 'qb-meta';
        meta.textContent = vervars.length + ' baris × ' + periods.length + ' periode' +
          (turvars.length > 1 ? ' · karakteristik: ' + (tv.label || tv.val) : '') +
          ' · cache 1 jam.';
        tableWrap.appendChild(meta);

        var apiBox = document.createElement('div');
        apiBox.className = 'qb-api-url';
        apiBox.textContent = 'GET ' + queryUrl.replace(/bps_query_api\.php\?/, 'webapi.bps.go.id/v1/api/list/model/data/lang/ind/domain/1200/') + '/key/****/';
        tableWrap.appendChild(apiBox);
      }

      resEl.appendChild(tableWrap);
      drawTable();
    }

    window.onload = qbInit;
  </script>
</body>

</html>