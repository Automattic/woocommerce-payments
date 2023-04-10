/**
 * Internal dependencies
 */
import WCPayAPI from 'wcpay/checkout/api';
import { getUPEConfig } from 'wcpay/utils/checkout';
import apiRequest from '../../utils/request';
import { getAppearance } from '../../upe-styles';
import showErrorCheckout from 'wcpay/checkout/utils/show-error-checkout';
import {
	appendFingerprintInputToForm,
	getFingerprint,
} from 'wcpay/checkout/utils/fingerprint';
import {
	appendPaymentMethodIdToForm,
	getUpeSettings,
} from 'wcpay/checkout/utils/upe';

const gatewayUPEComponents = {};
const api = new WCPayAPI(
	{
		publishableKey: getUPEConfig( 'publishableKey' ),
		accountId: getUPEConfig( 'accountId' ),
		forceNetworkSavedCards: getUPEConfig( 'forceNetworkSavedCards' ),
		locale: getUPEConfig( 'locale' ),
	},
	apiRequest
);

let fingerprint = null;

inititalizeStripeElements();

export function initializeAppearance() {
	let appearance = getUPEConfig( 'upeAppearance' );
	if ( ! appearance ) {
		appearance = getAppearance();
		api.saveUPEAppearance( appearance );
	}
	return appearance;
}

export function inititalizeStripeElements() {
	for ( const paymentMethodType in getUPEConfig( 'paymentMethodsConfig' ) ) {
		gatewayUPEComponents[ paymentMethodType ] = {
			elements: null,
			upeElement: null,
		};
	}
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

function createStripePaymentMethod( elements ) {
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

function validateElements( elements, jQueryForm ) {
	elements.submit().then( ( result ) => {
		if ( result.error ) {
			jQueryForm.removeClass( 'processing' ).unblock();
			showErrorCheckout( result.error.message );
		}
	} );
}

function submitForm( jQueryForm ) {
	jQueryForm.removeClass( 'processing' ).submit();
}

async function createStripePaymentElement( paymentMethodType ) {
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
 * @param {string} domElement The selector of the DOM element of particular payment method to mount the UPE element to.
 **/
export async function mountStripePaymentElement( domElement ) {
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
		( await createStripePaymentElement( paymentMethodType ) );
	upeElement.mount( domElement );
}

let hasCheckoutCompleted;
export const checkout = ( jQueryForm, paymentMethodType ) => {
	if ( hasCheckoutCompleted ) {
		hasCheckoutCompleted = false;
		return;
	}

	blockUI( jQueryForm );

	const elements = gatewayUPEComponents[ paymentMethodType ].elements;
	validateElements( elements, jQueryForm );
	createStripePaymentMethod( elements )
		.then( ( paymentMethodObject ) => {
			appendFingerprintInputToForm( jQueryForm, fingerprint );
			appendPaymentMethodIdToForm(
				jQueryForm,
				paymentMethodObject.paymentMethod.id
			);
			hasCheckoutCompleted = true;
			submitForm( jQueryForm );
		} )
		.catch( ( error ) => {
			jQueryForm.removeClass( 'processing' ).unblock();
			showErrorCheckout( error.message );
		} );

	// Prevent WC Core default form submission (see woocommerce/assets/js/frontend/checkout.js) from happening.
	return false;
};
