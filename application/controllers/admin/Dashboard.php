<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends Admin_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_permission('dashboard');
	}

	public function index()
	{
		$this->load->library('Business_clock');
		$this->load->library('Dashboard_sales_trend');
		$now = $this->business_clock->now();
		$today = $now->format('Y-m-d');
		$month_start_date = $now->modify('first day of this month')->setTime(0, 0, 0);
		$month_start = $month_start_date->format('Y-m-d');
		$next_month = $month_start_date->modify('+1 month')->format('Y-m-d');
		$tomorrow = $now->modify('+1 day')->setTime(0, 0, 0)->format('Y-m-d');
		$sales_window_start = $month_start_date->modify('-3 months')->format('Y-m-d');
		$near_expiry_end = $now->modify('+30 days')->format('Y-m-d');
		$ranking_window_start = $now->modify('-90 days')->format('Y-m-d');
		$trend_middle_start = $now->modify('-60 days')->format('Y-m-d');
		$trend_recent_start = $now->modify('-30 days')->format('Y-m-d');
		$day_names = array('Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu');
		$month_names = array(1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des');
		$data['dashboard_date'] = $day_names[(int) $now->format('w')] . ', ' .
			$now->format('d') . ' ' . $month_names[(int) $now->format('n')] . ' ' . $now->format('Y');
		$qualified_sales_orders = Dashboard_sales_trend::qualifiedSalesOrdersSql();
		$qualified_sales = $qualified_sales_orders . ' qualified_sales';

		$data['metrics'] = array(
			'products' => (int) $this->db->where('deleted_at IS NULL', null, false)->from('products')->count_all_results(),
			'customers' => (int) $this->db
				->join('user_roles', 'user_roles.user_id = users.id')
				->join('roles', 'roles.id = user_roles.role_id')
				->where('roles.slug', 'pelanggan')
				->where('users.deleted_at IS NULL', null, false)
				->from('users')->count_all_results(),
			'orders' => (int) $this->db->count_all('orders'),
			'today_sales' => (float) $this->db->query(
				'SELECT COALESCE(SUM(total), 0) AS total FROM ' . $qualified_sales .
				' WHERE first_paid_at >= ? AND first_paid_at < ?',
				array($today . ' 00:00:00', $tomorrow . ' 00:00:00')
			)->row()->total,
			'month_sales' => (float) $this->db->query(
				'SELECT COALESCE(SUM(total), 0) AS total FROM ' . $qualified_sales .
				' WHERE first_paid_at >= ? AND first_paid_at < ?',
				array($month_start . ' 00:00:00', $next_month . ' 00:00:00')
			)->row()->total,
			'pending_orders' => (int) $this->db->where('status', 'pending')->count_all_results('orders'),
			'low_stock' => (int) $this->db->query(
				'SELECT COUNT(*) AS total FROM (
					SELECT p.id
					FROM products p
					LEFT JOIN stock_batches sb ON sb.product_id = p.id AND sb.status = "active" AND sb.expiration_date >= ?
					WHERE p.deleted_at IS NULL AND p.is_active = 1
					GROUP BY p.id, p.minimum_stock
					HAVING COALESCE(SUM(sb.remaining_quantity - sb.reserved_quantity), 0) <= p.minimum_stock
				) low_stock',
				array($today)
			)->row()->total,
			'expiring_batches' => (int) $this->db
				->where('status', 'active')
				->where('expiration_date >=', $today)
				->where('expiration_date <=', $near_expiry_end)
				->where('remaining_quantity > reserved_quantity', null, false)
				->count_all_results('stock_batches')
		);
		$data['metrics']['near_expiry_products'] = (int) $this->db->query(
			'SELECT COUNT(DISTINCT product_id) AS total
			FROM stock_batches
			WHERE status = "active"
				AND expiration_date >= ?
				AND expiration_date <= ?
				AND remaining_quantity > reserved_quantity',
			array($today, $near_expiry_end)
		)->row()->total;
		$data['metrics']['inventory_value'] = (float) $this->db->query(
			'SELECT COALESCE(SUM(remaining_quantity * purchase_price), 0) AS total
			FROM stock_batches
			WHERE status = "active"
				AND expiration_date >= ?
				AND remaining_quantity > 0',
			array($today)
		)->row()->total;

		$ranking_query = Dashboard_sales_trend::productRankingQuery($qualified_sales, array(
			'today' => $today,
			'sales_window_start' => $sales_window_start,
			'month_start' => $month_start,
			'ranking_window_start' => $ranking_window_start,
			'trend_middle_start' => $trend_middle_start,
			'trend_recent_start' => $trend_recent_start,
			'tomorrow' => $tomorrow
		));
		$inventory_products = $this->db->query(
			$ranking_query['sql'],
			$ranking_query['bindings']
		)->result();

		$data['sales_window'] = array(
			'start' => $sales_window_start,
			'end' => $month_start_date->modify('-1 day')->format('Y-m-d'),
			'months' => 3
		);
		$data['ranking_period'] = array(
			'start' => $ranking_window_start,
			'end' => $today
		);
		$data['sales_period_has_data'] = false;
		$data['top_selling_products'] = array();
		$data['slow_moving_products'] = array();
		$data['coverage_products'] = array();
		$data['overstock_products'] = array();
		foreach ($inventory_products as $product) {
			$product->available_stock = (float) $product->available_stock;
			$product->sold_quantity = (float) $product->sold_quantity;
			$product->recent_sold_quantity = (float) $product->recent_sold_quantity;
			$product->sales_trend = $this->dashboard_sales_trend->summarize(array(
				$product->older_sold_quantity,
				$product->middle_sold_quantity,
				$product->latest_sold_quantity
			));
			$product->average_monthly_sales = $product->sold_quantity / 3;
			if ($product->recent_sold_quantity > 0) {
				$data['sales_period_has_data'] = true;
			}
			if ($product->sold_quantity > 0) {
				$product->coverage_months = $product->available_stock / $product->average_monthly_sales;
				$product->coverage_status = $product->coverage_months <= 1 ? 'kritis' : ($product->coverage_months <= 3 ? 'perlu diperhatikan' : ($product->coverage_months <= 6 ? 'ideal' : 'overstock'));
				$product->coverage_class = $product->coverage_months <= 1 ? 'danger' : ($product->coverage_months <= 3 ? 'warning' : ($product->coverage_months <= 6 ? 'success' : 'primary'));
				if ($product->coverage_months > 6) {
					$data['overstock_products'][] = $product;
				}
			} else {
				$product->coverage_months = null;
				$product->coverage_status = 'belum cukup data';
				$product->coverage_class = 'secondary';
			}

			if ($product->available_stock > 0) {
				$data['slow_moving_products'][] = $product;
				$data['coverage_products'][] = $product;
			}
			if ($product->recent_sold_quantity > 0) {
				$data['top_selling_products'][] = $product;
			}
		}
		usort($data['top_selling_products'], function ($left, $right) {
			return $right->recent_sold_quantity <=> $left->recent_sold_quantity;
		});
		usort($data['slow_moving_products'], function ($left, $right) {
			$quantity_order = $left->recent_sold_quantity <=> $right->recent_sold_quantity;
			return $quantity_order !== 0 ? $quantity_order : strcasecmp($left->name, $right->name);
		});
		usort($data['coverage_products'], function ($left, $right) {
			if ($left->coverage_months === null || $right->coverage_months === null) {
				if ($left->coverage_months === $right->coverage_months) {
					return strcasecmp($left->name, $right->name);
				}
				return $left->coverage_months === null ? 1 : -1;
			}
			return $left->coverage_months <=> $right->coverage_months;
		});
		$data['top_selling_products'] = array_slice($data['top_selling_products'], 0, 20);
		$data['slow_moving_products'] = array_slice($data['slow_moving_products'], 0, 20);
		$data['coverage_products'] = array_slice($data['coverage_products'], 0, 20);
		$data['overstock_products'] = array_slice($data['overstock_products'], 0, 20);

		$chart_start = $month_start_date->modify('-5 months')->format('Y-m-d');
		$month_names = array(1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des');
		$chart_months = array();
		$cursor = new DateTimeImmutable($chart_start);
		for ($index = 0; $index < 6; $index++) {
			$key = $cursor->format('Y-m');
			$chart_months[$key] = array('label' => $month_names[(int) $cursor->format('n')], 'sales' => 0, 'purchases' => 0);
			$cursor = $cursor->modify('+1 month');
		}

		$sales_rows = $this->db->query(
			'SELECT DATE_FORMAT(first_paid_at, "%Y-%m") AS month_key, SUM(total) AS amount FROM ' .
			$qualified_sales . ' WHERE first_paid_at >= ? AND first_paid_at < ? GROUP BY month_key',
			array($chart_start . ' 00:00:00', $next_month . ' 00:00:00')
		)
			->result();
		foreach ($sales_rows as $row) {
			if (isset($chart_months[$row->month_key])) {
				$chart_months[$row->month_key]['sales'] = (float) $row->amount;
			}
		}

		$purchase_rows = $this->db->select('DATE_FORMAT(purchase_date, "%Y-%m") AS month_key, SUM(total) AS amount', false)
			->where('purchase_date >=', $chart_start)
			->where('purchase_date <', $next_month)
			->where('status !=', 'cancelled')
			->group_by('month_key')
			->get('purchase_orders')
			->result();
		foreach ($purchase_rows as $row) {
			if (isset($chart_months[$row->month_key])) {
				$chart_months[$row->month_key]['purchases'] = (float) $row->amount;
			}
		}

		$product_categories = $this->db->query(
			'SELECT COALESCE(categories.name, "Tanpa kategori") AS label, COUNT(products.id) AS total
			FROM products
			LEFT JOIN categories ON categories.id = products.category_id
			WHERE products.deleted_at IS NULL
			GROUP BY categories.id, categories.name
			ORDER BY total DESC'
		)->result();
		$data['sales_chart'] = array(
			'labels' => array_values(array_map(function ($month) { return $month['label']; }, $chart_months)),
			'sales' => array_values(array_map(function ($month) { return $month['sales']; }, $chart_months)),
			'purchases' => array_values(array_map(function ($month) { return $month['purchases']; }, $chart_months))
		);
		$data['product_category_chart'] = array(
			'labels' => array_map(function ($row) { return $row->label; }, $product_categories),
			'values' => array_map(function ($row) { return (int) $row->total; }, $product_categories)
		);
		$data['dashboard_charts'] = true;
		$data['title'] = 'Dashboard';
		$data['forecast_dashboard_enabled'] = $this->forecastDashboardEnabled();
		if ($data['forecast_dashboard_enabled']) {
			$this->load->library('Forecast_service');
			$data['forecast_dashboard'] = $this->forecast_service->getDashboardForecast();
		}
		$data['content'] = $this->load->view('admin/dashboard', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	private function forecastDashboardEnabled()
	{
		$environment = defined('ENVIRONMENT') ? ENVIRONMENT : '';
		if ($environment !== 'demo') {
			return false;
		}

		$configPath = APPPATH . 'config' . DIRECTORY_SEPARATOR . 'secrets' .
			DIRECTORY_SEPARATOR . 'p15_forecast.php';
		if (!is_file($configPath)) {
			return false;
		}
		require_once APPPATH . 'libraries' . DIRECTORY_SEPARATOR . 'Forecast_service.php';
		if (!Forecast_service::dashboardFlagEnabled($environment, $configPath)) {
			return false;
		}

		$markerResult = $this->db->query(
			'SELECT environment FROM environment_marker WHERE id = 1 LIMIT 1'
		);
		if (!$markerResult) {
			log_message('error', 'Forecast dashboard disabled because the environment marker could not be read.');
			return false;
		}
		$marker = $markerResult->row_array();
		if (!$marker) {
			log_message('error', 'Forecast dashboard disabled because the environment marker is missing.');
			return false;
		}
		return Forecast_service::isDashboardEnabled(
			$environment,
			true,
			isset($marker['environment']) ? $marker['environment'] : null
		);
	}
}
