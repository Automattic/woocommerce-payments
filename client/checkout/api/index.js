/* global Stripe */

/**
 * Internal dependencies
 */
import { getConfig, getUPEConfig } from 'utils/checkout';
import {
	getPaymentRequestData,
	getPaymentRequestAjaxURL,
	buildAjaxURL,
	getExpressCheckoutAjaxURL,
	getExpressCheckoutConfig,
} from 'utils/express-checkout';
import { getAppearance } from 'checkout/upe-styles';
import { getAppearanceType } from '../utils';

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
	 * Load Stripe for Express Checkout with the merchant’s connected account.
	 *
	 * @return {Promise} Promise with the Stripe object or an error.
	 */
	loadStripeForExpressCheckout() {
		return new Promise( ( resolve ) => {
			try {
				// Force Stripe to be loadded with the connected account.
				resolve( this.getStripe( true ) );
			} catch ( error ) {
				// In order to avoid showing console error publicly to users,
				// we resolve instead of rejecting when there is an error.
				resolve( { error } );
			}
		} );
	}

	/**
	 * Extracts the details about a payment intent from the redirect URL,
	 * and displays the intent confirmation modal (if needed).
	 *
	 * @param {string} redirectUrl The redirect URL, returned from the server.
	 * @param {string} paymentMethodToSave The ID of a Payment Method if it should be saved (optional).
	 * @return {Promise<string>|boolean} A redirect URL on success, or `true` if no confirmation is needed.
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
				return this.getStripe().handleNextAction( {
					clientSecret: clientSecret,
				} );
			}

			// For woopay we need the capability to switch up the account ID specifically for
			// the intent confirmation step, that's why we create a new instance of the Stripe JS here.
			if ( accountIdForIntentConfirmation ) {
				return this.createStripe(
					publishableKey,
					locale,
					accountIdForIntentConfirmation
				).confirmCardPayment( clientSecret );
			}

			// When not dealing with a setup intent or woopay we need to force an account
			// specific request in Stripe.
			return this.getStripe( true ).handleNextAction( {
				clientSecret: clientSecret,
			} );
		};

		return (
			confirmPaymentOrSetup()
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

			if ( response.data.status === 'succeeded' ) {
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
	 * Saves the calculated UPE appearance values in a transient.
	 *
	 * @param {Object} appearance The UPE appearance object with style values
	 * @param {string} elementsLocation The location of the elements.
	 *
	 * @return {Promise} The final promise for the request to the server.
	 */
	saveUPEAppearance( appearance, elementsLocation ) {
		return this.request( getConfig( 'ajaxUrl' ), {
			elements_location: elementsLocation,
			appearance: JSON.stringify( appearance ),
			action: 'save_upe_appearance',
			// eslint-disable-next-line camelcase
			_ajax_nonce: getConfig( 'saveUPEAppearanceNonce' ),
		} )
			.then( ( response ) => {
				return response.data;
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
	 * Empty the cart.
	 *
	 * @param {number} bookingId Booking ID (optional).
	 * @return {Promise} Promise for the request to the server.
	 */
	paymentRequestEmptyCart( bookingId ) {
		return this.request( getPaymentRequestAjaxURL( 'empty_cart' ), {
			security: getPaymentRequestData( 'nonce' )?.empty_cart,
			booking_id: bookingId,
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
	 * Submits shipping address to get available shipping options
	 * from Express Checkout ECE payment method.
	 *
	 * @param {Object} shippingAddress Shipping details.
	 * @return {Promise} Promise for the request to the server.
	 */
	expressCheckoutECECalculateShippingOptions( shippingAddress ) {
		return this.request(
			getExpressCheckoutAjaxURL( 'get_shipping_options' ),
			{
				security: getExpressCheckoutConfig( 'nonce' )?.shipping,
				is_product_page: getExpressCheckoutConfig( 'is_product_page' ),
				...shippingAddress,
			}
		);
	}

	/**
	 * Creates order based on Express Checkout ECE payment method.
	 *
	 * @param {Object} paymentData Order data.
	 * @return {Promise} Promise for the request to the server.
	 */
	expressCheckoutECECreateOrder( paymentData ) {
		return this.request( getExpressCheckoutAjaxURL( 'create_order' ), {
			_wpnonce: getExpressCheckoutConfig( 'nonce' )?.checkout,
			...paymentData,
		} );
	}

	/**
	 * Pays for an order based on the Express Checkout payment method.
	 *
	 * @param {integer} order The order ID.
	 * @param {Object} paymentData Order data.
	 * @return {Promise} Promise for the request to the server.
	 */
	expressCheckoutECEPayForOrder( order, paymentData ) {
		return this.request( getExpressCheckoutAjaxURL( 'pay_for_order' ), {
			_wpnonce: getExpressCheckoutConfig( 'nonce' )?.pay_for_order,
			order,
			...paymentData,
		} );
	}

	initWooPay( userEmail, woopayUserSession ) {
		if ( ! this.isWooPayRequesting ) {
			this.isWooPayRequesting = true;
			const wcAjaxUrl = getConfig( 'wcAjaxUrl' );
			const nonce = getConfig( 'initWooPayNonce' );
			const appearanceType = getAppearanceType();

			return this.request( buildAjaxURL( wcAjaxUrl, 'init_woopay' ), {
				_wpnonce: nonce,
				appearance: getConfig( 'isWooPayGlobalThemeSupportEnabled' )
					? getAppearance( appearanceType, true )
					: null,
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

	/**
	 * Fetches the cart data from the woocommerce store api.
	 *
	 * @return {Object} JSON data.
	 * @throws Error if the response is not ok.
	 */
	pmmeGetCartData() {
		return fetch( `${ getUPEConfig( 'storeApiURL' ) }/cart`, {
			method: 'GET',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
			},
		} ).then( ( response ) => {
			if ( ! response.ok ) {
				throw new Error( response.statusText );
			}
			return response.json();
		} );
	}
}
