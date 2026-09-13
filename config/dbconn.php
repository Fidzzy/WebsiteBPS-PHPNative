<?php
require_once __DIR__ . '/load_env.php';

$db_hostname = getenv('DB_HOST') ?: "localhost";
$db_database = getenv('DB_NAME') ?: "projekpbw";
$db_username = getenv('DB_USER') ?: "root";
$db_password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "";
$db_charset  = getenv('DB_CHARSET') ?: "utf8mb4";

$dsn = "mysql:host=$db_hostname;dbname=$db_database;charset=$db_charset";
$opt = array(
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false
);

try {
    $pdo = new PDO($dsn, $db_username, $db_password, $opt);
} catch (PDOException $e) {
    // Jangan tampilkan detail error koneksi DB ke pengunjung (bisa membocorkan
    // info struktur/kredensial), cukup dicatat di server log.
    error_log('PDO Connection Error: ' . $e->getMessage());
    http_response_code(500);
    exit('Maaf, sedang terjadi gangguan pada server. Silakan coba lagi nanti.');
}
