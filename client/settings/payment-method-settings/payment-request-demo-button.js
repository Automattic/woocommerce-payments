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

/**
 * Internal dependencies
 */
import {
	usePaymentRequestButtonType,
	usePaymentRequestButtonSize,
	usePaymentRequestButtonTheme,
} from '../../data';

const PaymentRequestDemoButton = ( props ) => {
	const stripe = useStripe();
	const [ isLoading, setIsLoading ] = useState( true );
	const [ buttonType ] = usePaymentRequestButtonType();
	const [ size ] = usePaymentRequestButtonSize();
	const [ theme ] = usePaymentRequestButtonTheme();
	const { paymentRequest, setPaymentRequest } = props;

	// Since this is preview, we don't want the user to open up the browser's payment popup.
	const disablePaymentAction = ( e ) => {
		e.preventDefault();
	};

	// Helper function to convert UI options to pixels in height.
	const sizeToPx = () => {
		const sizeToPxMappings = {
			default: 40,
			medium: 48,
			large: 56,
		};
		return sizeToPxMappings[ size ] + 'px';
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
	}, [ stripe, setPaymentRequest ] );

	if ( isLoading ) {
		return null;
	}

	if ( paymentRequest ) {
		return (
			<PaymentRequestButtonElement
				onClick={ disablePaymentAction }
				options={ {
					paymentRequest: paymentRequest,
					style: {
						paymentRequestButton: {
							type: buttonType,
							theme: theme,
							height: sizeToPx(),
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
