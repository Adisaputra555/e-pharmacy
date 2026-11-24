# B6c — Karakterisasi, Status Demo, dan Kandidat Publik

## C1 — Karakterisasi

| Revisi | Hasil empat alur |
|---|---:|
| B1 `be20a34` | 4/4, 55 assertions |
| B2a `5c79b4b` | 4/4, 55 assertions |
| B4 `29f2ad5` | 4/4, 55 assertions |
| B5 `2c3836f` | 4/4, 55 assertions |
| master `120d31b` | 4/4, 55 assertions |

Tidak ditemukan regresi perilaku aplikasi. Kegagalan percobaan awal berasal dari orkestrasi Windows: argumen `auto_prepend_file` dengan spasi terpotong ketika server dimulai dari direktori lain. Menjalankan server dari worktree dengan konfigurasi scratch relatif menyelesaikan masalah. Semua worktree dan server sementara dibersihkan.

## C2 — Penjelasan status

| Produk | Kode alasan | Status aktual |
|---|---|---|
| `regular_03` | `PAID_AT_MISSING` | Data perlu diperiksa |
| `legacy_one` | `BASE_SNAPSHOT_INVALID` | Data perlu diperiksa |
| `legacy_two` | `BASE_SNAPSHOT_INVALID` | Data perlu diperiksa |

Hanya `regular_03` bermasalah karena `paid_at` hilang. Empat produk berstatus tidak ada penjualan:

| Skenario | Produk | Diharapkan menurut P1.5 | Aktual |
|---|---|---|---|
| fast-mover stabil | Parasetamol 500 mg | Cukup data | Cukup data |
| slow-mover | Kasa steril kecil | Cukup data | Cukup data |
| tanpa penjualan 3 bulan | Salep kulit ringan | Tidak ada penjualan | Tidak ada penjualan |
| musiman | Vitamin C 500 mg | Cukup data | Cukup data |
| produk baru | Oralit sachet | Belum cukup data | Belum cukup data |
| overstock | Masker medis | Tidak ada penjualan | Tidak ada penjualan |
| stok kritis | Antasida tablet | Cukup data | Cukup data |
| kedaluwarsa merah | Sirup batuk anak | Cukup data | Cukup data |
| kedaluwarsa kuning | Tablet hisap tenggorokan | Cukup data | Cukup data |
| multi-batch FEFO | Ibuprofen 200 mg | Tidak ada penjualan | Tidak ada penjualan |
| stok nol | Termometer digital | Tidak ada penjualan | Tidak ada penjualan |
| legacy satu | Klorfeniramin 4 mg (legacy) | Data perlu diperiksa | Data perlu diperiksa |
| legacy dua | Zinc 20 mg (legacy) | Data perlu diperiksa | Data perlu diperiksa |

Hanya satu nama skenario yang secara eksplisit menyebut tanpa penjualan. Tiga skenario lainnya juga memiliki permintaan bersih nol pada periode lengkap yang dinilai; hasilnya sesuai aturan P1.5, bukan kesalahan status atau alasan untuk rebuild seeder. Dokumen B5fix menyebut ekspektasi multi-batch sebagai “Sesuai fixture”, bukan status; status P1.5 yang terukur tetap tidak ada penjualan. Satu tes demo lama masih mengharapkan status berbeda dan gagal karena ekspektasinya usang; tidak ada kode atau data demo yang diubah.

## C3 — Kandidat rilis publik

Kandidat dibuat dari pohon commit master bersih, tidak mengambil perubahan lokal dashboard. Memuat aplikasi, tests, tools, docs portfolio/desain, dan sembilan migrasi SQL terlacak. Tidak memuat konfigurasi/secrets, konfigurasi database, akun demo, dump, uploads, logs, vendor, `.env`, metadata `.git`, atau berkas lisensi. README menjelaskan setup dan demo loopback tanpa membundel kredensial. Launcher mencari PHP melalui `PATH`, bukan jalur mesin lokal.

### Pemindaian nilai

Klasifikasi berdasarkan konteks nilai; kata kunci kode biasa bukan temuan. Nilai sensitif tidak disalin ke laporan.

| Kategori kandidat | Jumlah | Aman | Temuan | Path aman |
|---|---:|---:|---:|---|
| bcrypt/Argon | 0 | 0 | 0 | — |
| Hex >=32 | 180 | 180 | 0 | `composer.lock`; laporan desain B1, B2, B2a, B3, B4, B5, B5fix/B6a, B6b, Phase 0a; `system/libraries/Email.php` |
| Email | 52 | 52 | 0 | `application/views/admin/pharmacy_settings/index.php`, `application/views/auth/login.php`, `composer.lock`, `database/pharmacy_settings_migration.sql`, `system/libraries/Email.php`, `tests/unit/DemoSeederCredentialsFileTest.php` |
| Telepon | 12 | 12 | 0 | `application/libraries/Demo_seeder.php`, `application/views/admin/pharmacy_settings/index.php`, `system/helpers/string_helper.php`, `system/libraries/Xmlrpc.php`, `tests/bootstrap.php`, `tests/characterization/PosFlowTest.php` |
| Literal password/kunci | 1 | 1 | 0 | `tests/unit/DemoSeederCredentialsFileTest.php` — fixture tes saja |
| Token/JWT, kunci privat, path Windows berisi pengguna | 0 | 0 | 0 | — |

Hex pada composer adalah referensi/checksum dependensi; fingerprint laporan adalah checksum tabel; ID sumber publik framework bukan kredensial. Email dan nomor di source adalah metadata upstream, nilai contoh, atau fixture. Hash arsip backup pada laporan Phase 0a dihapus. Nilai kontak aplikasi historis diganti dengan domain contoh pada kandidat.

### Riwayat master

| Kategori | Jumlah | Aman | Temuan | Path dan klasifikasi |
|---|---:|---:|---:|---|
| bcrypt/Argon | 0 | 0 | 0 | — |
| Hex >=32 | 104 | 104 | 0 | Aman: `composer.lock`, `system/libraries/Email.php` — referensi/checksum publik |
| Email | 53 | 51 | 2 | Temuan: `README.md`, `database/pharmacy_settings_migration.sql`. Aman: `application/views/admin/pharmacy_settings/index.php`, `application/views/auth/login.php`, `composer.lock`, `system/libraries/Email.php`, `tests/unit/DemoSeederCredentialsFileTest.php` |
| Telepon | 28 | 28 | 0 | Aman: `application/libraries/Demo_seeder.php`, `application/views/admin/pharmacy_settings/index.php`, `system/helpers/string_helper.php`, `system/libraries/Xmlrpc.php`, `tests/bootstrap.php`, `tests/characterization/PosFlowTest.php` |
| Literal password/kunci | 1 | 1 | 0 | Aman: `tests/unit/DemoSeederCredentialsFileTest.php` — fixture tes |
| Token/JWT, kunci privat, path Windows berisi pengguna | 0 | 0 | 0 | — |

Kedua temuan email historis adalah nilai kontak, bukan kredensial autentikasi; keduanya tidak ada sebagai nilai pribadi dalam kandidat. Nilai aslinya tidak dicantumkan.

PHP lint mencakup seluruh 322 berkas PHP kandidat dan lulus. Kandidat final berisi 402 berkas dengan ukuran 2.930.057 byte.

## Fingerprint baseline

Mengikuti catatan B6b yang sudah ada: 35 tabel/207 baris penuh dan 34 tabel/161 baris stabil; sebelum/sesudah identik. Baseline tidak diakses ulang, tidak ada kredensial live yang digunakan, dan tidak ada rebuild.
