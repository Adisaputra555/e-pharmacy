<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller
{
	private $login_checked = false;

	protected function record_activity($action, $entity_type = null, $entity_id = null, $old_values = null, $new_values = null)
	{
		$user_id = (int) $this->session->userdata('user_id');
		$inserted = $this->db->insert('activity_logs', array(
			'user_id' => $user_id > 0 ? $user_id : null,
			'action' => substr((string) $action, 0, 100),
			'entity_type' => $entity_type ? substr((string) $entity_type, 0, 100) : null,
			'entity_id' => $entity_id ? (int) $entity_id : null,
			'ip_address' => $this->input->ip_address(),
			'user_agent' => substr((string) $this->input->user_agent(), 0, 255),
			'old_values' => $old_values === null ? null : json_encode($old_values, JSON_UNESCAPED_UNICODE),
			'new_values' => $new_values === null ? null : json_encode($new_values, JSON_UNESCAPED_UNICODE),
			'created_at' => date('Y-m-d H:i:s')
		));

		if (!$inserted) {
			$error = $this->db->error();
			log_message('error', 'Activity log insert failed: ' . (isset($error['message']) ? $error['message'] : 'Unknown database error'));
		}

		return $inserted;
	}

	protected function require_login()
	{
		if ($this->login_checked) {
			return;
		}

		$roles = $this->session->userdata('roles');
		if (!$this->session->userdata('logged_in') ||
			!(int) $this->session->userdata('user_id') ||
			!is_array($roles) ||
			!$roles) {
			$this->session->sess_destroy();
			redirect('login');
			exit;
		}

		$assigned_roles = $this->db->select('users.full_name, users.email, roles.slug, role_permissions.permission_key')
			->from('users')
			->join('user_roles', 'user_roles.user_id = users.id')
			->join('roles', 'roles.id = user_roles.role_id')
			->join('role_permissions', 'role_permissions.role_id = roles.id', 'left')
			->where('users.id', (int) $this->session->userdata('user_id'))
			->where('users.status', 'active')
			->where('users.deleted_at IS NULL', null, false)
			->where('roles.is_active', 1)
			->where('roles.deleted_at IS NULL', null, false)
			->get()
			->result();
		if (!$assigned_roles) {
			$this->session->sess_destroy();
			redirect('login');
			exit;
		}

		$role_slugs = array();
		$permissions = array();
		foreach ($assigned_roles as $role) {
			if (!in_array($role->slug, $role_slugs, true)) {
				$role_slugs[] = $role->slug;
			}
			if ($role->permission_key && !in_array($role->permission_key, $permissions, true)) {
				$permissions[] = $role->permission_key;
			}
		}
		$this->session->set_userdata(array(
			'user_name' => $assigned_roles[0]->full_name,
			'user_email' => $assigned_roles[0]->email,
			'roles' => $role_slugs,
			'permissions' => $permissions
		));
		$this->login_checked = true;
	}

	public function has_permission($permission_key)
	{
		$roles = $this->session->userdata('roles');
		if (is_array($roles) && in_array('administrator', $roles, true)) {
			return true;
		}
		$permissions = $this->session->userdata('permissions');
		return is_array($permissions) && in_array($permission_key, $permissions, true);
	}

	protected function require_permission($permission_key)
	{
		$this->require_login();
		if (!$this->has_permission($permission_key)) {
			show_error('Role Anda tidak memiliki izin untuk mengakses menu ini.', 403, 'Akses Ditolak');
		}
	}

	protected function require_roles($allowed_roles)
	{
		$this->require_login();

		if (!is_array($allowed_roles)) {
			$allowed_roles = array($allowed_roles);
		}

		$user_roles = $this->session->userdata('roles');
		$user_roles = is_array($user_roles) ? $user_roles : array();

		if (!array_intersect($allowed_roles, $user_roles)) {
			show_error('Anda tidak memiliki hak akses untuk halaman ini.', 403, 'Akses Ditolak');
		}
	}
}

class Admin_Controller extends MY_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_roles(array(
			'administrator',
			'apoteker',
			'petugas-gudang',
			'kasir'
		));
	}
}

class Customer_Controller extends MY_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_roles(array('pelanggan'));
	}
}

class Master_Admin_Controller extends Admin_Controller
{
	protected $table;
	protected $title;
	protected $fields = array();
	protected $slug_field = null;

	public function index()
	{
		$page = max(1, (int) $this->input->get('page'));
		$per_page = 15;
		$search = trim((string) $this->input->get('search'));

		$this->db->where($this->table . '.deleted_at IS NULL', null, false);
		if ($search !== '') {
			$searchable = array();
			foreach ($this->fields as $field) {
				if (isset($field['searchable']) && $field['searchable']) {
					$searchable[] = $field['name'];
				}
			}
			if ($searchable) {
				$this->db->group_start();
				foreach ($searchable as $index => $field) {
					$method = $index === 0 ? 'like' : 'or_like';
					$this->db->$method($this->table . '.' . $field, $search);
				}
				$this->db->group_end();
			}
		}

		$count = $this->db->count_all_results($this->table);
		$this->db->where($this->table . '.deleted_at IS NULL', null, false);
		if ($search !== '') {
			$searchable = array();
			foreach ($this->fields as $field) {
				if (isset($field['searchable']) && $field['searchable']) {
					$searchable[] = $field['name'];
				}
			}
			if ($searchable) {
				$this->db->group_start();
				foreach ($searchable as $index => $field) {
					$method = $index === 0 ? 'like' : 'or_like';
					$this->db->$method($this->table . '.' . $field, $search);
				}
				$this->db->group_end();
			}
		}
		$rows = $this->db->order_by('id', 'DESC')
			->limit($per_page, ($page - 1) * $per_page)
			->get($this->table)
			->result();

		$this->load->library('pagination');
		$pagination_config = array(
			'base_url' => site_url('admin/' . strtolower($this->router->fetch_class())),
			'total_rows' => $count,
			'per_page' => $per_page,
			'use_page_numbers' => true,
			'page_query_string' => true,
			'query_string_segment' => 'page',
			'reuse_query_string' => true
		);
		$this->pagination->initialize($pagination_config);

		$data = array(
			'title' => $this->title,
			'rows' => $rows,
			'fields' => $this->fields,
			'search' => $search,
			'module_url' => 'admin/' . strtolower($this->router->fetch_class())
		);
		$data['content'] = $this->load->view('admin/master/index', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function create()
	{
		$data = array(
			'title' => 'Tambah ' . $this->title,
			'row' => null,
			'fields' => $this->fields,
			'module_url' => 'admin/' . strtolower($this->router->fetch_class()),
			'action' => site_url('admin/' . strtolower($this->router->fetch_class()) . '/store')
		);
		if ($this->input->is_ajax_request()) {
			$this->load->view('admin/master/form', $data);
			return;
		}
		$data['content'] = $this->load->view('admin/master/form', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function store()
	{
		if ($this->input->method() !== 'post') {
			redirect('admin/' . strtolower($this->router->fetch_class()));
		}

		$insert = $this->collect_form();
		$insert['created_at'] = date('Y-m-d H:i:s');
		$insert['updated_at'] = date('Y-m-d H:i:s');
		if ($this->db->insert($this->table, $insert)) {
			$this->record_activity($this->table . '_created', $this->table, $this->db->insert_id(), null, $insert);
		}
		$this->finish_crud($this->title . ' berhasil ditambahkan.');
		return;
	}

	public function show($id)
	{
		$row = $this->db->where('id', (int) $id)
			->where('deleted_at IS NULL', null, false)
			->get($this->table)
			->row();
		if (!$row) {
			show_404();
		}

		$data = array(
			'title' => 'Detail ' . $this->title,
			'row' => $row,
			'fields' => $this->fields,
			'module_url' => 'admin/' . strtolower($this->router->fetch_class())
		);
		if ($this->input->is_ajax_request()) {
			$this->load->view('admin/master/detail', $data);
			return;
		}
		$data['content'] = $this->load->view('admin/master/detail', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function edit($id)
	{
		$row = $this->db->where('id', (int) $id)
			->where('deleted_at IS NULL', null, false)
			->get($this->table)
			->row();
		if (!$row) {
			show_404();
		}

		$data = array(
			'title' => 'Edit ' . $this->title,
			'row' => $row,
			'fields' => $this->fields,
			'module_url' => 'admin/' . strtolower($this->router->fetch_class()),
			'action' => site_url('admin/' . strtolower($this->router->fetch_class()) . '/update/' . (int) $id)
		);
		if ($this->input->is_ajax_request()) {
			$this->load->view('admin/master/form', $data);
			return;
		}
		$data['content'] = $this->load->view('admin/master/form', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function update($id)
	{
		if ($this->input->method() !== 'post') {
			redirect('admin/' . strtolower($this->router->fetch_class()));
		}

		$update = $this->collect_form();
		$update['updated_at'] = date('Y-m-d H:i:s');
		$old_row = $this->db->where('id', (int) $id)->get($this->table)->row_array();
		$updated = $this->db->where('id', (int) $id)->update($this->table, $update);
		if ($updated && $old_row) {
			$this->record_activity($this->table . '_updated', $this->table, (int) $id, $old_row, $update);
		}
		$this->finish_crud($this->title . ' berhasil diperbarui.');
		return;
	}

	public function delete($id)
	{
		if ($this->input->method() !== 'post') {
			show_error('Metode permintaan tidak diizinkan.', 405, 'Metode Tidak Diizinkan');
		}
		$old_row = $this->db->where('id', (int) $id)->get($this->table)->row_array();
		$deleted = $this->db->where('id', (int) $id)
			->update($this->table, array('deleted_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')));
		if ($deleted && $old_row) {
			$this->record_activity($this->table . '_deleted', $this->table, (int) $id, $old_row, null);
		}
		$this->finish_crud($this->title . ' berhasil dihapus.');
		return;
	}

	protected function finish_crud($message)
	{
		$this->session->set_flashdata('success', $message);
		if ($this->input->is_ajax_request()) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode(array('success' => true, 'message' => $message)));
			return;
		}
		redirect('admin/' . strtolower($this->router->fetch_class()));
	}

	protected function collect_form()
	{
		$data = array();
		foreach ($this->fields as $field) {
			$name = $field['name'];
			if ($name === 'deleted_at') {
				continue;
			}
			$value = $this->input->post($name, true);
			if (isset($field['type']) && $field['type'] === 'checkbox') {
				$value = $value ? 1 : 0;
			}
			if ($value === null || $value === '') {
				$value = null;
			}
			$data[$name] = $value;
		}
		if ($this->slug_field && $this->input->post($this->slug_field, true)) {
			$data[$this->slug_field] = strtolower(url_title((string) $this->input->post($this->slug_field, true), '-', true));
		}
		return $data;
	}
}
