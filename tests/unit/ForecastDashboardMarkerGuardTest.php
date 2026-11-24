<?php
if (!defined('BASEPATH')) {
	define('BASEPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Business_clock.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR .
	'libraries' . DIRECTORY_SEPARATOR . 'Forecast_service.php';

class ForecastDashboardMarkerGuardTest extends PHPUnit\Framework\TestCase
{
	public function testForecastDashboardRequiresDemoEnvironmentEnabledFlagAndDemoMarker()
	{
		$this->assertTrue(Forecast_service::isDashboardEnabled('demo', true, 'demo'));
		$this->assertFalse(Forecast_service::isDashboardEnabled('production', true, 'demo'));
		$this->assertFalse(Forecast_service::isDashboardEnabled('testing', true, 'demo'));
		$this->assertFalse(Forecast_service::isDashboardEnabled('demo', false, 'demo'));
		$this->assertFalse(Forecast_service::isDashboardEnabled('demo', true, 'production'));
		$this->assertFalse(Forecast_service::isDashboardEnabled('demo', true, null));
	}
}
