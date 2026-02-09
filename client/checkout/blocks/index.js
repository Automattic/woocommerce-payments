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
import { getUPEConfig, getConfig } from 'utils/checkout';
import { isLinkEnabled } from '../utils/upe';
import WCPayAPI from '../api';
import { SavedTokenHandler } from './saved-token-handler';
import PaymentMethodLabel from './payment-method-label';
import request from '../utils/request';
import enqueueFraudScripts from 'fraud-scripts';
import {
	expressCheckoutElementApplePay,
	expressCheckoutElementGooglePay,
	expressCheckoutElementAmazonPay,
} from 'wcpay/express-checkout/blocks';

import { getDeferredIntentCreationUPEFields } from './payment-elements';
import { handleWooPayEmailInput } from '../woopay/email-input-iframe';
import { recordUserEvent } from 'tracks';
import wooPayExpressCheckoutPaymentMethod from '../woopay/express-button/woopay-express-checkout-payment-method';
import { isPreviewing } from '../preview';
import '../utils/copy-test-number';

const enabledPaymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );
const isStripeLinkEnabled = isLinkEnabled( enabledPaymentMethodsConfig );

// Create an API object, which will be used throughout the checkout.
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

Object.entries( enabledPaymentMethodsConfig )
	.filter( ( [ upeName ] ) => upeName !== 'link' )
	.forEach( ( [ upeName, upeConfig ] ) => {
		// Label component renders the payment method title using the standard
		// PaymentMethodLabel from WooCommerce Blocks, with icons as a sibling
		// element for proper flexbox layout.
		const Label = ( props ) => (
			<PaymentMethodLabel
				{ ...props }
				title={ upeConfig.title }
				paymentMethodId={ upeName }
				icon={ upeConfig.icon }
				darkIcon={ upeConfig.darkIcon }
			/>
		);

		registerPaymentMethod( {
			name: upeConfig.gatewayId,
			content: getDeferredIntentCreationUPEFields(
				upeName,
				enabledPaymentMethodsConfig,
				api,
				upeConfig.testingInstructions
			),
			edit: getDeferredIntentCreationUPEFields(
				upeName,
				enabledPaymentMethodsConfig,
				api,
				upeConfig.testingInstructions
			),
			savedTokenComponent: <SavedTokenHandler api={ api } />,
			canMakePayment: ( cartData ) => {
				const billingCountry = cartData.billingAddress.country;
				const needsPayment = cartData.cart.cartNeedsPayment;
				const isRestrictedInAnyCountry = !! upeConfig.countries.length;
				const isAvailableInTheCountry =
					! isRestrictedInAnyCountry ||
					upeConfig.countries.includes( billingCountry );
				// We used to check if stripe was loaded with `getStripeForUPE`, but we can't guarantee it will be loaded synchronously.
				return needsPayment && isAvailableInTheCountry;
			},
			paymentMethodId: upeConfig.gatewayId,
			label: <Label />,
			ariaLabel: 'WooPayments',
			supports: {
				showSavedCards: getUPEConfig( 'isSavedCardsEnabled' ) ?? false,
				showSaveOption: upeConfig.showSaveOption ?? false,
				features: getUPEConfig( 'features' ),
			},
		} );
	} );

const addCheckoutTracking = () => {
	const placeOrderButton = document.getElementsByClassName(
		'wc-block-components-checkout-place-order-button'
	);
	if ( placeOrderButton.length ) {
		placeOrderButton[ 0 ].addEventListener( 'click', () => {
			const blocksCheckbox = document.getElementById(
				'radio-control-wc-payment-method-options-woocommerce_payments'
			);
			if ( ! blocksCheckbox?.checked ) {
				return;
			}

			recordUserEvent( 'checkout_place_order_button_click' );
		} );
	}
};

// Call handleWooPayEmailInput if woopay is enabled and this is the checkout page.
if ( getUPEConfig( 'isWooPayEnabled' ) ) {
	if (
		document.querySelector( '[data-block-name="woocommerce/checkout"]' ) &&
		getUPEConfig( 'isWooPayEmailInputEnabled' ) &&
		! isPreviewing()
	) {
		handleWooPayEmailInput( '#email', api, true );
	}

	if ( getUPEConfig( 'shouldShowWooPayButton' ) ) {
		registerExpressPaymentMethod( wooPayExpressCheckoutPaymentMethod() );
	}
}

if ( getUPEConfig( 'isPaymentRequestEnabled' ) ) {
	registerExpressPaymentMethod( expressCheckoutElementApplePay( api ) );
	registerExpressPaymentMethod( expressCheckoutElementGooglePay( api ) );
}

if ( getUPEConfig( 'isAmazonPayEnabled' ) ) {
	registerExpressPaymentMethod( expressCheckoutElementAmazonPay( api ) );
}
window.addEventListener( 'load', () => {
	enqueueFraudScripts( getUPEConfig( 'fraudServices' ) );
	addCheckoutTracking();
} );

// If multi-currency is enabled, add currency code to total amount in cart and checkout blocks.
if ( getConfig( 'isMultiCurrencyEnabled' ) ) {
	const { registerCheckoutFilters } = window.wc.blocksCheckout;

	const modifyTotalsPrice = ( defaultValue, extensions, args ) => {
		const { cart } = args;

		if ( cart?.cartTotals?.currency_code ) {
			return `<price/> ${ cart.cartTotals.currency_code }`;
		}

		return defaultValue;
	};

	registerCheckoutFilters( 'woocommerce-payments', {
		totalValue: modifyTotalsPrice,
	} );
}
