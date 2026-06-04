<?php
/**
 * Plugin uninstall hook for WooPayments.
 *
 * Runs once when the merchant uninstalls (not deactivates) the plugin
 * via wp-admin → Plugins → Delete. Standard WordPress convention: this
 * file is loaded by the uninstall pipeline with `WP_UNINSTALL_PLUGIN`
 * defined; the rest of the plugin is not active.
 *
 * The only cleanup wired here today is the WSN (Woo Shopping Network)
 * Profile sync goodbye DELETE — a fire-and-forget request to the
 * WooPay server that removes the merchant's row from
 * `wp_wsn_merchant_profile` immediately, instead of waiting for the
 * WooPay-side reconciliation cron (7-day window) to notice the
 * merchant disappeared.
 *
 * Best-effort by construction. Per the architecture doc's "Failure
 * modes" section:
 *
 *   uninstall.php runs in a restricted WP context (`WP_UNINSTALL_PLUGIN`
 *   constant set). Network calls during uninstall are flaky — the
 *   merchant might not have network, the WooPay endpoint might be
 *   down, the merchant might be uninstalling because the plugin is
 *   broken, etc. So we still need the reconciliation cron as the
 *   safety net.
 *
 *   Recommendation: ship BOTH. Graceful for the happy path (instant
 *   removal), reconciliation for everything else. Graceful is cheap.
 *
 * @package WooCommerce\Payments
 */

// Block direct access — file should only run from the WP uninstall pipeline.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Only fire the goodbye DELETE for merchants who actually opted into WSN
// (i.e. there's a row on the WooPay side to delete). The option is set
// when the merchant clicks Enable in the Hub; absent option = never
// enrolled = nothing to clean up.
if ( '1' !== (string) get_option( 'wcpay_wsn_enabled', '' ) ) {
	return;
}

try {
	// The plugin's autoloader and runtime classes are loaded by the WP
	// uninstall pipeline (it runs uninstall.php within the plugin
	// directory's context). If something has already torn down (a fatal
	// during the uninstall, a partial removal), each guard below
	// short-circuits cleanly.
	require_once __DIR__ . '/vendor/autoload_packages.php';

	if ( ! class_exists( 'Automattic\\Jetpack\\Connection\\Rest_Authentication' ) ) {
		return;
	}

	require_once __DIR__ . '/includes/class-wc-payments.php';

	if ( ! class_exists( 'WC_Payments' ) ) {
		return;
	}

	$api_client = WC_Payments::get_payments_api_client();
	if ( null === $api_client ) {
		return;
	}

	// Fire-and-forget. We do not check the return value — there is no
	// merchant-facing UI to report success or failure to, and the
	// WooPay-side reconciliation cron is the load-bearing cleanup
	// guarantee. A failure here just means cleanup defers to the cron.
	$api_client->delete_wsn_profile_payload();
} catch ( \Throwable $e ) {
	// Silent failure by design. The uninstall pipeline cannot meaningfully
	// surface an error to the merchant, and any exception thrown here
	// would short-circuit the rest of WordPress's uninstall handling
	// for other plugins. Swallow and rely on the reconciliation cron.
	unset( $e );
}
