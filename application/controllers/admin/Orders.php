<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Orders extends Admin_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_permission('orders');
	}

	public function index()
	{
		$data['title'] = 'Pesanan';
		$data['orders'] = $this->db->order_by('created_at', 'DESC')->limit(100)->get('orders')->result();
		$data['content'] = $this->load->view('admin/orders/index', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function cancel($id)
	{
		if ($this->input->method() !== 'post') {
			redirect('admin/orders');
		}
		$order = $this->db->where('id', (int) $id)->get('orders')->row();
		if (!$order) {
			show_404();
		}
		$payment = $this->db->where('order_id', (int) $id)->get('payments')->row();
		if ($payment && $payment->status === 'paid') {
			$this->session->set_flashdata('error', 'Pesanan dengan pembayaran terverifikasi tidak dapat dibatalkan langsung.');
			redirect('admin/orders');
		}

		$this->db->trans_start();
		$allocations = $this->db->select('order_item_batches.batch_id, order_item_batches.quantity')
			->join('order_items', 'order_items.id = order_item_batches.order_item_id')
			->where('order_items.order_id', (int) $id)
			->get('order_item_batches')
			->result();
		foreach ($allocations as $allocation) {
			$this->db->where('id', (int) $allocation->batch_id)
				->set('reserved_quantity', 'GREATEST(reserved_quantity - ' . (float) $allocation->quantity . ', 0)', false)
				->update('stock_batches');
		}
		$now = date('Y-m-d H:i:s');
		$this->db->where('id', (int) $id)->update('orders', array('status' => 'cancelled', 'updated_at' => $now));
		$this->db->insert('order_status_histories', array(
			'order_id' => (int) $id,
			'status' => 'cancelled',
			'notes' => 'Pesanan dibatalkan dan reservasi stok dilepaskan',
			'created_by' => (int) $this->session->userdata('user_id'),
			'created_at' => $now
		));
		if ($payment) {
			$this->db->where('id', $payment->id)->update('payments', array('status' => 'failed', 'updated_at' => $now));
		}
		$this->record_activity('order_cancelled', 'order', (int) $id, array(
			'order_number' => $order->order_number,
			'status' => $order->status
		), array('status' => 'cancelled'));
		$this->db->trans_complete();
		$this->session->set_flashdata($this->db->trans_status() ? 'success' : 'error', $this->db->trans_status() ? 'Pesanan dibatalkan dan stok dilepas.' : 'Pembatalan pesanan gagal.');
		redirect('admin/orders');
	}
}
