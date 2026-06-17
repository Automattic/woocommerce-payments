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

if ( getUPEConfig( 'isExpressCheckoutInPaymentMethodsEnabled' ) ) {
	// `paymentMethodsConfig` is the server's source of truth for which methods belong in
	// the payment methods list - the same gating the shortcode checkout rows use. The
	// `isPaymentRequestEnabled`/`isAmazonPayEnabled` flags describe the standalone express
	// buttons instead (e.g. Amazon Pay's flag carries the wallet-sheet tax restriction,
	// which doesn't apply when the checkout form computes the totals).
	if ( enabledPaymentMethodsConfig?.apple_pay ) {
		registerPaymentMethod( makeDynamicPlaceOrderButton( api, 'applePay' ) );
	}
	if ( enabledPaymentMethodsConfig?.google_pay ) {
		registerPaymentMethod(
			makeDynamicPlaceOrderButton( api, 'googlePay' )
		);
	}
	if ( enabledPaymentMethodsConfig?.amazon_pay ) {
		registerPaymentMethod(
			makeDynamicPlaceOrderButton( api, 'amazonPay' )
		);
	}
} else {
	if ( getUPEConfig( 'isPaymentRequestEnabled' ) ) {
		registerExpressPaymentMethod(
			makeExpressCheckoutElement( api, 'applePay' )
		);
		registerExpressPaymentMethod(
			makeExpressCheckoutElement( api, 'googlePay' )
		);
	}

	if ( getUPEConfig( 'isAmazonPayEnabled' ) ) {
		registerExpressPaymentMethod(
			makeExpressCheckoutElement( api, 'amazonPay' )
		);
	}
}
