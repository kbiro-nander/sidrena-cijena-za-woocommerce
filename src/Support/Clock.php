<?php
/**
 * Clock abstraction so time-dependent logic is testable.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Support;

use DateTimeImmutable;
use DateTimeZone;

interface Clock {
	/** Current time in UTC. */
	public function now(): DateTimeImmutable;

	/** The site's timezone. */
	public function timezone(): DateTimeZone;

	/** Current time in the site's timezone. */
	public function nowLocal(): DateTimeImmutable;
}
