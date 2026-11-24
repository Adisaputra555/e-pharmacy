<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Product_unit_feature_gate
{
	public static function enabled($environment, $localConfigValue)
	{
		return $environment === 'demo' && $localConfigValue === true;
	}
}
