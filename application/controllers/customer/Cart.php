<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cart extends Customer_Controller
{
	public function index()
	{
		$user_id = (int) $this->session->userdata('user_id');
		$cart = $this->db->where('user_id', $user_id)->where('status', 'active')->get('carts')->row();
		$items = array();
		if ($cart) {
			$items = $this->db->select('cart_items.*, products.name, products.selling_price, products.requires_prescription')
				->join('products', 'products.id = cart_items.product_id')
				->where('cart_items.cart_id', $cart->id)
				->get('cart_items')
				->result();
		}
		$this->load->view('customer/cart', array('title' => 'Keranjang', 'items' => $items));
	}

	public function add()
	{
		if ($this->input->method() !== 'post') {
			redirect('customer/catalog');
		}
		$product_id = (int) $this->input->post('product_id');
		$quantity = (float) $this->input->post('quantity');
		$product = $this->db->where('id', $product_id)->where('is_active', 1)->where('deleted_at IS NULL', null, false)->get('products')->row();
		if (!$product || $quantity <= 0) {
			$this->session->set_flashdata('error', 'Produk atau jumlah tidak valid.');
			redirect('customer/catalog');
		}
		if ((int) $product->requires_prescription === 1) {
			$approved = $this->db->where('user_id', (int) $this->session->userdata('user_id'))
				->where('status', 'approved')
				->order_by('created_at', 'DESC')
				->get('prescriptions')
				->row();
			if (!$approved) {
				$this->session->set_flashdata('error', 'Obat resep belum dapat ditambahkan. Unggah resep dan tunggu persetujuan apoteker.');
				redirect('customer/catalog/detail/' . $product_id);
			}
		}

		$user_id = (int) $this->session->userdata('user_id');
		$this->db->trans_start();
		$cart = $this->db->where('user_id', $user_id)->where('status', 'active')->get('carts')->row();
		if (!$cart) {
			$now = date('Y-m-d H:i:s');
			$this->db->insert('carts', array('user_id' => $user_id, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now));
			$cart_id = $this->db->insert_id();
		} else {
			$cart_id = $cart->id;
		}
		$item = $this->db->where('cart_id', $cart_id)->where('product_id', $product_id)->get('cart_items')->row();
		if ($item) {
			$new_quantity = (float) $item->quantity + $quantity;
			$this->db->where('id', $item->id)->update('cart_items', array('quantity' => $new_quantity, 'updated_at' => date('Y-m-d H:i:s')));
			$activity_item_id = (int) $item->id;
			$old_quantity = (float) $item->quantity;
		} else {
			$now = date('Y-m-d H:i:s');
			$this->db->insert('cart_items', array('cart_id' => $cart_id, 'product_id' => $product_id, 'quantity' => $quantity, 'created_at' => $now, 'updated_at' => $now));
			$activity_item_id = (int) $this->db->insert_id();
			$old_quantity = 0;
			$new_quantity = $quantity;
		}
		$this->record_activity('cart_item_updated', 'cart_item', $activity_item_id, array('quantity' => $old_quantity), array(
			'product_id' => $product_id,
			'quantity' => $new_quantity
		));
		$this->db->trans_complete();
		redirect('customer/cart');
	}

	public function remove_item($id)
	{
		$user_id = (int) $this->session->userdata('user_id');
		$cart = $this->db->where('user_id', $user_id)->where('status', 'active')->get('carts')->row();
		if ($cart) {
			$item = $this->db->where('id', (int) $id)->where('cart_id', $cart->id)->get('cart_items')->row();
			$deleted = $this->db->where('id', (int) $id)->where('cart_id', $cart->id)->delete('cart_items');
			if ($deleted && $item) {
				$this->record_activity('cart_item_removed', 'cart_item', (int) $id, array(
					'product_id' => (int) $item->product_id,
					'quantity' => (float) $item->quantity
				));
			}
		}
		redirect('customer/cart');
	}
}
