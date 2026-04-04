/**
 * External dependencies
 */
import { useCallback, useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { WoopayExpressCheckoutButton } from './woopay-express-checkout-button';
import { getConfig } from '../../../utils/checkout';
import WCPayAPI from '../../api';
import request from '../../utils/request';
import WooPayUserConnect from 'wcpay/checkout/woopay/connect/user-connect';

const PREFERRED_CARD_CACHE_KEY = 'woopay_preferred_card';

export const PAYMENT_METHOD_NAME_WOOPAY_EXPRESS_CHECKOUT =
	'woocommerce_payments_woopay_express_checkout';

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
	const [ preferredCard, setPreferredCard ] = useState( () => {
		try {
			const cached = localStorage.getItem( PREFERRED_CARD_CACHE_KEY );
			return cached ? JSON.parse( cached ) : null;
		} catch {
			return null;
		}
	} );

	useEffect( () => {
		const fetchPreferredCard = async () => {
			try {
				const userConnect = new WooPayUserConnect();
				const card = await userConnect.getPreferredPaymentMethod();

				if ( card && card.brand && card.last4 ) {
					localStorage.setItem(
						PREFERRED_CARD_CACHE_KEY,
						JSON.stringify( card )
					);
				} else {
					localStorage.removeItem( PREFERRED_CARD_CACHE_KEY );
				}

				setPreferredCard( card );
			} catch {
				// Connect iframe unavailable — keep cached state.
			}
		};

		fetchPreferredCard();
	}, [] );

	const onRefChange = useCallback(
		( node ) => {
			if ( node ) {
				const root = createRoot( node );

				root.render(
					<WoopayExpressCheckoutButton
						buttonSettings={ getConfig( 'woopayButton' ) }
						api={ api }
						emailSelector="#email"
						buttonAttributes={ buttonAttributes }
						preferredCard={ preferredCard }
					/>
				);
			}
		},
		[ buttonAttributes, preferredCard ]
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
	canMakePayment: () => typeof wcpayConfig !== 'undefined',
	paymentMethodId: PAYMENT_METHOD_NAME_WOOPAY_EXPRESS_CHECKOUT,
	supports: {
		features: getConfig( 'features' ),
		style: [ 'height', 'borderRadius' ],
	},
} );

export default wooPayExpressCheckoutPaymentMethod;
