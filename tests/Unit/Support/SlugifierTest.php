<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Support;

use SidrenaCijena\Support\Slugifier;
use SidrenaCijena\Tests\TestCase;

final class SlugifierTest extends TestCase {
	public function test_transliterates_croatian_diacritics_and_lowercases(): void {
		self::assertSame( 'ulica-kralja-zvonimira-12-zagreb', Slugifier::filenamePart( 'Ulica kralja Zvonimira 12, Zagreb' ) );
		self::assertSame( 'cvjecarnica-duro-sisak', Slugifier::filenamePart( 'Cvjećarnica Đuro Šišak' ) );
	}

	public function test_collapses_runs_of_punctuation_and_trims_dashes(): void {
		self::assertSame( 'web-1', Slugifier::filenamePart( '  --WEB / 1 --  ' ) );
	}

	public function test_empty_input_yields_empty_string(): void {
		self::assertSame( '', Slugifier::filenamePart( '   ' ) );
	}
}
