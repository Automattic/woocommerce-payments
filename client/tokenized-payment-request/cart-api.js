/* global jQuery */

/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { applyFilters } from '@wordpress/hooks';

export default class PaymentRequestCartApi {
	// Used on product pages to interact with an anonymous cart.
	// This anonymous cart is separate from the customer's cart, which might contain additional products.
	// This functionality is also useful to calculate product/shipping pricing (and shipping needs)
	// for compatibility scenarios with other plugins (like WC Bookings, Product Add-Ons, WC Deposits, etc.).
	cartRequestHeaders = {};

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
	 * @return {Promise} Result of the order creation request.
	 */
	async placeOrder( paymentData ) {
		return await apiFetch( {
			method: 'POST',
			path: '/wc/store/v1/checkout',
			credentials: 'omit',
			headers: {
				'X-WooPayments-Express-Payment-Request': true,
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
		return await apiFetch( {
			method: 'GET',
			path: '/wc/store/v1/cart',
			headers: {
				...this.cartRequestHeaders,
			},
		} );
	}

	/**
	 * Creates and returns a new cart object. The response type is the same as `getCart()`.
	 *
	 * @return {Promise} Cart response object.
	 */
	async createAnonymousCart() {
		const response = await apiFetch( {
			method: 'GET',
			path: '/wc/store/v1/cart',
			// omitting credentials, to create a new cart object separate from the user's cart.
			credentials: 'omit',
			// parse: false to ensure we can get the response headers
			parse: false,
		} );

		this.cartRequestHeaders = {
			Nonce: response.headers.get( 'Nonce' ),
			'Cart-Token': response.headers.get( 'Cart-Token' ),
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
	 * @return {Promise} Cart Response on success, or an Error Response on failure.
	 */
	async updateCustomer( customerData ) {
		return apiFetch( {
			method: 'POST',
			path: '/wc/store/v1/cart/update-customer',
			credentials: 'omit',
			headers: {
				'X-WooPayments-Express-Payment-Request': true,
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
	 * @return {Promise} Cart Response on success, or an Error Response on failure.
	 */
	async selectShippingRate( shippingRate ) {
		return apiFetch( {
			method: 'POST',
			path: '/wc/store/v1/cart/select-shipping-rate',
			credentials: 'omit',
			headers: {
				...this.cartRequestHeaders,
			},
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

		return apiFetch( {
			method: 'POST',
			path: '/wc/store/v1/cart/add-item',
			credentials: 'omit',
			headers: {
				...this.cartRequestHeaders,
			},
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
			const cartData = await apiFetch( {
				method: 'GET',
				path: '/wc/store/v1/cart',
				credentials: 'omit',
				headers: {
					...this.cartRequestHeaders,
				},
			} );

			const removeItemsPromises = cartData.items.map( ( item ) => {
				return apiFetch( {
					method: 'POST',
					path: '/wc/store/v1/cart/remove-item',
					credentials: 'omit',
					headers: {
						...this.cartRequestHeaders,
					},
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
