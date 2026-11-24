# B4 Report — P1.5 Forecast dan Stock Coverage

## Status dan batas izin

B4 selesai sesuai izin P1.5: forecast Moving Average tiga bulan kalender lengkap dan stock coverage hanya-baca pada dashboard demo. Tidak ada migrasi/schema baru, tidak ada DML, baseline hanya dibaca, dan fitur tidak diintegrasikan ke alur transaksi. Fitur hanya aktif ketika environment `demo`, flag lokal bernilai `true`, dan `environment_marker` DB bernilai `demo`; environment lain, flag OFF, file flag yang tidak ada, atau marker selain `demo` tetap memakai dashboard lama.

## Klarifikasi K1–K2

- **K1:** Isolasi akun `2/8` berarti 2 tes dan 8 assertion; semuanya lulus, tidak ada tes gagal atau dilewati.
- **K2:** Rekap B3 menyatakan 24 tabel berbeda tetapi mencantumkan hanya 23 nama. Nama tabel ke-24 tidak tercatat; penyebab yang dapat dipastikan hanya ketidaklengkapan rekap, sehingga nama tabel tidak ditebak.

## Implementasi

Forecast memakai demand qualified dari order non-cancelled yang pembayaran berstatus `paid` mencapai total order. Payment diagregasi per order dan keberadaan pembayaran diverifikasi dengan `EXISTS`, sehingga item tidak berlipat ganda. Bulan penjualan berasal dari `MIN(paid_at)`; paid tanpa `paid_at` tidak dialihkan ke `created_at` dan menandai data perlu diperiksa. Kuantitas hanya dihitung dari snapshot base-unit yang verified; produk legacy tidak dikonversi dengan tebakan.

`D` adalah net demand tiga bulan kalender lengkap terakhir dibagi tiga. Bulan berjalan dikecualikan; forecast 1/3/6 bulan adalah `D × 1/3/6`. Riwayat perdagangan dimulai dari penerimaan batch pertama; movement `purchase_receive` menjadi fallback bila batch tidak menyediakan waktu penerimaan. Status mengikuti prioritas data bermasalah, belum cukup data, tidak ada penjualan, lalu cukup data. Slow-moving 90 hari tetap terpisah dari MA bulanan.

Panel memperlihatkan metode, periode, demand tiap bulan, `D`, horizon forecast, status data, base unit, coverage dan peringatan stok. UI tidak membuat klaim trend, AI, atau akurasi.

Usable stock sementara adalah jumlah `MAX(0, remaining - reserved)` untuk batch aktif dan belum kedaluwarsa. Peringatan reserved melebihi remaining dan risiko kedaluwarsa ditampilkan terpisah. Coverage mengikuti batas P1.5: `≤1` kritis, `>1–3` perhatian, `>3–6` ideal, `>6` overstock; `D=0` tidak dibagi dan berstatus tidak ada penjualan.

**File existing yang diedit (tepat 2):**
- `application/controllers/admin/Dashboard.php`
- `application/views/admin/dashboard.php`

**File baru:** `application/libraries/Forecast_service.php`, `tools/backtest_forecast.php`, `tests/unit/ForecastServiceTest.php`, dan `tests/unit/ForecastServiceDemoTest.php`. File `tests/characterization/DemoDatabaseIsolationTest.php` hanya memperbaiki ekspektasi jumlah tabel demo 35→40 yang berubah setelah B2a; hak akses/isolation tetap diuji.

**Flag:** `forecast_dashboard_enabled` di `application/config/secrets/p15_forecast.php` (konfigurasi lokal ter-ignore, tidak masuk commit). Controller juga memastikan `environment_marker=demo`; jika marker hilang/tidak dapat dibaca fitur OFF dan kesalahan dicatat. Tidak ada perubahan route/menu ataupun Cart, Checkout, Sales, Purchases, Inventory_model, atau Products.

## R7 — perbandingan definisi dashboard lama dan baru

Definisi lama memakai `orders.created_at`, mengecualikan cancelled, tetapi tidak mensyaratkan pembayaran lunas. Definisi baru memakai demand lunas dan waktu `paid_at`.

| Sumber/periode | Lama: order/item | Baru: order/item | Catatan |
|---|---:|---:|---|
| Baseline, periode uji sama | 5 / 5 | 5 / 5 | Hasil identik. |
| Demo, histori yang dibandingkan | 560 / 1.680 | 521 / 1.563 | 40 cancelled dikecualikan kedua definisi; 39 pending hanya terhitung definisi lama. Satu order paid tanpa `paid_at` ditandai perlu diperiksa dan tidak dimasukkan ke bulan demand. |

Integrasi adjustment retur/koreksi mengikuti P1.4 dan belum tersedia pada cakupan B4; snapshot stok harian dan trading-period juga belum dibuat. Usable stock merupakan pendekatan sementara karena reservasi historis P1.2 belum tersedia.

## Tiga belas skenario wajib

| Tag | Status histori | Hasil/observasi tambahan |
|---|---|---|
| `fast_mover_stable` | Cukup data | Coverage ideal |
| `slow_mover` | Data perlu diperiksa | — |
| `no_sales_3_months` | Data perlu diperiksa | — |
| `seasonal` | Cukup data | Coverage ideal |
| `new_less_3_months` | Belum cukup data | — |
| `overstock` | Tidak ada penjualan | `D=0`; coverage tidak dihitung |
| `critical_stock` | Cukup data | Coverage kritis |
| `near_expiry_red` | Cukup data | Risiko merah 30 |
| `near_expiry_yellow` | Cukup data | Data aktual menghasilkan risiko merah 10 dan kuning 0; tag kuning tidak sesuai bucket tanggal aktual |
| `multi_batch_fefo` | Data perlu diperiksa | — |
| `zero_stock` | Tidak ada penjualan | Usable stock 0 |
| `legacy_one` | Data perlu diperiksa | Unit belum diverifikasi |
| `legacy_two` | Data perlu diperiksa | Unit belum diverifikasi |

Distribusi untuk **13 skenario wajib**: 5 Data perlu diperiksa, 1 Belum cukup data, 2 Tidak ada penjualan, 5 Cukup data. Distribusi untuk **seluruh 25 produk demo**: 5 Data perlu diperiksa, 1 Belum cukup data, 2 Tidak ada penjualan, 17 Cukup data.

## Backtest demo (CLI saja)

Rolling-origin memakai tiga bulan latih dan satu bulan prediksi, periode Nov 2025–Okt 2026. Data sintetis demo bukan klaim akurasi produksi.

| Produk | Prediksi | MAE | WAPE |
|---|---:|---:|---:|
| B3-PRD-001 | 9 | 10.074 | 8.99% |
| B3-PRD-004 | 9 | 38.593 | 74.38% |
| B3-PRD-006 | 9 | 0.000 | n/a |
| B3-PRD-007 | 9 | 2.148 | 16.11% |
| B3-PRD-008 | 9 | 5.407 | 37.73% |
| B3-PRD-009 | 9 | 2.444 | 51.16% |
| B3-PRD-011 | 9 | 0.000 | n/a |
| B3-PRD-012 | 9 | 7.000 | 27.04% |
| B3-PRD-013 | 9 | 9.926 | 33.09% |
| B3-PRD-014 | 9 | 5.630 | 23.90% |
| B3-PRD-015 | 9 | 8.519 | 32.76% |
| B3-PRD-016 | 9 | 6.037 | 21.39% |
| B3-PRD-017 | 9 | 7.852 | 24.37% |
| B3-PRD-018 | 9 | 9.593 | 39.97% |
| B3-PRD-019 | 9 | 6.148 | 22.13% |
| B3-PRD-020 | 9 | 5.852 | 23.94% |
| B3-PRD-021 | 9 | 8.889 | 27.40% |
| B3-PRD-022 | 9 | 7.444 | 29.91% |
| B3-PRD-023 | 9 | 8.222 | 27.21% |
| **Agregat: 19 produk, 171 prediksi** | **171** | **7.883** | **28.47%** |

WAPE per produk tidak terdefinisi (`n/a`) saat total aktual nol.

## Validasi dan database

| Pemeriksaan | Hasil |
|---|---|
| Forecast service: MA, batas bulan, payment, status, coverage, stok/expiry, flag | 16 tes / 42 assertion — lulus |
| Integrasi demo: 13 status skenario dan backtest WAPE nol | 2 tes / 12 assertion — lulus |
| Isolasi akun demo, ledger/checksum migrasi dan jumlah tabel dinamis | 2 tes / 36 assertion — lulus |
| Guard forecast environment + flag + environment marker | 1 tes / 6 assertion — lulus |
| Karakterisasi Phase 0a | 4/4 — lulus |
| PHP lint untuk file B4 yang berubah/baru | Lulus |
| `git diff --check` | Lulus |
| Smoke `GET /index.php/login` pada demo loopback | HTTP 200, tanpa login |
| Backtest baca-saja | 19 produk, 171 prediksi; MAE 7.883, WAPE agregat 28.47% |

Baseline SELECT-only sebelum/sesudah identik:

| Sidik jari | Cakupan | SHA-256 |
|---|---|---|
| Penuh | 35 tabel / 207 baris | `f31e084f7bd0d6bec1a37df4b12fa781704870a7257a4e1a074e2bdc94dabebb` |
| Stabil | 34 tabel / 161 baris | `6eabbefbd246fe235c2a2334996abb79c1579700605d99b6bc69bfd4ca672937` |

Tidak ada perubahan skema/data demo atau baseline oleh B4.

## Commit dan cara melihat

| Commit lokal | Isi |
|---|---|
| `8ffaf725bfa510245b398d1bea5132453686ffc2` | Service forecast, tes, backtest, dan koreksi tes isolasi |
| `6f8eb2b24036e50ef67355c1f83c94e2723e383e` | Dashboard controller dan view |
| `29f2ad571b6d4ae2a95040418a2d8156edef8711` | Guard marker demo dan isolasi tabel berbasis ledger/metadata |

Kedua commit tanpa trailer; pemindaian staged path sensitif bersih. Tidak ada remote. Worktree source bersih setelah commit. Untuk melihat panel, jalankan demo di `http://127.0.0.1:18800`, lalu buka dashboard admin; fitur hanya tampil dengan konfigurasi lokal demo aktif.
