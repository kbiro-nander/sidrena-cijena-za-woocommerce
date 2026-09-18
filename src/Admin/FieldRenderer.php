<?php
/**
 * Renders one settings field definition as a table row.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Admin;

use SidrenaCijena\Settings\Defaults;

/**
 * @phpstan-import-type FieldDef from Fields
 */
final class FieldRenderer {

	public const OVERRIDES_TEMPLATE = 'admin/category-overrides.php';

	/**
	 * @param array<int,array{id:int,name:string}> $categories Product categories for the overrides repeater.
	 */
	public function __construct( private readonly string $templateDir, private readonly array $categories = [] ) {}

	/**
	 * @param FieldDef $def   Field definition.
	 * @param mixed    $value Current value.
	 */
	public function render( array $def, $value ): string {
		$name = Fields::inputName( $def['path'] );
		$id   = Fields::inputId( $def['path'] );
		$type = $def['type'];

		if ( 'hidden' === $type ) {
			return $this->hidden( $name, $this->scalar( $value ) );
		}
		$control = match ( $type ) {
			'checkbox'           => $this->checkbox( $name, $id, (bool) $value, $def['description'] ?? '' ),
			'select'             => $this->select( $name, $id, $this->scalar( $value ), $def['options'] ?? [] ),
			'multicheck'         => $this->multicheck( $name, (array) $value, $def['options'] ?? [] ),
			'textarea'           => '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="2" class="large-text code">' . esc_textarea( $this->scalar( $value ) ) . '</textarea>',
			'readonly'           => $this->readonly( $name, $this->scalar( $value ) ),
			'category_overrides' => $this->overrides( $name, is_array( $value ) ? $value : [] ),
			default              => $this->input( $type, $name, $id, $this->scalar( $value ), $def ),
		};
		if ( 'checkbox' !== $type && '' !== ( $def['description'] ?? '' ) ) {
			$control .= '<p class="description">' . esc_html( $def['description'] ) . '</p>';
		}
		$label = 'checkbox' === $type
			? '<th scope="row">' . esc_html( $def['label'] ) . '</th>'
			: '<th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $def['label'] ) . '</label></th>';
		return '<tr class="scwc-field scwc-field--' . esc_attr( $type ) . '">' . $label . '<td>' . $control . '</td></tr>';
	}

	/** Section heading row. */
	public function heading( string $text ): string {
		return '<tr class="scwc-heading"><th colspan="2"><h3>' . esc_html( $text ) . '</h3></th></tr>';
	}

	public function hidden( string $name, string $value ): string {
		return '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
	}

	/**
	 * @param FieldDef $def Field definition.
	 */
	private function input( string $type, string $name, string $id, string $value, array $def ): string {
		if ( 'datetime-local' === $type ) {
			$value = str_replace( ' ', 'T', $value );
		}
		$attrs = ' type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"';
		if ( isset( $def['min'] ) ) {
			$attrs .= ' min="' . (int) $def['min'] . '"';
		}
		if ( '' !== ( $def['placeholder'] ?? '' ) ) {
			$attrs .= ' placeholder="' . esc_attr( $def['placeholder'] ) . '"';
		}
		$class = in_array( $type, [ 'text' ], true ) ? ' class="regular-text"' : ' class="small-text"';
		return '<input' . $attrs . $class . ' />';
	}

	private function checkbox( string $name, string $id, bool $checked, string $description ): string {
		return '<label for="' . esc_attr( $id ) . '"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . ( $checked ? ' checked="checked"' : '' ) . ' /> '
			. esc_html( $description ) . '</label>';
	}

	/**
	 * @param array<string,string> $options Value => label.
	 */
	private function select( string $name, string $id, string $value, array $options ): string {
		$html = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $options as $key => $label ) {
			$html .= '<option value="' . esc_attr( (string) $key ) . '"' . ( (string) $key === $value ? ' selected="selected"' : '' ) . '>' . esc_html( $label ) . '</option>';
		}
		return $html . '</select>';
	}

	/**
	 * @param array<int,mixed>     $values  Selected values.
	 * @param array<string,string> $options Value => label.
	 */
	private function multicheck( string $name, array $values, array $options ): string {
		$values = array_map( 'strval', $values );
		$html   = '<fieldset>';
		foreach ( $options as $key => $label ) {
			$html .= '<label class="scwc-multicheck"><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( (string) $key ) . '"'
				. ( in_array( (string) $key, $values, true ) ? ' checked="checked"' : '' ) . ' /> ' . esc_html( $label ) . '</label> ';
		}
		return $html . '</fieldset>';
	}

	private function readonly( string $name, string $value ): string {
		$shown = '' === $value ? '&mdash;' : '<code>' . esc_html( $value ) . '</code>';
		return $shown . $this->hidden( $name, $value );
	}

	/**
	 * @param array<int,mixed> $rows Override rows.
	 */
	private function overrides( string $name, array $rows ): string {
		$categories = $this->categories;
		$fmcgDate   = Defaults::FMCG_DATE;
		$file       = rtrim( $this->templateDir, '/' ) . '/' . self::OVERRIDES_TEMPLATE;
		ob_start();
		try {
			include $file;
		} finally {
			$out = (string) ob_get_clean();
		}
		return trim( $out );
	}

	/**
	 * @param mixed $value Any value.
	 */
	private function scalar( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}
		return is_scalar( $value ) ? (string) $value : '';
	}
}
