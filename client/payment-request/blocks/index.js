/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_PAYMENT_REQUEST } from '../../checkout/constants';
import { PaymentRequestExpress } from './payment-request-express';
import { applePayImage } from './apple-pay-preview';
import { loadStripe } from '../stripe-utils';

import { getConfig } from '../../utils/checkout';
// import { getPaymentRequestData } from '../utils';

const ApplePayPreview = () => <img src={ applePayImage } alt="" />;

const componentStripePromise = loadStripe();

const paymentRequestPaymentMethod = ( api ) => ( {
	name: PAYMENT_METHOD_NAME_PAYMENT_REQUEST,
	content: (
		<PaymentRequestExpress api={ api } stripe={ componentStripePromise } />
	),
	edit: <ApplePayPreview />,
	canMakePayment: !! api.getStripe(),
	paymentMethodId: PAYMENT_METHOD_NAME_PAYMENT_REQUEST,
	supports: {
		features: getConfig( 'features' ),
	},
} );

export default paymentRequestPaymentMethod;
