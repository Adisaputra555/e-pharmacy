<?php
error_reporting(0);

$root = dirname(__DIR__);
if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "Baseline fingerprint is CLI-only.\n");
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
	fwrite(STDERR, "Fingerprint refuses a non-baseline configured database.\n");
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
	fwrite(STDERR, "Unable to connect for read-only baseline fingerprint.\n");
	exit(66);
}

$identity = $connection->query('SELECT DATABASE()');
$identity = $identity ? $identity->fetch_row() : null;
if (!$identity || $identity[0] !== 'db_apotek_online') {
	$connection->close();
	fwrite(STDERR, "Fingerprint connection is not the approved baseline database.\n");
	exit(67);
}
$connection->set_charset('utf8mb4');
$connection->query('SET SESSION group_concat_max_len = 1048576');

$tableResult = $connection->query(
	"SELECT TABLE_NAME FROM information_schema.TABLES " .
	"WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"
);
if (!$tableResult) {
	$connection->close();
	fwrite(STDERR, "Unable to enumerate baseline table metadata.\n");
	exit(68);
}

$full = array();
$stable = array();
$fullRows = 0;
$stableRows = 0;
while ($tableRow = $tableResult->fetch_row()) {
	$table = $tableRow[0];
	$columnStatement = $connection->prepare(
		'SELECT COLUMN_NAME FROM information_schema.COLUMNS ' .
		'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
	);
	if (!$columnStatement) {
		$tableResult->free();
		$connection->close();
		fwrite(STDERR, "Unable to prepare baseline column metadata query.\n");
		exit(69);
	}
	$columnStatement->bind_param('s', $table);
	if (!$columnStatement->execute()) {
		$columnStatement->close();
		$tableResult->free();
		$connection->close();
		fwrite(STDERR, "Unable to read baseline column metadata.\n");
		exit(70);
	}
	$columnResult = $columnStatement->get_result();
	$columns = array();
	while ($columnRow = $columnResult->fetch_row()) {
		$columns[] = $columnRow[0];
	}
	$columnResult->free();
	$columnStatement->close();

	$full[$table] = fingerprintTable($connection, $table, $columns);
	$fullRows += $full[$table]['rows'];
	if ($table !== 'activity_logs') {
		$stableColumns = array_values(array_filter($columns, function ($column) use ($table) {
			return !($table === 'users' && $column === 'last_login_at');
		}));
		$stable[$table] = fingerprintTable($connection, $table, $stableColumns);
		$stableRows += $stable[$table]['rows'];
	}
}
$tableResult->free();

$aggregate = function (array $tables) {
	$lines = array();
	foreach ($tables as $name => $fingerprint) {
		$lines[] = $name . "\t" . $fingerprint['rows'] . "\t" . $fingerprint['sha256'];
	}
	return hash('sha256', implode("\n", $lines));
};
$summary = array(
	'database' => 'db_apotek_online',
	'full_tables' => count($full),
	'full_rows' => $fullRows,
	'full_sha256' => $aggregate($full),
	'stable_tables' => count($stable),
	'stable_rows' => $stableRows,
	'stable_sha256' => $aggregate($stable)
);

$outputPath = null;
foreach (array_slice($argv, 1) as $argument) {
	if (strpos($argument, '--output=') === 0) {
		$outputPath = substr($argument, 9);
	}
}
if ($outputPath !== null) {
	$resolved = realpath(dirname($outputPath));
	if ($resolved === false || strcasecmp($resolved, realpath(sys_get_temp_dir())) !== 0) {
		$connection->close();
		fwrite(STDERR, "Fingerprint snapshot may only be written under the system Temp directory.\n");
		exit(71);
	}
	$payload = array('summary' => $summary, 'full' => $full, 'stable' => $stable);
	if (file_put_contents($outputPath, json_encode($payload)) === false) {
		$connection->close();
		fwrite(STDERR, "Unable to save the temporary fingerprint snapshot.\n");
		exit(72);
	}
}

echo json_encode($summary, JSON_UNESCAPED_SLASHES) . PHP_EOL;
$connection->close();

function fingerprintTable($connection, $table, array $columns)
{
	if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !$columns) {
		throw new RuntimeException('Baseline fingerprint table metadata is invalid.');
	}
	$parts = array();
	foreach ($columns as $column) {
		if (!preg_match('/^[A-Za-z0-9_]+$/', $column)) {
			throw new RuntimeException('Baseline fingerprint column metadata is invalid.');
		}
		$name = '`' . $column . '`';
		$parts[] = "IF(" . $name . " IS NULL,'N;',CONCAT('V',OCTET_LENGTH(CAST(" .
			$name . " AS BINARY)),':',HEX(CAST(" . $name . " AS BINARY)),';'))";
	}
	$sql = 'SELECT COUNT(*), SHA2(COALESCE(GROUP_CONCAT(row_digest ORDER BY row_digest SEPARATOR \'\'), \'\'), 256) ' .
		'FROM (SELECT SHA2(CONCAT(' . implode(',', $parts) . '), 256) AS row_digest ' .
		'FROM `' . $table . '`) AS row_hashes';
	$result = $connection->query($sql);
	if (!$result) {
		throw new RuntimeException('Unable to compute a server-side baseline table fingerprint.');
	}
	$row = $result->fetch_row();
	$result->free();
	return array('rows' => (int) $row[0], 'sha256' => $row[1]);
}
