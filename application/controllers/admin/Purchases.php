<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Purchases extends Admin_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_permission('purchases');
	}

	public function index()
	{
		$data['title'] = 'Pembelian';
		$data['orders'] = $this->db->select('purchase_orders.*, suppliers.name AS supplier_name')
			->join('suppliers', 'suppliers.id = purchase_orders.supplier_id')
			->order_by('purchase_orders.id', 'DESC')
			->get('purchase_orders')
			->result();
		$data['content'] = $this->load->view('admin/purchases/index', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function create()
	{
		$data['title'] = 'Pembelian Baru';
		$data['suppliers'] = $this->db->where('deleted_at IS NULL', null, false)->where('is_active', 1)->order_by('name')->get('suppliers')->result();
		$data['products'] = $this->db->where('deleted_at IS NULL', null, false)->where('is_active', 1)->order_by('name')->get('products')->result();
		$data['content'] = $this->load->view('admin/purchases/form', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function store()
	{
		if ($this->input->method() !== 'post') {
			redirect('admin/purchases');
		}

		$quantity = (float) $this->input->post('quantity');
		$price = (float) $this->input->post('purchase_price');
		$discount = (float) $this->input->post('discount');
		$total = ($quantity * $price) - $discount;
		if ($quantity <= 0 || $price < 0 || $discount < 0 || $total < 0) {
			$this->session->set_flashdata('error', 'Nilai pembelian tidak valid.');
			redirect('admin/purchases/create');
		}

		$this->db->trans_start();
		$purchase_number = 'PO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
		$now = date('Y-m-d H:i:s');
		$this->db->insert('purchase_orders', array(
			'purchase_number' => $purchase_number,
			'supplier_id' => (int) $this->input->post('supplier_id'),
			'status' => 'ordered',
			'purchase_date' => date('Y-m-d'),
			'subtotal' => $quantity * $price,
			'discount' => $discount,
			'total' => $total,
			'notes' => trim((string) $this->input->post('notes')),
			'created_by' => (int) $this->session->userdata('user_id'),
			'created_at' => $now,
			'updated_at' => $now
		));
		$purchase_id = $this->db->insert_id();
		$this->db->insert('purchase_order_items', array(
			'purchase_order_id' => $purchase_id,
			'product_id' => (int) $this->input->post('product_id'),
			'quantity' => $quantity,
			'purchase_price' => $price,
			'discount' => $discount,
			'total' => $total,
			'received_quantity' => 0,
			'created_at' => $now,
			'updated_at' => $now
		));
		$this->record_activity('purchase_created', 'purchase_order', $purchase_id, null, array(
			'purchase_number' => $purchase_number,
			'supplier_id' => (int) $this->input->post('supplier_id'),
			'product_id' => (int) $this->input->post('product_id'),
			'quantity' => $quantity,
			'total' => $total
		));
		$this->db->trans_complete();

		if (!$this->db->trans_status()) {
			$this->session->set_flashdata('error', 'Pembelian gagal disimpan.');
		} else {
			$this->session->set_flashdata('success', 'Pembelian ' . $purchase_number . ' berhasil dibuat.');
		}
		redirect('admin/purchases');
	}

	public function receive($id)
	{
		$order = $this->db->select('purchase_orders.*, suppliers.name AS supplier_name')
			->join('suppliers', 'suppliers.id = purchase_orders.supplier_id')
			->where('purchase_orders.id', (int) $id)
			->get('purchase_orders')
			->row();
		if (!$order) {
			show_404();
		}
		$data['title'] = 'Penerimaan ' . $order->purchase_number;
		$data['order'] = $order;
		$data['items'] = $this->db->select('purchase_order_items.*, products.name AS product_name')
			->join('products', 'products.id = purchase_order_items.product_id')
			->where('purchase_order_items.purchase_order_id', (int) $id)
			->get('purchase_order_items')
			->result();
		$data['content'] = $this->load->view('admin/purchases/receive', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function receive_store($id)
	{
		if ($this->input->method() !== 'post') {
			redirect('admin/purchases');
		}

		$item_id = (int) $this->input->post('item_id');
		$batch_number = trim((string) $this->input->post('batch_number'));
		$expiration_date = (string) $this->input->post('expiration_date');
		$item = $this->db->where('id', $item_id)
			->where('purchase_order_id', (int) $id)
			->get('purchase_order_items')
			->row();
		if (!$item || $item->received_quantity >= $item->quantity || $batch_number === '' ||
			!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration_date) ||
			strtotime($expiration_date) <= strtotime(date('Y-m-d'))) {
			$this->session->set_flashdata('error', 'Data penerimaan tidak valid.');
			redirect('admin/purchases/receive/' . (int) $id);
		}

		$existing = $this->db->where('product_id', $item->product_id)
			->where('batch_number', $batch_number)
			->get('stock_batches')
			->row();
		if ($existing) {
			$this->session->set_flashdata('error', 'Nomor batch sudah digunakan.');
			redirect('admin/purchases/receive/' . (int) $id);
		}

		$this->db->trans_start();
		$now = date('Y-m-d H:i:s');
		$quantity = (float) $item->quantity - (float) $item->received_quantity;
		$this->db->insert('stock_batches', array(
			'product_id' => (int) $item->product_id,
			'batch_number' => $batch_number,
			'received_at' => $now,
			'expiration_date' => $expiration_date,
			'initial_quantity' => $quantity,
			'remaining_quantity' => $quantity,
			'reserved_quantity' => 0,
			'purchase_price' => (float) $item->purchase_price,
			'selling_price' => (float) $this->db->select('selling_price')->where('id', $item->product_id)->get('products')->row()->selling_price,
			'status' => 'active',
			'created_at' => $now,
			'updated_at' => $now
		));
		$batch_id = $this->db->insert_id();
		$this->db->where('id', $item_id)->update('purchase_order_items', array(
			'batch_number' => $batch_number,
			'expiration_date' => $expiration_date,
			'received_quantity' => $quantity,
			'updated_at' => $now
		));
		$this->db->insert('stock_movements', array(
			'product_id' => (int) $item->product_id,
			'batch_id' => $batch_id,
			'movement_type' => 'purchase_receive',
			'reference_type' => 'purchase_order',
			'reference_id' => (int) $id,
			'quantity' => $quantity,
			'balance_after' => $this->db->select_sum('remaining_quantity')
				->where('product_id', (int) $item->product_id)
				->where('status', 'active')
				->get('stock_batches')->row()->remaining_quantity,
			'notes' => 'Penerimaan pesanan pembelian',
			'created_by' => (int) $this->session->userdata('user_id'),
			'created_at' => $now
		));
		$this->db->where('id', (int) $id)->update('purchase_orders', array(
			'status' => 'received',
			'received_by' => (int) $this->session->userdata('user_id'),
			'received_at' => $now,
			'updated_at' => $now
		));
		$this->record_activity('purchase_received', 'purchase_order', (int) $id, array(
			'status' => 'ordered'
		), array(
			'status' => 'received',
			'batch_id' => (int) $batch_id,
			'batch_number' => $batch_number,
			'quantity' => $quantity
		));
		$this->db->trans_complete();

		if (!$this->db->trans_status()) {
			$this->session->set_flashdata('error', 'Penerimaan barang gagal.');
		} else {
			$this->session->set_flashdata('success', 'Penerimaan barang berhasil dan stok batch bertambah.');
		}
		redirect('admin/purchases');
	}
}
