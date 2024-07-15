/* global jQuery */

/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { applyFilters } from '@wordpress/hooks';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { getPaymentRequestData } from './frontend-utils';

export default class PaymentRequestCartApi {
	// Used on product pages to interact with an anonymous cart.
	// This anonymous cart is separate from the customer's cart, which might contain additional products.
	// This functionality is also useful to calculate product/shipping pricing (and shipping needs)
	// for compatibility scenarios with other plugins (like WC Bookings, Product Add-Ons, WC Deposits, etc.).
	cartRequestHeaders = {};

	/**
	 * Makes a request to the API.
	 *
	 * @param {Object} options The options to pass to `apiFetch`.
	 * @return {Promise} Result from `apiFetch`.
	 */
	async _request( options ) {
		return await apiFetch( {
			...options,
			path: addQueryArgs( options.path, {
				// `wcpayPaymentRequestParams` will always be defined if this file is needed.
				// If there's an issue with it, ask yourself why this file is queued and `wcpayPaymentRequestParams` isn't present.
				currency: getPaymentRequestData(
					'checkout'
				).currency_code.toUpperCase(),
			} ),
			headers: {
				'X-WooPayments-Tokenized-Cart-Session-Nonce':
					getPaymentRequestData( 'button_context' ) === 'product'
						? getPaymentRequestData( 'nonce' )
								.tokenized_cart_session_nonce
						: undefined,
				...this.cartRequestHeaders,
				...options.headers,
			},
		} );
	}

	/**
	 * Creates an order from the cart object.
	 * See https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/StoreApi/docs/checkout.md#process-order-and-payment
	 *
	 * @param {{
	 *          billing_address: Object,
	 *          shipping_address: Object,
	 *          customer_note: string?,
	 *          payment_method: string,
	 *          payment_data: Array,
	 *        }} paymentData Additional payment data to place the order.
	 * @param {string} context The context in which the request is being made.
	 * @return {Promise} Result of the order creation request.
	 */
	async placeOrder( paymentData ) {
		return await this._request( {
			method: 'POST',
			path: '/wc/store/v1/checkout',
			headers: {
				'X-WooPayments-Express-Payment-Request': true,
				// either using the global nonce or the one cached from the anonymous cart (with the anonymous cart one taking precedence).
				'X-WooPayments-Express-Payment-Request-Nonce':
					getPaymentRequestData( 'nonce' ).tokenized_cart_nonce ||
					undefined,
				...this.cartRequestHeaders,
			},
			data: paymentData,
		} );
	}

	/**
	 * Returns the customer's cart object.
	 * See https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/StoreApi/docs/cart.md#get-cart
	 *
	 * @return {Promise} Cart response object.
	 */
	async getCart() {
		const response = await this._request( {
			method: 'GET',
			path: '/wc/store/v1/cart',
			parse: false,
		} );

		this.cartRequestHeaders = {
			Nonce: response.headers.get( 'Nonce' ),
		};

		return response.json();
	}

	/**
	 * Creates and returns a new cart object. The response type is the same as `getCart()`.
	 *
	 * @return {Promise} Cart response object.
	 */
	async createAnonymousCart() {
		const response = await this._request( {
			method: 'GET',
			path: '/wc/store/v1/cart',
			// parse: false to ensure we can get the response headers
			parse: false,
			headers: {
				// sending an empty value, so that the custom session handler is leveraged.
				'X-WooPayments-Tokenized-Cart-Session': '',
			},
		} );

		this.cartRequestHeaders = {
			Nonce: response.headers.get( 'Nonce' ),
			// saving the received value as a cart reference for future usage.
			'X-WooPayments-Tokenized-Cart-Session': response.headers.get(
				'X-WooPayments-Tokenized-Cart-Session'
			),
			'X-WooPayments-Express-Payment-Request-Nonce': response.headers.get(
				'X-WooPayments-Express-Payment-Request-Nonce'
			),
		};
	}

	/**
	 * Update customer data and return the full cart response, or an error.
	 * See https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/StoreApi/docs/cart.md#update-customer
	 *
	 * @param {{
	 *          billing_address: Object?,
	 *          shipping_address: Object?,
	 *        }} customerData Customer data to update.
	 * @param {string} context The context in which the request is being made.
	 * @return {Promise} Cart Response on success, or an Error Response on failure.
	 */
	async updateCustomer( customerData ) {
		return await this._request( {
			method: 'POST',
			path: '/wc/store/v1/cart/update-customer',
			headers: {
				'X-WooPayments-Express-Payment-Request': true,
				// either using the global nonce or the one cached from the anonymous cart (with the anonymous cart one taking precedence).
				'X-WooPayments-Express-Payment-Request-Nonce':
					getPaymentRequestData( 'nonce' ).tokenized_cart_nonce ||
					undefined,
				...this.cartRequestHeaders,
			},
			data: customerData,
		} );
	}

	/**
	 * Selects an available shipping rate for a package, then returns the full cart response, or an error
	 * See https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/StoreApi/docs/cart.md#select-shipping-rate
	 *
	 * @param {{rate_id: string, package_id: integer}} shippingRate The selected shipping rate.
	 * @param {string} context The context in which the request is being made.
	 * @return {Promise} Cart Response on success, or an Error Response on failure.
	 */
	async selectShippingRate( shippingRate ) {
		return await this._request( {
			method: 'POST',
			path: '/wc/store/v1/cart/select-shipping-rate',
			data: shippingRate,
		} );
	}

	/**
	 * Add an item to the cart and return the full cart response, or an error.
	 * See https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/StoreApi/docs/cart.md#add-item
	 *
	 * @return {Promise} Cart Response on success, or an Error Response on failure.
	 */
	async addProductToCart() {
		const productData = {
			// can be modified in case of variable products, WC bookings plugin, etc.
			id: jQuery( '.single_add_to_cart_button' ).val(),
			quantity: parseInt( jQuery( '.quantity .qty' ).val(), 10 ) || 1,
			// can be modified in case of variable products, WC bookings plugin, etc.
			variation: [],
		};

		return await this._request( {
			method: 'POST',
			path: '/wc/store/v1/cart/add-item',
			data: applyFilters(
				'wcpay.payment-request.cart-add-item',
				productData
			),
		} );
	}

	/**
	 * Removes all items from the cart and clears the cart headers.
	 * See https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/StoreApi/docs/cart.md#remove-item
	 *
	 * @return {undefined}
	 */
	async emptyCart() {
		try {
			// TODO: this could be optimized, if we could cache the previous request to cart data.
			const cartData = await this._request( {
				method: 'GET',
				path: '/wc/store/v1/cart',
			} );

			const removeItemsPromises = cartData.items.map( ( item ) => {
				return this._request( {
					method: 'POST',
					path: '/wc/store/v1/cart/remove-item',
					data: {
						key: item.key,
					},
				} );
			} );

			await Promise.all( removeItemsPromises );
		} catch ( e ) {
			// let's ignore the error, it's likely not going to be relevant.
		}
	}
}
