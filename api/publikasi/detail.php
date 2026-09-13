<?php

/**
 * GET    /api/publikasi/detail.php?no=1   detail satu publikasi (publik, tanpa login)
 * PUT    /api/publikasi/detail.php?no=1   update publikasi (khusus admin)
 * DELETE /api/publikasi/detail.php?no=1   hapus publikasi (khusus admin)
 */

require_once '../../config/dbconn.php';
require_once '../../includes/auth.php';
require_once '../helpers/response.php';

$method = get_request_method();
$no     = $_GET['no'] ?? ($_POST['no'] ?? null);

if ($no === null || $no === '') {
    json_response(false, null, 'Parameter "no" wajib diisi.', 400);
}

// Ambil data lama dulu (dipakai di semua method untuk validasi & fallback sampul)
$stmt = $pdo->prepare('SELECT * FROM publikasi WHERE no = :no');
$stmt->execute([':no' => $no]);
$existing = $stmt->fetch();

if (!$existing) {
    json_response(false, null, 'Publikasi dengan nomor tersebut tidak ditemukan.', 404);
}

if ($method === 'GET') {
    // Endpoint publik: bisa diakses tanpa login.
    json_response(true, $existing, 'Detail publikasi ditemukan.');
}

if ($method === 'PUT') {
    apiRequireAdmin();

    $body           = get_json_or_post_body();
    apiRequireCsrf($body);
    $judul          = trim($body['judul'] ?? $existing['judul']);
    $tanggal_rilis  = trim($body['tanggal_rilis'] ?? $existing['tanggal_rilis']);
    $link_publikasi = trim($body['link_publikasi'] ?? $existing['link_publikasi']);
    $namaFile       = $existing['sampul']; // default: sampul lama tetap dipakai

    // Kalau ada file baru dikirim lewat multipart (dengan _method=PUT override)
    if (isset($_FILES['sampul']) && $_FILES['sampul']['error'] === UPLOAD_ERR_OK) {
        $fileBaru   = basename($_FILES['sampul']['name']);
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext        = strtolower(pathinfo($fileBaru, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt)) {
            json_response(false, null, 'Format file tidak didukung. Gunakan .jpg, .jpeg, .png, .gif, atau .webp.', 400);
        }

        if (@getimagesize($_FILES['sampul']['tmp_name']) === false) {
            json_response(false, null, 'File yang diunggah bukan file gambar yang valid.', 400);
        }

        $dirUpload = __DIR__ . '/../../assets/img/';
        move_uploaded_file($_FILES['sampul']['tmp_name'], $dirUpload . $fileBaru);

        if (!empty($existing['sampul']) && $existing['sampul'] !== $fileBaru && file_exists($dirUpload . $existing['sampul'])) {
            unlink($dirUpload . $existing['sampul']);
        }

        $namaFile = $fileBaru;
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE publikasi
             SET judul = :judul, tanggal_rilis = :tanggal_rilis,
                 link_publikasi = :link_publikasi, sampul = :sampul
             WHERE no = :no'
        );
        $stmt->execute([
            ':judul'          => $judul,
            ':tanggal_rilis'  => $tanggal_rilis,
            ':link_publikasi' => $link_publikasi,
            ':sampul'         => $namaFile,
            ':no'             => $no,
        ]);

        json_response(true, [
            'no'             => (int) $no,
            'judul'          => $judul,
            'tanggal_rilis'  => $tanggal_rilis,
            'link_publikasi' => $link_publikasi,
            'sampul'         => $namaFile,
        ], 'Publikasi berhasil diperbarui.');
    } catch (PDOException $e) {
        json_response(false, null, 'Gagal memperbarui data.', 500);
    }
}

if ($method === 'DELETE') {
    apiRequireAdmin();
    apiRequireCsrf(get_json_or_post_body());

    try {
        $dirUpload = __DIR__ . '/../../assets/img/';
        if (!empty($existing['sampul']) && file_exists($dirUpload . $existing['sampul'])) {
            unlink($dirUpload . $existing['sampul']);
        }

        $stmt = $pdo->prepare('DELETE FROM publikasi WHERE no = :no');
        $stmt->execute([':no' => $no]);

        json_response(true, null, 'Publikasi berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(false, null, 'Gagal menghapus data.', 500);
    }
}

json_response(false, null, 'Method tidak diizinkan. Gunakan GET, PUT, atau DELETE.', 405);
