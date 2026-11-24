<?php
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Migration_runner.php';

class ProcurementMigrationTest extends PHPUnit\Framework\TestCase
{
	public function testDemoProductSupplierMigrationIsRecordedWithMatchingChecksum()
	{
		$db = $GLOBALS['b1_demo_app_db'];
		$migrationFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR .
			'application' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR .
			'p16' . DIRECTORY_SEPARATOR . '20261122125200_product_suppliers.sql';
		$this->assertFileExists($migrationFile);

		$version = basename($migrationFile, '.sql');
		$sql = file_get_contents($migrationFile);
		$this->assertNotFalse($sql);
		$checksum = Migration_runner::checksum($sql);
		$stmt = $db->prepare(
			'SELECT checksum FROM schema_migrations WHERE version = ?'
		);
		$this->assertNotFalse($stmt);
		$stmt->bind_param('s', $version);
		$this->assertTrue($stmt->execute());
		$stmt->bind_result($recordedChecksum);
		$this->assertTrue($stmt->fetch());
		$stmt->close();
		$this->assertSame($checksum, $recordedChecksum);

		$result = $db->query(
			"SELECT COLUMN_NAME FROM information_schema.COLUMNS " .
			"WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_suppliers'"
		);
		$this->assertNotFalse($result);
		$columns = array();
		while ($row = $result->fetch_row()) {
			$columns[] = $row[0];
		}
		$result->free();
		$this->assertEqualsCanonicalizing(array(
			'id',
			'product_id',
			'supplier_id',
			'is_preferred',
			'purchase_unit',
			'base_per_purchase_unit',
			'moq_mode',
			'moq_purchase_units',
			'order_multiple_purchase_units',
			'manual_lead_time_days',
			'created_at',
			'updated_at'
		), $columns);
	}
}
