/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT } from 'wcpay/checkout/constants';
import { getConfig } from 'wcpay/utils/checkout';
import ExpressCheckoutContainer from './components/express-checkout-container';
import { checkPaymentMethodIsAvailable } from '../utils/checkPaymentMethodIsAvailable';

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

const CustomWrapper = ( { children } ) => <div>{ children }</div>;
export const paymentMethodGooglePay = ( api ) => {
	return {
		name: 'woocommerce_payments_googlePay',
		edit: <CustomWrapper />,
		content: <CustomWrapper />,
		paymentMethodId: 'woocommerce_payments_googlePay',
		// Enhanced placeOrderButton with proper props typing
		placeOrderButton: ( props ) => {
			// Validate that we have the required props
			if ( ! props || typeof props.onSubmit !== 'function' ) {
				// Remove console.error and return proper error component
				return (
					<div className="wc-block-components-error">
						{ __(
							'Error: Missing payment method interface',
							'woocommerce-payments'
						) }
					</div>
				);
			}

			return (
				<ExpressCheckoutContainer
					api={ api }
					expressPaymentMethod="googlePay"
					{ ...props }
				/>
			);
		},
		label: 'Google Pay',
		ariaLabel: 'Google Pay',
		supports: {
			showSavedCards: false,
			showSaveOption: false,
			features: getConfig( 'features' ),
		},
		canMakePayment: ( { cart } ) => {
			return checkPaymentMethodIsAvailable( 'googlePay', cart, api );
		},
	};
};

export const paymentMethodApplePay = ( api ) => {
	return {
		name: 'woocommerce_payments_applePay',
		edit: <CustomWrapper />,
		content: <CustomWrapper />,
		paymentMethodId: 'woocommerce_payments_applePay',
		// Enhanced placeOrderButton with proper props typing
		placeOrderButton: ( props ) => {
			// Validate that we have the required props
			if ( ! props || typeof props.onSubmit !== 'function' ) {
				console.error(
					'Apple Pay placeOrderButton: Missing required props'
				);
				return <div>Error: Missing payment method interface</div>;
			}

			return (
				<ExpressCheckoutContainer
					api={ api }
					expressPaymentMethod="applePay"
					{ ...props }
				/>
			);
		},
		label: 'Apple Pay',
		ariaLabel: 'Apple Pay',
		supports: {
			showSavedCards: false,
			showSaveOption: false,
			features: getConfig( 'features' ),
		},
		canMakePayment: ( { cart } ) => {
			return checkPaymentMethodIsAvailable( 'applePay', cart, api );
		},
	};
};
