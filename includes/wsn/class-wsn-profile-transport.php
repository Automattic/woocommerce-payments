<?php
/**
 * Class WSN_Profile_Transport
 *
 * @package WooCommerce\Payments\WSN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Direct Jetpack-signed transport for the WSN Profile push.
 *
 * Targets the WooPay host (`pay.woo.com`) directly via
 * `\Automattic\Jetpack\Connection\Client::remote_request()`. WooPay validates
 * the attached `X_JETPACK` blog-token signature natively (via
 * `Jetpack_Server_Version::get_token_from_authorization_header()` plus a
 * `signed_blog_id === path_blog_id` cross-check).
 *
 * **This is NOT routed through `WC_Payments_API_Client::request()`** — that
 * client always prefixes URLs with
 * `public-api.wordpress.com/wpcom/v2/sites/{blog_id}/wcpay/`, which addresses
 * the WCPay backend (Transact-API), not WooPay. WooPay is a separately-
 * addressed destination, reached via the merchant's own Jetpack-signed POST
 * to `pay.woo.com`. The proof-by-existence pattern in production is
 * `WooPay_Session::ajax_init_woopay()` (`/init` express-checkout push), which
 * uses the same `Client::remote_request()` + WooPay-host pair.
 *
 * Throws on transport failures (WP_Error or non-2xx response) so the emitter's
 * existing try/catch records the failure into `wsn_profile_last_error`.
 *
 * Owned by RSM-3945.
 */
class WSN_Profile_Transport {

	/**
	 * HTTP timeout in seconds. Mirrors the `/init` push at
	 * `includes/woopay/class-woopay-session.php`.
	 *
	 * @var int
	 */
	const REQUEST_TIMEOUT_SECONDS = 30;

	/**
	 * POST the canonical Profile payload to WooPay.
	 *
	 * Short-circuits silently when the payload has no resolvable blog_id —
	 * that means the site is not Jetpack-connected, so there's nothing to
	 * sign with. The emitter's recurring backstop retries once a connection
	 * is restored.
	 *
	 * @param array $payload Canonical payload built by `WSN_Profile_Payload_Composer::compose()`.
	 *
	 * @throws \Exception When wp_json_encode fails, Jetpack returns a WP_Error,
	 *                    or the response is non-2xx.
	 */
	public function send( array $payload ): void {
		$blog_id = (int) ( $payload['blog_id'] ?? 0 );
		if ( $blog_id <= 0 ) {
			return;
		}

		$body = wp_json_encode( $payload );
		if ( false === $body ) {
			throw new \Exception( 'WSN Profile: payload failed to JSON-encode.' );
		}

		$this->dispatch( 'POST', $blog_id, $body );
	}

	/**
	 * DELETE the merchant's Profile row on WooPay.
	 *
	 * Called from `uninstall.php` (best-effort) and from the emitter when
	 * `wcpay_wsn_enabled` is flipped to false without a full uninstall.
	 * Resolves `blog_id` from Jetpack when no explicit value is passed.
	 *
	 * @param int|null $blog_id Explicit blog_id, or null to resolve from Jetpack.
	 *
	 * @throws \Exception When Jetpack returns a WP_Error or the response is non-2xx.
	 */
	public function delete( ?int $blog_id = null ): void {
		$resolved = $blog_id ?? $this->resolve_current_blog_id();
		if ( null === $resolved || $resolved <= 0 ) {
			return;
		}

		$this->dispatch( 'DELETE', $resolved, '' );
	}

	/**
	 * Seam for the Jetpack-signed HTTP call. Production calls the static
	 * `Client::remote_request()` directly. Tests subclass and override to
	 * capture args + return a stub response without invoking the Jetpack
	 * signer (which would fail in test environments without an active
	 * Jetpack connection — `pre_http_request` doesn't help here because
	 * the signer chokes BEFORE `wp_remote_request()` is called).
	 *
	 * @param array  $args Request args ready for the Jetpack Client.
	 * @param string $body Pre-encoded body (passed for signing parity).
	 *
	 * @return array|\WP_Error Raw response from the Jetpack Client.
	 */
	protected function remote_request( array $args, string $body ) {
		return \Automattic\Jetpack\Connection\Client::remote_request( $args, $body );
	}

	/**
	 * Resolve the current site's Jetpack blog_id, or null when Jetpack
	 * isn't loaded (e.g., during uninstall on a disconnected site).
	 *
	 * Protected so tests can override without faking `Jetpack_Options`
	 * static state — see `WSN_Profile_Transport_Stub::resolve_current_blog_id`.
	 *
	 * @return int|null
	 */
	protected function resolve_current_blog_id(): ?int {
		if ( ! class_exists( '\Jetpack_Options' ) ) {
			return null;
		}
		$id = \Jetpack_Options::get_option( 'id' );
		return null === $id ? null : (int) $id;
	}

	/**
	 * Build the WooPay-host URL for a given blog_id.
	 *
	 * Builds off `WooPay_Utilities::get_woopay_url()` directly — the sibling
	 * helper `get_woopay_rest_url()` hardcodes the `platform-checkout/v1`
	 * namespace, which doesn't match the WSN receiver's `wsn/v1` namespace.
	 *
	 * @param int $blog_id The merchant's Jetpack blog_id.
	 * @return string
	 */
	private function build_url( int $blog_id ): string {
		return rtrim( \WCPay\WooPay\WooPay_Utilities::get_woopay_url(), '/' )
			. '/wp-json/wsn/v1/merchants/' . $blog_id . '/profile';
	}

	/**
	 * Build the request args and validate the response.
	 *
	 * @param string $method  HTTP method ('POST' or 'DELETE').
	 * @param int    $blog_id Merchant blog_id, used to build the URL.
	 * @param string $body    Request body (empty string for DELETE).
	 *
	 * @throws \Exception On WP_Error or non-2xx status.
	 */
	private function dispatch( string $method, int $blog_id, string $body ): void {
		$args = [
			'url'     => $this->build_url( $blog_id ),
			'method'  => $method,
			'timeout' => self::REQUEST_TIMEOUT_SECONDS,
			'body'    => $body,
			'headers' => [ 'Content-Type' => 'application/json' ],
		];

		$response = $this->remote_request( $args, $body );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( esc_html( $response->get_error_message() ) );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			throw new \Exception(
				esc_html(
					sprintf(
						'WSN Profile %s returned HTTP %d.',
						$method,
						$status
					)
				)
			);
		}
	}
}
