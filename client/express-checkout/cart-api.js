/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { applyFilters } from '@wordpress/hooks';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { getExpressCheckoutData } from './utils';
import {
	getProductId,
	getQuantity,
} from 'wcpay/utils/wc-product-page-selectors';

export default class ExpressCheckoutCartApi {
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
		// Build defaults explicitly so we only include header keys when we
		// actually have a value. apiFetch / Fetch serialize `undefined` and
		// `null` to the literal strings "undefined" and "null" on the wire,
		// which breaks server-side nonce verification.
		const headerDefaults = {
			Nonce: getExpressCheckoutData( 'nonce' ).store_api_nonce,
		};

		const tokenizedCartNonce =
			getExpressCheckoutData( 'nonce' ).tokenized_cart_nonce;
		if ( tokenizedCartNonce ) {
			headerDefaults[ 'X-WooPayments-Tokenized-Cart-Nonce' ] =
				tokenizedCartNonce;
		}

		// The session nonce is only meaningful on PDP, where the custom
		// session handler is intended to engage and create an isolated
		// tokenized cart. Sending it on shortcode cart/checkout would replace
		// the customer's existing cart with an empty one.
		if ( getExpressCheckoutData( 'button_context' ) === 'product' ) {
			const sessionNonce =
				getExpressCheckoutData( 'nonce' ).tokenized_cart_session_nonce;
			if ( sessionNonce ) {
				headerDefaults[ 'X-WooPayments-Tokenized-Cart-Session-Nonce' ] =
					sessionNonce;
			}
		}

		const response = await apiFetch( {
			...options,
			parse: false,
			path: addQueryArgs( options.path, {
				// `wcpayExpressCheckoutParams` will always be defined if this file is needed.
				// If there's an issue with it, ask yourself why this file is queued and `wcpayExpressCheckoutParams` isn't present.
				currency:
					getExpressCheckoutData(
						'checkout'
					).currency_code.toUpperCase(),
			} ),
			headers: {
				...headerDefaults,
				...this.cartRequestHeaders,
				...options.headers,
			},
		} );

		// Only carry forward response headers we actually received. Reading
		// an absent header returns `null`, and assigning that null over the
		// `Nonce` default on the next request would serialize as "null" and
		// trigger `woocommerce_rest_missing_nonce`.
		const newCartRequestHeaders = {};
		const rotatedNonce = response.headers.get( 'Nonce' );
		if ( rotatedNonce ) {
			// used as a reference on shortcode cart/checkout pages, where the Nonce might not be automatically added to the request.
			newCartRequestHeaders.Nonce = rotatedNonce;
		}
		const cartSession = response.headers.get(
			'X-WooPayments-Tokenized-Cart-Session'
		);
		if ( cartSession !== null ) {
			// saving the received value as a cart reference for future usage. This value could be updated multiple times.
			newCartRequestHeaders[ 'X-WooPayments-Tokenized-Cart-Session' ] =
				cartSession;
		}
		this.cartRequestHeaders = newCartRequestHeaders;

		return response.json();
	}

	/**
	 * Creates an order from the cart object.
	 * See https://github.com/woocommerce/woocommerce/blob/trunk/docs/apis/store-api/
	 * resources-endpoints/checkout.md#process-order-and-payment
	 *
	 * @param {{
	 *          billing_address: Object,
	 *          shipping_address: Object,
	 *          customer_note: string|null,
	 *          payment_method: string,
	 *          payment_data: Array,
	 *        }} paymentData Additional payment data to place the order.
	 * @return {Promise} Result of the order creation request.
	 */
	async placeOrder( paymentData ) {
		return await this._request( {
			method: 'POST',
			path: '/wc/store/v1/checkout',
			headers: {
				'X-WooPayments-Tokenized-Cart': true,
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
		return await this._request( {
			method: 'GET',
			path: '/wc/store/v1/cart',
		} );
	}

	/**
	 * Creates and returns a new cart object. The response type is the same as `getCart()`.
	 */
	useSeparateCart() {
		this.cartRequestHeaders = {
			// sending an empty value w/ the next request, so that the custom session handler is leveraged to create a separate cart.
			'X-WooPayments-Tokenized-Cart-Session': '',
		};
	}

	/**
	 * Ensures that the cart is emptied right after making the request.
	 */
	deleteAfterRequest() {
		this.cartRequestHeaders = {
			'X-WooPayments-Tokenized-Cart-Is-Ephemeral-Cart': '1',
		};
	}

	/**
	 * Update customer data and return the full cart response, or an error.
	 * See https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/StoreApi/docs/cart.md#update-customer
	 *
	 * @param {{
	 *          billing_address: Object|null,
	 *          shipping_address: Object|null,
	 *        }} customerData Customer data to update.
	 * @return {Promise} Cart Response on success, or an Error Response on failure.
	 */
	async updateCustomer( customerData ) {
		return await this._request( {
			method: 'POST',
			path: '/wc/store/v1/cart/update-customer',
			headers: {
				'X-WooPayments-Tokenized-Cart': true,
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
			id: getProductId(),
			quantity: getQuantity(),
			// can be modified in case of variable products, WC bookings plugin, etc.
			variation: [],
		};

		return await this._request( {
			method: 'POST',
			path: '/wc/store/v1/cart/add-item',
			data: applyFilters(
				'wcpay.express-checkout.cart-add-item',
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
			// TODO: this could be optimized, if we could cache the previous request of cart data for later reference.
			let cartData = await this._request( {
				method: 'GET',
				path: '/wc/store/v1/cart',
			} );

			// in the case for product bundles removing one item from the cart might remove additional items,
			// so we need to remove one item at a time.
			while ( cartData.items.length > 0 ) {
				cartData = await this._request( {
					method: 'POST',
					path: '/wc/store/v1/cart/remove-item',
					data: {
						key: cartData.items[ 0 ].key,
					},
				} );
			}
		} catch ( e ) {
			// let's ignore the error, it's likely not going to be relevant.
		}
	}
}
