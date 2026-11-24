<?php
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Forecast_service.php';

class ForecastServiceDemoTest extends PHPUnit\Framework\TestCase
{
	private function makeService()
	{
		$timezone = new DateTimeZone('Asia/Jakarta');
		$clock = new Business_clock(function () use ($timezone) {
			return new DateTimeImmutable('2026-11-22 11:20:00', $timezone);
		});
		return new Forecast_service($GLOBALS['b1_demo_app_db'], $clock);
	}

	public function testAllThirteenDemoScenariosFollowForecastStatusPriority()
	{
		$result = $this->makeService()->getDashboardForecast();
		$expected = array(
			'fast_mover_stable' => Forecast_service::STATUS_SUFFICIENT,
			'slow_mover' => Forecast_service::STATUS_SUFFICIENT,
			'no_sales_3_months' => Forecast_service::STATUS_NO_SALES,
			'seasonal' => Forecast_service::STATUS_SUFFICIENT,
			'new_less_3_months' => Forecast_service::STATUS_INSUFFICIENT,
			'overstock' => Forecast_service::STATUS_NO_SALES,
			'critical_stock' => Forecast_service::STATUS_SUFFICIENT,
			'near_expiry_red' => Forecast_service::STATUS_SUFFICIENT,
			'near_expiry_yellow' => Forecast_service::STATUS_SUFFICIENT,
			'multi_batch_fefo' => Forecast_service::STATUS_NO_SALES,
			'zero_stock' => Forecast_service::STATUS_NO_SALES,
			'legacy_one' => Forecast_service::STATUS_CHECK,
			'legacy_two' => Forecast_service::STATUS_CHECK
		);
		$actual = array();
		$byTag = array();
		foreach ($result['products'] as $product) {
			if ($product['scenario_tag'] !== null && array_key_exists($product['scenario_tag'], $expected)) {
				$actual[$product['scenario_tag']] = $product['data_status'];
				$byTag[$product['scenario_tag']] = $product;
			}
		}

		$this->assertCount(13, $actual);
		$this->assertSame($expected, $actual);
		$this->assertSame('Kritis', $byTag['critical_stock']['coverage_status']);
		$this->assertSame(0.0, $byTag['zero_stock']['usable_stock']);
		$this->assertFalse($byTag['legacy_one']['unit_verified']);
		$this->assertSame(Forecast_service::STATUS_CHECK, $byTag['legacy_one']['data_status']);
	}

	public function testDemoRollingOriginBacktestReportsUndefinedWapeForZeroActual()
	{
		$result = $this->makeService()->getBacktestReport();

		$this->assertGreaterThan(0, $result['eligible_products']);
		$this->assertGreaterThan(0, $result['predictions']);
		$this->assertNotNull($result['wape']);
		$zeroActualProducts = array_filter($result['products'], function ($product) {
			return $product['actual_total'] == 0.0;
		});
		$this->assertNotEmpty($zeroActualProducts);
		foreach ($zeroActualProducts as $product) {
			$this->assertNull($product['wape']);
		}
	}
}
