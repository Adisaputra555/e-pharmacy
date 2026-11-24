<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Units extends Master_Admin_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_permission('units');
		$this->table = 'units';
		$this->title = 'Satuan';
		$this->fields = array(
			array('name' => 'name', 'label' => 'Nama Satuan', 'type' => 'text', 'required' => true, 'searchable' => true),
			array('name' => 'code', 'label' => 'Kode', 'type' => 'text', 'required' => true, 'searchable' => true),
			array('name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox')
		);
	}
}
