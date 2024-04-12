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
	 * WC_Payments_Account.
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * The current time. Useful for mocking in tests.
	 *
	 * @var int
	 */
	private $current_time;

	/**
	 * Initialize class actions.
	 *
	 * @param WC_Payment_Gateway_WCPay $gateway Payment Gateway.
	 * @param WC_Payments_Account      $account Account information.
	 * @param int                      $current_time The current time.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $gateway, WC_Payments_Account $account, int $current_time ) {
		$this->gateway      = $gateway;
		$this->account      = $account;
		$this->current_time = $current_time;
	}

	/**
	 * Initializes this class's WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'current_screen', [ $this, 'maybe_enqueue_scripts' ] );
	}

	/**
	 * Needs to run after `current_screen`, to determine which page we're currently on.
	 *
	 * @return void
	 */
	public function maybe_enqueue_scripts() {
		if ( ! is_admin() ) {
			return;
		}

		// Only shown once to each Administrator and Shop Manager users.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Time boxed - Campaign expires after 90 days.
		if ( $this->current_time > strtotime( '2024-07-15 00:00:00' ) ) {
			return;
		}

		if ( get_user_meta( get_current_user_id(), '_wcpay_bnpl_april15_viewed', true ) === '1' ) {
			return;
		}

		// Only displayed to BNPL eligible countries - AU, NZ, US, AT, BE, CA, CZ, DK, FI, FR, DE, GR, IE, IT, NO, PL, PT, ES, SE, CH, NL, UK, US.
		if ( ! in_array(
			$this->account->get_account_country(),
			[
				\WCPay\Constants\Country_Code::AUSTRALIA,
				\WCPay\Constants\Country_Code::AUSTRIA,
				\WCPay\Constants\Country_Code::NEW_ZEALAND,
				\WCPay\Constants\Country_Code::UNITED_STATES,
				\WCPay\Constants\Country_Code::BELGIUM,
				\WCPay\Constants\Country_Code::CANADA,
				\WCPay\Constants\Country_Code::CZECHIA,
				\WCPay\Constants\Country_Code::DENMARK,
				\WCPay\Constants\Country_Code::FINLAND,
				\WCPay\Constants\Country_Code::FRANCE,
				\WCPay\Constants\Country_Code::GERMANY,
				\WCPay\Constants\Country_Code::GREECE,
				\WCPay\Constants\Country_Code::IRELAND,
				\WCPay\Constants\Country_Code::ITALY,
				\WCPay\Constants\Country_Code::NORWAY,
				\WCPay\Constants\Country_Code::POLAND,
				\WCPay\Constants\Country_Code::PORTUGAL,
				\WCPay\Constants\Country_Code::SPAIN,
				\WCPay\Constants\Country_Code::SWEDEN,
				\WCPay\Constants\Country_Code::SWITZERLAND,
				\WCPay\Constants\Country_Code::NETHERLANDS,
				\WCPay\Constants\Country_Code::UNITED_KINGDOM,
			],
			true
		) ) {
			return;
		}

		// just to be safe for older versions.
		if ( ! class_exists( '\Automattic\WooCommerce\Admin\PageController' ) ) {
			return;
		}

		// Target page to be displayed on - Any WooPayments page except disputes.
		$current_page = \Automattic\WooCommerce\Admin\PageController::get_instance()->get_current_page();
		if ( ! WC_Payments_Utils::is_payments_settings_page() && ( empty( $current_page ) || ! in_array(
			$current_page['id'],
			[
				'wc-payments',
				'wc-payments-deposits',
				'wc-payments-transactions',
				'wc-payments-deposit-details',
				'wc-payments-transaction-details',
				'wc-payments-multi-currency-setup',
			],
			true
		) ) ) {
			return;
		}

		// at least 3 purchases (on any payment method).
		$woopayments_successful_orders_count = $this->get_woopayments_successful_orders_count();
		if ( $woopayments_successful_orders_count < 3 ) {
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
		// conditionally show afterpay/clearpay based on account country.
		$wcpay_bnpl_announcement = rawurlencode( wp_json_encode( [ 'accountCountry' => $this->account->get_account_country() ] ) );
		wp_add_inline_script(
			'WCPAY_BNPL_ANNOUNCEMENT',
			"
			var wcpayBnplAnnouncement = wcpayBnplAnnouncement || JSON.parse( decodeURIComponent( '" . esc_js( $wcpay_bnpl_announcement ) . "' ) );
			",
			'before'
		);

		wp_enqueue_script( 'WCPAY_BNPL_ANNOUNCEMENT' );
		wp_enqueue_style( 'WCPAY_BNPL_ANNOUNCEMENT' );
	}

	/**
	 * Returns the number of successful orders paid with any WooPayments payment method.
	 *
	 * @return int
	 */
	private function get_woopayments_successful_orders_count() {
		// using a transient to store the value of a previous calculation, since it can be expensive on each page load.
		$wcpay_successful_orders_count = get_transient( 'wcpay_bnpl_april15_successful_purchases_count' );
		if ( false !== $wcpay_successful_orders_count ) {
			return intval( $wcpay_successful_orders_count );
		}

		$orders       = wc_get_orders(
			[
				// we don't need them all, just more than 3.
				'limit'  => 5,
				'status' => [ 'completed', 'processing' ],
			]
		);
		$orders_count = count( $orders );

		// storing the transient for a couple of days is probably sufficient, in case the value is too low (less than 3).
		set_transient( 'wcpay_bnpl_april15_successful_purchases_count', $orders_count, 2 * DAY_IN_SECONDS );

		return $orders_count;
	}
}
