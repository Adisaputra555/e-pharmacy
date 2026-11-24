<?php
if (!defined('BASEPATH')) {
	define('BASEPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Product_unit_service.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Demo_seeder.php';

class ProcurementSeedPlanTest extends PHPUnit\Framework\TestCase
{
	private $supplierIds = array(41, 42, 43, 44, 45, 46, 47, 48, 49);

	private function buildPlan()
	{
		$tags = array(
			'fast_mover_stable', 'slow_mover', 'no_sales_3_months', 'seasonal',
			'new_less_3_months', 'overstock', 'critical_stock', 'near_expiry_red',
			'near_expiry_yellow', 'multi_batch_fefo', 'zero_stock', 'regular_01',
			'regular_02', 'regular_03', 'regular_04', 'regular_05', 'regular_06',
			'regular_07', 'regular_08', 'regular_09', 'regular_10', 'regular_11',
			'regular_12', 'legacy_one', 'legacy_two'
		);
		$plan = array();
		foreach ($tags as $index => $tag) {
			$plan = array_merge($plan, Demo_seeder::deterministicSupplierPlan(
				array('id' => $index + 101, 'index' => $index, 'tag' => $tag),
				$this->supplierIds
			));
		}
		return $plan;
	}

	public function testRepeatedBuildProducesTheSameSupplierPlanChecksum()
	{
		$first = $this->buildPlan();
		$second = $this->buildPlan();

		$this->assertCount(26, $first);
		$this->assertSame(
			hash('sha256', serialize($first)),
			hash('sha256', serialize($second))
		);
	}

	public function testSupplierPlanIsDeterministicAndAssignsOnePreferredMultiSupplierPair()
	{
		$product = array('id' => 101, 'index' => 6, 'tag' => 'critical_stock');
		$first = Demo_seeder::deterministicSupplierPlan($product, $this->supplierIds);
		$second = Demo_seeder::deterministicSupplierPlan($product, $this->supplierIds);

		$this->assertSame($first, $second);
		$this->assertCount(2, $first);
		$this->assertTrue($first[0]['is_preferred']);
		$this->assertFalse($first[1]['is_preferred']);
		$this->assertSame('set', $first[0]['moq_mode']);
		$this->assertSame('2.000', $first[0]['moq_purchase_units']);
		$this->assertSame('2.000', $first[0]['order_multiple_purchase_units']);
		$this->assertNull($first[1]['base_per_purchase_unit']);
	}

	public function testSupplierFixtureCoversExplicitNoneAndInvalidLeadOrFactor()
	{
		$none = Demo_seeder::deterministicSupplierPlan(
			array('id' => 102, 'index' => 17, 'tag' => 'regular_06'),
			$this->supplierIds
		);
		$leadMissing = Demo_seeder::deterministicSupplierPlan(
			array('id' => 103, 'index' => 14, 'tag' => 'regular_04'),
			$this->supplierIds
		);
		$factorMissing = Demo_seeder::deterministicSupplierPlan(
			array('id' => 104, 'index' => 15, 'tag' => 'regular_05'),
			$this->supplierIds
		);
		$legacy = Demo_seeder::deterministicSupplierPlan(
			array('id' => 106, 'index' => 22, 'tag' => 'legacy_one'),
			$this->supplierIds
		);

		$this->assertSame('none', $none[0]['moq_mode']);
		$this->assertNull($none[0]['moq_purchase_units']);
		$this->assertNull($none[0]['order_multiple_purchase_units']);
		$this->assertNull($leadMissing[0]['manual_lead_time_days']);
		$this->assertNull($factorMissing[0]['base_per_purchase_unit']);
		$this->assertNull($legacy[0]['base_per_purchase_unit']);
	}

	public function testPrimarySupplierIndexMatchesTheSeededOpenOrderedPurchaseItem()
	{
		$product = array('id' => 105, 'index' => 11, 'tag' => 'regular_01');
		$plan = Demo_seeder::deterministicSupplierPlan($product, $this->supplierIds);

		$this->assertCount(1, $plan);
		$this->assertSame($this->supplierIds[2], $plan[0]['supplier_id']);
		$this->assertTrue($plan[0]['is_preferred']);
	}
}
