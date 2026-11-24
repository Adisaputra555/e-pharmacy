<?php
if (!defined('BASEPATH')) {
	define('BASEPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}

class DemoBannerTest extends PHPUnit\Framework\TestCase
{
	public function testAdminLayoutRequiresDemoEnvironmentAndMarker()
	{
		$layout = file_get_contents(
			dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
			'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'admin.php'
		);

		$this->assertNotFalse($layout);
		$this->assertStringContainsString("ENVIRONMENT === 'demo'", $layout);
		$this->assertStringContainsString("environment_marker", $layout);
		$this->assertStringContainsString("\$demoMarker['environment'] === 'demo'", $layout);
		$this->assertStringContainsString('Lingkungan sintetis · Tidak untuk produksi', $layout);
	}

	public function testBannerCannotBeDisabledByFeatureFlag()
	{
		$layout = file_get_contents(
			dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
			'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'admin.php'
		);

		$this->assertNotFalse($layout);
		$bannerPosition = strpos($layout, 'Lingkungan sintetis · Tidak untuk produksi');
		$bannerGuardPosition = strpos($layout, '$demoBannerEnabled = false');
		$this->assertNotFalse($bannerPosition);
		$this->assertNotFalse($bannerGuardPosition);
		$bannerGuard = substr($layout, $bannerGuardPosition, $bannerPosition - $bannerGuardPosition);
		$this->assertStringNotContainsString('procurement_recommendations_enabled', $bannerGuard);
	}
}
