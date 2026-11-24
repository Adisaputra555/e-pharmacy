# ERD Database db_apotek_online

## Ringkasan Relasi Utama

```mermaid
erDiagram
  ROLES ||--o{ USER_ROLES : assigned
  ROLES ||--o{ ROLE_PERMISSIONS : grants
  USERS ||--o{ USER_ROLES : has
  USERS ||--o{ USER_ADDRESSES : has
  USERS ||--o{ ACTIVITY_LOGS : creates

  CATEGORIES ||--o{ PRODUCTS : contains
  UNITS ||--o{ PRODUCTS : measures
  MANUFACTURERS ||--o{ PRODUCTS : produces
  PRODUCTS ||--o{ PRODUCT_IMAGES : has
  PRODUCTS ||--o{ STOCK_BATCHES : has
  STOCK_BATCHES ||--o{ STOCK_MOVEMENTS : referenced_by

  SUPPLIERS ||--o{ PURCHASE_ORDERS : supplies
  PURCHASE_ORDERS ||--o{ PURCHASE_ORDER_ITEMS : contains
  PRODUCTS ||--o{ PURCHASE_ORDER_ITEMS : ordered

  USERS ||--o{ CARTS : owns
  CARTS ||--o{ CART_ITEMS : contains
  PRODUCTS ||--o{ CART_ITEMS : selected

  USERS ||--o{ ORDERS : places
  ORDERS ||--o{ ORDER_ITEMS : contains
  PRODUCTS ||--o{ ORDER_ITEMS : sold
  ORDER_ITEMS ||--o{ ORDER_ITEM_BATCHES : allocated
  STOCK_BATCHES ||--o{ ORDER_ITEM_BATCHES : FEFO
  ORDERS ||--o{ PAYMENTS : paid_by
  ORDERS ||--|| SHIPMENTS : shipped_by
  ORDERS ||--o{ ORDER_STATUS_HISTORIES : has

  USERS ||--o{ PRESCRIPTIONS : submits
  PRESCRIPTIONS ||--o{ PRESCRIPTION_ITEMS : contains
  PRESCRIPTIONS ||--o{ PRESCRIPTION_REVIEWS : reviewed_by
  ORDERS o|--o| PRESCRIPTIONS : requires

  ORDERS ||--o{ RETURNS : may_have
  RETURNS ||--o{ RETURN_ITEMS : contains
  ORDER_ITEMS ||--o{ RETURN_ITEMS : references
```

## Tabel dan Kelompok

### Identitas dan RBAC
- `roles`, `users`, `user_roles`, `role_permissions`, `activity_logs`, `user_addresses`

### Master Data
- `categories`, `units`, `manufacturers`, `suppliers`, `products`, `product_images`

### Inventaris
- `stock_batches`, `stock_movements`, `stock_opnames`, `stock_opname_items`

### Pengadaan
- `purchase_orders`, `purchase_order_items`

### Pelanggan dan Transaksi
- `carts`, `cart_items`, `orders`, `order_items`, `order_item_batches`
- `payments`, `shipments`, `order_status_histories`

### Resep
- `prescriptions`, `prescription_items`, `prescription_reviews`

### Pengaturan dan Retur
- `store_settings`, `payment_methods`, `shipping_methods`, `promotions`
- `returns`, `return_items`

## Prinsip Integritas

- Nominal menggunakan `DECIMAL(15,2)`.
- Kuantitas menggunakan `DECIMAL(15,3)`.
- Waktu menggunakan `DATETIME`; tanggal menggunakan `DATE`.
- Relasi menggunakan `BIGINT UNSIGNED` dan foreign key InnoDB.
- Stok fisik adalah `stock_batches.remaining_quantity`.
- Stok reservasi adalah `stock_batches.reserved_quantity`.
- Pengurangan stok FEFO dicatat di `order_item_batches` dan `stock_movements`.
- Batch retur tidak otomatis direstok; penambahan hanya saat `restock_allowed = 1` dan dievaluasi.
- Semua proses checkout, pembayaran, penerimaan, reservasi, dan pembatalan wajib menggunakan transaksi database dan `SELECT ... FOR UPDATE`.
