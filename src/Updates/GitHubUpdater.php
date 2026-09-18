<?php
/**
 * Plugin updates from GitHub Releases: WordPress shows "Update now" when a newer `v*` release exists.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Updates;

final class GitHubUpdater {

	public const TRANSIENT    = 'scwc_github_release';
	public const CACHE_OK     = 12 * HOUR_IN_SECONDS;
	public const CACHE_FAIL   = HOUR_IN_SECONDS;
	public const REQUIRES_WP  = '6.4';
	public const REQUIRES_PHP = '8.1';
	public const TESTED_WP    = '6.8';

	private string $slug;

	public function __construct(
		private readonly string $repo,
		private readonly string $basename,
		private readonly string $currentVersion,
	) {
		$this->slug = dirname( $basename );
	}

	public function register(): void {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'checkUpdate' ] );
		add_filter( 'plugins_api', [ $this, 'pluginInformation' ], 10, 3 );
		add_filter( 'upgrader_source_selection', [ $this, 'renameSource' ], 10, 4 );
	}

	/**
	 * Latest release (cached). Null when unavailable (no release yet, private repo, network error).
	 *
	 * @return array{version:string,package:string,url:string,notes:string,published:string}|null
	 */
	public function latest(): ?array {
		$cached = get_site_transient( self::TRANSIENT );
		if ( is_array( $cached ) ) {
			return [] === $cached ? null : $cached;
		}
		$release = $this->fetch();
		set_site_transient( self::TRANSIENT, $release ?? [], null === $release ? self::CACHE_FAIL : self::CACHE_OK );
		return $release;
	}

	/**
	 * @return array{version:string,package:string,url:string,notes:string,published:string}|null
	 */
	private function fetch(): ?array {
		$headers = [
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'sidrena-cijena-za-woocommerce/' . $this->currentVersion,
		];
		/** @var string $token */
		$token = apply_filters( 'scwc_github_token', '' );
		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}
		$response = wp_remote_get(
			'https://api.github.com/repos/' . $this->repo . '/releases/latest',
			[
				'timeout' => 10,
				'headers' => $headers,
			]
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			return null;
		}
		$version = ltrim( (string) $data['tag_name'], 'vV' );
		if ( 1 !== preg_match( '/^\d+\.\d+(\.\d+)?/', $version ) ) {
			return null;
		}
		$package = '';
		foreach ( (array) ( $data['assets'] ?? [] ) as $asset ) {
			if ( is_array( $asset ) && str_ends_with( (string) ( $asset['name'] ?? '' ), '.zip' ) ) {
				$package = (string) ( $asset['browser_download_url'] ?? '' );
				break;
			}
		}
		if ( '' === $package ) {
			$package = (string) ( $data['zipball_url'] ?? '' );
		}
		if ( '' === $package ) {
			return null;
		}
		return [
			'version'   => $version,
			'package'   => $package,
			'url'       => (string) ( $data['html_url'] ?? 'https://github.com/' . $this->repo ),
			'notes'     => (string) ( $data['body'] ?? '' ),
			'published' => (string) ( $data['published_at'] ?? '' ),
		];
	}

	/**
	 * @param mixed $transient update_plugins site transient.
	 * @return mixed
	 */
	public function checkUpdate( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}
		$release = $this->latest();
		if ( null === $release || version_compare( $release['version'], $this->currentVersion, '<=' ) ) {
			return $transient;
		}
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = [];
		}
		$transient->response[ $this->basename ] = (object) [
			'id'           => 'github.com/' . $this->repo,
			'slug'         => $this->slug,
			'plugin'       => $this->basename,
			'new_version'  => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'icons'        => [],
			'banners'      => [],
			'tested'       => self::TESTED_WP,
			'requires'     => self::REQUIRES_WP,
			'requires_php' => self::REQUIRES_PHP,
		];
		return $transient;
	}

	/**
	 * "View details" popup on the plugins screen.
	 *
	 * @param mixed  $result Existing result.
	 * @param string $action API action.
	 * @param mixed  $args   Request args.
	 * @return mixed
	 */
	public function pluginInformation( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || ( $args->slug ?? '' ) !== $this->slug ) {
			return $result;
		}
		$release = $this->latest();
		if ( null === $release ) {
			return $result;
		}
		return (object) [
			'name'          => 'Sidrena cijena za WooCommerce',
			'slug'          => $this->slug,
			'version'       => $release['version'],
			'author'        => 'Kristijan Biro',
			'homepage'      => 'https://github.com/' . $this->repo,
			'download_link' => $release['package'],
			'requires'      => self::REQUIRES_WP,
			'tested'        => self::TESTED_WP,
			'requires_php'  => self::REQUIRES_PHP,
			'last_updated'  => $release['published'],
			'sections'      => [
				'description' => __( 'Sidrena (dodatna) cijena, najniža cijena u 30 dana i strojno čitljiv cjenik (XML/CSV) prema NN 101/2026.', 'sidrena-cijena-za-woocommerce' ),
				'changelog'   => '<pre>' . esc_html( $release['notes'] ) . '</pre>',
			],
		];
	}

	/**
	 * GitHub source zipballs extract to "{owner}-{repo}-{sha}"; WordPress needs the plugin folder name.
	 *
	 * @param mixed               $source       Extracted source directory.
	 * @param mixed               $remoteSource Parent temp directory.
	 * @param mixed               $upgrader     Upgrader instance.
	 * @param array<string,mixed> $hookExtra    Upgrade context.
	 * @return mixed
	 */
	public function renameSource( $source, $remoteSource, $upgrader, $hookExtra = [] ) {
		if ( ! is_string( $source ) || ! is_string( $remoteSource ) || ( $hookExtra['plugin'] ?? '' ) !== $this->basename ) {
			return $source;
		}
		$current = basename( rtrim( $source, '/\\' ) );
		if ( $current === $this->slug ) {
			return $source;
		}
		$target = rtrim( $remoteSource, '/\\' ) . '/' . $this->slug . '/';
		if ( is_dir( $target ) || ! @rename( rtrim( $source, '/\\' ), rtrim( $target, '/' ) ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			return $source;
		}
		return $target;
	}
}
