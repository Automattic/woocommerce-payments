<?php
/**
 * Class WC_Payments_Payment_Method_Service
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Exceptions\API_Exception;
use WCPay\Logger;

/**
 * Class handling payment method-related functionality.
 *
 * Note that this is regarding Stripe-like PaymentMethod objects, not payment methods (p24, card, etc.).
 */
class WC_Payments_Payment_Method_Service {
	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Order service.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * Constructor for WC_Payments_Payment_Method_Service.
	 *
	 * @param WC_Payments_API_Client    $payments_api_client Client for making requests to the WooCommerce Payments API.
	 * @param WC_Payments_Order_Service $order_service Order service.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		WC_Payments_Order_Service $order_service
	) {
		$this->payments_api_client = $payments_api_client;
		$this->order_service       = $order_service;
	}

	/**
	 * Initializes this class's WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_filter( 'wc_order_payment_card_info', [ $this, 'get_card_info' ], 10, 2 );
	}


	/**
	 * Prepare card info for an order.
	 *
	 * @param array    $card_info The card info.
	 * @param WC_Order $order The order.
	 * @return array The card info.
	 */
	public function get_card_info( $card_info, WC_Order $order ) {
		if ( WC_Payment_Gateway_WCPay::GATEWAY_ID !== $order->get_payment_method() ) {
			return $card_info;
		}

		$payment_method_details = $this->order_service->get_payment_method_details( $order );
		if ( ! $payment_method_details ) {
			$payment_method_id = $order->get_meta( '_payment_method_id' );
			if ( ! $payment_method_id ) {
				return $card_info;
			}

			try {
				$payment_method_details = $this->payments_api_client->get_payment_method( $payment_method_id );
			} catch ( API_Exception $ex ) {
				Logger::error(
					sprintf(
						'Retrieving info for payment method for order %s: %s',
						$order->get_id(),
						$ex->getMessage()
					)
				);

				return $card_info;
			}

			// Cache payment method details.
			$this->order_service->store_payment_method_details( $order, $payment_method_details );
		}

		$card_info = [];

		if ( isset( $payment_method_details['type'], $payment_method_details[ $payment_method_details['type'] ] ) ) {
			$details = $payment_method_details[ $payment_method_details['type'] ];
			switch ( $payment_method_details['type'] ) {
				case 'card':
				default:
					$card_info['brand'] = $details['brand'] ?? '';
					$card_info['last4'] = $details['last4'] ?? '';
					break;
				case 'card_present':
				case 'interac_present':
					$card_info['brand']        = $details['brand'] ?? '';
					$card_info['last4']        = $details['last4'] ?? '';
					$card_info['account_type'] = $details['receipt']['account_type'] ?? '';
					$card_info['aid']          = $details['receipt']['dedicated_file_name'] ?? '';
					$card_info['app_name']     = $details['receipt']['application_preferred_name'] ?? '';
					break;
			}
		}

		return array_map( 'sanitize_text_field', $card_info );
	}
}
