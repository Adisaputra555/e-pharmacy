<?php
$root = dirname(__DIR__);
$testCaseClass = 'PHPUnit\\Framework\\TestCase';
if (!class_exists($testCaseClass, false) && class_exists('PHPUnit_Framework_TestCase')) {
	class_alias('PHPUnit_Framework_TestCase', $testCaseClass);
}

$localConfigPath = $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'config' . DIRECTORY_SEPARATOR . 'migration_runner.local.php';
$testingConfigPath = $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'config' . DIRECTORY_SEPARATOR . 'testing' . DIRECTORY_SEPARATOR . 'database.php';

if (PHP_SAPI !== 'cli' ||
	getenv('CI_ENV') !== 'testing' ||
	getenv('APOTEK_MIGRATIONS_TEST') !== '1' ||
	getenv('APOTEK_BUSINESS_CLOCK_TEST') !== '1' ||
	!is_file($localConfigPath) ||
	!is_file($testingConfigPath)) {
	fwrite(STDERR, "Tests require the explicit CLI scratch-only environment.\n");
	exit(80);
}

if (!defined('BASEPATH')) {
	define('BASEPATH', $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
if (!defined('APPPATH')) {
	define('APPPATH', $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR);
}
if (!defined('ENVIRONMENT')) {
	define('ENVIRONMENT', 'testing');
}

$localConfig = require $localConfigPath;
if (!is_array($localConfig) ||
	!isset($localConfig['database']) ||
	$localConfig['database'] !== 'db_apotek_online_scratch_p1') {
	fwrite(STDERR, "Refusing tests: migrator config does not name the approved scratch database.\n");
	exit(81);
}
$requestedDatabase = getenv('APOTEK_MIGRATIONS_TEST_DATABASE_OVERRIDE');
if ($requestedDatabase !== false && $requestedDatabase !== 'db_apotek_online_scratch_p1') {
	fwrite(STDERR, "Refusing tests: migrator config does not name the approved scratch database.\n");
	exit(81);
}

$db = array();
require $testingConfigPath;
if (!isset($db['default']['database']) ||
	$db['default']['database'] !== 'db_apotek_online_scratch_p1') {
	fwrite(STDERR, "Refusing tests: DML test config does not name the approved scratch database.\n");
	exit(82);
}

mysqli_report(MYSQLI_REPORT_OFF);
$migrator = @new mysqli(
	$localConfig['hostname'],
	$localConfig['username'],
	$localConfig['password'],
	$localConfig['database'],
	isset($localConfig['port']) ? (int) $localConfig['port'] : 3306
);
if ($migrator->connect_errno) {
	fwrite(STDERR, "Refusing tests: scratch migrator connection failed.\n");
	exit(83);
}

$dmlConfig = $db['default'];
$dml = @new mysqli(
	$dmlConfig['hostname'],
	$dmlConfig['username'],
	$dmlConfig['password'],
	$dmlConfig['database'],
	isset($dmlConfig['port']) ? (int) $dmlConfig['port'] : 3306
);
if ($dml->connect_errno) {
	$migrator->close();
	fwrite(STDERR, "Refusing tests: scratch DML connection failed.\n");
	exit(84);
}

foreach (array($migrator, $dml) as $connection) {
	$identity = $connection->query('SELECT DATABASE(), CURRENT_USER()');
	$row = $identity ? $identity->fetch_row() : null;
	if (!$row || $row[0] !== 'db_apotek_online_scratch_p1') {
		$migrator->close();
		$dml->close();
		fwrite(STDERR, "Refusing tests: a database connection is not the approved scratch DB.\n");
		exit(85);
	}
	$expectedUser = $connection === $migrator ? 'apotek_scratch_migrator@' : 'apotek_scratch_p1@';
	if (strpos($row[1], $expectedUser) !== 0) {
		$migrator->close();
		$dml->close();
		fwrite(STDERR, "Refusing tests: a database connection is not the approved scratch account.\n");
		exit(86);
	}
	$connection->set_charset('utf8mb4');
}

require_once APPPATH . 'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
require_once APPPATH . 'libraries' . DIRECTORY_SEPARATOR . 'Migration_runner.php';

$GLOBALS['p0b1_local_config'] = $localConfig;
$GLOBALS['p0b1_migrator_db'] = $migrator;
$GLOBALS['p0b1_dml_db'] = $dml;
$GLOBALS['p0b1_dml_user'] = $dmlConfig['username'];
$GLOBALS['p0b1_dml_password'] = $dmlConfig['password'];
