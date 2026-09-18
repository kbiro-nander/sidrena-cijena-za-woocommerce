<?php
/**
 * Category-override repeater for one reference-price type (settings page).
 *
 * @package SidrenaCijena
 * @var string                                $name       Base input name, e.g. scwc_settings[reference_prices][anchor][category_overrides].
 * @var array<int,mixed>                      $rows       Existing rows [{term_ids:int[], date:string}].
 * @var array<int,array{id:int,name:string}>  $categories Product categories.
 * @var string                                $fmcgDate   FMCG helper date (Y-m-d).
 */

defined( 'ABSPATH' ) || exit;

$scwc_row = static function ( string $name, string $index, array $termIds, string $date, array $categories ): void {
	?>
	<div class="scwc-overrides__row" data-index="<?php echo esc_attr( $index ); ?>">
		<select multiple name="<?php echo esc_attr( "{$name}[{$index}][term_ids][]" ); ?>" class="scwc-overrides__cats" size="4">
			<?php foreach ( $categories as $cat ) : ?>
				<option value="<?php echo esc_attr( (string) $cat['id'] ); ?>"<?php echo in_array( (int) $cat['id'], $termIds, true ) ? ' selected="selected"' : ''; ?>><?php echo esc_html( $cat['name'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="date" name="<?php echo esc_attr( "{$name}[{$index}][date]" ); ?>" value="<?php echo esc_attr( $date ); ?>" class="scwc-overrides__date" />
		<button type="button" class="button-link-delete scwc-overrides__remove"><?php esc_html_e( 'Ukloni', 'sidrena-cijena-za-woocommerce' ); ?></button>
	</div>
	<?php
};
?>
<div class="scwc-overrides" data-name="<?php echo esc_attr( $name ); ?>" data-fmcg-date="<?php echo esc_attr( $fmcgDate ); ?>">
	<div class="scwc-overrides__rows">
		<?php
		$scwc_i = 0;
		foreach ( $rows as $scwc_override ) {
			if ( ! is_array( $scwc_override ) ) {
				continue;
			}
			$scwc_row( $name, (string) $scwc_i, array_map( 'intval', (array) ( $scwc_override['term_ids'] ?? [] ) ), (string) ( $scwc_override['date'] ?? '' ), $categories );
			++$scwc_i;
		}
		?>
	</div>
	<?php if ( [] === $categories ) : ?>
		<p class="description"><?php esc_html_e( 'Nema kategorija proizvoda.', 'sidrena-cijena-za-woocommerce' ); ?></p>
	<?php endif; ?>
	<p>
		<button type="button" class="button scwc-overrides__add"><?php esc_html_e( 'Dodaj kategoriju/e', 'sidrena-cijena-za-woocommerce' ); ?></button>
		<button type="button" class="button scwc-overrides__add-fmcg"><?php esc_html_e( 'Dodaj FMCG kategorije (2. 5. 2025.)', 'sidrena-cijena-za-woocommerce' ); ?></button>
	</p>
	<p class="description"><?php esc_html_e( 'FMCG: trgovci koji su sidrenu cijenu za šest kategorija (npr. mlijeko, kruh, jaja) već prikazivali od 2. 5. 2025. zadržavaju taj datum.', 'sidrena-cijena-za-woocommerce' ); ?></p>
	<template class="scwc-overrides__template">
		<?php $scwc_row( $name, '__INDEX__', [], '', $categories ); ?>
	</template>
</div>
