/* global wc_address_i18n_params */

/**
 * Internal dependencies
 */
import { getUPEConfig } from 'wcpay/utils/checkout';
import { getTerms } from '../utils/upe';
import { SHORTCODE_BILLING_ADDRESS_FIELDS } from './constants';

/**
 * Returns the gateway ID for a given payment method type.
 *
 * @param {string} paymentMethodType Payment method type (e.g. 'card', 'bancontact')
 * @return {string} Gateway ID (e.g. 'woocommerce_payments', 'woocommerce_payments_bancontact')
 */
export const getGatewayIdBy = ( paymentMethodType ) => {
	const gatewayPrefix = 'woocommerce_payments';
	// Only append underscore and payment method type for non-card payments
	return paymentMethodType === 'card'
		? gatewayPrefix
		: `${ gatewayPrefix }_${ paymentMethodType }`;
};

/**
 * Returns Stripe payment method (e.g. card, bancontact ) for selected payment gateway.
 *
 * @return {string} Payment method name
 */
export const getSelectedUPEGatewayPaymentMethod = () => {
	const selectedGateway = document.querySelector(
		'input[name="payment_method"][value*="woocommerce_payments"]:checked'
	);

	if ( ! selectedGateway ) {
		return null;
	}

	// 'woocommerce_payments_affirm' => 'affirm'
	// 'woocommerce_payments_p24' -> 'p24'
	// 'woocommerce_payments' -> ''
	const paymentMethodType = selectedGateway.value
		// non-card elements are prefixed with `woocommerce_payments_*`
		.replace( 'woocommerce_payments_', '' )
		// the card element is just called `woocommerce_payments` - we need to account for variation in the name
		.replace( 'woocommerce_payments', '' );

	// if the string is empty, it's the card element
	return paymentMethodType || 'card';
};

/**
 * Determines which billing fields should be hidden in the Stripe payment element.
 *
 * @param {Object} enabledBillingFields Object containing all the billing fields for the WooCommerce checkout.
 * @return {Object} Object mapping billing field names to their hidden status.
 */
export const getHiddenBillingFields = ( enabledBillingFields ) => {
	return {
		name:
			enabledBillingFields.billing_first_name ||
			enabledBillingFields.billing_last_name
				? 'never'
				: 'auto',
		email: enabledBillingFields.billing_email ? 'never' : 'auto',
		phone: enabledBillingFields.billing_phone ? 'never' : 'auto',
		address: {
			country: enabledBillingFields.billing_country ? 'never' : 'auto',
			line1: enabledBillingFields.billing_address_1 ? 'never' : 'auto',
			line2: enabledBillingFields.billing_address_2 ? 'never' : 'auto',
			city: enabledBillingFields.billing_city ? 'never' : 'auto',
			state: enabledBillingFields.billing_state ? 'never' : 'auto',
			postalCode: enabledBillingFields.billing_postcode
				? 'never'
				: 'auto',
		},
	};
};

/**
 * Checks if terms should be shown for the payment method.
 *
 * @param {string} paymentMethodType The type of payment method being configured
 * @return {boolean} Whether to include terms
 */
function shouldIncludeTerms( paymentMethodType ) {
	if ( getUPEConfig( 'cartContainsSubscription' ) ) {
		return true;
	}

	const paymentsForm = document.querySelector(
		`.wcpay-upe-form[data-payment-method-type="${ paymentMethodType }"]`
	);
	if ( ! paymentsForm ) {
		return false;
	}

	const savePaymentMethodCheckbox = paymentsForm.querySelector(
		`#wc-${ getGatewayIdBy( paymentMethodType ) }-new-payment-method`
	);

	return savePaymentMethodCheckbox?.checked || false;
}

/**
 * Generates payment method specific settings object for the Stripe Payment Elements.
 * Includes terms visibility, billing fields configuration, and default customer values.
 *
 * @param {string} paymentMethodType The type of payment method being configured (e.g. card, bancontact)
 * @return {Object} Settings object for Payment Elements
 */
export const getUpeSettings = ( paymentMethodType ) => {
	const upeSettings = {};
	const showTerms = shouldIncludeTerms( paymentMethodType )
		? 'always'
		: 'never';

	upeSettings.terms = getTerms(
		getUPEConfig( 'paymentMethodsConfig' ),
		showTerms
	);

	if (
		getUPEConfig( 'isCheckout' ) &&
		! (
			getUPEConfig( 'isOrderPay' ) || getUPEConfig( 'isChangingPayment' )
		)
	) {
		upeSettings.fields = {
			billingDetails: getHiddenBillingFields(
				getUPEConfig( 'enabledBillingFields' )
			),
		};
	}

	if ( window.wcpayCustomerData ) {
		upeSettings.defaultValues = {
			billingDetails: {
				name: window.wcpayCustomerData.name,
				email: window.wcpayCustomerData.email,
				address: {
					country: window.wcpayCustomerData.billing_country,
				},
			},
		};
	}

	return upeSettings;
};

/**
 * Generates checkout event names for all payment methods.
 *
 * @return {string} Space-separated list of checkout event names
 */
export const generateCheckoutEventNames = () => {
	return Object.values( getUPEConfig( 'paymentMethodsConfig' ) )
		.map( ( method ) => `checkout_place_order_${ method.gatewayId }` )
		.join( ' ' );
};

/**
 * Appends the payment method ID to the form.
 *
 * @param {Object} $form jQuery form object
 * @param {string} paymentMethodId Payment method ID to append
 */
export const appendPaymentMethodIdToForm = ( $form, paymentMethodId ) => {
	$form.append(
		`<input type="hidden" id="wcpay-payment-method" name="wcpay-payment-method" value="${ paymentMethodId }" />`
	);
};

/**
 * Appends payment method error data to the form.
 *
 * @param {Object} $form jQuery form object
 * @param {Object} paymentMethodError Error object containing code, decline_code, message, and type
 */
export const appendPaymentMethodErrorDataToForm = (
	$form,
	paymentMethodError
) => {
	[
		[ 'wcpay-payment-method-error-code', paymentMethodError.code ],
		[
			'wcpay-payment-method-error-decline-code',
			paymentMethodError.decline_code,
		],
		[ 'wcpay-payment-method-error-message', paymentMethodError.message ],
		[ 'wcpay-payment-method-error-type', paymentMethodError.type ],
	].forEach( ( [ fieldName, value ] ) => {
		$form.append(
			`<input type="hidden" id="${ fieldName }" name="${ fieldName }" value="${ value }" />`
		);
	} );
};

/**
 * Appends fraud prevention token to the form.
 *
 * @param {Object} $form jQuery form object
 */
export const appendFraudPreventionTokenInputToForm = ( $form ) => {
	const fraudPreventionToken = window.wcpayFraudPreventionToken ?? '';
	$form.append(
		`<input type="hidden" id="wcpay-fraud-prevention-token" name="wcpay-fraud-prevention-token" value="${ fraudPreventionToken }" />`
	);
};

/**
 * Checks if the customer is using a saved payment method.
 *
 * @param {string} paymentMethodType Stripe payment method type ID.
 * @return {boolean} Boolean indicating whether a saved payment method is being used.
 */
export function isUsingSavedPaymentMethod( paymentMethodType ) {
	const paymentsForm = document.querySelector(
		`.wcpay-upe-form[data-payment-method-type="${ paymentMethodType }"]`
	);
	if ( ! paymentsForm ) {
		return false;
	}

	const newPaymentTokenInputId = `wc-${ getGatewayIdBy(
		paymentMethodType
	) }-payment-token-new`;
	const newPaymentTokenInput = paymentsForm.querySelector(
		`input#${ newPaymentTokenInputId }`
	);
	if ( ! newPaymentTokenInput ) {
		return false;
	}

	return ! newPaymentTokenInput.checked;
}

/**
 * Dispatches a change event for an element.
 *
 * @param {HTMLElement} element DOM element to dispatch event on
 */
export function dispatchChangeEventFor( element ) {
	const event = new Event( 'change', { bubbles: true } );
	element.dispatchEvent( event );
}

/**
 * Returns true if the payment method has configured with any country restrictions.
 *
 * @param {HTMLElement} upeElement The selector of the DOM element of particular payment method to mount the UPE element to.
 * @return {boolean} Whether the payment method is restricted to selected billing country.
 **/
export const hasPaymentMethodCountryRestrictions = ( upeElement ) => {
	const paymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );
	const paymentMethodType = upeElement.dataset.paymentMethodType;
	return !! paymentMethodsConfig[ paymentMethodType ].countries.length;
};

/**
 * Hides payment method if it has set specific countries in the PHP class.
 *
 * @param {HTMLElement} upeElement The selector of the DOM element of particular payment method to mount the UPE element to.
 **/
export const togglePaymentMethodForCountry = ( upeElement ) => {
	const paymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );
	const paymentMethodType = upeElement.dataset.paymentMethodType;
	const supportedCountries =
		paymentMethodsConfig[ paymentMethodType ].countries;
	const selectedPaymentMethod = getSelectedUPEGatewayPaymentMethod();
	// Simplified approach - find the form ancestor and then search within it
	let billingInput = upeElement
		?.closest( 'form.checkout, form#add_payment_method' )
		?.querySelector( '[name="billing_country"]' );

	// If not found, fallback to the search in the whole document
	if ( ! billingInput ) {
		billingInput = document.querySelector( '#billing_country' );
	}

	// in the case of "pay for order", there is no "billing country" input, so we need to rely on backend data.
	const billingCountry =
		billingInput?.value || window?.wcpayCustomerData?.billing_country || '';

	const upeContainer = upeElement?.closest( '.wc_payment_method' );
	if ( supportedCountries.includes( billingCountry ) ) {
		upeContainer.style.removeProperty( 'display' );
	} else {
		upeContainer.style.display = 'none';
		if ( paymentMethodType === selectedPaymentMethod ) {
			const cardPaymentForm = document.querySelector(
				'input[name="payment_method"][value="woocommerce_payments"]'
			);

			cardPaymentForm?.click();
		}
	}
};

/**
 * Parses the locale from WooCommerce address i18n params.
 *
 * @return {Object|null} Parsed locale object or null if parsing fails
 */
function getParsedLocale() {
	try {
		return JSON.parse(
			wc_address_i18n_params.locale.replace( /&quot;/g, '"' )
		);
	} catch ( e ) {
		return null;
	}
}

/**
 * Checks if required billing information is missing from the checkout form.
 *
 * @return {boolean} True if required billing information is missing
 */
export const isBillingInformationMissing = () => {
	const enabledBillingFields = getUPEConfig( 'enabledBillingFields' );

	// first name and last name are kinda special - we just need one of them to be at checkout
	const name = `${
		document.querySelector(
			`#${ SHORTCODE_BILLING_ADDRESS_FIELDS.first_name }`
		)?.value || ''
	} ${
		document.querySelector(
			`#${ SHORTCODE_BILLING_ADDRESS_FIELDS.last_name }`
		)?.value || ''
	}`.trim();
	if (
		! name &&
		( enabledBillingFields[ SHORTCODE_BILLING_ADDRESS_FIELDS.first_name ] ||
			enabledBillingFields[ SHORTCODE_BILLING_ADDRESS_FIELDS.last_name ] )
	) {
		return true;
	}

	const billingFieldsToValidate = [
		'billing_email',
		SHORTCODE_BILLING_ADDRESS_FIELDS.country,
		SHORTCODE_BILLING_ADDRESS_FIELDS.address_1,
		SHORTCODE_BILLING_ADDRESS_FIELDS.city,
		SHORTCODE_BILLING_ADDRESS_FIELDS.postcode,
	].filter( ( field ) => enabledBillingFields[ field ] );

	const country = billingFieldsToValidate.includes(
		SHORTCODE_BILLING_ADDRESS_FIELDS.country
	)
		? document.querySelector(
				`#${ SHORTCODE_BILLING_ADDRESS_FIELDS.country }`
		  )?.value
		: null;

	// We need to just find one field with missing information. If even only one is missing, just return early.
	return Boolean(
		billingFieldsToValidate.find( ( fieldName ) => {
			const field = document.querySelector( `#${ fieldName }` );
			let isRequired = enabledBillingFields[ fieldName ]?.required;
			const locale = getParsedLocale();

			if ( country && locale && fieldName !== 'billing_email' ) {
				const key = fieldName.replace( 'billing_', '' );
				isRequired =
					locale[ country ]?.[ key ]?.required ??
					locale.default?.[ key ]?.required;
			}

			const hasValue = field?.value;

			return isRequired && ! hasValue;
		} )
	);
};
