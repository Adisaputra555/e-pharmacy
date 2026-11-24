<?php
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Migration_runner.php';

class ProcurementScratchMigrationTest extends PHPUnit\Framework\TestCase
{
	public function testScratchMigrationIsAppliedWithTheSourceChecksum()
	{
		$db = $GLOBALS['p0b1_migrator_db'];
		$migrationFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR .
			'application' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR .
			'p16' . DIRECTORY_SEPARATOR . '20261122125200_product_suppliers.sql';
		$this->assertFileExists($migrationFile);
		$version = basename($migrationFile, '.sql');
		$sql = file_get_contents($migrationFile);
		$this->assertNotFalse($sql);

		$stmt = $db->prepare(
			'SELECT checksum FROM schema_migrations WHERE version = ?'
		);
		$this->assertNotFalse($stmt);
		$stmt->bind_param('s', $version);
		$this->assertTrue($stmt->execute());
		$stmt->bind_result($recordedChecksum);
		$this->assertTrue($stmt->fetch());
		$stmt->close();
		$this->assertSame(Migration_runner::checksum($sql), $recordedChecksum);

		$result = $db->query(
			"SELECT COUNT(*) FROM information_schema.TABLES " .
			"WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_suppliers'"
		);
		$this->assertNotFalse($result);
		$row = $result->fetch_row();
		$result->free();
		$this->assertSame(1, (int) $row[0]);
	}
}
