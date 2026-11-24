<?php
$root = dirname(__DIR__, 2);
if (!defined('BASEPATH')) {
	define('BASEPATH', $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
require_once $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Product_unit_feature_gate.php';

class ProductUnitMissingFlagFallbackTest extends PHPUnit\Framework\TestCase
{
	public function testMissingLocalFlagValueKeepsTheFeatureDisabled()
	{
		$this->assertFalse(Product_unit_feature_gate::enabled('demo', null));
		$this->assertFalse(Product_unit_feature_gate::enabled('demo', false));
	}
}
