<?php
/**
 * POST modules/publikasi/track_view.php?no=X
 * Dipanggil lewat fetch() saat judul publikasi diklik (lihat script di
 * page09A.php). Tidak redirect kemana-mana cuma menambah counter
 * `dilihat` di background, dipakai untuk urutan "Terpopuler".
 */

require '../../config/dbconn.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// Counter publik: dipanggil siapa pun (termasuk guest tanpa login)
// saat judul publikasi diklik, untuk urutan "Terpopuler".

$no = $_GET['no'] ?? ($_POST['no'] ?? null);

if ($no !== null && ctype_digit((string) $no)) {
    $stmt = $pdo->prepare('UPDATE publikasi SET dilihat = dilihat + 1 WHERE no = :no');
    $stmt->execute([':no' => $no]);
}

echo json_encode(['success' => true]);
