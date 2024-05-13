/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT } from '../../checkout/constants';
import { ExpressCheckout } from './express-checkout';
import { applePayImage } from './apple-pay-preview';
import { getConfig } from '../../utils/checkout';
import { getPaymentRequest } from '../utils';

const ApplePayPreview = () => <img src={ applePayImage } alt="" />;
const expressCheckoutElementPaymentMethod = ( api ) => ( {
	name: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT,
	content: <ExpressCheckout api={ api } stripe={ api.loadStripe( true ) } />,
	edit: <ApplePayPreview />,
	paymentMethodId: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT,
	supports: {
		features: getConfig( 'features' ),
	},
	canMakePayment: () => true,
} );

export default expressCheckoutElementPaymentMethod;
