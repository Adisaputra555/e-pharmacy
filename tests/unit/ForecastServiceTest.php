<?php
if (!defined('BASEPATH')) {
	define('BASEPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Forecast_service.php';

class ForecastServiceTest extends PHPUnit\Framework\TestCase
{
	private function paidItem($itemId, $orderId, $productId, $month, $quantity)
	{
		return array(
			'order_item_id' => $itemId,
			'order_id' => $orderId,
			'product_id' => $productId,
			'order_status' => 'processing',
			'paid_payment_count' => 2,
			'paid_amount' => 20,
			'order_total' => 20,
			'first_paid_at' => $month,
			'missing_paid_at_count' => 0,
			'quantity_base' => $quantity,
			'unit_status_snapshot' => 'verified'
		);
	}

	public function testMovingAverageAndForecastHorizonsUseThreeCompleteMonths()
	{
		$result = Forecast_service::calculateMovingAverage(
			array('2026-08' => 2, '2026-09' => 4, '2026-10' => 6),
			array('2026-08', '2026-09', '2026-10')
		);

		$this->assertSame(4.0, $result['demand']);
		$this->assertSame(4.0, $result['forecast'][1]);
		$this->assertSame(12.0, $result['forecast'][3]);
		$this->assertSame(24.0, $result['forecast'][6]);
	}

	public function testCompletedMonthWindowChangesAtJakartaMidnightAndExcludesCurrentMonth()
	{
		$timezone = new DateTimeZone('Asia/Jakarta');
		$beforeBoundary = new DateTimeImmutable('2026-10-31 23:59:00', $timezone);
		$afterBoundary = new DateTimeImmutable('2026-11-01 00:00:00', $timezone);

		$this->assertSame(
			array('2026-07', '2026-08', '2026-09'),
			Forecast_service::completedMonthKeys($beforeBoundary)
		);
		$this->assertSame(
			array('2026-08', '2026-09', '2026-10'),
			Forecast_service::completedMonthKeys($afterBoundary)
		);
	}

	public function testQualifiedDemandDeduplicatesPaymentJoinAndExcludesCancelledOrUnpaid()
	{
		$paid = $this->paidItem(11, 5, 2, '2026-08-31 23:59:59', 3);
		$duplicatePaymentJoin = $paid;
		$cancelled = $this->paidItem(12, 6, 2, '2026-09-01 00:00:00', 20);
		$cancelled['order_status'] = 'cancelled';
		$pending = $this->paidItem(13, 7, 2, '2026-09-01 00:00:00', 30);
		$pending['paid_payment_count'] = 0;
		$pending['paid_amount'] = 0;
		$nextMonth = $this->paidItem(14, 8, 2, '2026-09-01 00:00:00', 4);
		$partialPayment = $this->paidItem(15, 9, 2, '2026-09-01 00:00:00', 50);
		$partialPayment['paid_payment_count'] = 1;
		$partialPayment['paid_amount'] = 10;

		$result = Forecast_service::aggregateQualifiedOrderItems(array(
			$paid,
			$duplicatePaymentJoin,
			$cancelled,
			$pending,
			$nextMonth,
			$partialPayment
		));

		$this->assertSame(3.0, $result['monthly'][2]['2026-08']);
		$this->assertSame(4.0, $result['monthly'][2]['2026-09']);
		$this->assertCount(2, $result['history'][2]);
		$this->assertArrayNotHasKey(2, $result['paid_at_issues']);
	}

	public function testPaidWithoutTimestampIsMarkedAndNotAssignedToCreatedMonth()
	{
		$paid = $this->paidItem(21, 9, 3, '2026-08-10 10:00:00', 5);
		$paid['first_paid_at'] = null;
		$paid['missing_paid_at_count'] = 1;
		$paidWithIncompletePayments = $this->paidItem(22, 10, 3, '2026-09-10 10:00:00', 10);
		$paidWithIncompletePayments['missing_paid_at_count'] = 1;

		$result = Forecast_service::aggregateQualifiedOrderItems(array($paid, $paidWithIncompletePayments));
		$history = Forecast_service::evaluateHistory(
			'2026-01-15 09:00:00',
			array('2026-08', '2026-09', '2026-10'),
			isset($result['monthly'][3]) ? $result['monthly'][3] : array(),
			isset($result['paid_at_issues'][3])
		);

		$this->assertArrayHasKey(3, $result['paid_at_issues']);
		$this->assertArrayNotHasKey(3, $result['monthly']);
		$this->assertSame(Forecast_service::STATUS_CHECK, $history['status']);
	}

	public function testBusinessMonthBoundaryUsesPaidAtMonth()
	{
		$first = $this->paidItem(31, 10, 4, '2026-08-31 23:59:59', 1);
		$second = $this->paidItem(32, 11, 4, '2026-09-01 00:00:00', 1);
		$result = Forecast_service::aggregateQualifiedOrderItems(array($first, $second));

		$this->assertSame(1.0, $result['monthly'][4]['2026-08']);
		$this->assertSame(1.0, $result['monthly'][4]['2026-09']);
	}

	public function testHistoryStatusesPrioritizeDataIssuesAndRequireThreeFullTradingMonths()
	{
		$months = array('2026-08', '2026-09', '2026-10');
		$this->assertSame(
			Forecast_service::STATUS_CHECK,
			Forecast_service::evaluateHistory(null, $months, array())['status']
		);
		$this->assertSame(
			Forecast_service::STATUS_INSUFFICIENT,
			Forecast_service::evaluateHistory('2026-09-25 09:00:00', $months, array())['status']
		);
		$this->assertSame(
			Forecast_service::STATUS_NO_SALES,
			Forecast_service::evaluateHistory('2026-07-31 23:59:00', $months, array())['status']
		);
		$this->assertSame(
			Forecast_service::STATUS_CHECK,
			Forecast_service::evaluateHistory(
				'2026-07-31 23:59:00',
				$months,
				array('2026-08' => 0, '2026-09' => -2, '2026-10' => 1)
			)['status']
		);
		$this->assertSame(
			Forecast_service::STATUS_CHECK,
			Forecast_service::evaluateHistory('2026-09-25 09:00:00', $months, array(), true)['status']
		);
	}

	/**
	 * @dataProvider coverageCases
	 */
	public function testCoverageClassifiesExactBoundaries($stock, $demand, $status)
	{
		$this->assertSame($status, Forecast_service::classifyCoverage($stock, $demand)['status']);
	}

	public function coverageCases()
	{
		return array(
			'one month critical boundary' => array(1, 1, 'Kritis'),
			'between one and three' => array(1.001, 1, 'Perhatian'),
			'three month attention boundary' => array(3, 1, 'Perhatian'),
			'between three and six' => array(3.001, 1, 'Ideal'),
			'six month ideal boundary' => array(6, 1, 'Ideal'),
			'above six month overstock' => array(6.001, 1, 'Overstock')
		);
	}

	public function testZeroDemandDoesNotDivideAndReportsNoSales()
	{
		$result = Forecast_service::classifyCoverage(100, 0);

		$this->assertNull($result['months']);
		$this->assertSame(Forecast_service::STATUS_NO_SALES, $result['status']);
	}

	public function testUsableStockExcludesExpiredBatchesAndReportsExpiryAndReservationWarnings()
	{
		$result = Forecast_service::calculateUsableStock(array(
			array('status' => 'active', 'expiration_date' => '2026-11-21', 'remaining_quantity' => 100, 'reserved_quantity' => 0),
			array('status' => 'active', 'expiration_date' => '2026-11-22', 'remaining_quantity' => 10, 'reserved_quantity' => 2),
			array('status' => 'active', 'expiration_date' => '2027-02-21', 'remaining_quantity' => 5, 'reserved_quantity' => 0),
			array('status' => 'active', 'expiration_date' => '2027-02-22', 'remaining_quantity' => 4, 'reserved_quantity' => 0),
			array('status' => 'active', 'expiration_date' => '2027-05-22', 'remaining_quantity' => 6, 'reserved_quantity' => 0),
			array('status' => 'active', 'expiration_date' => '2027-05-23', 'remaining_quantity' => 7, 'reserved_quantity' => 0),
			array('status' => 'active', 'expiration_date' => '2026-12-01', 'remaining_quantity' => 3, 'reserved_quantity' => 5),
			array('status' => 'depleted', 'expiration_date' => '2026-12-01', 'remaining_quantity' => 50, 'reserved_quantity' => 0)
		), '2026-11-22');

		$this->assertSame(30.0, $result['usable_stock']);
		$this->assertSame(13.0, $result['red_risk_quantity']);
		$this->assertSame(10.0, $result['yellow_risk_quantity']);
		$this->assertSame(2.0, $result['reserved_excess']);
	}

	public function testFeatureFlagDefaultsOffAndIsDemoOnly()
	{
		$this->assertFalse(Forecast_service::isEnabled('demo', false));
		$this->assertFalse(Forecast_service::isEnabled('demo', null));
		$this->assertFalse(Forecast_service::isEnabled('production', true));
		$this->assertFalse(Forecast_service::isEnabled('testing', true));
		$this->assertTrue(Forecast_service::isEnabled('demo', true));
	}

	public function testMissingLocalFlagDefaultsOffAndNonDemoDoesNotLoadIt()
	{
		$missingPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'missing-forecast-flag-' . bin2hex(random_bytes(6)) . '.php';
		$this->assertFalse(Forecast_service::dashboardFlagEnabled('demo', $missingPath));

		$path = tempnam(sys_get_temp_dir(), 'forecast-flag-test-');
		$this->assertNotFalse($path);
		try {
			$this->assertNotFalse(file_put_contents($path, '<?php return array("forecast_dashboard_enabled" => true);'));
			$this->assertTrue(Forecast_service::dashboardFlagEnabled('demo', $path));
			$this->assertFalse(Forecast_service::dashboardFlagEnabled('production', $path));
		} finally {
			unlink($path);
		}
	}
}
