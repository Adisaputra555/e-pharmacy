# B5 — P1.6 Procurement Ringan

## Status

**IMPLEMENTED — DEMO ONLY; validation exceptions are documented below.** The feature is read-only and does not create purchase orders. No baseline writes or application login were performed.

## Penutupan B4 (T1–T3)

| Item | Hasil |
|---|---|
| T1 | `B4_REPORT.md` SHA-256 cocok dengan PROGRESS/CHECKPOINT; commit `8ffaf725bfa510245b398d1bea5132453686ffc2` dan `6f8eb2b24036e50ef67355c1f83c94e2723e383e` tercatat. |
| T2 | Flag forecast hanya aktif pada environment `demo`, flag `true`, dan marker `demo`; guard test lulus 1/6. |
| T3 | Isolasi menghitung tabel dari metadata skema dan ledger migrasi, termasuk migrasi nested P1.6; 2 tes/42 assertion lulus. |

## Implementasi dan batas

- Tabel additive `product_suppliers` memiliki unique pair, FK, faktor `DECIMAL(18,9)`, MOQ/kelipatan, lead-time manual, dan timestamps. Migrasi P1.6 berada pada `application/migrations/p16/20261122125200_product_suppliers.sql`.
- `tools/run_p16_migration.php` memakai `Unit_migration_runner` yang sudah ada, guard akun/target dan marker demo, serta ledger/checksum. Scratch: checksum cocok dan rerun `applied=0`; demo: satu migrasi diterapkan.
- `Procurement_service` memakai `Forecast_service`; PO berstatus `ordered` tetap berupa peringatan dan tidak masuk IP/Q. Kalkulasi base/supplier tidak membulatkan hasil yang tak representable.
- Flag `procurement_recommendations_enabled` berada di file lokal ter-ignore `application/config/secrets/p16_procurement.php`. Flag absen/OFF atau environment/marker bukan demo membuat halaman/menu tidak tersedia. Izin memakai `inventory`.
- P1.6_APPROVED.md menetapkan batas P `0 < P ≤ 24`; instruksi B5 menyebut ≤12. Mengikuti dokumen APPROVED, tanpa fallback untuk nilai invalid.
- Batas yang ditunda: P global saja; lead time manual; tidak ada draft PO, snapshot rekomendasi, estimasi biaya, atau lead time otomatis. Stok layak masih definisi sementara B4; PO terbuka legacy tidak dihitung. Seeder memakai SQL langsung dan rebuild penuh, bukan service domain atau `demo_run_id`.

## Demo

Demo dibangun ulang dengan seed `20261122`, tanggal bisnis `2026-11-22`: 25 produk, 9 supplier, 600 order, 1.800 order item, 520 paid, 78 PO, 26 pasangan produk–supplier. Tidak ada data akun/kredensial yang disalin dari baseline. Tiga rebuild tambahan dilakukan setelah koreksi fixture dan dengan persetujuan eksplisit.

Distribusi 26 baris rekomendasi:

| Hasil | Baris |
|---|---:|
| Kalkulasi belum lengkap/diblokir | 9 |
| Review manual | 3 |
| Tidak perlu pembelian | 13 |
| Pembelian disarankan | 1 |

| Skenario P1.6 | Hasil teramati |
|---|---|
| Normal | Tidak perlu pembelian |
| MOQ + kelipatan; multi-supplier | 2 baris; 1 preferred. Preferred direkomendasikan, pasangan lain diblokir karena F kosong |
| MOQ none eksplisit | Fixture ada; Q0=0 tidak memicu MOQ |
| Lead time kosong / F kosong / unit belum diverifikasi | Diblokir |
| Demand nol, 3 bulan lengkap | Review manual, tanpa Q |
| Stok melebihi target dengan demand positif | Tidak perlu pembelian |
| PO ordered terbuka | Peringatan tampil; eligible PO/IP tambahan = 0. Baris juga diblokir karena histori `Data perlu diperiksa` pada fixture paid tanpa `paid_at`. |
| Risiko expiry merah/kuning | Kuantitas risiko terpisah dari perhitungan stok/Q |

Contoh satu baris (supplier preferred): produk demo `B3-PRD-007`, D=`43/3` base unit/bulan, periode `2026-08..2026-10`, P=1 bulan, L=15 hari (manual), SS=10, stok layak=5, PO eligible=0, IP=5, Q0=26.5, F=10, MOQ M=2 dan U=2, jumlah supplier=4, Q final=40 base unit, estimasi coverage setelah tiba=`227/86` bulan (≈2.64), status “Pembelian disarankan”, target P terpenuhi.

## Validasi

| Tes / pemeriksaan | Hasil |
|---|---|
| Procurement arithmetic | 21 tes/75 assertion lulus; termasuk vektor A/B, P invalid, MOQ, demand nol, skala dan representability |
| Deterministic supplier plan | 4 tes/19 assertion lulus |
| Skenario procurement pada demo | 4 tes/34 assertion lulus |
| Checksum migrasi demo | 1 tes/8 assertion lulus |
| Scratch checksum + rerun no-op | 1 tes/8 assertion lulus; CLI `applied=0` |
| Unit migration runner root | 7 tes/28 assertion lulus |
| Seeder invariant test B3 | 1 tes/85 assertion lulus |
| Isolasi demo / ledger / metadata | 2 tes/42 assertion lulus |
| Forecast feature-marker guard | 1 tes/6 assertion lulus |
| Demo invariant checker B3 | 37/39 PASS. Dua pemeriksaan tanggal expiry gagal karena checker lama mengunci tanggal absolut `2026-11-30`/`2027-01-15`; seeder menghasilkan expiry relatif terhadap `2026-11-22`. Skenario risiko red/yellow diverifikasi terpisah oleh 4/4 tes P1.6. Checker lama tidak diedit karena batas file. |
| Phase 0a | Laporan Phase 0a/B4 sebelumnya mencatat 4/4. Re-run lokal saat ini gagal pada POS (HTTP 307 saat mengambil form terlindungi); tiga tes lain tidak dijalankan setelah tes pertama berhenti. Tidak ada perubahan pada alur transaksi. |
| HTTP demo | GET halaman login `200`; procurement tanpa sesi `307` ke login. Tidak ada login yang dilakukan. |
| Fingerprint baseline SELECT-only | Sebelum dan sesudah identik: penuh 35 tabel/207 baris, `f31e084f7bd0d6bec1a37df4b12fa781704870a7257a4e1a074e2bdc94dabebb`; stabil 34 tabel/161 baris, `6eabbefbd246fe235c2a2334996abb79c1579700605d99b6bc69bfd4ca672937`. |

## File dan commit

Existing files yang diedit:

- `application/libraries/Demo_seeder.php` (izin eksplisit B5)
- `application/views/layouts/admin.php`
- `tests/characterization/DemoDatabaseIsolationTest.php` (perluasan ledger/metadata yang sebelumnya disetujui)

Commit lokal terpisah:

- Migrasi, service, seeder, dan tes: `f66ff7ea72c2a5f875e8291e2f2a4f6348f12890`
- Controller, view, dan sidebar: `15f0109cf201c676c027be4a59b52aec15ec56a5`

Kedua commit tanpa trailer. Pemindaian staged path tidak menemukan path sensitif. Flag lokal tetap ter-ignore; status Git diperiksa setelah commit.

## Cara melihat

Jalankan `tools\start_demo.ps1`, lalu buka `http://127.0.0.1:18800/index.php/admin/procurement` setelah masuk sebagai akun demo berizin `inventory`. Selama verifikasi ini hanya halaman login yang di-GET; tidak ada kredensial yang digunakan.
