/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

// Handled as an external dependency: see '/webpack.config.js:83'
import {
	registerPaymentMethod,
	registerExpressPaymentMethod,
	registerBlockComponent
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
import request from './request.js';
import enqueueFraudScripts from 'fraud-scripts';
import paymentRequestPaymentMethod from '../../payment-request/blocks';
import enableStripeLinkPaymentMethod from '../stripe-link'
import {lazy} from "@wordpress/element";

const paymentMethodsConfig = getConfig( 'paymentMethodsConfig' );
const isStripeLinkEnabled =
	paymentMethodsConfig.link !== undefined &&
	paymentMethodsConfig.card !== undefined;

// Create an API object, which will be used throughout the checkout.
const api = new WCPayAPI(
	{
		publishableKey: getConfig( 'publishableKey' ),
		accountId: getConfig( 'accountId' ),
		forceNetworkSavedCards: getConfig( 'forceNetworkSavedCards' ),
		locale: getConfig( 'locale' ),
		isUPEEnabled: getConfig( 'isUPEEnabled' ),
		isStripeLinkEnabled
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
	ariaLabel: __( 'WooCommerce Payments', 'woocommerce-payments' ),
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


registerBlockComponent( {
	blockName: 'woocommerce/checkout-stripe-link',
	component: lazy( () =>
		import(
			/* webpackChunkName: "product-category-list" */ '../stripe-link'
			)
	),
} );

window.addEventListener( 'load', () => {
	enqueueFraudScripts( getConfig( 'fraudServices' ) );
	if ( isStripeLinkEnabled ) {
		console.log(getConfig( 'upePaymentIntentData' ));
		// enableStripeLinkPaymentMethod( {
		// 	api: api,
		// 	emailId: 'email',
		// 	complete_shipping: true,
		// 	shipping_fields: {
		// 		address_1: 'shipping-address_1',
		// 		address_2: 'shipping-address_2',
		// 		city: 'shipping-city',
		// 		state: 'components-form-token-input-1',
		// 		postal_code: 'shipping-postcode',
		// 		country: 'components-form-token-input-0'
		// 	},
		// 	complete_billing: false,
		// } );
	}
} );
