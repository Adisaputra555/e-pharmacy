<?php
$root = dirname(__DIR__, 2);
if (!defined('BASEPATH')) {
	define('BASEPATH', $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Product_unit_feature_gate.php';

class ProductUnitFeatureGateTest extends PHPUnit\Framework\TestCase
{
	public function testFeatureIsEnabledOnlyForDemoWithLocalOptIn()
	{
		$this->assertTrue(Product_unit_feature_gate::enabled('demo', true));
		$this->assertFalse(Product_unit_feature_gate::enabled('testing', true));
		$this->assertFalse(Product_unit_feature_gate::enabled('development', true));
		$this->assertFalse(Product_unit_feature_gate::enabled('demo', false));
	}
}
