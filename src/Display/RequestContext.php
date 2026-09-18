<?php
/**
 * Facts about the current request relevant to rendering decisions.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Display;

final class RequestContext {

	public function __construct(
		private readonly ?string $restRoute,
		private readonly bool $isAdmin,
		private readonly bool $doingAjax,
	) {}

	public static function fromGlobals(): self {
		$route = null;
		$uri   = (string) ( $_SERVER['REQUEST_URI'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( preg_match( '#/wp-json(/.*?)(?:\?|$)#', $uri, $m ) ) {
			$route = $m[1];
		} elseif ( preg_match( '#[?&]rest_route=([^&]+)#', $uri, $m ) ) {
			$route = rawurldecode( $m[1] );
		} elseif ( defined( 'REST_REQUEST' ) && constant( 'REST_REQUEST' ) && isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			$route = (string) $GLOBALS['wp']->query_vars['rest_route'];
		}
		return new self( $route, is_admin(), wp_doing_ajax() );
	}

	public function isRest(): bool {
		return null !== $this->restRoute;
	}

	/** wc/v1..v3 REST API (integrations) – keep price_html clean there. */
	public function isWcRest(): bool {
		return null !== $this->restRoute && 1 === preg_match( '#^/wc/v\d#', $this->restRoute );
	}

	/** Store API (blocks). */
	public function isStoreApi(): bool {
		return null !== $this->restRoute && str_starts_with( $this->restRoute, '/wc/store' );
	}

	public function isAdminScreen(): bool {
		return $this->isAdmin && ! $this->doingAjax;
	}

	/** True while a WooCommerce email is being rendered. */
	public function isEmail(): bool {
		return did_action( 'woocommerce_email_header' ) > did_action( 'woocommerce_email_footer' );
	}
}
