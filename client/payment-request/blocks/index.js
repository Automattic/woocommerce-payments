/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_PAYMENT_REQUEST } from '../../checkout/constants';
import { PaymentRequestExpress } from './payment-request-express';
import { applePayImage } from './apple-pay-preview';
import { getConfig } from '../../utils/checkout';

const ApplePayPreview = () => <img src={ applePayImage } alt="" />;

const paymentRequestPaymentMethod = ( api ) => ( {
	name: PAYMENT_METHOD_NAME_PAYMENT_REQUEST,
	content: <PaymentRequestExpress api={ api } stripe={ api.loadStripe() } />,
	edit: <ApplePayPreview />,
	canMakePayment: () => {
		// If in the editor context, always return true to display the `edit` prop preview.
		// https://github.com/woocommerce/woocommerce-gutenberg-products-block/issues/4101.
		if ( getConfig( 'is_admin' ) ) {
			return true;
		}

		return (
			!! api.getStripe() &&
			'undefined' !== typeof wcpayPaymentRequestParams
		);
	},
	paymentMethodId: PAYMENT_METHOD_NAME_PAYMENT_REQUEST,
	supports: {
		features: getConfig( 'features' ),
	},
} );

export default paymentRequestPaymentMethod;
