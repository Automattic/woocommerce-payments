/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

// Handled as an external dependency: see '/webpack.config.js:83'
import {
	registerPaymentMethod,
	registerExpressPaymentMethod,
	// eslint-disable-next-line import/no-unresolved
} from '@woocommerce/blocks-registry';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME_CARD } from '../constants.js';
import { getConfig } from 'utils/checkout';
import WCPayAPI from './../api';
import WCPayFields from './fields.js';
import { SavedTokenHandler } from './saved-token-handler';
import request from './request.js';
import enqueueFraudScripts from 'fraud-scripts';
import paymentRequestPaymentMethod from '../../payment-request/blocks';
import { platformCheckoutPaymentMethod } from 'wcpay/components/platform-checkout';

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

registerPaymentMethod( {
	name: PAYMENT_METHOD_NAME_CARD,
	content: <WCPayFields api={ api } />,
	edit: <WCPayFields api={ api } />,
	savedTokenComponent: <SavedTokenHandler api={ api } />,
	canMakePayment: () => !! api.getStripe(),
	paymentMethodId: PAYMENT_METHOD_NAME_CARD,
	label: __( 'Credit card', 'woocommerce-payments' ),
	ariaLabel: __( 'Credit card', 'woocommerce-payments' ),
	supports: {
		showSavedCards: getConfig( 'isSavedCardsEnabled' ) ?? false,
		showSaveOption: getConfig( 'isSavedCardsEnabled' ) ?? false,
		features: getConfig( 'features' ),
	},
} );

registerExpressPaymentMethod( paymentRequestPaymentMethod( api ) );

if ( getConfig( 'isPlatformCheckoutEnabled' ) ) {
	registerExpressPaymentMethod( platformCheckoutPaymentMethod( api ) );
}

window.addEventListener( 'load', () => {
	enqueueFraudScripts( getConfig( 'fraudServices' ) );
} );
