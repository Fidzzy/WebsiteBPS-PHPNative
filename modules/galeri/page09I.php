<?php
require_once '../../includes/auth.php';
requireAdmin();

$id     = $_GET['id'] ?? '';
$judul  = $_GET['judul'] ?? '';
$urutan = $_GET['urutan'] ?? '0';
$gambar = $_GET['gambar'] ?? '';

if ($id === '') {
  header('Location: page09G.php');
  exit;
}
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Edit Galeri Kegiatan</title>
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
      <a href="../publikasi/page09A.php">Publikasi</a>
      <a href="../publikasi/page09C.php">Tambah Publikasi</a>
      <a class="active" href="page09G.php">Galeri Kegiatan</a>
      <span style="color:#fff; margin-left:10px;">
        <?= htmlspecialchars($_SESSION['nama']) ?> (<?= htmlspecialchars($_SESSION['role']) ?>)
      </span>
      <a href="../auth/logout.php">Logout</a>
    </nav>
  </header>

  <main>
    <h2 style="text-align: center; margin-bottom: 20px; color: #333">
      Edit Foto Galeri Kegiatan
    </h2>

    <p id="pesanError" style="display:none; width:60%; margin:0 auto 15px auto; padding:12px 16px; background-color:#fff3f3; border:1px solid #e74c3c; border-radius:4px; color:#c0392b; font-size:16px;"></p>

    <div class="kotak-form">
      <div class="sampul-lama-preview" style="justify-content:center; margin-bottom:15px;">
        <img src="../../assets/img/<?= htmlspecialchars($gambar) ?>" alt="Foto saat ini" width="120" style="border-radius:6px;">
        <span style="font-size:15px; color:#777;">Foto saat ini<br>(biarkan kosong kalau tidak ingin ganti)</span>
      </div>

      <form
        name="formEditGaleri"
        action="page09I_action.php"
        method="post"
        enctype="multipart/form-data"
        onsubmit="return validateGaleriEdit();">
        <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>" />
        <input type="hidden" name="gambar_lama" value="<?= htmlspecialchars($gambar) ?>" />
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>" />

        <table border="0" cellpadding="8" cellspacing="0">
          <tr>
            <td width="150"><label for="judul">Judul Kegiatan:</label></td>
            <td><input type="text" id="judul" name="judul" value="<?= htmlspecialchars($judul) ?>" required /></td>
          </tr>
          <tr>
            <td><label for="urutan">Urutan Tampil:</label></td>
            <td><input type="number" id="urutan" name="urutan" min="0" value="<?= htmlspecialchars($urutan) ?>" required /></td>
          </tr>
          <tr>
            <td><label for="gambar_baru">Ganti Foto (opsional):</label></td>
            <td>
              <input type="file" id="gambar_baru" name="gambar_baru" accept=".jpg,.jpeg,.png,.gif,.webp" />
              <div style="font-size:14px; color:#777; margin-top:6px;">
                Format: .jpg .jpeg .png .gif .webp
              </div>
            </td>
          </tr>
          <tr>
            <td></td>
            <td><input type="submit" value="Simpan Perubahan" /></td>
          </tr>
        </table>
      </form>
      <p style="text-align:center; margin-top:10px; font-size:16px;">
        <a href="page09G.php" style="color:#034f84;">&larr; Kembali ke Galeri</a>
      </p>
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
</body>

</html>