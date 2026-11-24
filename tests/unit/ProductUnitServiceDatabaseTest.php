<?php
$root = dirname(__DIR__, 2);
if (!defined('BASEPATH')) {
	define('BASEPATH', $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
require_once $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
require_once $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Product_unit_service.php';

class ProductUnitServiceDatabaseTest extends PHPUnit\Framework\TestCase
{
	private $service;
	private $connection;
	private $actorId;

	protected function setUp(): void
	{
		$this->connection = $GLOBALS['p0b1_dml_db'];
		$this->service = new Product_unit_service($this->connection, new Business_clock());
		$admin = $this->connection->query(
			"SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id = u.id " .
			"JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'administrator' " .
			"AND u.status = 'active' AND u.deleted_at IS NULL ORDER BY u.id LIMIT 1"
		);
		$row = $admin ? $admin->fetch_assoc() : null;
		if (!$row) {
			$this->fail('Scratch fixture requires an active administrator.');
		}
		$this->actorId = (int) $row['id'];
	}

	private function product($unitId)
	{
		$code = 'P11-UNIT-' . strtoupper(bin2hex(random_bytes(6)));
		$now = (new Business_clock())->now()->format('Y-m-d H:i:s');
		$stmt = $this->connection->prepare(
			'INSERT INTO products ' .
			'(unit_id, code, name, purchase_price, selling_price, minimum_stock, drug_classification, ' .
			'requires_prescription, is_active, created_at, updated_at) VALUES (?, ?, ?, 1, 2, 0, ?, 0, 1, ?, ?)'
		);
		$name = 'P1.1 unit test fixture';
		$class = 'obat-bebas';
		$stmt->bind_param('isssss', $unitId, $code, $name, $class, $now, $now);
		$stmt->execute();
		$id = (int) $stmt->insert_id;
		$stmt->close();
		return $id;
	}

	private function expectConversionRejected($productId)
	{
		try {
			$this->service->convertToBase($productId, 1, '1');
		} catch (RuntimeException $error) {
			$this->assertNotSame('', $error->getMessage());
			return;
		}
		$this->fail('Unverified product units must not be converted.');
	}

	private function auditCount($productId)
	{
		$stmt = $this->connection->prepare(
			'SELECT COUNT(*) FROM product_unit_conversion_audits WHERE product_id = ?'
		);
		$stmt->bind_param('i', $productId);
		$stmt->execute();
		$stmt->bind_result($count);
		$stmt->fetch();
		$stmt->close();
		return (int) $count;
	}

	public function testUnknownAndLegacyProductsAreNotConverted()
	{
		$unknown = $this->product(null);
		$legacy = $this->product(1);
		$this->assertSame('unknown', $this->service->getStatus($unknown));
		$this->assertSame('legacy_unverified', $this->service->getStatus($legacy));
		$this->expectConversionRejected($unknown);
		$this->expectConversionRejected($legacy);
	}

	public function testConfigurationVerificationAuditAndRetriesAreIdempotent()
	{
		$productId = $this->product(1);
		$alternatives = array(array('unit_id' => 4, 'factor' => '10', 'input_scale' => 3));
		$config = $this->service->saveConfiguration(
			$productId,
			$alternatives,
			3,
			$this->actorId,
			'P1.1 scratch test configuration.'
		);
		$this->assertSame(1, $config['config_version']);
		$this->assertSame('legacy_unverified', $this->service->getStatus($productId));
		$this->expectConversionRejected($productId);
		$this->assertSame(2, $this->auditCount($productId));

		$retry = $this->service->saveConfiguration(
			$productId,
			$alternatives,
			3,
			$this->actorId,
			'P1.1 scratch test configuration.'
		);
		$this->assertTrue($retry['unchanged']);
		$this->assertSame(2, $this->auditCount($productId));

		$verification = $this->service->verifyConfiguration(
			$productId,
			'Scratch test units',
			'Verified by a deterministic scratch test fixture.',
			$this->actorId
		);
		$this->assertFalse($verification['idempotent']);
		$this->assertSame('verified', $this->service->getStatus($productId));
		$this->assertSame(4, $this->auditCount($productId));

		$repeat = $this->service->verifyConfiguration(
			$productId,
			'Scratch test units',
			'Verified by a deterministic scratch test fixture.',
			$this->actorId
		);
		$this->assertTrue($repeat['idempotent']);
		$this->assertSame(4, $this->auditCount($productId));

		$this->expectException(RuntimeException::class);
		$this->service->verifyConfiguration(
			$productId,
			'Scratch test units',
			'Different evidence cannot replace one-time verification.',
			$this->actorId
		);
	}

	public function testVerifiedConfigurationConvertsWithoutChangingItsBaseUnit()
	{
		$productId = $this->product(1);
		$this->service->saveConfiguration(
			$productId,
			array(array('unit_id' => 4, 'factor' => '10', 'input_scale' => 3)),
			2,
			$this->actorId,
			'P1.1 exact conversion fixture.'
		);
		$this->service->verifyConfiguration(
			$productId,
			'Scratch test units',
			'Exact conversion test evidence.',
			$this->actorId
		);
		$result = $this->service->convertToBase($productId, 4, '0.125');
		$this->assertSame('1.250', $result['quantity_base']);
		$this->assertTrue($this->service->isBaseUnitLocked($productId));
	}
}
