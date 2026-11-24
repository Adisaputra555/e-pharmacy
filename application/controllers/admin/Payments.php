<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Payments extends Admin_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_permission('payments');
	}

	public function index()
	{
		$data['title'] = 'Pembayaran';
		$data['payments'] = $this->db->select('payments.*, orders.order_number')
			->join('orders', 'orders.id = payments.order_id')
			->order_by('payments.created_at', 'DESC')
			->limit(100)
			->get('payments')
			->result();
		$data['content'] = $this->load->view('admin/payments/index', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function verify($id)
	{
		if ($this->input->method() !== 'post') {
			redirect('admin/payments');
		}
		$payment = $this->db->where('id', (int) $id)->get('payments')->row();
		if (!$payment) {
			show_404();
		}
		if ($payment->status === 'paid') {
			$this->session->set_flashdata('success', 'Pembayaran sudah pernah diverifikasi.');
			redirect('admin/payments');
		}

		$this->db->trans_start();
		$now = date('Y-m-d H:i:s');
		$this->db->where('id', (int) $id)->update('payments', array(
			'status' => 'paid',
			'reference_number' => trim((string) $this->input->post('reference_number')) ?: $payment->reference_number,
			'paid_at' => $now,
			'updated_at' => $now
		));
		$this->db->where('id', (int) $payment->order_id)->update('orders', array('status' => 'verified', 'updated_at' => $now));
		$this->db->insert('order_status_histories', array(
			'order_id' => (int) $payment->order_id,
			'status' => 'verified',
			'notes' => 'Pembayaran diverifikasi manual',
			'created_by' => (int) $this->session->userdata('user_id'),
			'created_at' => $now
		));
		$this->confirm_paid_stock((int) $payment->order_id, $now);
		$this->record_activity('payment_verified', 'payment', (int) $id, array(
			'status' => $payment->status,
			'order_id' => (int) $payment->order_id
		), array(
			'status' => 'paid',
			'order_id' => (int) $payment->order_id
		));
		$this->db->trans_complete();
		$this->session->set_flashdata($this->db->trans_status() ? 'success' : 'error', $this->db->trans_status() ? 'Pembayaran berhasil diverifikasi.' : 'Pembayaran gagal diverifikasi.');
		redirect('admin/payments');
	}

	public function webhook()
	{
		$raw = file_get_contents('php://input');
		$payload = json_decode($raw, true);
		$secret_setting = $this->db->where('setting_key', 'payment_webhook_secret')->get('store_settings')->row();
		$secret = $secret_setting ? (string) $secret_setting->setting_value : '';
		$signature = (string) $this->input->get_request_header('X-Payment-Signature', true);

		if ($secret === '' || $signature === '' || !hash_equals(hash_hmac('sha256', $raw, $secret), $signature)) {
			$this->output->set_status_header(401)->set_content_type('application/json')->set_output(json_encode(array('error' => 'Invalid signature')));
			return;
		}
		if (!is_array($payload) || empty($payload['gateway_transaction_id']) || empty($payload['order_number']) || empty($payload['status'])) {
			$this->output->set_status_header(400)->set_content_type('application/json')->set_output(json_encode(array('error' => 'Invalid payload')));
			return;
		}
		if (!in_array($payload['status'], array('paid', 'failed'), true)) {
			$this->output->set_status_header(400)->set_content_type('application/json')->set_output(json_encode(array('error' => 'Unsupported status')));
			return;
		}

		$order = $this->db->where('order_number', trim((string) $payload['order_number']))->get('orders')->row();
		if (!$order) {
			$this->output->set_status_header(404)->set_content_type('application/json')->set_output(json_encode(array('error' => 'Order not found')));
			return;
		}
		$payment = $this->db->where('order_id', $order->id)->get('payments')->row();
		if (!$payment) {
			$this->output->set_status_header(404)->set_content_type('application/json')->set_output(json_encode(array('error' => 'Payment not found')));
			return;
		}

		$this->db->trans_start();
		if ($payload['status'] === 'paid' && $payment->status !== 'paid') {
			$now = date('Y-m-d H:i:s');
			$this->db->where('id', $payment->id)->update('payments', array(
				'status' => 'paid',
				'gateway_transaction_id' => substr(trim((string) $payload['gateway_transaction_id']), 0, 191),
				'paid_at' => $now,
				'updated_at' => $now
			));
			$this->db->where('id', $order->id)->update('orders', array('status' => 'verified', 'updated_at' => $now));
			$this->confirm_paid_stock((int) $order->id, $now);
		} elseif ($payload['status'] === 'failed' && $payment->status === 'pending') {
			$this->db->where('id', $payment->id)->update('payments', array('status' => 'failed', 'updated_at' => date('Y-m-d H:i:s')));
		}
		$this->db->trans_complete();
		$this->output->set_content_type('application/json')->set_output(json_encode(array('success' => true)));
	}

	private function confirm_paid_stock($order_id, $now)
	{
		$allocations = $this->db->select('order_item_batches.batch_id, order_item_batches.quantity, order_items.product_id')
			->join('order_items', 'order_items.id = order_item_batches.order_item_id')
			->where('order_items.order_id', (int) $order_id)
			->get('order_item_batches')
			->result();
		foreach ($allocations as $allocation) {
			$this->db->where('id', (int) $allocation->batch_id)
				->set('reserved_quantity', 'GREATEST(reserved_quantity - ' . (float) $allocation->quantity . ', 0)', false)
				->set('remaining_quantity', 'remaining_quantity - ' . (float) $allocation->quantity, false)
				->update('stock_batches');
			$row = $this->db->select('COALESCE(SUM(remaining_quantity - reserved_quantity),0) AS balance', false)
				->where('product_id', (int) $allocation->product_id)
				->where('status', 'active')
				->get('stock_batches')
				->row();
			$this->db->insert('stock_movements', array(
				'product_id' => (int) $allocation->product_id,
				'batch_id' => (int) $allocation->batch_id,
				'movement_type' => 'sale',
				'reference_type' => 'order',
				'reference_id' => (int) $order_id,
				'quantity' => -$allocation->quantity,
				'balance_after' => (float) $row->balance,
				'notes' => 'Pengurangan stok setelah pembayaran terverifikasi',
				'created_by' => (int) $this->session->userdata('user_id'),
				'created_at' => $now
			));
		}
	}
}
