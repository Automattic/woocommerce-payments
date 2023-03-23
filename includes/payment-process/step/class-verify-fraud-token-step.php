<?php
// Check if session exists before instantiating Fraud_Prevention_Service.
if ( WC()->session ) {
	$fraud_prevention_service = Fraud_Prevention_Service::get_instance();
	// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( $fraud_prevention_service->is_enabled() && ! $fraud_prevention_service->verify_token( $_POST['wcpay-fraud-prevention-token'] ?? null ) ) {
		throw new Process_Payment_Exception(
			__( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-payments' ),
			'fraud_prevention_enabled'
		);
	}
}
