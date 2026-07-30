<?php
/**
 * Class Dispute_Ledger_Backfill
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Queues the one-time backfill of the dispute closure ledger for existing installations.
 *
 * Dispute closures used to be de-duplicated on the order note text alone, and that text now carries
 * the dispute ID. A closure this store applied under an earlier version therefore no longer matches
 * when the platform redelivers its event, and the side effects — for a lost dispute, a refund — run
 * a second time. WC_Payments_Dispute_Ledger_Backfill_Service records ledger meta for those older
 * closures so they are recognised again.
 *
 * ActionScheduler is not reliably initialised while a plugin update runs, so this only records that
 * the backfill is due; the service schedules the work from a later hook.
 *
 * @since 11.1.0
 */
class Dispute_Ledger_Backfill {

	/**
	 * The plugin version this migration ships in.
	 *
	 * @var string
	 */
	const VERSION_SINCE = '11.1.0';

	/**
	 * Option holding the backfill progress.
	 *
	 * Same value as WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION, intentionally
	 * duplicated as a literal rather than referencing the live constant: a migration is a frozen
	 * historical step and must keep writing the same option name even if the service later renames
	 * or removes it. test_option_name_matches_service_constant() fails if the two ever drift apart.
	 *
	 * @var string
	 */
	const STATE_OPTION = 'wcpay_dispute_ledger_backfill_state';

	/**
	 * Queues the backfill for existing installs upgrading to this version.
	 *
	 * @return void
	 */
	public function maybe_migrate() {
		$previous_version = get_option( 'woocommerce_woocommerce_payments_version' );

		// Fresh installs have no closures predating the dispute ledger. Installs already on this
		// version (or newer) have either run the backfill or never needed it.
		if ( empty( $previous_version ) || version_compare( self::VERSION_SINCE, $previous_version, '<=' ) ) {
			return;
		}

		if ( false !== get_option( self::STATE_OPTION ) ) {
			return;
		}

		update_option(
			self::STATE_OPTION,
			[
				'status'         => 'pending',
				'page'           => 0,
				// Closures after this point are recorded in the ledger as they happen, so the scan
				// only needs what came before.
				'created_before' => gmdate( 'Y-m-d H:i:s' ),
				'attempts'       => 0,
			]
		);
	}
}
