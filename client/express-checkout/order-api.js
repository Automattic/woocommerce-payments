/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { getExpressCheckoutData } from './utils';

// Failures that happen before the Store API persists the billing address to the order, so the
// email we sent was not stored and should not be remembered for the next attempt.
const ERRORS_BEFORE_PERSISTENCE = [
	'woocommerce_rest_invalid_billing_email',
	'woocommerce_rest_invalid_order',
	'woocommerce_rest_invalid_user',
	'woocommerce_rest_invalid_address',
	'woocommerce_rest_invalid_address_country',
];

const WALLET_CONTACT_FIELDS = [
	'first_name',
	'last_name',
	'company',
	'email',
	'phone',
];

// Kept together, so an order never mixes its own location with the wallet's.
const LOCATION_FIELDS = [
	'address_1',
	'address_2',
	'city',
	'state',
	'postcode',
	'country',
];

/**
 * Returns the order address with its blank contact fields and all of its location fields taken from the wallet.
 *
 * @param {Object} orderAddress  The address saved on the order.
 * @param {Object} walletAddress The billing address from the wallet.
 * @return {Object} The address to send to the Store API.
 */
const fillMissingFields = ( orderAddress, walletAddress = {} ) => {
	const address = { ...orderAddress };

	WALLET_CONTACT_FIELDS.forEach( ( field ) => {
		address[ field ] =
			orderAddress[ field ] || walletAddress[ field ] || '';
	} );
	LOCATION_FIELDS.forEach( ( field ) => {
		address[ field ] = walletAddress[ field ] || '';
	} );

	return address;
};

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
		// Keep the merchant's address (it determines tax) and only fill the contact fields a
		// merchant-created pay-for-order may be missing (email, phone). The Store API needs them to
		// take payment and the wallet provides them, so backfilling is safe and tax stays untouched.
		// The exception, below, is an order with no address at all.
		// See https://github.com/woocommerce/woocommerce/issues/48540
		const walletEmail = paymentData.billing_address?.email;
		const walletPhone = paymentData.billing_address?.phone;

		const orderBillingAddress = this.cachedCartData.billing_address;
		const orderShippingAddress = this.cachedCartData.shipping_address;

		// An order with no billing country (e.g. one created in the mobile apps and shared as a
		// payment link) can't pass the Store API address validation, so it takes the
		// wallet's billing address: WooCommerce taxes it at the store's base address until then, and
		// lets its first address re-price it.
		const hasNoOrderBillingAddress =
			orderBillingAddress && ! orderBillingAddress.country;

		const billingAddress = hasNoOrderBillingAddress
			? fillMissingFields(
					orderBillingAddress,
					paymentData.billing_address
				)
			: orderBillingAddress && {
					...orderBillingAddress,
					email: orderBillingAddress.email || walletEmail,
					phone: orderBillingAddress.phone || walletPhone,
				};
		// A blank shipping address would replace the billing one WooCommerce ships to when none is sent.
		const shippingAddress =
			hasNoOrderBillingAddress && ! orderShippingAddress?.country
				? undefined
				: orderShippingAddress && {
						...orderShippingAddress,
						phone: orderShippingAddress.phone || walletPhone,
					};

		const sentBillingEmail = billingAddress?.email;

		try {
			const response = await apiFetch( {
				method: 'POST',
				path: `/wc/store/v1/checkout/${ this.orderId }`,
				headers: {
					Nonce: getExpressCheckoutData( 'nonce' ).store_api_nonce,
				},
				data: {
					...paymentData,
					key: this.key,
					// `billing_email` authorizes access and must match the order's stored email.
					// The new email is applied through `billing_address.email`.
					billing_email: this.billingEmail,
					billing_address: billingAddress,
					shipping_address: shippingAddress,
				},
			} );

			// Placing the order persists this email value server-side, so a later attempt (a retry, or
			// switching express method) must authorize with it. Remembering it, to ensure retries succeed.
			if ( sentBillingEmail ) {
				this.billingEmail = sentBillingEmail;
			}

			return response;
		} catch ( error ) {
			// A failed payment still persisted the email (it is saved before payment is taken), so
			// remember it for the same reason. Skip the failures that happen before persistence,
			// which a payment failure never is (it carries `process_payment_error`, or no code at
			// all when apiFetch rejects with an unparsed response).
			if (
				sentBillingEmail &&
				! ERRORS_BEFORE_PERSISTENCE.includes( error?.code )
			) {
				this.billingEmail = sentBillingEmail;
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
