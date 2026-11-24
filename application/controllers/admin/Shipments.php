<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Shipments extends Admin_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_permission('shipments');
	}

	public function index()
	{
		$data['title'] = 'Pengiriman';
		$data['shipments'] = $this->db->select('shipments.*, orders.order_number')
			->join('orders', 'orders.id = shipments.order_id')
			->order_by('shipments.created_at', 'DESC')
			->get('shipments')
			->result();
		$data['content'] = $this->load->view('admin/shipments/index', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function update_status($id)
	{
		if ($this->input->method() !== 'post') {
			redirect('admin/shipments');
		}
		$status = (string) $this->input->post('status');
		if (!in_array($status, array('pending', 'processing', 'packed', 'shipped', 'delivered', 'cancelled'), true)) {
			$this->session->set_flashdata('error', 'Status pengiriman tidak valid.');
			redirect('admin/shipments');
		}
		$shipment = $this->db->where('id', (int) $id)->get('shipments')->row();
		if (!$shipment) {
			show_404();
		}
		$now = date('Y-m-d H:i:s');
		$this->db->trans_start();
		$update = array(
			'status' => $status,
			'tracking_number' => trim((string) $this->input->post('tracking_number')) ?: $shipment->tracking_number,
			'updated_at' => $now
		);
		if ($status === 'shipped') {
			$update['shipped_at'] = $shipment->shipped_at ?: $now;
		}
		if ($status === 'delivered') {
			$update['delivered_at'] = $shipment->delivered_at ?: $now;
		}
		$this->db->where('id', (int) $id)->update('shipments', $update);
		$this->db->where('id', (int) $shipment->order_id)->update('orders', array(
			'status' => $status === 'delivered' ? 'delivered' : ($status === 'cancelled' ? 'cancelled' : $status),
			'updated_at' => $now
		));
		$this->db->insert('order_status_histories', array(
			'order_id' => (int) $shipment->order_id,
			'status' => $status,
			'notes' => 'Status pengiriman diperbarui',
			'created_by' => (int) $this->session->userdata('user_id'),
			'created_at' => $now
		));
		$this->record_activity('shipment_status_updated', 'shipment', (int) $id, array(
			'status' => $shipment->status
		), array(
			'status' => $status,
			'order_id' => (int) $shipment->order_id
		));
		$this->db->trans_complete();
		$this->session->set_flashdata($this->db->trans_status() ? 'success' : 'error', $this->db->trans_status() ? 'Status pengiriman diperbarui.' : 'Status pengiriman gagal diperbarui.');
		redirect('admin/shipments');
	}
}
