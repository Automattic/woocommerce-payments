/* global jQuery */
/**
 * External dependencies
 */
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import { getConfig } from 'utils/checkout';
import { WoopayExpressCheckoutButton } from './woopay-express-checkout-button';
import WCPayAPI from '../../api';
import request from '../../utils/request';
import { buildAjaxURL } from 'wcpay/utils/express-checkout';

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
			ReactDOM.unmountComponentAtNode( oldWoopayContainer );
		}

		oldWoopayContainers.push( woopayContainer );

		ReactDOM.render(
			<WoopayExpressCheckoutButton
				listenForCartChanges={ listenForCartChanges }
				buttonSettings={ getConfig( 'woopayButton' ) }
				api={ api }
				isProductPage={
					!! woopayContainer.getAttribute( 'data-product_page' )
				}
				emailSelector="#billing_email"
			/>,
			woopayContainer
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

	// On classic checkout, hide the WooPay button when meeting certain conditions.
	const handleWooPayExpressCheckoutButtonVisibility = async (
		_event,
		cart
	) => {
		// Hide WooPay button when cart total is 0 and do not need shipping,
		// and if the cart has no subscriptions or the recurring total value is 0.
		if (
			cart.total.amount === 0 &&
			! cart.needs_shipping &&
			( ! cart.cart_contains_subscription ||
				! cart.cart_subscriptions_renewal_need_payment )
		) {
			$( '#wcpay-woopay-button' ).hide();
			$( '#wcpay-express-checkout-button-separator' ).hide();
			return;
		}

		$( '#wcpay-woopay-button' ).show();
		$( '#wcpay-express-checkout-button-separator' ).show();
	};

	if ( getConfig( 'isPaymentRequestEnabled' ) ) {
		// Reuse express buttons ece_get_cart_details call to prevent calling two endpoints.
		$( document.body ).on(
			'updated_cart_details',
			handleWooPayExpressCheckoutButtonVisibility
		);

		return;
	}

	const getCartDetails = async () => {
		const cart = await request(
			buildAjaxURL( getConfig( 'wcAjaxUrl' ), 'woopay_get_cart_details' ),
			{
				_ajax_nonce: getConfig( 'woopayCartDetailsNonce' ),
			}
		);

		handleWooPayExpressCheckoutButtonVisibility( null, cart );
	};

	$( document.body ).on( 'updated_checkout', getCartDetails );

	$( document.body ).on( 'updated_cart_totals', getCartDetails );
} );

window.addEventListener(
	'load',
	renderWooPayExpressCheckoutButtonWithCallbacks
);
