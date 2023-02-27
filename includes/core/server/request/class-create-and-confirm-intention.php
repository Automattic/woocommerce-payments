<?php
/**
 * Class file for WCPay\Core\Server\Request\Create_Intention.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WC_Payments_API_Client;

/**
 * Request class for creating intents.
 */
class Create_And_Confirm_Intention extends Create_Intention {

	const IMMUTABLE_PARAMS = [
		// Those are up to us, we have to decide.
		'amount',
		'currency',
		'payment_method',
	];

	const REQUIRED_PARAMS = [
		'amount',
		'currency',
		'payment_method',
		'customer',
		'metadata',
	];

	const DEFAULT_PARAMS = [
		'confirm'        => true, // By the definition of the request.
		'capture_method' => 'automatic',
	];

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::INTENTIONS_API;
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'POST';
	}

	/**
	 * If the payment method should be saved to the store, this enables future usage.
	 */
	public function setup_future_usage() {
		$this->set_param( 'setup_future_usage', 'off_session' );
	}

	/**
	 * Off-session setter.
	 *
	 * @param bool $off_session Whether the payment is off-session (merchant-initiated), or on-session (customer-initiated).
	 */
	public function set_off_session( bool $off_session = true ) {
		// This one is tricky. We can have `true`, but otherwise we need to get rid of the parameter.
		if ( $off_session ) {
			$this->set_param( 'off_session', true );
		} else {
			$this->unset_param( 'off_session' );
		}
	}

	/**
	 * Payment methods setter.
	 *
	 * @param  array $payment_methods               An array of payment methods that might be used for the payment.
	 * @throws Invalid_Request_Parameter_Exception  When there are no payment methods provided.
	 */
	public function set_payment_methods( array $payment_methods ) {
		// Hard to validate without hardcoding a list here.
		if ( empty( $payment_methods ) ) {
			throw new Invalid_Request_Parameter_Exception(
				__( 'Intentions require at least one payment method', 'woocommerce-payments' ),
				'wcpay_core_invalid_request_parameter_missing_payment_method_types'
			);
		}

		$this->set_param( 'payment_methods_types', $payment_methods );
	}

	/**
	 * CVC confirmation setter.
	 *
	 * @param string $cvc_confirmation The CVC confirmation for this payment method (Optional).
	 */
	public function set_cvc_confirmation( $cvc_confirmation = null ) {
		$this->set_param( 'cvc_confirmation', $cvc_confirmation );
	}
}
