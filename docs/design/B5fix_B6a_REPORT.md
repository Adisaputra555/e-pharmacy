# B5-fix + UI + B6a ringan

## Status

**IMPLEMENTED — DEMO ONLY.** Perbaikan U1–U7 diterapkan pada file milik B5: service procurement, controller/view procurement, seeder demo, dan tes baru. Tidak ada perubahan alur transaksi, baseline, atau data baseline.

## Ringkasan U1–U7

| Butir | Hasil |
|---|---|
| U1 | Akar: `demand_period` berisi array bersarang `['months'=>..., 'start'=>..., 'end'=>...]`, lalu view meng-implode array bulan secara mentah. Periode kini memakai helper tunggal dan ditampilkan `Agu–Okt 2026`. |
| U2 | Helper tampilan id-ID membuang nol tak berarti, memakai maksimum 2 desimal untuk D/Layak/IP/Q, 1–2 untuk L/P, dan 1 untuk coverage/estimasi. Nilai exact tetap ditulis di panel formula. |
| U3 | Kasa steril kecil awalnya mendapat `PAID_AT_MISSING` karena pembayaran inkonsisten semula ikut order yang memuat Kasa. Incidence diperbaiki di seeder sehingga produk itu kini `Cukup data` dengan demand `6/6/6`; UI selalu menampilkan kode alasan untuk status Data perlu diperiksa. |
| U4 | P, lead-time, SS terverifikasi/belum terverifikasi tetap tampil pada baris diblokir. IP, Q0, dan Q final ditandai `—` bila perhitungan diblokir. |
| U5 | Nama produk awalan `[B3 ...]` diganti menjadi nama obat realistis. Tag `skenario demo` disimpan di deskripsi dan hanya ditampilkan dengan `?debug=1` di environment demo. |
| U6 | Halaman Procurement menampilkan judul/peringatan DEMO. Dashboard demo tidak memiliki banner DEMO server-rendered berdasarkan pemeriksaan view layout/dashboard; tidak diedit karena batas existing. |
| U7 | Seeding tidak diubah untuk memaksa rekomendasi karena pengguna sudah memverifikasi manual adanya MOQ/kelipatan dan PO terbuka. Hasil otomatis final pada 26 pasangan supplier dilaporkan per kategori di bawah. |

## Kode alasan Data perlu diperiksa

Forecast_service tetap menghasilkan status dan data. Procurement_service menambahkan kode diagnosis di baris:

- `PAID_AT_MISSING`: paid order memiliki paid_at kosong; demand item tidak dihitung.
- `BASE_SNAPSHOT_INVALID`: quantity/snapshot satuan base tidak valid.
- `FIRST_RECEIPT_MISSING`: tidak ada bukti penerimaan stok pertama.
- `NET_DEMAND_NEGATIVE`: demand net negatif.
- `FORECAST_ISSUE_UNCLASSIFIED`: fallback status tanpa bukti yang dapat diklasifikasikan.

## Distribusi 25 produk

| Status forecast | Jumlah |
|---|---:|
| Cukup data | 17 |
| Tidak ada penjualan | 4 |
| Belum cukup data | 1 |
| Data perlu diperiksa | 3 |

## Pemetaan 13 skenario

| Skenario B3 | Produk | Status forecast diharapkan | Aktual |
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
| multi-batch FEFO | Ibuprofen 200 mg | Sesuai fixture | Tidak ada penjualan |
| stok nol | Termometer digital | Tidak ada penjualan | Tidak ada penjualan |
| legacy satu | Klorfeniramin 4 mg (legacy) | Data perlu diperiksa | Data perlu diperiksa |
| legacy dua | Zinc 20 mg (legacy) | Data perlu diperiksa | Data perlu diperiksa |

## Distribusi rekomendasi B5

| Hasil | Jumlah baris |
|---|---:|
| Kalkulasi belum lengkap/diblokir | 7 |
| Review manual | 4 |
| Tidak perlu pembelian | 14 |
| Pembelian disarankan | 1 |

Baris Pembelian disarankan: Antasida tablet, skenario MOQ/kelipatan. Peringatan PO terbuka otomatis terlihat pada Cetirizine 10 mg dan baris lain yang memiliki PO `ordered`; nilai PO tidak masuk IP/Q.

## Hasil uji

| Suite | Tes/assertion | Hasil |
|---|---:|---|
| Procurement presentation | 6/34 | Lulus |
| Procurement service | 21/75 | Lulus |
| Procurement demo scenario | 4/34 | Lulus |
| Procurement seed-plan | 4/19 | Lulus |
| Demo seeder volume/invariant unit | 1/85 | Lulus |
| Invariant CLI demo | 39/39 | Lulus |
| Dual rebuild determinism | 2/2 checksum | Sama: `ce36aa340659128875f67885d7c37475cff8dce3a33e36f4ee08a6efd12451fc` |

Uji demo lainnya tetap dibatasi pada environment/DB demo. Tes format memastikan teks `Array` tidak muncul dan `100.000` tidak digunakan untuk 100 tablet.

## File dan commit

Existing files yang diedit:

- `application/controllers/admin/Procurement.php`
- `application/libraries/Demo_seeder.php`
- `application/libraries/Procurement_service.php`
- `application/views/admin/procurement/index.php`

File uji baru:

- `tests/unit/ProcurementPresentationTest.php`

Commit:

- `2c3836f` — Fix B5 procurement presentation and demo diagnosis

Status Git bekerja bersih setelah commit. Tidak ada nilai akun, password, hash, atau email dicetak atau dipindahkan ke laporan/commit.

## Fingerprint dan demo

Baseline SELECT-only tetap identik:

- Penuh: 35 tabel/207 baris, `f31e084f7bd0d6bec1a37df4b12fa781704870a7257a4e1a074e2bdc94dabebb`
- Stabil: 34 tabel/161 baris, `6eabbefbd246fe235c2a2334996abb79c1579700605d99b6bc69bfd4ca672937`

Demo: 25 produk, 9 supplier, 600 order, 1.800 item, 78 PO, 26 pasangan supplier. Server loopback `127.0.0.1:18800` digunakan untuk smoke HTTP; login halaman `200`, procurement tanpa sesi tetap diarahkan.
