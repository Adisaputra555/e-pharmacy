<?php
error_reporting(0);
set_exception_handler(function ($error) {
	fwrite(STDERR, 'invariant_error=' . $error->getMessage() . PHP_EOL);
	exit(1);
});

$root = dirname(__DIR__);
if (PHP_SAPI !== 'cli' || getenv('CI_ENV') !== 'demo') {
	fwrite(STDERR, "Demo invariant checks require CLI with CI_ENV=demo.\n");
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
	fwrite(STDERR, "Invariant checker refuses a non-demo database.\n");
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
	fwrite(STDERR, "Invariant checker requires the scoped demo app account.\n");
	exit(68);
}
$connection->set_charset('utf8mb4');

$checks = array();
$checks['demo_marker'] = countWhere(
	"SELECT COUNT(*) FROM environment_marker WHERE id = 1 AND environment = 'demo'"
) === 1;
$checks['product_count'] = countWhere(
	"SELECT COUNT(*) FROM products WHERE code LIKE 'B3-PRD-%'"
) === 25;
$checks['supplier_count'] = countWhere(
	"SELECT COUNT(*) FROM suppliers WHERE code LIKE 'B3-SUP-%'"
) === 9;
$checks['orders_count'] = countWhere(
	"SELECT COUNT(*) FROM orders WHERE order_number LIKE 'B3-%'"
) === 600;
$checks['order_items_count'] = countWhere(
	'SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.order_number LIKE \'B3-%\''
) === 1800;
$checks['paid_orders_count'] = countWhere(
	"SELECT COUNT(*) FROM payments p JOIN orders o ON o.id = p.order_id WHERE o.order_number LIKE 'B3-%' AND p.status = 'paid'"
) === 520;
$checks['cancelled_and_pending_count'] = countWhere(
	"SELECT COUNT(*) FROM orders WHERE order_number LIKE 'B3-%' AND status IN ('cancelled', 'pending')"
) === 80;
$checks['purchase_orders_count'] = countWhere(
	"SELECT COUNT(*) FROM purchase_orders WHERE purchase_number LIKE 'B3-PO-%'"
) === 78;
$checks['received_purchase_order_count'] = countWhere(
	"SELECT COUNT(*) FROM purchase_orders WHERE purchase_number LIKE 'B3-PO-%' AND status = 'received'"
) === 75;
$checks['open_purchase_orders_count'] = countWhere(
	"SELECT COUNT(*) FROM purchase_orders WHERE purchase_number LIKE 'B3-PO-OPEN-%' AND status = 'ordered'"
) === 3;
$checks['received_po_three_per_product_supplier'] = countWhere(
	"SELECT COUNT(*) FROM (" .
	"SELECT poi.product_id, po.supplier_id FROM purchase_orders po " .
	"JOIN purchase_order_items poi ON poi.purchase_order_id = po.id " .
	"WHERE po.purchase_number LIKE 'B3-PO-%' AND po.status = 'received' " .
	"GROUP BY poi.product_id, po.supplier_id HAVING COUNT(*) < 3" .
	") AS insufficient_pairs"
) === 0;
$checks['batch_remaining_matches_allocations'] = countWhere(
	'SELECT COUNT(*) FROM stock_batches b ' .
	'LEFT JOIN (SELECT batch_id, SUM(quantity) AS allocated FROM order_item_batches GROUP BY batch_id) a ON a.batch_id = b.id ' .
	'WHERE ABS(b.initial_quantity - COALESCE(a.allocated, 0) - b.remaining_quantity) > 0.0001'
) === 0;
$checks['stock_movements_match_remaining'] = countWhere(
	'SELECT COUNT(*) FROM stock_batches b ' .
	'LEFT JOIN (SELECT batch_id, SUM(quantity) AS movement_total FROM stock_movements GROUP BY batch_id) m ON m.batch_id = b.id ' .
	'WHERE ABS(b.remaining_quantity - COALESCE(m.movement_total, 0)) > 0.0001'
) === 0;
$checks['order_item_allocations_match_payment_status'] = countWhere(
	'SELECT COUNT(*) FROM order_items oi ' .
	'JOIN orders o ON o.id = oi.order_id ' .
	'JOIN payments p ON p.order_id = o.id ' .
	'LEFT JOIN (SELECT order_item_id, SUM(quantity) AS allocated FROM order_item_batches GROUP BY order_item_id) a ON a.order_item_id = oi.id ' .
	'WHERE o.order_number LIKE \'B3-%\' AND (' .
	'(p.status = \'paid\' AND ABS(oi.quantity - COALESCE(a.allocated, 0)) > 0.0001) OR ' .
	'(p.status <> \'paid\' AND COALESCE(a.allocated, 0) > 0.0001))'
) === 0;
$checks['order_totals_match_items'] = countWhere(
	'SELECT COUNT(*) FROM orders o ' .
	'WHERE o.order_number LIKE \'B3-%\' ' .
	'AND ABS(o.total - ((SELECT COALESCE(SUM(oi.total), 0) FROM order_items oi WHERE oi.order_id = o.id) ' .
	'- o.discount + o.shipping_cost)) > 0.01'
) === 0;
$checks['paid_payment_amounts_match'] = countWhere(
	"SELECT COUNT(*) FROM payments p JOIN orders o ON o.id = p.order_id " .
	"WHERE o.order_number LIKE 'B3-%' AND p.status = 'paid' AND ABS(p.amount - o.total) > 0.01"
) === 0;
$checks['no_negative_stock'] = countWhere(
	'SELECT COUNT(*) FROM stock_batches WHERE remaining_quantity < 0 OR reserved_quantity < 0'
) === 0;
$checks['one_intentional_missing_paid_at'] = countWhere(
	"SELECT COUNT(*) FROM payments p JOIN orders o ON o.id = p.order_id " .
	"WHERE o.order_number LIKE 'B3-%' AND p.status = 'paid' AND p.paid_at IS NULL"
) === 1;
$checks['paid_at_not_before_order'] = countWhere(
	"SELECT COUNT(*) FROM payments p JOIN orders o ON o.id = p.order_id " .
	"WHERE o.order_number LIKE 'B3-%' AND p.status = 'paid' AND p.paid_at IS NOT NULL AND p.paid_at < o.created_at"
) === 0;
$checks['pos_and_online_channels'] = countWhere(
	"SELECT COUNT(*) FROM shipments s JOIN orders o ON o.id = s.order_id WHERE o.order_number LIKE 'B3-%'"
) === 300;
$checks['online_delivered_orders'] = countWhere(
	"SELECT COUNT(*) FROM shipments s JOIN orders o ON o.id = s.order_id " .
	"JOIN payments p ON p.order_id = o.id " .
	"WHERE o.order_number LIKE 'B3-%' AND p.status = 'paid' AND s.status = 'delivered'"
) === 260;
$checks['multi_batch_fefo_case'] = countWhere(
	"SELECT COUNT(*) FROM (" .
	"SELECT oib.order_item_id FROM order_item_batches oib " .
	"JOIN order_items oi ON oi.id = oib.order_item_id " .
	"JOIN products pr ON pr.id = oi.product_id " .
	"WHERE pr.code = 'B3-PRD-010' GROUP BY oib.order_item_id HAVING COUNT(DISTINCT oib.batch_id) >= 2" .
	") AS multi_batch_items"
) > 0;
$checks['zero_stock_case'] = countWhere(
	"SELECT COUNT(*) FROM (" .
	"SELECT pr.id FROM products pr JOIN stock_batches b ON b.product_id = pr.id " .
	"WHERE pr.code = 'B3-PRD-011' GROUP BY pr.id HAVING SUM(b.remaining_quantity) = 0" .
	") AS zero_stock_products"
) === 1;
$checks['near_expiry_red_batch'] = countWhere(
	"SELECT COUNT(*) FROM stock_batches b JOIN products pr ON pr.id = b.product_id " .
	"WHERE pr.code = 'B3-PRD-008' AND b.expiration_date = '2026-11-30' AND b.remaining_quantity > 0"
) > 0;
$checks['near_expiry_yellow_batch'] = countWhere(
	"SELECT COUNT(*) FROM stock_batches b JOIN products pr ON pr.id = b.product_id " .
	"WHERE pr.code = 'B3-PRD-009' AND b.expiration_date = '2027-01-15' AND b.remaining_quantity > 0"
) > 0;
$checks['legacy_products_unverified'] = countWhere(
	"SELECT COUNT(*) FROM products pr " .
	"JOIN product_unit_verifications v ON v.product_id = pr.id " .
	"WHERE pr.code IN ('B3-PRD-024', 'B3-PRD-025')"
) === 0 && countWhere(
	"SELECT COUNT(*) FROM products WHERE code IN ('B3-PRD-024', 'B3-PRD-025')"
) === 2;

$scenarioTags = array(
	'fast_mover_stable',
	'slow_mover',
	'no_sales_3_months',
	'seasonal',
	'new_less_3_months',
	'overstock',
	'critical_stock',
	'near_expiry_red',
	'near_expiry_yellow',
	'multi_batch_fefo',
	'zero_stock',
	'legacy_one',
	'legacy_two'
);
foreach ($scenarioTags as $tag) {
	$checks['scenario_' . $tag] = countWhereLike(
		$connection,
		'SELECT COUNT(*) FROM products WHERE description LIKE ?',
		'%' . '[B3:' . $tag . ']%'
	) === 1;
}

foreach ($checks as $name => $passed) {
	echo $name . "\t" . ($passed ? 'PASS' : 'FAIL') . PHP_EOL;
}
$failures = count(array_filter($checks, function ($passed) {
	return !$passed;
}));
echo 'summary=' . (count($checks) - $failures) . '/' . count($checks) . PHP_EOL;
$connection->close();
exit($failures === 0 ? 0 : 1);

function countWhere($sql)
{
	global $connection;
	$result = $connection->query($sql);
	if (!$result) {
		throw new RuntimeException('An invariant query could not be executed: ' . $connection->error);
	}
	$row = $result->fetch_row();
	$result->free();
	return $row ? (int) $row[0] : 0;
}

function countWhereLike($connection, $sql, $pattern)
{
	$stmt = $connection->prepare($sql);
	if (!$stmt) {
		throw new RuntimeException('An invariant query could not be prepared.');
	}
	$stmt->bind_param('s', $pattern);
	if (!$stmt->execute()) {
		$stmt->close();
		throw new RuntimeException('An invariant query could not be executed.');
	}
	$stmt->bind_result($count);
	$stmt->fetch();
	$stmt->close();
	return (int) $count;
}
