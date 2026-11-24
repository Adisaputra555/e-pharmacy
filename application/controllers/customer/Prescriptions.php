<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Prescriptions extends Customer_Controller
{
	public function index()
	{
		$user_id = (int) $this->session->userdata('user_id');
		$prescriptions = $this->db->where('user_id', $user_id)->order_by('created_at', 'DESC')->get('prescriptions')->result();
		$this->load->view('customer/prescriptions', array('title' => 'Resep', 'prescriptions' => $prescriptions));
	}

	public function upload()
	{
		if ($this->input->method() !== 'post') {
			redirect('customer/prescriptions');
		}
		if (empty($_FILES['prescription']['name'])) {
			$this->session->set_flashdata('error', 'File resep wajib dipilih.');
			redirect('customer/prescriptions');
		}

		$upload_path = APPPATH . 'private/prescriptions/';
		if (!is_dir($upload_path)) {
			mkdir($upload_path, 0700, true);
		}
		$config = array(
			'upload_path' => $upload_path,
			'allowed_types' => 'jpg|jpeg|png|pdf',
			'max_size' => 3072,
			'encrypt_name' => true,
			'file_ext_tolower' => true
		);
		$this->load->library('upload', $config);
		if (!$this->upload->do_upload('prescription')) {
			$this->session->set_flashdata('error', strip_tags($this->upload->display_errors()));
			redirect('customer/prescriptions');
		}

		$file = $this->upload->data();
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_file($finfo, $file['full_path']);
		finfo_close($finfo);
		if (!in_array($mime, array('image/jpeg', 'image/png', 'application/pdf'), true)) {
			@unlink($file['full_path']);
			$this->session->set_flashdata('error', 'MIME type file resep tidak diizinkan.');
			redirect('customer/prescriptions');
		}

		$now = date('Y-m-d H:i:s');
		$user_id = (int) $this->session->userdata('user_id');
		$this->db->insert('prescriptions', array(
			'user_id' => $user_id,
			'prescription_number' => trim((string) $this->input->post('prescription_number', true)) ?: null,
			'doctor_name' => trim((string) $this->input->post('doctor_name', true)) ?: null,
			'prescription_date' => $this->input->post('prescription_date', true) ?: null,
			'file_name' => $file['orig_name'],
			'private_file_path' => $file['full_path'],
			'mime_type' => $mime,
			'file_size' => (int) $file['file_size'] * 1024,
			'status' => 'pending',
			'created_at' => $now,
			'updated_at' => $now
		));
		$this->record_activity('prescription_uploaded', 'prescription', $this->db->insert_id(), null, array('status' => 'pending'));
		$this->session->set_flashdata('success', 'Resep berhasil diunggah dan menunggu pemeriksaan apoteker.');
		redirect('customer/prescriptions');
	}

	public function file_view($id)
	{
		$user_id = (int) $this->session->userdata('user_id');
		$prescription = $this->db->where('id', (int) $id)->where('user_id', $user_id)->get('prescriptions')->row();
		if (!$prescription || !is_file($prescription->private_file_path)) {
			show_404();
		}
		$this->record_activity('prescription_file_viewed', 'prescription', (int) $id);
		header('Content-Type: ' . $prescription->mime_type);
		header('Content-Disposition: inline; filename="' . basename($prescription->file_name) . '"');
		readfile($prescription->private_file_path);
	}
}
