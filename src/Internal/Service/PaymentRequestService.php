<?php
/**
 * Class PaymentRequestService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WC_Payments_API_Payment_Intention;
use WCPay\Core\Server\Request\Create_And_Confirm_Intention;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Exceptions\Server\Request\Extend_Request_Exception;
use WCPay\Core\Exceptions\Server\Request\Immutable_Parameter_Exception;
use WCPay\Internal\Payment\PaymentContext;

/**
 * Service for managing requests, connecting payments and the API.
 */
class PaymentRequestService {
	/**
	 * Creates a payment intent. To be replaced by an abstraction soon.
	 *
	 * @param PaymentContext $context Context for the payment.
	 * @return WC_Payments_API_Payment_Intention
	 * @throws Invalid_Request_Parameter_Exception
	 * @throws Extend_Request_Exception
	 * @throws Immutable_Parameter_Exception
	 */
	public function create_intent( PaymentContext $context ) {
		$request = Create_And_Confirm_Intention::create();
		$request->set_amount( $context->get_amount() );
		$request->set_currency_code( $context->get_currency() );
		$request->set_payment_method( $context->get_payment_method()->get_id() );
		$request->set_customer( $context->get_customer_id() );
		// We are using the automatic capture, but intent signature accepts manual capture, so we have to change bool here.
		$request->set_capture_method( ! $context->should_capture_automatically() );
		$request->set_metadata( $context->get_metadata() );
		$request->set_level3( $context->get_level3_data() );
		$request->set_payment_methods( [ 'card' ] ); // Initial payment process only supports cards.
		$request->set_cvc_confirmation( $context->get_cvc_confirmation() );
		// Empty string to trigger the link to `Buyer_Fingerprinting_Service`.
		$request->set_fingerprint( $context->get_fingerprint() ?? '' );

		// ToDo: The WooPay service should accept the old and new payment contexts.
		$request->assign_hook( 'wcpay_create_and_confirm_intent_request_new' );
		return $request->send();
	}
}
