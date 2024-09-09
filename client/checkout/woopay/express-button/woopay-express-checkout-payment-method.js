/**
 * External dependencies
 */
import { useCallback } from 'react';
import ReactDOM from 'react-dom';
import { __ } from '@wordpress/i18n';

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

const WooPayExpressCheckoutButtonContainer = () => {
	const onRefChange = useCallback( ( node ) => {
		if ( node ) {
			const root = ReactDOM.createRoot( node );

			root.render(
				<WoopayExpressCheckoutButton
					buttonSettings={ getConfig( 'woopayButton' ) }
					api={ api }
					emailSelector="#email"
				/>
			);
		}
	}, [] );

	return <span ref={ onRefChange } />;
};

const wooPayExpressCheckoutPaymentMethod = () => ( {
	name: PAYMENT_METHOD_NAME_WOOPAY_EXPRESS_CHECKOUT,
	title: 'WooPayments - WooPay',
	description: __(
		'A one-click, high-converting, secure checkout built for Woo — themed to your brand.',
		'woocommerce-payments'
	),
	gatewayId: 'woocommerce_payments',
	content: <WooPayExpressCheckoutButtonContainer />,
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
