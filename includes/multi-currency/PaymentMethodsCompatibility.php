<?php
/**
 * Class PaymentMethodsCompatibility
 *
 * @package WooCommerce\Payments\MultiCurrency
 */

namespace WCPay\MultiCurrency;

use WC_Payment_Gateway_WCPay;
use WC_Payments_Features;
use WCPay\Constants\Payment_Method;

defined( 'ABSPATH' ) || exit;

/**
 * It ensures that when a payment method is added from the settings, the needed currency is also added.
 */
class PaymentMethodsCompatibility {
	/**
	 * The Multi-Currency class instance.
	 *
	 * @var MultiCurrency
	 */
	private $multi_currency;

	/**
	 * The WCPay gateway class instance.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * Constructor
	 *
	 * @param MultiCurrency            $multi_currency The Multi-Currency class instance.
	 * @param WC_Payment_Gateway_WCPay $gateway The WCPay gateway class instance.
	 */
	public function __construct( MultiCurrency $multi_currency, WC_Payment_Gateway_WCPay $gateway ) {
		$this->multi_currency = $multi_currency;
		$this->gateway        = $gateway;
	}

	/**
	 * Initializes this class' WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action(
			'update_option_woocommerce_woocommerce_payments_settings',
			[ $this, 'add_missing_currencies' ]
		);
		add_action( 'admin_head', [ $this, 'add_payment_method_currency_dependencies_script' ] );
	}

	/**
	 * Returns the currencies needed per enabled payment method
	 *
	 * @return  array  The currencies keyed with the related payment method
	 */
	public function get_enabled_payment_method_currencies() {

		if ( ! WC_Payments_Features::is_upe_enabled() ) {
			return [];
		}

		$enabled_payment_method_ids       = $this->gateway->get_upe_enabled_payment_method_ids();
		$payment_methods_needing_currency = array_reduce(
			$enabled_payment_method_ids,
			function ( $result, $method ) {
				if ( in_array( $method, [ 'card', 'card_present' ], true ) ) {
					return $result;
				}
				try {
					$method_key = Payment_Method::search( $method );
				} catch ( \InvalidArgumentException $e ) {
					return $result;
				}
				$class_key  = ucfirst( strtolower( $method_key ? $method_key : $method ) );
				$class_name = "\\WCPay\\Payment_Methods\\{$class_key}_Payment_Method";
				if ( ! class_exists( $class_name ) ) {
					return $result;
				}
				$payment_method_instance = new $class_name( null );

				$result[ $method ] = [
					'currencies' => $payment_method_instance->get_currencies(),
					'title'      => $payment_method_instance->get_title(),
				];

				return $result;
			},
			[]
		);

		return $payment_methods_needing_currency;
	}

	/**
	 * Ensures that when a payment method is added from the settings, the needed currency is also added.
	 */
	public function add_missing_currencies() {
		$payment_methods_needing_currency = $this->get_enabled_payment_method_currencies();
		if ( empty( $payment_methods_needing_currency ) ) {
			return;
		}

		$enabled_currencies   = $this->multi_currency->get_enabled_currencies();
		$available_currencies = $this->multi_currency->get_available_currencies();

		$missing_currency_codes = [];

		// TODO: we need to find something about having a currency not available for the method in case of having disabled currencies in the future.
		// First option, not do display it if the available currency is blocked by something else (Stripe, merchant, WCPay etc.)
		// Second option, showing a notice that it can't be selected because the currency is not available to use.

		// we have payments needing some currency being enabled, let's ensure the currency is present.
		foreach ( $payment_methods_needing_currency as $payment_method_data ) {
			$needed_currency_codes = $payment_method_data['currencies'];
			foreach ( $needed_currency_codes as $needed_currency_code ) {
				if ( ! isset( $available_currencies[ $needed_currency_code ] ) ) {
					continue;
				}
				if ( isset( $enabled_currencies[ $needed_currency_code ] ) ) {
					continue;
				}

				$missing_currency_codes[] = $needed_currency_code;
			}
		}

		$missing_currency_codes = array_unique( $missing_currency_codes );

		if ( empty( $missing_currency_codes ) ) {
			return;
		}

		/**
		 * The set_enabled_currencies method throws an exception if any currencies passed are not found in the current available currencies.
		 * Any currencies not found are filtered out above, so we shouldn't need a try/catch here.
		 */
		$this->multi_currency->set_enabled_currencies( array_merge( array_keys( $enabled_currencies ), $missing_currency_codes ) );
	}

	/**
	 * Adds the notices for currencies that are bound to an UPE payment method.
	 *
	 * @return  void
	 */
	public function add_payment_method_currency_dependencies_script() {
		$payment_methods_needing_currency = $this->get_enabled_payment_method_currencies();
		if ( empty( $payment_methods_needing_currency ) ) {
			return;
		}

		$currency_methods_map = [];
		foreach ( $payment_methods_needing_currency as $method => $data ) {
			foreach ( $data['currencies'] as $currency ) {
				if ( ! isset( $currency_methods_map[ $currency ] ) ) {
					$currency_methods_map[ $currency ] = [];
				}
				$currency_methods_map[ $currency ][ $method ] = $data['title'];
			}
		}

		if ( WC_Payments_Multi_Currency()->is_multi_currency_settings_page() ) : ?>
			<script type='text/javascript'>
				window.multiCurrencyPaymentMethodsMap = <?php echo wp_json_encode( $currency_methods_map ); ?>;
			</script>
			<?php
		endif;
	}
}
