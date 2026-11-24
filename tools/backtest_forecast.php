<?php
error_reporting(E_ALL);

$root = dirname(__DIR__);
if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "Forecast backtest is CLI-only.\n");
	exit(64);
}
if (!defined('BASEPATH')) {
	define('BASEPATH', $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
if (!defined('ENVIRONMENT')) {
	define('ENVIRONMENT', 'demo');
}
require_once $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
require_once $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Forecast_service.php';

$db = array();
$configPath = $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'config' . DIRECTORY_SEPARATOR . 'demo' . DIRECTORY_SEPARATOR . 'database.php';
if (!is_file($configPath)) {
	fwrite(STDERR, "Demo database configuration is unavailable.\n");
	exit(65);
}
require $configPath;
if (!isset($db['default']['database']) || $db['default']['database'] !== 'db_apotek_online_demo') {
	fwrite(STDERR, "Backtest refuses a non-demo database target.\n");
	exit(66);
}

$asOf = null;
foreach (array_slice($argv, 1) as $argument) {
	if (strpos($argument, '--today=') !== 0) {
		fwrite(STDERR, "Supported option: --today=YYYY-MM-DD\n");
		exit(67);
	}
	$dateValue = substr($argument, 8);
	$date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue, new DateTimeZone('Asia/Jakarta'));
	$errors = DateTimeImmutable::getLastErrors();
	if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count'])) ||
		$date->format('Y-m-d') !== $dateValue) {
		fwrite(STDERR, "The business date must be a valid YYYY-MM-DD value.\n");
		exit(68);
	}
	$asOf = $date->setTime(12, 0, 0);
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
	fwrite(STDERR, "Unable to connect using the demo application account.\n");
	exit(69);
}
$identityResult = $connection->query('SELECT DATABASE(), CURRENT_USER()');
$identity = $identityResult ? $identityResult->fetch_row() : null;
$markerResult = $connection->query('SELECT environment FROM environment_marker WHERE id = 1');
$marker = $markerResult ? $markerResult->fetch_row() : null;
if (!$identity || $identity[0] !== 'db_apotek_online_demo' ||
	strpos($identity[1], 'apotek_demo_app@') !== 0 ||
	!$marker || $marker[0] !== 'demo') {
	$connection->close();
	fwrite(STDERR, "Backtest refused: database identity or demo marker did not match.\n");
	exit(70);
}
if (!$connection->query('START TRANSACTION READ ONLY')) {
	$connection->close();
	fwrite(STDERR, "Unable to establish a read-only demo transaction.\n");
	exit(71);
}

$clock = $asOf
	? new Business_clock(function () use ($asOf) { return $asOf; })
	: new Business_clock();
$service = new Forecast_service($connection, $clock);
$forecast = $service->getDashboardForecast();
$backtest = $service->getBacktestReport();

echo "B3 scenario status (demo only)\n";
echo "scenario\tstatus\tcoverage\tusable_stock\tred_risk\tyellow_risk\n";
foreach ($forecast['products'] as $product) {
	if ($product['scenario_tag'] !== null) {
		echo $product['scenario_tag'] . "\t" . $product['data_status'] . "\t" . $product['coverage_status'] . "\t" .
			number_format($product['usable_stock'], 3, '.', '') . "\t" .
			number_format($product['red_risk_quantity'], 3, '.', '') . "\t" .
			number_format($product['yellow_risk_quantity'], 3, '.', '') . PHP_EOL;
	}
}
echo "status_distribution\n";
foreach ($forecast['status_counts'] as $status => $count) {
	echo $status . "\t" . $count . PHP_EOL;
}

echo PHP_EOL . "Rolling-origin backtest (synthetic demo data; not production accuracy)\n";
echo "method=" . $backtest['method'] . PHP_EOL;
echo "period=" . $backtest['months'][0] . " through " . $backtest['months'][count($backtest['months']) - 1] . PHP_EOL;
echo "eligible_products=" . $backtest['eligible_products'] . " excluded_products=" . $backtest['excluded_products'] . PHP_EOL;
echo "predictions=" . $backtest['predictions'] . PHP_EOL;
echo "aggregate_MAE=" . ($backtest['mae'] === null ? 'n/a' : number_format($backtest['mae'], 3, '.', '')) . PHP_EOL;
echo "aggregate_WAPE=" . ($backtest['wape'] === null ? 'n/a' : number_format($backtest['wape'] * 100, 2, '.', '') . '%') . PHP_EOL;
echo "product\tpredictions\tMAE\tWAPE\n";
foreach ($backtest['products'] as $product) {
	echo $product['code'] . "\t" . $product['predictions'] . "\t" .
		number_format($product['mae'], 3, '.', '') . "\t" .
		($product['wape'] === null ? 'n/a' : number_format($product['wape'] * 100, 2, '.', '') . '%') . PHP_EOL;
}

$connection->rollback();
$connection->close();
