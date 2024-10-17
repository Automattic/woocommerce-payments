/* global wcpayConfig, wcpayPaymentRequestParams */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_PAYMENT_REQUEST } from '../../checkout/constants';
import { PaymentRequestExpress } from './payment-request-express';
import { applePayImage } from './apple-pay-preview';
import { getConfig } from '../../utils/checkout';
import { getPaymentRequest } from '../utils';

const ApplePayPreview = () => <img src={ applePayImage } alt="" />;

const paymentRequestPaymentMethod = ( api ) => ( {
	name: PAYMENT_METHOD_NAME_PAYMENT_REQUEST,
	title: 'WooPayments - Payment Request',
	description: __(
		'Display the Apple Pay, Google Pay, or Stripe Link button to users based on their browser and login status.',
		'woocommerce-payments'
	),
	gatewayId: 'woocommerce_payments',
	content: (
		<PaymentRequestExpress
			api={ api }
			stripe={ api.loadStripeForExpressCheckout() }
		/>
	),
	edit: <ApplePayPreview />,
	canMakePayment: ( cartData ) => {
		// If in the editor context, always return true to display the `edit` prop preview.
		// https://github.com/woocommerce/woocommerce-gutenberg-products-block/issues/4101.
		if ( getConfig( 'is_admin' ) ) {
			return true;
		}

		if ( typeof wcpayPaymentRequestParams === 'undefined' ) {
			return false;
		}

		if (
			typeof wcpayConfig !== 'undefined' &&
			wcpayConfig.isExpressCheckoutElementEnabled
		) {
			return false;
		}

		return api.loadStripeForExpressCheckout().then( ( stripe ) => {
			// Create a payment request and check if we can make a payment to determine whether to
			// show the Payment Request Button or not. This is necessary because a browser might be
			// able to load the Stripe JS object, but not support Payment Requests.
			const pr = getPaymentRequest( {
				stripe,
				total: parseInt( cartData?.cartTotals?.total_price ?? 0, 10 ),
				requestShipping: cartData?.cartNeedsShipping,
				displayItems: [],
			} );

			return pr.canMakePayment();
		} );
	},
	paymentMethodId: PAYMENT_METHOD_NAME_PAYMENT_REQUEST,
	supports: {
		features: getConfig( 'features' ),
	},
} );

export default paymentRequestPaymentMethod;
