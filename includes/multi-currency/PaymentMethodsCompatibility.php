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
	 * A map between payment methods and the currency code they require.
	 *
	 * @var string[]
	 */
	private $payment_method_currency_map = [
		'giropay'    => 'EUR',
		'sepa_debit' => 'EUR',
		'sofort'     => 'EUR',
	];

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
		$enabled_payment_method_ids       = $this->gateway->get_upe_enabled_payment_method_ids();
		$payment_methods_needing_currency = array_filter(
			$enabled_payment_method_ids,
			function ( $method ) {
				return isset( $this->payment_method_currency_map[ $method ] );
			}
		);
		if ( empty( $payment_methods_needing_currency ) ) {
			return;
		}

		$default_currency   = $this->multi_currency->get_default_currency();
		$enabled_currencies = get_option( $this->multi_currency->id . '_enabled_currencies', [] );

		// we have payments needing some currency being enabled, let's ensure the currency is present.
		foreach ( $payment_methods_needing_currency as $payment_method ) {
			$needed_currency = $this->payment_method_currency_map[ $payment_method ];
			if ( $default_currency->get_code() === $needed_currency ) {
				continue;
			}
			if ( isset( $enabled_currencies[ $needed_currency ] ) ) {
				continue;
			}

			$enabled_currencies[] = $needed_currency;
		}

		update_option( $this->multi_currency->id . '_enabled_currencies', $enabled_currencies );
	}
}
