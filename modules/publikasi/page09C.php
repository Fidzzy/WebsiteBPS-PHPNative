<?php require_once '../../includes/auth.php';
requireAdmin(); ?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Form Publikasi Baru</title>
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
        <button class="nav-dropbtn">Produk ▾</button>
        <div class="nav-dropdown-content">
          <a href="page09A.php">Publikasi</a>
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

      <a class="active" href="page09C.php">Tambah Publikasi</a>
      <a href="../galeri/page09G.php">Galeri Kegiatan</a>
      <!-- <span style="color:#fff; margin-left:10px;">
        <?= htmlspecialchars($_SESSION['nama']) ?> (<?= htmlspecialchars($_SESSION['role']) ?>)
      </span>
      <a href="../auth/logout.php">Logout</a> -->
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
      Form Tambah Publikasi Manual
    </h2>
    <p style="padding-left: 10%; color: #666; font-size: 15px; margin-top: -8px;">
      Daftar publikasi utama diambil otomatis dari web BPS via API. Form ini hanya untuk menambah entri manual tambahan.
    </p>

    <?php if (!empty($_GET['status'])): ?>
      <?php if ($_GET['status'] === 'sukses'): ?>
        <p id="pesanSuksesDb" style="display:block; width:60%; margin:0 auto 15px auto; padding:12px 16px; background-color:#d4edda; border:1px solid #28a745; border-radius:4px; color:#155724; font-size:16px;">
          Publikasi <strong><?= htmlspecialchars($_GET['judul'] ?? '') ?></strong> berhasil ditambahkan!
        </p>

        <script>
          setTimeout(function() {
            var pesanSukses = document.getElementById("pesanSuksesDb");
            if (pesanSukses) {
              pesanSukses.style.transition = "opacity 0.6s";
              pesanSukses.style.opacity = "0";
              setTimeout(function() {
                pesanSukses.style.display = "none";
              }, 600);
            }
          }, 4000);
        </script>

      <?php elseif ($_GET['status'] === 'gagal'): ?>
        <p id="pesanErrorDb" style="display:block; width:60%; margin:0 auto 15px auto; padding:12px 16px; background-color:#fff3f3; border-radius:4px; color:#c0392b; font-size:16px;">
          <?= htmlspecialchars($_GET['pesan'] ?? 'Terjadi kesalahan.') ?>
        </p>
      <?php endif; ?>
    <?php endif; ?>

    <div class="kotak-form">
      <form
        name="formTambahPublikasi"
        action="page09C_action.php"
        method="post"
        enctype="multipart/form-data"
        onsubmit="return validate06C();">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>" />
        <table border="0" cellpadding="8" cellspacing="0">
          <tr>
            <td width="150"><label for="judul">Judul:</label></td>
            <td><input type="text" id="judul" name="judul" required /></td>
          </tr>
          <tr>
            <td><label for="tanggal">Tanggal Rilis:</label></td>
            <td><input type="date" id="tanggal" name="tanggal" required /></td>
          </tr>
          <tr>
            <td><label for="link_publikasi">Link Publikasi:</label></td>
            <td><input type="url" id="link_publikasi" name="link_publikasi" placeholder="https://sumut.bps.go.id/..." required style="width: 100%; box-sizing: border-box;" /></td>
          </tr>
          <tr>
            <td><label for="sampul">Sampul:</label></td>
            <td>
              <input
                type="file"
                id="sampul"
                name="sampul"
                accept=".jpg,.jpeg,.png,.gif,.webp" />
              <br /><small style="color:#888;">Opsional. Format: .jpg, .jpeg, .png, .gif, .webp</small>
            </td>
          </tr>
          <tr>
            <td></td>
            <td><input type="submit" value="Tambah" /></td>
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