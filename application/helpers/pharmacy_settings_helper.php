<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('pharmacy_profile')) {
	function pharmacy_profile()
	{
		static $profile = null;
		if ($profile !== null) {
			return $profile;
		}

		$profile = array(
			'store_name' => 'Apotek Online',
			'store_tagline' => '',
			'store_logo' => '',
			'store_address' => '',
			'store_phone' => '',
			'store_email' => '',
			'store_website' => '',
			'store_hours' => '',
			'pharmacist_name' => '',
			'pharmacist_license' => '',
			'pharmacy_license' => '',
			'store_npwp' => '',
			'receipt_footer' => ''
		);

		$CI =& get_instance();
		if (!isset($CI->db) || !$CI->db->table_exists('store_settings')) {
			return $profile;
		}

		$rows = $CI->db->where_in('setting_key', array_keys($profile))
			->get('store_settings')
			->result();
		foreach ($rows as $row) {
			$profile[$row->setting_key] = (string) $row->setting_value;
		}

		$profile['store_name'] = trim($profile['store_name']) !== '' ? trim($profile['store_name']) : 'Apotek Online';
		$logo = str_replace('\\', '/', trim($profile['store_logo']));
		$profile['store_logo'] = preg_match('#^uploads/pharmacy/[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp)$#i', $logo) ? $logo : '';

		return $profile;
	}
}
