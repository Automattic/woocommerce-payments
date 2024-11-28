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

const WooPayExpressCheckoutButtonContainer = ( { buttonAttributes } ) => {
	const onRefChange = useCallback(
		( node ) => {
			if ( node ) {
				const root = ReactDOM.createRoot( node );

				root.render(
					<WoopayExpressCheckoutButton
						buttonSettings={ getConfig( 'woopayButton' ) }
						api={ api }
						emailSelector="#email"
						buttonAttributes={ buttonAttributes }
					/>
				);
			}
		},
		[ buttonAttributes ]
	);

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
	canMakePayment: ( { cart } ) => {
		if ( typeof wcpayConfig === 'undefined' ) {
			return false;
		}

		// Hide WooPay button when cart total is 0 and do not need shipping.
		if (
			Number( cart.cartTotals.total_price ) === 0 &&
			! cart.needs_shipping
		) {
			// Hide WooPay button if the cart has no subscriptions or if the recurring total value is 0.
			if (
				! cart.cartItems.find(
					( item ) => item.type === 'subscription'
				) ||
				cart.extensions.subscriptions.totals.total_price === 0
			) {
				return false;
			}
		}

		return true;
	},
	paymentMethodId: PAYMENT_METHOD_NAME_WOOPAY_EXPRESS_CHECKOUT,
	supports: {
		features: getConfig( 'features' ),
		style: [ 'height', 'borderRadius' ],
	},
} );

export default wooPayExpressCheckoutPaymentMethod;
