<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Monkey\Functions\stubTranslationFunctions();
		Monkey\Functions\stubEscapeFunctions();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a stub product. Keys mirror WC getters: id, parent_id, type, sku, name, regular_price,
	 * sale_price, price, on_sale, virtual, stock_status, catalog_visibility, date_created,
	 * permalink, global_unique_id, category_ids, children, meta (array).
	 *
	 * @param array<string,mixed> $props
	 */
	protected function product( array $props = [] ): \WC_Product {
		$type = $props['type'] ?? 'simple';
		return match ( $type ) {
			'variable'  => new \WC_Product_Variable( $props ),
			'variation' => new \WC_Product_Variation( $props ),
			'external'  => new \WC_Product_External( $props ),
			'grouped'   => new \WC_Product_Grouped( $props ),
			default     => new \WC_Product_Simple( $props ),
		};
	}
}
