/**
 * External dependencies
 */
import { useEffect, useState } from 'react';

export function useStripeAsync( api, forceAccountRequest = false ) {
	const [ stripe, setStripe ] = useState( null );

	useEffect( () => {
		( async () => {
			const initializedStripe = await api.getStripe(
				forceAccountRequest
			);
			setStripe( initializedStripe );
		} )();
	}, [ api, forceAccountRequest ] );

	return stripe;
}

export function useStripeForUPE( api, paymentMethodId ) {
	const [ stripe, setStripe ] = useState( null );

	useEffect( () => {
		( async () => {
			const stripeForUPE = await api.getStripeForUPE( paymentMethodId );
			setStripe( stripeForUPE );
		} )();
	}, [ api, paymentMethodId ] );

	return stripe;
}
