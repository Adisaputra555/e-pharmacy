# Checklist Pengujian Fase Akhir

## Autentikasi dan RBAC

- [ ] Login administrator menggunakan akun seed.
- [ ] Logout menghapus session.
- [ ] Dashboard dan halaman admin ditolak tanpa login.
- [ ] Pelanggan tidak dapat membuka controller admin.
- [ ] Login lima kali gagal mengunci akun sementara.
- [ ] Administrator dapat membuat dan mengubah akun serta menetapkan role bawaan.
- [ ] Akun nonaktif tidak dapat login dan perubahan role berlaku pada permintaan berikutnya.
- [ ] Administrator dapat melihat login, logout, dan aksi operasional di Aktivitas Pengguna.
- [ ] Password dan isi dokumen resep tidak disimpan dalam detail audit.
- [ ] Administrator dapat mengatur akses menu per role pada halaman Akses Role.
- [ ] Role tanpa izin tidak melihat menu dan mendapat 403 jika membuka URL modul secara langsung.
- [ ] Role pelanggan tidak dapat membuka panel admin dan administrator selalu memiliki akses penuh.

## Master Data

- [ ] Tambah, edit, cari, dan hapus kategori.
- [ ] Tambah, edit, cari, dan hapus satuan.
- [ ] Tambah, edit, cari, dan hapus produsen.
- [ ] Tambah, edit, cari, dan hapus supplier.
- [ ] Tambah, edit, cari, dan hapus produk.
- [ ] Upload foto hanya menerima JPG/JPEG/PNG/WebP.

## Inventaris

- [ ] Penerimaan batch menambah stok dan membuat stock movement.
- [ ] Nomor batch duplikat untuk produk yang sama ditolak.
- [ ] Stok keluar tidak boleh melebihi stok tersedia.
- [ ] FEFO mengambil batch tanggal kedaluwarsa terdekat.
- [ ] Batch kedaluwarsa tidak dipilih.
- [ ] Stok minimum ditampilkan dengan status minimum.
- [ ] Opname menyimpan selisih dan movement.

## Pembelian dan Penjualan

- [ ] PO menyimpan header dan item dalam satu transaksi.
- [ ] Penerimaan PO menambah batch dan mengubah status PO menjadi received.
- [ ] Produk obat resep tidak dapat diproses sebagai penjualan kasir bebas.
- [ ] Penjualan kasir tidak boleh menggunakan stok negatif.
- [ ] Invoice mencetak nominal dan detail transaksi.
- [ ] Laporan penjualan memfilter rentang tanggal.

## Pelanggan dan Checkout

- [ ] Registrasi membuat user dengan role pelanggan.
- [ ] Produk ditampilkan pada katalog.
- [ ] Keranjang tidak menerima obat resep tanpa resep disetujui.
- [ ] Checkout menghitung ulang harga dan stok di server.
- [ ] Checkout ganda menggunakan checkout token unik.
- [ ] Pembatalan sebelum pembayaran melepas reservasi.

## Resep

- [ ] Upload menerima hanya JPG/JPEG/PNG/PDF.
- [ ] File di atas 3 MB ditolak.
- [ ] Dokumen tidak dapat diakses melalui URL publik langsung.
- [ ] Apoteker dapat approve, reject, atau revision required.
- [ ] Checkout resep ditolak jika belum ada resep approved.

## Pembayaran dan Pengiriman

- [ ] Pembayaran pending tidak dianggap berhasil.
- [ ] Verifikasi manual hanya sekali mengubah status menjadi paid.
- [ ] Webhook HMAC dan gateway transaction ID divalidasi.
- [ ] Pembayaran paid mengonfirmasi pengurangan stok FEFO.
- [ ] Status pengiriman dapat diperbarui dan tercatat pada histori.

## Integritas

- [ ] Import SQL berhasil tanpa foreign key error.
- [ ] Semua tabel menggunakan InnoDB dan utf8mb4.
- [ ] Kesalahan transaksi tidak menyimpan data sebagian.
- [ ] Password tersimpan sebagai bcrypt hash.
