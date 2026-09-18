<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Endpoint;

use SidrenaCijena\Endpoint\Headers;
use SidrenaCijena\Tests\TestCase;

final class HeadersTest extends TestCase {
	private array $sent = [];

	private function headers(): Headers {
		return new Headers( function ( string $h ) { $this->sent[] = $h; } );
	}

	public function test_latest_and_index_are_uncacheable(): void {
		$this->headers()->forLatest( 'xml', 'webshop_x.xml', 1234, '2026-10-01 04:00:12', 'abc' );
		self::assertContains( 'Content-Type: application/xml; charset=UTF-8', $this->sent );
		self::assertContains( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0', $this->sent );
		self::assertContains( 'Content-Disposition: inline; filename="webshop_x.xml"', $this->sent );
		self::assertContains( 'Content-Length: 1234', $this->sent );
		self::assertContains( 'ETag: "abc"', $this->sent );
		self::assertContains( 'Last-Modified: Thu, 01 Oct 2026 04:00:12 GMT', $this->sent );
		self::assertContains( 'Access-Control-Allow-Origin: *', $this->sent );
		self::assertContains( 'X-Robots-Tag: noindex', $this->sent );
		self::assertContains( 'X-SCWC-Latest: webshop_x.xml', $this->sent );
	}

	public function test_named_files_are_immutable_and_csv_has_text_type(): void {
		$this->headers()->forFile( 'csv', 'webshop_y.csv', 99, '2026-10-01 04:00:12', 'def' );
		self::assertContains( 'Content-Type: text/csv; charset=UTF-8', $this->sent );
		self::assertContains( 'Cache-Control: public, max-age=86400, immutable', $this->sent );
		self::assertContains( 'Content-Disposition: inline; filename="webshop_y.csv"', $this->sent );
	}

	public function test_index_html_and_json(): void {
		$this->headers()->forIndex( 'html' );
		self::assertContains( 'Content-Type: text/html; charset=UTF-8', $this->sent );
		self::assertNotContains( 'X-Robots-Tag: noindex', $this->sent, 'index page stays discoverable' );
		$this->sent = [];
		$this->headers()->forIndex( 'json' );
		self::assertContains( 'Content-Type: application/json; charset=UTF-8', $this->sent );
		self::assertContains( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0', $this->sent );
	}

	public function test_not_modified_check(): void {
		self::assertTrue( Headers::notModified( [ 'HTTP_IF_NONE_MATCH' => '"abc"' ], 'abc' ) );
		self::assertTrue( Headers::notModified( [ 'HTTP_IF_NONE_MATCH' => 'W/"abc"' ], 'abc' ) );
		self::assertFalse( Headers::notModified( [ 'HTTP_IF_NONE_MATCH' => '"zzz"' ], 'abc' ) );
		self::assertFalse( Headers::notModified( [], 'abc' ) );
	}
}
