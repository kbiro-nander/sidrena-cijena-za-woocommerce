<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Settings;

use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Sanitizer;
use SidrenaCijena\Tests\TestCase;

final class SanitizerTest extends TestCase {
	private function sanitize( array $raw ): array {
		return ( new Sanitizer() )->sanitize( $raw );
	}

	public function test_result_always_contains_every_default_key(): void {
		$out = $this->sanitize( [] );
		self::assertSame( array_keys( Defaults::all() ), array_keys( $out ) );
		self::assertSame( '2026-09-10', $out['reference_prices']['anchor']['date'] );
	}

	public function test_retention_days_has_legal_minimum_of_30(): void {
		self::assertSame( 30, $this->sanitize( [ 'price_list' => [ 'retention_days' => '7' ] ] )['price_list']['retention_days'] );
		self::assertSame( 60, $this->sanitize( [ 'price_list' => [ 'retention_days' => '60' ] ] )['price_list']['retention_days'] );
	}

	public function test_invalid_reference_date_falls_back_to_default(): void {
		$out = $this->sanitize( [ 'reference_prices' => [ 'anchor' => [ 'date' => '10.09.2026' ] ] ] );
		self::assertSame( '2026-09-10', $out['reference_prices']['anchor']['date'] );
		$out = $this->sanitize( [ 'reference_prices' => [ 'anchor' => [ 'date' => '2025-05-02' ] ] ] );
		self::assertSame( '2025-05-02', $out['reference_prices']['anchor']['date'] );
	}

	public function test_base_type_date_may_be_empty_until_pravilnik(): void {
		$out = $this->sanitize( [ 'reference_prices' => [ 'base' => [ 'enabled' => '1', 'date' => '' ] ] ] );
		self::assertTrue( $out['reference_prices']['base']['enabled'] );
		self::assertSame( '', $out['reference_prices']['base']['date'] );
	}

	public function test_category_overrides_keep_only_valid_rows_with_int_term_ids(): void {
		$out = $this->sanitize( [ 'reference_prices' => [ 'anchor' => [ 'category_overrides' => [
			[ 'term_ids' => [ '12', 'abc', '15' ], 'date' => '2025-05-02' ],
			[ 'term_ids' => [], 'date' => '2025-05-02' ],
			[ 'term_ids' => [ '3' ], 'date' => 'bad' ],
		] ] ] ] );
		self::assertSame( [ [ 'term_ids' => [ 12, 15 ], 'date' => '2025-05-02' ] ], $out['reference_prices']['anchor']['category_overrides'] );
	}

	public function test_slug_and_times_are_normalised(): void {
		$out = $this->sanitize( [ 'price_list' => [ 'slug' => ' Cjenik Ćevap/', 'generate_time' => '25:99' ], 'history' => [ 'sweep_time' => '7:05' ] ] );
		self::assertSame( 'cjenik-cevap', $out['price_list']['slug'] );
		self::assertSame( '06:00', $out['price_list']['generate_time'] );
		self::assertSame( '07:05', $out['history']['sweep_time'] );
	}

	public function test_empty_slug_falls_back_to_default(): void {
		self::assertSame( 'cjenik', $this->sanitize( [ 'price_list' => [ 'slug' => '' ] ] )['price_list']['slug'] );
	}

	public function test_enums_reject_unknown_values(): void {
		$out = $this->sanitize( [
			'price_list' => [ 'csv_delimiter' => '|', 'formats' => [ 'xml', 'pdf' ], 'service_rule' => 'nope', 'regenerate_on_change' => 'sometimes' ],
			'display'    => [ 'checkout' => 'weird', 'position' => 'left' ],
		] );
		self::assertSame( ';', $out['price_list']['csv_delimiter'] );
		self::assertSame( [ 'xml' ], $out['price_list']['formats'] );
		self::assertSame( 'virtual_or_flag', $out['price_list']['service_rule'] );
		self::assertSame( 'services', $out['price_list']['regenerate_on_change'] );
		self::assertSame( 'unit', $out['display']['checkout'] );
		self::assertSame( 'after', $out['display']['position'] );
	}

	public function test_formats_never_empty(): void {
		self::assertSame( [ 'xml', 'csv' ], $this->sanitize( [ 'price_list' => [ 'formats' => [] ] ] )['price_list']['formats'] );
	}

	public function test_booleans_are_normalised_from_form_values(): void {
		$out = $this->sanitize( [ 'display' => [ 'loop' => '0', 'omnibus' => 'on' ], 'advanced' => [ 'remove_data_on_uninstall' => 'yes' ] ] );
		self::assertFalse( $out['display']['loop'] );
		self::assertTrue( $out['display']['omnibus'] );
		self::assertTrue( $out['advanced']['remove_data_on_uninstall'] );
	}

	public function test_unchecked_checkbox_absent_from_post_becomes_false_when_section_present(): void {
		$out = $this->sanitize( [ 'display' => [ 'position' => 'after' ] ] );
		self::assertFalse( $out['display']['loop'] );
	}

	public function test_external_cron_key_is_generated_when_missing(): void {
		$out = $this->sanitize( [] );
		self::assertNotSame( '', $out['price_list']['external_cron_key'] );
		self::assertSame( 'keep-me', $this->sanitize( [ 'price_list' => [ 'external_cron_key' => 'keep-me' ] ] )['price_list']['external_cron_key'] );
	}

	public function test_text_fields_are_trimmed_and_stripped(): void {
		$out = $this->sanitize( [ 'outlet' => [ 'address' => '  <b>Ilica 1</b> ' ] ] );
		self::assertSame( 'Ilica 1', $out['outlet']['address'] );
	}

	public function test_auto_snapshot_datetime_validated(): void {
		self::assertSame( '2026-11-17 00:05', $this->sanitize( [ 'reference_prices' => [ 'base' => [ 'auto_snapshot_at' => '2026-11-17 00:05' ] ] ] )['reference_prices']['base']['auto_snapshot_at'] );
		self::assertSame( '', $this->sanitize( [ 'reference_prices' => [ 'base' => [ 'auto_snapshot_at' => 'tomorrow' ] ] ] )['reference_prices']['base']['auto_snapshot_at'] );
	}
}
