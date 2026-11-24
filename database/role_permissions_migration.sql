USE db_apotek_online;

CREATE TABLE IF NOT EXISTS role_permissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id BIGINT UNSIGNED NOT NULL,
  permission_key VARCHAR(60) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_role_permissions_role_key (role_id, permission_key),
  KEY idx_role_permissions_key (permission_key),
  CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO role_permissions (role_id, permission_key, created_at, updated_at)
SELECT roles.id, defaults.permission_key, NOW(), NOW()
FROM roles
JOIN (
  SELECT 'dashboard' AS permission_key, 'administrator' AS role_slug
  UNION ALL SELECT 'dashboard', 'apoteker'
  UNION ALL SELECT 'dashboard', 'petugas-gudang'
  UNION ALL SELECT 'dashboard', 'kasir'
  UNION ALL SELECT 'products', 'administrator'
  UNION ALL SELECT 'products', 'apoteker'
  UNION ALL SELECT 'products', 'petugas-gudang'
  UNION ALL SELECT 'categories', 'administrator'
  UNION ALL SELECT 'categories', 'petugas-gudang'
  UNION ALL SELECT 'units', 'administrator'
  UNION ALL SELECT 'units', 'petugas-gudang'
  UNION ALL SELECT 'manufacturers', 'administrator'
  UNION ALL SELECT 'manufacturers', 'petugas-gudang'
  UNION ALL SELECT 'suppliers', 'administrator'
  UNION ALL SELECT 'suppliers', 'petugas-gudang'
  UNION ALL SELECT 'inventory', 'administrator'
  UNION ALL SELECT 'inventory', 'petugas-gudang'
  UNION ALL SELECT 'purchases', 'administrator'
  UNION ALL SELECT 'purchases', 'petugas-gudang'
  UNION ALL SELECT 'sales', 'administrator'
  UNION ALL SELECT 'sales', 'kasir'
  UNION ALL SELECT 'orders', 'administrator'
  UNION ALL SELECT 'orders', 'kasir'
  UNION ALL SELECT 'orders', 'apoteker'
  UNION ALL SELECT 'prescriptions', 'administrator'
  UNION ALL SELECT 'prescriptions', 'apoteker'
  UNION ALL SELECT 'payments', 'administrator'
  UNION ALL SELECT 'payments', 'kasir'
  UNION ALL SELECT 'shipments', 'administrator'
  UNION ALL SELECT 'shipments', 'kasir'
  UNION ALL SELECT 'reports_sales', 'administrator'
  UNION ALL SELECT 'reports_sales', 'kasir'
  UNION ALL SELECT 'pharmacy_settings', 'administrator'
) AS defaults ON defaults.role_slug = roles.slug
WHERE roles.is_active = 1 AND roles.deleted_at IS NULL;
