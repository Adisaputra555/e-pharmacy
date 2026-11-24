<?php
$root = dirname(__DIR__, 2);
if (!defined('BASEPATH')) {
	define('BASEPATH', $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
require_once $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
require_once $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Product_unit_service.php';

class DemoProductUnitTest extends PHPUnit\Framework\TestCase
{
	public function testServiceCreatedDemoExampleIsInactiveVerifiedAndFourLevel()
	{
		$db = $GLOBALS['b1_demo_app_db'];
		$stmt = $db->prepare('SELECT id, name, unit_id, is_active FROM products WHERE code = ?');
		$code = 'DEMO-UNIT4-B2A';
		$stmt->bind_param('s', $code);
		$stmt->execute();
		$product = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		$this->assertNotEmpty($product);
		$this->assertStringStartsWith('[DEMO]', $product['name']);
		$this->assertSame(0, (int) $product['is_active']);

		$service = new Product_unit_service($db, new Business_clock());
		$this->assertSame('verified', $service->getStatus((int) $product['id']));
		$config = $service->getConfiguration((int) $product['id']);
		$this->assertCount(4, $config);

		$stmt = $db->prepare('SELECT id FROM units WHERE code = ?');
		$unitCode = 'DEMO-CTN';
		$stmt->bind_param('s', $unitCode);
		$stmt->execute();
		$stmt->bind_result($cartonId);
		$this->assertTrue($stmt->fetch());
		$stmt->close();
		$result = $service->convertToBase((int) $product['id'], (int) $cartonId, '1');
		$this->assertSame('1000.000', $result['quantity_base']);
	}
}
