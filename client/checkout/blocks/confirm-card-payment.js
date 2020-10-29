/**
 * Handles the confirmation of card payments (3DSv2 modals/SCA challenge).
 *
 * @param {WCPayAPI} api            The API used for connection both with the server and Stripe.
 * @param {object}   paymentDetails Details about the payment, received from the server.
 * @param {object}   emitResponse   Various helpers for usage with observer response objects.
 * @returns {object}                An object, which contains the result from the action.
 */
export default async function confirmCardPayment(
	api,
	paymentDetails,
	emitResponse
) {
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

		// `confirmIntent` also returns `isOrderPage`, but that's not supported in blocks yet.
		const { request } = confirmation;

		const finalRedirect = await request;
		return {
			type: 'success',
			redirectUrl: finalRedirect,
		};
	} catch ( error ) {
		return {
			type: 'error',
			message: error.message,
			messageContext: emitResponse.noticeContexts.PAYMENTS,
		};
	}
}
