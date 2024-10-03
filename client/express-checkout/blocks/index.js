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
import { checkPaymentMethodIsAvailable } from '../utils/checkPaymentMethodIsAvailable';

const expressCheckoutElementApplePay = ( api ) => ( {
	paymentMethodId: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT,
	name: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT + '_applePay',
	title: 'WooPayments - Apple Pay',
	description: __(
		"An easy, secure way to pay that's accepted on millions of stores.",
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
	canMakePayment: ( { cart } ) => {
		if ( typeof wcpayExpressCheckoutParams === 'undefined' ) {
			return false;
		}

		return new Promise( ( resolve ) => {
			checkPaymentMethodIsAvailable( 'applePay', cart, resolve );
		} );
	},
} );

const expressCheckoutElementGooglePay = ( api ) => {
	return {
		paymentMethodId: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT,
		name: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT + '_googlePay',
		title: 'WooPayments - Google Pay',
		description: __(
			'Simplify checkout with fewer steps to pay.',
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
		canMakePayment: ( { cart } ) => {
			if ( typeof wcpayExpressCheckoutParams === 'undefined' ) {
				return false;
			}

			return new Promise( ( resolve ) => {
				checkPaymentMethodIsAvailable( 'googlePay', cart, resolve );
			} );
		},
	};
};

export { expressCheckoutElementApplePay, expressCheckoutElementGooglePay };
