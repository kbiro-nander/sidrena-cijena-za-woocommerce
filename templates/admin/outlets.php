<?php
/**
 * Additional outlets (poslovnice) repeater on the settings page.
 *
 * @package SidrenaCijena
 * @var string           $name Base input name, e.g. scwc_settings[outlets][additional].
 * @var array<int,mixed> $rows Existing rows [{form, address, label, storage_number}].
 */

defined( 'ABSPATH' ) || exit;

$scwc_outlet_row = static function ( string $name, string $index, array $row ): void {
	$fields = [
		'form'           => [ __( 'Oblik (npr. poslovnica, prodavaonica, kiosk)', 'sidrena-cijena-za-woocommerce' ), 'poslovnica' ],
		'address'        => [ __( 'Adresa', 'sidrena-cijena-za-woocommerce' ), '' ],
		'label'          => [ __( 'Oznaka prodajnog objekta', 'sidrena-cijena-za-woocommerce' ), '' ],
		'storage_number' => [ __( 'Broj pohrane', 'sidrena-cijena-za-woocommerce' ), '1' ],
	];
	?>
	<div class="scwc-outlets__row" data-index="<?php echo esc_attr( $index ); ?>">
		<?php foreach ( $fields as $key => [ $label, $default ] ) : ?>
			<label class="scwc-outlets__field">
				<span><?php echo esc_html( $label ); ?></span>
				<input type="text" name="<?php echo esc_attr( "{$name}[{$index}][{$key}]" ); ?>" value="<?php echo esc_attr( (string) ( $row[ $key ] ?? $default ) ); ?>" class="regular-text" />
			</label>
		<?php endforeach; ?>
		<button type="button" class="button-link-delete scwc-outlets__remove"><?php esc_html_e( 'Ukloni', 'sidrena-cijena-za-woocommerce' ); ?></button>
	</div>
	<?php
};
?>
<div class="scwc-outlets" data-name="<?php echo esc_attr( $name ); ?>">
	<div class="scwc-outlets__rows">
		<?php
		$scwc_i = 0;
		foreach ( $rows as $scwc_outlet ) {
			if ( ! is_array( $scwc_outlet ) ) {
				continue;
			}
			$scwc_outlet_row( $name, (string) $scwc_i, $scwc_outlet );
			++$scwc_i;
		}
		?>
	</div>
	<p><button type="button" class="button scwc-outlets__add"><?php esc_html_e( 'Dodaj prodajni objekt', 'sidrena-cijena-za-woocommerce' ); ?></button></p>
	<template class="scwc-outlets__template">
		<?php $scwc_outlet_row( $name, '__INDEX__', [] ); ?>
	</template>
</div>
