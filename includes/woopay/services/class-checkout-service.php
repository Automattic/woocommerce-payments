<?php
/**
 * Class Create_And_Confirm_Intention_Test
 *
 * @package Checkout_Service
 */

namespace WCPay\WooPay\Service;

use WC_Payments_Features;
use WCPay\Core\Server\Request;
use WCPay\Core\Server\Request\WooPay_Create_And_Confirm_Intention;
use WCPay\Core\Server\Request\WooPay_Create_And_Confirm_Setup_Intention;
use WCPay\Payment_Information;

/**
 * Checkout service class.
 */
class Checkout_Service {

	/**
	 * Create woopay request from base create and confirm request.
	 *
	 * @param Request             $base_request Base request.
	 * @param Payment_Information $payment_information Using saved payment method.
	 *
	 * @return WooPay_Create_And_Confirm_Intention
	 * @throws \WCPay\Core\Exceptions\Server\Request\Extend_Request_Exception
	 */
	public function create_intention_request( Request $base_request, Payment_Information $payment_information ) {
		$request = WooPay_Create_And_Confirm_Intention::extend( $base_request );
		$request->set_has_woopay_subscription( '1' === $payment_information->get_order()->get_meta( '_woopay_has_subscription' ) );
		$request->set_save_payment_method_to_platform( $payment_information->should_save_payment_method_to_platform() );
		$request->set_is_platform_payment_method( $this->is_platform_payment_method( $payment_information->is_using_saved_payment_method() ) );
		return $request;
	}

	/**
	 * Create woopay setup and confirm intent request from base create and confirm request.
	 *
	 * @param Request             $base_request Base request.
	 * @param Payment_Information $payment_information Using saved payment method.
	 * @param bool                $save_in_platform_account Should save in platform account.
	 * @param bool                $save_payment_method_to_platform Should save in platform.
	 *
	 * @return WooPay_Create_And_Confirm_Setup_Intention
	 * @throws \WCPay\Core\Exceptions\Server\Request\Extend_Request_Exception
	 */
	public function create_and_confirm_setup_intention_request( Request $base_request, Payment_Information $payment_information, bool $save_in_platform_account, bool $save_payment_method_to_platform ) {
		$request = WooPay_Create_And_Confirm_Setup_Intention::extend( $base_request );
		$request->set_save_in_platform_account( $save_in_platform_account );
		$request->set_save_payment_method_to_platform( $save_payment_method_to_platform );
		$request->set_is_platform_payment_method( $this->is_platform_payment_method( $payment_information->is_using_saved_payment_method() ) );
		return $request;
	}

	/**
	 * Determine if current payment method is a platform payment method.
	 *
	 * @param boolean $is_using_saved_payment_method If it is using saved payment method.
	 *
	 * @return boolean True if it is a platform payment method.
	 */
	public function is_platform_payment_method( bool $is_using_saved_payment_method ) {
		// Return false for express checkout method.
		if ( isset( $_POST['payment_request_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return false;
		}

		// Make sure the payment method being charged was created in the platform.
		if (
			! $is_using_saved_payment_method &&
			$this->should_use_stripe_platform_on_checkout_page()
		) {
			// This payment method was created under the platform account.
			return true;
		}

		return false;
	}

	/**
	 * Whether we should use the platform account to initialize Stripe on the checkout page.
	 *
	 * @return bool
	 */
	public function should_use_stripe_platform_on_checkout_page() {
		if (
			WC_Payments_Features::is_woopay_eligible()
			&& 'yes' === \WC_Payments::get_gateway()->get_option( 'platform_checkout', 'no' )
			&& ! ( WC_Payments_Features::is_upe_legacy_enabled() && ! WC_Payments_Features::is_upe_split_enabled() )
			&& ( is_checkout() || has_block( 'woocommerce/checkout' ) )
			&& ! is_wc_endpoint_url( 'order-pay' )
			&& ! WC()->cart->is_empty()
			&& WC()->cart->needs_payment()
		) {
			return true;
		}

		return false;
	}

	/**
	 * Load filters.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'wcpay_create_and_confirm_intent_request', [ $this, 'create_intention_request' ], 10, 3 );
		add_filter( 'wcpay_create_and_confirm_setup_intention_request', [ $this, 'create_and_confirm_setup_intention_request' ], 10, 4 );
	}
}
