/**
 * Internal dependencies
 */
import WooPayDirectCheckout from 'wcpay/checkout/woopay/direct-checkout/woopay-direct-checkout';

window.addEventListener( 'load', async () => {
	if ( ! WooPayDirectCheckout.isWooPayEnabled() ) {
		return;
	}

	WooPayDirectCheckout.init();

	const isThirdPartyCookieEnabled = await WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled();
	const checkoutElements = WooPayDirectCheckout.getCheckoutRedirectElements();
	if ( isThirdPartyCookieEnabled ) {
		if ( await WooPayDirectCheckout.isUserLoggedIn() ) {
			WooPayDirectCheckout.redirectToWooPay( checkoutElements, true );
		}

		return;
	}

	// Pass false to indicate we are not sure if the user is logged in or not.
	WooPayDirectCheckout.redirectToWooPay( checkoutElements, false );
} );
