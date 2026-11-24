<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Users extends Admin_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_roles(array('administrator'));
	}

	public function index()
	{
		$search = trim((string) $this->input->get('search'));
		$page = max(1, (int) $this->input->get('page'));
		$per_page = 20;

		$this->db->from('users')->where('users.deleted_at IS NULL', null, false);
		$this->apply_search($search);
		$total = $this->db->count_all_results();

		$this->db->select('users.id, users.full_name, users.email, users.phone, users.status, users.last_login_at, users.created_at, GROUP_CONCAT(roles.name ORDER BY roles.name SEPARATOR ", ") AS role_names', false)
			->from('users')
			->join('user_roles', 'user_roles.user_id = users.id', 'left')
			->join('roles', 'roles.id = user_roles.role_id AND roles.deleted_at IS NULL', 'left')
			->where('users.deleted_at IS NULL', null, false);
		$this->apply_search($search);
		$rows = $this->db->group_by('users.id')
			->order_by('users.id', 'DESC')
			->limit($per_page, ($page - 1) * $per_page)
			->get()
			->result();

		$this->load->library('pagination');
		$this->pagination->initialize(array(
			'base_url' => site_url('admin/users'),
			'total_rows' => $total,
			'per_page' => $per_page,
			'use_page_numbers' => true,
			'page_query_string' => true,
			'query_string_segment' => 'page',
			'reuse_query_string' => true
		));

		$data = array('title' => 'Pengguna & Role', 'rows' => $rows, 'search' => $search);
		$data['content'] = $this->load->view('admin/users/index', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function create()
	{
		$data = array(
			'title' => 'Tambah Pengguna',
			'row' => null,
			'roles' => $this->active_roles(),
			'selected_roles' => array(),
			'action' => site_url('admin/users/store')
		);
		if ($this->input->is_ajax_request()) {
			$this->load->view('admin/users/form', $data);
			return;
		}
		$data['content'] = $this->load->view('admin/users/form', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function show($id)
	{
		$user = $this->find_user((int) $id);
		if (!$user) {
			show_404();
		}
		$data = array(
			'title' => 'Detail Pengguna',
			'user' => $user,
			'role_names' => $this->role_names((int) $id)
		);
		if ($this->input->is_ajax_request()) {
			$this->load->view('admin/users/detail', $data);
			return;
		}
		$data['content'] = $this->load->view('admin/users/detail', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function edit($id)
	{
		$user = $this->find_user((int) $id);
		if (!$user) {
			show_404();
		}
		$data = array(
			'title' => 'Edit Pengguna',
			'row' => $user,
			'roles' => $this->active_roles(),
			'selected_roles' => $this->role_ids((int) $id),
			'action' => site_url('admin/users/update/' . (int) $id)
		);
		if ($this->input->is_ajax_request()) {
			$this->load->view('admin/users/form', $data);
			return;
		}
		$data['content'] = $this->load->view('admin/users/form', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function store()
	{
		if ($this->input->method() !== 'post') {
			redirect('admin/users');
		}

		$this->form_validation->set_message('is_unique', '{field} sudah digunakan akun lain.');
		$this->form_validation->set_rules('full_name', 'Nama lengkap', 'required|max_length[150]');
		$this->form_validation->set_rules('email', 'Email', 'required|valid_email|max_length[190]|is_unique[users.email]');
		$this->form_validation->set_rules('phone', 'Telepon', 'max_length[30]');
		$this->form_validation->set_rules('password', 'Password', 'required|min_length[8]');
		$role_ids = $this->posted_role_ids();
		$status = (string) $this->input->post('status', true);

		if (!$this->form_validation->run()) {
			$this->respond_error(trim(strip_tags(validation_errors(' ', ' '))), 'admin/users/create');
			return;
		}
		if ($role_ids === null || !$role_ids || !in_array($status, array('active', 'inactive'), true)) {
			$this->respond_error('Pilih minimal satu role aktif dan status akun yang valid.', 'admin/users/create');
			return;
		}

		$now = date('Y-m-d H:i:s');
		$user_data = array(
			'email' => strtolower(trim((string) $this->input->post('email', true))),
			'password_hash' => password_hash((string) $this->input->post('password'), PASSWORD_BCRYPT),
			'full_name' => trim((string) $this->input->post('full_name', true)),
			'phone' => trim((string) $this->input->post('phone', true)) ?: null,
			'status' => $status,
			'created_at' => $now,
			'updated_at' => $now
		);

		$this->db->trans_start();
		$this->db->insert('users', $user_data);
		$user_id = (int) $this->db->insert_id();
		$this->save_roles($user_id, $role_ids, $now);
		$this->record_activity('user_created', 'user', $user_id, null, array(
			'full_name' => $user_data['full_name'],
			'email' => $user_data['email'],
			'phone' => $user_data['phone'],
			'status' => $status,
			'roles' => $this->role_names_from_ids($role_ids)
		));
		$this->db->trans_complete();

		if (!$this->db->trans_status()) {
			$this->respond_error('Akun pengguna gagal disimpan.', 'admin/users/create');
			return;
		}
		$this->respond_success('Akun pengguna berhasil dibuat.');
	}

	public function update($id)
	{
		if ($this->input->method() !== 'post') {
			redirect('admin/users');
		}
		$id = (int) $id;
		$user = $this->find_user($id);
		if (!$user) {
			show_404();
		}

		$this->form_validation->set_rules('full_name', 'Nama lengkap', 'required|max_length[150]');
		$this->form_validation->set_rules('email', 'Email', 'required|valid_email|max_length[190]');
		$this->form_validation->set_rules('phone', 'Telepon', 'max_length[30]');
		$password = (string) $this->input->post('password');
		if ($password !== '') {
			$this->form_validation->set_rules('password', 'Password baru', 'min_length[8]');
		}
		$role_ids = $this->posted_role_ids();
		$status = (string) $this->input->post('status', true);

		if (!$this->form_validation->run()) {
			$this->respond_error(trim(strip_tags(validation_errors(' ', ' '))), 'admin/users/edit/' . $id);
			return;
		}
		$email = strtolower(trim((string) $this->input->post('email', true)));
		$email_exists = $this->db->where('email', $email)
			->where('id !=', $id)
			->count_all_results('users') > 0;
		if ($email_exists) {
			$this->respond_error('Email sudah digunakan akun lain.', 'admin/users/edit/' . $id);
			return;
		}
		if ($role_ids === null || !$role_ids || !in_array($status, array('active', 'inactive'), true)) {
			$this->respond_error('Pilih minimal satu role aktif dan status akun yang valid.', 'admin/users/edit/' . $id);
			return;
		}
		$administrator_role = $this->administrator_role_id();
		if ($id === (int) $this->session->userdata('user_id') &&
			($status !== 'active' || !in_array($administrator_role, $role_ids, true))) {
			$this->respond_error('Akun yang sedang digunakan harus tetap aktif dan memiliki role Administrator.', 'admin/users/edit/' . $id);
			return;
		}
		if ($status !== 'active' || !in_array($administrator_role, $role_ids, true)) {
			if (!$this->has_other_active_administrator($id)) {
				$this->respond_error('Tidak dapat menonaktifkan atau menghapus role Administrator terakhir yang aktif.', 'admin/users/edit/' . $id);
				return;
			}
		}

		$old_values = $this->account_snapshot($id);
		$user_data = array(
			'email' => $email,
			'full_name' => trim((string) $this->input->post('full_name', true)),
			'phone' => trim((string) $this->input->post('phone', true)) ?: null,
			'status' => $status,
			'updated_at' => date('Y-m-d H:i:s')
		);
		if ($password !== '') {
			$user_data['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
		}

		$this->db->trans_start();
		$this->db->where('id', $id)->update('users', $user_data);
		$this->db->where('user_id', $id)->delete('user_roles');
		$this->save_roles($id, $role_ids, date('Y-m-d H:i:s'));
		$new_values = array(
			'full_name' => $user_data['full_name'],
			'email' => $user_data['email'],
			'phone' => $user_data['phone'],
			'status' => $status,
			'roles' => $this->role_names_from_ids($role_ids),
			'password_changed' => $password !== ''
		);
		$this->record_activity('user_updated', 'user', $id, $old_values, $new_values);
		$this->db->trans_complete();

		if (!$this->db->trans_status()) {
			$this->respond_error('Perubahan akun pengguna gagal disimpan.', 'admin/users/edit/' . $id);
			return;
		}
		$this->respond_success('Akun pengguna berhasil diperbarui.');
	}

	private function apply_search($search)
	{
		if ($search === '') {
			return;
		}
		$this->db->group_start()
			->like('users.full_name', $search)
			->or_like('users.email', $search)
			->or_like('users.phone', $search)
			->group_end();
	}

	private function active_roles()
	{
		return $this->db->where('is_active', 1)
			->where('deleted_at IS NULL', null, false)
			->order_by('name')
			->get('roles')
			->result();
	}

	private function find_user($id)
	{
		return $this->db->select('id, full_name, email, phone, status, last_login_at, created_at')
			->where('id', $id)
			->where('deleted_at IS NULL', null, false)
			->get('users')
			->row();
	}

	private function posted_role_ids()
	{
		$posted = $this->input->post('role_ids');
		if (!is_array($posted)) {
			return array();
		}
		$ids = array_values(array_unique(array_filter(array_map('intval', $posted))));
		if (!$ids) {
			return array();
		}
		$valid = $this->db->select('id')
			->where_in('id', $ids)
			->where('is_active', 1)
			->where('deleted_at IS NULL', null, false)
			->get('roles')
			->result_array();
		return count($valid) === count($ids) ? $ids : null;
	}

	private function role_ids($user_id)
	{
		$rows = $this->db->select('role_id')
			->where('user_id', $user_id)
			->get('user_roles')
			->result_array();
		return array_map('intval', array_column($rows, 'role_id'));
	}

	private function role_names($user_id)
	{
		$rows = $this->db->select('roles.name')
			->join('roles', 'roles.id = user_roles.role_id')
			->where('user_roles.user_id', $user_id)
			->order_by('roles.name')
			->get('user_roles')
			->result_array();
		return array_column($rows, 'name');
	}

	private function role_names_from_ids($role_ids)
	{
		$rows = $this->db->select('name')
			->where_in('id', $role_ids)
			->order_by('name')
			->get('roles')
			->result_array();
		return array_column($rows, 'name');
	}

	private function save_roles($user_id, $role_ids, $created_at)
	{
		foreach ($role_ids as $role_id) {
			$this->db->insert('user_roles', array(
				'user_id' => $user_id,
				'role_id' => $role_id,
				'created_at' => $created_at
			));
		}
	}

	private function account_snapshot($user_id)
	{
		$user = $this->find_user($user_id);
		return array(
			'full_name' => $user->full_name,
			'email' => $user->email,
			'phone' => $user->phone,
			'status' => $user->status,
			'roles' => $this->role_names($user_id)
		);
	}

	private function administrator_role_id()
	{
		$role = $this->db->select('id')->where('slug', 'administrator')->get('roles')->row();
		return $role ? (int) $role->id : 0;
	}

	private function has_other_active_administrator($excluded_user_id)
	{
		$administrator_role_id = $this->administrator_role_id();
		if (!$administrator_role_id) {
			return false;
		}
		return $this->db->from('users')
			->join('user_roles', 'user_roles.user_id = users.id')
			->where('users.status', 'active')
			->where('users.deleted_at IS NULL', null, false)
			->where('users.id !=', $excluded_user_id)
			->where('user_roles.role_id', $administrator_role_id)
			->count_all_results() > 0;
	}

	private function respond_error($message, $redirect_url)
	{
		if ($this->input->is_ajax_request()) {
			$this->output->set_status_header(422)
				->set_content_type('application/json')
				->set_output(json_encode(array(
					'success' => false,
					'message' => $message,
					'csrf_token_name' => $this->security->get_csrf_token_name(),
					'csrf_hash' => $this->security->get_csrf_hash()
				)));
			return;
		}
		$this->session->set_flashdata('error', $message);
		redirect($redirect_url);
	}

	private function respond_success($message)
	{
		$this->session->set_flashdata('success', $message);
		if ($this->input->is_ajax_request()) {
			$this->output->set_content_type('application/json')
				->set_output(json_encode(array('success' => true, 'message' => $message)));
			return;
		}
		redirect('admin/users');
	}
}
