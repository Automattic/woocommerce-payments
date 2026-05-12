<?php
/**
 * Coordinator for WooPayments admin banner nudges.
 *
 * Each individual banner is its own subclass of
 * WC_Payments_Abstract_Admin_Banner in includes/admin/banners/. This class
 * instantiates them all and dispatches the lifecycle hooks to each.
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates the admin banner subclasses: holds an instance of each, and
 * forwards init_hooks() / init_global_hooks() to all of them.
 */
class WC_Payments_Admin_Banner {

	/**
	 * Banners managed by this coordinator.
	 *
	 * @var WC_Payments_Abstract_Admin_Banner[]
	 */
	private $banners;

	/**
	 * Constructor.
	 *
	 * @param WC_Payment_Gateway_WCPay $wcpay_gateway WCPay Gateway instance.
	 * @param WC_Payments_Account      $account       Account service instance.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $wcpay_gateway, WC_Payments_Account $account ) {
		$this->banners = [
			new WC_Payments_One_And_Done_Banner( $wcpay_gateway, $account ),
			new WC_Payments_Test_To_Live_Banner( $wcpay_gateway, $account ),
			new WC_Payments_Post_Kyc_Activation_Banner( $wcpay_gateway, $account ),
		];
	}

	/**
	 * Dispatches init_global_hooks() to every banner. Called on every request
	 * so banners can register non-admin handlers (e.g. order-completion cache
	 * invalidators that fire from storefront checkout and REST webhooks).
	 *
	 * @return void
	 */
	public function init_global_hooks(): void {
		foreach ( $this->banners as $banner ) {
			$banner->init_global_hooks();
		}
	}

	/**
	 * Dispatches init_hooks() to every banner. Gated to admin context by the
	 * caller in WC_Payments::init().
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		foreach ( $this->banners as $banner ) {
			$banner->init_hooks();
		}
	}
}
