<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Manufacturers extends Master_Admin_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_permission('manufacturers');
		$this->table = 'manufacturers';
		$this->title = 'Produsen';
		$this->fields = array(
			array('name' => 'name', 'label' => 'Nama Produsen', 'type' => 'text', 'required' => true, 'searchable' => true),
			array('name' => 'country', 'label' => 'Negara', 'type' => 'text', 'searchable' => true),
			array('name' => 'phone', 'label' => 'Telepon', 'type' => 'text'),
			array('name' => 'email', 'label' => 'Email', 'type' => 'email'),
			array('name' => 'address', 'label' => 'Alamat', 'type' => 'textarea'),
			array('name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox')
		);
	}
}
