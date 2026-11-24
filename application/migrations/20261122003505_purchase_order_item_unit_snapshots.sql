ALTER TABLE purchase_order_items
	ADD COLUMN purchase_unit_id_snapshot BIGINT UNSIGNED NULL,
	ADD COLUMN purchase_unit_code_snapshot VARCHAR(32) NULL,
	ADD COLUMN purchase_unit_name_snapshot VARCHAR(100) NULL,
	ADD COLUMN factor_to_base_snapshot DECIMAL(18,9) NULL,
	ADD COLUMN received_quantity_base DECIMAL(15,3) NULL,
	ADD COLUMN input_quantity_scale_snapshot TINYINT UNSIGNED NULL,
	ADD COLUMN base_quantity_scale_snapshot TINYINT UNSIGNED NULL,
	ADD COLUMN unit_config_version_snapshot INT UNSIGNED NULL,
	ADD COLUMN unit_status_snapshot VARCHAR(24) NULL,
	ADD KEY idx_purchase_order_item_unit_snapshot (purchase_unit_id_snapshot),
	ADD CONSTRAINT fk_purchase_order_item_unit_snapshot FOREIGN KEY (purchase_unit_id_snapshot) REFERENCES units (id);
