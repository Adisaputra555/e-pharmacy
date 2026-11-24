<?php
$root = dirname(__DIR__, 2);
if (!defined('BASEPATH')) {
	define('BASEPATH', $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Product_unit_service.php';

class ProductUnitMathTest extends PHPUnit\Framework\TestCase
{
	public function testConvertsExactlyAtConfiguredScales()
	{
		$this->assertSame(
			'1.250',
			Product_unit_service::calculateBaseQuantity('0.125', '10', 3, 2)
		);
	}

	public function testRejectsInputScaleAndUnrepresentableBasePrecision()
	{
		$this->expectException(InvalidArgumentException::class);
		Product_unit_service::calculateBaseQuantity('0.125', '10', 2, 3);
	}

	public function testRejectsHiddenRoundingAtBaseScale()
	{
		$this->expectException(InvalidArgumentException::class);
		Product_unit_service::calculateBaseQuantity('0.25', '10', 2, 0);
	}

	public function testRejectsValuesBeyondDecimalStorageRange()
	{
		$this->expectException(InvalidArgumentException::class);
		Product_unit_service::calculateBaseQuantity(
			'999999999999.999',
			'999999999.999999999',
			3,
			3
		);
	}

	public function testRejectsZeroQuantity()
	{
		$this->expectException(InvalidArgumentException::class);
		Product_unit_service::calculateBaseQuantity('0', '1.000000000', 3, 3);
	}

	public function testRejectsExcessFactorScale()
	{
		$this->expectException(InvalidArgumentException::class);
		Product_unit_service::calculateBaseQuantity('1', '1.0000000001', 3, 3);
	}
}
