/**
 * External dependencies
 */
import ReactDOM from 'react-dom';
import { ExpressCheckoutElement } from '@stripe/react-stripe-js';
import { Elements } from '@stripe/react-stripe-js';

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
	canMakePayment: () => {
		if ( typeof wcpayExpressCheckoutParams === 'undefined' ) {
			return false;
		}

		return true;
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
			if ( typeof wcpayExpressCheckoutParams === 'undefined' ) {
				return false;
			}

			const root = ReactDOM.createRoot(
				document.getElementById(
					'express-checkout-check-availability-container'
				)
			);

			// Create an API object, which will be used throughout the checkout.
			const enabledPaymentMethodsConfig = getUPEConfig(
				'paymentMethodsConfig'
			);
			const isStripeLinkEnabled = isLinkEnabled(
				enabledPaymentMethodsConfig
			);

			const api = new WCPayAPI(
				{
					publishableKey: getUPEConfig( 'publishableKey' ),
					accountId: getUPEConfig( 'accountId' ),
					forceNetworkSavedCards: getUPEConfig(
						'forceNetworkSavedCards'
					),
					locale: getUPEConfig( 'locale' ),
					isStripeLinkEnabled,
				},
				request
			);

			const options = {
				mode: 'payment',
				paymentMethodCreation: 'manual',
				amount: Number( cart.cartTotals.total_price ),
				currency: cart.cartTotals.currency_code.toLowerCase(),
			};

			const stripePromise = api.loadStripe();
			const onElementsReady = () => {
				console.log( 'ready!' );
			};

			const eceOptions = {
				paymentMethods: {
					amazonPay: 'never',
					applePay: 'never',
					googlePay: 'always',
					link: 'never',
					paypal: 'never',
				},
			};

			root.render(
				<Elements stripe={ stripePromise } options={ options }>
					<ExpressCheckoutElement
						onReady={ onElementsReady }
						options={ eceOptions }
					/>
				</Elements>
			);

			return true;
		},
	};
};

export { expressCheckoutElementApplePay, expressCheckoutElementGooglePay };
