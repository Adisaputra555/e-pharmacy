<?php

$root = dirname(__DIR__);
$testCaseClass = 'PHPUnit\\Framework\\TestCase';
if (!class_exists($testCaseClass, false) && class_exists('PHPUnit_Framework_TestCase')) {
	class_alias('PHPUnit_Framework_TestCase', $testCaseClass);
}

$configPath = $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'config' . DIRECTORY_SEPARATOR . 'demo' . DIRECTORY_SEPARATOR . 'database.php';
if (PHP_SAPI !== 'cli' || getenv('APOTEK_DEMO_TEST') !== '1' || !is_file($configPath)) {
	fwrite(STDERR, "Tests require the explicit CLI demo-only environment.\n");
	exit(90);
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

$db = array();
require $configPath;
if (!isset($db['default']['database'], $db['migrator']['database']) ||
	$db['default']['database'] !== 'db_apotek_online_demo' ||
	$db['migrator']['database'] !== 'db_apotek_online_demo') {
	fwrite(STDERR, "Refusing tests: both demo groups must target the demo database.\n");
	exit(91);
}

mysqli_report(MYSQLI_REPORT_OFF);
$connections = array();
foreach (array('default' => 'apotek_demo_app@', 'migrator' => 'apotek_demo_migrator@') as $group => $userPrefix) {
	$config = $db[$group];
	$connection = @new mysqli(
		$config['hostname'],
		$config['username'],
		$config['password'],
		$config['database'],
		isset($config['port']) ? (int) $config['port'] : 3306
	);
	if ($connection->connect_errno) {
		foreach ($connections as $opened) {
			$opened->close();
		}
		fwrite(STDERR, "Refusing tests: a demo database account could not connect.\n");
		exit(92);
	}

	$identity = $connection->query('SELECT DATABASE(), CURRENT_USER()');
	$row = $identity ? $identity->fetch_row() : null;
	if (!$row || $row[0] !== 'db_apotek_online_demo' || strpos($row[1], $userPrefix) !== 0) {
		$connection->close();
		foreach ($connections as $opened) {
			$opened->close();
		}
		fwrite(STDERR, "Refusing tests: a connection is not the expected demo account.\n");
		exit(93);
	}

	$connection->set_charset('utf8mb4');
	$connections[$group] = $connection;
}

$GLOBALS['b1_demo_config'] = $db;
$GLOBALS['b1_demo_app_db'] = $connections['default'];
$GLOBALS['b1_demo_migrator_db'] = $connections['migrator'];
