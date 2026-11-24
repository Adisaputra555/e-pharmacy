<?php
error_reporting(0);

$root = dirname(__DIR__);
if (PHP_SAPI !== 'cli' || getenv('CI_ENV') !== 'demo') {
	fwrite(STDERR, "Demo data fingerprint requires CLI with CI_ENV=demo.\n");
	exit(64);
}
if (!defined('BASEPATH')) {
	define('BASEPATH', $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
if (!defined('ENVIRONMENT')) {
	define('ENVIRONMENT', 'demo');
}

$db = array();
$configPath = $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'config' . DIRECTORY_SEPARATOR . 'demo' . DIRECTORY_SEPARATOR . 'database.php';
if (!is_file($configPath)) {
	fwrite(STDERR, "The local demo database configuration is missing.\n");
	exit(65);
}
require $configPath;
if (!isset($db['default']['database']) ||
	$db['default']['database'] !== 'db_apotek_online_demo') {
	fwrite(STDERR, "Demo data fingerprint refuses a non-demo database.\n");
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
	fwrite(STDERR, "Unable to connect with the scoped demo app account.\n");
	exit(67);
}
$identity = $connection->query('SELECT DATABASE(), CURRENT_USER()');
$identity = $identity ? $identity->fetch_row() : null;
if (!$identity ||
	$identity[0] !== 'db_apotek_online_demo' ||
	strpos($identity[1], 'apotek_demo_app@') !== 0) {
	$connection->close();
	fwrite(STDERR, "Demo data fingerprint requires the scoped demo app account.\n");
	exit(68);
}
$connection->set_charset('utf8mb4');
$connection->query('SET SESSION group_concat_max_len = 1048576');

$tablesResult = $connection->query(
	"SELECT TABLE_NAME FROM information_schema.TABLES " .
	"WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"
);
if (!$tablesResult) {
	$connection->close();
	fwrite(STDERR, "Unable to enumerate demo table metadata.\n");
	exit(69);
}

$tables = array();
while ($tableRow = $tablesResult->fetch_row()) {
	$table = $tableRow[0];
	if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
		$tablesResult->free();
		$connection->close();
		fwrite(STDERR, "Demo table metadata contains an invalid name.\n");
		exit(70);
	}
	$stmt = $connection->prepare(
		'SELECT COLUMN_NAME FROM information_schema.COLUMNS ' .
		'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
	);
	if (!$stmt) {
		$tablesResult->free();
		$connection->close();
		fwrite(STDERR, "Unable to prepare demo column metadata query.\n");
		exit(71);
	}
	$stmt->bind_param('s', $table);
	if (!$stmt->execute()) {
		$stmt->close();
		$tablesResult->free();
		$connection->close();
		fwrite(STDERR, "Unable to read demo column metadata.\n");
		exit(72);
	}
	$columnResult = $stmt->get_result();
	$columns = array();
	while ($columnRow = $columnResult->fetch_row()) {
		if (!preg_match('/^[A-Za-z0-9_]+$/', $columnRow[0])) {
			$columnResult->free();
			$stmt->close();
			$tablesResult->free();
			$connection->close();
			fwrite(STDERR, "Demo column metadata contains an invalid name.\n");
			exit(73);
		}
		$sensitiveColumn = preg_match('/(password|hash|email|token|secret|credential)/i', $columnRow[0]);
		$volatileMigrationTimestamp = $table === 'schema_migrations' && $columnRow[0] === 'applied_at';
		if (!$sensitiveColumn && !$volatileMigrationTimestamp) {
			$columns[] = $columnRow[0];
		}
	}
	$columnResult->free();
	$stmt->close();
	$tables[$table] = fingerprintTable($connection, $table, $columns);
}
$tablesResult->free();

$lines = array();
$totalRows = 0;
foreach ($tables as $table => $fingerprint) {
	$lines[] = $table . "\t" . $fingerprint['rows'] . "\t" . $fingerprint['sha256'];
	$totalRows += $fingerprint['rows'];
}
$summary = array(
	'tables' => count($tables),
	'rows' => $totalRows,
	'sha256' => hash('sha256', implode("\n", $lines))
);

$outputPath = null;
foreach (array_slice($argv, 1) as $argument) {
	if (strpos($argument, '--output=') === 0) {
		$outputPath = substr($argument, 9);
	}
}
if ($outputPath !== null) {
	$tempPath = realpath(sys_get_temp_dir());
	$targetDirectory = realpath(dirname($outputPath));
	if ($tempPath === false || $targetDirectory === false ||
		strcasecmp($targetDirectory, $tempPath) !== 0) {
		$connection->close();
		fwrite(STDERR, "Demo checksum snapshot may only be saved under system Temp.\n");
		exit(74);
	}
	$payload = json_encode(array('summary' => $summary, 'tables' => $tables));
	if ($payload === false || file_put_contents($outputPath, $payload) === false) {
		$connection->close();
		fwrite(STDERR, "Unable to write the temporary demo checksum snapshot.\n");
		exit(75);
	}
}

echo json_encode($summary, JSON_UNESCAPED_SLASHES) . PHP_EOL;
$connection->close();

function fingerprintTable($connection, $table, array $columns)
{
	if (!$columns) {
		$result = $connection->query(
			"SELECT COUNT(*), SHA2('excluded-sensitive-columns', 256) FROM `" . $table . '`'
		);
		if (!$result) {
			throw new RuntimeException('Unable to count demo rows without reading sensitive columns.');
		}
		$row = $result->fetch_row();
		$result->free();
		return array('rows' => (int) $row[0], 'sha256' => $row[1]);
	}
	$parts = array();
	foreach ($columns as $column) {
		$name = '`' . $column . '`';
		$parts[] = "IF(" . $name . " IS NULL,'N;',CONCAT('V',OCTET_LENGTH(CAST(" .
			$name . " AS BINARY)),':',HEX(CAST(" . $name . " AS BINARY)),';'))";
	}
	$sql = 'SELECT COUNT(*), SHA2(COALESCE(GROUP_CONCAT(row_digest ORDER BY row_digest SEPARATOR \'\'), \'\'), 256) ' .
		'FROM (SELECT SHA2(CONCAT(' . implode(',', $parts) . '), 256) AS row_digest ' .
		'FROM `' . $table . '`) AS row_hashes';
	$result = $connection->query($sql);
	if (!$result) {
		throw new RuntimeException('Unable to compute a server-side demo table checksum.');
	}
	$row = $result->fetch_row();
	$result->free();
	return array('rows' => (int) $row[0], 'sha256' => $row[1]);
}
