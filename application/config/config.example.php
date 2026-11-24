<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['base_url'] = 'http://example.test/';
$config['index_page'] = '';
$config['uri_protocol'] = 'REQUEST_URI';
$config['language'] = 'english';
$config['charset'] = 'UTF-8';
$config['enable_hooks'] = FALSE;
$config['subclass_prefix'] = 'MY_';
$config['composer_autoload'] = FCPATH . 'vendor/autoload.php';
$config['encryption_key'] = '';
$config['sess_driver'] = 'files';
$config['sess_cookie_name'] = 'ci_session';
$config['sess_expiration'] = 7200;
$config['sess_save_path'] = sys_get_temp_dir();
$config['csrf_protection'] = TRUE;
$config['csrf_token_name'] = 'csrf_test_name';
$config['csrf_cookie_name'] = 'csrf_cookie_name';
$config['cookie_secure'] = FALSE;
$config['cookie_httponly'] = TRUE;
$config['cookie_samesite'] = 'Lax';
