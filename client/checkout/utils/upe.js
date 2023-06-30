/**
 * Internal dependencies
 */
import { getUPEConfig } from 'wcpay/utils/checkout';
import { WC_STORE_CART, getPaymentMethodsConstants } from '../constants';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Generates terms parameter for UPE, with value set for reusable payment methods
 *
 * @param {Object} paymentMethodsConfig Object mapping payment method strings to their settings.
 * @param {string} value The terms value for each available payment method.
 * @return {Object} Terms parameter fit for UPE.
 */
export const getTerms = ( paymentMethodsConfig, value = 'always' ) => {
	const reusablePaymentMethods = Object.keys( paymentMethodsConfig ).filter(
		( method ) => paymentMethodsConfig[ method ].isReusable
	);

	return reusablePaymentMethods.reduce( ( obj, method ) => {
		obj[ method ] = value;
		return obj;
	}, {} );
};

/**
 * Returns the value of the given cookie.
 *
 * @param {string} name Name of the cookie.
 *
 * @return {string} Value of the given cookie. Empty string if cookie doesn't exist.
 */
export const getCookieValue = ( name ) =>
	document.cookie.match( '(^|;)\\s*' + name + '\\s*=\\s*([^;]+)' )?.pop() ||
	'';

/**
 * Check if Card payment is being used.
 *
 * @return {boolean} Boolean indicating whether or not Card payment is being used.
 */
export const isWCPayChosen = function () {
	return document.getElementById( 'payment_method_woocommerce_payments' )
		.checked;
};

/**
 * Returns the cached payment intent for the current cart state.
 *
 * @param {Object} paymentMethodsConfig Array of configs for payment methods.
 * @param {string} paymentMethodType Type of the payment method.
 * @return {Object} The intent id and client secret required for mounting the UPE element.
 */
export const getPaymentIntentFromSession = (
	paymentMethodsConfig,
	paymentMethodType
) => {
	const cartHash = getCookieValue( 'woocommerce_cart_hash' );
	const upePaymentIntentData =
		paymentMethodsConfig[ paymentMethodType ].upePaymentIntentData;

	if (
		cartHash &&
		upePaymentIntentData &&
		upePaymentIntentData.startsWith( cartHash )
	) {
		const intentId = upePaymentIntentData.split( '-' )[ 1 ];
		const clientSecret = upePaymentIntentData.split( '-' )[ 2 ];
		return { intentId, clientSecret };
	}

	return {};
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
	if ( null !== radio ) {
		selectedGatewayId = radio.id;
	}

	if ( 'payment_method_woocommerce_payments' === selectedGatewayId ) {
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
		null !== savePaymentMethodCheckbox &&
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

export const appendPaymentMethodIdToForm = ( form, paymentMethodId ) => {
	form.append(
		`<input type="hidden" id="wcpay-payment-method" name="wcpay-payment-method" value="${ paymentMethodId }" />`
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
		'card' === paymentMethodType
			? prefix + suffix
			: prefix + '_' + paymentMethodType + suffix;

	return (
		null !== document.querySelector( savedPaymentSelector ) &&
		! document.querySelector( savedPaymentSelector ).checked
	);
}

/**
 *
 * Custom React hook that provides customer data and related functions for managing customer information.
 * The hook retrieves customer data from the WC_STORE_CART selector and dispatches actions to modify billing and shipping addresses.
 *
 * @return {Object} An object containing customer data and functions for managing customer information.
 */
export const useCustomerData = () => {
	const { customerData, isInitialized } = useSelect( ( select ) => {
		const store = select( WC_STORE_CART );
		return {
			customerData: store.getCustomerData(),
			isInitialized: store.hasFinishedResolution( 'getCartData' ),
		};
	} );
	const {
		setShippingAddress,
		setBillingData,
		setBillingAddress,
	} = useDispatch( WC_STORE_CART );

	return {
		isInitialized,
		billingData: customerData.billingData,
		// Backward compatibility billingData/billingAddress
		billingAddress: customerData.billingAddress,
		shippingAddress: customerData.shippingAddress,
		setBillingData,
		// Backward compatibility setBillingData/setBillingAddress
		setBillingAddress,
		setShippingAddress,
	};
};

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

export const maybeToggleAffirm = () => {
	const billingCountry = document.querySelector( '#billing_country' ).value;
	const affirmContainer = document.querySelector(
		'#payment_method_woocommerce_payments_affirm'
	).parentElement;
	if ( 'US' === billingCountry || 'CA' === billingCountry ) {
		affirmContainer.style.display = 'block';
	} else {
		affirmContainer.style.display = 'none';
	}
};
