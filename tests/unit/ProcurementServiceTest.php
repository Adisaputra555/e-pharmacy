<?php
if (!defined('BASEPATH')) {
	define('BASEPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Forecast_service.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Procurement_service.php';

class ProcurementServiceTest extends PHPUnit\Framework\TestCase
{
	private function input(array $changes = array())
	{
		return array_merge(array(
			'history_status' => Forecast_service::STATUS_SUFFICIENT,
			'demand_total_base' => '36.000',
			'period_months' => array('2026-08', '2026-09', '2026-10'),
			'usable_stock_base' => '8.000',
			'eligible_open_po_base' => '0.000',
			'global_target_months' => '1',
			'manual_lead_time_days' => 30,
			'minimum_stock_unit_verified' => true,
			'minimum_stock_quantity' => '0.000',
			'minimum_stock_factor' => '1.000000000',
			'minimum_stock_input_scale' => 3,
			'base_quantity_scale' => 3,
			'base_per_purchase_unit' => '1.000000000',
			'moq_mode' => 'none',
			'moq_purchase_units' => null,
			'order_multiple_purchase_units' => '1'
		), $changes);
	}

	public function testApprovedVectorAProducesSixteenBaseUnitsAndCriticalCoverage()
	{
		$result = Procurement_service::calculate($this->input());

		$this->assertSame(Procurement_service::STATUS_RECOMMENDED, $result['status']);
		$this->assertSame('12', $result['demand']);
		$this->assertSame('16', $result['q0']);
		$this->assertSame('16', $result['q_final_base']);
		$this->assertSame('1', $result['coverage_after_arrival_display']);
		$this->assertTrue($result['target_met']);
	}

	public function testApprovedVectorBHonorsMoqAndOrderMultiple()
	{
		$result = Procurement_service::calculate($this->input(array(
			'demand_total_base' => '30.000',
			'usable_stock_base' => '4.000',
			'manual_lead_time_days' => 15,
			'minimum_stock_quantity' => '2.000',
			'base_per_purchase_unit' => '10.000000000',
			'moq_mode' => 'set',
			'moq_purchase_units' => '2.000',
			'order_multiple_purchase_units' => '2.000'
		)));

		$this->assertSame(Procurement_service::STATUS_RECOMMENDED, $result['status']);
		$this->assertSame('13', $result['q0']);
		$this->assertSame('1.3', $result['raw_purchase_quantity']);
		$this->assertSame('2', $result['supplier_quantity']);
		$this->assertSame('20', $result['q_final_base']);
		$this->assertSame('1.9', $result['coverage_after_arrival_display']);
	}

	public function testExplicitMoqNoneDefaultsOnlyTheMissingMultipleAndCeilsToIt()
	{
		$result = Procurement_service::calculate($this->input(array(
			'order_multiple_purchase_units' => null
		)));

		$this->assertSame(Procurement_service::STATUS_RECOMMENDED, $result['status']);
		$this->assertTrue($result['order_multiple_defaulted']);
		$this->assertSame('1', $result['order_multiple_purchase_units']);
		$this->assertSame('16', $result['supplier_quantity']);
	}

	public function testCeilingRoundsSupplierQuantityUpToWholeMultiple()
	{
		$result = Procurement_service::calculate($this->input(array(
			'demand_total_base' => '36.000',
			'usable_stock_base' => '0.000',
			'manual_lead_time_days' => 0,
			'base_per_purchase_unit' => '3.000000000',
			'order_multiple_purchase_units' => '3.000'
		)));

		$this->assertSame('12', $result['q0']);
		$this->assertSame('4', $result['raw_purchase_quantity']);
		$this->assertSame('6', $result['supplier_quantity']);
		$this->assertSame('18', $result['q_final_base']);
	}

	public function testZeroDemandRequiresManualReviewAndHasNoOrderQuantity()
	{
		$result = Procurement_service::calculate($this->input(array(
			'history_status' => Forecast_service::STATUS_NO_SALES,
			'demand_total_base' => '0.000'
		)));

		$this->assertSame(Procurement_service::STATUS_MANUAL_REVIEW, $result['status']);
		$this->assertNull($result['q0']);
		$this->assertNull($result['q_final_base']);
	}

	public function testZeroQ0DoesNotTriggerMoqOrRequireSupplierConversion()
	{
		$result = Procurement_service::calculate($this->input(array(
			'usable_stock_base' => '20.000',
			'manual_lead_time_days' => 0,
			'base_per_purchase_unit' => null,
			'moq_mode' => 'invalid',
			'moq_purchase_units' => null,
			'order_multiple_purchase_units' => null
		)));

		$this->assertSame(Procurement_service::STATUS_NO_PURCHASE, $result['status']);
		$this->assertSame('0', $result['q0']);
		$this->assertNull($result['supplier_quantity']);
	}

	/**
	 * @dataProvider invalidTargets
	 */
	public function testInvalidGlobalTargetBlocksWithoutSuccessShapedZero($target)
	{
		$result = Procurement_service::calculate($this->input(array(
			'global_target_months' => $target
		)));

		$this->assertSame(Procurement_service::STATUS_BLOCKED, $result['status']);
		$this->assertStringContainsString('Target P', $result['reason']);
		$this->assertNull($result['q0']);
	}

	public function invalidTargets()
	{
		return array(
			'missing' => array(null),
			'zero' => array('0'),
			'above approved range' => array('24.001'),
			'not numeric' => array('invalid')
		);
	}

	public function testApprovedMaximumGlobalTargetIsTwentyFourMonths()
	{
		$result = Procurement_service::calculate($this->input(array(
			'global_target_months' => '24'
		)));

		$this->assertSame(Procurement_service::STATUS_RECOMMENDED, $result['status']);
		$this->assertSame('24', $result['target_months']);
	}

	public function testMissingManualLeadTimeBlocksWithoutQ0()
	{
		$result = Procurement_service::calculate($this->input(array(
			'manual_lead_time_days' => null
		)));

		$this->assertSame(Procurement_service::STATUS_BLOCKED, $result['status']);
		$this->assertStringContainsString('Lead time manual', $result['reason']);
		$this->assertNull($result['q0']);
	}

	public function testUnverifiedUnitsBlockSafetyStockConversion()
	{
		$result = Procurement_service::calculate($this->input(array(
			'minimum_stock_unit_verified' => false
		)));

		$this->assertSame(Procurement_service::STATUS_BLOCKED, $result['status']);
		$this->assertStringContainsString('Safety stock', $result['reason']);
		$this->assertNull($result['q0']);
	}

	/**
	 * @dataProvider invalidFactors
	 */
	public function testInvalidSupplierFactorBlocksPositiveQ0($factor)
	{
		$result = Procurement_service::calculate($this->input(array(
			'base_per_purchase_unit' => $factor
		)));

		$this->assertSame(Procurement_service::STATUS_BLOCKED, $result['status']);
		$this->assertStringContainsString('F (base_per_purchase_unit)', $result['reason']);
		$this->assertSame('16', $result['q0']);
		$this->assertNull($result['q_final_base']);
	}

	public function invalidFactors()
	{
		return array(
			'missing' => array(null),
			'zero' => array('0.000000000'),
			'negative' => array('-1'),
			'too many decimals' => array('1.0000000001')
		);
	}

	public function testMoqSetRequiresPositiveMoqAndMultiple()
	{
		$result = Procurement_service::calculate($this->input(array(
			'moq_mode' => 'set',
			'moq_purchase_units' => null
		)));

		$this->assertSame(Procurement_service::STATUS_BLOCKED, $result['status']);
		$this->assertStringContainsString('MOQ mode set', $result['reason']);
		$this->assertNull($result['q_final_base']);
	}

	public function testNonRepresentableBaseQuantityIsBlockedWithoutRounding()
	{
		$result = Procurement_service::calculate($this->input(array(
			'demand_total_base' => '4.500',
			'usable_stock_base' => '1.000',
			'manual_lead_time_days' => 0,
			'base_per_purchase_unit' => '0.500000000',
			'base_quantity_scale' => 0
		)));

		$this->assertSame(Procurement_service::STATUS_BLOCKED, $result['status']);
		$this->assertStringContainsString('tidak representable', $result['reason']);
		$this->assertNull($result['q_final_base']);
	}

	public function testOrderedLegacyPoQuantityDoesNotEnterInventoryPosition()
	{
		$input = $this->input(array(
			'usable_stock_base' => '8.000',
			'eligible_open_po_base' => '0.000',
			'open_po_review_purchase_units' => '50.000'
		));
		$result = Procurement_service::calculate($input);

		$this->assertSame('8', $result['inventory_position_base']);
		$this->assertSame('16', $result['q0']);
	}

	public function testDemoFeatureGateRequiresEnvironmentFlagAndMarker()
	{
		$this->assertTrue(Procurement_service::isFeatureEnabled(
			'demo',
			array('procurement_recommendations_enabled' => true),
			'demo'
		));
		$this->assertFalse(Procurement_service::isFeatureEnabled(
			'production',
			array('procurement_recommendations_enabled' => true),
			'demo'
		));
		$this->assertFalse(Procurement_service::isFeatureEnabled(
			'demo',
			array('procurement_recommendations_enabled' => false),
			'demo'
		));
		$this->assertFalse(Procurement_service::isFeatureEnabled(
			'demo',
			array(),
			'demo'
		));
		$this->assertFalse(Procurement_service::isFeatureEnabled(
			'demo',
			array('procurement_recommendations_enabled' => true),
			'scratch'
		));
	}
}
