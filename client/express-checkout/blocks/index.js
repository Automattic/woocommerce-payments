/* global wcpayConfig, wcpayExpressCheckoutParams */

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT } from '../../checkout/constants';
import { ExpressCheckout } from './express-checkout';
import { getConfig } from '../../utils/checkout';
import ApplePayPreview from './apple-pay-preview';

const expressCheckoutElementPaymentMethod = ( api ) => ( {
	name: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT,
	content: <ExpressCheckout api={ api } stripe={ api.loadStripe( true ) } />,
	edit: <ApplePayPreview />,
	paymentMethodId: PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT,
	supports: {
		features: getConfig( 'features' ),
	},
	canMakePayment: () => {
		if ( typeof wcpayExpressCheckoutParams === 'undefined' ) {
			return false;
		}

		if ( typeof wcpayConfig !== 'undefined' ) {
			return wcpayConfig.isExpressCheckoutElementEnabled;
		}

		return false;
	},
} );

export default expressCheckoutElementPaymentMethod;
