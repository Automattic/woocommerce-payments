/**
 * Creates a payment credential (either confirmation token or payment method)
 * based on the `useConfirmationTokens` argument.
 *
 * @param {Object} stripe The Stripe instance.
 * @param {Object} elements The Stripe Elements instance.
 * @param {boolean} useConfirmationTokens Whether to use confirmation tokens.
 * @throws The Stripe error if credential creation fails.
 */
export async function createPaymentCredential(
	stripe,
	elements,
	useConfirmationTokens
) {
	if ( useConfirmationTokens ) {
		const {
			confirmationToken,
			error,
		} = await stripe.createConfirmationToken( { elements } );
		if ( error ) {
			throw error;
		}
		return confirmationToken.id;
	}

	const { paymentMethod, error } = await stripe.createPaymentMethod( {
		elements,
	} );
	if ( error ) {
		throw error;
	}
	return paymentMethod.id;
}
