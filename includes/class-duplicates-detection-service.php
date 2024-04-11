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
	 * @return array Duplicated gateways.
	 */
	public function find_duplicates() {
		$registered_gateways                       = WC()->payment_gateways->payment_gateways;
		$gateways_qualified_by_duplicates_detector = [];

		$this->search_for_cc_payment_methods( $registered_gateways, $gateways_qualified_by_duplicates_detector )
			->search_for_additional_payment_methods( $registered_gateways, $gateways_qualified_by_duplicates_detector )
			->search_for_payment_request_buttons( $registered_gateways, $gateways_qualified_by_duplicates_detector )
			->keep_woopayments_enabled_gateways_only( $gateways_qualified_by_duplicates_detector )
			->keep_duplicates_only( $gateways_qualified_by_duplicates_detector );

		return $gateways_qualified_by_duplicates_detector;
	}

	/**
	 * Search for credit card gateways.
	 *
	 * @param array $registered_gateways All gateways.
	 * @param array $duplicates Credit card found.
	 *
	 * @return Duplicates_Detection_Service
	 */
	private function search_for_cc_payment_methods( $registered_gateways, &$duplicates ) {
		$keywords         = [ 'credit_card', 'creditcard', 'cc', 'card' ];
		$special_keywords = [ 'woocommerce_payments', 'stripe' ];

		$enabled_gateways = $this->filter_enabled_gateways_only( $registered_gateways );

		foreach ( $enabled_gateways as $gateway ) {
			if ( $this->gateway_contains_keyword( $gateway->id, $keywords ) || in_array( $gateway->id, $special_keywords, true ) ) {
				$duplicates[ CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID ][] = $gateway->id;
			}
		}

		return $this;
	}

	/**
	 * Search for additional payment methods.
	 *
	 * @param array $registered_gateways All gateways.
	 * @param array $duplicates Additional payment methods found.
	 *
	 * @return Duplicates_Detection_Service
	 */
	private function search_for_additional_payment_methods( $registered_gateways, &$duplicates ) {
		$keywords = [
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

		$enabled_gateways = $this->filter_enabled_gateways_only( $registered_gateways );

		foreach ( $enabled_gateways as $gateway ) {
			foreach ( $keywords as $keyword => $payment_method ) {
				if ( strpos( $gateway->id, $keyword ) !== false ) {
					$duplicates[ $payment_method ][] = $gateway->id;
					break;
				}
			}
		}

		return $this;
	}

	/**
	 * Search for payment request buttons.
	 *
	 * @param array $registered_gateways All gateways.
	 * @param array $duplicates Payment request buttons found.
	 *
	 * @return Duplicates_Detection_Service
	 */
	private function search_for_payment_request_buttons( $registered_gateways, &$duplicates ) {
		$prb_payment_method = 'apple_pay_google_pay';
		$keywords           = [
			'apple_pay',
			'applepay',
			'google_pay',
			'googlepay',
		];

		foreach ( $registered_gateways as $gateway ) {
			if ( 'stripe' === $gateway->id && 'yes' === $gateway->get_option( 'payment_request' ) ) {
				$duplicates[ $prb_payment_method ][] = $gateway->id;
				continue;
			}

			if ( 'yes' === $gateway->enabled ) {
				foreach ( $keywords as $keyword ) {
					if ( strpos( $gateway->id, $keyword ) !== false ) {
						$duplicates[ $prb_payment_method ][] = $gateway->id;
						break;
					} elseif ( 'yes' === $gateway->get_option( 'payment_request' ) && 'woocommerce_payments' === $gateway->id ) {
						$duplicates[ $prb_payment_method ][] = $gateway->id;
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
		 * @param array $duplicates Gateways found.
		 *
		 * @return Duplicates_Detection_Service
		 */
	private function keep_woopayments_enabled_gateways_only( &$duplicates ) {
		$woopayments_gateway_ids = array_map(
			function ( $gateway ) {
				return $gateway->id; },
			array_values( WC_Payments::get_payment_gateway_map() )
		);

		foreach ( $duplicates as $gateway_id => $gateway_ids ) {
			if ( empty( array_intersect( $gateway_ids, $woopayments_gateway_ids ) ) ) {
				unset( $duplicates[ $gateway_id ] );
			}
		}

		return $this;
	}

	/**
	 * Filter payment methods found to keep duplicates only.
	 *
	 * @param array $duplicates Payment methods found.
	 *
	 * @return Duplicates_Detection_Service
	 */
	private function keep_duplicates_only( &$duplicates ) {
		foreach ( $duplicates as $gateway_id => $gateway_ids ) {
			if ( count( $gateway_ids ) < 2 ) {
				unset( $duplicates[ $gateway_id ] );
			}
		}

		return $this;
	}

	/**
	 * Filter enabled gateways only.
	 *
	 * @param array $gateways All gateways including disabled ones.
	 *
	 * @return array Enabled gateways only.
	 */
	private function filter_enabled_gateways_only( $gateways ) {
		return array_filter(
			$gateways,
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
}
