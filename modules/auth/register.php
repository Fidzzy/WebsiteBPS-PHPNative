<?php
// Pendaftaran akun publik DINONAKTIFKAN: pengunjung (role user) tidak perlu
// login untuk melihat data, dan login hanya khusus admin. Akun admin dibuat
// langsung di database oleh administrator sistem.
require_once '../../includes/auth.php';

if (isAdmin()) {
    header('Location: ../publikasi/page09A.php');
    exit;
}

header('Location: login.php?status=gagal&pesan=' . urlencode('Pendaftaran akun dinonaktifkan. Pengunjung tidak perlu login; login hanya khusus admin.'));
exit;
