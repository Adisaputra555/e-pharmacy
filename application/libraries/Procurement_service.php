<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once __DIR__ . DIRECTORY_SEPARATOR . 'Product_unit_service.php';

class Procurement_service
{
	const STATUS_BLOCKED = 'Kalkulasi belum lengkap/diblokir';
	const STATUS_NO_PURCHASE = 'Tidak perlu pembelian';
	const STATUS_RECOMMENDED = 'Pembelian disarankan';
	const STATUS_MANUAL_REVIEW = 'Review manual';

	private $db;
	private $forecastService;
	private $config;

	public function __construct($db, Forecast_service $forecastService, array $config)
	{
		if (!is_object($db) || !method_exists($db, 'query')) {
			throw new InvalidArgumentException('Procurement service requires a database connection.');
		}
		$this->db = $db;
		$this->forecastService = $forecastService;
		$this->config = $config;
	}

	public static function isFeatureEnabled($environment, array $localConfig, $environmentMarker)
	{
		return $environment === 'demo' &&
			$environmentMarker === 'demo' &&
			isset($localConfig['procurement_recommendations_enabled']) &&
			$localConfig['procurement_recommendations_enabled'] === true;
	}

	public static function formatDisplayNumber($value, $maximumDecimals = 2, $minimumDecimals = 0)
	{
		$maximumDecimals = max(0, min(9, (int) $maximumDecimals));
		$minimumDecimals = max(0, min($maximumDecimals, (int) $minimumDecimals));
		if (!is_string($value) && !is_int($value) && !is_float($value)) {
			return '—';
		}

		$value = trim((string) $value);
		if (preg_match('/^(-?[0-9]+)\/([1-9][0-9]*)$/', $value, $match)) {
			$value = (float) $match[1] / (float) $match[2];
		} elseif (!is_numeric($value)) {
			return '—';
		}
		$number = (float) $value;
		if (is_infinite($number) || is_nan($number)) {
			return '—';
		}

		$formatted = number_format($number, $maximumDecimals, ',', '.');
		if ($maximumDecimals > $minimumDecimals && strpos($formatted, ',') !== false) {
			list($whole, $fraction) = explode(',', $formatted, 2);
			while (strlen($fraction) > $minimumDecimals && substr($fraction, -1) === '0') {
				$fraction = substr($fraction, 0, -1);
			}
			$formatted = $fraction === '' ? $whole : $whole . ',' . $fraction;
		}
		return $formatted;
	}

	public static function formatPeriodLabel(array $period)
	{
		$months = isset($period['months']) && is_array($period['months'])
			? array_values($period['months'])
			: array_values($period);
		$monthNames = array(
			1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
			5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
			9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
		);
		$labels = array();
		foreach ($months as $month) {
			if (!is_string($month) || !preg_match('/^([0-9]{4})-([0-9]{2})$/', $month, $match)) {
				continue;
			}
			$monthNumber = (int) $match[2];
			if (isset($monthNames[$monthNumber])) {
				$labels[] = array('month' => $monthNames[$monthNumber], 'year' => $match[1]);
			}
		}
		if (!$labels) {
			return 'periode tidak tersedia';
		}

		$first = $labels[0];
		$last = $labels[count($labels) - 1];
		if ($first['year'] === $last['year']) {
			return $first['month'] . '–' . $last['month'] . ' ' . $last['year'];
		}
		return $first['month'] . ' ' . $first['year'] . '–' . $last['month'] . ' ' . $last['year'];
	}

	public static function calculate(array $input)
	{
		$result = self::emptyResult($input);
		$historyStatus = isset($input['history_status']) ? $input['history_status'] : null;
		if ($historyStatus !== Forecast_service::STATUS_SUFFICIENT &&
			$historyStatus !== Forecast_service::STATUS_NO_SALES) {
			return self::blocked($result, 'Histori demand: ' . ($historyStatus ?: 'tidak tersedia') . '.');
		}

		$demandTotalValue = isset($input['demand_total_base']) ? $input['demand_total_base'] : null;
		$demandTotal = self::scaledInteger($demandTotalValue, 3, 'Demand', true);
		if ($demandTotal === null || bccomp($demandTotal, '0', 0) < 0) {
			return self::blocked($result, 'Demand tidak valid.');
		}
		if ($historyStatus === Forecast_service::STATUS_NO_SALES || bccomp($demandTotal, '0', 0) === 0) {
			$result['status'] = self::STATUS_MANUAL_REVIEW;
			$result['reason'] = 'Demand nol selama tiga bulan lengkap; perlu tinjauan manual.';
			$result['demand'] = '0';
			return $result;
		}
		$result['demand'] = self::formatRational($demandTotal, '3000');

		$usableStock = self::scaledInteger(
			isset($input['usable_stock_base']) ? $input['usable_stock_base'] : null,
			3,
			'Usable stock',
			true
		);
		$eligiblePo = self::scaledInteger(
			isset($input['eligible_open_po_base']) ? $input['eligible_open_po_base'] : '0',
			3,
			'Eligible open PO',
			true
		);
		if ($usableStock === null || $eligiblePo === null ||
			bccomp($usableStock, '0', 0) < 0 || bccomp($eligiblePo, '0', 0) < 0) {
			return self::blocked($result, 'Stok layak pakai atau sisa PO terhitung tidak valid.');
		}

		$pValue = array_key_exists('global_target_months', $input)
			? $input['global_target_months']
			: null;
		$pMilli = self::scaledInteger($pValue, 3, 'Target P', false);
		if ($pMilli === null || bccomp($pMilli, '0', 0) <= 0 ||
			bccomp($pMilli, '24000', 0) > 0) {
			return self::blocked($result, 'Target P global harus > 0 dan <= 24 bulan; tidak ada fallback.');
		}
		$result['target_months'] = self::formatScaled($pMilli, 3);

		$leadDays = self::integerValue(
			array_key_exists('manual_lead_time_days', $input) ? $input['manual_lead_time_days'] : null
		);
		if ($leadDays === null || $leadDays < 0 || $leadDays > 365) {
			return self::blocked($result, 'Lead time manual kosong atau di luar rentang 0–365 hari.');
		}
		$result['lead_time_days'] = $leadDays;
		$result['lead_time_months'] = self::formatRational((string) $leadDays, '30');
		$result['lead_time_source'] = 'manual';

		$safetyStock = self::convertMinimumStock($input);
		if ($safetyStock === null) {
			return self::blocked($result, 'Safety stock/minimum_stock tidak dapat dikonversi dengan satuan terverifikasi.');
		}
		$result['safety_stock_base'] = $safetyStock;
		$result['safety_stock_verified'] = true;

		$inventoryPosition = bcadd($usableStock, $eligiblePo, 0);
		$result['usable_stock_base'] = self::formatScaled($usableStock, 3);
		$result['eligible_open_po_base'] = self::formatScaled($eligiblePo, 3);
		$result['inventory_position_base'] = self::formatScaled($inventoryPosition, 3);

		$leadDaysTerm = bcadd(
			bcmul('30', $pMilli, 0),
			bcmul('1000', (string) $leadDays, 0),
			0
		);
		$demandTerm = bcmul($demandTotal, $leadDaysTerm, 0);
		$safetyDifference = bcsub(
			self::scaledInteger($safetyStock, 3, 'Safety stock', true),
			$inventoryPosition,
			0
		);
		$q0Numerator = bcadd(
			$demandTerm,
			bcmul($safetyDifference, '90000', 0),
			0
		);
		$q0Denominator = '90000000';
		if (bccomp($q0Numerator, '0', 0) < 0) {
			$q0Numerator = '0';
		}
		$result['q0'] = self::formatRational($q0Numerator, $q0Denominator);
		$result['q0_numerator'] = $q0Numerator;
		$result['q0_denominator'] = $q0Denominator;

		$zero = bccomp($q0Numerator, '0', 0) === 0;
		if ($zero) {
			$result['status'] = self::STATUS_NO_PURCHASE;
			$result['reason'] = 'Semua input valid dan Q0 = 0; MOQ tidak diterapkan.';
			$finalMilli = '0';
			$result['q_final_base'] = '0';
			$result = self::setProjection($result, $demandTotal, $pMilli, $leadDays, $inventoryPosition, $finalMilli);
			return $result;
		}

		$factor = self::scaledInteger(
			array_key_exists('base_per_purchase_unit', $input) ? $input['base_per_purchase_unit'] : null,
			9,
			'Base per purchase unit',
			false
		);
		if ($factor === null || bccomp($factor, '0', 0) <= 0 ||
			bccomp($factor, '999999999999999999', 0) > 0) {
			return self::blocked($result, 'F (base_per_purchase_unit) kosong, invalid, atau tidak > 0.');
		}
		$result['base_per_purchase_unit'] = self::formatScaled($factor, 9);

		$moqMode = isset($input['moq_mode']) ? $input['moq_mode'] : null;
		if ($moqMode !== 'none' && $moqMode !== 'set') {
			return self::blocked($result, 'Mode MOQ tidak valid.');
		}
		$multipleValue = array_key_exists('order_multiple_purchase_units', $input)
			? $input['order_multiple_purchase_units']
			: null;
		if (($multipleValue === null || $multipleValue === '') && $moqMode === 'none') {
			$multipleValue = '1';
			$result['order_multiple_defaulted'] = true;
		}
		$multiple = self::scaledInteger($multipleValue, 3, 'Order multiple', false);
		if ($multiple === null || bccomp($multiple, '0', 0) <= 0 ||
			bccomp($multiple, '999999999999999', 0) > 0) {
			return self::blocked($result, 'Kelipatan pembelian U harus > 0 dan representable.');
		}
		$result['order_multiple_purchase_units'] = self::formatScaled($multiple, 3);

		$moq = '0';
		if ($moqMode === 'set') {
			$moq = self::scaledInteger(
				array_key_exists('moq_purchase_units', $input) ? $input['moq_purchase_units'] : null,
				3,
				'MOQ',
				false
			);
			if ($moq === null || bccomp($moq, '0', 0) <= 0) {
				return self::blocked($result, 'MOQ mode set membutuhkan M > 0 yang representable.');
			}
		}
		$result['moq_mode'] = $moqMode;
		$result['moq_purchase_units'] = self::formatScaled($moq, 3);

		$rawNumerator = bcmul($q0Numerator, '1000000000000', 0);
		$rawDenominator = bcmul($q0Denominator, bcmul($factor, $multiple, 0), 0);
		$moqNumerator = bcmul($moq, $rawDenominator, 0);
		$rawComparedToMoq = bcmul($rawNumerator, $multiple, 0);
		$result['raw_purchase_quantity'] = self::formatRational(
			bcmul($q0Numerator, '1000000000', 0),
			bcmul($q0Denominator, $factor, 0)
		);
		if (bccomp($rawComparedToMoq, $moqNumerator, 0) >= 0) {
			$ceilCount = self::ceilDivide($rawNumerator, $rawDenominator);
		} else {
			$ceilCount = self::ceilDivide($moq, $multiple);
		}
		$supplierMilli = bcmul($multiple, $ceilCount, 0);
		if (bccomp($supplierMilli, '999999999999999', 0) > 0) {
			return self::blocked($result, 'Jumlah satuan beli melebihi DECIMAL(15,3).');
		}
		$result['supplier_quantity'] = self::formatScaled($supplierMilli, 3);

		$baseScale = self::integerValue(
			array_key_exists('base_quantity_scale', $input) ? $input['base_quantity_scale'] : null
		);
		if ($baseScale === null || $baseScale < 0 || $baseScale > 3) {
			return self::blocked($result, 'Skala satuan dasar tidak valid.');
		}
		$baseScaleDenominator = bcpow('10', (string) (12 - $baseScale), 0);
		$baseScaleNumerator = bcmul($supplierMilli, $factor, 0);
		if (bccomp(bcmod($baseScaleNumerator, $baseScaleDenominator), '0', 0) !== 0) {
			return self::blocked($result, 'Q final tidak representable pada skala satuan dasar; tidak dibulatkan.');
		}
		$finalAtScale = bcdiv($baseScaleNumerator, $baseScaleDenominator, 0);
		$finalMilli = bcmul($finalAtScale, bcpow('10', (string) (3 - $baseScale), 0), 0);
		if (bccomp($finalMilli, '999999999999999', 0) > 0) {
			return self::blocked($result, 'Q final melebihi DECIMAL(15,3).');
		}

		$result['q_final_base'] = self::formatScaled($finalMilli, 3);
		$result['status'] = self::STATUS_RECOMMENDED;
		$result['reason'] = 'Input valid; jumlah pembelian dibulatkan ke kelipatan supplier tanpa mengubah skala base.';
		$result = self::setProjection($result, $demandTotal, $pMilli, $leadDays, $inventoryPosition, $finalMilli);
		return $result;
	}

	public function getRecommendations()
	{
		$this->assertDemoIdentity();
		$forecast = $this->forecastService->getDashboardForecast();
		$forecastByProduct = array();
		foreach ($forecast['products'] as $product) {
			$forecastByProduct[(int) $product['id']] = $product;
		}

		$supplierRows = $this->queryRows(
			'SELECT ps.product_id, ps.supplier_id, ps.is_preferred, ps.purchase_unit, ' .
			'ps.base_per_purchase_unit, ps.moq_mode, ps.moq_purchase_units, ' .
			'ps.order_multiple_purchase_units, ps.manual_lead_time_days, ' .
			'p.code AS product_code, p.name AS product_name, p.description AS product_description, ' .
			'p.minimum_stock, p.unit_id, bu.name AS base_unit_name, s.name AS supplier_name, ' .
			'v.product_id AS verified_product_id, v.base_unit_id, ' .
			'c.conversion_factor_to_base AS minimum_stock_factor, c.input_scale AS minimum_stock_input_scale, ' .
			'c.base_scale AS base_quantity_scale, c.status AS minimum_stock_unit_status ' .
			'FROM product_suppliers ps ' .
			'JOIN products p ON p.id = ps.product_id ' .
			'JOIN suppliers s ON s.id = ps.supplier_id ' .
			'JOIN units bu ON bu.id = p.unit_id ' .
			'LEFT JOIN product_unit_verifications v ON v.product_id = p.id AND v.status = "verified" ' .
			'LEFT JOIN product_unit_conversions c ON c.product_id = p.id AND c.unit_id = p.unit_id AND c.status = "verified" ' .
			'WHERE p.deleted_at IS NULL AND p.is_active = 1 AND s.deleted_at IS NULL AND s.is_active = 1 ' .
			'ORDER BY p.code, ps.is_preferred DESC, s.name'
		);
		$orderedRows = $this->queryRows(
			'SELECT po.supplier_id, poi.product_id, ' .
			'SUM(GREATEST(0, poi.quantity - poi.received_quantity)) AS remaining_purchase_units, ' .
			'COUNT(DISTINCT po.id) AS open_order_count ' .
			'FROM purchase_orders po ' .
			'JOIN purchase_order_items poi ON poi.purchase_order_id = po.id ' .
			'WHERE po.status = "ordered" ' .
			'GROUP BY po.supplier_id, poi.product_id'
		);
		$orderedByPair = array();
		foreach ($orderedRows as $row) {
			$key = (int) $row['product_id'] . ':' . (int) $row['supplier_id'];
			$orderedByPair[$key] = $row;
		}
		$forecastIssueCodes = $this->loadForecastIssueCodes();

		$target = array_key_exists('global_target_months', $this->config)
			? $this->config['global_target_months']
			: null;
		$recommendations = array();
		foreach ($supplierRows as $supplier) {
			$productId = (int) $supplier['product_id'];
			$key = $productId . ':' . (int) $supplier['supplier_id'];
			$forecastProduct = isset($forecastByProduct[$productId])
				? $forecastByProduct[$productId]
				: null;
			$ordered = isset($orderedByPair[$key]) ? $orderedByPair[$key] : null;
			$input = $this->calculationInput(
				$supplier,
				$forecastProduct,
				$target
			);
			$calculation = self::calculate($input);
			$safetyStockBase = self::convertMinimumStock($input);
			$historyIssue = self::historyIssue(
				$forecastProduct,
				isset($forecastIssueCodes[$productId]) ? $forecastIssueCodes[$productId] : null
			);
			$isBlocked = $calculation['status'] === self::STATUS_BLOCKED;
			$usableStockBase = $calculation['usable_stock_base'] !== null
				? $calculation['usable_stock_base']
				: ($forecastProduct ? self::formatFloatQuantity($forecastProduct['usable_stock']) : null);
			$eligibleOpenPoBase = $calculation['eligible_open_po_base'] !== null
				? $calculation['eligible_open_po_base']
				: '0';
			$inventoryPositionBase = $isBlocked ? null : $calculation['inventory_position_base'];
			$leadDays = self::integerValue($supplier['manual_lead_time_days']);
			$leadMonths = $leadDays !== null
				? self::formatRational((string) $leadDays, '30')
				: null;
			$safetyStockDisplay = $safetyStockBase !== null
				? self::formatFloatQuantity($safetyStockBase)
				: self::formatOptionalQuantity($supplier['minimum_stock']);
			$coverage = null;
			if ($forecastProduct && $forecastProduct['average_monthly_demand'] !== null) {
				$coverage = Forecast_service::classifyCoverage(
					(float) $forecastProduct['usable_stock'],
					(float) $forecastProduct['average_monthly_demand']
				);
			}
			$recommendations[] = array(
				'product_id' => $productId,
				'product_code' => $supplier['product_code'],
				'product_name' => $supplier['product_name'],
				'scenario_tag' => $this->scenarioTag($supplier['product_description']),
				'supplier_id' => (int) $supplier['supplier_id'],
				'supplier_name' => $supplier['supplier_name'],
				'is_preferred' => (int) $supplier['is_preferred'] === 1,
				'purchase_unit' => $supplier['purchase_unit'],
				'base_unit_name' => $supplier['base_unit_name'],
				'demand_period' => $forecast ? $forecast['period'] : array(),
				'demand_months' => $forecastProduct ? $forecastProduct['monthly_demand'] : array(),
				'history_status' => $forecastProduct ? $forecastProduct['data_status'] : Forecast_service::STATUS_CHECK,
				'unit_verified' => $forecastProduct ? !empty($forecastProduct['unit_verified']) : false,
				'history_reason_code' => $historyIssue['code'],
				'history_reason' => $historyIssue['reason'],
				'demand' => $calculation['demand'],
				'target_months' => $target,
				'lead_time_days' => $supplier['manual_lead_time_days'],
				'lead_time_months' => $leadMonths,
				'lead_time_source' => $leadDays !== null ? 'manual' : null,
				'safety_stock_base' => $safetyStockBase,
				'safety_stock_display' => $safetyStockDisplay,
				'safety_stock_configured' => self::formatOptionalQuantity($supplier['minimum_stock']),
				'safety_stock_verified' => $safetyStockBase !== null,
				'usable_stock_base' => $usableStockBase,
				'eligible_open_po_base' => $eligibleOpenPoBase,
				'open_po_review_qty' => $ordered ? self::formatFloatQuantity($ordered['remaining_purchase_units']) : '0',
				'open_po_review_count' => $ordered ? (int) $ordered['open_order_count'] : 0,
				'inventory_position_base' => $inventoryPositionBase,
				'q0' => $isBlocked ? null : $calculation['q0'],
				'base_per_purchase_unit' => $calculation['base_per_purchase_unit'] !== null
					? $calculation['base_per_purchase_unit']
					: $supplier['base_per_purchase_unit'],
				'moq_mode' => $calculation['moq_mode'] ?: $supplier['moq_mode'],
				'moq_purchase_units' => $calculation['moq_purchase_units'] !== null
					? $calculation['moq_purchase_units']
					: $supplier['moq_purchase_units'],
				'order_multiple_purchase_units' => $calculation['order_multiple_purchase_units'] !== null
					? $calculation['order_multiple_purchase_units']
					: $supplier['order_multiple_purchase_units'],
				'raw_purchase_quantity' => $calculation['raw_purchase_quantity'],
				'supplier_quantity' => $calculation['supplier_quantity'],
				'q_final_base' => $isBlocked ? null : $calculation['q_final_base'],
				'coverage_months' => $coverage ? $coverage['months'] : null,
				'coverage_status' => $coverage ? $coverage['status'] : 'Tidak tersedia',
				'coverage_after_arrival' => $calculation['coverage_after_arrival'],
				'coverage_after_arrival_display' => $calculation['coverage_after_arrival_display'],
				'target_met' => $calculation['target_met'],
				'red_risk_quantity' => $forecastProduct ? self::formatFloatQuantity($forecastProduct['red_risk_quantity']) : '0',
				'yellow_risk_quantity' => $forecastProduct ? self::formatFloatQuantity($forecastProduct['yellow_risk_quantity']) : '0',
				'reserved_excess' => $forecastProduct ? self::formatFloatQuantity($forecastProduct['reserved_excess']) : '0',
				'status' => $calculation['status'],
				'reason' => $calculation['reason'],
				'formula_values' => array(
					'demand' => $calculation['demand'],
					'target_months' => $target,
					'lead_time_months' => $leadMonths,
					'safety_stock' => $safetyStockBase !== null
						? self::formatFloatQuantity($safetyStockBase)
						: self::formatOptionalQuantity($supplier['minimum_stock']),
					'usable_stock_base' => $usableStockBase,
					'eligible_open_po_base' => $eligibleOpenPoBase,
					'inventory_position_base' => $inventoryPositionBase,
					'q0' => $isBlocked ? null : $calculation['q0'],
					'q_final_base' => $isBlocked ? null : $calculation['q_final_base'],
					'base_per_purchase_unit' => $calculation['base_per_purchase_unit'] !== null
						? $calculation['base_per_purchase_unit']
						: self::formatOptionalQuantity($supplier['base_per_purchase_unit']),
					'moq_purchase_units' => $calculation['moq_purchase_units'] !== null
						? $calculation['moq_purchase_units']
						: self::formatOptionalQuantity($supplier['moq_purchase_units']),
					'order_multiple_purchase_units' => $calculation['order_multiple_purchase_units'] !== null
						? $calculation['order_multiple_purchase_units']
						: self::formatOptionalQuantity($supplier['order_multiple_purchase_units']),
					'raw_purchase_quantity' => $calculation['raw_purchase_quantity'],
					'supplier_quantity' => $calculation['supplier_quantity'],
					'coverage_after_arrival' => $calculation['coverage_after_arrival_display']
				),
				'formula' => $calculation['formula']
			);
		}

		$statusCounts = array(
			self::STATUS_BLOCKED => 0,
			self::STATUS_MANUAL_REVIEW => 0,
			self::STATUS_NO_PURCHASE => 0,
			self::STATUS_RECOMMENDED => 0
		);
		foreach ($recommendations as $row) {
			$statusCounts[$row['status']]++;
		}

		return array(
			'target_months' => $target,
			'period' => $forecast['period'],
			'method' => $forecast['method'],
			'status_counts' => $statusCounts,
			'recommendations' => $recommendations
		);
	}

	private function loadForecastIssueCodes()
	{
		$rows = $this->queryRows(
			'SELECT oi.product_id, paid.first_paid_at, paid.missing_paid_at_count, ' .
			'oi.unit_status_snapshot, oi.quantity_base ' .
			'FROM order_items oi ' .
			'JOIN orders o ON o.id = oi.order_id ' .
			'LEFT JOIN (' .
			'SELECT order_id, SUM(amount) AS paid_amount, MIN(paid_at) AS first_paid_at, ' .
			'SUM(CASE WHEN paid_at IS NULL THEN 1 ELSE 0 END) AS missing_paid_at_count ' .
			'FROM payments WHERE status = "paid" GROUP BY order_id' .
			') paid ON paid.order_id = o.id ' .
			'WHERE o.status <> "cancelled" ' .
			'AND paid.paid_amount >= o.total ' .
			'AND EXISTS (SELECT 1 FROM payments qualifying_payment ' .
			'WHERE qualifying_payment.order_id = o.id AND qualifying_payment.status = "paid") ' .
			'ORDER BY o.id, oi.id'
		);
		$codes = array();
		foreach ($rows as $row) {
			$productId = (int) $row['product_id'];
			if ((int) $row['missing_paid_at_count'] > 0 ||
				$row['first_paid_at'] === null || $row['first_paid_at'] === '') {
				$codes[$productId] = 'PAID_AT_MISSING';
				continue;
			}
			if ((!isset($row['unit_status_snapshot']) || $row['unit_status_snapshot'] !== 'verified' ||
				!isset($row['quantity_base']) || $row['quantity_base'] === '' ||
				!is_numeric($row['quantity_base'])) &&
				!isset($codes[$productId])) {
				$codes[$productId] = 'BASE_SNAPSHOT_INVALID';
			}
		}
		return $codes;
	}

	private static function historyIssue($forecastProduct, $issueCode)
	{
		if (!$forecastProduct || $forecastProduct['data_status'] !== Forecast_service::STATUS_CHECK) {
			return array('code' => null, 'reason' => null);
		}
		if (!isset($forecastProduct['net_demand']) || $forecastProduct['net_demand'] === null) {
			return array(
				'code' => 'FIRST_RECEIPT_MISSING',
				'reason' => 'Tidak ditemukan bukti penerimaan stok pertama.'
			);
		}
		if ($issueCode === 'PAID_AT_MISSING') {
			return array(
				'code' => $issueCode,
				'reason' => 'Order paid memiliki pembayaran tanpa paid_at; demand order tersebut tidak digunakan.'
			);
		}
		if ($issueCode === 'BASE_SNAPSHOT_INVALID') {
			return array(
				'code' => $issueCode,
				'reason' => 'Item paid tidak memiliki snapshot satuan base terverifikasi; demand item tersebut tidak digunakan.'
			);
		}
		if (is_numeric($forecastProduct['net_demand']) && (float) $forecastProduct['net_demand'] < 0) {
			return array(
				'code' => 'NET_DEMAND_NEGATIVE',
				'reason' => 'Demand bersih negatif setelah koreksi; data perlu direkonsiliasi.'
			);
		}
		return array(
			'code' => 'FORECAST_ISSUE_UNCLASSIFIED',
			'reason' => 'Forecast menandai data tidak konsisten; periksa penerimaan, pembayaran, dan snapshot satuan.'
		);
	}

	private function calculationInput(array $supplier, $forecastProduct, $target)
	{
		$unitVerified = $forecastProduct && !empty($forecastProduct['unit_verified']) &&
			$supplier['verified_product_id'] !== null &&
			$supplier['minimum_stock_unit_status'] === 'verified' &&
			(int) $supplier['base_unit_id'] > 0;
		$historyStatus = $forecastProduct
			? $forecastProduct['data_status']
			: Forecast_service::STATUS_CHECK;
		$demandTotal = $forecastProduct && is_numeric($forecastProduct['net_demand'])
			? number_format((float) $forecastProduct['net_demand'], 3, '.', '')
			: null;
		$usableStock = $forecastProduct
			? self::formatFloatQuantity($forecastProduct['usable_stock'])
			: null;
		return array(
			'history_status' => $historyStatus,
			'demand_total_base' => $demandTotal,
			'period_months' => $forecastProduct ? array_keys($forecastProduct['monthly_demand']) : array(),
			'usable_stock_base' => $usableStock,
			'eligible_open_po_base' => '0.000',
			'global_target_months' => $target,
			'manual_lead_time_days' => $supplier['manual_lead_time_days'],
			'minimum_stock_unit_verified' => $unitVerified,
			'minimum_stock_quantity' => $supplier['minimum_stock'],
			'minimum_stock_factor' => $supplier['minimum_stock_factor'],
			'minimum_stock_input_scale' => $supplier['minimum_stock_input_scale'],
			'base_quantity_scale' => $supplier['base_quantity_scale'],
			'base_per_purchase_unit' => $supplier['base_per_purchase_unit'],
			'moq_mode' => $supplier['moq_mode'],
			'moq_purchase_units' => $supplier['moq_purchase_units'],
			'order_multiple_purchase_units' => $supplier['order_multiple_purchase_units']
		);
	}

	private static function convertMinimumStock(array $input)
	{
		if (empty($input['minimum_stock_unit_verified']) ||
			!isset($input['minimum_stock_quantity'], $input['minimum_stock_factor']) ||
			$input['minimum_stock_factor'] === null ||
			!isset($input['minimum_stock_input_scale'], $input['base_quantity_scale'])) {
			return null;
		}
		$minimumStock = self::scaledInteger($input['minimum_stock_quantity'], 3, 'Minimum stock', true);
		$factor = self::scaledInteger($input['minimum_stock_factor'], 9, 'Minimum stock factor', false);
		$inputScale = self::integerValue($input['minimum_stock_input_scale']);
		$baseScale = self::integerValue($input['base_quantity_scale']);
		if ($minimumStock === null || bccomp($minimumStock, '0', 0) < 0 ||
			$factor === null || bccomp($factor, '0', 0) <= 0 ||
			$inputScale === null || $baseScale === null) {
			return null;
		}
		$quantity = self::formatScaled($minimumStock, 3);
		if (bccomp($minimumStock, '0', 0) === 0) {
			return '0.000';
		}
		try {
			return Product_unit_service::calculateBaseQuantity(
				$quantity,
				self::formatScaled($factor, 9),
				$inputScale,
				$baseScale
			);
		} catch (InvalidArgumentException $exception) {
			return null;
		}
	}

	private static function setProjection(array $result, $demandTotal, $pMilli, $leadDays, $inventoryPosition, $finalMilli)
	{
		$projectionNumerator = bcsub(
			bcmul(
				'90',
				bcadd($inventoryPosition, $finalMilli, 0),
				0
			),
			bcmul($demandTotal, (string) $leadDays, 0),
			0
		);
		$projectionDenominator = bcmul('30', $demandTotal, 0);
		$result['coverage_after_arrival_display'] = self::formatRational(
			$projectionNumerator,
			$projectionDenominator
		);
		$result['coverage_after_arrival'] = (float) $projectionNumerator / (float) $projectionDenominator;
		$result['target_met'] = bccomp(
			bcmul($projectionNumerator, '1000', 0),
			bcmul($projectionDenominator, $pMilli, 0),
			0
		) >= 0;
		$result['formula']['projection'] = '(IP + Q final - D x L) / D';
		return $result;
	}

	private static function emptyResult(array $input)
	{
		$period = isset($input['period_months']) && is_array($input['period_months'])
			? $input['period_months']
			: array();
		return array(
			'status' => self::STATUS_BLOCKED,
			'reason' => null,
			'demand' => null,
			'period_months' => $period,
			'target_months' => null,
			'lead_time_days' => null,
			'lead_time_months' => null,
			'lead_time_source' => null,
			'safety_stock_base' => null,
			'safety_stock_verified' => false,
			'usable_stock_base' => null,
			'eligible_open_po_base' => null,
			'inventory_position_base' => null,
			'q0' => null,
			'q0_numerator' => null,
			'q0_denominator' => null,
			'base_per_purchase_unit' => null,
			'moq_mode' => null,
			'moq_purchase_units' => null,
			'order_multiple_purchase_units' => null,
			'order_multiple_defaulted' => false,
			'raw_purchase_quantity' => null,
			'supplier_quantity' => null,
			'q_final_base' => null,
			'coverage_after_arrival' => null,
			'coverage_after_arrival_display' => null,
			'target_met' => false,
			'formula' => array(
				'q0' => 'MAX(0, D x (P + L) + SS - IP)',
				'q_supplier' => 'U x CEIL(MAX(Q0 / F, M) / U)',
				'q_final' => 'q_supplier x F'
			)
		);
	}

	private static function blocked(array $result, $reason)
	{
		$result['status'] = self::STATUS_BLOCKED;
		$result['reason'] = $reason;
		$result['coverage_after_arrival'] = null;
		$result['coverage_after_arrival_display'] = null;
		$result['target_met'] = false;
		return $result;
	}

	private static function scaledInteger($value, $scale, $label, $allowZero)
	{
		if (!is_string($value) && !is_int($value)) {
			return null;
		}
		$value = trim((string) $value);
		if (!preg_match('/^[0-9]+(?:\.[0-9]+)?$/', $value)) {
			return null;
		}
		$parts = explode('.', $value, 2);
		$fraction = isset($parts[1]) ? $parts[1] : '';
		if (strlen($fraction) > $scale) {
			return null;
		}
		$whole = ltrim($parts[0], '0');
		$whole = $whole === '' ? '0' : $whole;
		$fraction = str_pad($fraction, $scale, '0');
		$integer = ltrim($whole . $fraction, '0');
		$integer = $integer === '' ? '0' : $integer;
		if (!$allowZero && bccomp($integer, '0', 0) === 0) {
			return null;
		}
		return $integer;
	}

	private static function integerValue($value)
	{
		if ((!is_string($value) && !is_int($value)) ||
			!preg_match('/^[0-9]+$/', trim((string) $value))) {
			return null;
		}
		return (int) $value;
	}

	private static function formatScaled($integer, $scale)
	{
		$integer = ltrim((string) $integer, '0');
		$integer = $integer === '' ? '0' : $integer;
		if ($scale === 0) {
			return $integer;
		}
		$integer = str_pad($integer, $scale + 1, '0', STR_PAD_LEFT);
		$whole = substr($integer, 0, -$scale);
		$fraction = substr($integer, -$scale);
		$fraction = rtrim($fraction, '0');
		return $whole . ($fraction === '' ? '' : '.' . $fraction);
	}

	private static function formatRational($numerator, $denominator)
	{
		if (bccomp($denominator, '0', 0) <= 0) {
			throw new InvalidArgumentException('Rational denominator must be positive.');
		}
		$negative = bccomp($numerator, '0', 0) < 0;
		$absolute = $negative ? bcsub('0', $numerator, 0) : $numerator;
		$gcd = self::greatestCommonDivisor($absolute, $denominator);
		$reducedNumerator = bcdiv($absolute, $gcd, 0);
		$reducedDenominator = bcdiv($denominator, $gcd, 0);
		$remaining = $reducedDenominator;
		$twos = 0;
		$fives = 0;
		while (bccomp(bcmod($remaining, '2'), '0', 0) === 0) {
			$remaining = bcdiv($remaining, '2', 0);
			$twos++;
		}
		while (bccomp(bcmod($remaining, '5'), '0', 0) === 0) {
			$remaining = bcdiv($remaining, '5', 0);
			$fives++;
		}
		if (bccomp($remaining, '1', 0) !== 0) {
			return ($negative ? '-' : '') . $reducedNumerator . '/' . $reducedDenominator;
		}
		$scale = max($twos, $fives);
		$decimal = bcdiv($absolute, $denominator, $scale);
		$decimal = rtrim(rtrim($decimal, '0'), '.');
		if ($decimal === '') {
			$decimal = '0';
		}
		return ($negative ? '-' : '') . $decimal;
	}

	private static function greatestCommonDivisor($left, $right)
	{
		$left = bccomp($left, '0', 0) < 0 ? bcsub('0', $left, 0) : $left;
		$right = bccomp($right, '0', 0) < 0 ? bcsub('0', $right, 0) : $right;
		while (bccomp($right, '0', 0) !== 0) {
			$remainder = bcmod($left, $right);
			$left = $right;
			$right = $remainder;
		}
		return bccomp($left, '0', 0) === 0 ? '1' : $left;
	}

	private static function ceilDivide($numerator, $denominator)
	{
		if (bccomp($denominator, '0', 0) <= 0) {
			throw new InvalidArgumentException('Ceiling divisor must be positive.');
		}
		$quotient = bcdiv($numerator, $denominator, 0);
		$remainder = bcmod($numerator, $denominator);
		return bccomp($remainder, '0', 0) === 0
			? $quotient
			: bcadd($quotient, '1', 0);
	}

	private static function formatFloatQuantity($value)
	{
		return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
	}

	private static function formatOptionalQuantity($value)
	{
		return $value === null || $value === ''
			? null
			: self::formatFloatQuantity($value);
	}

	private function assertDemoIdentity()
	{
		$identity = $this->queryRows('SELECT DATABASE() AS database_name, CURRENT_USER() AS account_name');
		if (!$identity ||
			$identity[0]['database_name'] !== 'db_apotek_online_demo' ||
			strpos($identity[0]['account_name'], 'apotek_demo_app@') !== 0) {
			throw new RuntimeException('Procurement recommendations require the scoped demo app account.');
		}
		$marker = $this->queryRows('SELECT environment FROM environment_marker WHERE id = 1');
		if (!$marker || $marker[0]['environment'] !== 'demo') {
			throw new RuntimeException('Procurement recommendations require the demo environment marker.');
		}
	}

	private function scenarioTag($description)
	{
		return is_string($description) && preg_match('/\[B5:([a-z0-9_]+)\]/i', $description, $match)
			? strtolower($match[1])
			: null;
	}

	private function queryRows($sql)
	{
		$result = $this->db->query($sql);
		if ($result === false) {
			throw new RuntimeException('Procurement read query failed.');
		}
		if ($result instanceof mysqli_result) {
			$rows = $result->fetch_all(MYSQLI_ASSOC);
			$result->free();
			return $rows;
		}
		if (is_object($result) && method_exists($result, 'result_array')) {
			return $result->result_array();
		}
		throw new RuntimeException('Procurement database result is unsupported.');
	}
}
