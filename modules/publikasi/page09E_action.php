<?php
include '../../config/dbconn.php';
require_once '../../includes/auth.php';
requireAdmin();
requireValidCsrf($_POST['csrf_token'] ?? null);

try {
    $no             = $_POST['nomor'] ?? '';
    $judul          = $_POST['judul'] ?? '';
    $tanggal_rilis  = $_POST['tanggal'] ?? '';
    $link_publikasi = $_POST['link_publikasi'] ?? '';
    $sampul_lama    = $_POST['sampul_lama'] ?? '';

    if ($no === '' || $judul === '' || $tanggal_rilis === '') {
        exit("<script>alert('Judul dan Tanggal Rilis wajib diisi.'); window.history.back();</script>");
    }

    if (isset($_FILES['sampul_baru']) && $_FILES['sampul_baru']['error'] === 0) {
        $namaFile        = basename($_FILES['sampul_baru']['name']);
        $lokasiSementara = $_FILES['sampul_baru']['tmp_name'];

        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext        = strtolower(pathinfo($namaFile, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt)) {
            exit("<script>alert('Format file tidak didukung. Gunakan .jpg, .jpeg, .png, .gif, atau .webp'); window.history.back();</script>");
        }

        if (@getimagesize($lokasiSementara) === false) {
            exit("<script>alert('File yang diunggah bukan file gambar yang valid.'); window.history.back();</script>");
        }

        $dirUpload   = __DIR__ . "/../../assets/img/";
        $sampul_lama = basename($sampul_lama ?? '');
        move_uploaded_file($lokasiSementara, $dirUpload . $namaFile);

        if (!empty($sampul_lama) && $sampul_lama !== $namaFile && file_exists($dirUpload . $sampul_lama)) {
            unlink($dirUpload . $sampul_lama);
        }

        $sql  = "UPDATE publikasi SET judul = :judul, tanggal_rilis = :tanggal_rilis, link_publikasi = :link_publikasi, sampul = :sampul WHERE no = :no";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':judul'          => $judul,
            ':tanggal_rilis'  => $tanggal_rilis,
            ':link_publikasi' => $link_publikasi,
            ':sampul'         => $namaFile,
            ':no'             => $no,
        ]);
    } else {
        $sql  = "UPDATE publikasi SET judul = :judul, tanggal_rilis = :tanggal_rilis, link_publikasi = :link_publikasi WHERE no = :no";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':judul'          => $judul,
            ':tanggal_rilis'  => $tanggal_rilis,
            ':link_publikasi' => $link_publikasi,
            ':no'             => $no,
        ]);
    }

    echo "
        <script>
            alert('Data Berhasil Diubah');
            window.location.href = 'page09A.php';
        </script>
    ";

    $pdo = null;
} catch (PDOException $e) {
    error_log('PDO Error (page09E_action.php): ' . $e->getMessage());
    exit("<script>alert('Terjadi kesalahan sistem, silakan coba lagi.'); window.history.back();</script>");
}
