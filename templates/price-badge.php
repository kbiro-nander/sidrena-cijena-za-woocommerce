<?php
/**
 * Price badge template. Override by copying to yourtheme/sidrena-cijena/price-badge.php.
 *
 * Available: $badge (BadgeData), $context (string), $display (array), $compact (bool),
 * $position (string), $renderer (PriceBadge).
 *
 * @package SidrenaCijena
 * @var \SidrenaCijena\Display\BadgeData  $badge
 * @var \SidrenaCijena\Display\PriceBadge $renderer
 * @var string                            $context
 * @var string                            $position
 * @var bool                              $compact
 * @var array<string,mixed>               $display
 */

defined( 'ABSPATH' ) || exit;
?>
<span class="scwc-badge scwc-badge--<?php echo esc_attr( $context ); ?> scwc-badge--<?php echo esc_attr( $position ); ?>">
<?php if ( $badge->omnibus ) : ?>
	<span class="scwc-lowest30"><?php echo esc_html( (string) ( $display['omnibus_label'] ?? '' ) ); ?>: <span class="scwc-lowest30__amount"><?php echo wp_kses_post( $badge->omnibus->amountHtml ); ?></span></span>
	<?php if ( ! empty( $display['show_percent'] ) && null !== $badge->omnibus->percent ) : ?>
	<span class="scwc-discount">&minus;<?php echo (int) $badge->omnibus->percent; ?>&nbsp;%</span>
	<?php endif; ?>
<?php endif; ?>
<?php foreach ( $badge->references as $ref ) : ?>
	<span class="scwc-ref-price scwc-ref-price--<?php echo esc_attr( $ref->key ); ?><?php echo $ref->isRange ? ' scwc-ref-price--range' : ''; ?>" data-scwc-ref="<?php echo esc_attr( $ref->key ); ?>" data-scwc-date="<?php echo esc_attr( $ref->dateIso ); ?>"><?php echo wp_kses_post( $renderer->formatReference( $ref, $compact ) ); ?></span>
<?php endforeach; ?>
</span>
