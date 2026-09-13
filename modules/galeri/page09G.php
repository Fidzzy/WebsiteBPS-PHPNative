<?php
include '../../config/dbconn.php';
require_once '../../includes/auth.php';
// Halaman publik: galeri bisa dilihat tanpa login.

$galeriList = $pdo->query('SELECT * FROM galeri ORDER BY urutan ASC, id ASC')->fetchAll();
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Galeri Kegiatan BPS Sumatera Utara</title>
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
          <a href="../publikasi/page09A.php">Publikasi</a>
          <a href="../publikasi/berita.php">Berita Resmi Statistik</a>
          <a href="../publikasi/katalog.php">Statistik Berdasarkan Subjek</a>
          <a href="../publikasi/data_dinamis.php">Tabel Dinamis</a>
          <a href="../publikasi/exim.php">Data Ekspor Impor</a>
          <a href="../publikasi/pers.php">Berita dan Siaran Pers</a>
          <a href="../publikasi/infografis.php">Infografis</a>
          <a href="https://sensus.bps.go.id" target="_blank" rel="noopener">Data Sensus</a>
          <a href="https://direktori.web.bps.go.id" target="_blank" rel="noopener">Direktori</a>
          <a href="https://sirusa.web.bps.go.id/metadata" target="_blank" rel="noopener">Metadata</a>
        </div>
      </div>

      <?php if (isAdmin()): ?>
        <a href="../publikasi/page09C.php">Tambah Publikasi</a>
      <?php endif; ?>
      <a class="active" href="../galeri/page09G.php">Galeri Kegiatan</a>
      <?php if (isAdmin()): ?>
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
      <?php else: ?>
        <a href="../auth/login.php">Login</a>
      <?php endif; ?>
    </nav>
  </header>

  <main>
    <h2 style="text-align: center; margin-bottom: 20px; color: #333">
      Galeri Kegiatan BPS Provinsi Sumatera Utara
    </h2>

    <?php if (empty($galeriList)): ?>
      <p class="hasil-kosong">Belum ada foto kegiatan.</p>
    <?php else: ?>
      <div class="galeri-container">
        <div class="galeri-preview">
          <img
            id="previewGambar"
            src="../../assets/img/<?= htmlspecialchars($galeriList[0]['gambar']) ?>"
            alt="Preview Kegiatan" />
          <p class="galeri-judul" id="judulPreview">
            <?= htmlspecialchars($galeriList[0]['judul']) ?>
          </p>
        </div>

        <div class="galeri-thumbnail" id="thumbnailArea">
          <?php foreach ($galeriList as $item): ?>
            <img
              src="../../assets/img/<?= htmlspecialchars($item['gambar']) ?>"
              alt="<?= htmlspecialchars($item['judul']) ?>"
              title="<?= htmlspecialchars($item['judul']) ?>"
              onclick="gantiPreview(this, <?= htmlspecialchars(json_encode($item['judul'], JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP)) ?>)" />
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (isAdmin()): ?>
      <div class="kelola-galeri">
        <div class="kelola-galeri-header">
          <h3 style="margin:0; color:#333;">Kelola Galeri</h3>
          <a href="page09H.php" class="filter-submit" style="display:inline-block; width:auto; margin:0; text-decoration:none; padding:10px 18px;">
            + Tambah Galeri
          </a>
        </div>

        <div class="tabel-publikasi">
          <table class="data-tabel">
            <tr>
              <th width="60">Urutan</th>
              <th width="100">Gambar</th>
              <th>Judul</th>
              <th width="100">Aksi</th>
            </tr>
            <?php foreach ($galeriList as $item): ?>
              <tr>
                <td align="center"><?= (int) $item['urutan'] ?></td>
                <td align="center">
                  <img src="../../assets/img/<?= htmlspecialchars($item['gambar']) ?>" alt="" width="70" style="border-radius:4px;">
                </td>
                <td><?= htmlspecialchars($item['judul']) ?></td>
                <td align="center">
                  <div style="display:grid; justify-content:center; gap:15px;">
                    <a href="page09I.php?id=<?= urlencode($item['id']) ?>&judul=<?= urlencode($item['judul']) ?>&urutan=<?= urlencode($item['urutan']) ?>&gambar=<?= urlencode($item['gambar']) ?>" style="display:inline-flex; align-items:center; gap:5px; text-decoration:none; color:#034f84; font-weight:bold;">
                      <img src="../../assets/img/edit.png" style="width:15px;height:15px;" alt="Edit"> Edit
                    </a>
                    <form method="post" action="page09J.php" onsubmit="return confirm('Yakin ingin menghapus foto ini?');" style="margin:0;">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                      <input type="hidden" name="id" value="<?= htmlspecialchars($item['id']) ?>">
                      <input type="hidden" name="gambar" value="<?= htmlspecialchars($item['gambar']) ?>">
                      <button type="submit" style="display:inline-flex; align-items:center; gap:5px; background:none; border:none; padding:0; cursor:pointer; text-decoration:none; color:#d9534f; font-weight:bold; font:inherit;">
                        <img src="../../assets/img/delete.png" style="width:15px;height:15px;" alt="Hapus"> Hapus
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>
      </div>
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
    window.onload = function() {
      var thumbnails = document.querySelectorAll(".galeri-thumbnail img");
      if (thumbnails.length > 0) {
        thumbnails[0].classList.add("aktif");
        document.getElementById("previewGambar").src = thumbnails[0].src;
        document.getElementById("previewGambar").alt = thumbnails[0].alt;
      }
    };

    function gantiPreview(elThumb, judul) {
      var preview = document.getElementById("previewGambar");
      preview.src = elThumb.src;
      preview.alt = elThumb.alt;
      document.getElementById("judulPreview").textContent = judul;
      var semuaThumb = document.querySelectorAll(".galeri-thumbnail img");
      for (var i = 0; i < semuaThumb.length; i++) {
        semuaThumb[i].classList.remove("aktif");
      }
      elThumb.classList.add("aktif");
    }
  </script>
  <script src="../../assets/js/validasiForm.js"></script>
</body>

</html>