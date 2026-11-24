<?php

class PurchaseReceiptFlowTest extends Phase0aHttpTestCase
{
	public function testPurchaseOrderReceiptCreatesBatchAndMovement()
	{
		$this->loginAs('admin');
		$fixture = $GLOBALS['phase0aFixtures']['products']['purchase'];
		$supplierId = $GLOBALS['phase0aFixtures']['supplier_id'];
		$this->assertNotNull($supplierId, 'Scratch baseline must contain an active supplier for this flow.');

		$create = $this->postForm(
			'/index.php/admin/purchases/create',
			'/index.php/admin/purchases/store',
			array(
				'supplier_id' => $supplierId,
				'product_id' => $fixture['product_id'],
				'quantity' => '3',
				'purchase_price' => '4.00',
				'discount' => '0',
				'notes' => 'Phase 0a characterization'
			)
		);
		$this->assertContains($create['status'], array(302, 303));

		$order = $this->dbRow(
			'SELECT id,status FROM purchase_orders WHERE created_by = ? ORDER BY id DESC LIMIT 1',
			'i',
			array($GLOBALS['phase0aFixtures']['admin']['id'])
		);
		$item = $this->dbRow(
			'SELECT id FROM purchase_order_items WHERE purchase_order_id = ? ORDER BY id LIMIT 1',
			'i',
			array($order['id'])
		);
		$batchNumber = $fixture['batch_number'] . '-' . bin2hex(random_bytes(3));
		$receive = $this->postForm(
			'/index.php/admin/purchases/receive/' . (int) $order['id'],
			'/index.php/admin/purchases/receive_store/' . (int) $order['id'],
			array(
				'item_id' => $item['id'],
				'batch_number' => $batchNumber,
				'expiration_date' => date('Y-m-d', strtotime('+1 year'))
			)
		);
		$this->assertContains($receive['status'], array(302, 303));

		$orderAfter = $this->dbRow('SELECT status FROM purchase_orders WHERE id = ?', 'i', array($order['id']));
		$itemAfter = $this->dbRow('SELECT received_quantity FROM purchase_order_items WHERE id = ?', 'i', array($item['id']));
		$batch = $this->dbRow(
			'SELECT initial_quantity,remaining_quantity,status FROM stock_batches WHERE product_id = ? AND batch_number = ?',
			'is',
			array($fixture['product_id'], $batchNumber)
		);
		$movement = $this->dbRow(
			"SELECT quantity,movement_type FROM stock_movements WHERE batch_id = ? AND movement_type = 'purchase_receive' ORDER BY id DESC LIMIT 1",
			'i',
			array($this->dbRow(
				'SELECT id FROM stock_batches WHERE product_id = ? AND batch_number = ?',
				'is',
				array($fixture['product_id'], $batchNumber)
			)['id'])
		);

		$this->assertSame('received', $orderAfter['status']);
		$this->assertSame(3.0, (float) $itemAfter['received_quantity']);
		$this->assertSame(3.0, (float) $batch['initial_quantity']);
		$this->assertSame(3.0, (float) $batch['remaining_quantity']);
		$this->assertSame('active', $batch['status']);
		$this->assertSame(3.0, (float) $movement['quantity']);
	}
}
