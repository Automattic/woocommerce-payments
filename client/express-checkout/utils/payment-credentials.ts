/**
 * External dependencies
 */
import type { Stripe, StripeElements } from '@stripe/stripe-js';

/**
 * Creates a payment credential (either confirmation token or payment method)
 * based on the `useConfirmationTokens` argument.
 *
 * @throws The Stripe error if credential creation fails.
 */
export async function createPaymentCredential(
	stripe: Stripe,
	elements: StripeElements,
	useConfirmationTokens: boolean
): Promise< string > {
	if ( useConfirmationTokens ) {
		const {
			confirmationToken,
			error,
		} = await stripe.createConfirmationToken( { elements } );
		if ( error ) {
			throw error;
		}
		return confirmationToken!.id;
	}

	const { paymentMethod, error } = await stripe.createPaymentMethod( {
		elements,
	} );
	if ( error ) {
		throw error;
	}
	return paymentMethod!.id;
}
