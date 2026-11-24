# B3 Report - Demo Seeder 12 Bulan

## Status dan batas

B3 selesai dengan deviasi yang dicatat di bawah. Tidak ada eksekusi B4/B5, P1.2-P1.8, 0b-2/0b-3, atau migrasi ke baseline. Perubahan source hanya file baru; `.gitignore` tidak perlu diubah karena `application/config/secrets/` sudah diabaikan. File kredensial demo dibuat di lokasi lokal yang diabaikan Git dan tidak dibaca ulang atau dicetak.

## Klarifikasi K1-K4

**K1 - drift B2a.** Baris baru di `activity_logs` adalah aksi `login_success` pada `2026-11-22 00:23:30`. Satu-satunya kolom `users` yang berubah adalah `last_login_at`. Fingerprint penuh baseline saat ini menjadi acuan: 35 tabel/207 baris; fingerprint stabil: 34 tabel/161 baris.

**K2 - rekonsiliasi B2a demo terhadap scratch termigrasi.** Perbandingan dinamis sebelum rebuild menggunakan skrip yang sama: 39 tabel demo, 40 tabel scratch, dan 39 tabel bersama. Rekap yang tersimpan menyebut 24 tabel berbeda, tetapi memuat 23 baris rinci berikut; tabel ke-24 tidak tercatat dalam rekap sebelumnya.

| Tabel | Demo | Scratch |
|---|---:|---:|
| activity_logs | 47 | 99 |
| cart_items | 0 | 19 |
| carts | 0 | 25 |
| order_item_batches | 5 | 17 |
| order_items | 5 | 17 |
| order_status_histories | 5 | 17 |
| orders | 5 | 17 |
| payments | 5 | 17 |
| product_unit_conversion_audits | 8 | 32 |
| product_unit_conversions | 4 | 16 |
| product_unit_verifications | 1 | 8 |
| products | 11 | 126 |
| purchase_order_items | 5 | 11 |
| purchase_orders | 5 | 11 |
| schema_migrations | 6 | 14 |
| shipments | 0 | 6 |
| stock_batches | 5 | 111 |
| stock_movements | 10 | 28 |
| stock_opname_items | 0 | 6 |
| stock_opnames | 0 | 6 |
| user_addresses | 0 | 25 |
| user_roles | 2 | 52 |
| users | 2 | 52 |

**K3 - flag OFF/missing.** Tes B2a `ProductUnitFeatureGateTest` menguji flag OFF dan environment non-demo. B3 menambahkan `ProductUnitMissingFlagFallbackTest`; nilai flag yang tidak tersedia (`null`) maupun `false` tetap menonaktifkan fitur. Call-site produk juga mengembalikan OFF saat konfigurasi lokal tidak dapat dimuat.

**K4 - runner.** `tools/run_p11_migrations.php` memanggil `Unit_migration_runner`; runner unit menggunakan normalisasi LF, checksum, dan lint dari `Migration_runner`. Alur apply/ledger/lock tetap terpisah. Konsolidasi tidak dilakukan karena aturan B3 melarang perubahan file existing; ini deviasi terbuka, bukan konsolidasi yang diklaim selesai.

## Rebuild, migrasi, dan seed

Rebuild hanya menargetkan `db_apotek_online_demo`, dengan pemeriksaan nama DB dan akun, dan menyalin hanya referensi yang diizinkan. Baseline hanya dibaca. Struktur dibuat dari metadata `SHOW CREATE TABLE`, kemudian data delapan tabel referensi disalin server-side:

| Referensi | Baris |
|---|---:|
| roles | 5 |
| role_permissions | 33 |
| units | 9 |
| categories | 7 |
| payment_methods | 4 |
| shipping_methods | 3 |
| store_settings | 16 |
| manufacturers | 10 |

`environment_marker=demo` dibuat. Enam migrasi B2a additive dijalankan pada demo lewat runner terjaga; rerun berikutnya no-op (`applied=0`).

| Data demo | Jumlah |
|---|---:|
| Akun (5 peran + 2 pelanggan tambahan) | 7 |
| Produk | 25 |
| Pemasok | 9 |
| Pesanan | 600 |
| Baris pesanan | 1.800 |
| Pembayaran paid | 520 |
| Pesanan cancelled/pending | 80 |
| PO received | 75 |
| PO ordered terbuka | 3 |
| Total PO | 78 |

Seed memakai tanggal bisnis `2026-11-22`, menghasilkan data dari November 2025 sampai November 2026 berjalan. Tiga belas skenario masing-masing terbentuk satu produk: fast-mover stabil, slow-mover, tanpa penjualan tiga bulan penuh, musiman, produk baru, overstock, stok kritis, batch merah hampir kedaluwarsa, batch kuning hampir kedaluwarsa, multi-batch FEFO, stok nol, serta dua produk `legacy_unverified`. Sembilan pemasok dipakai dengan sedikitnya tiga PO received per pasangan produk-pemasok utama. Ada tepat satu pembayaran paid tanpa `paid_at`, sebagai kasus inkonsistensi disengaja.

Password dibuat acak dengan `password_hash`; hanya ditulis ke `application/config/secrets/demo_accounts.txt`. Nilai akun/password/hash/email tidak dimasukkan ke laporan, output CLI, atau Git.

## Validasi

| Pemeriksaan | Hasil |
|---|---|
| Invarian demo | 39/39 lulus |
| Determinisme dua build, seed sama | Lulus; 40 tabel/7.719 baris dan checksum non-kredensial identik |
| Karakterisasi Phase 0a | 4/4 lulus |
| Guard target demo/marker | 1 tes, 6 assertions lulus |
| Scenario integration test | 1 tes, 85 assertions lulus |
| Isolasi akun app/migrator dan penolakan baseline | 2 tes, 8 assertions lulus |
| Flag OFF | 1 tes, 4 assertions lulus |
| Flag hilang/null | 1 tes, 2 assertions lulus |
| Migration runner rerun | No-op |
| Smoke `GET /index.php/login` demo | HTTP 200; tanpa login |
| Demo data available | Query invarian menunjukkan 25 produk, 600 pesanan, 1.800 item |
| Akun demo tidak dapat mengakses baseline | Lulus |
| Baseline SELECT-only sebelum/sesudah | Identik |

Checksum determinisme mengecualikan kolom kredensial dan `schema_migrations.applied_at` yang merupakan timestamp runtime. Fingerprint baseline penuh sebelum/sesudah identik: 35 tabel/207 baris, `f31e084f7bd0d6bec1a37df4b12fa781704870a7257a4e1a074e2bdc94dabebb`. Fingerprint stabil identik: 34 tabel/161 baris, `6eabbefbd246fe235c2a2334996abb79c1579700605d99b6bc69bfd4ca672937`.

## Commit dan path sensitif

| Commit | Isi |
|---|---|
| `9b4add259c8cca88c13927a31a82476b4b574168` | Rebuilder, seeder, fingerprint, pemeriksa invarian |
| `b0d44c8abed3c994d39cb15a137e13ac0159785d` | Tes B3 |

Commit terpisah, lokal, tanpa remote dan tanpa trailer. Pemindaian staged path sensitif kosong; file kredensial ter-ignore. Worktree bersih setelah commit.

## Deviasi dan keterbatasan yang diketahui

- Clone struktur memakai `SHOW CREATE TABLE` dan operasi server-side, bukan `mysqldump --no-data` yang dipipe. Ini menghindari berkas dump dan kredensial pada command line, tetapi berbeda dari metode yang diminta.
- `Migration_runner` dan `Unit_migration_runner` masih memiliki orchestration apply/lock/ledger terpisah; konsolidasi memerlukan perubahan pada file existing yang dilarang oleh batas B3.
- Seeder memakai SQL langsung untuk sebagian besar transaksi, batch, alokasi FEFO, dan pembayaran; konfigurasi satuan tetap memakai `Product_unit_service`. Ini bukan seeder domain P1.7 dan belum memakai `demo_run_id`.
- Integrasi transaksi satuan/snapshot tetap tertunda sesuai batas B2a.
- Server demo berjalan hanya pada loopback `127.0.0.1:18800`; server uji scratch `18751` sudah dihentikan.
