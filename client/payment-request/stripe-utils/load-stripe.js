/**
 * External dependencies
 */
import { loadStripe } from '@stripe/stripe-js';

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
				} )
			);
		} catch ( error ) {
			// In order to avoid showing console error publicly to users,
			// we resolve instead of rejecting when there is an error.
			resolve( { error } );
		}
	} );

export { stripePromise as loadStripe };
