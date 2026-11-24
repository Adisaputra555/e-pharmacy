# Jalur B - B1 Laporan Eksekusi

**Status: COMPLETE - hanya sesi B1.** Tidak ada tindakan 0b-2/0b-3, migrasi
baseline, implementasi P1.x, atau login ke aplikasi live.

## 1. Prasyarat dan runner/clock

`PHASE_0b1_REPORT.md` tersedia dan lulus; SHA-256:
`ab866ac134c8ae8894005a6c1678b7b2d2eb4aacb3c8df90b52f572e9caf1aa3`.
Sesuai keputusan, runner dan `Business_clock` yang ada dipakai, dan pembuatan
ulang runner/clock pada butir 3 dilewati. Runner yang ada tetap dipatok ke
`db_apotek_online_scratch_p1`; uji runner dilakukan pada scratch. Tidak ada
migrasi yang dijalankan pada DB demo.

## 2. DB demo dan akun

- Baseline hanya dibaca. Clone dibuat dengan `mysqldump --single-transaction`
  yang langsung di-pipe ke klien restore; tidak ada dump sementara di disk.
  `--skip-add-drop-table` digunakan karena target DB baru dan kosong.
- `db_apotek_online_demo` cocok dengan baseline: 35 tabel dan 206 baris; jumlah
  baris cocok per tabel. Setelah smoke test tetap 35/206.
- `apotek_demo_app` memiliki tepat `SELECT, INSERT, UPDATE, DELETE` pada
  `db_apotek_online_demo.*`.
- `apotek_demo_migrator` memiliki tepat `CREATE, ALTER, INDEX, REFERENCES,
  SELECT, INSERT, UPDATE, DELETE` pada `db_apotek_online_demo.*`. Tidak diberi
  `DROP`, `GRANT`, privilege global, atau hak pada DB operasional.
- Hasil `SHOW GRANTS` diverifikasi di memori; output grant mentah tidak dicetak.
  Uji koneksi memastikan kedua akun dapat memakai demo dan ditolak pada
  `db_apotek_online`.
- Password acak kedua akun hanya disimpan di
  `application/config/demo/database.php`, yang diabaikan Git. Tidak ada
  password, hash kredensial, atau email di laporan, output uji, atau commit.

## 3. File dan environment

Perubahan file existing tepat dua:

1. `.gitignore`: tambah aturan `application/config/demo/database.php`.
2. `index.php`: tambah satu case `demo` pada kelompok environment testing/
   production. Environment lain tidak diubah:

```diff
 	case 'testing':
+	case 'demo':
 	case 'production':
```

File baru lain: `tools/start_demo.ps1`,
`tests/demo_database_bootstrap.php`, dan
`tests/characterization/DemoDatabaseIsolationTest.php`. Tidak ada perubahan
source/config existing lain. Script men-set `CI_ENV=demo`, lalu menjalankan
server hanya pada `127.0.0.1:18800`; tidak memuat kredensial.

## 4. Validasi

| Uji | Hasil |
|---|---|
| Phase 0a POS | LULUS - 1 test / 15 assertion |
| Phase 0a checkout/payment | LULUS - 1 / 13 |
| Phase 0a penerimaan PO | LULUS - 1 / 14 |
| Phase 0a opname | LULUS - 1 / 13 |
| Runner existing di scratch | LULUS - 6 test / 22 assertion: dry-run, lint, lock timeout, key/checksum mismatch, idempotensi, dan LF/CRLF |
| Runner menolak target baseline | LULUS - `db_apotek_online` ditolak sebelum query DB |
| Guard nama DB negatif | LULUS - exit 81 sebelum koneksi |
| BusinessClock existing | LULUS - 2 test / 4 assertion |
| Isolasi DB demo | LULUS - 2 test / 8 assertion, termasuk `SHOW GRANTS`, app tanpa DDL, dan kedua akun ditolak dari baseline |
| Smoke HTTP | LULUS - GET `/index.php/login` pada loopback mengembalikan HTTP 200; tidak login |
| CLI migration dry-run | LULUS - exit 0, tanpa write |
| Syntax / patch | LULUS - file PHP baru dan `index.php` lolos `php -l`; `git diff --check` bersih |

Server uji dihentikan setelah smoke; port 18751 dan 18800 tidak memiliki
listener. Tidak ada file dump staging.

## 5. Fingerprint baseline

Fingerprint SELECT-only diukur sebelum dan sesudah seluruh clone/uji. Untuk
setiap tabel, hash SHA-256 baris diurutkan dan dibandingkan; hasil identik:
35 tabel / 206 baris. SHA-256 agregat sebelum dan sesudah:
`406c920bffb73f4272fc7a70b79b8db9b49e8a5e5aacb36eedf770701b079b67`.
Tidak ada tabel atau baris baseline yang berubah.

## 6. Git

- Commit lokal terpisah: `be20a34c5146c0e1204c63553ba1e98a0ff08b1f`.
- Lima path dikomit; scan staged menemukan 0 path sensitif. Tidak ada trailer
  Co-authored-by, remote, atau perubahan tersisa (`git status --short` kosong).
- DB demo dan akun demo tetap lokal; file konfigurasi kredensial tidak dilacak.

**Status akhir:** B1 selesai. Tahap B2/P1.1, B3, B4/P1.5, B5/P1.6-ringan, dan
semua tahap 0b/P1.x yang tidak termasuk B1 tetap menunggu izin masing-masing.
