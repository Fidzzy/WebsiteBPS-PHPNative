<?php require_once '../../includes/auth.php';
requireAdmin(); ?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Edit Publikasi</title>
  <link rel="stylesheet" href="../../assets/css/myCSS2.css" />
  <script src="../../assets/js/nav.js" defer></script>
  <style>
    .sampul-lama-preview {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 4px 0;
    }

    .sampul-lama-preview img {
      border: 1px solid #ccc;
      border-radius: 4px;
    }
  </style>
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
      <a href="#">Home</a>
      <a href="page09A.php">Publikasi</a>
      <a href="page09C.php">Tambah Publikasi</a>
      <a href="../galeri/page09G.php">Galeri Kegiatan</a>
      <!--<span style="color:#fff; margin-left:10px;">
        <?= htmlspecialchars($_SESSION['nama']) ?> (<?= htmlspecialchars($_SESSION['role']) ?>)
      </span>
      <a href="../auth/logout.php">Logout</a>-->
      <div class="profile-dropdown">
        <!-- Ikon profil berbasis SVG agar tidak perlu mengunduh gambar eksternal -->
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
    </nav>
  </header>

  <main>
    <h2 style="text-align: left; padding-left: 10%; color: #333">
      Formulir Ubah Data Publikasi
    </h2>

    <div class="kotak-form">
      <p id="pesanSukses"></p>
      <p id="pesanError"></p>
      <form
        name="formEditPublikasi"
        action="page09E_action.php"
        method="post"
        enctype="multipart/form-data"
        onsubmit="return validate09E();">

        <!-- Hidden field untuk menyimpan nama sampul lama -->
        <input type="hidden" name="sampul_lama" value="<?= htmlspecialchars($_GET['sampul'] ?? ''); ?>" />
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>" />
        <!-- ID internal (kolom `no`) tetap dikirim tersembunyi untuk proses UPDATE,
             tapi tidak lagi ditampilkan sebagai kolom "Nomor" di form -->
        <input type="hidden" name="nomor" value="<?= htmlspecialchars($_GET['no'] ?? ''); ?>" />

        <table border="0" cellpadding="8" cellspacing="0">
          <tr>
            <td><label for="judul">Judul:</label></td>
            <td>
              <input
                type="text"
                id="judul"
                name="judul"
                value="<?= htmlspecialchars($_GET['judul'] ?? ''); ?>" />
            </td>
          </tr>
          <tr>
            <td><label for="tanggal">Tanggal Rilis:</label></td>
            <td>
              <input
                type="date"
                id="tanggal"
                name="tanggal"
                value="<?= htmlspecialchars($_GET['tanggal'] ?? ''); ?>" />
            </td>
          </tr>
          <tr>
            <td><label for="link_publikasi">Link Publikasi:</label></td>
            <td>
              <input
                type="url"
                id="link_publikasi"
                name="link_publikasi"
                value="<?= htmlspecialchars($_GET['link'] ?? ''); ?>"
                style="width: 100%; box-sizing: border-box;" required />
            </td>
          </tr>
          <tr>
            <td><label>Sampul Lama:</label></td>
            <td>
              <div class="sampul-lama-preview">
                <img
                  src="../../assets/img/<?= htmlspecialchars($_GET['sampul'] ?? ''); ?>"
                  alt="No Image"
                  width="70px"
                  onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';" />
                <span style="display:none; color:#999; font-size:15px; font-style:italic;">Gambar tidak ditemukan</span>
                <span style="font-size:15px; color:#555;"><?= htmlspecialchars($_GET['sampul'] ?? '-'); ?></span>
              </div>
            </td>
          </tr>
          <tr>
            <td><label for="sampul_baru">Sampul Baru:</label></td>
            <td>
              <input
                type="file"
                id="sampul_baru"
                name="sampul_baru"
                accept=".jpg, .jpeg, .png, .gif, .webp" />
              <br /><small style="color:#888;">Kosongkan jika tidak ingin mengganti sampul.</small>
            </td>
          </tr>
          <tr>
            <td></td>
            <td>
              <input type="submit" value="Ubah Data" />
              &nbsp;
              <a href="page09A.php" style="text-decoration:none;">
                <button type="button" style="padding:12px 20px; border:1px solid #034f84; background:#fff; color:#034f84; border-radius:5px; cursor:pointer;">Batal</button>
              </a>
            </td>
          </tr>
        </table>
      </form>
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
            <a href="https://manual-website-bps.readthedocs.io/" target="_blank">Manual</a>
            <a href="https://sumut.bps.go.id/id/term-of-use" target="_blank">S&K</a>
            <a href="https://sumut.bps.go.id/id/tautan" target="_blank">Daftar Tautan</a>
          </div>
        </div>
        <div class="footer-col">
          <h4>Tentang Kami</h4>
          <ul>
            <li><a href="https://ppid.bps.go.id/app/konten/1200/Profil-BPS.html" target="_blank">Profil BPS</a></li>
            <li><a href="https://ppid.bps.go.id/?mfd=1200" target="_blank">PPID</a></li>
            <li><a href="https://ppid.bps.go.id/app/konten/0000/Layanan-BPS.html#pills-3" target="_blank">Kebijakan Diseminasi</a></li>
          </ul>
        </div>
        <div class="footer-col">
          <h4>Tautan Lainnya</h4>
          <ul>
            <li><a href="https://www.aseanstats.org/" target="_blank">ASEAN Stats</a></li>
            <li><a href="https://rb.bps.go.id/" target="_blank">Reformasi Birokrasi</a></li>
            <li><a href="https://lpse.bps.go.id/" target="_blank">Layanan Pengadaan Secara Elektronik</a></li>
            <li><a href="https://stis.ac.id/" target="_blank">Politeknik Statistika STIS</a></li>
            <li><a href="https://pusdiklat.bps.go.id/" target="_blank">Pusdiklat BPS</a></li>
            <li><a href="https://jdih.bps.go.id/" target="_blank">JDIH BPS</a></li>
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
</body>

</html>