<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['modules'] = array(
	'dashboard' => array(
		'name' => 'Dashboard', 'group' => 'WORKSPACE', 'url' => 'admin/dashboard', 'icon' => '◈',
		'default_roles' => array('administrator', 'apoteker', 'petugas-gudang', 'kasir'), 'required' => true
	),
	'products' => array(
		'name' => 'Produk', 'group' => 'KATALOG & DATA', 'url' => 'admin/products', 'icon' => '▦',
		'default_roles' => array('administrator', 'apoteker', 'petugas-gudang')
	),
	'categories' => array(
		'name' => 'Kategori', 'group' => 'KATALOG & DATA', 'url' => 'admin/categories', 'icon' => '⌘',
		'default_roles' => array('administrator', 'petugas-gudang')
	),
	'units' => array(
		'name' => 'Satuan', 'group' => 'KATALOG & DATA', 'url' => 'admin/units', 'icon' => '◫',
		'default_roles' => array('administrator', 'petugas-gudang')
	),
	'manufacturers' => array(
		'name' => 'Produsen', 'group' => 'KATALOG & DATA', 'url' => 'admin/manufacturers', 'icon' => '✳',
		'default_roles' => array('administrator', 'petugas-gudang')
	),
	'suppliers' => array(
		'name' => 'Supplier', 'group' => 'KATALOG & DATA', 'url' => 'admin/suppliers', 'icon' => '⇄',
		'default_roles' => array('administrator', 'petugas-gudang')
	),
	'inventory' => array(
		'name' => 'Inventaris', 'group' => 'OPERASIONAL', 'url' => 'admin/inventory', 'icon' => '▤',
		'default_roles' => array('administrator', 'petugas-gudang')
	),
	'purchases' => array(
		'name' => 'Pembelian', 'group' => 'OPERASIONAL', 'url' => 'admin/purchases', 'icon' => '↙',
		'default_roles' => array('administrator', 'petugas-gudang')
	),
	'sales' => array(
		'name' => 'Penjualan kasir', 'group' => 'OPERASIONAL', 'url' => 'admin/sales', 'icon' => '↗',
		'default_roles' => array('administrator', 'kasir')
	),
	'orders' => array(
		'name' => 'Pesanan', 'group' => 'OPERASIONAL', 'url' => 'admin/orders', 'icon' => '☷',
		'default_roles' => array('administrator', 'kasir', 'apoteker')
	),
	'prescriptions' => array(
		'name' => 'Verifikasi resep', 'group' => 'LAYANAN FARMASI', 'url' => 'admin/prescriptions', 'icon' => '▧',
		'default_roles' => array('administrator', 'apoteker')
	),
	'payments' => array(
		'name' => 'Pembayaran', 'group' => 'LAYANAN FARMASI', 'url' => 'admin/payments', 'icon' => '◎',
		'default_roles' => array('administrator', 'kasir')
	),
	'shipments' => array(
		'name' => 'Pengiriman', 'group' => 'LAYANAN FARMASI', 'url' => 'admin/shipments', 'icon' => '➜',
		'default_roles' => array('administrator', 'kasir')
	),
	'reports_sales' => array(
		'name' => 'Laporan penjualan', 'group' => 'ANALISIS', 'url' => 'admin/reports/sales', 'icon' => '▥',
		'default_roles' => array('administrator', 'kasir')
	),
	'pharmacy_settings' => array(
		'name' => 'Pengaturan Apotek', 'group' => 'ADMINISTRASI', 'url' => 'admin/pharmacy_settings', 'icon' => '⚙',
		'default_roles' => array('administrator')
	)
);
