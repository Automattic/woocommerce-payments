// global Stripe, wcpayStripeSiteMessaging
export const initializeBnplSiteMessaging = () => {
	const {
		productId,
		currency,
		productPrices,
		country,
		publishableKey,
		paymentMethods,
	} = window.wcpayStripeSiteMessaging;

	// eslint-disable-next-line no-undef
	const stripe = Stripe( publishableKey );
	const options = {
		amount: parseInt( productPrices[ productId ], 10 ) || 0,
		currency: currency || 'USD',
		paymentMethodTypes: paymentMethods || [],
		countryCode: country, // Customer's country or base country of the store.
	};
	const paymentMessageElement = stripe
		.elements()
		.create( 'paymentMethodMessaging', options );
	paymentMessageElement.mount( '#payment-method-message' );

	return paymentMessageElement;
};
