<?php

class PosFlowTest extends Phase0aHttpTestCase
{
	public function testCashSaleCreatesPaidPosOrderAndConsumesFefoBatch()
	{
		$this->loginAs('admin');
		$fixture = $GLOBALS['phase0aFixtures']['products']['pos'];
		$before = $this->dbRow(
			'SELECT remaining_quantity,reserved_quantity FROM stock_batches WHERE id = ?',
			'i',
			array($fixture['batch_id'])
		);
		$this->assertNotNull($GLOBALS['phase0aFixtures']['cash_method_id']);
		$this->assertNotNull($GLOBALS['phase0aFixtures']['pickup_method_id']);

		$response = $this->postForm(
			'/index.php/admin/sales/create',
			'/index.php/admin/sales/store',
			array(
				'product_id' => $fixture['product_id'],
				'quantity' => '1',
				'discount' => '0',
				'customer_name' => 'Phase 0a POS',
				'customer_phone' => '0000000002'
			)
		);
		$this->assertContains($response['status'], array(302, 303));

		$order = $this->dbRow(
			"SELECT id,status,address_snapshot FROM orders WHERE order_number LIKE 'POS-%' ORDER BY id DESC LIMIT 1"
		);
		$this->assertNotNull($order);
		$item = $this->dbRow('SELECT id,quantity FROM order_items WHERE order_id = ? LIMIT 1', 'i', array($order['id']));
		$payment = $this->dbRow('SELECT status,amount FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1', 'i', array($order['id']));
		$allocation = $this->dbRow(
			'SELECT batch_id,quantity FROM order_item_batches WHERE order_item_id = ? ORDER BY id LIMIT 1',
			'i',
			array($item['id'])
		);
		$after = $this->dbRow(
			'SELECT remaining_quantity,reserved_quantity FROM stock_batches WHERE id = ?',
			'i',
			array($fixture['batch_id'])
		);

		$this->assertSame('processing', $order['status']);
		$this->assertSame('offline', json_decode($order['address_snapshot'], true)['channel']);
		$this->assertSame('paid', $payment['status']);
		$this->assertSame('1.000', number_format((float) $item['quantity'], 3, '.', ''));
		$this->assertSame((int) $fixture['batch_id'], (int) $allocation['batch_id']);
		$this->assertSame((float) $before['remaining_quantity'] - 1.0, (float) $after['remaining_quantity']);
		$this->assertSame(0.0, (float) $after['reserved_quantity']);
	}
}
