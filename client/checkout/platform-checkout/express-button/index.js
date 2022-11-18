/**
 * External dependencies
 */
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import { WoopayExpressCheckoutButton } from './woopay-express-checkout-button';

const renderPlatformCheckoutExpressButton = () => {
	const platformCheckoutContainer = document.getElementById(
		'wcpay-platform-checkout-button'
	);

	ReactDOM.render(
		<WoopayExpressCheckoutButton
			buttonSettings={ global.wcpayWooPayExpressParams.button }
		/>,
		platformCheckoutContainer
	);
};

window.addEventListener( 'load', () => {
	renderPlatformCheckoutExpressButton();
} );
