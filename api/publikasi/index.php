<?php

/**
 * GET  /api/publikasi/index.php                            list semua publikasi (publik, tanpa login)
 * GET  /api/publikasi/index.php?search=xxx                 filter judul (publik, tanpa login)
 * GET  /api/publikasi/index.php?tahun=2026                 filter tahun rilis
 * GET  /api/publikasi/index.php?urutan=terpopuler          terbaru|terlama|terpopuler
 * POST /api/publikasi/index.php                            tambah publikasi baru (khusus admin)

 */

require_once '../../config/dbconn.php';
require_once '../../includes/auth.php';
require_once '../helpers/response.php';

$method = get_request_method();

if ($method === 'GET') {
    // Endpoint publik: bisa diakses tanpa login (role user tidak perlu login).

    $search = trim($_GET['search'] ?? '');
    $tahun  = trim($_GET['tahun'] ?? '');
    $urutan = $_GET['urutan'] ?? 'terbaru';

    if (!in_array($urutan, ['terbaru', 'terlama', 'terpopuler'], true)) {
        $urutan = 'terbaru';
    }

    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[] = 'judul LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    if ($tahun !== '' && ctype_digit($tahun)) {
        $where[] = 'YEAR(tanggal_rilis) = :tahun';
        $params[':tahun'] = $tahun;
    }

    $sql = 'SELECT * FROM publikasi';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    switch ($urutan) {
        case 'terlama':
            $sql .= ' ORDER BY tanggal_rilis ASC';
            break;
        case 'terpopuler':
            $sql .= ' ORDER BY dilihat DESC, tanggal_rilis DESC';
            break;
        default:
            $sql .= ' ORDER BY tanggal_rilis DESC';
            break;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    json_response(true, $rows, count($rows) . ' publikasi ditemukan.');
}

if ($method === 'POST') {
    apiRequireAdmin();

    $body = get_json_or_post_body();
    apiRequireCsrf($body);

    $no             = trim($body['no'] ?? '');
    $judul          = trim($body['judul'] ?? '');
    $tanggal_rilis  = trim($body['tanggal_rilis'] ?? '');
    $link_publikasi = trim($body['link_publikasi'] ?? '');
    $namaFile       = '';

    if ($no === '' || $judul === '' || $tanggal_rilis === '' || $link_publikasi === '') {
        json_response(false, null, 'Field no, judul, tanggal_rilis, dan link_publikasi wajib diisi.', 400);
    }

    // Cek nomor sudah dipakai atau belum
    $cek = $pdo->prepare('SELECT no FROM publikasi WHERE no = :no');
    $cek->execute([':no' => $no]);
    if ($cek->fetch()) {
        json_response(false, null, 'Nomor publikasi sudah dipakai.', 409);
    }

    // Upload sampul kalau ada file yang dikirim (multipart/form-data)
    if (isset($_FILES['sampul']) && $_FILES['sampul']['error'] === UPLOAD_ERR_OK) {
        $namaFile   = basename($_FILES['sampul']['name']);
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext        = strtolower(pathinfo($namaFile, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt)) {
            json_response(false, null, 'Format file tidak didukung. Gunakan .jpg, .jpeg, .png, .gif, atau .webp.', 400);
        }

        if (@getimagesize($_FILES['sampul']['tmp_name']) === false) {
            json_response(false, null, 'File yang diunggah bukan file gambar yang valid.', 400);
        }

        $dirUpload = __DIR__ . '/../../assets/img/';
        move_uploaded_file($_FILES['sampul']['tmp_name'], $dirUpload . $namaFile);
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO publikasi (no, judul, tanggal_rilis, link_publikasi, sampul)
             VALUES (:no, :judul, :tanggal_rilis, :link_publikasi, :sampul)'
        );
        $stmt->execute([
            ':no'             => $no,
            ':judul'          => $judul,
            ':tanggal_rilis'  => $tanggal_rilis,
            ':link_publikasi' => $link_publikasi,
            ':sampul'         => $namaFile,
        ]);

        json_response(true, [
            'no'             => (int) $no,
            'judul'          => $judul,
            'tanggal_rilis'  => $tanggal_rilis,
            'link_publikasi' => $link_publikasi,
            'sampul'         => $namaFile,
        ], 'Publikasi berhasil ditambahkan.', 201);
    } catch (PDOException $e) {
        json_response(false, null, 'Gagal menyimpan ke database.', 500);
    }
}

json_response(false, null, 'Method tidak diizinkan. Gunakan GET atau POST.', 405);
