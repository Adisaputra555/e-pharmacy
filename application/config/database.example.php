<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$db['default'] = array(
	'dsn' => '',
	'hostname' => 'db.example.invalid',
	'username' => 'example_user',
	'password' => '',
	'database' => 'example_database',
	'dbdriver' => 'mysqli',
	'dbprefix' => '',
	'pconnect' => FALSE,
	'db_debug' => (ENVIRONMENT !== 'production'),
	'cache_on' => FALSE,
	'cachedir' => '',
	'char_set' => 'utf8mb4',
	'dbcollat' => 'utf8mb4_unicode_ci',
	'swap_pre' => '',
	'encrypt' => FALSE,
	'compress' => FALSE,
	'stricton' => FALSE,
	'failover' => array(),
	'save_queries' => TRUE
);
