# Laporan Eksekusi Phase 0a

**Status: COMPLETE — tindakan salinan ke drive eksternal masih manual.** Hanya izin Phase 0a yang digunakan. Tidak ada perubahan pada perilaku aplikasi atau database operasional, dan tidak ada tindakan Phase 0b/P1.x.

## Ringkasan hasil

| Area | Hasil |
|---|---|
| Git | Repository lokal; metadata di `C:\xampp\backup\apotek-online\gitdir`; tidak ada remote. |
| Commit 1 — kode awal + `.gitignore` | `cee014d41de7e1b439a3f4f4c9655fdc70c234e2` (`Initial project snapshot`). |
| Commit 2 — tes Phase 0a | `de875cd93af5fd53f3cbba0a9d411a7ea72a20c5` (`Add Phase 0a characterization tests`). |
| Git status / staged paths | `git status --short` kosong; tidak ada staged path sensitif. Kedua pesan commit tanpa trailer. |
| `.git` melalui web | `http://localhost/apotek-online/.git/HEAD` memberikan HTTP 404 setelah pemindahan; `.htaccess` tidak diubah. |
| SQL di Git | Kedua file migration dilacak. `database/db_apotek_online.sql` tetap diabaikan. |
| Seed akun di SQL | **Ya** — dump memuat baris akun dan kolom hash password. Nilai akun/hash tidak ditampilkan. |
| Backup terenkripsi | Ada di `D:\backup_apotek`; hash arsip dan manifest di bawah. Manifest menyatakan AES-256, filename encryption, dan verifikasi ekstraksi/hash. |
| Restore scratch | `db_apotek_online_scratch_p1`: 35 tabel, 204 baris; jumlah per tabel cocok dengan fingerprint 35/204 yang tercatat sebelumnya. |
| Akun/config scratch | Akun `apotek_scratch_p1`; hanya SELECT/INSERT/UPDATE/DELETE pada schema scratch, tanpa privilege global atau DDL. Konfigurasi testing berada pada file ignored `application\config\testing\database.php`; password tidak dicetak. |
| Tes | Empat characterization test lulus (4 tes, 55 assertion); uji negatif nama DB salah ditolak dengan exit code 71. |
| Operasional | Fingerprint SELECT-only sebelum dan sesudah seluruh pekerjaan identik: 35 tabel, 206 baris; hash CSV agregat sama. Tidak ada DDL/DML terhadap baseline. |
| Cleanup staging | Dump plaintext dan dua CSV fingerprint dihapus pada path Temp persis yang telah digunakan. Arsip terenkripsi tidak dihapus. |

## Arsip dan manifest

| Artefak | Lokasi | SHA-256 |
|---|---|---|
| Arsip AES-256 | `D:\backup_apotek\apotek_online_phase0a_20261121_181549_v2.7z` | `23a7ffa8593dd64fa96720f63f5a30e9e76c425dd080aebe3f0884b5186c5587` |
| Manifest | `D:\backup_apotek\apotek_online_phase0a_20261121_181549.sha256.txt` | `8946fecce0c29b734d1fb7bc4e640cd975f906d473472708e4f8dd0ad05103d5` |
| Payload SQL (hash sebelum file plaintext dihapus) | Dump baseline `db_apotek_online` | `4711ed25ed4a01b1a0721ddadbff19ceeb5bd1c3381033c83840def27582608b` |

Manifest payload hash cocok dengan file staging sebelum restore. Hash arsip cocok dengan manifest. File dump plaintext staging yang dihapus: `[LOCAL PATH] Adi Saputra\AppData\Local\Temp\apotek-online-phase0a-staging-20261121_181549\db_apotek_online.sql`. CSV yang dihapus: `apotek-online-phase0a-baseline.csv` dan `apotek-online-phase0a-after.csv` pada `%TEMP%`. File checksum `payload.sha256` bukan dump plaintext dan tidak dihapus.

## Rekonsiliasi restore scratch

Restore dilakukan ke schema baru yang sebelumnya tidak ada, menggunakan file staging dengan hash payload di atas. Jumlah baris per tabel sama dengan catatan fingerprint baseline historis 35 tabel/204 baris:

| Tabel | Baris | Tabel | Baris |
|---|---:|---|---:|
| activity_logs | 43 | cart_items | 0 |
| carts | 0 | categories | 7 |
| manufacturers | 10 | order_item_batches | 5 |
| order_items | 5 | order_status_histories | 5 |
| orders | 5 | payment_methods | 4 |
| payments | 5 | prescription_items | 0 |
| prescription_reviews | 0 | prescriptions | 0 |
| product_images | 0 | products | 10 |
| promotions | 0 | purchase_order_items | 5 |
| purchase_orders | 5 | return_items | 0 |
| returns | 0 | role_permissions | 33 |
| roles | 5 | shipments | 0 |
| shipping_methods | 3 | stock_batches | 5 |
| stock_movements | 10 | stock_opname_items | 0 |
| stock_opnames | 0 | store_settings | 16 |
| suppliers | 10 | units | 9 |
| user_addresses | 0 | user_roles | 2 |
| users | 2 | **Total** | **204** |

## Characterization tests

| Tes | Hasil | Perilaku yang diverifikasi |
|---|---|---|
| POS | LULUS | Order kasir berstatus processing, pembayaran paid, alokasi batch FEFO dan pengurangan stok. |
| Checkout/payment | LULUS | Order/payment/shipment pending dan stok batch direservasi. |
| Penerimaan PO | LULUS | Penerimaan menghasilkan batch, movement, dan status PO received. |
| Opname | LULUS | Selisih hitung tersimpan, saldo batch disesuaikan, movement tercatat. |
| Negatif bootstrap | LULUS | Dengan nama DB selain `db_apotek_online_scratch_p1`, bootstrap berhenti sebelum koneksi database (exit 71); konfigurasi scratch dipulihkan byte-identik. |

Tes dijalankan melalui PHPUnit CLI 9.6.23 dengan `CI_ENV=testing`; HTTP hanya menuju server loopback `127.0.0.1:18751` yang memakai `auto_prepend_file` guard dan kredensial scratch. Redirect tidak diikuti. Keempat tes lulus; tidak ada perilaku salah yang teramati untuk dilaporkan.

## Fingerprint baseline operasional

Metode SELECT-only: baca seluruh kolom setiap tabel dasar, hash serialisasi setiap baris (NULL dibedakan), urutkan hash baris, lalu hitung SHA-256 tabel. Perbandingan dilakukan untuk setiap baris CSV fingerprint, bukan hanya total. Hash CSV agregat sebelum **dan** sesudah: `cc78711208c22b7b5fbdc5276b7398b64770397c7acdb0047f4efa396f799b4f`; identik, 35 tabel/206 baris.

Catatan penting: fingerprint historis 35/204 pada laporan sebelumnya berbeda dari snapshot operasional saat Phase 0a ini dimulai (35/206). Sebelum langkah yang diizinkan, `activity_logs` berubah dari 43 menjadi 45 baris; `users` tetap 2 baris tetapi hash tabel berbeda. Tidak ada nilai baris yang dicetak dan tidak disimpulkan penyebabnya. Fingerprint tepat sebelum dan sesudah pekerjaan ini identik.

| Tabel | Baris | SHA-256 |
|---|---:|---|
| activity_logs | 45 | `a9700841cd13a2a2460409775c267fa28dd85d9a2cfa77527b62f230a25b28a1` |
| cart_items | 0 | `6f7f6e1268e4f9458c0ef7301d5db7989ae0f90bd2aa537c8a02406877f6f391` |
| carts | 0 | `4635bc945aca39c19eb8a8a8d64a351b74aff22221bb2e5764c4b751eaf64d34` |
| categories | 7 | `3bb4fededcf4e73cdce9d001ba44b482d0c06f67ec3d6ed3ec15a9ede99f29df` |
| manufacturers | 10 | `ddd7e8ac0c63162d833509b7ce96a29573cebd72bc8e8f8a653d19e2632ccc09` |
| order_item_batches | 5 | `cd7668187d1120f60518a972caba3a81db2d6af9a52fb83695fc5308251ac3b4` |
| order_items | 5 | `b11e58fb1a62441adf40d4cb11d8ead798a5ddaf752fb9d3b5bb57d2a9ddfebf` |
| order_status_histories | 5 | `c4630a024983a3ebf69d486e0f0e5ace027b6ea57320676284fcf0a63e75742c` |
| orders | 5 | `73a24886d2698afa5f40deaff73eacbe5a2bb871410ef129024afda92ea2cd57` |
| payment_methods | 4 | `4f227dfb5c1c8751a45b70d95af84aec46ff697225aa19cc0fe6b9b77362b58b` |
| payments | 5 | `eabe4f31a0b33ef03d9086be76569a3ce49f3e7364ee19424550ad419af223bc` |
| prescription_items | 0 | `ad62c6165b3e2fb87f2b5720a607b54c30dd784558ae2bf34c34f5c05f224366` |
| prescription_reviews | 0 | `62d7882be7ffb876c97b14adaff394c45d3e1423cc01bc2f187311a0799e07f1` |
| prescriptions | 0 | `272b3df4c5594183a08d0f32682829f30f1fa8c55da24efb929f62d7f48afaf0` |
| product_images | 0 | `34d056de803e0e5cc86efaa3aa7d4d6f1aa7055f803de19de25bd6981b7ca8c3` |
| products | 10 | `7076e507cf2a4c5ae6e7b8bed2ed13787ee277e72e18f15357e7300c4d91ccbe` |
| promotions | 0 | `14c5e0838c49b3bc7ddd6e6840c83e86d20d6be79e74ef6da049988167572589` |
| purchase_order_items | 5 | `97d7edb03efa903fb4dec42dab27c43fed02672ff8137122e639214a45c65fab` |
| purchase_orders | 5 | `d737ae17e1bfa00df4f716275298476197a4b8d92440e45c0ce48a41c2ed48fa` |
| return_items | 0 | `b1f4b82f59262b34cca7ce1bbd30537b0d43e9237a207bef15b3487561683a69` |
| returns | 0 | `88fbb7ff59a1a6a06ea4ecb573f4394356e069ac7b78928ca09d9bca4bb725f4` |
| role_permissions | 33 | `6a55eb30066acc013634d9e52881746c0cff33dc25ffac5c2e88a020878949da` |
| roles | 5 | `03c6d44fa1cf160fc794f1b29e235f388a9c9bda4fdc7fee0925a30b498cc866` |
| shipments | 0 | `e80d05fa3efe1ba5a77c44a9872e662fa2dc6a711ee5fdf15a2895ef41c53a1d` |
| shipping_methods | 3 | `33f51229e4102e0c67b52a72700f202c846524e5da0a229d910d18e7c964c948` |
| stock_batches | 5 | `8eff08b2efcdf3aca66612e019474949ce432bf8fbcd142c82a00946a868ebcd` |
| stock_movements | 10 | `2338d7d38d447ae0e6dd06420dde5d56505674f672d93921dded544874f1b79f` |
| stock_opname_items | 0 | `8bc4546d4b49c217f7324f9e57cb5bce5ca46dd55ee94ef6c2b145b7704690ad` |
| stock_opnames | 0 | `4abebd1d893302db9cefcd8803a1bd3ad73c0354fd44ff506e254c20d77089c0` |
| store_settings | 16 | `e0ceca185fca880b639f039204c2853af95cf02ac7af774414d3360b4c410a27` |
| suppliers | 10 | `30c5396b37613a55c2a45e9a44a0f365b4945165209d876d9fbfb11815a77cf6` |
| units | 9 | `841ee5f2141e6f1e7366cfe65104bf96c7d6a62acde93aef3486a502a43ef2e5` |
| user_addresses | 0 | `981a5f26cbbe8df21cb92593fae37c5596cd7cb88ddab138387e16c485817abd` |
| user_roles | 2 | `164cacceede250f77826fc10d7ee7788b7dd85d1e86076693d58fc85c71aca11` |
| users | 2 | `9cbf51681dbac82b46b4ef809e06b91b24674495c95bd76b32c317dbef5acba9` |

## Tindakan manual tersisa

Salin arsip terenkripsi dan manifest dari `D:\backup_apotek` ke drive eksternal/offline, lalu verifikasi hash salinannya. Langkah ini belum dilakukan. Scratch DB, akun scratch, dan konfigurasi testing dipertahankan; tidak ada DROP/restore ulang atau penghapusan data lain.
