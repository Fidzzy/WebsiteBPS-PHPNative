<?php

require_once __DIR__ . '/load_env.php';

/**
 * Konfigurasi API BPS.
 *
 * Key ASLI jangan ditulis di file ini. Isi lewat file .env lokal:
 *   1. Copy .env.example jadi .env
 *   2. Isi BPS_API_KEY dengan key dari https://webapi.bps.go.id/developer/
 *
 * File .env sudah di-.gitignore, jadi aman di-push ke GitHub.
 * Nilai fallback di bawah hanya placeholder agar kode tetap jalan
 * (menampilkan pesan "belum dikonfigurasi") di perangkat lain.
 */
define('BPS_API_KEY', getenv('BPS_API_KEY') ?: 'GANTI_DENGAN_API_KEY_ANDA');
define('BPS_DOMAIN', getenv('BPS_DOMAIN') ?: '1200');
define('BPS_API_BASE', 'https://webapi.bps.go.id/v1/api/');
