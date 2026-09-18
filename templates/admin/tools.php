<?php
/**
 * Tools page template: snapshot, CSV import, CSV export.
 *
 * @package SidrenaCijena
 * @var array<string,\SidrenaCijena\Reference\ReferencePriceType> $types    Enabled reference-price types.
 * @var \SidrenaCijena\Settings\Settings                          $settings Plugin settings.
 * @var string                                                    $template Sample CSV for download.
 */

defined( 'ABSPATH' ) || exit;

$scwc_first        = reset( $types );
$scwc_default_date = $scwc_first ? (string) $scwc_first->defaultDate : '';
$scwc_type_dates   = [];
foreach ( $types as $scwc_type ) {
	$scwc_type_dates[ $scwc_type->key ] = (string) $scwc_type->defaultDate;
}
$scwc_template_href = 'data:text/csv;charset=utf-8,' . rawurlencode( "\xEF\xBB\xBF" . $template );
?>
<div class="wrap scwc-tools">
	<h1><?php esc_html_e( 'Sidrena cijena – Alati', 'sidrena-cijena-za-woocommerce' ); ?></h1>

	<div class="card" style="max-width:900px">
		<h2><?php esc_html_e( 'Snimi trenutne redovne cijene kao referentnu cijenu', 'sidrena-cijena-za-woocommerce' ); ?></h2>
		<p>
			<?php esc_html_e( 'Sidrena cijena je REDOVNA cijena proizvoda na dan 10. 9. 2026. Alat kopira trenutnu redovnu cijenu svakog proizvoda i varijacije u odabranu vrstu referentne cijene. Akcijske (snižene) cijene se nikada ne kopiraju.', 'sidrena-cijena-za-woocommerce' ); ?>
		</p>
		<form id="scwc-snapshot-form" data-type-dates="<?php echo esc_attr( (string) wp_json_encode( $scwc_type_dates ) ); ?>">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="scwc-snapshot-type"><?php esc_html_e( 'Vrsta referentne cijene', 'sidrena-cijena-za-woocommerce' ); ?></label></th>
					<td>
						<select id="scwc-snapshot-type" name="type">
							<?php foreach ( $types as $scwc_type ) : ?>
								<option value="<?php echo esc_attr( $scwc_type->key ); ?>"><?php echo esc_html( $scwc_type->label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="scwc-snapshot-date"><?php esc_html_e( 'Datum referentne cijene', 'sidrena-cijena-za-woocommerce' ); ?></label></th>
					<td>
						<input type="date" id="scwc-snapshot-date" name="date" value="<?php echo esc_attr( $scwc_default_date ); ?>" />
						<p class="description"><?php esc_html_e( 'Ostavite prazno da se koristi zadani datum vrste (bez zapisa datuma po proizvodu).', 'sidrena-cijena-za-woocommerce' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Način', 'sidrena-cijena-za-woocommerce' ); ?></th>
					<td>
						<label><input type="radio" name="mode" value="only_missing" checked="checked" /> <?php esc_html_e( 'Samo nedostajuće (postojeće referentne cijene ostaju)', 'sidrena-cijena-za-woocommerce' ); ?></label><br />
						<label><input type="radio" name="mode" value="overwrite" /> <?php esc_html_e( 'Prepiši sve', 'sidrena-cijena-za-woocommerce' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Opcije', 'sidrena-cijena-za-woocommerce' ); ?></th>
					<td>
						<label><input type="checkbox" name="skip_on_sale" value="1" /> <?php esc_html_e( 'Preskoči proizvode koji su trenutno na akciji', 'sidrena-cijena-za-woocommerce' ); ?></label><br />
						<label><input type="checkbox" name="dry_run" value="1" /> <?php esc_html_e( 'Probno izvršavanje (samo prikaži što bi se dogodilo, ništa ne zapisuj)', 'sidrena-cijena-za-woocommerce' ); ?></label>
						<?php if ( (bool) $settings->get( 'reference_prices.auto_na_after_date', true ) ) : ?>
							<p class="description"><?php esc_html_e( 'Proizvodi kreirani nakon datuma referentne cijene bit će označeni kao „bez referentne cijene” (postavka „Automatski označi proizvode nakon datuma”).', 'sidrena-cijena-za-woocommerce' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary" id="scwc-snapshot-run"><?php esc_html_e( 'Pokreni', 'sidrena-cijena-za-woocommerce' ); ?></button>
			</p>
			<progress id="scwc-snapshot-progress" max="100" value="0" style="width:100%;display:none"></progress>
			<pre id="scwc-snapshot-log" class="scwc-log" style="max-height:220px;overflow:auto;background:#f6f7f7;padding:8px;display:none"></pre>
			<table class="widefat striped" id="scwc-snapshot-samples" style="display:none">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'sidrena-cijena-za-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Šifra', 'sidrena-cijena-za-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Naziv', 'sidrena-cijena-za-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Redovna cijena', 'sidrena-cijena-za-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Radnja', 'sidrena-cijena-za-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</form>
	</div>

	<div class="card" style="max-width:900px">
		<h2><?php esc_html_e( 'Uvoz iz CSV-a', 'sidrena-cijena-za-woocommerce' ); ?></h2>
		<p>
			<?php esc_html_e( 'Stupci: sku (šifra), cijena, datum (GGGG-MM-DD, neobavezno), tip (neobavezno), na (1 = bez referentne cijene). Decimalni zarez je dopušten. Razdjelnik se prepoznaje automatski.', 'sidrena-cijena-za-woocommerce' ); ?>
			<a href="<?php echo esc_url( $scwc_template_href, [ 'data' ] ); ?>" download="sidrene-cijene-predlozak.csv"><?php esc_html_e( 'Preuzmi predložak', 'sidrena-cijena-za-woocommerce' ); ?></a>
		</p>
		<form id="scwc-import-form" enctype="multipart/form-data">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="scwc-import-file"><?php esc_html_e( 'CSV datoteka', 'sidrena-cijena-za-woocommerce' ); ?></label></th>
					<td><input type="file" id="scwc-import-file" name="file" accept=".csv,text/csv,text/plain" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="scwc-import-type"><?php esc_html_e( 'Zadana vrsta referentne cijene', 'sidrena-cijena-za-woocommerce' ); ?></label></th>
					<td>
						<select id="scwc-import-type" name="type">
							<?php foreach ( $types as $scwc_type ) : ?>
								<option value="<?php echo esc_attr( $scwc_type->key ); ?>"><?php echo esc_html( $scwc_type->label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Koristi se kada datoteka nema stupac „tip”.', 'sidrena-cijena-za-woocommerce' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="scwc-import-delimiter"><?php esc_html_e( 'Razdjelnik', 'sidrena-cijena-za-woocommerce' ); ?></label></th>
					<td>
						<select id="scwc-import-delimiter" name="delimiter">
							<option value="auto"><?php esc_html_e( 'Automatski', 'sidrena-cijena-za-woocommerce' ); ?></option>
							<option value=";">;</option>
							<option value=",">,</option>
							<option value="tab"><?php esc_html_e( 'Tabulator', 'sidrena-cijena-za-woocommerce' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" class="button" id="scwc-import-preview"><?php esc_html_e( 'Pregled', 'sidrena-cijena-za-woocommerce' ); ?></button>
				<button type="button" class="button button-primary" id="scwc-import-apply" disabled="disabled"><?php esc_html_e( 'Primijeni uvoz', 'sidrena-cijena-za-woocommerce' ); ?></button>
			</p>
			<input type="hidden" id="scwc-import-token" value="" />
			<p id="scwc-import-summary" style="display:none"></p>
			<div id="scwc-import-errors" class="notice notice-error inline" style="display:none"><ul></ul></div>
			<table class="widefat striped" id="scwc-import-rows" style="display:none">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Redak', 'sidrena-cijena-za-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Šifra', 'sidrena-cijena-za-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Cijena', 'sidrena-cijena-za-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Datum', 'sidrena-cijena-za-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Tip', 'sidrena-cijena-za-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Bez cijene', 'sidrena-cijena-za-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Status', 'sidrena-cijena-za-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
			<p id="scwc-import-result" style="display:none"></p>
		</form>
	</div>

	<div class="card" style="max-width:900px">
		<h2><?php esc_html_e( 'Izvoz referentnih cijena', 'sidrena-cijena-za-woocommerce' ); ?></h2>
		<p><?php esc_html_e( 'CSV s jednim retkom po proizvodu i vrsti referentne cijene (sku, naziv, vrsta_proizvoda, redovna_cijena, tip, cijena, datum, izvor, na). Datoteka se može urediti i ponovno uvesti.', 'sidrena-cijena-za-woocommerce' ); ?></p>
		<form id="scwc-export-form">
			<p>
				<label><input type="checkbox" name="only_missing" value="1" /> <?php esc_html_e( 'Samo proizvodi bez referentne cijene', 'sidrena-cijena-za-woocommerce' ); ?></label>
			</p>
			<p class="submit">
				<button type="submit" class="button button-primary" id="scwc-export-run"><?php esc_html_e( 'Izvezi CSV', 'sidrena-cijena-za-woocommerce' ); ?></button>
			</p>
			<p id="scwc-export-status" style="display:none"></p>
		</form>
	</div>
</div>
