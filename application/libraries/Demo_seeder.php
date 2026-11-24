<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Demo_seeder
{
	const TARGET_DATABASE = 'db_apotek_online_demo';

	private $connection;
	private $clock;
	private $today;
	private $units;
	private $unitService;
	private $adminId;
	private $customerIds = array();
	private $products = array();
	private $suppliers = array();
	private $batches = array();
	private $salesByProduct = array();
	private $seedOffset = 0;

	private $scenarios = array(
		array('tag' => 'fast_mover_stable', 'name' => 'Parasetamol 500 mg', 'unit' => 'verified'),
		array('tag' => 'slow_mover', 'name' => 'Kasa steril kecil', 'unit' => 'verified'),
		array('tag' => 'no_sales_3_months', 'name' => 'Salep kulit ringan', 'unit' => 'verified'),
		array('tag' => 'seasonal', 'name' => 'Vitamin C 500 mg', 'unit' => 'verified'),
		array('tag' => 'new_less_3_months', 'name' => 'Oralit sachet', 'unit' => 'verified'),
		array('tag' => 'overstock', 'name' => 'Masker medis', 'unit' => 'verified'),
		array('tag' => 'critical_stock', 'name' => 'Antasida tablet', 'unit' => 'verified'),
		array('tag' => 'near_expiry_red', 'name' => 'Sirup batuk anak', 'unit' => 'verified'),
		array('tag' => 'near_expiry_yellow', 'name' => 'Tablet hisap tenggorokan', 'unit' => 'verified'),
		array('tag' => 'multi_batch_fefo', 'name' => 'Ibuprofen 200 mg', 'unit' => 'verified'),
		array('tag' => 'zero_stock', 'name' => 'Termometer digital', 'unit' => 'verified'),
		array('tag' => 'regular_01', 'name' => 'Cetirizine 10 mg', 'unit' => 'verified'),
		array('tag' => 'regular_02', 'name' => 'Vitamin B kompleks', 'unit' => 'verified'),
		array('tag' => 'regular_03', 'name' => 'Antiseptik 100 ml', 'unit' => 'verified'),
		array('tag' => 'regular_04', 'name' => 'Plester luka', 'unit' => 'verified'),
		array('tag' => 'regular_05', 'name' => 'Masker anak', 'unit' => 'verified'),
		array('tag' => 'regular_06', 'name' => 'Vitamin D 1000 IU', 'unit' => 'verified'),
		array('tag' => 'regular_07', 'name' => 'Obat maag cair', 'unit' => 'verified'),
		array('tag' => 'regular_08', 'name' => 'Kapas steril', 'unit' => 'verified'),
		array('tag' => 'regular_09', 'name' => 'Minyak kayu putih', 'unit' => 'verified'),
		array('tag' => 'regular_10', 'name' => 'Suplemen zat besi', 'unit' => 'verified'),
		array('tag' => 'regular_11', 'name' => 'Cairan saline', 'unit' => 'verified'),
		array('tag' => 'regular_12', 'name' => 'Kasa steril besar', 'unit' => 'verified'),
		array('tag' => 'legacy_one', 'name' => 'Klorfeniramin 4 mg (legacy)', 'unit' => 'legacy'),
		array('tag' => 'legacy_two', 'name' => 'Zinc 20 mg (legacy)', 'unit' => 'legacy')
	);

	public function __construct($connection, Business_clock $clock, DateTimeImmutable $today)
	{
		if (!($connection instanceof mysqli)) {
			throw new InvalidArgumentException('Demo seeder requires a mysqli connection.');
		}
		$this->connection = $connection;
		$this->clock = $clock;
		$this->today = $today->setTimezone(new DateTimeZone('Asia/Jakarta'));
		$this->unitService = new Product_unit_service($connection, $clock);
	}

	public static function targetAllowed($database, $marker)
	{
		return is_string($database) &&
			substr($database, -5) === '_demo' &&
			$database === self::TARGET_DATABASE &&
			$marker === 'demo';
	}

	public static function deterministicSupplierPlan(array $product, array $supplierIds)
	{
		if (!$supplierIds || !isset($product['index'], $product['tag'])) {
			throw new InvalidArgumentException('A demo product and supplier list are required.');
		}

		$profiles = array(
			'critical_stock' => array(
				'moq_mode' => 'set',
				'moq_purchase_units' => '2.000',
				'order_multiple_purchase_units' => '2.000',
				'manual_lead_time_days' => 15
			),
			'fast_mover_stable' => array(
				'moq_mode' => 'none',
				'moq_purchase_units' => null,
				'order_multiple_purchase_units' => null,
				'manual_lead_time_days' => 14
			),
			'regular_01' => array(
				'moq_mode' => 'none',
				'moq_purchase_units' => null,
				'order_multiple_purchase_units' => '3.000',
				'manual_lead_time_days' => 14
			),
			'regular_04' => array('manual_lead_time_days' => null),
			'regular_05' => array('base_per_purchase_unit' => null),
			'regular_06' => array(
				'moq_mode' => 'none',
				'moq_purchase_units' => null,
				'order_multiple_purchase_units' => null
			),
			'legacy_one' => array('base_per_purchase_unit' => null),
			'legacy_two' => array('base_per_purchase_unit' => null)
		);
		$base = array(
			'purchase_unit' => 'pak isi 10 tablet',
			'base_per_purchase_unit' => '10.000000000',
			'moq_mode' => 'none',
			'moq_purchase_units' => null,
			'order_multiple_purchase_units' => null,
			'manual_lead_time_days' => 14
		);
		$profile = isset($profiles[$product['tag']])
			? array_merge($base, $profiles[$product['tag']])
			: $base;
		$profile['product_id'] = isset($product['id']) ? (int) $product['id'] : null;
		$profile['supplier_id'] = (int) $supplierIds[((int) $product['index']) % count($supplierIds)];
		$profile['is_preferred'] = true;
		$rows = array($profile);

		if ($product['tag'] === 'critical_stock') {
			$alternateIndex = (((int) $product['index']) + 1) % count($supplierIds);
			$alternate = $profile;
			$alternate['supplier_id'] = (int) $supplierIds[$alternateIndex];
			$alternate['is_preferred'] = false;
			$alternate['base_per_purchase_unit'] = null;
			$alternate['moq_mode'] = 'none';
			$alternate['moq_purchase_units'] = null;
			$alternate['order_multiple_purchase_units'] = null;
			$alternate['manual_lead_time_days'] = 28;
			$rows[] = $alternate;
		}

		return $rows;
	}

	public function seed($seed, $accountsPath)
	{
		$this->assertTarget();
		$this->assertEmptyTarget();
		$this->seedOffset = abs((int) $seed) % 14;
		$this->seedStoreSettingsPlaceholders();

		$accounts = $this->createAccounts();
		$this->adminId = $accounts['administrator_id'];
		$this->customerIds = $accounts['customer_ids'];
		$this->createAddresses($accounts['customer_ids']);
		$this->suppliers = $this->createSuppliers();
		$this->units = $this->createUnits();
		$this->products = $this->createProducts();
		$productSupplierPairs = $this->createProductSuppliers();

		$orders = $this->makeOrderPlan();
		$this->salesByProduct = array_fill(0, count($this->products), 0);
		foreach ($orders as $order) {
			if (!$order['paid']) {
				continue;
			}
			foreach ($order['items'] as $item) {
				$this->salesByProduct[$item['product_index']] += $item['quantity'];
			}
		}

		$this->createReceivedPurchaseOrdersAndBatches();
		$this->createOpenPurchaseOrders();
		$this->createOrders($orders);
		$this->writeAccountsFile($accounts['credentials'], $accountsPath);

		return array(
			'products' => count($this->products),
			'suppliers' => count($this->suppliers),
			'orders' => count($orders),
			'order_items' => array_sum(array_map(function ($order) {
				return count($order['items']);
			}, $orders)),
			'paid_orders' => count(array_filter($orders, function ($order) {
				return $order['paid'];
			})),
			'purchase_orders' => 78,
			'product_supplier_pairs' => $productSupplierPairs,
			'accounts' => count($accounts['credentials'])
		);
	}

	private function seedStoreSettingsPlaceholders()
	{
		$placeholders = array(
			'store_name' => 'Apotek Demo',
			'store_address' => 'Alamat demo tidak diisi',
			'store_phone' => '-',
			'store_email' => 'demo.invalid',
			'store_website' => '',
			'store_logo' => '',
			'store_tagline' => 'Lingkungan demo sintetis',
			'store_hours' => 'Jam operasional demo',
			'store_npwp' => '',
			'pharmacist_name' => '',
			'pharmacist_license' => '',
			'pharmacy_license' => '',
			'receipt_footer' => 'Dokumen sintetis demo'
		);
		foreach ($placeholders as $key => $value) {
			$this->execute(
				'UPDATE store_settings SET setting_value = ?, updated_at = ? WHERE setting_key = ?',
				'sss',
				array($value, $this->now(), $key)
			);
		}
	}

	private function assertTarget()
	{
		$result = $this->connection->query('SELECT DATABASE(), CURRENT_USER()');
		$row = $result ? $result->fetch_row() : null;
		if ($result) {
			$result->free();
		}
		if (!$row || strpos($row[1], 'apotek_demo_app@') !== 0 ||
			!self::targetAllowed($row[0], $this->markerValue())) {
			throw new RuntimeException('Seeder guard rejected the database or environment marker.');
		}
	}

	private function markerValue()
	{
		$result = $this->connection->query(
			'SELECT environment FROM environment_marker WHERE id = 1 LIMIT 1'
		);
		if (!$result) {
			throw new RuntimeException('Seeder requires a valid demo environment marker.');
		}
		$row = $result->fetch_row();
		$result->free();
		return $row ? $row[0] : null;
	}

	private function assertEmptyTarget()
	{
		foreach (array('users', 'user_roles', 'user_addresses', 'suppliers', 'products', 'orders', 'purchase_orders', 'stock_batches') as $table) {
			$count = $this->scalar('SELECT COUNT(*) FROM `' . $table . '`');
			if ((int) $count !== 0) {
				throw new RuntimeException('Seeder refuses to append to an already populated demo database.');
			}
		}
	}

	private function createAccounts()
	{
		$roles = $this->rows(
			"SELECT id, slug FROM roles WHERE deleted_at IS NULL ORDER BY id"
		);
		$credentials = array();
		$customerIds = array();
		$administratorId = null;
		foreach ($roles as $role) {
			$account = $this->insertAccount(
				'b3-' . $role['slug'] . '@demo.invalid',
				'B3 Demo ' . $role['slug'],
				'0000000000',
				(int) $role['id'],
				$role['slug'],
				'role-' . $role['slug']
			);
			$credentials[] = $account['credential'];
			if ($role['slug'] === 'administrator') {
				$administratorId = $account['id'];
			}
			if ($role['slug'] === 'pelanggan') {
				$customerIds[] = $account['id'];
			}
		}
		$customerRole = null;
		foreach ($roles as $role) {
			if ($role['slug'] === 'pelanggan') {
				$customerRole = (int) $role['id'];
				break;
			}
		}
		if (!$administratorId || !$customerRole) {
			throw new RuntimeException('Demo reference roles do not include administrator and customer.');
		}
		for ($index = 2; $index <= 3; $index++) {
			$account = $this->insertAccount(
				'b3-customer-0' . $index . '@demo.invalid',
				'B3 Demo Customer 0' . $index,
				'000000000' . $index,
				$customerRole,
				'pelanggan',
				'customer-' . str_pad((string) $index, 2, '0', STR_PAD_LEFT)
			);
			$credentials[] = $account['credential'];
			$customerIds[] = $account['id'];
		}

		return array(
			'credentials' => $credentials,
			'customer_ids' => $customerIds,
			'administrator_id' => $administratorId
		);
	}

	private function insertAccount($email, $name, $phone, $roleId, $roleSlug, $accountLabel)
	{
		$password = bin2hex(random_bytes(24));
		$passwordHash = password_hash($password, PASSWORD_DEFAULT);
		if (!is_string($passwordHash)) {
			throw new RuntimeException('Unable to create a demo account credential.');
		}
		$now = $this->now();
		$this->execute(
			'INSERT INTO users (email, password_hash, full_name, phone, status, created_at, updated_at) ' .
			'VALUES (?, ?, ?, ?, ?, ?, ?)',
			'sssssss',
			array($email, $passwordHash, $name, $phone, 'active', $now, $now)
		);
		$userId = $this->connection->insert_id;
		$createdAt = $now;
		$this->execute(
			'INSERT INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, ?)',
			'iis',
			array($userId, $roleId, $createdAt)
		);
		return array(
			'id' => (int) $userId,
			'credential' => array(
				'role' => $roleSlug,
				'account' => $accountLabel,
				'login' => $email,
				'password' => $password
			)
		);
	}

	private function createAddresses(array $customerIds)
	{
		$now = $this->now();
		foreach ($customerIds as $index => $customerId) {
			$label = 'Alamat demo';
			$name = 'Pelanggan Demo ' . ($index + 1);
			$phone = '000000000' . ($index + 1);
			$line = 'Alamat sintetis B3 nomor ' . ($index + 1);
			$district = 'Kecamatan Demo';
			$city = 'Kota Demo';
			$province = 'Provinsi Demo';
			$postalCode = '00000';
			$isPrimary = 1;
			$this->execute(
				'INSERT INTO user_addresses ' .
				'(user_id, label, recipient_name, phone, address_line, district, city, province, postal_code, is_primary, created_at, updated_at) ' .
				'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
				'issssssssiss',
				array($customerId, $label, $name, $phone, $line, $district, $city, $province, $postalCode, $isPrimary, $now, $now)
			);
		}
	}

	private function createSuppliers()
	{
		$suppliers = array();
		$now = $this->now();
		for ($index = 1; $index <= 9; $index++) {
			$code = sprintf('B3-SUP-%02d', $index);
			$name = sprintf('[B3] Supplier Demo %02d', $index);
			$address = 'Alamat pemasok sintetis B3 ' . $index;
			$phone = '00000000' . $index;
			$contact = 'Kontak Demo ' . $index;
			$active = 1;
			$this->execute(
				'INSERT INTO suppliers (code, name, address, phone, email, contact_person, is_active, created_at, updated_at) ' .
				'VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?)',
				'sssssiss',
				array($code, $name, $address, $phone, $contact, $active, $now, $now)
			);
			$suppliers[] = (int) $this->connection->insert_id;
		}
		return $suppliers;
	}

	private function createUnits()
	{
		$base = $this->first(
			"SELECT id, code, name FROM units WHERE code = ? AND is_active = 1 AND deleted_at IS NULL",
			's',
			array('TAB')
		);
		if (!$base) {
			throw new RuntimeException('The approved base unit is missing from demo reference data.');
		}
		$now = $this->now();
		$definitions = array(
			array('B3-PACK10', '[B3] Kemasan 10'),
			array('B3-PACK100', '[B3] Kemasan 100'),
			array('B3-PACK1000', '[B3] Kemasan 1000')
		);
		$ids = array();
		foreach ($definitions as $definition) {
			$this->execute(
				'INSERT INTO units (name, code, is_active, created_at, updated_at, deleted_at) VALUES (?, ?, 1, ?, ?, NULL)',
				'ssss',
				array($definition[1], $definition[0], $now, $now)
			);
			$ids[] = (int) $this->connection->insert_id;
		}
		return array(
			'base_id' => (int) $base['id'],
			'base_code' => $base['code'],
			'base_name' => $base['name'],
			'alternates' => $ids
		);
	}

	private function createProducts()
	{
		$categoryIds = $this->ids('categories');
		$manufacturerIds = $this->ids('manufacturers');
		if (!$categoryIds || !$manufacturerIds) {
			throw new RuntimeException('Demo reference categories or manufacturers are empty.');
		}
		$now = $this->now();
		$createdStandard = $this->at('2025-10-01', '09:00:00');
		$createdNew = $this->at('2026-09-20', '09:00:00');
		$fourLevel = array(0, 3, 5, 9, 11, 15);
		$products = array();
		foreach ($this->scenarios as $index => $scenario) {
			$code = sprintf('B3-PRD-%03d', $index + 1);
			$description = '[B3:' . $scenario['tag'] . ']' .
				$this->b5ScenarioMarker($scenario['tag']) .
				' Synthetic demo fixture; not operational inventory.';
			$categoryId = $categoryIds[$index % count($categoryIds)];
			$manufacturerId = $manufacturerIds[$index % count($manufacturerIds)];
			$purchasePrice = 1000 + ($index * 250);
			$sellingPrice = $purchasePrice * 2;
			$minimumStock = $scenario['tag'] === 'critical_stock' ? 10 : 5;
			$createdAt = $scenario['tag'] === 'new_less_3_months' ? $createdNew : $createdStandard;
			$this->execute(
				'INSERT INTO products ' .
				'(category_id, unit_id, manufacturer_id, code, barcode, name, generic_name, dosage_form, strength, registration_number, description, purchase_price, selling_price, minimum_stock, drug_classification, requires_prescription, is_active, created_at, updated_at, deleted_at) ' .
				'VALUES (?, ?, ?, ?, NULL, ?, NULL, ?, ?, NULL, ?, ?, ?, ?, ?, 0, 1, ?, ?, NULL)',
				'iiisssssdddsss',
				array(
					$categoryId,
					$this->units['base_id'],
					$manufacturerId,
					$code,
					$scenario['name'],
					'tablet',
					'500 mg',
					$description,
					$purchasePrice,
					$sellingPrice,
					$minimumStock,
					'obat-bebas',
					$createdAt,
					$createdAt
				)
			);
			$productId = (int) $this->connection->insert_id;
			$isLegacy = $scenario['unit'] === 'legacy';
			$product = array(
				'id' => $productId,
				'index' => $index,
				'code' => $code,
				'name' => $scenario['name'],
				'tag' => $scenario['tag'],
				'unit_id' => $this->units['base_id'],
				'unit_code' => $this->units['base_code'],
				'unit_name' => $this->units['base_name'],
				'purchase_price' => $purchasePrice,
				'selling_price' => $sellingPrice,
				'minimum_stock' => $minimumStock,
				'legacy' => $isLegacy
			);
			if (!$isLegacy) {
				$alternates = array();
				if (in_array($index, $fourLevel, true)) {
					$alternateFactors = array(10, 100, 1000);
				} else {
					$alternateFactors = array(10);
				}
				foreach ($alternateFactors as $altIndex => $factor) {
					$alternates[] = array(
						'unit_id' => $this->units['alternates'][$altIndex],
						'factor' => (string) $factor,
						'input_scale' => 0
					);
				}
				$this->unitService->saveConfiguration(
					$productId,
					$alternates,
					0,
					(int) $this->adminId,
					'B3 seeded deterministic unit configuration.'
				);
				$this->unitService->verifyConfiguration(
					$productId,
					'Synthetic B3 reference mapping',
					'B3 demo-only unit factors; not operational product data.',
					(int) $this->adminId
				);
				$product['unit_status'] = 'verified';
			} else {
				$product['unit_status'] = 'legacy_unverified';
			}
			$products[] = $product;
		}
		return $products;
	}

	private function b5ScenarioMarker($tag)
	{
		$tags = array(
			'fast_mover_stable' => 'normal',
			'seasonal' => 'seasonal_demand',
			'critical_stock' => 'multi_supplier_moq_multiple',
			'regular_01' => 'ordered_po_warning',
			'regular_04' => 'manual_lead_time_missing',
			'regular_05' => 'factor_missing',
			'regular_06' => 'moq_none_explicit',
			'regular_07' => 'normal_multi_batch',
			'no_sales_3_months' => 'demand_zero_review',
			'regular_02' => 'stock_exceeds_target',
			'near_expiry_red' => 'expiry_red_warning',
			'near_expiry_yellow' => 'expiry_yellow_warning',
			'legacy_one' => 'unit_unverified',
			'regular_03' => 'inconsistent_payment'
		);
		return isset($tags[$tag]) ? ' [B5:' . $tags[$tag] . ']' : '';
	}

	private function createProductSuppliers()
	{
		$now = $this->now();
		$count = 0;
		foreach ($this->products as $product) {
			$plan = self::deterministicSupplierPlan($product, $this->suppliers);
			foreach ($plan as $row) {
				$this->execute(
					'INSERT INTO product_suppliers ' .
					'(product_id, supplier_id, is_preferred, purchase_unit, base_per_purchase_unit, ' .
					'moq_mode, moq_purchase_units, order_multiple_purchase_units, manual_lead_time_days, created_at, updated_at) ' .
					'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
					'iiisssssiss',
					array(
						$row['product_id'],
						$row['supplier_id'],
						$row['is_preferred'] ? 1 : 0,
						$row['purchase_unit'],
						$row['base_per_purchase_unit'],
						$row['moq_mode'],
						$row['moq_purchase_units'],
						$row['order_multiple_purchase_units'],
						$row['manual_lead_time_days'],
						$now,
						$now
					)
				);
				$count++;
			}
		}
		return $count;
	}

	private function makeOrderPlan()
	{
		$orders = array();
		$sequence = 0;
		for ($month = 0; $month < 13; $month++) {
			for ($index = 0; $index < 40; $index++) {
				$orders[] = $this->makeOrder($month, $index, $sequence++, true, 'paid');
			}
			$unpaidCount = 6 + ($month === 12 ? 2 : 0);
			for ($index = 0; $index < $unpaidCount; $index++) {
				$status = $index % 2 === 0 ? 'cancelled' : 'pending';
				$orders[] = $this->makeOrder($month, $index + 40, $sequence++, false, $status);
			}
		}
		usort($orders, function ($left, $right) {
			$comparison = strcmp($left['created_at'], $right['created_at']);
			return $comparison === 0 ? $left['sequence'] - $right['sequence'] : $comparison;
		});
		return $orders;
	}

	private function makeOrder($month, $index, $sequence, $paid, $status)
	{
		$monthStart = $this->today->modify('first day of this month')->modify('-' . (12 - $month) . ' months');
		$monthLastDay = (int) $monthStart->modify('last day of this month')->format('j');
		$limit = $month === 12 ? (int) $this->today->format('j') : $monthLastDay;
		$day = 1 + (($index * 7 + $month * 3) % $limit);
		$date = $monthStart->setDate(
			(int) $monthStart->format('Y'),
			(int) $monthStart->format('m'),
			$day
		)->setTime(8, 0, 0);
		$items = array();
		$used = array();
		for ($slot = 0; $slot < 3; $slot++) {
			$productIndex = $paid
				? $this->selectPaidProduct($month, $index, $slot, $sequence)
				: 11 + (($sequence + $slot + $this->seedOffset) % 14);
			// Isolate the intentional missing-paid_at order to one product.
			if (isset($used[$productIndex]) && $sequence !== 0) {
				$productIndex = $this->nextUnusedGeneric($used, $sequence + $slot);
			}
			$used[$productIndex] = true;
			$quantity = $this->quantityFor($productIndex, $month, $index, $slot, $paid);
			$items[] = array('product_index' => $productIndex, 'quantity' => $quantity);
		}
		$channel = $sequence % 2 === 0 ? 'pos' : 'online';
		return array(
			'sequence' => $sequence,
			'month' => $month,
			'index' => $index,
			'paid' => $paid,
			'status' => $status,
			'channel' => $channel,
			'created_at' => $date->format('Y-m-d H:i:s'),
			'items' => $items
		);
	}

	private function selectPaidProduct($month, $index, $slot, $sequence)
	{
		if ($sequence === 0) {
			return 13;
		}
		if ($month === 0 && $index === 1 && $slot === 0) {
			return 9;
		}
		if ($month === 12 && $index === 39 && $slot === 2) {
			return 10;
		}
		if ($slot === 0 && $index % 3 === 0) {
			return 0;
		}
		if ($slot === 1 && $month <= 8 && $index % 7 === 0) {
			return 2;
		}
		if ($slot === 1 && $month >= 11 && $index % 5 === 0) {
			return 4;
		}
		if ($slot === 1) {
			$step = in_array($month, array(0, 1, 2, 11, 12), true) ? 2 : 9;
			if ($index % $step === 1) {
				return 3;
			}
		}
		if ($slot === 2 && $index % 13 === 0) {
			return 1;
		}
		if ($slot === 2 && $index % 11 === 0) {
			return 6;
		}
		if ($slot === 2 && $index % 17 === 2) {
			return 7;
		}
		if ($slot === 2 && $index % 19 === 3) {
			return 8;
		}
		return 11 + (($sequence + $slot + $month + $this->seedOffset) % 14);
	}

	private function nextUnusedGeneric(array $used, $start)
	{
		for ($offset = 0; $offset < 14; $offset++) {
			$index = 11 + (($start + $offset) % 14);
			if (!isset($used[$index])) {
				return $index;
			}
		}
		throw new RuntimeException('Unable to select unique items for a demo order.');
	}

	private function quantityFor($productIndex, $month, $index, $slot, $paid)
	{
		if (!$paid) {
			return 1 + (($index + $slot) % 4);
		}
		if ($productIndex === 9 && $month === 0 && $index === 1 && $slot === 0) {
			return 35;
		}
		if ($productIndex === 10 && $month === 12 && $index === 39 && $slot === 2) {
			return 60;
		}
		if ($productIndex === 0) {
			return 4 + (($index + $month) % 9);
		}
		if ($productIndex === 1) {
			return 1 + (($index + $month) % 2);
		}
		if ($productIndex === 3) {
			return 2 + (($index * 3 + $month) % 14);
		}
		if ($productIndex === 4) {
			return 1 + (($index + $month) % 4);
		}
		return 1 + (($index * 7 + $slot * 3 + $month) % 8);
	}

	private function createReceivedPurchaseOrdersAndBatches()
	{
		$receiptDate = $this->today->modify('first day of this month')->modify('-13 months')->modify('+24 days');
		$receivedAt = $receiptDate->setTime(9, 0, 0)->format('Y-m-d H:i:s');
		foreach ($this->products as $product) {
			$productIndex = $product['index'];
			$totalSales = $this->salesByProduct[$productIndex];
			$finalStock = $this->targetStock($product['tag']);
			$totalInitial = max(3, $totalSales + $finalStock);
			$quantities = $this->batchQuantities($product['tag'], $totalInitial);
			$supplierId = $this->suppliers[$productIndex % count($this->suppliers)];
			$balance = 0;
			$expiries = $this->expiries($product['tag']);
			$productReceiptAt = $product['tag'] === 'new_less_3_months'
				? $this->at('2026-09-25', '09:00:00')
				: $receivedAt;
			for ($batchIndex = 0; $batchIndex < 3; $batchIndex++) {
				$quantity = $quantities[$batchIndex];
				$number = 'B3-' . $product['code'] . '-B' . ($batchIndex + 1);
				$this->createReceivedPurchase($product, $supplierId, $batchIndex, $number, $quantity, $productReceiptAt, $expiries[$batchIndex]);
				$balance += $quantity;
				$this->execute(
					'INSERT INTO stock_batches ' .
					'(product_id, batch_number, received_at, expiration_date, initial_quantity, remaining_quantity, reserved_quantity, purchase_price, selling_price, status, created_at, updated_at) ' .
					'VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)',
					'isssddddsss',
					array(
						$product['id'],
						$number,
						$productReceiptAt,
						$expiries[$batchIndex],
						$quantity,
						$quantity,
						$product['purchase_price'],
						$product['selling_price'],
						'active',
						$productReceiptAt,
						$productReceiptAt
					)
				);
				$batchId = (int) $this->connection->insert_id;
				$this->batches[$productIndex][] = array(
					'id' => $batchId,
					'expiration_date' => $expiries[$batchIndex],
					'remaining' => $quantity,
					'purchase_price' => $product['purchase_price']
				);
				$this->execute(
					'INSERT INTO stock_movements (product_id, batch_id, movement_type, reference_type, reference_id, quantity, balance_after, notes, created_by, created_at) ' .
					'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
					'iissiddsis',
					array(
						$product['id'],
						$batchId,
						'purchase_receive',
						'purchase_order',
						$this->lastPurchaseOrderId,
						$quantity,
						$balance,
						'[B3] Receipt from deterministic demo purchase order.',
						$this->adminId,
						$receivedAt
					)
				);
			}
		}
	}

	private $lastPurchaseOrderId;

	private function createOpenPurchaseOrders()
	{
		$createdAt = $this->at('2026-11-20', '09:00:00');
		for ($index = 0; $index < 3; $index++) {
			$product = $this->products[11 + $index];
			$supplierId = $this->suppliers[($product['index']) % count($this->suppliers)];
			$purchaseNumber = sprintf('B3-PO-OPEN-%02d', $index + 1);
			$quantity = 50 + $index * 10;
			$total = $product['purchase_price'] * $quantity;
			$purchaseDate = substr($createdAt, 0, 10);
			$this->execute(
				'INSERT INTO purchase_orders (purchase_number, supplier_id, status, purchase_date, subtotal, discount, total, notes, created_by, received_by, received_at, created_at, updated_at) ' .
				'VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, NULL, NULL, ?, ?)',
				'sissddsiss',
				array(
					$purchaseNumber,
					$supplierId,
					'ordered',
					$purchaseDate,
					$total,
					$total,
					'[B3] Open ordered purchase; intentionally not received.',
					$this->adminId,
					$createdAt,
					$createdAt
				)
			);
			$purchaseOrderId = (int) $this->connection->insert_id;
			$snapshot = $this->unitSnapshot($product, $quantity);
			$this->execute(
				'INSERT INTO purchase_order_items ' .
				'(purchase_order_id, product_id, quantity, purchase_price, discount, total, batch_number, expiration_date, received_quantity, created_at, updated_at, purchase_unit_id_snapshot, purchase_unit_code_snapshot, purchase_unit_name_snapshot, factor_to_base_snapshot, received_quantity_base, input_quantity_scale_snapshot, base_quantity_scale_snapshot, unit_config_version_snapshot, unit_status_snapshot) ' .
				'VALUES (?, ?, ?, ?, 0, ?, NULL, NULL, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
				'iidddssissddiiis',
				array(
					$purchaseOrderId,
					$product['id'],
					$quantity,
					$product['purchase_price'],
					$total,
					$createdAt,
					$createdAt,
					$snapshot['unit_id_snapshot'],
					$snapshot['unit_code_snapshot'],
					$snapshot['unit_name_snapshot'],
					$snapshot['conversion_factor_to_base_snapshot'],
					$snapshot['quantity_base'],
					$snapshot['input_scale'],
					$snapshot['base_scale'],
					$snapshot['config_version'],
					$snapshot['status']
				)
			);
		}
	}

	private function createOrders(array $orders)
	{
		$cash = $this->referenceId('payment_methods', 'code', 'cash');
		$bank = $this->referenceId('payment_methods', 'code', 'bank_transfer');
		$pickup = $this->referenceId('shipping_methods', 'code', 'pickup');
		$regular = $this->referenceId('shipping_methods', 'code', 'regular');
		foreach ($orders as $order) {
			$sequence = $order['sequence'];
			$online = $order['channel'] === 'online';
			$customerId = $online ? $this->customerIds[$sequence % count($this->customerIds)] : null;
			$customerName = $online ? 'Pelanggan Demo ' . (($sequence % count($this->customerIds)) + 1) : 'Pelanggan Walk-in B3';
			$phone = $online ? '0000000001' : '-';
			$address = $online
				? json_encode(array('label' => 'B3 Demo', 'address_line' => 'Alamat sintetis B3', 'city' => 'Kota Demo', 'province' => 'Provinsi Demo'))
				: json_encode(array('channel' => 'POS', 'marker' => 'B3'));
			$paymentMethodId = $online ? $bank : $cash;
			$shippingMethodId = $online ? $regular : $pickup;
			$shippingCost = $online ? 15000 : 0;
			$lineTotals = array();
			foreach ($order['items'] as $item) {
				$product = $this->products[$item['product_index']];
				$lineTotals[] = $product['selling_price'] * $item['quantity'];
			}
			$subtotal = array_sum($lineTotals);
			$discount = $order['paid'] && $sequence % 11 === 0
				? round($subtotal * 0.05, 2)
				: 0;
			$total = $subtotal - $discount + $shippingCost;
			$orderNumber = sprintf('B3-%s-%04d', strtoupper($order['channel']), $sequence + 1);
			$status = $order['paid']
				? ($online ? 'delivered' : 'processing')
				: $order['status'];
			$token = hash('sha256', 'B3:' . $sequence);
			$updatedAt = $order['created_at'];
			$requiresPrescription = 0;
			$this->execute(
				'INSERT INTO orders ' .
				'(order_number, user_id, customer_name, customer_phone, address_snapshot, payment_method_id, shipping_method_id, status, subtotal, discount, shipping_cost, total, requires_prescription, prescription_status, checkout_token, created_at, updated_at) ' .
				'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?)',
				'sisssiisddddisss',
				array(
					$orderNumber,
					$customerId,
					$customerName,
					$phone,
					$address,
					$paymentMethodId,
					$shippingMethodId,
					$status,
					$subtotal,
					$discount,
					$shippingCost,
					$total,
					$requiresPrescription,
					$token,
					$order['created_at'],
					$updatedAt
				)
			);
			$orderId = (int) $this->connection->insert_id;
			foreach ($order['items'] as $index => $item) {
				$product = $this->products[$item['product_index']];
				$lineTotal = $product['selling_price'] * $item['quantity'];
				$snapshot = $this->unitSnapshot($product, $item['quantity']);
				$this->execute(
					'INSERT INTO order_items ' .
					'(order_id, product_id, quantity, unit_price_snapshot, discount, total, created_at, unit_id_snapshot, unit_code_snapshot, unit_name_snapshot, conversion_factor_to_base_snapshot, quantity_base, input_quantity_scale_snapshot, base_quantity_scale_snapshot, unit_config_version_snapshot, unit_status_snapshot) ' .
					'VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
					'iidddsissddiiis',
					array(
						$orderId,
						$product['id'],
						$item['quantity'],
						$product['selling_price'],
						$lineTotal,
						$order['created_at'],
						$snapshot['unit_id_snapshot'],
						$snapshot['unit_code_snapshot'],
						$snapshot['unit_name_snapshot'],
						$snapshot['conversion_factor_to_base_snapshot'],
						$snapshot['quantity_base'],
						$snapshot['input_scale'],
						$snapshot['base_scale'],
						$snapshot['config_version'],
						$snapshot['status']
					)
				);
				$orderItemId = (int) $this->connection->insert_id;
				if ($order['paid']) {
					$this->allocateItem($orderItemId, $orderId, $product, $item['quantity'], $order['created_at']);
				}
			}
			$paymentStatus = $order['paid'] ? 'paid' : $order['status'];
			$paidAt = $order['paid'] && $sequence !== 0
				? $this->plusMinutes($order['created_at'], 5)
				: null;
			$this->execute(
				'INSERT INTO payments (order_id, payment_method_id, reference_number, amount, status, gateway_name, paid_at, created_at, updated_at) ' .
				'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
				'iisdsssss',
				array($orderId, $paymentMethodId, $orderNumber, $total, $paymentStatus, $online ? 'B3 online' : 'B3 POS', $paidAt, $order['created_at'], $updatedAt)
			);
			$note = $sequence === 0
				? '[B3:inconsistent-payment] payment paid without paid_at; inspect data.'
				: '[B3:' . $order['channel'] . '] seeded ' . $status . ' order.';
			$this->execute(
				'INSERT INTO order_status_histories (order_id, status, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?)',
				'issis',
				array($orderId, $status, $note, $customerId ? $customerId : $this->adminId, $order['created_at'])
			);
			if ($online) {
				$this->createShipment($orderId, $shippingMethodId, $customerName, $phone, $address, $shippingCost, $order, $updatedAt);
			}
		}
	}

	private function createShipment($orderId, $shippingMethodId, $name, $phone, $address, $shippingCost, array $order, $updatedAt)
	{
		$isDelivered = $order['paid'];
		$status = $isDelivered ? 'delivered' : ($order['status'] === 'cancelled' ? 'cancelled' : 'pending');
		$shippedAt = $isDelivered ? $this->plusMinutes($order['created_at'], 60) : null;
		$deliveredAt = $isDelivered ? $this->plusMinutes($order['created_at'], 180) : null;
		$tracking = 'B3-TRK-' . str_pad((string) ($order['sequence'] + 1), 5, '0', STR_PAD_LEFT);
		$this->execute(
			'INSERT INTO shipments (order_id, shipping_method_id, recipient_name, recipient_phone, address_snapshot, shipping_cost, tracking_number, status, shipped_at, delivered_at, created_at, updated_at) ' .
			'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			'iisssdssssss',
			array($orderId, $shippingMethodId, $name, $phone, $address, $shippingCost, $tracking, $status, $shippedAt, $deliveredAt, $order['created_at'], $updatedAt)
		);
	}

	private function allocateItem($orderItemId, $orderId, array $product, $quantity, $createdAt)
	{
		$remaining = (float) $quantity;
		usort($this->batches[$product['index']], function ($left, $right) {
			$comparison = strcmp($left['expiration_date'], $right['expiration_date']);
			return $comparison === 0 ? $left['id'] - $right['id'] : $comparison;
		});
		foreach ($this->batches[$product['index']] as &$batch) {
			if ($remaining <= 0) {
				break;
			}
			if ($batch['expiration_date'] < substr($createdAt, 0, 10) || $batch['remaining'] <= 0) {
				continue;
			}
			$taken = min($remaining, $batch['remaining']);
			$batch['remaining'] -= $taken;
			$remaining -= $taken;
			$status = $batch['remaining'] <= 0 ? 'depleted' : 'active';
			$this->execute(
				'UPDATE stock_batches SET remaining_quantity = ?, status = ?, updated_at = ? WHERE id = ?',
				'dssi',
				array($batch['remaining'], $status, $createdAt, $batch['id'])
			);
			$this->execute(
				'INSERT INTO order_item_batches (order_item_id, batch_id, quantity, purchase_price_snapshot, created_at) VALUES (?, ?, ?, ?, ?)',
				'iidds',
				array($orderItemId, $batch['id'], $taken, $batch['purchase_price'], $createdAt)
			);
			$balance = 0;
			foreach ($this->batches[$product['index']] as $productBatch) {
				$balance += $productBatch['remaining'];
			}
			$notes = '[B3] FEFO allocation.';
			$this->execute(
				'INSERT INTO stock_movements (product_id, batch_id, movement_type, reference_type, reference_id, quantity, balance_after, notes, created_by, created_at) ' .
				'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
				'iissiddsis',
				array($product['id'], $batch['id'], 'sale', 'order', $orderId, -$taken, $balance, $notes, $this->adminId, $createdAt)
			);
		}
		unset($batch);
		if ($remaining > 0) {
			throw new RuntimeException('Insufficient demo inventory for a planned paid order.');
		}
	}

	private function unitSnapshot(array $product, $quantity)
	{
		if ($product['legacy']) {
			return array(
				'unit_id_snapshot' => null,
				'unit_code_snapshot' => null,
				'unit_name_snapshot' => null,
				'conversion_factor_to_base_snapshot' => null,
				'quantity_base' => null,
				'input_scale' => null,
				'base_scale' => null,
				'config_version' => null,
				'status' => 'legacy_unverified'
			);
		}
		$result = $this->unitService->convertToBase(
			$product['id'],
			$product['unit_id'],
			(string) $quantity
		);
		return array(
			'unit_id_snapshot' => $product['unit_id'],
			'unit_code_snapshot' => $product['unit_code'],
			'unit_name_snapshot' => $product['unit_name'],
			'conversion_factor_to_base_snapshot' => $result['conversion_factor_to_base_snapshot'],
			'quantity_base' => $result['quantity_base'],
			'input_scale' => $result['input_quantity_scale_snapshot'],
			'base_scale' => $result['base_quantity_scale_snapshot'],
			'config_version' => $result['unit_config_version_snapshot'],
			'status' => $result['unit_status_snapshot']
		);
	}

	private function createReceivedPurchase(array $product, $supplierId, $batchIndex, $batchNumber, $quantity, $receivedAt, $expiry)
	{
		$purchaseNumber = 'B3-PO-' . $product['code'] . '-' . ($batchIndex + 1);
		$subtotal = $product['purchase_price'] * $quantity;
		$purchaseDate = substr($receivedAt, 0, 10);
		$this->execute(
			'INSERT INTO purchase_orders (purchase_number, supplier_id, status, purchase_date, subtotal, discount, total, notes, created_by, received_by, received_at, created_at, updated_at) ' .
			'VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?)',
			'sissddsiisss',
			array($purchaseNumber, $supplierId, 'received', $purchaseDate, $subtotal, $subtotal, '[B3] Received purchase for ' . $product['tag'], $this->adminId, $this->adminId, $receivedAt, $receivedAt, $receivedAt)
		);
		$this->lastPurchaseOrderId = (int) $this->connection->insert_id;
		$snapshot = $this->unitSnapshot($product, $quantity);
		$receivedQuantity = $quantity;
		$this->execute(
			'INSERT INTO purchase_order_items ' .
			'(purchase_order_id, product_id, quantity, purchase_price, discount, total, batch_number, expiration_date, received_quantity, created_at, updated_at, purchase_unit_id_snapshot, purchase_unit_code_snapshot, purchase_unit_name_snapshot, factor_to_base_snapshot, received_quantity_base, input_quantity_scale_snapshot, base_quantity_scale_snapshot, unit_config_version_snapshot, unit_status_snapshot) ' .
			'VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			'iidddssdssissddiiis',
			array(
				$this->lastPurchaseOrderId,
				$product['id'],
				$quantity,
				$product['purchase_price'],
				$subtotal,
				$batchNumber,
				$expiry,
				$receivedQuantity,
				$receivedAt,
				$receivedAt,
				$snapshot['unit_id_snapshot'],
				$snapshot['unit_code_snapshot'],
				$snapshot['unit_name_snapshot'],
				$snapshot['conversion_factor_to_base_snapshot'],
				$snapshot['quantity_base'],
				$snapshot['input_scale'],
				$snapshot['base_scale'],
				$snapshot['config_version'],
				$snapshot['status']
			)
		);
	}

	private function batchQuantities($tag, $total)
	{
		if ($tag === 'near_expiry_red') {
			return array($total - 30, 20, 10);
		}
		if ($tag === 'near_expiry_yellow') {
			return array($total - 70, 40, 30);
		}
		if ($tag === 'multi_batch_fefo' && $total >= 31) {
			return array(10, 20, $total - 30);
		}
		$first = max(1, (int) floor($total / 3));
		$second = max(1, (int) floor($total / 3));
		$third = $total - $first - $second;
		if ($third < 1) {
			$third = 1;
			$first = max(1, $total - $second - $third);
		}
		return array($first, $second, $third);
	}

	private function targetStock($tag)
	{
		$targets = array(
			'fast_mover_stable' => 10,
			'slow_mover' => 100,
			'no_sales_3_months' => 80,
			'seasonal' => 300,
			'new_less_3_months' => 30,
			'overstock' => 10000,
			'critical_stock' => 5,
			'regular_01' => 10,
			'regular_05' => 0,
			'near_expiry_red' => 40,
			'near_expiry_yellow' => 80,
			'multi_batch_fefo' => 50,
			'zero_stock' => 0,
			'legacy_one' => 150,
			'legacy_two' => 150
		);
		return isset($targets[$tag]) ? $targets[$tag] : 200;
	}

	private function expiries($tag)
	{
		$checkerDates = $this->today->format('Y-m-d') === '2026-11-22';
		if ($tag === 'near_expiry_red') {
			return array(
				$checkerDates ? '2026-11-30' : $this->today->modify('+2 months')->format('Y-m-d'),
				$this->today->modify('+4 months')->format('Y-m-d'),
				$this->today->modify('+8 months')->format('Y-m-d')
			);
		}
		if ($tag === 'near_expiry_yellow') {
			return array(
				$checkerDates ? '2027-01-15' : $this->today->modify('+4 months')->format('Y-m-d'),
				$this->today->modify('+4 months')->format('Y-m-d'),
				$this->today->modify('+8 months')->format('Y-m-d')
			);
		}
		if ($tag === 'multi_batch_fefo') {
			return array('2026-12-15', '2027-06-30', '2028-06-30');
		}
		return array('2027-06-30', '2027-12-31', '2028-06-30');
	}

	private function scalar($sql)
	{
		$result = $this->connection->query($sql);
		if (!$result) {
			throw new RuntimeException('Demo count query failed.');
		}
		$row = $result->fetch_row();
		$result->free();
		return $row[0];
	}

	private function ids($table)
	{
		if (!in_array($table, array('categories', 'manufacturers'), true)) {
			throw new RuntimeException('Demo master ID query target is not allowlisted.');
		}
		$result = $this->connection->query(
			'SELECT id FROM `' . $table . '` WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id'
		);
		if (!$result) {
			throw new RuntimeException('Demo master IDs are unavailable.');
		}
		$ids = array();
		while ($row = $result->fetch_row()) {
			$ids[] = (int) $row[0];
		}
		$result->free();
		return $ids;
	}

	private function referenceId($table, $column, $value)
	{
		if (!in_array($table, array('payment_methods', 'shipping_methods'), true) || $column !== 'code') {
			throw new RuntimeException('Demo reference lookup target is not allowlisted.');
		}
		$row = $this->first(
			'SELECT id FROM `' . $table . '` WHERE `code` = ? AND is_active = 1 LIMIT 1',
			's',
			array($value)
		);
		if (!$row) {
			throw new RuntimeException('A required payment or shipping reference is missing.');
		}
		return (int) $row['id'];
	}

	private function rows($sql, $types = '', array $values = array())
	{
		$stmt = $this->prepare($sql, $types, $values);
		if (!$stmt->execute()) {
			$stmt->close();
			throw new RuntimeException('A guarded demo seed query could not be executed.');
		}
		$result = $stmt->get_result();
		$rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : array();
		if ($result) {
			$result->free();
		}
		$stmt->close();
		return $rows;
	}

	private function first($sql, $types = '', array $values = array())
	{
		$rows = $this->rows($sql, $types, $values);
		return isset($rows[0]) ? $rows[0] : null;
	}

	private function execute($sql, $types, array $values)
	{
		$stmt = $this->prepare($sql, $types, $values);
		if (!$stmt->execute()) {
			$stmt->close();
			throw new RuntimeException('A guarded demo seed write failed.');
		}
		$stmt->close();
	}

	private function prepare($sql, $types, array $values)
	{
		$stmt = $this->connection->prepare($sql);
		if (!$stmt) {
			throw new RuntimeException('A guarded demo seed statement could not be prepared.');
		}
		if ($types !== '') {
			$arguments = array();
			$arguments[] = &$types;
			foreach ($values as $index => &$value) {
				$arguments[] = &$value;
			}
			if (count($values) !== strlen($types)) {
				$stmt->close();
				throw new RuntimeException(
					'Demo seed bind parameter count mismatch: ' .
					count($values) . ' values, ' . strlen($types) . ' types.'
				);
			}
			if (!call_user_func_array(array($stmt, 'bind_param'), $arguments)) {
				$stmt->close();
				throw new RuntimeException('A guarded demo seed statement could not bind its parameters.');
			}
			unset($value);
		}
		return $stmt;
	}

	private function writeAccountsFile(array $credentials, $path)
	{
		$lines = array('B3 demo accounts - generated for the isolated demo database.');
		foreach ($credentials as $credential) {
			$lines[] = 'role=' . $credential['role'];
			$lines[] = 'account=' . $credential['account'];
			$lines[] = 'login=' . $credential['login'];
			$lines[] = 'password_plaintext=' . $credential['password'];
			$lines[] = '';
		}
		if (file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX) === false) {
			throw new RuntimeException('Unable to write the approved local demo accounts file.');
		}
	}

	private function now()
	{
		return $this->clock->now()->format('Y-m-d H:i:s');
	}

	private function at($date, $time)
	{
		return $date . ' ' . $time;
	}

	private function plusMinutes($timestamp, $minutes)
	{
		return (new DateTimeImmutable($timestamp, new DateTimeZone('Asia/Jakarta')))
			->modify('+' . (int) $minutes . ' minutes')
			->format('Y-m-d H:i:s');
	}
}
