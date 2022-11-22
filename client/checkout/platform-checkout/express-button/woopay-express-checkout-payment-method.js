/* global wcpayWooPayExpressParams */
/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_WOOPAY_EXPRESS_CHECKOUT } from '../../constants';
import { WoopayExpressCheckoutButton } from './woopay-express-checkout-button';
import { getConfig } from '../../../utils/checkout';

const wooPayExpressCheckoutPaymentMethod = () => ( {
	name: PAYMENT_METHOD_NAME_WOOPAY_EXPRESS_CHECKOUT,
	content: (
		<WoopayExpressCheckoutButton
			buttonSettings={ wcpayWooPayExpressParams?.button }
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
