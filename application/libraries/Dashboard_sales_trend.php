<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard_sales_trend
{
	public static function qualifiedSalesOrdersSql()
	{
		return '(
			SELECT o.id, o.total, paid.first_paid_at
			FROM orders o
			JOIN (
				SELECT order_id, COUNT(*) AS paid_payment_count, SUM(amount) AS paid_amount,
					MIN(paid_at) AS first_paid_at,
					SUM(CASE WHEN paid_at IS NULL THEN 1 ELSE 0 END) AS missing_paid_at_count
				FROM payments
				WHERE status = "paid"
				GROUP BY order_id
			) paid ON paid.order_id = o.id
			WHERE o.status <> "cancelled"
				AND paid.paid_payment_count > 0
				AND paid.paid_amount >= o.total
				AND paid.missing_paid_at_count = 0
				AND paid.first_paid_at IS NOT NULL
		)';
	}

	public static function productRankingQuery($qualified_sales, array $period)
	{
		$stock_totals = '(
			SELECT product_id, SUM(GREATEST(remaining_quantity - reserved_quantity, 0)) AS available_stock
			FROM stock_batches
			WHERE status = "active" AND expiration_date >= ?
			GROUP BY product_id
		) stock_totals';
		$qualified_sales_totals = '(
			SELECT oi.product_id,
				SUM(CASE WHEN qualified_sales.first_paid_at >= ? AND qualified_sales.first_paid_at < ?
					THEN oi.quantity_base ELSE 0 END) AS sold_quantity,
				SUM(CASE WHEN qualified_sales.first_paid_at >= ? AND qualified_sales.first_paid_at < ?
					THEN oi.quantity_base ELSE 0 END) AS older_sold_quantity,
				SUM(CASE WHEN qualified_sales.first_paid_at >= ? AND qualified_sales.first_paid_at < ?
					THEN oi.quantity_base ELSE 0 END) AS middle_sold_quantity,
				SUM(CASE WHEN qualified_sales.first_paid_at >= ? AND qualified_sales.first_paid_at < ?
					THEN oi.quantity_base ELSE 0 END) AS recent_sold_quantity
			FROM order_items oi
			JOIN ' . $qualified_sales . ' ON qualified_sales.id = oi.order_id
			WHERE qualified_sales.first_paid_at >= ? AND qualified_sales.first_paid_at < ?
				AND oi.unit_status_snapshot = "verified"
				AND oi.quantity_base IS NOT NULL
			GROUP BY oi.product_id
		) qualified_sales_totals';

		return array(
			'sql' => 'SELECT p.id, p.code, p.name, p.minimum_stock,
				COALESCE(stock_totals.available_stock, 0) AS available_stock,
				COALESCE(qualified_sales_totals.sold_quantity, 0) AS sold_quantity,
				COALESCE(qualified_sales_totals.recent_sold_quantity, 0) AS recent_sold_quantity,
				COALESCE(qualified_sales_totals.older_sold_quantity, 0) AS older_sold_quantity,
				COALESCE(qualified_sales_totals.middle_sold_quantity, 0) AS middle_sold_quantity,
				COALESCE(qualified_sales_totals.recent_sold_quantity, 0) AS latest_sold_quantity
			FROM products p
			LEFT JOIN ' . $stock_totals . ' ON stock_totals.product_id = p.id
			LEFT JOIN ' . $qualified_sales_totals . ' ON qualified_sales_totals.product_id = p.id
			WHERE p.deleted_at IS NULL AND p.is_active = 1
			ORDER BY p.name',
			'bindings' => array(
				$period['today'] . ' 00:00:00',
				$period['sales_window_start'] . ' 00:00:00',
				$period['month_start'] . ' 00:00:00',
				$period['ranking_window_start'] . ' 00:00:00',
				$period['trend_middle_start'] . ' 00:00:00',
				$period['trend_middle_start'] . ' 00:00:00',
				$period['trend_recent_start'] . ' 00:00:00',
				$period['trend_recent_start'] . ' 00:00:00',
				$period['tomorrow'] . ' 00:00:00',
				$period['sales_window_start'] . ' 00:00:00',
				$period['tomorrow'] . ' 00:00:00'
			)
		);
	}

	public static function summarize($periods)
	{
		$periods = array_values(array_map('floatval', $periods));
		$last = count($periods) - 1;
		$previous = $last > 0 ? $periods[$last - 1] : 0;
		$current = $last >= 0 ? $periods[$last] : 0;

		if ($current > $previous) {
			$direction = 'up';
			$change = $previous > 0
				? '+' . number_format((($current - $previous) / $previous) * 100, 1, ',', '.') . '%'
				: 'Baru';
		} elseif ($current < $previous) {
			$direction = 'down';
			$change = number_format((($current - $previous) / $previous) * 100, 1, ',', '.') . '%';
		} else {
			$direction = 'steady';
			$change = '0,0%';
		}

		return array(
			'periods' => $periods,
			'direction' => $direction,
			'change' => $change
		);
	}
}
