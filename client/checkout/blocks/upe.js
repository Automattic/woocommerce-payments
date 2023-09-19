/**
 * External dependencies
 */
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
import { getConfig, getCustomGatewayTitle } from 'utils/checkout';
import WCPayAPI from './../api';
import WCPayUPEFields from './upe-fields.js';
import { SavedTokenHandler } from './saved-token-handler';
import request from '../utils/request';
import enqueueFraudScripts from 'fraud-scripts';
import paymentRequestPaymentMethod from '../../payment-request/blocks';
import { isLinkEnabled } from '../utils/upe.js';

const paymentMethodsConfig = getConfig( 'paymentMethodsConfig' );
const isStripeLinkEnabled = isLinkEnabled( paymentMethodsConfig );

// Create an API object, which will be used throughout the checkout.
const api = new WCPayAPI(
	{
		publishableKey: getConfig( 'publishableKey' ),
		accountId: getConfig( 'accountId' ),
		forceNetworkSavedCards: getConfig( 'forceNetworkSavedCards' ),
		locale: getConfig( 'locale' ),
		isUPEEnabled: getConfig( 'isUPEEnabled' ),
		isStripeLinkEnabled,
	},
	request
);

registerPaymentMethod( {
	name: PAYMENT_METHOD_NAME_CARD,
	content: <WCPayUPEFields api={ api } />,
	edit: <WCPayUPEFields api={ api } />,
	savedTokenComponent: <SavedTokenHandler api={ api } />,
	canMakePayment: () => !! api.getStripe(),
	paymentMethodId: PAYMENT_METHOD_NAME_CARD,
	label: getCustomGatewayTitle( getConfig( 'paymentMethodsConfig' ) ),
	ariaLabel: 'WooPayments',
	supports: {
		showSavedCards: getConfig( 'isSavedCardsEnabled' ) ?? false,
		showSaveOption:
			( getConfig( 'isSavedCardsEnabled' ) &&
				! getConfig( 'cartContainsSubscription' ) ) ??
			false,
		features: getConfig( 'features' ),
	},
} );

registerExpressPaymentMethod( paymentRequestPaymentMethod( api ) );
window.addEventListener( 'load', () => {
	enqueueFraudScripts( getConfig( 'fraudServices' ) );
} );
