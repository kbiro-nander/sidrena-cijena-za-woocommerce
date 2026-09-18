<?php
/**
 * Default plugin settings.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Settings;

final class Defaults {

	public const ANCHOR_DATE = '2026-09-10';
	public const FMCG_DATE   = '2025-05-02';

	/**
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		return [
			'outlet'           => [
				'form'           => 'webshop',
				'address'        => '',
				'label'          => '',
				'storage_number' => '1',
				'merchant_name'  => '',
			],
			'outlets'          => [
				'additional' => [],
			],
			'reference_prices' => [
				'anchor'                   => self::referenceType( true, 'Cijena na dan', self::ANCHOR_DATE ),
				'base'                     => self::referenceType( false, 'Bazna cijena na dan', '' ),
				'variation_inherit_parent' => false,
				'auto_na_after_date'       => true,
			],
			'display'          => [
				'position'            => 'after',
				'format'              => '{label} {date}: {price}',
				'format_compact'      => '{date}: {price}',
				'date_format'         => 'j. n. Y.',
				'loop'                => true,
				'single'              => true,
				'cart'                => true,
				'checkout'            => 'unit',
				'mini_cart'           => false,
				'item_data'           => true,
				'variable_loop'       => 'range',
				'omnibus'             => true,
				'omnibus_label'       => 'Najniža cijena u 30 dana prije sniženja',
				'omnibus_replace_del' => true,
				'show_percent'        => true,
				'load_css'            => true,
			],
			'price_list'       => [
				'enabled'              => true,
				'formats'              => [ 'xml', 'csv' ],
				'slug'                 => 'cjenik',
				'generate_time'        => '06:00',
				'regenerate_on_change' => 'services',
				'debounce_seconds'     => 300,
				'retention_days'       => 35,
				'include_out_of_stock' => true,
				'include_hidden'       => false,
				'service_rule'         => 'virtual_or_flag',
				'sale_name_default'    => 'Sniženje',
				'csv_delimiter'        => ';',
				'csv_bom'              => true,
				'tax_mode'             => 'incl',
				'external_cron_key'    => '',
			],
			'history'          => [
				'enabled'          => true,
				'retention_days'   => 400,
				'sweep_time'       => '00:30',
				'omnibus_fallback' => 'regular',
			],
			'advanced'         => [
				'remove_data_on_uninstall' => false,
				'debug_log'                => false,
			],
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function referenceType( bool $enabled, string $label, string $date ): array {
		return [
			'enabled'            => $enabled,
			'label'              => $label,
			'date'               => $date,
			'category_overrides' => [],
			'auto_snapshot_at'   => '',
			'auto_snapshot_done' => '',
		];
	}

	/**
	 * Allowed values for enumerated settings.
	 *
	 * @return array<string,array<int,string>>
	 */
	public static function enums(): array {
		return [
			'display.position'                => [ 'after', 'below', 'before' ],
			'display.checkout'                => [ 'unit', 'total', 'none' ],
			'display.variable_loop'           => [ 'range', 'none' ],
			'price_list.regenerate_on_change' => [ 'services', 'all', 'never' ],
			'price_list.service_rule'         => [ 'virtual_or_flag', 'virtual', 'flag', 'all', 'none' ],
			'price_list.csv_delimiter'        => [ ';', ',', "\t" ],
			'price_list.tax_mode'             => [ 'incl', 'excl' ],
			'price_list.formats'              => [ 'xml', 'csv' ],
			'history.omnibus_fallback'        => [ 'regular', 'hide' ],
		];
	}
}
