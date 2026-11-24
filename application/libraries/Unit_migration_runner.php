<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Unit_migration_runner
{
	private $connection;
	private $config;
	private $clock;

	public function __construct($connection, array $config, Business_clock $clock = null)
	{
		if (!($connection instanceof mysqli)) {
			throw new InvalidArgumentException('Unit migration runner requires a mysqli connection.');
		}

		$this->connection = $connection;
		$this->config = $config;
		$this->clock = $clock ?: new Business_clock();
	}

	public function loadMigrations($directory)
	{
		if (!is_dir($directory)) {
			throw new RuntimeException('Unit migration directory is missing.');
		}

		$files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql');
		if ($files === false) {
			throw new RuntimeException('Unable to enumerate unit migrations.');
		}
		sort($files, SORT_STRING);

		$migrations = array();
		foreach ($files as $file) {
			$basename = basename($file);
			if (!preg_match('/^([0-9]{14}_[a-z0-9_]+)\.sql$/i', $basename, $matches)) {
				throw new RuntimeException('Unit migration filename has an invalid version.');
			}
			$sql = file_get_contents($file);
			if ($sql === false) {
				throw new RuntimeException('Unable to read a unit migration.');
			}
			$migrations[] = array('version' => $matches[1], 'sql' => $sql);
		}

		return $migrations;
	}

	public function dryRun(array $migrations)
	{
		$this->assertCliMode();
		$this->assertTarget();
		return $this->validateMigrations($migrations);
	}

	public function apply(array $migrations)
	{
		$this->assertCliMode();
		$this->assertTarget();
		$validated = $this->validateMigrations($migrations);
		if (!$validated) {
			return 0;
		}

		$this->verifyBackupGate();
		$this->acquireLock();
		try {
			$this->ensureLedger();
			$pending = $this->verifyChecksums($validated);
			foreach ($pending as $migration) {
				if (!$this->connection->query($migration['sql'])) {
					throw new RuntimeException('Unit migration failed; stop before continuing.');
				}

				$appliedAt = $this->clock->now()->format('Y-m-d H:i:s');
				$stmt = $this->connection->prepare(
					'INSERT INTO schema_migrations (version, checksum, applied_at) VALUES (?, ?, ?)'
				);
				if (!$stmt) {
					throw new RuntimeException('Unable to prepare unit migration ledger entry.');
				}
				$stmt->bind_param('sss', $migration['version'], $migration['checksum'], $appliedAt);
				if (!$stmt->execute()) {
					$stmt->close();
					throw new RuntimeException('Unable to write unit migration ledger entry.');
				}
				$stmt->close();
			}
		} finally {
			$this->releaseLock();
		}

		return count($pending);
	}

	private function validateMigrations(array $migrations)
	{
		$validated = array();
		$seen = array();
		foreach ($migrations as $migration) {
			if (!isset($migration['version'], $migration['sql']) ||
				!preg_match('/^[0-9]{14}_[a-z0-9_]+$/i', $migration['version']) ||
				!is_string($migration['sql'])) {
				throw new RuntimeException('Unit migration version or SQL is invalid.');
			}
			if (isset($seen[$migration['version']])) {
				throw new RuntimeException('Unit migration versions must be unique.');
			}
			$seen[$migration['version']] = true;
			Migration_runner::lintSql($migration['sql']);
			$validated[] = array(
				'version' => $migration['version'],
				'sql' => Migration_runner::normalizeSql($migration['sql']),
				'checksum' => Migration_runner::checksum($migration['sql'])
			);
		}

		usort($validated, function ($left, $right) {
			return strcmp($left['version'], $right['version']);
		});
		return $validated;
	}

	private function assertCliMode()
	{
		if (PHP_SAPI !== 'cli' ||
			getenv('CI_ENV') !== 'testing' ||
			getenv('APOTEK_UNIT_MIGRATIONS_TEST') !== '1' ||
			!empty($this->config['migration_web_enabled'])) {
			throw new RuntimeException('Unit migrations are disabled outside explicit CLI test mode.');
		}
	}

	private function assertTarget()
	{
		$expectedUsers = array(
			'db_apotek_online_scratch_p1' => 'apotek_scratch_migrator@',
			'db_apotek_online_demo' => 'apotek_demo_migrator@'
		);
		$database = isset($this->config['database']) ? $this->config['database'] : '';
		if (!isset($expectedUsers[$database])) {
			throw new RuntimeException('Unit migration runner refuses this target database.');
		}

		$result = $this->connection->query('SELECT DATABASE(), CURRENT_USER()');
		$row = $result ? $result->fetch_row() : null;
		if (!$row || $row[0] !== $database || strpos($row[1], $expectedUsers[$database]) !== 0) {
			throw new RuntimeException('Unit migration runner refuses a non-migrator connection.');
		}
	}

	private function verifyBackupGate()
	{
		foreach (array('backup_archive_path', 'backup_manifest_path', 'backup_report_path', 'backup_report_sha256') as $key) {
			if (empty($this->config[$key])) {
				throw new RuntimeException('A verified backup is required before unit migrations.');
			}
		}

		$archivePath = $this->config['backup_archive_path'];
		$manifestPath = $this->config['backup_manifest_path'];
		$reportPath = $this->config['backup_report_path'];
		if (!is_file($archivePath) || !is_file($manifestPath) || !is_file($reportPath)) {
			throw new RuntimeException('A verified backup artifact or restore report is missing.');
		}

		$manifest = file_get_contents($manifestPath);
		$archiveHash = hash_file('sha256', $archivePath);
		if ($manifest === false || $archiveHash === false ||
			strpos($manifest, basename($archivePath)) === false ||
			!preg_match('/\b' . preg_quote($archiveHash, '/') . '\b/i', $manifest)) {
			throw new RuntimeException('Backup archive does not match its SHA-256 manifest.');
		}

		$reportHash = hash_file('sha256', $reportPath);
		$report = file_get_contents($reportPath);
		if ($reportHash === false ||
			!hash_equals(strtolower($this->config['backup_report_sha256']), strtolower($reportHash)) ||
			$report === false ||
			!preg_match('/restore|restor|pemulihan/i', $report)) {
			throw new RuntimeException('Verified restore evidence is missing or does not match.');
		}
	}

	private function acquireLock()
	{
		$name = 'apotek_unit_migrations:' . $this->config['database'];
		$timeout = isset($this->config['lock_timeout']) ? (int) $this->config['lock_timeout'] : 5;
		$stmt = $this->connection->prepare('SELECT GET_LOCK(?, ?)');
		if (!$stmt) {
			throw new RuntimeException('Unable to prepare unit migration lock.');
		}
		$stmt->bind_param('si', $name, $timeout);
		$stmt->execute();
		$stmt->bind_result($acquired);
		$stmt->fetch();
		$stmt->close();
		if ((int) $acquired !== 1) {
			throw new RuntimeException('Unit migration lock timed out.');
		}
	}

	private function releaseLock()
	{
		$name = 'apotek_unit_migrations:' . $this->config['database'];
		$stmt = $this->connection->prepare('SELECT RELEASE_LOCK(?)');
		if (!$stmt) {
			throw new RuntimeException('Unable to prepare unit migration lock release.');
		}
		$stmt->bind_param('s', $name);
		if (!$stmt->execute()) {
			$stmt->close();
			throw new RuntimeException('Unable to release unit migration lock.');
		}
		$stmt->close();
	}

	private function ensureLedger()
	{
		$sql = 'CREATE TABLE IF NOT EXISTS schema_migrations (' .
			'version VARCHAR(128) NOT NULL PRIMARY KEY, ' .
			'checksum CHAR(64) NOT NULL, ' .
			'applied_at DATETIME NOT NULL' .
			') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
		if (!$this->connection->query($sql)) {
			throw new RuntimeException('Unable to create the unit migration ledger.');
		}
		$result = $this->connection->query('SELECT version, checksum, applied_at FROM schema_migrations LIMIT 0');
		if (!$result) {
			throw new RuntimeException('Unit migration ledger schema is invalid.');
		}
		$result->free();
	}

	private function verifyChecksums(array $migrations)
	{
		$pending = array();
		$stmt = $this->connection->prepare('SELECT checksum FROM schema_migrations WHERE version = ?');
		if (!$stmt) {
			throw new RuntimeException('Unable to inspect unit migration ledger.');
		}

		foreach ($migrations as $migration) {
			$version = $migration['version'];
			$stmt->bind_param('s', $version);
			if (!$stmt->execute()) {
				$stmt->close();
				throw new RuntimeException('Unable to inspect unit migration ledger.');
			}
			$stmt->bind_result($storedChecksum);
			$found = $stmt->fetch();
			$stmt->free_result();
			if ($found) {
				if (!hash_equals(strtolower($storedChecksum), strtolower($migration['checksum']))) {
					$stmt->close();
					throw new RuntimeException('Applied unit migration checksum differs from its payload.');
				}
				continue;
			}
			$pending[] = $migration;
		}
		$stmt->close();
		return $pending;
	}
}
