<?php
/**
 * Minimal WooCommerce class stubs for unit tests (no WordPress required).
 *
 * Products are backed by a props array; meta by an in-memory array.
 */
declare(strict_types=1);

if ( ! class_exists( 'WC_DateTime' ) ) {
	class WC_DateTime extends DateTime {
		public function date( string $format ): string {
			return $this->format( $format );
		}
		public function date_i18n( string $format = 'Y-m-d' ): string {
			return $this->format( $format );
		}
	}
}

if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {
		/** @var array<string,mixed> */
		protected array $props;
		/** @var array<string,mixed> */
		protected array $meta;
		/** @var array<int,WC_Product> */
		public static array $registry = [];

		/** @param array<string,mixed> $props */
		public function __construct( array $props = [] ) {
			$this->props = array_merge(
				[
					'id'                 => 0,
					'parent_id'          => 0,
					'type'               => 'simple',
					'sku'                => '',
					'name'               => '',
					'regular_price'      => '',
					'sale_price'         => '',
					'price'              => null,
					'on_sale'            => null,
					'virtual'            => false,
					'stock_status'       => 'instock',
					'catalog_visibility' => 'visible',
					'date_created'       => null,
					'date_on_sale_from'  => null,
					'date_on_sale_to'    => null,
					'permalink'          => '',
					'global_unique_id'   => '',
					'category_ids'       => [],
					'children'           => [],
					'status'             => 'publish',
					'exists'             => true,
				],
				$props
			);
			$this->meta = $props['meta'] ?? [];
			if ( $this->props['id'] ) {
				self::$registry[ (int) $this->props['id'] ] = $this;
			}
		}

		public function get_id(): int { return (int) $this->props['id']; }
		public function get_parent_id( string $context = 'view' ): int { return (int) $this->props['parent_id']; }
		public function get_type(): string { return (string) $this->props['type']; }
		public function is_type( $type ): bool { return is_array( $type ) ? in_array( $this->get_type(), $type, true ) : $this->get_type() === $type; }
		public function get_sku( string $context = 'view' ): string { return (string) $this->props['sku']; }
		public function get_name( string $context = 'view' ): string { return (string) $this->props['name']; }
		public function get_status( string $context = 'view' ): string { return (string) $this->props['status']; }
		public function exists(): bool { return (bool) $this->props['exists']; }
		public function get_regular_price( string $context = 'view' ): string { return (string) $this->props['regular_price']; }
		public function get_sale_price( string $context = 'view' ): string { return (string) $this->props['sale_price']; }
		public function get_price( string $context = 'view' ): string {
			if ( null !== $this->props['price'] ) {
				return (string) $this->props['price'];
			}
			return $this->is_on_sale( $context ) ? (string) $this->props['sale_price'] : (string) $this->props['regular_price'];
		}
		public function is_on_sale( string $context = 'view' ): bool {
			if ( null !== $this->props['on_sale'] ) {
				return (bool) $this->props['on_sale'];
			}
			return '' !== $this->props['sale_price'] && (float) $this->props['sale_price'] < (float) $this->props['regular_price'];
		}
		public function get_date_on_sale_from( string $context = 'view' ): ?WC_DateTime { return $this->props['date_on_sale_from']; }
		public function get_date_on_sale_to( string $context = 'view' ): ?WC_DateTime { return $this->props['date_on_sale_to']; }
		public function is_virtual(): bool { return (bool) $this->props['virtual']; }
		public function get_stock_status( string $context = 'view' ): string { return (string) $this->props['stock_status']; }
		public function is_in_stock(): bool { return 'instock' === $this->props['stock_status'] || 'onbackorder' === $this->props['stock_status']; }
		public function get_catalog_visibility( string $context = 'view' ): string { return (string) $this->props['catalog_visibility']; }
		public function is_visible(): bool { return in_array( $this->props['catalog_visibility'], [ 'visible', 'catalog' ], true ); }
		public function get_date_created( string $context = 'view' ): ?WC_DateTime { return $this->props['date_created']; }
		public function get_permalink(): string { return (string) $this->props['permalink']; }
		public function get_global_unique_id( string $context = 'view' ): string { return (string) $this->props['global_unique_id']; }
		/** @return int[] */
		public function get_category_ids( string $context = 'view' ): array { return (array) $this->props['category_ids']; }
		/** @return int[] */
		public function get_children(): array { return (array) $this->props['children']; }
		/** @return int[] */
		public function get_visible_children(): array { return (array) $this->props['children']; }
		public function get_price_html( string $deprecated = '' ): string { return (string) ( $this->props['price_html'] ?? '<span class="amount">' . $this->get_price() . '</span>' ); }
		public function get_price_suffix( $price = '', $qty = 1 ): string { return (string) ( $this->props['price_suffix'] ?? '' ); }

		/** @return mixed */
		public function get_meta( string $key = '', bool $single = true, string $context = 'view' ) {
			if ( ! array_key_exists( $key, $this->meta ) ) {
				return $single ? '' : [];
			}
			return $this->meta[ $key ];
		}
		public function meta_exists( string $key ): bool { return array_key_exists( $key, $this->meta ); }
		/** @param mixed $value */
		public function update_meta_data( string $key, $value, int $meta_id = 0 ): void { $this->meta[ $key ] = $value; }
		/** @param mixed $value */
		public function add_meta_data( string $key, $value, bool $unique = false ): void { $this->meta[ $key ] = $value; }
		public function delete_meta_data( string $key ): void { unset( $this->meta[ $key ] ); }
		/** @return array<string,mixed> */
		public function all_meta(): array { return $this->meta; }
		public function save(): int { $this->props['saved'] = ( $this->props['saved'] ?? 0 ) + 1; return $this->get_id(); }
		public function save_count(): int { return (int) ( $this->props['saved'] ?? 0 ); }
		/** @param mixed $value */
		public function set_prop( string $key, $value ): void { $this->props[ $key ] = $value; }
	}
}

if ( ! class_exists( 'WC_Product_Simple' ) ) {
	class WC_Product_Simple extends WC_Product {
		public function __construct( array $props = [] ) { parent::__construct( array_merge( [ 'type' => 'simple' ], $props ) ); }
	}
}
if ( ! class_exists( 'WC_Product_External' ) ) {
	class WC_Product_External extends WC_Product {
		public function __construct( array $props = [] ) { parent::__construct( array_merge( [ 'type' => 'external' ], $props ) ); }
	}
}
if ( ! class_exists( 'WC_Product_Grouped' ) ) {
	class WC_Product_Grouped extends WC_Product {
		public function __construct( array $props = [] ) { parent::__construct( array_merge( [ 'type' => 'grouped' ], $props ) ); }
	}
}
if ( ! class_exists( 'WC_Product_Variable' ) ) {
	class WC_Product_Variable extends WC_Product {
		public function __construct( array $props = [] ) { parent::__construct( array_merge( [ 'type' => 'variable' ], $props ) ); }
	}
}
if ( ! class_exists( 'WC_Product_Variation' ) ) {
	class WC_Product_Variation extends WC_Product {
		public function __construct( array $props = [] ) { parent::__construct( array_merge( [ 'type' => 'variation' ], $props ) ); }
	}
}
