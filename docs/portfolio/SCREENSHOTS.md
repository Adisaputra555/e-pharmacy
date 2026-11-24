# Panduan Screenshot Portofolio

Simpan hasil tangkapan layar aplikasi di `docs/portfolio/img/`. Screenshot harus berasal
dari aplikasi demo lokal dengan data sintetis; jangan gunakan baseline atau data operasional.
Jangan tampilkan kredensial, email, nomor telepon, informasi akun, atau data pribadi.

## Daftar screenshot yang diperlukan

| Nama berkas | Halaman | Yang harus terlihat |
|---|---|---|
| `01-dashboard.png` | Dashboard, bagian atas | Banner DEMO, kartu ringkasan, grafik penjualan dan pembelian |
| `02-dashboard-forecast.png` | Dashboard, panel forecast dan coverage | Status data, coverage, badge status |
| `03-dashboard-ranking.png` | Dashboard, daftar terlaris dan slow-moving | Dua daftar berisi data, bukan kosong |
| `04-procurement-list.png` | Rekomendasi pengadaan | Baris "Pembelian disarankan", "Tidak perlu pembelian", dan "Kalkulasi diblokir" sekaligus |
| `05-procurement-formula.png` | Rekomendasi pengadaan | Satu baris dengan MOQ/kelipatan yang berlaku, dan panel "Lihat rincian rumus" terbuka |
| `06-units.png` | Produk → Edit pada produk demo bersatuan 4 tingkat (mis. box > strip > tablet) | Konfigurasi satuan dan status verifikasinya |

Gunakan nama persis seperti tabel. Format yang diterima: PNG, JPG/JPEG, atau WebP.
Setiap berkas harus berukuran maksimal **2 MB**.

## Cara mengambil

1. Jalankan aplikasi pada environment demo lokal yang sudah disiapkan dan buka halaman melalui
   browser setelah autentikasi demo dilakukan secara lokal.
2. Pastikan banner/penanda demo terlihat bila disediakan. Gunakan hanya data sintetis.
3. Ambil gambar pada ukuran layar yang cukup untuk membaca judul, label, dan nilai yang
   ditampilkan. Hindari dialog, notifikasi, tab lain, URL dengan parameter sensitif, serta
   panel developer/debug.
4. Jangan mengubah atau merekayasa angka, label, maupun hasil. Pangkas hanya bagian browser
   yang tidak relevan; jangan menghapus konteks demo atau menyamarkan temuan dengan manipulasi.
5. Simpan keenam berkas pada `docs/portfolio/img/`, lalu periksa nama, format, dan ukuran
   sebelum menautkannya dari README.

Jangan mengambil atau menyimpan screenshot halaman login, kredensial, konfigurasi, database,
atau informasi pengguna. Berkas tambahan selain enam nama yang diwajibkan di atas bukan bagian
dari set screenshot portofolio yang diperlukan. Setelah keenam gambar tersedia, cocokkan nama,
format, dan ukuran berkas sebelum melanjutkan pemeriksaan publikasi.