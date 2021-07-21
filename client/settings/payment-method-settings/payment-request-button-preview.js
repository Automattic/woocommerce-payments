/** @format */
/**
 * External dependencies
 */
import { React, useState, useEffect } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
	getPaymentRequestData,
	shouldUseGooglePayBrand,
} from 'payment-request/utils';
import {
	Elements,
	PaymentRequestButtonElement,
	useStripe,
} from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';

const stripeSettings = getPaymentRequestData( 'stripe' );
const stripePromise = loadStripe( stripeSettings.publishableKey, {
	stripeAccount: stripeSettings.accountId,
	locale: stripeSettings.locale,
} );

const PaymentRequestDemoButton = ( props ) => {
	const stripe = useStripe();
	const [ paymentRequest, setPaymentRequest ] = useState( null );

	const sizeToPx = ( size ) => {
		const sizeToPxMappings = {
			default: 40,
			medium: 48,
			large: 56,
		};
		return sizeToPxMappings[ size ] + 'px';
	};

	// Since this is preview, we don't want the user to open up the browser's payment popup.
	const disablePaymentAction = ( e ) => {
		e.preventDefault();
	};

	useEffect( () => {
		if ( ! stripe ) {
			return;
		}

		const pr = stripe.paymentRequest( {
			country: 'US',
			currency: 'usd',
			total: {
				label: 'Demo total',
				amount: 99, //99c
			},
			requestPayerName: true,
			requestPayerEmail: true,
		} );

		// Check the availability of the Payment Request API.
		pr.canMakePayment().then( ( result ) => {
			if ( result ) {
				setPaymentRequest( pr );
			}
		} );
	}, [ stripe ] );

	if ( paymentRequest ) {
		return (
			<PaymentRequestButtonElement
				onClick={ disablePaymentAction }
				options={ {
					paymentRequest,
					style: {
						paymentRequestButton: {
							type: props.buttonType,
							theme: props.theme,
							height: sizeToPx( props.size ),
						},
					},
				} }
			/>
		);
	}
	return null;
};

const PaymentRequestButtonPreview = ( props ) => {
	const { buttonType, size, theme } = props;
	let browser = 'Google';
	let paymentMethodName = 'Google Pay';

	if ( shouldUseGooglePayBrand() ) {
		browser = 'Apple';
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

	return (
		<>
			<p>{ __( 'Preview', 'woocommerce-payments' ) }</p>
			<div className="payment-method-settings__preview">
				<Elements stripe={ stripePromise }>
					<PaymentRequestDemoButton
						buttonType={ buttonType }
						size={ size }
						theme={ theme }
					/>
				</Elements>
			</div>
			<p>{ requestButtonHelpText }</p>
		</>
	);
};

export default PaymentRequestButtonPreview;
