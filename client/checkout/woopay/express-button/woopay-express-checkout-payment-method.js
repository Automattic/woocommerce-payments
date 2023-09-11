/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_WOOPAY_EXPRESS_CHECKOUT } from '../../constants';
import { WoopayExpressCheckoutButton } from './woopay-express-checkout-button';
import { getConfig } from '../../../utils/checkout';
import WCPayAPI from '../../api';
import request from '../../utils/request';

// Create an API object, which will be used throughout the checkout.
const api = new WCPayAPI(
	{
		publishableKey: getConfig( 'publishableKey' ),
		accountId: getConfig( 'accountId' ),
		forceNetworkSavedCards: getConfig( 'forceNetworkSavedCards' ),
		locale: getConfig( 'locale' ),
	},
	request
);

const wooPayExpressCheckoutPaymentMethod = () => ( {
	name: PAYMENT_METHOD_NAME_WOOPAY_EXPRESS_CHECKOUT,
	content: (
		<WoopayExpressCheckoutButton
			buttonSettings={ getConfig( 'woopayButton' ) }
			api={ api }
			emailSelector="#email"
		/>
	),
	edit: (
		<WoopayExpressCheckoutButton
			buttonSettings={ getConfig( 'woopayButton' ) }
			isPreview={ true }
			emailSelector="#email"
		/>
	),
	canMakePayment: () => typeof wcpayConfig !== 'undefined',
	paymentMethodId: PAYMENT_METHOD_NAME_WOOPAY_EXPRESS_CHECKOUT,
	supports: {
		features: getConfig( 'features' ),
	},
} );

export default wooPayExpressCheckoutPaymentMethod;
