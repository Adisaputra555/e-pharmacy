<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Suppliers extends Master_Admin_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_permission('suppliers');
		$this->table = 'suppliers';
		$this->title = 'Supplier';
		$this->fields = array(
			array('name' => 'code', 'label' => 'Kode Supplier', 'type' => 'text', 'required' => true, 'searchable' => true),
			array('name' => 'name', 'label' => 'Nama Supplier', 'type' => 'text', 'required' => true, 'searchable' => true),
			array('name' => 'address', 'label' => 'Alamat', 'type' => 'textarea'),
			array('name' => 'phone', 'label' => 'Telepon', 'type' => 'text', 'searchable' => true),
			array('name' => 'email', 'label' => 'Email', 'type' => 'email'),
			array('name' => 'contact_person', 'label' => 'Kontak', 'type' => 'text'),
			array('name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox')
		);
	}
}
