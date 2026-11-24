<?php
$root = dirname(__DIR__, 2);
if (!defined('BASEPATH')) {
	define('BASEPATH', $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
require_once $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
require_once $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Migration_runner.php';
require_once $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Unit_migration_runner.php';

class UnitMigrationRunnerTest extends PHPUnit\Framework\TestCase
{
	private function runner($config = null)
	{
		$config = $config === null ? $GLOBALS['p0b1_local_config'] : $config;
		return new Unit_migration_runner(
			$GLOBALS['p0b1_migrator_db'],
			$config,
			new Business_clock()
		);
	}

	public function testDryRunValidatesAllAdditiveUnitMigrations()
	{
		$runner = $this->runner();
		$migrations = $runner->loadMigrations(
			dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'migrations'
		);
		$this->assertCount(6, $migrations);
		$this->assertCount(6, $runner->dryRun($migrations));
	}

	public function testTargetGuardRejectsBaselineBeforeDatabaseWork()
	{
		$config = $GLOBALS['p0b1_local_config'];
		$config['database'] = 'db_apotek_online';
		$this->expectException(RuntimeException::class);
		$this->runner($config)->dryRun(array());
	}

	public function testLineEndingNormalizationAndDestructiveSqlGuard()
	{
		$lf = "CREATE TABLE p11_lf_probe (id INT NOT NULL)\n";
		$crlf = str_replace("\n", "\r\n", $lf);
		$this->assertSame(Migration_runner::checksum($lf), Migration_runner::checksum($crlf));

		foreach (array('DROP TABLE p11_probe', 'TRUNCATE TABLE p11_probe', 'DELETE FROM p11_probe') as $sql) {
			try {
				Migration_runner::lintSql($sql);
				$this->fail('Destructive migration SQL must be rejected.');
			} catch (RuntimeException $error) {
				$this->assertNotSame('', $error->getMessage());
			}
		}
	}

	public function testDryRunDoesNotExecuteItsMigrationSql()
	{
		$migration = array(
			'version' => '20261122003600_unit_dry_run_probe',
			'sql' => 'CREATE TABLE p11_unit_dry_run_probe (id INT NOT NULL)'
		);
		$runner = $this->runner();
		$this->assertCount(1, $runner->dryRun(array($migration)));
		$stmt = $GLOBALS['p0b1_migrator_db']->prepare(
			'SELECT COUNT(*) FROM information_schema.TABLES ' .
			'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
		);
		$table = 'p11_unit_dry_run_probe';
		$stmt->bind_param('s', $table);
		$stmt->execute();
		$stmt->bind_result($count);
		$stmt->fetch();
		$stmt->close();
		$this->assertSame(0, (int) $count);
	}

	public function testLockTimeoutPreventsUnitMigration()
	{
		$config = $GLOBALS['p0b1_local_config'];
		$config['lock_timeout'] = 0;
		$lockName = 'apotek_unit_migrations:' . $config['database'];
		$stmt = $GLOBALS['p0b1_dml_db']->prepare('SELECT GET_LOCK(?, 0)');
		$stmt->bind_param('s', $lockName);
		$stmt->execute();
		$stmt->bind_result($acquired);
		$stmt->fetch();
		$stmt->close();
		$this->assertSame(1, (int) $acquired);

		try {
			$this->expectException(RuntimeException::class);
			$this->runner($config)->apply(array(array(
				'version' => '20261122003601_unit_lock_probe',
				'sql' => 'CREATE TABLE p11_unit_lock_probe (id INT NOT NULL)'
			)));
		} finally {
			$stmt = $GLOBALS['p0b1_dml_db']->prepare('SELECT RELEASE_LOCK(?)');
			$stmt->bind_param('s', $lockName);
			$stmt->execute();
			$stmt->close();
		}
	}

	public function testReapplyingMigrationsIsANoOpAndChangedChecksumIsRejected()
	{
		$runner = $this->runner();
		$migrations = $runner->loadMigrations(
			dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'migrations'
		);
		$this->assertSame(0, $runner->apply($migrations));

		$this->expectException(RuntimeException::class);
		$runner->apply(array(array(
			'version' => '20261122003500_product_unit_conversions',
			'sql' => 'CREATE TABLE p11_changed_payload_probe (id INT NOT NULL)'
		)));
	}

	public function testSnapshotColumnsExistWithLegacyRowsNull()
	{
		$expected = array(
			'cart_items' => array('unit_id_snapshot', 'quantity_base', 'unit_status_snapshot'),
			'order_items' => array('unit_id_snapshot', 'quantity_base', 'unit_status_snapshot'),
			'purchase_order_items' => array('purchase_unit_id_snapshot', 'received_quantity_base', 'unit_status_snapshot')
		);
		foreach ($expected as $table => $columns) {
			foreach ($columns as $column) {
				$stmt = $GLOBALS['p0b1_migrator_db']->prepare(
					'SELECT COUNT(*) FROM information_schema.COLUMNS ' .
					'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
				);
				$stmt->bind_param('ss', $table, $column);
				$stmt->execute();
				$stmt->bind_result($count);
				$stmt->fetch();
				$stmt->close();
				$this->assertSame(1, (int) $count, $table . '.' . $column . ' must exist.');
			}
		}

		foreach (array(
			'cart_items' => 'unit_id_snapshot',
			'order_items' => 'unit_id_snapshot',
			'purchase_order_items' => 'purchase_unit_id_snapshot'
		) as $table => $unitColumn) {
			$quantityColumn = $table === 'purchase_order_items' ? 'received_quantity_base' : 'quantity_base';
			$result = $GLOBALS['p0b1_dml_db']->query(
				'SELECT COUNT(*) FROM `' . $table . '` WHERE `' . $unitColumn .
				'` IS NOT NULL OR `' . $quantityColumn . '` IS NOT NULL'
			);
			$this->assertNotFalse($result);
			$row = $result->fetch_row();
			$result->free();
			$this->assertSame(0, (int) $row[0], 'Legacy snapshots must remain NULL in ' . $table . '.');
		}
	}
}
