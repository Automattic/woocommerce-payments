<?php
/**
 * Class WC_REST_Payments_WSN_Profile_Export_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

use Automattic\Jetpack\Connection\Rest_Authentication;

/**
 * Sibling read endpoint for the Profile push (RSM-3945) — returns the
 * canonical Profile payload the composer emits.
 *
 * Route: GET /wp-json/wc/v3/payments/wsn/profile-export
 *
 * Called by WooPay-side code in two scenarios (per the architecture
 * doc's "Sync flow" section):
 *
 *   1. **Reconciliation cron** — WooPay walks rows in its merchant
 *      profile table whose `last_seen_at < now() - 7d`, calls this
 *      endpoint to verify the merchant still wants WSN enabled, and
 *      either refreshes the row or deletes it.
 *
 *   2. **Lazy-fetch fallback** — on the first storefront hit for a
 *      merchant who opted in just before the shopper arrived (so the
 *      Profile push table doesn't have a row yet), WooPay calls this
 *      endpoint to populate the row.
 *
 * Auth: Jetpack signature (LOCKED 2026-06-04). The standard
 * `manage_woocommerce` gate inherited from `WC_Payments_REST_Controller`
 * is overridden here because the caller is a WooPay-side cron / handler,
 * not a logged-in WP admin. The blog token signature is what
 * authenticates the request; WP cookie auth is not part of this path.
 *
 * Response shape: identical to `WSN_Profile_Payload_Composer::compose()`.
 * This endpoint and the POST emitter (`WC_Payments_API_Client::send_wsn_profile_payload`)
 * deliver the same canonical payload — only the trigger direction
 * differs (push vs. pull).
 *
 * Owned by RSM-3945.
 */
class WC_REST_Payments_WSN_Profile_Export_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path under the inherited namespace (`wc/v3`).
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/wsn/profile-export';

	/**
	 * Registers the GET route.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_profile_export' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);
	}

	/**
	 * GET handler — returns the canonical Profile payload.
	 *
	 * @param WP_REST_Request $request The REST request (unused — composer
	 *                                 takes no inputs from the caller).
	 * @return WP_REST_Response
	 */
	public function get_profile_export( WP_REST_Request $request ) {
		unset( $request );
		return rest_ensure_response( WSN_Profile_Payload_Composer::compose() );
	}

	/**
	 * Override the base controller's `manage_woocommerce` gate with
	 * Jetpack blog-token signature validation.
	 *
	 * This endpoint is called by WooPay-side cron + handler code, not
	 * by an admin browser. The standard WP cookie + nonce path doesn't
	 * apply; the Jetpack signature is the authentication layer.
	 *
	 * The filter `wcpay_wsn_profile_export_is_signed_with_blog_token`
	 * mirrors the convention from `WC_REST_WooPay_Session_Controller`
	 * so test code can override the signature check without spoofing
	 * Jetpack's connection state.
	 *
	 * @return bool
	 */
	public function check_permission() {
		return apply_filters(
			'wcpay_wsn_profile_export_is_signed_with_blog_token',
			Rest_Authentication::is_signed_with_blog_token()
		);
	}
}
