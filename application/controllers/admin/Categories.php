<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Categories extends Master_Admin_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_permission('categories');
		$this->table = 'categories';
		$this->title = 'Kategori';
		$this->slug_field = 'slug';
		$this->fields = array(
			array('name' => 'name', 'label' => 'Nama Kategori', 'type' => 'text', 'required' => true, 'searchable' => true),
			array('name' => 'slug', 'label' => 'Slug', 'type' => 'text', 'required' => true, 'searchable' => true),
			array('name' => 'description', 'label' => 'Deskripsi', 'type' => 'textarea'),
			array('name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox')
		);
	}
}
