<?php
error_reporting(0);

$root = dirname(__DIR__);
$target = isset($argv[1]) ? $argv[1] : '';
$operation = isset($argv[2]) ? $argv[2] : '--dry-run';
if (!in_array($target, array('scratch', 'demo'), true) ||
	!in_array($operation, array('--dry-run', '--apply'), true)) {
	fwrite(STDERR, "Usage: php tools/run_p11_migrations.php scratch|demo --dry-run|--apply\n");
	exit(64);
}
if (PHP_SAPI !== 'cli' ||
	getenv('CI_ENV') !== 'testing' ||
	getenv('APOTEK_UNIT_MIGRATIONS_TEST') !== '1') {
	fwrite(STDERR, "Unit migrations require explicit CLI test mode.\n");
	exit(65);
}

if (!defined('BASEPATH')) {
	define('BASEPATH', $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
if (!defined('APPPATH')) {
	define('APPPATH', $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR);
}

$localPath = APPPATH . 'config' . DIRECTORY_SEPARATOR . 'migration_runner.local.php';
if (!is_file($localPath)) {
	fwrite(STDERR, "Local migration configuration is missing.\n");
	exit(66);
}
$local = require $localPath;
if (!is_array($local) || !isset($local['database']) ||
	$local['database'] !== 'db_apotek_online_scratch_p1') {
	fwrite(STDERR, "Local scratch migration configuration failed its target guard.\n");
	exit(67);
}

if ($target === 'scratch') {
	$config = $local;
} else {
	$demoPath = APPPATH . 'config' . DIRECTORY_SEPARATOR . 'demo' . DIRECTORY_SEPARATOR . 'database.php';
	if (!is_file($demoPath)) {
		fwrite(STDERR, "Local demo migration configuration is missing.\n");
		exit(68);
	}
	$db = array();
	require $demoPath;
	if (!isset($db['migrator']) || $db['migrator']['database'] !== 'db_apotek_online_demo') {
		fwrite(STDERR, "Local demo migration configuration failed its target guard.\n");
		exit(69);
	}
	$config = $db['migrator'];
	foreach (array('backup_archive_path', 'backup_manifest_path', 'backup_report_path', 'backup_report_sha256', 'lock_timeout') as $key) {
		if (isset($local[$key])) {
			$config[$key] = $local[$key];
		}
	}
}
$config['migration_web_enabled'] = false;

mysqli_report(MYSQLI_REPORT_OFF);
$connection = @new mysqli(
	$config['hostname'],
	$config['username'],
	$config['password'],
	$config['database'],
	isset($config['port']) ? (int) $config['port'] : 3306
);
if ($connection->connect_errno) {
	fwrite(STDERR, "Unable to connect using the guarded migration account.\n");
	exit(70);
}
$connection->set_charset('utf8mb4');

require_once APPPATH . 'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
require_once APPPATH . 'libraries' . DIRECTORY_SEPARATOR . 'Migration_runner.php';
require_once APPPATH . 'libraries' . DIRECTORY_SEPARATOR . 'Unit_migration_runner.php';

try {
	$runner = new Unit_migration_runner($connection, $config, new Business_clock());
	$migrations = $runner->loadMigrations(APPPATH . 'migrations');
	$count = $operation === '--apply'
		? $runner->apply($migrations)
		: count($runner->dryRun($migrations));
	echo $operation === '--apply'
		? 'applied=' . $count . PHP_EOL
		: 'validated=' . $count . PHP_EOL;
} catch (Throwable $error) {
	fwrite(STDERR, $error->getMessage() . PHP_EOL);
	$connection->close();
	exit(71);
}

$connection->close();
