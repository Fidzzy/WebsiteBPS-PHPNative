<?php

/**
 * Konfigurasi indikator BPS yang ditampilkan di dashboard (index.php).
 *
 * Ada 2 cara mengisi tiap indikator:
 *
 * A) OTOMATIS lewat 'keyword' (default di bawah, langsung jalan begitu
 *    BPS_API_KEY diisi, TANPA perlu cari var_id manual dulu):
 * Sistem mencari variabel BPS yang judulnya cocok dengan kata kunci,
 *      lalu otomatis memilih yang paling terlihat seperti "indikator
 * utama" (bukan tabel breakdown/silang) lihat bpsPickHeadlineVariable()
 *      di includes/bps_client.php untuk detail heuristiknya.
 * PRAKTIS tapi tidak 100% presisi kadang bisa salah pilih kalau ada
 *      banyak variabel serupa. Cek hasilnya di dashboard; kalau kurang
 *      pas, upgrade ke cara B untuk indikator itu.
 *
 * B) MANUAL lewat 'var_id' (presisi, tapi perlu 1x pencarian dulu):
 *    1. Login sebagai admin, buka di browser:
 *       modules/publikasi/bps_variable_search.php?keyword=<kata kunci>
 *    2. Catat var_id yang paling sesuai dari hasilnya.
 *    3. Isi 'var_id' pada entri terkait (kalau 'var_id' diisi, dia dipakai
 *       duluan, 'keyword' diabaikan).
 *    4. (Opsional) Kalau nilai yang muncul kurang tepat, tambahkan
 * vervar_id/turvar_id spesifik lihat instruksi lengkapnya dengan
 *       membuka langsung di browser:
 *       https://webapi.bps.go.id/v1/api/list/model/data/lang/ind/domain/1200/var/VAR_ID_ANDA/key/API_KEY_ANDA/
 *       lalu lihat isi "vervar" dan "turvar" di respons JSON-nya.
 *
 * Field per indikator:
 * keyword Kata kunci pencarian (cara A). Diabaikan kalau 'var_id' diisi.
 * var_id ID variabel presisi (cara B). Kalau diisi, didahulukan.
 * th_id (opsional, cuma untuk cara B) Pin periode (th) secara
 * manual, mis. "126" lewati pencarian periode otomatis lewat
 *   model=th sepenuhnya. WAJIB diisi kalau tabelnya mencampur beberapa
 *   jenis periodisitas berbeda dalam var_id yang sama (mis. ada baris
 *   bulanan m-to-m DAN baris tahunan y-on-y sekaligus) sehingga "ambil
 *   periode terbaru otomatis" bisa salah pilih baris. Cari th_id yang
 *   benar lewat https://webapi.bps.go.id/v1/api/list/model/th/lang/ind/domain/1200/var/VAR_ID_ANDA/key/API_KEY_ANDA/
 * title (wajib) Judul yang ditampilkan di kartu dashboard.
 * unit (opsional) Satuan, mis. "persen", "jiwa".
 * vervar_id (opsional, cuma untuk cara B) Filter wilayah tertentu.
 * turvar_id (opsional, cuma untuk cara B) Filter turunan variabel tertentu.
 * turtahun_id (opsional, cuma untuk cara B) Pin turunan periode, mis.
 *   "32" = Triwulan II. WAJIB diisi kalau tabelnya mencampur triwulan/
 * bulan dengan baris agregat "Tahunan" (mis. var 183) tanpa pin,
 *   tahun lama kepilih "Tahunan" sementara tahun terbaru kepilih
 *   triwulan (dua jenis angka yang tidak sebanding).
 * show_trend (opsional, default false) Tampilkan grafik tren beberapa
  *   tahun terakhir untuk indikator ini (maksimal 1 yang aktif dipakai).
 * icon (opsional) Ikon yang tampil di kartu indikator dashboard.
  *   Diisi HTML inline seperti '<img src="assets/img/inflasi.png" alt="">',
  *   '<i class="..."></i>' / SVG. Contoh:
 * 'icon' '<img src="assets/img/inflasi.png" alt="">',
  *   Kalau dikosongkan, kartu tampil tanpa ikon (tetap rapi).
  *
  * Kosongkan array ini (return [];) kalau belum mau menampilkan indikator
 * apa pun dashboard otomatis menyembunyikan bagian "Indikator BPS" kalau
 * tidak ada yang dikonfigurasi.
 */

return [
    [
        // var_id=762 dikonfirmasi langsung oleh admin lewat pengujian manual:
        // https://webapi.bps.go.id/v1/api/list/model/data/lang/ind/domain/1200/var/762/th/126/key/API_KEY_ANDA/
        // th_id sengaja TIDAK dipin di sini (biar tetap auto-update ke
        // periode terbaru tiap kali API BPS rilis data baru) th/126 di
        // atas cuma dipakai admin untuk MEMASTIKAN var_id-nya benar/ada
        // datanya, bukan untuk dibekukan selamanya ke periode itu.
        'var_id'     => '762',
        'vervar_id' => '9',
        'title'      => 'Inflasi',
        'unit'       => 'persen',
        'icon'       => '<img src="assets/img/inflasi.png" alt="">',
    ],
    [
        // var_id=60, dikonfirmasi sama seperti di atas.
        'var_id' => '60',
        'title'  => 'Nilai Ekspor',
        'unit'   => 'juta usd',
        'icon'   => '<img src="assets/img/export.png" alt="">',
    ],
    [
        // var_id=183. PERHATIAN: "baris" tabel ini ada di vervar (bukan
        // turvar): sektor A s.d. U plus agregat PDRB. Tanpa vervar_id,
        // kode otomatis mengambil baris PERTAMA = "A Pertanian, Kehutanan,
        // dan Perikanan" (salah). vervar_id=18 = baris PRODUK DOMESTIK
        // REGIONAL BRUTO. turtahun_id=32 = Triwulan II (tanpa pin, tahun
        // lama kepilih baris agregat "Tahunan" yang tidak sebanding dengan
        // angka triwulanan).
        'var_id'      => '183',
        'vervar_id'   => '18',
        'turtahun_id' => '32',
        'title'       => 'Pertumbuhan Ekonomi',
        'unit'        => 'persen',
        'icon'        => '<img src="assets/img/pertumbuhanekonomi.png" alt="">',
    ],

    [
        'var_id' => '63',
        'title' => 'Nilai Impor',
        'unit' => 'Juta USD',
        'icon' => '<img src="assets/img/import.png" alt="">',

    ],

    [
        // var_id=80, dikonfirmasi sama seperti di atas.
        'var_id' => '80',
        'title'  => 'Jumlah Penduduk Miskin',
        'unit'   => 'ribu jiwa',
        'icon'   => '<img src="assets/img/ppm.png" alt="">',
    ],

    [
        // var_id=80, dikonfirmasi sama seperti di atas.
        'var_id' => '83',
        'title' => 'Nilai Neraca Perdagangan',
        'unit' => 'Juta USD',
        'icon' => '<img src="assets/img/neraca.png" alt="">',
    ],

    [
        // var_id=161, dikonfirmasi sama seperti di atas menggantikan
        // entri lama berbasis kata kunci "pengangguran".
        'var_id' => '161',
        'title'  => 'Tingkat Pengangguran Terbuka',
        'unit'   => 'persen',
        'icon'   => '<img src="assets/img/unemployment.png" alt="">',
    ],

    [
        // var_id=65, dikonfirmasi sama seperti di atas menggantikan
        // entri lama berbasis kata kunci "penduduk" yang sebelumnya
        // terbukti salah pilih tabel (grafik trennya malah menurun tiap
        // tahun, padahal jumlah penduduk asli terus naik).
        'var_id'     => '65',
        'title'      => 'Jumlah Penduduk',
        'unit'       => 'jiwa',
        'icon'       => '<img src="assets/img/penduduk.png" alt="">',
    ],


    [
        // var_id=788. Kolom wilayah ada di turvar: Kota (371) / Desa (372)
        // / Kota+Desa (373). Tanpa turvar_id, kode otomatis mengambil kolom
        // PERTAMA = "Kota" saja (salah). turvar_id=373 = kolom Kota+Desa.
        // Bulan (Maret/September) tidak perlu dipin otomatis diambil
        // bulan terbaru yang sudah rilis (saat ini Maret 2026).
        'var_id'    => '788',
        'turvar_id' => '373',
        'title'     => 'Gini Ratio',
        'unit'      => '',
        'icon'      => '<img src="assets/img/giniratio.png" alt="">',
    ],

    [
        'var_id' => '748',
        'title' => 'Indeks Demokrasi Indonesia',
        'unit' => 'Persen',
        'icon' => '<img src="assets/img/democracy.png" alt="">',

    ],

    [
        'var_id' => '750',
        'title' => 'Indeks Pembangunan Manusia',
        'unit' => 'Persen',
        'icon' => '<img src="assets/img/ipm.png" alt="">',

    ],

    [
        'var_id' => '84',
        'title' => 'Nilai Tukar Petani',
        'unit' => '',
        'icon' => '<img src="assets/img/petani.png" alt="">',

    ],

    [
        'var_id' => '797',
        'vervar_id' => '4',
        'title' => 'Kunjungan Wisman ke Sumut',
        'unit' => 'Kunjungan',
        'icon' => '<img src="assets/img/turis.png" alt="">',

    ],

    [
        // var_id=161, kolom "Jumlah Penganggur". PERHATIAN: turvar tabel
        // ini adalah 134 (TPT) / 135 (Jumlah Penganggur) JANGAN diisi
        // "32" karena itu val turTAHUN (Triwulan II), bukan turvar,
        // sehingga tidak ada kolom yang cocok dan kartunya error.
        'var_id' => '161',
        'turvar_id' => '135',
        'title' => 'Jumlah Penganggur',
        'unit' => 'Ribu Jiwa',
        'icon' => '<img src="assets/img/pengangguran.png" alt="">',

    ],

];

/*'var_id' '',
 'title' '',
 'unit' '',
        */