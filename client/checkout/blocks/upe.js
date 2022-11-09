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
import {
	PAYMENT_METHOD_NAME_CARD,
	PAYMENT_METHOD_NAME_BANCONTACT,
	PAYMENT_METHOD_NAME_BECS,
	PAYMENT_METHOD_NAME_EPS,
	PAYMENT_METHOD_NAME_GIROPAY,
	PAYMENT_METHOD_NAME_IDEAL,
	PAYMENT_METHOD_NAME_P24,
	PAYMENT_METHOD_NAME_SEPA,
	PAYMENT_METHOD_NAME_SOFORT,
} from '../constants.js';

const upeMethods = {
	card: PAYMENT_METHOD_NAME_CARD,
	bancontact: PAYMENT_METHOD_NAME_BANCONTACT,
	au_becs_debit: PAYMENT_METHOD_NAME_BECS,
	eps: PAYMENT_METHOD_NAME_EPS,
	giropay: PAYMENT_METHOD_NAME_GIROPAY,
	ideal: PAYMENT_METHOD_NAME_IDEAL,
	p24: PAYMENT_METHOD_NAME_P24,
	sepa_debit: PAYMENT_METHOD_NAME_SEPA,
	sofort: PAYMENT_METHOD_NAME_SOFORT,
};

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
		edit: (
			<WCPayUPEFields
				paymentMethodId={ upeName }
				api={ api }
				testingInstructions={ upeConfig.testingInstructions }
			/>
		),
		savedTokenComponent: <SavedTokenHandler api={ api } />,
		canMakePayment: () => !! api.getStripe(),
		paymentMethodId: upeMethods[ upeName ],
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
