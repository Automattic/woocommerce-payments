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
import { maybePersistAdminWoopayAppearance } from '../appearance/persist-admin';
import {
	getCachedPreferredCard,
	setCachedPreferredCard,
	isSameCard,
} from './preferred-card-utils';
import { fetchPreferredCard as fetchPreferredCardFromConnect } from './preferred-card-fetch';

const oldWoopayRoots = [];

const renderWooPayExpressCheckoutButton = (
	listenForCartChanges = {},
	preferredCard = null
) => {
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
		while ( oldWoopayRoots.length > 0 ) {
			// Ensure previous buttons are unmounted and cleaned up.
			const oldWoopayRoot = oldWoopayRoots.pop();
			oldWoopayRoot.unmount();
		}

		const root = createRoot( woopayContainer );
		oldWoopayRoots.push( root );

		root.render(
			<WoopayExpressCheckoutButton
				listenForCartChanges={ listenForCartChanges }
				buttonSettings={ getConfig( 'woopayButton' ) }
				api={ api }
				isProductPage={
					!! woopayContainer.getAttribute( 'data-product_page' )
				}
				emailSelector="#billing_email"
				preferredCard={ preferredCard }
			/>
		);
	}
};

let listenForCartChanges = null;
let currentPreferredCard = getCachedPreferredCard();

const renderWooPayExpressCheckoutButtonWithCallbacks = () => {
	renderWooPayExpressCheckoutButton(
		listenForCartChanges,
		currentPreferredCard
	);
};

/**
 * Queries the WooPay Connect iframe for the user's preferred payment method
 * and re-renders the button if card data is available or has changed.
 */
const fetchPreferredCard = async () => {
	try {
		const card = await fetchPreferredCardFromConnect();

		setCachedPreferredCard( card );

		if ( ! isSameCard( card, currentPreferredCard ) ) {
			currentPreferredCard = card;
			renderWooPayExpressCheckoutButtonWithCallbacks();
		}
	} catch {
		// Connect iframe unavailable — keep current state.
	}
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

window.addEventListener( 'load', () => {
	renderWooPayExpressCheckoutButtonWithCallbacks();

	// Query the Connect iframe for preferred card data to personalize the button.
	fetchPreferredCard();

	// When the checkout is loaded inside the Customizer preview, capture
	// the live DOM appearance and persist it via the admin endpoint.
	maybePersistAdminWoopayAppearance();
} );
