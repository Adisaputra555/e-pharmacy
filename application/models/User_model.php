<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model
{
	public function find_by_email($email)
	{
		$user = $this->db
			->where('email', strtolower(trim($email)))
			->where('deleted_at IS NULL', null, false)
			->get('users')
			->row();

		if (!$user) {
			return null;
		}

		$role_rows = $this->db
			->select('roles.slug')
			->join('roles', 'roles.id = user_roles.role_id')
			->where('user_roles.user_id', $user->id)
			->get('user_roles')
			->result_array();

		$user->role_list = array();
		foreach ($role_rows as $role) {
			$user->role_list[] = $role['slug'];
		}
		return $user;
	}

	public function register_failed_login($user_id)
	{
		$this->db->set('failed_login_attempts', 'failed_login_attempts + 1', false)
			->where('id', $user_id)
			->update('users');

		$user = $this->db->select('failed_login_attempts')->where('id', $user_id)->get('users')->row();
		if ($user && (int) $user->failed_login_attempts >= 5) {
			$this->db->set('locked_until', date('Y-m-d H:i:s', time() + (15 * 60)))
				->where('id', $user_id)
				->update('users');
		}
	}

	public function register_successful_login($user_id)
	{
		$this->db->set('failed_login_attempts', 0)
			->set('locked_until', null)
			->set('last_login_at', date('Y-m-d H:i:s'))
			->where('id', $user_id)
			->update('users');
	}

	public function log_activity($user_id, $action)
	{
		$this->db->insert('activity_logs', array(
			'user_id' => $user_id,
			'action' => $action,
			'entity_type' => 'auth',
			'entity_id' => $user_id,
			'ip_address' => $this->input->ip_address(),
			'user_agent' => substr((string) $this->input->user_agent(), 0, 255),
			'created_at' => date('Y-m-d H:i:s')
		));
	}
}
