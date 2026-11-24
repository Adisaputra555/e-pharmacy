<?php
class BusinessClockTest extends PHPUnit\Framework\TestCase
{
	protected $backupGlobals = false;

	public function testDefaultClockUsesJakartaWithoutChangingGlobalTimezone()
	{
		$timezone = date_default_timezone_get();
		$clock = new Business_clock();
		$now = $clock->now();

		$this->assertSame('Asia/Jakarta', $now->getTimezone()->getName());
		$this->assertSame($timezone, date_default_timezone_get());
	}

	public function testInjectedPastTimeIsConvertedToJakarta()
	{
		$clock = new Business_clock(function () {
			return new DateTimeImmutable('2026-11-21 16:15:00', new DateTimeZone('UTC'));
		});
		$now = $clock->now();

		$this->assertSame('Asia/Jakarta', $now->getTimezone()->getName());
		$this->assertSame('2026-11-21 23:15:00', $now->format('Y-m-d H:i:s'));
	}
}
