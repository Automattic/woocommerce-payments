<?php
/**
 * Class Payment_Service
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment;

use WCPay\Core\Server\Request\Create_And_Confirm_Intention;

/**
 * Orchestrates the standard payment process.
 */
class New_Payment_Service {
	public function create_and_confirm_intention( $converted_amount, $currency, $payment_method, $customer_id, $manual_capture, $metadata, $level3, $off_session, $payment_methods, $cvc_confirmation, $fingerprint, $mandate ) {
		$request = Create_And_Confirm_Intention::create();
		$request->set_amount( $converted_amount );
		$request->set_currency_code( $currency );
		$request->set_payment_method( $payment_information->get_payment_method() );
		$request->set_customer( $customer_id );
		$request->set_capture_method( $payment_information->is_using_manual_capture() );
		$request->set_metadata( $metadata );
		$request->set_level3( $this->get_level3_data_from_order( $order ) );
		$request->set_off_session( $payment_information->is_merchant_initiated() );
		$request->set_payment_methods( $payment_methods );
		$request->set_cvc_confirmation( $payment_information->get_cvc_confirmation() );

		// The below if-statement ensures the support for UPE payment methods.
		if ( $this->upe_needs_redirection( $payment_methods ) ) {
			$request->set_return_url(
				wp_sanitize_redirect(
					esc_url_raw(
						add_query_arg(
							[
								'order_id' => $order_id,
								'wc_payment_method' => self::GATEWAY_ID,
								'_wpnonce' => wp_create_nonce( 'wcpay_process_redirect_order_nonce' ),
							],
							$this->get_return_url( $order )
						)
					)
				)
			);
		}

		// Make sure that setting fingerprint is performed after setting metadata because metadata will override any values you set before for metadata param.
		$request->set_fingerprint( $payment_information->get_fingerprint() );
		if ( $save_payment_method_to_store ) {
			$request->setup_future_usage();
		}
		if ( $scheduled_subscription_payment ) {
			$mandate = $this->get_mandate_param_for_renewal_order( $order );
			if ( $mandate ) {
				$request->set_mandate( $mandate );
			}
		}

		return $request->send( 'wcpay_create_and_confirm_intent_request', $payment_information );
	}
}
