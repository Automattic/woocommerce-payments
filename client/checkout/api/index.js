/* global Stripe */

/**
 * Internal dependencies
 */
import { getConfig, getUPEConfig } from 'wcpay/utils/checkout';
import {
	getExpressCheckoutConfig,
	buildAjaxURL,
} from 'wcpay/utils/express-checkout';
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
	async getStripeForUPE( paymentMethodType ) {
		this.options.forceNetworkSavedCards = getUPEConfig(
			'paymentMethodsConfig'
		)[ paymentMethodType ].forceNetworkSavedCards;
		return this.getStripe();
	}

	async getStripe( forceAccountRequest = false ) {
		const maxWaitTime = 600 * 1000; // 600 seconds
		const waitInterval = 100;
		let currentWaitTime = 0;
		while ( ! window.Stripe ) {
			await new Promise( ( resolve ) =>
				setTimeout( resolve, waitInterval )
			);
			currentWaitTime += waitInterval;
			if ( currentWaitTime > maxWaitTime ) {
				throw new Error( 'Stripe object not found' );
			}
		}
		return this.__getStripe( forceAccountRequest );
	}

	/**
	 * Generates a new instance of Stripe.
	 *
	 * @param {boolean}  forceAccountRequest True to instantiate the Stripe object with the merchant's account key.
	 * @return {Object} The Stripe Object.
	 */
	__getStripe( forceAccountRequest = false ) {
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
	async loadStripeForExpressCheckout() {
		// Force Stripe to be loadded with the connected account.
		try {
			return this.getStripe( true );
		} catch ( error ) {
			// In order to avoid showing console error publicly to users,
			// we resolve instead of rejecting when there is an error.
			return { error };
		}
	}

	/**
	 * Extracts the details about a payment intent from the redirect URL,
	 * and displays the intent confirmation modal (if needed).
	 *
	 * @param {string} redirectUrl The redirect URL, returned from the server.
	 * @param {boolean} shouldSavePaymentMethod Whether the payment method should be saved.
	 * @return {Promise<string>|boolean} A redirect URL on success, or `true` if no confirmation is needed.
	 */
	confirmIntent( redirectUrl, shouldSavePaymentMethod = false ) {
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

		const confirmPaymentOrSetup = async () => {
			const { locale, publishableKey } = this.options;
			const accountIdForIntentConfirmation = getConfig(
				'accountIdForIntentConfirmation'
			);

			// If this is a setup intent we're not processing a woopay payment so we can
			// use the regular getStripe function.
			const stripe = await this.getStripe();
			if ( isSetupIntent ) {
				return stripe.handleNextAction( {
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
			const stripeWithForcedAccountRequest = await this.getStripe( true );
			return stripeWithForcedAccountRequest.handleNextAction( {
				clientSecret: clientSecret,
			} );
		};

		return (
			confirmPaymentOrSetup()
				// ToDo: Switch to an async function once it works with webpack.
				.then( ( result ) => {
					let paymentError = null;
					if ( result.paymentIntent?.last_payment_error ) {
						paymentError = {
							message:
								result.paymentIntent.last_payment_error.message,
						};
					}
					// If a wallet iframe is closed, Stripe doesn't throw an error, but the intent status will be requires_action.
					if ( result.paymentIntent?.status === 'requires_action' ) {
						paymentError = {
							message: 'Payment requires additional action.',
						};
					}

					const intentId =
						( result.paymentIntent && result.paymentIntent.id ) ||
						( result.setupIntent && result.setupIntent.id ) ||
						( result.error &&
							result.error.payment_intent &&
							result.error.payment_intent.id ) ||
						( result.error.setup_intent &&
							result.error.setup_intent.id );

					// In case this is being called via payment request button from a product page,
					// the getConfig function won't work, so fallback to getExpressCheckoutConfig.
					const ajaxUrl =
						getExpressCheckoutConfig( 'ajax_url' ) ??
						getConfig( 'ajaxUrl' );

					const isChangingPayment = getConfig( 'isChangingPayment' );

					const ajaxCall = this.request( ajaxUrl, {
						action: 'update_order_status',
						order_id: orderId,
						// Update the current order status nonce with the new one to ensure that the update
						// order status call works when a guest user creates an account during checkout.
						_ajax_nonce: nonce,
						intent_id: intentId,
						should_save_payment_method: shouldSavePaymentMethod
							? 'true'
							: 'false',
						is_changing_payment: isChangingPayment
							? 'true'
							: 'false',
					} );

					return [ ajaxCall, paymentError, result.error ];
				} )
				.then( ( [ verificationCall, paymentError, resultError ] ) => {
					if ( resultError ) {
						throw resultError;
					}

					return verificationCall.then( ( response ) => {
						const result =
							typeof response === 'string'
								? JSON.parse( response )
								: response;

						if ( result.error ) {
							throw result.error;
						}

						if ( paymentError ) {
							throw paymentError;
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
	async setupIntent( paymentMethodId ) {
		const response = await this.request( getConfig( 'ajaxUrl' ), {
			action: 'create_setup_intent',
			'wcpay-payment-method': paymentMethodId,
			_ajax_nonce: getConfig( 'createSetupIntentNonce' ),
		} );

		if ( ! response.success ) {
			throw response.data.error;
		}

		if ( response.data.status === 'succeeded' ) {
			// No need for further authentication.
			return response.data;
		}

		const stripe = await this.getStripe();

		const confirmedSetupIntent = await stripe.confirmCardSetup(
			response.data.client_secret
		);

		const { setupIntent, error } = confirmedSetupIntent;
		if ( error ) {
			throw error;
		}

		return setupIntent;
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
}
