<?php
/**
 * Class WCPay_Playground_Demo_Http
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stands in for the WooPayments server in WordPress Playground previews.
 *
 * Reports the store as connected and answers each request from a JSON file named after the request,
 * e.g. `GET deposits/overview-all` is read from `fixtures/GET/deposits/overview-all.json`.
 * Requests without a file get an error response, so a missing fixture shows up in the UI
 * instead of being papered over with an empty result.
 */
class WCPay_Playground_Demo_Http implements WC_Payments_Http_Interface {

	const BLOG_ID = 1;

	/**
	 * Directory holding the fixture files.
	 *
	 * @var string
	 */
	private $fixtures_dir;

	/**
	 * Constructor.
	 *
	 * @param string $fixtures_dir Directory holding the fixture files.
	 */
	public function __construct( string $fixtures_dir ) {
		$this->fixtures_dir = untrailingslashit( $fixtures_dir );
	}

	/**
	 * Answers a server request from a fixture file.
	 *
	 * @param array  $args             Request arguments.
	 * @param string $body             Request body. Ignored.
	 * @param bool   $is_site_specific Ignored.
	 * @param bool   $use_user_token   Ignored.
	 *
	 * @return array HTTP response in the shape `wp_remote_request()` returns.
	 */
	public function remote_request( $args, $body = null, $is_site_specific = true, $use_user_token = false ) {
		$method = strtoupper( $args['method'] ?? 'GET' );
		$route  = $this->get_route( (string) ( $args['url'] ?? '' ) );
		$data   = $this->load_fixture( $method, $route );

		if ( null === $data ) {
			error_log( sprintf( 'WooPayments Playground preview: no fixture for %s %s', $method, $route ) );

			return $this->build_response(
				400,
				[
					'code'    => 'wcpay_playground_no_demo_data',
					'message' => sprintf( 'This preview runs on demo data and has none for %1$s %2$s.', $method, $route ),
				]
			);
		}

		return $this->build_response( 200, $data );
	}

	/**
	 * The preview always reports a connection.
	 *
	 * @return bool
	 */
	public function is_connected() {
		return true;
	}

	/**
	 * The preview always reports a connection owner.
	 *
	 * @return bool
	 */
	public function has_connection_owner() {
		return true;
	}

	/**
	 * The preview always reports the current user as connected.
	 *
	 * @return bool
	 */
	public function is_user_connected() {
		return true;
	}

	/**
	 * Returns placeholder WordPress.com user data.
	 *
	 * @return array
	 */
	public function get_connected_user_data() {
		return [
			'ID'           => 1,
			'login'        => 'demo',
			'email'        => 'demo@example.com',
			'display_name' => 'Demo merchant',
		];
	}

	/**
	 * Returns a placeholder blog ID.
	 *
	 * @return int
	 */
	public function get_blog_id() {
		return self::BLOG_ID;
	}

	/**
	 * There is nothing to connect to, so go straight to where the flow would have ended.
	 *
	 * @param string $redirect URL to redirect to.
	 */
	public function start_connection( $redirect ) {
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * WordPress.com user requests are not available in the preview.
	 *
	 * @param string       $path          REST API path.
	 * @param string       $version       REST API version.
	 * @param array        $args          Request arguments.
	 * @param string|array $body          Request body.
	 * @param string       $base_api_path REST API root.
	 *
	 * @return WP_Error
	 */
	public function wpcom_json_api_request_as_user( $path, $version = '2', $args = [], $body = null, $base_api_path = 'wpcom' ) {
		return new WP_Error( 'wcpay_playground_no_demo_data', 'This preview runs on demo data and cannot reach WordPress.com.' );
	}

	/**
	 * Reduces a request URL to the part that identifies the endpoint, e.g. `deposits/overview-all`.
	 *
	 * @param string $url Request URL. Site-specific URLs still carry the `%s` blog ID placeholder.
	 *
	 * @return string
	 */
	private function get_route( string $url ): string {
		$path = (string) strtok( $url, '?' );
		$path = preg_replace( '#^https?://[^/]+/#', '', $path );
		$path = preg_replace( '#^.*?/wcpay/#', '', $path, 1, $is_wcpay_route );

		if ( ! $is_wcpay_route ) {
			$path = preg_replace( '#^.*?/sites/[^/]+/#', '', $path );
		}

		return trim( $path, '/' );
	}

	/**
	 * Reads the fixture for a request.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Endpoint, as returned by get_route().
	 *
	 * @return array|null Decoded fixture, or null when there is none.
	 */
	private function load_fixture( string $method, string $route ) {
		// The route becomes a file path, so only plain path characters get through.
		if ( ! preg_match( '#^[A-Z]+$#', $method ) || ! preg_match( '#^[a-z0-9_-]+(/[a-z0-9_-]+)*$#', $route ) ) {
			return null;
		}

		$file = $this->fixtures_dir . '/' . $method . '/' . $route . '.json';
		if ( ! is_readable( $file ) ) {
			return null;
		}

		$data = json_decode( (string) file_get_contents( $file ), true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		array_walk_recursive( $data, [ $this, 'resolve_relative_date' ] );

		return $data;
	}

	/**
	 * Replaces relative dates in fixtures, so the demo data never looks stale.
	 *
	 * `@timestamp:-2 days` becomes a Unix timestamp, `@timestamp_ms:-2 days` the same in milliseconds
	 * and `@datetime:-2 days` a `Y-m-d H:i:s` string in UTC.
	 *
	 * @param mixed $value Fixture value, changed in place.
	 */
	private function resolve_relative_date( &$value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^@(timestamp|timestamp_ms|datetime):(.+)$/', $value, $matches ) ) {
			return;
		}

		$time = strtotime( $matches[2] );
		if ( false === $time ) {
			return;
		}

		if ( 'datetime' === $matches[1] ) {
			$value = gmdate( 'Y-m-d H:i:s', $time );
		} else {
			$value = 'timestamp_ms' === $matches[1] ? $time * 1000 : $time;
		}
	}

	/**
	 * Builds a response in the shape `wp_remote_request()` returns.
	 *
	 * @param int   $code HTTP status code.
	 * @param array $data Response body, before JSON encoding.
	 *
	 * @return array
	 */
	private function build_response( int $code, array $data ): array {
		return [
			'headers'  => [ 'content-type' => 'application/json' ],
			'body'     => wp_json_encode( $data ),
			'response' => [
				'code'    => $code,
				'message' => 200 === $code ? 'OK' : 'Bad Request',
			],
			'cookies'  => [],
		];
	}
}
