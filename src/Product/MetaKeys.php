<?php
/**
 * Product/variation meta keys used by the plugin.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Product;

final class MetaKeys {
	public const IS_SERVICE        = '_scwc_is_service';
	public const UNIT              = '_scwc_unit';
	public const UNIT_QUANTITY     = '_scwc_unit_quantity';
	public const SALE_NAME         = '_scwc_sale_name';
	public const EXCLUDE           = '_scwc_exclude_from_price_list';
	public const PRICE_ON_REQUEST  = '_scwc_price_on_request';
	public const OMNIBUS_REF_PRICE = '_scwc_omnibus_ref_price';
	public const OMNIBUS_SALE_START = '_scwc_omnibus_sale_start';
	public const OMNIBUS_SOURCE    = '_scwc_omnibus_ref_source';
	public const REF_PREFIX        = '_scwc_ref_';

	/** Keys a variation inherits from its parent when it has no own value. */
	public const INHERITED = [ self::IS_SERVICE, self::UNIT, self::UNIT_QUANTITY, self::SALE_NAME, self::EXCLUDE, self::PRICE_ON_REQUEST ];

	/** Fallback barcode meta keys from popular plugins (filterable). */
	public const BARCODE_FALLBACKS = [ '_ean', '_gtin', '_barcode', '_alg_ean', '_wpm_gtin_code', 'hwp_product_gtin' ];
}
