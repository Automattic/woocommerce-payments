/* global jQuery */
// global Stripe, wcpayStripeSiteMessaging
function bnplSiteMessaging() {
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
		amount: parseInt( price, 10 ) || 0,
		currency: currency || 'USD',
		paymentMethodTypes: paymentMethods || [],
		countryCode: country, // Customer's country or base country of the store.
	};
	const paymentMessageElement = stripe
		.elements()
		.create( 'paymentMethodMessaging', options );
	paymentMessageElement.mount( '#payment-method-message' );

	const quantitySelector = document.querySelector( '.quantity input' );
	quantitySelector.addEventListener( 'change', ( event ) => {
		const newQuantity = event.target.value;
		paymentMessageElement.update( {
			amount: parseInt( price, 10 ) * newQuantity,
		} );
	} );

	jQuery( function ( $ ) {
		$( '.single_variation_wrap' ).on( 'show_variation', function (
			event,
			variation
		) {
			console.log( variation.display_price );
			paymentMessageElement.update( {
				amount: parseInt( variation.display_price, 10 ),
			} );
		} );
	} );
}

export default bnplSiteMessaging;
