<?php
require_once '../../config/dbconn.php';
require_once '../../includes/auth.php';
requireAdmin();
requireValidCsrf($_POST['csrf_token'] ?? null);

try {
    $judul  = trim($_POST['judul'] ?? '');
    $urutan = trim($_POST['urutan'] ?? '0');

    if ($judul === '') {
        header('Location: page09H.php?status=gagal&pesan=' . urlencode('Judul kegiatan wajib diisi.'));
        exit;
    }

    if (!isset($_FILES['gambar']) || $_FILES['gambar']['error'] !== UPLOAD_ERR_OK) {
        header('Location: page09H.php?status=gagal&pesan=' . urlencode('Foto kegiatan wajib diunggah.'));
        exit;
    }

    $namaFile   = basename($_FILES['gambar']['name']);
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext        = strtolower(pathinfo($namaFile, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExt)) {
        header('Location: page09H.php?status=gagal&pesan=' . urlencode('Format file tidak didukung. Gunakan .jpg, .jpeg, .png, .gif, atau .webp.'));
        exit;
    }

    if (@getimagesize($_FILES['gambar']['tmp_name']) === false) {
        header('Location: page09H.php?status=gagal&pesan=' . urlencode('File yang diunggah bukan file gambar yang valid.'));
        exit;
    }

    // Hindari nama file bentrok dengan file lain yang sudah ada
    $dirUpload = __DIR__ . '/../../assets/img/';
    if (file_exists($dirUpload . $namaFile)) {
        $namaFile = uniqid('galeri_') . '.' . $ext;
    }

    move_uploaded_file($_FILES['gambar']['tmp_name'], $dirUpload . $namaFile);

    $stmt = $pdo->prepare('INSERT INTO galeri (judul, gambar, urutan) VALUES (:judul, :gambar, :urutan)');
    $stmt->execute([
        ':judul'  => $judul,
        ':gambar' => $namaFile,
        ':urutan' => (int) $urutan,
    ]);

    header('Location: page09G.php');
    exit;
} catch (PDOException $e) {
    header('Location: page09H.php?status=gagal&pesan=' . urlencode('Terjadi kesalahan sistem, coba lagi.'));
    exit;
}
