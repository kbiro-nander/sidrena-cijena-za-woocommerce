<?php
/**
 * Renders the /cjenik/ HTML index and index.json.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Endpoint;

use SidrenaCijena\PriceList\Manifest;
use SidrenaCijena\PriceList\Outlet;
use SidrenaCijena\PriceList\Outlets;
use SidrenaCijena\PriceList\Storage;
use SidrenaCijena\Settings\Settings;

final class IndexRenderer {

	public function __construct(
		private readonly Settings $settings,
		private readonly Storage $storage,
		private readonly Manifest $manifest,
		private readonly string $templateDir,
	) {}

	public function baseUrl(): string {
		return home_url( '/' . trim( (string) $this->settings->get( 'price_list.slug', 'cjenik' ), '/' ) . '/' );
	}

	public function fileUrl( string $name ): string {
		return $this->baseUrl() . rawurlencode( $name );
	}

	/**
	 * @param Outlet|null $only Limit to one outlet (per-outlet page); null = all outlets.
	 * @return array<string,mixed>
	 */
	public function data( ?Outlet $only = null ): array {
		$base    = $this->baseUrl();
		$outlets = Outlets::fromSettings( $this->settings );
		$primary = (string) ( Outlets::primary( $outlets )->key ?? '' );
		$list    = [];
		foreach ( $outlets as $outlet ) {
			if ( $only && $only->key !== $outlet->key ) {
				continue;
			}
			$files = [];
			foreach ( $this->manifest->entriesFor( $outlet->key, $primary ) as $e ) {
				if ( ! $this->storage->exists( (string) $e['name'] ) ) {
					continue;
				}
				$files[] = [
					'name'         => (string) $e['name'],
					'format'       => (string) $e['format'],
					'url'          => $this->fileUrl( (string) $e['name'] ),
					'direct_url'   => $this->storage->url( (string) $e['name'] ),
					'generated_at' => (string) ( $e['generated_at'] ?? '' ),
					'size'         => (int) ( $e['size'] ?? 0 ),
					'products'     => (int) ( $e['products'] ?? 0 ),
					'services'     => (int) ( $e['services'] ?? 0 ),
					'sha256'       => (string) ( $e['sha256'] ?? '' ),
				];
			}
			$latest = [];
			foreach ( [ 'xml', 'csv' ] as $format ) {
				$entry = $this->manifest->latest( $format, $outlet->key, $primary );
				if ( $entry && $this->storage->exists( (string) $entry['name'] ) ) {
					$latest[ $format ] = $base . ( $outlet->isPrimary() ? '' : $outlet->key . '/' ) . 'latest.' . $format;
				}
			}
			$list[] = [
				'key'            => $outlet->key,
				'primary'        => $outlet->isPrimary(),
				'form'           => $outlet->form,
				'address'        => $outlet->address,
				'label'          => $outlet->label,
				'storage_number' => $outlet->storageNumber,
				'merchant_name'  => $outlet->merchantName,
				'url'            => $base . ( $outlet->isPrimary() ? '' : $outlet->key . '/' ),
				'latest'         => $latest,
				'files'          => $files,
			];
		}
		$first = $list[0] ?? [
			'form'           => '',
			'address'        => '',
			'label'          => '',
			'storage_number' => '',
			'merchant_name'  => '',
			'latest'         => [],
			'files'          => [],
		];
		return [
			'base_url' => $base,
			'outlet'   => [
				'form'           => $first['form'],
				'address'        => $first['address'],
				'label'          => $first['label'],
				'storage_number' => $first['storage_number'],
				'merchant_name'  => $first['merchant_name'],
			],
			'latest'   => $first['latest'],
			'files'    => $first['files'],
			'outlets'  => $list,
		];
	}

	public function json( ?Outlet $only = null ): string {
		return (string) wp_json_encode( $this->data( $only ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	public function html( ?Outlet $only = null ): string {
		$data = $this->data( $only );
		$file = rtrim( $this->templateDir, '/' ) . '/cjenik-index.php';
		ob_start();
		( static function () use ( $file, $data ): void {
			$outlet   = $data['outlet'];
			$latest   = $data['latest'];
			$files    = $data['files'];
			$base_url = $data['base_url'];
			$outlets  = $data['outlets'];
			include $file;
		} )();
		return (string) ob_get_clean();
	}
}
