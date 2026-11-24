<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Products extends Admin_Controller
{
	private $unitFeatureEnabled;

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('products');
	}

	public function index()
	{
		$search = trim((string) $this->input->get('search'));
		$page = max(1, (int) $this->input->get('page'));
		$per_page = 15;

		$this->db->select('products.*, categories.name AS category_name, units.name AS unit_name')
			->join('categories', 'categories.id = products.category_id', 'left')
			->join('units', 'units.id = products.unit_id', 'left')
			->where('products.deleted_at IS NULL', null, false);
		if ($search !== '') {
			$this->db->group_start()
				->like('products.code', $search)
				->or_like('products.name', $search)
				->or_like('products.generic_name', $search)
				->or_like('products.barcode', $search)
				->group_end();
		}
		$rows = $this->db->order_by('products.id', 'DESC')
			->limit($per_page, ($page - 1) * $per_page)
			->get('products')
			->result();

		$count_query = $this->db->reset_query();
		$this->db->select('COUNT(*) AS total')
			->where('deleted_at IS NULL', null, false);
		if ($search !== '') {
			$this->db->group_start()
				->like('code', $search)
				->or_like('name', $search)
				->or_like('generic_name', $search)
				->or_like('barcode', $search)
				->group_end();
		}
		$total = $this->db->get('products')->row()->total;

		$this->load->library('pagination');
		$this->pagination->initialize(array(
			'base_url' => site_url('admin/products'),
			'total_rows' => (int) $total,
			'per_page' => $per_page,
			'use_page_numbers' => true,
			'page_query_string' => true,
			'query_string_segment' => 'page',
			'reuse_query_string' => true
		));

		$data = array(
			'title' => 'Produk',
			'rows' => $rows,
			'search' => $search,
			'units_v2_enabled' => $this->units_v2_enabled()
		);
		if ($data['units_v2_enabled']) {
			$data['unit_statuses'] = $this->unit_service()->getStatuses($rows);
		}
		$data['content'] = $this->load->view('admin/products/index', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function create()
	{
		$data = $this->form_data();
		$identifiers = $this->generate_product_identifiers();
		$data['title'] = 'Tambah Produk';
		$data['row'] = null;
		$data['units_v2_enabled'] = $this->units_v2_enabled();
		$data['generated_code'] = $identifiers['code'];
		$data['generated_barcode'] = $identifiers['barcode'];
		$data['action'] = site_url('admin/products/store');
		if ($this->input->is_ajax_request()) {
			$this->load->view('admin/products/form', $data);
			return;
		}
		$data['content'] = $this->load->view('admin/products/form', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function store()
	{
		if ($this->input->method() !== 'post') {
			redirect('admin/products');
		}
		$data = $this->validated_product();
		$data['created_at'] = date('Y-m-d H:i:s');
		$data['updated_at'] = date('Y-m-d H:i:s');

		$this->db->trans_start();
		$this->db->insert('products', $data);
		$product_id = $this->db->insert_id();
		$this->save_primary_image($product_id);
		$this->record_activity('product_created', 'product', $product_id, null, array_intersect_key($data, array_flip(array(
			'code', 'name', 'generic_name', 'selling_price', 'requires_prescription', 'is_active'
		))));
		$this->db->trans_complete();

		if (!$this->db->trans_status()) {
			if ($this->input->is_ajax_request()) {
				$this->output->set_status_header(500)->set_content_type('application/json')
					->set_output(json_encode(array('success' => false, 'message' => 'Produk gagal disimpan.')));
				return;
			}
			$this->session->set_flashdata('error', 'Produk gagal disimpan.');
			redirect('admin/products/create');
		}

		$this->session->set_flashdata('success', 'Produk berhasil ditambahkan.');
		if ($this->input->is_ajax_request()) {
			$this->output->set_content_type('application/json')
				->set_output(json_encode(array('success' => true, 'message' => 'Produk berhasil ditambahkan.')));
			return;
		}
		redirect('admin/products');
	}

	public function edit($id)
	{
		$row = $this->db->where('id', (int) $id)
			->where('deleted_at IS NULL', null, false)
			->get('products')
			->row();
		if (!$row) {
			show_404();
		}
		$data = $this->form_data();
		$data['title'] = 'Edit Produk';
		$data['row'] = $row;
		$data['action'] = site_url('admin/products/update/' . (int) $id);
		$this->unit_form_data($data, $row);
		if ($this->input->is_ajax_request()) {
			$this->load->view('admin/products/form', $data);
			return;
		}
		$data['content'] = $this->load->view('admin/products/form', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function show($id)
	{
		$product = $this->db->select('products.*, categories.name AS category_name, units.name AS unit_name, manufacturers.name AS manufacturer_name')
			->join('categories', 'categories.id = products.category_id', 'left')
			->join('units', 'units.id = products.unit_id', 'left')
			->join('manufacturers', 'manufacturers.id = products.manufacturer_id', 'left')
			->where('products.id', (int) $id)
			->where('products.deleted_at IS NULL', null, false)
			->get('products')
			->row();
		if (!$product) {
			show_404();
		}
		$data = array('title' => 'Detail Produk', 'product' => $product);
		if ($this->input->is_ajax_request()) {
			$this->load->view('admin/products/detail', $data);
			return;
		}
		$data['content'] = $this->load->view('admin/products/detail', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function update($id)
	{
		if ($this->input->method() !== 'post') {
			redirect('admin/products');
		}
		if ($this->units_v2_enabled()) {
			$unitService = $this->unit_service();
			if ($unitService->isBaseUnitLocked((int) $id)) {
				$current = $this->db->select('unit_id')->where('id', (int) $id)->get('products')->row();
				$postedUnit = $this->input->post('unit_id', true);
				$postedUnit = $postedUnit ? (int) $postedUnit : null;
				$currentUnit = $current && $current->unit_id !== null ? (int) $current->unit_id : null;
				if ($postedUnit !== $currentUnit) {
					$this->session->set_flashdata('error', 'Satuan dasar terkunci setelah konfigurasi dibuat.');
					redirect('admin/products/edit/' . (int) $id);
					return;
				}
			}
		}
		$data = $this->validated_product((int) $id);
		$data['updated_at'] = date('Y-m-d H:i:s');
		$old_product = $this->db->select('code, name, generic_name, selling_price, requires_prescription, is_active')
			->where('id', (int) $id)
			->get('products')
			->row_array();

		$this->db->trans_start();
		$this->db->where('id', (int) $id)->update('products', $data);
		$this->save_primary_image((int) $id);
		$this->record_activity('product_updated', 'product', (int) $id, $old_product, array_intersect_key($data, array_flip(array(
			'code', 'name', 'generic_name', 'selling_price', 'requires_prescription', 'is_active'
		))));
		$this->db->trans_complete();

		if (!$this->db->trans_status()) {
			if ($this->input->is_ajax_request()) {
				$this->output->set_status_header(500)->set_content_type('application/json')
					->set_output(json_encode(array('success' => false, 'message' => 'Produk gagal diperbarui.')));
				return;
			}
			$this->session->set_flashdata('error', 'Produk gagal diperbarui.');
			redirect('admin/products/edit/' . (int) $id);
		}
		$this->session->set_flashdata('success', 'Produk berhasil diperbarui.');
		if ($this->input->is_ajax_request()) {
			$this->output->set_content_type('application/json')
				->set_output(json_encode(array('success' => true, 'message' => 'Produk berhasil diperbarui.')));
			return;
		}
		redirect('admin/products');
	}

	public function save_unit_configuration($id)
	{
		$this->require_units_v2();
		if ($this->input->method() !== 'post') {
			show_error('Metode permintaan tidak diizinkan.', 405, 'Metode Tidak Diizinkan');
		}

		$submitted = $this->input->post('unit_config', true);
		$alternatives = array();
		if (is_array($submitted)) {
			foreach ($submitted as $item) {
				if (!is_array($item)) {
					continue;
				}
				$unitId = isset($item['unit_id']) ? trim((string) $item['unit_id']) : '';
				$factor = isset($item['factor']) ? trim((string) $item['factor']) : '';
				$inputScale = isset($item['input_scale']) ? trim((string) $item['input_scale']) : '';
				if ($unitId === '' && $factor === '') {
					continue;
				}
				$alternatives[] = array(
					'unit_id' => $unitId,
					'factor' => $factor,
					'input_scale' => $inputScale
				);
			}
		}

		try {
			$result = $this->unit_service()->saveConfiguration(
				(int) $id,
				$alternatives,
				$this->input->post('base_scale', true),
				(int) $this->session->userdata('user_id'),
				(string) $this->input->post('reason', true)
			);
			$this->session->set_flashdata(
				'success',
				$result['unchanged'] ? 'Konfigurasi satuan tidak berubah.' : 'Konfigurasi satuan tersimpan.'
			);
		} catch (InvalidArgumentException $error) {
			$this->session->set_flashdata('error', $error->getMessage());
		} catch (RuntimeException $error) {
			$this->session->set_flashdata('error', $error->getMessage());
		}
		redirect('admin/products/edit/' . (int) $id);
	}

	public function verify_unit_configuration($id)
	{
		$this->require_units_v2();
		if ($this->input->method() !== 'post') {
			show_error('Metode permintaan tidak diizinkan.', 405, 'Metode Tidak Diizinkan');
		}

		try {
			$result = $this->unit_service()->verifyConfiguration(
				(int) $id,
				(string) $this->input->post('source_unit_description', true),
				(string) $this->input->post('mapping_evidence', true),
				(int) $this->session->userdata('user_id')
			);
			$this->session->set_flashdata(
				'success',
				$result['idempotent'] ? 'Verifikasi satuan sudah tercatat.' : 'Verifikasi satuan berhasil dicatat.'
			);
		} catch (InvalidArgumentException $error) {
			$this->session->set_flashdata('error', $error->getMessage());
		} catch (RuntimeException $error) {
			$this->session->set_flashdata('error', $error->getMessage());
		}
		redirect('admin/products/edit/' . (int) $id);
	}

	public function delete($id)
	{
		if ($this->input->method() !== 'post') {
			show_error('Metode permintaan tidak diizinkan.', 405, 'Metode Tidak Diizinkan');
		}
		$old_product = $this->db->select('code, name, is_active')
			->where('id', (int) $id)
			->get('products')
			->row_array();
		$this->db->where('id', (int) $id)->update('products', array(
			'deleted_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s')
		));
		if ($old_product) {
			$this->record_activity('product_deleted', 'product', (int) $id, $old_product, null);
		}
		$this->session->set_flashdata('success', 'Produk berhasil dihapus.');
		if ($this->input->is_ajax_request()) {
			$this->output->set_content_type('application/json')
				->set_output(json_encode(array('success' => true, 'message' => 'Produk berhasil dihapus.')));
			return;
		}
		redirect('admin/products');
	}

	private function form_data()
	{
		return array(
			'categories' => $this->db->where('deleted_at IS NULL', null, false)->where('is_active', 1)->order_by('name')->get('categories')->result(),
			'units' => $this->db->where('deleted_at IS NULL', null, false)->where('is_active', 1)->order_by('name')->get('units')->result(),
			'manufacturers' => $this->db->where('deleted_at IS NULL', null, false)->where('is_active', 1)->order_by('name')->get('manufacturers')->result()
		);
	}

	private function unit_form_data(array &$data, $row)
	{
		$data['units_v2_enabled'] = $this->units_v2_enabled();
		$data['unit_status'] = null;
		$data['unit_configuration'] = array();
		$data['unit_alternatives'] = array();
		$data['unit_verification'] = null;
		$data['unit_base_scale'] = 0;
		$data['unit_base_locked'] = false;
		if (!$data['units_v2_enabled'] || !$row) {
			return;
		}

		$service = $this->unit_service();
		$data['unit_status'] = $service->getStatus((int) $row->id);
		$data['unit_configuration'] = $service->getConfiguration((int) $row->id);
		$data['unit_verification'] = $service->getVerification((int) $row->id);
		$data['unit_base_locked'] = $service->isBaseUnitLocked((int) $row->id);
		foreach ($data['unit_configuration'] as $unit) {
			if ((int) $unit['unit_id'] === (int) $row->unit_id) {
				$data['unit_base_scale'] = (int) $unit['base_scale'];
			} else {
				$data['unit_alternatives'][] = $unit;
			}
		}
	}

	private function units_v2_enabled()
	{
		if ($this->unitFeatureEnabled !== null) {
			return $this->unitFeatureEnabled;
		}
		if (!defined('ENVIRONMENT') || ENVIRONMENT !== 'demo') {
			$this->unitFeatureEnabled = false;
			return false;
		}

		if (!$this->config->load('secrets/units_v2', true, true)) {
			$this->unitFeatureEnabled = false;
			return false;
		}
		$configValue = $this->config->item('units_v2_enabled', 'secrets/units_v2') === true;
		require_once APPPATH . 'libraries' . DIRECTORY_SEPARATOR . 'Product_unit_feature_gate.php';
		$this->unitFeatureEnabled = Product_unit_feature_gate::enabled(ENVIRONMENT, $configValue);
		return $this->unitFeatureEnabled;
	}

	private function require_units_v2()
	{
		if (!$this->units_v2_enabled()) {
			show_404();
		}
	}

	private function unit_service()
	{
		require_once APPPATH . 'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
		require_once APPPATH . 'libraries' . DIRECTORY_SEPARATOR . 'Product_unit_service.php';
		return new Product_unit_service($this->db->conn_id, new Business_clock());
	}

	private function validated_product($exclude_id = null)
	{
		$this->form_validation->set_rules('code', 'Kode produk', 'required|max_length[50]|alpha_dash');
		if ($exclude_id === null) {
			$this->form_validation->set_rules('barcode', 'Barcode', 'required|exact_length[13]|numeric');
		}
		$this->form_validation->set_rules('name', 'Nama obat', 'required|max_length[150]');
		$this->form_validation->set_rules('purchase_price', 'Harga beli', 'required|decimal');
		$this->form_validation->set_rules('selling_price', 'Harga jual', 'required|decimal');
		$this->form_validation->set_rules('minimum_stock', 'Stok minimum', 'required|decimal');
		$this->form_validation->set_rules('drug_classification', 'Klasifikasi obat', 'required');

		if (!$this->form_validation->run()) {
			$this->session->set_flashdata('error', validation_errors());
			redirect($exclude_id === null ? 'admin/products/create' : 'admin/products/edit/' . (int) $exclude_id);
		}

		$code = trim((string) $this->input->post('code', true));
		$barcode = trim((string) $this->input->post('barcode', true));
		if (!$this->product_identifiers_available($code, $barcode, $exclude_id)) {
			$this->session->set_flashdata('error', 'Kode produk atau barcode sudah digunakan. Silakan buat ulang formulir.');
			redirect($exclude_id === null ? 'admin/products/create' : 'admin/products/edit/' . (int) $exclude_id);
		}

		return array(
			'category_id' => $this->input->post('category_id', true) ? (int) $this->input->post('category_id', true) : null,
			'unit_id' => $this->input->post('unit_id', true) ? (int) $this->input->post('unit_id', true) : null,
			'manufacturer_id' => $this->input->post('manufacturer_id', true) ? (int) $this->input->post('manufacturer_id', true) : null,
			'code' => $code,
			'barcode' => $barcode ?: null,
			'name' => trim((string) $this->input->post('name', true)),
			'generic_name' => trim((string) $this->input->post('generic_name', true)) ?: null,
			'dosage_form' => trim((string) $this->input->post('dosage_form', true)) ?: null,
			'strength' => trim((string) $this->input->post('strength', true)) ?: null,
			'registration_number' => trim((string) $this->input->post('registration_number', true)) ?: null,
			'description' => trim((string) $this->input->post('description', true)) ?: null,
			'purchase_price' => (float) $this->input->post('purchase_price'),
			'selling_price' => (float) $this->input->post('selling_price'),
			'minimum_stock' => (float) $this->input->post('minimum_stock'),
			'drug_classification' => trim((string) $this->input->post('drug_classification', true)),
			'requires_prescription' => $this->input->post('requires_prescription') ? 1 : 0,
			'is_active' => $this->input->post('is_active') ? 1 : 0
		);
	}

	private function generate_product_identifiers()
	{
		do {
			$code = 'PRD-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(6)));
			$code_exists = $this->db->where('code', $code)->count_all_results('products') > 0;
		} while ($code_exists);

		do {
			$barcode_base = '20';
			for ($digit = 0; $digit < 10; $digit++) {
				$barcode_base .= (string) random_int(0, 9);
			}
			$barcode = $barcode_base . $this->ean13_check_digit($barcode_base);
			$barcode_exists = $this->db->where('barcode', $barcode)->count_all_results('products') > 0;
		} while ($barcode_exists);

		return array('code' => $code, 'barcode' => $barcode);
	}

	private function ean13_check_digit($digits)
	{
		$sum = 0;
		for ($index = 0; $index < strlen($digits); $index++) {
			$sum += (int) $digits[$index] * ($index % 2 === 0 ? 1 : 3);
		}
		return (string) ((10 - ($sum % 10)) % 10);
	}

	private function product_identifiers_available($code, $barcode, $exclude_id = null)
	{
		$this->db->group_start()->where('code', $code);
		if ($barcode !== '') {
			$this->db->or_where('barcode', $barcode);
		}
		$this->db->group_end();
		if ($exclude_id !== null) {
			$this->db->where('id !=', (int) $exclude_id);
		}
		return $this->db->count_all_results('products') === 0;
	}

	private function save_primary_image($product_id)
	{
		if (!$product_id || empty($_FILES['photo']['name'])) {
			return;
		}

		$upload_path = FCPATH . 'assets/images/products/';
		if (!is_dir($upload_path)) {
			mkdir($upload_path, 0755, true);
		}

		$this->load->library('upload', array(
			'upload_path' => $upload_path,
			'allowed_types' => 'jpg|jpeg|png|webp',
			'max_size' => 2048,
			'encrypt_name' => true,
			'file_ext_tolower' => true
		));

		if (!$this->upload->do_upload('photo')) {
			$this->session->set_flashdata('error', strip_tags($this->upload->display_errors()));
			return;
		}

		$file = $this->upload->data();
		$this->db->insert('product_images', array(
			'product_id' => $product_id,
			'file_name' => $file['file_name'],
			'file_path' => 'assets/images/products/' . $file['file_name'],
			'mime_type' => $file['file_type'],
			'file_size' => (int) $file['file_size'] * 1024,
			'is_primary' => 1,
			'created_at' => date('Y-m-d H:i:s')
		));
	}
}
