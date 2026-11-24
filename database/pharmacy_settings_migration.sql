USE db_apotek_online;

INSERT INTO store_settings (setting_key, setting_value, is_public, created_at, updated_at) VALUES
('store_name', 'Apotek Online', 1, NOW(), NOW()),
('store_tagline', '', 1, NOW(), NOW()),
('store_logo', '', 1, NOW(), NOW()),
('store_address', '', 1, NOW(), NOW()),
('store_phone', '', 1, NOW(), NOW()),
('store_email', '', 1, NOW(), NOW()),
('store_website', '', 1, NOW(), NOW()),
('store_hours', '', 1, NOW(), NOW()),
('pharmacist_name', '', 1, NOW(), NOW()),
('pharmacist_license', '', 1, NOW(), NOW()),
('pharmacy_license', '', 1, NOW(), NOW()),
('store_npwp', '', 0, NOW(), NOW()),
('receipt_footer', '', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

UPDATE store_settings SET is_public = 0 WHERE setting_key = 'store_npwp';

INSERT IGNORE INTO role_permissions (role_id, permission_key, created_at, updated_at)
SELECT id, 'pharmacy_settings', NOW(), NOW()
FROM roles
WHERE slug = 'administrator' AND is_active = 1 AND deleted_at IS NULL;
