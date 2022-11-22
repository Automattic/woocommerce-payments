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

const renderPlatformCheckoutExpressButton = () => {
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

	const platformCheckoutContainer = document.getElementById(
		'wcpay-platform-checkout-button'
	);

	if ( platformCheckoutContainer ) {
		ReactDOM.render(
			<WoopayExpressCheckoutButton
				buttonSettings={ getConfig( 'button' ) }
				api={ api }
			/>,
			platformCheckoutContainer
		);
	}
};

window.addEventListener( 'load', () => {
	renderPlatformCheckoutExpressButton();
} );
