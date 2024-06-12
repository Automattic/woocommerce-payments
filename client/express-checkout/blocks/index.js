/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT } from '../../checkout/constants';
import ExpressCheckoutContainer from './components/express-checkout-container';
import { getConfig } from '../../utils/checkout';
import ApplePayPreview from './apple-pay-preview';

const expressCheckoutElementPaymentMethod = ( api ) => ( {
	name: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT,
	content: (
		<ExpressCheckoutContainer
			api={ api }
			stripe={ api.loadStripe( true ) }
		/>
	),
	edit: <ApplePayPreview />,
	paymentMethodId: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT,
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

export default expressCheckoutElementPaymentMethod;
