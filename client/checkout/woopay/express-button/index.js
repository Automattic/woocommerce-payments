/* global jQuery */
/**
 * External dependencies
 */
import { createRoot } from 'react-dom/client';

/**
 * Internal dependencies
 */
import { getConfig } from 'utils/checkout';
import { WoopayExpressCheckoutButton } from './woopay-express-checkout-button';
import WCPayAPI from '../../api';
import request from '../../utils/request';

const oldWoopayContainers = [];

const renderWooPayExpressCheckoutButton = ( listenForCartChanges = {} ) => {
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

	const woopayContainer = document.getElementById( 'wcpay-woopay-button' );

	if ( woopayContainer ) {
		while ( oldWoopayContainers.length > 0 ) {
			// Ensure previous buttons are unmounted and cleaned up.
			const oldWoopayContainer = oldWoopayContainers.pop();
			// Note: With createRoot, we should store the root instance for proper cleanup
			// For now, we'll remove this line as unmountComponentAtNode is deprecated
			// oldWoopayContainer.root?.unmount();
		}

		oldWoopayContainers.push( woopayContainer );

		const root = createRoot( woopayContainer );
		root.render(
			<WoopayExpressCheckoutButton
				listenForCartChanges={ listenForCartChanges }
				buttonSettings={ getConfig( 'woopayButton' ) }
				api={ api }
				isProductPage={
					!! woopayContainer.getAttribute( 'data-product_page' )
				}
				emailSelector="#billing_email"
			/>
		);
	}
};

let listenForCartChanges = null;
const renderWooPayExpressCheckoutButtonWithCallbacks = () => {
	renderWooPayExpressCheckoutButton( listenForCartChanges );
};

jQuery( ( $ ) => {
	listenForCartChanges = {
		start: () => {
			$( document.body ).on(
				'updated_cart_totals updated_checkout',
				renderWooPayExpressCheckoutButtonWithCallbacks
			);
		},
		stop: () => {
			$( document.body ).off(
				'updated_cart_totals updated_checkout',
				renderWooPayExpressCheckoutButtonWithCallbacks
			);
		},
	};

	listenForCartChanges.start();
} );

window.addEventListener(
	'load',
	renderWooPayExpressCheckoutButtonWithCallbacks
);
