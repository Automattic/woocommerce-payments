/**
 * Handles the confirmation of card payments (3DSv2 modals/SCA challenge).
 *
 * @param {WCPayAPI} api            The API used for connection both with the server and Stripe.
 * @param {string}   redirectUrl    The URL to redirect to after confirming the intent on Stripe.
 * @param {Object}   paymentElement Reference to the UPE element mounted on the page.
 * @param {Object}   billingData    An object containing the customer's billing data.
 * @param {Object}   emitResponse   Various helpers for usage with observer response objects.
 * @return {Object}                An object, which contains the result from the action.
 */
export default async function confirmUPEPayment(
	api,
	redirectUrl,
	paymentElement,
	billingData,
	emitResponse
) {
	const name = `${ billingData.first_name } ${ billingData.last_name }`.trim();

	try {
		const confirmParams = {
			return_url: redirectUrl,
			payment_method_data: {
				billing_details: {
					name,
					email: billingData.email,
					phone: billingData.phone,
					address: {
						country: billingData.country,
						postal_code: billingData.postcode,
						state: billingData.state,
						city: billingData.city,
						line1: billingData.address_1,
						line2: billingData.address_2,
					},
				},
			},
		};

		const { error } = await api.getStripe().confirmPayment( {
			element: paymentElement,
			confirmParams,
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
