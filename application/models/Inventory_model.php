<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventory_model extends CI_Model
{
	public function receive_batch($product_id, $batch_number, $expiration_date, $quantity, $purchase_price, $selling_price, $user_id)
	{
		$this->db->trans_start();

		$product = $this->db->where('id', (int) $product_id)
			->where('deleted_at IS NULL', null, false)
			->get('products')
			->row();
		if (!$product) {
			$this->db->trans_complete();
			return array('success' => false, 'message' => 'Produk tidak ditemukan.');
		}

		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration_date) ||
			strtotime($expiration_date) <= strtotime(date('Y-m-d'))) {
			$this->db->trans_complete();
			return array('success' => false, 'message' => 'Tanggal kedaluwarsa tidak valid.');
		}

		$existing = $this->db->where('product_id', (int) $product_id)
			->where('batch_number', trim($batch_number))
			->get('stock_batches')
			->row();
		if ($existing) {
			$this->db->trans_complete();
			return array('success' => false, 'message' => 'Nomor batch sudah digunakan untuk produk ini.');
		}

		$now = date('Y-m-d H:i:s');
		$this->db->insert('stock_batches', array(
			'product_id' => (int) $product_id,
			'batch_number' => trim($batch_number),
			'received_at' => $now,
			'expiration_date' => $expiration_date,
			'initial_quantity' => (float) $quantity,
			'remaining_quantity' => (float) $quantity,
			'reserved_quantity' => 0,
			'purchase_price' => (float) $purchase_price,
			'selling_price' => (float) $selling_price,
			'status' => 'active',
			'created_at' => $now,
			'updated_at' => $now
		));
		$batch_id = $this->db->insert_id();

		$balance = $this->total_available((int) $product_id);
		$this->db->insert('stock_movements', array(
			'product_id' => (int) $product_id,
			'batch_id' => $batch_id,
			'movement_type' => 'purchase_receive',
			'reference_type' => 'manual_receive',
			'reference_id' => $batch_id,
			'quantity' => (float) $quantity,
			'balance_after' => $balance,
			'notes' => 'Penerimaan batch manual',
			'created_by' => $user_id,
			'created_at' => $now
		));

		$this->db->trans_complete();
		return array(
			'success' => (bool) $this->db->trans_status(),
			'message' => $this->db->trans_status() ? 'Batch berhasil diterima.' : 'Penerimaan batch gagal.',
			'batch_id' => $this->db->trans_status() ? (int) $batch_id : null
		);
	}

	public function adjust_batch($batch_id, $quantity, $movement_type, $notes, $user_id)
	{
		$this->db->trans_start();
		$batch = $this->db->where('id', (int) $batch_id)
			->where('status', 'active')
			->get('stock_batches')
			->row();
		if (!$batch) {
			$this->db->trans_complete();
			return array('success' => false, 'message' => 'Batch tidak ditemukan.');
		}

		$quantity = (float) $quantity;
		if ($movement_type === 'stock_in') {
			$new_remaining = (float) $batch->remaining_quantity + $quantity;
			$movement_quantity = $quantity;
		} else {
			if ($quantity > ((float) $batch->remaining_quantity - (float) $batch->reserved_quantity)) {
				$this->db->trans_complete();
				return array('success' => false, 'message' => 'Stok tersedia tidak mencukupi.');
			}
			$new_remaining = (float) $batch->remaining_quantity - $quantity;
			$movement_quantity = -$quantity;
		}

		$now = date('Y-m-d H:i:s');
		$this->db->where('id', (int) $batch_id)->update('stock_batches', array(
			'remaining_quantity' => $new_remaining,
			'status' => $new_remaining <= 0 ? 'depleted' : 'active',
			'updated_at' => $now
		));
		$this->db->insert('stock_movements', array(
			'product_id' => (int) $batch->product_id,
			'batch_id' => (int) $batch_id,
			'movement_type' => $movement_type,
			'quantity' => $movement_quantity,
			'balance_after' => $this->total_available((int) $batch->product_id),
			'notes' => trim($notes),
			'created_by' => $user_id,
			'created_at' => $now
		));

		$this->db->trans_complete();
		return array(
			'success' => (bool) $this->db->trans_status(),
			'message' => $this->db->trans_status() ? 'Penyesuaian stok berhasil.' : 'Penyesuaian stok gagal.'
		);
	}

	public function allocate_fefo($product_id, $quantity)
	{
		$this->db->trans_start();
		$batches = $this->db->where('product_id', (int) $product_id)
			->where('status', 'active')
			->where('expiration_date >=', date('Y-m-d'))
			->where('remaining_quantity > reserved_quantity', null, false)
			->order_by('expiration_date', 'ASC')
			->order_by('id', 'ASC')
			->get('stock_batches')
			->result();

		$remaining = (float) $quantity;
		$allocations = array();
		foreach ($batches as $batch) {
			if ($remaining <= 0) {
				break;
			}
			$available = (float) $batch->remaining_quantity - (float) $batch->reserved_quantity;
			$taken = min($remaining, $available);
			if ($taken > 0) {
				$this->db->where('id', $batch->id)
					->set('reserved_quantity', 'reserved_quantity + ' . (float) $taken, false)
					->update('stock_batches');
				$allocations[] = array(
					'batch_id' => (int) $batch->id,
					'quantity' => (float) $taken,
					'purchase_price' => (float) $batch->purchase_price
				);
				$remaining -= $taken;
			}
		}

		$this->db->trans_complete();
		if ($remaining > 0) {
			return array('success' => false, 'allocations' => array(), 'message' => 'Stok tersedia tidak mencukupi.');
		}
		return array('success' => (bool) $this->db->trans_status(), 'allocations' => $allocations, 'message' => 'Stok berhasil direservasi FEFO.');
	}

	public function release_reservation($allocations)
	{
		$this->db->trans_start();
		foreach ($allocations as $allocation) {
			$this->db->where('id', (int) $allocation['batch_id'])
				->set('reserved_quantity', 'GREATEST(reserved_quantity - ' . (float) $allocation['quantity'] . ', 0)', false)
				->update('stock_batches');
		}
		$this->db->trans_complete();
		return (bool) $this->db->trans_status();
	}

	public function confirm_fefo_allocation($allocations)
	{
		$this->db->trans_start();
		foreach ($allocations as $allocation) {
			$batch = $this->db->where('id', (int) $allocation['batch_id'])->get('stock_batches')->row();
			if (!$batch) {
				$this->db->trans_complete();
				return false;
			}
			$this->db->where('id', (int) $allocation['batch_id'])
				->set('reserved_quantity', 'GREATEST(reserved_quantity - ' . (float) $allocation['quantity'] . ', 0)', false)
				->set('remaining_quantity', 'remaining_quantity - ' . (float) $allocation['quantity'], false)
				->update('stock_batches');
		}
		$this->db->trans_complete();
		return (bool) $this->db->trans_status();
	}

	public function total_available($product_id)
	{
		$row = $this->db->select('COALESCE(SUM(remaining_quantity - reserved_quantity), 0) AS total', false)
			->where('product_id', (int) $product_id)
			->where('status', 'active')
			->get('stock_batches')
			->row();
		return $row ? (float) $row->total : 0;
	}
}
