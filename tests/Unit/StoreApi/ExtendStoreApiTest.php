<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\StoreApi;

use Brain\Monkey\Actions;
use SidrenaCijena\Display\BadgeDataFactory;
use SidrenaCijena\Display\PriceBadge;
use SidrenaCijena\Display\PriceFormatter;
use SidrenaCijena\Product\BarcodeResolver;
use SidrenaCijena\Product\BrandResolver;
use SidrenaCijena\Product\ProductAdapter;
use SidrenaCijena\Reference\CategoryOverrideResolver;
use SidrenaCijena\Reference\ReferenceDateResolver;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Reference\ReferencePriceRepository;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\StoreApi\ExtendStoreApi;
use SidrenaCijena\Tests\TestCase;

final class ExtendStoreApiTest extends TestCase {
	private function ext(): ExtendStoreApi {
		$settings = new Settings( Defaults::all() );
		$factory  = new BadgeDataFactory(
			$settings,
			ReferencePriceRegistry::fromSettings( $settings ),
			new ProductAdapter( new BrandResolver(), new BarcodeResolver() ),
			new ReferencePriceRepository( new ReferenceDateResolver( new CategoryOverrideResolver( fn() => [] ) ) ),
			new PriceFormatter(),
			fn( int $id ) => \wc_get_product( $id ) ?: null,
		);
		return new ExtendStoreApi( $factory, new PriceBadge( $settings, dirname( __DIR__, 3 ) . '/templates' ) );
	}

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['scwc_test_store_api'] = [];
	}

	public function test_registers_on_blocks_loaded(): void {
		Actions\expectAdded( 'woocommerce_blocks_loaded' )->once();
		$this->ext()->register();
	}

	public function test_registers_immediately_when_blocks_already_loaded(): void {
		do_action( 'woocommerce_blocks_loaded' );
		Actions\expectAdded( 'woocommerce_blocks_loaded' )->never();
		$this->ext()->register();
		self::assertCount( 2, $GLOBALS['scwc_test_store_api'] );
	}

	public function test_registers_cart_item_and_product_endpoint_data_using_class_constants(): void {
		$this->ext()->registerEndpointData();
		$regs = $GLOBALS['scwc_test_store_api'];
		self::assertCount( 2, $regs );
		self::assertSame( 'cart-item', $regs[0]['endpoint'] );
		self::assertSame( 'product', $regs[1]['endpoint'] );
		self::assertSame( 'sidrena-cijena', $regs[0]['namespace'] );
		self::assertSame( ARRAY_A, $regs[0]['schema_type'] );
		$schema = ( $regs[0]['schema_callback'] )();
		self::assertArrayHasKey( 'references', $schema );
		self::assertArrayHasKey( 'lowest_30_days', $schema );
		self::assertArrayHasKey( 'badge_html', $schema );
	}

	public function test_cart_item_data_callback_exposes_reference_and_badge(): void {
		$p = $this->product( [ 'id' => 3, 'regular_price' => '10', 'sale_price' => '8', 'meta' => [ '_scwc_ref_anchor_price' => '12', '_scwc_omnibus_ref_price' => '9' ] ] );
		$this->ext()->registerEndpointData();
		$data = ( $GLOBALS['scwc_test_store_api'][0]['data_callback'] )( [ 'data' => $p, 'quantity' => 1 ] );
		self::assertSame( 'anchor', $data['references'][0]['key'] );
		self::assertSame( '2026-09-10', $data['references'][0]['date'] );
		self::assertSame( 12.0, $data['references'][0]['amount'] );
		self::assertSame( 9.0, $data['lowest_30_days']['amount'] );
		self::assertStringContainsString( 'scwc-badge--store_api', $data['badge_html'] );
	}

	public function test_product_data_callback_returns_empty_shape_when_nothing_to_show(): void {
		$p = $this->product( [ 'id' => 4, 'regular_price' => '10' ] );
		$this->ext()->registerEndpointData();
		$data = ( $GLOBALS['scwc_test_store_api'][1]['data_callback'] )( $p );
		self::assertSame( [], $data['references'] );
		self::assertNull( $data['lowest_30_days'] );
		self::assertSame( '', $data['badge_html'] );
	}
}
