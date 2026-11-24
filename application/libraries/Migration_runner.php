<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_runner
{
	const TARGET_DATABASE = 'db_apotek_online_scratch_p1';

	private $connection;
	private $config;
	private $clock;

	public function __construct($connection, array $config, Business_clock $clock = null)
	{
		if (!($connection instanceof mysqli)) {
			throw new InvalidArgumentException('Migration runner requires a mysqli connection.');
		}

		$this->connection = $connection;
		$this->config = $config;
		$this->clock = $clock ?: new Business_clock();
	}

	public static function normalizeSql($sql)
	{
		return str_replace(array("\r\n", "\r"), "\n", (string) $sql);
	}

	public static function checksum($sql)
	{
		return hash('sha256', self::normalizeSql($sql));
	}

	public static function lockName()
	{
		return 'apotek_migrations:' . self::TARGET_DATABASE;
	}

	public static function lintSql($sql, $allowDestructive = false)
	{
		$sql = self::normalizeSql($sql);
		$checked = preg_replace('/\/\*.*?\*\//s', ' ', $sql);
		$checked = preg_replace('/^\s*(?:--|#).*$/m', ' ', $checked);

		if (preg_match('/\bNOW\s*\(|\bCURRENT_TIMESTAMP\b/i', $checked)) {
			throw new RuntimeException('Migration SQL must use BusinessClock timestamps, not database time functions.');
		}

		if (!$allowDestructive && preg_match('/\b(?:DROP|TRUNCATE|DELETE)\b/i', $checked)) {
			throw new RuntimeException('Destructive migration SQL is blocked by the linter.');
		}

		$trimmed = trim($checked);
		if (substr_count($trimmed, ';') > 1 ||
			(strpos($trimmed, ';') !== false && substr(rtrim($trimmed), -1) !== ';')) {
			throw new RuntimeException('Each migration file must contain one SQL statement.');
		}

		return true;
	}

	public function loadMigrations($directory)
	{
		if (!is_dir($directory)) {
			return array();
		}

		$files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql');
		if ($files === false) {
			throw new RuntimeException('Unable to enumerate migration files.');
		}
		sort($files, SORT_STRING);

		$migrations = array();
		foreach ($files as $file) {
			$basename = basename($file);
			if (!preg_match('/^([0-9]{14}_[a-z0-9_]+)\.sql$/i', $basename, $matches)) {
				throw new RuntimeException('Migration filename does not use the required version format.');
			}
			$sql = file_get_contents($file);
			if ($sql === false) {
				throw new RuntimeException('Unable to read a migration file.');
			}
			$migrations[] = array('version' => $matches[1], 'sql' => $sql);
		}

		return $migrations;
	}

	public function dryRun(array $migrations, $allowDestructive = false)
	{
		$this->assertCliTestMode();
		$this->assertTargetDatabase();
		return $this->validateMigrations($migrations, $allowDestructive);
	}

	public function apply(array $migrations, $allowDestructive = false)
	{
		$this->assertCliTestMode();
		$this->assertTargetDatabase();
		$validated = $this->validateMigrations($migrations, $allowDestructive);
		if (!$validated) {
			return 0;
		}

		$this->verifyBackupGate();
		$this->acquireLock();
		try {
			$this->ensureLedger();
			$pending = $this->verifyLedgerChecksums($validated);
			foreach ($pending as $migration) {
				if (!$this->connection->query($migration['sql'])) {
					throw new RuntimeException('Migration DDL failed; scratch may be partial. Stop and restore scratch.');
				}

				$appliedAt = $this->clock->now()->format('Y-m-d H:i:s');
				$stmt = $this->connection->prepare(
					'INSERT INTO schema_migrations (version, checksum, applied_at) VALUES (?, ?, ?)'
				);
				if (!$stmt) {
					throw new RuntimeException('Unable to prepare migration ledger entry.');
				}
				$stmt->bind_param('sss', $migration['version'], $migration['checksum'], $appliedAt);
				if (!$stmt->execute()) {
					$stmt->close();
					throw new RuntimeException('Unable to write migration ledger entry.');
				}
				$stmt->close();
			}
		} finally {
			$this->releaseLock();
		}

		return count($pending);
	}

	private function validateMigrations(array $migrations, $allowDestructive)
	{
		$validated = array();
		$seen = array();
		$allowList = isset($this->config['destructive_allowlist']) && is_array($this->config['destructive_allowlist'])
			? $this->config['destructive_allowlist']
			: array();

		foreach ($migrations as $migration) {
			if (!isset($migration['version'], $migration['sql']) ||
				!preg_match('/^[0-9]{14}_[a-z0-9_]+$/i', $migration['version']) ||
				!is_string($migration['sql'])) {
				throw new RuntimeException('Migration version or SQL payload is invalid.');
			}
			if (isset($seen[$migration['version']])) {
				throw new RuntimeException('Migration versions must be unique in one run.');
			}
			$seen[$migration['version']] = true;

			$hasDestructiveToken = preg_match(
				'/\b(?:DROP|TRUNCATE|DELETE)\b/i',
				preg_replace('/^\s*(?:--|#).*$/m', ' ', preg_replace('/\/\*.*?\*\//s', ' ', self::normalizeSql($migration['sql'])))
			) === 1;
			$explicitlyAllowed = $allowDestructive &&
				in_array($migration['version'], $allowList, true);
			if ($hasDestructiveToken && !$explicitlyAllowed) {
				self::lintSql($migration['sql'], false);
			}
			self::lintSql($migration['sql'], $explicitlyAllowed);

			$validated[] = array(
				'version' => $migration['version'],
				'sql' => self::normalizeSql($migration['sql']),
				'checksum' => self::checksum($migration['sql'])
			);
		}

		usort($validated, function ($left, $right) {
			return strcmp($left['version'], $right['version']);
		});
		return $validated;
	}

	private function assertCliTestMode()
	{
		if (PHP_SAPI !== 'cli' ||
			getenv('CI_ENV') !== 'testing' ||
			getenv('APOTEK_MIGRATIONS_TEST') !== '1' ||
			!empty($this->config['migration_web_enabled'])) {
			throw new RuntimeException('Migration runner is disabled outside the explicit CLI scratch test mode.');
		}
	}

	private function assertTargetDatabase()
	{
		if (!isset($this->config['database']) ||
			$this->config['database'] !== self::TARGET_DATABASE) {
			throw new RuntimeException('Migration runner refuses a non-scratch configured database.');
		}

		$result = $this->connection->query('SELECT DATABASE(), CURRENT_USER()');
		$row = $result ? $result->fetch_row() : null;
		if (!$row ||
			$row[0] !== self::TARGET_DATABASE ||
			strpos($row[1], 'apotek_scratch_migrator@') !== 0) {
			throw new RuntimeException('Migration runner refuses a non-scratch or non-migrator connection.');
		}
	}

	private function verifyBackupGate()
	{
		$required = array(
			'backup_archive_path',
			'backup_manifest_path',
			'backup_report_path',
			'backup_report_sha256'
		);
		foreach ($required as $key) {
			if (empty($this->config[$key])) {
				throw new RuntimeException('A verified backup is required before migrations can run.');
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
			throw new RuntimeException('Verified restore evidence is missing or does not match the recorded report.');
		}
	}

	private function acquireLock()
	{
		$timeout = isset($this->config['lock_timeout']) ? (int) $this->config['lock_timeout'] : 2;
		$stmt = $this->connection->prepare('SELECT GET_LOCK(?, ?)');
		if (!$stmt) {
			throw new RuntimeException('Unable to prepare migration lock request.');
		}
		$name = self::lockName();
		$stmt->bind_param('si', $name, $timeout);
		if (!$stmt->execute()) {
			$stmt->close();
			throw new RuntimeException('Unable to acquire migration lock.');
		}
		$stmt->bind_result($acquired);
		$stmt->fetch();
		$stmt->close();
		if ((int) $acquired !== 1) {
			throw new RuntimeException('Migration lock timed out.');
		}
	}

	private function releaseLock()
	{
		$stmt = $this->connection->prepare('SELECT RELEASE_LOCK(?)');
		if (!$stmt) {
			throw new RuntimeException('Unable to prepare migration lock release.');
		}
		$name = self::lockName();
		$stmt->bind_param('s', $name);
		$ok = $stmt->execute();
		$stmt->close();
		if (!$ok) {
			throw new RuntimeException('Unable to release migration lock.');
		}
	}

	private function ensureLedger()
	{
		$sql = 'CREATE TABLE IF NOT EXISTS schema_migrations (' .
			'version VARCHAR(128) NOT NULL PRIMARY KEY, ' .
			'checksum CHAR(64) NOT NULL, ' .
			'applied_at DATETIME NOT NULL' .
			') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
		if (!$this->connection->query($sql)) {
			throw new RuntimeException('Unable to create the scratch migration ledger.');
		}
		$result = $this->connection->query('SELECT version, checksum, applied_at FROM schema_migrations LIMIT 0');
		if (!$result) {
			throw new RuntimeException('Scratch migration ledger has an unexpected schema.');
		}
		$result->free();
	}

	private function verifyLedgerChecksums(array $migrations)
	{
		$pending = array();
		$stmt = $this->connection->prepare('SELECT checksum FROM schema_migrations WHERE version = ?');
		if (!$stmt) {
			throw new RuntimeException('Unable to inspect migration ledger.');
		}

		foreach ($migrations as $migration) {
			$version = $migration['version'];
			$stmt->bind_param('s', $version);
			if (!$stmt->execute()) {
				$stmt->close();
				throw new RuntimeException('Unable to inspect migration ledger.');
			}
			$stmt->bind_result($storedChecksum);
			$found = $stmt->fetch();
			$stmt->free_result();
			if ($found) {
				if (!hash_equals(strtolower($storedChecksum), strtolower($migration['checksum']))) {
					$stmt->close();
					throw new RuntimeException('Applied migration checksum differs from its current payload.');
				}
				continue;
			}
			$pending[] = $migration;
		}

		$stmt->close();
		return $pending;
	}
}
