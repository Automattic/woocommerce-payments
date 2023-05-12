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
	getSelectedUPEGatewayPaymentMethod,
	getTerms,
	getUpeSettings,
} from 'wcpay/checkout/utils/upe';

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
 * @param {Object} jQueryForm The jQuery object for the form being validated.
 * @return {Promise} Promise for the checkout submission.
 */
function validateElements( elements, jQueryForm ) {
	return elements.submit().then( ( result ) => {
		if ( result.error ) {
			jQueryForm.removeClass( 'processing' ).unblock();
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
 * @return {Promise<Object>} A promise that resolves with the created Stripe payment method.
 */
function createStripePaymentMethod( api, elements ) {
	return api.getStripe().createPaymentMethod( {
		elements,
		params: {
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
		},
	} );
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
	const options = {
		mode: 1 > amount ? 'setup' : 'payment',
		currency: getUPEConfig( 'currency' ).toLowerCase(),
		amount: amount,
		paymentMethodCreation: 'manual',
		paymentMethodTypes: [ paymentMethodType ],
		appearance: initializeAppearance( api ),
	};

	const elements = api.getStripe().elements( options );
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
	const paymentMethodType = domElement.dataset.paymentMethodType;
	const upeElement =
		gatewayUPEComponents[ paymentMethodType ].upeElement ||
		( await createStripePaymentElement( api, paymentMethodType ) );
	upeElement.mount( domElement );
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
export const checkout = ( api, jQueryForm, paymentMethodType ) => {
	if ( hasCheckoutCompleted ) {
		hasCheckoutCompleted = false;
		return;
	}

	blockUI( jQueryForm );

	const elements = gatewayUPEComponents[ paymentMethodType ].elements;

	( async () => {
		try {
			await validateElements( elements, jQueryForm );
			const paymentMethodObject = await createStripePaymentMethod(
				api,
				elements
			);
			appendFingerprintInputToForm( jQueryForm, fingerprint );
			appendPaymentMethodIdToForm(
				jQueryForm,
				paymentMethodObject.paymentMethod.id
			);
			hasCheckoutCompleted = true;
			submitForm( jQueryForm );
		} catch ( err ) {
			jQueryForm.removeClass( 'processing' ).unblock();
			showErrorCheckout( err.message );
		}
	} )();

	// Prevent WC Core default form submission (see woocommerce/assets/js/frontend/checkout.js) from happening.
	return false;
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
