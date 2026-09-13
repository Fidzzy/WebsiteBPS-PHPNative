<?php
// Endpoint pendaftaran DINONAKTIFKAN: role user tidak perlu login, login
// hanya khusus admin. Tolak semua request pendaftaran publik.
header('Location: login.php?status=gagal&pesan=' . urlencode('Pendaftaran akun dinonaktifkan. Pengunjung tidak perlu login; login hanya khusus admin.'));
exit;
