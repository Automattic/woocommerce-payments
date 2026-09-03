/**
 * Creates a payment credential (either confirmation token or payment method)
 * based on the `useConfirmationTokens` argument.
 *
 * `setupFutureUsage` is read back off the token rather than echoed from whatever Elements was
 * configured with, so the server learns what Stripe actually recorded. A PaymentMethod carries
 * no equivalent, so that branch reports null.
 *
 * @param {Object}  stripe                The Stripe instance.
 * @param {Object}  elements              The Stripe Elements instance.
 * @param {boolean} useConfirmationTokens Whether to use confirmation tokens.
 * @throws The Stripe error if credential creation fails.
 * @return {Promise<{id: string, setupFutureUsage: string|null}>} The credential id, and the
 *                                                                `setup_future_usage` it carries.
 */
export async function createPaymentCredential(
	stripe,
	elements,
	useConfirmationTokens
) {
	if ( useConfirmationTokens ) {
		const { confirmationToken, error } =
			await stripe.createConfirmationToken( { elements } );
		if ( error ) {
			throw error;
		}
		return {
			id: confirmationToken.id,
			setupFutureUsage: confirmationToken.setup_future_usage ?? null,
		};
	}

	const { paymentMethod, error } = await stripe.createPaymentMethod( {
		elements,
	} );
	if ( error ) {
		throw error;
	}

	return { id: paymentMethod.id, setupFutureUsage: null };
}
