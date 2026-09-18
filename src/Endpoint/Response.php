<?php
/**
 * Result of resolving an endpoint request (headers already emitted).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Endpoint;

final class Response {

	public function __construct(
		public readonly int $status,
		public readonly ?string $file = null,
		public readonly ?string $body = null,
	) {}
}
