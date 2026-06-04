<?php
/**
 * Class WSN_Profile_Transport_Test
 *
 * @package WooCommerce\Payments\WSN
 */

/**
 * Tests for the direct-to-WooPay transport.
 *
 * Coverage focus:
 *
 *  1. **URL composition** — payloads land at `{woopay_host}/wp-json/wsn/v1/merchants/{blog_id}/profile`.
 *     A regression here means the WooPay receiver can't route the request,
 *     and pushes silently fall back to the 6h backstop — which would still
 *     fail until the URL is fixed.
 *
 *  2. **blog_id short-circuit** — without a Jetpack-resolvable blog_id, the
 *     transport returns without firing HTTP. The Jetpack signer has nothing
 *     to sign with on a disconnected site; firing anyway would produce an
 *     unsigned request that WooPay rejects, polluting `wsn_profile_last_error`.
 *
 *  3. **Throws on WP_Error / non-2xx** — both surface to the emitter's
 *     try/catch as the same `\Throwable` shape, which is what the existing
 *     last-error transient code expects.
 *
 *  4. **DELETE method dispatched** — the uninstall path needs DELETE not POST.
 *
 * Mocking strategy: `\Automattic\Jetpack\Connection\Client::remote_request`
 * dispatches through `wp_remote_request()` (see vendor/automattic/jetpack-connection/src/class-client.php),
 * which respects WP's `pre_http_request` filter. The tests register a filter
 * that captures the args + returns a stub response. This avoids mocking
 * Jetpack internals while still exercising the real call chain up to the
 * HTTP-API boundary.
 */
class WSN_Profile_Transport_Test extends WCPAY_UnitTestCase {

	/**
	 * @var array{args: array, url: string}|null Captured request args from the most recent pre_http_request fire.
	 */
	private $captured;

	/**
	 * @var array|WP_Error|null Stub response the next pre_http_request fire returns. Null = pass through (which would fire a real HTTP call — tests fail loudly if this happens unintentionally).
	 */
	private $stub_response;

	public function set_up() {
		parent::set_up();

		$this->captured      = null;
		$this->stub_response = [
			'response' => [ 'code' => 200 ],
			'body'     => '{"ok":true}',
			'headers'  => [],
		];

		add_filter( 'pre_http_request', [ $this, 'capture_request' ], 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'capture_request' ], 10 );

		parent::tear_down();
	}

	/**
	 * The pre_http_request filter callback. Captures the call and returns
	 * the test's configured stub response (short-circuiting the real HTTP
	 * call inside wp_remote_request).
	 *
	 * @param mixed  $pre  Filter pre-empt value (false to continue normally).
	 * @param array  $args Request args.
	 * @param string $url  Request URL.
	 *
	 * @return mixed The stub response.
	 */
	public function capture_request( $pre, $args, $url ) {
		unset( $pre );
		$this->captured = [
			'args' => $args,
			'url'  => $url,
		];
		return $this->stub_response;
	}

	public function test_send_dispatches_post_to_woopay_with_blog_id_in_url() {
		( new WSN_Profile_Transport() )->send(
			[
				'blog_id'         => 12345,
				'payload_version' => 'abc123',
			]
		);

		$this->assertNotNull( $this->captured, 'pre_http_request must fire — otherwise the transport never reached wp_remote_request.' );
		$this->assertStringContainsString( '/wp-json/wsn/v1/merchants/12345/profile', $this->captured['url'] );
		$this->assertSame( 'POST', $this->captured['args']['method'] );
		$this->assertSame( 'application/json', $this->captured['args']['headers']['Content-Type'] );
		$this->assertStringContainsString( '"blog_id":12345', $this->captured['args']['body'] );
	}

	public function test_send_short_circuits_when_blog_id_is_zero() {
		// No HTTP call should fire — not Jetpack-connected, nothing to sign with.
		// The emitter's recurring backstop is the retry path once a connection returns.
		( new WSN_Profile_Transport() )->send( [ 'blog_id' => 0 ] );

		$this->assertNull(
			$this->captured,
			'A zero blog_id must short-circuit BEFORE wp_remote_request — the backstop covers the retry.'
		);
	}

	public function test_send_short_circuits_when_blog_id_is_missing() {
		( new WSN_Profile_Transport() )->send( [] );

		$this->assertNull( $this->captured );
	}

	public function test_send_throws_when_jetpack_returns_wp_error() {
		$this->stub_response = new \WP_Error( 'connection_failed', 'Boom: connection failed.' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Boom: connection failed.' );

		( new WSN_Profile_Transport() )->send(
			[
				'blog_id'         => 12345,
				'payload_version' => 'abc',
			]
		);
	}

	public function test_send_throws_when_response_is_non_2xx() {
		// Simulates the WooPay-side receiver being flagged off during rollout
		// (handoff: pushes may get 403/404 until WooPay flips its receiver flag).
		// The emitter's existing catch records this as wsn_profile_last_error
		// and the 6h backstop retries — which is the correct rollout behavior.
		$this->stub_response = [
			'response' => [ 'code' => 403 ],
			'body'     => 'Forbidden',
			'headers'  => [],
		];

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'HTTP 403' );

		( new WSN_Profile_Transport() )->send(
			[
				'blog_id'         => 12345,
				'payload_version' => 'abc',
			]
		);
	}

	public function test_delete_dispatches_delete_method_with_blog_id_in_url() {
		( new WSN_Profile_Transport() )->delete( 67890 );

		$this->assertNotNull( $this->captured );
		$this->assertSame( 'DELETE', $this->captured['args']['method'] );
		$this->assertStringContainsString( '/wp-json/wsn/v1/merchants/67890/profile', $this->captured['url'] );
		$this->assertSame( '', $this->captured['args']['body'] );
	}

	public function test_delete_short_circuits_when_explicit_blog_id_is_zero() {
		( new WSN_Profile_Transport() )->delete( 0 );

		$this->assertNull( $this->captured );
	}
}
