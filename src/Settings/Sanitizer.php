<?php
/**
 * Pure sanitizer for the settings array (used as register_setting callback).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Settings;

use SidrenaCijena\Support\DateFormat;

final class Sanitizer {

	public const MIN_RETENTION_DAYS = 30;

	/**
	 * @param array<string,mixed> $raw Raw (form) values.
	 * @return array<string,mixed>
	 */
	public function sanitize( array $raw ): array {
		$defaults = Defaults::all();
		$out      = [];
		foreach ( $defaults as $section => $sectionDefaults ) {
			$rawSection      = isset( $raw[ $section ] ) && is_array( $raw[ $section ] ) ? $raw[ $section ] : null;
			$out[ $section ] = $this->sanitizeSection( $section, $sectionDefaults, $rawSection );
		}
		if ( '' === $out['price_list']['external_cron_key'] ) {
			$out['price_list']['external_cron_key'] = wp_generate_password( 32, false );
		}
		return $out;
	}

	/**
	 * @param array<string,mixed>      $defaults Section defaults.
	 * @param array<string,mixed>|null $raw      Raw section, null when the section was not submitted at all.
	 * @return array<string,mixed>
	 */
	private function sanitizeSection( string $section, array $defaults, ?array $raw ): array {
		$out = [];
		foreach ( $defaults as $key => $default ) {
			$path = "{$section}.{$key}";
			if ( 'reference_prices' === $section && is_array( $default ) && isset( $default['enabled'] ) ) {
				$rawType     = isset( $raw[ $key ] ) && is_array( $raw[ $key ] ) ? $raw[ $key ] : null;
				$out[ $key ] = $this->sanitizeReferenceType( $default, $rawType );
				continue;
			}
			if ( 'outlets.additional' === $path ) {
				$out[ $key ] = $this->outlets( $raw[ $key ] ?? [] );
				continue;
			}
			// A section that was submitted but lacks a boolean key = unchecked checkbox.
			$submitted   = null !== $raw && array_key_exists( $key, $raw );
			$value       = $submitted ? $raw[ $key ] : ( null !== $raw && is_bool( $default ) ? false : $default );
			$out[ $key ] = $this->sanitizeValue( $path, $value, $default );
		}
		return $out;
	}

	/**
	 * @param array<string,mixed>      $default Type defaults.
	 * @param array<string,mixed>|null $raw     Raw type values.
	 * @return array<string,mixed>
	 */
	private function sanitizeReferenceType( array $default, ?array $raw ): array {
		$out = $default;
		if ( null === $raw ) {
			return $out;
		}
		$out['enabled'] = $this->bool( $raw['enabled'] ?? false );
		$out['label']   = $this->text( $raw['label'] ?? $default['label'] ) ?: $default['label'];
		if ( array_key_exists( 'date', $raw ) ) {
			$date        = trim( (string) $raw['date'] );
			$out['date'] = ( '' === $date && '' === $default['date'] ) ? '' : ( DateFormat::parseIso( $date ) ? $date : $default['date'] );
		}
		$out['category_overrides'] = $this->categoryOverrides( $raw['category_overrides'] ?? [] );
		$out['auto_snapshot_at']   = $this->dateTime( (string) ( $raw['auto_snapshot_at'] ?? '' ) );
		$out['auto_snapshot_done'] = $this->text( $raw['auto_snapshot_done'] ?? $default['auto_snapshot_done'] );
		return $out;
	}

	/**
	 * @param mixed $rows Raw override rows.
	 * @return array<int,array{term_ids:int[],date:string}>
	 */
	private function categoryOverrides( $rows ): array {
		$out = [];
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$ids  = array_values( array_filter( array_map( 'intval', (array) ( $row['term_ids'] ?? [] ) ) ) );
			$date = trim( (string) ( $row['date'] ?? '' ) );
			if ( [] === $ids || null === DateFormat::parseIso( $date ) ) {
				continue;
			}
			$out[] = [
				'term_ids' => $ids,
				'date'     => $date,
			];
		}
		return $out;
	}

	/**
	 * Additional outlets (poslovnice): rows without address or label are dropped.
	 *
	 * @param mixed $rows Raw rows.
	 * @return array<int,array{form:string,address:string,label:string,storage_number:string}>
	 */
	private function outlets( $rows ): array {
		$out = [];
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$address = $this->text( $row['address'] ?? '' );
			$label   = $this->text( $row['label'] ?? '' );
			if ( '' === $address || '' === $label ) {
				continue;
			}
			$out[] = [
				'form'           => $this->text( $row['form'] ?? '' ) ?: 'poslovnica',
				'address'        => $address,
				'label'          => $label,
				'storage_number' => $this->text( $row['storage_number'] ?? '' ) ?: '1',
			];
		}
		return $out;
	}

	/**
	 * @param mixed $value   Raw value.
	 * @param mixed $default Default value.
	 * @return mixed
	 */
	private function sanitizeValue( string $path, $value, $default ) {
		$enums = Defaults::enums();
		switch ( $path ) {
			case 'price_list.retention_days':
				return max( self::MIN_RETENTION_DAYS, (int) $value );
			case 'history.retention_days':
				return max( 31, (int) $value );
			case 'price_list.debounce_seconds':
				return max( 0, (int) $value );
			case 'price_list.slug':
				$slug = sanitize_title( (string) $value );
				return '' === $slug ? $default : $slug;
			case 'price_list.generate_time':
			case 'history.sweep_time':
				return $this->time( (string) $value ) ?? $default;
			case 'price_list.formats':
				$formats = array_values( array_intersect( $enums[ $path ], array_map( 'strval', (array) $value ) ) );
				return [] === $formats ? $default : $formats;
			case 'outlet.storage_number':
				return $this->text( $value ) ?: $default;
		}
		if ( isset( $enums[ $path ] ) ) {
			return in_array( $value, $enums[ $path ], true ) ? $value : $default;
		}
		if ( is_bool( $default ) ) {
			return $this->bool( $value );
		}
		if ( is_int( $default ) ) {
			return (int) $value;
		}
		return $this->text( $value );
	}

	/**
	 * @param mixed $value Raw value.
	 */
	private function bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		return in_array( strtolower( trim( (string) $value ) ), [ '1', 'yes', 'on', 'true' ], true );
	}

	/**
	 * @param mixed $value Raw value.
	 */
	private function text( $value ): string {
		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	private function time( string $value ): ?string {
		if ( 1 !== preg_match( '/^(\d{1,2}):(\d{2})$/', trim( $value ), $m ) ) {
			return null;
		}
		$h = (int) $m[1];
		$i = (int) $m[2];
		if ( $h > 23 || $i > 59 ) {
			return null;
		}
		return sprintf( '%02d:%02d', $h, $i );
	}

	private function dateTime( string $value ): string {
		$value = trim( str_replace( 'T', ' ', $value ) );
		if ( 1 !== preg_match( '/^(\d{4}-\d{2}-\d{2}) (\d{1,2}:\d{2})$/', $value, $m ) ) {
			return '';
		}
		$time = $this->time( $m[2] );
		if ( null === $time || null === DateFormat::parseIso( $m[1] ) ) {
			return '';
		}
		return "{$m[1]} {$time}";
	}
}
