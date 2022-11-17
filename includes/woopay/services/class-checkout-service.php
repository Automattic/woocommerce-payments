<?php
/**
 * Class Create_And_Confirm_Intention_Test
 *
 * @package Checkout_Service
 */

namespace WCPay\WooPay\Service;

use WCPay\Core\Exceptions\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
use WCPay\Core\Server\Request\WooPay_Create_And_Confirm_Intention;

/**
 * Checkout service class.
 */
class Checkout_Service {

	/**
	 * Create woopay request from base create and confirm request.
	 *
	 * @param Request   $base_request Base request.
	 * @param \WC_Order $order Order.
	 * @param bool      $is_platform_payment_method Is platform payment method.
	 *
	 * @return WooPay_Create_And_Confirm_Intention
	 * @throws Invalid_Request_Parameter_Exception
	 * @throws \WCPay\Core\Exceptions\Extend_Request_Exception
	 */
	public function create_woopay_intention_request( $base_request, $order, $is_platform_payment_method ) {
		$request = WooPay_Create_And_Confirm_Intention::extend( $base_request );
		if ( ! $order ) {
			throw new Invalid_Request_Parameter_Exception(
				'Invalid order passed',
				'wcpay_core_invalid_request_parameter_order'
			);
		}
		$request->set_has_woopay_subscription( '1' === $order->get_meta( '_woopay_has_subscription' ) );
		$request->set_is_platform_payment_method( $is_platform_payment_method );
		return $request;
	}
}
