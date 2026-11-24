<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventory extends Admin_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_permission('inventory');
		$this->load->model('Inventory_model', 'inventory');
	}

	public function index()
	{
		$data['title'] = 'Inventaris';
		$data['products'] = $this->db->select('products.id, products.code, products.name, products.minimum_stock')
			->where('products.deleted_at IS NULL', null, false)
			->order_by('products.name')
			->get('products')
			->result();
		foreach ($data['products'] as $product) {
			$product->available = $this->inventory->total_available($product->id);
		}
		$data['receive_products'] = $this->db->where('deleted_at IS NULL', null, false)
			->where('is_active', 1)
			->order_by('name')
			->get('products')
			->result();
		$data['batches'] = $this->db->select('stock_batches.*, products.name AS product_name')
			->join('products', 'products.id = stock_batches.product_id')
			->where('stock_batches.status', 'active')
			->order_by('products.name')
			->order_by('stock_batches.expiration_date')
			->get('stock_batches')
			->result();
		$data['movements'] = $this->db->select('stock_movements.*, products.name AS product_name, stock_batches.batch_number')
			->join('products', 'products.id = stock_movements.product_id')
			->join('stock_batches', 'stock_batches.id = stock_movements.batch_id', 'left')
			->order_by('stock_movements.created_at', 'DESC')
			->limit(100)
			->get('stock_movements')
			->result();
		$data['expiring_batches'] = $this->db->select('stock_batches.*, products.name AS product_name')
			->join('products', 'products.id = stock_batches.product_id')
			->where('stock_batches.status', 'active')
			->where('stock_batches.expiration_date >=', date('Y-m-d'))
			->where('stock_batches.expiration_date <=', date('Y-m-d', strtotime('+30 days')))
			->order_by('stock_batches.expiration_date')
			->get('stock_batches')
			->result();
		$data['content'] = $this->load->view('admin/inventory/index', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function receive()
	{
		$data['title'] = 'Terima Batch';
		$data['products'] = $this->db->where('deleted_at IS NULL', null, false)
			->where('is_active', 1)
			->order_by('name')
			->get('products')
			->result();
		$data['content'] = $this->load->view('admin/inventory/receive', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function receive_store()
	{
		if ($this->input->method() !== 'post') {
			redirect('admin/inventory');
		}
		$result = $this->inventory->receive_batch(
			(int) $this->input->post('product_id'),
			(string) $this->input->post('batch_number'),
			(string) $this->input->post('expiration_date'),
			(float) $this->input->post('quantity'),
			(float) $this->input->post('purchase_price'),
			(float) $this->input->post('selling_price'),
			(int) $this->session->userdata('user_id')
		);
		if ($result['success']) {
			$this->record_activity('stock_received', 'stock_batch', isset($result['batch_id']) ? (int) $result['batch_id'] : null, null, array(
				'product_id' => (int) $this->input->post('product_id'),
				'batch_number' => trim((string) $this->input->post('batch_number')),
				'expiration_date' => (string) $this->input->post('expiration_date'),
				'quantity' => (float) $this->input->post('quantity')
			));
		}
		$this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
		redirect('admin/inventory#inventory-receive');
	}

	public function adjust()
	{
		$data['title'] = 'Penyesuaian Stok';
		$data['batches'] = $this->db->select('stock_batches.*, products.name AS product_name')
			->join('products', 'products.id = stock_batches.product_id')
			->where('stock_batches.status', 'active')
			->order_by('products.name')
			->order_by('stock_batches.expiration_date')
			->get('stock_batches')
			->result();
		$data['content'] = $this->load->view('admin/inventory/adjust', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function adjust_store()
	{
		if ($this->input->method() !== 'post') {
			redirect('admin/inventory');
		}
		$quantity = (float) $this->input->post('quantity');
		if ($quantity <= 0) {
			$this->session->set_flashdata('error', 'Jumlah penyesuaian harus lebih dari nol.');
			redirect('admin/inventory#inventory-adjust');
		}
		$result = $this->inventory->adjust_batch(
			(int) $this->input->post('batch_id'),
			$quantity,
			(string) $this->input->post('movement_type'),
			(string) $this->input->post('notes'),
			(int) $this->session->userdata('user_id')
		);
		if ($result['success']) {
			$this->record_activity('stock_adjusted', 'stock_batch', (int) $this->input->post('batch_id'), null, array(
				'quantity' => $quantity,
				'movement_type' => (string) $this->input->post('movement_type'),
				'notes' => trim((string) $this->input->post('notes'))
			));
		}
		$this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
		redirect('admin/inventory#inventory-adjust');
	}

	public function movements()
	{
		$data['title'] = 'Riwayat Pergerakan Stok';
		$data['movements'] = $this->db->select('stock_movements.*, products.name AS product_name, stock_batches.batch_number')
			->join('products', 'products.id = stock_movements.product_id')
			->join('stock_batches', 'stock_batches.id = stock_movements.batch_id', 'left')
			->order_by('stock_movements.created_at', 'DESC')
			->limit(100)
			->get('stock_movements')
			->result();
		$data['content'] = $this->load->view('admin/inventory/movements', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function opname()
	{
		$data['title'] = 'Stok Opname';
		$data['batches'] = $this->db->select('stock_batches.*, products.name AS product_name')
			->join('products', 'products.id = stock_batches.product_id')
			->where('stock_batches.status', 'active')
			->order_by('products.name')
			->order_by('stock_batches.expiration_date')
			->get('stock_batches')
			->result();
		$data['content'] = $this->load->view('admin/inventory/opname', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function opname_store()
	{
		if ($this->input->method() !== 'post') {
			redirect('admin/inventory/opname');
		}

		$actual_values = $this->input->post('actual');
		if (!is_array($actual_values) || !$actual_values) {
			$this->session->set_flashdata('error', 'Tidak ada data stok fisik.');
			redirect('admin/inventory#inventory-opname');
		}

		$this->db->trans_start();
		$user_id = (int) $this->session->userdata('user_id');
		$now = date('Y-m-d H:i:s');
		$opname_number = 'OP-' . date('Ymd-His');
		$this->db->insert('stock_opnames', array(
			'opname_number' => $opname_number,
			'opname_date' => date('Y-m-d'),
			'status' => 'approved',
			'notes' => 'Stok opname diterapkan',
			'created_by' => $user_id,
			'approved_by' => $user_id,
			'created_at' => $now,
			'updated_at' => $now
		));
		$opname_id = $this->db->insert_id();

		foreach ($actual_values as $batch_id => $actual_quantity) {
			$batch_id = (int) $batch_id;
			$actual_quantity = (float) $actual_quantity;
			$batch = $this->db->where('id', $batch_id)->get('stock_batches')->row();
			if (!$batch) {
				continue;
			}
			$system_quantity = (float) $batch->remaining_quantity;
			$difference = $actual_quantity - $system_quantity;

			$this->db->insert('stock_opname_items', array(
				'stock_opname_id' => $opname_id,
				'product_id' => (int) $batch->product_id,
				'batch_id' => $batch_id,
				'system_quantity' => $system_quantity,
				'actual_quantity' => $actual_quantity,
				'difference_quantity' => $difference,
				'created_at' => $now
			));

			if (abs($difference) > 0.0001) {
				$this->db->where('id', $batch_id)->update('stock_batches', array(
					'remaining_quantity' => $actual_quantity,
					'status' => $actual_quantity <= 0 ? 'depleted' : 'active',
					'updated_at' => $now
				));
				$this->db->insert('stock_movements', array(
					'product_id' => (int) $batch->product_id,
					'batch_id' => $batch_id,
					'movement_type' => 'opname',
					'reference_type' => 'stock_opname',
					'reference_id' => $opname_id,
					'quantity' => $difference,
					'balance_after' => $this->inventory->total_available((int) $batch->product_id),
					'notes' => 'Penyesuaian stok opname',
					'created_by' => $user_id,
					'created_at' => $now
				));
			}
		}

		$this->db->trans_complete();
		if ($this->db->trans_status()) {
			$this->record_activity('stock_opname_completed', 'stock_opname', $opname_id, null, array(
				'opname_number' => $opname_number,
				'batch_count' => count($actual_values)
			));
			$this->session->set_flashdata('success', 'Stok opname berhasil disimpan.');
		} else {
			$this->session->set_flashdata('error', 'Stok opname gagal disimpan.');
		}
		redirect('admin/inventory#inventory-opname');
	}
}
