<?php
/**
 * Class PaymentMethodsCompatibility
 *
 * @package WooCommerce\Payments\MultiCurrency
 */

namespace WCPay\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * It ensures that when a payment method is added from the settings, the needed currency is also added.
 */
class PaymentMethodsCompatibility {
	/**
	 * The multi currency class instance.
	 *
	 * @var MultiCurrency
	 */
	private $multi_currency;

	/**
	 * The WCPay gateway class instance
	 *
	 * @var \WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * Constructor
	 *
	 * @param MultiCurrency             $multi_currency The multi currency class instance.
	 * @param \WC_Payment_Gateway_WCPay $gateway The WCPay gateway class instance.
	 */
	public function __construct( MultiCurrency $multi_currency, \WC_Payment_Gateway_WCPay $gateway ) {
		$this->multi_currency = $multi_currency;
		$this->gateway        = $gateway;

		add_action(
			'update_option_woocommerce_woocommerce_payments_settings',
			[
				$this,
				'add_missing_currencies',
			]
		);
	}

	/**
	 * Ensures that when a payment method is added from the settings, the needed currency is also added.
	 */
	public function add_missing_currencies() {
		$enabled_payment_method_ids   = $this->gateway->get_upe_enabled_payment_method_ids();
		$payment_methods_needing_euro = array_filter(
			$enabled_payment_method_ids,
			function ( $method ) {
				return in_array( $method, [ 'giropay', 'sepa_debit', 'sofort' ], true );
			}
		);
		if ( empty( $payment_methods_needing_euro ) ) {
			return;
		}

		// we have payments needing eur
		// is eur added as a currency?
		$default_currency = $this->multi_currency->get_default_currency();
		if ( $default_currency->get_id() === 'eur' ) {
			return;
		}
		$enabled_currencies = get_option( $this->multi_currency->id . '_enabled_currencies', [] );
		if ( isset( $enabled_currencies['EUR'] ) ) {
			return;
		}

		// Euro is not the default currency nor it is added - enabling it now.
		$enabled_currencies[] = 'EUR';
		update_option( $this->multi_currency->id . '_enabled_currencies', $enabled_currencies );
	}
}
