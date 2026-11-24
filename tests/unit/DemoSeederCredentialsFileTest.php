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

class DemoSeederCredentialsFileTest extends PHPUnit\Framework\TestCase
{
	public function testCredentialFileLabelsLoginAndPlaintextPassword()
	{
		$path = tempnam(sys_get_temp_dir(), 'demo-credentials-test-');
		$this->assertNotFalse($path);
		try {
			$seeder = (new ReflectionClass('Demo_seeder'))->newInstanceWithoutConstructor();
			$writer = new ReflectionMethod('Demo_seeder', 'writeAccountsFile');
			$writer->setAccessible(true);
			$writer->invoke($seeder, array(array(
				'role' => 'administrator',
				'account' => 'test-only',
				'login' => 'demo-admin@example.invalid',
				'password' => 'test-only-not-a-real-credential'
			)), $path);
			$content = file_get_contents($path);
			$this->assertTrue(strpos($content, 'login=demo-admin@example.invalid') !== false);
			$this->assertTrue(strpos($content, 'password_plaintext=test-only-not-a-real-credential') !== false);
			$this->assertFalse(strpos($content, 'password_hash=') !== false);
		} finally {
			unlink($path);
		}
	}
}
