<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Updates;

use Brain\Monkey\Filters;
use SidrenaCijena\Tests\TestCase;
use SidrenaCijena\Updates\GitHubUpdater;

final class GitHubUpdaterTest extends TestCase {
	private const API = 'https://api.github.com/repos/kbiro-nander/sidrena-cijena-za-woocommerce/releases/latest';

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['scwc_test_http_get'] = [];
		unset( $GLOBALS['scwc_test_transients'][ GitHubUpdater::TRANSIENT ] );
	}

	private function release( string $tag = 'v1.2.0', bool $withAsset = true ): void {
		$GLOBALS['scwc_test_http_get'][ self::API ] = [ 'response' => [ 'code' => 200 ], 'body' => json_encode( [
			'tag_name'     => $tag,
			'html_url'     => 'https://github.com/kbiro-nander/sidrena-cijena-za-woocommerce/releases/tag/' . $tag,
			'body'         => "## Novo\n* nešto",
			'published_at' => '2026-10-01T06:00:00Z',
			'zipball_url'  => 'https://api.github.com/repos/kbiro-nander/sidrena-cijena-za-woocommerce/zipball/' . $tag,
			'assets'       => $withAsset ? [ [ 'name' => 'sidrena-cijena-za-woocommerce-1.2.0.zip', 'browser_download_url' => 'https://github.com/kbiro-nander/sidrena-cijena-za-woocommerce/releases/download/' . $tag . '/sidrena-cijena-za-woocommerce-1.2.0.zip' ] ] : [],
		] ) ];
	}

	private function updater( string $current = '1.1.3' ): GitHubUpdater {
		return new GitHubUpdater( 'kbiro-nander/sidrena-cijena-za-woocommerce', 'sidrena-cijena-za-woocommerce/sidrena-cijena-za-woocommerce.php', $current );
	}

	public function test_registers_update_and_info_filters(): void {
		Filters\expectAdded( 'pre_set_site_transient_update_plugins' )->once();
		Filters\expectAdded( 'plugins_api' )->once()->with( \Mockery::type( 'callable' ), 10, 3 );
		Filters\expectAdded( 'upgrader_source_selection' )->once()->with( \Mockery::type( 'callable' ), 10, 4 );
		$this->updater()->register();
	}

	public function test_latest_release_prefers_the_zip_asset_and_strips_the_v(): void {
		$this->release();
		$r = $this->updater()->latest();
		self::assertSame( '1.2.0', $r['version'] );
		self::assertStringEndsWith( '/releases/download/v1.2.0/sidrena-cijena-za-woocommerce-1.2.0.zip', $r['package'] );
		self::assertStringContainsString( 'nešto', $r['notes'] );
		$this->release( 'v1.2.0', false );
		unset( $GLOBALS['scwc_test_transients'][ GitHubUpdater::TRANSIENT ] );
		self::assertStringContainsString( '/zipball/v1.2.0', $this->updater()->latest()['package'], 'falls back to the source zipball' );
	}

	public function test_release_lookup_is_cached_and_failures_are_cached_briefly(): void {
		$this->release();
		$this->updater()->latest();
		$GLOBALS['scwc_test_http_get'] = [];
		self::assertSame( '1.2.0', $this->updater()->latest()['version'], 'served from cache without a second request' );
		unset( $GLOBALS['scwc_test_transients'][ GitHubUpdater::TRANSIENT ] );
		self::assertNull( $this->updater()->latest(), '404 (private repo / no release) yields null' );
		self::assertArrayHasKey( GitHubUpdater::TRANSIENT, $GLOBALS['scwc_test_transients'], 'negative result cached too' );
	}

	public function test_update_offered_only_when_newer(): void {
		$this->release( 'v1.2.0' );
		$t = (object) [ 'response' => [], 'checked' => [] ];
		$out = $this->updater( '1.1.3' )->checkUpdate( $t );
		$item = $out->response['sidrena-cijena-za-woocommerce/sidrena-cijena-za-woocommerce.php'];
		self::assertSame( '1.2.0', $item->new_version );
		self::assertSame( 'sidrena-cijena-za-woocommerce', $item->slug );
		self::assertStringContainsString( 'releases/download', $item->package );
		self::assertSame( '8.1', $item->requires_php );

		$same = $this->updater( '1.2.0' )->checkUpdate( (object) [ 'response' => [] ] );
		self::assertSame( [], $same->response );
		$newerLocal = $this->updater( '1.3.0' )->checkUpdate( (object) [ 'response' => [] ] );
		self::assertSame( [], $newerLocal->response );
		self::assertSame( 'x', $this->updater()->checkUpdate( 'x' ), 'non-object transient passes through' );
	}

	public function test_plugin_information_popup_only_for_our_slug(): void {
		$this->release();
		$u = $this->updater();
		self::assertFalse( $u->pluginInformation( false, 'plugin_information', (object) [ 'slug' => 'other' ] ) );
		$info = $u->pluginInformation( false, 'plugin_information', (object) [ 'slug' => 'sidrena-cijena-za-woocommerce' ] );
		self::assertSame( '1.2.0', $info->version );
		self::assertSame( 'Sidrena cijena za WooCommerce', $info->name );
		self::assertStringContainsString( 'nešto', $info->sections['changelog'] );
		self::assertStringContainsString( 'releases/download', $info->download_link );
	}

	public function test_extracted_folder_is_renamed_to_the_plugin_slug(): void {
		$base = sys_get_temp_dir() . '/scwc-upd-' . uniqid();
		mkdir( $base . '/kbiro-nander-sidrena-cijena-za-woocommerce-abc123', 0755, true );
		$u   = $this->updater();
		$out = $u->renameSource( $base . '/kbiro-nander-sidrena-cijena-za-woocommerce-abc123/', $base . '/', new \stdClass(), [ 'plugin' => 'sidrena-cijena-za-woocommerce/sidrena-cijena-za-woocommerce.php' ] );
		self::assertSame( $base . '/sidrena-cijena-za-woocommerce/', $out );
		self::assertDirectoryExists( $base . '/sidrena-cijena-za-woocommerce' );
		self::assertSame( $base . '/sidrena-cijena-za-woocommerce/', $u->renameSource( $base . '/sidrena-cijena-za-woocommerce/', $base . '/', new \stdClass(), [ 'plugin' => 'sidrena-cijena-za-woocommerce/sidrena-cijena-za-woocommerce.php' ] ), 'already correct' );
		self::assertSame( $base . '/whatever/', $u->renameSource( $base . '/whatever/', $base . '/', new \stdClass(), [ 'plugin' => 'other/other.php' ] ), 'other plugins untouched' );
		rmdir( $base . '/sidrena-cijena-za-woocommerce' );
		rmdir( $base );
	}
}
