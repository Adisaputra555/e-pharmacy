<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends MY_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('User_model', 'users');
	}

	public function login()
	{
		if ($this->session->userdata('logged_in')) {
			redirect('admin/dashboard');
		}

		if ($this->input->method() !== 'post') {
			$this->load->view('auth/login');
			return;
		}

		$email = strtolower(trim((string) $this->input->post('email', true)));
		$password = (string) $this->input->post('password');

		$this->form_validation->set_rules('email', 'Email', 'required|valid_email');
		$this->form_validation->set_rules('password', 'Password', 'required');

		if (!$this->form_validation->run()) {
			$this->session->set_flashdata('error', 'Email atau password tidak valid.');
			$this->load->view('auth/login');
			return;
		}

		$user = $this->users->find_by_email($email);
		if (!$user) {
			sleep(1);
			$this->session->set_flashdata('error', 'Email atau password salah.');
			redirect('login');
		}

		if ($user->status !== 'active') {
			$this->users->log_activity($user->id, 'login_inactive_account');
			$this->session->set_flashdata('error', 'Akun tidak aktif.');
			redirect('login');
		}

		if ($user->locked_until && strtotime($user->locked_until) > time()) {
			$this->users->log_activity($user->id, 'login_locked_account');
			$this->session->set_flashdata('error', 'Akun terkunci sementara karena terlalu banyak percobaan login.');
			redirect('login');
		}

		if (!password_verify($password, $user->password_hash)) {
			$this->users->register_failed_login($user->id);
			$this->users->log_activity($user->id, 'login_failed');
			sleep(1);
			$this->session->set_flashdata('error', 'Email atau password salah.');
			redirect('login');
		}

		$this->users->register_successful_login($user->id);
		$this->users->log_activity($user->id, 'login_success');

		$this->session->sess_regenerate(TRUE);
		$this->session->set_userdata(array(
			'logged_in' => true,
			'user_id' => $user->id,
			'user_name' => $user->full_name,
			'user_email' => $user->email,
			'roles' => $user->role_list
		));

		redirect(in_array('pelanggan', $user->role_list, true) ? 'customer/catalog' : 'admin/dashboard');
	}

	public function logout()
	{
		$user_id = $this->session->userdata('user_id');
		if ($user_id) {
			$this->users->log_activity($user_id, 'logout');
		}
		$this->session->sess_destroy();
		redirect('login');
	}

	public function register()
	{
		if ($this->session->userdata('logged_in')) {
			redirect('customer/catalog');
		}
		if ($this->input->method() !== 'post') {
			$this->load->view('auth/register');
			return;
		}

		$this->form_validation->set_rules('full_name', 'Nama lengkap', 'required|max_length[150]');
		$this->form_validation->set_rules('email', 'Email', 'required|valid_email|max_length[190]|is_unique[users.email]');
		$this->form_validation->set_rules('password', 'Password', 'required|min_length[8]');
		$this->form_validation->set_rules('phone', 'Telepon', 'required|max_length[30]');
		if (!$this->form_validation->run()) {
			$this->session->set_flashdata('error', validation_errors());
			$this->load->view('auth/register');
			return;
		}

		$this->db->trans_start();
		$now = date('Y-m-d H:i:s');
		$this->db->insert('users', array(
			'email' => strtolower(trim((string) $this->input->post('email', true))),
			'password_hash' => password_hash((string) $this->input->post('password'), PASSWORD_BCRYPT),
			'full_name' => trim((string) $this->input->post('full_name', true)),
			'phone' => trim((string) $this->input->post('phone', true)),
			'status' => 'active',
			'created_at' => $now,
			'updated_at' => $now
		));
		$user_id = $this->db->insert_id();
		$role = $this->db->where('slug', 'pelanggan')->get('roles')->row();
		if ($role) {
			$this->db->insert('user_roles', array('user_id' => $user_id, 'role_id' => $role->id, 'created_at' => $now));
		}
		$this->db->trans_complete();

		if (!$this->db->trans_status()) {
			$this->session->set_flashdata('error', 'Registrasi gagal disimpan.');
			redirect('register');
		}
		$this->session->set_flashdata('success', 'Registrasi berhasil. Silakan login.');
		redirect('login');
	}
}
