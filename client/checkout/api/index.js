/* global Stripe */

/**
 * Internal dependencies
 */
import { getConfig } from 'utils/checkout';
import {
	getPaymentRequestData,
	getPaymentRequestAjaxURL,
	buildAjaxURL,
} from '../../payment-request/utils';

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
		this.stripePlatform = null;
		this.request = request;
	}

	/**
	 * Generates a new instance of Stripe.
	 *
	 * @param {boolean}  forceAccountRequest True to instantiate the Stripe object with the merchant's account key.
	 * @return {Object} The Stripe Object.
	 */
	getStripe( forceAccountRequest = false ) {
		const {
			publishableKey,
			forceNetworkSavedCards,
			locale,
			isUPEEnabled,
		} = this.options;

		if ( forceNetworkSavedCards && ! forceAccountRequest ) {
			if ( ! this.stripePlatform ) {
				this.stripePlatform = new Stripe( publishableKey, { locale } );
			}
			return this.stripePlatform;
		}

		if ( ! this.stripe ) {
			if ( isUPEEnabled ) {
				this.stripe = new Stripe( publishableKey, {
					betas: [
						'payment_element_beta_1',
						'card_country_event_beta_1',
					],
					locale,
				} );
			} else {
				this.stripe = new Stripe( publishableKey, {
					locale,
				} );
			}
		}
		return this.stripe;
	}

	/**
	 * Load Stripe for payment request button.
	 *
	 * @return {Promise} Promise with the Stripe object or an error.
	 */
	loadStripe() {
		return new Promise( ( resolve ) => {
			try {
				resolve( this.getStripe() );
			} catch ( error ) {
				// In order to avoid showing console error publicly to users,
				// we resolve instead of rejecting when there is an error.
				resolve( { error } );
			}
		} );
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
					...elements,
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
				if ( 'undefined' === typeof value || 0 === value.length ) {
					value = preparedCustomerData[ name ]; // `undefined` if not set.
				}

				if ( 'undefined' !== typeof value && 0 < value.length ) {
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
				if ( 'undefined' !== typeof preparedValue ) {
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
				if ( 'undefined' !== typeof preparedValue ) {
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

		const isSetupIntent = 'si' === partials[ 1 ];
		let orderId = partials[ 2 ];
		const clientSecret = partials[ 3 ];
		const nonce = partials[ 4 ];

		const orderPayIndex = redirectUrl.indexOf( 'order-pay' );
		const isOrderPage = -1 < orderPayIndex;

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
			: this.getStripe( true ).confirmCardPayment( clientSecret );

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

				// In case this is being called via payment request button from a product page,
				// the getConfig function won't work, so fallback to getPaymentRequestData.
				const ajaxUrl =
					getPaymentRequestData( 'ajax_url' ) ??
					getConfig( 'ajaxUrl' );

				const ajaxCall = this.request( ajaxUrl, {
					action: 'update_order_status',
					order_id: orderId,
					// Update the current order status nonce with the new one to ensure that the update
					// order status call works when a guest user creates an account during checkout.
					_ajax_nonce: nonce,
					intent_id: intentId,
					payment_method_id: paymentMethodToSave || null,
				} );

				return [ ajaxCall, result.error ];
			} )
			.then( ( [ verificationCall, originalError ] ) => {
				if ( originalError ) {
					throw originalError;
				}

				return verificationCall.then( ( response ) => {
					const result =
						'string' === typeof response
							? JSON.parse( response )
							: response;

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
	 * Creates a setup intent without confirming it.
	 *
	 * @return {Promise} The final promise for the request to the server.
	 */
	initSetupIntent() {
		return this.request(
			buildAjaxURL( getConfig( 'wcAjaxUrl' ), 'init_setup_intent' ),
			{ _ajax_nonce: getConfig( 'createSetupIntentNonce' ) }
		).then( ( response ) => {
			if ( ! response.success ) {
				throw response.data.error;
			}
			return response.data;
		} );
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
			_ajax_nonce: getConfig( 'createSetupIntentNonce' ),
		} ).then( ( response ) => {
			if ( ! response.success ) {
				throw response.data.error;
			}

			if ( 'succeeded' === response.data.status ) {
				// No need for further authentication.
				return response.data;
			}

			return this.getStripe()
				.confirmCardSetup( response.data.client_secret )
				.then( ( confirmedSetupIntent ) => {
					const { setupIntent, error } = confirmedSetupIntent;
					if ( error ) {
						throw error;
					}

					return setupIntent;
				} );
		} );
	}

	/**
	 * Creates an intent based on a payment method.
	 *
	 * @param {int} orderId The id of the order if creating the intent on Order Pay page.
	 *
	 * @return {Promise} The final promise for the request to the server.
	 */
	createIntent( orderId ) {
		return this.request(
			buildAjaxURL( getConfig( 'wcAjaxUrl' ), 'create_payment_intent' ),
			{
				wcpay_order_id: orderId,
				_ajax_nonce: getConfig( 'createPaymentIntentNonce' ),
			}
		)
			.then( ( response ) => {
				if ( ! response.success ) {
					throw response.data.error;
				}
				return response.data;
			} )
			.catch( ( error ) => {
				if ( error.message ) {
					throw error;
				} else {
					// Covers the case of error on the Ajax request.
					throw new Error( error.statusText );
				}
			} );
	}

	/**
	 * Updates a payment intent with data from order: customer, level3 data and and maybe sets the payment for future use.
	 *
	 * @param {string} paymentIntentId The id of the payment intent.
	 * @param {int} orderId The id of the order.
	 * @param {string} savePaymentMethod 'yes' if saving.
	 * @param {string} selectedUPEPaymentType The name of the selected UPE payment type or empty string.
	 * @param {string?} paymentCountry The payment two-letter iso country code or null.
	 *
	 * @return {Promise} The final promise for the request to the server.
	 */
	updateIntent(
		paymentIntentId,
		orderId,
		savePaymentMethod,
		selectedUPEPaymentType,
		paymentCountry
	) {
		return this.request(
			buildAjaxURL( getConfig( 'wcAjaxUrl' ), 'update_payment_intent' ),
			{
				wcpay_order_id: orderId,
				wc_payment_intent_id: paymentIntentId,
				save_payment_method: savePaymentMethod,
				wcpay_selected_upe_payment_type: selectedUPEPaymentType,
				wcpay_payment_country: paymentCountry,
				_ajax_nonce: getConfig( 'updatePaymentIntentNonce' ),
			}
		)
			.then( ( response ) => {
				if ( 'failure' === response.result ) {
					throw new Error( response.messages );
				}
				return response;
			} )
			.catch( ( error ) => {
				if ( error.message ) {
					throw error;
				} else {
					// Covers the case of error on the Ajaxrequest.
					throw new Error( error.statusText );
				}
			} );
	}

	/**
	 * Saves the calculated UPE appearance values in a transient.
	 *
	 * @param {Object} appearance The UPE appearance object with style values
	 *
	 * @return {Promise} The final promise for the request to the server.
	 */
	saveUPEAppearance( appearance ) {
		return this.request( getConfig( 'ajaxUrl' ), {
			appearance,
			action: 'save_upe_appearance',
			// eslint-disable-next-line camelcase
			_ajax_nonce: getConfig( 'saveUPEAppearanceNonce' ),
		} )
			.then( ( response ) => {
				// There is not any action to take or harm caused by a failed update, so just returning success status.
				return response.success;
			} )
			.catch( ( error ) => {
				if ( error.message ) {
					throw error;
				} else {
					// Covers the case of error on the Ajaxrequest.
					throw new Error( error.statusText );
				}
			} );
	}

	/**
	 * Process checkout and update payment intent via AJAX.
	 *
	 * @param {string} paymentIntentId ID of payment intent to be updated.
	 * @param {Object} fields Checkout fields.
	 * @return {Promise} Promise containing redirect URL for UPE element.
	 */
	processCheckout( paymentIntentId, fields ) {
		return this.request(
			buildAjaxURL( getConfig( 'wcAjaxUrl' ), 'checkout', '' ),
			{
				...fields,
				wc_payment_intent_id: paymentIntentId,
			}
		)
			.then( ( response ) => {
				if ( 'failure' === response.result ) {
					throw new Error( response.messages );
				}
				return response;
			} )
			.catch( ( error ) => {
				if ( error.message ) {
					throw error;
				} else {
					// Covers the case of error on the Ajaxrequest.
					throw new Error( error.statusText );
				}
			} );
	}

	/**
	 * Submits shipping address to get available shipping options
	 * from Payment Request button.
	 *
	 * @param {Object} shippingAddress Shipping details.
	 * @return {Promise} Promise for the request to the server.
	 */
	paymentRequestCalculateShippingOptions( shippingAddress ) {
		return this.request(
			getPaymentRequestAjaxURL( 'get_shipping_options' ),
			{
				security: getPaymentRequestData( 'nonce' )?.shipping,
				is_product_page: getPaymentRequestData( 'is_product_page' ),
				...shippingAddress,
			}
		);
	}

	/**
	 * Updates cart with selected shipping option.
	 *
	 * @param {Object} shippingOption Shipping option.
	 * @return {Promise} Promise for the request to the server.
	 */
	paymentRequestUpdateShippingDetails( shippingOption ) {
		return this.request(
			getPaymentRequestAjaxURL( 'update_shipping_method' ),
			{
				security: getPaymentRequestData( 'nonce' )?.update_shipping,
				shipping_method: [ shippingOption.id ],
				is_product_page: getPaymentRequestData( 'is_product_page' ),
			}
		);
	}

	/**
	 * Get cart items and total amount.
	 *
	 * @return {Promise} Promise for the request to the server.
	 */
	paymentRequestGetCartDetails() {
		return this.request( getPaymentRequestAjaxURL( 'get_cart_details' ), {
			security: getPaymentRequestData( 'nonce' )?.get_cart_details,
		} );
	}

	/**
	 * Add product to cart from variable product page.
	 *
	 * @param {Object} productData Product data.
	 * @return {Promise} Promise for the request to the server.
	 */
	paymentRequestAddToCart( productData ) {
		return this.request( getPaymentRequestAjaxURL( 'add_to_cart' ), {
			security: getPaymentRequestData( 'nonce' )?.add_to_cart,
			...productData,
		} );
	}

	/**
	 * Get selected product data from variable product page.
	 *
	 * @param {Object} productData Product data.
	 * @return {Promise} Promise for the request to the server.
	 */
	paymentRequestGetSelectedProductData( productData ) {
		return this.request(
			getPaymentRequestAjaxURL( 'get_selected_product_data' ),
			{
				security: getPaymentRequestData( 'nonce' )
					?.get_selected_product_data,
				...productData,
			}
		);
	}

	/**
	 * Creates order based on Payment Request payment method.
	 *
	 * @param {Object} paymentData Order data.
	 * @return {Promise} Promise for the request to the server.
	 */
	paymentRequestCreateOrder( paymentData ) {
		return this.request( getPaymentRequestAjaxURL( 'create_order' ), {
			_wpnonce: getPaymentRequestData( 'nonce' )?.checkout,
			...paymentData,
		} );
	}

	/**
	 * Log Payment Errors via Ajax.
	 *
	 * @param {string} chargeId Stripe Charge ID
	 * @return {boolean} Returns true irrespective of result.
	 */
	logPaymentError( chargeId ) {
		return this.request(
			buildAjaxURL( getConfig( 'wcAjaxUrl' ), 'log_payment_error' ),
			{
				charge_id: chargeId,
				_ajax_nonce: getConfig( 'logPaymentErrorNonce' ),
			}
		).then( () => {
			// There is not any action to take or harm caused by a failed update, so just returning true.
			return true;
		} );
	}
}
