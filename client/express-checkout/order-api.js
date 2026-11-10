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
	// WooCommerce 11.3+ rejects an address change that would re-price the order.
	'woocommerce_rest_checkout_order_address_change_not_allowed',
	// The request failed the endpoint's schema, e.g. a malformed wallet email or phone.
	'rest_invalid_param',
];

const CONTACT_FIELDS = [
	'first_name',
	'last_name',
	'company',
	'email',
	'phone',
];

// Kept together, so an address never mixes its own location with another one's.
const LOCATION_FIELDS = [
	'address_1',
	'address_2',
	'city',
	'state',
	'postcode',
	'country',
];

/**
 * Returns the order address with its blank contact fields and all of its location fields taken from another address.
 *
 * @param {Object} orderAddress  The address saved on the order.
 * @param {Object} sourceAddress The address to fill from, e.g. the wallet's billing address.
 * @return {Object} The address to send to the Store API.
 */
const fillMissingFields = ( orderAddress, sourceAddress = {} ) => {
	const address = { ...orderAddress };

	CONTACT_FIELDS.forEach( ( field ) => {
		address[ field ] =
			orderAddress[ field ] || sourceAddress[ field ] || '';
	} );
	LOCATION_FIELDS.forEach( ( field ) => {
		address[ field ] = sourceAddress[ field ] || '';
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
		// Orders with no billing country are the exception, handled below.
		// See https://github.com/woocommerce/woocommerce/issues/48540
		const walletEmail = paymentData.billing_address?.email;
		const walletPhone = paymentData.billing_address?.phone;

		const orderBillingAddress = this.cachedCartData.billing_address;
		const orderShippingAddress = this.cachedCartData.shipping_address;

		// An order with no billing country (e.g. one created in the mobile apps and shared as a
		// payment link) can't pass the Store API address validation, so it takes the wallet's
		// billing address. WooCommerce allows an order's first address even when it re-prices the order.
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

		let shippingAddress = orderShippingAddress && {
			...orderShippingAddress,
			phone: orderShippingAddress.phone || walletPhone,
		};
		if ( hasNoOrderBillingAddress && ! orderShippingAddress?.country ) {
			// The order ships to its new billing address. It's sent explicitly, because WooCommerce
			// before 11.0 passes a missing shipping address straight to `WC_Order::set_shipping_address()`.
			shippingAddress = fillMissingFields(
				orderShippingAddress ?? {},
				billingAddress
			);
			// The shipping address has no email field.
			delete shippingAddress.email;
		}

		const sentBillingEmail = billingAddress?.email;
		// Placing the order persists what was sent, so a later attempt (a retry, or switching express
		// method) must start from it: authorize with the new email, and keep the saved address
		// instead of sending another wallet's.
		const rememberPersistedData = () => {
			if ( sentBillingEmail ) {
				this.billingEmail = sentBillingEmail;
			}
			if ( hasNoOrderBillingAddress ) {
				this.cachedCartData = {
					...this.cachedCartData,
					billing_address: billingAddress,
					shipping_address: shippingAddress,
				};
			}
		};

		try {
			const response = await apiFetch( {
				method: 'POST',
				path: `/wc/store/v1/checkout/${ this.orderId }`,
				headers: {
					Nonce: getExpressCheckoutData( 'nonce' ).store_api_nonce,
					// The wallet's address needs the same normalization as on the cart (state names, address lines).
					...( hasNoOrderBillingAddress && {
						'X-WooPayments-Tokenized-Cart': true,
						'X-WooPayments-Tokenized-Cart-Nonce':
							getExpressCheckoutData( 'nonce' )
								.tokenized_cart_nonce,
					} ),
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

			rememberPersistedData();

			return response;
		} catch ( error ) {
			// A failed payment still persisted the addresses (they are saved before payment is taken),
			// so remember them for the same reason. Skip the failures that happen before persistence,
			// which a payment failure never is (it carries `process_payment_error`, or no code at
			// all when apiFetch rejects with an unparsed response).
			if ( ! ERRORS_BEFORE_PERSISTENCE.includes( error?.code ) ) {
				rememberPersistedData();
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
