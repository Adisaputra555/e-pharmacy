<?php
error_reporting(0);

$root = dirname(__DIR__);
if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "Demo rebuild is CLI-only.\n");
	exit(64);
}

$mode = null;
$today = null;
foreach (array_slice($argv, 1) as $argument) {
	if ($argument === '--dry-run' || $argument === '--rebuild') {
		if ($mode !== null) {
			fwrite(STDERR, "Choose exactly one rebuild mode.\n");
			exit(64);
		}
		$mode = $argument;
	} elseif (strpos($argument, '--today=') === 0) {
		$today = substr($argument, 8);
	}
}
if ($mode === null) {
	fwrite(STDERR, "Usage: php tools/rebuild_demo.php --dry-run|--rebuild [--today=YYYY-MM-DD]\n");
	exit(64);
}
$today = $today ?: '2026-11-22';
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $today, new DateTimeZone('Asia/Jakarta'));
if (!$date || $date->format('Y-m-d') !== $today) {
	fwrite(STDERR, "The business date must use YYYY-MM-DD.\n");
	exit(64);
}

if (!defined('BASEPATH')) {
	define('BASEPATH', $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
if (!defined('ENVIRONMENT')) {
	define('ENVIRONMENT', 'production');
}

$db = array();
require $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
$config = $db['default'];
if (!isset($config['database']) || $config['database'] !== 'db_apotek_online') {
	fwrite(STDERR, "Rebuild refuses a connection configured for a non-baseline database.\n");
	exit(65);
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
	fwrite(STDERR, "Unable to connect for read-only baseline schema inspection.\n");
	exit(66);
}
$identity = $connection->query('SELECT DATABASE()');
$identity = $identity ? $identity->fetch_row() : null;
if (!$identity || $identity[0] !== 'db_apotek_online') {
	$connection->close();
	fwrite(STDERR, "Rebuild connection is not the approved baseline database.\n");
	exit(67);
}

require_once $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
require_once $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Demo_database_rebuilder.php';

$clock = new Business_clock(function () use ($date) {
	return $date->setTime(12, 0, 0);
});
try {
	$rebuilder = new Demo_database_rebuilder($connection, $clock);
	$result = $mode === '--rebuild' ? $rebuilder->rebuild() : $rebuilder->preflight();
	echo 'mode=' . ($mode === '--rebuild' ? 'rebuild' : 'dry-run') . PHP_EOL;
	echo 'baseline_structure_tables=' . $result['table_count'] . PHP_EOL;
	foreach ($result['reference_counts'] as $table => $count) {
		echo $table . '=' . $count . PHP_EOL;
	}
	if (isset($result['marker'])) {
		echo 'environment_marker=demo' . PHP_EOL;
	}
} catch (Throwable $error) {
	fwrite(STDERR, $error->getMessage() . PHP_EOL);
	$connection->close();
	exit(68);
}

$connection->close();
