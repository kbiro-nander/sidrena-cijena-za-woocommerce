<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Endpoint;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use SidrenaCijena\Endpoint\Endpoint;
use SidrenaCijena\Endpoint\Headers;
use SidrenaCijena\Endpoint\IndexRenderer;
use SidrenaCijena\Endpoint\Response;
use SidrenaCijena\PriceList\Manifest;
use SidrenaCijena\PriceList\Storage;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\TestCase;

final class EndpointTest extends TestCase {
	private string $dir;
	private Storage $storage;
	private Manifest $manifest;
	private array $sent = [];
	private array $runs = [];

	protected function setUp(): void {
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/scwc-ep-' . uniqid();
		$this->storage  = new Storage( $this->dir, 'https://example.hr/wp-content/uploads/scwc-cjenik' );
		$this->storage->ensure();
		$this->manifest = new Manifest( $this->storage );
		file_put_contents( $this->storage->path( 'webshop_a_web1_1_20261001_060000.xml' ), '<Cjenik/>' );
		file_put_contents( $this->storage->path( 'webshop_a_web1_1_20261001_060000.csv' ), "vrsta;naziv\n" );
		file_put_contents( $this->storage->path( 'webshop_a_web1_1_20260930_060000.xml' ), '<Cjenik/>' );
		$this->manifest->add( [ 'name' => 'webshop_a_web1_1_20260930_060000.xml', 'format' => 'xml', 'generated_at' => '2026-09-30T06:00:00+02:00', 'generated_at_utc' => '2026-09-30 04:00:00', 'reason' => 'scheduled', 'products' => 1, 'services' => 0, 'size' => 9, 'sha256' => 'old' ] );
		$this->manifest->add( [ 'name' => 'webshop_a_web1_1_20261001_060000.xml', 'format' => 'xml', 'generated_at' => '2026-10-01T06:00:00+02:00', 'generated_at_utc' => '2026-10-01 04:00:00', 'reason' => 'scheduled', 'products' => 2, 'services' => 1, 'size' => 9, 'sha256' => 'newxml' ] );
		$this->manifest->add( [ 'name' => 'webshop_a_web1_1_20261001_060000.csv', 'format' => 'csv', 'generated_at' => '2026-10-01T06:00:00+02:00', 'generated_at_utc' => '2026-10-01 04:00:00', 'reason' => 'scheduled', 'products' => 2, 'services' => 1, 'size' => 12, 'sha256' => 'newcsv' ] );
		file_put_contents( $this->storage->path( 'poslovnica_vukovarska-5_zg-02_3_20261001_060000.xml' ), '<Cjenik/>' );
		$this->manifest->add( [ 'name' => 'poslovnica_vukovarska-5_zg-02_3_20261001_060000.xml', 'format' => 'xml', 'outlet' => 'zg-02', 'generated_at' => '2026-10-01T06:00:00+02:00', 'generated_at_utc' => '2026-10-01 04:00:00', 'reason' => 'scheduled', 'products' => 2, 'services' => 1, 'size' => 9, 'sha256' => 'zgxml' ] );
		$this->sent = [];
		$this->runs = [];
	}

	protected function tearDown(): void {
		foreach ( glob( $this->dir . '/*' ) ?: [] as $f ) { is_file( $f ) && unlink( $f ); }
		foreach ( glob( $this->dir . '/tmp/*' ) ?: [] as $f ) { is_file( $f ) && unlink( $f ); }
		@rmdir( $this->dir . '/tmp' );
		@rmdir( $this->dir );
		parent::tearDown();
	}

	private function endpoint( ?Settings $settings = null ): Endpoint {
		$settings = $settings ?? ( new Settings( Defaults::all() ) )
			->with( 'price_list.external_cron_key', 'secret' )
			->with( 'outlet.address', 'Ulica 1' )
			->with( 'outlet.label', 'WEB1' )
			->with( 'outlets.additional', [ [ 'form' => 'poslovnica', 'address' => 'Vukovarska 5', 'label' => 'ZG-02', 'storage_number' => '3' ] ] );
		return new Endpoint(
			$settings,
			$this->storage,
			$this->manifest,
			new Headers( function ( string $h ) { $this->sent[] = $h; } ),
			new IndexRenderer( $settings, $this->storage, $this->manifest, dirname( __DIR__, 3 ) . '/templates' ),
			function ( string $reason ) { $this->runs[] = $reason; return true; }
		);
	}

	public function test_registers_rewrite_rules_query_vars_and_handlers(): void {
		Actions\expectAdded( 'init' )->once();
		Filters\expectAdded( 'query_vars' )->once();
		Actions\expectAdded( 'template_redirect' )->once()->with( \Mockery::type( 'callable' ), 1 );
		Filters\expectAdded( 'robots_txt' )->once();
		Actions\expectAdded( 'update_option_scwc_settings' )->once();
		Actions\expectAdded( 'wp_loaded' )->once()->with( \Mockery::type( 'callable' ) );
		$this->endpoint()->register();
	}

	public function test_rewrite_rules_for_slug(): void {
		$rules = $this->endpoint()->rules();
		self::assertSame( 'index.php?scwc_cjenik=index', $rules['^cjenik/?$'] );
		self::assertSame( 'index.php?scwc_cjenik=json', $rules['^cjenik/index\.json$'] );
		self::assertSame( 'index.php?scwc_cjenik=latest&scwc_format=$matches[1]', $rules['^cjenik/latest\.(xml|csv)$'] );
		self::assertSame( 'index.php?scwc_cjenik=file&scwc_file=$matches[1]', $rules['^cjenik/([a-z0-9][a-z0-9._-]*\.(?:xml|csv))$'] );
		self::assertSame( [ 'scwc_cjenik', 'scwc_format', 'scwc_file', 'scwc_outlet' ], $this->endpoint()->queryVars( [] ), 'scwc_run/key stay private ($_GET), `key` would collide with WC order keys' );
		self::assertSame( 'index.php?scwc_cjenik=latest&scwc_outlet=$matches[1]&scwc_format=$matches[2]', $rules['^cjenik/([a-z0-9-]+)/latest\.(xml|csv)$'] );
		self::assertSame( 'index.php?scwc_cjenik=json&scwc_outlet=$matches[1]', $rules['^cjenik/([a-z0-9-]+)/index\.json$'] );
		self::assertSame( 'index.php?scwc_cjenik=index&scwc_outlet=$matches[1]', $rules['^cjenik/([a-z0-9-]+)/?$'] );
		$keys = array_keys( $rules );
		self::assertLessThan( array_search( '^cjenik/([a-z0-9-]+)/?$', $keys, true ), array_search( '^cjenik/([a-z0-9-]+)/latest\.(xml|csv)$', $keys, true ), 'outlet latest before outlet index' );
	}

	public function test_latest_xml_streams_newest_file_with_no_cache_headers(): void {
		$r = $this->endpoint()->resolve( [ 'scwc_cjenik' => 'latest', 'scwc_format' => 'xml' ], [] );
		self::assertSame( 200, $r->status );
		self::assertSame( $this->storage->path( 'webshop_a_web1_1_20261001_060000.xml' ), $r->file );
		self::assertContains( 'Content-Type: application/xml; charset=UTF-8', $this->sent );
		self::assertContains( Headers::NO_CACHE, $this->sent );
		self::assertContains( 'ETag: "newxml"', $this->sent );
	}

	public function test_latest_without_outlet_serves_the_primary_even_when_another_outlet_is_newer(): void {
		$this->manifest->add( [ 'name' => 'poslovnica_vukovarska-5_zg-02_3_20261002_060000.xml', 'format' => 'xml', 'outlet' => 'zg-02', 'generated_at' => '2026-10-02T06:00:00+02:00', 'generated_at_utc' => '2026-10-02 04:00:00', 'reason' => 'scheduled', 'products' => 2, 'services' => 1, 'size' => 9, 'sha256' => 'zg2' ] );
		file_put_contents( $this->storage->path( 'poslovnica_vukovarska-5_zg-02_3_20261002_060000.xml' ), '<Cjenik/>' );
		$r = $this->endpoint()->resolve( [ 'scwc_cjenik' => 'latest', 'scwc_format' => 'xml' ], [] );
		self::assertSame( $this->storage->path( 'webshop_a_web1_1_20261001_060000.xml' ), $r->file, 'legacy entries (no outlet key) belong to the primary' );
	}

	public function test_latest_for_a_specific_outlet(): void {
		$r = $this->endpoint()->resolve( [ 'scwc_cjenik' => 'latest', 'scwc_outlet' => 'zg-02', 'scwc_format' => 'xml' ], [] );
		self::assertSame( 200, $r->status );
		self::assertSame( $this->storage->path( 'poslovnica_vukovarska-5_zg-02_3_20261001_060000.xml' ), $r->file );
		self::assertContains( 'ETag: "zgxml"', $this->sent );
		self::assertSame( 404, $this->endpoint()->resolve( [ 'scwc_cjenik' => 'latest', 'scwc_outlet' => 'zg-02', 'scwc_format' => 'csv' ], [] )->status );
		self::assertSame( 404, $this->endpoint()->resolve( [ 'scwc_cjenik' => 'latest', 'scwc_outlet' => 'nope', 'scwc_format' => 'xml' ], [] )->status );
		$r = $this->endpoint()->resolve( [ 'scwc_cjenik' => 'latest', 'scwc_outlet' => 'web1', 'scwc_format' => 'csv' ], [] );
		self::assertSame( $this->storage->path( 'webshop_a_web1_1_20261001_060000.csv' ), $r->file, 'primary reachable under its key too' );
	}

	public function test_index_lists_every_outlet_with_its_own_latest_links(): void {
		$r = $this->endpoint()->resolve( [ 'scwc_cjenik' => 'index' ], [] );
		self::assertStringContainsString( 'ZG-02', $r->body );
		self::assertStringContainsString( 'Vukovarska 5', $r->body );
		self::assertStringContainsString( 'https://example.hr/cjenik/zg-02/latest.xml', $r->body );
		self::assertStringContainsString( 'https://example.hr/cjenik/latest.xml', $r->body );
		self::assertStringContainsString( 'https://example.hr/cjenik/poslovnica_vukovarska-5_zg-02_3_20261001_060000.xml', $r->body );
		$one = $this->endpoint()->resolve( [ 'scwc_cjenik' => 'index', 'scwc_outlet' => 'zg-02' ], [] );
		self::assertSame( 200, $one->status );
		self::assertStringContainsString( 'ZG-02', $one->body );
		self::assertStringNotContainsString( 'webshop_a_web1_1_20261001_060000.xml', $one->body, 'per-outlet page lists only that outlet' );
		self::assertSame( 404, $this->endpoint()->resolve( [ 'scwc_cjenik' => 'index', 'scwc_outlet' => 'nope' ], [] )->status );
	}

	public function test_index_json_has_outlets_and_keeps_primary_at_top_level(): void {
		$json = json_decode( $this->endpoint()->resolve( [ 'scwc_cjenik' => 'json' ], [] )->body, true );
		self::assertSame( [ 'web1', 'zg-02' ], array_column( $json['outlets'], 'key' ) );
		self::assertSame( 'https://example.hr/cjenik/zg-02/latest.xml', $json['outlets'][1]['latest']['xml'] );
		self::assertArrayNotHasKey( 'csv', $json['outlets'][1]['latest'] );
		self::assertSame( 'ZG-02', $json['outlets'][1]['label'] );
		self::assertSame( 'https://example.hr/wp-content/uploads/scwc-cjenik/poslovnica_vukovarska-5_zg-02_3_20261001_060000.xml', $json['outlets'][1]['files'][0]['direct_url'], 'stable path independent of rewrite rules' );
		self::assertArrayHasKey( 'direct_url', $json['files'][0] );
		self::assertCount( 1, $json['outlets'][1]['files'] );
		self::assertCount( 3, $json['outlets'][0]['files'], 'legacy entries listed under the primary' );
		self::assertSame( 'https://example.hr/cjenik/latest.xml', $json['latest']['xml'], 'top-level = primary (backward compatible)' );
		self::assertCount( 3, $json['files'] );
		$one = json_decode( $this->endpoint()->resolve( [ 'scwc_cjenik' => 'json', 'scwc_outlet' => 'zg-02' ], [] )->body, true );
		self::assertCount( 1, $one['outlets'] );
		self::assertSame( 'zg-02', $one['outlets'][0]['key'] );
	}

	public function test_latest_returns_404_when_format_missing_or_unknown(): void {
		$this->manifest->remove( 'webshop_a_web1_1_20261001_060000.csv' );
		self::assertSame( 404, $this->endpoint()->resolve( [ 'scwc_cjenik' => 'latest', 'scwc_format' => 'csv' ], [] )->status );
		self::assertSame( 404, $this->endpoint()->resolve( [ 'scwc_cjenik' => 'latest', 'scwc_format' => 'pdf' ], [] )->status );
	}

	public function test_named_file_served_immutable_and_only_when_in_manifest(): void {
		$r = $this->endpoint()->resolve( [ 'scwc_cjenik' => 'file', 'scwc_file' => 'webshop_a_web1_1_20260930_060000.xml' ], [] );
		self::assertSame( 200, $r->status );
		self::assertContains( Headers::IMMUTABLE, $this->sent );
		file_put_contents( $this->storage->path( 'rogue.xml' ), 'x' );
		self::assertSame( 404, $this->endpoint()->resolve( [ 'scwc_cjenik' => 'file', 'scwc_file' => 'rogue.xml' ], [] )->status, 'file not in manifest' );
		self::assertSame( 404, $this->endpoint()->resolve( [ 'scwc_cjenik' => 'file', 'scwc_file' => '../manifest.json' ], [] )->status, 'traversal' );
		self::assertSame( 404, $this->endpoint()->resolve( [ 'scwc_cjenik' => 'file', 'scwc_file' => 'manifest.json' ], [] )->status );
	}

	public function test_etag_match_returns_304(): void {
		$r = $this->endpoint()->resolve( [ 'scwc_cjenik' => 'latest', 'scwc_format' => 'xml' ], [ 'HTTP_IF_NONE_MATCH' => '"newxml"' ] );
		self::assertSame( 304, $r->status );
		self::assertNull( $r->file );
	}

	public function test_index_html_lists_files_newest_first_with_links(): void {
		$r = $this->endpoint()->resolve( [ 'scwc_cjenik' => 'index' ], [] );
		self::assertSame( 200, $r->status );
		self::assertStringContainsString( 'Content-Type: text/html; charset=UTF-8', implode( "\n", $this->sent ) );
		self::assertStringContainsString( 'https://example.hr/cjenik/latest.xml', $r->body );
		self::assertStringContainsString( 'https://example.hr/cjenik/latest.csv', $r->body );
		self::assertStringContainsString( 'https://example.hr/cjenik/webshop_a_web1_1_20261001_060000.xml', $r->body );
		self::assertLessThan( strpos( $r->body, '20260930' ), strpos( $r->body, '20261001_060000.xml' ) );
		self::assertStringContainsString( 'Cjenik', $r->body );
	}

	public function test_index_json_shape(): void {
		$r    = $this->endpoint()->resolve( [ 'scwc_cjenik' => 'json' ], [] );
		$json = json_decode( $r->body, true );
		self::assertSame( 'https://example.hr/cjenik/latest.xml', $json['latest']['xml'] );
		self::assertSame( 'https://example.hr/cjenik/latest.csv', $json['latest']['csv'] );
		self::assertCount( 3, $json['files'] );
		self::assertSame( 'https://example.hr/cjenik/webshop_a_web1_1_20261001_060000.csv', $json['files'][0]['url'], 'newest-first (csv added last)' );
		self::assertSame( 2, $json['files'][0]['products'] );
		self::assertArrayNotHasKey( 'reason', $json['files'][0] );
	}

	public function test_external_trigger_requires_matching_key(): void {
		$r = $this->endpoint()->resolve( [ 'scwc_cjenik' => 'index', 'scwc_run' => '1', 'key' => 'wrong' ], [] );
		self::assertSame( 403, $r->status );
		self::assertSame( [], $this->runs );
		$r = $this->endpoint()->resolve( [ 'scwc_cjenik' => 'index', 'scwc_run' => '1', 'key' => 'secret' ], [] );
		self::assertSame( 200, $r->status );
		self::assertSame( [ 'external' ], $this->runs );
		self::assertStringContainsString( '"ok":true', $r->body );
	}

	public function test_nothing_to_do_without_our_query_var_or_when_disabled(): void {
		self::assertNull( $this->endpoint()->resolve( [ 'p' => 5 ], [] ) );
		$off = ( new Settings( Defaults::all() ) )->with( 'price_list.enabled', false );
		self::assertSame( 404, $this->endpoint( $off )->resolve( [ 'scwc_cjenik' => 'index' ], [] )->status );
	}

	public function test_slug_change_on_settings_save_flags_rewrite_flush(): void {
		\Brain\Monkey\Functions\expect( 'update_option' )->once()->with( 'scwc_flush_rewrite', 1 );
		$e = $this->endpoint();
		$e->onSettingsUpdated( [ 'price_list' => [ 'slug' => 'cjenik' ] ], [ 'price_list' => [ 'slug' => 'cijene' ] ] );
		\Brain\Monkey\Functions\expect( 'update_option' )->never();
		$e->onSettingsUpdated( [ 'price_list' => [ 'slug' => 'cjenik' ] ], [ 'price_list' => [ 'slug' => 'cjenik' ] ] );
	}

	public function test_index_json_link_is_absolute(): void {
		$r = $this->endpoint()->resolve( [ 'scwc_cjenik' => 'index' ], [] );
		self::assertStringContainsString( 'href="https://example.hr/cjenik/index.json"', $r->body );
	}

	public function test_robots_txt_allows_the_directory(): void {
		self::assertStringContainsString( "Allow: /cjenik/\n", $this->endpoint()->robots( "User-agent: *\n", true ) );
	}

	public function test_slug_change_flags_rewrite_flush_and_maybe_flush_consumes_it(): void {
		$GLOBALS['scwc_test_flushed'] = 0;
		\Brain\Monkey\Functions\expect( 'get_option' )->with( 'scwc_flush_rewrite' )->andReturn( 1 );
		\Brain\Monkey\Functions\expect( 'delete_option' )->once()->with( 'scwc_flush_rewrite' );
		$this->endpoint()->maybeFlush();
		self::assertSame( 1, $GLOBALS['scwc_test_flushed'] );
	}

	public function test_rules_persisted_checks_the_saved_rewrite_rules_option(): void {
		\Brain\Monkey\Functions\when( 'get_option' )->alias( fn( $k, $d = false ) => 'rewrite_rules' === $k ? [ '^cjenik/?$' => 'index.php?scwc_cjenik=index', 'other' => 'x' ] : $d );
		self::assertTrue( $this->endpoint()->rulesPersisted() );
		\Brain\Monkey\Functions\when( 'get_option' )->alias( fn( $k, $d = false ) => 'rewrite_rules' === $k ? [ 'other' => 'x' ] : $d );
		self::assertFalse( $this->endpoint()->rulesPersisted() );
		\Brain\Monkey\Functions\when( 'get_option' )->alias( fn( $k, $d = false ) => $d );
		self::assertFalse( $this->endpoint()->rulesPersisted(), 'no saved rules at all' );
	}

	public function test_ensure_rules_flags_a_flush_only_when_rules_are_missing(): void {
		\Brain\Monkey\Functions\when( 'get_option' )->alias( fn( $k, $d = false ) => 'rewrite_rules' === $k ? [ 'other' => 'x' ] : $d );
		\Brain\Monkey\Functions\expect( 'update_option' )->once()->with( 'scwc_flush_rewrite', 1 );
		self::assertTrue( $this->endpoint()->ensureRules() );
		\Brain\Monkey\Functions\when( 'get_option' )->alias( fn( $k, $d = false ) => 'rewrite_rules' === $k ? [ '^cjenik/?$' => 'y' ] : $d );
		\Brain\Monkey\Functions\expect( 'update_option' )->never();
		self::assertFalse( $this->endpoint()->ensureRules() );
	}
}
