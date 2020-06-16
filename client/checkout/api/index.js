/* global Stripe, jQuery */

/**
 * Internal dependencies
 */
import { getConfig } from '../utils.js';

export default class WCPayAPI {
	constructor( options ) {
		this.options = options;
		this.stripe  = null;
	}

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

	generatePaymentMethodFromCard( elements, preparedCustomerData = {} ) {
		const stripe = this.getStripe();

		return new class {
			constructor() {
				this.args = {
					type: 'card',
					...elements,
					billing_details: {
						address: {},
					},
				};
			}

			sanitizeValue( name, value ) {
				// Fall back to the value in `preparedCustomerData`.
				if ( ( 'undefined' === typeof value ) || 0 === value.length ) {
					value = preparedCustomerData[ name ]; // `undefined` if not set.
				}

				if ( ( 'undefined' !== typeof value ) && 0 < value.length ) {
					return value;
				}
			}

			setBillingDetail( name, value ) {
				const sanitizedValue = this.sanitizeValue( name, value );
				if ( 'undefined' !== typeof sanitizedValue ) {
					this.args.billing_details[ name ] = sanitizedValue;
				}
			}

			setAddressDetail( name, value ) {
				const sanitizedValue = this.sanitizeValue( name, value );
				if ( 'undefined' !== typeof sanitizedValue ) {
					this.args.billing_details.address[ name ] = sanitizedValue;
				}
			}

			send() {
				return stripe.createPaymentMethod( this.args )
					.then( ( paymentMethod ) => {
						if ( paymentMethod.error ) {
							throw paymentMethod.error;
						}

						return paymentMethod;
					} );
			}
		}
	}

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
