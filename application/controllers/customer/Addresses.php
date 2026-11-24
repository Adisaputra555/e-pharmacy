<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Addresses extends Customer_Controller
{
	public function index()
	{
		$user_id = (int) $this->session->userdata('user_id');
		$addresses = $this->db->where('user_id', $user_id)->where('deleted_at IS NULL', null, false)->order_by('is_primary', 'DESC')->get('user_addresses')->result();
		$this->load->view('customer/addresses', array('title' => 'Alamat', 'addresses' => $addresses));
	}

	public function store()
	{
		if ($this->input->method() !== 'post') {
			redirect('customer/addresses');
		}
		$this->db->trans_start();
		$user_id = (int) $this->session->userdata('user_id');
		$is_primary = $this->input->post('is_primary') ? 1 : 0;
		if ($is_primary) {
			$this->db->where('user_id', $user_id)->update('user_addresses', array('is_primary' => 0));
		}
		$now = date('Y-m-d H:i:s');
		$this->db->insert('user_addresses', array(
			'user_id' => $user_id,
			'label' => trim((string) $this->input->post('label', true)),
			'recipient_name' => trim((string) $this->input->post('recipient_name', true)),
			'phone' => trim((string) $this->input->post('phone', true)),
			'address_line' => trim((string) $this->input->post('address_line', true)),
			'district' => trim((string) $this->input->post('district', true)),
			'city' => trim((string) $this->input->post('city', true)),
			'province' => trim((string) $this->input->post('province', true)),
			'postal_code' => trim((string) $this->input->post('postal_code', true)),
			'is_primary' => $is_primary,
			'created_at' => $now,
			'updated_at' => $now
		));
		$this->record_activity('address_created', 'user_address', $this->db->insert_id(), null, array(
			'label' => trim((string) $this->input->post('label', true)),
			'city' => trim((string) $this->input->post('city', true)),
			'is_primary' => $is_primary
		));
		$this->db->trans_complete();
		redirect('customer/addresses');
	}
}
