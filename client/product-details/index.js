/* global jQuery */
// global wcpayStripeSiteMessaging

/**
 * Internal dependencies
 */
import { initializeBnplSiteMessaging } from './bnpl-site-messaging';

jQuery( function ( $ ) {
	// Check for required configuration based on the global variable.
	if ( ! window.wcpayStripeSiteMessaging ) {
		return;
	}

	const { productVariations, productId } = window.wcpayStripeSiteMessaging;
	const bnplPaymentMessageElement = initializeBnplSiteMessaging();

	// Utility function to safely parse integers and handle NaN
	const safeParseInt = ( value ) => {
		const result = parseInt( value, 10 );
		return isNaN( result ) ? 0 : result;
	};

	const updateBnplPaymentMessage = ( amount, currency ) => {
		if ( amount > 0 && currency ) {
			bnplPaymentMessageElement.update( { amount, currency } );
		}
	};

	const quantityInput = $( '.quantity input[type=number]' );

	const resetBnplPaymentMessage = () => {
		if ( ! quantityInput.length || ! productVariations.base_product )
			return;

		const quantity = safeParseInt( quantityInput.val() );
		const baseProductAmount = safeParseInt(
			productVariations.base_product.amount
		);

		const amount = baseProductAmount * quantity;
		const currency = productVariations.base_product.currency;

		updateBnplPaymentMessage( amount, currency );
	};

	// Update BNPL message based on the quantity change
	quantityInput.on( 'change', ( event ) => {
		const newQuantity = safeParseInt( event.target.value );
		const price = safeParseInt( productVariations[ productId ].amount );

		const amount = price * newQuantity;
		const currency = productVariations[ productId ]?.currency;

		updateBnplPaymentMessage( amount, currency );
	} );

	// Handle BNPL messaging for variable products.
	if ( Object.keys( productVariations ).length > 1 ) {
		// Update BNPL message based on product variation
		$( '.single_variation_wrap' ).on(
			'show_variation',
			( event, variation ) => {
				if (
					! quantityInput.length ||
					! productVariations[ variation.variation_id ]
				)
					return;

				const quantity = safeParseInt( quantityInput.val() );
				const variationPrice = safeParseInt(
					productVariations[ variation.variation_id ].amount
				);

				const amount = variationPrice * quantity;
				const currency =
					productVariations[ variation.variation_id ]?.currency;

				updateBnplPaymentMessage( amount, currency );
			}
		);

		// Reset BNPL message if variation is changed back to default
		$( '.variations' ).on( 'change', ( event ) => {
			if ( event.target.value === '' ) resetBnplPaymentMessage();
		} );

		// Reset BNPL message on variations reset
		$( '.reset_variations' ).on( 'click', resetBnplPaymentMessage );
	}
} );
