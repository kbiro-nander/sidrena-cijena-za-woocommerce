<?php
/**
 * Product-edit fields: reference prices in the pricing group, service/unit/list fields on the general tab.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Admin;

use SidrenaCijena\Product\MetaKeys;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Settings\Settings;

class ProductFields {

	/** @var callable(int,string):string */
	private $metaReader;

	/** @var callable():int */
	private $postId;

	/**
	 * @param callable(int,string):string|null $metaReader     Reads a single meta value for a post ID.
	 * @param callable():int|null              $postIdProvider Returns the product ID being edited.
	 */
	public function __construct(
		private readonly ReferencePriceRegistry $registry,
		private readonly Settings $settings,
		?callable $metaReader = null,
		?callable $postIdProvider = null
	) {
		$this->metaReader = $metaReader ?? static fn( int $id, string $key ): string => (string) get_post_meta( $id, $key, true );
		$this->postId     = $postIdProvider ?? static fn(): int => (int) ( $GLOBALS['post']->ID ?? 0 );
	}

	public function register(): void {
		// Not inside the pricing group (hidden for variable products): parents may carry an inheritable reference price.
		add_action( 'woocommerce_product_options_general_product_data', [ $this, 'renderPricing' ], 5 );
		add_action( 'woocommerce_product_options_general_product_data', [ $this, 'renderGeneral' ] );
	}

	public function renderPricing(): void {
		$id = ( $this->postId )();
		echo '<div class="options_group scwc-reference-prices">';
		foreach ( $this->registry->enabled() as $key => $type ) {
			woocommerce_wp_text_input(
				[
					'id'        => "scwc_ref_{$key}_price",
					'label'     => ReferenceFieldLabel::price( $type ),
					'data_type' => 'price',
					'value'     => $this->meta( $id, $type->metaKey( 'price' ) ),
				]
			);
			woocommerce_wp_text_input(
				[
					'id'          => "scwc_ref_{$key}_date",
					'type'        => 'date',
					'label'       => ReferenceFieldLabel::date( $type ),
					'description' => ReferenceFieldLabel::dateDescription(),
					'value'       => $this->meta( $id, $type->metaKey( 'date' ) ),
				]
			);
			woocommerce_wp_checkbox(
				[
					'id'    => "scwc_ref_{$key}_na",
					'label' => ReferenceFieldLabel::na(),
					'value' => '1' === $this->meta( $id, $type->metaKey( 'na' ) ) ? 'yes' : 'no',
				]
			);
			$source = $this->meta( $id, $type->metaKey( 'source' ) );
			if ( '' !== $source ) {
				echo '<p class="form-field scwc-ref-source"><label>' . esc_html__( 'Izvor', 'sidrena-cijena-za-woocommerce' ) . '</label><span class="description">'
					. esc_html( ReferenceFieldLabel::source( $source ) ) . ' (' . esc_html( $source ) . ')</span></p>';
			}
		}
		echo '</div>';
	}

	public function renderGeneral(): void {
		$id = ( $this->postId )();
		echo '<div class="options_group scwc-general-fields">';
		echo '<input type="hidden" name="' . esc_attr( ProductSave::GENERAL_MARKER ) . '" value="1" />';
		woocommerce_wp_checkbox(
			[
				'id'          => 'scwc_is_service',
				'label'       => __( 'Ovo je usluga', 'sidrena-cijena-za-woocommerce' ),
				'description' => __( 'Cjenik usluga regenerira se pri svakoj promjeni cijene.', 'sidrena-cijena-za-woocommerce' ),
				'value'       => 'yes' === $this->meta( $id, MetaKeys::IS_SERVICE ) ? 'yes' : 'no',
			]
		);
		woocommerce_wp_text_input(
			[
				'id'    => 'scwc_unit',
				'label' => __( 'Jedinica mjere (kg, l, m, kom)', 'sidrena-cijena-za-woocommerce' ),
				'value' => $this->meta( $id, MetaKeys::UNIT ),
			]
		);
		woocommerce_wp_text_input(
			[
				'id'          => 'scwc_unit_quantity',
				'label'       => __( 'Neto količina u jedinici mjere', 'sidrena-cijena-za-woocommerce' ),
				'description' => __( 'Cijena za jedinicu mjere = cijena / neto količina.', 'sidrena-cijena-za-woocommerce' ),
				'data_type'   => 'decimal',
				'value'       => $this->meta( $id, MetaKeys::UNIT_QUANTITY ),
			]
		);
		woocommerce_wp_text_input(
			[
				'id'          => 'scwc_sale_name',
				'label'       => __( 'Naziv posebnog oblika prodaje', 'sidrena-cijena-za-woocommerce' ),
				'description' => __( 'Npr. sniženje, akcija, rasprodaja. Prazno = zadani naziv iz postavki.', 'sidrena-cijena-za-woocommerce' ),
				'placeholder' => (string) $this->settings->get( 'price_list.sale_name_default', '' ),
				'value'       => $this->meta( $id, MetaKeys::SALE_NAME ),
			]
		);
		woocommerce_wp_checkbox(
			[
				'id'    => 'scwc_exclude_from_price_list',
				'label' => __( 'Isključi iz cjenika', 'sidrena-cijena-za-woocommerce' ),
				'value' => 'yes' === $this->meta( $id, MetaKeys::EXCLUDE ) ? 'yes' : 'no',
			]
		);
		woocommerce_wp_checkbox(
			[
				'id'    => 'scwc_price_on_request',
				'label' => __( 'Cijena na upit (isključeno iz cjenika)', 'sidrena-cijena-za-woocommerce' ),
				'value' => 'yes' === $this->meta( $id, MetaKeys::PRICE_ON_REQUEST ) ? 'yes' : 'no',
			]
		);
		echo '</div>';
		$this->renderOmnibusPanel( $id );
	}

	private function renderOmnibusPanel( int $id ): void {
		$price = $this->meta( $id, MetaKeys::OMNIBUS_REF_PRICE );
		if ( '' === $price ) {
			return;
		}
		$rows = [
			__( 'Referentna cijena', 'sidrena-cijena-za-woocommerce' ) => $price . ' €',
			__( 'Početak sniženja', 'sidrena-cijena-za-woocommerce' ) => $this->meta( $id, MetaKeys::OMNIBUS_SALE_START ),
			__( 'Izvor', 'sidrena-cijena-za-woocommerce' ) => $this->meta( $id, MetaKeys::OMNIBUS_SOURCE ),
		];
		echo '<div class="options_group scwc-omnibus-panel">';
		echo '<p class="form-field"><strong>' . esc_html__( 'Najniža cijena u 30 dana', 'sidrena-cijena-za-woocommerce' ) . '</strong></p>';
		foreach ( $rows as $label => $value ) {
			if ( '' === $value ) {
				continue;
			}
			echo '<p class="form-field"><label>' . esc_html( $label ) . '</label><span class="description">' . esc_html( $value ) . '</span></p>';
		}
		echo '</div>';
	}

	private function meta( int $id, string $key ): string {
		return (string) ( $this->metaReader )( $id, $key );
	}
}
