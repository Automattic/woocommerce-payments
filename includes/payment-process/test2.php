<?php

use WCPay\Payment_Process\Exception\Existing_Processing_Order_Exception;
use WCPay\Payment_Process\Payment;
use WCPay\Payment_Process\Payment_Method\Payment_Method_Factory;
use WCPay\Payment_Process\Payment_Method\Saved_Payment_Method;
use WCPay\Payment_Process\State\Payment_Requires_Setup;
use WCPay\Payment_Process\Storage\Payment_Storage;

function process_payment( Payment_Storage $storage, Payment_Method_Factory $payment_method_factory, WC_Order $order ) {
	$payment = new Payment_Requires_Setup( $storage, $payment_method_factory );
	$payment->set_order( $order );

	/**
	 * Depending on whether a payment existed in the first place, $payment
	 * could be either a Set_Up_Payment state, or New_Payment_State (name TBD).
	 *
	 * In Set_Up_Payment state, the object would get the same setters called,
	 * but if a value changes, it would throw an exception, which will need to be
	 * handled accordingly. If a root value changes, this would mean that the payment
	 * is not the same as it was before, and we need to proceed only if confident.
	 */
	try {
		$payment->set_flag( Payment::RECURRING );
	} catch ( Exception $e ) {
		return [
			'result'  => 'error',
			'message' => 'Hummm',
		];
	}

	try {
		// Returns a Verified_Payment state.
		$payment = $payment->verify();
	} catch ( Existing_Processing_Order_Exception $e ) {
		// The exception will already contain data for a redirect.
		return $e->get_redirect_array();
	} catch ( Exception $e ) {
		// It's generally not possible to process the order.
		return $payment->get_redirect_from_exception( $e );
	}

	// Flow is a variable here, but will be distributed through methods.
	$flow = '';
	if ( Payment::STANDARD_FLOW === $flow ) {
		if ( $order->get_total() > 0 ) {
			$payment = $payment->create_and_confirm_intent();
		} else {
			$payment = $payment->create_setup_intent();
		}
	}

	if ( Payment::POST_CHECKOUT_REDIRECT_FLOW === $flow ) {
		$payment = $payment->load_intent_after_checkout();
	}

	if ( Payment::SCHEDULED_SUBSCRIPTION_PAYMENT_FLOW === $flow ) {
		$payment = $payment->create_and_confirm_intent();
	}

	if ( Payment::UPE_PREPARE_INTENT_FLOW === $flow ) {
		// We could keep this an isolated flow. It requires:
		// - payment_method_types & fingerprint are simply proxied through `Payment`.
		// - Order number might be added to the metadata here.
		// It would be good to keep the flow, as it might use an existing payment intent.
	}

	if ( Payment::UPE_PROCESS_PAYMENT_FLOW === $flow ) {
		if ( $payment_method instanceof Saved_Payment_Method ) {
			// Fallback to standard flow. Should update the existing intent ideally.
			$payment = $payment->create_and_confirm_intent();
		} else {
			$payment = $payment->update_upe_intent();
		}
	}

	if ( Payment::UPE_PROCESS_REDIRECT_FLOW === $flow ) {
		if ( ! $order->is_paid() ) { // This page could be reloaded.
			$payment->load_upe_intent();
		}
	}

	$payment = $payment->process();

	// Payment is either in Failed_Payment or Successful_Payment state, eot maybe another one?
	$payment->complete(); // Includes cleanup. Works for both failed and successful
}
