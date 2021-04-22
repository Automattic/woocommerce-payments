/**
 * External dependencies
 */
import { loadStripe } from '@stripe/stripe-js';
import { getPaymentRequestData } from '../utils';

/**
 * Internal dependencies
 */
import { getConfig } from 'utils/checkout';

const stripePromise = () =>
	new Promise( ( resolve ) => {
		try {
			resolve(
				loadStripe( getConfig( 'publishableKey' ), {
					stripeAccount: getConfig( 'accountId' ),
					locale: getPaymentRequestData( 'button' )?.locale,
				} )
			);
		} catch ( error ) {
			// In order to avoid showing console error publicly to users,
			// we resolve instead of rejecting when there is an error.
			resolve( { error } );
		}
	} );

export { stripePromise as loadStripe };
