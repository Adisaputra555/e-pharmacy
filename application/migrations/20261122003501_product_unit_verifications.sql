CREATE TABLE product_unit_verifications (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	product_id BIGINT UNSIGNED NOT NULL,
	base_unit_id BIGINT UNSIGNED NOT NULL,
	source_unit_description VARCHAR(255) NOT NULL,
	mapping_evidence TEXT NOT NULL,
	verified_by BIGINT UNSIGNED NOT NULL,
	verified_at DATETIME NOT NULL,
	status VARCHAR(24) NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_product_unit_verification_product (product_id),
	KEY idx_product_unit_verification_unit (base_unit_id),
	KEY idx_product_unit_verification_actor (verified_by),
	CONSTRAINT fk_puv_product FOREIGN KEY (product_id) REFERENCES products (id),
	CONSTRAINT fk_puv_unit FOREIGN KEY (base_unit_id) REFERENCES units (id),
	CONSTRAINT fk_puv_user FOREIGN KEY (verified_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
