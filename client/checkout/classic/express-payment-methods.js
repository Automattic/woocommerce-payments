/* global jQuery, wc */

/**
 * Internal dependencies
 */
import { getUPEConfig } from 'wcpay/utils/checkout';
import {
	appendPaymentMethodIdToForm,
	appendFraudPreventionTokenInputToForm,
} from '../utils/upe';
import {
	appendFingerprintInputToForm,
	getFingerprint,
} from '../utils/fingerprint';
import { getExpressCheckoutButtonAppearance } from 'wcpay/express-checkout/utils';

// Track which gateways have been registered to avoid duplicate registration.
const registeredGateways = {};

/**
 * Converts a snake_case string to camelCase.
 *
 * @param {string} str The snake_case string.
 * @return {string} The camelCase string.
 */
function snakeToCamel( str ) {
	return str.replace( /_([a-z])/g, ( _, letter ) => letter.toUpperCase() );
}

/**
 * Parses the cart total from the DOM.
 *
 * @return {number} The cart total in smallest currency unit (e.g., cents).
 */
function getCartTotalFromDOM() {
	// Try to get the total from the checkout order review table.
	const orderTotalElement = document.querySelector(
		'.woocommerce-checkout-review-order-table .order-total .woocommerce-Price-amount'
	);

	if ( orderTotalElement ) {
		// Extract the numeric value, removing currency symbols and formatting.
		const totalText = orderTotalElement.textContent || '';
		// Remove all non-numeric characters except decimal points.
		const numericValue = totalText.replace( /[^\d.]/g, '' );
		const total = parseFloat( numericValue );

		if ( ! isNaN( total ) ) {
			// Convert to smallest currency unit (cents).
			return Math.round( total * 100 );
		}
	}

	// Fallback to the cart total from config.
	return Number( getUPEConfig( 'cartTotal' ) ) || 0;
}

/**
 * Hides a payment method from the payment methods list.
 *
 * @param {string} gatewayId The gateway ID to hide.
 */
function hidePaymentMethod( gatewayId ) {
	const paymentMethodElement = document.querySelector(
		`.wc_payment_method.payment_method_${ gatewayId }`
	);
	if ( paymentMethodElement ) {
		paymentMethodElement.style.display = 'none';
	}
}

/**
 * Shows a payment method in the payment methods list.
 *
 * @param {string} gatewayId The gateway ID to show.
 */
function showPaymentMethod( gatewayId ) {
	const paymentMethodElement = document.querySelector(
		`.wc_payment_method.payment_method_${ gatewayId }`
	);
	if ( paymentMethodElement ) {
		paymentMethodElement.style.display = 'block';
	}
}

/**
 * Checks which express payment methods are available on the current device/browser.
 * Creates a hidden ECE element to detect availability.
 *
 * @param {Object} api      The WCPay API instance.
 * @param {number} amount   The payment amount in smallest currency unit.
 * @param {string} currency The currency code.
 * @return {Promise<Object>} Object with payment method availability (e.g., { applePay: true, googlePay: false }).
 */
async function checkExpressPaymentMethodsAvailability( api, amount, currency ) {
	return new Promise( async ( resolve ) => {
		try {
			const stripe = await api.getStripe();

			// Create a hidden container for the availability check.
			const container = document.createElement( 'div' );
			container.style.display = 'none';
			document.body.appendChild( container );

			// Create elements to check availability.
			const elements = stripe.elements( {
				mode: 'payment',
				amount: amount,
				currency: currency,
				paymentMethodCreation: 'manual',
			} );

			// Create ECE with all methods set to 'always' to check availability.
			const eceButton = elements.create( 'expressCheckout', {
				buttonType: {
					applePay: 'plain',
					googlePay: 'plain',
				},
				paymentMethods: {
					applePay: 'always',
					googlePay: 'always',
				},
			} );

			// Listen for ready event to get availability.
			eceButton.on( 'ready', ( { availablePaymentMethods } ) => {
				// Clean up.
				eceButton.unmount();
				container.remove();

				resolve( availablePaymentMethods || {} );
			} );

			// Handle load errors.
			eceButton.on( 'loaderror', () => {
				// Clean up.
				eceButton.unmount();
				container.remove();

				resolve( {} );
			} );

			// Mount to trigger the availability check.
			eceButton.mount( container );
		} catch ( error ) {
			resolve( {} );
		}
	} );
}

/**
 * Creates and registers express payment methods with the Custom Place Order Button API.
 *
 * @param {Object} api The WCPay API instance.
 */
async function registerExpressPaymentMethods( api ) {
	const currency = getUPEConfig( 'currency' )?.toLowerCase();
	const cartTotal = getCartTotalFromDOM();
	const paymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );

	// Get express checkout payment methods from the config.
	const expressPaymentMethods = Object.entries( paymentMethodsConfig ).filter(
		( [ , config ] ) => config.isExpressCheckout
	);

	// Skip if no express payment methods are configured.
	if ( expressPaymentMethods.length === 0 ) {
		return;
	}

	// Hide all express payment methods when total is 0 (free orders).
	if ( cartTotal <= 0 ) {
		for ( const [ , config ] of expressPaymentMethods ) {
			hidePaymentMethod( config.gatewayId );
		}
		return;
	}

	// Check which payment methods are available on this device/browser.
	const availablePaymentMethods = await checkExpressPaymentMethodsAvailability(
		api,
		cartTotal,
		currency
	);

	// Get a fingerprint for fraud prevention.
	let fingerprint = '';
	try {
		const fingerprintResult = await getFingerprint();
		fingerprint = fingerprintResult.visitorId;
	} catch ( error ) {
		// Continue without fingerprint.
	}

	// Process each express payment method.
	for ( const [ paymentMethodId, config ] of expressPaymentMethods ) {
		const { gatewayId } = config;

		// The payment method ID returned in the ready event is in snake_case.
		// We need its name in camelCase to check its availability.
		const paymentMethodType = snakeToCamel( paymentMethodId );

		if ( ! availablePaymentMethods[ paymentMethodType ] ) {
			continue;
		}

		// Register with the Custom Place Order Button API (only once).
		if ( ! registeredGateways[ gatewayId ] ) {
			registerCustomPlaceOrderButton( api, paymentMethodId, fingerprint );
			registeredGateways[ gatewayId ] = true;
		}

		// Show the payment method now that it's confirmed available.
		showPaymentMethod( gatewayId );
	}
}

/**
 * Registers a single express payment method with the Custom Place Order Button API.
 *
 * @param {Object} api                  The WCPay API instance.
 * @param {string} paymentMethodId      The currency code.
 * @param {string} fingerprint          The fingerprint for fraud prevention.
 */
function registerCustomPlaceOrderButton( api, paymentMethodId, fingerprint ) {
	const { gatewayId } = getUPEConfig( 'paymentMethodsConfig' )[
		paymentMethodId
	];
	const currency = getUPEConfig( 'currency' )?.toLowerCase();
	const paymentMethodType = snakeToCamel( paymentMethodId );
	const paymentMethodOptions = {
		applePay: paymentMethodId === 'apple_pay' ? 'always' : 'never',
		googlePay: paymentMethodId === 'google_pay' ? 'always' : 'never',
		link: 'never',
		paypal: 'never',
		klarna: 'never',
	};

	// Store state for this payment method.
	const state = {
		elements: null,
		eceButton: null,
	};

	wc.classicCheckout.registerCustomPlaceOrderButton( gatewayId, {
		render: async function ( container, wcApi ) {
			const cartTotal = getCartTotalFromDOM();

			// Skip rendering if cart total is 0.
			if ( cartTotal <= 0 ) {
				hidePaymentMethod( gatewayId );
				return;
			}

			try {
				// Create Stripe elements.
				const stripe = await api.getStripe();
				state.elements = stripe.elements( {
					mode: 'payment',
					amount: cartTotal,
					currency: currency,
					paymentMethodCreation: 'manual',
					appearance: getExpressCheckoutButtonAppearance(),
					locale: getUPEConfig( 'locale' ) || 'auto',
				} );

				// Create the Express Checkout Element.
				state.eceButton = state.elements.create( 'expressCheckout', {
					buttonType: {
						applePay: 'plain',
						googlePay: 'plain',
					},
					paymentMethods: paymentMethodOptions,
				} );

				// Handle the ready event to check availability.
				state.eceButton.on(
					'ready',
					( { availablePaymentMethods } ) => {
						const isAvailable = Boolean(
							availablePaymentMethods?.[ paymentMethodType ]
						);

						if ( ! isAvailable ) {
							// Payment method not available, hide it.
							hidePaymentMethod( gatewayId );
							container.style.display = 'none';
						} else {
							showPaymentMethod( gatewayId );
							container.style.display = '';
						}
					}
				);

				// Handle click event - validate checkout form first.
				state.eceButton.on( 'click', async ( event ) => {
					// Validate the checkout form using WC's API.
					const validationResult = await wcApi.validate();

					if ( validationResult.hasError ) {
						// Validation failed, don't open the payment sheet.
						// WC will display the error messages.
						return;
					}

					// Validation passed, resolve the event to open the payment sheet.
					event.resolve( {
						business: {
							name:
								getUPEConfig( 'storeName' ) ||
								document.title ||
								'Store',
						},
						emailRequired: true,
						phoneNumberRequired: false,
						shippingAddressRequired: false,
					} );
				} );

				// Handle the confirm event - create payment method and submit form.
				state.eceButton.on( 'confirm', async () => {
					try {
						// Submit elements to validate.
						const {
							error: submitError,
						} = await state.elements.submit();
						if ( submitError ) {
							throw new Error( submitError.message );
						}

						// Create the payment method.
						const {
							paymentMethod,
							error,
						} = await stripe.createPaymentMethod( {
							elements: state.elements,
						} );

						if ( error ) {
							throw new Error( error.message );
						}

						// Get the checkout form.
						const $form = jQuery( 'form.checkout' );

						// Append the payment method ID to the form.
						appendPaymentMethodIdToForm( $form, paymentMethod.id );

						// Append fingerprint for fraud prevention.
						appendFingerprintInputToForm( $form, fingerprint );

						// Append fraud prevention token.
						appendFraudPreventionTokenInputToForm( $form );

						// Submit the checkout form using WC's API.
						wcApi.submit();
					} catch ( error ) {
						// Display error to the user.
						const $container = jQuery(
							'.woocommerce-notices-wrapper'
						).first();
						if ( $container.length ) {
							$container.find( '.woocommerce-error' ).remove();
							$container.append(
								jQuery(
									'<div class="woocommerce-error" />'
								).text( error.message )
							);
						}
					}
				} );

				// Handle load errors.
				state.eceButton.on( 'loaderror', () => {
					hidePaymentMethod( gatewayId );
					container.style.display = 'none';
				} );

				// Mount the button.
				state.eceButton.mount( container );
			} catch ( error ) {
				// Error creating elements, hide the payment method.
				hidePaymentMethod( gatewayId );
			}
		},

		cleanup: function () {
			if ( state.eceButton ) {
				state.eceButton.unmount();
				state.eceButton = null;
			}

			state.elements = null;
		},
	} );
}

/**
 * Initialize express payment methods for classic checkout.
 *
 * @param {Object} api The WCPay API instance.
 */
export function initExpressPaymentMethods( api ) {
	if (
		typeof wc === 'undefined' ||
		! wc.classicCheckout ||
		! wc.classicCheckout.registerCustomPlaceOrderButton
	) {
		return;
	}

	if ( ! getUPEConfig( 'isAppleGooglePayInPaymentMethodsOptionsEnabled' ) ) {
		// Check if the feature is enabled.
		return;
	}

	// Register express payment methods.
	registerExpressPaymentMethods( api );
}
