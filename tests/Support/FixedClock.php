<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use SidrenaCijena\Support\Clock;

final class FixedClock implements Clock {
	private DateTimeImmutable $now;
	private DateTimeZone $tz;

	public function __construct( string $nowUtc = '2026-10-01 04:00:12', string $tz = 'Europe/Zagreb' ) {
		$this->now = new DateTimeImmutable( $nowUtc, new DateTimeZone( 'UTC' ) );
		$this->tz  = new DateTimeZone( $tz );
	}

	public function now(): DateTimeImmutable { return $this->now; }
	public function timezone(): DateTimeZone { return $this->tz; }
	public function nowLocal(): DateTimeImmutable { return $this->now->setTimezone( $this->tz ); }
	public function set( string $nowUtc ): void { $this->now = new DateTimeImmutable( $nowUtc, new DateTimeZone( 'UTC' ) ); }
}
