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

// Only fire the goodbye DELETE for merchants who actually ENGAGED with WSN
// at some point (i.e. there's potentially a row on the WooPay side to
// delete). The option is set the first time the merchant clicks Enable in
// the Hub; once present it stays present whether the current value is '1'
// (still enrolled) or '0' (opted out later) — both cases have produced a
// WooPay-side row historically, and the DELETE is idempotent + swallowed
// on failure. Absent option = never enrolled = nothing to clean up.
//
// Using a sentinel default for get_option() instead of a boolean check on
// the value, so '0' and '1' both pass the gate while a truly-absent option
// short-circuits.
if ( '__wsn_absent__' === get_option( 'wcpay_wsn_enabled', '__wsn_absent__' ) ) {
	return;
}

try {
	// The plugin's autoloader and the dependencies required by the
	// transport are loaded explicitly so the goodbye-DELETE path does
	// NOT require WC_Payments::init() to have run (it hasn't — the WP
	// uninstall pipeline loads uninstall.php in isolation, not the
	// main plugin bootstrap). Each guard below short-circuits cleanly
	// when a class is missing — partial teardown, vendor not present,
	// disconnected from Jetpack — and the WooPay-side reconciliation
	// cron is the load-bearing cleanup path either way.
	//
	// `file_exists` precedes each `require_once` because a missing/
	// corrupted file would raise an E_COMPILE_ERROR fatal that the
	// surrounding try/catch CAN'T intercept (fatals aren't Throwables
	// at the PHP language level). The reconciliation cron is the
	// correctness layer; the goodbye DELETE is an optimization, so
	// silently bailing on a missing file is the right behavior.
	$autoloader = __DIR__ . '/vendor/autoload_packages.php';
	if ( ! file_exists( $autoloader ) ) {
		return;
	}
	require_once $autoloader;

	if ( ! class_exists( 'Automattic\\Jetpack\\Connection\\Client' )
		|| ! class_exists( '\\Jetpack_Options' ) ) {
		return;
	}

	$woopay_utilities = __DIR__ . '/includes/woopay/class-woopay-utilities.php';
	$wsn_transport    = __DIR__ . '/includes/wsn/class-wsn-profile-transport.php';
	if ( ! file_exists( $woopay_utilities ) || ! file_exists( $wsn_transport ) ) {
		return;
	}
	require_once $woopay_utilities;
	require_once $wsn_transport;

	$wcpay_blog_id = (int) \Jetpack_Options::get_option( 'id' );
	if ( $wcpay_blog_id <= 0 ) {
		return; // Not Jetpack-connected; nothing to sign with → nothing to delete.
	}

	// Fire-and-forget. The transport returns void; failures throw and the
	// catch below swallows them. The WooPay-side reconciliation cron is
	// the load-bearing cleanup guarantee — a failure here just means
	// cleanup defers to the cron's 7-day window.
	( new WSN_Profile_Transport() )->delete( $wcpay_blog_id );
} catch ( \Throwable $e ) {
	// Silent failure by design. The uninstall pipeline cannot meaningfully
	// surface an error to the merchant, and any exception thrown here
	// would short-circuit the rest of WordPress's uninstall handling
	// for other plugins. Swallow and rely on the reconciliation cron.
	unset( $e );
}
