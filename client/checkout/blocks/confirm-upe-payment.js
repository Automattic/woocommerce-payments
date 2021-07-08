/**
 * Handles the confirmation of card payments (3DSv2 modals/SCA challenge).
 *
 * @param {WCPayAPI} api            The API used for connection both with the server and Stripe.
 * @param {Object}   paymentDetails Details about the payment, received from the server.
 * @param {Object}   paymentElement Reference to the UPE element mounted on the page.
 * @param {Object}   emitResponse   Various helpers for usage with observer response objects.
 * @return {Object}                An object, which contains the result from the action.
 */
export default async function confirmUPEPayment(
	api,
	paymentDetails,
	paymentElement,
	emitResponse
) {
	try {
		const redirectUrl = paymentDetails.redirect_url;
		const { error } = await api.getStripe().confirmPayment( {
			element: paymentElement,
			confirmParams: {
				return_url: redirectUrl,
			},
		} );
		if ( error ) {
			throw error;
		}
	} catch ( error ) {
		return {
			type: 'error',
			message: error.message,
			messageContext: emitResponse.noticeContexts.PAYMENTS,
		};
	}
}
