export default async function confirmCardPayment( api, paymentDetails ) {
	const { redirect } = paymentDetails;

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
}
