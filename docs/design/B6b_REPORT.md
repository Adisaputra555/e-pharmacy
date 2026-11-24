# B6b — Repository Hardening dan Public Release

## T0

| Butir | Hasil |
|---|---|
| A2 | Phase 0a post-B5 belum 4/4: POS, checkout, purchase, opname masih mendapat 307 sebelum form 200. Penyebab: server scratch loopback 18751 menerima redirect sesi ke origin baseline `http://localhost/apotek-online/login`; cookie/sesi tidak konsisten lintas origin. Konfigurasi testing baru dibuat untuk base URL loopback, tetapi rerun tetap menunjukkan 307, sehingga status tetap tidak 4/4 dan tidak ada perubahan alur transaksi. |
| A3 | Procurement dan forecast OFF/non-demo ditolak oleh unit gate; `Procurement_service::isFeatureEnabled` membutuhkan environment demo, flag true, dan marker demo. Layout admin tidak menampilkan menu/halaman procurement di environment lain. |
| Fingerprint | Baseline penuh 35 tabel/207 baris `f31e084f7bd0d6bec1a37df4b12fa781704870a7257a4e1a074e2bdc94dabebb`; stabil 34 tabel/161 baris `6eabbefbd246fe235c2a2334996abb79c1579700605d99b6bc69bfd4ca672937`. Sebelum/sesudah identik. |
| Commit | B5fix `2c3836ff9747f9687904e7b9ea075a428a560b2e`; B6a banner commit `4d5e4921a7392280534d3842941204a67ca33ca1`. |
| Data status | Tepat satu produk demo menghasilkan kasus paid_at hilang yang disengaja. Distribusi 25 produk: 17 cukup data, 4 tidak ada penjualan, 1 belum cukup data, 3 data perlu diperiksa. |

## T1

Parameter seeder diubah tanpa mengubah logika perhitungan. Dua rebuild seed `20261122` menghasilkan checksum demo identik `bde55d6158cd191dc705b8fbfe83a5d08ae85babe8a2fe48488d47191485759e`. Demo account file dibuat ulang tetapi nilainya tidak dibaca/dicetak.

| Hasil procurement | Jumlah |
|---|---:|
| Pembelian disarankan | 3 |
| Review manual | 4 |
| Tidak perlu pembelian | 12 |
| Kalkulasi belum lengkap/diblokir | 7 |

Tiga baris disarankan: normal (Parasetamol), MOQ berlaku (Antasida), kelipatan berlaku (Cetirizine/PO terbuka). Invariant checker 39/39; scenario 4/34 dan supplier plan 4/19 lulus.

## T2

Banner global DEMO ditambahkan ke layout admin dan guard dua lapis: `ENVIRONMENT==='demo'` plus `environment_marker='demo'`. Environment lain tidak menambah banner atau mengubah layout. Uji banner 2/9 lulus.

## R1

Scan semua riwayat git menghitung path unik yang cocok, bukan nilai/pola framework yang berulang:

| Kategori | Path unik | Temuan contoh path |
|---|---:|---|
| Password | 13 | README.md, application/controllers/Auth.php, application/libraries/Demo_seeder.php |
| Hash | 11 | application/controllers/Auth.php, application/libraries/Demo_seeder.php, tests/bootstrap.php |
| Token | 34 | views, controllers, security, dokumen |
| API key/secret | 2 | README.md, Payments controller |
| Email | 7 | README.md, login, pharmacy settings, tests |
| Telepon | 0 | Tidak ditemukan pola yang memenuhi regex scan |
| Alamat | 15 | seeder, settings, alamat pelanggan, dokumen |
| Rekening | 0 | Tidak ditemukan |

File terlacak yang tidak layak publik: `uploads/` dan runtime cache/log, dump SQL/backup/archive, vendor/node_modules, secret/config lokal, serta file SQL/dump data selain schema/migration yang disetujui.

## R2

store_settings demo diperiksa per kunci. Nama toko yang ditampilkan adalah `Apotek Demo`. Terdapat nama toko lain dari referensi baseline, dan placeholder demo sekarang ditulis oleh seeder:

- store_name: placeholder `Apotek Demo`
- alamat, telepon, email, NPWP, lisensi, logo, jam, tagline: placeholder/non-riil
- kunci referensi lain diklasifikasikan placeholder, lainnya, atau tidak memiliki nilai

## R3

README lama dengan akun bawaan dihapus dan diganti ringkasan portofolio. Draft portofolio tersedia di `docs/portfolio/README_DRAFT.md`. Tidak ada lisensi baru.

## R4

Branch yatim `public-main` dibuat lokal, tanpa remote/push. Commit akar `ebb86fb` berisi satu commit `Initial public release` dari README, draft portofolio, dan checklist keamanan saja. Riwayat privat tetap pada `master`.
Scan ulang `public-main`: 0 temuan pada pola secret/personal; README lama dengan akun default tidak ada.

## R5

Lihat `SECURITY_BEFORE_PUBLISH.md`.
