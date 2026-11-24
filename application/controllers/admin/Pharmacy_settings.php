<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pharmacy_settings extends Admin_Controller
{
	private $defaults = array(
		'store_name' => 'Apotek Online',
		'store_tagline' => '',
		'store_logo' => '',
		'store_address' => '',
		'store_phone' => '',
		'store_email' => '',
		'store_website' => '',
		'store_hours' => '',
		'pharmacist_name' => '',
		'pharmacist_license' => '',
		'pharmacy_license' => '',
		'store_npwp' => '',
		'receipt_footer' => ''
	);

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('pharmacy_settings');
	}

	public function index()
	{
		$this->render_page($this->settings_values());
	}

	public function save()
	{
		if ($this->input->method() !== 'post') {
			show_error('Metode permintaan tidak diizinkan.', 405, 'Metode Tidak Diizinkan');
		}

		$values = $this->defaults;
		foreach (array_keys($values) as $key) {
			$posted = $this->input->post($key, true);
			$values[$key] = $key === 'store_logo' ? '' : trim((string) $posted);
		}
		$values['store_logo'] = $this->settings_values()['store_logo'];

		$this->form_validation->set_rules('store_name', 'Nama apotek', 'required|max_length[150]');
		$this->form_validation->set_rules('store_tagline', 'Tagline', 'max_length[200]');
		$this->form_validation->set_rules('store_address', 'Alamat', 'max_length[2000]');
		$this->form_validation->set_rules('store_phone', 'Telepon', 'max_length[30]');
		$this->form_validation->set_rules('store_email', 'Email', 'valid_email|max_length[190]');
		$this->form_validation->set_rules('store_website', 'Website', 'max_length[255]');
		$this->form_validation->set_rules('store_hours', 'Jam operasional', 'max_length[300]');
		$this->form_validation->set_rules('pharmacist_name', 'Apoteker penanggung jawab', 'max_length[150]');
		$this->form_validation->set_rules('pharmacist_license', 'Nomor SIPA', 'max_length[100]');
		$this->form_validation->set_rules('pharmacy_license', 'Nomor SIA', 'max_length[100]');
		$this->form_validation->set_rules('store_npwp', 'NPWP', 'max_length[30]');
		$this->form_validation->set_rules('receipt_footer', 'Pesan pada struk', 'max_length[500]');

		$errors = '';
		if (!$this->form_validation->run()) {
			$errors = trim(strip_tags(validation_errors(' ', ' ')));
		}
		if ($errors === '' && $values['store_website'] !== '') {
			$scheme = strtolower((string) parse_url($values['store_website'], PHP_URL_SCHEME));
			if (!filter_var($values['store_website'], FILTER_VALIDATE_URL) || !in_array($scheme, array('http', 'https'), true)) {
				$errors = 'Website harus berupa URL lengkap yang diawali http:// atau https://.';
			}
		}
		if ($errors !== '') {
			$this->render_page($values, $errors);
			return;
		}

		$old_values = $this->settings_values();
		$old_logo = $old_values['store_logo'];
		$new_logo_path = null;
		$upload_path = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'pharmacy' . DIRECTORY_SEPARATOR;
		if (isset($_FILES['store_logo']) && is_array($_FILES['store_logo']) && (int) $_FILES['store_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
			if (!is_dir($upload_path) && !mkdir($upload_path, 0755, true) && !is_dir($upload_path)) {
				$this->render_page($values, 'Folder penyimpanan logo tidak dapat dibuat. Periksa izin folder uploads.');
				return;
			}

			$this->load->library('upload');
			$this->upload->initialize(array(
				'upload_path' => $upload_path,
				'allowed_types' => 'jpg|jpeg|png|webp',
				'max_size' => 2048,
				'file_ext_tolower' => true,
				'encrypt_name' => true,
				'detect_mime' => true
			));
			if (!$this->upload->do_upload('store_logo')) {
				$this->render_page($values, 'Logo gagal diunggah: ' . trim(strip_tags($this->upload->display_errors('', ''))));
				return;
			}

			$uploaded_file = $this->upload->data();
			$new_logo_path = 'uploads/pharmacy/' . $uploaded_file['file_name'];
			$values['store_logo'] = $new_logo_path;
		} elseif ($this->input->post('remove_logo') === '1') {
			$values['store_logo'] = '';
		}

		$now = date('Y-m-d H:i:s');
		$existing_rows = $this->db->where_in('setting_key', array_keys($values))
			->get('store_settings')
			->result();
		$existing = array();
		foreach ($existing_rows as $row) {
			$existing[$row->setting_key] = (int) $row->id;
		}

		$this->db->trans_start();
		foreach ($values as $key => $value) {
			$setting = array(
				'setting_value' => $value,
				'is_public' => $key === 'store_npwp' ? 0 : 1,
				'updated_at' => $now
			);
			if (isset($existing[$key])) {
				$this->db->where('id', $existing[$key])->update('store_settings', $setting);
			} else {
				$setting['setting_key'] = $key;
				$setting['created_at'] = $now;
				$this->db->insert('store_settings', $setting);
			}
		}
		$this->db->trans_complete();

		if (!$this->db->trans_status()) {
			if ($new_logo_path !== null) {
				$this->delete_logo_file($new_logo_path);
			}
			$error = $this->db->error();
			log_message('error', 'Saving pharmacy settings failed: ' . (isset($error['message']) ? $error['message'] : 'Unknown database error'));
			$this->render_page($values, 'Pengaturan gagal disimpan. Periksa log aplikasi atau hubungi administrator.');
			return;
		}

		$this->record_activity('pharmacy_settings_updated', 'store_settings', null, $old_values, $values);
		if ($old_logo !== $values['store_logo']) {
			$this->delete_logo_file($old_logo);
		}
		$this->session->set_flashdata('success', 'Pengaturan apotek berhasil disimpan.');
		redirect('admin/pharmacy_settings');
	}

	private function settings_values()
	{
		$values = $this->defaults;
		$rows = $this->db->where_in('setting_key', array_keys($values))
			->get('store_settings')
			->result();
		foreach ($rows as $row) {
			$values[$row->setting_key] = (string) $row->setting_value;
		}
		return $values;
	}

	private function render_page($values, $error = '')
	{
		$data = array(
			'title' => 'Pengaturan Apotek',
			'values' => $values,
			'error' => $error
		);
		$data['content'] = $this->load->view('admin/pharmacy_settings/index', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	private function delete_logo_file($relative_path)
	{
		$relative_path = str_replace('\\', '/', (string) $relative_path);
		if (!preg_match('#^uploads/pharmacy/[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp)$#i', $relative_path)) {
			return;
		}

		$file_path = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $relative_path);
		if (is_file($file_path) && !unlink($file_path)) {
			log_message('error', 'Unable to remove old pharmacy logo: ' . $file_path);
		}
	}
}
