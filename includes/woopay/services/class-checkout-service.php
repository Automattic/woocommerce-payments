<?php
/**
 * Class Create_And_Confirm_Intention_Test
 *
 * @package Checkout_Service
 */

namespace WCPay\WooPay\Service;

use WC_Payments_Features;
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
	 * @param bool      $using_saved_payment_method Using saved payment method.
	 *
	 * @return WooPay_Create_And_Confirm_Intention
	 * @throws Invalid_Request_Parameter_Exception
	 * @throws \WCPay\Core\Exceptions\Extend_Request_Exception
	 */
	public function create_woopay_intention_request( $base_request, $order, $using_saved_payment_method ) {
		if ( ! $order ) {
			throw new Invalid_Request_Parameter_Exception(
				'Invalid order passed',
				'wcpay_core_invalid_request_parameter_order'
			);
		}
		$request = WooPay_Create_And_Confirm_Intention::extend( $base_request );
		$request->set_has_woopay_subscription( '1' === $order->get_meta( '_woopay_has_subscription' ) );
		$request->set_is_platform_payment_method( self::is_platform_payment_method( $using_saved_payment_method ) );
		return $request;
	}

	/**
	 * Determine if current payment method is a platform payment method.
	 *
	 * @param boolean $is_using_saved_payment_method If it is using saved payment method.
	 *
	 * @return boolean True if it is a platform payment method.
	 */
	public static function is_platform_payment_method( bool $is_using_saved_payment_method ) {
		// Make sure the payment method being charged was created in the platform.
		if (
			! $is_using_saved_payment_method &&
			// This flag is useful to differentiate between PRB, blocks and shortcode checkout, since this endpoint is being used for all of them.
			! empty( $_POST['wcpay-is-platform-payment-method'] ) && // phpcs:ignore WordPress.Security.NonceVerification
			filter_var( $_POST['wcpay-is-platform-payment-method'], FILTER_VALIDATE_BOOLEAN ) // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		) {

			// This payment method was created under the platform account.
			return self::should_use_stripe_platform_on_checkout_page();
		}

		return false;
	}

	/**
	 * Whether we should use the platform account to initialize Stripe on the checkout page.
	 *
	 * @return bool
	 */
	public static function should_use_stripe_platform_on_checkout_page() {
			// TODO: Add support for blocks checkout.
		if (
				WC_Payments_Features::is_platform_checkout_eligible() &&
				'yes' === get_option( 'platform_checkout', 'no' ) &&
				! WC_Payments_Features::is_upe_enabled() &&
				is_checkout() &&
				! has_block( 'woocommerce/checkout' ) &&
				! is_wc_endpoint_url( 'order-pay' ) &&
				! WC()->cart->is_empty() &&
				WC()->cart->needs_payment()
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
	public static function init() {
		if ( ! defined( 'WCPAY_TEST_ENV' ) ) { // This should be tested manually.
			add_filter( 'create_woopay_intention_request', [ __CLASS__, 'create_woopay_intention_request' ], 10, 3 );
		}
	}
}
