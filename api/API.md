# Dokumentasi API - Web BPS Provinsi Sumatera Utara

Semua endpoint API pakai session PHP yang sama dengan halaman web biasa
(login lewat `modules/auth/login.php` atau `api/auth/login.php`, dua-duanya
sama-sama bikin session). Jadi kalau sudah login lewat browser, endpoint
API otomatis mengenali (asal request dikirim dari browser yang sama /
cookie session-nya ikut terkirim).

## Format response

Semua endpoint balas JSON dengan struktur yang sama:

```json
{
  "success": true,
  "message": "Pesan singkat",
  "data": {}
}
```

## Autentikasi

| Endpoint               | Method | Auth  | Keterangan                          |
| ---------------------- | ------ | ----- | ----------------------------------- |
| `/api/auth/login.php`  | POST   | -     | Login, body: `username`, `password` |
| `/api/auth/logout.php` | POST   | login | Logout, hapus session               |
| `/api/auth/me.php`     | GET    | -     | Cek status login saat ini           |

**Contoh login (curl):**

```bash
curl -c cookies.txt -X POST http://localhost/projekpbw/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

`-c cookies.txt` menyimpan cookie session supaya bisa dipakai di request
berikutnya (`-b cookies.txt`).

Response login (dan `GET /api/auth/me.php`) juga menyertakan `csrf_token`.
**Simpan nilai ini** — dibutuhkan untuk semua request yang mengubah data
(POST/PUT/DELETE), lihat bagian "Token CSRF" di bawah.

**Contoh login (fetch dari browser):**

```javascript
const res = await fetch("/projekpbw/api/auth/login.php", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  credentials: "include", // WAJIB, supaya cookie session ikut terkirim
  body: JSON.stringify({ username: "admin", password: "admin123" }),
});
const json = await res.json();
const csrfToken = json.data.csrf_token; // simpan untuk request POST/PUT/DELETE berikutnya
```

## Token CSRF (wajib untuk POST/PUT/DELETE)

Endpoint API ini pakai autentikasi session cookie yang sama dengan halaman
web, jadi **tanpa proteksi tambahan, request pengubah data (create/update/
delete) rentan CSRF** — situs lain bisa memanfaatkan cookie session admin
yang sedang login untuk mengirim request atas nama admin tanpa sepengetahuan
mereka. Karena itu, semua endpoint yang mengubah data (`POST`, `PUT`,
`DELETE`) wajib menyertakan token CSRF.

**Cara mendapatkan token:** ambil field `csrf_token` dari response
`POST /api/auth/login.php` atau `GET /api/auth/me.php`.

**Cara mengirim token** (pilih salah satu):

- Header: `X-CSRF-Token: <token>` (disarankan, paling aman)
- Body JSON: `{"csrf_token": "<token>", ...field lain}`
- Form field: `csrf_token=<token>` (untuk `multipart/form-data`)

Kalau token tidak dikirim atau tidak valid, endpoint balas `403 Forbidden`.

**Contoh (curl, pakai header):**

```bash
curl -b cookies.txt -X POST http://localhost/projekpbw/api/publikasi/index.php \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: TOKEN_DARI_LOGIN" \
  -d '{"no": 10, "judul": "Statistik Contoh 2026", "tanggal_rilis": "2026-08-01", "link_publikasi": "https://sumut.bps.go.id/contoh"}'
```

## Publikasi (CRUD)

| Endpoint                               | Method | Auth               | Keterangan            |
| -------------------------------------- | ------ | ------------------ | --------------------- |
| `/api/publikasi/index.php`             | GET    | login (semua role) | List semua publikasi  |
| `/api/publikasi/index.php?search=kata` | GET    | login (semua role) | Filter judul          |
| `/api/publikasi/index.php`             | POST   | **admin**          | Tambah publikasi baru |
| `/api/publikasi/detail.php?no=1`       | GET    | login (semua role) | Detail satu publikasi |
| `/api/publikasi/detail.php?no=1`       | PUT    | **admin**          | Update publikasi      |
| `/api/publikasi/detail.php?no=1`       | DELETE | **admin**          | Hapus publikasi       |

### GET list

```bash
curl -b cookies.txt http://localhost/projekpbw/api/publikasi/index.php
```

### POST tambah (tanpa upload file, JSON)

```bash
curl -b cookies.txt -X POST http://localhost/projekpbw/api/publikasi/index.php \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: TOKEN_DARI_LOGIN" \
  -d '{
    "no": 10,
    "judul": "Statistik Contoh 2026",
    "tanggal_rilis": "2026-08-01",
    "link_publikasi": "https://sumut.bps.go.id/contoh"
  }'
```

### POST tambah (sekalian upload sampul, multipart)

```bash
curl -b cookies.txt -X POST http://localhost/projekpbw/api/publikasi/index.php \
  -F "csrf_token=TOKEN_DARI_LOGIN" \
  -F "no=11" \
  -F "judul=Statistik Contoh Lain 2026" \
  -F "tanggal_rilis=2026-08-15" \
  -F "link_publikasi=https://sumut.bps.go.id/contoh2" \
  -F "sampul=@/path/ke/gambar.jpg"
```

### PUT update (JSON, tanpa ganti file)

```bash
curl -b cookies.txt -X PUT http://localhost/projekpbw/api/publikasi/detail.php?no=10 \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: TOKEN_DARI_LOGIN" \
  -d '{"judul": "Judul Baru"}'
```

### PUT update (sekalian ganti file sampul)

Browser/HTML form tidak bisa kirim `multipart/form-data` langsung sebagai
method PUT, jadi dipakai **method override**: kirim POST biasa dengan field
`_method=PUT`.

```bash
curl -b cookies.txt -X POST http://localhost/projekpbw/api/publikasi/detail.php?no=10 \
  -F "_method=PUT" \
  -F "csrf_token=TOKEN_DARI_LOGIN" \
  -F "judul=Judul Baru" \
  -F "sampul=@/path/ke/gambar-baru.jpg"
```

### DELETE hapus

```bash
curl -b cookies.txt -X DELETE "http://localhost/projekpbw/api/publikasi/detail.php?no=10" \
  -H "X-CSRF-Token: TOKEN_DARI_LOGIN"
```

## Kode status HTTP yang dipakai

| Kode | Arti                                         |
| ---- | -------------------------------------------- |
| 200  | Berhasil                                     |
| 201  | Berhasil dibuat (POST create)                |
| 400  | Input tidak valid / field wajib kosong       |
| 401  | Belum login                                  |
| 403  | Login tapi bukan admin (akses ditolak)       |
| 404  | Data tidak ditemukan                         |
| 405  | Method HTTP tidak didukung endpoint ini      |
| 409  | Konflik (mis. nomor publikasi sudah dipakai) |
| 500  | Kesalahan server/database                    |

## Catatan keamanan

- Endpoint API pakai helper yang sama dengan halaman web (`includes/auth.php`)
  jadi aturan role-nya konsisten: **admin** boleh POST/PUT/DELETE, **user**
  cuma boleh GET.
- Semua endpoint yang mengubah data (POST/PUT/DELETE) **wajib token CSRF**
  (lihat bagian "Token CSRF" di atas) — mencegah request dipalsukan dari
  situs lain memakai cookie session admin yang sedang login.
- Nama file upload selalu difilter `basename()` sebelum dipakai, ekstensi
  dibatasi ke `.jpg .jpeg .png .gif .webp`, dan isi file divalidasi dengan
  `getimagesize()` (bukan cuma cek nama/ekstensinya).
- Kalau API ini nanti dipakai dari domain lain (frontend terpisah, mobile
  app native, dll — bukan `fetch()` dari halaman yang sama), pertimbangkan
  ganti dari session-based ke **token-based (JWT)**, karena cookie session
  tidak otomatis ikut ke request lintas domain kecuali dikonfigurasi CORS
  dengan benar.
