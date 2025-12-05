/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT } from 'wcpay/checkout/constants';
import { getConfig, getUPEConfig } from 'wcpay/utils/checkout';
import ExpressCheckoutContainer from './components/express-checkout-container';
import { checkPaymentMethodIsAvailable } from '../utils/checkPaymentMethodIsAvailable';
import DynamicButtonContainer from 'wcpay/express-checkout/blocks/components/dynamic-button-container';
import PaymentMethodLabel from 'wcpay/checkout/blocks/payment-method-label';

const NoopComponent = () => null;

export const expressCheckoutElementApplePay = ( api ) => ( {
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
	edit: (
		<ExpressCheckoutContainer
			api={ api }
			expressPaymentMethod="applePay"
			isPreview
		/>
	),
	supports: {
		features: getConfig( 'features' ),
		style: [ 'height', 'borderRadius' ],
	},
	canMakePayment: ( { cart } ) => {
		if ( typeof wcpayExpressCheckoutParams === 'undefined' ) {
			return false;
		}

		return checkPaymentMethodIsAvailable( 'applePay', cart, api );
	},
} );

export const expressCheckoutElementGooglePay = ( api ) => ( {
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
	edit: (
		<ExpressCheckoutContainer
			api={ api }
			expressPaymentMethod="googlePay"
			isPreview
		/>
	),
	supports: {
		features: getConfig( 'features' ),
		style: [ 'height', 'borderRadius' ],
	},
	canMakePayment: ( { cart } ) => {
		if ( typeof wcpayExpressCheckoutParams === 'undefined' ) {
			return false;
		}

		return checkPaymentMethodIsAvailable( 'googlePay', cart, api );
	},
} );

export const dynamicPlaceOrderApplePay = ( api ) => ( {
	paymentMethodId: 'woocommerce_payments_apple_pay',
	icons: null,
	gatewayId: 'woocommerce_payments',
	name: 'woocommerce_payments_apple_pay',
	label: (
		<PaymentMethodLabel
			api={ api }
			title={ getUPEConfig( 'paymentMethodsConfig' ).apple_pay.title }
			iconLight={ getUPEConfig( 'paymentMethodsConfig' ).apple_pay.icon }
			iconDark={
				getUPEConfig( 'paymentMethodsConfig' ).apple_pay.darkIcon
			}
			upeName="apple_pay"
		/>
	),
	placeOrderButton: ( props ) => (
		<DynamicButtonContainer
			{ ...props }
			api={ api }
			expressPaymentMethod="applePay"
		/>
	),
	content: <NoopComponent />,
	edit: <NoopComponent />,
	savedTokenComponent: <div>Apple Pay saved token</div>,
	title: 'WooPayments - Apple Pay',
	ariaLabel: 'WooPayments',
	description: __(
		"An easy, secure way to pay that's accepted on millions of stores.",
		'woocommerce-payments'
	),
	supports: {
		features: getConfig( 'features' ),
	},
	canMakePayment: ( { cart } ) => {
		if ( typeof wcpayExpressCheckoutParams === 'undefined' ) {
			return false;
		}

		return checkPaymentMethodIsAvailable( 'applePay', cart, api );
	},
} );

export const dynamicPlaceOrderGooglePay = ( api ) => ( {
	paymentMethodId: 'woocommerce_payments_google_pay',
	icons: null,
	gatewayId: 'woocommerce_payments',
	name: 'woocommerce_payments_google_pay',
	label: (
		<PaymentMethodLabel
			api={ api }
			title={ getUPEConfig( 'paymentMethodsConfig' ).google_pay.title }
			iconLight={ getUPEConfig( 'paymentMethodsConfig' ).google_pay.icon }
			iconDark={
				getUPEConfig( 'paymentMethodsConfig' ).google_pay.darkIcon
			}
			upeName="google_pay"
		/>
	),
	placeOrderButton: ( props ) => (
		<DynamicButtonContainer
			{ ...props }
			api={ api }
			expressPaymentMethod="googlePay"
		/>
	),
	content: <NoopComponent />,
	edit: <NoopComponent />,
	savedTokenComponent: <div>Google Pay saved token</div>,
	title: 'WooPayments - Google Pay',
	ariaLabel: 'WooPayments',
	description: __(
		'Simplify checkout with fewer steps to pay.',
		'woocommerce-payments'
	),
	supports: {
		features: getConfig( 'features' ),
	},
	canMakePayment: ( { cart } ) => {
		if ( typeof wcpayExpressCheckoutParams === 'undefined' ) {
			return false;
		}

		return checkPaymentMethodIsAvailable( 'googlePay', cart, api );
	},
} );
