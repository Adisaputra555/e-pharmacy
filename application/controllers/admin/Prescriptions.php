<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Prescriptions extends Admin_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_permission('prescriptions');
	}

	public function index()
	{
		$data['title'] = 'Pemeriksaan Resep';
		$data['prescriptions'] = $this->db->select('prescriptions.*, users.full_name AS patient_name')
			->join('users', 'users.id = prescriptions.user_id')
			->order_by('prescriptions.created_at', 'DESC')
			->get('prescriptions')
			->result();
		$data['content'] = $this->load->view('admin/prescriptions/index', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function review($id)
	{
		$prescription = $this->db->select('prescriptions.*, users.full_name AS patient_name')
			->join('users', 'users.id = prescriptions.user_id')
			->where('prescriptions.id', (int) $id)
			->get('prescriptions')
			->row();
		if (!$prescription) {
			show_404();
		}
		$data = array('title' => 'Review Resep', 'prescription' => $prescription);
		$data['content'] = $this->load->view('admin/prescriptions/review', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function review_store($id)
	{
		if ($this->input->method() !== 'post') {
			redirect('admin/prescriptions');
		}
		$status = (string) $this->input->post('status');
		if (!in_array($status, array('under_review', 'approved', 'rejected', 'revision_required'), true)) {
			$this->session->set_flashdata('error', 'Status pemeriksaan tidak valid.');
			redirect('admin/prescriptions/review/' . (int) $id);
		}

		$notes = trim((string) $this->input->post('notes'));
		$now = date('Y-m-d H:i:s');
		$reviewer_id = (int) $this->session->userdata('user_id');
		$this->db->trans_start();
		$this->db->where('id', (int) $id)->update('prescriptions', array(
			'status' => $status,
			'notes' => $notes,
			'updated_at' => $now
		));
		$this->db->insert('prescription_reviews', array(
			'prescription_id' => (int) $id,
			'reviewer_id' => $reviewer_id,
			'status' => $status,
			'notes' => $notes,
			'reviewed_at' => $now,
			'created_at' => $now
		));
		$this->record_activity('prescription_review', 'prescription', (int) $id, null, array('status' => $status));
		$this->db->trans_complete();

		$this->session->set_flashdata($this->db->trans_status() ? 'success' : 'error', $this->db->trans_status() ? 'Hasil pemeriksaan resep berhasil disimpan.' : 'Hasil pemeriksaan gagal disimpan.');
		redirect('admin/prescriptions');
	}

	public function file_view($id)
	{
		$prescription = $this->db->where('id', (int) $id)->get('prescriptions')->row();
		if (!$prescription || !is_file($prescription->private_file_path)) {
			show_404();
		}
		$this->record_activity('prescription_file_viewed', 'prescription', (int) $id);
		header('Content-Type: ' . $prescription->mime_type);
		header('Content-Disposition: inline; filename="' . basename($prescription->file_name) . '"');
		readfile($prescription->private_file_path);
	}
}
