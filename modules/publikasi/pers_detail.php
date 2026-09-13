<?php
/**
 * GET modules/publikasi/pers_detail.php?news_id=817
 *
 * Proxy ke endpoint Detail News BPS (v1/api/view/model/news/.../id/N/).
 * Alasannya: endpoint LIST (yang dipakai pers.php) hanya mengembalikan
 * ringkasan isi berita yang dipotong ("..... di akhir teks), sedangkan
 * endpoint VIEW ini mengembalikan isi berita LENGKAP untuk modal popup
 * "Baca Selengkapnya".
 *
 * Dibuat sebagai proxy PHP (bukan fetch langsung dari browser ke BPS)
 * supaya API key tidak terekspos di JavaScript publik dan terhindar
 * dari masalah CORS pola yang sama dengan bps_search.php.
 *
 * Response: { success: bool, message: string,
 *             data: {judul, tanggal, kategori, gambar, isi} | null }
 */

require_once '../../config/bps_api.php';

header('Content-Type: application/json; charset=utf-8');

function jawab(bool $ok, string $pesan, ?array $data = null): void
{
    echo json_encode(['success' => $ok, 'message' => $pesan, 'data' => $data]);
    exit;
}

/**
 * Sama dengan bersihkanHtmlBerita() di pers.php (anti-XSS): buang
 * tag/blok berbahaya, atribut event JavaScript, dan link javascript:,
 * lalu hanya izinkan tag format teks dasar. Disalin ke sini karena
 * file ini standalone dan tidak bisa include pers.php (yang me-render
 * halaman HTML). Kalau fungsi di sana diubah, samakan juga di sini.
 */
function bersihkanHtmlDetail(string $html): string
{
    // Isi dari endpoint VIEW dikodekan sebagai entitas HTML
    // (&lt;p&gt;...), jadi decode dulu sebelum dibersihkan.
    $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
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

$newsId = trim($_GET['news_id'] ?? '');

// news_id harus angka selain validasi, ini juga memastikan tidak ada
// injeksi segmen URL lain ke request cURL di bawah (format URL fix).
if ($newsId === '' || !ctype_digit($newsId)) {
    jawab(false, 'ID berita tidak valid.');
}

$url = BPS_API_BASE . 'view/model/news/lang/ind/domain/' . urlencode(BPS_DOMAIN)
    . '/id/' . $newsId
    . '/key/' . urlencode(BPS_API_KEY) . '/';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($response === false) {
    jawab(false, 'Gagal menghubungi server BPS Pusat: ' . $curlErr);
}

$json = json_decode($response, true);
if (!is_array($json) || ($json['status'] ?? '') !== 'OK' || !isset($json['data']) || !is_array($json['data'])) {
    jawab(false, 'Detail berita tidak ditemukan di BPS Pusat.');
}

$d = $json['data'];
$isi = bersihkanHtmlDetail((string) ($d['news'] ?? ''));

if (trim(strip_tags($isi)) === '') {
    jawab(false, 'Isi lengkap berita ini tidak tersedia.');
}

jawab(true, 'OK', [
    'judul'    => $d['title'] ?? '(Tanpa Judul)',
    'tanggal'  => $d['rl_date'] ?? '',
    'kategori' => $d['newscat_name'] ?? '',
    'gambar'   => $d['picture'] ?? '',
    'isi'      => $isi,
]);
