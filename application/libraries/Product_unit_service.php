<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Product_unit_service
{
	private $connection;
	private $clock;

	public function __construct($connection, Business_clock $clock = null)
	{
		if (!($connection instanceof mysqli)) {
			throw new InvalidArgumentException('Product unit service requires a mysqli connection.');
		}
		if (!extension_loaded('bcmath')) {
			throw new RuntimeException('BCMath is required for exact unit conversion.');
		}

		$this->connection = $connection;
		$this->clock = $clock ?: new Business_clock();
	}

	public static function calculateBaseQuantity($quantity, $factor, $inputScale, $baseScale)
	{
		$inputScale = (int) $inputScale;
		$validatedBaseScale = filter_var($baseScale, FILTER_VALIDATE_INT);
		if ($validatedBaseScale === false) {
			throw new InvalidArgumentException('Base-unit precision must be an integer.');
		}
		$baseScale = (int) $validatedBaseScale;
		if ($inputScale < 0 || $inputScale > 3 || $baseScale < 0 || $baseScale > 3) {
			throw new InvalidArgumentException('Unit precision must be between zero and three decimals.');
		}

		$submittedScale = self::decimalScale($quantity);
		$quantity = self::normalizeDecimal($quantity, 3, 'quantity');
		$factor = self::normalizeDecimal($factor, 9, 'conversion factor');
		if ($submittedScale > $inputScale) {
			throw new InvalidArgumentException('Input quantity exceeds its configured precision.');
		}
		if (bccomp($quantity, '0', 3) <= 0 ||
			bccomp($factor, '0', 9) <= 0 ||
			bccomp($quantity, '999999999999.999', 3) > 0 ||
			bccomp($factor, '999999999.999999999', 9) > 0) {
			throw new InvalidArgumentException('Quantity or conversion factor is outside the supported range.');
		}

		$baseQuantity = bcmul($quantity, $factor, 12);
		if (bccomp($baseQuantity, '999999999999.999', 12) > 0) {
			throw new InvalidArgumentException('Converted quantity exceeds DECIMAL(15,3).');
		}
		$fraction = strpos($baseQuantity, '.') === false
			? ''
			: rtrim(substr($baseQuantity, strpos($baseQuantity, '.') + 1), '0');
		if (strlen($fraction) > $baseScale) {
			throw new InvalidArgumentException('Converted quantity cannot be represented at the base-unit precision.');
		}

		return bcadd($baseQuantity, '0', 3);
	}

	public static function normalizeDecimal($value, $maxScale, $label)
	{
		if (!is_string($value) && !is_int($value)) {
			throw new InvalidArgumentException(ucfirst($label) . ' must be a decimal string.');
		}
		$value = trim((string) $value);
		if (!preg_match('/^[0-9]+(?:\.[0-9]+)?$/', $value)) {
			throw new InvalidArgumentException(ucfirst($label) . ' must be a non-negative decimal value.');
		}

		$scale = self::decimalScale($value);
		if ($scale > (int) $maxScale) {
			throw new InvalidArgumentException(ucfirst($label) . ' has too many decimal places.');
		}
		$normalized = bcadd($value, '0', (int) $maxScale);
		if (bccomp($normalized, '0', (int) $maxScale) < 0) {
			throw new InvalidArgumentException(ucfirst($label) . ' cannot be negative.');
		}
		return $normalized;
	}

	public static function decimalScale($value)
	{
		$point = strpos((string) $value, '.');
		return $point === false ? 0 : strlen((string) $value) - $point - 1;
	}

	public function getStatus($productId)
	{
		$product = $this->first(
			'SELECT unit_id FROM products WHERE id = ? AND deleted_at IS NULL',
			'i',
			array((int) $productId)
		);
		if (!$product) {
			throw new RuntimeException('Product was not found.');
		}
		if ($this->first('SELECT id FROM product_unit_verifications WHERE product_id = ? LIMIT 1', 'i', array((int) $productId))) {
			return 'verified';
		}
		return $product['unit_id'] === null ? 'unknown' : 'legacy_unverified';
	}

	public function getStatuses(array $products)
	{
		$ids = array();
		$statuses = array();
		foreach ($products as $product) {
			$id = (int) (is_object($product) ? $product->id : $product['id']);
			$unitId = is_object($product) ? $product->unit_id : $product['unit_id'];
			$ids[] = $id;
			$statuses[$id] = $unitId === null ? 'unknown' : 'legacy_unverified';
		}
		$ids = array_values(array_unique(array_filter($ids)));
		if (!$ids) {
			return $statuses;
		}

		$placeholders = implode(',', array_fill(0, count($ids), '?'));
		$rows = $this->all(
			'SELECT product_id FROM product_unit_verifications WHERE product_id IN (' . $placeholders . ')',
			str_repeat('i', count($ids)),
			$ids
		);
		foreach ($rows as $row) {
			$statuses[(int) $row['product_id']] = 'verified';
		}
		return $statuses;
	}

	public function getConfiguration($productId)
	{
		return $this->all(
			'SELECT c.unit_id, u.code AS unit_code, u.name AS unit_name, ' .
			'c.conversion_factor_to_base, c.input_scale, c.base_scale, c.status, c.config_version ' .
			'FROM product_unit_conversions c JOIN units u ON u.id = c.unit_id ' .
			'WHERE c.product_id = ? ORDER BY c.unit_id',
			'i',
			array((int) $productId)
		);
	}

	public function getVerification($productId)
	{
		return $this->first(
			'SELECT v.base_unit_id, v.source_unit_description, v.mapping_evidence, v.verified_at, ' .
			'v.status, u.full_name AS verifier_name ' .
			'FROM product_unit_verifications v JOIN users u ON u.id = v.verified_by ' .
			'WHERE v.product_id = ? LIMIT 1',
			'i',
			array((int) $productId)
		);
	}

	public function isBaseUnitLocked($productId)
	{
		$row = $this->first(
			'SELECT COUNT(*) AS total FROM product_unit_conversions WHERE product_id = ?',
			'i',
			array((int) $productId)
		);
		return $row && (int) $row['total'] > 0;
	}

	public function saveConfiguration($productId, array $alternatives, $baseScale, $actorId, $reason)
	{
		$productId = (int) $productId;
		$actorId = (int) $actorId;
		$baseScale = (int) $baseScale;
		$reason = trim((string) $reason);
		if ($productId < 1 || $actorId < 1 || $reason === '' || strlen($reason) > 500) {
			throw new InvalidArgumentException('A product, administrator, and audit reason are required.');
		}
		if ($baseScale < 0 || $baseScale > 3 || count($alternatives) > 3) {
			throw new InvalidArgumentException('A product may use at most four units with precision from zero to three.');
		}

		$this->begin();
		try {
			$product = $this->first(
				'SELECT unit_id FROM products WHERE id = ? AND deleted_at IS NULL FOR UPDATE',
				'i',
				array($productId)
			);
			if (!$product) {
				throw new RuntimeException('Product was not found.');
			}
			if ($product['unit_id'] === null) {
				throw new RuntimeException('A product with unknown base unit cannot be configured.');
			}

			$baseUnit = $this->activeUnit((int) $product['unit_id']);
			$expected = array(
				(int) $baseUnit['id'] => array(
					'unit_id' => (int) $baseUnit['id'],
					'factor' => '1.000000000',
					'input_scale' => $baseScale,
					'base_scale' => $baseScale
				)
			);
			foreach ($alternatives as $alternative) {
				if (!is_array($alternative) ||
					!isset($alternative['unit_id'], $alternative['factor'], $alternative['input_scale'])) {
					throw new InvalidArgumentException('Every alternate unit requires an ID, factor, and precision.');
				}
				$unitId = filter_var($alternative['unit_id'], FILTER_VALIDATE_INT);
				$inputScale = filter_var($alternative['input_scale'], FILTER_VALIDATE_INT);
				if ($unitId === false || $unitId < 1 ||
					$inputScale === false || $inputScale < 0 || $inputScale > 3 ||
					isset($expected[$unitId])) {
					throw new InvalidArgumentException('Alternate units must be unique and use valid precision.');
				}
				$unitId = (int) $unitId;
				$this->activeUnit($unitId);
				$factor = self::normalizeDecimal($alternative['factor'], 9, 'conversion factor');
				if (bccomp($factor, '0', 9) <= 0 ||
					bccomp($factor, '999999999.999999999', 9) > 0) {
					throw new InvalidArgumentException('Conversion factor must fit DECIMAL(18,9) and be positive.');
				}
				$expected[$unitId] = array(
					'unit_id' => $unitId,
					'factor' => $factor,
					'input_scale' => (int) $inputScale,
					'base_scale' => $baseScale
				);
			}
			if (count($expected) > 4) {
				throw new InvalidArgumentException('A product may use at most four units including its base unit.');
			}

			$current = $this->all(
				'SELECT unit_id, conversion_factor_to_base, input_scale, base_scale, status, config_version ' .
				'FROM product_unit_conversions WHERE product_id = ? FOR UPDATE',
				'i',
				array($productId)
			);
			$currentByUnit = array();
			foreach ($current as $row) {
				$currentByUnit[(int) $row['unit_id']] = $row;
				if (!isset($expected[(int) $row['unit_id']])) {
					throw new RuntimeException('Configured units cannot be removed; include every existing unit.');
				}
			}

			$verification = $this->first(
				'SELECT id FROM product_unit_verifications WHERE product_id = ? LIMIT 1',
				'i',
				array($productId)
			);
			$unchanged = $this->configurationMatches($expected, $currentByUnit);
			if ($verification) {
				if ($unchanged) {
					$this->commit();
					return array('config_version' => $this->currentVersion($currentByUnit), 'unchanged' => true);
				}
				throw new RuntimeException('A verified unit configuration is immutable.');
			}
			if ($unchanged) {
				$this->commit();
				return array('config_version' => $this->currentVersion($currentByUnit), 'unchanged' => true);
			}

			$version = $this->currentVersion($currentByUnit) + 1;
			$now = $this->now();
			foreach ($expected as $unitId => $values) {
				$old = isset($currentByUnit[$unitId]) ? $currentByUnit[$unitId] : null;
				$factor = $values['factor'];
				$inputScale = $values['input_scale'];
				$expectedBaseScale = $values['base_scale'];
				$status = 'legacy_unverified';
				$stmt = $this->execute(
					'INSERT INTO product_unit_conversions ' .
					'(product_id, unit_id, conversion_factor_to_base, input_scale, base_scale, status, config_version, created_at, updated_at) ' .
					'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ' .
					'ON DUPLICATE KEY UPDATE conversion_factor_to_base = VALUES(conversion_factor_to_base), ' .
					'input_scale = VALUES(input_scale), base_scale = VALUES(base_scale), status = VALUES(status), ' .
					'config_version = VALUES(config_version), updated_at = VALUES(updated_at)',
					'iisiisiss',
					array($productId, $unitId, $factor, $inputScale, $expectedBaseScale, $status, $version, $now, $now)
				);
				$stmt->close();

				$newValues = array(
					'unit_id' => $unitId,
					'conversion_factor_to_base' => $factor,
					'input_scale' => $inputScale,
					'base_scale' => $expectedBaseScale,
					'status' => $status,
					'config_version' => $version
				);
				if (!$old ||
					!$this->rowMatches($old, $values) ||
					(int) $old['config_version'] !== $version ||
					$old['status'] !== $status) {
					$this->audit($productId, $unitId, $actorId, $old, $newValues, $reason, $now);
				}
			}
			$this->commit();
			return array('config_version' => $version, 'unchanged' => false);
		} catch (Throwable $error) {
			$this->rollback();
			throw $error;
		}
	}

	public function verifyConfiguration($productId, $sourceDescription, $evidence, $actorId)
	{
		$productId = (int) $productId;
		$actorId = (int) $actorId;
		$sourceDescription = trim((string) $sourceDescription);
		$evidence = trim((string) $evidence);
		if ($productId < 1 || $actorId < 1 ||
			$sourceDescription === '' || strlen($sourceDescription) > 255 ||
			$evidence === '') {
			throw new InvalidArgumentException('Source, evidence, and administrator are required for verification.');
		}

		$this->begin();
		try {
			$product = $this->first(
				'SELECT unit_id FROM products WHERE id = ? AND deleted_at IS NULL FOR UPDATE',
				'i',
				array($productId)
			);
			if (!$product) {
				throw new RuntimeException('Product was not found.');
			}
			if ($product['unit_id'] === null) {
				throw new RuntimeException('A product with unknown base unit cannot be verified.');
			}

			$existing = $this->first(
				'SELECT id, source_unit_description, mapping_evidence, verified_by ' .
				'FROM product_unit_verifications WHERE product_id = ? LIMIT 1 FOR UPDATE',
				'i',
				array($productId)
			);
			if ($existing) {
				if ((int) $existing['verified_by'] === $actorId &&
					$existing['source_unit_description'] === $sourceDescription &&
					$existing['mapping_evidence'] === $evidence) {
					$this->commit();
					return array('verification_id' => (int) $existing['id'], 'idempotent' => true);
				}
				throw new RuntimeException('Unit verification is one-time and cannot be changed.');
			}

			$rows = $this->all(
				'SELECT unit_id, conversion_factor_to_base, input_scale, base_scale, status, config_version ' .
				'FROM product_unit_conversions WHERE product_id = ? ORDER BY unit_id FOR UPDATE',
				'i',
				array($productId)
			);
			if (!$rows || count($rows) > 4) {
				throw new RuntimeException('Configure between one and four units before verification.');
			}
			$baseUnitId = (int) $product['unit_id'];
			$hasBase = false;
			$version = (int) $rows[0]['config_version'];
			foreach ($rows as $row) {
				$unitId = (int) $row['unit_id'];
				$this->activeUnit($unitId);
				if ($unitId === $baseUnitId) {
					$hasBase = true;
					if (bccomp($row['conversion_factor_to_base'], '1', 9) !== 0) {
						throw new RuntimeException('The base unit factor must equal one.');
					}
				}
				if ($row['status'] !== 'legacy_unverified' ||
					(int) $row['config_version'] !== $version ||
					(int) $row['input_scale'] > 3 ||
					(int) $row['base_scale'] > 3 ||
					bccomp($row['conversion_factor_to_base'], '0', 9) <= 0) {
					throw new RuntimeException('Unit configuration is invalid or inconsistent.');
				}
			}
			if (!$hasBase) {
				throw new RuntimeException('Configured units do not include the product base unit.');
			}

			$verifiedAt = $this->now();
			$status = 'verified';
			$this->execute(
				'INSERT INTO product_unit_verifications ' .
				'(product_id, base_unit_id, source_unit_description, mapping_evidence, verified_by, verified_at, status) ' .
				'VALUES (?, ?, ?, ?, ?, ?, ?)',
				'iississ',
				array($productId, $baseUnitId, $sourceDescription, $evidence, $actorId, $verifiedAt, $status)
			)->close();
			$this->execute(
				'UPDATE product_unit_conversions SET status = ? WHERE product_id = ?',
				'si',
				array($status, $productId)
			)->close();
			foreach ($rows as $row) {
				$old = $row;
				$new = array(
					'unit_id' => (int) $row['unit_id'],
					'conversion_factor_to_base' => $row['conversion_factor_to_base'],
					'input_scale' => (int) $row['input_scale'],
					'base_scale' => (int) $row['base_scale'],
					'status' => $status,
					'config_version' => $version
				);
				$old['unit_id'] = (int) $old['unit_id'];
				$this->audit(
					$productId,
					(int) $row['unit_id'],
					$actorId,
					$old,
					$new,
					'Unit mapping verified: ' . $sourceDescription,
					$verifiedAt
				);
			}
			$this->commit();
			$verification = $this->first(
				'SELECT id FROM product_unit_verifications WHERE product_id = ?',
				'i',
				array($productId)
			);
			return array('verification_id' => (int) $verification['id'], 'idempotent' => false);
		} catch (Throwable $error) {
			$this->rollback();
			throw $error;
		}
	}

	public function convertToBase($productId, $unitId, $quantity)
	{
		$status = $this->getStatus((int) $productId);
		if ($status !== 'verified') {
			throw new RuntimeException('Only verified product units can be converted.');
		}
		$row = $this->first(
			'SELECT conversion_factor_to_base, input_scale, base_scale, config_version ' .
			'FROM product_unit_conversions WHERE product_id = ? AND unit_id = ? AND status = ?',
			'iis',
			array((int) $productId, (int) $unitId, 'verified')
		);
		if (!$row) {
			throw new RuntimeException('The selected unit is not part of the verified product configuration.');
		}
		return array(
			'quantity_base' => self::calculateBaseQuantity(
				(string) $quantity,
				$row['conversion_factor_to_base'],
				(int) $row['input_scale'],
				(int) $row['base_scale']
			),
			'conversion_factor_to_base_snapshot' => $row['conversion_factor_to_base'],
			'input_quantity_scale_snapshot' => (int) $row['input_scale'],
			'base_quantity_scale_snapshot' => (int) $row['base_scale'],
			'unit_config_version_snapshot' => (int) $row['config_version'],
			'unit_status_snapshot' => 'verified'
		);
	}

	public function createDemoExample($actorId)
	{
		$identity = $this->first('SELECT DATABASE() AS database_name, CURRENT_USER() AS account_name');
		if (!$identity ||
			$identity['database_name'] !== 'db_apotek_online_demo' ||
			strpos($identity['account_name'], 'apotek_demo_app@') !== 0) {
			throw new RuntimeException('Demo example creation requires the demo app account.');
		}

		$now = $this->now();
		$carton = $this->first('SELECT id, is_active, deleted_at FROM units WHERE code = ?', 's', array('DEMO-CTN'));
		if (!$carton) {
			$this->execute(
				'INSERT INTO units (name, code, is_active, created_at, updated_at, deleted_at) VALUES (?, ?, 1, ?, ?, NULL)',
				'ssss',
				array('Karton Demo', 'DEMO-CTN', $now, $now)
			)->close();
			$cartonId = (int) $this->connection->insert_id;
		} else {
			if ($carton['deleted_at'] !== null || (int) $carton['is_active'] !== 1) {
				throw new RuntimeException('The demo carton unit exists but is inactive.');
			}
			$cartonId = (int) $carton['id'];
		}

		$tablet = $this->unitByCode('TAB');
		$strip = $this->unitByCode('STRIP');
		$box = $this->unitByCode('BOX');
		$product = $this->first('SELECT id, unit_id, name, description, is_active, deleted_at FROM products WHERE code = ?', 's', array('DEMO-UNIT4-B2A'));
		if (!$product) {
			$this->execute(
				'INSERT INTO products ' .
				'(unit_id, code, barcode, name, description, purchase_price, selling_price, minimum_stock, drug_classification, requires_prescription, is_active, created_at, updated_at, deleted_at) ' .
				'VALUES (?, ?, NULL, ?, ?, 0, 0, 0, ?, 0, 0, ?, ?, NULL)',
				'issssss',
				array((int) $tablet['id'], 'DEMO-UNIT4-B2A', '[DEMO] Hierarki Satuan 4 Tingkat', 'Synthetic B2a sample; not for sale.', 'obat-bebas', $now, $now)
			)->close();
			$productId = (int) $this->connection->insert_id;
		} else {
			if ($product['deleted_at'] !== null ||
				(int) $product['unit_id'] !== (int) $tablet['id'] ||
				strpos($product['name'], '[DEMO]') !== 0 ||
				(int) $product['is_active'] !== 0) {
				throw new RuntimeException('The reserved demo example code belongs to an unexpected product.');
			}
			$productId = (int) $product['id'];
		}

		$this->saveConfiguration(
			$productId,
			array(
				array('unit_id' => (int) $strip['id'], 'factor' => '10', 'input_scale' => 0),
				array('unit_id' => (int) $box['id'], 'factor' => '100', 'input_scale' => 0),
				array('unit_id' => $cartonId, 'factor' => '1000', 'input_scale' => 0)
			),
			0,
			(int) $actorId,
			'B2a demo marker: synthetic four-level unit hierarchy.'
		);
		$this->verifyConfiguration(
			$productId,
			'Synthetic demo-only hierarchy',
			'B2a service-created example; not a saleable product.',
			(int) $actorId
		);

		return $productId;
	}

	private function unitByCode($code)
	{
		$row = $this->first(
			'SELECT id FROM units WHERE code = ? AND is_active = 1 AND deleted_at IS NULL',
			's',
			array($code)
		);
		if (!$row) {
			throw new RuntimeException('A required active demo unit is missing.');
		}
		return $row;
	}

	private function activeUnit($unitId)
	{
		$row = $this->first(
			'SELECT id FROM units WHERE id = ? AND is_active = 1 AND deleted_at IS NULL',
			'i',
			array((int) $unitId)
		);
		if (!$row) {
			throw new InvalidArgumentException('Select an active unit.');
		}
		return $row;
	}

	private function configurationMatches(array $expected, array $current)
	{
		if (count($expected) !== count($current)) {
			return false;
		}
		foreach ($expected as $unitId => $values) {
			if (!isset($current[$unitId]) || !$this->rowMatches($current[$unitId], $values)) {
				return false;
			}
		}
		return true;
	}

	private function rowMatches(array $current, array $expected)
	{
		return bccomp($current['conversion_factor_to_base'], $expected['factor'], 9) === 0 &&
			(int) $current['input_scale'] === (int) $expected['input_scale'] &&
			(int) $current['base_scale'] === (int) $expected['base_scale'];
	}

	private function currentVersion(array $rows)
	{
		$version = 0;
		foreach ($rows as $row) {
			$version = max($version, (int) $row['config_version']);
		}
		return $version;
	}

	private function audit($productId, $unitId, $actorId, $oldValues, array $newValues, $reason, $createdAt)
	{
		$oldJson = $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$newJson = json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($newJson === false || ($oldValues !== null && $oldJson === false)) {
			throw new RuntimeException('Unable to serialize unit audit values.');
		}
		$this->execute(
			'INSERT INTO product_unit_conversion_audits ' .
			'(product_id, unit_id, actor_id, old_values, new_values, reason, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
			'iiissss',
			array((int) $productId, (int) $unitId, (int) $actorId, $oldJson, $newJson, (string) $reason, (string) $createdAt)
		)->close();
	}

	private function now()
	{
		return $this->clock->now()->format('Y-m-d H:i:s');
	}

	private function begin()
	{
		if (!$this->connection->begin_transaction()) {
			throw new RuntimeException('Unable to begin unit configuration transaction.');
		}
	}

	private function commit()
	{
		if (!$this->connection->commit()) {
			throw new RuntimeException('Unable to commit unit configuration transaction.');
		}
	}

	private function rollback()
	{
		$this->connection->rollback();
	}

	private function execute($sql, $types = '', array $values = array())
	{
		$stmt = $this->connection->prepare($sql);
		if (!$stmt) {
			throw new RuntimeException('Unable to prepare product unit operation.');
		}
		if ($types !== '') {
			if (strlen($types) !== count($values)) {
				$stmt->close();
				throw new InvalidArgumentException('Product unit query parameters are inconsistent.');
			}
			$references = array();
			foreach ($values as $index => &$value) {
				$references[$index] = &$value;
			}
			if (!$stmt->bind_param($types, ...$references)) {
				$stmt->close();
				throw new RuntimeException('Unable to bind product unit operation.');
			}
		}
		if (!$stmt->execute()) {
			$stmt->close();
			throw new RuntimeException('Product unit operation failed.');
		}
		return $stmt;
	}

	private function first($sql, $types = '', array $values = array())
	{
		$stmt = $this->execute($sql, $types, $values);
		$result = $stmt->get_result();
		$row = $result ? $result->fetch_assoc() : null;
		$stmt->close();
		return $row;
	}

	private function all($sql, $types = '', array $values = array())
	{
		$stmt = $this->execute($sql, $types, $values);
		$result = $stmt->get_result();
		if (!$result) {
			$stmt->close();
			throw new RuntimeException('Unable to read product unit data.');
		}
		$rows = $result->fetch_all(MYSQLI_ASSOC);
		$result->free();
		$stmt->close();
		return $rows;
	}
}
