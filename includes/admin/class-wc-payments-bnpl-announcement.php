<?php
/**
 * Class WC_Payments_Bnpl_Announcement
 *
 * @package WooCommerce\Payments\Admin
 */

/**
 * WC_Payments_Bnpl_Announcement class.
 */
class WC_Payments_Bnpl_Announcement {

	/**
	 * WC_Payment_Gateway_WCPay.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * Initialize class actions.
	 *
	 * @param WC_Payment_Gateway_WCPay $gateway Payment Gateway.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $gateway, WC_Payments_Account $account ) {
		$this->gateway = $gateway;
		$this->account = $account;
	}

	/**
	 * Initializes this class's WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'current_screen', [ $this, 'maybe_enqueue_scripts' ] );
	}

	public function maybe_enqueue_scripts() {
		if ( ! is_admin() ) {
			return;
		}

		// Only shown once to each Administrator and Shop Manager users.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( get_user_meta( get_current_user_id(), '_wcpay_bnpl_april15_viewed', true ) === '1' ) {
			return;
		}

		// just to be safe for older versions.
		if ( ! class_exists( '\Automattic\WooCommerce\Admin\PageController' ) ) {
			return;
		}

		// Target page to be displayed on - Any WooPayments page except disputes.
		$current_page = \Automattic\WooCommerce\Admin\PageController::get_instance()->get_current_page();
		if ( empty( $current_page ) ) {
			return;
		}
		if ( ! in_array( $current_page['id'], [
				'wc-payments',
				'wc-payments-deposits',
				'wc-payments-transactions',
				'wc-payments-deposit-details',
				'wc-payments-transaction-details',
				'wc-payments-multi-currency-setup'
			] ) && ! WC_Payments_Utils::is_payments_settings_page() ) {
			return;
		}

		if ( ! WC_Payments_Features::are_payments_enabled() ) {
			return;
		}

		// don't display the promo if the merchant already has BNPL methods enabled.
		$enabled_bnpl_payment_methods = array_intersect(
			\WCPay\Constants\Payment_Method::BNPL_PAYMENT_METHODS,
			$this->gateway->get_upe_enabled_payment_method_ids()
		);
		if ( ! empty( $enabled_bnpl_payment_methods ) ) {
			return;
		}

		// TODO: at least 3 purchases (on any gateway).
		// TODO: Only displayed to BNPL eligible countries - AU, NZ, US, AT, BE, CA, CZ, DK, FI, FR, DE, GR, IE, IT, NO, PL, PT, ES, SE, CH, NL, UK, US.
		// TODO: Time boxed - Campaign expires after 90 days.

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		add_user_meta( get_current_user_id(), '_wcpay_bnpl_april15_viewed', '1' );
	}

	/**
	 * Enqueues the script & styles for the BNPL announcement dialog.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		WC_Payments::register_script_with_dependencies( 'WCPAY_BNPL_ANNOUNCEMENT', 'dist/bnpl-announcement' );
		wp_set_script_translations( 'WCPAY_BNPL_ANNOUNCEMENT', 'woocommerce-payments' );
		WC_Payments_Utils::register_style(
			'WCPAY_BNPL_ANNOUNCEMENT',
			plugins_url( 'dist/bnpl-announcement.css', WCPAY_PLUGIN_FILE ),
			[ 'wc-components' ],
			WC_Payments::get_file_version( 'dist/bnpl-announcement.css' ),
			'all'
		);
		$account_status_data = $this->account->get_account_status_data();
		// conditionally show afterpay/clearpay based on account country.
		wp_localize_script(
			'WCPAY_BNPL_ANNOUNCEMENT',
			'wcpayBnplAnnouncement',
			[
				'accountStatus' => $account_status_data,
			]
		);

		wp_enqueue_script( 'WCPAY_BNPL_ANNOUNCEMENT' );
		wp_enqueue_style( 'WCPAY_BNPL_ANNOUNCEMENT' );
	}
}
