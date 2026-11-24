<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Business_clock
{
	private $provider;

	public function __construct($provider = null)
	{
		$this->provider = $provider ?: function () {
			return new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
		};
	}

	public function now()
	{
		$value = call_user_func($this->provider);
		if (!($value instanceof DateTimeInterface)) {
			throw new RuntimeException('BusinessClock provider must return a date/time value.');
		}

		$source = new DateTimeImmutable($value->format('Y-m-d H:i:s.uP'));
		return $source->setTimezone(new DateTimeZone('Asia/Jakarta'));
	}
}
