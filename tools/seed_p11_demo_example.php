<?php
error_reporting(0);

$root = dirname(__DIR__);
if (PHP_SAPI !== 'cli' ||
	getenv('CI_ENV') !== 'testing' ||
	getenv('APOTEK_UNIT_EXAMPLE_SEED') !== '1') {
	fwrite(STDERR, "B2a example seeding requires explicit CLI test mode.\n");
	exit(64);
}

if (!defined('BASEPATH')) {
	define('BASEPATH', $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
if (!defined('APPPATH')) {
	define('APPPATH', $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR);
}

$configPath = APPPATH . 'config' . DIRECTORY_SEPARATOR . 'demo' . DIRECTORY_SEPARATOR . 'database.php';
if (!is_file($configPath)) {
	fwrite(STDERR, "Local demo database configuration is missing.\n");
	exit(65);
}
$db = array();
require $configPath;
if (!isset($db['default']['database']) || $db['default']['database'] !== 'db_apotek_online_demo') {
	fwrite(STDERR, "Demo example target guard failed.\n");
	exit(66);
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
	fwrite(STDERR, "Demo app connection failed.\n");
	exit(67);
}
$connection->set_charset('utf8mb4');
$identity = $connection->query('SELECT DATABASE(), CURRENT_USER()');
$row = $identity ? $identity->fetch_row() : null;
if (!$row || $row[0] !== 'db_apotek_online_demo' || strpos($row[1], 'apotek_demo_app@') !== 0) {
	$connection->close();
	fwrite(STDERR, "Demo example requires the demo app account.\n");
	exit(68);
}

require_once APPPATH . 'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
require_once APPPATH . 'libraries' . DIRECTORY_SEPARATOR . 'Product_unit_service.php';

try {
	$admin = $connection->query(
		"SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id = u.id " .
		"JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'administrator' " .
		"AND u.status = 'active' AND u.deleted_at IS NULL ORDER BY u.id LIMIT 1"
	);
	$adminRow = $admin ? $admin->fetch_assoc() : null;
	if (!$adminRow) {
		throw new RuntimeException('An active demo administrator is required for verification.');
	}
	$service = new Product_unit_service($connection, new Business_clock());
	$service->createDemoExample((int) $adminRow['id']);
	echo "demo_example=ready\n";
} catch (Throwable $error) {
	fwrite(STDERR, $error->getMessage() . PHP_EOL);
	$connection->close();
	exit(69);
}

$connection->close();
