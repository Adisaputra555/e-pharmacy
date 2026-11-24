<?php
error_reporting(0);

$root = dirname(__DIR__);
if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "Demo seeder is CLI-only.\n");
	exit(64);
}

$seed = null;
$today = null;
foreach (array_slice($argv, 1) as $argument) {
	if (strpos($argument, '--seed=') === 0) {
		$seed = substr($argument, 7);
	} elseif (strpos($argument, '--today=') === 0) {
		$today = substr($argument, 8);
	}
}
if ($seed === null || !preg_match('/^-?[0-9]{1,9}$/', $seed)) {
	fwrite(STDERR, "Usage: php tools/seed_demo.php --seed=INTEGER [--today=YYYY-MM-DD]\n");
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
if (!defined('APPPATH')) {
	define('APPPATH', $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR);
}
if (!defined('ENVIRONMENT')) {
	define('ENVIRONMENT', 'demo');
}
if (getenv('CI_ENV') !== 'demo') {
	fwrite(STDERR, "Demo seeding requires CI_ENV=demo.\n");
	exit(65);
}

$configPath = APPPATH . 'config' . DIRECTORY_SEPARATOR . 'demo' . DIRECTORY_SEPARATOR . 'database.php';
if (!is_file($configPath)) {
	fwrite(STDERR, "The local demo database configuration is missing.\n");
	exit(66);
}
$db = array();
require $configPath;
if (!isset($db['default']['database']) ||
	$db['default']['database'] !== 'db_apotek_online_demo') {
	fwrite(STDERR, "Seeder refuses a non-demo configured database.\n");
	exit(67);
}

$accountsPath = APPPATH . 'config' . DIRECTORY_SEPARATOR . 'secrets' .
	DIRECTORY_SEPARATOR . 'demo_accounts.txt';
if (!is_dir(dirname($accountsPath))) {
	fwrite(STDERR, "The local secrets directory is missing.\n");
	exit(68);
}

mysqli_report(MYSQLI_REPORT_OFF);
$config = $db['default'];
$connection = @new mysqli(
	$config['hostname'],
	$config['username'],
	$config['password'],
	$config['database'],
	isset($config['port']) ? (int) $config['port'] : 3306
);
if ($connection->connect_errno) {
	fwrite(STDERR, "Unable to connect with the demo app account.\n");
	exit(69);
}
$identity = $connection->query('SELECT DATABASE(), CURRENT_USER()');
$identity = $identity ? $identity->fetch_row() : null;
if (!$identity ||
	$identity[0] !== 'db_apotek_online_demo' ||
	strpos($identity[1], 'apotek_demo_app@') !== 0) {
	$connection->close();
	fwrite(STDERR, "Seeder requires the scoped demo app database account.\n");
	exit(70);
}
$connection->set_charset('utf8mb4');

require_once APPPATH . 'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
require_once APPPATH . 'libraries' . DIRECTORY_SEPARATOR . 'Product_unit_service.php';
require_once APPPATH . 'libraries' . DIRECTORY_SEPARATOR . 'Demo_seeder.php';

$businessDate = $date->setTime(12, 0, 0);
$clock = new Business_clock(function () use ($businessDate) {
	return $businessDate;
});

try {
	$seeder = new Demo_seeder($connection, $clock, $businessDate);
	$result = $seeder->seed((int) $seed, $accountsPath);
	echo 'seed=' . (int) $seed . PHP_EOL;
	foreach ($result as $name => $count) {
		echo $name . '=' . (int) $count . PHP_EOL;
	}
	echo 'credentials_file_written=yes (contents withheld)' . PHP_EOL;
} catch (Throwable $error) {
	fwrite(STDERR, $error->getMessage() . PHP_EOL);
	$connection->close();
	exit(71);
}

$connection->close();
