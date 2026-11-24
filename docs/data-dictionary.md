# Data Dictionary db_apotek_online

## users

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | BIGINT UNSIGNED PK | Identitas pengguna |
| email | VARCHAR(190) UNIQUE | Login pengguna |
| password_hash | VARCHAR(255) | Hasil `password_hash()` |
| full_name | VARCHAR(150) | Nama lengkap |
| phone | VARCHAR(30) | Nomor telepon |
| status | VARCHAR(20) | active / inactive / locked |
| failed_login_attempts | SMALLINT UNSIGNED | Percobaan login gagal |
| locked_until | DATETIME NULL | Waktu buka kunci |
| last_login_at | DATETIME NULL | Login terakhir |
| created_at, updated_at, deleted_at | DATETIME | Metadata dan soft delete |

## role_permissions

| Kolom | Tipe | Keterangan |
|---|---|---|
| role_id | BIGINT UNSIGNED FK | Role penerima akses |
| permission_key | VARCHAR(60) | Kunci akses menu/modul operasional |
| created_at, updated_at | DATETIME | Waktu pemberian/perubahan akses |

Setiap baris memberi akses ke satu menu. Administrator selalu memiliki seluruh akses; akses beberapa role pada satu pengguna digabungkan.

## products

| Kolom | Tipe | Keterangan |
|---|---|---|
| code | VARCHAR(50) UNIQUE | Kode produk |
| barcode | VARCHAR(100) UNIQUE NULL | Barcode |
| name | VARCHAR(150) | Nama dagang obat |
| generic_name | VARCHAR(150) | Nama generik |
| purchase_price, selling_price | DECIMAL(15,2) | Harga pembelian dan penjualan |
| minimum_stock | DECIMAL(15,3) | Ambang stok minimum |
| drug_classification | VARCHAR(50) | bebas / bebas terbatas / keras / narkotika / psikotropika / lain |
| requires_prescription | BOOLEAN | Wajib resep jika bernilai 1 |

## stock_batches

| Kolom | Tipe | Keterangan |
|---|---|---|
| batch_number | VARCHAR(100) | Nomor batch |
| received_at | DATETIME | Waktu penerimaan |
| expiration_date | DATE | Tanggal kedaluwarsa |
| initial_quantity | DECIMAL(15,3) | Kuantitas awal batch |
| remaining_quantity | DECIMAL(15,3) | Kuantitas fisik tersisa |
| reserved_quantity | DECIMAL(15,3) | Kuantitas yang sudah direservasi |
| purchase_price, selling_price | DECIMAL(15,2) | Harga batch |
| status | VARCHAR(20) | active / blocked / expired / depleted |

## stock_movements

| Kolom | Tipe | Keterangan |
|---|---|---|
| product_id | BIGINT UNSIGNED FK | Produk yang bergerak |
| batch_id | BIGINT UNSIGNED FK | Batch sumber; boleh NULL untuk penyesuaian umum |
| movement_type | VARCHAR(30) | purchase_receive / sale / reservation / reservation_release / adjustment / return_restock / opname |
| reference_type, reference_id | VARCHAR + BIGINT | Dokumen asal pergerakan |
| quantity | DECIMAL(15,3) | Nilai perubahan |
| balance_after | DECIMAL(15,3) | Saldo setelah perubahan |

## purchase_orders

| Kolom | Tipe | Keterangan |
|---|---|---|
| purchase_number | VARCHAR(50) UNIQUE | Nomor pembelian |
| supplier_id | BIGINT UNSIGNED FK | Supplier |
| status | VARCHAR(30) | draft / ordered / partially_received / received / cancelled |
| subtotal, discount, total | DECIMAL(15,2) | Nominal pembelian |
| received_at | DATETIME | Waktu penerimaan validasi |

## orders

| Kolom | Tipe | Keterangan |
|---|---|---|
| order_number | VARCHAR(50) UNIQUE | Nomor pesanan |
| checkout_token | VARCHAR(100) UNIQUE | Pencegah double checkout |
| address_snapshot | JSON | Salinan alamat saat checkout |
| status | VARCHAR(30) | pending / verified / processing / packed / shipped / delivered / cancelled |
| requires_prescription | BOOLEAN | Menandai pesanan obat resep |
| prescription_status | VARCHAR(30) | pending / under_review / approved / rejected / revision_required |
| subtotal, discount, shipping_cost, total | DECIMAL(15,2) | Nominal hasil revalidasi server |

## prescriptions

| Kolom | Tipe | Keterangan |
|---|---|---|
| private_file_path | VARCHAR(255) | Lokasi di luar document root |
| file_name, mime_type, file_size | String / integer | Metadata upload |
| status | VARCHAR(30) | pending / under_review / approved / rejected / revision_required |

## payments

| Kolom | Tipe | Keterangan |
|---|---|---|
| gateway_transaction_id | VARCHAR(191) | ID transaksi gateway |
| webhook_signature_hash | VARCHAR(255) | Bukti identitas webhook yang telah divalidasi |
| status | VARCHAR(30) | pending / paid / failed / expired / refunded |

## returns

| Kolom | Tipe | Keterangan |
|---|---|---|
| status | VARCHAR(30) | requested / reviewed / approved / received / rejected / completed |
| restock_allowed | BOOLEAN | Hanya bernilai 1 setelah evaluasi kondisi |

## Tabel Relasional Tambahan

Tabel berikut merupakan tabel pivot atau child yang wajib memiliki foreign key ke induknya:
`user_roles`, `role_permissions`, `product_images`, `purchase_order_items`, `stock_movements`,
`stock_opname_items`, `cart_items`, `order_items`, `order_item_batches`,
`payments`, `shipments`, `order_status_histories`, `prescription_items`,
`prescription_reviews`, dan `return_items`.
