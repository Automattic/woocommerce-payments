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
import WCPayAPI from 'wcpay/checkout/api';
import request from 'wcpay/checkout/utils/request';
import { isLinkEnabled } from 'wcpay/checkout/utils/upe';
import {
	makeExpressCheckoutElement,
	makeDynamicPlaceOrderButton,
} from './payment-methods';

const enabledPaymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );
const isStripeLinkEnabled = isLinkEnabled( enabledPaymentMethodsConfig );

const api = new WCPayAPI(
	{
		publishableKey: getUPEConfig( 'publishableKey' ),
		accountId: getUPEConfig( 'accountId' ),
		forceNetworkSavedCards: getUPEConfig( 'forceNetworkSavedCards' ),
		locale: getUPEConfig( 'locale' ),
		isStripeLinkEnabled,
	},
	request
);

if ( getUPEConfig( 'isPaymentRequestEnabled' ) ) {
	if ( getUPEConfig( 'isExpressCheckoutInPaymentMethodsEnabled' ) ) {
		registerPaymentMethod( makeDynamicPlaceOrderButton( api, 'applePay' ) );
		registerPaymentMethod(
			makeDynamicPlaceOrderButton( api, 'googlePay' )
		);
	} else {
		registerExpressPaymentMethod(
			makeExpressCheckoutElement( api, 'applePay' )
		);
		registerExpressPaymentMethod(
			makeExpressCheckoutElement( api, 'googlePay' )
		);
	}
}

if ( getUPEConfig( 'isAmazonPayEnabled' ) ) {
	if ( getUPEConfig( 'isExpressCheckoutInPaymentMethodsEnabled' ) ) {
		registerPaymentMethod(
			makeDynamicPlaceOrderButton( api, 'amazonPay' )
		);
	} else {
		registerExpressPaymentMethod(
			makeExpressCheckoutElement( api, 'amazonPay' )
		);
	}
}
