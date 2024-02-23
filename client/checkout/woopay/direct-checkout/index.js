/* global jQuery */
/**
 * Internal dependencies
 */
import WooPayDirectCheckout from 'wcpay/checkout/woopay/direct-checkout/woopay-direct-checkout';

window.addEventListener( 'load', async () => {
	if ( ! WooPayDirectCheckout.isWooPayDirectCheckoutEnabled() ) {
		return;
	}

	WooPayDirectCheckout.init();
	WooPayDirectCheckout.maybePrefetchWooPaySession();

	const isThirdPartyCookieEnabled = await WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled();
	const checkoutElements = WooPayDirectCheckout.getCheckoutRedirectElements();
	if ( isThirdPartyCookieEnabled ) {
		if ( await WooPayDirectCheckout.isUserLoggedIn() ) {
			WooPayDirectCheckout.redirectToWooPaySession( checkoutElements );
		}

		return;
	}

	WooPayDirectCheckout.redirectToWooPay( checkoutElements );
} );

jQuery( ( $ ) => {
	$( document.body ).on( 'updated_cart_totals', async () => {
		if ( ! WooPayDirectCheckout.isWooPayDirectCheckoutEnabled() ) {
			return;
		}

		WooPayDirectCheckout.maybePrefetchWooPaySession();

		// When "updated_cart_totals" is triggered, the classic 'Proceed to Checkout' button is
		// re-rendered. So, the click-event listener needs to be re-attached to the new button.
		const checkoutButton = WooPayDirectCheckout.getClassicProceedToCheckoutButton();
		const isThirdPartyCookieEnabled = await WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled();
		if ( isThirdPartyCookieEnabled ) {
			if ( await WooPayDirectCheckout.isUserLoggedIn() ) {
				WooPayDirectCheckout.redirectToWooPaySession( [
					checkoutButton,
				] );
			}

			return;
		}

		WooPayDirectCheckout.redirectToWooPay( [ checkoutButton ] );
	} );
} );
