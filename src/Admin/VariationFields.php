<?php
/**
 * Reference-price fields on each variation panel (loop-indexed input names).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Admin;

use SidrenaCijena\Reference\ReferencePriceRegistry;

class VariationFields {

	/** @var callable(int,string):string */
	private $metaReader;

	/**
	 * @param callable(int,string):string|null $metaReader Reads a single meta value for a post ID.
	 */
	public function __construct( private readonly ReferencePriceRegistry $registry, ?callable $metaReader = null ) {
		$this->metaReader = $metaReader ?? static fn( int $id, string $key ): string => (string) get_post_meta( $id, $key, true );
	}

	public function register(): void {
		add_action( 'woocommerce_variation_options_pricing', [ $this, 'render' ], 10, 3 );
	}

	/**
	 * @param int                 $loop          Variation loop index.
	 * @param array<string,mixed> $variationData Variation post meta (unused; read through the meta reader).
	 * @param object              $variation     WP_Post-like object with an ID property.
	 */
	public function render( int $loop, array $variationData, object $variation ): void {
		$id = (int) ( $variation->ID ?? 0 );
		foreach ( $this->registry->enabled() as $key => $type ) {
			echo '<div class="form-row form-row-full scwc-variation-reference">';
			woocommerce_wp_text_input(
				[
					'id'            => "scwc_ref_{$key}_price_{$loop}",
					'name'          => "scwc_ref_{$key}_price[{$loop}]",
					'label'         => ReferenceFieldLabel::price( $type ),
					'data_type'     => 'price',
					'wrapper_class' => 'form-row form-row-first',
					'value'         => $this->meta( $id, $type->metaKey( 'price' ) ),
				]
			);
			woocommerce_wp_text_input(
				[
					'id'            => "scwc_ref_{$key}_date_{$loop}",
					'name'          => "scwc_ref_{$key}_date[{$loop}]",
					'type'          => 'date',
					'label'         => ReferenceFieldLabel::date( $type ),
					'description'   => ReferenceFieldLabel::dateDescription(),
					'wrapper_class' => 'form-row form-row-last',
					'value'         => $this->meta( $id, $type->metaKey( 'date' ) ),
				]
			);
			woocommerce_wp_checkbox(
				[
					'id'            => "scwc_ref_{$key}_na_{$loop}",
					'name'          => "scwc_ref_{$key}_na[{$loop}]",
					'label'         => ReferenceFieldLabel::na(),
					'wrapper_class' => 'form-row form-row-full',
					'value'         => '1' === $this->meta( $id, $type->metaKey( 'na' ) ) ? 'yes' : 'no',
				]
			);
			echo '</div>';
		}
	}

	private function meta( int $id, string $key ): string {
		return (string) ( $this->metaReader )( $id, $key );
	}
}
