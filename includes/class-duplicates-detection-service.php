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

use WCPay\Payment_Methods\Affirm_Payment_Method;
use WCPay\Payment_Methods\Afterpay_Payment_Method;
use WCPay\Payment_Methods\Bancontact_Payment_Method;
use WCPay\Payment_Methods\Becs_Payment_Method;
use WCPay\Payment_Methods\CC_Payment_Method;
use WCPay\Payment_Methods\Eps_Payment_Method;
use WCPay\Payment_Methods\Giropay_Payment_Method;
use WCPay\Payment_Methods\Ideal_Payment_Method;
use WCPay\Payment_Methods\Klarna_Payment_Method;
use WCPay\Payment_Methods\P24_Payment_Method;
use WCPay\Payment_Methods\Sepa_Payment_Method;
use WCPay\Payment_Methods\Sofort_Payment_Method;

/**
 * Class handling detection of payment methods enabled by multiple plugins simultaneously.
 */
class Duplicates_Detection_Service {

		/**
		 * Find duplicates.
		 *
		 * @param array $gateways Gateways registered in WooCommerce store, both enabled and not.
		 *
		 * @return array Duplicated gateways.
		 */
	public function find_duplicates( $gateways ) {
		$gateways_qualified_by_duplicates_detector = [];

		$this->search_for_credit_cards( $gateways, $gateways_qualified_by_duplicates_detector );
		$this->search_for_additional_payment_methods( $gateways, $gateways_qualified_by_duplicates_detector );
		$this->search_for_payment_request_buttons( $gateways, $gateways_qualified_by_duplicates_detector );
		$this->filter_duplicates( $gateways_qualified_by_duplicates_detector );

		return $gateways_qualified_by_duplicates_detector;
	}

	/**
	 * Search for credit card gateways.
	 *
	 * @param array $gateways All gateways.
	 * @param array $duplicates Credit card found.
	 *
	 * @return void
	 */
	private function search_for_credit_cards( $gateways, &$duplicates ) {
		$keywords = [
			'credit_card' => CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'creditcard'  => CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'cc'          => CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'card'        => CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
		];

		$enabled_gateways = array_filter(
			$gateways,
			function ( $gateway ) {
				return 'yes' === $gateway->enabled;
			}
		);

		foreach ( $enabled_gateways as $gateway ) {
			foreach ( $keywords as $keyword => $stripe_id ) {
				if ( strpos( $gateway->id, $keyword ) !== false ) {
					$duplicates[ $stripe_id ][] = $gateway->id;
					break;
				}

				// WooPayments card gateway.
				if ( 'woocommerce_payments' === $gateway->id ) {
					$duplicates[ CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID ][] = $gateway->id;
					break;
				}

				// Stripe card gateway.
				if ( 'stripe' === $gateway->id ) {
					$duplicates[ CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID ][] = $gateway->id;
					break;
				}
			}
		}
	}

	/**
	 * Search for additional payment methods.
	 *
	 * @param array $gateways All gateways.
	 * @param array $duplicates Additional payment methods found.
	 *
	 * @return void
	 */
	private function search_for_additional_payment_methods( $gateways, &$duplicates ) {
		$keywords = [
			// APMs including BNPLs.
			'bancontact' => Bancontact_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'sepa'       => Sepa_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'giropay'    => Giropay_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'sofort'     => Sofort_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'p24'        => P24_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'przelewy24' => P24_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'ideal'      => Ideal_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'becs'       => Becs_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'eps'        => Eps_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'affirm'     => Affirm_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'afterpay'   => Afterpay_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'clearpay'   => Afterpay_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'klarna'     => Klarna_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
		];

		$enabled_gateways = array_filter(
			$gateways,
			function ( $gateway ) {
				return 'yes' === $gateway->enabled;
			}
		);

		foreach ( $enabled_gateways as $gateway ) {
			foreach ( $keywords as $keyword => $stripe_id ) {
				if ( strpos( $gateway->id, $keyword ) !== false ) {
					$duplicates[ $stripe_id ][] = $gateway->id;
					break;
				}
			}
		}
	}

	/**
	 * Search for payment request buttons.
	 *
	 * @param array $gateways All gateways.
	 * @param array $duplicates Payment request buttons found.
	 *
	 * @return void
	 */
	private function search_for_payment_request_buttons( $gateways, &$duplicates ) {
		$keywords = [
			// Google Pay/Apple Pay.
			'apple_pay'  => 'apple_pay_google_pay',
			'applepay'   => 'apple_pay_google_pay',
			'google_pay' => 'apple_pay_google_pay',
			'googlepay'  => 'apple_pay_google_pay',
		];

		foreach ( $gateways as $gateway ) {
			if ( 'yes' === $gateway->enabled ) {
				foreach ( $keywords as $keyword => $stripe_id ) {
					if ( strpos( $gateway->id, $keyword ) !== false ) {
						$duplicates[ $stripe_id ][] = $gateway->id;
						break;
					} elseif ( 'yes' === $gateway->get_option( 'payment_request' ) && ( 'woocommerce_payments' === $gateway->id || 'stripe' === $gateway->id ) ) {
						$duplicates['apple_pay_google_pay'][] = $gateway->id;
						break;
					}
				}
			} elseif ( 'stripe' === $gateway->id && 'yes' === $gateway->get_option( 'payment_request' ) ) {
				$duplicates['apple_pay_google_pay'][] = $gateway->id;
				break;
			}
		}
	}

	/**
	 * Filter payment methods found to keep duplicates only.
	 *
	 * @param array $duplicates Payment methods found.
	 *
	 * @return void
	 */
	private function filter_duplicates( &$duplicates ) {
		foreach ( $duplicates as $gateway_id => $gateway_ids ) {
			if ( count( $gateway_ids ) < 2 ) {
				unset( $duplicates[ $gateway_id ] );
			}
		}
	}
}
