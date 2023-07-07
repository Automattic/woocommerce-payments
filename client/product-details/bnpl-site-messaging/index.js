// global Stripe, wcpayStripeSiteMessaging
export const initializeBnplSiteMessaging = () => {
	const {
		productVariations,
		country,
		publishableKey,
		paymentMethods,
	} = window.wcpayStripeSiteMessaging;

	// eslint-disable-next-line no-undef
	const stripe = Stripe( publishableKey );
	const options = {
		amount: parseInt( productVariations.base_product.amount, 10 ) || 0,
		currency: productVariations.base_product.currency || 'USD',
		paymentMethodTypes: paymentMethods || [],
		countryCode: country, // Customer's country or base country of the store.
	};
	const paymentMessageElement = stripe
		.elements()
		.create( 'paymentMethodMessaging', options );
	paymentMessageElement.mount( '#payment-method-message' );

	return paymentMessageElement;
};
