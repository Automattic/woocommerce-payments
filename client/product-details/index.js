/* global jQuery */
// global wcpayStripeSiteMessaging

/**
 * Internal dependencies
 */
import { initializeBnplSiteMessaging } from './bnpl-site-messaging';

jQuery( function ( $ ) {
	const bnplPaymentMessageElement = initializeBnplSiteMessaging();
	const { productVariations } = window.wcpayStripeSiteMessaging;
	let { productId } = window.wcpayStripeSiteMessaging;

	const resetBnplPaymentMessage = () => {
		const quantity = $( '.quantity input' ).val();
		productId = 'base_product';
		bnplPaymentMessageElement.update( {
			amount:
				parseInt( productVariations.base_product.amount, 10 ) *
				quantity,
			currency: productVariations.base_product.currency,
		} );
	};

	$( '.quantity input' ).on( 'change', function ( event ) {
		const newQuantity = event.target.value;
		const price = productVariations[ productId ].amount;
		bnplPaymentMessageElement.update( {
			amount: parseInt( price, 10 ) * newQuantity,
			currency: productVariations[ productId ].currency,
		} );
	} );

	// Handle BNPL messaging for variable products.
	if ( Object.keys( productVariations ).length > 1 ) {
		$( '.single_variation_wrap' ).on( 'show_variation', function (
			event,
			variation
		) {
			const quantity = $( '.quantity input' ).val();
			const variationPrice =
				productVariations[ variation.variation_id ].amount;
			productId = variation.variation_id;
			bnplPaymentMessageElement.update( {
				amount: parseInt( variationPrice, 10 ) * quantity,
				currency: productVariations[ variation.variation_id ].currency,
			} );
		} );

		// If variation is changed back to default, reset BNPL messaging.
		$( '.variations' ).on( 'change', function ( event ) {
			if ( event.target.value === '' ) resetBnplPaymentMessage();
		} );

		$( '.reset_variations' ).on( 'click', resetBnplPaymentMessage );
	}
} );
