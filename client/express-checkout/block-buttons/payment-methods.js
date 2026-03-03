/**
 * External dependencies
 */
import { lazy, Suspense } from 'react';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT } from './constants';
import { getConfig, getUPEConfig } from 'wcpay/utils/checkout';
import ExpressCheckoutContainer from './components/express-checkout-container';
import DynamicButtonContainer from './components/dynamic-button-container';
import PaymentMethodLabel from 'wcpay/checkout/blocks/payment-method-label';
import { checkPaymentMethodIsAvailable } from '../utils/checkPaymentMethodIsAvailable';
import { getExpressCheckoutData } from '../utils';
import { EXPRESS_PAYMENT_METHODS } from '../constants';
import '../compatibility/wc-order-attribution';
import '../compatibility/wc-subscriptions';

/**
 * Maps each express payment method key to its corresponding value
 * in the `enabled_methods` backend setting.
 */
const enabledMethodMap = {
	applePay: 'payment_request',
	googlePay: 'payment_request',
	amazonPay: 'amazon_pay',
};

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
			/* webpackChunkName: "block-buttons-preview" */ './components/apple-pay-preview'
		)
	),
	googlePay: lazyPreview( () =>
		import(
			/* webpackChunkName: "block-buttons-preview" */ './components/google-pay-preview'
		)
	),
	amazonPay: lazyPreview( () =>
		import(
			/* webpackChunkName: "block-buttons-preview" */ './components/amazon-pay-preview'
		)
	),
};

export const makeExpressCheckoutElement = ( api, methodKey ) => {
	const method = EXPRESS_PAYMENT_METHODS[ methodKey ];
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

			const enabledMethods =
				getExpressCheckoutData( 'enabled_methods' ) ?? [];
			if ( ! enabledMethods.includes( enabledMethodMap[ methodKey ] ) ) {
				return false;
			}

			return checkPaymentMethodIsAvailable( methodKey, cart, api );
		},
	};
};

const EmptyContent = () => null;

export const makeDynamicPlaceOrderButton = ( api, methodKey ) => {
	const method = EXPRESS_PAYMENT_METHODS[ methodKey ];
	const Preview = previewComponents[ methodKey ];
	const config = getUPEConfig( 'paymentMethodsConfig' )?.[ method.key ];

	return {
		paymentMethodId: method.gatewayId,
		name: method.gatewayId,
		content: <EmptyContent />,
		edit: <Preview />,
		savedTokenComponent: null,
		label: (
			<PaymentMethodLabel
				title={ config?.title ?? method.fallbackTitle }
				paymentMethodId={ method.key }
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
