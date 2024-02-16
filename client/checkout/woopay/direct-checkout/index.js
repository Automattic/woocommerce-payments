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
			WooPayDirectCheckout.redirectToWooPay( checkoutElements, false );
		}

		return;
	}

	// Pass true to append '&woopay_checkout_redirect=1' and let WooPay decide the checkout flow
	WooPayDirectCheckout.redirectToWooPay( checkoutElements, true );
} );
