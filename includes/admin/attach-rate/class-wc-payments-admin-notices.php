<?php
/**
 * Coordinator for WooPayments admin notice nudges.
 *
 * Each individual notice is its own subclass of
 * WC_Payments_Abstract_Admin_Notice in includes/admin/attach-rate/. This class
 * instantiates them all and dispatches the lifecycle hooks to each.
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates the admin notice subclasses: holds an instance of each, and
 * forwards init_hooks() / init_global_hooks() to all of them.
 */
class WC_Payments_Admin_Notices {

	/**
	 * Notices managed by this coordinator.
	 *
	 * @var WC_Payments_Abstract_Admin_Notice[]
	 */
	private $notices;

	/**
	 * Constructor.
	 *
	 * @param WC_Payment_Gateway_WCPay $wcpay_gateway WCPay Gateway instance.
	 * @param WC_Payments_Account      $account       Account service instance.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $wcpay_gateway, WC_Payments_Account $account ) {
		$this->notices = [
			new WC_Payments_One_And_Done_Notice( $wcpay_gateway, $account ),
			new WC_Payments_Test_To_Live_Notice( $wcpay_gateway, $account ),
			new WC_Payments_Post_Kyc_Activation_Notice( $wcpay_gateway, $account ),
		];
	}

	/**
	 * Dispatches init_global_hooks() to every notice. Called on every request
	 * so notices can register non-admin handlers (e.g. order-completion cache
	 * invalidators that fire from storefront checkout and REST webhooks).
	 *
	 * @return void
	 */
	public function init_global_hooks(): void {
		foreach ( $this->notices as $notice ) {
			$notice->init_global_hooks();
		}
	}

	/**
	 * Dispatches init_hooks() to every notice. Gated to admin context by the
	 * caller in WC_Payments::init().
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		foreach ( $this->notices as $notice ) {
			$notice->init_hooks();
		}
	}
}
