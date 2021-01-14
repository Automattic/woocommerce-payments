/* global Stripe */

/**
 * Internal dependencies
 */
import { getConfig, setConfig } from 'utils/checkout';

/**
 * Handles generic connections to the server and Stripe.
 */
export default class WCPayAPI {
	/**
	 * Prepares the API.
	 *
	 * @param {Object}   options Options for the initialization.
	 * @param {Function} request A function to use for AJAX requests.
	 */
	constructor( options, request ) {
		this.options = options;
		this.stripe = null;
		this.request = request;
	}

	/**
	 * Generates a new instance of Stripe.
	 *
	 * @return {Object} The Stripe Object.
	 */
	getStripe() {
		if ( ! this.stripe ) {
			const { publishableKey, accountId } = this.options;

			this.stripe = new Stripe( publishableKey, {
				stripeAccount: accountId,
			} );
		}

		return this.stripe;
	}

	/**
	 * Generates a Stripe payment method.
	 *
	 * @param {Object} elements A hash of all Stripe elements, used to enter card data.
	 * @param {Object} preparedCustomerData Default values for customer data, used on pages like Pay for Order.
	 * @return {Object} A request object, which will be prepared and then `.send()`.
	 */
	generatePaymentMethodRequest( elements, preparedCustomerData = {} ) {
		const stripe = this.getStripe();

		return new ( class {
			constructor() {
				this.args = {
					type: 'card',
					...elements,
					// eslint-disable-next-line camelcase
					billing_details: {
						address: {},
					},
				};
			}

			/**
			 * Prepares a value that's been loaded from inputs,
			 * uses a default value if none is present.
			 *
			 * @param {string} name The key of the value.
			 * @param {mixed} value The value to sanitize.
			 * @return {mixed}     The sanitized value, `undefined` if not present.
			 */
			prepareValue( name, value ) {
				// Fall back to the value in `preparedCustomerData`.
				if ( typeof value === 'undefined' || value.length === 0 ) {
					value = preparedCustomerData[ name ]; // `undefined` if not set.
				}

				if ( typeof value !== 'undefined' && value.length > 0 ) {
					return value;
				}
			}

			/**
			 * Updates a billing detail within the request.
			 *
			 * @param {string} name The name of the billing value.
			 * @param {string} value The actual value.
			 */
			setBillingDetail( name, value ) {
				const preparedValue = this.prepareValue( name, value );
				if ( typeof preparedValue !== 'undefined' ) {
					this.args.billing_details[ name ] = preparedValue;
				}
			}

			/**
			 * Updates an address detail within the request.
			 *
			 * @param {string} name The name of the address value.
			 * @param {string} value The actual value.
			 */
			setAddressDetail( name, value ) {
				const preparedValue = this.prepareValue( name, value );
				if ( typeof preparedValue !== 'undefined' ) {
					this.args.billing_details.address[ name ] = preparedValue;
				}
			}

			/**
			 * Sends the request to Stripe once everything is ready.
			 *
			 * @return {Object} The payment method object if successfully loaded.
			 */
			send() {
				return stripe
					.createPaymentMethod( this.args )
					.then( ( paymentMethod ) => {
						if ( paymentMethod.error ) {
							throw paymentMethod.error;
						}

						return paymentMethod;
					} );
			}
		} )();
	}

	/**
	 * Extracts the details about a payment intent from the redirect URL,
	 * and displays the intent confirmation modal (if needed).
	 *
	 * @param {string} redirectUrl The redirect URL, returned from the server.
	 * @param {string} paymentMethodToSave The ID of a Payment Method if it should be saved (optional).
	 * @return {mixed} A redirect URL on success, or `true` if no confirmation is needed.
	 */
	confirmIntent( redirectUrl, paymentMethodToSave ) {
		const partials = redirectUrl.match(
			/#wcpay-confirm-(pi|si):(.+):(.+):(.+)$/
		);

		if ( ! partials ) {
			return true;
		}

		const isSetupIntent = partials[ 1 ] === 'si';
		let orderId = partials[ 2 ];
		const clientSecret = partials[ 3 ];
		// Update the current order status nonce with the new one to ensure that the update
		// order status call works when a guest user creates an account during checkout.
		// eslint-disable-next-line camelcase
		setConfig( 'updateOrderStatusNonce', partials[ 4 ] );

		const orderPayIndex = redirectUrl.indexOf( 'order-pay' );
		const isOrderPage = orderPayIndex > -1;

		// If we're on the Pay for Order page, get the order ID
		// directly from the URL instead of relying on the hash.
		// The checkout URL does not contain the string 'order-pay'.
		// The Pay for Order page contains the string 'order-pay' and
		// can have these formats:
		// Plain permalinks:
		// /?page_id=7&order-pay=189&pay_for_order=true&key=wc_order_key
		// Non-plain permalinks:
		// /checkout/order-pay/189/
		// Match for consecutive digits after the string 'order-pay' to get the order ID.
		const orderIdPartials =
			isOrderPage &&
			redirectUrl.substring( orderPayIndex ).match( /\d+/ );
		if ( orderIdPartials ) {
			orderId = orderIdPartials[ 0 ];
		}

		const confirmAction = isSetupIntent
			? this.getStripe().confirmCardSetup( clientSecret )
			: this.getStripe().confirmCardPayment( clientSecret );

		const request = confirmAction
			// ToDo: Switch to an async function once it works with webpack.
			.then( ( result ) => {
				const intentId =
					( result.paymentIntent && result.paymentIntent.id ) ||
					( result.setupIntent && result.setupIntent.id ) ||
					( result.error &&
						result.error.payment_intent &&
						result.error.payment_intent.id ) ||
					( result.error.setup_intent &&
						result.error.setup_intent.id );

				const ajaxCall = this.request( getConfig( 'ajaxUrl' ), {
					action: 'update_order_status',
					// eslint-disable-next-line camelcase
					order_id: orderId,
					// eslint-disable-next-line camelcase
					_ajax_nonce: getConfig( 'updateOrderStatusNonce' ),
					// eslint-disable-next-line camelcase
					intent_id: intentId,
					// eslint-disable-next-line camelcase
					payment_method_id: paymentMethodToSave || null,
				} );

				return [ ajaxCall, result.error ];
			} )
			.then( ( [ verificationCall, originalError ] ) => {
				if ( originalError ) {
					throw originalError;
				}

				return verificationCall.then( ( response ) => {
					const result = JSON.parse( response );

					if ( result.error ) {
						throw result.error;
					}

					return result.return_url;
				} );
			} );

		return {
			request,
			isOrderPage,
		};
	}

	/**
	 * Sets up an intent based on a payment method.
	 *
	 * @param {string} paymentMethodId The ID of the payment method.
	 * @return {Promise} The final promise for the request to the server.
	 */
	setupIntent( paymentMethodId ) {
		return this.request( getConfig( 'ajaxUrl' ), {
			action: 'create_setup_intent',
			'wcpay-payment-method': paymentMethodId,
			// eslint-disable-next-line camelcase
			_ajax_nonce: getConfig( 'createSetupIntentNonce' ),
		} ).then( ( response ) => {
			if ( ! response.success ) {
				throw response.data.error;
			}

			if ( response.data.status === 'succeeded' ) {
				// No need for further authentication.
				return response.data;
			}

			return (
				this.getStripe()
					// eslint-disable-next-line camelcase
					.confirmCardSetup( response.data.client_secret )
					.then( ( confirmedSetupIntent ) => {
						const { setupIntent, error } = confirmedSetupIntent;
						if ( error ) {
							throw error;
						}

						return setupIntent;
					} )
			);
		} );
	}
}
