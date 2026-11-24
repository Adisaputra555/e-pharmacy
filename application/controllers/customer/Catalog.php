<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Catalog extends CI_Controller
{
	public function index()
	{
		$search = trim((string) $this->input->get('search'));
		$this->db->select('products.*, categories.name AS category_name')
			->join('categories', 'categories.id = products.category_id', 'left')
			->where('products.deleted_at IS NULL', null, false)
			->where('products.is_active', 1);
		if ($search !== '') {
			$this->db->group_start()->like('products.name', $search)->or_like('products.generic_name', $search)->or_like('products.code', $search)->group_end();
		}
		$products = $this->db->order_by('products.name')->get('products')->result();
		foreach ($products as $product) {
			$row = $this->db->select('COALESCE(SUM(remaining_quantity - reserved_quantity),0) AS available', false)
				->where('product_id', $product->id)
				->where('status', 'active')
				->where('expiration_date >=', date('Y-m-d'))
				->get('stock_batches')->row();
			$product->available = $row ? (float) $row->available : 0;
		}
		$data = array('title' => 'Katalog', 'products' => $products, 'search' => $search);
		$this->load->view('customer/catalog', $data);
	}

	public function detail($id)
	{
		$product = $this->db->where('id', (int) $id)->where('is_active', 1)->where('deleted_at IS NULL', null, false)->get('products')->row();
		if (!$product) {
			show_404();
		}
		$row = $this->db->select('COALESCE(SUM(remaining_quantity - reserved_quantity),0) AS available', false)
			->where('product_id', $product->id)
			->where('status', 'active')
			->where('expiration_date >=', date('Y-m-d'))
			->get('stock_batches')->row();
		$product->available = $row ? (float) $row->available : 0;
		$this->load->view('customer/detail', array('title' => $product->name, 'product' => $product));
	}
}
