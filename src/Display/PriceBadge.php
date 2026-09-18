<?php
/**
 * The single badge renderer used by every hook, shortcode and the Store API.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Display;

use SidrenaCijena\Settings\Settings;

final class PriceBadge {

	public const TEMPLATE = 'price-badge.php';

	/** @var callable(string):string */
	private $locator;

	/**
	 * @param string                    $templateDir Plugin templates directory.
	 * @param callable(string):string|null $locator  Returns a theme override path for a template file ('' when none).
	 */
	public function __construct( private readonly Settings $settings, private readonly string $templateDir, ?callable $locator = null ) {
		$this->locator = $locator ?? static function ( string $file ): string {
			return function_exists( 'locate_template' ) ? (string) locate_template( [ 'sidrena-cijena/' . $file ] ) : '';
		};
	}

	public function render( BadgeData $data, string $context ): string {
		if ( ! $data->hasContent() ) {
			return '';
		}
		$override = ( $this->locator )( self::TEMPLATE );
		$file     = ( '' !== $override && is_readable( $override ) ) ? $override : rtrim( $this->templateDir, '/' ) . '/' . self::TEMPLATE;

		$html = $this->include(
			$file,
			[
				'badge'    => $data,
				'context'  => $context,
				'display'  => $this->settings->section( 'display' ),
				'compact'  => in_array( $context, BadgeContext::COMPACT, true ),
				'position' => (string) $this->settings->get( 'display.position', 'after' ),
				'renderer' => $this,
			]
		);
		/** @var string $html */
		$html = apply_filters( 'scwc_price_badge_html', $html, $data, $context );
		return $html;
	}

	/**
	 * Fill "{label} {date}: {price}" tokens. Text tokens are escaped; {price} is trusted HTML.
	 */
	public function formatReference( ReferenceView $ref, bool $compact ): string {
		$format = (string) $this->settings->get( $compact ? 'display.format_compact' : 'display.format', '{label} {date}: {price}' );
		$out    = strtr(
			esc_html( $format ),
			[
				'{label}' => esc_html( $ref->label ),
				'{date}'  => esc_html( $ref->dateFormatted ),
				'{key}'   => esc_html( $ref->key ),
				'{price}' => '<span class="scwc-ref-price__amount">' . $ref->amountHtml . '</span>',
			]
		);
		return trim( (string) preg_replace( '/\s{2,}/', ' ', $out ) );
	}

	/**
	 * @param array<string,mixed> $vars Template variables.
	 */
	private function include( string $file, array $vars ): string {
		$render = static function () use ( $file, $vars ): void {
			extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			include $file;
		};
		ob_start();
		try {
			$render();
		} finally {
			$out = (string) ob_get_clean();
		}
		return trim( $out );
	}
}
