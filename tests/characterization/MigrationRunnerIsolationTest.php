<?php
class MigrationRunnerIsolationTest extends PHPUnit\Framework\TestCase
{
	protected $backupGlobals = false;

	private function grants($connection)
	{
		$result = $connection->query('SHOW GRANTS FOR CURRENT_USER()');
		$grants = array();
		while ($result && ($row = $result->fetch_row())) {
			$grants[] = strtoupper($row[0]);
		}
		return $grants;
	}

	public function testScratchDmlAccountHasNoDdlAndCannotReadBaseline()
	{
		$grants = $this->grants($GLOBALS['p0b1_dml_db']);
		$all = implode("\n", $grants);
		$this->assertFalse((bool) preg_match('/\b(?:CREATE|ALTER|DROP|INDEX|REFERENCES|ALL PRIVILEGES|LOCK TABLES|CREATE TEMPORARY TABLES)\b/', $all));
		$this->assertTrue(strpos($all, 'DB_APOTEK_ONLINE_SCRATCH_P1') !== false);

		$created = @$GLOBALS['p0b1_dml_db']->query(
			'CREATE TABLE p0b1_app_ddl_probe (id INT NOT NULL)'
		);
		$this->assertFalse($created);

		$config = $GLOBALS['p0b1_local_config'];
		$baseline = @new mysqli(
			$config['hostname'],
			$GLOBALS['p0b1_dml_user'],
			$GLOBALS['p0b1_dml_password'],
			'db_apotek_online',
			isset($config['port']) ? (int) $config['port'] : 3306
		);
		$this->assertNotSame(0, $baseline->connect_errno);
	}

	public function testMigratorHasNoDropPrivilegeAndDropDatabaseIsDenied()
	{
		$grants = $this->grants($GLOBALS['p0b1_migrator_db']);
		$all = implode("\n", $grants);
		$this->assertFalse((bool) preg_match('/\b(?:DROP|ALL PRIVILEGES|GRANT OPTION)\b/', $all));
		$this->assertTrue(strpos($all, 'DB_APOTEK_ONLINE_SCRATCH_P1') !== false);

		$config = $GLOBALS['p0b1_local_config'];
		$baseline = @new mysqli(
			$config['hostname'],
			$config['username'],
			$config['password'],
			'db_apotek_online',
			isset($config['port']) ? (int) $config['port'] : 3306
		);
		$this->assertNotSame(0, $baseline->connect_errno);

		$dropped = @$GLOBALS['p0b1_migrator_db']->query(
			'DROP DATABASE db_apotek_online_scratch_p1'
		);
		$this->assertFalse($dropped);
		$result = $GLOBALS['p0b1_migrator_db']->query('SELECT DATABASE()');
		$row = $result ? $result->fetch_row() : null;
		$this->assertNotEmpty($row);
		$this->assertSame('db_apotek_online_scratch_p1', $row[0]);
	}
}
