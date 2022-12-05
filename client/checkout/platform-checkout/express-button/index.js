/**
 * External dependencies
 */
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import { getWooPayExpressData } from './utils';
import { WoopayExpressCheckoutButton } from './woopay-express-checkout-button';
import WCPayAPI from '../../api';
import request from '../../utils/request';

const renderPlatformCheckoutExpressButton = () => {
	// Create an API object, which will be used throughout the checkout.
	const api = new WCPayAPI(
		{
			publishableKey: getWooPayExpressData( 'publishableKey' ),
			accountId: getWooPayExpressData( 'accountId' ),
			forceNetworkSavedCards: getWooPayExpressData(
				'forceNetworkSavedCards'
			),
			locale: getWooPayExpressData( 'locale' ),
		},
		request
	);

	const platformCheckoutContainer = document.getElementById(
		'wcpay-platform-checkout-button'
	);

	if ( platformCheckoutContainer ) {
		ReactDOM.render(
			<WoopayExpressCheckoutButton
				buttonSettings={ getWooPayExpressData( 'button' ) }
				api={ api }
			/>,
			platformCheckoutContainer
		);
	}
};

window.addEventListener( 'load', () => {
	renderPlatformCheckoutExpressButton();
} );
