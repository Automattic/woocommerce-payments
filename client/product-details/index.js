/* global jQuery */
// global wcpayStripeSiteMessaging

/**
 * Internal dependencies
 */
import { initializeBnplSiteMessaging } from './bnpl-site-messaging';

jQuery( function ( $ ) {
	/**
	 * Check for the existence of the `wcpayStripeSiteMessaging` variable on the window object.
	 * This variable holds the configuration for Stripe site messaging and contains the following keys:
	 *  - productId: The ID of the product.
	 *  - productVariations: Variations of the product.
	 *  - country: The country of the customer. Defaults to the store's country.
	 *  - publishableKey: The key used for Stripe's API calls.
	 *  - paymentMethods: Enabled BNPL payment methods.
	 *
	 * If this variable is not set, the script will exit early to prevent further execution.
	 */
	if ( ! window.wcpayStripeSiteMessaging ) {
		return;
	}

	const { productVariations, productId } = window.wcpayStripeSiteMessaging;
	const bnplPaymentMessageElement = initializeBnplSiteMessaging();

	// Utility function to safely parse integers and handle NaN
	const parseIntOrReturnZero = ( value ) => {
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
		if ( ! quantityInput.length || ! productVariations.base_product ) {
			return;
		}

		const quantity = parseIntOrReturnZero( quantityInput.val() );
		const baseProductAmount = parseIntOrReturnZero(
			productVariations.base_product.amount
		);

		const amount = baseProductAmount * quantity;
		const currency = productVariations.base_product.currency;

		updateBnplPaymentMessage( amount, currency );
	};

	// Update BNPL message based on the quantity change
	quantityInput.on( 'change', ( event ) => {
		const newQuantity = parseIntOrReturnZero( event.target.value );
		const price = parseIntOrReturnZero(
			productVariations[ productId ]?.amount
		);

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
				) {
					return;
				}

				const quantity = parseIntOrReturnZero( quantityInput.val() );
				const variationPrice = parseIntOrReturnZero(
					productVariations[ variation.variation_id ]?.amount
				);

				const amount = variationPrice * quantity;
				const currency =
					productVariations[ variation.variation_id ]?.currency;

				updateBnplPaymentMessage( amount, currency );
			}
		);

		// Reset BNPL message if variation is changed back to default
		$( '.variations' ).on( 'change', ( event ) => {
			if ( event.target.value === '' ) {
				resetBnplPaymentMessage();
			}
		} );

		// Reset BNPL message on variations reset
		$( '.reset_variations' ).on( 'click', resetBnplPaymentMessage );
	}
} );
