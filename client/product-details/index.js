/* global jQuery */
// global wcpayStripeSiteMessaging

/**
 * Internal dependencies
 */
import { initializeBnplSiteMessaging } from './bnpl-site-messaging';

jQuery( function ( $ ) {
	// Check for required global variables
	if ( ! window.wcpayStripeSiteMessaging ) {
		return;
	}
	const bnplPaymentMessageElement = initializeBnplSiteMessaging();
	const { productVariations } = window.wcpayStripeSiteMessaging;
	let { productId } = window.wcpayStripeSiteMessaging;

	const resetBnplPaymentMessage = () => {
		const quantityInput = $( '.quantity input[type=number]' );
		const quantity = quantityInput.length
			? parseInt( quantityInput.val(), 10 )
			: 1;
		const baseProductAmount = productVariations.base_product
			? parseInt( productVariations.base_product.amount, 10 )
			: 0;

		productId = 'base_product';
		bnplPaymentMessageElement.update( {
			amount: baseProductAmount * quantity,
			currency: productVariations.base_product?.currency || 'USD',
		} );
	};

	$( '.quantity input[type=number]' ).on( 'change', function ( event ) {
		const newQuantity = parseInt( event.target.value, 10 ) || 1;
		const price = productVariations[ productId ]
			? parseInt( productVariations[ productId ].amount, 10 )
			: 0;

		bnplPaymentMessageElement.update( {
			amount: price * newQuantity,
			currency: productVariations[ productId ]?.currency || 'USD',
		} );
	} );

	// Handle BNPL messaging for variable products.
	if ( Object.keys( productVariations ).length > 1 ) {
		$( '.single_variation_wrap' ).on( 'show_variation', function (
			event,
			variation
		) {
			const quantityInput = $( '.quantity input[type=number]' );
			const quantity = quantityInput.length
				? parseInt( quantityInput.val(), 10 )
				: 1;
			const variationPrice = productVariations[ variation.variation_id ]
				? parseInt(
						productVariations[ variation.variation_id ].amount,
						10
				  )
				: 0;

			productId = variation.variation_id;
			bnplPaymentMessageElement.update( {
				amount: variationPrice * quantity,
				currency:
					productVariations[ variation.variation_id ]?.currency ||
					'USD',
			} );
		} );

		// If variation is changed back to default, reset BNPL messaging.
		$( '.variations' ).on( 'change', function ( event ) {
			if ( event.target.value === '' ) resetBnplPaymentMessage();
		} );

		$( '.reset_variations' ).on( 'click', resetBnplPaymentMessage );
	}
} );
