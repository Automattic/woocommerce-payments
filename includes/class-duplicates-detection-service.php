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
use WCPay\Payment_Methods\Affirm_Payment_Method;
use WCPay\Payment_Methods\Afterpay_Payment_Method;
use WCPay\Payment_Methods\Bancontact_Payment_Method;
use WCPay\Payment_Methods\Becs_Payment_Method;
use WCPay\Payment_Methods\CC_Payment_Method;
use WCPay\Payment_Methods\Eps_Payment_Method;
use WCPay\Payment_Methods\Ideal_Payment_Method;
use WCPay\Payment_Methods\Klarna_Payment_Method;
use WCPay\Payment_Methods\P24_Payment_Method;
use WCPay\Payment_Methods\Sepa_Payment_Method;
use WCPay\Payment_Methods\Grabpay_Payment_Method;
use WCPay\Payment_Methods\Wechatpay_Payment_Method;
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
		} catch ( \Exception $e ) {
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
				$this->gateways_qualified_by_duplicates_detector[ CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID ][] = $gateway->id;
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
		$keywords = [
			'bancontact' => Bancontact_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'sepa'       => Sepa_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'p24'        => P24_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'przelewy24' => P24_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'ideal'      => Ideal_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'becs'       => Becs_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'eps'        => Eps_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'affirm'     => Affirm_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'afterpay'   => Afterpay_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'clearpay'   => Afterpay_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'klarna'     => Klarna_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'grabpay'    => Grabpay_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'wechatpay'  => Wechatpay_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
		];

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

		foreach ( $this->get_registered_gateways() as $gateway ) {
			if ( 'yes' === $gateway->enabled ) {
				foreach ( $keywords as $keyword ) {
					if ( strpos( $gateway->id, $keyword ) !== false ) {
						$this->gateways_qualified_by_duplicates_detector[ $prb_payment_method ][] = $gateway->id;
						break;
					} elseif ( 'yes' === $gateway->get_option( 'payment_request' ) && in_array( $gateway->id, [ 'woocommerce_payments', 'stripe' ], true ) ) {
						$this->gateways_qualified_by_duplicates_detector[ $prb_payment_method ][] = $gateway->id;
						break;
					} elseif ( 'yes' === $gateway->get_option( 'express_checkout_enabled' ) ) {
						$this->gateways_qualified_by_duplicates_detector[ $prb_payment_method ][] = $gateway->id;
						break;
					}
				}
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
		$woopayments_gateway_ids = array_map(
			function ( $gateway ) {
				return $gateway->id; },
			array_values( WC_Payments::get_payment_gateway_map() )
		);

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
	 * @return Duplicates_Detection_Service
	 */
	private function keep_duplicates_only() {
		foreach ( $this->gateways_qualified_by_duplicates_detector as $gateway_id => $gateway_ids ) {
			if ( count( $gateway_ids ) < 2 ) {
				unset( $this->gateways_qualified_by_duplicates_detector[ $gateway_id ] );
			}
		}

		return $this;
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
	 * @return array Registered gateways.
	 */
	private function get_registered_gateways() {
		if ( null === $this->registered_gateways ) {
			$this->registered_gateways = WC()->payment_gateways->payment_gateways();
		}
		return $this->registered_gateways;
	}
}
