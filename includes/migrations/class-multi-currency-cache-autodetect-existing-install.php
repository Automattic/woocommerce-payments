<?php
/**
 * Class Multi_Currency_Cache_Autodetect_Existing_Install
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Marks Multi-Currency cache auto-detection as already run for existing installations.
 *
 * WOOPMNT-6025 added a one-time auto-detection
 * (MultiCurrency::maybe_auto_enable_cache_rendering_mode()) that can switch the Multi-Currency
 * price rendering mode to 'cache' on sites with page caching. Existing installations must NOT
 * have their rendering mode changed on upgrade — they keep their current behavior and instead see
 * the dismissible recommendation notice. This migration force-stores the "already run" flag so the
 * auto-detection only ever applies to fresh installations.
 *
 * @since 11.0.0
 */
class Multi_Currency_Cache_Autodetect_Existing_Install {

	/**
	 * The plugin version this migration ships in.
	 *
	 * @var string
	 */
	const VERSION_SINCE = '11.0.0';

	/**
	 * Option flag marking that the one-time caching auto-detection has run.
	 *
	 * Same value as MultiCurrency::CACHE_AUTODETECT_DONE_OPTION, intentionally duplicated as a
	 * literal rather than referencing the live constant: a migration is a frozen historical step
	 * and must keep writing the same option name even if MultiCurrency later renames or removes it.
	 * test_option_name_matches_multi_currency_constant() fails if the two ever drift apart.
	 *
	 * @var string
	 */
	const AUTODETECT_DONE_OPTION = 'wcpay_multi_currency_cache_autodetect_done';

	/**
	 * Marks auto-detection as done for existing installs upgrading to this version.
	 *
	 * @return void
	 */
	public function maybe_migrate() {
		$previous_version = get_option( 'woocommerce_woocommerce_payments_version' );

		// Fresh installs have no previous version — let auto-detection run for them. Installs already
		// on this version (or newer) have nothing to migrate. Only existing installs upgrading from an
		// earlier version are marked as already-detected so their rendering mode is left untouched.
		if ( empty( $previous_version ) || version_compare( self::VERSION_SINCE, $previous_version, '<=' ) ) {
			return;
		}

		update_option( self::AUTODETECT_DONE_OPTION, 'yes' );
	}
}
