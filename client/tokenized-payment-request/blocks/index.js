/* global wcpayConfig, wcpayPaymentRequestParams */

/**
 * Internal dependencies
 */
import { PaymentRequestExpress } from './payment-request-express';
import { applePayImage } from './apple-pay-preview';
import { getConfig } from '../../utils/checkout';
import { getPaymentRequest } from '../../payment-request/utils';

const PAYMENT_METHOD_NAME_PAYMENT_REQUEST =
	'woocommerce_payments_tokenized_cart_payment_request';

const ApplePayPreview = () => <img src={ applePayImage } alt="" />;

const tokenizedCartPaymentRequestPaymentMethod = ( api ) => ( {
	name: PAYMENT_METHOD_NAME_PAYMENT_REQUEST,
	content: (
		<PaymentRequestExpress api={ api } stripe={ api.loadStripe( true ) } />
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

		return api.loadStripe( true ).then( ( stripe ) => {
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

export default tokenizedCartPaymentRequestPaymentMethod;
