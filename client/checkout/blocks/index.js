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
import request from '../utils/request';
import enqueueFraudScripts from 'fraud-scripts';
import paymentRequestPaymentMethod from '../../payment-request/blocks';
import { handlePlatformCheckoutEmailInput } from '../platform-checkout/email-input-iframe';
import wooPayExpressCheckoutPaymentMethod from '../platform-checkout/express-button/woopay-express-checkout-payment-method';

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
	// see .wc-block-checkout__payment-method styles in blocks/style.scss
	label: (
		<>
			<span>
				{ __( 'Credit card', 'woocommerce-payments' ) }
				<img
					src={ getConfig( 'icon' ) }
					alt={ __( 'Credit card', 'woocommerce-payments' ) }
				/>
			</span>
		</>
	),
	ariaLabel: __( 'Credit card', 'woocommerce-payments' ),
	supports: {
		showSavedCards: getConfig( 'isSavedCardsEnabled' ) ?? false,
		showSaveOption:
			( getConfig( 'isSavedCardsEnabled' ) &&
				! getConfig( 'isPlatformCheckoutEnabled' ) ) ??
			false,
		features: getConfig( 'features' ),
	},
} );

registerExpressPaymentMethod( paymentRequestPaymentMethod( api ) );

if ( getConfig( 'isPlatformCheckoutEnabled' ) ) {
	// Call handlePlatformCheckoutEmailInput if platform checkout is enabled and this is the checkout page.
	if (
		document.querySelector( '[data-block-name="woocommerce/checkout"]' )
	) {
		handlePlatformCheckoutEmailInput( '#email', api, true );
	}
	if ( getConfig( 'isWoopayExpressCheckoutEnabled' ) ) {
		registerExpressPaymentMethod( wooPayExpressCheckoutPaymentMethod() );
	}
}

window.addEventListener( 'load', () => {
	enqueueFraudScripts( getConfig( 'fraudServices' ) );
} );
