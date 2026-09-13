<?php
include '../../config/dbconn.php';
require_once '../../includes/auth.php';
requireAdmin();

// Hapus data adalah aksi yang mengubah state, jadi wajib POST + token CSRF
// (sebelumnya bisa dipicu lewat GET biasa, rawan CSRF).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method tidak diizinkan. Gunakan form hapus di halaman daftar publikasi.');
}
requireValidCsrf($_POST['csrf_token'] ?? null);

try {
    $no       = $_POST['no'] ?? '';
    $namaFile = basename($_POST['sampul'] ?? ''); // basename() cegah path traversal (../../)

    $dirUpload = __DIR__ . "/../../assets/img/";
    if (!empty($namaFile) && file_exists($dirUpload . $namaFile)) {
        unlink($dirUpload . $namaFile);
    }

    $sql  = "DELETE FROM publikasi WHERE no = :no";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':no' => $no]);

    echo "
        <script>
            alert('Data Berhasil Dihapus');
            window.location.href = 'page09A.php';
        </script>
    ";

    $pdo = null;
} catch (PDOException $e) {
    error_log('PDO Error (page09F.php): ' . $e->getMessage());
    exit("<script>alert('Terjadi kesalahan sistem saat menghapus data.'); window.location.href = 'page09A.php';</script>");
}
