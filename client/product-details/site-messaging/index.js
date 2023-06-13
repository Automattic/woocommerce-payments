// global Stripe, wcpayStripeSiteMessaging
function SiteMessaging() {
	const {
		price,
		currency,
		country,
		publishableKey,
		paymentMethods,
	} = window.wcpayStripeSiteMessaging;

	// eslint-disable-next-line no-undef
	const stripe = Stripe( publishableKey );
	const options = {
		amount: parseInt( price, 10 ) || 0, // In cents TODO: use quantity selector
		currency: currency || 'USD',
		paymentMethodTypes: paymentMethods || [],
		countryCode: country, // Customer's country or base country of the store.
	};
	const elements = stripe.elements();
	const PaymentMessageElement = elements.create(
		'paymentMethodMessaging',
		options
	);
	PaymentMessageElement.mount( '#payment-method-message' );
}

export default SiteMessaging;
