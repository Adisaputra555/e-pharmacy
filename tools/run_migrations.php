<?php
if (PHP_SAPI !== 'cli') {
	exit(64);
}

$root = dirname(__DIR__);
$localConfigPath = $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'config' . DIRECTORY_SEPARATOR . 'migration_runner.local.php';
if (getenv('CI_ENV') !== 'testing' ||
	getenv('APOTEK_MIGRATIONS_TEST') !== '1' ||
	!is_file($localConfigPath)) {
	fwrite(STDERR, "Migration runner is disabled; use the explicit CLI scratch test mode.\n");
	exit(65);
}

define('BASEPATH', $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
define('APPPATH', $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'testing');
require_once APPPATH . 'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
require_once APPPATH . 'libraries' . DIRECTORY_SEPARATOR . 'Migration_runner.php';

$config = require $localConfigPath;
if (!is_array($config) ||
	!isset($config['hostname'], $config['username'], $config['password'], $config['database']) ||
	$config['database'] !== Migration_runner::TARGET_DATABASE) {
	fwrite(STDERR, "Migration runner refuses an invalid scratch configuration.\n");
	exit(66);
}

mysqli_report(MYSQLI_REPORT_OFF);
$connection = @new mysqli(
	$config['hostname'],
	$config['username'],
	$config['password'],
	$config['database'],
	isset($config['port']) ? (int) $config['port'] : 3306
);
if ($connection->connect_errno) {
	fwrite(STDERR, "Migration runner could not connect to the configured scratch database.\n");
	exit(67);
}

try {
	$runner = new Migration_runner($connection, $config, new Business_clock());
	$migrations = $runner->loadMigrations(APPPATH . 'migrations');
	$dryRun = in_array('--dry-run', $argv, true);
	$allowDestructive = in_array('--allow-destructive', $argv, true);
	if ($dryRun) {
		$validated = $runner->dryRun($migrations, $allowDestructive);
		echo 'Dry-run passed lint for ' . count($validated) . " migration(s); no writes performed.\n";
	} else {
		$count = $runner->apply($migrations, $allowDestructive);
		echo 'Applied ' . $count . " migration(s) to scratch.\n";
	}
} catch (Throwable $error) {
	fwrite(STDERR, $error->getMessage() . PHP_EOL);
	$connection->close();
	exit(68);
}

$connection->close();
