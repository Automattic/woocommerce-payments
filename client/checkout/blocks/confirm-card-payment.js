/**
 * Handles the confirmation of card payments (3DSv2 modals/SCA challenge).
 *
 * @param {WCPayAPI} api            The API used for connection both with the server and Stripe.
 * @param {Object}   paymentDetails Details about the payment, received from the server.
 * @returns {Object}                An object, which contains the result from the action.
 */
export default async function confirmCardPayment( api, paymentDetails ) {
	const { redirect } = paymentDetails;

	try {
		const confirmation = api.confirmIntent( redirect );

		// `true` means there is no intent to confirm.
		if ( true === confirmation ) {
			return {
				type: 'success',
				redirectUrl: redirect,
			};
		}

		const finalRedirect = await confirmation;
		return {
			type: 'success',
			redirectUrl: finalRedirect,
		};
	} catch ( error ) {
		return {
			type: 'error',
			message: error.message,
		};
	}
}
