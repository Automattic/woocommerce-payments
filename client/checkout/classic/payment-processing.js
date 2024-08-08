/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { getUPEConfig } from 'wcpay/utils/checkout';
import { getAppearance, getFontRulesFromPage } from '../upe-styles';
import { normalizeCurrencyToMinorUnit } from 'wcpay/checkout/utils';
import showErrorCheckout from 'wcpay/checkout/utils/show-error-checkout';
import {
	appendFingerprintInputToForm,
	getFingerprint,
} from 'wcpay/checkout/utils/fingerprint';
import {
	appendFraudPreventionTokenInputToForm,
	appendPaymentMethodIdToForm,
	appendPaymentMethodErrorDataToForm,
	getPaymentMethodTypes,
	getSelectedUPEGatewayPaymentMethod,
	getTerms,
	getUpeSettings,
	isLinkEnabled,
} from 'wcpay/checkout/utils/upe';
import enableStripeLinkPaymentMethod, {
	switchToNewPaymentTokenElement,
} from 'wcpay/checkout/stripe-link';
import {
	SHORTCODE_SHIPPING_ADDRESS_FIELDS,
	SHORTCODE_BILLING_ADDRESS_FIELDS,
	PAYMENT_METHOD_ERROR,
} from 'wcpay/checkout/constants';

// It looks like on file import there are some side effects. Should probably be fixed.
const gatewayUPEComponents = {};
let fingerprint = null;

for ( const paymentMethodType in getUPEConfig( 'paymentMethodsConfig' ) ) {
	gatewayUPEComponents[ paymentMethodType ] = {
		elements: null,
		upeElement: null,
		hasLoadError: false,
	};
}

/**
 * Initializes the appearance of the payment element by retrieving the UPE configuration
 * from the API and saving the appearance if it doesn't exist. If the appearance already exists,
 * it is simply returned.
 *
 * @param {Object} api The API object used to save the UPE configuration.
 * @param {string} elementsLocation The location of the UPE elements.
 * @return {Promise<Object>} The appearance object for the UPE.
 */
async function initializeAppearance( api, elementsLocation ) {
	const upeConfigMap = {
		shortcode_checkout: 'upeAppearance',
		add_payment_method: 'upeAddPaymentMethodAppearance',
	};
	const upeConfigProperty =
		upeConfigMap[ elementsLocation ] ?? 'upeAppearance';
	const appearance = getUPEConfig( upeConfigProperty );
	if ( appearance ) {
		return Promise.resolve( appearance );
	}

	return await api.saveUPEAppearance(
		getAppearance( elementsLocation ),
		elementsLocation
	);
}

/**
 * Block the UI to indicate processing and avoid duplicate submission.
 *
 * @param {Object} $form The jQuery object for the form.
 */
export async function blockUI( $form ) {
	await $form.addClass( 'processing' ).block( {
		message: null,
		overlayCSS: {
			background: '#fff',
			opacity: 0.6,
		},
	} );
}

/**
 * Unblocks the UI to allow payment processing.
 *
 * @param {Object} $form The jQuery object for the form.
 */
export function unblockUI( $form ) {
	$form.removeClass( 'processing' ).unblock();
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
	/* global wcpayCustomerData */
	let params = {};
	if ( window.wcpayCustomerData ) {
		params = {
			billing_details: {
				name: wcpayCustomerData.name || undefined,
				email: wcpayCustomerData.email,
				address: {
					country: wcpayCustomerData.billing_country,
				},
			},
		};
	}

	if ( jQueryForm.attr( 'name' ) === 'checkout' ) {
		params = {
			billing_details: {
				...params.billing_details,
				name:
					`${
						document.querySelector(
							`#${ SHORTCODE_BILLING_ADDRESS_FIELDS.first_name }`
						)?.value || ''
					} ${
						document.querySelector(
							`#${ SHORTCODE_BILLING_ADDRESS_FIELDS.last_name }`
						)?.value || ''
					}`.trim() || undefined,
				email: document.querySelector( '#billing_email' )?.value,
				phone: document.querySelector( '#billing_phone' )?.value,
				address: {
					...params.billing_details?.address,
					city: document.querySelector(
						`#${ SHORTCODE_BILLING_ADDRESS_FIELDS.city }`
					)?.value,
					country: document.querySelector(
						`#${ SHORTCODE_BILLING_ADDRESS_FIELDS.country }`
					)?.value,
					line1: document.querySelector(
						`#${ SHORTCODE_BILLING_ADDRESS_FIELDS.address_1 }`
					)?.value,
					line2: document.querySelector(
						`#${ SHORTCODE_BILLING_ADDRESS_FIELDS.address_2 }`
					)?.value,
					postal_code: document.querySelector(
						`#${ SHORTCODE_BILLING_ADDRESS_FIELDS.postcode }`
					)?.value,
					state: document.querySelector(
						`#${ SHORTCODE_BILLING_ADDRESS_FIELDS.state }`
					)?.value,
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
 * @param {string} elementsLocation The location of the UPE elements.
 * @return {Object} A promise that resolves with the created Stripe payment element.
 */
async function createStripePaymentElement(
	api,
	paymentMethodType,
	elementsLocation
) {
	const amount = Number( getUPEConfig( 'cartTotal' ) );
	const paymentMethodTypes = getPaymentMethodTypes( paymentMethodType );
	const options = {
		mode: amount < 1 ? 'setup' : 'payment',
		currency: getUPEConfig( 'currency' ).toLowerCase(),
		amount: amount,
		paymentMethodCreation: 'manual',
		paymentMethodTypes: paymentMethodTypes,
		appearance: await initializeAppearance( api, elementsLocation ),
		fonts: getFontRulesFromPage(),
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
 * @param {HTMLElement} $form The HTML form element to which the input field will be appended.
 * @param {Object} confirmedIntent The confirmed setup intent object containing the ID to be stored in the input field.
 */
function appendSetupIntentToForm( $form, confirmedIntent ) {
	const input = document.createElement( 'input' );
	input.type = 'hidden';
	input.id = 'wcpay-setup-intent';
	input.name = 'wcpay-setup-intent';
	input.value = confirmedIntent.id;

	$form.append( input );
}

const ensureSameAsBillingIsUnchecked = () => {
	const sameAsBillingCheckbox = document.getElementById(
		'ship-to-different-address-checkbox'
	);

	if ( ! sameAsBillingCheckbox ) {
		return;
	}

	if ( sameAsBillingCheckbox.checked === true ) {
		return;
	}

	sameAsBillingCheckbox.checked = true;

	if ( window.jQuery ) {
		const $sameAsBillingCheckbox = window.jQuery( sameAsBillingCheckbox );

		$sameAsBillingCheckbox.prop( 'checked', true ).change();
	}
};

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
			onAutofill: ( billingAddress, shippingAddress ) => {
				const fillAddress = ( addressValues, fieldsMap ) => {
					// in some cases, the address might be empty.
					if ( ! addressValues ) return;

					// setting the country first, in case the "state"/"county"/"province"
					// select changes from a select to a text field (or vice-versa).
					const countryElement = document.getElementById(
						fieldsMap.country
					);
					if ( countryElement ) {
						countryElement.value = addressValues.country;
						// manually dispatching the "change" event, since the element might not be a `select2` component.
						countryElement.dispatchEvent( new Event( 'change' ) );
					}

					Object.entries( addressValues ).forEach(
						( [ piece, value ] ) => {
							const element = document.getElementById(
								fieldsMap[ piece ]
							);
							if ( element ) {
								element.value = value;
							}
						}
					);
				};

				// this is needed on shortcode checkout, but not on blocks checkout.
				ensureSameAsBillingIsUnchecked();

				fillAddress( billingAddress, SHORTCODE_BILLING_ADDRESS_FIELDS );
				fillAddress(
					shippingAddress,
					SHORTCODE_SHIPPING_ADDRESS_FIELDS
				);

				// manually dispatching the "change" event, since the element might be a `select2` component.
				document
					.querySelectorAll(
						`#${ SHORTCODE_BILLING_ADDRESS_FIELDS.country }, #${ SHORTCODE_BILLING_ADDRESS_FIELDS.state }, ` +
							`#${ SHORTCODE_SHIPPING_ADDRESS_FIELDS.country }, #${ SHORTCODE_SHIPPING_ADDRESS_FIELDS.state }`
					)
					.forEach( ( element ) => {
						if ( ! window.jQuery ) return;

						const $element = window.jQuery( element );
						if ( $element.data( 'select2' ) ) {
							$element.trigger( 'change' );
						}
					} );
			},
			onButtonShow: ( linkAutofill ) => {
				// Display StripeLink button if email field is prefilled.
				const billingEmailInput = document.getElementById(
					'billing_email'
				);
				if ( billingEmailInput.value !== '' ) {
					const linkButtonTop =
						billingEmailInput.offsetTop +
						( billingEmailInput.offsetHeight - 40 ) / 2;
					const stripeLinkButton = document.querySelector(
						'.wcpay-stripelink-modal-trigger'
					);
					stripeLinkButton.style.display = 'block';
					stripeLinkButton.style.top = `${ linkButtonTop }px`;
				}

				// Handle StripeLink button click.
				const stripeLinkButton = document.querySelector(
					'.wcpay-stripelink-modal-trigger'
				);
				stripeLinkButton.addEventListener( 'click', ( event ) => {
					event.preventDefault();
					// Trigger modal.
					linkAutofill.launch( { email: billingEmailInput.value } );
					switchToNewPaymentTokenElement();
				} );
			},
		} );
	}
}

/**
 * Mounts the existing Stripe Payment Element to the DOM element.
 * Creates the Stipe Payment Element instance if it doesn't exist and mounts it to the DOM element.
 *
 * @param {Object} api The API object.
 * @param {string} domElement The selector of the DOM element of particular payment method to mount the UPE element to.
 * @param {string} elementsLocation Thhe location of the UPE element.
 **/
export async function mountStripePaymentElement(
	api,
	domElement,
	elementsLocation
) {
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
	if ( ! gatewayUPEComponents[ paymentMethodType ] ) {
		return;
	}

	const upeElement =
		gatewayUPEComponents[ paymentMethodType ].upeElement ||
		( await createStripePaymentElement(
			api,
			paymentMethodType,
			elementsLocation
		) );
	upeElement.mount( domElement );
	upeElement.on( 'loaderror', ( e ) => {
		// setting the flag to true to prevent the form from being submitted.
		gatewayUPEComponents[ paymentMethodType ].hasLoadError = true;
		// unset any styling to ensure the WC error message wrapper can take more width.
		domElement.style.padding = '0';
		// creating a new element to be added to the DOM, so that the message can be displayed.
		const messageWrapper = document.createElement( 'div' );
		messageWrapper.classList.add( 'woocommerce-error' );
		messageWrapper.innerHTML = e.error.message;
		messageWrapper.style.margin = '0';
		domElement.appendChild( messageWrapper );
		// hiding any "save payment method" checkboxes.
		const savePaymentMethodWrapper = domElement
			.closest( '.payment_box' )
			?.querySelector( '.woocommerce-SavedPaymentMethods-saveNew' );
		if ( savePaymentMethodWrapper ) {
			savePaymentMethodWrapper.style.display = 'none';
		}
	} );
}

export async function mountStripePaymentMethodMessagingElement(
	api,
	domElement,
	cartData
) {
	const paymentMethodType = domElement.dataset.paymentMethodType;
	const appearance = await initializeAppearance( api );

	try {
		const paymentMethodMessagingElement = api
			.getStripe()
			.elements( {
				appearance: appearance,
				fonts: getFontRulesFromPage(),
			} )
			.create( 'paymentMethodMessaging', {
				currency: cartData.currency,
				amount: normalizeCurrencyToMinorUnit(
					cartData.amount,
					cartData.decimalPlaces
				),
				countryCode: cartData.country, // Customer's country or base country of the store.
				paymentMethodTypes: [ paymentMethodType ],
				displayType: 'promotional_text',
			} );

		return paymentMethodMessagingElement.mount( domElement );
	} finally {
		// Resolve the promise even if the element mounting fails.
		return Promise.resolve();
	}
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
	if ( upeElement ) {
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
	$form,
	paymentMethodType,
	additionalActionsHandler = () => Promise.resolve()
) => {
	if ( hasCheckoutCompleted ) {
		hasCheckoutCompleted = false;
		return;
	}

	( async () => {
		try {
			await blockUI( $form );

			const { elements, hasLoadError } = gatewayUPEComponents[
				paymentMethodType
			];

			if ( hasLoadError ) {
				throw new Error(
					__(
						'Invalid or missing payment details. Please ensure the provided payment method is correctly entered.',
						'woocommerce-payments'
					)
				);
			}

			await validateElements( elements );
			const paymentMethodObject = await createStripePaymentMethod(
				api,
				elements,
				$form,
				paymentMethodType
			);

			if ( paymentMethodObject.error ) {
				appendPaymentMethodIdToForm( $form, PAYMENT_METHOD_ERROR );
				appendPaymentMethodErrorDataToForm(
					$form,
					paymentMethodObject.error
				);
			} else {
				appendPaymentMethodIdToForm(
					$form,
					paymentMethodObject.paymentMethod.id
				);
			}
			appendFingerprintInputToForm( $form, fingerprint );
			appendFraudPreventionTokenInputToForm( $form );
			await additionalActionsHandler(
				paymentMethodObject.paymentMethod,
				$form,
				api
			);
			hasCheckoutCompleted = true;
			submitForm( $form );
		} catch ( err ) {
			hasCheckoutCompleted = false;
			unblockUI( $form );
			showErrorCheckout( err.message );
		}
	} )();

	// Prevent WC Core default form submission (see woocommerce/assets/js/frontend/checkout.js) from happening.
	return false;
};

/**
 * Used only for testing, resets the hasCheckoutCompleted value.
 *
 * @return {void}
 */
export function __resetHasCheckoutCompleted() {
	hasCheckoutCompleted = false;
}

/**
 * Used only for testing, resets the gatewayUPEComponents internal cache of elements for a given property.
 *
 * @param {string} paymentMethodType The paymentMethodType we want to remove the upeElement from.
 * @return {void}
 */
export function __resetGatewayUPEComponentsElement( paymentMethodType ) {
	if ( gatewayUPEComponents[ paymentMethodType ]?.upeElement ) {
		delete gatewayUPEComponents[ paymentMethodType ].upeElement;
	}
}
