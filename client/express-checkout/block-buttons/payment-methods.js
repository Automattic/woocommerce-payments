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

const camelToSnake = ( camel ) =>
	camel.replace( /[A-Z]/g, ( letter ) => `_${ letter.toLowerCase() }` );

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
	const snakeKey = camelToSnake( methodKey );
	const serverConfig =
		getUPEConfig( 'paymentMethodsConfig' )?.[ snakeKey ] ?? {};
	const Preview = previewComponents[ methodKey ];
	return {
		paymentMethodId: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT,
		name: serverConfig.gatewayId,
		title: serverConfig.title,
		description: serverConfig.description,
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
	const snakeKey = camelToSnake( methodKey );
	const serverConfig =
		getUPEConfig( 'paymentMethodsConfig' )?.[ snakeKey ] ?? {};
	const Preview = previewComponents[ methodKey ];

	return {
		paymentMethodId: serverConfig.gatewayId,
		name: serverConfig.gatewayId,
		content: <EmptyContent />,
		edit: <Preview />,
		savedTokenComponent: null,
		label: (
			<PaymentMethodLabel
				title={ serverConfig.title }
				paymentMethodId={ snakeKey }
				icon={ serverConfig.icon }
				darkIcon={ serverConfig.darkIcon }
			/>
		),
		placeOrderButton: ( props ) => (
			<DynamicButtonContainer
				expressPaymentMethod={ methodKey }
				expressPaymentType={ snakeKey }
				stripePaymentMethodType={
					serverConfig.stripePaymentMethodType ?? snakeKey
				}
				gatewayId={ serverConfig.gatewayId }
				api={ api }
				{ ...props }
			/>
		),
		ariaLabel: serverConfig.title,
		canMakePayment: ( { cart } ) => {
			return checkPaymentMethodIsAvailable( methodKey, cart, api );
		},
		supports: {
			features: getConfig( 'features' ),
		},
	};
};
