/** @format */

/**
 * External dependencies
 */
import { React, useState, useMemo } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Elements } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';

/**
 * Internal dependencies
 */
import PaymentRequestDemoButton from './payment-request-demo-button';
import {
	getPaymentRequestData,
	shouldUseGooglePayBrand,
} from 'payment-request/utils';
import InlineNotice from 'components/inline-notice';

/**
 * stripePromise is used to pass into <Elements>'s stripe props.
 * The stripe prop in <Elements> can't be change once passed in.
 * Keeping this outside of <PaymentRequestButtonPreview> so that
 * re-rendering does not change it.
 */

const PaymentRequestButtonPreview = ( props ) => {
	const [ paymentRequest, setPaymentRequest ] = useState(
		props.paymentRequest ?? null
	);
	const [ isLoading, setIsLoading ] = useState( props.isLoading ?? true );

	const stripePromise = useMemo( () => {
		const stripeSettings = getPaymentRequestData( 'stripe' );
		return loadStripe( stripeSettings.publishableKey, {
			stripeAccount: stripeSettings.accountId,
			locale: stripeSettings.locale,
		} );
	}, [] );

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

	/**
	 * If stripe is loading, then display nothing.
	 * If stripe finished loading but payment request button failed to load (null), display info section.
	 * If stripe finished loading and payment request button loads, display the button.
	 */
	if ( ! isLoading && ! paymentRequest ) {
		preview = (
			<InlineNotice status="info" isDismissible={ false }>
				To preview the buttons, ensure your device is configured to
				accept Apple Pay, or Google Pay, and view this page using the
				Safari or Chrome browsers.
			</InlineNotice>
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
