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
	 * @return array<string,mixed>
	 */
	public function data(): array {
		$base  = $this->baseUrl();
		$files = [];
		foreach ( $this->manifest->entries() as $e ) {
			if ( ! $this->storage->exists( (string) $e['name'] ) ) {
				continue;
			}
			$files[] = [
				'name'         => (string) $e['name'],
				'format'       => (string) $e['format'],
				'url'          => $this->fileUrl( (string) $e['name'] ),
				'generated_at' => (string) ( $e['generated_at'] ?? '' ),
				'size'         => (int) ( $e['size'] ?? 0 ),
				'products'     => (int) ( $e['products'] ?? 0 ),
				'services'     => (int) ( $e['services'] ?? 0 ),
				'sha256'       => (string) ( $e['sha256'] ?? '' ),
			];
		}
		$latest = [];
		foreach ( [ 'xml', 'csv' ] as $format ) {
			$entry = $this->manifest->latest( $format );
			if ( $entry && $this->storage->exists( (string) $entry['name'] ) ) {
				$latest[ $format ] = $base . 'latest.' . $format;
			}
		}
		$outlet = Outlet::fromSettings( $this->settings );
		return [
			'outlet' => [
				'form'           => $outlet->form,
				'address'        => $outlet->address,
				'label'          => $outlet->label,
				'storage_number' => $outlet->storageNumber,
				'merchant_name'  => $outlet->merchantName,
			],
			'latest' => $latest,
			'files'  => $files,
		];
	}

	public function json(): string {
		return (string) wp_json_encode( $this->data(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	public function html(): string {
		$data = $this->data();
		$file = rtrim( $this->templateDir, '/' ) . '/cjenik-index.php';
		ob_start();
		( static function () use ( $file, $data ): void {
			$outlet = $data['outlet'];
			$latest = $data['latest'];
			$files  = $data['files'];
			include $file;
		} )();
		return (string) ob_get_clean();
	}
}
