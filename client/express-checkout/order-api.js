/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { getExpressCheckoutData } from './utils';

export default class ExpressCheckoutOrderApi {
	// parameters used in every request, just in different ways.
	orderId;
	key;
	billingEmail = '';

	// needed to replay the cart data to the `placeOrder` endpoint when placing the order.
	cachedCartData = {};

	/**
	 * Creates an instance of class to query for order data.
	 *
	 * @param {string}  orderId      The order ID,
	 * @param {string}  key          The order key, used to verify the order ID.
	 * @param {string?} billingEmail The billing email address, used for guest orders.
	 */
	constructor( { orderId, key, billingEmail = '' } ) {
		this.orderId = orderId;
		this.key = key;
		this.billingEmail = billingEmail;
	}

	/**
	 * Creates an order from the cart object.
	 * See https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/StoreApi/docs/checkout-order.md
	 *
	 * @param {{
	 *          billing_address: Object,
	 *          shipping_address: Object,
	 *          payment_method: string,
	 *          payment_data: Array,
	 *        }} paymentData Additional payment data to place the order.
	 * @return {Promise} Result of the order creation request.
	 */
	async placeOrder( paymentData ) {
		// The order's billing and shipping address are set by the merchant and are
		// authoritative (they're used for tax) so we keep them as-is rather than overwriting
		// them with the address from the wallet.
		const orderBillingAddress = this.cachedCartData.billing_address;

		// The Store API stores the billing email as `billing_address.email` and requires it to
		// process the payment. An order can be created without one (e.g. a merchant-created
		// pay-for-order), so when the order has no billing email, we fall back to the email
		// captured from the wallet.
		const billingEmail =
			orderBillingAddress?.email || paymentData.billing_address?.email;

		try {
			return await apiFetch( {
				method: 'POST',
				path: `/wc/store/v1/checkout/${ this.orderId }`,
				headers: {
					Nonce: getExpressCheckoutData( 'nonce' ).store_api_nonce,
				},
				data: {
					...paymentData,
					key: this.key,
					// `billing_email` authorizes access to the order, so it must match the order's
					// *current* stored email. The new email is sent in `billing_address.email` below,
					// which the Store API persists to the order before taking payment.
					billing_email: this.billingEmail,
					billing_address: orderBillingAddress && {
						...orderBillingAddress,
						email: billingEmail,
					},
					shipping_address: this.cachedCartData.shipping_address,
				},
			} );
		} catch ( error ) {
			// The Store API persists `billing_address.email` to the order *before* taking payment,
			// so a failed payment leaves the (previously email-less) order carrying the email we
			// just sent. Remember it as the email we authorize with - otherwise a retry is rejected
			// (401) because the top-level `billing_email` no longer matches the order's stored email.
			if (
				billingEmail &&
				error?.code ===
					'woocommerce_rest_checkout_process_payment_error'
			) {
				this.billingEmail = billingEmail;
			}

			throw error;
		}
	}

	/**
	 * Returns the customer's order object.
	 * See https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/StoreApi/docs/order.md
	 *
	 * @return {Promise} Cart response object.
	 */
	async getCart() {
		return ( this.cachedCartData = await apiFetch( {
			method: 'GET',
			path: addQueryArgs( `/wc/store/v1/order/${ this.orderId }`, {
				key: this.key,
				billing_email: this.billingEmail,
			} ),
		} ) );
	}
}
