<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Admin;

use SidrenaCijena\Admin\FieldRenderer;
use SidrenaCijena\Tests\TestCase;

final class FieldRendererTest extends TestCase {
	private function renderer( array $categories = [] ): FieldRenderer {
		return new FieldRenderer( dirname( __DIR__, 3 ) . '/templates', $categories );
	}

	public function test_text_row_has_label_name_id_and_escaped_value(): void {
		$html = $this->renderer()->render( [ 'path' => 'outlet.address', 'type' => 'text', 'label' => 'Adresa', 'description' => 'Ulica & broj', 'placeholder' => 'Ilica 1' ], 'Trg <b>1</b>' );
		self::assertStringStartsWith( '<tr', $html );
		self::assertStringContainsString( '<th scope="row"><label for="scwc_settings_outlet_address">Adresa</label></th>', $html );
		self::assertStringContainsString( 'type="text"', $html );
		self::assertStringContainsString( 'id="scwc_settings_outlet_address"', $html );
		self::assertStringContainsString( 'name="scwc_settings[outlet][address]"', $html );
		self::assertStringContainsString( 'value="Trg &lt;b&gt;1&lt;/b&gt;"', $html );
		self::assertStringContainsString( 'placeholder="Ilica 1"', $html );
		self::assertStringContainsString( '<p class="description">Ulica &amp; broj</p>', $html );
		self::assertStringEndsWith( '</tr>', $html );
	}

	public function test_number_time_date_types_and_min(): void {
		$html = $this->renderer()->render( [ 'path' => 'price_list.retention_days', 'type' => 'number', 'label' => 'Dani', 'min' => 30 ], 35 );
		self::assertStringContainsString( 'type="number"', $html );
		self::assertStringContainsString( 'min="30"', $html );
		self::assertStringContainsString( 'value="35"', $html );
		self::assertStringContainsString( 'type="time"', $this->renderer()->render( [ 'path' => 'history.sweep_time', 'type' => 'time', 'label' => 'x' ], '00:30' ) );
		self::assertStringContainsString( 'type="date"', $this->renderer()->render( [ 'path' => 'reference_prices.anchor.date', 'type' => 'date', 'label' => 'x' ], '2026-09-10' ) );
	}

	public function test_datetime_local_converts_stored_space_to_t(): void {
		$html = $this->renderer()->render( [ 'path' => 'reference_prices.base.auto_snapshot_at', 'type' => 'datetime-local', 'label' => 'x' ], '2026-11-17 00:05' );
		self::assertStringContainsString( 'type="datetime-local"', $html );
		self::assertStringContainsString( 'value="2026-11-17T00:05"', $html );
	}

	public function test_checkbox_checked_state_and_value_one(): void {
		$on = $this->renderer()->render( [ 'path' => 'display.loop', 'type' => 'checkbox', 'label' => 'U popisu', 'description' => 'Prikaži' ], true );
		self::assertStringContainsString( 'type="checkbox"', $on );
		self::assertStringContainsString( 'name="scwc_settings[display][loop]"', $on );
		self::assertStringContainsString( 'value="1"', $on );
		self::assertMatchesRegularExpression( '/<input[^>]*checked/', $on );
		$off = $this->renderer()->render( [ 'path' => 'display.loop', 'type' => 'checkbox', 'label' => 'U popisu' ], false );
		self::assertDoesNotMatchRegularExpression( '/<input[^>]*checked/', $off );
	}

	public function test_select_marks_current_option(): void {
		$html = $this->renderer()->render( [ 'path' => 'display.position', 'type' => 'select', 'label' => 'Pozicija', 'options' => [ 'after' => 'Iza', 'below' => 'Ispod' ] ], 'below' );
		self::assertStringContainsString( '<select id="scwc_settings_display_position" name="scwc_settings[display][position]">', $html );
		self::assertStringContainsString( '<option value="after">Iza</option>', $html );
		self::assertStringContainsString( '<option value="below" selected="selected">Ispod</option>', $html );
	}

	public function test_multicheck_uses_array_name_and_checks_present_values(): void {
		$html = $this->renderer()->render( [ 'path' => 'price_list.formats', 'type' => 'multicheck', 'label' => 'Formati', 'options' => [ 'xml' => 'XML', 'csv' => 'CSV' ] ], [ 'csv' ] );
		self::assertStringContainsString( 'name="scwc_settings[price_list][formats][]" value="xml"', $html );
		self::assertMatchesRegularExpression( '/name="scwc_settings\[price_list\]\[formats\]\[\]" value="csv" checked/', $html );
		self::assertDoesNotMatchRegularExpression( '/value="xml" checked/', $html );
	}

	public function test_textarea_escapes_content(): void {
		$html = $this->renderer()->render( [ 'path' => 'display.format', 'type' => 'textarea', 'label' => 'Format' ], '{label} <{date}>' );
		self::assertStringContainsString( '<textarea id="scwc_settings_display_format" name="scwc_settings[display][format]"', $html );
		self::assertStringContainsString( '{label} &lt;{date}&gt;</textarea>', $html );
	}

	public function test_hidden_renders_only_input_and_readonly_shows_value_with_hidden_carrier(): void {
		$hidden = $this->renderer()->render( [ 'path' => 'reference_prices.anchor.enabled', 'type' => 'hidden', 'label' => 'x' ], true );
		self::assertSame( '<input type="hidden" name="scwc_settings[reference_prices][anchor][enabled]" value="1" />', $hidden );
		$ro = $this->renderer()->render( [ 'path' => 'reference_prices.anchor.auto_snapshot_done', 'type' => 'readonly', 'label' => 'Obavljeno' ], '2026-09-10 00:05' );
		self::assertStringContainsString( '<code>2026-09-10 00:05</code>', $ro );
		self::assertStringContainsString( '<input type="hidden" name="scwc_settings[reference_prices][anchor][auto_snapshot_done]" value="2026-09-10 00:05" />', $ro );
		self::assertStringContainsString( '&mdash;', $this->renderer()->render( [ 'path' => 'reference_prices.anchor.auto_snapshot_done', 'type' => 'readonly', 'label' => 'Obavljeno' ], '' ) );
	}

	public function test_category_overrides_repeater_renders_rows_select_and_buttons(): void {
		$cats = [ [ 'id' => 12, 'name' => 'Mlijeko' ], [ 'id' => 15, 'name' => 'Kruh & pecivo' ] ];
		$html = $this->renderer( $cats )->render(
			[ 'path' => 'reference_prices.anchor.category_overrides', 'type' => 'category_overrides', 'label' => 'Iznimke' ],
			[ [ 'term_ids' => [ 15 ], 'date' => '2025-05-02' ] ]
		);
		self::assertStringContainsString( 'scwc-overrides', $html );
		self::assertStringContainsString( 'data-name="scwc_settings[reference_prices][anchor][category_overrides]"', $html );
		self::assertStringContainsString( '<select multiple name="scwc_settings[reference_prices][anchor][category_overrides][0][term_ids][]"', $html );
		self::assertStringContainsString( '<option value="12">Mlijeko</option>', $html );
		self::assertStringContainsString( '<option value="15" selected="selected">Kruh &amp; pecivo</option>', $html );
		self::assertStringContainsString( 'type="date" name="scwc_settings[reference_prices][anchor][category_overrides][0][date]" value="2025-05-02"', $html );
		self::assertStringContainsString( 'Dodaj kategoriju/e', $html );
		self::assertStringContainsString( 'Dodaj FMCG kategorije (2. 5. 2025.)', $html );
		self::assertStringContainsString( 'data-fmcg-date="2025-05-02"', $html );
		self::assertStringContainsString( '<template', $html, 'blank row template for JS' );
		self::assertStringContainsString( '__INDEX__', $html );
	}

	public function test_outlets_repeater_renders_rows_and_blank_template(): void {
		$html = $this->renderer( [] )->render(
			[ 'path' => 'outlets.additional', 'type' => 'outlets', 'label' => 'Dodatni prodajni objekti' ],
			[ [ 'form' => 'poslovnica', 'address' => 'Vukovarska 5', 'label' => 'ZG-02', 'storage_number' => '3' ] ]
		);
		self::assertStringContainsString( 'scwc-outlets', $html );
		self::assertStringContainsString( 'data-name="scwc_settings[outlets][additional]"', $html );
		self::assertStringContainsString( 'name="scwc_settings[outlets][additional][0][form]" value="poslovnica"', $html );
		self::assertStringContainsString( 'name="scwc_settings[outlets][additional][0][address]" value="Vukovarska 5"', $html );
		self::assertStringContainsString( 'name="scwc_settings[outlets][additional][0][label]" value="ZG-02"', $html );
		self::assertStringContainsString( 'name="scwc_settings[outlets][additional][0][storage_number]" value="3"', $html );
		self::assertStringContainsString( 'Dodaj prodajni objekt', $html );
		self::assertStringContainsString( '<template', $html );
		self::assertStringContainsString( '__INDEX__', $html );
	}
}
