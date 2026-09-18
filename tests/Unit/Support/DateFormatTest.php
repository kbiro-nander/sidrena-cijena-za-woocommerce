<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Support;

use DateTimeImmutable;
use SidrenaCijena\Support\DateFormat;
use SidrenaCijena\Tests\TestCase;

final class DateFormatTest extends TestCase {
	public function test_default_croatian_format_drops_leading_zeros_and_ends_with_dot(): void {
		self::assertSame( '10. 9. 2026.', DateFormat::croatian( new DateTimeImmutable( '2026-09-10' ) ) );
		self::assertSame( '2. 5. 2025.', DateFormat::croatian( new DateTimeImmutable( '2025-05-02' ) ) );
	}

	public function test_custom_format_is_respected(): void {
		self::assertSame( '10.09.2026', DateFormat::croatian( new DateTimeImmutable( '2026-09-10' ), 'd.m.Y' ) );
	}

	public function test_parse_iso_date_returns_immutable_or_null(): void {
		self::assertSame( '2026-09-10', DateFormat::parseIso( '2026-09-10' )?->format( 'Y-m-d' ) );
		self::assertNull( DateFormat::parseIso( '10.09.2026' ) );
		self::assertNull( DateFormat::parseIso( '2026-13-40' ) );
		self::assertNull( DateFormat::parseIso( '' ) );
	}
}
