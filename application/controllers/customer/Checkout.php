<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Checkout extends Customer_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('Inventory_model', 'inventory');
	}

	public function index()
	{
		$user_id = (int) $this->session->userdata('user_id');
		$addresses = $this->db->where('user_id', $user_id)->where('deleted_at IS NULL', null, false)->get('user_addresses')->result();
		$cart = $this->db->where('user_id', $user_id)->where('status', 'active')->get('carts')->row();
		$items = array();
		if ($cart) {
			$items = $this->db->select('cart_items.*, products.name, products.selling_price, products.requires_prescription')
				->join('products', 'products.id = cart_items.product_id')
				->where('cart_items.cart_id', $cart->id)
				->get('cart_items')
				->result();
		}
		$data = array(
			'title' => 'Checkout',
			'addresses' => $addresses,
			'items' => $items,
			'shipping_methods' => $this->db->where('is_active', 1)->get('shipping_methods')->result()
		);
		$this->load->view('customer/checkout', $data);
	}

	public function store()
	{
		if ($this->input->method() !== 'post') {
			redirect('customer/checkout');
		}
		$user_id = (int) $this->session->userdata('user_id');
		$address_id = (int) $this->input->post('address_id');
		$shipping_id = (int) $this->input->post('shipping_method_id');
		$address = $this->db->where('id', $address_id)->where('user_id', $user_id)->get('user_addresses')->row();
		$shipping = $this->db->where('id', $shipping_id)->where('is_active', 1)->get('shipping_methods')->row();
		$cart = $this->db->where('user_id', $user_id)->where('status', 'active')->get('carts')->row();
		if (!$address || !$shipping || !$cart) {
			$this->session->set_flashdata('error', 'Alamat, pengiriman, atau keranjang tidak valid.');
			redirect('customer/checkout');
		}

		$items = $this->db->select('cart_items.*, products.name, products.selling_price, products.requires_prescription')
			->join('products', 'products.id = cart_items.product_id')
			->where('cart_items.cart_id', $cart->id)
			->get('cart_items')
			->result();
		if (!$items) {
			redirect('customer/cart');
		}

		$subtotal = 0;
		$allocations_by_item = array();
		foreach ($items as $item) {
			if ((int) $item->requires_prescription === 1) {
				$approved = $this->db->where('user_id', $user_id)
					->where('status', 'approved')
					->order_by('created_at', 'DESC')
					->get('prescriptions')
					->row();
				if (!$approved) {
					$this->session->set_flashdata('error', 'Pesanan mengandung obat resep. Unggah resep dan tunggu persetujuan apoteker.');
					redirect('customer/checkout');
				}
			}
			$available = $this->inventory->total_available((int) $item->product_id);
			if ((float) $item->quantity > $available) {
				$this->session->set_flashdata('error', 'Stok tidak mencukupi untuk ' . $item->name . '.');
				redirect('customer/checkout');
			}
			$allocation = $this->inventory->allocate_fefo((int) $item->product_id, (float) $item->quantity);
			if (!$allocation['success']) {
				$this->session->set_flashdata('error', $allocation['message']);
				redirect('customer/checkout');
			}
			$allocations_by_item[$item->id] = $allocation['allocations'];
			$subtotal += (float) $item->selling_price * (float) $item->quantity;
		}

		$now = date('Y-m-d H:i:s');
		$shipping_cost = (float) $shipping->base_cost;
		$total = $subtotal + $shipping_cost;
		$order_number = 'ORD-' . date('YmdHis') . '-' . rand(100, 999);
		$payment_method = $this->db->where('code', 'bank_transfer')->where('is_active', 1)->get('payment_methods')->row();

		$this->db->trans_start();
		$this->db->insert('orders', array(
			'order_number' => $order_number,
			'user_id' => $user_id,
			'customer_name' => $address->recipient_name,
			'customer_phone' => $address->phone,
			'address_snapshot' => json_encode(array(
				'label' => $address->label,
				'address_line' => $address->address_line,
				'district' => $address->district,
				'city' => $address->city,
				'province' => $address->province,
				'postal_code' => $address->postal_code
			)),
			'payment_method_id' => $payment_method ? $payment_method->id : null,
			'shipping_method_id' => $shipping->id,
			'status' => 'pending',
			'subtotal' => $subtotal,
			'discount' => 0,
			'shipping_cost' => $shipping_cost,
			'total' => $total,
			'requires_prescription' => 0,
			'prescription_status' => null,
			'checkout_token' => bin2hex(random_bytes(16)),
			'created_at' => $now,
			'updated_at' => $now
		));
		$order_id = $this->db->insert_id();

		foreach ($items as $item) {
			$this->db->insert('order_items', array(
				'order_id' => $order_id,
				'product_id' => (int) $item->product_id,
				'quantity' => (float) $item->quantity,
				'unit_price_snapshot' => (float) $item->selling_price,
				'discount' => 0,
				'total' => (float) $item->selling_price * (float) $item->quantity,
				'created_at' => $now
			));
			$order_item_id = $this->db->insert_id();
			foreach ($allocations_by_item[$item->id] as $allocation) {
				$this->db->insert('order_item_batches', array(
					'order_item_id' => $order_item_id,
					'batch_id' => $allocation['batch_id'],
					'quantity' => $allocation['quantity'],
					'purchase_price_snapshot' => $allocation['purchase_price'],
					'created_at' => $now
				));
			}
		}
		$this->db->insert('payments', array(
			'order_id' => $order_id,
			'payment_method_id' => $payment_method ? $payment_method->id : null,
			'reference_number' => $order_number,
			'amount' => $total,
			'status' => 'pending',
			'created_at' => $now,
			'updated_at' => $now
		));
		$this->db->insert('shipments', array(
			'order_id' => $order_id,
			'shipping_method_id' => $shipping->id,
			'recipient_name' => $address->recipient_name,
			'recipient_phone' => $address->phone,
			'address_snapshot' => json_encode(array('address_line' => $address->address_line, 'city' => $address->city, 'province' => $address->province)),
			'shipping_cost' => $shipping_cost,
			'status' => 'pending',
			'created_at' => $now,
			'updated_at' => $now
		));
		$this->db->insert('order_status_histories', array(
			'order_id' => $order_id,
			'status' => 'pending',
			'notes' => 'Pesanan online dibuat',
			'created_by' => $user_id,
			'created_at' => $now
		));
		$this->db->where('id', $cart->id)->update('carts', array('status' => 'checked_out', 'updated_at' => $now));
		$this->db->where('cart_id', $cart->id)->delete('cart_items');
		$this->record_activity('order_created', 'order', $order_id, null, array(
			'order_number' => $order_number,
			'total' => $total,
			'item_count' => count($items)
		));
		$this->db->trans_complete();

		if (!$this->db->trans_status()) {
			foreach ($allocations_by_item as $allocations) {
				$this->inventory->release_reservation($allocations);
			}
			$this->session->set_flashdata('error', 'Checkout gagal disimpan.');
			redirect('customer/checkout');
		}
		redirect('customer/orders/detail/' . $order_id);
	}
}
