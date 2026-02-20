/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { lazy, Suspense } from 'react';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT } from 'wcpay/checkout/constants';
import { getConfig, getUPEConfig } from 'wcpay/utils/checkout';
import ExpressCheckoutContainer from './components/express-checkout-container';
import DynamicButtonContainer from './components/dynamic-button-container';
import PaymentMethodLabel from 'wcpay/checkout/blocks/payment-method-label';
import { checkPaymentMethodIsAvailable } from '../utils/checkPaymentMethodIsAvailable';
import '../compatibility/wc-order-attribution';
import '../compatibility/wc-subscriptions';

const PreviewFallback = () => <div style={ { minHeight: '40px' } } />;

const lazyPreview = ( importFn ) => {
	const LazyComponent = lazy( importFn );
	return ( props ) => (
		<Suspense fallback={ <PreviewFallback /> }>
			<LazyComponent { ...props } />
		</Suspense>
	);
};

const previewComponents = {
	applePay: lazyPreview( () =>
		import(
			/* webpackChunkName: "express-checkout-previews" */ './components/apple-pay-preview'
		)
	),
	googlePay: lazyPreview( () =>
		import(
			/* webpackChunkName: "express-checkout-previews" */ './components/google-pay-preview'
		)
	),
	amazonPay: lazyPreview( () =>
		import(
			/* webpackChunkName: "express-checkout-previews" */ './components/amazon-pay-preview'
		)
	),
};

const expressMethodConfig = {
	applePay: {
		configKey: 'apple_pay',
		gatewayId: 'woocommerce_payments_apple_pay',
		title: 'WooPayments - Apple Pay',
		description: __(
			"An easy, secure way to pay that's accepted on millions of stores.",
			'woocommerce-payments'
		),
		ariaLabel: 'Apple Pay',
		fallbackTitle: 'Apple Pay',
	},
	googlePay: {
		configKey: 'google_pay',
		gatewayId: 'woocommerce_payments_google_pay',
		title: 'WooPayments - Google Pay',
		description: __(
			'Simplify checkout with fewer steps to pay.',
			'woocommerce-payments'
		),
		ariaLabel: 'Google Pay',
		fallbackTitle: 'Google Pay',
	},
	amazonPay: {
		configKey: 'amazon_pay',
		gatewayId: 'woocommerce_payments_amazon_pay',
		title: 'WooPayments - Amazon Pay',
		description: __(
			'Pay with your Amazon account.',
			'woocommerce-payments'
		),
		ariaLabel: 'Amazon Pay',
		fallbackTitle: 'Amazon Pay',
	},
};

export const makeExpressCheckoutElement = ( api, methodKey ) => {
	const method = expressMethodConfig[ methodKey ];
	const Preview = previewComponents[ methodKey ];
	return {
		paymentMethodId: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT,
		name: method.gatewayId,
		title: method.title,
		description: method.description,
		gatewayId: 'woocommerce_payments',
		content: (
			<ExpressCheckoutContainer
				api={ api }
				expressPaymentMethod={ methodKey }
			/>
		),
		edit: <Preview />,
		supports: {
			features: getConfig( 'features' ),
			style: [ 'height', 'borderRadius' ],
		},
		canMakePayment: ( { cart } ) => {
			if ( typeof wcpayExpressCheckoutParams === 'undefined' ) {
				return false;
			}

			return checkPaymentMethodIsAvailable( methodKey, cart, api );
		},
	};
};

const EmptyContent = () => null;

export const makeDynamicPlaceOrderButton = ( api, methodKey ) => {
	const method = expressMethodConfig[ methodKey ];
	const Preview = previewComponents[ methodKey ];
	const config = getUPEConfig( 'paymentMethodsConfig' )?.[ method.configKey ];

	return {
		paymentMethodId: method.gatewayId,
		name: method.gatewayId,
		content: <EmptyContent />,
		edit: <Preview />,
		savedTokenComponent: null,
		label: (
			<PaymentMethodLabel
				title={ config?.title ?? method.fallbackTitle }
				paymentMethodId={ method.configKey }
				icon={ config?.icon }
				darkIcon={ config?.darkIcon }
			/>
		),
		placeOrderButton: ( props ) => (
			<DynamicButtonContainer
				expressPaymentMethod={ methodKey }
				api={ api }
				{ ...props }
			/>
		),
		ariaLabel: method.ariaLabel,
		canMakePayment: ( { cart } ) => {
			return checkPaymentMethodIsAvailable( methodKey, cart, api );
		},
		supports: {
			features: getConfig( 'features' ),
		},
	};
};
