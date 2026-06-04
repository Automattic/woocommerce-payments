<?php
/**
 * Test stub — subclass of `WSN_Profile_Transport` that overrides the
 * `remote_request` seam to capture args + return a controllable stub
 * response without invoking Jetpack's static `Client::remote_request()`.
 *
 * The seam exists because the Jetpack signer chokes BEFORE
 * `wp_remote_request()` is reached in unit-test contexts (no active
 * Jetpack connection), so the usual `pre_http_request` filter trick
 * doesn't intercept the call. Subclass-override is the simplest seam.
 *
 * @package WooCommerce\Payments\WSN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Test double for `WSN_Profile_Transport`.
 */
class WSN_Profile_Transport_Stub extends WSN_Profile_Transport {

	/**
	 * Captured args of the most recent call. Null until a call is made.
	 *
	 * @var array{args: array, body: string}|null
	 */
	public $captured = null;

	/**
	 * Stub response returned on next call. Default is a 200 success.
	 * Tests overwrite to simulate WP_Error or non-2xx responses.
	 *
	 * @var array|\WP_Error
	 */
	public $stub_response = [
		'response' => [ 'code' => 200 ],
		'body'     => '{"ok":true}',
		'headers'  => [],
	];

	/**
	 * Stub value returned from `resolve_current_blog_id()`. Null means
	 * the resolver returns null (simulates a disconnected Jetpack), which
	 * causes `delete(null)` to short-circuit. Set to an int to simulate a
	 * connected site and exercise the fallback path.
	 *
	 * @var int|null
	 */
	public $stub_resolved_blog_id = null;

	/**
	 * Override the production seam — capture and return the stub.
	 *
	 * @param array  $args Request args.
	 * @param string $body Pre-encoded body.
	 * @return array|\WP_Error
	 */
	protected function remote_request( array $args, string $body ) {
		$this->captured = [
			'args' => $args,
			'body' => $body,
		];
		return $this->stub_response;
	}

	/**
	 * Override the Jetpack-options-backed resolver so tests don't need to
	 * stub `Jetpack_Options` static state. Returns whatever the test set
	 * in `$stub_resolved_blog_id` (default null = simulates "not connected").
	 *
	 * @return int|null
	 */
	protected function resolve_current_blog_id(): ?int {
		return $this->stub_resolved_blog_id;
	}
}
