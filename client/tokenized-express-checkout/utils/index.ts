/**
 * Internal dependencies
 */
import { WCPayExpressCheckoutParams } from 'wcpay/express-checkout/utils';
export * from './normalize';
import { getDefaultBorderRadius } from 'wcpay/utils/express-checkout';

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
 * Returns the style settings for the Express Checkout buttons.
 */
export const getExpressCheckoutButtonStyleSettings = () => {
	const buttonSettings = getExpressCheckoutData( 'button' );

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

	return {
		paymentMethods: {
			applePay: 'always',
			googlePay: 'always',
			link: 'never',
			paypal: 'never',
			amazonPay: 'never',
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
};
