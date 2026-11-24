<?php

class StockOpnameFlowTest extends Phase0aHttpTestCase
{
	public function testOpnamePersistsCountDifferenceAndMovement()
	{
		$this->loginAs('admin');
		$fixture = $GLOBALS['phase0aFixtures']['products']['opname'];
		$before = $this->dbRow(
			'SELECT remaining_quantity,reserved_quantity FROM stock_batches WHERE id = ?',
			'i',
			array($fixture['batch_id'])
		);
		$actual = (float) $before['remaining_quantity'] - 0.5;

		$response = $this->postForm(
			'/index.php/admin/inventory/opname',
			'/index.php/admin/inventory/opname_store',
			array('actual' => array($fixture['batch_id'] => number_format($actual, 3, '.', '')))
		);
		$this->assertContains($response['status'], array(302, 303));

		$opname = $this->dbRow('SELECT id,status FROM stock_opnames ORDER BY id DESC LIMIT 1');
		$item = $this->dbRow(
			'SELECT system_quantity,actual_quantity,difference_quantity FROM stock_opname_items WHERE stock_opname_id = ? AND batch_id = ?',
			'ii',
			array($opname['id'], $fixture['batch_id'])
		);
		$after = $this->dbRow('SELECT remaining_quantity,status FROM stock_batches WHERE id = ?', 'i', array($fixture['batch_id']));
		$movement = $this->dbRow(
			"SELECT quantity,movement_type FROM stock_movements WHERE reference_type = 'stock_opname' AND reference_id = ? AND batch_id = ? ORDER BY id DESC LIMIT 1",
			'ii',
			array($opname['id'], $fixture['batch_id'])
		);

		$this->assertSame('approved', $opname['status']);
		$this->assertSame((float) $before['remaining_quantity'], (float) $item['system_quantity']);
		$this->assertSame($actual, (float) $item['actual_quantity']);
		$this->assertSame(-0.5, (float) $item['difference_quantity']);
		$this->assertSame($actual, (float) $after['remaining_quantity']);
		$this->assertSame('active', $after['status']);
		$this->assertSame(-0.5, (float) $movement['quantity']);
		$this->assertSame('opname', $movement['movement_type']);
	}
}
