<?php
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Migration_runner.php';

class DemoDatabaseIsolationTest extends PHPUnit\Framework\TestCase
{
	protected $backupGlobals = false;

	private function hasExpectedScopedGrants($connection, array $expected)
	{
		$result = $connection->query('SHOW GRANTS FOR CURRENT_USER()');
		if (!$result) {
			return false;
		}

		$found = 0;
		while ($row = $result->fetch_row()) {
			$grant = strtoupper($row[0]);
			if (strpos($grant, 'GRANT USAGE ON *.*') === 0) {
				continue;
			}
			if (!preg_match('/^GRANT (.+) ON `?DB_APOTEK_ONLINE_DEMO`?\.\* TO /', $grant, $matches)) {
				return false;
			}

			$actual = array_map('trim', explode(',', $matches[1]));
			$wanted = $expected;
			sort($actual, SORT_STRING);
			sort($wanted, SORT_STRING);
			if ($actual !== $wanted) {
				return false;
			}
			$found++;
		}

		return $found === 1;
	}

	public function testDemoDatabaseGroupsAndGrantsAreScoped()
	{
		$this->assertSame('db_apotek_online_demo', $GLOBALS['b1_demo_config']['default']['database']);
		$this->assertSame('db_apotek_online_demo', $GLOBALS['b1_demo_config']['migrator']['database']);
		$this->assertTrue($this->hasExpectedScopedGrants(
			$GLOBALS['b1_demo_app_db'],
			array('SELECT', 'INSERT', 'UPDATE', 'DELETE')
		));
		$this->assertTrue($this->hasExpectedScopedGrants(
			$GLOBALS['b1_demo_migrator_db'],
			array('CREATE', 'ALTER', 'INDEX', 'REFERENCES', 'SELECT', 'INSERT', 'UPDATE', 'DELETE')
		));

		$tableResult = $GLOBALS['b1_demo_app_db']->query(
			"SELECT TABLE_NAME FROM information_schema.TABLES " .
			"WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
		);
		$this->assertNotFalse($tableResult);
		$tableNames = array();
		while ($table = $tableResult->fetch_row()) {
			$tableNames[] = $table[0];
		}
		$tableResult->free();

		$migrationDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR .
			'application' . DIRECTORY_SEPARATOR . 'migrations';
		$migrationFiles = glob($migrationDirectory . DIRECTORY_SEPARATOR . '*.sql');
		$this->assertNotFalse($migrationFiles);
		$nestedMigrationDirectories = glob($migrationDirectory . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
		$this->assertNotFalse($nestedMigrationDirectories);
		foreach ($nestedMigrationDirectories as $nestedDirectory) {
			$nestedFiles = glob($nestedDirectory . DIRECTORY_SEPARATOR . '*.sql');
			$this->assertNotFalse($nestedFiles);
			$migrationFiles = array_merge($migrationFiles, $nestedFiles);
		}
		sort($migrationFiles, SORT_STRING);
		$ledgerResult = $GLOBALS['b1_demo_app_db']->query(
			'SELECT version, checksum FROM schema_migrations ORDER BY version'
		);
		$this->assertNotFalse($ledgerResult);
		$ledger = array();
		while ($entry = $ledgerResult->fetch_assoc()) {
			$ledger[$entry['version']] = $entry['checksum'];
		}
		$ledgerResult->free();
		$this->assertCount(count($migrationFiles), $ledger);

		$migrationTables = array('environment_marker', 'schema_migrations');
		foreach ($migrationFiles as $migrationFile) {
			$version = basename($migrationFile, '.sql');
			$sql = file_get_contents($migrationFile);
			$this->assertNotFalse($sql);
			$this->assertArrayHasKey($version, $ledger);
			$this->assertSame(
				Migration_runner::checksum($sql),
				$ledger[$version],
				'Applied migration checksum must match the source file.'
			);
			if (preg_match('/^\s*CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([a-z0-9_]+)/im', $sql, $match)) {
				$migrationTables[] = $match[1];
			}
		}
		foreach (array_unique($migrationTables) as $introducedTable) {
			$this->assertContains($introducedTable, $tableNames);
		}

		$introducedTableCount = count(array_unique($migrationTables));
		$metadataTableCount = count($tableNames);
		$legacyTableCount = $metadataTableCount - $introducedTableCount;
		$this->assertGreaterThan(0, $legacyTableCount);
		$this->assertSame(
			$legacyTableCount + $introducedTableCount,
			$metadataTableCount,
			'Table total is derived from schema metadata, ledger migrations, and the marker.'
		);
		$this->assertFalse(@$GLOBALS['b1_demo_app_db']->query(
			'CREATE TABLE b1_demo_app_ddl_probe (id INT NOT NULL)'
		));
	}

	public function testDemoAccountsCannotConnectToBaseline()
	{
		foreach (array('default', 'migrator') as $group) {
			$config = $GLOBALS['b1_demo_config'][$group];
			$baseline = @new mysqli(
				$config['hostname'],
				$config['username'],
				$config['password'],
				'db_apotek_online',
				isset($config['port']) ? (int) $config['port'] : 3306
			);
			$this->assertNotSame(0, $baseline->connect_errno);
		}
	}
}
