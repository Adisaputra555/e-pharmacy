<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Forecast_service
{
	const STATUS_CHECK = 'Data perlu diperiksa';
	const STATUS_INSUFFICIENT = 'Belum cukup data';
	const STATUS_NO_SALES = 'Tidak ada penjualan';
	const STATUS_SUFFICIENT = 'Cukup data';

	private $db;
	private $clock;

	public function __construct($db = null, Business_clock $clock = null)
	{
		if (is_array($db)) {
			$options = $db;
			$db = isset($options['db']) ? $options['db'] : null;
			$clock = isset($options['clock']) ? $options['clock'] : $clock;
		}

		if ($db === null && function_exists('get_instance')) {
			$application = &get_instance();
			$db = $application->db;
		}
		if ($db === null) {
			throw new InvalidArgumentException('Forecast service requires a database connection.');
		}
		if (!class_exists('Business_clock')) {
			require_once __DIR__ . DIRECTORY_SEPARATOR . 'Business_clock.php';
		}

		$this->db = $db;
		$this->clock = $clock ?: new Business_clock();
	}

	public static function isEnabled($environment, $localConfigValue)
	{
		return $environment === 'demo' && $localConfigValue === true;
	}

	public static function isDashboardEnabled($environment, $flagEnabled, $environmentMarker)
	{
		return self::isEnabled($environment, $flagEnabled) && $environmentMarker === 'demo';
	}

	public static function dashboardFlagEnabled($environment, $configPath)
	{
		if ($environment !== 'demo' || !is_file($configPath)) {
			return false;
		}
		$localConfig = require $configPath;
		$enabled = is_array($localConfig) && isset($localConfig['forecast_dashboard_enabled'])
			? $localConfig['forecast_dashboard_enabled']
			: false;
		return self::isEnabled($environment, $enabled);
	}

	public static function completedMonthKeys(DateTimeInterface $now, $count = 3)
	{
		if ((int) $count < 1) {
			throw new InvalidArgumentException('Completed month count must be positive.');
		}

		$timezone = new DateTimeZone('Asia/Jakarta');
		$current = (new DateTimeImmutable($now->format('Y-m-d H:i:s.uP')))->setTimezone($timezone);
		$currentMonth = $current->modify('first day of this month')->setTime(0, 0, 0);
		$firstMonth = $currentMonth->modify('-' . (int) $count . ' months');
		$months = array();
		for ($index = 0; $index < (int) $count; $index++) {
			$months[] = $firstMonth->modify('+' . $index . ' months')->format('Y-m');
		}
		return $months;
	}

	public static function calculateMovingAverage(array $monthlyDemand, array $monthKeys)
	{
		if (count($monthKeys) !== 3) {
			throw new InvalidArgumentException('Moving average requires exactly three complete months.');
		}

		$total = 0.0;
		foreach ($monthKeys as $monthKey) {
			if (array_key_exists($monthKey, $monthlyDemand) && $monthlyDemand[$monthKey] === null) {
				throw new InvalidArgumentException('Moving average cannot include an incomplete trading month.');
			}
			$value = array_key_exists($monthKey, $monthlyDemand) ? $monthlyDemand[$monthKey] : 0;
			if (!is_numeric($value)) {
				throw new InvalidArgumentException('Monthly demand must be numeric.');
			}
			$total += (float) $value;
		}

		$average = $total / 3;
		return array(
			'demand' => $average,
			'forecast' => array(
				1 => $average,
				3 => $average * 3,
				6 => $average * 6
			)
		);
	}

	public static function evaluateHistory($firstReceivedAt, array $monthKeys, array $monthlyDemand, $hasDataIssue = false)
	{
		$receipt = self::parseBusinessDate($firstReceivedAt);
		if (!$receipt) {
			return array(
				'status' => self::STATUS_CHECK,
				'complete_month_count' => 0,
				'net_demand' => null,
				'monthly_demand' => array_fill_keys($monthKeys, null)
			);
		}

		$firstCompleteMonth = $receipt->modify('first day of next month')->format('Y-m');
		$values = array();
		$completeMonthCount = 0;
		$netDemand = 0.0;
		foreach ($monthKeys as $monthKey) {
			if ($monthKey < $firstCompleteMonth) {
				$values[$monthKey] = null;
				continue;
			}
			$value = array_key_exists($monthKey, $monthlyDemand) ? $monthlyDemand[$monthKey] : 0;
			if (!is_numeric($value)) {
				$hasDataIssue = true;
				$value = 0;
			}
			$value = (float) $value;
			$values[$monthKey] = $value;
			$netDemand += $value;
			$completeMonthCount++;
		}

		if ($hasDataIssue || $netDemand < 0) {
			$status = self::STATUS_CHECK;
		} elseif ($completeMonthCount < 3) {
			$status = self::STATUS_INSUFFICIENT;
		} elseif ($netDemand == 0.0) {
			$status = self::STATUS_NO_SALES;
		} else {
			$status = self::STATUS_SUFFICIENT;
		}

		return array(
			'status' => $status,
			'complete_month_count' => $completeMonthCount,
			'net_demand' => $netDemand,
			'monthly_demand' => $values
		);
	}

	public static function classifyCoverage($usableStock, $averageMonthlyDemand)
	{
		$usableStock = (float) $usableStock;
		$averageMonthlyDemand = (float) $averageMonthlyDemand;
		if ($averageMonthlyDemand < 0 || $usableStock < 0) {
			throw new InvalidArgumentException('Coverage inputs cannot be negative.');
		}
		if ($averageMonthlyDemand == 0.0) {
			return array('months' => null, 'status' => self::STATUS_NO_SALES);
		}

		$months = $usableStock / $averageMonthlyDemand;
		if ($usableStock <= $averageMonthlyDemand) {
			$status = 'Kritis';
		} elseif ($usableStock <= 3 * $averageMonthlyDemand) {
			$status = 'Perhatian';
		} elseif ($usableStock <= 6 * $averageMonthlyDemand) {
			$status = 'Ideal';
		} else {
			$status = 'Overstock';
		}
		return array('months' => $months, 'status' => $status);
	}

	public static function calculateUsableStock(array $batches, $asOfDate)
	{
		$today = self::parseBusinessDate($asOfDate);
		if (!$today) {
			throw new InvalidArgumentException('Stock calculation requires a valid business date.');
		}
		$today = $today->setTime(0, 0, 0);
		$redLimit = self::addCalendarMonths($today, 3)->format('Y-m-d');
		$yellowLimit = self::addCalendarMonths($today, 6)->format('Y-m-d');
		$usable = 0.0;
		$redRisk = 0.0;
		$yellowRisk = 0.0;
		$reservedExcess = 0.0;

		foreach ($batches as $batch) {
			if (!isset($batch['status']) || $batch['status'] !== 'active') {
				continue;
			}
			$remaining = isset($batch['remaining_quantity']) ? (float) $batch['remaining_quantity'] : 0.0;
			$reserved = isset($batch['reserved_quantity']) ? (float) $batch['reserved_quantity'] : 0.0;
			if ($reserved > $remaining) {
				$reservedExcess += $reserved - $remaining;
			}

			$expiry = isset($batch['expiration_date']) ? $batch['expiration_date'] : null;
			if (!is_string($expiry) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry) || $expiry < $today->format('Y-m-d')) {
				continue;
			}

			$available = max(0.0, $remaining - $reserved);
			$usable += $available;
			if ($expiry < $redLimit) {
				$redRisk += $available;
			} elseif ($expiry <= $yellowLimit) {
				$yellowRisk += $available;
			}
		}

		return array(
			'usable_stock' => $usable,
			'red_risk_quantity' => $redRisk,
			'yellow_risk_quantity' => $yellowRisk,
			'reserved_excess' => $reservedExcess
		);
	}

	public static function aggregateQualifiedOrderItems(array $rows)
	{
		$monthly = array();
		$history = array();
		$paidAtIssues = array();
		$baseSnapshotIssues = array();
		$seenItems = array();

		foreach ($rows as $row) {
			if (!isset($row['order_status'])) {
				throw new InvalidArgumentException('Order item is missing its order status.');
			}
			if ($row['order_status'] === 'cancelled') {
				continue;
			}
			if (!array_key_exists('paid_payment_count', $row)) {
				throw new InvalidArgumentException('Order item is missing its paid-payment aggregate.');
			}
			if (!array_key_exists('paid_amount', $row) || !array_key_exists('order_total', $row)) {
				throw new InvalidArgumentException('Order item is missing its paid-total qualification.');
			}
			if ((int) $row['paid_payment_count'] < 1 ||
				!is_numeric($row['paid_amount']) || !is_numeric($row['order_total']) ||
				(float) $row['paid_amount'] < (float) $row['order_total']) {
				continue;
			}
			if (!isset($row['order_item_id'], $row['order_id'], $row['product_id'])) {
				throw new InvalidArgumentException('Qualified order item is missing its identity.');
			}

			$itemId = (string) $row['order_item_id'];
			if (isset($seenItems[$itemId])) {
				continue;
			}
			$seenItems[$itemId] = true;
			$productId = (int) $row['product_id'];
			if (!array_key_exists('missing_paid_at_count', $row)) {
				throw new InvalidArgumentException('Qualified order item is missing its paid-timestamp aggregate.');
			}
			$missingPaidAtCount = (int) $row['missing_paid_at_count'];
			$paidAt = isset($row['first_paid_at']) ? $row['first_paid_at'] : null;
			if ($missingPaidAtCount > 0 || $paidAt === null || $paidAt === '') {
				$paidAtIssues[$productId] = true;
				continue;
			}
			$date = self::parseBusinessDate($paidAt);
			if (!$date) {
				$paidAtIssues[$productId] = true;
				continue;
			}
			if (!isset($row['unit_status_snapshot']) || $row['unit_status_snapshot'] !== 'verified' ||
				!array_key_exists('quantity_base', $row) || $row['quantity_base'] === null ||
				$row['quantity_base'] === '' || !is_numeric($row['quantity_base'])) {
				$baseSnapshotIssues[$productId] = true;
				continue;
			}

			$quantity = (float) $row['quantity_base'];
			$monthKey = $date->format('Y-m');
			if (!isset($monthly[$productId])) {
				$monthly[$productId] = array();
				$history[$productId] = array();
			}
			if (!isset($monthly[$productId][$monthKey])) {
				$monthly[$productId][$monthKey] = 0.0;
			}
			$monthly[$productId][$monthKey] += $quantity;
			$history[$productId][] = array(
				'order_id' => (int) $row['order_id'],
				'month' => $monthKey,
				'quantity' => $quantity
			);
		}

		return array(
			'monthly' => $monthly,
			'history' => $history,
			'paid_at_issues' => $paidAtIssues,
			'base_snapshot_issues' => $baseSnapshotIssues
		);
	}

	public function getDashboardForecast()
	{
		$now = $this->clock->now();
		$monthKeys = self::completedMonthKeys($now, 3);
		$source = $this->loadSourceData();
		$products = array();
		$statusCounts = array(
			self::STATUS_CHECK => 0,
			self::STATUS_INSUFFICIENT => 0,
			self::STATUS_NO_SALES => 0,
			self::STATUS_SUFFICIENT => 0
		);
		$today = $now->format('Y-m-d');

		foreach ($source['products'] as $product) {
			$productId = (int) $product['id'];
			$monthlyDemand = isset($source['demand']['monthly'][$productId])
				? $source['demand']['monthly'][$productId]
				: array();
			$hasDataIssue = isset($source['demand']['paid_at_issues'][$productId]) ||
				isset($source['demand']['base_snapshot_issues'][$productId]);
			$firstReceivedAt = isset($source['first_receipts'][$productId])
				? $source['first_receipts'][$productId]
				: null;
			$history = self::evaluateHistory($firstReceivedAt, $monthKeys, $monthlyDemand, $hasDataIssue);
			$stock = self::calculateUsableStock(
				isset($source['batches'][$productId]) ? $source['batches'][$productId] : array(),
				$today
			);
			$unitVerified = !empty($product['unit_verified']);
			$average = null;
			$forecasts = array(1 => null, 3 => null, 6 => null);
			$coverage = array('months' => null, 'status' => 'Tidak tersedia');

			if ($history['status'] === self::STATUS_NO_SALES) {
				$average = 0.0;
				$forecasts = array(1 => 0.0, 3 => 0.0, 6 => 0.0);
			} elseif ($history['status'] === self::STATUS_SUFFICIENT && $unitVerified) {
				$movingAverage = self::calculateMovingAverage($history['monthly_demand'], $monthKeys);
				$average = $movingAverage['demand'];
				$forecasts = $movingAverage['forecast'];
				$coverage = self::classifyCoverage($stock['usable_stock'], $average);
			}

			$statusCounts[$history['status']]++;
			$products[] = array(
				'id' => $productId,
				'code' => $product['code'],
				'name' => $product['name'],
				'scenario_tag' => $this->scenarioTag($product['description']),
				'base_unit' => $unitVerified ? $product['base_unit'] : null,
				'base_unit_code' => $unitVerified ? $product['base_unit_code'] : null,
				'unit_verified' => $unitVerified,
				'data_status' => $history['status'],
				'monthly_demand' => $history['monthly_demand'],
				'complete_month_count' => $history['complete_month_count'],
				'net_demand' => $history['net_demand'],
				'average_monthly_demand' => $average,
				'forecast' => $forecasts,
				'coverage_months' => $coverage['months'],
				'coverage_status' => $coverage['status'],
				'usable_stock' => $stock['usable_stock'],
				'red_risk_quantity' => $stock['red_risk_quantity'],
				'yellow_risk_quantity' => $stock['yellow_risk_quantity'],
				'reserved_excess' => $stock['reserved_excess']
			);
		}

		return array(
			'method' => 'Moving Average 3 bulan kalender lengkap',
			'period' => array(
				'months' => $monthKeys,
				'start' => $monthKeys[0] . '-01',
				'end' => $now->modify('first day of this month')->modify('-1 day')->format('Y-m-d')
			),
			'status_counts' => $statusCounts,
			'products' => $products
		);
	}

	public function getBacktestReport()
	{
		$now = $this->clock->now();
		$monthKeys = self::completedMonthKeys($now, 12);
		$source = $this->loadSourceData();
		$results = array();
		$aggregateAbsoluteError = 0.0;
		$aggregateActual = 0.0;
		$aggregatePredictions = 0;
		$excludedProducts = 0;

		foreach ($source['products'] as $product) {
			$productId = (int) $product['id'];
			$firstReceivedAt = isset($source['first_receipts'][$productId])
				? self::parseBusinessDate($source['first_receipts'][$productId])
				: null;
			$hasIssue = isset($source['demand']['paid_at_issues'][$productId]) ||
				isset($source['demand']['base_snapshot_issues'][$productId]);
			$monthly = isset($source['demand']['monthly'][$productId])
				? $source['demand']['monthly'][$productId]
				: array();
			if (!$firstReceivedAt || $hasIssue || empty($product['unit_verified']) ||
				(count(array_filter($monthly, function ($quantity) { return (float) $quantity < 0; })) > 0)) {
				$excludedProducts++;
				continue;
			}

			$firstCompleteMonth = $firstReceivedAt->modify('first day of next month')->format('Y-m');
			$errors = array();
			$actualTotal = 0.0;
			$absoluteErrorTotal = 0.0;

			for ($targetIndex = 3; $targetIndex < count($monthKeys); $targetIndex++) {
				$trainingMonths = array_slice($monthKeys, $targetIndex - 3, 3);
				if ($trainingMonths[0] < $firstCompleteMonth ||
					$monthKeys[$targetIndex] < $firstCompleteMonth) {
					continue;
				}

				$movingAverage = self::calculateMovingAverage($monthly, $trainingMonths);
				$actual = isset($monthly[$monthKeys[$targetIndex]]) ? (float) $monthly[$monthKeys[$targetIndex]] : 0.0;
				if ($actual < 0) {
					$errors = array();
					break;
				}
				$error = abs($movingAverage['demand'] - $actual);
				$errors[] = $error;
				$absoluteErrorTotal += $error;
				$actualTotal += $actual;
			}

			if (!$errors) {
				$excludedProducts++;
				continue;
			}
			$aggregateAbsoluteError += $absoluteErrorTotal;
			$aggregateActual += $actualTotal;
			$aggregatePredictions += count($errors);
			$results[] = array(
				'code' => $product['code'],
				'predictions' => count($errors),
				'mae' => $absoluteErrorTotal / count($errors),
				'wape' => $actualTotal > 0 ? $absoluteErrorTotal / $actualTotal : null,
				'actual_total' => $actualTotal
			);
		}

		usort($results, function ($left, $right) {
			return strcmp($left['code'], $right['code']);
		});
		return array(
			'method' => 'Rolling-origin: 3 bulan latih, 1 bulan prediksi',
			'months' => $monthKeys,
			'eligible_products' => count($results),
			'excluded_products' => $excludedProducts,
			'predictions' => $aggregatePredictions,
			'mae' => $aggregatePredictions > 0 ? $aggregateAbsoluteError / $aggregatePredictions : null,
			'wape' => $aggregateActual > 0 ? $aggregateAbsoluteError / $aggregateActual : null,
			'actual_total' => $aggregateActual,
			'absolute_error_total' => $aggregateAbsoluteError,
			'products' => $results
		);
	}

	private function loadSourceData()
	{
		$products = $this->queryRows(
			'SELECT p.id, p.code, p.name, p.description, p.unit_id, ' .
			'COALESCE(v.base_unit_id, p.unit_id) AS base_unit_id, ' .
			'u.name AS base_unit, u.code AS base_unit_code, ' .
			'CASE WHEN v.product_id IS NOT NULL AND v.status = "verified" THEN 1 ELSE 0 END AS unit_verified ' .
			'FROM products p ' .
			'LEFT JOIN product_unit_verifications v ON v.product_id = p.id ' .
			'LEFT JOIN units u ON u.id = COALESCE(v.base_unit_id, p.unit_id) ' .
			'WHERE p.deleted_at IS NULL AND p.is_active = 1 ' .
			'ORDER BY p.code'
		);
		$orderItems = $this->queryRows(
			'SELECT oi.id AS order_item_id, oi.order_id, oi.product_id, o.status AS order_status, o.total AS order_total, ' .
			'paid.paid_payment_count, paid.paid_amount, paid.first_paid_at, paid.missing_paid_at_count, ' .
			'oi.quantity, oi.quantity_base, oi.unit_status_snapshot ' .
			'FROM order_items oi ' .
			'JOIN orders o ON o.id = oi.order_id ' .
			'LEFT JOIN (' .
			'SELECT order_id, COUNT(*) AS paid_payment_count, SUM(amount) AS paid_amount, MIN(paid_at) AS first_paid_at, ' .
			'SUM(CASE WHEN paid_at IS NULL THEN 1 ELSE 0 END) AS missing_paid_at_count ' .
			'FROM payments WHERE status = "paid" GROUP BY order_id' .
			') paid ON paid.order_id = o.id ' .
			'WHERE o.status <> "cancelled" ' .
			'AND paid.paid_amount >= o.total ' .
			'AND EXISTS (SELECT 1 FROM payments qualifying_payment ' .
			'WHERE qualifying_payment.order_id = o.id AND qualifying_payment.status = "paid") ' .
			'ORDER BY o.id, oi.id'
		);
		$receiptRows = $this->queryRows(
			'SELECT batch_receipts.product_id, batch_receipts.first_received_at ' .
			'FROM (' .
			'SELECT product_id, MIN(received_at) AS first_received_at ' .
			'FROM stock_batches WHERE received_at IS NOT NULL GROUP BY product_id' .
			') batch_receipts ' .
			'UNION ALL ' .
			'SELECT movement_receipts.product_id, movement_receipts.first_received_at ' .
			'FROM (' .
			'SELECT product_id, MIN(created_at) AS first_received_at ' .
			'FROM stock_movements WHERE movement_type = "purchase_receive" GROUP BY product_id' .
			') movement_receipts ' .
			'WHERE NOT EXISTS (' .
			'SELECT 1 FROM stock_batches b WHERE b.product_id = movement_receipts.product_id AND b.received_at IS NOT NULL' .
			')'
		);
		$batchRows = $this->queryRows(
			'SELECT product_id, remaining_quantity, reserved_quantity, expiration_date, status ' .
			'FROM stock_batches WHERE status = "active" ORDER BY product_id, id'
		);

		$firstReceipts = array();
		foreach ($receiptRows as $row) {
			$firstReceipts[(int) $row['product_id']] = $row['first_received_at'];
		}
		$batches = array();
		foreach ($batchRows as $row) {
			$productId = (int) $row['product_id'];
			if (!isset($batches[$productId])) {
				$batches[$productId] = array();
			}
			$batches[$productId][] = $row;
		}

		return array(
			'products' => $products,
			'demand' => self::aggregateQualifiedOrderItems($orderItems),
			'first_receipts' => $firstReceipts,
			'batches' => $batches
		);
	}

	private function queryRows($sql)
	{
		$result = $this->db->query($sql);
		if ($result === false) {
			throw new RuntimeException('Forecast data query failed.');
		}
		if ($result instanceof mysqli_result) {
			$rows = $result->fetch_all(MYSQLI_ASSOC);
			$result->free();
			return $rows;
		}
		if (is_object($result) && method_exists($result, 'result_array')) {
			return $result->result_array();
		}
		throw new RuntimeException('Forecast database result is unsupported.');
	}

	private function scenarioTag($description)
	{
		return is_string($description) && preg_match('/\[B3:([a-z0-9_]+)\]/i', $description, $match)
			? strtolower($match[1])
			: null;
	}

	private static function parseBusinessDate($value)
	{
		if ($value instanceof DateTimeInterface) {
			return (new DateTimeImmutable($value->format('Y-m-d H:i:s.uP')))
				->setTimezone(new DateTimeZone('Asia/Jakarta'));
		}
		if (!is_string($value) || trim($value) === '') {
			return null;
		}
		try {
			return (new DateTimeImmutable($value, new DateTimeZone('Asia/Jakarta')))
				->setTimezone(new DateTimeZone('Asia/Jakarta'));
		} catch (Exception $exception) {
			return null;
		}
	}

	private static function addCalendarMonths(DateTimeImmutable $date, $months)
	{
		$year = (int) $date->format('Y');
		$month = (int) $date->format('n') + (int) $months;
		$year += intdiv($month - 1, 12);
		$month = (($month - 1) % 12) + 1;
		$firstOfMonth = $date->setDate($year, $month, 1);
		$lastDay = (int) $firstOfMonth->modify('last day of this month')->format('j');
		$day = min((int) $date->format('j'), $lastDay);
		return $firstOfMonth->setDate($year, $month, $day)->setTime(0, 0, 0);
	}
}
