/**
 * Internal dependencies
 */
import WooPayDirectCheckout from 'wcpay/checkout/woopay/direct-checkout/woopay-direct-checkout';

window.addEventListener( 'load', async () => {
	if ( ! WooPayDirectCheckout.isWooPayEnabled() ) {
		return;
	}

	WooPayDirectCheckout.init();

	const checkoutElements = WooPayDirectCheckout.getCheckoutRedirectElements();
	const isThirdPartyCookieEnabled = await WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled();
	if ( isThirdPartyCookieEnabled ) {
		if ( await WooPayDirectCheckout.isUserLoggedIn() ) {
			WooPayDirectCheckout.redirectToWooPaySession( checkoutElements );
		}

		return;
	}

	WooPayDirectCheckout.redirectToWooPay( checkoutElements );
} );