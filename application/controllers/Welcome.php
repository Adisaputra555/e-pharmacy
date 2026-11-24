<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Welcome extends CI_Controller
{
	public function index()
	{
		if ($this->session->userdata('logged_in')) {
			$roles = $this->session->userdata('roles');
			if (is_array($roles) && in_array('pelanggan', $roles, true)) {
				redirect('customer/catalog');
			}
			redirect('admin/dashboard');
		}
		redirect('login');
	}
}
