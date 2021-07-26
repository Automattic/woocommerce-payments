/** @format */

/**
 * External dependencies
 */
import { React, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Elements } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import { Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import PaymentRequestDemoButton from './payment-request-demo-button';
import {
	getPaymentRequestData,
	shouldUseGooglePayBrand,
} from 'payment-request/utils';

/**
 * stripePromise is used to pass into <Elements>'s stripe props.
 * The stripe prop in <Elements> can't be change once passed in.
 * Keeping this outside of <PaymentRequestButtonPreview> so that
 * re-rendering does not change it.
 */
const stripeSettings = getPaymentRequestData( 'stripe' );
const stripePromise = loadStripe( stripeSettings.publishableKey, {
	stripeAccount: stripeSettings.accountId,
	locale: stripeSettings.locale,
} );

const PaymentRequestButtonPreview = () => {
	const [ paymentRequest, setPaymentRequest ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );

	let browser = 'Google Chrome';
	let paymentMethodName = 'Google Pay';

	if ( shouldUseGooglePayBrand() ) {
		browser = 'Safari';
		paymentMethodName = 'Apple Pay';
	}

	const requestButtonHelpText = sprintf(
		__(
			/* translators: %1: Payment method name %2: Browser name. */
			'To preview the %1$s button, view this page in the %2$s browser.',
			'woocommerce-payments'
		),
		paymentMethodName,
		browser
	);

	let preview;
	if ( ! isLoading && ! paymentRequest ) {
		preview = (
			<Notice status="info" isDismissible={ false }>
				To preview the buttons, ensure your device is configured to
				accept Apple Pay, or Google Pay, and view this page using the
				Safari or Chrome browsers.
			</Notice>
		);
	} else {
		preview = (
			<>
				<div className="payment-method-settings__preview">
					<Elements stripe={ stripePromise }>
						<PaymentRequestDemoButton
							paymentRequest={ paymentRequest }
							setPaymentRequest={ setPaymentRequest }
							isLoading={ isLoading }
							setIsLoading={ setIsLoading }
						/>
					</Elements>
				</div>
				<p className="payment-method-settings__preview-help-text">
					{ requestButtonHelpText }
				</p>
			</>
		);
	}

	return (
		<>
			<p>{ __( 'Preview', 'woocommerce-payments' ) }</p>
			{ preview }
		</>
	);
};

export default PaymentRequestButtonPreview;
