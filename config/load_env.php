<?php
/**
 * Loader .env sederhana (tanpa composer).
 *
 * Cara pakai: require_once __DIR__ . '/load_env.php'; lalu loadEnv();
 * - Mencari file .env dari folder project root (satu level di atas config/).
 * - Format: KEY=VALUE, baris kosong dan # diabaikan.
 * - Nilai yang sudah ada di environment asli TIDAK ditimpa (server tetap menang).
 * - Aman dipanggil berkali-kali (idempotent).
 */
function loadEnv($path = null)
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    if ($path === null) {
        // config/load_env.php -> project root = dirname(__DIR__)
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    }

    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        // Dukung "export KEY=VALUE"
        if (strpos($line, 'export ') === 0) {
            $line = trim(substr($line, 7));
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));

        // Hapus kutip pembungkus "..." atau '...'
        if (strlen($val) >= 2) {
            $first = $val[0];
            $last = $val[strlen($val) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $val = substr($val, 1, -1);
            }
        }

        if ($key === '' || getenv($key) !== false || isset($_ENV[$key]) || isset($_SERVER[$key])) {
            continue;
        }

        putenv($key . '=' . $val);
        $_ENV[$key] = $val;
        $_SERVER[$key] = $val;
    }
}

loadEnv();
