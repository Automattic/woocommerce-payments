/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
// Handled as an external dependency: see '/webpack.config.js:83'
import {
	registerPaymentMethod,
	registerExpressPaymentMethod,
	// eslint-disable-next-line import/no-unresolved
} from '@woocommerce/blocks-registry';
import { subscribe, select } from '@wordpress/data';
// eslint-disable-next-line import/no-unresolved
import { paymentStore } from '@woocommerce/block-data';
// eslint-disable-next-line import/no-unresolved
import { registerCheckoutFilters } from '@woocommerce/blocks-checkout';

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
} from '../../express-checkout/blocks';
import {
	tokenizedExpressCheckoutElementApplePay,
	tokenizedExpressCheckoutElementGooglePay,
} from 'wcpay/tokenized-express-checkout/blocks';

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
					countries={ upeConfig.countries }
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
	if ( getUPEConfig( 'isTokenizedCartEceEnabled' ) ) {
		registerExpressPaymentMethod(
			tokenizedExpressCheckoutElementApplePay( api )
		);
		registerExpressPaymentMethod(
			tokenizedExpressCheckoutElementGooglePay( api )
		);
	} else {
		registerExpressPaymentMethod( expressCheckoutElementApplePay( api ) );
		registerExpressPaymentMethod( expressCheckoutElementGooglePay( api ) );
	}
}
window.addEventListener( 'load', () => {
	enqueueFraudScripts( getUPEConfig( 'fraudServices' ) );
	addCheckoutTracking();
} );

// If multi-currency is enabled, add currency code to total amount in cart and checkout blocks.
if ( getConfig( 'isMultiCurrencyEnabled' ) ) {
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

document.addEventListener( 'DOMContentLoaded', () => {
	let lastActivePaymentMethod = null;
	let lastActiveSavedToken = null;

	// this operation registers a new filter to the same namespace each time the active payment method changes.
	// as a consequence, the "place order" button re-renders.
	const overridePlaceOrderButtonLabelFilter = ( newLabel ) => {
		registerCheckoutFilters(
			'woocommerce-payments/place-order-button-label',
			{
				placeOrderButtonLabel: ( defaultLabel ) => {
					return newLabel || defaultLabel;
				},
			}
		);
	};

	subscribe( () => {
		const method = select( paymentStore ).getActivePaymentMethod();
		const token = select( paymentStore ).getActiveSavedToken();

		// slight optimization to ensure that we don't execute the logic below too often.
		if (
			method === lastActivePaymentMethod &&
			token === lastActiveSavedToken
		) {
			return;
		}

		lastActivePaymentMethod = method;
		lastActiveSavedToken = token;

		// if there is no method or the selected method is not a WooPayments one, don't override the "Place order" button label..
		if ( ! method || ! method.includes( 'woocommerce_payments' ) ) {
			overridePlaceOrderButtonLabelFilter();
			return;
		}

		// if a saved token is selected it could be a WooPayments token - don't override the "Place order" button label, in that case.
		if ( lastActiveSavedToken ) {
			overridePlaceOrderButtonLabelFilter();
			return;
		}

		// 'woocommerce_payments_affirm' => 'affirm'
		// 'woocommerce_payments_p24' -> 'p24'
		// 'woocommerce_payments' -> ''
		let paymentMethodId = method
			// non-card elements are prefixed with `woocommerce_payments_*`
			.replace( 'woocommerce_payments_', '' )
			// the card element is just called `woocommerce_payments` - we need to account for variation in the name
			.replace( 'woocommerce_payments', '' );
		paymentMethodId = paymentMethodId || 'card';

		const paymentMethodTitle =
			enabledPaymentMethodsConfig[ paymentMethodId ]?.title;

		overridePlaceOrderButtonLabelFilter(
			paymentMethodTitle
				? sprintf(
						__( 'Pay with %s', 'woocommerce-payments' ),
						paymentMethodTitle
				  )
				: undefined
		);
	} );
} );
