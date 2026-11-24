<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Reports extends Admin_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_permission('reports_sales');
	}

	public function sales()
	{
		$from = (string) $this->input->get('from');
		$to = (string) $this->input->get('to');
		$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-01');
		$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : date('Y-m-d');

		$rows = $this->db->where('created_at >=', $from . ' 00:00:00')
			->where('created_at <=', $to . ' 23:59:59')
			->where('status !=', 'cancelled')
			->order_by('created_at', 'DESC')
			->get('orders')
			->result();
		$total = $this->db->select_sum('total')
			->where('created_at >=', $from . ' 00:00:00')
			->where('created_at <=', $to . ' 23:59:59')
			->where('status !=', 'cancelled')
			->get('orders')
			->row()->total;

		$data = array(
			'title' => 'Laporan Penjualan',
			'rows' => $rows,
			'total' => (float) $total,
			'from' => $from,
			'to' => $to
		);
		$data['content'] = $this->load->view('admin/reports/sales', $data, true);
		$this->load->view('layouts/admin', $data);
	}
}
