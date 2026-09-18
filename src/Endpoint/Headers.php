<?php
/**
 * HTTP headers for the public price-list endpoint.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Endpoint;

final class Headers {

	public const NO_CACHE  = 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0';
	public const IMMUTABLE = 'Cache-Control: public, max-age=86400, immutable';

	/** @var callable(string):void */
	private $send;

	/**
	 * @param callable(string):void|null $sender Header emitter (defaults to header()).
	 */
	public function __construct( ?callable $sender = null ) {
		$this->send = $sender ?? static function ( string $h ): void {
			header( $h );
		};
	}

	public static function contentType( string $format ): string {
		return match ( $format ) {
			'xml'  => 'application/xml; charset=UTF-8',
			'csv'  => 'text/csv; charset=UTF-8',
			'json' => 'application/json; charset=UTF-8',
			default => 'text/html; charset=UTF-8',
		};
	}

	public function forLatest( string $format, string $filename, int $size, string $modifiedUtc, string $etag ): void {
		$this->common( $format, $filename, $size, $modifiedUtc, $etag );
		$this->emit( self::NO_CACHE );
		$this->emit( 'Pragma: no-cache' );
		$this->emit( 'X-SCWC-Latest: ' . $filename );
	}

	public function forFile( string $format, string $filename, int $size, string $modifiedUtc, string $etag ): void {
		$this->common( $format, $filename, $size, $modifiedUtc, $etag );
		$this->emit( self::IMMUTABLE );
	}

	public function forIndex( string $format ): void {
		$this->emit( 'Content-Type: ' . self::contentType( $format ) );
		$this->emit( self::NO_CACHE );
		$this->emit( 'Pragma: no-cache' );
		$this->emit( 'Access-Control-Allow-Origin: *' );
	}

	public function notModifiedResponse(): void {
		$this->emit( 'HTTP/1.1 304 Not Modified' );
	}

	/**
	 * @param array<string,mixed> $server $_SERVER-like array.
	 */
	public static function notModified( array $server, string $etag ): bool {
		$inm = (string) ( $server['HTTP_IF_NONE_MATCH'] ?? '' );
		if ( '' === $inm ) {
			return false;
		}
		$candidates = array_map( static fn( string $s ) => trim( str_replace( 'W/', '', trim( $s ) ), '"' ), explode( ',', $inm ) );
		return in_array( $etag, $candidates, true );
	}

	private function common( string $format, string $filename, int $size, string $modifiedUtc, string $etag ): void {
		$this->emit( 'Content-Type: ' . self::contentType( $format ) );
		$this->emit( 'Content-Disposition: inline; filename="' . str_replace( '"', '', $filename ) . '"' );
		$this->emit( 'Content-Length: ' . $size );
		$this->emit( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', (int) strtotime( $modifiedUtc . ' UTC' ) ) . ' GMT' );
		$this->emit( 'ETag: "' . $etag . '"' );
		$this->emit( 'Access-Control-Allow-Origin: *' );
		$this->emit( 'X-Robots-Tag: noindex' );
		$this->emit( 'X-Content-Type-Options: nosniff' );
	}

	private function emit( string $header ): void {
		( $this->send )( $header );
	}
}
