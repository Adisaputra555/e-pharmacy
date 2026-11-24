<?php
class DemoSeederInvariantTest extends PHPUnit\Framework\TestCase
{
	private function countRows($sql, $types = '', array $values = array())
	{
		$db = $GLOBALS['b1_demo_app_db'];
		$stmt = $db->prepare($sql);
		$this->assertNotFalse($stmt);
		if ($types !== '') {
			$arguments = array(&$types);
			foreach ($values as $index => &$value) {
				$arguments[] = &$value;
			}
			$this->assertTrue(call_user_func_array(array($stmt, 'bind_param'), $arguments));
		}
		$this->assertTrue($stmt->execute());
		$stmt->bind_result($count);
		$this->assertTrue($stmt->fetch());
		$stmt->close();
		return (int) $count;
	}

	public function testDemoSeedContainsExpectedTwelveMonthVolumeAndRequiredScenarios()
	{
		$this->assertSame(
			25,
			$this->countRows("SELECT COUNT(*) FROM products WHERE code LIKE 'B3-PRD-%'")
		);
		$this->assertSame(
			600,
			$this->countRows("SELECT COUNT(*) FROM orders WHERE order_number LIKE 'B3-%'")
		);
		$this->assertSame(
			1800,
			$this->countRows(
				"SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id = oi.order_id " .
				"WHERE o.order_number LIKE 'B3-%'"
			)
		);
		$this->assertSame(
			75,
			$this->countRows(
				"SELECT COUNT(*) FROM purchase_orders WHERE purchase_number LIKE 'B3-PO-%' AND status = 'received'"
			)
		);
		$this->assertSame(
			1,
			$this->countRows(
				"SELECT COUNT(*) FROM payments p JOIN orders o ON o.id = p.order_id " .
				"WHERE o.order_number LIKE 'B3-%' AND p.status = 'paid' AND p.paid_at IS NULL"
			)
		);

		foreach (array(
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
		) as $tag) {
			$this->assertSame(
				1,
				$this->countRows(
					'SELECT COUNT(*) FROM products WHERE description LIKE ?',
					's',
					array('%[B3:' . $tag . ']%')
				),
				'Missing or duplicated B3 scenario: ' . $tag
			);
		}
	}
}
