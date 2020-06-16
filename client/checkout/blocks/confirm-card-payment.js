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
		}
	}
}
