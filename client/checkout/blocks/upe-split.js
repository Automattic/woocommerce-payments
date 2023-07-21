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
import { getUPEConfig } from 'utils/checkout';
import { isLinkEnabled } from '../utils/upe';
import WCPayAPI from './../api';
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
	PAYMENT_METHOD_NAME_AFFIRM,
	PAYMENT_METHOD_NAME_AFTERPAY,
} from '../constants.js';
import { getSplitUPEFields } from './upe-split-fields';
import { getDeferredIntentCreationUPEFields } from './upe-deferred-intent-creation/payment-elements';

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
	affirm: PAYMENT_METHOD_NAME_AFFIRM,
	afterpay_clearpay: PAYMENT_METHOD_NAME_AFTERPAY,
};

const enabledPaymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );
const isStripeLinkEnabled = isLinkEnabled( enabledPaymentMethodsConfig );

// Create an API object, which will be used throughout the checkout.
const api = new WCPayAPI(
	{
		publishableKey: getUPEConfig( 'publishableKey' ),
		accountId: getUPEConfig( 'accountId' ),
		forceNetworkSavedCards: getUPEConfig( 'forceNetworkSavedCards' ),
		locale: getUPEConfig( 'locale' ),
		isUPEEnabled: getUPEConfig( 'isUPEEnabled' ),
		isUPESplitEnabled: getUPEConfig( 'isUPESplitEnabled' ),
		isUPEDeferredEnabled: getUPEConfig( 'isUPEDeferredEnabled' ),
		isStripeLinkEnabled,
	},
	request
);
const getUPEFields = getUPEConfig( 'isUPEDeferredEnabled' )
	? getDeferredIntentCreationUPEFields
	: getSplitUPEFields;
Object.entries( enabledPaymentMethodsConfig )
	.filter( ( [ upeName ] ) => 'link' !== upeName )
	.forEach( ( [ upeName, upeConfig ] ) => {
		registerPaymentMethod( {
			name: upeMethods[ upeName ],
			content: getUPEFields(
				upeName,
				upeMethods,
				api,
				upeConfig.testingInstructions
			),
			edit: getUPEFields(
				upeName,
				upeMethods,
				api,
				upeConfig.testingInstructions
			),
			savedTokenComponent: <SavedTokenHandler api={ api } />,
			canMakePayment: ( cartData ) => {
				const billingCountry = cartData.billingAddress.country;
				const isRestrictedInAnyCountry = !! upeConfig.countries.length;
				const isAvailableInTheCountry =
					! isRestrictedInAnyCountry ||
					upeConfig.countries.includes( billingCountry );
				return (
					isAvailableInTheCountry && !! api.getStripeForUPE( upeName )
				);
			},
			paymentMethodId: upeMethods[ upeName ],
			// see .wc-block-checkout__payment-method styles in blocks/style.scss
			label: (
				<>
					<span>
						{ upeConfig.title }
						<img src={ upeConfig.icon } alt={ upeConfig.title } />
					</span>
				</>
			),
			ariaLabel: 'WooPayments',
			supports: {
				showSavedCards: getUPEConfig( 'isSavedCardsEnabled' ) ?? false,
				showSaveOption: upeConfig.showSaveOption ?? false,
				features: getUPEConfig( 'features' ),
			},
		} );
	} );

registerExpressPaymentMethod( paymentRequestPaymentMethod( api ) );
window.addEventListener( 'load', () => {
	enqueueFraudScripts( getUPEConfig( 'fraudServices' ) );
} );
