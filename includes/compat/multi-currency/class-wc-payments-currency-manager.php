<?php
/**
 * Class WC_Payments_Currency_Manager
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use WC_Payment_Gateway_WCPay;

defined( 'ABSPATH' ) || exit;

/**
 * It ensures that when a payment method is added and multi-currency is enabled, the needed currency is also added.
 */
class WC_Payments_Currency_Manager {
	/**
	 * The WCPay gateway class instance.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * Constructor
	 *
	 * @param WC_Payment_Gateway_WCPay $gateway The WCPay gateway class instance.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Initializes this class' WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'update_option_woocommerce_woocommerce_payments_settings', [ $this, 'maybe_add_currencies_for_enabled_payment_methods' ] );
		add_action( 'admin_head', [ $this, 'add_payment_method_currency_dependencies_script' ] );
	}

	/**
	 * Gets the multi-currency instance or returns null if it's not available.
	 * This method allows for easier testing by allowing the multi-currency instance to be mocked.
	 *
	 * @return \WCPay\MultiCurrency\MultiCurrency|null
	 */
	public function get_multi_currency_instance() {
		if ( ! function_exists( 'WC_Payments_Multi_Currency' ) ) {
			return null;
		}

		if ( ! WC_Payments_Multi_Currency()->is_initialized() ) {
			return null;
		}

		return WC_Payments_Multi_Currency();
	}

	/**
	 * Returns the currencies needed per enabled payment method
	 *
	 * @return array The currencies keyed with the related payment method
	 */
	public function get_enabled_payment_method_currencies() {
		$enabled_payment_method_ids = $this->gateway->get_upe_enabled_payment_method_ids();
		// getting all the payment methods that are also present in `$enabled_payment_method_ids`.
		$enabled_payment_methods          = array_values( array_intersect_key( $this->gateway->wc_payments_get_payment_method_map(), array_flip( $enabled_payment_method_ids ) ) );
		$account_currency                 = $this->gateway->get_account_domestic_currency();
		$payment_methods_needing_currency = array_reduce(
			$enabled_payment_methods,
			function ( $result, $payment_method_instance ) use ( $account_currency ) {
				$method = $payment_method_instance->get_id();
				if ( in_array( $method, [ 'card', 'card_present', 'link' ], true ) ) {
					return $result;
				}

				$result[ $method ] = [
					'currencies' => $payment_method_instance->has_domestic_transactions_restrictions() ? [ $account_currency ] : $payment_method_instance->get_currencies(),
					'title'      => $payment_method_instance->get_title( $this->gateway->get_account_country() ),
				];

				return $result;
			},
			[]
		);

		return $payment_methods_needing_currency;
	}

	/**
	 * Ensures that when a payment method is enabled, at least one of its supported currencies is also enabled.
	 *
	 * Payment methods that support multiple currencies (e.g. P24 supports EUR and PLN) only need one of them
	 * to be enabled. If none are enabled, all available supported currencies are added. If at least one is already
	 * enabled, no changes are made.
	 */
	public function maybe_add_currencies_for_enabled_payment_methods() {
		$multi_currency = $this->get_multi_currency_instance();
		if ( is_null( $multi_currency ) ) {
			return;
		}

		$payment_methods_needing_currency = $this->get_enabled_payment_method_currencies();
		if ( empty( $payment_methods_needing_currency ) ) {
			return;
		}

		$enabled_currencies   = $multi_currency->get_enabled_currencies();
		$available_currencies = $multi_currency->get_available_currencies();

		$missing_currency_codes = [];

		foreach ( $payment_methods_needing_currency as $payment_method_data ) {
			$needed_currency_codes = $payment_method_data['currencies'];

			// If at least one of the payment method's supported currencies is already enabled, skip it.
			$has_enabled_currency = false;
			foreach ( $needed_currency_codes as $needed_currency_code ) {
				if ( isset( $enabled_currencies[ $needed_currency_code ] ) ) {
					$has_enabled_currency = true;
					break;
				}
			}

			if ( $has_enabled_currency ) {
				continue;
			}

			// None of the supported currencies are enabled — add all available ones.
			foreach ( $needed_currency_codes as $needed_currency_code ) {
				if ( isset( $available_currencies[ $needed_currency_code ] ) ) {
					$missing_currency_codes[] = $needed_currency_code;
				}
			}
		}

		$missing_currency_codes = array_unique( $missing_currency_codes );

		if ( empty( $missing_currency_codes ) ) {
			return;
		}

		$multi_currency->set_enabled_currencies( array_merge( array_keys( $enabled_currencies ), $missing_currency_codes ) );
	}

	/**
	 * Adds the `multiCurrencyPaymentMethodsMap` JS object to the multi-currency settings page.
	 *
	 * This object maps currencies to payment methods that depend on them, so the multi-currency
	 * settings page displays a warning when removing a currency that a payment method needs.
	 *
	 * A currency is only mapped to a payment method if it is the ONLY enabled currency for that
	 * payment method. If the payment method has other enabled currencies, removing this one won't
	 * break the payment method, so no warning is needed.
	 */
	public function add_payment_method_currency_dependencies_script() {
		$multi_currency = $this->get_multi_currency_instance();

		if ( is_null( $multi_currency ) || ! $multi_currency->is_multi_currency_settings_page() ) {
			return;
		}

		$payment_methods_needing_currency = $this->get_enabled_payment_method_currencies();
		if ( empty( $payment_methods_needing_currency ) ) {
			return;
		}

		$enabled_currencies   = $multi_currency->get_enabled_currencies();
		$currency_methods_map = [];

		foreach ( $payment_methods_needing_currency as $method => $data ) {
			$enabled_for_method = array_filter(
				$data['currencies'],
				function ( $currency ) use ( $enabled_currencies ) {
					return isset( $enabled_currencies[ $currency ] );
				}
			);

			// Only warn about removing a currency if it's the sole enabled currency for this payment method.
			if ( 1 === count( $enabled_for_method ) ) {
				$sole_currency = reset( $enabled_for_method );
				if ( ! isset( $currency_methods_map[ $sole_currency ] ) ) {
					$currency_methods_map[ $sole_currency ] = [];
				}
				$currency_methods_map[ $sole_currency ][ $method ] = $data['title'];
			}
		}

		?>
			<script type='text/javascript'>
				window.multiCurrencyPaymentMethodsMap = <?php echo wp_json_encode( $currency_methods_map ); ?>;
			</script>
		<?php
	}
}
