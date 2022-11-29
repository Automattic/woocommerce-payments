/* global wcpayWooPayExpressParams */
/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_WOOPAY_EXPRESS_CHECKOUT } from '../../constants';
import { WoopayExpressCheckoutButton } from './woopay-express-checkout-button';
import { getConfig } from '../../../utils/checkout';
import WCPayAPI from '../../api';
import request from '../../utils/request';
import { getWooPayExpressData } from './utils';

// Create an API object, which will be used throughout the checkout.
const api = new WCPayAPI(
	{
		publishableKey: getWooPayExpressData( 'publishableKey' ),
		accountId: getWooPayExpressData( 'accountId' ),
		forceNetworkSavedCards: getWooPayExpressData(
			'forceNetworkSavedCards'
		),
		locale: getWooPayExpressData( 'locale' ),
	},
	request
);

const wooPayExpressCheckoutPaymentMethod = () => ( {
	name: PAYMENT_METHOD_NAME_WOOPAY_EXPRESS_CHECKOUT,
	content: (
		<WoopayExpressCheckoutButton
			buttonSettings={ wcpayWooPayExpressParams?.button }
			api={ api }
		/>
	),
	edit: (
		<WoopayExpressCheckoutButton
			buttonSettings={ wcpayWooPayExpressParams?.button }
			isPreview={ true }
		/>
	),
	canMakePayment: () => true,
	paymentMethodId: PAYMENT_METHOD_NAME_WOOPAY_EXPRESS_CHECKOUT,
	supports: {
		features: getConfig( 'features' ),
	},
} );

export default wooPayExpressCheckoutPaymentMethod;
