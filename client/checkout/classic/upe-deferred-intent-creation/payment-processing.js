/**
 * Internal dependencies
 */
import { getUPEConfig } from 'wcpay/utils/checkout';
import { getAppearance } from '../../upe-styles';
import showErrorCheckout from 'wcpay/checkout/utils/show-error-checkout';
import {
	appendFingerprintInputToForm,
	getFingerprint,
} from 'wcpay/checkout/utils/fingerprint';
import {
	appendPaymentMethodIdToForm,
	getPaymentMethodTypes,
	getSelectedUPEGatewayPaymentMethod,
	getTerms,
	getUpeSettings,
	isLinkEnabled,
} from 'wcpay/checkout/utils/upe';
import enableStripeLinkPaymentMethod from 'wcpay/checkout/stripe-link';
import {
	SHORTCODE_SHIPPING_ADDRESS_FIELDS,
	SHORTCODE_BILLING_ADDRESS_FIELDS,
} from '../../constants';

const gatewayUPEComponents = {};
let fingerprint = null;

for ( const paymentMethodType in getUPEConfig( 'paymentMethodsConfig' ) ) {
	gatewayUPEComponents[ paymentMethodType ] = {
		elements: null,
		upeElement: null,
	};
}

/**
 * Initializes the appearance of the payment element by retrieving the UPE configuration
 * from the API and saving the appearance if it doesn't exist. If the appearance already exists,
 * it is simply returned.
 *
 * @param {Object} api The API object used to save the UPE configuration.
 * @return {Object} The appearance object for the UPE.
 */
function initializeAppearance( api ) {
	let appearance = getUPEConfig( 'upeAppearance' );
	if ( ! appearance ) {
		appearance = getAppearance();
		api.saveUPEAppearance( appearance );
	}
	return appearance;
}

/**
 * Block UI to indicate processing and avoid duplicate submission.
 *
 * @param {Object} jQueryForm The jQuery object for the jQueryForm.
 */
function blockUI( jQueryForm ) {
	jQueryForm.addClass( 'processing' ).block( {
		message: null,
		overlayCSS: {
			background: '#fff',
			opacity: 0.6,
		},
	} );
}

/**
 * Validates the Stripe elements by submitting them and handling any errors that occur during submission.
 * If an error occurs, the function removes loading effect from the provided jQuery form and thus unblocks it,
 * and shows an error message in the checkout.
 *
 * @param {Object} elements The Stripe elements object to be validated.
 * @return {Promise} Promise for the checkout submission.
 */
export function validateElements( elements ) {
	return elements.submit().then( ( result ) => {
		if ( result.error ) {
			throw new Error( result.error.message );
		}
	} );
}

/**
 * Submits the provided jQuery form and removes the 'processing' class from it.
 *
 * @param {Object} jQueryForm The jQuery object for the form being submitted.
 */
function submitForm( jQueryForm ) {
	jQueryForm.removeClass( 'processing' ).submit();
}

/**
 * Creates a Stripe payment method by calling the Stripe API's createPaymentMethod with the provided elements
 * and billing details. The billing details are obtained from various form elements on the page.
 *
 * @param {Object} api The API object used to call the Stripe API's createPaymentMethod method.
 * @param {Object} elements The Stripe elements object used to create a Stripe payment method.
 * @param {Object} jQueryForm The jQuery object for the form being submitted.
 * @param {string} paymentMethodType The type of Stripe payment method to create.
 * @return {Promise<Object>} A promise that resolves with the created Stripe payment method.
 */
function createStripePaymentMethod(
	api,
	elements,
	jQueryForm,
	paymentMethodType
) {
	let params = {};
	if ( 'checkout' === jQueryForm.attr( 'name' ) ) {
		params = {
			billing_details: {
				name: document.querySelector( '#billing_first_name' )
					? (
							document.querySelector( '#billing_first_name' )
								.value +
							' ' +
							document.querySelector( '#billing_last_name' ).value
					  ).trim()
					: undefined,
				email: document.querySelector( '#billing_email' ).value,
				phone: document.querySelector( '#billing_phone' ).value,
				address: {
					city: document.querySelector( '#billing_city' ).value,
					country: document.querySelector( '#billing_country' ).value,
					line1: document.querySelector( '#billing_address_1' ).value,
					line2: document.querySelector( '#billing_address_2' ).value,
					postal_code: document.querySelector( '#billing_postcode' )
						.value,
					state: document.querySelector( '#billing_state' ).value,
				},
			},
		};
	}

	return api
		.getStripeForUPE( paymentMethodType )
		.createPaymentMethod( { elements, params: params } );
}

/**
 * Creates a Stripe payment element with the specified payment method type and options. The function
 * retrieves the necessary data from the UPE configuration and initializes the appearance. It then creates the
 * Stripe elements and the Stripe payment element, which is attached to the gatewayUPEComponents object afterward.
 *
 * @param {Object} api The API object used to create the Stripe payment element.
 * @param {string} paymentMethodType The type of Stripe payment method to create.
 * @return {Object} A promise that resolves with the created Stripe payment element.
 */
async function createStripePaymentElement( api, paymentMethodType ) {
	const amount = Number( getUPEConfig( 'cartTotal' ) );
	const paymentMethodTypes = getPaymentMethodTypes( paymentMethodType );
	const options = {
		mode: 1 > amount ? 'setup' : 'payment',
		currency: getUPEConfig( 'currency' ).toLowerCase(),
		amount: amount,
		paymentMethodCreation: 'manual',
		paymentMethodTypes: paymentMethodTypes,
		appearance: initializeAppearance( api ),
	};

	const elements = api
		.getStripeForUPE( paymentMethodType )
		.elements( options );
	const createdStripePaymentElement = elements.create( 'payment', {
		...getUpeSettings(),
		wallets: {
			applePay: 'never',
			googlePay: 'never',
		},
	} );

	gatewayUPEComponents[ paymentMethodType ].elements = elements;
	gatewayUPEComponents[
		paymentMethodType
	].upeElement = createdStripePaymentElement;
	return createdStripePaymentElement;
}

/**
 * Appends a hidden input field with the confirmed setup intent ID to the provided form.
 *
 * @param {HTMLElement} form The HTML form element to which the input field will be appended.
 * @param {Object} confirmedIntent The confirmed setup intent object containing the ID to be stored in the input field.
 */
function appendSetupIntentToForm( form, confirmedIntent ) {
	const input = document.createElement( 'input' );
	input.type = 'hidden';
	input.id = 'wcpay-setup-intent';
	input.name = 'wcpay-setup-intent';
	input.value = confirmedIntent.id;

	form.append( input );
}

/**
 * If Link is enabled, add event listeners and handlers.
 *
 * @param {Object} api WCPayAPI instance.
 */
export function maybeEnableStripeLink( api ) {
	if ( isLinkEnabled( getUPEConfig( 'paymentMethodsConfig' ) ) ) {
		enableStripeLinkPaymentMethod( {
			api: api,
			elements: gatewayUPEComponents.card.elements,
			emailId: 'billing_email',
			complete_billing: () => {
				return true;
			},
			complete_shipping: () => {
				return (
					document.getElementById(
						'ship-to-different-address-checkbox'
					) &&
					document.getElementById(
						'ship-to-different-address-checkbox'
					).checked
				);
			},
			shipping_fields: SHORTCODE_SHIPPING_ADDRESS_FIELDS,
			billing_fields: SHORTCODE_BILLING_ADDRESS_FIELDS,
		} );
	}
}

/**
 * Mounts the existing Stripe Payment Element to the DOM element.
 * Creates the Stipe Payment Element instance if it doesn't exist and mounts it to the DOM element.
 *
 * @param {Object} api The API object.
 * @param {string} domElement The selector of the DOM element of particular payment method to mount the UPE element to.
 **/
export async function mountStripePaymentElement( api, domElement ) {
	try {
		if ( ! fingerprint ) {
			const { visitorId } = await getFingerprint();
			fingerprint = visitorId;
		}
	} catch ( error ) {
		showErrorCheckout( error.message );
		return;
	}

	/*
	 * Trigger this event to ensure the tokenization-form.js init
	 * is executed.
	 *
	 * This script handles the radio input interaction when toggling
	 * between the user's saved card / entering new card details.
	 *
	 * Ref: https://github.com/woocommerce/woocommerce/blob/2429498/assets/js/frontend/tokenization-form.js#L109
	 */
	const event = new Event( 'wc-credit-card-form-init' );
	document.body.dispatchEvent( event );

	const paymentMethodType = domElement.dataset.paymentMethodType;
	const upeElement =
		gatewayUPEComponents[ paymentMethodType ].upeElement ||
		( await createStripePaymentElement( api, paymentMethodType ) );
	upeElement.mount( domElement );
}

/**
 * Creates and confirms a setup intent using the provided ID, then appends the confirmed setup intent to the given jQuery form.
 *
 * @param {Object} id Payment method object ID.
 * @param {Object} $form The jQuery object for the form to which the confirmed setup intent will be appended.
 * @param {Object} api The API object with the setupIntent method to create and confirm the setup intent.
 *
 * @return {Promise} A promise that resolves when the setup intent is confirmed and appended to the form.
 */
export const createAndConfirmSetupIntent = ( { id }, $form, api ) => {
	return api.setupIntent( id ).then( function ( confirmedSetupIntent ) {
		appendSetupIntentToForm( $form, confirmedSetupIntent );
	} );
};

/**
 * Updates the terms parameter in the Payment Element based on the "save payment information" checkbox.
 *
 * @param {Event} event The change event that triggers the function.
 */
export function renderTerms( event ) {
	const isChecked = event.target.checked;
	const value = isChecked ? 'always' : 'never';
	const paymentMethodType = getSelectedUPEGatewayPaymentMethod();
	if ( ! paymentMethodType ) {
		return;
	}
	const upeElement = gatewayUPEComponents[ paymentMethodType ].upeElement;
	if ( getUPEConfig( 'isUPEEnabled' ) && upeElement ) {
		upeElement.update( {
			terms: getTerms( getUPEConfig( 'paymentMethodsConfig' ), value ),
		} );
	}
}

/**
 * Handles the checkout process for the provided jQuery form and Stripe payment method type. The function blocks the
 * form UI to prevent duplicate submission and validates the Stripe elements. It then creates a Stripe payment method
 * object and appends the necessary data to the form for checkout completion. Finally, it submits the form and prevents
 * the default form submission from WC Core.
 *
 * @param {Object} api The API object used to create the Stripe payment method.
 * @param {Object} jQueryForm The jQuery object for the form being submitted.
 * @param {string} paymentMethodType The type of Stripe payment method being used.
 * @return {boolean} return false to prevent the default form submission from WC Core.
 */
let hasCheckoutCompleted;
export const processPayment = (
	api,
	jQueryForm,
	paymentMethodType,
	additionalActionsHandler = () => {}
) => {
	if ( hasCheckoutCompleted ) {
		hasCheckoutCompleted = false;
		return;
	}

	blockUI( jQueryForm );

	const elements = gatewayUPEComponents[ paymentMethodType ].elements;

	( async () => {
		try {
			await validateElements( elements );
			const paymentMethodObject = await createStripePaymentMethod(
				api,
				elements,
				jQueryForm,
				paymentMethodType
			);
			appendFingerprintInputToForm( jQueryForm, fingerprint );
			appendPaymentMethodIdToForm(
				jQueryForm,
				paymentMethodObject.paymentMethod.id
			);
			await additionalActionsHandler(
				paymentMethodObject.paymentMethod,
				jQueryForm,
				api
			);
			hasCheckoutCompleted = true;
			submitForm( jQueryForm );
		} catch ( err ) {
			hasCheckoutCompleted = false;
			jQueryForm.removeClass( 'processing' ).unblock();
			showErrorCheckout( err.message );
		}
	} )();

	// Prevent WC Core default form submission (see woocommerce/assets/js/frontend/checkout.js) from happening.
	return false;
};
