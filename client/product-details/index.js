/* global jQuery */
// global wcpayStripeSiteMessaging

/**
 * Internal dependencies
 */
import { initializeBnplSiteMessaging } from './bnpl-site-messaging';

jQuery( async function ( $ ) {
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
		amount: baseProductAmount = 0,
		currency: productCurrency,
	} = productVariations[ productId ];
	const QUANTITY_INPUT_SELECTOR = '.quantity input[type=number]';
	const SINGLE_VARIATION_SELECTOR = '.single_variation_wrap';
	const VARIATIONS_SELECTOR = '.variations';
	const RESET_VARIATIONS_SELECTOR = '.reset_variations';
	const VARIATION_ID_SELECTOR = 'input[name="variation_id"]';

	const quantityInput = $( QUANTITY_INPUT_SELECTOR );
	const bnplPaymentMessageElement = await initializeBnplSiteMessaging();
	const hasVariations = Object.keys( productVariations ).length > 1;

	/**
	 * Safely parses a given value to an integer number.
	 * If the parsed value is NaN, the function returns 0.
	 *
	 * @param {string|number} value - The value to be parsed to integer number.
	 * @return {number} The parsed number, or 0 if the parsed value is NaN.
	 */
	const parseIntOrReturnZero = ( value ) => {
		const result = parseInt( value, 10 );
		return isNaN( result ) ? 0 : result;
	};

	/**
	 * Updates the BNPL payment message displayed on the page.
	 * The function takes an amount, a currency, and an optional quantity.
	 * If the amount is less than or equal to zero, or if the currency is not provided,
	 * the function will exit early without making updates.
	 *
	 * @param {number} amount - The total amount for the BNPL message.
	 * @param {string} currency - The currency code (e.g., 'USD', 'EUR') for the BNPL message.
	 * @param {number} [quantity=1] - The quantity of the product being purchased. Defaults to 1.
	 */
	const updateBnplPaymentMessage = ( amount, currency, quantity = 1 ) => {
		const totalAmount =
			parseIntOrReturnZero( amount ) * parseIntOrReturnZero( quantity );

		if ( totalAmount <= 0 || ! currency ) {
			return;
		}

		bnplPaymentMessageElement.update( { amount: totalAmount, currency } );
	};

	/**
	 * Resets the BNPL payment message displayed on the page.
	 * The function updates the BNPL message using the global `baseProductAmount` and the current value
	 * from `quantityInput` by calling `updateBnplPaymentMessage`.
	 */
	const resetBnplPaymentMessage = () => {
		updateBnplPaymentMessage(
			baseProductAmount,
			productCurrency,
			quantityInput.val()
		);
	};

	// Update BNPL message based on the quantity change
	quantityInput.on( 'change', ( event ) => {
		let amount = baseProductAmount;
		const variationId = $( VARIATION_ID_SELECTOR ).val();

		// If the product has variations, get the amount from the selected variation.
		if (
			hasVariations &&
			productVariations.hasOwnProperty( variationId )
		) {
			amount = productVariations[ variationId ]?.amount;
		}

		updateBnplPaymentMessage( amount, productCurrency, event.target.value );
	} );

	// Handle BNPL messaging for variable products.
	if ( hasVariations ) {
		// Update BNPL message based on product variation
		$( SINGLE_VARIATION_SELECTOR ).on(
			'show_variation',
			( event, variation ) => {
				if ( ! productVariations[ variation.variation_id ] ) {
					return;
				}

				updateBnplPaymentMessage(
					productVariations[ variation.variation_id ].amount,
					productCurrency,
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
