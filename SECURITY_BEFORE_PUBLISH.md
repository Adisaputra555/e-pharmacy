# Security Before Publish

## Status Repository

| Butir | Status | Catatan |
|---|---|---|
| Pemindaian riwayat git | Selesai | Laporan di `design/B6b_REPORT.md`; riwayat privat tidak diubah. |
| Pemindaian file terlacak | Selesai untuk cabang publik | Snapshot cabang publik akan dinilai ulang; hasil harus nol temuan secret. |
| Kredensial demo tidak tertulis | Selesai | `demo_accounts.txt` dan konfigurasi rahasia tetap ter-ignore. |
| Upload tidak dilacak | Selesai | `uploads/` masuk .gitignore. |
| Dump/data backup tidak dilacak | Selesai | Dump, backup, dan archive dilindungi .gitignore. |
| Vendor tidak dilacak | Selesai | `vendor/` masuk .gitignore. |
| `.git` di luar webroot | Selesai | Metadata Git dipisahkan dari webroot/source. |
| Remote git | Selesai | Tidak ada remote; tidak ada push. |
| Lisensi baru | Tidak berlaku untuk repo-saja | Lisensi menunggu keputusan pengguna. |
| Akun default XAMPP lokal | Belum | Hanya relevan saat hosting; tidak ditutup oleh keputusan repo-saja. |
| Akun DB live | Belum | Akun/host/root live tidak dibuka atau diubah. |

## Cabang Publik

`public-main` dibuat sebagai cabang yatim lokal dengan satu commit portofolio. Riwayat privat lengkap tetap berada pada cabang lama.
