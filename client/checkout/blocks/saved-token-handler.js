/**
 * Internal dependencies
 */
import { useEffect } from 'react';
import { usePaymentCompleteHandler } from './hooks';
import { useSelect } from '@wordpress/data';

export const SavedTokenHandler = ( {
	api,
	eventRegistration: { onPaymentSetup, onCheckoutSuccess },
	emitResponse,
} ) => {
	const paymentMethodData = useSelect( ( select ) => {
		const store = select( 'wc/store/payment' );
		return store.getPaymentMethodData();
	} );

	useEffect( () => {
		return onPaymentSetup( () => {
			const fraudPreventionToken = window.wcpayFraudPreventionToken;

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
		onCheckoutSuccess,
		emitResponse,
		false // No need to save a payment that has already been saved.
	);

	return <></>;
};
