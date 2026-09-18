<?php
/**
 * Clock backed by WordPress' timezone settings.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Support;

use DateTimeImmutable;
use DateTimeZone;

final class WpClock implements Clock {

	public function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}

	public function timezone(): DateTimeZone {
		return wp_timezone();
	}

	public function nowLocal(): DateTimeImmutable {
		return $this->now()->setTimezone( $this->timezone() );
	}
}
