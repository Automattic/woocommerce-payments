/**
 * Internal dependencies
 */
import { useEffect } from 'react';
import { usePaymentCompleteHandler } from './hooks';
import { useSelect } from '@wordpress/data';
import { removeLinkButton } from '../stripe-link';

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
		stripe,
		elements,
		onCheckoutSuccess,
		emitResponse,
		false // No need to save a payment that has already been saved.
	);

	// Once saved token component is loaded, Stripe Link button should be removed,
	// because payment elements are not used then and there's no element to attach the button to.
	removeLinkButton();

	return <></>;
};
