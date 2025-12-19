/**
 * Internal dependencies
 */
export * from './normalize';
export * from './shipping-fields';
import { getDefaultBorderRadius } from 'wcpay/utils/express-checkout';

interface MyWindow extends Window {
	wcpayExpressCheckoutParams: WCPayExpressCheckoutParams;
}

declare let window: MyWindow;

/**
 * Subscription trial data for Apple Pay recurringPaymentRequest.
 */
export interface SubscriptionTrialData {
	paymentDescription: string;
	managementURL: string;
	regularBilling: {
		amount: number;
		label: string;
		recurringPaymentStartDate: string;
		recurringPaymentIntervalUnit:
			| 'year'
			| 'month'
			| 'day'
			| 'hour'
			| 'minute';
		recurringPaymentIntervalCount: number;
	};
	trialBilling: {
		amount: number;
		label: string;
		recurringPaymentIntervalUnit:
			| 'year'
			| 'month'
			| 'day'
			| 'hour'
			| 'minute';
		recurringPaymentIntervalCount: number;
	};
	applePayOnly: boolean;
	has_trial: boolean;
	trial_length: number;
	trial_period: string;
}

/**
 * An /incomplete/ representation of the data that is loaded into the frontend for the Express Checkout.
 */
export interface WCPayExpressCheckoutParams {
	ajax_url: string;

	/**
	 * Express Checkout Button style configuration.
	 */
	button: {
		type: string;
		theme: string;
		height: string;
		locale: string;
		branded_type: string;
		radius: number;
	};

	/**
	 * Indicates in which context the button is being displayed.
	 */
	button_context: 'checkout' | 'cart' | 'product' | 'pay_for_order';
	checkout: {
		country_code: string;
		currency_code: string;
		needs_payer_phone: boolean;
		needs_shipping: boolean;
		currency_decimals: number;
		allowed_shipping_countries?: string[];
	};

	/**
	 * Indicaters whether the page has a Cart or Checkout Block on it.
	 */
	has_block: boolean;

	nonce: {
		add_to_cart: string;
		checkout: string;
		empty_cart: string;
		get_cart_details: string;
		get_selected_product_data: string;
		pay_for_order: string;
		platform_tracker: string;
		shipping: string;
		update_shipping: string;
		tokenized_cart_nonce: string;
		tokenized_cart_session_nonce: string;
		store_api_nonce: string;
	};

	/**
	 * Product specific options.
	 */
	product: {
		needs_shipping: boolean;
		currency: string;
		product_type: string;
		shippingOptions: {
			id: string;
			label: string;
			detail: string;
			amount: number;
		};
		subscription_trial?: SubscriptionTrialData;
	};

	/**
	 * Subscription trial data for Apple Pay recurringPaymentRequest.
	 * Available when cart contains a subscription with free trial.
	 */
	subscription_trial?: SubscriptionTrialData;

	/**
	 * Settings for the user authentication dialog and redirection.
	 */
	login_confirmation: { message: string; redirect_url: string } | false;

	stripe: {
		accountId: string;
		locale: string;
		publishableKey: string;
	};
	total_label: string;
	wc_ajax_url: string;
}

declare global {
	interface Window {
		wcpayExpressCheckoutParams?: WCPayExpressCheckoutParams;
	}
}

export const getExpressCheckoutData = <
	K extends keyof WCPayExpressCheckoutParams
>(
	key: K
) => {
	if ( typeof window.wcpayExpressCheckoutParams !== 'undefined' ) {
		return window.wcpayExpressCheckoutParams[ key ] ?? null;
	}

	if ( typeof window.wc?.wcSettings !== 'undefined' ) {
		return window.wc.wcSettings.getSetting( 'ece_data' )?.[ key ] ?? null;
	}

	return null;
};

/**
 * Get error messages from WooCommerce notice from server response.
 *
 * @param notice Error notice.
 * @return Error messages.
 */
export const getErrorMessageFromNotice = ( notice: string | undefined ) => {
	if ( ! notice ) return '';

	const div = document.createElement( 'div' );
	div.innerHTML = notice.trim();
	return div.firstChild ? div.firstChild.textContent : '';
};

type ExpressPaymentType =
	| 'apple_pay'
	| 'google_pay'
	| 'amazon_pay'
	| 'paypal'
	| 'link';

/**
 * Displays a `confirm` dialog which leads to a redirect.
 *
 * @param expressPaymentType Can be either 'apple_pay', 'google_pay', 'amazon_pay', 'paypal' or 'link'.
 */
export const displayLoginConfirmation = (
	expressPaymentType: ExpressPaymentType
) => {
	const loginConfirmation = getExpressCheckoutData( 'login_confirmation' );

	if ( ! loginConfirmation ) {
		return;
	}

	const paymentTypesMap = {
		apple_pay: 'Apple Pay',
		google_pay: 'Google Pay',
		amazon_pay: 'Amazon Pay',
		paypal: 'PayPal',
		link: 'Link',
	};
	let message = loginConfirmation.message;

	// Replace dialog text with specific express checkout type.
	message = message.replace(
		/\*\*.*?\*\*/,
		paymentTypesMap[ expressPaymentType ]
	);

	// Remove asterisks from string.
	message = message.replace( /\*\*/g, '' );

	if ( confirm( message ) ) {
		// Redirect to my account page.
		window.location.href = loginConfirmation.redirect_url;
	}
};

type ButtonAttributesType =
	| { height: string; borderRadius: string }
	| undefined;

/**
 * Returns the appearance settings for the Express Checkout buttons.
 * Currently only configures border radius for the buttons.
 */
export const getExpressCheckoutButtonAppearance = (
	buttonAttributes: ButtonAttributesType
) => {
	let borderRadius = getDefaultBorderRadius();
	const buttonSettings = getExpressCheckoutData( 'button' );

	// Border radius from WooPayments settings
	borderRadius = buttonSettings?.radius ?? borderRadius;

	// Border radius from Cart & Checkout blocks attributes
	if ( typeof buttonAttributes !== 'undefined' ) {
		borderRadius = Number( buttonAttributes?.borderRadius ) ?? borderRadius;
	}

	return {
		variables: {
			borderRadius: `${ borderRadius }px`,
			spacingUnit: '6px',
		},
	};
};

/**
 * Gets subscription trial data from product or global params.
 */
export const getSubscriptionTrialData = (): SubscriptionTrialData | null => {
	// Check product-level subscription trial data first.
	const productData = getExpressCheckoutData( 'product' );
	if ( productData?.subscription_trial ) {
		return productData.subscription_trial;
	}

	// Check global subscription trial data (for cart/checkout pages).
	const subscriptionTrial = getExpressCheckoutData( 'subscription_trial' );
	if ( subscriptionTrial ) {
		return subscriptionTrial as SubscriptionTrialData;
	}

	return null;
};

/**
 * Returns the style settings for the Express Checkout buttons.
 */
export const getExpressCheckoutButtonStyleSettings = () => {
	const buttonSettings = getExpressCheckoutData( 'button' );
	const subscriptionTrialData = getSubscriptionTrialData();

	const mapWooPaymentsThemeToButtonTheme = (
		buttonType: string,
		theme: string
	) => {
		switch ( theme ) {
			case 'dark':
				return 'black';
			case 'light':
				return 'white';
			case 'light-outline':
				if ( buttonType === 'googlePay' ) {
					return 'white';
				}

				return 'white-outline';
			default:
				return 'black';
		}
	};

	const googlePayType =
		buttonSettings?.type === 'default'
			? 'plain'
			: buttonSettings?.type ?? 'buy';

	const applePayType =
		buttonSettings?.type === 'default'
			? 'plain'
			: buttonSettings?.type ?? 'plain';

	// If subscription has trial, only show Apple Pay (Google Pay doesn't support recurringPaymentRequest).
	const hasSubscriptionTrial = subscriptionTrialData?.has_trial ?? false;

	const settings: Record< string, unknown > = {
		paymentMethods: {
			applePay: 'always',
			// Hide Google Pay for subscriptions with trial since it doesn't support recurringPaymentRequest.
			googlePay: hasSubscriptionTrial ? 'never' : 'always',
			amazonPay: 'never',
			link: 'never',
			paypal: 'never',
			klarna: 'never',
		},
		layout: { overflow: 'never' },
		buttonTheme: {
			googlePay: mapWooPaymentsThemeToButtonTheme(
				'googlePay',
				buttonSettings?.theme ?? 'black'
			),
			applePay: mapWooPaymentsThemeToButtonTheme(
				'applePay',
				buttonSettings?.theme ?? 'black'
			),
		},
		buttonType: {
			googlePay: googlePayType,
			applePay: applePayType,
		},
		// Allowed height must be 40px to 55px.
		buttonHeight: Math.min(
			Math.max( parseInt( buttonSettings?.height ?? '48', 10 ), 40 ),
			55
		),
	};

	// Add Apple Pay recurringPaymentRequest for subscriptions with trial.
	// This allows Apple Pay to show even when the initial payment is $0.
	// See: https://docs.stripe.com/js/elements_object/create_express_checkout_element#express_checkout_element_create-options-applePay
	if ( subscriptionTrialData ) {
		// When using recurringPaymentRequest, contact/shipping options must be at element creation time.
		const checkoutData = getExpressCheckoutData( 'checkout' );
		settings.emailRequired = true;
		settings.phoneNumberRequired = checkoutData?.needs_payer_phone ?? false;
		settings.shippingAddressRequired =
			checkoutData?.needs_shipping ?? false;
		settings.allowedShippingCountries =
			checkoutData?.allowed_shipping_countries ?? [];

		settings.applePay = {
			recurringPaymentRequest: {
				paymentDescription: subscriptionTrialData.paymentDescription,
				managementURL: subscriptionTrialData.managementURL,
				regularBilling: {
					amount: subscriptionTrialData.regularBilling.amount,
					label: subscriptionTrialData.regularBilling.label,
					recurringPaymentStartDate: new Date(
						subscriptionTrialData.regularBilling.recurringPaymentStartDate
					),
					recurringPaymentIntervalUnit:
						subscriptionTrialData.regularBilling
							.recurringPaymentIntervalUnit,
					recurringPaymentIntervalCount:
						subscriptionTrialData.regularBilling
							.recurringPaymentIntervalCount,
				},
				trialBilling: {
					amount: subscriptionTrialData.trialBilling.amount,
					label: subscriptionTrialData.trialBilling.label,
					recurringPaymentIntervalUnit:
						subscriptionTrialData.trialBilling
							.recurringPaymentIntervalUnit,
					recurringPaymentIntervalCount:
						subscriptionTrialData.trialBilling
							.recurringPaymentIntervalCount,
				},
			},
		};
	}

	return settings;
};
