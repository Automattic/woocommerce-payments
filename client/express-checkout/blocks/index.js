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
import { getExpressCheckoutData } from '../utils';
import '../compatibility/wc-order-attribution';

/**
 * Check if shipping is required but no shipping methods are configured.
 * This prevents the ECE dialog from opening with a "Pending" placeholder rate
 * that cannot be fulfilled, which would allow orders to complete without valid shipping.
 *
 * @param {Object} cart Cart data from WooCommerce Blocks.
 * @return {boolean} True if shipping configuration is invalid.
 */
const hasInvalidShippingConfiguration = () => {
	const checkoutData = getExpressCheckoutData( 'checkout' );
	const needsShipping = checkoutData?.needs_shipping ?? false;
	const hasShippingMethods = checkoutData?.has_shipping_methods ?? true;

	return needsShipping && ! hasShippingMethods;
};

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

		// Don't show if shipping is required but no shipping methods are configured.
		if ( hasInvalidShippingConfiguration() ) {
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

		// Don't show if shipping is required but no shipping methods are configured.
		if ( hasInvalidShippingConfiguration() ) {
			return false;
		}

		return checkPaymentMethodIsAvailable( 'googlePay', cart, api );
	},
} );
