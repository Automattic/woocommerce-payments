/* global Stripe */

/**
 * Internal dependencies
 */
import { getConfig, getUPEConfig } from 'utils/checkout';
import {
	getPaymentRequestData,
	getPaymentRequestAjaxURL,
	buildAjaxURL,
} from '../../payment-request/utils';
import { decryptClientSecret } from '../utils/encryption';

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
		this.isWooPayRequesting = false;
	}

	createStripe( publishableKey, locale, accountId = '', betas = [] ) {
		const options = { locale };

		if ( accountId ) {
			options.stripeAccount = accountId;
		}
		if ( betas ) {
			options.betas = betas;
		}

		return new Stripe( publishableKey, options );
	}

	/**
	 * Overloaded method to get the Stripe object for UPE. Leverages the original getStripe method but before doing
	 * so, sets the forceNetworkSavedCards option to the proper value for the payment method type.
	 * forceNetworkSavedCards is currently the flag that among others determines whether or not to use the Stripe Platform on the checkout.
	 *
	 * @param {string} paymentMethodType The payment method type.
	 * @return {Object} The Stripe Object.
	 */
	getStripeForUPE( paymentMethodType ) {
		this.options.forceNetworkSavedCards = getUPEConfig(
			'paymentMethodsConfig'
		)[ paymentMethodType ].forceNetworkSavedCards;
		return this.getStripe();
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
			accountId,
			forceNetworkSavedCards,
			locale,
			isStripeLinkEnabled,
		} = this.options;

		if ( forceNetworkSavedCards && ! forceAccountRequest ) {
			if ( ! this.stripePlatform ) {
				this.stripePlatform = this.createStripe(
					publishableKey,
					locale
				);
			}
			return this.stripePlatform;
		}

		if ( ! this.stripe ) {
			let betas = [ 'card_country_event_beta_1' ];
			if ( isStripeLinkEnabled ) {
				// https://stripe.com/docs/payments/link/autofill-modal
				betas = betas.concat( [ 'link_autofill_modal_beta_1' ] );
			}

			this.stripe = this.createStripe(
				publishableKey,
				locale,
				accountId,
				betas
			);
		}
		return this.stripe;
	}

	/**
	 * Load Stripe for payment request button.
	 *
	 * @param {boolean}  forceAccountRequest True to instantiate the Stripe object with the merchant's account key.
	 * @return {Promise} Promise with the Stripe object or an error.
	 */
	loadStripe( forceAccountRequest = false ) {
		return new Promise( ( resolve ) => {
			try {
				resolve( this.getStripe( forceAccountRequest ) );
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
			/#wcpay-confirm-(pi|si):(.*):(.*):(.*)$/
		);

		if ( ! partials ) {
			return true;
		}

		const isSetupIntent = partials[ 1 ] === 'si';
		let orderId = partials[ 2 ];
		const clientSecret = partials[ 3 ];
		const nonce = partials[ 4 ];

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

		const confirmPaymentOrSetup = () => {
			const { locale, publishableKey } = this.options;
			const accountIdForIntentConfirmation = getConfig(
				'accountIdForIntentConfirmation'
			);

			// If this is a setup intent we're not processing a woopay payment so we can
			// use the regular getStripe function.
			if ( isSetupIntent ) {
				return this.getStripe().confirmCardSetup(
					decryptClientSecret( clientSecret )
				);
			}

			// For woopay we need the capability to switch up the account ID specifically for
			// the intent confirmation step, that's why we create a new instance of the Stripe JS here.
			if ( accountIdForIntentConfirmation ) {
				return this.createStripe(
					publishableKey,
					locale,
					accountIdForIntentConfirmation
				).confirmCardPayment(
					decryptClientSecret(
						clientSecret,
						accountIdForIntentConfirmation
					)
				);
			}

			// When not dealing with a setup intent or woopay we need to force an account
			// specific request in Stripe.
			return this.getStripe( true ).confirmCardPayment(
				decryptClientSecret( clientSecret )
			);
		};

		return (
			confirmPaymentOrSetup()
				// ToDo: Switch to an async function once it works with webpack.
				.then( ( result ) => {
					if ( ! orderId ) {
						return [ true, result.error ];
					}

					// In case this is being called via payment request button from a product page,
					// the getConfig function won't work, so fallback to getPaymentRequestData.
					const ajaxUrl =
						getPaymentRequestData( 'ajax_url' ) ??
						getConfig( 'ajaxUrl' );

					const intentId =
						( result.paymentIntent && result.paymentIntent.id ) ||
						( result.setupIntent && result.setupIntent.id ) ||
						( result.error &&
							result.error.payment_intent &&
							result.error.payment_intent.id ) ||
						( result.error.setup_intent &&
							result.error.setup_intent.id );

					const orderUpdateCall = this.request( ajaxUrl, {
						action: 'update_order_status',
						order_id: orderId,
						// Update the current order status nonce with the new one to ensure that the update
						// order status call works when a guest user creates an account during checkout.
						_ajax_nonce: nonce,
						intent_id: intentId,
						payment_method_id: paymentMethodToSave || null,
					} );

					return [ orderUpdateCall, result.error ];
				} )
				.then( ( [ orderUpdateCall, confirmationError ] ) => {
					if ( confirmationError ) {
						throw confirmationError;
					}

					if ( orderUpdateCall === true ) {
						return '';
					}

					return orderUpdateCall.then( ( response ) => {
						const result =
							typeof response === 'string'
								? JSON.parse( response )
								: response;

						if ( result.error ) {
							throw result.error;
						}

						return result.return_url;
					} );
				} )
		);
	}

	/**
	 * Confirm Stripe payment with fallback for rate limit error.
	 *
	 * @param {Object|StripeElements} elements Stripe elements.
	 * @param {Object} confirmParams Confirm payment request parameters.
	 * @param {string|null} paymentIntentSecret Payment intent secret used to validate payment on rate limit error
	 *
	 * @return {Promise} The payment confirmation promise.
	 */
	async handlePaymentConfirmation(
		elements,
		confirmParams,
		paymentIntentSecret
	) {
		const stripe = this.getStripe();
		const confirmPaymentResult = await stripe.confirmPayment( {
			elements,
			confirmParams,
		} );
		if (
			paymentIntentSecret &&
			confirmPaymentResult.error &&
			confirmPaymentResult.error.code === 'lock_timeout'
		) {
			const paymentIntentResult = await stripe.retrievePaymentIntent(
				decryptClientSecret( paymentIntentSecret )
			);
			if (
				! paymentIntentResult.error &&
				paymentIntentResult.paymentIntent.status === 'succeeded'
			) {
				window.location.href = confirmParams.redirect_url;
				return paymentIntentResult; //To prevent returning an error during the redirection.
			}
		}

		return confirmPaymentResult;
	}

	/**
	 * Saves the calculated UPE appearance values in a transient.
	 *
	 * @param {Object} appearance The UPE appearance object with style values
	 * @param {string} isBlocksCheckout 'true' if save request is for Blocks Checkout. Default 'false'.
	 *
	 * @return {Promise} The final promise for the request to the server.
	 */
	saveUPEAppearance( appearance, isBlocksCheckout = 'false' ) {
		return this.request( getConfig( 'ajaxUrl' ), {
			is_blocks_checkout: isBlocksCheckout,
			appearance: JSON.stringify( appearance ),
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

	initWooPay( userEmail, woopayUserSession ) {
		if ( ! this.isWooPayRequesting ) {
			this.isWooPayRequesting = true;
			const wcAjaxUrl = getConfig( 'wcAjaxUrl' );
			const nonce = getConfig( 'initWooPayNonce' );
			return this.request( buildAjaxURL( wcAjaxUrl, 'init_woopay' ), {
				_wpnonce: nonce,
				email: userEmail,
				user_session: woopayUserSession,
				order_id: getConfig( 'order_id' ),
				key: getConfig( 'key' ),
				billing_email: getConfig( 'billing_email' ),
			} ).finally( () => {
				this.isWooPayRequesting = false;
			} );
		}
	}

	expressCheckoutAddToCart( productData ) {
		const wcAjaxUrl = getConfig( 'wcAjaxUrl' );
		const addToCartNonce = getConfig( 'addToCartNonce' );

		return this.request( buildAjaxURL( wcAjaxUrl, 'add_to_cart' ), {
			security: addToCartNonce,
			...productData,
		} );
	}

	paymentRequestPayForOrder( order, paymentData ) {
		return this.request( getPaymentRequestAjaxURL( 'pay_for_order' ), {
			_wpnonce: getPaymentRequestData( 'nonce' )?.pay_for_order,
			order,
			...paymentData,
		} );
	}
}
