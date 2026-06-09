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
 *     and pushes fall back to the 6h backstop — which would still fail
 *     until the URL is fixed.
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
 * Tests use a subclass that overrides the `remote_request` seam — the
 * static `\Automattic\Jetpack\Connection\Client::remote_request()` performs
 * blog-token signing BEFORE `wp_remote_request()` is reached, so the
 * `pre_http_request` filter trick doesn't intercept it in unit-test
 * contexts where Jetpack isn't connected.
 */
class WSN_Profile_Transport_Test extends WCPAY_UnitTestCase {

	public function test_send_dispatches_post_to_woopay_with_blog_id_in_url() {
		$transport = new WSN_Profile_Transport_Stub();

		$transport->send(
			[
				'blog_id'         => 12345,
				'payload_version' => 'abc123',
			]
		);

		$this->assertNotNull( $transport->captured, 'The seam must fire — otherwise the transport never reached the Jetpack Client call.' );
		$this->assertStringContainsString( '/wp-json/wsn/v1/merchants/12345/profile', $transport->captured['args']['url'] );
		$this->assertSame( 'POST', $transport->captured['args']['method'] );
		$this->assertSame( 'application/json', $transport->captured['args']['headers']['Content-Type'] );
		$this->assertStringContainsString( '"blog_id":12345', $transport->captured['args']['body'] );
		$this->assertStringContainsString( '"blog_id":12345', $transport->captured['body'] );
	}

	public function test_send_short_circuits_when_blog_id_is_zero() {
		$transport = new WSN_Profile_Transport_Stub();

		// No HTTP call should fire — not Jetpack-connected, nothing to sign with.
		// The emitter's recurring backstop is the retry path once a connection returns.
		$transport->send( [ 'blog_id' => 0 ] );

		$this->assertNull(
			$transport->captured,
			'A zero blog_id must short-circuit BEFORE remote_request — the backstop covers the retry.'
		);
	}

	public function test_send_short_circuits_when_blog_id_is_missing() {
		$transport = new WSN_Profile_Transport_Stub();

		$transport->send( [] );

		$this->assertNull( $transport->captured );
	}

	public function test_send_throws_when_jetpack_returns_wp_error() {
		$transport                = new WSN_Profile_Transport_Stub();
		$transport->stub_response = new \WP_Error( 'connection_failed', 'Boom: connection failed.' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Boom: connection failed.' );

		$transport->send(
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
		$transport                = new WSN_Profile_Transport_Stub();
		$transport->stub_response = [
			'response' => [ 'code' => 403 ],
			'body'     => 'Forbidden',
			'headers'  => [],
		];

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'HTTP 403' );

		$transport->send(
			[
				'blog_id'         => 12345,
				'payload_version' => 'abc',
			]
		);
	}

	public function test_delete_dispatches_delete_method_with_blog_id_in_url() {
		$transport = new WSN_Profile_Transport_Stub();

		$transport->delete( 67890 );

		$this->assertNotNull( $transport->captured );
		$this->assertSame( 'DELETE', $transport->captured['args']['method'] );
		$this->assertStringContainsString( '/wp-json/wsn/v1/merchants/67890/profile', $transport->captured['args']['url'] );
		$this->assertSame( '', $transport->captured['args']['body'] );
	}

	public function test_delete_short_circuits_when_explicit_blog_id_is_zero() {
		$transport = new WSN_Profile_Transport_Stub();

		$transport->delete( 0 );

		$this->assertNull( $transport->captured );
	}

	public function test_delete_null_short_circuits_when_resolver_returns_null() {
		// Exercises the null-arg fallback through resolve_current_blog_id().
		// Stub leaves $stub_resolved_blog_id at its default (null),
		// simulating a disconnected Jetpack — the path uninstall.php would
		// hit on a never-connected merchant.
		$transport = new WSN_Profile_Transport_Stub();

		$transport->delete();

		$this->assertNull(
			$transport->captured,
			'delete(null) with no resolvable blog_id must short-circuit — firing an unsigned DELETE would 4xx on WooPay and pollute wsn_profile_last_error.'
		);
	}

	public function test_delete_null_dispatches_with_resolved_blog_id() {
		// Exercises the null-arg fallback through resolve_current_blog_id().
		// Stub returns 99999, simulating a connected Jetpack — the path
		// uninstall.php would hit on a connected merchant if it ever
		// stopped passing the explicit blog_id (today it always does, but
		// the public surface guarantees this path works).
		$transport                        = new WSN_Profile_Transport_Stub();
		$transport->stub_resolved_blog_id = 99999;

		$transport->delete();

		$this->assertNotNull( $transport->captured );
		$this->assertSame( 'DELETE', $transport->captured['args']['method'] );
		$this->assertStringContainsString( '/wp-json/wsn/v1/merchants/99999/profile', $transport->captured['args']['url'] );
	}
}
