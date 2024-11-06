/* global wcpayPaymentRequestParams */

/**
 * Internal dependencies
 */
import {
	transformCartDataForDisplayItems,
	transformPrice,
} from './transformers/wc-to-stripe';

/**
 * Retrieves payment request data from global variable.
 *
 * @param {string} key The object property key.
 * @return {mixed} Value of the object prop or null.
 */
export const getPaymentRequestData = ( key ) => {
	if (
		typeof wcpayPaymentRequestParams === 'object' &&
		wcpayPaymentRequestParams.hasOwnProperty( key )
	) {
		return wcpayPaymentRequestParams[ key ];
	}
	return null;
};

/**
 * Returns a Stripe payment request object.
 *
 * @param {Object} config A configuration object for getting the payment request.
 * @return {Object} Payment Request options object
 */
export const getPaymentRequest = ( { stripe, cartData, productData } ) => {
	// the country code defined here comes from the WC settings.
	// It might be interesting to ensure the country code coincides with the Stripe account's country,
	// as defined here: https://docs.stripe.com/js/payment_request/create
	let country = getPaymentRequestData( 'checkout' )?.country_code;

	// Puerto Rico (PR) is the only US territory/possession that's supported by Stripe.
	// Since it's considered a US state by Stripe, we need to do some special mapping.
	if ( country === 'PR' ) {
		country = 'US';
	}

	return stripe.paymentRequest( {
		country,
		requestPayerName: true,
		requestPayerEmail: true,
		requestPayerPhone: getPaymentRequestData( 'checkout' )
			?.needs_payer_phone,
		...( productData
			? {
					// we can't just pass `productData`, and we need a little bit of massaging for older data.
					currency: productData.currency,
					total: productData.total,
					displayItems: productData.displayItems,
					requestShipping: productData.needs_shipping,
			  }
			: {
					currency: cartData.totals.currency_code.toLowerCase(),
					total: {
						label: getPaymentRequestData( 'total_label' ),
						amount: transformPrice(
							parseInt( cartData.totals.total_price, 10 ) -
								parseInt(
									cartData.totals.total_refund || 0,
									10
								),
							cartData.totals
						),
					},
					requestShipping:
						getPaymentRequestData( 'button_context' ) ===
						'pay_for_order'
							? false
							: cartData.needs_shipping,
					displayItems: transformCartDataForDisplayItems( cartData ),
			  } ),
	} );
};

/**
 * Utility function for updating the Stripe PaymentRequest object
 *
 * @param {Object} update An object containing the things needed for the update.
 */
export const updatePaymentRequest = ( { paymentRequest, cartData } ) => {
	paymentRequest.update( {
		total: {
			label: getPaymentRequestData( 'total_label' ),
			amount: transformPrice(
				parseInt( cartData.totals.total_price, 10 ) -
					parseInt( cartData.totals.total_refund || 0, 10 ),
				cartData.totals
			),
		},
		displayItems: transformCartDataForDisplayItems( cartData ),
	} );
};

/**
 * Displays a `confirm` dialog which leads to a redirect.
 *
 * @param {string} paymentRequestType Can be either apple_pay, google_pay or payment_request_api.
 */
export const displayLoginConfirmationDialog = ( paymentRequestType ) => {
	if ( ! getPaymentRequestData( 'login_confirmation' ) ) {
		return;
	}

	let message = getPaymentRequestData( 'login_confirmation' )?.message;

	// Replace dialog text with specific payment request type "Apple Pay" or "Google Pay".
	message = message.replace(
		/\*\*.*?\*\*/,
		paymentRequestType === 'apple_pay' ? 'Apple Pay' : 'Google Pay'
	);

	// Remove asterisks from string.
	message = message.replace( /\*\*/g, '' );

	if ( confirm( message ) ) {
		// Redirect to my account page.
		window.location.href = getPaymentRequestData(
			'login_confirmation'
		)?.redirect_url;
	}
};

/**
 * Parses HTML error notice and returns single error message.
 *
 * @param {string} notice Error notice DOM HTML.
 * @return {string} Error message content
 */
export const getErrorMessageFromNotice = ( notice ) => {
	const div = document.createElement( 'div' );
	div.innerHTML = notice.trim();
	return div.firstChild ? div.firstChild.textContent : '';
};

/**
 * Searches object for matching key and returns corresponding property value from matched item.
 *
 * @param {Object} obj Object to search for key.
 * @param {string} key Key to match in object.
 * @param {string} property Property in object to return correct value.
 * @return {int|null} Value to return
 */
const getPropertyByKey = ( obj, key, property ) => {
	const foundItem = obj.find( ( item ) => item.key === key );
	return foundItem ? foundItem[ property ] : null;
};

/**
 * Transforms totals from cartDataContent into format expected by the Store API.
 *
 * @param {Object} cartDataContent cartData from content component
 * @return {Object} Cart totals object for Store API
 */
const constructCartDataContentTotals = ( cartDataContent ) => {
	const totals = {
		total_items: getPropertyByKey(
			cartDataContent.cartTotalItems,
			'total_items',
			'value'
		)?.toString(),
		total_items_tax: getPropertyByKey(
			cartDataContent.cartTotalItems,
			'total_tax',
			'value'
		)?.toString(),
		total_fees: getPropertyByKey(
			cartDataContent.cartTotalItems,
			'total_fees',
			'value'
		)?.toString(),
		total_fees_tax: getPropertyByKey(
			cartDataContent.cartTotalItems,
			'total_fees',
			'valueWithTax'
		)?.toString(),
		total_discount: getPropertyByKey(
			cartDataContent.cartTotalItems,
			'total_discount',
			'value'
		)?.toString(),
		total_discount_tax: getPropertyByKey(
			cartDataContent.cartTotalItems,
			'total_discount',
			'valueWithTax'
		)?.toString(),
		total_shipping: getPropertyByKey(
			cartDataContent.cartTotalItems,
			'total_shipping',
			'value'
		)?.toString(),
		total_shipping_tax: getPropertyByKey(
			cartDataContent.cartTotalItems,
			'total_shipping',
			'valueWithTax'
		)?.toString(),
		total_price: cartDataContent.cartTotal.value.toString(),
		total_tax: getPropertyByKey(
			cartDataContent.cartTotalItems,
			'total_tax',
			'value'
		)?.toString(),
		currency_code: cartDataContent.currency.code,
		currency_symbol: cartDataContent.currency.symbol,
		currency_minor_unit: cartDataContent.currency.minorUnit,
		currency_decimal_separator: cartDataContent.currency.decimalSeparator,
		currency_thousand_separator: cartDataContent.currency.thousandSeparator,
		currency_prefix: cartDataContent.currency.prefix,
		currency_suffix: cartDataContent.currency.suffix,
	};

	return totals;
};

/**
 * Transforms the cartData object to the format expected by the Store API. cartData is coming to the blocks Payment Request method
 * in two different formats: from the canMakePayment function and from the content component. This function takes in either format
 * and transforms it into the format expected by the Store API.
 *
 * @param {Object|null} cartDataCanMakePayment cartData from canMakePayment function.
 * @param {Object|null} cartDataContent cartData from content component.
 * @return {Object} Cart totals object.
 */
export const transformCartDataForStoreAPI = (
	cartDataCanMakePayment,
	cartDataContent
) => {
	let mappedCartData = {};

	if ( cartDataCanMakePayment ) {
		mappedCartData = {
			...cartDataCanMakePayment,
			items: cartDataCanMakePayment.cart.cartItems,
			totals: cartDataCanMakePayment.cartTotals,
			needs_shipping: cartDataCanMakePayment.cartNeedsShipping,
			shipping_rates: cartDataCanMakePayment.cart.shippingRates,
		};
	}

	if ( cartDataContent ) {
		mappedCartData = {
			items: cartDataContent.cartItems,
			totals: constructCartDataContentTotals( cartDataContent ),
			needs_shipping: cartDataContent.needsShipping,
			shipping_rates: cartDataContent.shippingRates,
			extensions: cartDataContent.extensions,
		};
	}

	return mappedCartData;
};
