/** @format */
/**
 * External dependencies
 */
import { React, useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';
import {
	PaymentRequestButtonElement,
	useStripe,
} from '@stripe/react-stripe-js';

const PaymentRequestDemoButton = ( props ) => {
	const stripe = useStripe();
	const [ paymentRequest, setPaymentRequest ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );

	// Since this is preview, we don't want the user to open up the browser's payment popup.
	const disablePaymentAction = ( e ) => {
		e.preventDefault();
	};

	useEffect( () => {
		if ( ! stripe ) {
			return;
		}

		// Create a preview for payment button. The label and its total are placeholders.
		const stripePaymentRequest = stripe.paymentRequest( {
			country: 'US',
			currency: 'usd',
			total: {
				label: __( 'Total', 'woocommerce-payments' ),
				amount: 99,
			},
			requestPayerName: true,
			requestPayerEmail: true,
		} );

		// Check the availability of the Payment Request API.
		stripePaymentRequest.canMakePayment().then( ( result ) => {
			if ( result ) {
				setPaymentRequest( stripePaymentRequest );
			}
			setIsLoading( false );
		} );
	}, [ stripe ] );

	if ( isLoading ) {
		return null;
	}

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
							height: props.height,
						},
					},
				} }
			/>
		);
	}

	return (
		<Notice status="info" isDismissible={ false }>
			To preview the buttons, ensure your device is configured to accept
			Apple Pay, or Google Pay, and view this page using the Safari or
			Chrome browsers.
		</Notice>
	);
};

export default PaymentRequestDemoButton;
