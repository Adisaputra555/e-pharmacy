<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Role_permissions extends Admin_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_roles(array('administrator'));
		$this->config->load('role_permissions', true);
	}

	public function index()
	{
		$roles = $this->db->where('is_active', 1)
			->where('deleted_at IS NULL', null, false)
			->order_by('id')
			->get('roles')
			->result();
		$permissions = array();
		foreach ($this->db->select('role_id, permission_key')->get('role_permissions')->result() as $row) {
			$permissions[(int) $row->role_id][] = $row->permission_key;
		}

		$data = array(
			'title' => 'Akses Role',
			'roles' => $roles,
			'modules' => $this->config->item('modules', 'role_permissions'),
			'permissions' => $permissions
		);
		$data['content'] = $this->load->view('admin/role_permissions/index', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	public function save()
	{
		if ($this->input->method() !== 'post') {
			show_error('Metode permintaan tidak diizinkan.', 405, 'Metode Tidak Diizinkan');
		}

		$roles = $this->db->where('is_active', 1)
			->where('deleted_at IS NULL', null, false)
			->order_by('id')
			->get('roles')
			->result();
		$modules = $this->config->item('modules', 'role_permissions');
		$module_keys = array_keys($modules);
		$posted = $this->input->post('permissions');
		$posted = is_array($posted) ? $posted : array();
		$old_permissions = array();
		foreach ($this->db->select('role_permissions.role_id, role_permissions.permission_key')
			->join('roles', 'roles.id = role_permissions.role_id')
			->where('roles.is_active', 1)
			->where('roles.deleted_at IS NULL', null, false)
			->get('role_permissions')->result() as $row) {
			$old_permissions[$row->role_id][] = $row->permission_key;
		}

		$new_permissions = array();
		$this->db->trans_start();
		foreach ($roles as $role) {
			if ($role->slug === 'administrator') {
				$selected = $module_keys;
			} elseif ($role->slug === 'pelanggan') {
				$selected = array();
			} else {
				$submitted = isset($posted[$role->id]) && is_array($posted[$role->id]) ? $posted[$role->id] : array();
				$selected = array();
				foreach ($submitted as $permission_key) {
					if (is_string($permission_key) && in_array($permission_key, $module_keys, true)) {
						$selected[] = $permission_key;
					}
				}
				$selected = array_values(array_unique($selected));
				if ($role->slug !== 'pelanggan' && in_array('dashboard', $module_keys, true)) {
					$selected[] = 'dashboard';
					$selected = array_values(array_unique($selected));
				}
			}

			$this->db->where('role_id', (int) $role->id)->delete('role_permissions');
			foreach ($selected as $permission_key) {
				$this->db->insert('role_permissions', array(
					'role_id' => (int) $role->id,
					'permission_key' => $permission_key,
					'created_at' => date('Y-m-d H:i:s'),
					'updated_at' => date('Y-m-d H:i:s')
				));
			}
			$new_permissions[$role->slug] = $selected;
		}
		$old_snapshot = array();
		foreach ($roles as $role) {
			$old_snapshot[$role->slug] = isset($old_permissions[$role->id]) ? $old_permissions[$role->id] : array();
		}
		$this->record_activity('role_permissions_updated', 'roles', null, $old_snapshot, $new_permissions);
		$this->db->trans_complete();

		if (!$this->db->trans_status()) {
			$this->session->set_flashdata('error', 'Pengaturan akses role gagal disimpan.');
			redirect('admin/role_permissions');
		}
		$this->session->set_flashdata('success', 'Pengaturan akses role berhasil diperbarui.');
		redirect('admin/role_permissions');
	}
}
