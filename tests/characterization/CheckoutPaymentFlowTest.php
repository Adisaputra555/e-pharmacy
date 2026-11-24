<?php

class CheckoutPaymentFlowTest extends Phase0aHttpTestCase
{
	public function testCheckoutCreatesPendingOrderAndReservesStock()
	{
		$this->loginAs('customer');
		$customer = $GLOBALS['phase0aFixtures']['customer'];
		$fixture = $GLOBALS['phase0aFixtures']['products']['checkout'];
		$before = $this->dbRow(
			'SELECT remaining_quantity,reserved_quantity FROM stock_batches WHERE id = ?',
			'i',
			array($fixture['batch_id'])
		);
		$this->assertNotNull($GLOBALS['phase0aFixtures']['shipping_method_id']);

		$response = $this->postForm(
			'/index.php/customer/checkout',
			'/index.php/customer/checkout/store',
			array(
				'address_id' => $customer['address_id'],
				'shipping_method_id' => $GLOBALS['phase0aFixtures']['shipping_method_id']
			)
		);
		$this->assertContains($response['status'], array(302, 303));

		$order = $this->dbRow(
			'SELECT id,status,total FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1',
			'i',
			array($customer['id'])
		);
		$this->assertNotNull($order);
		$payment = $this->dbRow(
			'SELECT status,amount FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1',
			'i',
			array($order['id'])
		);
		$shipment = $this->dbRow(
			'SELECT status FROM shipments WHERE order_id = ?',
			'i',
			array($order['id'])
		);
		$allocation = $this->dbRow(
			'SELECT batch_id,quantity FROM order_item_batches WHERE order_item_id IN (SELECT id FROM order_items WHERE order_id = ?) ORDER BY id LIMIT 1',
			'i',
			array($order['id'])
		);
		$after = $this->dbRow(
			'SELECT remaining_quantity,reserved_quantity FROM stock_batches WHERE id = ?',
			'i',
			array($fixture['batch_id'])
		);

		$this->assertSame('pending', $order['status']);
		$this->assertSame('pending', $payment['status']);
		$this->assertSame('pending', $shipment['status']);
		$this->assertSame((int) $fixture['batch_id'], (int) $allocation['batch_id']);
		$this->assertSame((float) $before['remaining_quantity'], (float) $after['remaining_quantity']);
		$this->assertSame((float) $before['reserved_quantity'] + 1.0, (float) $after['reserved_quantity']);
	}
}
