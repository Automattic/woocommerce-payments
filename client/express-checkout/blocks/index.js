/**
 * External dependencies
 */
import ReactDOM from 'react-dom';
import { ExpressCheckoutElement, Elements } from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT } from 'wcpay/checkout/constants';
import { getConfig, getUPEConfig } from 'wcpay/utils/checkout';
import ApplePayPreview from './components/apple-pay-preview';
import ExpressCheckoutContainer from './components/express-checkout-container';
import GooglePayPreview from './components/google-pay-preview';
import { isLinkEnabled } from 'wcpay/checkout/utils/upe';
import request from 'wcpay/checkout/utils/request';
import WCPayAPI from 'wcpay/checkout/api';

const running = { applePay: false, googlePay: false };

const expressCheckoutElementApplePay = ( api ) => ( {
	paymentMethodId: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT,
	name: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT + '_applePay',
	content: (
		<ExpressCheckoutContainer api={ api } expressPaymentMethod="applePay" />
	),
	edit: <ApplePayPreview />,
	supports: {
		features: getConfig( 'features' ),
	},
	canMakePayment: ( { cart } ) => {
		if ( running.applePay ) {
			return false;
		}
		running.applePay = true;
		return new Promise( ( resolve ) => {
			checkPaymentMethodIsAvailable( 'applePay', cart, resolve );
		} );
	},
} );

const expressCheckoutElementGooglePay = ( api ) => {
	return {
		paymentMethodId: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT,
		name: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT + '_googlePay',
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
			if ( running.googlePay ) {
				return false;
			}
			running.googlePay = true;
			return new Promise( ( resolve ) => {
				checkPaymentMethodIsAvailable( 'googlePay', cart, resolve );
			} );
		},
	};
};

function checkPaymentMethodIsAvailable( paymentMethod, cart, resolve ) {
	if ( typeof wcpayExpressCheckoutParams === 'undefined' ) {
		return false;
	}

	const root = ReactDOM.createRoot(
		document.getElementById(
			`express-checkout-check-availability-container-${ paymentMethod }`
		)
	);

	// Create an API object, which will be used throughout the checkout.
	const enabledPaymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );
	const isStripeLinkEnabled = isLinkEnabled( enabledPaymentMethodsConfig );

	const api = new WCPayAPI(
		{
			publishableKey: getUPEConfig( 'publishableKey' ),
			accountId: getUPEConfig( 'accountId' ),
			forceNetworkSavedCards: getUPEConfig( 'forceNetworkSavedCards' ),
			locale: getUPEConfig( 'locale' ),
			isStripeLinkEnabled,
		},
		request
	);

	const stripePromise = api.loadStripe();
	const options = {
		mode: 'payment',
		paymentMethodCreation: 'manual',
		amount: Number( cart.cartTotals.total_price ),
		currency: cart.cartTotals.currency_code.toLowerCase(),
	};

	const eceOptions = {
		paymentMethods: {
			amazonPay: 'never',
			applePay: paymentMethod === 'applePay' ? 'always' : 'never',
			googlePay: paymentMethod === 'googlePay' ? 'always' : 'never',
			link: 'never',
			paypal: 'never',
		},
	};

	const onElementsReadyHandler = () => {
		setTimeout( () => {
			const iframeHeight = document
				.querySelector(
					`#express-checkout-check-availability-container-${ paymentMethod } iframe`
				)
				.getBoundingClientRect().height;
			resolve( iframeHeight < 40 ? false : true );
		}, 2000 );
	};

	root.render(
		<Elements stripe={ stripePromise } options={ options }>
			<ExpressCheckoutElement
				options={ eceOptions }
				onReady={ onElementsReadyHandler }
			/>
		</Elements>
	);
}

export { expressCheckoutElementApplePay, expressCheckoutElementGooglePay };
