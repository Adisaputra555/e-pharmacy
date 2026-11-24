<?php
$root = dirname(__DIR__, 2);
if (!defined('BASEPATH')) {
	define('BASEPATH', $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
require_once $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
require_once $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Product_unit_service.php';
require_once $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Demo_seeder.php';

class DemoSeederGuardTest extends PHPUnit\Framework\TestCase
{
	public function testOnlyMarkedDemoDatabaseIsAllowed()
	{
		$this->assertTrue(Demo_seeder::targetAllowed('db_apotek_online_demo', 'demo'));
		$this->assertFalse(Demo_seeder::targetAllowed('db_apotek_online', 'demo'));
		$this->assertFalse(Demo_seeder::targetAllowed('db_apotek_online_scratch_p1', 'demo'));
		$this->assertFalse(Demo_seeder::targetAllowed('other_demo', 'demo'));
		$this->assertFalse(Demo_seeder::targetAllowed('db_apotek_online_demo', 'testing'));
		$this->assertFalse(Demo_seeder::targetAllowed('db_apotek_online_demo', null));
	}
}
