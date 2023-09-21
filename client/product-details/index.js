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
	const {
		amount: productAmount = 0,
		currency: productCurrency,
	} = productVariations[ productId ];
	const QUANTITY_INPUT_SELECTOR = '.quantity input[type=number]';
	const SINGLE_VARIATION_SELECTOR = '.single_variation_wrap';
	const VARIATIONS_SELECTOR = '.variations';
	const RESET_VARIATIONS_SELECTOR = '.reset_variations';

	const quantityInput = $( QUANTITY_INPUT_SELECTOR );
	const bnplPaymentMessageElement = initializeBnplSiteMessaging();

	// Utility function to safely parse integers and handle NaN
	const parseFloatOrReturnZero = ( value ) => {
		const result = parseFloat( value, 10 );
		return isNaN( result ) ? 0 : result;
	};

	const updateBnplPaymentMessage = ( amount, currency ) => {
		if ( amount <= 0 || ! currency ) {
			return;
		}
		bnplPaymentMessageElement.update( { amount, currency } );
	};

	const updateMessageWithQuantity = ( amount, quantity ) => {
		updateBnplPaymentMessage(
			parseFloatOrReturnZero( amount ) *
				parseFloatOrReturnZero( quantity ),
			productCurrency
		);
	};

	const resetBnplPaymentMessage = () => {
		if ( ! quantityInput.length || ! productVariations[ productId ] ) {
			return;
		}

		updateMessageWithQuantity( productAmount, quantityInput.val() );
	};

	// Update BNPL message based on the quantity change
	quantityInput.on( 'change', ( event ) => {
		updateMessageWithQuantity( productAmount, event.target.value );
	} );

	// Handle BNPL messaging for variable products.
	if ( Object.keys( productVariations ).length > 1 ) {
		// Update BNPL message based on product variation
		$( SINGLE_VARIATION_SELECTOR ).on(
			'show_variation',
			( event, variation ) => {
				if (
					! quantityInput.length ||
					! productVariations[ variation.variation_id ]
				) {
					return;
				}

				updateMessageWithQuantity(
					productVariations[ variation.variation_id ]?.amount,
					quantityInput.val()
				);
			}
		);

		// Reset BNPL message if variation is changed back to default
		$( VARIATIONS_SELECTOR ).on( 'change', ( event ) => {
			if ( event.target.value === '' ) {
				resetBnplPaymentMessage();
			}
		} );

		// Reset BNPL message on variations reset
		$( RESET_VARIATIONS_SELECTOR ).on( 'click', resetBnplPaymentMessage );
	}
} );
