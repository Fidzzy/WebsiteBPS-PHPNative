<?php
require_once '../../config/dbconn.php';
require_once '../../includes/auth.php';
requireAdmin();
requireValidCsrf($_POST['csrf_token'] ?? null);

try {
    $id         = $_POST['id'] ?? '';
    $judul      = trim($_POST['judul'] ?? '');
    $urutan     = trim($_POST['urutan'] ?? '0');
    $gambarLama = basename($_POST['gambar_lama'] ?? '');
    $namaFile   = $gambarLama; // default: foto lama tetap dipakai

    if ($id === '' || $judul === '') {
        header('Location: page09I.php?id=' . urlencode($id) . '&status=gagal');
        exit;
    }

    $dirUpload = __DIR__ . '/../../assets/img/';

    if (isset($_FILES['gambar_baru']) && $_FILES['gambar_baru']['error'] === UPLOAD_ERR_OK) {
        $fileBaru   = basename($_FILES['gambar_baru']['name']);
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext        = strtolower(pathinfo($fileBaru, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt)) {
            header('Location: page09I.php?id=' . urlencode($id) . '&judul=' . urlencode($judul) . '&urutan=' . urlencode($urutan) . '&gambar=' . urlencode($gambarLama) . '&status=gagal');
            exit;
        }

        if (@getimagesize($_FILES['gambar_baru']['tmp_name']) === false) {
            header('Location: page09I.php?id=' . urlencode($id) . '&judul=' . urlencode($judul) . '&urutan=' . urlencode($urutan) . '&gambar=' . urlencode($gambarLama) . '&status=gagal');
            exit;
        }

        if (file_exists($dirUpload . $fileBaru)) {
            $fileBaru = uniqid('galeri_') . '.' . $ext;
        }

        move_uploaded_file($_FILES['gambar_baru']['tmp_name'], $dirUpload . $fileBaru);

        if (!empty($gambarLama) && $gambarLama !== $fileBaru && file_exists($dirUpload . $gambarLama)) {
            unlink($dirUpload . $gambarLama);
        }

        $namaFile = $fileBaru;
    }

    $stmt = $pdo->prepare('UPDATE galeri SET judul = :judul, urutan = :urutan, gambar = :gambar WHERE id = :id');
    $stmt->execute([
        ':judul'  => $judul,
        ':urutan' => (int) $urutan,
        ':gambar' => $namaFile,
        ':id'     => $id,
    ]);

    header('Location: page09G.php');
    exit;
} catch (PDOException $e) {
    header('Location: page09I.php?id=' . urlencode($id) . '&status=gagal');
    exit;
}
