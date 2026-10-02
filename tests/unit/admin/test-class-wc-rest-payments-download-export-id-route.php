<?php
/**
 * Class WC_REST_Payments_Download_Export_Id_Route_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Tests for the export_id route parameter regex used in transaction report
 * download endpoints.
 *
 * The regex [^/\\%]+ must:
 *  - Allow any valid Stripe export ID (alphanumeric, hyphens, dots, UTF-8).
 *  - Block path traversal via literal separators (/ and \) and percent-encoded
 *    variants (%2f, %5c, %2e%2e%2f, etc.) by rejecting the % character.
 */
class WC_REST_Payments_Download_Export_Id_Route_Test extends WCPAY_UnitTestCase {

	/**
	 * The REST bases whose download routes take an export_id.
	 *
	 * @var string[]
	 */
	private const DOWNLOAD_ROUTE_BASES = [
		'/wc/v3/payments/transactions',
		'/wc/v3/payments/disputes',
	];

	/**
	 * @dataProvider valid_export_id_provider
	 */
	public function test_valid_export_ids_are_allowed( string $export_id ): void {
		foreach ( self::DOWNLOAD_ROUTE_BASES as $base ) {
			$this->assertTrue(
				$this->download_route_matches( $base, $export_id ),
				"Export ID '$export_id' should be allowed by $base but was rejected."
			);
		}
	}

	public function valid_export_id_provider(): array {
		return [
			'simple alphanumeric'           => [ 'abc123' ],
			'alphanumeric with hyphens'     => [ 'export-2024-01-01' ],
			'alphanumeric with underscores' => [ 'export_id_123' ],
			'stripe-style ID'               => [ 'export_60d5ecf943f0ab11d2' ],
			'dots allowed'                  => [ 'some.export.id' ],
			'dots do not make traversal'    => [ '..' ],
			'UTF-8 characters'              => [ 'exporté123' ],
			'mixed UTF-8 and ASCII'         => [ 'id-ñoño-2024' ],
			'numeric only'                  => [ '1234567890' ],
			'equals padding (base64)'       => [ 'aGVsbG8=' ],
		];
	}

	/**
	 * @dataProvider path_traversal_export_id_provider
	 */
	public function test_path_traversal_export_ids_are_blocked( string $export_id ): void {
		foreach ( self::DOWNLOAD_ROUTE_BASES as $base ) {
			$this->assertFalse(
				$this->download_route_matches( $base, $export_id ),
				"Export ID '$export_id' should be blocked by $base but was allowed."
			);
		}
	}

	public function path_traversal_export_id_provider(): array {
		return [
			'dot-dot-slash traversal'             => [ '../etc/passwd' ],
			'slash only'                          => [ '/' ],
			'id with embedded slash'              => [ 'id/traversal' ],
			'backslash traversal'                 => [ '..\\etc\\passwd' ],
			'backslash only'                      => [ '\\' ],
			'id with embedded backslash'          => [ 'id\\traversal' ],
			'percent-encoded slash (%2f)'         => [ 'id%2ftraversal' ],
			'percent-encoded slash uppercase'     => [ 'id%2Ftraversal' ],
			'percent-encoded backslash (%5c)'     => [ 'id%5ctraversal' ],
			'percent-encoded backslash uppercase' => [ 'id%5Ctraversal' ],
			'percent-encoded dot (%2e) sequence'  => [ '%2e%2e%2fetc' ],
			'double-encoded percent (%25)'        => [ 'id%252ftraversal' ],
			'percent sign only'                   => [ '%' ],
			'empty string'                        => [ '' ],
		];
	}

	/**
	 * Matches a download path against the registered route, the way WP_REST_Server does.
	 *
	 * @param string $base      The controller's REST base.
	 * @param string $export_id The export ID in the path.
	 *
	 * @return bool Whether the route accepts the path.
	 */
	private function download_route_matches( string $base, string $export_id ): bool {
		$download_routes = array_values(
			array_filter(
				array_keys( rest_get_server()->get_routes() ),
				function ( $route ) use ( $base ) {
					return 0 === strpos( $route, $base . '/download/' );
				}
			)
		);
		$this->assertCount( 1, $download_routes, "Expected one download route under $base." );

		return 1 === preg_match( '@^' . $download_routes[0] . '$@i', $base . '/download/' . $export_id );
	}
}
