<?php
/**
 * Class Update_Service_Data_From_Server
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class Update_Service_Data_From_Server
 *
 * Ensures that the service data is being updated after the upgrade to UPE.
 * Updating the service data ensures that the payment method fees are present.
 * If the fees are not present, the plugin shows "missing fees" text next to the UPE payment methods.
 *
 * @since 2.8.0
 */
class Update_Service_Data_From_Server {
	/**
	 * Instance to get information about the account.
	 *
	 * @var \WC_Payments_Account
	 */
	private $account;

	/**
	 * Update_Service_Data_From_Server constructor.
	 *
	 * @param \WC_Payments_Account $account instance to get information about the account.
	 */
	public function __construct( \WC_Payments_Account $account ) {
		$this->account = $account;
	}

	/**
	 * Checks whether it's worth doing the migration.
	 */
	public function maybe_migrate() {
		$account_data = $this->account->get_cached_account_data();
		// no need to migrate anything, maybe the site is disconnected.
		// the plugin will eventually fetch new account data.
		if ( empty( $account_data ) ) {
			return;
		}

		// we have account data, do we have the fees for sofort/sepa/giropay/p24 etc?
		// if we do, no need to migrate.
		$account_fees = $this->account->get_fees();
		if ( ! empty( $account_fees['giropay']['base'] ) ) {
			return;
		}

		$this->migrate();
	}

	/**
	 * Does the actual migration.
	 */
	private function migrate() {
		$this->account->refresh_account_data();
	}
}
