<?php
if (!defined('BASEPATH')) {
	define('BASEPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Dashboard_sales_trend.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Forecast_service.php';

if (!function_exists('html_escape')) {
	function html_escape($value)
	{
		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}
}

class DashboardSalesTrendTest extends PHPUnit\Framework\TestCase
{
	public function testTrendDirectionAndChangeUseTheLatestTwoPeriods()
	{
		$this->assertSame(
			array('direction' => 'up', 'change' => '+50,0%'),
			array_intersect_key(
				Dashboard_sales_trend::summarize(array(2, 4, 6)),
				array('direction' => true, 'change' => true)
			)
		);
		$this->assertSame(
			array('direction' => 'down', 'change' => '-50,0%'),
			array_intersect_key(
				Dashboard_sales_trend::summarize(array(6, 4, 2)),
				array('direction' => true, 'change' => true)
			)
		);
		$this->assertSame(
			array('direction' => 'steady', 'change' => '0,0%'),
			array_intersect_key(
				Dashboard_sales_trend::summarize(array(2, 4, 4)),
				array('direction' => true, 'change' => true)
			)
		);
		$this->assertSame('Baru', Dashboard_sales_trend::summarize(array(0, 0, 3))['change']);
	}

	public function testAdminLayoutDoesNotRenderAccountEmailOrRealBusinessName()
	{
		$layout = file_get_contents(
			dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
			'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'admin.php'
		);

		$this->assertNotFalse($layout);
		$this->assertStringNotContainsString('userdata(\'user_email\')', $layout);
		$this->assertStringNotContainsString('Apotek Lama', $layout);
	}

	public function testDashboardShowsStockStyleTrendInBothProductLists()
	{
		$dashboard_date = 'Senin, 23 November 2026';
		$trend = Dashboard_sales_trend::summarize(array(3, 5, 10));
		$product = (object) array(
			'code' => 'MED-001',
			'name' => '<fixture>',
			'recent_sold_quantity' => 18,
			'available_stock' => 25,
			'sales_trend' => $trend
		);
		$metrics = array(
			'products' => 1,
			'customers' => 0,
			'orders' => 1,
			'today_sales' => 0,
			'month_sales' => 0,
			'pending_orders' => 0,
			'low_stock' => 0,
			'inventory_value' => 0,
			'near_expiry_products' => 0,
			'expiring_batches' => 0
		);
		$sales_chart = array('labels' => array(), 'sales' => array(), 'purchases' => array());
		$product_category_chart = array('labels' => array(), 'values' => array());
		$ranking_period = array('start' => '2026-01-01', 'end' => '2026-03-31');
		$sales_window = array('start' => '2026-01-01', 'end' => '2026-03-31');
		$top_selling_products = array();
		$slow_moving_products = array();
		for ($index = 1; $index <= 6; $index++) {
			$listProduct = clone $product;
			$listProduct->code = sprintf('MED-%03d', $index);
			$listProduct->name = $index === 1 ? '<fixture>' : 'Obat Contoh ' . $index;
			$top_selling_products[] = $listProduct;
			$slow_moving_products[] = $listProduct;
		}
		$sales_period_has_data = true;
		$coverage_products = array();
		$overstock_products = array();
		$forecast_dashboard_enabled = false;

		ob_start();
		include dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
			'views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'dashboard.php';
		$html = ob_get_clean();

		$this->assertSame(10, substr_count($html, 'aria-label="Grafik penjualan lunas tiga periode 30 hari"'));
		$this->assertSame(10, substr_count($html, 'Tren naik, +100,0%'));
		$this->assertStringContainsString('Top 5 Obat Terlaris', $html);
		$this->assertStringContainsString('Top 5 Obat Slow-Moving', $html);
		$this->assertSame(5, substr_count($html, '<div class="dashboard-market-row">'));
		$this->assertSame(5, substr_count($html, '<div class="dashboard-market-row dashboard-market-row-slow">'));
		$this->assertStringContainsString('MED-001', $html);
		$this->assertStringNotContainsString('MED-006', $html);
		$this->assertStringContainsString('<h2>Dasbor</h2>', $html);
		$this->assertStringContainsString('Ringkasan lainnya', $html);
		$this->assertSame(7, substr_count($html, 'class="dashboard-overview-card'));
		$this->assertSame(7, substr_count($html, '<span class="dashboard-overview-icon'));
		$this->assertSame(2, substr_count($html, '<span class="dashboard-primary-icon-tile'));
		$this->assertStringContainsString('Penjualan lunas bulan ini', $html);
		$this->assertStringContainsString('&lt;fixture&gt;', $html);
		$this->assertStringNotContainsString('<fixture>', $html);
		$this->assertStringContainsString('Total produk katalog', $html);
		$this->assertStringContainsString('Nilai persediaan', $html);
		$this->assertSame(3, substr_count($html, ' data-count-up data-count-target='));
		$this->assertStringContainsString('class="dashboard-sales-decoration"', $html);
		$this->assertStringContainsString('Batch kedaluwarsa ≤ 30 hari', $html);
		$this->assertStringContainsString('dashboard-overview-critical', $html);
		$this->assertStringContainsString('id="dashboardPeriodFilter"', $html);
		$this->assertStringContainsString('class="dashboard-filter-control"', $html);
		$this->assertStringContainsString('<option value="6">6 bulan</option>', $html);
		$this->assertStringContainsString('<option value="3">3 bulan</option>', $html);
		$this->assertStringContainsString('data-bs-toggle="pill"', $html);
		$this->assertStringContainsString('id="dashboard-fast-moving"', $html);
		$this->assertStringContainsString('id="dashboard-slow-moving"', $html);
		$this->assertStringContainsString('dashboard-health-panel', $html);
		$this->assertStringContainsString('Dari daftar rasio stok (maks. 20 produk)', $html);
		$this->assertSame(2, substr_count($html, 'class="dashboard-market-list dashboard-market-list-scroll"'));
	}

	public function testDashboardKeepsCoverageAndForecastPresentationCompact()
	{
		$dashboard_date = 'Senin, 23 November 2026';
		$metrics = array(
			'products' => 1,
			'customers' => 1,
			'orders' => 2,
			'today_sales' => 1000,
			'month_sales' => 5000,
			'pending_orders' => 1,
			'low_stock' => 1,
			'inventory_value' => 2000,
			'near_expiry_products' => 1,
			'expiring_batches' => 1
		);
		$sales_chart = array('labels' => array('Agu', 'Sep', 'Okt'), 'sales' => array(1, 2, 3), 'purchases' => array(2, 3, 4));
		$product_category_chart = array('labels' => array('Obat'), 'values' => array(1));
		$ranking_period = array('start' => '2026-08-25', 'end' => '2026-11-22');
		$sales_window = array('start' => '2026-08-01', 'end' => '2026-10-31');
		$top_selling_products = array();
		$slow_moving_products = array();
		$sales_period_has_data = true;
		$coverageProduct = (object) array(
			'name' => 'Produk Contoh',
			'available_stock' => 20,
			'average_monthly_sales' => 4,
			'coverage_months' => 3,
			'coverage_class' => 'success',
			'coverage_status' => 'ideal'
		);
		$coverage_products = array($coverageProduct);
		$overstockProduct = clone $coverageProduct;
		$overstockProduct->coverage_months = 9;
		$overstockProduct->coverage_status = 'overstock';
		$overstock_products = array($overstockProduct);
		$forecast_dashboard_enabled = true;
		$forecast_dashboard = array(
			'method' => 'Moving Average 3 bulan',
			'period' => array(
				'start' => '2026-08-01',
				'end' => '2026-10-31',
				'months' => array('2026-08', '2026-09', '2026-10')
			),
			'status_counts' => array('Cukup data' => 1),
			'products' => array(array(
				'name' => 'Produk Contoh',
				'code' => 'MED-001',
				'unit_verified' => true,
				'base_unit' => 'tablet',
				'base_unit_code' => 'TAB',
				'data_status' => 'Cukup data',
				'monthly_demand' => array('2026-08' => 4, '2026-09' => 6, '2026-10' => 8),
				'net_demand' => 18,
				'average_monthly_demand' => 6,
				'forecast' => array(1 => 6, 3 => 18, 6 => 36),
				'usable_stock' => 50,
				'coverage_months' => 8.3,
				'coverage_status' => 'overstock',
				'reserved_excess' => 0,
				'red_risk_quantity' => 0,
				'yellow_risk_quantity' => 0
			))
		);

		ob_start();
		include dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
			'views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'dashboard.php';
		$html = ob_get_clean();

		$this->assertStringContainsString('dashboard-forecast-wrap', $html);
		$this->assertStringContainsString('id="apotek-dashboard-stitch"', $html);
		$this->assertStringContainsString('<section class="dashboard-panel dashboard-forecast-panel', $html);
		$this->assertStringContainsString('dashboard-health-status-ideal', $html);
		$this->assertStringContainsString('dashboard-health-status-overstock', $html);
		$this->assertStringContainsString('dashboard-category-legend', $html);
		$this->assertStringContainsString('data-colors=', $html);
		$this->assertStringContainsString('100% <span class="text-muted">(1)</span>', $html);
		$this->assertStringContainsString('Permintaan aktual', $html);
		$this->assertStringContainsString('Demand bersih: 18', $html);
		$this->assertStringContainsString('Proyeksi MA', $html);
		$this->assertStringContainsString('Coverage &amp; stok layak', $html);
		$this->assertSame(3, substr_count($html, 'class="forecast-horizon"'));
		$this->assertStringContainsString('50% target 6 bln', $html);
		$this->assertStringContainsString('150% target', $html);
	}

	public function testDashboardForecastPanelUsesForecastServiceProductAndStatusTotals()
	{
		$clock = new Business_clock(function () {
			return new DateTimeImmutable('2026-11-24 12:00:00', new DateTimeZone('Asia/Jakarta'));
		});
		$service = new Forecast_service($GLOBALS['b1_demo_app_db'], $clock);
		$forecast_dashboard = $service->getDashboardForecast();
		$product_status_counts = array_fill_keys(array_keys($forecast_dashboard['status_counts']), 0);
		foreach ($forecast_dashboard['products'] as $product) {
			$product_status_counts[$product['data_status']]++;
		}

		$this->assertSame($forecast_dashboard['status_counts'], $product_status_counts);
		$this->assertCount(array_sum($forecast_dashboard['status_counts']), $forecast_dashboard['products']);
		$this->assertSame(25, count($forecast_dashboard['products']));
		foreach ($forecast_dashboard['products'] as $product) {
			$this->assertArrayHasKey('monthly_demand', $product);
			$this->assertArrayHasKey('data_status', $product);
		}
	}

	public function testDashboardCompletedMonthDemandMatchesForecastServiceForEachProduct()
	{
		$clock = new Business_clock(function () {
			return new DateTimeImmutable('2026-11-24 12:00:00', new DateTimeZone('Asia/Jakarta'));
		});
		$forecast = (new Forecast_service($GLOBALS['b1_demo_app_db'], $clock))->getDashboardForecast();
		$period_start = $forecast['period']['start'];
		$period_end = (new DateTimeImmutable($forecast['period']['end']))->modify('+1 day')->format('Y-m-d');
		$qualified_orders = Dashboard_sales_trend::qualifiedSalesOrdersSql() . ' qualified_sales';
		$statement = $GLOBALS['b1_demo_app_db']->prepare(
			'SELECT oi.product_id, SUM(oi.quantity_base) AS qualified_quantity ' .
			'FROM order_items oi JOIN ' . $qualified_orders . ' ON qualified_sales.id = oi.order_id ' .
			'WHERE qualified_sales.first_paid_at >= ? AND qualified_sales.first_paid_at < ? ' .
			'AND oi.unit_status_snapshot = "verified" AND oi.quantity_base IS NOT NULL ' .
			'GROUP BY oi.product_id'
		);
		$this->assertNotFalse($statement);
		$period_start = $period_start . ' 00:00:00';
		$period_end = $period_end . ' 00:00:00';
		$statement->bind_param('ss', $period_start, $period_end);
		$this->assertTrue($statement->execute());
		$statement->bind_result($product_id, $qualified_quantity);
		$actual_by_product = array();
		while ($statement->fetch()) {
			$actual_by_product[(int) $product_id] = (float) $qualified_quantity;
		}
		$statement->close();

		foreach ($forecast['products'] as $product) {
			$expected = array_sum(array_map('floatval', array_filter(
				$product['monthly_demand'],
				function ($demand) {
					return $demand !== null;
				}
			)));
			$actual = isset($actual_by_product[$product['id']]) ? $actual_by_product[$product['id']] : 0.0;
			$this->assertEqualsWithDelta($expected, $actual, 0.001, 'Qualified base demand must match forecast history.');
		}
	}

	public function testProductRankingQueryBindsEveryPlaceholderAndHasBalancedSql()
	{
		$query = Dashboard_sales_trend::productRankingQuery(
			Dashboard_sales_trend::qualifiedSalesOrdersSql() . ' qualified_sales',
			array(
				'today' => '2026-11-24',
				'sales_window_start' => '2026-08-01',
				'month_start' => '2026-11-01',
				'ranking_window_start' => '2026-08-26',
				'trend_middle_start' => '2026-09-25',
				'trend_recent_start' => '2026-10-25',
				'tomorrow' => '2026-11-25'
			)
		);

		$this->assertSame(substr_count($query['sql'], '?'), count($query['bindings']));
		$this->assertStringNotContainsString(
			'AND (SELECT qualified_sales.first_paid_at',
			$query['sql']
		);
		$this->assertTrue($this->hasBalancedParentheses($query['sql']));
	}

	public function testProductRankingQueryRunsReadOnlyOnDemoAndMatchesForecastDemand()
	{
		$db = $GLOBALS['b1_demo_app_db'];
		$identityResult = $db->query(
			'SELECT SUBSTRING_INDEX(CURRENT_USER(), CHAR(64), 1) AS account_name'
		);
		$this->assertNotSame(false, $identityResult);
		$identity = $identityResult->fetch_assoc();
		$this->assertTrue(
			strtolower($identity['account_name']) !== 'root',
			'Ranking query tests require a non-root demo application account.'
		);

		$markerResult = $db->query(
			'SELECT environment FROM environment_marker WHERE id = 1'
		);
		$this->assertNotSame(false, $markerResult);
		$marker = $markerResult->fetch_assoc();
		$this->assertSame('demo', $marker['environment']);

		$businessDate = new DateTimeImmutable(
			'2026-11-24 12:00:00',
			new DateTimeZone('Asia/Jakarta')
		);
		$clock = new Business_clock(function () use ($businessDate) {
			return $businessDate;
		});
		$forecast = (new Forecast_service($db, $clock))->getDashboardForecast();
		$now = $clock->now();
		$monthStart = $now->modify('first day of this month')->format('Y-m-d');
		$tomorrow = $now->modify('+1 day')->setTime(0, 0, 0)->format('Y-m-d');
		$query = Dashboard_sales_trend::productRankingQuery(
			Dashboard_sales_trend::qualifiedSalesOrdersSql() . ' qualified_sales',
			array(
				'today' => $now->format('Y-m-d'),
				'sales_window_start' => $forecast['period']['start'],
				'month_start' => $monthStart,
				'ranking_window_start' => $now->modify('-90 days')->format('Y-m-d'),
				'trend_middle_start' => $now->modify('-60 days')->format('Y-m-d'),
				'trend_recent_start' => $now->modify('-30 days')->format('Y-m-d'),
				'tomorrow' => $tomorrow
			)
		);
		$statement = $db->prepare($query['sql']);
		$this->assertNotFalse($statement);
		$bindings = $query['bindings'];
		$bindTypes = str_repeat('s', count($bindings));
		$bindArguments = array(&$bindTypes);
		foreach ($bindings as $index => &$binding) {
			$bindArguments[] = &$binding;
		}
		$this->assertTrue(call_user_func_array(array($statement, 'bind_param'), $bindArguments));
		$this->assertTrue($statement->execute());
		$result = $statement->get_result();
		$this->assertNotFalse($result);
		$rankingProducts = $result->fetch_all(MYSQLI_ASSOC);
		$statement->close();
		$this->assertGreaterThan(0, count($rankingProducts));
		foreach ($rankingProducts as $product) {
			$this->assertNotNull($product['middle_sold_quantity']);
		}

		$rankingById = array();
		foreach ($rankingProducts as $product) {
			$rankingById[(int) $product['id']] = (float) $product['sold_quantity'];
		}
		$matchedForecastProduct = false;
		foreach ($forecast['products'] as $forecastProduct) {
			if (empty($forecastProduct['unit_verified']) ||
				!isset($rankingById[$forecastProduct['id']])) {
				continue;
			}
			$expectedDemand = 0.0;
			$hasDemand = false;
			foreach ($forecastProduct['monthly_demand'] as $monthlyDemand) {
				if ($monthlyDemand !== null) {
					$expectedDemand += (float) $monthlyDemand;
					$hasDemand = true;
				}
			}
			if (!$hasDemand || $expectedDemand <= 0) {
				continue;
			}

			$this->assertEqualsWithDelta(
				$expectedDemand,
				$rankingById[$forecastProduct['id']],
				0.001
			);
			$matchedForecastProduct = true;
			break;
		}
		$this->assertTrue($matchedForecastProduct, 'A forecast product with demand must be available.');
	}

	private function hasBalancedParentheses($sql)
	{
		$depth = 0;
		for ($index = 0, $length = strlen($sql); $index < $length; $index++) {
			if ($sql[$index] === '(') {
				$depth++;
			} elseif ($sql[$index] === ')') {
				$depth--;
				if ($depth < 0) {
					return false;
				}
			}
		}

		return $depth === 0;
	}
}
