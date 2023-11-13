/**
 * Internal dependencies
 */
import { useEffect } from 'react';
import { usePaymentCompleteHandler } from './hooks';
import { useSelect } from '@wordpress/data';

export const SavedTokenHandler = ( {
	api,
	stripe,
	elements,
	eventRegistration: { onPaymentSetup, onCheckoutSuccess },
	emitResponse,
} ) => {
	const paymentMethodData = useSelect( ( select ) => {
		const store = select( 'wc/store/payment' );
		return store.getPaymentMethodData();
	} );

	useEffect( () => {
		return onPaymentSetup( () => {
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
	}, [ onPaymentSetup, paymentMethodData ] );

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
