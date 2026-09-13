<?php
require_once '../../config/dbconn.php';
require_once '../../includes/auth.php';
requireAdmin();

// Hapus data adalah aksi yang mengubah state, jadi wajib POST + token CSRF.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method tidak diizinkan. Gunakan form hapus di halaman galeri.');
}
requireValidCsrf($_POST['csrf_token'] ?? null);

try {
    $id       = $_POST['id'] ?? '';
    $namaFile = basename($_POST['gambar'] ?? ''); // basename() cegah path traversal (../../)

    if ($id === '') {
        header('Location: page09G.php');
        exit;
    }

    $dirUpload = __DIR__ . '/../../assets/img/';
    if (!empty($namaFile) && file_exists($dirUpload . $namaFile)) {
        unlink($dirUpload . $namaFile);
    }

    $stmt = $pdo->prepare('DELETE FROM galeri WHERE id = :id');
    $stmt->execute([':id' => $id]);

    header('Location: page09G.php');
    exit;
} catch (PDOException $e) {
    header('Location: page09G.php');
    exit;
}
