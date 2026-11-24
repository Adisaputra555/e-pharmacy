<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Demo_database_rebuilder
{
	const BASELINE = 'db_apotek_online';
	const DEMO = 'db_apotek_online_demo';

	private $connection;
	private $clock;

	private $referenceTables = array(
		'roles',
		'role_permissions',
		'units',
		'categories',
		'payment_methods',
		'shipping_methods',
		'store_settings',
		'manufacturers'
	);

	public function __construct($connection, Business_clock $clock)
	{
		if (!($connection instanceof mysqli)) {
			throw new InvalidArgumentException('Demo rebuild requires a mysqli connection.');
		}
		$this->connection = $connection;
		$this->clock = $clock;
	}

	public function preflight()
	{
		if ($this->databaseName() !== self::BASELINE) {
			throw new RuntimeException('Rebuild connection must select the approved baseline database.');
		}
		$this->tables(self::BASELINE);
		$charset = $this->firstValue(
			'SELECT DEFAULT_CHARACTER_SET_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
			self::BASELINE
		);
		$collation = $this->firstValue(
			'SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
			self::BASELINE
		);
		if (!preg_match('/^[a-zA-Z0-9_]+$/', (string) $charset) ||
			!preg_match('/^[a-zA-Z0-9_]+$/', (string) $collation)) {
			throw new RuntimeException('Baseline schema encoding metadata is invalid.');
		}

		$sourceTables = $this->dependencyOrder();
		foreach ($sourceTables as $table) {
			$this->showCreateTable($table);
		}
		foreach ($this->referenceTables as $table) {
			if (!in_array($table, $sourceTables, true)) {
				throw new RuntimeException('An approved reference table is missing from baseline.');
			}
		}

		return array(
			'table_count' => count($sourceTables),
			'reference_counts' => $this->referenceCounts()
		);
	}

	public function rebuild()
	{
		$preflight = $this->preflight();
		$charset = $this->firstValue(
			'SELECT DEFAULT_CHARACTER_SET_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
			self::BASELINE
		);
		$collation = $this->firstValue(
			'SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
			self::BASELINE
		);

		$target = self::DEMO;
		if ($target !== 'db_apotek_online_demo') {
			throw new RuntimeException('Destructive rebuild target guard failed.');
		}
		if (!$this->connection->query('DROP DATABASE IF EXISTS `db_apotek_online_demo`')) {
			throw new RuntimeException('Unable to drop the exact demo database.');
		}
		if (!$this->connection->query(
			'CREATE DATABASE `db_apotek_online_demo` CHARACTER SET ' . $charset . ' COLLATE ' . $collation
		)) {
			throw new RuntimeException('Unable to create the demo database.');
		}

		$tables = $this->dependencyOrder();
		if (!$this->connection->query('SET FOREIGN_KEY_CHECKS = 0')) {
			throw new RuntimeException('Unable to suspend demo foreign-key checks for schema restore.');
		}
		foreach ($tables as $table) {
			$ddl = $this->showCreateTable($table);
			if (!$this->connection->query('USE `db_apotek_online_demo`') ||
				!$this->connection->query($ddl)) {
				throw new RuntimeException('Unable to restore a baseline table structure into demo.');
			}
		}

		$copied = array();
		foreach ($this->referenceTables as $table) {
			$this->copyReferenceTable($table);
			$copied[$table] = $this->countRows(self::DEMO, $table);
		}

		if (!$this->connection->query(
			'CREATE TABLE environment_marker (' .
			'id TINYINT UNSIGNED NOT NULL PRIMARY KEY, ' .
			'environment VARCHAR(16) NOT NULL, ' .
			'created_at DATETIME NOT NULL' .
			') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
		)) {
			throw new RuntimeException('Unable to create the demo environment marker.');
		}
		$now = $this->clock->now()->format('Y-m-d H:i:s');
		$stmt = $this->connection->prepare(
			'INSERT INTO db_apotek_online_demo.environment_marker (id, environment, created_at) VALUES (1, ?, ?)'
		);
		if (!$stmt) {
			throw new RuntimeException('Unable to prepare the demo environment marker.');
		}
		$marker = 'demo';
		$stmt->bind_param('ss', $marker, $now);
		if (!$stmt->execute()) {
			$stmt->close();
			throw new RuntimeException('Unable to write the demo environment marker.');
		}
		$stmt->close();

		if (!$this->connection->query('SET FOREIGN_KEY_CHECKS = 1')) {
			throw new RuntimeException('Unable to restore foreign-key checks.');
		}
		if (!$this->connection->select_db('db_apotek_online')) {
			throw new RuntimeException('Unable to restore the baseline connection selection.');
		}

		return array(
			'table_count' => $preflight['table_count'],
			'reference_counts' => $copied,
			'marker' => 'demo'
		);
	}

	private function copyReferenceTable($table)
	{
		if (!in_array($table, $this->referenceTables, true)) {
			throw new RuntimeException('Reference-table copy allowlist rejected a table.');
		}

		if ($table === 'manufacturers') {
			$sql = 'INSERT INTO db_apotek_online_demo.manufacturers ' .
				'(id, name, country, phone, address, is_active, created_at, updated_at, deleted_at) ' .
				'SELECT id, name, country, phone, address, is_active, created_at, updated_at, deleted_at ' .
				'FROM db_apotek_online.manufacturers';
		} elseif ($table === 'store_settings') {
			$sql = 'INSERT INTO db_apotek_online_demo.store_settings ' .
				'(id, setting_key, setting_value, is_public, created_at, updated_at) ' .
				'SELECT id, setting_key, ' .
				'CASE WHEN LOWER(setting_key) REGEXP ' .
				'\'(email|pass|hash|secret|token|credential|api|key)\' ' .
				'OR setting_value REGEXP ' .
				'\'([[:alnum:]._%+-]+@[[:alnum:].-]+\\.[[:alpha:]]{2,}|\\$2[aby]\\$|argon2)\' ' .
				'THEN NULL ELSE setting_value END, is_public, created_at, updated_at ' .
				'FROM db_apotek_online.store_settings';
		} else {
			$sql = 'INSERT INTO db_apotek_online_demo.`' . $table . '` ' .
				'SELECT * FROM db_apotek_online.`' . $table . '`';
		}

		if (!$this->connection->query($sql)) {
			throw new RuntimeException('Unable to copy an approved reference table into demo.');
		}
	}

	private function referenceCounts()
	{
		$counts = array();
		foreach ($this->referenceTables as $table) {
			$counts[$table] = $this->countRows(self::BASELINE, $table);
		}
		return $counts;
	}

	private function countRows($database, $table)
	{
		if (!in_array($database, array(self::BASELINE, self::DEMO), true) ||
			!in_array($table, $this->referenceTables, true)) {
			throw new RuntimeException('Row-count query target is not allowlisted.');
		}
		$result = $this->connection->query(
			'SELECT COUNT(*) FROM `' . $database . '`.`' . $table . '`'
		);
		if (!$result) {
			throw new RuntimeException('Unable to count approved reference rows.');
		}
		$row = $result->fetch_row();
		$result->free();
		return (int) $row[0];
	}

	private function showCreateTable($table)
	{
		if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
			throw new RuntimeException('Baseline table metadata contains an invalid name.');
		}
		$result = $this->connection->query(
			'SHOW CREATE TABLE `db_apotek_online`.`' . $table . '`'
		);
		if (!$result) {
			throw new RuntimeException('Unable to read baseline table structure.');
		}
		$row = $result->fetch_row();
		$result->free();
		if (!$row || !isset($row[1]) || stripos($row[1], 'CREATE TABLE') !== 0) {
			throw new RuntimeException('Baseline table structure metadata is invalid.');
		}
		return $row[1];
	}

	private function tables($database)
	{
		if ($database !== self::BASELINE) {
			throw new RuntimeException('Table metadata query is restricted to baseline.');
		}
		$stmt = $this->connection->prepare(
			'SELECT TABLE_NAME FROM information_schema.TABLES ' .
			'WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME'
		);
		if (!$stmt) {
			throw new RuntimeException('Unable to prepare baseline table metadata query.');
		}
		$type = 'BASE TABLE';
		$stmt->bind_param('ss', $database, $type);
		if (!$stmt->execute()) {
			$stmt->close();
			throw new RuntimeException('Unable to read baseline table metadata.');
		}
		$result = $stmt->get_result();
		$tables = array();
		while ($row = $result->fetch_row()) {
			$tables[] = $row[0];
		}
		$result->free();
		$stmt->close();
		return $tables;
	}

	private function dependencyOrder()
	{
		$tables = $this->tables(self::BASELINE);
		$dependencies = array_fill_keys($tables, array());
		$result = $this->connection->query(
			"SELECT TABLE_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE " .
			"WHERE TABLE_SCHEMA = 'db_apotek_online' AND REFERENCED_TABLE_SCHEMA = 'db_apotek_online' " .
			"AND REFERENCED_TABLE_NAME IS NOT NULL"
		);
		if (!$result) {
			throw new RuntimeException('Unable to inspect baseline table dependencies.');
		}
		while ($row = $result->fetch_assoc()) {
			if ($row['TABLE_NAME'] !== $row['REFERENCED_TABLE_NAME'] &&
				isset($dependencies[$row['TABLE_NAME']], $dependencies[$row['REFERENCED_TABLE_NAME']])) {
				$dependencies[$row['TABLE_NAME']][$row['REFERENCED_TABLE_NAME']] = true;
			}
		}
		$result->free();

		$ordered = array();
		while ($dependencies) {
			$progress = false;
			foreach ($dependencies as $table => $parents) {
				if (array_diff_key($parents, array_fill_keys($ordered, true))) {
					continue;
				}
				$ordered[] = $table;
				unset($dependencies[$table]);
				$progress = true;
			}
			if (!$progress) {
				throw new RuntimeException('Baseline foreign-key dependencies contain a cycle.');
			}
		}
		return $ordered;
	}

	private function firstValue($sql, $value)
	{
		$stmt = $this->connection->prepare($sql);
		if (!$stmt) {
			throw new RuntimeException('Unable to prepare baseline metadata query.');
		}
		$stmt->bind_param('s', $value);
		if (!$stmt->execute()) {
			$stmt->close();
			throw new RuntimeException('Unable to read baseline schema metadata.');
		}
		$stmt->bind_result($result);
		$found = $stmt->fetch();
		$stmt->close();
		if (!$found) {
			throw new RuntimeException('Baseline schema metadata is missing.');
		}
		return $result;
	}

	private function databaseName()
	{
		$result = $this->connection->query('SELECT DATABASE()');
		$row = $result ? $result->fetch_row() : null;
		if ($result) {
			$result->free();
		}
		return $row ? $row[0] : null;
	}
}
