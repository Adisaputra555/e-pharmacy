<?php

$phase0aRoot = dirname(__DIR__);
$phase0aTestingConfig = $phase0aRoot . DIRECTORY_SEPARATOR . 'application'
	. DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'testing'
	. DIRECTORY_SEPARATOR . 'database.php';

if (PHP_SAPI === 'cli-server') {
	if (getenv('PHASE0A_TESTING') !== '1' || getenv('CI_ENV') !== 'testing' ||
		!is_file($phase0aTestingConfig)) {
		http_response_code(503);
		exit('Scratch-only test server guard failed.');
	}

	$_SERVER['CI_ENV'] = 'testing';
	return;
}

if (PHP_SAPI !== 'cli' || !is_file($phase0aTestingConfig)) {
	fwrite(STDERR, "Tests require CLI and the scratch-only database config.\n");
	exit(70);
}

putenv('CI_ENV=testing');
$_SERVER['CI_ENV'] = 'testing';

if (!defined('BASEPATH')) {
	define('BASEPATH', $phase0aRoot . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
if (!defined('ENVIRONMENT')) {
	define('ENVIRONMENT', 'testing');
}

$db = array();
require $phase0aTestingConfig;
if (!isset($db['default']['database']) ||
	$db['default']['database'] !== 'db_apotek_online_scratch_p1') {
	fwrite(STDERR, "Refusing tests: configured database is not the approved scratch DB.\n");
	exit(71);
}

mysqli_report(MYSQLI_REPORT_OFF);
$phase0aDbConfig = $db['default'];
$phase0aDb = @new mysqli(
	$phase0aDbConfig['hostname'],
	$phase0aDbConfig['username'],
	$phase0aDbConfig['password'],
	$phase0aDbConfig['database'],
	$phase0aDbConfig['port']
);
if ($phase0aDb->connect_errno) {
	fwrite(STDERR, "Refusing tests: scratch database connection failed.\n");
	exit(72);
}
$phase0aDb->set_charset('utf8mb4');
$phase0aIdentity = $phase0aDb->query('SELECT DATABASE() AS db_name, CURRENT_USER() AS account');
$phase0aIdentity = $phase0aIdentity ? $phase0aIdentity->fetch_assoc() : null;
if (!$phase0aIdentity ||
	$phase0aIdentity['db_name'] !== 'db_apotek_online_scratch_p1' ||
	strpos($phase0aIdentity['account'], 'apotek_scratch_p1@') !== 0) {
	$phase0aDb->close();
	fwrite(STDERR, "Refusing tests: actual DB identity is not the scratch-only account.\n");
	exit(73);
}

function phase0a_require_row($db, $sql, $types = '', $values = array())
{
	$stmt = $db->prepare($sql);
	if (!$stmt) {
		throw new RuntimeException('Unable to prepare a scratch fixture query.');
	}
	if ($types !== '') {
		$references = array();
		foreach ($values as $index => &$value) {
			$references[$index] = &$value;
		}
		$stmt->bind_param($types, ...$references);
	}
	if (!$stmt->execute()) {
		$stmt->close();
		throw new RuntimeException('Unable to execute a scratch fixture query.');
	}
	$result = $stmt->get_result();
	$row = $result ? $result->fetch_assoc() : null;
	$stmt->close();
	return $row;
}

function phase0a_insert($db, $sql, $types, $values)
{
	$stmt = $db->prepare($sql);
	if (!$stmt) {
		throw new RuntimeException('Unable to prepare a scratch fixture insert.');
	}
	$references = array();
	foreach ($values as $index => &$value) {
		$references[$index] = &$value;
	}
	$stmt->bind_param($types, ...$references);
	if (!$stmt->execute()) {
		$stmt->close();
		throw new RuntimeException('Unable to create a scratch-only test fixture.');
	}
	$id = (int) $stmt->insert_id;
	$stmt->close();
	return $id;
}

$phase0aNonce = bin2hex(random_bytes(6));
$phase0aNow = date('Y-m-d H:i:s');
$phase0aAdminPassword = bin2hex(random_bytes(24));
$phase0aCustomerPassword = bin2hex(random_bytes(24));
$phase0aAdminRole = phase0a_require_row(
	$phase0aDb,
	"SELECT id FROM roles WHERE slug = 'administrator' AND is_active = 1 LIMIT 1"
);
$phase0aCustomerRole = phase0a_require_row(
	$phase0aDb,
	"SELECT id FROM roles WHERE slug = 'pelanggan' AND is_active = 1 LIMIT 1"
);
$phase0aSupplier = phase0a_require_row(
	$phase0aDb,
	"SELECT id FROM suppliers WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1"
);
$phase0aCashMethod = phase0a_require_row(
	$phase0aDb,
	"SELECT id FROM payment_methods WHERE code = 'cash' AND is_active = 1 LIMIT 1"
);
$phase0aPickupMethod = phase0a_require_row(
	$phase0aDb,
	"SELECT id FROM shipping_methods WHERE code = 'pickup' AND is_active = 1 LIMIT 1"
);
$phase0aShippingMethod = phase0a_require_row(
	$phase0aDb,
	"SELECT id FROM shipping_methods WHERE is_active = 1 ORDER BY id LIMIT 1"
);
if (!$phase0aAdminRole || !$phase0aCustomerRole) {
	fwrite(STDERR, "Scratch baseline is missing required administrator/customer roles.\n");
	exit(74);
}

$phase0aAdminEmail = 'phase0a-admin-' . $phase0aNonce . '@example.invalid';
$phase0aCustomerEmail = 'phase0a-customer-' . $phase0aNonce . '@example.invalid';
$phase0aAdminId = phase0a_insert(
	$phase0aDb,
	'INSERT INTO users (email,password_hash,full_name,phone,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',
	'sssssss',
	array($phase0aAdminEmail, password_hash($phase0aAdminPassword, PASSWORD_DEFAULT), 'Phase 0a Test Admin', '0000000000', 'active', $phase0aNow, $phase0aNow)
);
$phase0aCustomerId = phase0a_insert(
	$phase0aDb,
	'INSERT INTO users (email,password_hash,full_name,phone,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',
	'sssssss',
	array($phase0aCustomerEmail, password_hash($phase0aCustomerPassword, PASSWORD_DEFAULT), 'Phase 0a Test Customer', '0000000001', 'active', $phase0aNow, $phase0aNow)
);
phase0a_insert(
	$phase0aDb,
	'INSERT INTO user_roles (user_id,role_id,created_at) VALUES (?,?,?)',
	'iis',
	array($phase0aAdminId, (int) $phase0aAdminRole['id'], $phase0aNow)
);
phase0a_insert(
	$phase0aDb,
	'INSERT INTO user_roles (user_id,role_id,created_at) VALUES (?,?,?)',
	'iis',
	array($phase0aCustomerId, (int) $phase0aCustomerRole['id'], $phase0aNow)
);
$phase0aCartId = phase0a_insert(
	$phase0aDb,
	'INSERT INTO carts (user_id,session_id,status,created_at,updated_at) VALUES (?,NULL,?,?,?)',
	'isss',
	array($phase0aCustomerId, 'active', $phase0aNow, $phase0aNow)
);

$phase0aFixtureIds = array();
$phase0aLabels = array('pos', 'checkout', 'purchase', 'opname');
$phase0aExpiry = date('Y-m-d', strtotime('+1 year'));
foreach ($phase0aLabels as $phase0aLabel) {
	$phase0aCode = 'P0A-' . strtoupper($phase0aNonce) . '-' . strtoupper($phase0aLabel);
	$phase0aProductId = phase0a_insert(
		$phase0aDb,
		'INSERT INTO products (code,name,purchase_price,selling_price,minimum_stock,drug_classification,requires_prescription,is_active,created_at,updated_at) VALUES (?,?,3.00,5.00,0,\'other\',0,1,?,?)',
		'ssss',
		array($phase0aCode, 'Phase 0a ' . $phase0aLabel, $phase0aNow, $phase0aNow)
	);
	$phase0aBatchNumber = $phase0aCode . '-B1';
	$phase0aBatchId = phase0a_insert(
		$phase0aDb,
		'INSERT INTO stock_batches (product_id,batch_number,received_at,expiration_date,initial_quantity,remaining_quantity,reserved_quantity,purchase_price,selling_price,status,created_at,updated_at) VALUES (?,?,?, ?,100,100,0,3.00,5.00,\'active\',?,?)',
		'isssss',
		array($phase0aProductId, $phase0aBatchNumber, $phase0aNow, $phase0aExpiry, $phase0aNow, $phase0aNow)
	);
	$phase0aFixtureIds[$phase0aLabel] = array('product_id' => $phase0aProductId, 'batch_id' => $phase0aBatchId, 'batch_number' => $phase0aBatchNumber);
}
$phase0aCheckoutProduct = $phase0aFixtureIds['checkout']['product_id'];
phase0a_insert(
	$phase0aDb,
	'INSERT INTO cart_items (cart_id,product_id,quantity,created_at,updated_at) VALUES (?,?,1,?,?)',
	'iiss',
	array($phase0aCartId, $phase0aCheckoutProduct, $phase0aNow, $phase0aNow)
);
$phase0aCustomerAddressId = phase0a_insert(
	$phase0aDb,
	'INSERT INTO user_addresses (user_id,label,recipient_name,phone,address_line,city,province,is_primary,created_at,updated_at) VALUES (?,?,?,?,?,?,?,1,?,?)',
	'issssssss',
	array($phase0aCustomerId, 'Test', 'Phase 0a Test Customer', '0000000001', 'Scratch-only test address', 'Kota Uji', 'Provinsi Uji', $phase0aNow, $phase0aNow)
);

$GLOBALS['phase0aDb'] = $phase0aDb;
$GLOBALS['phase0aFixtures'] = array(
	'admin' => array('id' => $phase0aAdminId, 'email' => $phase0aAdminEmail, 'password' => $phase0aAdminPassword),
	'customer' => array('id' => $phase0aCustomerId, 'email' => $phase0aCustomerEmail, 'password' => $phase0aCustomerPassword, 'address_id' => $phase0aCustomerAddressId, 'cart_id' => $phase0aCartId),
	'products' => $phase0aFixtureIds,
	'supplier_id' => $phase0aSupplier ? (int) $phase0aSupplier['id'] : null,
	'cash_method_id' => $phase0aCashMethod ? (int) $phase0aCashMethod['id'] : null,
	'pickup_method_id' => $phase0aPickupMethod ? (int) $phase0aPickupMethod['id'] : null,
	'shipping_method_id' => $phase0aShippingMethod ? (int) $phase0aShippingMethod['id'] : null
);

if (!extension_loaded('curl')) {
	fwrite(STDERR, "PHP cURL extension is required for loopback HTTP characterization tests.\n");
	exit(75);
}
$GLOBALS['phase0aHttpBase'] = rtrim((string) getenv('PHASE0A_TEST_BASE_URL'), '/');
if ($GLOBALS['phase0aHttpBase'] === '') {
	fwrite(STDERR, "PHASE0A_TEST_BASE_URL must point to the loopback test server.\n");
	exit(76);
}

abstract class Phase0aHttpTestCase extends PHPUnit\Framework\TestCase
{
	protected $cookies = array();

	protected function request($method, $path, $data = array())
	{
		$url = $GLOBALS['phase0aHttpBase'] . $path;
		$curl = curl_init($url);
		$headers = array('Accept: text/html');
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($curl, CURLOPT_TIMEOUT, 15);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
		if ($this->cookies) {
			$cookiePairs = array();
			foreach ($this->cookies as $name => $value) {
				$cookiePairs[] = $name . '=' . $value;
			}
			curl_setopt($curl, CURLOPT_COOKIE, implode('; ', $cookiePairs));
		}
		if (strtoupper($method) === 'POST') {
			$headers[] = 'Content-Type: application/x-www-form-urlencoded';
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
		}
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		$response = curl_exec($curl);
		if ($response === false) {
			$error = curl_error($curl);
			curl_close($curl);
			$this->fail('Loopback request failed: ' . $error);
		}
		$headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
		$status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		curl_close($curl);
		$rawHeaders = substr($response, 0, $headerSize);
		$body = substr($response, $headerSize);
		$responseHeaders = array();
		foreach (preg_split('/\r\n|\n|\r/', $rawHeaders) as $line) {
			if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $line, $match)) {
				$this->cookies[$match[1]] = $match[2];
			} elseif (strpos($line, ':') !== false) {
				list($name, $value) = explode(':', $line, 2);
				$responseHeaders[strtolower(trim($name))] = trim($value);
			}
		}
		return array('status' => $status, 'headers' => $responseHeaders, 'body' => $body);
	}

	protected function csrfToken($html)
	{
		if (!preg_match('/<input[^>]+name="([^"]+)"[^>]+value="([^"]*)"[^>]*>/i', $html, $match)) {
			$this->fail('No CSRF form token found in the scratch HTTP response.');
		}
		return array($match[1] => html_entity_decode($match[2], ENT_QUOTES, 'UTF-8'));
	}

	protected function postForm($pagePath, $postPath, $data)
	{
		$page = $this->request('GET', $pagePath);
		$this->assertSame(200, $page['status'], 'Expected the test form page to return HTTP 200.');
		return $this->request('POST', $postPath, array_merge($data, $this->csrfToken($page['body'])));
	}

	protected function loginAs($role)
	{
		$account = $GLOBALS['phase0aFixtures'][$role];
		$response = $this->postForm(
			'/index.php/login',
			'/index.php/login',
			array('email' => $account['email'], 'password' => $account['password'])
		);
		$this->assertContains($response['status'], array(302, 303), 'Login should redirect after authentication.');
		$this->assertNotEmpty($this->cookies, 'Login must establish the scratch app session.');
	}

	protected function dbRow($sql, $types = '', $values = array())
	{
		return phase0a_require_row($GLOBALS['phase0aDb'], $sql, $types, $values);
	}
}
