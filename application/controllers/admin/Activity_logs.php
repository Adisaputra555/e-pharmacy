<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Activity_logs extends Admin_Controller
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
		$per_page = 50;

		$this->db->from('activity_logs')
			->join('users', 'users.id = activity_logs.user_id', 'left');
		$this->apply_search($search);
		$total = $this->db->count_all_results();

		$this->db->select('activity_logs.*, users.full_name AS user_name, users.email AS user_email')
			->from('activity_logs')
			->join('users', 'users.id = activity_logs.user_id', 'left');
		$this->apply_search($search);
		$rows = $this->db->order_by('activity_logs.created_at', 'DESC')
			->order_by('activity_logs.id', 'DESC')
			->limit($per_page, ($page - 1) * $per_page)
			->get()
			->result();

		$this->load->library('pagination');
		$this->pagination->initialize(array(
			'base_url' => site_url('admin/activity_logs'),
			'total_rows' => $total,
			'per_page' => $per_page,
			'use_page_numbers' => true,
			'page_query_string' => true,
			'query_string_segment' => 'page',
			'reuse_query_string' => true
		));

		$data = array('title' => 'Aktivitas Pengguna', 'rows' => $rows, 'search' => $search);
		$data['content'] = $this->load->view('admin/activity_logs/index', $data, true);
		$this->load->view('layouts/admin', $data);
	}

	private function apply_search($search)
	{
		if ($search === '') {
			return;
		}
		$this->db->group_start()
			->like('users.full_name', $search)
			->or_like('users.email', $search)
			->or_like('activity_logs.action', $search)
			->or_like('activity_logs.entity_type', $search)
			->group_end();
	}
}
