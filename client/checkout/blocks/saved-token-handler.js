/**
 * Internal dependencies
 */
import { useEffect } from 'react';
import { usePaymentCompleteHandler } from './hooks';
import { select } from '@wordpress/data';

const { getPaymentMethodData } = select( 'wc/store/payment' );

export const SavedTokenHandler = ( {
	api,
	stripe,
	elements,
	eventRegistration: { onPaymentSetup, onCheckoutSuccess },
	emitResponse,
} ) => {
	useEffect( () => {
		onPaymentSetup( async () => {
			const paymentMethodData = getPaymentMethodData();

			const fraudPreventionToken = document
				.querySelector( '#wcpay-fraud-prevention-token' )
				?.getAttribute( 'value' );

			return {
				type: 'success',
				meta: {
					paymentMethodData: {
						...paymentMethodData,
						'wcpay-fraud-prevention-token':
							fraudPreventionToken ?? '',
					},
				},
			};
		} );
	}, [ onPaymentSetup ] );

	// Once the server has completed payment processing, confirm the intent of necessary.
	usePaymentCompleteHandler(
		api,
		stripe,
		elements,
		onCheckoutSuccess,
		emitResponse,
		false // No need to save a payment that has already been saved.
	);

	return <></>;
};
