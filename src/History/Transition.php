<?php
/**
 * What a recorded price change means.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\History;

final class Transition {
	public const NONE         = 'none';
	public const FIRST        = 'first';
	public const CHANGED      = 'changed';
	public const SALE_STARTED = 'sale_started';
	public const SALE_LOWERED = 'sale_lowered';
	public const SALE_ENDED   = 'sale_ended';
}
