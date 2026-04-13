/**
 * External dependencies
 */
import type { Stripe, StripeElements } from '@stripe/stripe-js';

export interface PaymentCredentialResult {
	id: string;
	type: 'confirmation_token' | 'payment_method';
}

/**
 * Creates a payment credential (either confirmation token or payment method)
 * based on the useConfirmationTokens flag.
 *
 * @throws The Stripe error if credential creation fails.
 */
export async function createPaymentCredential(
	stripe: Stripe,
	elements: StripeElements,
	useConfirmationTokens: boolean
): Promise< PaymentCredentialResult > {
	if ( useConfirmationTokens ) {
		const { confirmationToken, error } =
			await stripe.createConfirmationToken( { elements } );
		if ( error ) {
			throw error;
		}
		return { id: confirmationToken!.id, type: 'confirmation_token' };
	}

	const { paymentMethod, error } = await stripe.createPaymentMethod( {
		elements,
	} );
	if ( error ) {
		throw error;
	}
	return { id: paymentMethod!.id, type: 'payment_method' };
}
