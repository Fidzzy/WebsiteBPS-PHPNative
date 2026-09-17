# Website Tiruan BPS Provinsi Sumatera Utara

Dibuat oleh: Muhammad Hafidz Ar Rasyid Hutagalung
NIM: 222413683 — Kelas: 2KS3
Repo Github: https://github.com/Fidzzy/WebsiteBPS-PHPNative

Website tiruan BPS Sumut: dashboard publik + daftar publikasi, BRS, katalog data,
tabel dinamis, exim, berita pers, infografis, dan galeri — dengan data live dari
Web API BPS yang di-cache, plus data manual kelolaan admin via database lokal.
Login hanya untuk admin; pengunjung tidak perlu login.

## 1. Kebutuhan Sistem

- PHP 8.2+ (teruji 8.2.12 / 8.3), ekstensi `pdo_mysql` + `curl`
- MariaDB 10.4 / MySQL + phpMyAdmin (paket XAMPP)
- Apache (XAMPP), folder `cache/` writable

## 2. Instalasi

```bat
:: 1. Extract ZIP ke htdocs sehingga menjadi:
::    C:\xampp\htdocs\projekpbw_bugfix\index.php

:: 2. File .env sudah terisi (BPS_API_KEY + config DB) — langsung pakai.
::    Kalau key bermasalah, edit .env dan isi BPS_API_KEY baru dari
::    https://webapi.bps.go.id/developer/ (atau copy dari .env.example).
```

Isi `.env`:

```
BPS_API_KEY=GANTI_DENGAN_API_KEY_ANDA
BPS_DOMAIN=1200
DB_HOST=localhost
DB_NAME=projekpbw
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
```

```text
3. Import database via phpMyAdmin (urut):
   database/hafidz_db.sql        -> tabel publikasi + 6 contoh
   database/galeri.sql           -> tabel galeri + 8 contoh
   database/publikasi_dilihat.sql-> kolom publikasi.dilihat (counter Terpopuler)
   database/users.sql            -> tabel users (admin/admin123, user/user123)
   database/login_attempts.sql   -> tabel rate-limit login

4. Buka:
   http://localhost/projekpbw_bugfix/                 -> dashboard publik
   http://localhost/projekpbw_bugfix/modules/auth/login.php -> login admin
```

> Paket ZIP ini sudah berisi file `.env` yang terisi (BPS_API_KEY + config DB),
> jadi tinggal extract, import database, dan buka di browser.
> File `.env.example` disertakan sebagai cadangan template config.

Akun demo:

```
admin / admin123 (role admin, bisa login)
user  / user123  (role user, ditolak login — pengunjung memang tidak perlu login)
```

## 3. Struktur Folder

```
index.php                  -> dashboard publik (entry point)
.htaccess                  -> blokir *.sql/*.md/*.env + /.git
.env / .env.example        -> config lokal / contoh (lihat §2)
config/                    -> dbconn.php, load_env.php, bps_api.php, bps_indicators.php
includes/                  -> auth.php, bps_client.php, bps_publication.php, xlsx_helper.php
modules/auth/              -> login.php, login_action.php, logout.php, register*.php (nonaktif)
modules/publikasi/         -> CRUD publikasi + BRS, katalog, tabel dinamis, exim, pers, infografis
modules/galeri/            -> CRUD galeri (page09G/H/I/J)
api/                       -> REST JSON (auth + publikasi), didok di api/API.md
assets/                    -> css/myCSS2.css, js/nav.js + validasiForm.js + page11A_suggestion.js,
                              img/ (logo, ikon indikator, sampul, foto galeri), pdf/
cache/                     -> *.json hasil fetch BPS (otomatis, tidak ikut ZIP)
database/                  -> 5 file .sql (lihat §2) + .htaccess proteksi
```

## 4. Konfigurasi (`config/` + `.env`)

| File                        | Fungsi                                                                                                                                                                                                      |
| --------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `config/load_env.php`       | Loader `.env` tanpa composer. `loadEnv()` idempotent: parse `KEY=VALUE` (skip `#`/kosong, dukung `export`, strip quotes), tidak menimpa env server, auto-run saat di-require.                               |
| `config/bps_api.php`        | `BPS_API_KEY` (dari `.env`, fallback placeholder), `BPS_DOMAIN` (default `1200` = Sumut), `BPS_API_BASE` Web API BPS.                                                                                       |
| `config/bps_indicators.php` | Daftar kartu dashboard. Cara A: `keyword` (auto-discovery) / Cara B: presisi `var_id, vervar_id, turvar_id, th_id, turtahun_id` + `title, unit, icon, show_trend`. Contoh: inflasi 762, ekspor 60, IPM 750. |
| `config/dbconn.php`         | Baca `DB_HOST/DB_NAME/DB_USER/DB_PASS/DB_CHARSET` dari env (fallback localhost/projekpbw/root/“”). Koneksi PDO (`ERRMODE_EXCEPTION`, `FETCH_ASSOC`, charset `utf8mb4`). Error disamarkan + `error_log`.     |

## 5. Auth & Role (`includes/auth.php` + `modules/auth/`)

- `isLoggedIn()`, `isAdmin()`, `requireLogin()`, `requireAdmin()` (403 jika bukan admin)
- `csrfToken()`, `requireValidCsrf()` — semua hapus/tambah/edit via POST + token
- `tooManyLoginAttempts()`, `recordFailedLogin()`, `clearLoginAttempts()` — blokir 5 gagal/15 menit per username+IP (`login_attempts`)
- Session cookie hardened: `httponly`, `SameSite=Lax`, `secure` otomatis jika HTTPS
- `login.php` form khusus admin (sudah login → redirect `page09A.php`); `login_action.php` (POST): validasi kosong → rate-limit → `password_verify()` (bcrypt) → tolak `role != admin` → `session_regenerate_id` + isi session → `page09A.php`
- `logout.php`: bersihkan session + cookie + `session_destroy()`
- `register.php` / `register_action.php`: **dinonaktifkan** — redirect “pendaftaran dinonaktifkan”

## 6. Integrasi Web API BPS (`includes/` + `modules/publikasi/bps_*`)

- `includes/bps_client.php`: `bpsApiCall()` (cURL + UA browser), fetch variabel/periode/indikator (`bpsFetchIndicatorCached`, `bpsFetchIndicatorByKeywordCached`), query builder dinamis, exim, semua cache file TTL ~1 jam
- `includes/bps_publication.php`: `bpsFetchPublicationPage()`, `bpsFetchPublicationsAll()` (curl_multi 10 paralel, maks ~150 hal, cache 6 jam), `bpsPubNormalizeManualRow()` — gabung API + DB lokal
- `bps_variable_search.php` (admin): cari `var_id` by keyword; `bps_debug.php` (admin): dump mentah API
- `bps_katalog_data.php` (publik JSON): nilai terbaru per subjek untuk `katalog.php`
- `bps_query_api.php` + `bps_query_download.php`: proxy + unduh CSV/XLSX tabel dinamis
- `bps_exim_api.php` + `bps_exim_download.php`: proxy + unduh CSV/XLSX exim

## 7. Halaman (`modules/`)

Publik (tanpa login):

| File                                     | Isi                                                                                                                                                                                                                |
| ---------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `index.php`                              | Dashboard: hero + layanan, carousel indikator + grafik tren SVG server-side (`renderBpsTrendSvg()`), tab Informasi Terbaru (6 publikasi/BRS/infografis, cache `home_brs.json` 1 jam, `home_infografis.json` 6 jam) |
| `publikasi/page09A.php`                  | Daftar publikasi API+DB: filter `?q=&tahun=&urutan=terbaru/terlama/terpopuler`, paginasi 5/10/20                                                                                                                   |
| `publikasi/berita.php`                   | BRS (`model=pressrelease`, filter q/bulan/tahun)                                                                                                                                                                   |
| `publikasi/katalog.php`                  | Statistik per subjek (sidebar via `bps_katalog_data.php`)                                                                                                                                                          |
| `publikasi/data_dinamis.php`             | Query-builder tabel dinamis (maks 2 tabel)                                                                                                                                                                         |
| `publikasi/exim.php`                     | Ekspor-impor nasional + HS 2-digit, murni API-driven                                                                                                                                                               |
| `publikasi/pers.php` + `pers_detail.php` | Berita (`model=news`) + proxy JSON detail untuk modal (anti-XSS)                                                                                                                                                   |
| `publikasi/infografis.php`               | Galeri infografis (`model=infographic`, filter subjek)                                                                                                                                                             |
| `publikasi/track_view.php`               | POST increment `dilihat` (counter Terpopuler)                                                                                                                                                                      |
| `publikasi/page11A_gethint.php`          | Autocomplete JSON 5 judul (`LIKE`)                                                                                                                                                                                 |
| `galeri/page09G.php`                     | Galeri kegiatan (`ORDER BY urutan`)                                                                                                                                                                                |

Khusus admin (`requireAdmin()` + POST + CSRF):

| File                                           | Isi                                                |
| ---------------------------------------------- | -------------------------------------------------- |
| `publikasi/page09C.php` + `page09C_action.php` | Form + INSERT publikasi (validasi + upload sampul) |
| `publikasi/page09E.php` + `page09E_action.php` | Form + UPDATE publikasi (ganti sampul)             |
| `publikasi/page09F.php`                        | DELETE publikasi + hapus file sampul               |
| `galeri/page09H.php` + `page09H_action.php`    | Tambah galeri + upload gambar                      |
| `galeri/page09I.php` + `page09I_action.php`    | Edit galeri + ganti foto                           |
| `galeri/page09J.php`                           | Hapus galeri                                       |

## 8. REST API (`api/`, didok di `api/API.md`)

Helper `api/helpers/response.php`: `json_response()`, `get_json_or_post_body()`, `apiRequireLogin/Admin/Csrf()` (session cookie + header `X-CSRF-Token`).

| Endpoint                                               | Akses                                              |
| ------------------------------------------------------ | -------------------------------------------------- |
| `POST api/auth/login.php`                              | Login admin → `{id,username,nama,role,csrf_token}` |
| `POST api/auth/logout.php`                             | Hancurkan session                                  |
| `GET api/auth/me.php`                                  | Cek session (publik)                               |
| `GET api/publikasi/index.php` (`?search&tahun&urutan`) | List publikasi (publik)                            |
| `POST api/publikasi/index.php`                         | Tambah (admin+CSRF)                                |
| `GET/PUT/DELETE api/publikasi/detail.php?no=`          | Detail (publik) / update / hapus (admin+CSRF)      |

## 9. Frontend (`assets/`)

- `assets/css/myCSS2.css`: style utama — header navy fixed, nav + dropdown Produk, tabel, form, filter, footer, responsif ≤900/600px
- `assets/js/nav.js`: hamburger + drawer mobile, dropdown Produk jadi accordion di HP
- `assets/js/validasiForm.js`: `validate06C()` (tambah) + `validate09E()` (edit) — blokir `<>…` di judul, cek ekstensi sampul, pesan auto-hilang 4 detik
- `assets/js/page11A_suggestion.js`: autocomplete via `createElement+textContent` (anti Stored-XSS)
- `assets/img/`: logo BPS, `latar.webp`, `berakhlak.webp`, ikon layanan + ikon indikator (inflasi, penduduk, IPM, ekspor, impor, …), sampul `cover*.webp` / `bps_*.jpg`, `edit.png`/`delete.png`, foto galeri
- `assets/pdf/bps_*.pdf`: hasil unduh PDF publikasi

## 10. Cache, Keamanan, Catatan

- `cache/*.json` dibuat otomatis (indikator, publikasi, exim, tabel dinamis, home). Tidak ikut ZIP (dibuat ulang saat program dijalankan). Folder `cache/` butuh writable.
- Keamanan: API key + kredensial DB via `.env`; password bcrypt + `session_regenerate_id`; rate-limit login; proteksi CSRF; upload dicek `getimagesize()` + `basename()`; output `htmlspecialchars()`; error DB generik; `.htaccess` blokir `*.sql/*.md/*.env` + `/.git` di root, `Require all denied` di `config/`, `includes/`, `database/`, `cache/`.
