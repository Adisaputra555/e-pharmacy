CREATE TABLE product_unit_conversions (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	product_id BIGINT UNSIGNED NOT NULL,
	unit_id BIGINT UNSIGNED NOT NULL,
	conversion_factor_to_base DECIMAL(18,9) NOT NULL,
	input_scale TINYINT UNSIGNED NOT NULL,
	base_scale TINYINT UNSIGNED NOT NULL,
	status VARCHAR(24) NOT NULL,
	config_version INT UNSIGNED NOT NULL,
	created_at DATETIME NOT NULL,
	updated_at DATETIME NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_product_unit_conversion (product_id, unit_id),
	KEY idx_product_unit_conversion_unit (unit_id),
	CONSTRAINT fk_puc_product FOREIGN KEY (product_id) REFERENCES products (id),
	CONSTRAINT fk_puc_unit FOREIGN KEY (unit_id) REFERENCES units (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
