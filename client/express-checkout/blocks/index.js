/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { lazy, Suspense } from 'react';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT } from 'wcpay/checkout/constants';
import { getConfig } from 'wcpay/utils/checkout';
import ExpressCheckoutContainer from './components/express-checkout-container';
import { checkPaymentMethodIsAvailable } from '../utils/checkPaymentMethodIsAvailable';
import '../compatibility/wc-order-attribution';

const LazyApplePayPreview = lazy( () =>
	import(
		/* webpackChunkName: "express-checkout-previews" */ './components/apple-pay-preview'
	)
);
const LazyGooglePayPreview = lazy( () =>
	import(
		/* webpackChunkName: "express-checkout-previews" */ './components/google-pay-preview'
	)
);
const LazyAmazonPayPreview = lazy( () =>
	import(
		/* webpackChunkName: "express-checkout-previews" */ './components/amazon-pay-preview'
	)
);

const PreviewFallback = () => <div style={ { minHeight: '40px' } } />;

const ApplePayPreview = ( props ) => (
	<Suspense fallback={ <PreviewFallback /> }>
		<LazyApplePayPreview { ...props } />
	</Suspense>
);

const GooglePayPreview = ( props ) => (
	<Suspense fallback={ <PreviewFallback /> }>
		<LazyGooglePayPreview { ...props } />
	</Suspense>
);

const AmazonPayPreview = ( props ) => (
	<Suspense fallback={ <PreviewFallback /> }>
		<LazyAmazonPayPreview { ...props } />
	</Suspense>
);

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
	edit: <ApplePayPreview />,
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
	edit: <GooglePayPreview />,
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

export const expressCheckoutElementAmazonPay = ( api ) => ( {
	paymentMethodId: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT,
	name: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT + '_amazonPay',
	title: 'WooPayments - Amazon Pay',
	description: __( 'Pay with your Amazon account.', 'woocommerce-payments' ),
	gatewayId: 'woocommerce_payments',
	content: (
		<ExpressCheckoutContainer
			api={ api }
			expressPaymentMethod="amazonPay"
		/>
	),
	edit: <AmazonPayPreview />,
	supports: {
		features: getConfig( 'features' ),
		style: [ 'height', 'borderRadius' ],
	},
	canMakePayment: ( { cart } ) => {
		if ( typeof wcpayExpressCheckoutParams === 'undefined' ) {
			return false;
		}

		return checkPaymentMethodIsAvailable( 'amazonPay', cart, api );
	},
} );
