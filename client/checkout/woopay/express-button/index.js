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
import '../../express-checkout-buttons.scss';

const oldWoopayContainers = [];

const renderWooPayExpressCheckoutButton = () => {
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

window.addEventListener( 'load', renderWooPayExpressCheckoutButton );

jQuery( ( $ ) => {
	$( document.body ).on( 'updated_cart_totals', () => {
		renderWooPayExpressCheckoutButton();
	} );
} );
