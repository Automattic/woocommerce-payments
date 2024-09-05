/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT } from 'wcpay/checkout/constants';
import { getConfig } from 'wcpay/utils/checkout';
import ApplePayPreview from './components/apple-pay-preview';
import ExpressCheckoutContainer from './components/express-checkout-container';
import GooglePayPreview from './components/google-pay-preview';

const expressCheckoutElementApplePay = ( api ) => ( {
	paymentMethodId: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT,
	name: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT + '_applePay',
	title: 'WooPayments - Apple Pay',
	description: __(
		'Apple Pay will be displayed to users on Safari or iOS devices with an active Apple account.',
		'woocommerce-payments'
	),
	gatewayId: 'woocommerce_payments',
	content: (
		<ExpressCheckoutContainer api={ api } expressPaymentMethod="applePay" />
	),
	edit: <ApplePayPreview />,
	supports: {
		features: getConfig( 'features' ),
	},
	canMakePayment: () => {
		if ( typeof wcpayExpressCheckoutParams === 'undefined' ) {
			return false;
		}

		return true;
	},
} );

const expressCheckoutElementGooglePay = ( api ) => ( {
	paymentMethodId: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT,
	name: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT + '_googlePay',
	title: 'WooPayments - Google Pay',
	description: __(
		'The Google Pay button will be displayed to users who are signed in to their Google account.',
		'woocommerce-payments'
	),
	gatewayId: 'woocommerce_payments',
	content: (
		<ExpressCheckoutContainer
			api={ api }
			expressPaymentMethod="googlePay"
		/>
	),
	edit: <GooglePayPreview />,
	supports: {
		features: getConfig( 'features' ),
	},
	canMakePayment: () => {
		if ( typeof wcpayExpressCheckoutParams === 'undefined' ) {
			return false;
		}

		return true;
	},
} );

export { expressCheckoutElementApplePay, expressCheckoutElementGooglePay };
