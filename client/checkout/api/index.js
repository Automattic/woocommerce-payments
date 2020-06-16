/* global Stripe, jQuery */

/**
 * Internal dependencies
 */
import { getConfig } from '../utils.js';

/**
 * Handles generic connections to the server and Stripe.
 */
export default class WCPayAPI {
	/**
	 * Prepares the API.
	 *
	 * @param {Object} options Options for the initialization.
	 */
	constructor( options ) {
		this.options = options;
		this.stripe = null;
	}

	/**
	 * Generates a new instance of Stripe.
	 *
	 * @returns {Object} The Stripe Object.
	 */
	getStripe() {
		if ( ! this.stripe ) {
			const {
				publishableKey,
				accountId,
			} = this.options;

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
	 * @returns {Object} A request object, which will be prepared and then `.send()`.
	 */
	generatePaymentMethodFromCard( elements, preparedCustomerData = {} ) {
		const stripe = this.getStripe();

		return new class {
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
			 * Sanitizes a value from inputs, or loads a default one.
			 *
			 * @param {string} name The key of the value.
			 * @param {mixed} value The value to sanitize.
			 * @returns {mixed}     The sanitized value, `undefined` if not present.
			 */
			sanitizeValue( name, value ) {
				// Fall back to the value in `preparedCustomerData`.
				if ( ( 'undefined' === typeof value ) || 0 === value.length ) {
					value = preparedCustomerData[ name ]; // `undefined` if not set.
				}

				if ( ( 'undefined' !== typeof value ) && 0 < value.length ) {
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
				const sanitizedValue = this.sanitizeValue( name, value );
				if ( 'undefined' !== typeof sanitizedValue ) {
					this.args.billing_details[ name ] = sanitizedValue;
				}
			}

			/**
			 * Updates an address detail within the request.
			 *
			 * @param {string} name The name of the address value.
			 * @param {string} value The actual value.
			 */
			setAddressDetail( name, value ) {
				const sanitizedValue = this.sanitizeValue( name, value );
				if ( 'undefined' !== typeof sanitizedValue ) {
					this.args.billing_details.address[ name ] = sanitizedValue;
				}
			}

			/**
			 * Sends the request to Stripe once everything is ready.
			 *
			 * @return {Object} The payment method object if successfully loaded.
			 */
			send() {
				return stripe.createPaymentMethod( this.args )
					.then( ( paymentMethod ) => {
						if ( paymentMethod.error ) {
							throw paymentMethod.error;
						}

						return paymentMethod;
					} );
			}
		};
	}

	/**
	 * Extracts the details about a payment intent from the redirect URL,
	 * and displays the intent confirmation modal (if needed).
	 *
	 * @param {string} redirectUrl The redirect URL, returned from the server.
	 * @returns {mixed} A redirect URL on success, or `true` if no confirmation is needed.
	 */
	confirmIntent( redirectUrl ) {
		const partials = redirectUrl.match( /#wcpay-confirm-pi:(.+):(.+)$/ );

		if ( ! partials ) {
			return true;
		}

		const orderId = partials[ 1 ];
		const clientSecret = partials[ 2 ];

		return this.getStripe().confirmCardPayment( clientSecret )
			// ToDo: Switch to an async function once it works with webpack.
			.then( ( result ) => {
				const ajaxCall = jQuery.post( getConfig( 'ajaxUrl' ), {
					action: 'update_order_status',
					// eslint-disable-next-line camelcase
					order_id: orderId,
					// eslint-disable-next-line camelcase
					_ajax_nonce: getConfig( 'updateOrderStatusNonce' ),
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
	}
}
