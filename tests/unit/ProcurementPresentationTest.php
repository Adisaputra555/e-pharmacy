<?php
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Forecast_service.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Procurement_service.php';

if (!function_exists('html_escape')) {
	function html_escape($value)
	{
		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}
}

class ProcurementPresentationTest extends PHPUnit\Framework\TestCase
{
	private function makeForecastService()
	{
		$businessDate = new DateTimeImmutable('2026-11-22 12:00:00', new DateTimeZone('Asia/Jakarta'));
		$clock = new Business_clock(function () use ($businessDate) {
			return $businessDate;
		});
		return new Forecast_service($GLOBALS['b1_demo_app_db'], $clock);
	}

	private function recommendations()
	{
		return (new Procurement_service(
			$GLOBALS['b1_demo_app_db'],
			$this->makeForecastService(),
			array('global_target_months' => '1')
		))->getRecommendations();
	}

	private function renderPage($showScenarioDebug)
	{
		$data = $this->recommendations();
		$data['show_scenario_debug'] = $showScenarioDebug;
		extract($data, EXTR_SKIP);
		ob_start();
		include dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
			'views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'procurement' .
			DIRECTORY_SEPARATOR . 'index.php';
		return ob_get_clean();
	}

	private function htmlRowForProduct($html, $productCode)
	{
		$position = strpos($html, $productCode);
		if ($position === false) {
			return '';
		}
		$before = substr($html, 0, $position);
		$rowStart = strrpos($before, '<tr>');
		$rowEnd = strpos($html, '</tr>', $position);
		return $rowStart === false || $rowEnd === false
			? ''
			: preg_replace('/\s+/', ' ', substr($html, $rowStart, $rowEnd - $rowStart));
	}

	public function testNumberAndCompletedPeriodDisplayUseIndonesianFormatting()
	{
		$this->assertSame('100', Procurement_service::formatDisplayNumber('100.000'));
		$this->assertSame('85,67', Procurement_service::formatDisplayNumber('257/3'));
		$this->assertSame('0,47', Procurement_service::formatDisplayNumber('7/15'));
		$this->assertSame('3,0', Procurement_service::formatDisplayNumber('11701/3855', 1, 1));
		$this->assertSame(
			'Agu–Okt 2026',
			Procurement_service::formatPeriodLabel(array(
				'months' => array('2026-08', '2026-09', '2026-10'),
				'start' => '2026-08-01',
				'end' => '2026-10-31'
			))
		);
	}

	public function testPageFormatsNestedPeriodWithoutArrayCastOrLongZeroDecimals()
	{
		$html = $this->renderPage(false);

		$this->assertStringContainsString('Agu–Okt 2026', $html);
		$this->assertStringNotContainsString('Array', $html);
		$this->assertStringNotContainsString('100.000', $html);
	}

	public function testScenarioTagsAreOnlyVisibleInDemoDebugMode()
	{
		$normalHtml = $this->renderPage(false);
		$debugHtml = $this->renderPage(true);

		$this->assertStringNotContainsString('seasonal_demand', $normalHtml);
		$this->assertStringContainsString('skenario demo · seasonal_demand', $debugHtml);
	}

	public function testBlockedRowsKeepConfiguredInputsAndHideCalculationOutputs()
	{
		$rows = array();
		foreach ($this->recommendations()['recommendations'] as $row) {
			if ($row['scenario_tag'] === 'factor_missing') {
				$rows[] = $row;
			}
		}

		$this->assertCount(1, $rows);
		$this->assertSame(Procurement_service::STATUS_BLOCKED, $rows[0]['status']);
		$this->assertSame('1', (string) $rows[0]['target_months']);
		$this->assertSame(14, (int) $rows[0]['lead_time_days']);
		$this->assertSame('5', Procurement_service::formatDisplayNumber($rows[0]['safety_stock_display']));
		$this->assertNull($rows[0]['inventory_position_base']);
		$this->assertNull($rows[0]['q0']);
		$this->assertNull($rows[0]['q_final_base']);

		$rowHtml = $this->htmlRowForProduct($this->renderPage(false), $rows[0]['product_code']);
		$this->assertStringContainsString('P: 1 bln', $rowHtml);
		$this->assertStringContainsString('L: 0,47 bln (14 hari; manual)', $rowHtml);
		$this->assertStringContainsString('SS base: 5', $rowHtml);
		$this->assertStringContainsString('IP: —', $rowHtml);
		$this->assertStringContainsString('Q0: —', $rowHtml);
		$this->assertStringContainsString('Q final: —', $rowHtml);
	}

	public function testSlowMoverIsNotContaminatedAndInconsistentPaymentHasAnExplicitReason()
	{
		$forecast = $this->makeForecastService()->getDashboardForecast();
		$slowMover = null;
		foreach ($forecast['products'] as $product) {
			if ($product['code'] === 'B3-PRD-002') {
				$slowMover = $product;
				break;
			}
		}

		$this->assertNotNull($slowMover);
		$this->assertSame(Forecast_service::STATUS_SUFFICIENT, $slowMover['data_status']);
		$this->assertSame(array(6.0, 6.0, 6.0), array_values($slowMover['monthly_demand']));

		$inconsistent = array();
		foreach ($this->recommendations()['recommendations'] as $row) {
			if ($row['scenario_tag'] === 'inconsistent_payment') {
				$inconsistent[] = $row;
			}
		}
		$this->assertCount(1, $inconsistent);
		$this->assertSame(Forecast_service::STATUS_CHECK, $inconsistent[0]['history_status']);
		$this->assertSame('PAID_AT_MISSING', $inconsistent[0]['history_reason_code']);
		$this->assertNotSame('', $inconsistent[0]['history_reason']);
		$this->assertStringContainsString(
			'PAID_AT_MISSING:',
			$this->renderPage(false)
		);
	}

	public function testForecastStatusDistributionCoversAllTwentyFiveDemoProducts()
	{
		$counts = array(
			Forecast_service::STATUS_CHECK => 0,
			Forecast_service::STATUS_INSUFFICIENT => 0,
			Forecast_service::STATUS_NO_SALES => 0,
			Forecast_service::STATUS_SUFFICIENT => 0
		);
		$products = $this->makeForecastService()->getDashboardForecast()['products'];

		$this->assertCount(25, $products);
		foreach ($products as $product) {
			$counts[$product['data_status']]++;
		}
		$this->assertSame(array(
			Forecast_service::STATUS_CHECK => 3,
			Forecast_service::STATUS_INSUFFICIENT => 1,
			Forecast_service::STATUS_NO_SALES => 4,
			Forecast_service::STATUS_SUFFICIENT => 17
		), $counts);
	}
}
