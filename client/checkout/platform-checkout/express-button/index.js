/* global jQuery */
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

// mount component again if parent fragment if re-rendered after ajax request by woocommerce core
// https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/legacy/js/frontend/checkout.js#L372
jQuery( function ( $ ) {
	$( document ).ajaxComplete( function () {
		renderPlatformCheckoutExpressButton();
	} );
} );
