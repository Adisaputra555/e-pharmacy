ALTER TABLE cart_items
	ADD COLUMN unit_id_snapshot BIGINT UNSIGNED NULL,
	ADD COLUMN unit_code_snapshot VARCHAR(32) NULL,
	ADD COLUMN unit_name_snapshot VARCHAR(100) NULL,
	ADD COLUMN conversion_factor_to_base_snapshot DECIMAL(18,9) NULL,
	ADD COLUMN quantity_base DECIMAL(15,3) NULL,
	ADD COLUMN input_quantity_scale_snapshot TINYINT UNSIGNED NULL,
	ADD COLUMN base_quantity_scale_snapshot TINYINT UNSIGNED NULL,
	ADD COLUMN unit_config_version_snapshot INT UNSIGNED NULL,
	ADD COLUMN unit_status_snapshot VARCHAR(24) NULL,
	ADD KEY idx_cart_item_unit_snapshot (unit_id_snapshot),
	ADD CONSTRAINT fk_cart_item_unit_snapshot FOREIGN KEY (unit_id_snapshot) REFERENCES units (id);
