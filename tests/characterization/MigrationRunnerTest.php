<?php
class MigrationRunnerTest extends PHPUnit\Framework\TestCase
{
	protected $backupGlobals = false;

	private function runner($config = null)
	{
		if ($config === null) {
			$config = $GLOBALS['p0b1_local_config'];
		}
		return new Migration_runner(
			$GLOBALS['p0b1_migrator_db'],
			$config,
			new Business_clock()
		);
	}

	private function assertThrows($callback)
	{
		try {
			call_user_func($callback);
		} catch (RuntimeException $error) {
			$this->assertNotSame('', $error->getMessage());
			return;
		}
		$this->fail('Expected the migration operation to be rejected.');
	}

	private function tableExists($name)
	{
		$stmt = $GLOBALS['p0b1_migrator_db']->prepare(
			'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
		);
		$stmt->bind_param('s', $name);
		$stmt->execute();
		$stmt->bind_result($count);
		$stmt->fetch();
		$stmt->close();
		return (int) $count > 0;
	}

	public function testDefaultOffAndDryRunDoNotWrite()
	{
		$this->assertFalse($GLOBALS['p0b1_local_config']['migration_web_enabled']);
		$this->assertFalse($GLOBALS['p0b1_local_config']['business_clock_web_enabled']);

		$oldFlag = getenv('APOTEK_MIGRATIONS_TEST');
		putenv('APOTEK_MIGRATIONS_TEST=0');
		$this->assertThrows(function () {
			$this->runner()->apply(array());
		});
		if ($oldFlag === false) {
			putenv('APOTEK_MIGRATIONS_TEST');
		} else {
			putenv('APOTEK_MIGRATIONS_TEST=' . $oldFlag);
		}

		$migration = array(
			'version' => '20261121231500_dry_run_probe',
			'sql' => 'CREATE TABLE p0b1_dry_run_probe (id INT NOT NULL PRIMARY KEY)'
		);
		$before = $this->tableExists('p0b1_dry_run_probe');
		$result = $this->runner()->dryRun(array($migration));
		$this->assertSame(1, count($result));
		$this->assertTrue($before === $this->tableExists('p0b1_dry_run_probe'));
	}

	public function testDestructiveStatementsAndDatabaseClockAreRejected()
	{
		$blocked = array(
			'DROP TABLE p0b1_probe',
			'TRUNCATE TABLE p0b1_probe',
			'DELETE FROM p0b1_probe',
			'ALTER TABLE p0b1_probe DROP COLUMN old_value'
		);
		foreach ($blocked as $sql) {
			$this->assertThrows(function () use ($sql) {
				Migration_runner::lintSql($sql);
			});
		}
		$this->assertThrows(function () {
			Migration_runner::lintSql('INSERT INTO p0b1_probe (created_at) VALUES (NOW())');
		});
		$this->assertThrows(function () {
			Migration_runner::lintSql('ALTER TABLE p0b1_probe ADD created_at DATETIME DEFAULT CURRENT_TIMESTAMP');
		});
	}

	public function testLineEndingNormalizationProducesSameChecksum()
	{
		$lf = "CREATE TABLE p0b1_line_endings (id INT NOT NULL)\n";
		$crlf = str_replace("\n", "\r\n", $lf);
		$this->assertSame(
			Migration_runner::checksum($lf),
			Migration_runner::checksum($crlf)
		);
	}

	public function testMissingBackupStopsBeforeMigrationLedgerWrite()
	{
		$config = $GLOBALS['p0b1_local_config'];
		$config['backup_archive_path'] = sys_get_temp_dir() . DIRECTORY_SEPARATOR .
			'missing-phase0b1-backup-' . bin2hex(random_bytes(8)) . '.7z';
		$version = '20261121231501_backup_gate_probe';
		$this->assertThrows(function () use ($config, $version) {
			$this->runner($config)->apply(array(array(
				'version' => $version,
				'sql' => 'CREATE TABLE p0b1_backup_gate_probe (id INT NOT NULL)'
			)));
		});
		$this->assertFalse($this->tableExists('p0b1_backup_gate_probe'));
	}

	public function testLockTimeoutPreventsMigration()
	{
		$lockName = Migration_runner::lockName();
		$stmt = $GLOBALS['p0b1_dml_db']->prepare('SELECT GET_LOCK(?, 0)');
		$stmt->bind_param('s', $lockName);
		$stmt->execute();
		$stmt->bind_result($acquired);
		$stmt->fetch();
		$stmt->close();
		$this->assertSame(1, (int) $acquired);

		try {
			$this->assertThrows(function () {
				$this->runner()->apply(array(array(
					'version' => '20261121231502_lock_timeout_probe',
					'sql' => 'CREATE TABLE p0b1_lock_timeout_probe (id INT NOT NULL)'
				)));
			});
		} finally {
			$stmt = $GLOBALS['p0b1_dml_db']->prepare('SELECT RELEASE_LOCK(?)');
			$stmt->bind_param('s', $lockName);
			$stmt->execute();
			$stmt->close();
		}
		$this->assertFalse($this->tableExists('p0b1_lock_timeout_probe'));
	}

	public function testApplyIsIdempotentAndRejectsChangedPayloadForSameVersion()
	{
		$version = date('YmdHis') . '_runner_probe_' . bin2hex(random_bytes(4));
		$sql = 'CREATE TABLE IF NOT EXISTS p0b1_runner_probe (id BIGINT NOT NULL PRIMARY KEY)';
		$migration = array('version' => $version, 'sql' => $sql);
		$clock = new Business_clock(function () {
			return new DateTimeImmutable('2026-11-21 23:15:00', new DateTimeZone('Asia/Jakarta'));
		});
		$runner = new Migration_runner(
			$GLOBALS['p0b1_migrator_db'],
			$GLOBALS['p0b1_local_config'],
			$clock
		);
		$this->assertSame(1, $runner->apply(array($migration)));
		$this->assertSame(0, $runner->apply(array($migration)));

		$stmt = $GLOBALS['p0b1_migrator_db']->prepare(
			'SELECT applied_at FROM schema_migrations WHERE version = ?'
		);
		$stmt->bind_param('s', $version);
		$stmt->execute();
		$stmt->bind_result($appliedAt);
		$stmt->fetch();
		$stmt->close();
		$this->assertSame('2026-11-21 23:15:00', $appliedAt);

		$this->assertThrows(function () use ($runner, $version) {
			$runner->apply(array(array(
				'version' => $version,
				'sql' => 'ALTER TABLE p0b1_runner_probe ADD changed_value TINYINT NULL'
			)));
		});
		$this->assertFalse($this->columnExists('p0b1_runner_probe', 'changed_value'));
	}

	private function columnExists($table, $column)
	{
		$stmt = $GLOBALS['p0b1_migrator_db']->prepare(
			'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
		);
		$stmt->bind_param('ss', $table, $column);
		$stmt->execute();
		$stmt->bind_result($count);
		$stmt->fetch();
		$stmt->close();
		return (int) $count > 0;
	}
}
