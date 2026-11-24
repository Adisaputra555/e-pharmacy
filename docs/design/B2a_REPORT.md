# B2a Report - P1.1 Unit Tanpa Integrasi Transaksi

## Status

**COMPLETE - 0b-2, 0b-3, P1.2-P1.8, dan integrasi transaksi tidak dijalankan.**

Migrasi additive dijalankan ke scratch lalu DB demo. Produk contoh dibuat melalui service dan ditandai `[DEMO]`. Snapshot unit ditambahkan pada tabel transaksi tetapi tidak diisi atau dipakai oleh alur transaksi lama.

## Pra-syarat dan fingerprint

Pra-syarat drift B2a diterima sebelum implementasi: baseline 207 baris dan demo 206 baris dari clone B1; perubahan yang ditinjau terbatas pada satu activity log login dan `users.last_login_at`. Gerbang sidik jari stabil diterapkan.

| Pemeriksaan | Sebelum | Sesudah | Hasil |
|---|---|---|---|
| Fingerprint penuh baseline | `b1a65417368e12b3fbb3e0d24b9ea7afa1d2b3f5fca063ddd8f05bbb6c668465` | Sama | LULUS |
| Sidik jari stabil baseline | `f739c042ef322e9a28c3494437fba9c478b1006b3fdf773ca6f12cff2894eaf3` | Sama | LULUS |
| Tabel lama demo, 35 tabel/206 baris | Sama sebelum migrasi | Tidak ada perubahan akibat migrasi | LULUS |
| Produk contoh demo | Produk lama 10 | Bertambah satu via service | Rekonsiliasi sesuai |
| Unit contoh demo | Unit lama 9 | Bertambah satu via service | Rekonsiliasi sesuai |

Acuan historis yang telah dicatat pada sesi implementasi tetap: fingerprint penuh `bdb3d0108a36a8a5422a2d38bf0b7f9cd59d2558e11ed198eab9e8c6a77a7227` dan stabil `b05c71ad188d97da65b9f8834c1052d3730daf4be7c121e4191d77e833bc022b`. Pemeriksaan final ini memakai metode SELECT-only yang sama untuk sebelum dan sesudah; kedua hasil final identik.

## Uji

| Uji | Hasil |
|---|---|
| Matematika konversi/presisi/ekstrem | LULUS, 6 tes / 6 assertions |
| Feature gate | LULUS, 1 tes / 4 assertions |
| Runner migration | LULUS, 7 tes / 28 assertions |
| Service scratch | LULUS, 3 tes / 18 assertions |
| Karakterisasi Phase 0a | LULUS 4/4 |
| Contoh produk demo empat tingkat | LULUS |
| Isolasi akun demo dan penolakan baseline | LULUS, 2 tes / 8 assertions |
| Smoke HTTP login demo | LULUS, halaman login loopback HTTP 200 |
| POST CSRF login demo tanpa kredensial | LULUS, HTTP 200 dan tidak ditolak CSRF |
| Flag OFF = perilaku lama | LULUS melalui gate; Environment selain demo/test tidak mengaktifkan flag |
| Nama DB salah / isolasi scratch | LULUS |

## Perubahan

File existing yang diedit, tepat lima atau kurang:

- `application/controllers/admin/Products.php`
- `application/views/admin/products/form.php`
- `application/views/admin/products/index.php`

Tidak ada file existing lain yang diedit. Tidak ada perubahan pada Cart, Checkout, Sales, Purchases, Inventory_model, atau baseline.

Flag: `units_v2_enabled` pada `application/config/secrets/units_v2.php`, default `false`, aktif hanya untuk environment demo. File konfigurasi tersebut ter-ignore Git.

Commit terpisah:

| Commit | Isi |
|---|---|
| `e867d4a4208e7e68db6541e9420ac545ab0f8edc` | Migrasi, service, runner, dan uji |
| `c15b7ac6fb81e52cfb56b372ada3a4ce6001ee68` | Controller dan halaman admin produk |
| `5c79b4bd5acc6b5a080d0cdd9ebc681dce8cc70a` | Memastikan launcher menetapkan environment demo ke `$_SERVER` |

Git status bersih. Tidak ada staged/committed path sensitif. Tidak ada remote.

## Temuan dan batas

Temuan perilaku lama dicatat sebagai temuan, tidak diperbaiki. Integrasi transaksi baru ditunda: `cart_items`, `order_items`, dan `purchase_order_items` mempunyai kolom snapshot additive, tetapi data lama tetap `NULL` dan alur transaksi demo/baseline belum mengisinya atau menggunakannya.

Masalah login demo yang ditemukan setelah B2a: PHP built-in server mengekspos `CI_ENV` melalui `getenv`, sedangkan `index.php` memilih environment dari `$_SERVER['CI_ENV']`. Akibatnya environment yang termuat bukan `demo`, `base_url` mengarah ke webroot live, dan submit dapat tersasar ke host live. Perbaikan terbatas pada launcher demo: `tools/start_demo.ps1` menggunakan konfigurasi lokal sebagai `auto_prepend_file`; konfigurasi itu menetapkan `$_SERVER['CI_ENV']` sebelum bootstrap dan mengatur `base_url` serta `index_page` ke origin demo. Tidak ada perubahan pada konfigurasi live atau database.

Verifikasi setelah perbaikan: form action tepat `http://127.0.0.1:18800/index.php/login`; POST berisi token CSRF saja (tanpa kredensial) diterima HTTP 200 dan tidak menghasilkan penolakan CSRF. Config demo lokal diabaikan Git melalui `.git/info/exclude`.

Cara melihat demo: jalankan `powershell -ExecutionPolicy Bypass -File tools/start_demo.ps1`, lalu buka `http://127.0.0.1:18800/index.php/login`. Biarkan jendela server berjalan.
