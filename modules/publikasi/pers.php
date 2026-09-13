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

// Kategori berita (model=newscategory), difilter server-side via newscat.
$KAT_LIST = [
    'Sensus dan Survey' => 'Kegiatan Statistik',
    'Statistik Lain'    => 'Kegiatan Statistik Lainnya',
];
$kat = trim($_GET['kat'] ?? '');
if (!isset($KAT_LIST[$kat])) {
    $kat = '';
}

$url = BPS_API_BASE . "list/"
    . "?model=news"
    . "&domain=" . urlencode(BPS_DOMAIN)
    . "&lang=ind"
    . "&key=" . urlencode(BPS_API_KEY)
    . "&page=" . $page;
if ($q !== '') {
    $url .= "&keyword=" . urlencode($q);
}
if ($kat !== '') {
    $url .= "&newscat=" . urlencode($kat);
}

// Suffix query string agar link paginasi tetap membawa filter yang aktif
$qParam = '';
if ($q !== '') {
    $qParam .= '&q=' . urlencode($q);
}
if ($kat !== '') {
    $qParam .= '&kat=' . urlencode($kat);
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
$curlError = curl_error($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$bpsData = json_decode($response, true);

/**
 * Bersihkan HTML isi berita dari API sebelum ditampilkan (anti-XSS):
 * buang tag/blok berbahaya, atribut event JavaScript, dan link
 * javascript:, lalu hanya izinkan tag format teks dasar.
 */
function bersihkanHtmlBerita(string $html): string
{
    // Buang blok script/style/iframe/object/embed beserta isinya.
    $html = preg_replace('#<(script|style|iframe|object|embed)[^>]*?>.*?</\\1>#is', '', (string) $html);
    // Buang atribut event handler (onclick, onerror, ...) dan style.
    $html = preg_replace('#\\s+(on\\w+|style)\\s*=\\s*("[^"]*"|\'[^\']*\'|[^\\s>]+)#i', '', (string) $html);
    // Netralkan link javascript:/data:.
    $html = preg_replace('#\\s+href\\s*=\\s*([\'"]?)\\s*(javascript|data):.*?\\1#i', ' href="#"', (string) $html);
    return strip_tags(
        (string) $html,
        '<p><br><b><strong><i><em><u><ul><ol><li><a><blockquote><h3><h4><div><span>'
    );
}
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Berita dan Siaran Pers - BPS Sumut</title>
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

        /* Modal detail berita. */
        .modal-berita-overlay {
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

        .modal-berita-box {
            background: #fff;
            width: 95%;
            max-width: 720px;
            max-height: 88vh;
            border-radius: 10px;
            overflow: auto;
            padding: 20px;
            position: relative;
        }

        .modal-berita-close {
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

        .modal-berita-box img {
            width: 100%;
            border-radius: 6px;
            margin: 10px 0;
        }

        .modal-berita-isi {
            font-size: 16px;
            color: #333;
            line-height: 1.7;
        }

        .modal-berita-isi p {
            margin: 0 0 10px 0;
        }

        .modal-berita-isi ul,
        .modal-berita-isi ol {
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
                    <a href="pers.php" class="current">Berita dan Siaran Pers</a>
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
        <h2 style="text-align: center; margin-bottom: 10px; color: #333">Berita dan Siaran Pers</h2>

        <form method="get" action="pers.php" id="formCariPers" style="display:flex; gap:8px; justify-content:center; margin:15px 0 5px 0; flex-wrap:wrap;">
            <input
                type="text"
                id="cariPers"
                name="q"
                placeholder="Ketik kata kunci untuk mencari di semua halaman..."
                autocomplete="off"
                value="<?= htmlspecialchars($q) ?>"
                style="width:min(360px, 90%); padding:10px 14px; border:1px solid #ccc; border-radius:6px; font-size:16px;" />
            <select id="katPers" name="kat" style="padding:10px 12px; border:1px solid #ccc; border-radius:6px; font-size:16px; background:#fff; max-width:90%;">
                <option value="">Semua Kategori</option>
                <?php foreach ($KAT_LIST as $id => $nama): ?>
                    <option value="<?= htmlspecialchars($id) ?>" <?= ($kat === $id) ? 'selected' : '' ?>><?= htmlspecialchars($nama) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" style="background-color:#034f84; color:#fff; border:none; padding:0 18px; border-radius:6px; cursor:pointer; font-weight:bold;">Cari</button>
            <?php if ($q !== '' || $kat !== ''): ?>
                <a href="pers.php" style="align-self:center; font-size:15px; color:#d9534f; font-weight:bold; text-decoration:none;">Reset &times;</a>
            <?php endif; ?>
        </form>
        <p style="text-align:center; color:#888; font-size:15px; margin-bottom:5px;" id="statusCariPers">
            <?php if (($q !== '' || $kat !== '') && $bpsData && ($bpsData['status'] ?? '') === 'OK'): ?>
                <?= (int) ($bpsData['data'][0]['total'] ?? 0) ?> hasil
                <?php if ($kat !== ''): ?>
                    pada kategori &ldquo;<?= htmlspecialchars($KAT_LIST[$kat]) ?>&rdquo;
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
                // Dikumpulkan untuk modal popup (di-output sebagai JSON di script bawah)
                $daftarModal = [];
                ?>

                <?php foreach ($newsList as $idx => $news): ?>
                    <?php
                    // Struktur model=news BERBEDA dengan model=pressrelease
                    // (yang dipakai berita.php): field-nya news_id,
                    // newscat_name, title, news (isi HTML), rl_date
                    // ("2026-08-22"), dan picture. Khususnya TIDAK ADA field
                    // pdf di sini tombol "Baca PDF" warisan copy-paste
                    // selalu mengarah ke "#" sehingga tidak bisa dibuka.
                    $imgUrl  = !empty($news['picture']) ? $news['picture'] : 'https://ppid.bps.go.id/upload/img/logo_(1)_1643969039217.png';
                    $judul   = $news['title'] ?? '(Tanpa Judul)';
                    $kategori = $news['newscat_name'] ?? '';
                    $isi     = bersihkanHtmlBerita((string) ($news['news'] ?? ''));

                    // Format tanggal: "2026-08-22" menjadi "22 Agustus 2026"
                    // (terima juga format lama "2026 08 31" kalau API berubah).
                    $rawDate = $news['rl_date'] ?? '';
                    $tanggal = $rawDate;
                    $dt = DateTime::createFromFormat('Y-m-d', (string) $rawDate);
                    if (!$dt) {
                        $dt = DateTime::createFromFormat('Y m d', (string) $rawDate);
                    }
                    if ($dt) {
                        $bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                        $tanggal = $dt->format('d') . ' ' . $bulan[(int)$dt->format('m') - 1] . ' ' . $dt->format('Y');
                    }

                    // Data untuk modal popup "Baca Selengkapnya"
                    $daftarModal[] = [
                        'id'       => isset($news['news_id']) ? (int) $news['news_id'] : 0,
                        'judul'    => $judul,
                        'img'      => $imgUrl,
                        'tanggal'  => $tanggal,
                        'kategori' => $kategori,
                        'isi'      => $isi,
                    ];
                    ?>
                    <div class="news-card">
                        <img src="<?= htmlspecialchars($imgUrl) ?>" alt="Gambar Berita" onerror="this.src='../../assets/img/logo_(1)_1643969039217.png'" <?php if (trim(strip_tags($isi)) !== ''): ?> onclick="bukaBerita(<?= (int) $idx ?>)" style="cursor:pointer;" <?php endif; ?>>
                        <div class="news-content">
                            <div class="news-date"> <?= htmlspecialchars($tanggal) ?></div>
                            <h3 class="news-title" style="font-size:16px;"><?= htmlspecialchars($judul) ?></h3>
                            <?php if ($kategori): ?>
                                <span style="display:inline-block; background:#e8f0fe; color:#034f84; padding:3px 8px; border-radius:3px; font-size:13px; margin-bottom:8px;"><?= htmlspecialchars($kategori) ?></span>
                            <?php endif; ?>
                            <br>
                            <?php if (trim(strip_tags($isi)) !== ''): ?>
                                <button type="button" class="news-btn" style="border:none; cursor:pointer; font:inherit;" onclick="bukaBerita(<?= (int) $idx ?>)">Baca Selengkapnya &rarr;</button>
                            <?php endif; ?>
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
                    $totalPages = $bpsData['data'][0]['pages'] ?? 1;

                    // Jendela nomor halaman.
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

    <!-- Modal detail berita (popup besar, pola yang sama dengan
         modal infografis di infografis.php) -->
    <div class="modal-berita-overlay" id="modalBerita" onclick="if(event.target===this) tutupBerita()">
        <div class="modal-berita-box">
            <button type="button" class="modal-berita-close" onclick="tutupBerita()">&times;</button>
            <h3 id="modalBeritaJudul" style="color:#002b6a; margin-right:40px;"></h3>
            <div id="modalBeritaTanggal" style="color:#888; font-size:14px;"></div>
            <div id="modalBeritaKategori" style="margin-top:6px;"></div>
            <img id="modalBeritaGambar" src="" alt="Gambar Berita" onerror="this.src='../../assets/img/logo_(1)_1643969039217.png'" />
            <div id="modalBeritaIsi" class="modal-berita-isi"></div>
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
        // Data berita halaman ini untuk modal popup (isi sudah dibersihkan
        // server-side via bersihkanHtmlBerita(); JSON di-escape aman).
        var DATA_BERITA = <?= json_encode(
                                $daftarModal ?? [],
                                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                            ) ?>;

        // Popup isi berita lengkap. LIST hanya memberi ringkasan;
        // isi penuh diambil via pers_detail.php saat popup dibuka.
        function bukaBerita(idx) {
            var item = DATA_BERITA[idx];
            if (!item) return;
            document.getElementById('modalBeritaJudul').textContent = item.judul || '(Tanpa Judul)';
            document.getElementById('modalBeritaTanggal').textContent = item.tanggal || '';
            var katEl = document.getElementById('modalBeritaKategori');
            katEl.innerHTML = '';
            if (item.kategori) {
                var badge = document.createElement('span');
                badge.style.display = 'inline-block';
                badge.style.background = '#e8f0fe';
                badge.style.color = '#034f84';
                badge.style.padding = '3px 8px';
                badge.style.borderRadius = '3px';
                badge.style.fontSize = '11px';
                badge.textContent = item.kategori;
                katEl.appendChild(badge);
            }
            var gbr = document.getElementById('modalBeritaGambar');
            gbr.src = item.img || '../../assets/img/logo_(1)_1643969039217.png';
            var isiEl = document.getElementById('modalBeritaIsi');
            // Tampilkan ringkasan dulu + penanda memuat (isi sudah difilter aman di PHP).
            isiEl.innerHTML = (item.isi || '<i>Tidak ada isi berita.</i>') +
                '<p id="memuatLengkap" style="color:#888; font-size:14px; margin-top:8px;"><i>Memuat isi lengkap...</i></p>';
            document.getElementById('modalBerita').style.display = 'flex';
            document.body.style.overflow = 'hidden';

            // Ganti dengan isi lengkap dari endpoint detail.
            if (!item.id) {
                var tanda = document.getElementById('memuatLengkap');
                if (tanda) tanda.remove();
                return;
            }
            fetch('pers_detail.php?news_id=' + encodeURIComponent(item.id))
                .then(function(res) {
                    return res.json();
                })
                .then(function(json) {
                    var tanda = document.getElementById('memuatLengkap');
                    if (tanda) tanda.remove();
                    if (json && json.success && json.data && json.data.isi) {
                        isiEl.innerHTML = json.data.isi;
                    }
                    // Kalau gagal: biarkan ringkasan yang sudah tampil.
                })
                .catch(function() {
                    var tanda = document.getElementById('memuatLengkap');
                    if (tanda) tanda.remove();
                });
        }

        function tutupBerita() {
            document.getElementById('modalBerita').style.display = 'none';
            document.body.style.overflow = '';
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') tutupBerita();
        });

        // Pencarian dinamis lintas halaman: 700ms setelah berhenti mengetik,
        // form otomatis tersubmit ke server yang mencari via API BPS (seluruh data).
        // Dropdown kategori langsung tersubmit saat diganti.
        (function() {
            var input = document.getElementById('cariPers');
            var form = document.getElementById('formCariPers');
            var kat = document.getElementById('katPers');
            if (!input || !form) return;
            var timer = null;
            var awal = input.value;
            input.addEventListener('input', function() {
                clearTimeout(timer);
                timer = setTimeout(function() {
                    if (input.value !== awal) form.submit();
                }, 700);
            });
            if (kat) {
                kat.addEventListener('change', function() {
                    form.submit();
                });
            }
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
        })();
    </script>
</body>

</html>