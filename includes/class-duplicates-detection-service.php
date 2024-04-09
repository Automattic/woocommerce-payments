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
		$keywords = [
			// Credit card.
			'credit_card' => CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'creditcard'  => CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'cc'          => CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'card'        => CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID,

			// Google Pay/Apple Pay.
			'apple_pay'   => 'apple_pay_google_pay',
			'applepay'    => 'apple_pay_google_pay',
			'google_pay'  => 'apple_pay_google_pay',
			'googlepay'   => 'apple_pay_google_pay',

			// APMs including BNPLs.
			'bancontact'  => Bancontact_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'sepa'        => Sepa_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'giropay'     => Giropay_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'sofort'      => Sofort_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'p24'         => P24_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'przelewy24'  => P24_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'ideal'       => Ideal_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'becs'        => Becs_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'eps'         => Eps_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'affirm'      => Affirm_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'afterpay'    => Afterpay_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'clearpay'    => Afterpay_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			'klarna'      => Klarna_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
		];

		$gateways_qualified_by_duplicates_detector = [];

		foreach ( $gateways as $gateway ) {
			if ( 'yes' === $gateway->enabled ) {
				foreach ( $keywords as $keyword => $stripe_id ) {
					// Card + APMs (incl. BNPLs).
					if ( strpos( $gateway->id, $keyword ) !== false ) {
						$gateways_qualified_by_duplicates_detector[ $stripe_id ][] = $gateway->id;
						break;
					}

					if ( 'woocommerce_payments' === $gateway->id ) {
						$gateways_qualified_by_duplicates_detector[ CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID ][] = $gateway->id;

						// WooPayments PRBs.
						if ( 'yes' === $gateway->get_option( 'payment_request' ) ) {
							$gateways_qualified_by_duplicates_detector['apple_pay_google_pay'][] = $gateway->id;
						}
						break;
					}

					if ( 'stripe' === $gateway->id ) {
						$gateways_qualified_by_duplicates_detector[ CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID ][] = $gateway->id;
						break;
					}
				}
			}
			if ( 'stripe' === $gateway->id && 'yes' === $gateway->get_option( 'payment_request' ) ) {
				$gateways_qualified_by_duplicates_detector['apple_pay_google_pay'][] = $gateway->id;
			}
		}

		foreach ( $gateways_qualified_by_duplicates_detector as $gateway_id => $gateway_ids ) {
			if ( count( $gateway_ids ) < 2 ) {
				unset( $gateways_qualified_by_duplicates_detector[ $gateway_id ] );
			}
		}

		return $gateways_qualified_by_duplicates_detector;
	}
}
