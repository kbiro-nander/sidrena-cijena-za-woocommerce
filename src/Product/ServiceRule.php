<?php
/**
 * Decides whether a product is listed as a service (usluga) in the price list.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Product;

final class ServiceRule {

	public function __construct( private readonly string $mode = 'virtual_or_flag' ) {}

	public function isService( ProductSnapshot $p ): bool {
		$result = match ( $this->mode ) {
			'virtual'         => $p->isVirtual,
			'flag'            => true === $p->serviceFlag,
			'all'             => true,
			'none'            => false,
			default           => null !== $p->serviceFlag ? $p->serviceFlag : $p->isVirtual,
		};
		/** @var bool $result */
		$result = apply_filters( 'scwc_is_service', $result, $p );
		return $result;
	}
}
