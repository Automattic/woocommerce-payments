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
import { getUPEConfig } from 'utils/checkout';
import WCPayAPI from './../api';
import WCPayUPEFields from './upe-fields.js';
import { SavedTokenHandler } from './saved-token-handler';
import request from '../utils/request';
import enqueueFraudScripts from 'fraud-scripts';
import paymentRequestPaymentMethod from '../../payment-request/blocks';

const enabledPaymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );
const isStripeLinkEnabled =
	enabledPaymentMethodsConfig.link !== undefined &&
	enabledPaymentMethodsConfig.card !== undefined;

// Create an API object, which will be used throughout the checkout.
const api = new WCPayAPI(
	{
		publishableKey: getUPEConfig( 'publishableKey' ),
		accountId: getUPEConfig( 'accountId' ),
		forceNetworkSavedCards: getUPEConfig( 'forceNetworkSavedCards' ),
		locale: getUPEConfig( 'locale' ),
		isUPEEnabled: getUPEConfig( 'isUPEEnabled' ),
		isStripeLinkEnabled,
	},
	request
);

Object.entries( enabledPaymentMethodsConfig ).map( ( [ upeName, upeConfig ] ) =>
	registerPaymentMethod( {
		name: upeName,
		content: (
			<WCPayUPEFields
				paymentMethodId={ upeName }
				api={ api }
				testingInstructions={ upeConfig.testingInstructions }
			/>
		),
		edit: <WCPayUPEFields paymentMethodId={ upeName } api={ api } />,
		savedTokenComponent: <SavedTokenHandler api={ api } />,
		canMakePayment: () => !! api.getStripe(),
		paymentMethodId: upeName,
		label: upeConfig.title,
		ariaLabel: __( 'WooCommerce Payments', 'woocommerce-payments' ),
		supports: {
			showSavedCards: getUPEConfig( 'isSavedCardsEnabled' ) ?? false,
			showSaveOption:
				( getUPEConfig( 'isSavedCardsEnabled' ) &&
					! getUPEConfig( 'cartContainsSubscription' ) ) ??
				false,
			features: getUPEConfig( 'features' ),
		},
	} )
);

registerExpressPaymentMethod( paymentRequestPaymentMethod( api ) );
window.addEventListener( 'load', () => {
	enqueueFraudScripts( getUPEConfig( 'fraudServices' ) );
} );
