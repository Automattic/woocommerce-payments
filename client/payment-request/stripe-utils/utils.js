/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { normalizeLineItems } from '../utils';

/**
 * @typedef {import('./type-defs').StripePaymentItem} StripePaymentItem
 * @typedef {import('./type-defs').StripePaymentRequest} StripePaymentRequest
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').PreparedCartTotalItem} CartTotalItem
 */

/**
 * The total PaymentItem object used for the stripe PaymentRequest object.
 *
 * @param {CartTotalItem} total  The total amount.
 *
 * @return {StripePaymentItem} The PaymentItem object used for stripe.
 */
const getTotalPaymentItem = ( total ) => {
	return {
		label:
			// - TODO: Get total label
			// getStripeServerData().stripeTotalLabel ||
			__( 'Total', 'woocommerce-payments' ),
		amount: total.value,
	};
};

/**
 * Returns a stripe payment request object
 *
 * @param {Object}          config                  A configuration object for
 *                                                  getting the payment request.
 * @param {Object}          config.stripe           The stripe api.
 * @param {CartTotalItem}   config.total            The amount for the total
 *                                                  (in subunits) provided by
 *                                                  checkout/cart.
 * @param {string}          config.currencyCode     The currency code provided
 *                                                  by checkout/cart.
 * @param {string}          config.countryCode      The country code provided by
 *                                                  checkout/cart.
 * @param {boolean}         config.shippingRequired Whether or not shipping is
 *                                                  required.
 * @param {CartTotalItem[]} config.cartTotalItems   Array of line items provided
 *                                                  by checkout/cart.
 *
 * @return {StripePaymentRequest} A stripe payment request object
 */
const getPaymentRequest = ( {
	stripe,
	total,
	currencyCode,
	countryCode,
	shippingRequired,
	cartTotalItems,
} ) => {
	const options = {
		total: getTotalPaymentItem( total ),
		currency: currencyCode,
		country: countryCode || 'US',
		requestPayerName: true,
		requestPayerEmail: true,
		requestPayerPhone: true,
		requestShipping: shippingRequired,
		displayItems: normalizeLineItems( cartTotalItems ),
	};
	return stripe.paymentRequest( options );
};

/**
 * Utility function for updating the Stripe PaymentRequest object
 *
 * @param {Object}               update                An object containing the
 *                                                     things needed for the
 *                                                     update
 * @param {StripePaymentRequest} update.paymentRequest A Stripe payment request
 *                                                     object
 * @param {CartTotalItem}        update.total          A total line item.
 * @param {string}               update.currencyCode   The currency code for the
 *                                                     amount provided.
 * @param {CartTotalItem[]}      update.cartTotalItems An array of line items
 *                                                     provided by the
 *                                                     cart/checkout.
 */
const updatePaymentRequest = ( {
	paymentRequest,
	total,
	currencyCode,
	cartTotalItems,
} ) => {
	paymentRequest.update( {
		total: getTotalPaymentItem( total ),
		currency: currencyCode,
		displayItems: normalizeLineItems( cartTotalItems ),
	} );
};

/**
 * Returns whether or not the current session can make payments and what type of request it uses.
 *
 * @param {StripePaymentRequest} paymentRequest A Stripe PaymentRequest instance.
 *
 * @return {Promise<Object>} Object containing canPay and the requestType, which can be either
 * - payment_request_api
 * - apple_pay
 * - google_pay
 */
const canDoPaymentRequest = ( paymentRequest ) => {
	return new Promise( ( resolve ) => {
		paymentRequest.canMakePayment().then( ( result ) => {
			if ( result ) {
				let paymentRequestType = 'payment_request_api';
				if ( result.applePay ) {
					paymentRequestType = 'apple_pay';
				} else if ( result.googlePay ) {
					paymentRequestType = 'google_pay';
				}

				resolve( { canPay: true, requestType: paymentRequestType } );
				return;
			}
			resolve( { canPay: false } );
		} );
	} );
};

export {
	getTotalPaymentItem,
	getPaymentRequest,
	updatePaymentRequest,
	canDoPaymentRequest,
};
