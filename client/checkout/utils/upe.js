/**
 * Internal dependencies
 */
import { getUPEConfig } from 'wcpay/utils/checkout';
import { getPaymentMethodsConstants } from '../constants';

/**
 * Generates terms parameter for UPE, with value set for reusable payment methods
 *
 * @param {Object} paymentMethodsConfig Object mapping payment method strings to their settings.
 * @param {string} value The terms value for each available payment method.
 * @return {Object} Terms parameter fit for UPE.
 */
export const getTerms = ( paymentMethodsConfig, value = 'always' ) => {
	const reusablePaymentMethods = Object.keys( paymentMethodsConfig ).filter(
		( method ) =>
			// Stripe link doesn't need the "terms" - adding this property causes a warning in the console.
			method !== 'link' && paymentMethodsConfig[ method ].isReusable
	);

	return reusablePaymentMethods.reduce( ( obj, method ) => {
		obj[ method ] = value;
		return obj;
	}, {} );
};

/**
 * Finds selected payment gateway and returns matching Stripe payment method for gateway.
 *
 * @return {string} Stripe payment method type
 */
export const getSelectedUPEGatewayPaymentMethod = () => {
	const paymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );
	const gatewayCardId = getUPEConfig( 'gatewayId' );
	let selectedGatewayId = null;

	// Handle payment method selection on the Checkout page or Add Payment Method page where class names differ.
	const radio = document.querySelector(
		'li.wc_payment_method input.input-radio:checked, li.woocommerce-PaymentMethod input.input-radio:checked'
	);
	if ( radio !== null ) {
		selectedGatewayId = radio.id;
	}

	if ( selectedGatewayId === 'payment_method_woocommerce_payments' ) {
		selectedGatewayId = 'payment_method_woocommerce_payments_card';
	}

	let selectedPaymentMethod = null;

	for ( const paymentMethodType in paymentMethodsConfig ) {
		if (
			`payment_method_${ gatewayCardId }_${ paymentMethodType }` ===
			selectedGatewayId
		) {
			selectedPaymentMethod = paymentMethodType;
			break;
		}
	}

	return selectedPaymentMethod;
};

export const getHiddenBillingFields = ( enabledBillingFields ) => {
	return {
		name:
			enabledBillingFields.includes( 'billing_first_name' ) ||
			enabledBillingFields.includes( 'billing_last_name' )
				? 'never'
				: 'auto',
		email: enabledBillingFields.includes( 'billing_email' )
			? 'never'
			: 'auto',
		phone: enabledBillingFields.includes( 'billing_phone' )
			? 'never'
			: 'auto',
		address: {
			country: enabledBillingFields.includes( 'billing_country' )
				? 'never'
				: 'auto',
			line1: enabledBillingFields.includes( 'billing_address_1' )
				? 'never'
				: 'auto',
			line2: enabledBillingFields.includes( 'billing_address_2' )
				? 'never'
				: 'auto',
			city: enabledBillingFields.includes( 'billing_city' )
				? 'never'
				: 'auto',
			state: enabledBillingFields.includes( 'billing_state' )
				? 'never'
				: 'auto',
			postalCode: enabledBillingFields.includes( 'billing_postcode' )
				? 'never'
				: 'auto',
		},
	};
};

export const getUpeSettings = () => {
	const upeSettings = {};
	const showTerms = shouldIncludeTerms() ? 'always' : 'never';

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

	return upeSettings;
};

function shouldIncludeTerms() {
	if ( getUPEConfig( 'cartContainsSubscription' ) ) {
		return true;
	}

	const savePaymentMethodCheckbox = document.getElementById(
		'wc-woocommerce_payments-new-payment-method'
	);
	if (
		savePaymentMethodCheckbox !== null &&
		savePaymentMethodCheckbox.checked
	) {
		return true;
	}

	return false;
}

export const generateCheckoutEventNames = () => {
	return getPaymentMethodsConstants()
		.map( ( method ) => `checkout_place_order_${ method }` )
		.join( ' ' );
};

export const appendPaymentMethodIdToForm = ( $form, paymentMethodId ) => {
	$form.append(
		`<input type="hidden" id="wcpay-payment-method" name="wcpay-payment-method" value="${ paymentMethodId }" />`
	);
};

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
	const prefix = '#wc-woocommerce_payments';
	const suffix = '-payment-token-new';
	const savedPaymentSelector =
		paymentMethodType === 'card' || paymentMethodType === 'link'
			? prefix + suffix
			: prefix + '_' + paymentMethodType + suffix;

	return (
		document.querySelector( savedPaymentSelector ) !== null &&
		! document.querySelector( savedPaymentSelector ).checked
	);
}

export function dispatchChangeEventFor( element ) {
	const event = new Event( 'change', { bubbles: true } );
	element.dispatchEvent( event );
}

/**
 * Returns the prepared set of options needed to initialize the Stripe elements for UPE in Block Checkout.
 * The initial options have all the fields set to 'never' to hide them from the UPE, because all the
 * information is already collected in the checkout form. Additionally, the options are updated with
 * the terms text if needed.
 *
 * @param {boolean} shouldSavePayment Whether the payment method should be saved.
 * @param {Object} paymentMethodsConfig The payment methods config object.
 *
 * @return {Object} The options object for the Stripe elements.
 */
export const getStripeElementOptions = (
	shouldSavePayment,
	paymentMethodsConfig
) => {
	const options = {
		fields: {
			billingDetails: {
				name: 'never',
				email: 'never',
				phone: 'never',
				address: {
					country: 'never',
					line1: 'never',
					line2: 'never',
					city: 'never',
					state: 'never',
					postalCode: 'never',
				},
			},
		},
		wallets: {
			applePay: 'never',
			googlePay: 'never',
		},
	};

	const showTerms =
		shouldSavePayment || getUPEConfig( 'cartContainsSubscription' )
			? 'always'
			: 'never';

	options.terms = getTerms( paymentMethodsConfig, showTerms );

	return options;
};

/**
 * Check whether Stripe Link is enabled.
 *
 * @param {Object} paymentMethodsConfig Checkout payment methods configuration settings object.
 * @return {boolean} True, if enabled; false otherwise.
 */
export const isLinkEnabled = ( paymentMethodsConfig ) => {
	return (
		paymentMethodsConfig.link !== undefined &&
		paymentMethodsConfig.card !== undefined
	);
};

/**
 * Get array of payment method types to use with intent.
 *
 * @param {string} paymentMethodType Payment method type Stripe ID.
 * @return {Array} Array of payment method types to use with intent.
 */
export const getPaymentMethodTypes = ( paymentMethodType ) => {
	const paymentMethodTypes = [ paymentMethodType ];
	if (
		paymentMethodType === 'card' &&
		isLinkEnabled( getUPEConfig( 'paymentMethodsConfig' ) )
	) {
		paymentMethodTypes.push( 'link' );
	}
	return paymentMethodTypes;
};

/**
 * Returns the value of the email input on the blocks checkout page.
 *
 * @return {string} The value of email input.
 */
export const getBlocksEmailValue = () => {
	return document.getElementById( 'email' ).value;
};

/**
 * Function to initialise Stripe Link button on email input field.
 *
 * @param {Object} linkAutofill Stripe Link Autofill instance.
 */
export const blocksShowLinkButtonHandler = ( linkAutofill ) => {
	const emailInput = document.getElementById( 'email' );

	const stripeLinkButton = document.createElement( 'button' );
	stripeLinkButton.setAttribute( 'class', 'wcpay-stripelink-modal-trigger' );
	stripeLinkButton.style.display = emailInput.value ? 'inline-block' : 'none';
	stripeLinkButton.addEventListener( 'click', ( event ) => {
		event.preventDefault();
		linkAutofill.launch( {
			email: document.getElementById( 'email' ).value,
		} );
	} );

	emailInput.parentNode.appendChild( stripeLinkButton );
};

/**
 * Hides payment method if it has set specific countries in the PHP class.
 *
 * @param {Object} upeElement The selector of the DOM element of particular payment method to mount the UPE element to.
 * @return {boolean} Whether the payment method is restricted to selected billing country.
 **/
export const isPaymentMethodRestrictedToLocation = ( upeElement ) => {
	const paymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );
	const paymentMethodType = upeElement.dataset.paymentMethodType;
	return !! paymentMethodsConfig[ paymentMethodType ].countries.length;
};

/**
 * @param {Object} upeElement The selector of the DOM element of particular payment method to mount the UPE element to.
 **/
export const togglePaymentMethodForCountry = ( upeElement ) => {
	const paymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );
	const paymentMethodType = upeElement.dataset.paymentMethodType;
	const supportedCountries =
		paymentMethodsConfig[ paymentMethodType ].countries;

	/* global wcpayCustomerData */
	// in the case of "pay for order", there is no "billing country" input, so we need to rely on backend data.
	const billingCountry =
		document.getElementById( 'billing_country' )?.value ||
		wcpayCustomerData?.billing_country ||
		'';

	const upeContainer = document.querySelector(
		'.payment_method_woocommerce_payments_' + paymentMethodType
	);
	if ( supportedCountries.includes( billingCountry ) ) {
		upeContainer.style.display = 'block';
	} else {
		upeContainer.style.display = 'none';
	}
};
