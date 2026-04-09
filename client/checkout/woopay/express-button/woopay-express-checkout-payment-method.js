/**
 * External dependencies
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { WoopayExpressCheckoutButton } from './woopay-express-checkout-button';
import { getConfig } from '../../../utils/checkout';
import WCPayAPI from '../../api';
import request from '../../utils/request';
import {
	getCachedPreferredCard,
	setCachedPreferredCard,
	fetchPreferredCard,
} from './preferred-card-utils';

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
	const [ preferredCard, setPreferredCard ] = useState(
		getCachedPreferredCard
	);

	useEffect( () => {
		fetchPreferredCard()
			.then( ( card ) => {
				setCachedPreferredCard( card );
				setPreferredCard( card );
			} )
			.catch( () => {
				// Connect iframe unavailable — keep cached state.
			} );
	}, [] );

	const rootRef = useRef( null );

	const onRefChange = useCallback( ( node ) => {
		if ( node && ! rootRef.current ) {
			rootRef.current = createRoot( node );
		}
	}, [] );

	useEffect( () => {
		if ( rootRef.current ) {
			rootRef.current.render(
				<WoopayExpressCheckoutButton
					buttonSettings={ getConfig( 'woopayButton' ) }
					api={ api }
					emailSelector="#email"
					buttonAttributes={ buttonAttributes }
					preferredCard={ preferredCard }
				/>
			);
		}
	}, [ buttonAttributes, preferredCard ] );

	return <div ref={ onRefChange } />;
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
