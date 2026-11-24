# B6d — Verifikasi kandidat rilis publik

Tanggal audit: 2026-11-23. Pekerjaan dibatasi pada C4 (D1–D9); tidak ada remote/push, rebuild demo, akses baseline, kredensial live, atau perubahan P1.2–P1.8.

## D1 — Status lokal dan Dashboard

`git status --short` pada audit awal:

```text
 M README.md
 M SECURITY_BEFORE_PUBLISH.md
 M application/config/config.example.php
 M application/config/database.example.php
 M application/config/demo/database.example.php
 M application/controllers/admin/Dashboard.php
 M application/views/admin/dashboard.php
 M application/views/layouts/admin.php
 M tests/unit/ForecastServiceDemoTest.php
?? application/libraries/Dashboard_sales_trend.php
?? tests/unit/DashboardSalesTrendTest.php
```

Setelah mengembalikan hanya tiga ekspektasi forecast yang dirty ke nilai commit D2, status bersih untuk tes forecast. Perubahan lain dipertahankan.

Dashboard terlacak: 933 baris ditambah dan 222 dihapus pada controller serta dua view. Library tren baru dan tes unitnya masing-masing 29 dan 195 baris. Perubahannya berupa controller/view Dashboard, layout, library tren, dan unit test. Tidak ada perubahan Dashboard yang di-stage, di-commit, atau di-revert.

## D2 — Ekspektasi forecast demo

Perubahan D2 yang committed hanya memperbarui ekspektasi tes, tanpa mengubah kode aplikasi atau membangun ulang demo. Tiga suite dijalankan dengan bootstrap demo-only yang diwajibkan:

| Suite | Tes | Assertion | Hasil |
|---|---:|---:|---|
| ForecastServiceDemoTest | 2 | 13 | Lulus |
| ProcurementDemoScenarioTest | 4 | 34 | Lulus |
| ProcurementPresentationTest | 6 | 34 | Lulus |

Commit `16faa18` sudah ada di `master`; ekspektasi lokal yang menyebabkan gagal telah dikembalikan persis ke commit tersebut. Tes sekarang bersih.

## D3 — Templat konfigurasi

Commit `cf2acd8` menambahkan `application/config/config.example.php`, `application/config/database.example.php`, dan `application/config/demo/database.example.php`. Ketiganya tidak di-ignore. `git archive master` menggunakan salinan committed yang berisi placeholder. Salinan lokal yang sudah dirty sebelum tugas dibiarkan tanpa perubahan.

## D4 — README

Commit `8fff4bc` memulihkan README dari draf portofolio. Isinya menjelaskan data sintetis, Moving Average tiga bulan (bukan AI/ML), WAPE pada data sintetis, keterbatasan/roadmap, reproducibility, setup memakai templat, lisensi CI3, serta akun default XAMPP lokal. Salinan README lokal yang dirty tidak disentuh; rilis memakai versi commit.

## D5 — Lisensi dan ignore

`license.txt` dipertahankan dan disertakan; lisensinya MIT CodeIgniter 3. `.gitignore` rilis memakai aturan generik dan tidak menyebut nama berkas/kredensial mesin.

## D6 — Pembangun pohon publik

Skrip `C:\xampp\backup\apotek-online\release\make_public_tree.py` membangun ulang `public-tree` dari `git archive master`, lalu menerapkan hanya overlay rilis yang disetujui sebelumnya. Skrip mengecualikan konfigurasi asli/rahasia, demo account config, dump SQL, uploads, logs, vendor, `.env`, `.git*`, dan backup; menyertakan lisensi, `.gitignore`, dan konfigurasi `*.example`. Dokumen desain yang dipilih disalin dari folder `design` dan sanitasi regex hanya dijalankan pada `.md`/`.txt`. Tidak ada regex sanitasi yang dijalankan pada PHP, SQL, PS1, atau JS.

## D7 — Perbedaan kandidat terhadap master

Daftar perubahan non-dokumen kandidat yang disetujui:

| Path | Alasan |
|---|---|
| `.gitignore` | Aturan generik, tanpa nama berkas/kredensial lokal |
| `application/views/auth/login.php` | Kontak placeholder dinetralkan |
| `application/views/admin/pharmacy_settings/index.php` | Kontak placeholder dinetralkan |
| `database/pharmacy_settings_migration.sql` | Nilai kontak default dikosongkan |
| `tools/start_demo.ps1` | PHP dicari melalui `PATH`, bukan jalur XAMPP lokal |
| `docs/portfolio/img/.gitkeep` | Menjaga direktori gambar yang diizinkan |

Tidak ada perubahan non-dokumen lain yang disetujui. Perbedaan dokumentasi berisi laporan desain yang disalin dan disanitasi.

## D8 — Pemindaian kandidat dua tingkat

Pemindaian longgar memeriksa 409 berkas. Pemindaian ketat mengecualikan hanya `tests/`, `system/`, dan `composer.lock`, serta hanya mengakui hash commit yang cocok dengan `git rev-list` dan 64-hex di `docs/design`. Nilai email, telepon, password, dan hash mentah tidak dicantumkan.

| Kategori | Longgar | Ketat | Klasifikasi |
|---|---:|---:|---|
| bcrypt/Argon | 0 | 0 | Tidak ditemukan |
| Hex 32–64 karakter | 189 | 77 | 103 checksum `composer.lock`, 18 commit valid, 59 fingerprint 64-hex desain, 9 pola sumber/font `system`; tidak ada hex tak terklasifikasi |
| Email | 50 | 0 | Semua berada dalam path pengecualian; domain dan path di bawah |
| Telepon | 22 | 6 | 16 pada path pengecualian; 13 pola seluruhnya nol dan 9 non-nol |
| Literal assignment password/kunci | 3 | 2 | Satu fixture tes; dua placeholder konfigurasi kosong |
| Bentuk mirip JWT | 1 | 1 | Bukan JWT; ditemukan di `application/config/mimes.php` |
| Penanda kunci privat | 0 | 0 | Tidak ditemukan |
| Jalur Windows berisi pengguna | 0 | 0 | Tidak ditemukan |
| Apotek Demo | 0 | 0 | Target rilis tercapai; dokumen memakai “Apotek Demo” |

Domain email unik dan path (domain saja, bukan alamat):

| Domain | Path |
|---|---|
| `2bepublished.at` | `composer.lock` |
| `blankerts.de` | `composer.lock` |
| `cronyx.ru` | `system/fonts/texb.ttf` |
| `example.invalid` | `tests/unit/DemoSeederCredentialsFileTest.php` |
| `gmail.com` | `composer.lock` |
| `kore-nordmann.de` | `composer.lock` |
| `mattfarina.com` | `composer.lock` |
| `namepros.com` | `system/libraries/Email.php` |
| `oliverklee.de` | `composer.lock` |
| `php.net` | `composer.lock` |
| `phpeople.de` | `composer.lock` |
| `phpunit.de` | `composer.lock` |
| `qzdesign.co.uk` | `composer.lock` |
| `smith.com` | `system/libraries/Email.php` |
| `wallbash.com` | `composer.lock` |

Pola telepon per path dan kelas:

| Path | Nol semua | Non-nol |
|---|---:|---:|
| `application/libraries/Demo_seeder.php` | 4 | 1 |
| `application/libraries/Product_unit_service.php` | 1 | 0 |
| `system/fonts/texb.ttf` | 0 | 1 |
| `system/helpers/string_helper.php` | 0 | 1 |
| `system/libraries/Xmlrpc.php` | 0 | 2 |
| `tests/bootstrap.php` | 1 | 2 |
| `tests/characterization/PosFlowTest.php` | 0 | 1 |
| `tests/unit/ProcurementServiceTest.php` | 5 | 1 |
| `tests/unit/ProductUnitMathTest.php` | 1 | 1 |

Matriks 13 skenario sintetis:

| Skenario | Produk | Status forecast |
|---|---|---|
| fast-mover stabil | Parasetamol 500 mg | Cukup data |
| slow-mover | Kasa steril kecil | Cukup data |
| tanpa penjualan 3 bulan | Salep kulit ringan | Tidak ada penjualan |
| musiman | Vitamin C 500 mg | Cukup data |
| produk baru | Oralit sachet | Belum cukup data |
| overstock | Masker medis | Tidak ada penjualan |
| stok kritis | Antasida tablet | Cukup data |
| kedaluwarsa merah | Sirup batuk anak | Cukup data |
| kedaluwarsa kuning | Tablet hisap tenggorokan | Cukup data |
| multi-batch FEFO | Ibuprofen 200 mg | Tidak ada penjualan |
| stok nol | Termometer digital | Tidak ada penjualan |
| legacy satu | Klorfeniramin 4 mg (legacy) | Data perlu diperiksa |
| legacy dua | Zinc 20 mg (legacy) | Data perlu diperiksa |

## D9 — Validasi rilis lokal

`public-main` dibangun ulang sebagai satu root commit berjudul `Initial public release`, author `Public Release` dengan alamat placeholder `.invalid`, tanpa trailer Co-authored-by dan tanpa remote. Semua 325 berkas PHP lolos `php -l`. Delapan tautan lokal README valid; tidak ada gambar tertaut atau target lokal yang hilang. Jumlah dan ukuran pohon serta hash root dicatat pada checkpoint, bukan di dokumen rilis agar hash tidak membuat commit berubah secara sirkular.
