<?php
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Forecast_service.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Procurement_service.php';

class ProcurementDemoScenarioTest extends PHPUnit\Framework\TestCase
{
	private function recommendations()
	{
		$businessDate = new DateTimeImmutable('2026-11-22 12:00:00', new DateTimeZone('Asia/Jakarta'));
		$clock = new Business_clock(function () use ($businessDate) {
			return $businessDate;
		});
		$forecast = new Forecast_service(array(
			'db' => $GLOBALS['b1_demo_app_db'],
			'clock' => $clock
		));
		$service = new Procurement_service(
			$GLOBALS['b1_demo_app_db'],
			$forecast,
			array('global_target_months' => '1')
		);
		return $service->getRecommendations();
	}

	private function rowsByScenario(array $recommendations)
	{
		$rows = array();
		foreach ($recommendations['recommendations'] as $row) {
			if ($row['scenario_tag'] !== null) {
				$rows[$row['scenario_tag']][] = $row;
			}
		}
		return $rows;
	}

	public function testAllRequiredProcurementDemoScenarioFixturesExist()
	{
		$rows = $this->rowsByScenario($this->recommendations());
		$expected = array(
			'normal',
			'seasonal_demand',
			'multi_supplier_moq_multiple',
			'ordered_po_warning',
			'manual_lead_time_missing',
			'factor_missing',
			'moq_none_explicit',
			'normal_multi_batch',
			'demand_zero_review',
			'stock_exceeds_target',
			'expiry_red_warning',
			'expiry_yellow_warning',
			'unit_unverified'
		);
		foreach ($expected as $scenario) {
			$this->assertArrayHasKey($scenario, $rows, 'Missing B5 scenario fixture: ' . $scenario);
		}
	}

	public function testBlockedReviewAndNoPurchaseScenariosProduceExpectedStates()
	{
		$rows = $this->rowsByScenario($this->recommendations());

		$this->assertSame(
			Procurement_service::STATUS_BLOCKED,
			$rows['manual_lead_time_missing'][0]['status']
		);
		$this->assertSame(
			Procurement_service::STATUS_BLOCKED,
			$rows['factor_missing'][0]['status']
		);
		$this->assertSame(
			Procurement_service::STATUS_BLOCKED,
			$rows['unit_unverified'][0]['status']
		);
		$this->assertSame(
			Procurement_service::STATUS_MANUAL_REVIEW,
			$rows['demand_zero_review'][0]['status'],
			json_encode(array_intersect_key(
				$rows['demand_zero_review'][0],
				array_flip(array('history_status', 'demand', 'reason', 'status'))
			))
		);
		$this->assertNull($rows['demand_zero_review'][0]['q0']);
		$this->assertSame(
			Forecast_service::STATUS_NO_SALES,
			$rows['demand_zero_review'][0]['coverage_status']
		);
		$this->assertSame(
			Procurement_service::STATUS_NO_PURCHASE,
			$rows['stock_exceeds_target'][0]['status']
		);
		$this->assertSame(
			Forecast_service::STATUS_SUFFICIENT,
			$rows['stock_exceeds_target'][0]['history_status']
		);
		$this->assertGreaterThan(0, (float) $rows['stock_exceeds_target'][0]['demand']);
		$this->assertSame('0', $rows['stock_exceeds_target'][0]['q_final_base']);
	}

	public function testPreferredSupplierMoqAndOpenPoWarningDoNotChangeEligibleInventory()
	{
		$rows = $this->rowsByScenario($this->recommendations());
		$multiSupplier = $rows['multi_supplier_moq_multiple'];
		$this->assertCount(2, $multiSupplier);
		$this->assertSame(1, count(array_filter($multiSupplier, function ($row) {
			return $row['is_preferred'];
		})));
		$this->assertSame('set', $multiSupplier[0]['moq_mode']);
		$this->assertSame('2', $multiSupplier[0]['moq_purchase_units']);

		$openPo = $rows['ordered_po_warning'][0];
		$this->assertGreaterThan(0, $openPo['open_po_review_count']);
		$this->assertGreaterThan(0, (float) $openPo['open_po_review_qty']);
		$this->assertSame('0', $openPo['eligible_open_po_base']);
	}

	public function testExpiryRiskIsReportedSeparatelyFromCoverageAndSupplierDemand()
	{
		$rows = $this->rowsByScenario($this->recommendations());

		$this->assertGreaterThan(0, (float) $rows['expiry_red_warning'][0]['red_risk_quantity']);
		$this->assertGreaterThan(0, (float) $rows['expiry_yellow_warning'][0]['yellow_risk_quantity']);
		$this->assertTrue($rows['expiry_red_warning'][0]['unit_verified']);
		$this->assertTrue($rows['expiry_yellow_warning'][0]['unit_verified']);
	}
}
