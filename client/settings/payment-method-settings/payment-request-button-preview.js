/** @format */

/**
 * External dependencies
 */
import { React } from 'react';
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

const PaymentRequestButtonPreview = ( props ) => {
	const { buttonType, size, theme } = props;
	let browser = 'Google Chrome';
	let paymentMethodName = 'Google Pay';

	if ( shouldUseGooglePayBrand() ) {
		browser = 'Safari';
		paymentMethodName = 'Apple Pay';
	}

	// Helper function to convert UI options to pixels in height.
	const sizeToPx = () => {
		const sizeToPxMappings = {
			default: 40,
			medium: 48,
			large: 56,
		};
		return sizeToPxMappings[ size ] + 'px';
	};

	const requestButtonHelpText = sprintf(
		__(
			/* translators: %1: Payment method name %2: Browser name. */
			'To preview the %1$s button, view this page in the %2$s browser.',
			'woocommerce-payments'
		),
		paymentMethodName,
		browser
	);

	return (
		<>
			<p>{ __( 'Preview', 'woocommerce-payments' ) }</p>
			<div className="payment-method-settings__preview">
				<Elements stripe={ stripePromise }>
					<PaymentRequestDemoButton
						buttonType={ buttonType }
						height={ sizeToPx() }
						theme={ theme }
					/>
				</Elements>
			</div>
			<p className="payment-method-settings__preview-help-text">
				{ requestButtonHelpText }
			</p>
		</>
	);
};

export default PaymentRequestButtonPreview;
