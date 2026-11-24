<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Orders extends Customer_Controller
{
	public function index()
	{
		$user_id = (int) $this->session->userdata('user_id');
		$orders = $this->db->where('user_id', $user_id)->order_by('created_at', 'DESC')->get('orders')->result();
		$this->load->view('customer/orders', array('title' => 'Pesanan', 'orders' => $orders));
	}

	public function detail($id)
	{
		$user_id = (int) $this->session->userdata('user_id');
		$order = $this->db->where('id', (int) $id)->where('user_id', $user_id)->get('orders')->row();
		if (!$order) {
			show_404();
		}
		$items = $this->db->select('order_items.*, products.name')
			->join('products', 'products.id = order_items.product_id')
			->where('order_items.order_id', $order->id)
			->get('order_items')
			->result();
		$shipment = $this->db->where('order_id', $order->id)->get('shipments')->row();
		$this->load->view('customer/order_detail', array('title' => 'Detail Pesanan', 'order' => $order, 'items' => $items, 'shipment' => $shipment));
	}
}
