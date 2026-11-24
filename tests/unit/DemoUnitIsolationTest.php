<?php
class DemoUnitIsolationTest extends PHPUnit\Framework\TestCase
{
	private function scopedGrants($connection)
	{
		$result = $connection->query('SHOW GRANTS FOR CURRENT_USER()');
		if (!$result) {
			return array();
		}
		$scoped = array();
		while ($row = $result->fetch_row()) {
			$grant = strtoupper($row[0]);
			if (strpos($grant, 'GRANT USAGE ON *.*') === 0) {
				continue;
			}
			if (!preg_match('/^GRANT (.+) ON `?DB_APOTEK_ONLINE_DEMO`?\.\* TO /', $grant, $matches)) {
				return array();
			}
			$privileges = array_map('trim', explode(',', $matches[1]));
			sort($privileges, SORT_STRING);
			$scoped[] = $privileges;
		}
		return $scoped;
	}

	private function cannotConnectToBaseline(array $config)
	{
		$connection = @new mysqli(
			$config['hostname'],
			$config['username'],
			$config['password'],
			'db_apotek_online',
			isset($config['port']) ? (int) $config['port'] : 3306
		);
		$denied = $connection->connect_errno !== 0;
		if (!$denied) {
			$connection->close();
		}
		return $denied;
	}

	public function testDemoAppAccountHasOnlyScopedDmlAndCannotReadBaseline()
	{
		$expected = array('DELETE', 'INSERT', 'SELECT', 'UPDATE');
		$grants = $this->scopedGrants($GLOBALS['b1_demo_app_db']);
		$this->assertCount(1, $grants);
		$this->assertSame($expected, $grants[0]);
		$this->assertTrue($this->cannotConnectToBaseline($GLOBALS['b1_demo_config']['default']));
		$this->assertFalse(@$GLOBALS['b1_demo_app_db']->query(
			'CREATE TABLE p11_demo_app_ddl_probe (id INT NOT NULL)'
		));
	}

	public function testDemoMigratorHasScopedDdlWithoutDropAndCannotReadBaseline()
	{
		$expected = array('ALTER', 'CREATE', 'DELETE', 'INDEX', 'INSERT', 'REFERENCES', 'SELECT', 'UPDATE');
		$grants = $this->scopedGrants($GLOBALS['b1_demo_migrator_db']);
		$this->assertCount(1, $grants);
		$this->assertSame($expected, $grants[0]);
		$this->assertNotContains('DROP', $grants[0]);
		$this->assertTrue($this->cannotConnectToBaseline($GLOBALS['b1_demo_config']['migrator']));
	}
}
