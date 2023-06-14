// global Stripe, wcpayStripeSiteMessaging
function siteMessaging() {
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
	const paymentMessageElement = elements.create(
		'paymentMethodMessaging',
		options
	);
	paymentMessageElement.mount( '#payment-method-message' );

	const quantitySelector = document.querySelector( '.quantity input' );
	quantitySelector.addEventListener( 'change', ( event ) => {
		const newQuantity = event.target.value;

		paymentMessageElement.update( {
			amount: parseInt( price, 10 ) * newQuantity,
		} );
	} );
}

export default siteMessaging;
