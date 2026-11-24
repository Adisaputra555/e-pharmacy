CREATE TABLE product_suppliers (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	product_id BIGINT UNSIGNED NOT NULL,
	supplier_id BIGINT UNSIGNED NOT NULL,
	is_preferred TINYINT(1) NOT NULL DEFAULT 0,
	purchase_unit VARCHAR(100) NOT NULL,
	base_per_purchase_unit DECIMAL(18,9) NULL,
	moq_mode ENUM('none','set') NOT NULL,
	moq_purchase_units DECIMAL(15,3) NULL,
	order_multiple_purchase_units DECIMAL(15,3) NULL,
	manual_lead_time_days SMALLINT UNSIGNED NULL,
	created_at DATETIME NOT NULL,
	updated_at DATETIME NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_product_supplier_pair (product_id, supplier_id),
	KEY idx_product_suppliers_supplier (supplier_id),
	CONSTRAINT chk_product_supplier_factor_positive
		CHECK (base_per_purchase_unit IS NULL OR base_per_purchase_unit > 0),
	CONSTRAINT fk_product_suppliers_product
		FOREIGN KEY (product_id) REFERENCES products (id),
	CONSTRAINT fk_product_suppliers_supplier
		FOREIGN KEY (supplier_id) REFERENCES suppliers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
