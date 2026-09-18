<?php
/**
 * Public /cjenik/ endpoint: index (HTML/JSON), latest.{xml,csv}, named archived files, external trigger.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Endpoint;

use SidrenaCijena\PriceList\FilenameBuilder;
use SidrenaCijena\PriceList\Manifest;
use SidrenaCijena\PriceList\Storage;
use SidrenaCijena\Settings\Settings;

final class Endpoint {

	public const QUERY_VARS = [ 'scwc_cjenik', 'scwc_format', 'scwc_file', 'scwc_run', 'key' ];

	/** @var callable(string):bool */
	private $runGenerator;

	/**
	 * @param callable(string):bool $runGenerator Runs a generation for a reason; returns success.
	 */
	public function __construct(
		private readonly Settings $settings,
		private readonly Storage $storage,
		private readonly Manifest $manifest,
		private readonly Headers $headers,
		private readonly IndexRenderer $index,
		callable $runGenerator,
	) {
		$this->runGenerator = $runGenerator;
	}

	public function register(): void {
		add_action( 'init', [ $this, 'addRules' ] );
		add_filter( 'query_vars', [ $this, 'queryVars' ] );
		add_action( 'template_redirect', [ $this, 'dispatch' ], 1 );
		add_filter( 'robots_txt', [ $this, 'robots' ], 10, 2 );
	}

	public function slug(): string {
		return trim( (string) $this->settings->get( 'price_list.slug', 'cjenik' ), '/' );
	}

	/**
	 * @return array<string,string>
	 */
	public function rules(): array {
		$s = preg_quote( $this->slug(), '/' );
		return [
			"^{$s}/?$"                                   => 'index.php?scwc_cjenik=index',
			"^{$s}/index\.json$"                         => 'index.php?scwc_cjenik=json',
			"^{$s}/latest\.(xml|csv)$"                   => 'index.php?scwc_cjenik=latest&scwc_format=$matches[1]',
			"^{$s}/([a-z0-9][a-z0-9._-]*\.(?:xml|csv))$" => 'index.php?scwc_cjenik=file&scwc_file=$matches[1]',
		];
	}

	public function addRules(): void {
		foreach ( $this->rules() as $regex => $redirect ) {
			add_rewrite_rule( $regex, $redirect, 'top' );
		}
		$this->maybeFlush();
	}

	public function maybeFlush(): void {
		if ( get_option( 'scwc_flush_rewrite' ) ) {
			delete_option( 'scwc_flush_rewrite' );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * @param mixed $vars Query vars.
	 * @return string[]
	 */
	public function queryVars( $vars ): array {
		return array_values( array_unique( array_merge( (array) $vars, self::QUERY_VARS ) ) );
	}

	/**
	 * @param mixed $output robots.txt content.
	 * @param mixed $public Whether the site is public.
	 */
	public function robots( $output, $public = true ): string {
		return (string) $output . "Allow: /{$this->slug()}/\n";
	}

	public function dispatch(): void {
		$wp   = $GLOBALS['wp'] ?? null;
		$vars = is_object( $wp ) && isset( $wp->query_vars ) ? (array) $wp->query_vars : [];
		foreach ( self::QUERY_VARS as $var ) { // Plain-permalink fallback.
			if ( ! isset( $vars[ $var ] ) && isset( $_GET[ $var ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$vars[ $var ] = sanitize_text_field( wp_unslash( (string) $_GET[ $var ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			}
		}
		$response = $this->resolve( $vars, $_SERVER );
		if ( null === $response ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- cache-plugin convention.
		}
		status_header( $response->status );
		if ( null !== $response->file ) {
			readfile( $response->file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		} elseif ( null !== $response->body ) {
			echo $response->body; // phpcs:ignore WordPress.Security.EscapeOutput -- body is fully built with escaping / JSON.
		}
		exit;
	}

	/**
	 * @param array<string,mixed> $vars   Query vars.
	 * @param array<string,mixed> $server $_SERVER-like array.
	 */
	public function resolve( array $vars, array $server ): ?Response {
		$action = (string) ( $vars['scwc_cjenik'] ?? '' );
		if ( '' === $action ) {
			return null;
		}
		if ( ! (bool) $this->settings->get( 'price_list.enabled', true ) ) {
			return $this->notFound();
		}
		if ( ! empty( $vars['scwc_run'] ) ) {
			return $this->externalRun( (string) ( $vars['key'] ?? '' ) );
		}
		$this->manifest->load();
		return match ( $action ) {
			'index'  => $this->indexResponse( 'html' ),
			'json'   => $this->indexResponse( 'json' ),
			'latest' => $this->latest( (string) ( $vars['scwc_format'] ?? '' ), $server ),
			'file'   => $this->file( (string) ( $vars['scwc_file'] ?? '' ), $server ),
			default  => $this->notFound(),
		};
	}

	private function indexResponse( string $format ): Response {
		$this->headers->forIndex( $format );
		return new Response( 200, null, 'json' === $format ? $this->index->json() : $this->index->html() );
	}

	/**
	 * @param array<string,mixed> $server $_SERVER-like array.
	 */
	private function latest( string $format, array $server ): Response {
		if ( ! in_array( $format, [ 'xml', 'csv' ], true ) ) {
			return $this->notFound();
		}
		$entry = $this->manifest->latest( $format );
		if ( ! $entry || ! $this->storage->exists( (string) $entry['name'] ) ) {
			return $this->notFound();
		}
		if ( Headers::notModified( $server, (string) ( $entry['sha256'] ?? '' ) ) ) {
			$this->headers->notModifiedResponse();
			return new Response( 304 );
		}
		$this->headers->forLatest( $format, (string) $entry['name'], $this->storage->size( (string) $entry['name'] ), (string) ( $entry['generated_at_utc'] ?? '' ), (string) ( $entry['sha256'] ?? '' ) );
		return new Response( 200, $this->storage->path( (string) $entry['name'] ) );
	}

	/**
	 * @param array<string,mixed> $server $_SERVER-like array.
	 */
	private function file( string $name, array $server ): Response {
		$name = basename( $name );
		if ( ! FilenameBuilder::isValid( $name ) || ! $this->manifest->has( $name ) || ! $this->storage->exists( $name ) ) {
			return $this->notFound();
		}
		$entry = (array) $this->manifest->entry( $name );
		if ( Headers::notModified( $server, (string) ( $entry['sha256'] ?? '' ) ) ) {
			$this->headers->notModifiedResponse();
			return new Response( 304 );
		}
		$format = (string) ( $entry['format'] ?? pathinfo( $name, PATHINFO_EXTENSION ) );
		$this->headers->forFile( $format, $name, $this->storage->size( $name ), (string) ( $entry['generated_at_utc'] ?? '' ), (string) ( $entry['sha256'] ?? '' ) );
		return new Response( 200, $this->storage->path( $name ) );
	}

	private function externalRun( string $key ): Response {
		$expected = (string) $this->settings->get( 'price_list.external_cron_key', '' );
		$this->headers->forIndex( 'json' );
		if ( '' === $expected || '' === $key || ! hash_equals( $expected, $key ) ) {
			return new Response( 403, null, '{"ok":false,"error":"forbidden"}' );
		}
		$ok = (bool) ( $this->runGenerator )( 'external' );
		return new Response( $ok ? 200 : 500, null, (string) wp_json_encode( [ 'ok' => $ok ] ) );
	}

	private function notFound(): Response {
		$this->headers->forIndex( 'html' );
		return new Response( 404, null, '<!DOCTYPE html><html lang="hr"><body><h1>404</h1><p>Cjenik nije pronađen.</p></body></html>' );
	}
}
