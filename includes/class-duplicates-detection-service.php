<?php
/**
 * Class Duplicates_Detection_Service
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WC_Payments;
use WCPay\PaymentMethods\Configs\Definitions\CardDefinition;
use WCPay\PaymentMethods\Configs\Registry\PaymentMethodDefinitionRegistry;

/**
 * Class handling detection of payment methods enabled by multiple plugins simultaneously.
 */
class Duplicates_Detection_Service {

	/**
	 * Registered gateways.
	 *
	 * @var array
	 */
	private $registered_gateways = null;

	/**
	 * Gateways qualified by duplicates detector.
	 *
	 * @var array
	 */
	private $gateways_qualified_by_duplicates_detector = [];

	/**
	 * Find duplicates.
	 *
	 * @return array Duplicated gateways.
	 */
	public function find_duplicates() {
		try {
			$this->gateways_qualified_by_duplicates_detector = [];

			$this->search_for_cc()
				->search_for_additional_payment_methods()
				->search_for_payment_request_buttons()
				->keep_gateways_enabled_in_woopayments()
				->keep_duplicates_only();

			// Return payment method IDs list so that front-end can successfully compare with its own list.
			return $this->gateways_qualified_by_duplicates_detector;
		} catch ( \Throwable $e ) {
			Logger::warning( 'Duplicates detection service failed silently with the following error: ' . $e->getMessage() );

			// Fail silently and return an empty array in case of any exception.
			return [];
		}
	}

	/**
	 * Search for credit card gateways.
	 *
	 * @return Duplicates_Detection_Service
	 */
	private function search_for_cc() {
		$keywords         = [ 'credit_card', 'creditcard', 'cc', 'card' ];
		$special_keywords = [ 'woocommerce_payments', 'stripe' ];

		foreach ( $this->get_enabled_gateways() as $gateway ) {
			if ( $this->gateway_contains_keyword( $gateway->id, $keywords ) || in_array( $gateway->id, $special_keywords, true ) ) {
				$this->gateways_qualified_by_duplicates_detector[ CardDefinition::get_id() ][] = $gateway->id;
			}
		}

		return $this;
	}

	/**
	 * Search for additional payment methods.
	 *
	 * @return Duplicates_Detection_Service
	 */
	private function search_for_additional_payment_methods() {
		/**
		 * FLAG: PAYMENT_METHODS_LIST
		 * As payment methods are converted to use definitions, they need to be removed from the list below.
		 */
		$keywords = [];

		// Get all payment method definitions.
		$payment_method_definitions = PaymentMethodDefinitionRegistry::instance()->get_all_payment_method_definitions();

		// This gets all the registered payment method definitions. As new payment methods are converted from the legacy style, they need to be removed from the list above.
		foreach ( $payment_method_definitions as $definition_class ) {
			$definition_keywords = $definition_class::get_keywords();
			foreach ( $definition_keywords as $keyword ) {
				$keywords[ $keyword ] = $definition_class::get_id();
			}
		}

		foreach ( $this->get_enabled_gateways() as $gateway ) {
			foreach ( $keywords as $keyword => $payment_method ) {
				if ( strpos( $gateway->id, $keyword ) !== false ) {
					$this->gateways_qualified_by_duplicates_detector[ $payment_method ][] = $gateway->id;
					break;
				}
			}
		}

		return $this;
	}

	/**
	 * Search for payment request buttons.
	 *
	 * @return Duplicates_Detection_Service
	 */
	private function search_for_payment_request_buttons() {
		$prb_payment_method = 'apple_pay_google_pay';
		$keywords           = [
			'apple_pay',
			'applepay',
			'google_pay',
			'googlepay',
		];

		foreach ( $this->get_enabled_gateways() as $gateway ) {
			if ( $this->gateway_contains_keyword( $gateway->id, $keywords ) || $this->gateway_has_wallets_in_settings( $gateway ) ) {
				$this->gateways_qualified_by_duplicates_detector[ $prb_payment_method ][] = $gateway->id;
			}
		}

		return $this;
	}

		/**
		 * Keep only WooCommerce Payments enabled gateways.
		 *
		 * @return Duplicates_Detection_Service
		 */
	private function keep_gateways_enabled_in_woopayments() {
		$woopayments_gateway_ids = $this->get_woopayments_gateway_ids();

		foreach ( $this->gateways_qualified_by_duplicates_detector as $gateway_id => $gateway_ids ) {
			if ( empty( array_intersect( $gateway_ids, $woopayments_gateway_ids ) ) ) {
				unset( $this->gateways_qualified_by_duplicates_detector[ $gateway_id ] );
			}
		}

		return $this;
	}

	/**
	 * Filter payment methods found to keep duplicates only.
	 *
	 * WooPayments registers several gateways for one payment method (e.g. the card gateway and the
	 * Apple Pay / Google Pay gateways all qualify for express checkouts), so a payment method is only
	 * a duplicate when a gateway outside WooPayments also offers it.
	 *
	 * @return Duplicates_Detection_Service
	 */
	private function keep_duplicates_only() {
		$woopayments_gateway_ids = $this->get_woopayments_gateway_ids();

		foreach ( $this->gateways_qualified_by_duplicates_detector as $gateway_id => $gateway_ids ) {
			if ( empty( array_diff( $gateway_ids, $woopayments_gateway_ids ) ) ) {
				unset( $this->gateways_qualified_by_duplicates_detector[ $gateway_id ] );
			}
		}

		return $this;
	}

	/**
	 * Check whether a gateway offers Apple Pay / Google Pay through its settings, rather than through
	 * a dedicated gateway whose ID names the wallet.
	 *
	 * @param \WC_Payment_Gateway $gateway Enabled gateway.
	 *
	 * @return bool True if the gateway's wallets are enabled, false otherwise.
	 */
	private function gateway_has_wallets_in_settings( $gateway ) {
		try {
			return $this->plugin_gateway_has_wallets_enabled( $gateway )
				|| 'yes' === $gateway->get_option( 'express_checkout_enabled' );
		} catch ( \Throwable $e ) {
			// Other plugins' code can change, so one failure must not cost the remaining detection results.
			Logger::warning( 'Duplicates detection could not read wallet settings of gateway ' . $gateway->id . ': ' . $e->getMessage() );

			return false;
		}
	}

	/**
	 * Check the wallet settings of WooCommerce-maintained plugins that keep them on another gateway.
	 *
	 * Third-party plugins are left out on purpose: these settings are internal to each plugin and may
	 * change without notice. A plugin that renames one stops being detected, so only its duplicate
	 * notice is lost.
	 *
	 * @param \WC_Payment_Gateway $gateway Enabled gateway.
	 *
	 * @return bool True if the gateway's wallets are enabled, false otherwise.
	 */
	private function plugin_gateway_has_wallets_enabled( $gateway ) {
		switch ( $gateway->id ) {
			case 'woocommerce_payments':
				return method_exists( $gateway, 'is_payment_request_enabled' ) && $gateway->is_payment_request_enabled();

			case 'stripe':
				// With Stripe's payment method configurations, the stored option can be out of date.
				if ( method_exists( $gateway, 'is_express_checkout_enabled' ) ) {
					return (bool) $gateway->is_express_checkout_enabled();
				}

				// Stripe 9.1.0 moved `payment_request` to `express_checkout`.
				$express_checkout = $gateway->get_option( 'express_checkout' );
				return 'yes' === ( '' !== $express_checkout ? $express_checkout : $gateway->get_option( 'payment_request' ) );

			case 'square_credit_card':
				$hidden_wallets = (array) $gateway->get_option( 'digital_wallets_hide_button_options', [] );

				return 'yes' === $gateway->get_option( 'enable_digital_wallets', 'yes' )
					&& ! empty( array_diff( [ 'apple', 'google' ], $hidden_wallets ) );

			case 'braintree_credit_card':
				return 'yes' === get_option( 'sv_wc_apple_pay_enabled' ) || 'yes' === get_option( 'sv_wc_google_pay_enabled' );
		}

		return false;
	}

	/**
	 * Get the IDs of all gateways registered by WooPayments.
	 *
	 * @return string[] Gateway IDs.
	 */
	private function get_woopayments_gateway_ids() {
		return array_map(
			function ( $gateway ) {
				return $gateway->id;
			},
			array_values( WC_Payments::get_payment_gateway_map() )
		);
	}

	/**
	 * Filter enabled gateways only.
	 *
	 * @return array Enabled gateways only.
	 */
	private function get_enabled_gateways() {
		return array_filter(
			$this->get_registered_gateways(),
			function ( $gateway ) {
				return 'yes' === $gateway->enabled;
			}
		);
	}

	/**
	 * Check if gateway ID contains any of the keywords.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @param array  $keywords Keywords to search for.
	 *
	 * @return bool True if gateway ID contains any of the keywords, false otherwise.
	 */
	private function gateway_contains_keyword( $gateway_id, $keywords ) {
		foreach ( $keywords as $keyword ) {
			if ( strpos( $gateway_id, $keyword ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Lazy load registered gateways.
	 *
	 * @return \WC_Payment_Gateway[] Registered gateways.
	 */
	private function get_registered_gateways() {
		if ( null === $this->registered_gateways ) {
			$this->registered_gateways = WC()->payment_gateways()->payment_gateways();
		}
		return $this->registered_gateways;
	}
}
