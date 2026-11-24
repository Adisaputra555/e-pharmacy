CREATE TABLE product_unit_conversion_audits (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	product_id BIGINT UNSIGNED NOT NULL,
	unit_id BIGINT UNSIGNED NOT NULL,
	actor_id BIGINT UNSIGNED NULL,
	old_values JSON NULL,
	new_values JSON NOT NULL,
	reason VARCHAR(500) NOT NULL,
	created_at DATETIME NOT NULL,
	PRIMARY KEY (id),
	KEY idx_product_unit_audit_product_time (product_id, created_at),
	KEY idx_product_unit_audit_unit (unit_id),
	KEY idx_product_unit_audit_actor (actor_id),
	CONSTRAINT fk_puca_product FOREIGN KEY (product_id) REFERENCES products (id),
	CONSTRAINT fk_puca_unit FOREIGN KEY (unit_id) REFERENCES units (id),
	CONSTRAINT fk_puca_user FOREIGN KEY (actor_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
