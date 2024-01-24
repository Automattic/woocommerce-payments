/**
 * Handles the confirmation of card payments (3DSv2 modals/SCA challenge).
 *
 * @param {WCPayAPI} api               The API used for connection both with the server and Stripe.
 * @param {Object}   paymentDetails    Details about the payment, received from the server.
 * @param {Object}   emitResponse      Various helpers for usage with observer response objects.
 * @param {boolean}  shouldSavePayment Indicates whether the payment method should be saved or not.
 * @return {Object}                An object, which contains the result from the action.
 */
export default async function confirmCardPayment(
	api,
	paymentDetails,
	emitResponse,
	shouldSavePayment
) {
	const { redirect, payment_method: paymentMethod } = paymentDetails;

	try {
		const confirmationRequest = api.confirmIntent(
			redirect,
			shouldSavePayment ? paymentMethod : null
		);

		// `true` means there is no intent to confirm.
		if ( confirmationRequest === true ) {
			return {
				type: 'success',
				redirectUrl: redirect,
			};
		}

		const finalRedirect = await confirmationRequest;
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
