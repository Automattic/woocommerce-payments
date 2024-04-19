/* global jQuery */

/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { applyFilters } from '@wordpress/hooks';

const paymentRequestCartInterface = {
	// Used on product pages to interact with an anonymous cart.
	// This anonymous cart is separate from the customer's cart, which might contain additional products.
	// This functionality is also useful to calculate product/shipping pricing (and shipping needs)
	// for compatibility scenarios with other plugins (like WC Bookings, Product Add-Ons, WC Deposits, etc.).
	cartRequestHeaders: {},

	init: ( {} ) => {},

	createAnonymousCart: async () => {
		const response = await apiFetch( {
			method: 'GET',
			path: '/wc/store/v1/cart',
			// omitting credentials, to create a new cart object separate from the user's cart.
			credentials: 'omit',
			// parse: false to ensure we can get the response headers
			parse: false,
		} );

		paymentRequestCartInterface.cartRequestHeaders = {
			Nonce: response.headers.get( 'Nonce' ),
			'Cart-Token': response.headers.get( 'Cart-Token' ),
		};
	},

	addProductToCart: async () => {
		const productData = {
			// can be modified in case of variable products, WC bookings plugin, etc.
			id: jQuery( '.single_add_to_cart_button' ).val(),
			qty: parseInt( jQuery( '.quantity .qty' ).val(), 10 ) || 1,
			// can be modified in case of variable products, WC bookings plugin, etc.
			variation: [],
		};

		return apiFetch( {
			method: 'POST',
			path: '/wc/store/v1/cart/add-item',
			credentials: 'omit',
			headers: {
				...paymentRequestCartInterface.cartRequestHeaders,
			},
			data: applyFilters(
				'wcpay.payment-request.cart-add-item',
				productData
			),
		} );
	},
};

export default paymentRequestCartInterface;
