<?php
/**
 * Where a badge is being rendered.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Display;

final class BadgeContext {
	public const LOOP           = 'loop';
	public const SINGLE         = 'single';
	public const VARIATION_JSON = 'variation_json';
	public const CART           = 'cart';
	public const MINI_CART      = 'mini_cart';
	public const CHECKOUT       = 'checkout';
	public const SHORTCODE      = 'shortcode';
	public const STORE_API      = 'store_api';

	/** Contexts where cart tax display mode applies. */
	public const CART_LIKE = [ self::CART, self::MINI_CART, self::CHECKOUT ];

	/** Contexts using the compact label format. */
	public const COMPACT = [ self::CART, self::MINI_CART, self::CHECKOUT ];
}
