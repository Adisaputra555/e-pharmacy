<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sales extends Admin_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_permission('sales');
		$this->load->model('Inventory_model', 'inventory');
	}

	public function index()
	{
		$data['title'] = 'Penjualan Kasir';
		$data['orders'] = $this->db->order_by('id', 'DESC')->limit(50)->get('orders')->result();
		$data['content'] = $this->load->view('admin/sales/index', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function create()
	{
		$data['title'] = 'Transaksi Penjualan';
		$data['products'] = $this->db->where('deleted_at IS NULL', null, false)->where('is_active', 1)->order_by('name')->get('products')->result();
		$data['content'] = $this->load->view('admin/sales/form', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function store()
	{
		if ($this->input->method() !== 'post') {
			redirect('admin/sales');
		}

		$product_id = (int) $this->input->post('product_id');
		$quantity = (float) $this->input->post('quantity');
		$discount = (float) $this->input->post('discount');
		$product = $this->db->where('id', $product_id)->where('deleted_at IS NULL', null, false)->get('products')->row();
		if (!$product || $quantity <= 0 || $discount < 0 || (int) $product->requires_prescription === 1) {
			$this->session->set_flashdata('error', 'Produk atau kuantitas tidak valid untuk penjualan kasir.');
			redirect('admin/sales/create');
		}

		$allocation = $this->inventory->allocate_fefo($product_id, $quantity);
		if (!$allocation['success']) {
			$this->session->set_flashdata('error', $allocation['message']);
			redirect('admin/sales/create');
		}

		$now = date('Y-m-d H:i:s');
		$subtotal = (float) $product->selling_price * $quantity;
		$total = max(0, $subtotal - $discount);
		$order_number = 'POS-' . date('YmdHis') . '-' . rand(100, 999);

		$this->db->trans_start();
		$this->db->insert('orders', array(
			'order_number' => $order_number,
			'user_id' => null,
			'customer_name' => trim((string) $this->input->post('customer_name')) ?: 'Walk-in Customer',
			'customer_phone' => trim((string) $this->input->post('customer_phone')) ?: '-',
			'address_snapshot' => json_encode(array('channel' => 'offline')),
			'payment_method_id' => (int) $this->db->select('id')->where('code', 'cash')->get('payment_methods')->row()->id,
			'shipping_method_id' => (int) $this->db->select('id')->where('code', 'pickup')->get('shipping_methods')->row()->id,
			'status' => 'processing',
			'subtotal' => $subtotal,
			'discount' => $discount,
			'shipping_cost' => 0,
			'total' => $total,
			'requires_prescription' => 0,
			'checkout_token' => bin2hex(random_bytes(16)),
			'created_at' => $now,
			'updated_at' => $now
		));
		$order_id = $this->db->insert_id();
		$this->db->insert('order_items', array(
			'order_id' => $order_id,
			'product_id' => $product_id,
			'quantity' => $quantity,
			'unit_price_snapshot' => (float) $product->selling_price,
			'discount' => $discount,
			'total' => $total,
			'created_at' => $now
		));
		$order_item_id = $this->db->insert_id();

		foreach ($allocation['allocations'] as $batch_allocation) {
			$this->db->insert('order_item_batches', array(
				'order_item_id' => $order_item_id,
				'batch_id' => $batch_allocation['batch_id'],
				'quantity' => $batch_allocation['quantity'],
				'purchase_price_snapshot' => $batch_allocation['purchase_price'],
				'created_at' => $now
			));
			$this->db->where('id', (int) $batch_allocation['batch_id'])
				->set('reserved_quantity', 'GREATEST(reserved_quantity - ' . (float) $batch_allocation['quantity'] . ', 0)', false)
				->set('remaining_quantity', 'remaining_quantity - ' . (float) $batch_allocation['quantity'], false)
				->update('stock_batches');
			$this->db->insert('stock_movements', array(
				'product_id' => $product_id,
				'batch_id' => $batch_allocation['batch_id'],
				'movement_type' => 'sale',
				'reference_type' => 'order',
				'reference_id' => $order_id,
				'quantity' => -$batch_allocation['quantity'],
				'balance_after' => $this->inventory->total_available($product_id),
				'notes' => 'Penjualan kasir',
				'created_by' => (int) $this->session->userdata('user_id'),
				'created_at' => $now
			));
		}

		$payment_method_id = (int) $this->db->select('id')->where('code', 'cash')->get('payment_methods')->row()->id;
		$this->db->insert('payments', array(
			'order_id' => $order_id,
			'payment_method_id' => $payment_method_id,
			'reference_number' => $order_number,
			'amount' => $total,
			'status' => 'paid',
			'paid_at' => $now,
			'created_at' => $now,
			'updated_at' => $now
		));
		$this->db->insert('order_status_histories', array(
			'order_id' => $order_id,
			'status' => 'processing',
			'notes' => 'Penjualan tunai kasir',
			'created_by' => (int) $this->session->userdata('user_id'),
			'created_at' => $now
		));
		$this->record_activity('cash_sale_created', 'order', $order_id, null, array(
			'order_number' => $order_number,
			'total' => $total,
			'product_id' => $product_id,
			'quantity' => $quantity
		));
		$this->db->trans_complete();

		if (!$this->db->trans_status()) {
			$this->inventory->release_reservation($allocation['allocations']);
			$this->session->set_flashdata('error', 'Transaksi penjualan gagal.');
			redirect('admin/sales/create');
		}

		$this->session->set_flashdata('success', 'Penjualan ' . $order_number . ' berhasil.');
		redirect('admin/sales/invoice/' . $order_id);
	}

	public function invoice($id)
	{
		$data = $this->invoice_data($id);
		$data['title'] = 'Invoice ' . $data['order']->order_number;
		$data['invoice_content'] = $this->load->view('admin/sales/invoice_content', $data, true);
		$data['content'] = $this->load->view('admin/sales/invoice', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function invoice_drawer($id)
	{
		$data = $this->invoice_data($id);
		$this->load->view('admin/sales/invoice_content', $data);
	}

	private function invoice_data($id)
	{
		$order = $this->db->where('id', (int) $id)->get('orders')->row();
		if (!$order) {
			show_404();
		}

		return array(
			'order' => $order,
			'items' => $this->db->select('order_items.*, products.name AS product_name')
				->join('products', 'products.id = order_items.product_id')
				->where('order_items.order_id', (int) $id)
				->get('order_items')
				->result()
		);
	}
}
