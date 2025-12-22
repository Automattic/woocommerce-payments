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
} from 'wcpay/express-checkout/blocks';

import { getDeferredIntentCreationUPEFields } from './payment-elements';
import { recordUserEvent } from 'tracks';
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
			// see .wc-block-checkout__payment-method styles in blocks/style.scss
			label: (
				<PaymentMethodLabel
					api={ api }
					title={ upeConfig.title }
					iconLight={ upeConfig.icon }
					iconDark={ upeConfig.darkIcon }
					upeName={ upeName }
				/>
			),
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

// Dynamically load and initialize WooPay functionality if enabled.
if ( getUPEConfig( 'isWooPayEnabled' ) ) {
	// Email input handling
	if (
		document.querySelector( '[data-block-name="woocommerce/checkout"]' ) &&
		getUPEConfig( 'isWooPayEmailInputEnabled' ) &&
		! isPreviewing()
	) {
		import(
			/* webpackChunkName: "woopay-email-input" */ '../woopay/email-input-iframe'
		).then( ( { handleWooPayEmailInput } ) => {
			handleWooPayEmailInput( '#email', api, true );
		} );
	}

	// Express button registration
	if ( getUPEConfig( 'shouldShowWooPayButton' ) ) {
		import(
			/* webpackChunkName: "woopay-express-checkout-method" */ '../woopay/express-button/woopay-express-checkout-payment-method'
		).then( ( { default: wooPayExpressCheckoutPaymentMethod } ) => {
			registerExpressPaymentMethod(
				wooPayExpressCheckoutPaymentMethod()
			);
		} );
	}
}

if ( getUPEConfig( 'isPaymentRequestEnabled' ) ) {
	registerExpressPaymentMethod( expressCheckoutElementApplePay( api ) );
	registerExpressPaymentMethod( expressCheckoutElementGooglePay( api ) );
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
